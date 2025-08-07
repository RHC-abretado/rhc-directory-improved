<?php
// imports.php - Department and Staff Import System (Admin only)
require_once 'auth.php';
require_once 'config.php';
requireAdmin();

$database = new Database();
$db = $database->getConnection();

$success = '';
$error = '';
$preview_data = [];
$import_summary = [];
$import_type = $_GET['type'] ?? 'departments'; // 'departments' or 'staff'

// Handle file upload and import
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['preview_import']) && isset($_FILES['csv_file'])) {
        // Preview import
        $file = $_FILES['csv_file'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error = "File upload error. Please try again.";
        } elseif ($file['type'] !== 'text/csv' && !str_ends_with($file['name'], '.csv')) {
            $error = "Please upload a valid CSV file.";
        } else {
            try {
                $handle = fopen($file['tmp_name'], 'r');
                if ($handle === false) {
                    throw new Exception("Could not read the uploaded file.");
                }
                
                // Read header row
                $header = fgetcsv($handle);
                
                if ($import_type === 'departments') {
                    $expected_headers = ['department_name', 'extension', 'building', 'room_number'];
                } else {
                    $expected_headers = ['name', 'title', 'extension', 'room_number', 'department_name'];
                }
                
                if ($header !== $expected_headers) {
                    throw new Exception("Invalid CSV format. Expected headers: " . implode(', ', $expected_headers));
                }
                
                // Read data rows
                $row_count = 0;
                $total_rows = 0;
                while (($row = fgetcsv($handle)) !== false) {
                    $total_rows++;
                    if ($row_count < 200) { // Limit preview to 200 rows
                        if (count($row) === count($header)) {
                            $data = array_combine($header, $row);
                            $data['row_number'] = $total_rows + 1; // +1 because of header
                            
                            if ($import_type === 'departments') {
                                // Check if department already exists
                                $check_query = "SELECT id FROM departments WHERE department_name = :dept_name";
                                $check_stmt = $db->prepare($check_query);
                                $check_stmt->bindParam(':dept_name', $data['department_name']);
                                $check_stmt->execute();
                                $data['exists'] = $check_stmt->rowCount() > 0;
                            } else {
                                // For staff, check if department exists and if staff member exists
                                $dept_query = "SELECT id FROM departments WHERE department_name = :dept_name";
                                $dept_stmt = $db->prepare($dept_query);
                                $dept_stmt->bindParam(':dept_name', $data['department_name']);
                                $dept_stmt->execute();
                                $dept_result = $dept_stmt->fetch(PDO::FETCH_ASSOC);
                                
                                if ($dept_result) {
                                    $data['department_id'] = $dept_result['id'];
                                    $data['department_exists'] = true;
                                    
                                    // Check if staff member already exists
                                    $staff_query = "SELECT id FROM staff WHERE name = :name AND department_id = :dept_id";
                                    $staff_stmt = $db->prepare($staff_query);
                                    $staff_stmt->bindParam(':name', $data['name']);
                                    $staff_stmt->bindParam(':dept_id', $dept_result['id']);
                                    $staff_stmt->execute();
                                    $data['exists'] = $staff_stmt->rowCount() > 0;
                                } else {
                                    $data['department_exists'] = false;
                                    $data['exists'] = false;
                                }
                            }
                            
                            $preview_data[] = $data;
                        }
                        $row_count++;
                    }
                }
                fclose($handle);
                
                if (empty($preview_data)) {
                    $error = "No valid data found in the CSV file.";
                } else {
                    // Store the file path for the actual import
                    $temp_file = $_FILES['csv_file']['tmp_name'];
                    $upload_dir = sys_get_temp_dir();
                    $stored_file = $upload_dir . '/' . $import_type . '_import_' . session_id() . '.csv';
                    move_uploaded_file($temp_file, $stored_file);
                    $_SESSION['import_file'] = $stored_file;
                    $_SESSION['total_rows'] = $total_rows;
                    $_SESSION['import_type'] = $import_type;
                }
                
                if (count($preview_data) >= 200) {
                    $success = "Showing first 200 rows for preview. All $total_rows rows will be imported.";
                }
                
            } catch (Exception $e) {
                $error = "Error reading CSV: " . $e->getMessage();
            }
        }
        
    } elseif (isset($_POST['confirm_import'])) {
        // Perform actual import - read from stored file
        try {
            $db->beginTransaction();
            
            if (!isset($_SESSION['import_file']) || !file_exists($_SESSION['import_file'])) {
                throw new Exception("Import file not found. Please upload the file again.");
            }
            
            $stored_import_type = $_SESSION['import_type'] ?? 'departments';
            
            $handle = fopen($_SESSION['import_file'], 'r');
            if ($handle === false) {
                throw new Exception("Could not read the import file.");
            }
            
            // Skip header row
            fgetcsv($handle);
            
            $imported_count = 0;
            $updated_count = 0;
            $skipped_count = 0;
            $error_count = 0;
            $errors = [];
            $row_number = 2; // Start at 2 because of header
            
            while (($row = fgetcsv($handle)) !== false) {
                if ($stored_import_type === 'departments') {
                    if (count($row) < 4) continue; // Skip incomplete rows
                    
                    try {
                        $dept = [
                            'department_name' => trim($row[0]),
                            'extension' => trim($row[1]),
                            'building' => trim($row[2]),
                            'room_number' => trim($row[3])
                        ];
                        
                        // Skip rows with empty department name
                        if (empty($dept['department_name'])) {
                            $row_number++;
                            continue;
                        }
                        
                        // Check if department exists
                        $check_query = "SELECT id FROM departments WHERE department_name = :dept_name";
                        $check_stmt = $db->prepare($check_query);
                        $check_stmt->bindParam(':dept_name', $dept['department_name']);
                        $check_stmt->execute();
                        
                        if ($check_stmt->rowCount() > 0) {
                            if (isset($_POST['update_existing']) && $_POST['update_existing'] === '1') {
                                // Update existing department
                                $update_query = "UPDATE departments SET 
                                                extension = :extension,
                                                building = :building,
                                                room_number = :room_number,
                                                updated_at = CURRENT_TIMESTAMP
                                                WHERE department_name = :dept_name";
                                $update_stmt = $db->prepare($update_query);
                                $update_stmt->bindParam(':dept_name', $dept['department_name']);
                                $update_stmt->bindParam(':extension', $dept['extension']);
                                $update_stmt->bindParam(':building', $dept['building']);
                                $update_stmt->bindParam(':room_number', $dept['room_number']);
                                $update_stmt->execute();
                                $updated_count++;
                            } else {
                                // Skip existing department
                                $skipped_count++;
                            }
                        } else {
                            // Insert new department
                            $insert_query = "INSERT INTO departments (department_name, extension, building, room_number, created_by) 
                                            VALUES (:dept_name, :extension, :building, :room_number, :created_by)";
                            $insert_stmt = $db->prepare($insert_query);
                            $insert_stmt->bindParam(':dept_name', $dept['department_name']);
                            $insert_stmt->bindParam(':extension', $dept['extension']);
                            $insert_stmt->bindParam(':building', $dept['building']);
                            $insert_stmt->bindParam(':room_number', $dept['room_number']);
                            $insert_stmt->bindParam(':created_by', $_SESSION['user_id']);
                            $insert_stmt->execute();
                            $imported_count++;
                        }
                    } catch (Exception $e) {
                        $error_count++;
                        $errors[] = "Row $row_number: " . $e->getMessage();
                    }
                } else {
                    // Staff import
                    if (count($row) < 5) continue; // Skip incomplete rows
                    
                    try {
                        $staff = [
                            'name' => trim($row[0]),
                            'title' => trim($row[1]),
                            'extension' => trim($row[2]),
                            'room_number' => trim($row[3]),
                            'department_name' => trim($row[4])
                        ];
                        
                        // Skip rows with empty name or department
                        if (empty($staff['name']) || empty($staff['department_name'])) {
                            $row_number++;
                            continue;
                        }
                        
                        // Get department ID
                        $dept_query = "SELECT id FROM departments WHERE department_name = :dept_name";
                        $dept_stmt = $db->prepare($dept_query);
                        $dept_stmt->bindParam(':dept_name', $staff['department_name']);
                        $dept_stmt->execute();
                        $dept_result = $dept_stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if (!$dept_result) {
                            $error_count++;
                            $errors[] = "Row $row_number: Department '{$staff['department_name']}' not found";
                            $row_number++;
                            continue;
                        }
                        
                        $department_id = $dept_result['id'];
                        
                        // Check if staff member exists
                        $check_query = "SELECT id FROM staff WHERE name = :name AND department_id = :dept_id";
                        $check_stmt = $db->prepare($check_query);
                        $check_stmt->bindParam(':name', $staff['name']);
                        $check_stmt->bindParam(':dept_id', $department_id);
                        $check_stmt->execute();
                        
                        if ($check_stmt->rowCount() > 0) {
                            if (isset($_POST['update_existing']) && $_POST['update_existing'] === '1') {
                                // Update existing staff member
                                $update_query = "UPDATE staff SET 
                                                title = :title,
                                                extension = :extension,
                                                room_number = :room_number,
                                                updated_at = CURRENT_TIMESTAMP
                                                WHERE name = :name AND department_id = :dept_id";
                                $update_stmt = $db->prepare($update_query);
                                $update_stmt->bindParam(':name', $staff['name']);
                                $update_stmt->bindParam(':title', $staff['title']);
                                $update_stmt->bindParam(':extension', $staff['extension']);
                                $update_stmt->bindParam(':room_number', $staff['room_number']);
                                $update_stmt->bindParam(':dept_id', $department_id);
                                $update_stmt->execute();
                                $updated_count++;
                            } else {
                                // Skip existing staff member
                                $skipped_count++;
                            }
                        } else {
                            // Insert new staff member
                            $insert_query = "INSERT INTO staff (user_id, department_id, name, title, extension, room_number) 
                                            VALUES (:user_id, :dept_id, :name, :title, :extension, :room_number)";
                            $insert_stmt = $db->prepare($insert_query);
                            $insert_stmt->bindParam(':user_id', $_SESSION['user_id']);
                            $insert_stmt->bindParam(':dept_id', $department_id);
                            $insert_stmt->bindParam(':name', $staff['name']);
                            $insert_stmt->bindParam(':title', $staff['title']);
                            $insert_stmt->bindParam(':extension', $staff['extension']);
                            $insert_stmt->bindParam(':room_number', $staff['room_number']);
                            $insert_stmt->execute();
                            $imported_count++;
                        }
                    } catch (Exception $e) {
                        $error_count++;
                        $errors[] = "Row $row_number: " . $e->getMessage();
                    }
                }
                
                $row_number++;
            }
            
            fclose($handle);
            
            // Clean up the temporary file
            if (isset($_SESSION['import_file'])) {
                @unlink($_SESSION['import_file']);
                unset($_SESSION['import_file']);
                unset($_SESSION['import_type']);
            }
            
            $db->commit();
            
            $import_summary = [
                'imported' => $imported_count,
                'updated' => $updated_count,
                'skipped' => $skipped_count,
                'errors' => $error_count,
                'error_details' => $errors
            ];
            
            $item_type = $stored_import_type === 'departments' ? 'departments' : 'staff members';
            $success = "Import completed! Imported: $imported_count, Updated: $updated_count, Skipped: $skipped_count, Errors: $error_count";
            
        } catch (Exception $e) {
            $db->rollback();
            $error = "Import failed: " . $e->getMessage();
        }
    }
}

$page_title = 'Import Data - Admin Panel';
$current_page = 'imports';
include 'header.php';
?>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-upload"></i> Import Data from CSV</h5>
                        <div class="mt-2">
                            <div class="btn-group" role="group">
                                <a href="?type=departments" class="btn btn-<?php echo $import_type === 'departments' ? 'primary' : 'outline-primary'; ?>">
                                    <i class="fas fa-building"></i> Import Departments
                                </a>
                                <a href="?type=staff" class="btn btn-<?php echo $import_type === 'staff' ? 'primary' : 'outline-primary'; ?>">
                                    <i class="fas fa-users"></i> Import Staff
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
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

                        <?php if (!empty($import_summary)): ?>
                            <div class="alert alert-info">
                                <h6><i class="fas fa-info-circle"></i> Import Summary</h6>
                                <?php $item_type = $_SESSION['import_type'] === 'departments' ? 'Departments' : 'Staff Members'; ?>
                                <ul class="mb-0">
                                    <li><strong>New <?php echo $item_type; ?> Added:</strong> <?php echo $import_summary['imported']; ?></li>
                                    <li><strong>Existing <?php echo $item_type; ?> Updated:</strong> <?php echo $import_summary['updated']; ?></li>
                                    <li><strong><?php echo $item_type; ?> Skipped:</strong> <?php echo $import_summary['skipped']; ?></li>
                                    <li><strong>Errors:</strong> <?php echo $import_summary['errors']; ?></li>
                                </ul>
                                <?php if (!empty($import_summary['error_details'])): ?>
                                    <div class="mt-2">
                                        <strong>Error Details:</strong>
                                        <ul class="mb-0">
                                            <?php foreach ($import_summary['error_details'] as $error_detail): ?>
                                                <li><small><?php echo htmlspecialchars($error_detail); ?></small></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (empty($preview_data) && empty($import_summary)): ?>
                            <!-- Upload Form -->
                            <div class="alert alert-info">
                                <h6><i class="fas fa-info-circle"></i> CSV Format Requirements</h6>
                                <?php if ($import_type === 'departments'): ?>
                                    <p>Your CSV file must contain the following columns in this exact order:</p>
                                    <ul>
                                        <li><strong>department_name</strong> - Name of the department (required)</li>
                                        <li><strong>extension</strong> - Phone extension (optional)</li>
                                        <li><strong>building</strong> - Building name or code (optional)</li>
                                        <li><strong>room_number</strong> - Room number (optional)</li>
                                    </ul>
                                <?php else: ?>
                                    <p>Your CSV file must contain the following columns in this exact order:</p>
                                    <ul>
                                        <li><strong>name</strong> - Staff member's full name (required)</li>
                                        <li><strong>title</strong> - Job title or position (required)</li>
                                        <li><strong>extension</strong> - Phone extension (optional)</li>
                                        <li><strong>room_number</strong> - Room number (optional)</li>
                                        <li><strong>department_name</strong> - Department name (must match existing department)</li>
                                    </ul>
                                <?php endif; ?>
                                <p class="mb-0">
                                    <small><strong>Note:</strong> The first row must contain the column headers exactly as shown above.</small>
                                </p>
                            </div>

                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="import_type" value="<?php echo $import_type; ?>">
                                <div class="mb-3">
                                    <label for="csv_file" class="form-label">Select CSV File</label>
                                    <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required>
                                    <small class="form-text text-muted">Maximum file size: 10MB</small>
                                </div>
                                
                                <div class="text-center">
                                    <button type="submit" name="preview_import" class="btn btn-primary btn-lg">
                                        <i class="fas fa-eye"></i> Preview Import
                                    </button>
                                    <a href="admin_departments.php" class="btn btn-secondary btn-lg ms-2">Cancel</a>
                                </div>
                            </form>

                            <hr class="my-4">
                            
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6><i class="fas fa-download"></i> Sample CSV Template</h6>
                                    <p>Download a sample CSV file to see the correct format:</p>
                                    <?php if ($import_type === 'departments'): ?>
                                        <a href="departments_import.csv" class="btn btn-outline-primary btn-sm" download>
                                            <i class="fas fa-file-csv"></i> Download Departments Sample CSV
                                        </a>
                                    <?php else: ?>
                                        <a href="staff_import.csv" class="btn btn-outline-primary btn-sm" download>
                                            <i class="fas fa-file-csv"></i> Download Staff Sample CSV
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>

                        <?php elseif (!empty($preview_data)): ?>
                            <!-- Preview Data -->
                            <div class="alert alert-warning">
                                <h6><i class="fas fa-exclamation-triangle"></i> Import Preview</h6>
                                <p>Review the data below before importing. 
                                <?php 
                                $total_rows = $_SESSION['total_rows'] ?? count($preview_data);
                                echo count($preview_data) >= 200 ? "Showing first 200 rows for preview - all $total_rows rows will be imported." : "All " . count($preview_data) . " rows will be imported."; 
                                ?></p>
                                <?php if ($import_type === 'departments'): ?>
                                    <p>Departments marked as "Exists" already exist in the system.</p>
                                <?php else: ?>
                                    <p>Staff members marked as "Exists" already exist in the system. Items marked as "Dept Missing" cannot be imported.</p>
                                <?php endif; ?>
                            </div>

                            <form method="POST">
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="update_existing" value="1" id="update_existing">
                                        <label class="form-check-label" for="update_existing">
                                            Update existing <?php echo $import_type === 'departments' ? 'departments' : 'staff members'; ?> with new data
                                        </label>
                                        <small class="form-text text-muted d-block">
                                            If unchecked, existing items will be skipped.
                                        </small>
                                    </div>
                                </div>

                                <div class="table-responsive mb-3" style="max-height: 400px; overflow-y: auto;">
                                    <table class="table table-sm table-striped">
                                        <thead class="table-dark sticky-top">
                                            <tr>
                                                <th>Row</th>
                                                <?php if ($import_type === 'departments'): ?>
                                                    <th>Department Name</th>
                                                    <th>Extension</th>
                                                    <th>Building</th>
                                                    <th>Room</th>
                                                <?php else: ?>
                                                    <th>Name</th>
                                                    <th>Title</th>
                                                    <th>Extension</th>
                                                    <th>Room</th>
                                                    <th>Department</th>
                                                <?php endif; ?>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($preview_data as $row): ?>
                                            <tr class="<?php 
                                                if ($import_type === 'staff' && !$row['department_exists']) {
                                                    echo 'table-danger';
                                                } elseif ($row['exists']) {
                                                    echo 'table-warning';
                                                }
                                            ?>">
                                                <td><?php echo $row['row_number']; ?></td>
                                                <?php if ($import_type === 'departments'): ?>
                                                    <td><strong><?php echo htmlspecialchars($row['department_name']); ?></strong></td>
                                                    <td><?php echo htmlspecialchars($row['extension']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['building']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['room_number']); ?></td>
                                                <?php else: ?>
                                                    <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                                                    <td><?php echo htmlspecialchars($row['title']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['extension']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['room_number']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['department_name']); ?></td>
                                                <?php endif; ?>
                                                <td>
                                                    <?php if ($import_type === 'staff' && !$row['department_exists']): ?>
                                                        <span class="badge bg-danger">Dept Missing</span>
                                                    <?php elseif ($row['exists']): ?>
                                                        <span class="badge bg-warning text-dark">Exists</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">New</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="text-center">
                                    <button type="submit" name="confirm_import" class="btn btn-success btn-lg">
                                        <i class="fas fa-check"></i> Confirm Import 
                                        <?php 
                                        $total_rows = $_SESSION['total_rows'] ?? count($preview_data);
                                        $item_type = $import_type === 'departments' ? 'departments' : 'staff members';
                                        echo count($preview_data) >= 200 ? "($total_rows $item_type)" : '(' . count($preview_data) . " $item_type)"; 
                                        ?>
                                    </button>
                                    <a href="imports.php?type=<?php echo $import_type; ?>" class="btn btn-secondary btn-lg ms-2">Start Over</a>
                                </div>
                            </form>
                        <?php endif; ?>

                        <?php if (!empty($import_summary)): ?>
                            <div class="text-center mt-3">
                                <?php if ($import_type === 'departments'): ?>
                                    <a href="admin_departments.php" class="btn btn-primary">
                                        <i class="fas fa-building"></i> View All Departments
                                    </a>
                                <?php else: ?>
                                    <a href="admin_staff.php" class="btn btn-primary">
                                        <i class="fas fa-users"></i> View All Staff
                                    </a>
                                <?php endif; ?>
                                <a href="imports.php?type=<?php echo $import_type; ?>" class="btn btn-secondary ms-2">
                                    <i class="fas fa-upload"></i> Import More <?php echo ucfirst($import_type); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php include 'footer.php'; ?>