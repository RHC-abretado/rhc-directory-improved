<?php
/**
 * database_directory.php
 * Database-based directory implementation optimized for fast searches with proper indexing
 */

class DatabaseDirectory {
    private $pdo;
    private $cacheExpiry = 900; // 15 minutes for query result caching
    
    public function __construct($database) {
        $this->pdo = $database->getConnection();
        
        // Optimize MySQL for read performance
        try {
            $this->pdo->exec("SET SESSION query_cache_type = OFF");
            $this->pdo->exec("SET SESSION query_cache_size = 67108864"); // 64MB
        } catch (Exception $e) {
            // Query cache settings might not be available - continue anyway
            error_log('Could not set query cache: ' . $e->getMessage());
        }
    }
    
    /**
     * Get all staff with optional filtering
     * Uses indexes for optimal performance
     */
    public function getStaff($filters = []) {
        $cacheKey = 'staff_' . md5(serialize($filters));
        
        // Check if we have cached results
        if ($cached = $this->getFromCache($cacheKey)) {
            return $cached;
        }
        
        // Use direct table joins instead of view for compatibility
        $sql = "
            SELECT 
                s.id,
                s.name,
                s.title,
                s.extension,
                s.phone,
                s.email,
                s.room_number,
                s.building,
                s.is_department_head,
                d.department_name,
                d.extension as dept_extension,
                d.building as dept_building,
                d.room_number as dept_room
            FROM staff s
            JOIN departments d ON s.department_id = d.id
            WHERE 1=1
        ";
        $params = [];
        
        // Add filters with indexed columns
        if (!empty($filters['department'])) {
            $sql .= " AND d.department_name = :department";
            $params[':department'] = $filters['department'];
        }
        
        if (!empty($filters['search'])) {
            // Use LIKE search for compatibility (full-text may not be available)
            $sql .= " AND (
                s.name LIKE :search_like 
                OR s.title LIKE :search_like
                OR s.email LIKE :search_like
                OR s.extension LIKE :search_like
                OR s.room_number LIKE :search_like
                OR d.department_name LIKE :search_like
            )";
            $params[':search_like'] = '%' . $filters['search'] . '%';
        }
        
        if (!empty($filters['building'])) {
            $sql .= " AND (s.building = :building OR d.building = :building)";
            $params[':building'] = $filters['building'];
        }
        
        // Always order by department, then name for consistent results
        $sql .= " ORDER BY d.department_name, s.is_department_head DESC, s.name";
        
        // Add limit for large datasets
        if (isset($filters['limit'])) {
            $sql .= " LIMIT " . (int)$filters['limit'];
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Cache results
        $this->setCache($cacheKey, $results);
        
        return $results;
    }
    
    /**
     * Get departments with staff counts
     * Optimized with aggregation
     */
    public function getDepartments() {
        $cacheKey = 'departments_with_counts';
        
        if ($cached = $this->getFromCache($cacheKey)) {
            return $cached;
        }
        
        $sql = "
            SELECT 
                d.*,
                COALESCE(d.staff_count, 0) as staff_count
            FROM departments d
            WHERE d.staff_count > 0 OR d.staff_count IS NULL
            ORDER BY d.department_name
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $this->setCache($cacheKey, $results);
        return $results;
    }
    
    /**
     * Fast search across all fields
     * Uses indexes for performance
     */
    public function search($query, $limit = 100) {
        $cacheKey = 'search_' . md5($query) . '_' . $limit;
        
        if ($cached = $this->getFromCache($cacheKey)) {
            return $cached;
        }
        
        // Simple but effective search
        $sql = "
            SELECT 
                s.name,
                s.title,
                s.extension,
                s.phone,
                s.email,
                s.room_number,
                s.building,
                d.department_name,
                d.extension as dept_extension,
                d.building as dept_building,
                d.room_number as dept_room,
                CASE 
                    WHEN s.name LIKE :exact_query THEN 100
                    WHEN s.extension = :query THEN 90
                    WHEN s.name LIKE :query_start THEN 80
                    WHEN s.title LIKE :query_start THEN 70
                    ELSE 10
                END as relevance
            FROM staff s
            JOIN departments d ON s.department_id = d.id
            WHERE s.name LIKE :query_like 
                OR s.title LIKE :query_like
                OR s.email LIKE :query_like
                OR s.extension LIKE :query_like
                OR s.room_number LIKE :query_like
                OR d.department_name LIKE :query_like
            ORDER BY relevance DESC, d.department_name, s.name
            LIMIT :limit
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':query', $query, PDO::PARAM_STR);
        $stmt->bindValue(':exact_query', $query, PDO::PARAM_STR);
        $stmt->bindValue(':query_start', $query . '%', PDO::PARAM_STR);
        $stmt->bindValue(':query_like', '%' . $query . '%', PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $this->setCache($cacheKey, $results);
        return $results;
    }
    
    /**
     * Check if database tables exist and have data
     */
    public function isReady() {
        try {
            $tables = ['departments', 'staff'];
            foreach ($tables as $table) {
                $stmt = $this->pdo->query("SELECT COUNT(*) FROM $table");
                $count = $stmt->fetchColumn();
                if ($count == 0) {
                    return false;
                }
            }
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get basic statistics
     */
    public function getStats() {
        $sql = "
            SELECT 
                (SELECT COUNT(*) FROM departments) as department_count,
                (SELECT COUNT(*) FROM staff) as staff_count,
                (SELECT COUNT(DISTINCT building) FROM staff WHERE building IS NOT NULL) as building_count
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Refresh data from Microsoft Graph API
     * Batch insert for optimal performance
     */
    public function refreshFromAPI() {
        try {
            $this->pdo->beginTransaction();
            
            // Get fresh data from Graph API
            $accessToken = getGraphAccessToken();
            if (!$accessToken) {
                throw new Exception('Unable to authenticate with Microsoft Graph API');
            }
            
            $graphClient = new GraphApiClient($accessToken);
            $users = $graphClient->getUsers();
            
            if (!$users) {
                throw new Exception('No users retrieved from Graph API');
            }
            
            // Process users using existing logic
            $processedData = $this->processUsers($users);
            
            // Clear existing data
            $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            $this->pdo->exec("DELETE FROM staff");
            $this->pdo->exec("DELETE FROM departments");
            $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            
            // Batch insert departments
            $this->batchInsertDepartments($processedData['departments']);
            
            // Batch insert staff
            $this->batchInsertStaff($processedData['staff_list']);
            
            // Update staff counts
            $this->updateStaffCounts();
            
            // Clear all caches
            $this->clearAllCaches();
            
            $this->pdo->commit();
            return [
                'success' => true,
                'staff_count' => count($processedData['staff_list']),
                'department_count' => count($processedData['departments'])
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollback();
            error_log('Database refresh failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Process users from Graph API (using your existing logic)
     */
    private function processUsers($users) {
        $staff_list = [];
        $dept_set = [];
        
        foreach ($users as $user) {
            // Skip invalid users
            if (empty($user['department']) || 
                (isset($user['userType']) && $user['userType'] !== 'Member') ||
                (isset($user['accountEnabled']) && !$user['accountEnabled']) ||
                (isset($user['companyName']) && $user['companyName'] !== 'Rio Hondo College')) {
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
                'display_name' => $user['displayName'] ?? '',
                'company_name' => $user['companyName'] ?? 'Rio Hondo College'
            ];
            
            if (!empty($staff['name']) && !empty($staff['department'])) {
                $staff_list[] = $staff;
                $dept_set[$staff['department']] = true;
            }
        }
        
        // Create department directory
        $departments = [];
        foreach (array_keys($dept_set) as $dept_name) {
            $dept_info = $this->getDepartmentInfo($dept_name, $staff_list);
            $departments[] = $dept_info;
        }
        
        // Sort data
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
            'departments' => $departments
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
    
    /**
     * Batch insert departments for performance
     */
    private function batchInsertDepartments($departments) {
        if (empty($departments)) return;
        
        $sql = "INSERT INTO departments (department_name, extension, building, room_number, staff_count) VALUES ";
        $values = [];
        $params = [];
        
        foreach ($departments as $i => $dept) {
            $values[] = "(?, ?, ?, ?, ?)";
            $params[] = $dept['department_name'];
            $params[] = $dept['extension'];
            $params[] = $dept['building'];
            $params[] = $dept['room_number'];
            $params[] = $dept['staff_count'];
        }
        
        $sql .= implode(', ', $values);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }
    
    /**
     * Batch insert staff for performance
     */
    private function batchInsertStaff($staffList) {
        if (empty($staffList)) return;
        
        // Get department ID mapping
        $deptMap = [];
        $deptStmt = $this->pdo->query("SELECT id, department_name FROM departments");
        while ($row = $deptStmt->fetch(PDO::FETCH_ASSOC)) {
            $deptMap[$row['department_name']] = $row['id'];
        }
        
        $sql = "INSERT INTO staff (department_id, user_id, name, title, extension, phone, email, room_number, building, is_department_head, display_name, company_name) VALUES ";
        $values = [];
        $params = [];
        
        foreach ($staffList as $staff) {
            $deptId = $deptMap[$staff['department']] ?? null;
            if (!$deptId) continue; // Skip if department not found
            
            $values[] = "(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $params[] = $deptId;
            $params[] = $staff['user_id'];
            $params[] = $staff['name'];
            $params[] = $staff['title'];
            $params[] = $staff['extension'];
            $params[] = $staff['phone'];
            $params[] = $staff['email'];
            $params[] = $staff['room_number'];
            $params[] = $staff['building'];
            $params[] = !empty($staff['is_department_head']) ? 1 : 0;
            $params[] = $staff['display_name'];
            $params[] = $staff['company_name'];
        }
        
        if (!empty($values)) {
            $sql .= implode(', ', $values);
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        }
    }
    
    private function updateStaffCounts() {
        $this->pdo->exec("
            UPDATE departments d 
            SET staff_count = (
                SELECT COUNT(*) FROM staff s WHERE s.department_id = d.id
            )
        ");
    }
    
    /**
     * Simple memory-based caching for query results
     */
    private function getFromCache($key) {
        static $cache = [];
        return $cache[$key] ?? null;
    }
    
    private function setCache($key, $data) {
        static $cache = [];
        $cache[$key] = $data;
    }
    
    private function clearAllCaches() {
        static $cache = [];
        $cache = [];
    }
}
?>