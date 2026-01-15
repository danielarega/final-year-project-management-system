<!-- includes/classes/BatchManager.php -->
<?php
class BatchManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    // Create new batch
    public function createBatch($data) {
        try {
            // Check if batch with same name and year exists in department
            $checkQuery = "SELECT id FROM batches 
                          WHERE batch_name = :batch_name 
                          AND batch_year = :batch_year 
                          AND department_id = :department_id";
            
            $stmt = $this->db->prepare($checkQuery);
            $stmt->execute([
                ':batch_name' => trim($data['batch_name']),
                ':batch_year' => $data['batch_year'],
                ':department_id' => $data['department_id']
            ]);
            
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Batch already exists in this department'];
            }
            
            $query = "INSERT INTO batches (batch_name, batch_year, department_id, 
                      title_deadline, proposal_deadline, final_report_deadline, defense_deadline, created_by) 
                      VALUES (:batch_name, :batch_year, :department_id, 
                      :title_deadline, :proposal_deadline, :final_report_deadline, :defense_deadline, :created_by)";
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                ':batch_name' => trim($data['batch_name']),
                ':batch_year' => $data['batch_year'],
                ':department_id' => $data['department_id'],
                ':title_deadline' => !empty($data['title_deadline']) ? $data['title_deadline'] : null,
                ':proposal_deadline' => !empty($data['proposal_deadline']) ? $data['proposal_deadline'] : null,
                ':final_report_deadline' => !empty($data['final_report_deadline']) ? $data['final_report_deadline'] : null,
                ':defense_deadline' => !empty($data['defense_deadline']) ? $data['defense_deadline'] : null,
                ':created_by' => $data['created_by']
            ]);
            
            if ($result) {
                return ['success' => true, 'message' => 'Batch created successfully', 'id' => $this->db->lastInsertId()];
            }
            
            return ['success' => false, 'message' => 'Failed to create batch'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    // Update batch
    public function updateBatch($id, $data) {
        try {
            $query = "UPDATE batches SET 
                      batch_name = :batch_name,
                      batch_year = :batch_year,
                      title_deadline = :title_deadline,
                      proposal_deadline = :proposal_deadline,
                      final_report_deadline = :final_report_deadline,
                      defense_deadline = :defense_deadline,
                      updated_at = NOW()
                      WHERE id = :id";
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                ':batch_name' => trim($data['batch_name']),
                ':batch_year' => $data['batch_year'],
                ':title_deadline' => !empty($data['title_deadline']) ? $data['title_deadline'] : null,
                ':proposal_deadline' => !empty($data['proposal_deadline']) ? $data['proposal_deadline'] : null,
                ':final_report_deadline' => !empty($data['final_report_deadline']) ? $data['final_report_deadline'] : null,
                ':defense_deadline' => !empty($data['defense_deadline']) ? $data['defense_deadline'] : null,
                ':id' => $id
            ]);
            
            if ($result) {
                return ['success' => true, 'message' => 'Batch updated successfully'];
            }
            
            return ['success' => false, 'message' => 'Failed to update batch'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    // Delete batch
    public function deleteBatch($id) {
        try {
            // Check if batch has students
            $checkQuery = "SELECT COUNT(*) as count FROM students WHERE batch_id = :id";
            $stmt = $this->db->prepare($checkQuery);
            $stmt->execute([':id' => $id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['count'] > 0) {
                return ['success' => false, 'message' => 'Cannot delete batch with assigned students'];
            }
            
            $query = "DELETE FROM batches WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([':id' => $id]);
            
            if ($result) {
                return ['success' => true, 'message' => 'Batch deleted successfully'];
            }
            
            return ['success' => false, 'message' => 'Failed to delete batch'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    // Get all batches for a department
    public function getBatchesByDepartment($departmentId, $withStats = false) {
        try {
            if ($withStats) {
                $query = "SELECT b.*, 
                         (SELECT COUNT(*) FROM students s WHERE s.batch_id = b.id) as student_count,
                         a.full_name as created_by_name
                         FROM batches b
                         LEFT JOIN admins a ON b.created_by = a.id
                         WHERE b.department_id = :dept_id
                         ORDER BY b.batch_year DESC, b.batch_name";
            } else {
                $query = "SELECT b.*, a.full_name as created_by_name 
                         FROM batches b
                         LEFT JOIN admins a ON b.created_by = a.id
                         WHERE b.department_id = :dept_id
                         ORDER BY b.batch_year DESC, b.batch_name";
            }
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([':dept_id' => $departmentId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching batches: " . $e->getMessage());
            return [];
        }
    }
    
    // Get batch by ID
    public function getBatchById($id) {
        try {
            $query = "SELECT b.*, d.dept_name, a.full_name as created_by_name 
                     FROM batches b
                     LEFT JOIN departments d ON b.department_id = d.id
                     LEFT JOIN admins a ON b.created_by = a.id
                     WHERE b.id = :id";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([':id' => $id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching batch: " . $e->getMessage());
            return null;
        }
    }
    
    // Get all batches for Super Admin
    public function getAllBatches($withStats = false) {
        try {
            if ($withStats) {
                $query = "SELECT b.*, d.dept_name,
                         (SELECT COUNT(*) FROM students s WHERE s.batch_id = b.id) as student_count,
                         a.full_name as created_by_name
                         FROM batches b
                         LEFT JOIN departments d ON b.department_id = d.id
                         LEFT JOIN admins a ON b.created_by = a.id
                         ORDER BY b.batch_year DESC, d.dept_name, b.batch_name";
            } else {
                $query = "SELECT b.*, d.dept_name, a.full_name as created_by_name 
                         FROM batches b
                         LEFT JOIN departments d ON b.department_id = d.id
                         LEFT JOIN admins a ON b.created_by = a.id
                         ORDER BY b.batch_year DESC, d.dept_name, b.batch_name";
            }
            
            $stmt = $this->db->query($query);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching all batches: " . $e->getMessage());
            return [];
        }
    }
    
    // Assign student to batch
    public function assignStudentToBatch($studentId, $batchId) {
        try {
            $query = "UPDATE students SET batch_id = :batch_id WHERE id = :student_id";
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([':batch_id' => $batchId, ':student_id' => $studentId]);
            
            if ($result) {
                return ['success' => true, 'message' => 'Student assigned to batch successfully'];
            }
            
            return ['success' => false, 'message' => 'Failed to assign student to batch'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    // Bulk assign students to batch
    public function bulkAssignStudentsToBatch($studentIds, $batchId) {
        try {
            $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
            
            $query = "UPDATE students SET batch_id = ? WHERE id IN ($placeholders)";
            $params = array_merge([$batchId], $studentIds);
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute($params);
            
            if ($result) {
                $count = $stmt->rowCount();
                return ['success' => true, 'message' => "Assigned $count students to batch successfully"];
            }
            
            return ['success' => false, 'message' => 'Failed to assign students to batch'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    // Get students in a batch
    public function getStudentsInBatch($batchId) {
        try {
            $query = "SELECT s.*, d.dept_name 
                     FROM students s
                     LEFT JOIN departments d ON s.department_id = d.id
                     WHERE s.batch_id = :batch_id
                     ORDER BY s.full_name";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([':batch_id' => $batchId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching batch students: " . $e->getMessage());
            return [];
        }
    }
    
    // Get students not assigned to any batch in a department
    public function getUnassignedStudents($departmentId) {
        try {
            $query = "SELECT s.*, d.dept_name 
                     FROM students s
                     LEFT JOIN departments d ON s.department_id = d.id
                     WHERE s.department_id = :dept_id 
                     AND (s.batch_id IS NULL OR s.batch_id = 0)
                     ORDER BY s.full_name";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([':dept_id' => $departmentId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching unassigned students: " . $e->getMessage());
            return [];
        }
    }
    
    // Get batch statistics
    public function getBatchStatistics($departmentId = null) {
        try {
            $whereClause = $departmentId ? "WHERE b.department_id = :dept_id" : "";
            $params = $departmentId ? [':dept_id' => $departmentId] : [];
            
            $query = "SELECT 
                     b.id, b.batch_name, b.batch_year, d.dept_name,
                     COUNT(s.id) as student_count,
                     SUM(CASE WHEN s.batch_id IS NOT NULL THEN 1 ELSE 0 END) as assigned_count,
                     b.title_deadline, b.proposal_deadline, b.final_report_deadline
                     FROM batches b
                     LEFT JOIN departments d ON b.department_id = d.id
                     LEFT JOIN students s ON b.id = s.batch_id
                     $whereClause
                     GROUP BY b.id
                     ORDER BY b.batch_year DESC, b.batch_name";
            
            $stmt = $departmentId ? 
                   $this->db->prepare($query) : 
                   $this->db->query($query);
                   
            if ($departmentId) {
                $stmt->execute($params);
            }
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching batch statistics: " . $e->getMessage());
            return [];
        }
    }
    
    // Import students from CSV/Excel (simplified version)
    public function importStudentsFromCSV($filePath, $departmentId, $batchId, $adminId) {
        // This is a simplified version. In production, you would use a CSV parsing library
        return ['success' => true, 'message' => 'CSV import functionality will be enhanced in future version'];
    }
}
?>