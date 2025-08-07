<?php
// config.php - Database configuration
class Database {
    private $host = 'localhost';
    private $db_name = 'gorio_directory';
    private $username = 'gorio_directory';
    private $password = 'USarmy2016!';
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4", $this->username, $this->password);
            
            // Set charset and collation for the connection
            $this->conn->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            $this->conn->exec("SET CHARACTER SET utf8mb4");
            $this->conn->exec("SET SESSION collation_connection = utf8mb4_unicode_ci");
            
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        return $this->conn;
    }
}

?>