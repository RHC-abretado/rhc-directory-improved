<?php
$page_title = 'Documentation - Staff Management System';
$current_page = '';
include 'header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4><i class="fas fa-book"></i> Staff Management System Documentation</h4>
                <p class="mb-0">Complete guide for department managers and staff</p>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> 
                    <strong>Welcome!</strong> This documentation is designed to help you efficiently manage your department's staff directory. 
                    Click on any section below to expand detailed instructions.
                </div>

                <!-- Documentation Accordions -->
                <div class="accordion" id="documentationAccordion">
                    
                    <!-- Getting Started -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="gettingStarted">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseGettingStarted">
                                <i class="fas fa-play-circle me-2"></i> Getting Started
                            </button>
                        </h2>
                        <div id="collapseGettingStarted" class="accordion-collapse collapse show" data-bs-parent="#documentationAccordion">
                            <div class="accordion-body">
                                <h6>Welcome to the Staff Management System!</h6>
                                <p>As a department manager, you can manage staff members for the departments you've been assigned to. Here's what you can do:</p>
                                <ul>
                                    <li><strong>View your departments</strong> - See all departments you manage</li>
                                    <li><strong>Add staff members</strong> - Add new employees to your departments</li>
                                    <li><strong>Edit staff information</strong> - Update employee details</li>
                                    <li><strong>Export data</strong> - Download staff information as CSV files</li>
                                    <li><strong>View public directory</strong> - See how your staff appears in the directory</li>
                                </ul>
                                <div class="alert alert-warning">
                                    <strong>Important:</strong> You can only manage staff in departments that have been assigned to you by an administrator.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- How to Add Staff -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="addStaff">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAddStaff">
                                <i class="fas fa-user-plus me-2"></i> How do I add a staff member?
                            </button>
                        </h2>
                        <div id="collapseAddStaff" class="accordion-collapse collapse" data-bs-parent="#documentationAccordion">
                            <div class="accordion-body">
                                <h6>Adding staff members is easy!</h6>
                                <ol>
                                    <li><strong>Navigate to Add Staff:</strong>
                                        <ul>
                                            <li>Click <code>Add Staff</code> in the navigation menu, OR</li>
                                            <li>Go to <code>My Departments</code> and click <code>Add Staff</code> on a specific department card</li>
                                        </ul>
                                    </li>
                                    <li><strong>Select Department:</strong>
                                        <ul>
                                            <li>If you manage multiple departments, choose which department to add staff to</li>
                                            <li>If you manage only one department, it will be pre-selected</li>
                                        </ul>
                                    </li>
                                    <li><strong>Fill out staff information:</strong>
                                        <ul>
                                            <li><strong>Name *</strong> - Full name (required)</li>
                                            <li><strong>Title *</strong> - Job title or position (required)</li>
                                            <li><strong>Phone</strong> - Main phone number</li>
                                            <li><strong>Extension</strong> - Phone extension (displays in directory)</li>
                                            <li><strong>Email</strong> - Contact email address</li>
                                            <li><strong>Room Number</strong> - Office or room location</li>
                                        </ul>
                                    </li>
                                    <li><strong>Add multiple staff members:</strong>
                                        <ul>
                                            <li>Click <code>Add Another Staff Member</code> to add more entries</li>
                                            <li>Click <code>Remove Last Entry</code> if you added too many</li>
                                        </ul>
                                    </li>
                                    <li><strong>Save:</strong> Click <code>Save All Staff Members</code> to add them to your department</li>
                                </ol>
                                
                                <div class="alert alert-success">
                                    <strong>Tip:</strong> You can add multiple staff members at once to save time!
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- How to Edit Staff -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="editStaff">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseEditStaff">
                                <i class="fas fa-edit me-2"></i> How do I edit a staff member?
                            </button>
                        </h2>
                        <div id="collapseEditStaff" class="accordion-collapse collapse" data-bs-parent="#documentationAccordion">
                            <div class="accordion-body">
                                <h6>Editing staff information:</h6>
                                <ol>
                                    <li><strong>Go to Manage Staff:</strong> Click <code>Manage Staff</code> in the navigation menu</li>
                                    <li><strong>Find the staff member:</strong>
                                        <ul>
                                            <li>Use the department filter dropdown if you manage multiple departments</li>
                                            <li>Look through the staff table to find the person you want to edit</li>
                                        </ul>
                                    </li>
                                    <li><strong>Click the edit button:</strong> Click the yellow <code><i class="fas fa-edit"></i></code> button in the Actions column</li>
                                    <li><strong>Update information:</strong> A popup window will appear where you can edit:
                                        <ul>
                                            <li>Name</li>
                                            <li>Title</li>
                                            <li>Phone number</li>
                                            <li>Extension</li>
                                            <li>Email address</li>
                                            <li>Room number</li>
                                        </ul>
                                    </li>
                                    <li><strong>Save changes:</strong> Click <code>Save Changes</code> to update the staff member's information</li>
                                </ol>

                                <div class="alert alert-info">
                                    <strong>Note:</strong> Changes are immediately reflected in the public directory and exported data.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- How to Delete Staff -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="deleteStaff">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseDeleteStaff">
                                <i class="fas fa-trash me-2"></i> How do I delete a staff member?
                            </button>
                        </h2>
                        <div id="collapseDeleteStaff" class="accordion-collapse collapse" data-bs-parent="#documentationAccordion">
                            <div class="accordion-body">
                                <h6>Removing staff members:</h6>
                                <ol>
                                    <li><strong>Go to Manage Staff:</strong> Click <code>Manage Staff</code> in the navigation menu</li>
                                    <li><strong>Find the staff member:</strong> Locate the person you want to remove</li>
                                    <li><strong>Click the delete button:</strong> Click the red <code><i class="fas fa-trash"></i></code> button in the Actions column</li>
                                    <li><strong>Confirm deletion:</strong> A confirmation dialog will ask if you're sure</li>
                                    <li><strong>Confirm:</strong> Click <code>OK</code> to permanently delete the staff member</li>
                                </ol>

                                <div class="alert alert-danger">
                                    <strong>Warning:</strong> Deleting a staff member is permanent and cannot be undone. The person will be immediately removed from the public directory.
                                </div>

                                <h6>When to delete vs. edit:</h6>
                                <ul>
                                    <li><strong>Delete</strong> when someone leaves the organization permanently</li>
                                    <li><strong>Edit</strong> when someone changes positions, departments, or contact information</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Managing Multiple Departments -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="multipleDepts">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseMultipleDepts">
                                <i class="fas fa-building me-2"></i> How do I manage multiple departments?
                            </button>
                        </h2>
                        <div id="collapseMultipleDepts" class="accordion-collapse collapse" data-bs-parent="#documentationAccordion">
                            <div class="accordion-body">
                                <h6>If you're assigned to multiple departments:</h6>
                                
                                <h6>Viewing Your Departments:</h6>
                                <ul>
                                    <li>Click <code>My Departments</code> to see all departments you manage</li>
                                    <li>Each department shows its staff count and basic information</li>
                                    <li>Click <code>View Staff</code> or <code>Add Staff</code> on any department card</li>
                                </ul>

                                <h6>Adding Staff to Specific Departments:</h6>
                                <ul>
                                    <li>From <code>My Departments</code>: Click <code>Add Staff</code> on the department you want</li>
                                    <li>From <code>Add Staff</code> menu: Select the department from the available options</li>
                                </ul>

                                <h6>Filtering Staff by Department:</h6>
                                <ul>
                                    <li>In <code>Manage Staff</code>, use the department dropdown to filter</li>
                                    <li>Select "All My Departments" to see everyone you manage</li>
                                    <li>Select a specific department to see only that department's staff</li>
                                </ul>

                                <div class="alert alert-success">
                                    <strong>Tip:</strong> The system tracks which department each staff member belongs to, so you can easily organize and filter your staff.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Exporting Data -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="exportData">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseExportData">
                                <i class="fas fa-download me-2"></i> How do I export staff data?
                            </button>
                        </h2>
                        <div id="collapseExportData" class="accordion-collapse collapse" data-bs-parent="#documentationAccordion">
                            <div class="accordion-body">
                                <h6>Exporting your staff information:</h6>
                                <ol>
                                    <li><strong>From Manage Staff:</strong> Click the <code>Export CSV</code> button</li>
                                    <li><strong>From Dashboard:</strong> Click <code>Export My Staff Data</code></li>
                                    <li><strong>From My Departments:</strong> Click <code>Export My Staff Data</code> in the Quick Actions</li>
                                </ol>

                                <h6>What gets exported:</h6>
                                <ul>
                                    <li>All staff from your assigned departments</li>
                                    <li>Name, title, phone, extension, email, room number</li>
                                    <li>Department name for each staff member</li>
                                    <li>Date each staff member was added</li>
                                </ul>

                                <h6>File format:</h6>
                                <ul>
                                    <li>CSV (Comma Separated Values) format</li>
                                    <li>Opens in Excel, Google Sheets, or any spreadsheet program</li>
                                    <li>Filename includes your departments and export date</li>
                                </ul>

                                <div class="alert alert-info">
                                    <strong>Use cases:</strong> Export data for reports, backup purposes, or to share staff listings with other departments.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Understanding the Directory -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="directory">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseDirectory">
                                <i class="fas fa-address-book me-2"></i> Understanding the public directory
                            </button>
                        </h2>
                        <div id="collapseDirectory" class="accordion-collapse collapse" data-bs-parent="#documentationAccordion">
                            <div class="accordion-body">
                                <h6>How the public directory works:</h6>
                                <p>The staff directory is publicly accessible and shows all staff from all departments in a professional format.</p>

                                <h6>Directory views:</h6>
                                <ul>
                                    <li><strong>Card View:</strong> Staff shown as individual cards with contact information</li>
                                    <li><strong>Directory View:</strong> Professional format with three sections:
                                        <ul>
                                            <li>Department Directory (lists all departments)</li>
                                            <li>Department/Staff Listing (staff organized by department)</li>
                                            <li>Alphabetical Staff Listing (all staff in alphabetical order)</li>
                                        </ul>
                                    </li>
                                </ul>

                                <h6>What information is displayed:</h6>
                                <ul>
                                    <li>Staff member's name and title</li>
                                    <li>Phone extension (if provided) or full phone number</li>
                                    <li>Email address (clickable)</li>
                                    <li>Room number and department location</li>
                                    <li>Department name and main phone</li>
                                </ul>

                                <h6>Accessing the directory:</h6>
                                <ul>
                                    <li>Click <code>View Directory</code> in the footer or navigation</li>
                                    <li>Use filters to show specific departments</li>
                                    <li>Print the directory using the Print button</li>
                                </ul>

                                <div class="alert alert-success">
                                    <strong>Professional appearance:</strong> The directory is designed to look like traditional institutional phone directories and can be printed or shared as needed.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Troubleshooting -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="troubleshooting">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTroubleshooting">
                                <i class="fas fa-tools me-2"></i> Troubleshooting & Common Issues
                            </button>
                        </h2>
                        <div id="collapseTroubleshooting" class="accordion-collapse collapse" data-bs-parent="#documentationAccordion">
                            <div class="accordion-body">
                                <h6>Common issues and solutions:</h6>

                                <div class="mb-3">
                                    <strong>I can't see any departments:</strong>
                                    <ul>
                                        <li>You haven't been assigned to any departments yet</li>
                                        <li>Contact your system administrator to assign you to departments</li>
                                    </ul>
                                </div>

                                <div class="mb-3">
                                    <strong>I can't edit a staff member:</strong>
                                    <ul>
                                        <li>The staff member might belong to a department you don't manage</li>
                                        <li>Make sure you're looking in the right department filter</li>
                                    </ul>
                                </div>

                                <div class="mb-3">
                                    <strong>The directory isn't showing my changes:</strong>
                                    <ul>
                                        <li>Changes should appear immediately</li>
                                        <li>Try refreshing the directory page</li>
                                        <li>Make sure you clicked "Save Changes" when editing</li>
                                    </ul>
                                </div>

                                <div class="mb-3">
                                    <strong>I accidentally deleted someone:</strong>
                                    <ul>
                                        <li>Deletions cannot be undone</li>
                                        <li>You'll need to re-add the staff member manually</li>
                                        <li>Contact your administrator if you need help recovering information</li>
                                    </ul>
                                </div>

                                <div class="alert alert-info">
                                    <strong>Need more help?</strong> Contact your system administrator for additional support or to report technical issues.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- System Roles -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="systemRoles">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSystemRoles">
                                <i class="fas fa-users-cog me-2"></i> System Roles & Permissions
                            </button>
                        </h2>
                        <div id="collapseSystemRoles" class="accordion-collapse collapse" data-bs-parent="#documentationAccordion">
                            <div class="accordion-body">
                                <h6>Understanding user roles in the system:</h6>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card border-danger mb-3">
                                            <div class="card-header bg-danger text-white">
                                                <h6 class="mb-0"><i class="fas fa-shield-alt"></i> Administrator</h6>
                                            </div>
                                            <div class="card-body">
                                                <p><strong>Full system access and control</strong></p>
                                                <ul class="mb-0">
                                                    <li>Create and manage all departments</li>
                                                    <li>Add, edit, delete any staff member</li>
                                                    <li>Create and manage user accounts</li>
                                                    <li>Assign users to departments</li>
                                                    <li>Export all system data</li>
                                                    <li>System monitoring and oversight</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card border-primary mb-3">
                                            <div class="card-header bg-primary text-white">
                                                <h6 class="mb-0"><i class="fas fa-user-tie"></i> Department Manager</h6>
                                            </div>
                                            <div class="card-body">
                                                <p><strong>Limited access to assigned departments</strong></p>
                                                <ul class="mb-0">
                                                    <li>Manage staff in assigned departments only</li>
                                                    <li>Add, edit, delete staff (own departments)</li>
                                                    <li>Export own department data</li>
                                                    <li>View assigned department information</li>
                                                    <li>Cannot create departments or users</li>
                                                    <li>Cannot access other departments</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="card border-info">
                                    <div class="card-header bg-info text-white">
                                        <h6 class="mb-0"><i class="fas fa-eye"></i> Public Access</h6>
                                    </div>
                                    <div class="card-body">
                                        <p><strong>Read-only directory viewing</strong></p>
                                        <ul class="mb-0">
                                            <li>View complete staff directory</li>
                                            <li>Filter by department</li>
                                            <li>Print directory</li>
                                            <li>No editing capabilities</li>
                                            <li>No login required</li>
                                        </ul>
                                    </div>
                                </div>

                                <div class="alert alert-warning mt-3">
                                    <h6><i class="fas fa-key"></i> Your Current Role:</h6>
                                    <?php if (isLoggedIn()): ?>
                                        <?php if (isAdmin()): ?>
                                            <p class="mb-0">You are logged in as an <strong>Administrator</strong> with full system access.</p>
                                        <?php else: ?>
                                            <p class="mb-0">You are logged in as a <strong>Department Manager</strong> with access to your assigned departments.</p>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <p class="mb-0">You are viewing as <strong>Public Access</strong>. <a href="login.php">Login</a> for staff management features.</p>
                                    <?php endif; ?>
                                </div>

                                <h6>Multi-Department Support:</h6>
                                <ul>
                                    <li><strong>One user</strong> can manage <strong>multiple departments</strong></li>
                                    <li><strong>Multiple users</strong> can manage the <strong>same department</strong></li>
                                    <li><strong>Flexible assignment</strong> allows for complex organizational structures</li>
                                    <li><strong>Administrators</strong> can reassign users to different departments at any time</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                </div> <!-- End Accordion -->

                <div class="mt-4 text-center">
                    <div class="alert alert-light">
                        <h6><i class="fas fa-question-circle"></i> Still need help?</h6>
                        <p class="mb-0">Contact your system administrator for additional support or to request new features.</p>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>