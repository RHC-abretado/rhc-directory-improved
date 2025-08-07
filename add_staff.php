<?php
// add_staff.php
require_once 'auth.php';
require_once 'config.php';
requireLogin();

// Redirect admins to admin panel
if (isAdmin()) {
    header('Location: admin_staff.php?action=add');
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

// Check if specific department is requested
$selected_department_id = $_GET['department_id'] ?? '';
$selected_department = null;

if ($selected_department_id) {
    foreach ($user_departments as $dept) {
        if ($dept['id'] == $selected_department_id) {
            $selected_department = $dept;
            break;
        }
    }
    // If department not found or not accessible, redirect
    if (!$selected_department) {
        header('Location: add_staff.php');
        exit;
    }
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $db->beginTransaction();
        
        // Verify user can manage the selected department
        if (!canManageDepartment($_POST['department_id'])) {
            throw new Exception("You don't have permission to manage this department.");
        }
        
        $query = "INSERT INTO staff (user_id, department_id, name, title, extension, room_number) 
                  VALUES (:user_id, :department_id, :name, :title, :extension, :room_number)";
        $stmt = $db->prepare($query);
        
        $added_count = 0;
        foreach ($_POST['staff'] as $staff_member) {
            if (!empty($staff_member['name']) && !empty($staff_member['title'])) {
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->bindParam(':department_id', $_POST['department_id']);
                $stmt->bindParam(':name', $staff_member['name']);
                $stmt->bindParam(':title', $staff_member['title']);
                $stmt->bindParam(':extension', $staff_member['extension']);
                $stmt->bindParam(':room_number', $staff_member['room_number']);
                $stmt->execute();
                $added_count++;
            }
        }
        
        $db->commit();
        
        // Get department name for success message
        $dept_name = '';
        foreach ($user_departments as $dept) {
            if ($dept['id'] == $_POST['department_id']) {
                $dept_name = $dept['department_name'];
                break;
            }
        }
        
        $success = "Successfully added $added_count staff member(s) to " . htmlspecialchars($dept_name) . "!";
        
    } catch (Exception $e) {
        $db->rollback();
        $error = "Error adding staff: " . $e->getMessage();
    }
}
?>

<?php
$page_title = 'Add Staff - Staff Management System';
$current_page = 'add_staff';
include 'header.php';
?>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5>
                            <i class="fas fa-user-plus"></i> Add Staff Members
                            <?php if ($selected_department): ?>
                                to <span class="text-primary"><?php echo htmlspecialchars($selected_department['department_name']); ?></span>
                            <?php endif; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <?php echo $success; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Department Selection -->
                        <?php if (!$selected_department): ?>
                            <div class="alert alert-info">
                                <h6><i class="fas fa-info-circle"></i> Select Department</h6>
                                <p class="mb-3">Choose which department to add staff members to:</p>
                                <div class="row">
                                    <?php foreach ($user_departments as $dept): ?>
                                        <div class="col-md-6 col-lg-4 mb-3">
                                            <div class="card border-primary">
                                                <div class="card-body text-center">
                                                    <h6><?php echo htmlspecialchars($dept['department_name']); ?></h6>
                                                    <?php if ($dept['extension']): ?>
                                                        <small class="text-muted">Ext. <?php echo htmlspecialchars($dept['extension']); ?></small>
                                                    <?php endif; ?>
                                                    <div class="mt-3">
                                                        <a href="add_staff.php?department_id=<?php echo $dept['id']; ?>" class="btn btn-primary">
                                                            <i class="fas fa-plus"></i> Add Staff Here
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Department Information Display -->
                            <div class="alert alert-info">
                                <h6><i class="fas fa-info-circle"></i> Department Information</h6>
                                <p class="mb-2">Adding staff to: <strong><?php echo htmlspecialchars($selected_department['department_name']); ?></strong></p>
                                <?php if ($selected_department['extension']): ?>
                                    <p class="mb-2">Extension: <?php echo htmlspecialchars($selected_department['extension']); ?></p>
                                <?php endif; ?>
                                <?php if ($selected_department['building'] && $selected_department['room_number']): ?>
                                    <p class="mb-0">Location: <?php echo htmlspecialchars($selected_department['building']); ?>, Room <?php echo htmlspecialchars($selected_department['room_number']); ?></p>
                                <?php endif; ?>
                                <div class="mt-2">
                                    <a href="add_staff.php" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-arrow-left"></i> Choose Different Department
                                    </a>
                                </div>
                            </div>

                            <form method="POST" id="staffForm">
                                <input type="hidden" name="department_id" value="<?php echo $selected_department['id']; ?>">
                            <div id="staffContainer">
                                <div class="staff-entry border rounded p-3 mb-3">
                                    <h6 class="text-primary">Staff Member 1</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Name *</label>
                                                <input type="text" class="form-control" name="staff[0][name]" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Title *</label>
                                                <input type="text" class="form-control" name="staff[0][title]" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Extension</label>
                                                <input type="text" class="form-control extension-input" name="staff[0][extension]" placeholder="3402" maxlength="9">
                                                <small class="form-text text-muted">Format: 4 digits or 4 digits/4 digits (e.g., 3402 or 3402/3403)</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Room Number</label>
                                                <input type="text" class="form-control" name="staff[0][room_number]" placeholder="A101">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <button type="button" class="btn btn-outline-primary" onclick="addStaffEntry()">
                                    <i class="fas fa-plus"></i> Add Another Staff Member
                                </button>
                                <button type="button" class="btn btn-outline-danger" onclick="removeLastEntry()">
                                    <i class="fas fa-minus"></i> Remove Last Entry
                                </button>
                            </div>

                            <div class="text-center">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-save"></i> Save All Staff Members
                                </button>
                                <a href="index.php" class="btn btn-secondary btn-lg ms-2">Cancel</a>
                            </div>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php
$additional_scripts = '<script>
let staffCounter = 1;

function formatExtension(input) {
    let value = input.value.replace(/[^0-9\/]/g, "");
    
    // Allow 4 digits or 4 digits/4 digits format
    if (value.length <= 4) {
        input.value = value;
    } else if (value.length > 4 && value.indexOf("/") === -1) {
        // Auto-add slash after 4 digits
        input.value = value.substring(0, 4) + "/" + value.substring(4, 8);
    } else {
        // Ensure proper format with slash
        let parts = value.split("/");
        if (parts.length === 2) {
            input.value = parts[0].substring(0, 4) + "/" + parts[1].substring(0, 4);
        }
    }
}

function addStaffEntry() {
    const container = document.getElementById("staffContainer");
    const newEntry = document.createElement("div");
    newEntry.className = "staff-entry border rounded p-3 mb-3";
    newEntry.innerHTML = `
        <h6 class="text-primary">Staff Member ${staffCounter + 1}</h6>
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label">Name *</label>
                    <input type="text" class="form-control" name="staff[${staffCounter}][name]" required>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label">Title *</label>
                    <input type="text" class="form-control" name="staff[${staffCounter}][title]" required>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label">Extension</label>
                    <input type="text" class="form-control extension-input" name="staff[${staffCounter}][extension]" placeholder="3402" maxlength="9">
                    <small class="form-text text-muted">Format: 4 digits or 4 digits/4 digits (e.g., 3402 or 3402/3403)</small>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label">Room Number</label>
                    <input type="text" class="form-control" name="staff[${staffCounter}][room_number]" placeholder="A101">
                </div>
            </div>
        </div>
    `;
    container.appendChild(newEntry);
    staffCounter++;
    
    // Add event listeners for new extension inputs
    attachExtensionFormatting();
}

function removeLastEntry() {
    const container = document.getElementById("staffContainer");
    const entries = container.querySelectorAll(".staff-entry");
    if (entries.length > 1) {
        entries[entries.length - 1].remove();
        staffCounter--;
    }
}

function attachExtensionFormatting() {
    document.querySelectorAll(".extension-input").forEach(function(input) {
        input.removeEventListener("input", handleExtensionInput);
        input.addEventListener("input", handleExtensionInput);
    });
}

function handleExtensionInput(e) {
    formatExtension(e.target);
}

// Initialize extension formatting on page load
document.addEventListener("DOMContentLoaded", function() {
    attachExtensionFormatting();
});
</script>';

include 'footer.php';
?>