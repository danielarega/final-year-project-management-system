<!-- includes/classes/DepartmentManager.php -->
<?php
class DepartmentManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    // Create new department
    public function createDepartment($data) {
        try {
            $query = "INSERT INTO departments (dept_code, dept_name, dept_type, created_by) 
                      VALUES (:dept_code, :dept_name, :dept_type, :created_by)";
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                ':dept_code' => strtoupper(trim($data['dept_code'])),
                ':dept_name' => trim($data['dept_name']),
                ':dept_type' => $data['dept_type'],
                ':created_by' => $data['created_by']
            ]);
            
            if ($result) {
                return ['success' => true, 'message' => 'Department created successfully', 'id' => $this->db->lastInsertId()];
            }
            
            return ['success' => false, 'message' => 'Failed to create department'];
            
        } catch (PDOException $e) {
            // Check if duplicate department code
            if ($e->errorInfo[1] == 1062) { // Duplicate entry
                return ['success' => false, 'message' => 'Department code already exists'];
            }
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    // Update department
    public function updateDepartment($id, $data) {
        try {
            $query = "UPDATE departments SET 
                      dept_code = :dept_code, 
                      dept_name = :dept_name, 
                      dept_type = :dept_type,
                      updated_at = NOW()
                      WHERE id = :id";
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                ':dept_code' => strtoupper(trim($data['dept_code'])),
                ':dept_name' => trim($data['dept_name']),
                ':dept_type' => $data['dept_type'],
                ':id' => $id
            ]);
            
            if ($result) {
                return ['success' => true, 'message' => 'Department updated successfully'];
            }
            
            return ['success' => false, 'message' => 'Failed to update department'];
            
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                return ['success' => false, 'message' => 'Department code already exists'];
            }
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    // Delete department
   // Delete department
// Delete department (Alternative with UNION fix)
public function deleteDepartment($id) {
    try {
        // Check if department has users
        $checkQuery = "SELECT COUNT(*) as count FROM admins WHERE department_id = :dept_id1 
                      UNION ALL 
                      SELECT COUNT(*) FROM teachers WHERE department_id = :dept_id2 
                      UNION ALL 
                      SELECT COUNT(*) FROM students WHERE department_id = :dept_id3
                      UNION ALL 
                      SELECT COUNT(*) FROM batches WHERE department_id = :dept_id4";
        
        $stmt = $this->db->prepare($checkQuery);
        $stmt->execute([
            ':dept_id1' => $id,
            ':dept_id2' => $id,
            ':dept_id3' => $id,
            ':dept_id4' => $id
        ]);
        $results = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        
        $totalUsers = array_sum($results);
        
        if ($totalUsers > 0) {
            return ['success' => false, 'message' => 'Cannot delete department with existing users, students, teachers, or batches'];
        }
        
        $query = "DELETE FROM departments WHERE id = :dept_id";
        $stmt = $this->db->prepare($query);
        $result = $stmt->execute([':dept_id' => $id]);
        
        if ($result) {
            return ['success' => true, 'message' => 'Department deleted successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to delete department'];
        
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}
    // Get all departments
    public function getAllDepartments($withStats = false) {
        try {
            if ($withStats) {
                $query = "SELECT d.*, 
                         (SELECT COUNT(*) FROM admins a WHERE a.department_id = d.id) as admin_count,
                         (SELECT COUNT(*) FROM teachers t WHERE t.department_id = d.id) as teacher_count,
                         (SELECT COUNT(*) FROM students s WHERE s.department_id = d.id) as student_count,
                         sa.full_name as created_by_name
                         FROM departments d
                         LEFT JOIN super_admins sa ON d.created_by = sa.id
                         ORDER BY d.dept_name";
            } else {
                $query = "SELECT d.*, sa.full_name as created_by_name 
                         FROM departments d
                         LEFT JOIN super_admins sa ON d.created_by = sa.id
                         ORDER BY d.dept_name";
            }
            
            $stmt = $this->db->query($query);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching departments: " . $e->getMessage());
            return [];
        }
    }
    
    // Get department by ID
    public function getDepartmentById($id) {
        try {
            $query = "SELECT d.*, sa.full_name as created_by_name 
                     FROM departments d
                     LEFT JOIN super_admins sa ON d.created_by = sa.id
                     WHERE d.id = :id";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([':id' => $id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching department: " . $e->getMessage());
            return null;
        }
    }
    
    // Get department heads (admins) for a department
    public function getDepartmentHeads($departmentId) {
        try {
            $query = "SELECT a.* FROM admins a 
                     WHERE a.department_id = :dept_id 
                     ORDER BY a.full_name";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([':dept_id' => $departmentId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching department heads: " . $e->getMessage());
            return [];
        }
    }
    
    // Assign department head
    public function assignDepartmentHead($departmentId, $adminId) {
        try {
            $query = "UPDATE admins SET department_id = :dept_id WHERE id = :admin_id";
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([':dept_id' => $departmentId, ':admin_id' => $adminId]);
            
            if ($result) {
                return ['success' => true, 'message' => 'Department head assigned successfully'];
            }
            
            return ['success' => false, 'message' => 'Failed to assign department head'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    // Get department statistics
    public function getDepartmentStatistics() {
        try {
            $query = "SELECT 
                     d.id, d.dept_code, d.dept_name,
                     COUNT(DISTINCT a.id) as admin_count,
                     COUNT(DISTINCT t.id) as teacher_count,
                     COUNT(DISTINCT s.id) as student_count,
                     COUNT(DISTINCT b.id) as batch_count
                     FROM departments d
                     LEFT JOIN admins a ON d.id = a.department_id
                     LEFT JOIN teachers t ON d.id = t.department_id
                     LEFT JOIN students s ON d.id = s.department_id
                     LEFT JOIN batches b ON d.id = b.department_id
                     GROUP BY d.id
                     ORDER BY d.dept_name";
            
            $stmt = $this->db->query($query);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching department statistics: " . $e->getMessage());
            return [];
        }
    }
}
?>