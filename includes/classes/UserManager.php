<?php
class UserManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    // Super Admin functions
    public function createDepartmentHead($data) {
        // Check if username or email already exists
        $checkQuery = "SELECT id FROM admins WHERE username = :username OR email = :email";
        $checkStmt = $this->db->prepare($checkQuery);
        $checkStmt->execute([':username' => $data['username'], ':email' => $data['email']]);
        
        if ($checkStmt->fetch()) {
            return ['success' => false, 'message' => 'Username or email already exists'];
        }
        
        // Create department head
        $query = "INSERT INTO admins (username, password, full_name, email, department_id, created_by) 
                  VALUES (:username, :password, :full_name, :email, :department_id, :created_by)";
        
        $stmt = $this->db->prepare($query);
        $result = $stmt->execute([
            ':username' => $data['username'],
            ':password' => password_hash($data['password'], PASSWORD_BCRYPT),
            ':full_name' => $data['full_name'],
            ':email' => $data['email'],
            ':department_id' => $data['department_id'],
            ':created_by' => $data['created_by']
        ]);
        
        if ($result) {
            return ['success' => true, 'message' => 'Department Head created successfully', 'id' => $this->db->lastInsertId()];
        }
        
        return ['success' => false, 'message' => 'Failed to create Department Head'];
    }
    /**
 * Add teacher specialization
 */
public function addTeacherSpecialization($teacherId, $specialization, $level) {
    try {
        // Check if specialization already exists for this teacher
        $checkQuery = "SELECT id FROM teacher_specializations 
                      WHERE teacher_id = :teacher_id 
                      AND LOWER(specialization) = LOWER(:specialization)";
        
        $checkStmt = $this->db->prepare($checkQuery);
        $checkStmt->execute([
            ':teacher_id' => $teacherId,
            ':specialization' => $specialization
        ]);
        
        if ($checkStmt->fetch()) {
            return ['success' => false, 'message' => 'This specialization already exists for this teacher'];
        }
        
        $query = "INSERT INTO teacher_specializations (teacher_id, specialization, level) 
                 VALUES (:teacher_id, :specialization, :level)";
        
        $stmt = $this->db->prepare($query);
        $result = $stmt->execute([
            ':teacher_id' => $teacherId,
            ':specialization' => $specialization,
            ':level' => $level
        ]);
        
        if ($result) {
            return ['success' => true, 'message' => 'Specialization added successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to add specialization'];
        
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Get teacher specializations
 */
public function getTeacherSpecializations($teacherId) {
    try {
        $query = "SELECT * FROM teacher_specializations 
                 WHERE teacher_id = :teacher_id 
                 ORDER BY level DESC, specialization";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([':teacher_id' => $teacherId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error fetching teacher specializations: " . $e->getMessage());
        return [];
    }
}

/**
 * Get teacher current load
 */
public function getTeacherCurrentLoad($teacherId) {
    try {
        $query = "SELECT COUNT(*) as count FROM projects 
                 WHERE supervisor_id = :teacher_id 
                 AND status IN ('approved', 'in_progress')";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([':teacher_id' => $teacherId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['count'] ?? 0;
        
    } catch (PDOException $e) {
        error_log("Error fetching teacher current load: " . $e->getMessage());
        return 0;
    }
}
    // Admin functions
    public function createTeacher($data) {
    
    $checkQuery = "SELECT id FROM teachers WHERE username = :username OR email = :email";
    $checkStmt = $this->db->prepare($checkQuery);
    $checkStmt->execute([':username' => $data['username'], ':email' => $data['email']]);
    
    if ($checkStmt->fetch()) {
        return ['success' => false, 'message' => 'Username or email already exists'];
    }
    
    $query = "INSERT INTO teachers (username, password, full_name, email, department_id, max_students, created_by) 
              VALUES (:username, :password, :full_name, :email, :department_id, :max_students, :created_by)";
    
    $stmt = $this->db->prepare($query);
    $result = $stmt->execute([
        ':username' => $data['username'],
        ':password' => password_hash($data['password'], PASSWORD_BCRYPT),
        ':full_name' => $data['full_name'],
        ':email' => $data['email'],
        ':department_id' => $data['department_id'],
        ':max_students' => $data['max_students'] ?? 5,
        ':created_by' => $data['created_by']
    ]);
    
    if ($result) {
        return ['success' => true, 'message' => 'Teacher created successfully', 'id' => $this->db->lastInsertId()];
    }
    
    return ['success' => false, 'message' => 'Failed to create teacher'];
}
    
   public function createStudent($data) {
    // Check if username or email already exists
    $checkQuery = "SELECT id FROM students WHERE username = :username OR email = :email";
    $checkStmt = $this->db->prepare($checkQuery);
    $checkStmt->execute([':username' => $data['username'], ':email' => $data['email']]);
    
    if ($checkStmt->fetch()) {
        return ['success' => false, 'message' => 'Username or email already exists'];
    }
    
    $query = "INSERT INTO students (username, password, full_name, email, department_id, batch_id, created_by) 
              VALUES (:username, :password, :full_name, :email, :department_id, :batch_id, :created_by)";
    
    $stmt = $this->db->prepare($query);
    $result = $stmt->execute([
        ':username' => $data['username'],
        ':password' => password_hash($data['password'], PASSWORD_BCRYPT),
        ':full_name' => $data['full_name'],
        ':email' => $data['email'],
        ':department_id' => $data['department_id'],
        ':batch_id' => $data['batch_id'] ?? null,
        ':created_by' => $data['created_by']
    ]);
    
    if ($result) {
        return ['success' => true, 'message' => 'Student created successfully', 'id' => $this->db->lastInsertId()];
    }
    
    return ['success' => false, 'message' => 'Failed to create student'];
}
    public function bulkImportStudents($filePath, $departmentId, $batchId, $adminId) {
        // This function would parse CSV/Excel file
        // For now, we'll create a placeholder
        return ['success' => true, 'message' => 'Bulk import functionality will be implemented in Phase 2'];
    }
    
    // Get all users by type
    public function getAllUsers($userType, $departmentId = null) {
        $tables = [
            'superadmin' => 'super_admins',
            'admin' => 'admins',
            'teacher' => 'teachers',
            'student' => 'students'
        ];
        
        $table = $tables[$userType];
        $query = "SELECT * FROM $table WHERE 1=1";
        $params = [];
        
        if ($departmentId && in_array($userType, ['admin', 'teacher', 'student'])) {
            $query .= " AND department_id = :department_id";
            $params[':department_id'] = $departmentId;
        }
        
        if ($userType === 'admin') {
            $query .= " ORDER BY department_id, full_name";
        } else {
            $query .= " ORDER BY full_name";
        }
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get user by ID
    public function getUserById($id, $userType) {
        $tables = [
            'superadmin' => 'super_admins',
            'admin' => 'admins',
            'teacher' => 'teachers',
            'student' => 'students'
        ];
        
        $table = $tables[$userType];
        $query = "SELECT * FROM $table WHERE id = :id";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([':id' => $id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Update user
    public function updateUser($id, $userType, $data) {
        $tables = [
            'superadmin' => 'super_admins',
            'admin' => 'admins',
            'teacher' => 'teachers',
            'student' => 'students'
        ];
        
        $table = $tables[$userType];
        
        // Build update query dynamically
        $query = "UPDATE $table SET ";
        $updates = [];
        $params = [':id' => $id];
        
        foreach ($data as $key => $value) {
            if ($key !== 'id') {
                $updates[] = "$key = :$key";
                $params[":$key"] = $value;
            }
        }
        
        $query .= implode(', ', $updates) . ", updated_at = NOW() WHERE id = :id";
        
        $stmt = $this->db->prepare($query);
        return $stmt->execute($params);
    }
    
    // Delete/Deactivate user
    public function toggleUserStatus($id, $userType, $status) {
        $tables = [
            'superadmin' => 'super_admins',
            'admin' => 'admins',
            'teacher' => 'teachers',
            'student' => 'students'
        ];
        
        $table = $tables[$userType];
        $query = "UPDATE $table SET status = :status, updated_at = NOW() WHERE id = :id";
        
        $stmt = $this->db->prepare($query);
        return $stmt->execute([':status' => $status, ':id' => $id]);
    }
    
    // Get statistics
    public function getUserStatistics($departmentId = null) {
        $stats = [];
        
        // Count by user type
        $query = "SELECT 'admins' as type, COUNT(*) as count FROM admins";
        if ($departmentId) {
            $query .= " WHERE department_id = :dept_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':dept_id' => $departmentId]);
        } else {
            $stmt = $this->db->query($query);
        }
        $stats['admins'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        $query = "SELECT 'teachers' as type, COUNT(*) as count FROM teachers";
        if ($departmentId) {
            $query .= " WHERE department_id = :dept_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':dept_id' => $departmentId]);
        } else {
            $stmt = $this->db->query($query);
        }
        $stats['teachers'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        $query = "SELECT 'students' as type, COUNT(*) as count FROM students";
        if ($departmentId) {
            $query .= " WHERE department_id = :dept_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':dept_id' => $departmentId]);
        } else {
            $stmt = $this->db->query($query);
        }
        $stats['students'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        return $stats;
    }
}