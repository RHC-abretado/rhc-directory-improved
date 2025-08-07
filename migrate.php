<?php
/**
 * Fixed migration script: JSON cache to optimized database
 * Handles duplicate department names and data inconsistencies
 */

require_once 'db_config.php';

class FixedDirectoryMigration {
    private $pdo;
    
    public function __construct($database) {
        $this->pdo = $database->getConnection();
    }
    
    /**
     * Complete migration process with duplicate handling
     */
    public function migrate() {
        echo "Starting migration with duplicate handling...\n";
        
        try {
            $this->createOptimizedTables();
            $this->migrateFromJsonCache();
            $this->createIndexes();
            $this->verifyMigration();
            
            echo "✅ Migration completed successfully!\n";
            return true;
            
        } catch (Exception $e) {
            echo "❌ Migration failed: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Create optimized tables for 2,500+ records
     */
    private function createOptimizedTables() {
        echo "Creating optimized database tables...\n";
        
        // Drop existing tables if they exist
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        $this->pdo->exec("DROP TABLE IF EXISTS staff");
        $this->pdo->exec("DROP TABLE IF EXISTS departments");
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        
        // Create departments table (removed UNIQUE constraint to handle duplicates)
        $this->pdo->exec("
            CREATE TABLE departments (
                id INT PRIMARY KEY AUTO_INCREMENT,
                department_name VARCHAR(255) NOT NULL,
                extension VARCHAR(20),
                building VARCHAR(100),
                room_number VARCHAR(50),
                staff_count INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                -- Basic indexes (no unique constraint)
                KEY idx_dept_name (department_name),
                KEY idx_extension (extension),
                KEY idx_building (building)
            ) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Create staff table optimized for search
        $this->pdo->exec("
            CREATE TABLE staff (
                id INT PRIMARY KEY AUTO_INCREMENT,
                department_id INT NOT NULL,
                user_id VARCHAR(100),
                name VARCHAR(255) NOT NULL,
                title VARCHAR(255),
                extension VARCHAR(20),
                phone VARCHAR(50),
                email VARCHAR(255),
                room_number VARCHAR(50),
                building VARCHAR(100),
                display_name VARCHAR(255),
                company_name VARCHAR(255) DEFAULT 'Rio Hondo College',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                -- Foreign key
                FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
                
                -- Essential indexes for 2,500 records
                KEY idx_name (name),
                KEY idx_department (department_id),
                KEY idx_extension (extension),
                KEY idx_email (email),
                KEY idx_room (room_number),
                KEY idx_title (title),
                
                -- Composite indexes for common queries
                KEY idx_dept_name (department_id, name),
                KEY idx_name_title (name(50), title(50))
                
            ) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        echo "✅ Tables created\n";
    }
    
    /**
     * Migrate data from existing JSON cache with deduplication
     */
    private function migrateFromJsonCache() {
        echo "Migrating data from JSON cache with deduplication...\n";
        
        // Read existing cache
        $cacheFile = 'cache/directory_data.json';
        if (!file_exists($cacheFile)) {
            throw new Exception("Cache file not found: $cacheFile");
        }
        
        $data = json_decode(file_get_contents($cacheFile), true);
        if (!$data) {
            throw new Exception("Could not parse JSON cache file");
        }
        
        $this->pdo->beginTransaction();
        
        try {
            // Migrate departments with deduplication
            $this->migrateDepartmentsWithDedup($data['departments'] ?? []);
            
            // Then migrate staff
            $this->migrateStaff($data['staff_list'] ?? []);
            
            // Update staff counts
            $this->updateStaffCounts();
            
            // Add unique constraint AFTER migration to prevent future duplicates
            $this->addUniqueConstraint();
            
            $this->pdo->commit();
            echo "✅ Data migration completed\n";
            
        } catch (Exception $e) {
            $this->pdo->rollback();
            throw $e;
        }
    }
    
    /**
     * Migrate departments with smart deduplication
     */
    private function migrateDepartmentsWithDedup($departments) {
        echo "Migrating departments with deduplication...\n";
        
        $cleanedDepartments = [];
        $duplicates = [];
        
        // First pass: clean and identify duplicates
        foreach ($departments as $dept) {
            $cleanName = $this->cleanDepartmentName($dept['department_name']);
            
            if (isset($cleanedDepartments[$cleanName])) {
                // Duplicate found - merge data
                $duplicates[] = $dept['department_name'];
                $existing = &$cleanedDepartments[$cleanName];
                
                // Use non-empty values from duplicate
                if (empty($existing['extension']) && !empty($dept['extension'])) {
                    $existing['extension'] = $dept['extension'];
                }
                if (empty($existing['building']) && !empty($dept['building'])) {
                    $existing['building'] = $dept['building'];
                }
                if (empty($existing['room_number']) && !empty($dept['room_number'])) {
                    $existing['room_number'] = $dept['room_number'];
                }
                
            } else {
                // New department
                $cleanedDepartments[$cleanName] = [
                    'department_name' => $cleanName,
                    'extension' => $dept['extension'] ?? null,
                    'building' => $dept['building'] ?? null,
                    'room_number' => $dept['room_number'] ?? null,
                    'original_name' => $dept['department_name']
                ];
            }
        }
        
        if (!empty($duplicates)) {
            echo "⚠️ Found duplicates that were merged:\n";
            foreach ($duplicates as $dup) {
                echo "   - '$dup'\n";
            }
        }
        
        // Insert cleaned departments
        $stmt = $this->pdo->prepare("
            INSERT INTO departments (department_name, extension, building, room_number) 
            VALUES (?, ?, ?, ?)
        ");
        
        $inserted = 0;
        foreach ($cleanedDepartments as $dept) {
            $stmt->execute([
                $dept['department_name'],
                $dept['extension'],
                $dept['building'],
                $dept['room_number']
            ]);
            $inserted++;
        }
        
        echo "✅ Migrated $inserted unique departments (merged " . count($duplicates) . " duplicates)\n";
    }
    
    /**
     * Clean department name for deduplication
     */
    private function cleanDepartmentName($name) {
        // Remove common variations
        $cleaned = trim($name);
        
        // Remove trailing " Dept" or " Department"
        $cleaned = preg_replace('/\s+(Dept|Department)$/i', '', $cleaned);
        
        // Remove extra whitespace
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);
        
        // Remove common prefixes/suffixes that might cause duplicates
        $cleaned = preg_replace('/\s*\([^)]*\)\s*$/', '', $cleaned); // Remove trailing (codes)
        
        return trim($cleaned);
    }
    
    private function migrateStaff($staffList) {
        echo "Migrating " . count($staffList) . " staff members...\n";
        
        // Get department mapping (including cleaned names)
        $deptMap = [];
        $deptStmt = $this->pdo->query("SELECT id, department_name FROM departments");
        while ($row = $deptStmt->fetch(PDO::FETCH_ASSOC)) {
            $deptMap[$row['department_name']] = $row['id'];
            // Also map cleaned version
            $cleaned = $this->cleanDepartmentName($row['department_name']);
            $deptMap[$cleaned] = $row['id'];
        }
        
        $stmt = $this->pdo->prepare("
            INSERT INTO staff (department_id, user_id, name, title, extension, phone, email, room_number, building, display_name, company_name) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $migrated = 0;
        $skipped = 0;
        
        foreach ($staffList as $staff) {
            // Try original name first, then cleaned name
            $deptId = $deptMap[$staff['department']] ?? 
                     $deptMap[$this->cleanDepartmentName($staff['department'])] ?? null;
            
            if (!$deptId) {
                echo "⚠️ Skipping staff member {$staff['name']} - department '{$staff['department']}' not found\n";
                $skipped++;
                continue;
            }
            
            $stmt->execute([
                $deptId,
                $staff['user_id'] ?? null,
                $staff['name'],
                $staff['title'] ?? null,
                $staff['extension'] ?? null,
                $staff['phone'] ?? null,
                $staff['email'] ?? null,
                $staff['room_number'] ?? null,
                $staff['building'] ?? null,
                $staff['display_name'] ?? $staff['name'],
                $staff['company_name'] ?? 'Rio Hondo College'
            ]);
            $migrated++;
        }
        
        echo "✅ Migrated $migrated staff members, skipped $skipped\n";
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
     * Add unique constraint after migration to prevent future duplicates
     */
    private function addUniqueConstraint() {
        echo "Adding unique constraint to prevent future duplicates...\n";
        try {
            $this->pdo->exec("ALTER TABLE departments ADD UNIQUE KEY uk_dept_name (department_name)");
            echo "✅ Unique constraint added\n";
        } catch (Exception $e) {
            echo "⚠️ Could not add unique constraint (duplicates may still exist): " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Create full-text search indexes (after data is loaded)
     */
    private function createIndexes() {
        echo "Creating full-text search indexes...\n";
        
        try {
            // Add full-text indexes for search performance
            $this->pdo->exec("ALTER TABLE staff ADD FULLTEXT idx_staff_search (name, title, email)");
            $this->pdo->exec("ALTER TABLE departments ADD FULLTEXT idx_dept_search (department_name, building)");
            
            echo "✅ Search indexes created\n";
        } catch (Exception $e) {
            echo "⚠️ Could not create full-text indexes: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Verify migration was successful
     */
    private function verifyMigration() {
        $deptCount = $this->pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();
        $staffCount = $this->pdo->query("SELECT COUNT(*) FROM staff")->fetchColumn();
        
        echo "\n📊 Migration Results:\n";
        echo "Departments: $deptCount\n";
        echo "Staff: $staffCount\n";
        
        // Check for any remaining duplicates
        $duplicateCheck = $this->pdo->query("
            SELECT department_name, COUNT(*) as count 
            FROM departments 
            GROUP BY department_name 
            HAVING COUNT(*) > 1
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($duplicateCheck)) {
            echo "⚠️ Remaining duplicates:\n";
            foreach ($duplicateCheck as $dup) {
                echo "   - '{$dup['department_name']}' ({$dup['count']} times)\n";
            }
        }
        
        // Test search performance
        $start = microtime(true);
        $results = $this->pdo->query("
            SELECT COUNT(*) FROM staff s 
            JOIN departments d ON s.department_id = d.id 
            WHERE s.name LIKE '%john%' OR s.title LIKE '%director%'
        ")->fetchColumn();
        $searchTime = round((microtime(true) - $start) * 1000, 2);
        
        echo "Search test: $results results in {$searchTime}ms\n";
        
        if ($deptCount >= 200 && $staffCount >= 2000) {
            echo "✅ Migration verification successful!\n";
        } else {
            echo "⚠️ Migration verification warning - counts seem low\n";
        }
    }
}

// Run migration
echo "Fixed Migration Script - Handles Duplicate Departments\n";
echo "================================================\n";

$database = new Database();
$migration = new FixedDirectoryMigration($database);
$migration->migrate();