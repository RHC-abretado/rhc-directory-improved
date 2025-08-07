<?php
// my_departments.php
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

// Get user's assigned departments with staff counts
$departments_query = "SELECT d.*, COUNT(s.id) as staff_count,
                      u.username as created_by_username
                      FROM departments d
                      JOIN user_departments ud ON d.id = ud.department_id
                      LEFT JOIN staff s ON d.id = s.department_id
                      LEFT JOIN users u ON d.created_by = u.id
                      WHERE ud.user_id = :user_id
                      GROUP BY d.id
                      ORDER BY d.department_name";
$departments_stmt = $db->prepare($departments_query);
$departments_stmt->bindParam(':user_id', $_SESSION['user_id']);
$departments_stmt->execute();
$departments = $departments_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php
$page_title = 'My Departments - Staff Management System';
$current_page = 'departments';
include 'header.php';
?>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-building"></i> My Assigned Departments</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($departments)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-building fa-3x text-muted mb-3"></i>
                                <h5>No Department Assignments</h5>
                                <p class="text-muted">You haven't been assigned to any departments yet. Contact your administrator to get department access.</p>
                                <a href="mailto:admin@example.com" class="btn btn-primary">
                                    <i class="fas fa-envelope"></i> Contact Administrator
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($departments as $dept): ?>
                                    <div class="col-md-6 col-lg-4 mb-4">
                                        <div class="card h-100 border-primary">
                                            <div class="card-header bg-primary text-white">
                                                <h6 class="mb-0"><?php echo htmlspecialchars($dept['department_name']); ?></h6>
                                            </div>
                                            <div class="card-body">
                                                <?php if ($dept['extension']): ?>
                                                    <p class="mb-2">
                                                        <i class="fas fa-phone text-muted"></i> 
                                                        Ext. <?php echo htmlspecialchars($dept['extension']); ?>
                                                    </p>
                                                <?php endif; ?>
                                                
                                                <?php if ($dept['building'] || $dept['room_number']): ?>
                                                    <p class="mb-2">
                                                        <i class="fas fa-map-marker-alt text-muted"></i> 
                                                        <?php echo htmlspecialchars($dept['building']); ?>
                                                        <?php if ($dept['room_number']): ?>
                                                            , Room <?php echo htmlspecialchars($dept['room_number']); ?>
                                                        <?php endif; ?>
                                                    </p>
                                                <?php endif; ?>
                                                
                                                <div class="text-center">
                                                    <span class="badge bg-success fs-6 mb-3">
                                                        <?php echo $dept['staff_count']; ?> Staff Member<?php echo $dept['staff_count'] != 1 ? 's' : ''; ?>
                                                    </span>
                                                </div>
                                                
                                                <small class="text-muted">
                                                    Created by: <?php echo htmlspecialchars($dept['created_by_username'] ?: 'Unknown'); ?>
                                                </small>
                                            </div>
                                            <div class="card-footer">
                                                <div class="d-grid gap-2">
                                                    <a href="manage_staff.php?department_id=<?php echo $dept['id']; ?>" class="btn btn-primary btn-sm">
                                                        <i class="fas fa-users"></i> View Staff
                                                    </a>
                                                    <a href="add_staff.php?department_id=<?php echo $dept['id']; ?>" class="btn btn-success btn-sm">
                                                        <i class="fas fa-user-plus"></i> Add Staff
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="mt-4">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card bg-light">
                                            <div class="card-body text-center">
                                                <h6><i class="fas fa-info-circle text-primary"></i> Quick Actions</h6>
                                                <div class="d-grid gap-2">
                                                    <a href="add_staff.php" class="btn btn-success btn-sm">
                                                        <i class="fas fa-user-plus"></i> Add Staff to Any Department
                                                    </a>
                                                    <a href="manage_staff.php" class="btn btn-info btn-sm">
                                                        <i class="fas fa-list"></i> View All My Staff
                                                    </a>
                                                    <a href="export.php" class="btn btn-warning btn-sm">
                                                        <i class="fas fa-download"></i> Export My Staff Data
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card bg-light">
                                            <div class="card-body">
                                                <h6><i class="fas fa-chart-bar text-success"></i> Summary</h6>
                                                <ul class="list-unstyled mb-0">
                                                    <li><strong>Departments:</strong> <?php echo count($departments); ?></li>
                                                    <li><strong>Total Staff:</strong> <?php echo array_sum(array_column($departments, 'staff_count')); ?></li>
                                                    <li><strong>Average per Dept:</strong> 
                                                        <?php 
                                                        $total_staff = array_sum(array_column($departments, 'staff_count'));
                                                        echo count($departments) > 0 ? round($total_staff / count($departments), 1) : 0;
                                                        ?>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php include 'footer.php'; ?>