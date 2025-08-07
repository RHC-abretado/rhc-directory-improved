<?php
// index.php
require_once 'auth.php';
require_once 'config.php';
requireLogin();

// Redirect admins to admin dashboard
if (isAdmin()) {
    header('Location: admin_dashboard.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Get user's assigned departments
$user_departments = getUserDepartments();

// Get total staff count across all assigned departments
$total_staff = getStaffCounts();

// Get recent staff additions from assigned departments
$recent_staff = [];
if (!empty($user_departments)) {
    $dept_ids = array_column($user_departments, 'id');
    $placeholders = str_repeat('?,', count($dept_ids) - 1) . '?';
    
    $recent_query = "SELECT s.name, s.created_at, d.department_name 
                     FROM staff s 
                     JOIN departments d ON s.department_id = d.id 
                     WHERE s.department_id IN ($placeholders) 
                     ORDER BY s.created_at DESC LIMIT 5";
    $recent_stmt = $db->prepare($recent_query);
    $recent_stmt->execute($dept_ids);
    $recent_staff = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<?php
$page_title = 'Dashboard - Staff Management System';
$current_page = 'dashboard';
include 'header.php';
?>
        <?php if (empty($user_departments)): ?>
            <!-- No Department Assignment -->
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card border-warning">
                        <div class="card-header bg-warning text-dark">
                            <h5><i class="fas fa-exclamation-triangle"></i> No Department Assignment</h5>
                        </div>
                        <div class="card-body">
                            <p class="lead">You haven't been assigned to any departments yet.</p>
                            <p>Please contact your system administrator to assign you to one or more departments so you can manage staff members.</p>
                            
                            <div class="text-center mt-4">
                                <a href="mailto:admin@example.com" class="btn btn-warning">
                                    <i class="fas fa-envelope"></i> Contact Administrator
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Normal Dashboard -->
            <div class="row">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-building fa-3x text-primary mb-3"></i>
                            <h5>My Departments</h5>
                            <p>View departments you manage</p>
                            <a href="my_departments.php" class="btn btn-primary">View Departments</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-user-plus fa-3x text-success mb-3"></i>
                            <h5>Add Staff</h5>
                            <p>Add new staff members to your departments</p>
                            <a href="add_staff.php" class="btn btn-success">Add Staff</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-list fa-3x text-info mb-3"></i>
                            <h5>Manage Staff</h5>
                            <p>View, edit, and manage your staff members</p>
                            <a href="manage_staff.php" class="btn btn-info">Manage Staff</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-chart-bar"></i> Department Summary</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="text-center">
                                        <h3 class="text-primary"><?php echo $total_staff; ?></h3>
                                        <p>Total Staff Members</p>
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <h6>Recent Activity</h6>
                                    <?php if (!empty($recent_staff)): ?>
                                        <ul class="list-group list-group-flush">
                                            <?php foreach ($recent_staff as $staff): ?>
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <?php echo htmlspecialchars($staff['name']); ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($staff['department_name']); ?></small>
                                                    </div>
                                                    <small class="text-muted"><?php echo date('M j, Y', strtotime($staff['created_at'])); ?></small>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <p class="text-muted">No staff members added yet.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h6><i class="fas fa-info-circle"></i> Department Info</h6>
                        </div>
                        <div class="card-body">
                            <p><strong><?php echo htmlspecialchars($user_department['department_name']); ?></strong></p>
                            <?php if ($user_department['main_phone']): ?>
                                <p><i class="fas fa-phone text-muted"></i> <?php echo htmlspecialchars($user_department['main_phone']); ?></p>
                            <?php endif; ?>
                            <?php if ($user_department['building']): ?>
                                <p><i class="fas fa-building text-muted"></i> <?php echo htmlspecialchars($user_department['building']); ?></p>
                            <?php endif; ?>
                            <?php if ($user_department['room_number']): ?>
                                <p><i class="fas fa-map-marker-alt text-muted"></i> Room <?php echo htmlspecialchars($user_department['room_number']); ?></p>
                            <?php endif; ?>
                            
                            <div class="mt-3">
                                <a href="export.php" class="btn btn-warning btn-sm w-100 mb-2">
                                    <i class="fas fa-download"></i> Export Staff Data
                                </a>
                                <a href="display.php" class="btn btn-secondary btn-sm w-100" target="_blank">
                                    <i class="fas fa-eye"></i> View Public Directory
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php include 'footer.php'; ?>