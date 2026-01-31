<!-- includes/classes/Auth.php -->
<?php
class Auth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function login($username, $password, $userType) {
        // Debug: Check if function is called
        error_log("Login attempt: $username, $userType");
        
        // Determine table based on user type
        $tables = [
            'superadmin' => 'super_admins',
            'admin' => 'admins',
            'teacher' => 'teachers',
            'student' => 'students'
        ];
        
        if (!isset($tables[$userType])) {
            return ['success' => false, 'message' => 'Invalid user type'];
        }
        
        $table = $tables[$userType];
        
        // Debug: Check table name
        error_log("Table: $table");
        
        try {
            // Prepare query - use the same column name for all tables
            $query = "SELECT * FROM $table WHERE username = :username OR email = :email";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':username' => $username,
                ':email' => $username
            ]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return ['success' => false, 'message' => 'User not found'];
            }
            
            // Check if user is active
            if (isset($user['status']) && $user['status'] !== 'active') {
                return ['success' => false, 'message' => 'Account is inactive'];
            }
            
            // Verify password - for testing, accept "password" for all users
            $testPassword = 'password'; // Default test password
            
            // For all our test users, the password is "password"
            if ($password === $testPassword) {
                // Allow test password to pass
            } elseif (!password_verify($password, $user['password'])) {
                $this->logActivity($username, 'Failed login attempt - wrong password');
                return ['success' => false, 'message' => 'Invalid password'];
            }
            
            // Update last login
            $this->updateLastLogin($table, $user['id']);
            
            // Create session data
            $sessionData = [
                'user_id' => $user['id'],
                'username' => $user['username'],
                'full_name' => $user['full_name'],
                'email' => $user['email'],
                'user_type' => $userType,
                'logged_in' => true,
                  'role_id' => 0 
            ];
            
            // Add role-specific data
           if (in_array($userType, ['admin', 'teacher', 'student'])) {
    if (isset($user['department_id'])) {
        $sessionData['department_id'] = $user['department_id'];
    }
    if ($userType === 'student' && isset($user['batch_id'])) {
        $sessionData['batch_id'] = $user['batch_id'];
    }
}
            
            // Start session
            Session::set('user', $sessionData);
            
            // Log successful login
            $this->logActivity($username, 'Logged in successfully');
            
            return ['success' => true, 'data' => $sessionData];
            
        } catch (PDOException $e) {
            error_log("Database error in login: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    private function updateLastLogin($table, $userId) {
        try {
            $query = "UPDATE $table SET last_login = NOW() WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':id' => $userId]);
        } catch (PDOException $e) {
            error_log("Error updating last login: " . $e->getMessage());
        }
    }
    
    private function logActivity($username, $action) {
        try {
            $user = Session::get('user');
            $userId = $user ? $user['username'] : $username;
            $userType = $user ? $user['user_type'] : 'guest';
            
            $query = "INSERT INTO activity_logs (user_id, user_type, action, ip_address) 
                      VALUES (:user_id, :user_type, :action, :ip)";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':user_id' => $userId,
                ':user_type' => $userType,
                ':action' => $action,
                ':ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            ]);
        } catch (PDOException $e) {
            error_log("Error logging activity: " . $e->getMessage());
        }
    }
    
    public function logout() {
        $user = Session::get('user');
        if ($user) {
            $this->logActivity($user['username'], 'Logged out');
        }
        Session::destroy();
    }
    
    public function isLoggedIn() {
        $user = Session::get('user');
        return !empty($user) && isset($user['logged_in']) && $user['logged_in'] === true;
    }
    
    public function getUser() {
        return Session::get('user');
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: ' . BASE_URL);
            exit();
        }
    }
    
    public function requireRole($roles) {
        if (!$this->isLoggedIn()) {
            header('Location: ' . BASE_URL);
            exit();
        }
        
        $user = $this->getUser();
        if (!in_array($user['user_type'], (array)$roles)) {
            header('Location: ' . BASE_URL . 'dashboard.php');
            exit();
        }
    }
    
    // Password reset functions (simplified for now)
    public function requestPasswordReset($email, $userType) {
        // For development, just return success
        return ['success' => true, 'message' => 'Password reset link has been sent (demo mode)'];
    }
    
    public function changePassword($currentPassword, $newPassword, $userId, $userType) {
        // For development
        return ['success' => true, 'message' => 'Password changed successfully (demo mode)'];
    }
}
?>