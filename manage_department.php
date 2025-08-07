<?php
// manage_department.php
require_once 'auth.php';
require_once 'config.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();

$success = '';
$error = '';

// Check if user already has a department
$check_query = "SELECT d.* FROM departments d WHERE d.created_by = :user_id";
$check_stmt = $db->prepare($check_query);
$check_stmt->bindParam(':user_id', $_SESSION['user_id']);
$check_stmt->execute();
$user_department = $check_stmt->fetch(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $db->beginTransaction();
        
        if ($user_department) {
            // Update existing department
            $query = "UPDATE departments SET 
                      department_name = :dept_name,
                      main_phone = :main_phone,
                      building = :building,
                      room_number = :room_number,
                      description = :description,
                      updated_at = CURRENT_TIMESTAMP
                      WHERE id = :dept_id AND created_by = :user_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':dept_id', $user_department['id']);
        } else {
            // Create new department
            $query = "INSERT INTO departments (department_name, main_phone, building, room_number, description, created_by) 
                      VALUES (:dept_name, :main_phone, :building, :room_number, :description, :user_id)";
            $stmt = $db->prepare($query);
        }
        
        $stmt->bindParam(':dept_name', $_POST['department_name']);
        $stmt->bindParam(':main_phone', $_POST['main_phone']);
        $stmt->bindParam(':building', $_POST['building']);
        $stmt->bindParam(':room_number', $_POST['room_number']);
        $stmt->bindParam(':description', $_POST['description']);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->execute();
        
        if (!$user_department) {
            $dept_id = $db->lastInsertId();
            // Update user's department_id
            $user_query = "UPDATE users SET department_id = :dept_id WHERE id = :user_id";
            $user_stmt = $db->prepare($user_query);
            $user_stmt->bindParam(':dept_id', $dept_id);
            $user_stmt->bindParam(':user_id', $_SESSION['user_id']);
            $user_stmt->execute();
        }
        
        $db->commit();
        $success = $user_department ? "Department updated successfully!" : "Department created successfully!";
        
        // Refresh department data
        $check_stmt->execute();
        $user_department = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $db->rollback();
        $error = "Error saving department: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Department - Staff Management System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-users"></i> Staff Management
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php">Dashboard</a>
                <?php if ($user_department): ?>
                    <a class="nav-link" href="add_staff.php">Add Staff</a>
                    <a class="nav-link" href="manage_staff.php">Manage Staff</a>
                <?php endif; ?>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5>
                            <i class="fas fa-building"></i> 
                            <?php echo $user_department ? 'Edit' : 'Setup'; ?> Department Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!$user_department): ?>
                            <div class="alert alert-info">
                                <h6><i class="fas fa-info-circle"></i> Welcome!</h6>
                                Before you can add staff members, please set up your department information below. 
                                This will be displayed in the staff directory and help organize your staff listings.
                            </div>
                        <?php endif; ?>

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

                        <form method="POST">
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="mb-3">
                                        <label class="form-label">Department Name *</label>
                                        <input type="text" class="form-control" name="department_name" 
                                               value="<?php echo $user_department ? htmlspecialchars($user_department['department_name']) : ''; ?>" 
                                               required placeholder="e.g., Academic Affairs, Business Division, Math & Science">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Main Phone Number</label>
                                        <input type="tel" class="form-control" name="main_phone" 
                                               value="<?php echo $user_department ? htmlspecialchars($user_department['main_phone']) : ''; ?>" 
                                               placeholder="(562) 692-0921">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Building</label>
                                        <input type="text" class="form-control" name="building" 
                                               value="<?php echo $user_department ? htmlspecialchars($user_department['building']) : ''; ?>" 
                                               placeholder="e.g., Administration Building, Science Tower">
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="mb-3">
                                        <label class="form-label">Main Office Room Number</label>
                                        <input type="text" class="form-control" name="room_number" 
                                               value="<?php echo $user_department ? htmlspecialchars($user_department['room_number']) : ''; ?>" 
                                               placeholder="e.g., A101, S233">
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="mb-3">
                                        <label class="form-label">Department Description</label>
                                        <textarea class="form-control" name="description" rows="3" 
                                                  placeholder="Brief description of the department's role and responsibilities..."><?php echo $user_department ? htmlspecialchars($user_department['description']) : ''; ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="text-center">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-save"></i> 
                                    <?php echo $user_department ? 'Update' : 'Create'; ?> Department
                                </button>
                                <?php if ($user_department): ?>
                                    <a href="index.php" class="btn btn-secondary btn-lg ms-2">Cancel</a>
                                <?php endif; ?>
                            </div>
                        </form>

                        <?php if ($user_department): ?>
                            <hr class="my-4">
                            <div class="bg-light p-3 rounded">
                                <h6><i class="fas fa-info-circle text-primary"></i> Current Department Information</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <strong>Department:</strong> <?php echo htmlspecialchars($user_department['department_name']); ?><br>
                                        <strong>Phone:</strong> <?php echo htmlspecialchars($user_department['main_phone'] ?: 'Not set'); ?><br>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Building:</strong> <?php echo htmlspecialchars($user_department['building'] ?: 'Not set'); ?><br>
                                        <strong>Room:</strong> <?php echo htmlspecialchars($user_department['room_number'] ?: 'Not set'); ?><br>
                                    </div>
                                </div>
                                <?php if ($user_department['description']): ?>
                                    <div class="mt-2">
                                        <strong>Description:</strong><br>
                                        <?php echo nl2br(htmlspecialchars($user_department['description'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>