<?php
// export.php - CSV export functionality
require_once 'auth.php';
require_once 'config.php';
requireLogin();

// Redirect admins to admin export
if (isAdmin()) {
    header('Location: admin_export.php');
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

// Get staff for user's departments
$dept_ids = array_column($user_departments, 'id');
$placeholders = str_repeat('?,', count($dept_ids) - 1) . '?';

$query = "SELECT s.name, s.title, s.extension, s.room_number, s.created_at,
          d.department_name
          FROM staff s 
          JOIN departments d ON s.department_id = d.id 
          WHERE s.department_id IN ($placeholders)
          ORDER BY d.department_name, s.name";
$stmt = $db->prepare($query);
$stmt->execute($dept_ids);

$dept_names = array_column($user_departments, 'department_name');
$filename = "my_staff_export_" . preg_replace('/[^A-Za-z0-9_-]/', '_', implode('_', $dept_names)) . "_" . date('Y-m-d') . ".csv";

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// Add CSV headers including department info
fputcsv($output, ['My Departments Staff Export']);
fputcsv($output, ['Departments: ' . implode(', ', $dept_names)]);
fputcsv($output, ['Exported by: ' . $_SESSION['username']]);
fputcsv($output, ['Exported on: ' . date('Y-m-d H:i:s')]);
fputcsv($output, []); // Empty row

// Add CSV headers
fputcsv($output, ['Name', 'Title', 'Extension', 'Room Number', 'Department', 'Date Added']);

// Add data rows
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, [
        $row['name'],
        $row['title'],
        $row['extension'],
        $row['room_number'],
        $row['department_name'],
        date('Y-m-d', strtotime($row['created_at']))
    ]);
}

fclose($output);
?>