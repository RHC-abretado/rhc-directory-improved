<?php
// cache_admin.php - Admin panel for cache management
require_once 'auth.php';
require_once 'config.php';
requireAdmin(); // Only admins can access this

class DirectoryCache {
    private $cacheFile = 'cache/directory_data.json';
    private $cacheMetaFile = 'cache/directory_meta.json';
    private $cacheDir = 'cache';
    private $cacheExpiry = 604800; // 7 days in seconds
    
    public function __construct() {
        // Ensure cache directory exists
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
    
    public function refreshCache() {
        try {
            $data = $this->fetchFromAPI();
            if ($data) {
                return $this->setCachedData($data);
            }
            return false;
        } catch (Exception $e) {
            error_log('Cache refresh error: ' . $e->getMessage());
            return false;
        }
    }
    
    private function fetchFromAPI() {
        $accessToken = getGraphAccessToken();
        if (!$accessToken) {
            throw new Exception('Unable to get Graph access token');
        }
        
        $graphClient = new GraphApiClient($accessToken);
        $users = $graphClient->getUsers();
        
        if (!$users) {
            throw new Exception('No users retrieved from Graph API');
        }
        
        return $this->processUsers($users);
    }
    
    private function processUsers($users) {
        $staff_list = [];
        $dept_set = [];
        
        foreach ($users as $user) {
            if (empty($user['department']) || 
                (isset($user['userType']) && $user['userType'] !== 'Member') ||
                (isset($user['accountEnabled']) && !$user['accountEnabled'])) {
                continue;
            }
            
            $staff = [
                'name' => trim(($user['givenName'] ?? '') . ' ' . ($user['surname'] ?? '')),
                'title' => $user['jobTitle'] ?? '',
                'department' => $user['department'] ?? '',
                'extension' => $this->extractExtension($user['businessPhones'] ?? []),
                'phone' => isset($user['businessPhones'][0]) ? $user['businessPhones'][0] : '',
                'email' => $user['mail'] ?? $user['userPrincipalName'] ?? '',
                'room_number' => $user['officeLocation'] ?? '',
                'building' => $this->extractBuilding($user['officeLocation'] ?? ''),
                'user_id' => $user['id'] ?? '',
                'display_name' => $user['displayName'] ?? ''
            ];
            
            if (!empty($staff['name']) && !empty($staff['department'])) {
                $staff_list[] = $staff;
                $dept_set[$staff['department']] = true;
            }
        }
        
        $departments = [];
        $dept_directory = [];
        
        foreach (array_keys($dept_set) as $dept_name) {
            $dept_info = $this->getDepartmentInfo($dept_name, $staff_list);
            $departments[] = $dept_info;
            $dept_directory[$dept_name] = $dept_info;
        }
        
        usort($staff_list, function($a, $b) {
            $deptCompare = strcmp($a['department'], $b['department']);
            if ($deptCompare === 0) {
                return strcmp($a['name'], $b['name']);
            }
            return $deptCompare;
        });
        
        usort($departments, function($a, $b) {
            return strcmp($a['department_name'], $b['department_name']);
        });
        
        return [
            'staff_list' => $staff_list,
            'departments' => $departments,
            'dept_directory' => $dept_directory,
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }
    
    private function extractExtension($phones) {
        if (empty($phones)) return '';
        
        foreach ($phones as $phone) {
            if (preg_match('/\b(\d{4}(?:\/\d{4})?)\b/', $phone, $matches)) {
                return $matches[1];
            }
            if (preg_match('/(?:ext\.?|x\.?)\s*(\d+)/i', $phone, $matches)) {
                return $matches[1];
            }
        }
        return '';
    }
    
    private function extractBuilding($officeLocation) {
        if (empty($officeLocation)) return '';
        
        if (preg_match('/^([A-Z]+)/', $officeLocation, $matches)) {
            return $matches[1];
        }
        return '';
    }
    
    private function getDepartmentInfo($deptName, $staffList) {
        $deptStaff = array_filter($staffList, function($staff) use ($deptName) {
            return $staff['department'] === $deptName;
        });
        
        $buildings = [];
        $extensions = [];
        
        foreach ($deptStaff as $staff) {
            if (!empty($staff['building'])) {
                $buildings[] = $staff['building'];
            }
            if (!empty($staff['extension'])) {
                $extensions[] = $staff['extension'];
            }
        }
        
        $building = !empty($buildings) ? array_count_values($buildings) : [];
        $extension = !empty($extensions) ? array_count_values($extensions) : [];
        
        return [
            'department_name' => $deptName,
            'extension' => !empty($extension) ? array_keys($extension, max($extension))[0] : '',
            'building' => !empty($building) ? array_keys($building, max($building))[0] : '',
            'room_number' => '',
            'staff_count' => count($deptStaff)
        ];
    }
    
    private function setCachedData($data) {
        try {
            file_put_contents($this->cacheFile, json_encode($data, JSON_PRETTY_PRINT));
            
            $meta = [
                'timestamp' => time(),
                'staff_count' => count($data['staff_list']),
                'department_count' => count($data['departments']),
                'last_updated' => date('Y-m-d H:i:s')
            ];
            file_put_contents($this->cacheMetaFile, json_encode($meta, JSON_PRETTY_PRINT));
            
            return true;
        } catch (Exception $e) {
            error_log('Cache write error: ' . $e->getMessage());
            return false;
        }
    }
}

class CacheAdmin {
    private $cacheFile = 'cache/directory_data.json';
    private $cacheMetaFile = 'cache/directory_meta.json';
    private $logFile = 'cache/directory_refresh.log';
    private $cacheDir = 'cache';
    
    public function getCacheStatus() {
        $status = [
            'cache_exists' => file_exists($this->cacheFile),
            'meta_exists' => file_exists($this->cacheMetaFile),
            'log_exists' => file_exists($this->logFile),
            'cache_size' => 0,
            'cache_age' => null,
            'meta_data' => null,
            'is_valid' => false
        ];
        
        if ($status['cache_exists']) {
            $status['cache_size'] = filesize($this->cacheFile);
            $status['cache_age'] = time() - filemtime($this->cacheFile);
        }
        
        if ($status['meta_exists']) {
            $metaContent = file_get_contents($this->cacheMetaFile);
            $status['meta_data'] = json_decode($metaContent, true);
            
            if ($status['meta_data'] && isset($status['meta_data']['timestamp'])) {
                $status['is_valid'] = (time() - $status['meta_data']['timestamp']) < 604800; // 7 days
            }
        }
        
        return $status;
    }
    
    public function getRecentLogs($lines = 50) {
        if (!file_exists($this->logFile)) {
            return [];
        }
        
        $content = file_get_contents($this->logFile);
        $logLines = array_filter(explode("\n", $content));
        
        // Return last N lines
        return array_slice($logLines, -$lines);
    }
    
    public function clearCache() {
        $files = [$this->cacheFile, $this->cacheMetaFile];
        $success = true;
        
        foreach ($files as $file) {
            if (file_exists($file) && !unlink($file)) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    public function getCacheStats() {
        $status = $this->getCacheStatus();
        
        $stats = [
            'cache_directory_exists' => is_dir($this->cacheDir),
            'cache_directory_writable' => is_writable($this->cacheDir),
            'php_version' => PHP_VERSION,
            'disk_space' => disk_free_space('.'),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time')
        ];
        
        return array_merge($status, $stats);
    }
}

$cacheAdmin = new CacheAdmin();
$message = '';
$messageType = 'info';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'refresh_cache':
                // Use the DirectoryCache class directly instead of including the cron script
                try {
                    require_once 'graph_config.php';
                    require_once 'graph_auth.php';
                    
                    // Include the GraphApiClient class
                    if (!class_exists('GraphApiClient')) {
                        class GraphApiClient {
                            private $accessToken;
                            private $baseUrl = 'https://graph.microsoft.com/v1.0';
                            
                            public function __construct($accessToken) {
                                $this->accessToken = $accessToken;
                            }
                            
                            public function getUsers($filter = null) {
                                $url = $this->baseUrl . '/users';
                                
                                $select = [
                                    'id', 'displayName', 'givenName', 'surname', 'mail',
                                    'userPrincipalName', 'jobTitle', 'department', 'businessPhones',
                                    'officeLocation', 'userType', 'accountEnabled'
                                ];
                                
                                $params = [
                                    '$select' => implode(',', $select),
                                    '$top' => 999
                                ];
                                
                                if ($filter) {
                                    $params['$filter'] = $filter;
                                }
                                
                                $url .= '?' . http_build_query($params);
                                
                                $allUsers = [];
                                $nextLink = $url;
                                
                                while ($nextLink) {
                                    $response = $this->makeRequest($nextLink);
                                    
                                    if (!$response || !isset($response['value'])) {
                                        break;
                                    }
                                    
                                    $allUsers = array_merge($allUsers, $response['value']);
                                    $nextLink = isset($response['@odata.nextLink']) ? $response['@odata.nextLink'] : null;
                                }
                                
                                return $allUsers;
                            }
                            
                            private function makeRequest($url) {
                                $headers = [
                                    'Authorization: Bearer ' . $this->accessToken,
                                    'Content-Type: application/json',
                                    'Accept: application/json'
                                ];
                                
                                $ch = curl_init();
                                curl_setopt_array($ch, [
                                    CURLOPT_URL => $url,
                                    CURLOPT_RETURNTRANSFER => true,
                                    CURLOPT_HTTPHEADER => $headers,
                                    CURLOPT_TIMEOUT => 30,
                                    CURLOPT_CONNECTTIMEOUT => 10,
                                    CURLOPT_SSL_VERIFYPEER => true,
                                    CURLOPT_USERAGENT => 'Staff-Directory/1.0'
                                ]);
                                
                                $response = curl_exec($ch);
                                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                                $error = curl_error($ch);
                                curl_close($ch);
                                
                                if ($error) {
                                    throw new Exception('cURL Error: ' . $error);
                                }
                                
                                if ($httpCode !== 200) {
                                    throw new Exception('HTTP Error ' . $httpCode . ': ' . $response);
                                }
                                
                                $decoded = json_decode($response, true);
                                if (json_last_error() !== JSON_ERROR_NONE) {
                                    throw new Exception('JSON Decode Error: ' . json_last_error_msg());
                                }
                                
                                return $decoded;
                            }
                        }
                    }
                    
                    // Create a simple cache refresh
                    $cache = new DirectoryCache();
                    if ($cache->refreshCache()) {
                        $message = 'Cache refresh completed successfully!';
                        $messageType = 'success';
                    } else {
                        $message = 'Cache refresh failed. Check the logs for details.';
                        $messageType = 'danger';
                    }
                } catch (Exception $e) {
                    $message = 'Cache refresh failed: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
                
            case 'clear_cache':
                if ($cacheAdmin->clearCache()) {
                    $message = 'Cache cleared successfully.';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to clear cache. Check file permissions.';
                    $messageType = 'danger';
                }
                break;
                
            case 'test_api':
                try {
                    require_once 'graph_config.php';
                    require_once 'graph_auth.php';
                    
                    $accessToken = getGraphAccessToken();
                    if ($accessToken) {
                        $message = 'Microsoft Graph API connection successful.';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to authenticate with Microsoft Graph API.';
                        $messageType = 'danger';
                    }
                } catch (Exception $e) {
                    $message = 'API test failed: ' . $e->getMessage();
                    $messageType = 'danger';
                }
                break;
        }
    }
}

$cacheStatus = $cacheAdmin->getCacheStatus();
$cacheStats = $cacheAdmin->getCacheStats();
$recentLogs = $cacheAdmin->getRecentLogs(30);

$page_title = 'Directory Cache Management - Admin Panel';
$current_page = 'cache';
include 'header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4><i class="fas fa-database"></i> Directory Cache Management</h4>
                <p class="mb-0">Manage Microsoft Graph directory caching system</p>
            </div>
            <div class="card-body">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Cache Status -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card border-<?php echo $cacheStatus['is_valid'] ? 'success' : 'warning'; ?>">
                            <div class="card-header bg-<?php echo $cacheStatus['is_valid'] ? 'success' : 'warning'; ?> text-white">
                                <h6 class="mb-0">
                                    <i class="fas fa-<?php echo $cacheStatus['is_valid'] ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                                    Cache Status
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-6"><strong>Status:</strong></div>
                                    <div class="col-6">
                                        <span class="badge bg-<?php echo $cacheStatus['is_valid'] ? 'success' : 'warning'; ?>">
                                            <?php echo $cacheStatus['is_valid'] ? 'Valid' : 'Expired/Missing'; ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <?php if ($cacheStatus['meta_data']): ?>
                                    <hr class="my-2">
                                    <div class="row">
                                        <div class="col-6"><strong>Last Updated:</strong></div>
                                        <div class="col-6"><?php echo htmlspecialchars($cacheStatus['meta_data']['last_updated']); ?></div>
                                    </div>
                                    <div class="row">
                                        <div class="col-6"><strong>Staff Count:</strong></div>
                                        <div class="col-6"><?php echo number_format($cacheStatus['meta_data']['staff_count']); ?></div>
                                    </div>
                                    <div class="row">
                                        <div class="col-6"><strong>Departments:</strong></div>
                                        <div class="col-6"><?php echo number_format($cacheStatus['meta_data']['department_count']); ?></div>
                                    </div>
                                    <div class="row">
                                        <div class="col-6"><strong>File Size:</strong></div>
                                        <div class="col-6"><?php echo round($cacheStatus['cache_size'] / 1024, 2); ?> KB</div>
                                    </div>
                                    <?php if ($cacheStatus['cache_age']): ?>
                                        <div class="row">
                                            <div class="col-6"><strong>Age:</strong></div>
                                            <div class="col-6">
                                                <?php 
                                                $hours = floor($cacheStatus['cache_age'] / 3600);
                                                if ($hours < 24) {
                                                    echo $hours . ' hours';
                                                } else {
                                                    echo floor($hours / 24) . ' days, ' . ($hours % 24) . ' hours';
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card border-info">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0"><i class="fas fa-cogs"></i> System Information</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-6"><strong>PHP Version:</strong></div>
                                    <div class="col-6"><?php echo $cacheStats['php_version']; ?></div>
                                </div>
                                <div class="row">
                                    <div class="col-6"><strong>Memory Limit:</strong></div>
                                    <div class="col-6"><?php echo $cacheStats['memory_limit']; ?></div>
                                </div>
                                <div class="row">
                                    <div class="col-6"><strong>Max Execution:</strong></div>
                                    <div class="col-6"><?php echo $cacheStats['max_execution_time']; ?>s</div>
                                </div>
                                <div class="row">
                                    <div class="col-6"><strong>Cache Directory:</strong></div>
                                    <div class="col-6">
                                        <span class="badge bg-<?php echo $cacheStats['cache_directory_writable'] ? 'success' : 'danger'; ?>">
                                            <?php echo $cacheStats['cache_directory_writable'] ? 'Writable' : 'Not Writable'; ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-6"><strong>Disk Space:</strong></div>
                                    <div class="col-6"><?php echo round($cacheStats['disk_space'] / (1024*1024*1024), 2); ?> GB</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6><i class="fas fa-tools"></i> Cache Actions</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="refresh_cache">
                                    <button type="submit" class="btn btn-primary w-100" onclick="return confirm('This will refresh the cache from Microsoft Graph. Continue?')">
                                        <i class="fas fa-sync"></i> Refresh Cache Now
                                    </button>
                                </form>
                                <small class="text-muted">Fetch fresh data from Microsoft Graph API</small>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="clear_cache">
                                    <button type="submit" class="btn btn-warning w-100" onclick="return confirm('This will delete all cached data. Continue?')">
                                        <i class="fas fa-trash"></i> Clear Cache
                                    </button>
                                </form>
                                <small class="text-muted">Delete all cached files</small>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="test_api">
                                    <button type="submit" class="btn btn-info w-100">
                                        <i class="fas fa-plug"></i> Test API Connection
                                    </button>
                                </form>
                                <small class="text-muted">Test Microsoft Graph connectivity</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cron Job Setup Instructions -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6><i class="fas fa-clock"></i> Automated Refresh Setup</h6>
                    </div>
                    <div class="card-body">
                        <p>To enable automatic weekly cache refresh, set up a cron job:</p>
                        
                        <div class="alert alert-info">
                            <h6>Cron Job Command:</h6>
                            <code>0 2 * * 0 /usr/bin/php <?php echo realpath('refresh_directory_cache.php'); ?></code>
                            <br><small class="text-muted">This runs every Sunday at 2:00 AM</small>
                        </div>
                        
                        <div class="accordion" id="cronInstructions">
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="cronSetup">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#cronSetupContent">
                                        <i class="fas fa-terminal me-2"></i> Setup Instructions
                                    </button>
                                </h2>
                                <div id="cronSetupContent" class="accordion-collapse collapse" data-bs-parent="#cronInstructions">
                                    <div class="accordion-body">
                                        <ol>
                                            <li><strong>Access your server's crontab:</strong><br>
                                                <code>crontab -e</code>
                                            </li>
                                            <li><strong>Add the cron job line:</strong><br>
                                                <code>0 2 * * 0 /usr/bin/php <?php echo realpath('refresh_directory_cache.php'); ?></code>
                                            </li>
                                            <li><strong>Save and exit the editor</strong></li>
                                            <li><strong>Verify the cron job:</strong><br>
                                                <code>crontab -l</code>
                                            </li>
                                        </ol>
                                        
                                        <h6>Alternative Schedules:</h6>
                                        <ul>
                                            <li><code>0 2 * * 1</code> - Every Monday at 2 AM</li>
                                            <li><code>0 2 */3 * *</code> - Every 3 days at 2 AM</li>
                                            <li><code>0 2 1 * *</code> - First day of every month at 2 AM</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Logs -->
                <div class="card">
                    <div class="card-header">
                        <h6><i class="fas fa-file-alt"></i> Recent Logs</h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentLogs)): ?>
                            <p class="text-muted">No logs available yet.</p>
                        <?php else: ?>
                            <div style="max-height: 400px; overflow-y: auto; background: #f8f9fa; padding: 1rem; border-radius: 0.375rem;">
                                <pre style="margin: 0; font-size: 0.875em;"><?php echo htmlspecialchars(implode("\n", $recentLogs)); ?></pre>
                            </div>
                            <div class="mt-2">
                                <small class="text-muted">
                                    Showing last 30 log entries. Full log: <code>cache/directory_refresh.log</code>
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$additional_scripts = '<script>
// Auto-refresh page every 30 seconds when cache refresh is in progress
if (window.location.search.includes("refresh_cache") || window.location.search.includes("cache_refreshed")) {
    setTimeout(function() {
        if (confirm("Refresh the page to see updated cache status?")) {
            window.location.reload();
        }
    }, 5000);
}

// Add loading states to buttons
document.querySelectorAll("button[type=submit]").forEach(button => {
    button.addEventListener("click", function(e) {
        const form = this.closest("form");
        if (form) {
            setTimeout(() => {
                this.disabled = true;
                this.innerHTML = "<i class=\"fas fa-spinner fa-spin\"></i> Processing...";
            }, 100);
        }
    });
});
</script>';

include 'footer.php';
?>