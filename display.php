<?php
// display.php - Public display page for staff directory
require_once 'config.php';

// Get department parameter
$department = isset($_GET['dept']) ? $_GET['dept'] : '';

$cacheKey = 'display_directory_data';
$cacheFile = __DIR__ . '/cache/display_cache.json';
$cacheTTL = 900; // 15 minutes

$cachedData = false;
if (function_exists('apcu_fetch')) {
    $cachedData = apcu_fetch($cacheKey);
}
if ($cachedData === false && file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTTL) {
    $cachedData = json_decode(file_get_contents($cacheFile), true);
}

if ($cachedData !== false && is_array($cachedData)) {
    $staff_list = $cachedData['staff_list'];
    $departments = $cachedData['departments'];
    $dept_directory = $cachedData['dept_directory'];
} else {
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        die('Database connection failed');
    }

    $query = "SELECT s.*, d.department_name as department FROM staff s
              JOIN departments d ON s.department_id = d.id
              ORDER BY d.department_name, s.is_department_head DESC, s.name";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $staff_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $dept_query = "SELECT d.department_name, d.extension, d.building, d.room_number
                   FROM departments d
                   ORDER BY d.department_name";
    $dept_stmt = $db->prepare($dept_query);
    $dept_stmt->execute();
    $departments = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);

    $dept_directory = [];
    foreach ($departments as $dept) {
        $dept_directory[$dept['department_name']] = $dept;
    }

    $dataToCache = [
        'staff_list' => $staff_list,
        'departments' => $departments,
        'dept_directory' => $dept_directory
    ];

    if (function_exists('apcu_store')) {
        apcu_store($cacheKey, $dataToCache, $cacheTTL);
    } else {
        if (!is_dir(dirname($cacheFile))) {
            mkdir(dirname($cacheFile), 0755, true);
        }
        file_put_contents($cacheFile, json_encode($dataToCache));
    }
}

if ($department) {
    $staff_list = array_filter($staff_list, function ($staff) use ($department) {
        return $staff['department'] === $department;
    });
}

$staff_by_dept = [];
foreach ($staff_list as $staff) {
    $staff_by_dept[$staff['department']][] = $staff;
}
?>

<?php
$page_title = 'Staff Directory';
$show_public = true;
include 'header.php';
?>
        <!-- Filter, Search and Print Controls -->
        <div class="view-toggle no-print">
            <div class="row align-items-center">
                <div class="col-md-4">
                    <label for="departmentFilter" class="form-label small mb-1">Filter by Department:</label>
                    <select id="departmentFilter" class="form-select" onchange="filterByDepartment(this.value)">
                        <option value="">All Departments</option>
                        <?php foreach (array_keys($dept_directory) as $dept_name): ?>
                            <option value="<?php echo htmlspecialchars($dept_name); ?>" 
                                    <?php echo ($department == $dept_name) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="searchInput" class="form-label small mb-1">Search Directory:</label>
                    <div class="input-group">
                        <input type="text" id="searchInput" class="form-control" 
                               placeholder="Search staff, departments, or extensions..." 
                               onkeyup="searchDirectory(this.value)">
                        <button class="btn btn-outline-secondary" type="button" onclick="clearSearch()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <label class="form-label small mb-1 d-block">&nbsp;</label>
                    <button onclick="window.print()" class="btn btn-primary">
                        <i class="fas fa-print"></i> Print Directory
                    </button>
                </div>
            </div>
            
            <!-- Search Results Summary -->
            <div class="row mt-2">
                <div class="col-12">
                    <div id="searchSummary" class="alert alert-info d-none">
                        <i class="fas fa-search"></i> <span id="searchResultsText"></span>
                        <button type="button" class="btn-close btn-sm ms-2" onclick="clearSearch()"></button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Directory View -->
        <div class="row">
            <div class="col-12">
                <!-- Department/Staff Directory -->
                <div id="staffDirectory" class="directory-section">
                    <div class="directory-title">
                        <?php echo $department ? htmlspecialchars($department) . ' ' : ''; ?>Department/Staff Directory
                    </div>
                    <div class="card-body">
                        <?php if ($department && empty($staff_by_dept[$department])): ?>
                            <!-- Show department even if no staff -->
                            <div class="dept-section" data-dept-name="<?php echo htmlspecialchars($department); ?>">
                                <div class="dept-header">
                                    <?php echo htmlspecialchars($department); ?>
                                    <?php 
                                    $dept_info = $dept_directory[$department];
                                    $location_parts = [];
                                    
                                    // Combine building and room
                                    if ($dept_info['building'] && $dept_info['room_number']) {
                                        $location_parts[] = htmlspecialchars($dept_info['building']) . htmlspecialchars($dept_info['room_number']);
                                    } elseif ($dept_info['room_number']) {
                                        $location_parts[] = htmlspecialchars($dept_info['room_number']);
                                    } elseif ($dept_info['building']) {
                                        $location_parts[] = htmlspecialchars($dept_info['building']);
                                    }
                                    
                                    if ($dept_info['extension']) {
                                        $location_parts[] = 'Ext. ' . htmlspecialchars($dept_info['extension']);
                                    }
                                    if (!empty($location_parts)): ?>
                                        <small class="text-muted">(<?php echo implode(' • ', $location_parts); ?>)</small>
                                    <?php endif; ?>
                                </div>
                                <div class="text-center py-3 no-staff-message">
                                    <p class="text-muted">No staff members are currently listed for this department.</p>
                                </div>
                            </div>
                        <?php elseif (empty($staff_list) && !$department): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <h5>No Staff Members Found</h5>
                                <p class="text-muted">No staff members are currently listed.</p>
                            </div>
                        <?php else: ?>
                            <!-- Show all departments, including those without staff -->
                            <?php foreach ($dept_directory as $dept_name => $dept_info): ?>
                                <?php if (!$department || $department === $dept_name): ?>
                                    <div class="dept-section" data-dept-name="<?php echo htmlspecialchars($dept_name); ?>" 
                                         data-extension="<?php echo htmlspecialchars($dept_info['extension'] ?? ''); ?>"
                                         data-building="<?php echo htmlspecialchars($dept_info['building'] ?? ''); ?>"
                                         data-room="<?php echo htmlspecialchars($dept_info['room_number'] ?? ''); ?>">
                                        <div class="dept-header">
                                            <?php echo htmlspecialchars($dept_name); ?>
                                            <?php 
                                            $location_parts = [];
                                            
                                            // Combine building and room
                                            if ($dept_info['building'] && $dept_info['room_number']) {
                                                $location_parts[] = htmlspecialchars($dept_info['building']) . htmlspecialchars($dept_info['room_number']);
                                            } elseif ($dept_info['room_number']) {
                                                $location_parts[] = htmlspecialchars($dept_info['room_number']);
                                            } elseif ($dept_info['building']) {
                                                $location_parts[] = htmlspecialchars($dept_info['building']);
                                            }
                                            
                                            if ($dept_info['extension']) {
                                                $location_parts[] = 'Ext. ' . htmlspecialchars($dept_info['extension']);
                                            }
                                            if (!empty($location_parts)): ?>
                                                <small class="text-muted">(<?php echo implode(' • ', $location_parts); ?>)</small>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (isset($staff_by_dept[$dept_name]) && !empty($staff_by_dept[$dept_name])): ?>
                                            <div class="row mb-4">
                                                <?php foreach ($staff_by_dept[$dept_name] as $staff): ?>
                                                    <div class="col-12 col-md-6 col-lg-4 mb-3">
                                                        <div class="staff-listing-new staff-item" 
                                                             data-name="<?php echo htmlspecialchars(strtolower($staff['name'])); ?>"
                                                             data-title="<?php echo htmlspecialchars(strtolower($staff['title'])); ?>"
                                                             data-extension="<?php echo htmlspecialchars(strtolower($staff['extension'] ?? '')); ?>"
                                                             data-room="<?php echo htmlspecialchars(strtolower($staff['room_number'] ?? '')); ?>"
                                                             data-dept="<?php echo htmlspecialchars(strtolower($dept_name)); ?>">
                                                            <div class="d-flex justify-content-between align-items-start">
                                                                <div class="staff-name">
                                                                    <?php echo htmlspecialchars($staff['name']); ?>
                                                                    <?php if ($staff['room_number']): ?>
                                                                        (<?php echo htmlspecialchars($staff['room_number']); ?>)
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div class="phone-number">
                                                                    <?php 
                                                                    if ($staff['extension']) {
                                                                        echo htmlspecialchars($staff['extension']);
                                                                    } else {
                                                                        echo 'N/A';
                                                                    }
                                                                    ?>
                                                                </div>
                                                            </div>
                                                            <div class="staff-title">- <?php echo htmlspecialchars($staff['title']); ?></div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-center py-2 mb-4 no-staff-message">
                                                <small class="text-muted">No staff members currently listed</small>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($department) break; // Only show one department if filtering ?>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- No Results Message -->
        <div id="noResultsMessage" class="row d-none">
            <div class="col-12">
                <div class="text-center py-5">
                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                    <h5>No Results Found</h5>
                    <p class="text-muted">No departments or staff members match your search criteria.</p>
                    <button class="btn btn-primary" onclick="clearSearch()">
                        <i class="fas fa-times"></i> Clear Search
                    </button>
                </div>
            </div>
        </div>

<?php
$additional_scripts = '<style>
/* Print Styles for Two-Column Layout */
@media print {
    .no-print { 
        display: none !important; 
    }
    
    body { 
        font-size: 12px; 
        line-height: 1.3;
    }
    
    .container { 
        max-width: none;
        padding: 0;
        margin: 0;
    }
    
    .navbar {
        display: none !important;
    }
    
    footer {
        display: none !important;
    }
    
    /* Two-column layout for directory content */
    .directory-section .card-body {
        column-count: 2;
        column-gap: 1.5rem;
        column-rule: 1px solid #ddd;
    }
    
    /* Prevent department sections from breaking across columns */
    .dept-section {
        break-inside: avoid;
        page-break-inside: avoid;
        margin-bottom: 1rem;
    }
    
    /* Keep department header with its content */
    .dept-header {
        break-after: avoid;
        page-break-after: avoid;
        margin-bottom: 0.5rem;
        font-weight: bold;
        font-size: 14px;
    }
    
    /* Staff items styling for print */
    .staff-listing-new {
        break-inside: avoid;
        page-break-inside: avoid;
        margin-bottom: 0.25rem;
        font-size: 11px;
        line-height: 1.2;
    }
    
    .staff-name {
        font-weight: 500;
    }
    
    .staff-title {
        font-style: italic;
        margin-left: 0.5rem;
    }
    
    .phone-number {
        font-family: monospace;
    }
    
    /* Directory title */
    .directory-title {
        column-span: all;
        text-align: center;
        font-size: 18px;
        font-weight: bold;
        margin-bottom: 1rem;
        padding: 0.5rem;
        border-bottom: 2px solid #333;
    }
    
    /* Adjust spacing for print */
    .row {
        margin: 0;
    }
    
    .col-12 {
        padding: 0;
    }
    
    /* Ensure text is black for good print contrast */
    * {
        color: black !important;
    }
    
    .text-muted {
        color: #666 !important;
    }
}
</style>

<script>
let originalContent = null;
let isSearchActive = false;

function filterByDepartment(dept) {
    // Clear any active search when using department filter
    if (isSearchActive) {
        clearSearch();
    }
    
    if (dept) {
        window.location.href = "display.php?dept=" + encodeURIComponent(dept);
    } else {
        window.location.href = "display.php";
    }
}

function searchDirectory(searchTerm) {
    const term = searchTerm.toLowerCase().trim();
    
    if (term === "") {
        clearSearch();
        return;
    }
    
    // Store original content if not already stored
    if (!originalContent) {
        const staffDir = document.getElementById("staffDirectory");
        originalContent = {
            staffDirectory: staffDir ? staffDir.innerHTML : ""
        };
    }
    
    isSearchActive = true;
    let hasResults = false;
    let resultCount = 0;
    
    // Search in department sections and staff
    const deptSections = document.querySelectorAll(".dept-section");
    
    deptSections.forEach(section => {
        let sectionHasResults = false;
        
        // Check if department matches search
        const deptName = (section.getAttribute("data-dept-name") || "").toLowerCase();
        const extension = (section.getAttribute("data-extension") || "").toLowerCase();
        const building = (section.getAttribute("data-building") || "").toLowerCase();
        const room = (section.getAttribute("data-room") || "").toLowerCase();
        
        const deptMatches = deptName.includes(term) || extension.includes(term) || 
                           building.includes(term) || room.includes(term);
        
        if (deptMatches) {
            sectionHasResults = true;
            resultCount++;
        }
        
        // Check staff in this department
        const staffItems = section.querySelectorAll(".staff-item");
        let visibleStaffCount = 0;
        
        staffItems.forEach(item => {
            const name = (item.getAttribute("data-name") || "");
            const title = (item.getAttribute("data-title") || "");
            const staffExtension = (item.getAttribute("data-extension") || "");
            const staffRoom = (item.getAttribute("data-room") || "");
            
            if (name.includes(term) || title.includes(term) || 
                staffExtension.includes(term) || staffRoom.includes(term) || deptMatches) {
                item.style.display = "block";
                sectionHasResults = true;
                visibleStaffCount++;
                if (!deptMatches) resultCount++; // Only count staff if dept didnt already match
            } else {
                item.style.display = "none";
            }
        });
        
        // Show/hide the department section
        if (sectionHasResults) {
            section.style.display = "block";
            hasResults = true;
            
            // Hide "no staff" message if there are visible staff
            const noStaffMsg = section.querySelector(".no-staff-message");
            if (noStaffMsg && visibleStaffCount > 0) {
                noStaffMsg.style.display = "none";
            } else if (noStaffMsg) {
                noStaffMsg.style.display = "block";
            }
        } else {
            section.style.display = "none";
        }
    });
    
    // Show/hide results
    showSearchResults(hasResults, resultCount, term);
}

function showSearchResults(hasResults, count, searchTerm) {
    const noResultsMsg = document.getElementById("noResultsMessage");
    const searchSummary = document.getElementById("searchSummary");
    const searchResultsText = document.getElementById("searchResultsText");
    const staffDirectory = document.getElementById("staffDirectory");
    
    if (hasResults) {
        noResultsMsg.classList.add("d-none");
        searchSummary.classList.remove("d-none");
        searchResultsText.textContent = `Found ${count} result${count !== 1 ? "s" : ""} for "${searchTerm}"`;
        if (staffDirectory) staffDirectory.style.display = "";
    } else {
        noResultsMsg.classList.remove("d-none");
        searchSummary.classList.add("d-none");
        if (staffDirectory) staffDirectory.style.display = "none";
    }
}

function clearSearch() {
    // Clear search input
    document.getElementById("searchInput").value = "";
    
    // Hide search-related elements
    document.getElementById("searchSummary").classList.add("d-none");
    document.getElementById("noResultsMessage").classList.add("d-none");
    
    if (originalContent && isSearchActive) {
        // Restore original content
        const staffDir = document.getElementById("staffDirectory");
        
        if (staffDir && originalContent.staffDirectory) {
            staffDir.innerHTML = originalContent.staffDirectory;
            staffDir.style.display = "";
        }
        
        isSearchActive = false;
    } else {
        // If no stored content, just show all elements
        const allItems = document.querySelectorAll(".dept-section, .staff-item");
        allItems.forEach(item => {
            item.style.display = "";
        });
        
        // Show all no-staff messages
        const noStaffMessages = document.querySelectorAll(".no-staff-message");
        noStaffMessages.forEach(msg => {
            msg.style.display = "";
        });
        
        // Show directory section
        const staffDirectory = document.getElementById("staffDirectory");
        if (staffDirectory) staffDirectory.style.display = "";
    }
}

// Add keyboard shortcut for search
document.addEventListener("keydown", function(e) {
    // Ctrl/Cmd + K to focus search
    if ((e.ctrlKey || e.metaKey) && e.key === "k") {
        e.preventDefault();
        document.getElementById("searchInput").focus();
    }
    
    // Escape to clear search
    if (e.key === "Escape" && isSearchActive) {
        clearSearch();
    }
});

// Auto-focus search input when typing (if not already focused on an input)
document.addEventListener("keydown", function(e) {
    const activeElement = document.activeElement;
    const isInputFocused = activeElement.tagName === "INPUT" || 
                          activeElement.tagName === "TEXTAREA" || 
                          activeElement.tagName === "SELECT";
    
    // If user starts typing and no input is focused, focus search
    if (!isInputFocused && e.key.match(/^[a-zA-Z0-9]$/)) {
        const searchInput = document.getElementById("searchInput");
        searchInput.focus();
        // Let the character be typed in the search box
    }
});
</script>';

include 'footer.php';
?>