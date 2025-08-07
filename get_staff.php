<?php
// get_staff.php - AJAX endpoint for getting staff data
require_once 'auth.php';
require_once 'config.php';
requireLogin();

if (isset($_GET['id'])) {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get staff data first
    $query = "SELECT s.*, d.department_name FROM staff s 
              JOIN departments d ON s.department_id = d.id 
              WHERE s.id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $_GET['id']);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $staff = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Check if user can manage this staff member's department
        if (isAdmin() || canManageDepartment($staff['department_id'])) {
            // Only return the fields we're using now
            $response = [
                'id' => $staff['id'],
                'department_id' => $staff['department_id'],
                'name' => $staff['name'],
                'title' => $staff['title'],
                'extension' => $staff['extension'],
                'room_number' => $staff['room_number'],
                'department_name' => $staff['department_name']
            ];
            
            header('Content-Type: application/json');
            echo json_encode($response);
        } else {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
        }
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Staff not found']);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Missing ID parameter']);
}
?>