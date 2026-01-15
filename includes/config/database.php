<!-- config/database.php -->
<?php
class Database {
    private $host = "localhost";
    private $db_name = "fypms_db";
    private $username = "root";
    private $password = "";
    public $conn;
    
    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8");
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        return $this->conn;
    }
}
?>

<!-- config/constants.php -->
<?php
// Base URL
define('BASE_URL', 'http://localhost/fypms/');

// Upload paths
define('UPLOAD_DIR', $_SERVER['DOCUMENT_ROOT'] . '/fypms/assets/uploads/');
define('UPLOAD_URL', BASE_URL . 'assets/uploads/');

// File size limits (in bytes)
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50MB

// Allowed file types
define('ALLOWED_TYPES', [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/zip',
    'application/x-zip-compressed'
]);

// User roles
define('ROLE_SUPERADMIN', 1);
define('ROLE_ADMIN', 2);
define('ROLE_TEACHER', 3);
define('ROLE_STUDENT', 4);
?>