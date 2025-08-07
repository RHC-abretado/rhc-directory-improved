<?php
// header.php - Common header for all pages


// Default values
$page_title = $page_title ?? 'Staff Management System';
$current_page = $current_page ?? '';
$show_public = $show_public ?? false; // For public pages like display.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    
    <!-- Google Fonts - Source Sans Pro -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom Styles -->
    <link href="style.css" rel="stylesheet">
</head>
<body>
    <?php if (!$show_public): ?>
        <?php if (isLoggedIn()): ?>
            <?php if (isAdmin()): ?>
                <!-- Admin Navigation -->
                <nav class="navbar navbar-expand-lg navbar-dark bg-danger">
                    <div class="container">
                        <a class="navbar-brand" href="admin_dashboard.php">
                            <i class="fas fa-shield-alt"></i> Admin Panel
                        </a>
                        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar">
                            <span class="navbar-toggler-icon"></span>
                        </button>
                        <div class="collapse navbar-collapse" id="adminNavbar">
                            <div class="navbar-nav ms-auto">
                                <a class="nav-link <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>" href="admin_dashboard.php">
                                    <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                                </a>
                                <a class="nav-link <?php echo $current_page === 'departments' ? 'active' : ''; ?>" href="admin_departments.php">
                                    <i class="fas fa-building me-1"></i> Departments
                                </a>
                                <a class="nav-link <?php echo $current_page === 'staff' ? 'active' : ''; ?>" href="admin_staff.php">
                                    <i class="fas fa-users me-1"></i> All Staff
                                </a>
                                <a class="nav-link <?php echo $current_page === 'users' ? 'active' : ''; ?>" href="admin_users.php">
                                    <i class="fas fa-users-cog me-1"></i> Users
                                </a>
                                <a class="nav-link" href="imports.php">
                                    <i class="fas fa-upload me-1"></i> Import
                                </a>
                                <a class="nav-link" href="display.php" target="_blank">
                                    <i class="fas fa-eye me-1"></i> Directory
                                </a>
                                <div class="navbar-text d-none d-lg-block mx-3">
                                    <i class="fas fa-user-shield me-1"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
                                </div>
                                <a class="nav-link" href="logout.php">
                                    <i class="fas fa-sign-out-alt me-1"></i> Logout
                                </a>
                            </div>
                        </div>
                    </div>
                </nav>
            <?php else: ?>
                <!-- Regular User Navigation -->
                <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
                    <div class="container">
                        <a class="navbar-brand" href="index.php">
                            <i class="fas fa-users"></i> Staff Management
                        </a>
                        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#userNavbar">
                            <span class="navbar-toggler-icon"></span>
                        </button>
                        <div class="collapse navbar-collapse" id="userNavbar">
                            <div class="navbar-nav ms-auto">
                                <a class="nav-link <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>" href="index.php">
                                    <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                                </a>
                                <a class="nav-link <?php echo $current_page === 'departments' ? 'active' : ''; ?>" href="my_departments.php">
                                    <i class="fas fa-building me-1"></i> My Departments
                                </a>
                                <a class="nav-link <?php echo $current_page === 'add_staff' ? 'active' : ''; ?>" href="add_staff.php">
                                    <i class="fas fa-user-plus me-1"></i> Add Staff
                                </a>
                                <a class="nav-link <?php echo $current_page === 'manage_staff' ? 'active' : ''; ?>" href="manage_staff.php">
                                    <i class="fas fa-list me-1"></i> Manage Staff
                                </a>
                                <div class="navbar-text d-none d-lg-block mx-3">
                                    <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
                                </div>
                                <a class="nav-link" href="logout.php">
                                    <i class="fas fa-sign-out-alt me-1"></i> Logout
                                </a>
                            </div>
                        </div>
                    </div>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    <?php else: ?>
        <!-- Public Navigation -->
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
            <div class="container">
                <a class="navbar-brand" href="display.php">
                    <i class="fas fa-address-book"></i> Staff Directory
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#publicNavbar">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="publicNavbar">
                    <div class="navbar-nav ms-auto">
                        <a class="nav-link" href="login.php">
                            <i class="fas fa-sign-in-alt me-1"></i> Staff Login
                        </a>
                    </div>
                </div>
            </div>
        </nav>
    <?php endif; ?>

    <!-- Main Content Container -->
    <div class="main-content">
        <div class="container mt-4">