<?php
// admin_staff.php
require_once 'auth.php';
require_once 'config.php';
requireAdmin();

$database = new Database();
$db = $database->getConnection();

$success = '';
$error = '';
$action = $_GET['action'] ?? 'list';
$department_filter = $_GET['department_id'] ?? '';
$edit_staff = null;

// Get all departments for dropdown
$departments_query = "SELECT * FROM departments ORDER BY department_name";
$departments_stmt = $db->prepare($departments_query);
$departments_stmt->execute();
$departments = $departments_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $db->beginTransaction();
        
        if (isset($_POST['create_staff'])) {
            // Create new staff member
            $query = "INSERT INTO staff (user_id, department_id, name, title, extension, room_number) 
                      VALUES (:user_id, :department_id, :name, :title, :extension, :room_number)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->bindParam(':department_id', $_POST['department_id']);
            $stmt->bindParam(':name', $_POST['name']);
            $stmt->bindParam(':title', $_POST['title']);
            $stmt->bindParam(':extension', $_POST['extension']);
            $stmt->bindParam(':room_number', $_POST['room_number']);
            $stmt->execute();
            
            $success = "Staff member created successfully!";
            
        } elseif (isset($_POST['update_staff'])) {
            // Update existing staff member
            $query = "UPDATE staff SET 
                      department_id = :department_id,
                      name = :name,
                      title = :title,
                      extension = :extension,
                      room_number = :room_number,
                      updated_at = CURRENT_TIMESTAMP
                      WHERE id = :staff_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':department_id', $_POST['department_id']);
            $stmt->bindParam(':name', $_POST['name']);
            $stmt->bindParam(':title', $_POST['title']);
            $stmt->bindParam(':extension', $_POST['extension']);
            $stmt->bindParam(':room_number', $_POST['room_number']);
            $stmt->bindParam(':staff_id', $_POST['staff_id']);
            $stmt->execute();
            
            $success = "Staff member updated successfully!";
            
        } elseif (isset($_POST['bulk_delete_staff'])) {
            // Handle bulk delete
            if (!empty($_POST['selected_staff'])) {
                $selected_ids = $_POST['selected_staff'];
                $placeholders = str_repeat('?,', count($selected_ids) - 1) . '?';
                
                $delete_query = "DELETE FROM staff WHERE id IN ($placeholders)";
                $delete_stmt = $db->prepare($delete_query);
                $delete_stmt->execute($selected_ids);
                
                $deleted_count = $delete_stmt->rowCount();
                $success = "Successfully deleted $deleted_count staff member(s)!";
            } else {
                $error = "Please select at least one staff member to delete.";
            }
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
        $delete_query = "DELETE FROM staff WHERE id = :staff_id";
        $delete_stmt = $db->prepare($delete_query);
        $delete_stmt->bindParam(':staff_id', $_GET['delete_id']);
        $delete_stmt->execute();
        
        $success = "Staff member deleted successfully!";
        
    } catch (Exception $e) {
        $error = "Error deleting staff member: " . $e->getMessage();
    }
}

// Get staff member for editing
if ($action === 'edit' && isset($_GET['id'])) {
    $edit_query = "SELECT s.*, d.department_name FROM staff s 
                   JOIN departments d ON s.department_id = d.id 
                   WHERE s.id = :id";
    $edit_stmt = $db->prepare($edit_query);
    $edit_stmt->bindParam(':id', $_GET['id']);
    $edit_stmt->execute();
    $edit_staff = $edit_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$edit_staff) {
        $error = "Staff member not found.";
        $action = 'list';
    }
}

// Build staff query with optional department filter
$staff_query = "SELECT s.*, d.department_name, u.username as added_by_username
                FROM staff s 
                JOIN departments d ON s.department_id = d.id 
                LEFT JOIN users u ON s.user_id = u.id";

$query_params = [];
if ($department_filter) {
    $staff_query .= " WHERE s.department_id = :department_id";
    $query_params[':department_id'] = $department_filter;
}

$staff_query .= " ORDER BY d.department_name, s.name";

$staff_stmt = $db->prepare($staff_query);
foreach ($query_params as $key => $value) {
    $staff_stmt->bindValue($key, $value);
}
$staff_stmt->execute();
$staff_list = $staff_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php
$page_title = 'Manage All Staff - Admin Panel';
$current_page = 'staff';
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
                    <!-- Add/Edit Staff Form -->
                    <div class="card">
                        <div class="card-header">
                            <h5>
                                <i class="fas fa-<?php echo $action === 'add' ? 'user-plus' : 'user-edit'; ?>"></i>
                                <?php echo $action === 'add' ? 'Add New' : 'Edit'; ?> Staff Member
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <?php if ($action === 'edit'): ?>
                                    <input type="hidden" name="staff_id" value="<?php echo $edit_staff['id']; ?>">
                                <?php endif; ?>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Department *</label>
                                            <select class="form-control" name="department_id" required>
                                                <option value="">Select Department</option>
                                                <?php foreach ($departments as $dept): ?>
                                                    <option value="<?php echo $dept['id']; ?>" 
                                                            <?php echo ($edit_staff && $edit_staff['department_id'] == $dept['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($dept['department_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Name *</label>
                                            <input type="text" class="form-control" name="name" 
                                                   value="<?php echo $edit_staff ? htmlspecialchars($edit_staff['name']) : ''; ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="mb-3">
                                            <label class="form-label">Title *</label>
                                            <input type="text" class="form-control" name="title" 
                                                   value="<?php echo $edit_staff ? htmlspecialchars($edit_staff['title']) : ''; ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Extension</label>
                                            <input type="text" class="form-control extension-input" name="extension" 
                                                   value="<?php echo $edit_staff ? htmlspecialchars($edit_staff['extension']) : ''; ?>" 
                                                   placeholder="3402" maxlength="9">
                                            <small class="form-text text-muted">Format: 4 digits or 4 digits/4 digits (e.g., 3402 or 3402/3403)</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Room Number</label>
                                            <input type="text" class="form-control" name="room_number" 
                                                   value="<?php echo $edit_staff ? htmlspecialchars($edit_staff['room_number']) : ''; ?>" 
                                                   placeholder="A101">
                                        </div>
                                    </div>
                                </div>

                                <div class="text-center">
                                    <button type="submit" name="<?php echo $action === 'add' ? 'create_staff' : 'update_staff'; ?>" class="btn btn-success btn-lg">
                                        <i class="fas fa-save"></i> 
                                        <?php echo $action === 'add' ? 'Create' : 'Update'; ?> Staff Member
                                    </button>
                                    <a href="admin_staff.php" class="btn btn-secondary btn-lg ms-2">Cancel</a>
                                </div>
                            </form>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- Staff List -->
                    <div class="card">
                        <div class="card-header">
                            <div class="row align-items-center">
                                <div class="col-md-4">
                                    <h5><i class="fas fa-users"></i> All Staff Members</h5>
                                </div>
                                <div class="col-md-4">
                                    <select class="form-select" onchange="filterByDepartment(this.value)">
                                        <option value="">All Departments</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo $dept['id']; ?>" 
                                                    <?php echo ($department_filter == $dept['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($dept['department_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4 text-end">
                                    <a href="admin_staff.php?action=add" class="btn btn-primary">
                                        <i class="fas fa-user-plus"></i> Add Staff
                                    </a>
                                    <a href="admin_export.php" class="btn btn-success">
                                        <i class="fas fa-download"></i> Export All
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (empty($staff_list)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                    <h5>No Staff Members Found</h5>
                                    <p class="text-muted">
                                        <?php if ($department_filter): ?>
                                            No staff members found in the selected department.
                                        <?php else: ?>
                                            No staff members have been added yet.
                                        <?php endif; ?>
                                    </p>
                                    <a href="admin_staff.php?action=add" class="btn btn-primary">Add First Staff Member</a>
                                </div>
                            <?php else: ?>
                                <form method="POST" id="bulkDeleteForm">
                                    <!-- Bulk Actions Bar -->
                                    <div class="row mb-3" id="bulkActionsBar" style="display: none;">
                                        <div class="col-12">
                                            <div class="alert alert-warning d-flex justify-content-between align-items-center">
                                                <span id="selectedCount">0 staff members selected</span>
                                                <div>
                                                    <button type="button" class="btn btn-sm btn-danger" onclick="confirmBulkDelete()">
                                                        <i class="fas fa-trash"></i> Delete Selected
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-secondary" onclick="clearSelection()">
                                                        Clear Selection
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead class="table-dark">
                                                <tr>
                                                    <th width="40">
                                                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                                    </th>
                                                    <th>Name</th>
                                                    <th>Department</th>
                                                    <th>Title</th>
                                                    <th>Extension</th>
                                                    <th>Room</th>
                                                    <th>Added By</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($staff_list as $staff): ?>
                                                <tr>
                                                    <td>
                                                        <input type="checkbox" class="staff-checkbox" name="selected_staff[]" 
                                                               value="<?php echo $staff['id']; ?>" onchange="updateBulkActions()">
                                                    </td>
                                                    <td><strong><?php echo htmlspecialchars($staff['name']); ?></strong></td>
                                                    <td>
                                                        <span class="badge bg-primary"><?php echo htmlspecialchars($staff['department_name']); ?></span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($staff['title']); ?></td>
                                                    <td><?php echo htmlspecialchars($staff['extension'] ?: 'N/A'); ?></td>
                                                    <td><?php echo htmlspecialchars($staff['room_number'] ?: 'N/A'); ?></td>
                                                    <td><small><?php echo htmlspecialchars($staff['added_by_username'] ?: 'Unknown'); ?></small></td>
                                                    <td>
                                                        <div class="btn-group" role="group">
                                                            <a href="admin_staff.php?action=edit&id=<?php echo $staff['id']; ?>" class="btn btn-sm btn-warning">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <button class="btn btn-sm btn-danger" onclick="deleteStaff(<?php echo $staff['id']; ?>, '<?php echo htmlspecialchars($staff['name']); ?>')">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </form>
                                
                                <div class="mt-3">
                                    <small class="text-muted">
                                        Showing <?php echo count($staff_list); ?> staff member(s)
                                        <?php if ($department_filter): ?>
                                            in selected department
                                        <?php endif; ?>
                                    </small>
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

function filterByDepartment(departmentId) {
    if (departmentId) {
        window.location.href = "admin_staff.php?department_id=" + departmentId;
    } else {
        window.location.href = "admin_staff.php";
    }
}

function deleteStaff(id, name) {
    if (confirm(`Are you sure you want to delete "${name}"?\\n\\nThis action cannot be undone.`)) {
        window.location.href = `admin_staff.php?delete_id=${id}`;
    }
}

// Multi-select functionality
function toggleSelectAll() {
    const selectAllCheckbox = document.getElementById("selectAll");
    const staffCheckboxes = document.querySelectorAll(".staff-checkbox");
    
    staffCheckboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
    });
    
    updateBulkActions();
}

function updateBulkActions() {
    const selectedCheckboxes = document.querySelectorAll(".staff-checkbox:checked");
    const bulkActionsBar = document.getElementById("bulkActionsBar");
    const selectedCount = document.getElementById("selectedCount");
    const selectAllCheckbox = document.getElementById("selectAll");
    const allCheckboxes = document.querySelectorAll(".staff-checkbox");
    
    // Update selected count and show/hide bulk actions bar
    if (selectedCheckboxes.length > 0) {
        bulkActionsBar.style.display = "block";
        selectedCount.textContent = `${selectedCheckboxes.length} staff member${selectedCheckboxes.length !== 1 ? "s" : ""} selected`;
    } else {
        bulkActionsBar.style.display = "none";
    }
    
    // Update select all checkbox state
    if (selectedCheckboxes.length === 0) {
        selectAllCheckbox.indeterminate = false;
        selectAllCheckbox.checked = false;
    } else if (selectedCheckboxes.length === allCheckboxes.length) {
        selectAllCheckbox.indeterminate = false;
        selectAllCheckbox.checked = true;
    } else {
        selectAllCheckbox.indeterminate = true;
        selectAllCheckbox.checked = false;
    }
}

function confirmBulkDelete() {
    const selectedCheckboxes = document.querySelectorAll(".staff-checkbox:checked");
    const count = selectedCheckboxes.length;
    
    if (count === 0) {
        alert("Please select at least one staff member to delete.");
        return;
    }
    
    if (confirm(`Are you sure you want to delete ${count} staff member${count !== 1 ? "s" : ""}?\\n\\nThis action cannot be undone.`)) {
        // Add bulk_delete_staff input to form and submit
        const form = document.getElementById("bulkDeleteForm");
        const input = document.createElement("input");
        input.type = "hidden";
        input.name = "bulk_delete_staff";
        input.value = "1";
        form.appendChild(input);
        form.submit();
    }
}

function clearSelection() {
    const staffCheckboxes = document.querySelectorAll(".staff-checkbox");
    const selectAllCheckbox = document.getElementById("selectAll");
    
    staffCheckboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
    selectAllCheckbox.checked = false;
    selectAllCheckbox.indeterminate = false;
    
    updateBulkActions();
}

// Initialize extension formatting on page load
document.addEventListener("DOMContentLoaded", function() {
    const extensionInputs = document.querySelectorAll(".extension-input");
    extensionInputs.forEach(function(input) {
        input.addEventListener("input", function(e) {
            formatExtension(e.target);
        });
    });
    
    // Initialize bulk actions state
    updateBulkActions();
});
</script>';

include 'footer.php';
?>