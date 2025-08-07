<?php
// admin_dashboard.php
require_once 'auth.php';
require_once 'config.php';
requireAdmin();

$database = new Database();
$db = $database->getConnection();

// Get system statistics
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM departments) as total_departments,
    (SELECT COUNT(*) FROM staff) as total_staff,
    (SELECT COUNT(*) FROM users WHERE role = 'department_manager') as total_managers,
    (SELECT COUNT(*) FROM users WHERE role = 'admin') as total_admins";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get recent activity
$recent_depts_query = "SELECT department_name, created_at FROM departments ORDER BY created_at DESC LIMIT 5";
$recent_depts_stmt = $db->prepare($recent_depts_query);
$recent_depts_stmt->execute();
$recent_departments = $recent_depts_stmt->fetchAll(PDO::FETCH_ASSOC);

$recent_staff_query = "SELECT s.name, d.department_name, s.created_at 
                       FROM staff s 
                       JOIN departments d ON s.department_id = d.id 
                       ORDER BY s.created_at DESC LIMIT 5";
$recent_staff_stmt = $db->prepare($recent_staff_query);
$recent_staff_stmt->execute();
$recent_staff = $recent_staff_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get departments with staff counts
$dept_stats_query = "SELECT d.department_name, d.extension, 
                     COUNT(s.id) as staff_count,
                     d.created_at
                     FROM departments d 
                     LEFT JOIN staff s ON d.id = s.department_id 
                     GROUP BY d.id 
                     ORDER BY d.department_name";
$dept_stats_stmt = $db->prepare($dept_stats_query);
$dept_stats_stmt->execute();
$department_stats = $dept_stats_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php
$page_title = 'Admin Dashboard - Staff Management System';
$current_page = 'dashboard';
include 'header.php';
?>
        <!-- System Overview -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-info">
                    <h5><i class="fas fa-info-circle"></i> System Administrator Dashboard</h5>
                    <p class="mb-0">Welcome to the admin panel. You have full access to manage all departments, staff, and users across the system.</p>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-building fa-2x mb-2"></i>
                        <h4><?php echo $stats['total_departments']; ?></h4>
                        <p>Departments</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-users fa-2x mb-2"></i>
                        <h4><?php echo $stats['total_staff']; ?></h4>
                        <p>Staff Members</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-dark">
                    <div class="card-body text-center">
                        <i class="fas fa-user-tie fa-2x mb-2"></i>
                        <h4><?php echo $stats['total_managers']; ?></h4>
                        <p>Dept. Managers</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-shield-alt fa-2x mb-2"></i>
                        <h4><?php echo $stats['total_admins']; ?></h4>
                        <p>Administrators</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="fas fa-plus-circle fa-3x text-primary mb-3"></i>
                        <h5>Add Department</h5>
                        <p>Create a new department</p>
                        <a href="admin_departments.php?action=add" class="btn btn-primary">Add Department</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="fas fa-user-plus fa-3x text-success mb-3"></i>
                        <h5>Add Staff</h5>
                        <p>Add staff to any department</p>
                        <a href="admin_staff.php?action=add" class="btn btn-success">Add Staff</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="fas fa-users-cog fa-3x text-warning mb-3"></i>
                        <h5>Manage Users</h5>
                        <p>Create and assign users</p>
                        <a href="admin_users.php" class="btn btn-warning">Manage Users</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="fas fa-download fa-3x text-info mb-3"></i>
                        <h5>Export All</h5>
                        <p>Download complete directory</p>
                        <a href="admin_export.php" class="btn btn-info">Export All</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Department Overview -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-building"></i> Department Overview</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($department_stats)): ?>
                            <p class="text-muted">No departments created yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Department</th>
                                            <th>Staff Count</th>
                                            <th>Extension</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($department_stats as $dept): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($dept['department_name']); ?></td>
                                            <td><span class="badge bg-primary"><?php echo $dept['staff_count']; ?></span></td>
                                            <td><?php echo htmlspecialchars($dept['extension'] ?: 'N/A'); ?></td>
                                            <td>
                                                <a href="admin_departments.php?action=edit&id=<?php echo $dept['department_name']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="admin_staff.php?department=<?php echo urlencode($dept['department_name']); ?>" class="btn btn-sm btn-outline-success">
                                                    <i class="fas fa-users"></i>
                                                </a>
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

            <!-- Recent Activity -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h6><i class="fas fa-clock"></i> Recent Activity</h6>
                    </div>
                    <div class="card-body">
                        <h6 class="text-primary">Recent Departments</h6>
                        <?php if (!empty($recent_departments)): ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($recent_departments as $dept): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center p-2">
                                        <small><?php echo htmlspecialchars($dept['department_name']); ?></small>
                                        <small class="text-muted"><?php echo date('M j', strtotime($dept['created_at'])); ?></small>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-muted small">No recent activity</p>
                        <?php endif; ?>

                        <h6 class="text-success mt-3">Recent Staff</h6>
                        <?php if (!empty($recent_staff)): ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($recent_staff as $staff): ?>
                                    <li class="list-group-item p-2">
                                        <div class="d-flex justify-content-between">
                                            <small><strong><?php echo htmlspecialchars($staff['name']); ?></strong></small>
                                            <small class="text-muted"><?php echo date('M j', strtotime($staff['created_at'])); ?></small>
                                        </div>
                                        <small class="text-muted"><?php echo htmlspecialchars($staff['department_name']); ?></small>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-muted small">No recent activity</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php include 'footer.php'; ?>