<?php
// manage_staff.php
require_once 'auth.php';
require_once 'config.php';
requireLogin();

// Redirect admins to admin panel
if (isAdmin()) {
    header('Location: admin_staff.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Get user's assigned departments
$user_departments = getUserDepartments();

if (empty($user_departments)) {
    header('Location: my_departments.php');
    exit;
}

// Get department filter
$department_filter = $_GET['department_id'] ?? '';
$selected_department = null;

if ($department_filter) {
    foreach ($user_departments as $dept) {
        if ($dept['id'] == $department_filter) {
            $selected_department = $dept;
            break;
        }
    }
    // If department not found or not accessible, clear filter
    if (!$selected_department) {
        $department_filter = '';
    }
}

// Handle delete request
if (isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    
    // Verify user can manage this staff member's department
    $check_query = "SELECT department_id FROM staff WHERE id = :id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':id', $delete_id);
    $check_stmt->execute();
    $staff_dept = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($staff_dept && canManageDepartment($staff_dept['department_id'])) {
        $query = "DELETE FROM staff WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $delete_id);
        $stmt->execute();
        header('Location: manage_staff.php?msg=deleted' . ($department_filter ? '&department_id=' . $department_filter : ''));
        exit;
    } else {
        header('Location: manage_staff.php?msg=error');
        exit;
    }
}

// Handle edit request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_id'])) {
    // Verify user can manage this staff member's department
    if (!canManageDepartment($_POST['department_id'])) {
        header('Location: manage_staff.php?msg=error');
        exit;
    }
    
    $query = "UPDATE staff SET name = :name, title = :title, extension = :extension, room_number = :room_number 
              WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':name', $_POST['name']);
    $stmt->bindParam(':title', $_POST['title']);
    $stmt->bindParam(':extension', $_POST['extension']);
    $stmt->bindParam(':room_number', $_POST['room_number']);
    $stmt->bindParam(':id', $_POST['edit_id']);
    $stmt->execute();
    header('Location: manage_staff.php?msg=updated' . ($department_filter ? '&department_id=' . $department_filter : ''));
    exit;
}

// Get staff list for user's departments
$dept_ids = array_column($user_departments, 'id');
$placeholders = str_repeat('?,', count($dept_ids) - 1) . '?';

$staff_query = "SELECT s.*, d.department_name FROM staff s 
                JOIN departments d ON s.department_id = d.id 
                WHERE s.department_id IN ($placeholders)";

$query_params = $dept_ids;

if ($department_filter) {
    $staff_query = "SELECT s.*, d.department_name FROM staff s 
                    JOIN departments d ON s.department_id = d.id 
                    WHERE s.department_id = ?";
    $query_params = [$department_filter];
}

$staff_query .= " ORDER BY d.department_name, s.name";

$stmt = $db->prepare($staff_query);
$stmt->execute($query_params);
$staff_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php
$page_title = 'Manage Staff - Staff Management System';
$current_page = 'manage_staff';
include 'header.php';
?>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <div class="row align-items-center">
                            <div class="col-md-4">
                                <h5>
                                    <i class="fas fa-list"></i> Manage Staff Members
                                    <?php if ($selected_department): ?>
                                        - <span class="text-primary"><?php echo htmlspecialchars($selected_department['department_name']); ?></span>
                                    <?php endif; ?>
                                </h5>
                            </div>
                            <div class="col-md-4">
                                <select class="form-select" onchange="filterByDepartment(this.value)">
                                    <option value="">All My Departments</option>
                                    <?php foreach ($user_departments as $dept): ?>
                                        <option value="<?php echo $dept['id']; ?>" 
                                                <?php echo ($department_filter == $dept['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($dept['department_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 text-end">
                                <a href="export.php" class="btn btn-success btn-sm">
                                    <i class="fas fa-download"></i> Export CSV
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_GET['msg'])): ?>
                            <div class="alert alert-<?php echo $_GET['msg'] === 'error' ? 'danger' : 'success'; ?> alert-dismissible fade show" role="alert">
                                <?php 
                                if ($_GET['msg'] == 'deleted') echo 'Staff member deleted successfully!';
                                elseif ($_GET['msg'] == 'updated') echo 'Staff member updated successfully!';
                                elseif ($_GET['msg'] == 'error') echo 'Error: You do not have permission to perform this action.';
                                ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if (empty($staff_list)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <h5>No Staff Members Found</h5>
                                <p class="text-muted">Start by adding some staff members to your department.</p>
                                <a href="add_staff.php" class="btn btn-primary">Add Staff</a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Name</th>
                                            <?php if (!$department_filter): ?>
                                                <th>Department</th>
                                            <?php endif; ?>
                                            <th>Title</th>
                                            <th>Extension</th>
                                            <th>Room</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($staff_list as $staff): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($staff['name']); ?></td>
                                            <?php if (!$department_filter): ?>
                                                <td><span class="badge bg-primary"><?php echo htmlspecialchars($staff['department_name']); ?></span></td>
                                            <?php endif; ?>
                                            <td><?php echo htmlspecialchars($staff['title']); ?></td>
                                            <td><?php echo htmlspecialchars($staff['extension'] ?: 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($staff['room_number'] ?: 'N/A'); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-warning" onclick="editStaff(<?php echo $staff['id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="deleteStaff(<?php echo $staff['id']; ?>, '<?php echo htmlspecialchars($staff['name']); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Staff Member</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="edit_id" id="edit_id">
                        <input type="hidden" name="department_id" id="edit_department_id">
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" class="form-control" name="name" id="edit_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" class="form-control" name="title" id="edit_title" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Extension</label>
                            <input type="text" class="form-control extension-input" name="extension" id="edit_extension" placeholder="3402" maxlength="9">
                            <small class="form-text text-muted">Format: 4 digits or 4 digits/4 digits (e.g., 3402 or 3402/3403)</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Room Number</label>
                            <input type="text" class="form-control" name="room_number" id="edit_room_number">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
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

function editStaff(id) {
    fetch(`get_staff.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert("Error: " + data.error);
                return;
            }
            document.getElementById("edit_id").value = data.id;
            document.getElementById("edit_department_id").value = data.department_id;
            document.getElementById("edit_name").value = data.name;
            document.getElementById("edit_title").value = data.title;
            document.getElementById("edit_extension").value = data.extension || "";
            document.getElementById("edit_room_number").value = data.room_number || "";
            new bootstrap.Modal(document.getElementById("editModal")).show();
        })
        .catch(error => {
            alert("Error loading staff data: " + error);
        });
}

function deleteStaff(id, name) {
    if (confirm(`Are you sure you want to delete ${name}?`)) {
        const currentFilter = new URLSearchParams(window.location.search).get("department_id") || "";
        const filterParam = currentFilter ? `&department_id=${currentFilter}` : "";
        window.location.href = `manage_staff.php?delete=${id}${filterParam}`;
    }
}

function filterByDepartment(departmentId) {
    if (departmentId) {
        window.location.href = "manage_staff.php?department_id=" + departmentId;
    } else {
        window.location.href = "manage_staff.php";
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