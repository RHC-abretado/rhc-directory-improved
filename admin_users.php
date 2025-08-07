<?php
// admin_users.php
require_once 'auth.php';
require_once 'config.php';
requireAdmin();

$database = new Database();
$db = $database->getConnection();

$success = '';
$error = '';
$action = $_GET['action'] ?? 'list';
$edit_user = null;

// Get all departments for assignment
$departments_query = "SELECT * FROM departments ORDER BY department_name";
$departments_stmt = $db->prepare($departments_query);
$departments_stmt->execute();
$departments = $departments_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $db->beginTransaction();
        
        if (isset($_POST['create_user'])) {
            // Create new user
            $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            
            $query = "INSERT INTO users (username, password, role, email) 
                      VALUES (:username, :password, :role, :email)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':username', $_POST['username']);
            $stmt->bindParam(':password', $hashed_password);
            $stmt->bindParam(':role', $_POST['role']);
            $stmt->bindParam(':email', $_POST['email']);
            $stmt->execute();
            
            $user_id = $db->lastInsertId();
            
            // Assign to departments if role is department_manager
            if ($_POST['role'] === 'department_manager' && !empty($_POST['assigned_departments'])) {
                $dept_query = "INSERT INTO user_departments (user_id, department_id, assigned_by) VALUES (?, ?, ?)";
                $dept_stmt = $db->prepare($dept_query);
                
                foreach ($_POST['assigned_departments'] as $dept_id) {
                    $dept_stmt->execute([$user_id, $dept_id, $_SESSION['user_id']]);
                }
            }
            
            $success = "User created successfully!";
            
        } elseif (isset($_POST['update_user'])) {
            // Update existing user
            $query = "UPDATE users SET username = :username, role = :role, email = :email";
            
            // Only update password if provided
            if (!empty($_POST['password'])) {
                $query .= ", password = :password";
            }
            
            $query .= " WHERE id = :user_id";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':username', $_POST['username']);
            $stmt->bindParam(':role', $_POST['role']);
            $stmt->bindParam(':email', $_POST['email']);
            $stmt->bindParam(':user_id', $_POST['user_id']);
            
            if (!empty($_POST['password'])) {
                $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt->bindParam(':password', $hashed_password);
            }
            
            $stmt->execute();
            
            // Update department assignments for department managers
            if ($_POST['role'] === 'department_manager') {
                // Remove existing assignments
                $remove_query = "DELETE FROM user_departments WHERE user_id = :user_id";
                $remove_stmt = $db->prepare($remove_query);
                $remove_stmt->bindParam(':user_id', $_POST['user_id']);
                $remove_stmt->execute();
                
                // Add new assignments
                if (!empty($_POST['assigned_departments'])) {
                    $dept_query = "INSERT INTO user_departments (user_id, department_id, assigned_by) VALUES (?, ?, ?)";
                    $dept_stmt = $db->prepare($dept_query);
                    
                    foreach ($_POST['assigned_departments'] as $dept_id) {
                        $dept_stmt->execute([$_POST['user_id'], $dept_id, $_SESSION['user_id']]);
                    }
                }
            } else {
                // If changing to admin, remove all department assignments
                $remove_query = "DELETE FROM user_departments WHERE user_id = :user_id";
                $remove_stmt = $db->prepare($remove_query);
                $remove_stmt->bindParam(':user_id', $_POST['user_id']);
                $remove_stmt->execute();
            }
            
            $success = "User updated successfully!";
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
        // Cannot delete yourself
        if ($_GET['delete_id'] == $_SESSION['user_id']) {
            $error = "You cannot delete your own account.";
        } else {
            $db->beginTransaction();
            
            // Check if user has created departments or staff
            $check_query = "SELECT 
                           (SELECT COUNT(*) FROM departments WHERE created_by = :user_id) as dept_count,
                           (SELECT COUNT(*) FROM staff WHERE user_id = :user_id) as staff_count";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':user_id', $_GET['delete_id']);
            $check_stmt->execute();
            $usage = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($usage['dept_count'] > 0 || $usage['staff_count'] > 0) {
                $error = "Cannot delete user. They have created {$usage['dept_count']} department(s) and {$usage['staff_count']} staff member(s).";
            } else {
                $delete_query = "DELETE FROM users WHERE id = :user_id";
                $delete_stmt = $db->prepare($delete_query);
                $delete_stmt->bindParam(':user_id', $_GET['delete_id']);
                $delete_stmt->execute();
                
                $success = "User deleted successfully!";
            }
            
            $db->commit();
        }
        
    } catch (Exception $e) {
        $db->rollback();
        $error = "Error deleting user: " . $e->getMessage();
    }
}

// Get user for editing
if ($action === 'edit' && isset($_GET['id'])) {
    $edit_query = "SELECT * FROM users WHERE id = :id";
    $edit_stmt = $db->prepare($edit_query);
    $edit_stmt->bindParam(':id', $_GET['id']);
    $edit_stmt->execute();
    $edit_user = $edit_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$edit_user) {
        $error = "User not found.";
        $action = 'list';
    } else {
        // Get user's department assignments
        $assignments_query = "SELECT department_id FROM user_departments WHERE user_id = :user_id";
        $assignments_stmt = $db->prepare($assignments_query);
        $assignments_stmt->bindParam(':user_id', $_GET['id']);
        $assignments_stmt->execute();
        $edit_user['assigned_departments'] = $assignments_stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

// Get all users with their department assignments
$users_query = "SELECT u.*, 
                COUNT(DISTINCT ud.department_id) as dept_count,
                COUNT(DISTINCT d.id) as created_dept_count,
                COUNT(DISTINCT s.id) as staff_count,
                GROUP_CONCAT(DISTINCT dep.department_name SEPARATOR ', ') as assigned_departments
                FROM users u 
                LEFT JOIN user_departments ud ON u.id = ud.user_id 
                LEFT JOIN departments dep ON ud.department_id = dep.id
                LEFT JOIN departments d ON u.id = d.created_by
                LEFT JOIN staff s ON u.id = s.user_id
                GROUP BY u.id 
                ORDER BY u.role, u.username";
$users_stmt = $db->prepare($users_query);
$users_stmt->execute();
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php
$page_title = 'Manage Users - Admin Panel';
$current_page = 'users';
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
                    <!-- Add/Edit User Form -->
                    <div class="card">
                        <div class="card-header">
                            <h5>
                                <i class="fas fa-<?php echo $action === 'add' ? 'user-plus' : 'user-edit'; ?>"></i>
                                <?php echo $action === 'add' ? 'Add New' : 'Edit'; ?> User
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <?php if ($action === 'edit'): ?>
                                    <input type="hidden" name="user_id" value="<?php echo $edit_user['id']; ?>">
                                <?php endif; ?>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Username *</label>
                                            <input type="text" class="form-control" name="username" 
                                                   value="<?php echo $edit_user ? htmlspecialchars($edit_user['username']) : ''; ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Email</label>
                                            <input type="email" class="form-control" name="email" 
                                                   value="<?php echo $edit_user ? htmlspecialchars($edit_user['email']) : ''; ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Password <?php echo $action === 'edit' ? '(leave empty to keep current)' : '*'; ?></label>
                                            <input type="password" class="form-control" name="password" <?php echo $action === 'add' ? 'required' : ''; ?>>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Role *</label>
                                            <select class="form-control" name="role" required onchange="toggleDepartmentAssignment(this.value)">
                                                <option value="department_manager" <?php echo ($edit_user && $edit_user['role'] === 'department_manager') ? 'selected' : ''; ?>>
                                                    Department Manager
                                                </option>
                                                <option value="admin" <?php echo ($edit_user && $edit_user['role'] === 'admin') ? 'selected' : ''; ?>>
                                                    Administrator
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-12" id="department_assignment" style="<?php echo ($edit_user && $edit_user['role'] === 'admin') ? 'display: none;' : ''; ?>">
                                        <div class="mb-3">
                                            <label class="form-label">Assigned Departments</label>
                                            <div class="border rounded p-3" style="max-height: 200px; overflow-y: auto;">
                                                <?php if (empty($departments)): ?>
                                                    <p class="text-muted">No departments available. Create departments first.</p>
                                                <?php else: ?>
                                                    <!-- Select All checkbox -->
                                                    <div class="form-check mb-2 pb-2 border-bottom">
                                                        <input class="form-check-input" type="checkbox" id="selectAllDepts" onchange="toggleAllDepartments()">
                                                        <label class="form-check-label fw-bold" for="selectAllDepts">
                                                            <i class="fas fa-check-double"></i> Select All Departments
                                                        </label>
                                                    </div>
                                                    
                                                    <?php foreach ($departments as $dept): ?>
                                                        <div class="form-check">
                                                            <input class="form-check-input dept-checkbox" type="checkbox" name="assigned_departments[]" 
                                                                   value="<?php echo $dept['id']; ?>" id="dept_<?php echo $dept['id']; ?>"
                                                                   <?php echo ($edit_user && in_array($dept['id'], $edit_user['assigned_departments'] ?? [])) ? 'checked' : ''; ?>
                                                                   onchange="updateSelectAllState()">
                                                            <label class="form-check-label" for="dept_<?php echo $dept['id']; ?>">
                                                                <?php echo htmlspecialchars($dept['department_name']); ?>
                                                                <?php if ($dept['building'] && $dept['room_number']): ?>
                                                                    <small class="text-muted">(<?php echo htmlspecialchars($dept['building']); ?>, <?php echo htmlspecialchars($dept['room_number']); ?>)</small>
                                                                <?php endif; ?>
                                                            </label>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                            <small class="form-text text-muted">Department managers can only manage assigned departments. Administrators have access to all departments.</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="text-center">
                                    <button type="submit" name="<?php echo $action === 'add' ? 'create_user' : 'update_user'; ?>" class="btn btn-success btn-lg">
                                        <i class="fas fa-save"></i> 
                                        <?php echo $action === 'add' ? 'Create' : 'Update'; ?> User
                                    </button>
                                    <a href="admin_users.php" class="btn btn-secondary btn-lg ms-2">Cancel</a>
                                </div>
                            </form>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- Users List -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5><i class="fas fa-users-cog"></i> System Users</h5>
                            <a href="admin_users.php?action=add" class="btn btn-primary">
                                <i class="fas fa-user-plus"></i> Add User
                            </a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($users)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                    <h5>No Users Found</h5>
                                    <p class="text-muted">This shouldn't happen since you're logged in as an admin.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>Username</th>
                                                <th>Role</th>
                                                <th>Email</th>
                                                <th>Assigned Departments</th>
                                                <th>Activity</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                                    <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                                        <span class="badge bg-warning text-dark">You</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($user['role'] === 'admin'): ?>
                                                        <span class="badge bg-danger">Administrator</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-primary">Department Manager</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($user['email'] ?: 'N/A'); ?></td>
                                                <td>
                                                    <?php if ($user['role'] === 'admin'): ?>
                                                        <span class="text-muted">All Departments</span>
                                                    <?php elseif ($user['assigned_departments']): ?>
                                                        <small><?php echo htmlspecialchars($user['assigned_departments']); ?></small>
                                                    <?php else: ?>
                                                        <span class="text-warning">No assignments</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small>
                                                        <strong>Created:</strong> <?php echo $user['created_dept_count']; ?> dept(s)<br>
                                                        <strong>Added:</strong> <?php echo $user['staff_count']; ?> staff
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="admin_users.php?action=edit&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-warning">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                            <?php if ($user['created_dept_count'] == 0 && $user['staff_count'] == 0): ?>
                                                                <button class="btn btn-sm btn-danger" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            <?php else: ?>
                                                                <button class="btn btn-sm btn-secondary" disabled title="Cannot delete - user has created content">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <button class="btn btn-sm btn-secondary" disabled title="Cannot delete yourself">
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
function toggleDepartmentAssignment(role) {
    const deptAssignment = document.getElementById("department_assignment");
    if (role === "admin") {
        deptAssignment.style.display = "none";
        const checkboxes = deptAssignment.querySelectorAll("input[type=checkbox]");
        checkboxes.forEach(cb => cb.checked = false);
    } else {
        deptAssignment.style.display = "block";
    }
}

function toggleAllDepartments() {
    const selectAllCheckbox = document.getElementById("selectAllDepts");
    const deptCheckboxes = document.querySelectorAll(".dept-checkbox");
    
    deptCheckboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
    });
}

function updateSelectAllState() {
    const selectAllCheckbox = document.getElementById("selectAllDepts");
    const deptCheckboxes = document.querySelectorAll(".dept-checkbox");
    const checkedBoxes = document.querySelectorAll(".dept-checkbox:checked");
    
    if (checkedBoxes.length === 0) {
        selectAllCheckbox.indeterminate = false;
        selectAllCheckbox.checked = false;
    } else if (checkedBoxes.length === deptCheckboxes.length) {
        selectAllCheckbox.indeterminate = false;
        selectAllCheckbox.checked = true;
    } else {
        selectAllCheckbox.indeterminate = true;
        selectAllCheckbox.checked = false;
    }
}

function deleteUser(id, username) {
    if (confirm(`Are you sure you want to delete user "${username}"?\\n\\nThis action cannot be undone.`)) {
        window.location.href = `admin_users.php?delete_id=${id}`;
    }
}

// Initialize select all state on page load
document.addEventListener("DOMContentLoaded", function() {
    updateSelectAllState();
});
</script>';

include 'footer.php';
?>