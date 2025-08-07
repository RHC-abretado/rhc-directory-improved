<?php
/**
 * refresh_directory_cache.php - Cron job script for weekly directory refresh
 * 
 * This script should be run via cron job weekly to refresh the directory cache
 * Example cron entry (runs every Sunday at 2 AM):
 * 0 2 * * 0 /usr/bin/php /path/to/your/site/refresh_directory_cache.php
 */

// Set error reporting for CLI execution
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include required files
require_once __DIR__ . '/graph_config.php';
require_once __DIR__ . '/graph_auth.php';

// Class for logging
class CronLogger {
    private $logFile;
    
    public function __construct($logFile = 'cache/directory_refresh.log') {
        $this->logFile = $logFile;
        
        // Ensure log directory exists
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    public function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] [$level] $message" . PHP_EOL;
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        // Also output to console if running in CLI
        if (php_sapi_name() === 'cli') {
            echo $logEntry;
        }
    }
    
    public function error($message) {
        $this->log($message, 'ERROR');
    }
    
    public function success($message) {
        $this->log($message, 'SUCCESS');
    }
    
    public function warning($message) {
        $this->log($message, 'WARNING');
    }
}

// Enhanced DirectoryCache class for cron usage
class DirectoryCacheCron {
    private $cacheFile = 'cache/directory_data.json';
    private $cacheMetaFile = 'cache/directory_meta.json';
    private $cacheDir = 'cache';
    private $logger;
    
    public function __construct($logger) {
        $this->logger = $logger;
        
        // Ensure cache directory exists
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
            $this->logger->log("Created cache directory: {$this->cacheDir}");
        }
    }
    
    /**
     * Refresh cache with enhanced error handling and logging
     */
    public function refreshCache() {
        $this->logger->log("Starting directory cache refresh...");
        
        try {
            // Check current cache status
            $currentMeta = $this->getCacheMeta();
            if ($currentMeta) {
                $this->logger->log("Current cache: {$currentMeta['staff_count']} staff, {$currentMeta['department_count']} departments, last updated: {$currentMeta['last_updated']}");
            }
            
            // Fetch fresh data from API
            $startTime = microtime(true);
            $data = $this->fetchFromAPI();
            $fetchTime = round(microtime(true) - $startTime, 2);
            
            if (!$data) {
                throw new Exception('No data received from Microsoft Graph API');
            }
            
            $this->logger->log("Data fetched from Graph API in {$fetchTime} seconds");
            $this->logger->log("Retrieved {$data['staff_count']} staff members and {$data['department_count']} departments");
            
            // Store the data
            if ($this->setCachedData($data)) {
                $this->logger->success("Cache refresh completed successfully");
                
                // Compare with previous cache if available
                if ($currentMeta) {
                    $staffDiff = $data['staff_count'] - $currentMeta['staff_count'];
                    $deptDiff = $data['department_count'] - $currentMeta['department_count'];
                    
                    if ($staffDiff != 0 || $deptDiff != 0) {
                        $this->logger->log("Changes detected - Staff: {$staffDiff:+d}, Departments: {$deptDiff:+d}");
                    } else {
                        $this->logger->log("No changes in staff or department counts");
                    }
                }
                
                return true;
            } else {
                throw new Exception('Failed to write cache data to disk');
            }
            
        } catch (Exception $e) {
            $this->logger->error("Cache refresh failed: " . $e->getMessage());
            
            // Send email notification on failure (if configured)
            $this->sendFailureNotification($e->getMessage());
            
            return false;
        }
    }
    
    /**
     * Fetch fresh data from Microsoft Graph API
     */
    private function fetchFromAPI() {
        $this->logger->log("Authenticating with Microsoft Graph...");
        
        $accessToken = getGraphAccessToken();
        if (!$accessToken) {
            throw new Exception('Unable to authenticate with Microsoft Graph API');
        }
        
        $this->logger->log("Authentication successful, fetching users...");
        
        $graphClient = new GraphApiClient($accessToken);
        $users = $graphClient->getUsers();
        
        if (!$users) {
            throw new Exception('No users retrieved from Graph API');
        }
        
        $this->logger->log("Retrieved " . count($users) . " users from Graph API");
        
        return $this->processUsers($users);
    }
    
    /**
     * Process users data
     */
    private function processUsers($users) {
        $this->logger->log("Processing user data...");
        
        $staff_list = [];
        $dept_set = [];
        $skipped_users = 0;
        
        foreach ($users as $user) {
            // Skip users without department or who are not staff
            if (empty($user['department']) || 
                (isset($user['userType']) && $user['userType'] !== 'Member') ||
                (isset($user['accountEnabled']) && !$user['accountEnabled'])) {
                $skipped_users++;
                continue;
            }
            
            // Extract staff information
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
            
            // Only add if we have essential information
            if (!empty($staff['name']) && !empty($staff['department'])) {
                $staff_list[] = $staff;
                $dept_set[$staff['department']] = true;
            } else {
                $skipped_users++;
            }
        }
        
        $this->logger->log("Processed " . count($staff_list) . " valid staff members, skipped {$skipped_users} users");
        
        // Create department directory
        $departments = [];
        $dept_directory = [];
        
        foreach (array_keys($dept_set) as $dept_name) {
            $dept_info = $this->getDepartmentInfo($dept_name, $staff_list);
            $departments[] = $dept_info;
            $dept_directory[$dept_name] = $dept_info;
        }
        
        $this->logger->log("Created " . count($departments) . " department entries");
        
        // Sort staff and departments
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
            'generated_at' => date('Y-m-d H:i:s'),
            'staff_count' => count($staff_list),
            'department_count' => count($departments),
            'skipped_users' => $skipped_users
        ];
    }
    
    /**
     * Store data in cache
     */
    private function setCachedData($data) {
        try {
            // Store the data
            $jsonData = json_encode($data, JSON_PRETTY_PRINT);
            if ($jsonData === false) {
                throw new Exception('JSON encoding failed: ' . json_last_error_msg());
            }
            
            if (file_put_contents($this->cacheFile, $jsonData) === false) {
                throw new Exception('Failed to write cache file');
            }
            
            // Store metadata
            $meta = [
                'timestamp' => time(),
                'staff_count' => $data['staff_count'],
                'department_count' => $data['department_count'],
                'last_updated' => date('Y-m-d H:i:s'),
                'skipped_users' => $data['skipped_users'] ?? 0,
                'file_size' => filesize($this->cacheFile)
            ];
            
            $jsonMeta = json_encode($meta, JSON_PRETTY_PRINT);
            if ($jsonMeta === false) {
                throw new Exception('Metadata JSON encoding failed: ' . json_last_error_msg());
            }
            
            if (file_put_contents($this->cacheMetaFile, $jsonMeta) === false) {
                throw new Exception('Failed to write cache metadata file');
            }
            
            $this->logger->log("Cache files written successfully (size: " . round($meta['file_size'] / 1024, 2) . " KB)");
            
            return true;
        } catch (Exception $e) {
            $this->logger->error('Cache write error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get cache metadata
     */
    private function getCacheMeta() {
        if (!file_exists($this->cacheMetaFile)) {
            return null;
        }
        
        $content = file_get_contents($this->cacheMetaFile);
        if ($content === false) {
            return null;
        }
        
        return json_decode($content, true);
    }
    
    /**
     * Extract phone extension from business phones array
     */
    private function extractExtension($phones) {
        if (empty($phones)) return '';
        
        foreach ($phones as $phone) {
            // Look for extension patterns (4 digits, or 4/4 format)
            if (preg_match('/\b(\d{4}(?:\/\d{4})?)\b/', $phone, $matches)) {
                return $matches[1];
            }
            // Look for "ext" or "x" followed by digits
            if (preg_match('/(?:ext\.?|x\.?)\s*(\d+)/i', $phone, $matches)) {
                return $matches[1];
            }
        }
        
        return '';
    }
    
    /**
     * Extract building information from office location
     */
    private function extractBuilding($officeLocation) {
        if (empty($officeLocation)) return '';
        
        // Extract building part (letters before numbers)
        if (preg_match('/^([A-Z]+)/', $officeLocation, $matches)) {
            return $matches[1];
        }
        
        return '';
    }
    
    /**
     * Get department information aggregated from staff data
     */
    private function getDepartmentInfo($deptName, $staffList) {
        $deptStaff = array_filter($staffList, function($staff) use ($deptName) {
            return $staff['department'] === $deptName;
        });
        
        // Try to get common building and main extension for department
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
        
        // Get most common building and extension
        $building = !empty($buildings) ? array_count_values($buildings) : [];
        $extension = !empty($extensions) ? array_count_values($extensions) : [];
        
        return [
            'department_name' => $deptName,
            'extension' => !empty($extension) ? array_keys($extension, max($extension))[0] : '',
            'building' => !empty($building) ? array_keys($building, max($building))[0] : '',
            'room_number' => '', // Can be enhanced to extract common room prefix
            'staff_count' => count($deptStaff)
        ];
    }
    
    /**
     * Send failure notification email (configure as needed)
     */
    private function sendFailureNotification($errorMessage) {
        // Configure email settings as needed
        $adminEmail = 'admin@yourdomain.com'; // Change this to your admin email
        $subject = 'Directory Cache Refresh Failed';
        $message = "The weekly directory cache refresh failed with the following error:\n\n";
        $message .= "Error: $errorMessage\n";
        $message .= "Time: " . date('Y-m-d H:i:s') . "\n";
        $message .= "Server: " . gethostname() . "\n\n";
        $message .= "Please check the logs and resolve the issue.";
        
        $headers = "From: noreply@yourdomain.com\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        
        // Uncomment the next line to enable email notifications
        // mail($adminEmail, $subject, $message, $headers);
        
        $this->logger->log("Failure notification prepared (email sending disabled - configure if needed)");
    }
}

// Main execution
function main() {
    $logger = new CronLogger();
    $logger->log("=== Directory Cache Refresh Started ===");
    
    // Check if running in CLI mode (recommended for cron jobs)
    if (php_sapi_name() !== 'cli') {
        $logger->warning("Script is not running in CLI mode. For security, cron jobs should use CLI.");
    }
    
    try {
        $cache = new DirectoryCacheCron($logger);
        
        if ($cache->refreshCache()) {
            $logger->success("=== Directory Cache Refresh Completed Successfully ===");
            exit(0); // Success exit code
        } else {
            $logger->error("=== Directory Cache Refresh Failed ===");
            exit(1); // Error exit code
        }
        
    } catch (Exception $e) {
        $logger->error("Fatal error: " . $e->getMessage());
        $logger->error("=== Directory Cache Refresh Failed ===");
        exit(1); // Error exit code
    }
}

// Run the main function
main();
?>