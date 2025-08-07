<?php
// Allow iframe embedding from any domain
header('X-Frame-Options: ALLOWALL');

// Enable CORS for cross-domain requests  
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// embed_directory.php - Embeddable staff directory widget
require_once 'config.php';

// Get parameters
$department = isset($_GET['dept']) ? $_GET['dept'] : '';
$format = isset($_GET['format']) ? $_GET['format'] : 'html'; // html, json, or minimal
$theme = isset($_GET['theme']) ? $_GET['theme'] : 'default'; // default, minimal, or custom
$sections = isset($_GET['sections']) ? $_GET['sections'] : 'both'; // departments, staff, or both

$database = new Database();
$db = $database->getConnection();

// Check if database connection exists
if (!$db) {
    if ($format === 'json') {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Database connection failed']);
        exit;
    }
    die('Database connection failed');
}

// Get staff data
if ($department) {
    $query = "SELECT s.*, d.department_name as department FROM staff s 
              JOIN departments d ON s.department_id = d.id 
              WHERE d.department_name = ? 
              ORDER BY s.name";
    $stmt = $db->prepare($query);
    $stmt->execute([$department]);
} else {
    $query = "SELECT s.*, d.department_name as department FROM staff s 
              JOIN departments d ON s.department_id = d.id 
              ORDER BY d.department_name, s.name";
    $stmt = $db->prepare($query);
    $stmt->execute();
}

$staff_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get departments
$dept_query = "SELECT d.department_name, d.extension, d.building, d.room_number 
               FROM departments d 
               ORDER BY d.department_name";
$dept_stmt = $db->prepare($dept_query);
$dept_stmt->execute();
$departments = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);

// Create department directory grouped by first letter
$dept_directory = [];
$dept_by_letter = [];
foreach ($departments as $dept) {
    $dept_directory[$dept['department_name']] = $dept;
    $first_letter = strtoupper(substr($dept['department_name'], 0, 1));
    $dept_by_letter[$first_letter][] = $dept;
}

// Sort each letter group alphabetically
foreach ($dept_by_letter as $letter => $depts) {
    usort($dept_by_letter[$letter], function($a, $b) {
        return strcmp($a['department_name'], $b['department_name']);
    });
}
ksort($dept_by_letter);

// Group staff by department
$staff_by_dept = [];
foreach ($staff_list as $staff) {
    $staff_by_dept[$staff['department']][] = $staff;
}

// Handle JSON output
if ($format === 'json') {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *'); // Allow cross-origin requests
    
    $output = [
        'departments' => $departments,
        'staff' => $staff_list,
        'staff_by_department' => $staff_by_dept,
        'generated_at' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($output, JSON_PRETTY_PRINT);
    exit;
}

// HTML Output
$embed_styles = '';
if ($theme === 'minimal') {
    $embed_styles = '
    <style>
        .embed-container { font-family: Arial, sans-serif; font-size: 14px; }
        .embed-directory-title { background: #f8f9fa; padding: 8px 12px; margin: 0 0 15px 0; font-weight: bold; border-left: 4px solid #007bff; }
        .embed-dept-header { font-weight: bold; color: #007bff; margin: 15px 0 8px 0; padding-bottom: 4px; border-bottom: 1px solid #007bff; }
        .embed-dept-listing, .embed-staff-listing { display: grid; grid-template-columns: 1fr auto; gap: 15px; padding: 4px 0; border-bottom: 1px dotted #ddd; }
        .embed-phone-number { font-family: monospace; text-align: right; }
        .embed-staff-name { font-weight: 500; }
        .embed-staff-title { color: #666; font-style: italic; }
        .embed-letter-header { font-weight: bold; color: #007bff; margin: 20px 0 10px 0; font-size: 18px; }
    </style>';
}

// Allow iframe embedding
header('X-Frame-Options: SAMEORIGIN');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Directory</title>
    <?php echo $embed_styles; ?>
    <?php if ($theme === 'default'): ?>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
    <?php endif; ?>
</head>
<body>
    <div class="<?php echo $theme === 'minimal' ? 'embed-container' : 'container-fluid'; ?>">
        
        <?php if ($sections === 'departments' || $sections === 'both'): ?>
        <!-- Department Directory -->
        <?php if (!$department): ?>
        <div class="<?php echo $theme === 'minimal' ? '' : 'directory-section'; ?>">
            <div class="<?php echo $theme === 'minimal' ? 'embed-directory-title' : 'directory-title'; ?>">
                Department Directory
            </div>
            <div class="<?php echo $theme === 'minimal' ? '' : 'card-body'; ?>">
                <?php foreach ($dept_by_letter as $letter => $depts): ?>
                    <div class="<?php echo $theme === 'minimal' ? 'embed-letter-header' : ''; ?>">
                        <?php if ($theme !== 'minimal'): ?>
                            <h4 class="fw-bold text-primary border-bottom pb-2 mb-3"><?php echo $letter; ?></h4>
                        <?php else: ?>
                            <?php echo $letter; ?>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($theme === 'minimal'): ?>
                        <?php foreach ($depts as $dept_info): ?>
                            <div class="embed-dept-listing">
                                <div>
                                    <?php echo htmlspecialchars($dept_info['department_name']); ?>
                                    <?php if ($dept_info['building'] && $dept_info['room_number']): ?>
                                        <small style="color: #999;">(<?php echo htmlspecialchars($dept_info['building']); ?>, <?php echo htmlspecialchars($dept_info['room_number']); ?>)</small>
                                    <?php endif; ?>
                                </div>
                                <div class="embed-phone-number">
                                    <?php echo htmlspecialchars($dept_info['extension'] ?: 'N/A'); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="row mb-4">
                            <?php foreach ($depts as $dept_info): ?>
                                <div class="col-12 col-md-6 col-lg-4 mb-3">
                                    <div class="dept-listing">
                                        <div>
                                            <?php echo htmlspecialchars($dept_info['department_name']); ?>
                                            <?php if ($dept_info['building'] && $dept_info['room_number']): ?>
                                                <small class="text-muted">(<?php echo htmlspecialchars($dept_info['building']); ?>, <?php echo htmlspecialchars($dept_info['room_number']); ?>)</small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="phone-number">
                                            <?php echo htmlspecialchars($dept_info['extension'] ?: 'N/A'); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <?php if ($sections === 'staff' || $sections === 'both'): ?>
        <!-- Staff Listing -->
        <div class="<?php echo $theme === 'minimal' ? '' : 'directory-section'; ?>">
            <div class="<?php echo $theme === 'minimal' ? 'embed-directory-title' : 'directory-title'; ?>">
                <?php echo $department ? htmlspecialchars($department) . ' ' : ''; ?>Staff Directory
            </div>
            <div class="<?php echo $theme === 'minimal' ? '' : 'card-body'; ?>">
                <?php if ($department && empty($staff_by_dept[$department])): ?>
                    <div style="text-align: center; padding: 20px; color: #666;">
                        <p>No staff members are currently listed for this department.</p>
                    </div>
                <?php elseif (empty($staff_list) && !$department): ?>
                    <div style="text-align: center; padding: 20px; color: #666;">
                        <p>No staff members are currently listed.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($dept_directory as $dept_name => $dept_info): ?>
                        <?php if (!$department || $department === $dept_name): ?>
                            <div class="<?php echo $theme === 'minimal' ? 'embed-dept-header' : 'dept-header'; ?>">
                                <?php echo htmlspecialchars($dept_name); ?>
                            </div>
                            <?php if (isset($staff_by_dept[$dept_name]) && !empty($staff_by_dept[$dept_name])): ?>
                                <?php if ($theme === 'minimal'): ?>
                                    <?php foreach ($staff_by_dept[$dept_name] as $staff): ?>
                                        <div class="embed-staff-listing">
                                            <div>
                                                <div class="embed-staff-name">
                                                    <?php echo htmlspecialchars($staff['name']); ?>
                                                    <?php if ($staff['room_number']): ?>
                                                        (<?php echo htmlspecialchars($staff['room_number']); ?>)
                                                    <?php endif; ?>
                                                </div>
                                                <div class="embed-staff-title">- <?php echo htmlspecialchars($staff['title']); ?></div>
                                            </div>
                                            <div class="embed-phone-number">
                                                <?php echo htmlspecialchars($staff['extension'] ?: 'N/A'); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="row mb-4">
                                        <?php foreach ($staff_by_dept[$dept_name] as $staff): ?>
                                            <div class="col-12 col-md-6 col-lg-4 mb-3">
                                                <div class="staff-listing-new">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div class="staff-name">
                                                            <?php echo htmlspecialchars($staff['name']); ?>
                                                            <?php if ($staff['room_number']): ?>
                                                                (<?php echo htmlspecialchars($staff['room_number']); ?>)
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="phone-number">
                                                            <?php echo htmlspecialchars($staff['extension'] ?: 'N/A'); ?>
                                                        </div>
                                                    </div>
                                                    <div class="staff-title">- <?php echo htmlspecialchars($staff['title']); ?></div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div style="text-align: center; padding: 10px; color: #999;">
                                    <small>No staff members currently listed</small>
                                </div>
                            <?php endif; ?>
                            <?php if ($department) break; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div style="margin-top: 20px; padding: 10px; background: #f8f9fa; text-align: center; font-size: 12px; color: #666;">
            Last updated: <?php echo date('M j, Y g:i A'); ?>
        </div>
    </div>
</body>
</html>