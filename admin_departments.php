<?php
// admin_departments.php
require_once 'auth.php';
require_once 'config.php';
requireAdmin();

$database = new Database();
$db = $database->getConnection();

$success = '';
$error = '';
$action = $_GET['action'] ?? 'list';
$edit_department = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $db->beginTransaction();
        
        if (isset($_POST['create_department'])) {
            // Create new department
            $query = "INSERT INTO departments (department_name, extension, building, room_number, created_by) 
                      VALUES (:dept_name, :extension, :building, :room_number, :created_by)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':dept_name', $_POST['department_name']);
            $stmt->bindParam(':extension', $_POST['extension']);
            $stmt->bindParam(':building', $_POST['building']);
            $stmt->bindParam(':room_number', $_POST['room_number']);
            $stmt->bindParam(':created_by', $_SESSION['user_id']);
            $stmt->execute();
            
            $success = "Department created successfully!";
            
        } elseif (isset($_POST['update_department'])) {
            // Update existing department
            $query = "UPDATE departments SET 
                      department_name = :dept_name,
                      extension = :extension,
                      building = :building,
                      room_number = :room_number,
                      updated_at = CURRENT_TIMESTAMP
                      WHERE id = :dept_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':dept_name', $_POST['department_name']);
            $stmt->bindParam(':extension', $_POST['extension']);
            $stmt->bindParam(':building', $_POST['building']);
            $stmt->bindParam(':room_number', $_POST['room_number']);
            $stmt->bindParam(':dept_id', $_POST['department_id']);
            $stmt->execute();
            
            $success = "Department updated successfully!";
        }
        
        $db->commit();
        
    } catch (Exception $e) {
        $db->rollback();
        $error = "Error: " . $e->getMessage();
    }
}

// Handle delete requests
if (isset($_GET['delete_id'])) {
    try {
        $db->beginTransaction();
        
        // Check if department has staff
        $check_query = "SELECT COUNT(*) as staff_count FROM staff WHERE department_id = :dept_id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':dept_id', $_GET['delete_id']);
        $check_stmt->execute();
        $staff_count = $check_stmt->fetch(PDO::FETCH_ASSOC)['staff_count'];
        
        if ($staff_count > 0) {
            $error = "Cannot delete department. It has $staff_count staff member(s). Please move or delete staff first.";
        } else {
            $delete_query = "DELETE FROM departments WHERE id = :dept_id";
            $delete_stmt = $db->prepare($delete_query);
            $delete_stmt->bindParam(':dept_id', $_GET['delete_id']);
            $delete_stmt->execute();
            
            $success = "Department deleted successfully!";
        }
        
        $db->commit();
        
    } catch (Exception $e) {
        $db->rollback();
        $error = "Error deleting department: " . $e->getMessage();
    }
}

// Get department for editing
if ($action === 'edit' && isset($_GET['id'])) {
    $edit_query = "SELECT * FROM departments WHERE id = :id";
    $edit_stmt = $db->prepare($edit_query);
    $edit_stmt->bindParam(':id', $_GET['id']);
    $edit_stmt->execute();
    $edit_department = $edit_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$edit_department) {
        $error = "Department not found.";
        $action = 'list';
    }
}

// Get all departments with stats
$departments_query = "SELECT d.*, 
                      COUNT(s.id) as staff_count,
                      u.username as created_by_username
                      FROM departments d 
                      LEFT JOIN staff s ON d.id = s.department_id 
                      LEFT JOIN users u ON d.created_by = u.id
                      GROUP BY d.id 
                      ORDER BY d.department_name";
$departments_stmt = $db->prepare($departments_query);
$departments_stmt->execute();
$departments = $departments_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php
$page_title = 'Manage Departments - Admin Panel';
$current_page = 'departments';
include 'header.php';
?>
        <div class="row">
            <div class="col-12">
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($action === 'add' || $action === 'edit'): ?>
                    <!-- Add/Edit Department Form -->
                    <div class="card">
                        <div class="card-header">
                            <h5>
                                <i class="fas fa-<?php echo $action === 'add' ? 'plus' : 'edit'; ?>"></i>
                                <?php echo $action === 'add' ? 'Add New' : 'Edit'; ?> Department
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <?php if ($action === 'edit'): ?>
                                    <input type="hidden" name="department_id" value="<?php echo $edit_department['id']; ?>">
                                <?php endif; ?>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Department Name *</label>
                                            <input type="text" class="form-control" name="department_name" 
                                                   value="<?php echo $edit_department ? htmlspecialchars($edit_department['department_name']) : ''; ?>" 
                                                   required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Extension</label>
                                            <input type="text" class="form-control extension-input" name="extension" 
                                                   value="<?php echo $edit_department ? htmlspecialchars($edit_department['extension']) : ''; ?>" 
                                                   placeholder="3402" maxlength="9">
                                            <small class="form-text text-muted">Format: 4 digits or 4 digits/4 digits (e.g., 3402 or 3402/3403)</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Building</label>
                                            <input type="text" class="form-control" name="building" 
                                                   value="<?php echo $edit_department ? htmlspecialchars($edit_department['building']) : ''; ?>" 
                                                   placeholder="Administration Building">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Room Number</label>
                                            <input type="text" class="form-control" name="room_number" 
                                                   value="<?php echo $edit_department ? htmlspecialchars($edit_department['room_number']) : ''; ?>" 
                                                   placeholder="A101">
                                        </div>
                                    </div>
                                </div>

                                <div class="text-center">
                                    <button type="submit" name="<?php echo $action === 'add' ? 'create_department' : 'update_department'; ?>" class="btn btn-success btn-lg">
                                        <i class="fas fa-save"></i> 
                                        <?php echo $action === 'add' ? 'Create' : 'Update'; ?> Department
                                    </button>
                                    <a href="admin_departments.php" class="btn btn-secondary btn-lg ms-2">Cancel</a>
                                </div>
                            </form>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- Department List -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5><i class="fas fa-building"></i> All Departments</h5>
                            <a href="admin_departments.php?action=add" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add Department
                            </a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($departments)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-building fa-3x text-muted mb-3"></i>
                                    <h5>No Departments Found</h5>
                                    <p class="text-muted">Create the first department to get started.</p>
                                    <a href="admin_departments.php?action=add" class="btn btn-primary">Add Department</a>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>Department Name</th>
                                                <th>Extension</th>
                                                <th>Location</th>
                                                <th>Staff Count</th>
                                                <th>Created By</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($departments as $dept): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($dept['department_name']); ?></strong>
                                                </td>
                                                <td><?php echo htmlspecialchars($dept['extension'] ?: 'N/A'); ?></td>
                                                <td>
                                                    <?php if ($dept['building'] || $dept['room_number']): ?>
                                                        <?php echo htmlspecialchars($dept['building']); ?>
                                                        <?php if ($dept['room_number']): ?>
                                                            <br><small>Room <?php echo htmlspecialchars($dept['room_number']); ?></small>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        N/A
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo $dept['staff_count']; ?></span>
                                                </td>
                                                <td><?php echo htmlspecialchars($dept['created_by_username'] ?: 'Unknown'); ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="admin_departments.php?action=edit&id=<?php echo $dept['id']; ?>" class="btn btn-sm btn-warning">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="admin_staff.php?department_id=<?php echo $dept['id']; ?>" class="btn btn-sm btn-info">
                                                            <i class="fas fa-users"></i>
                                                        </a>
                                                        <?php if ($dept['staff_count'] == 0): ?>
                                                            <button class="btn btn-sm btn-danger" onclick="deleteDepartment(<?php echo $dept['id']; ?>, '<?php echo htmlspecialchars($dept['department_name']); ?>')">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        <?php else: ?>
                                                            <button class="btn btn-sm btn-secondary" disabled title="Cannot delete - has staff members">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
  <?php
$additional_scripts = '<script>
function formatExtension(input) {
    let value = input.value.replace(/[^0-9\/]/g, "");
    
    // Allow 4 digits or 4 digits/4 digits format
    if (value.length <= 4) {
        input.value = value;
    } else if (value.length > 4 && value.indexOf("/") === -1) {
        // Auto-add slash after 4 digits
        input.value = value.substring(0, 4) + "/" + value.substring(4, 8);
    } else {
        // Ensure proper format with slash
        let parts = value.split("/");
        if (parts.length === 2) {
            input.value = parts[0].substring(0, 4) + "/" + parts[1].substring(0, 4);
        }
    }
}

function deleteDepartment(id, name) {
    if (confirm(`Are you sure you want to delete the department "${name}"?\\n\\nThis action cannot be undone.`)) {
        window.location.href = `admin_departments.php?delete_id=${id}`;
    }
}

// Initialize extension formatting on page load
document.addEventListener("DOMContentLoaded", function() {
    const extensionInputs = document.querySelectorAll(".extension-input");
    extensionInputs.forEach(function(input) {
        input.addEventListener("input", function(e) {
            formatExtension(e.target);
        });
    });
});
</script>';

include 'footer.php';
?>