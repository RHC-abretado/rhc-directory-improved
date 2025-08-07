<?php
// auth.php - Enhanced Authentication functions with roles
session_set_cookie_params([
    'httponly' => true,
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'samesite' => 'Strict'
]);
session_start();
require_once 'config.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: index.php');
        exit;
    }
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isDepartmentManager() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'department_manager';
}

function getUserDepartments($user_id = null) {
    if ($user_id === null) {
        $user_id = $_SESSION['user_id'];
    }
    
    $database = new Database();
    $db = $database->getConnection();
    
    // For admins, return all departments
    if (isAdmin()) {
        $query = "SELECT d.* FROM departments d ORDER BY d.department_name";
        $stmt = $db->prepare($query);
    } else {
        // For department managers, return only assigned departments
        $query = "SELECT d.* FROM departments d 
                  JOIN user_departments ud ON d.id = ud.department_id 
                  WHERE ud.user_id = :user_id 
                  ORDER BY d.department_name";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function canManageDepartment($department_id) {
    if (isAdmin()) {
        return true; // Admins can manage all departments
    }
    
    // Check if user is assigned to this department
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT COUNT(*) as count FROM user_departments 
              WHERE user_id = :user_id AND department_id = :department_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->bindParam(':department_id', $department_id);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'] > 0;
}

function login($username, $password) {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT id, username, password, role, department_id FROM users WHERE username = :username";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (password_verify($password, $row['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'];
            $_SESSION['department_id'] = $row['department_id'];
            return true;
        }
    }
    return false;
}

function logout() {
    session_destroy();
    header('Location: login.php');
    exit;
}

function getStaffCounts() {
    $database = new Database();
    $db = $database->getConnection();
    
    if (isAdmin()) {
        // Admin sees all staff
        $query = "SELECT COUNT(*) as total FROM staff";
        $stmt = $db->prepare($query);
    } else {
        // Department managers see only their staff
        $departments = getUserDepartments();
        if (empty($departments)) {
            return 0;
        }
        
        $dept_ids = array_column($departments, 'id');
        $placeholders = str_repeat('?,', count($dept_ids) - 1) . '?';
        $query = "SELECT COUNT(*) as total FROM staff WHERE department_id IN ($placeholders)";
        $stmt = $db->prepare($query);
        $stmt->execute($dept_ids);
    }
    
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
}
?>