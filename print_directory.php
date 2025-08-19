<?php
// print_directory.php - Professional Directory Print Layout
require_once 'config.php';

$database = new Database();
$db = $database->getConnection();

// Check if database connection exists
if (!$db) {
    die('Database connection failed');
}

// Get all departments with extensions, ordered alphabetically
$departments_query = "SELECT department_name, extension, building, room_number 
                      FROM departments 
                      ORDER BY department_name";
$departments_stmt = $db->prepare($departments_query);
$departments_stmt->execute();
$departments = $departments_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all staff with department info, ordered by department then name
$staff_query = "SELECT s.name, s.title, s.extension, s.room_number, s.is_department_head, d.department_name, d.extension as dept_extension
                FROM staff s
                JOIN departments d ON s.department_id = d.id
                ORDER BY d.department_name, s.is_department_head DESC, s.name";
$staff_stmt = $db->prepare($staff_query);
$staff_stmt->execute();
$all_staff = $staff_stmt->fetchAll(PDO::FETCH_ASSOC);

// Group staff by department
$staff_by_dept = [];
foreach ($all_staff as $staff) {
    $staff_by_dept[$staff['department_name']][] = $staff;
}

// Create alphabetical staff listing
$alpha_staff = $all_staff;
usort($alpha_staff, function($a, $b) {
    return strcmp($a['name'], $b['name']);
});

// Group alphabetical staff by first letter
$alpha_by_letter = [];
foreach ($alpha_staff as $staff) {
    $first_letter = strtoupper(substr($staff['name'], 0, 1));
    $alpha_by_letter[$first_letter][] = $staff;
}
ksort($alpha_by_letter);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Directory</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Screen styles */
        body {
            font-family: Arial, sans-serif;
            line-height: 1.4;
        }
        
        .no-print {
            margin-bottom: 2rem;
        }
        
        .screen-preview {
            background: #f8f9fa;
            padding: 2rem;
            border-radius: 0.375rem;
            border: 1px solid #dee2e6;
        }
        
        /* Print styles - matches PDF exactly */
        @media print {
            .no-print { 
                display: none !important; 
            }
            
            body { 
                font-family: Arial, sans-serif;
                font-size: 8pt;
                line-height: 1.1;
                margin: 0.5in;
                padding: 0;
                color: #000;
            }
            
            /* Clearfix for floating elements */
            .dept-entry::after,
            .staff-entry::after,
            .alpha-entry::after {
                content: "";
                display: table;
                clear: both;
            }
            
            .container { 
                max-width: none;
                padding: 0;
                margin: 0;
            }
            
            /* Page headers */
            .page-number {
                position: fixed;
                top: 0.2in;
                right: 0.5in;
                font-size: 10pt;
                font-weight: bold;
            }
            
            .main-title {
                text-align: center;
                font-size: 12pt;
                font-weight: bold;
                text-transform: uppercase;
                margin-bottom: 0.2in;
                page-break-after: avoid;
            }
            
            .section-title {
                font-size: 10pt;
                font-weight: bold;
                text-transform: uppercase;
                margin-bottom: 0.1in;
                page-break-after: avoid;
            }
            
            .general-info {
                font-size: 8pt;
                margin-bottom: 0.15in;
                text-align: center;
            }
            
            /* Department Directory Styles */
            .dept-directory {
                columns: 3;
                column-gap: 0.25in;
                column-fill: balance;
            }
            
            .letter-section {
                break-inside: avoid;
                margin-bottom: 0.1in;
            }
            
            .letter-header {
                font-weight: bold;
                font-size: 9pt;
                margin-bottom: 0.05in;
                page-break-after: avoid;
            }
            
            .dept-entry {
                break-inside: avoid;
                margin-bottom: 0.01in;
                font-size: 7pt;
                line-height: 1.0;
                display: block;
            }
            
            .dept-name {
                font-weight: bold;
                text-transform: uppercase;
            }
            
            .dept-location {
                font-size: 6pt;
                margin-left: 0.1in;
            }
            
            .dept-extension {
                font-family: Arial, sans-serif;
                float: right;
                font-weight: normal;
                font-size: 7pt;
            }
            
            /* Staff Section Styles */
            .staff-section {
                margin-top: 0.3in;
                columns: 3;
                column-gap: 0.25in;
                column-fill: balance;
            }
            
            .dept-header {
                font-weight: bold;
                font-size: 8pt;
                margin-top: 0.15in;
                margin-bottom: 0.05in;
                page-break-after: avoid;
                text-transform: uppercase;
                break-after: avoid;
            }
            
            .staff-entry {
                break-inside: avoid;
                margin-bottom: 0.01in;
                font-size: 7pt;
                line-height: 1.0;
                display: block;
            }
            
            .staff-name {
                font-weight: normal;
            }
            
            .staff-title {
                font-weight: normal;
                margin-left: 0.05in;
            }
            
            .staff-extension-line {
                font-family: Arial, sans-serif;
                float: right;
                font-size: 7pt;
                margin-top: -1.0em;
            }
            
            /* Alphabetical Section */
            .alpha-section {
                margin-top: 0.4in;
                columns: 3;
                column-gap: 0.25in;
                column-fill: balance;
            }
            
            .alpha-letter-header {
                font-weight: bold;
                font-size: 9pt;
                margin-top: 0.1in;
                margin-bottom: 0.05in;
                page-break-after: avoid;
                break-after: avoid;
            }
            
            .alpha-entry {
                break-inside: avoid;
                margin-bottom: 0.01in;
                font-size: 7pt;
                line-height: 1.0;
                display: block;
            }
            
            .alpha-name-dept {
                display: block;
            }
            
            .alpha-extension {
                font-family: Arial, sans-serif;
                float: right;
                font-size: 7pt;
                margin-top: -1.0em;
            }
            
            /* Page breaks */
            .page-break {
                page-break-before: always;
            }
        }
    </style>
</head>
<body>
    <!-- Screen Controls -->
    <div class="no-print">
        <div class="container">
            <div class="screen-preview">
                <div class="row align-items-center mb-4">
                    <div class="col-md-6">
                        <h2><i class="fas fa-print"></i> Directory Print Preview</h2>
                        <p class="text-muted">This page will print exactly like the professional directory format.</p>
                    </div>
                    <div class="col-md-6 text-end">
                        <button onclick="window.print()" class="btn btn-primary btn-lg">
                            <i class="fas fa-print"></i> Print Directory
                        </button>
                        <a href="display.php" class="btn btn-secondary btn-lg ms-2">
                            <i class="fas fa-arrow-left"></i> Back to Directory
                        </a>
                    </div>
                </div>
                <div class="alert alert-info">
                    <h6><i class="fas fa-info-circle"></i> Print Instructions</h6>
                    <ul class="mb-0">
                        <li>Click "Print Directory" button above</li>
                        <li>In print dialog, select "More settings" and choose "Portrait" orientation</li>
                        <li>Ensure "Print backgrounds" is enabled for best appearance</li>
                        <li>Use standard 8.5" x 11" paper size</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Print Content -->
    <div class="container">
        <!-- Page Number -->
        <div class="page-number">2</div>
        
        <!-- Main Title -->
        <div class="main-title">Department Directory</div>
        <div class="general-info">General Information: (562) 692-0921</div>

        <!-- Department Directory Section -->
        <div class="dept-directory">
            <?php
            $current_letter = '';
            foreach ($departments as $dept):
                $first_letter = strtoupper(substr($dept['department_name'], 0, 1));
                
                // New letter section
                if ($first_letter !== $current_letter):
                    if ($current_letter !== '') echo '</div>'; // Close previous section
                    $current_letter = $first_letter;
                    echo '<div class="letter-section">';
                    echo '<div class="letter-header">' . $first_letter . '</div>';
                endif;
            ?>
                <div class="dept-entry">
                    <?php echo strtoupper(htmlspecialchars($dept['department_name'])); ?>
                    <?php if ($dept['building'] && $dept['room_number']): ?>
                        <div class="dept-location">(<?php echo htmlspecialchars($dept['building'] . ', ' . $dept['room_number']); ?>)</div>
                    <?php endif; ?>
                    <span class="dept-extension"><?php echo htmlspecialchars($dept['extension'] ?: 'N/A'); ?></span>
                </div>
            <?php endforeach; ?>
            <?php if ($current_letter !== '') echo '</div>'; // Close last section ?>
        </div>

        <!-- Page Break for Staff Section -->
        <div class="page-break">
            <div class="page-number">3</div>
            <div class="main-title">Department/Staff Listing</div>
        </div>

        <!-- Department/Staff Listing Section -->
        <div class="staff-section">
            <?php foreach ($departments as $dept): ?>
                <?php if (isset($staff_by_dept[$dept['department_name']])): ?>
                    <div class="dept-header">
                        <?php echo strtoupper(htmlspecialchars($dept['department_name'])); ?>
                        <?php if ($dept['extension']): ?>
                            - <?php echo htmlspecialchars($dept['extension']); ?>
                        <?php endif; ?>
                    </div>
                    
                    <?php foreach ($staff_by_dept[$dept['department_name']] as $staff): ?>
                        <div class="staff-entry">
                            <div class="staff-name">
                                <?php echo htmlspecialchars($staff['name']); ?>
                                <?php if ($staff['title']): ?>
                                    <span class="staff-title">– <?php echo htmlspecialchars($staff['title']); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="staff-extension-line">
                                <?php echo htmlspecialchars($staff['extension'] ?: 'N/A'); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <!-- Page Break for Alphabetical Section -->
        <div class="page-break">
            <div class="page-number">4</div>
            <div class="main-title">Alphabetical Staff Listing</div>
        </div>

        <!-- Alphabetical Staff Listing Section -->
        <div class="alpha-section">
            <?php foreach ($alpha_by_letter as $letter => $staff_list): ?>
                <div class="alpha-letter-header"><?php echo $letter; ?></div>
                <?php foreach ($staff_list as $staff): ?>
                    <div class="alpha-entry">
                        <?php echo htmlspecialchars($staff['name']); ?>: <?php echo htmlspecialchars($staff['department_name']); ?>
                        <span class="alpha-extension"><?php echo htmlspecialchars($staff['extension'] ?: 'N/A'); ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js" defer></script>
</body>
</html>