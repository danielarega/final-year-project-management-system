<!-- C:\xampp\htdocs\fypms\final-year-project-management-system\includes\classes\BatchManager.php (UPDATED) -->
<?php
class BatchManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Create new batch with dual-semester support
     */
    public function createBatchWithSemesters($data) {
        try {
            $this->db->beginTransaction();
            
            // Create main batch
            $batchQuery = "INSERT INTO batches (batch_name, batch_year, academic_year, department_id, 
                          semester_type, current_semester, created_by) 
                          VALUES (:batch_name, :batch_year, :academic_year, :department_id, 
                          :semester_type, :current_semester, :created_by)";
            
            $batchStmt = $this->db->prepare($batchQuery);
            $batchStmt->execute([
                ':batch_name' => trim($data['batch_name']),
                ':batch_year' => $data['batch_year'],
                ':academic_year' => $data['academic_year'] ?? $data['batch_year'] . '/' . ($data['batch_year'] + 1),
                ':department_id' => $data['department_id'],
                ':semester_type' => $data['semester_type'] ?? 'dual',
                ':current_semester' => 1,
                ':created_by' => $data['created_by']
            ]);
            
            $batchId = $this->db->lastInsertId();
            
            // Create semesters if dual-semester type
            if (($data['semester_type'] ?? 'dual') === 'dual') {
                // Semester 1: Proposal & Documentation
                $semester1Query = "INSERT INTO academic_semesters 
                                 (batch_id, semester_number, semester_name, 
                                  title_deadline, proposal_deadline, documentation_deadline) 
                                 VALUES (:batch_id, 1, 'Semester 1: Proposal & Documentation',
                                  :title_deadline, :proposal_deadline, :documentation_deadline)";
                
                $sem1Stmt = $this->db->prepare($semester1Query);
                $sem1Stmt->execute([
                    ':batch_id' => $batchId,
                    ':title_deadline' => $data['semester1']['title_deadline'] ?? null,
                    ':proposal_deadline' => $data['semester1']['proposal_deadline'] ?? null,
                    ':documentation_deadline' => $data['semester1']['documentation_deadline'] ?? null
                ]);
                
                // Semester 2: Implementation & Defense
                $semester2Query = "INSERT INTO academic_semesters 
                                 (batch_id, semester_number, semester_name,
                                  implementation_deadline, defense_deadline) 
                                 VALUES (:batch_id, 2, 'Semester 2: Implementation & Defense',
                                  :implementation_deadline, :defense_deadline)";
                
                $sem2Stmt = $this->db->prepare($semester2Query);
                $sem2Stmt->execute([
                    ':batch_id' => $batchId,
                    ':implementation_deadline' => $data['semester2']['implementation_deadline'] ?? null,
                    ':defense_deadline' => $data['semester2']['defense_deadline'] ?? null
                ]);
            }
            
            // Initialize batch statistics
            $statsQuery = "INSERT INTO batch_statistics (batch_id) VALUES (:batch_id)";
            $statsStmt = $this->db->prepare($statsQuery);
            $statsStmt->execute([':batch_id' => $batchId]);
            
            $this->db->commit();
            
            return ['success' => true, 'message' => 'Batch created successfully with semesters', 'batch_id' => $batchId];
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Import students from CSV
     */
    public function importStudentsFromCSV($filePath, $departmentId, $batchId, $adminId) {
        try {
            $this->db->beginTransaction();
            
            // Create import log
            $logQuery = "INSERT INTO student_import_logs 
                        (department_id, batch_id, filename, imported_by, status) 
                        VALUES (:dept_id, :batch_id, :filename, :admin_id, 'processing')";
            
            $logStmt = $this->db->prepare($logQuery);
            $logStmt->execute([
                ':dept_id' => $departmentId,
                ':batch_id' => $batchId,
                ':filename' => basename($filePath),
                ':admin_id' => $adminId
            ]);
            
            $logId = $this->db->lastInsertId();
            
            // Read CSV file
            $successCount = 0;
            $failCount = 0;
            $errors = [];
            
            if (($handle = fopen($filePath, "r")) !== FALSE) {
                $row = 1;
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    if ($row == 1) {
                        $row++;
                        continue; // Skip header
                    }
                    
                    // Expected columns: student_id, full_name, email
                    if (count($data) >= 3) {
                        $username = trim($data[0]);
                        $fullName = trim($data[1]);
                        $email = trim($data[2]);
                        
                        // Check if student already exists
                        $checkQuery = "SELECT id FROM students WHERE username = :username OR email = :email";
                        $checkStmt = $this->db->prepare($checkQuery);
                        $checkStmt->execute([':username' => $username, ':email' => $email]);
                        
                        if ($checkStmt->fetch()) {
                            $failCount++;
                            $errors[] = "Row $row: Student $username already exists";
                            continue;
                        }
                        
                        // Insert student
                        $insertQuery = "INSERT INTO students 
                                      (username, password, full_name, email, department_id, batch_id, created_by) 
                                      VALUES (:username, :password, :full_name, :email, :dept_id, :batch_id, :admin_id)";
                        
                        $insertStmt = $this->db->prepare($insertQuery);
                        $result = $insertStmt->execute([
                            ':username' => $username,
                            ':password' => password_hash('password', PASSWORD_BCRYPT), // Default password
                            ':full_name' => $fullName,
                            ':email' => $email,
                            ':dept_id' => $departmentId,
                            ':batch_id' => $batchId,
                            ':admin_id' => $adminId
                        ]);
                        
                        if ($result) {
                            $successCount++;
                        } else {
                            $failCount++;
                            $errors[] = "Row $row: Failed to insert student $username";
                        }
                    } else {
                        $failCount++;
                        $errors[] = "Row $row: Invalid data format";
                    }
                    
                    $row++;
                }
                fclose($handle);
            }
            
            // Update import log
            $updateLogQuery = "UPDATE student_import_logs 
                             SET total_records = :total, 
                                 successful_imports = :success, 
                                 failed_imports = :failed,
                                 error_log = :errors,
                                 status = :status
                             WHERE id = :log_id";
            
            $updateStmt = $this->db->prepare($updateLogQuery);
            $updateStmt->execute([
                ':total' => $successCount + $failCount,
                ':success' => $successCount,
                ':failed' => $failCount,
                ':errors' => implode('\n', $errors),
                ':status' => $failCount > 0 ? 'completed_with_errors' : 'completed',
                ':log_id' => $logId
            ]);
            
            // Update batch statistics
            $this->updateBatchStatistics($batchId);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => "Import completed: $successCount successful, $failCount failed",
                'log_id' => $logId,
                'stats' => ['success' => $successCount, 'failed' => $failCount]
            ];
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => 'Import error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Update batch statistics
     */
    public function updateBatchStatistics($batchId) {
        try {
            $query = "UPDATE batch_statistics bs
                     SET total_students = (SELECT COUNT(*) FROM students WHERE batch_id = :batch_id),
                         assigned_students = (SELECT COUNT(*) FROM students s 
                                             JOIN projects p ON s.id = p.student_id 
                                             WHERE s.batch_id = :batch_id),
                         unassigned_students = (SELECT COUNT(*) FROM students 
                                               WHERE batch_id = :batch_id 
                                               AND id NOT IN (SELECT student_id FROM projects)),
                         last_updated = NOW()
                     WHERE batch_id = :batch_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([':batch_id' => $batchId]);
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Error updating batch statistics: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get batch with semester details
     */
    public function getBatchWithSemesters($batchId) {
        try {
            $query = "SELECT b.*, d.dept_name, 
                     (SELECT COUNT(*) FROM students WHERE batch_id = b.id) as student_count,
                     (SELECT COUNT(*) FROM projects WHERE batch_id = b.id) as project_count
                     FROM batches b
                     LEFT JOIN departments d ON b.department_id = d.id
                     WHERE b.id = :batch_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([':batch_id' => $batchId]);
            $batch = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($batch) {
                // Get semesters
                $semQuery = "SELECT * FROM academic_semesters 
                            WHERE batch_id = :batch_id 
                            ORDER BY semester_number";
                
                $semStmt = $this->db->prepare($semQuery);
                $semStmt->execute([':batch_id' => $batchId]);
                $batch['semesters'] = $semStmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Get statistics
                $statsQuery = "SELECT * FROM batch_statistics WHERE batch_id = :batch_id";
                $statsStmt = $this->db->prepare($statsQuery);
                $statsStmt->execute([':batch_id' => $batchId]);
                $batch['statistics'] = $statsStmt->fetch(PDO::FETCH_ASSOC);
            }
            
            return $batch;
            
        } catch (PDOException $e) {
            error_log("Error fetching batch with semesters: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get department configuration
     */
    public function getDepartmentConfig($departmentId) {
        try {
            $query = "SELECT * FROM department_configs WHERE department_id = :dept_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':dept_id' => $departmentId]);
            
            $config = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // If no config exists, create default
            if (!$config) {
                $insertQuery = "INSERT INTO department_configs (department_id) VALUES (:dept_id)";
                $insertStmt = $this->db->prepare($insertQuery);
                $insertStmt->execute([':dept_id' => $departmentId]);
                
                $config = ['department_id' => $departmentId, 'min_group_size' => 1, 'max_group_size' => 3];
            }
            
            return $config;
            
        } catch (PDOException $e) {
            error_log("Error fetching department config: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Update department configuration
     */
    public function updateDepartmentConfig($departmentId, $data) {
        try {
            // Check if config exists
            $checkQuery = "SELECT id FROM department_configs WHERE department_id = :dept_id";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->execute([':dept_id' => $departmentId]);
            
            if ($checkStmt->fetch()) {
                // Update existing
                $query = "UPDATE department_configs SET 
                         min_group_size = :min_size,
                         max_group_size = :max_size,
                         grading_template = :grading_template,
                         submission_requirements = :requirements,
                         updated_at = NOW()
                         WHERE department_id = :dept_id";
            } else {
                // Insert new
                $query = "INSERT INTO department_configs 
                         (department_id, min_group_size, max_group_size, grading_template, submission_requirements) 
                         VALUES (:dept_id, :min_size, :max_size, :grading_template, :requirements)";
            }
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                ':dept_id' => $departmentId,
                ':min_size' => $data['min_group_size'] ?? 1,
                ':max_size' => $data['max_group_size'] ?? 3,
                ':grading_template' => $data['grading_template'] ?? null,
                ':requirements' => $data['submission_requirements'] ?? null
            ]);
            
            return $result;
            
        } catch (PDOException $e) {
            error_log("Error updating department config: " . $e->getMessage());
            return false;
        }
    }
    // C:\xampp\htdocs\fypms\final-year-project-management-system\includes\classes\BatchManager.php
// Replace the getBatchesByDepartment method with this:

/**
 * Get batches for a specific department
 */
public function getBatchesByDepartment($departmentId) {
    try {
        $query = "SELECT b.*, d.dept_name, 
                 a.full_name as created_by_name,
                 (SELECT COUNT(*) FROM students WHERE batch_id = b.id) as student_count,
                 (SELECT COUNT(*) FROM projects WHERE batch_id = b.id) as project_count
                 FROM batches b
                 LEFT JOIN departments d ON b.department_id = d.id
                 LEFT JOIN admins a ON b.created_by = a.id
                 WHERE b.department_id = :dept_id 
                 ORDER BY b.created_at DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([':dept_id' => $departmentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error fetching batches by department: " . $e->getMessage());
        return [];
    }
}
/**
 * Get all batches for a department with progress
 */
public function getAllBatchesWithProgress($departmentId) {
    try {
        $query = "SELECT b.*, d.dept_name,
                 (SELECT COUNT(*) FROM students WHERE batch_id = b.id) as total_students,
                 (SELECT COUNT(*) FROM students s 
                  JOIN projects p ON s.id = p.student_id 
                  WHERE s.batch_id = b.id) as assigned_students,
                 (SELECT COUNT(*) FROM projects 
                  WHERE batch_id = b.id AND status = 'completed') as completed_projects
                 FROM batches b
                 LEFT JOIN departments d ON b.department_id = d.id
                 WHERE b.department_id = :dept_id
                 ORDER BY b.batch_year DESC, b.created_at DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([':dept_id' => $departmentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error fetching batches with progress: " . $e->getMessage());
        return [];
    }
}

/**
 * Get batch by ID
 */
/**
 * Get batch by ID
 *
 * @param int $batchId
 * @return array|null
 */
/**
 * Get batch by ID
 */
public function getBatchById($batchId) {
    try {
        $query = "SELECT b.*, d.dept_name, d.dept_code, 
                 a.full_name as created_by_name
                 FROM batches b
                 LEFT JOIN departments d ON b.department_id = d.id
                 LEFT JOIN admins a ON b.created_by = a.id
                 WHERE b.id = :batch_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([':batch_id' => $batchId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error fetching batch: " . $e->getMessage());
        return null;
    }
}

/**
 * Get students in a specific batch
 */
/**
 * Get students in a specific batch
 */
public function getStudentsInBatch($batchId) {
    try {
        $query = "SELECT s.* 
                 FROM students s
                 WHERE s.batch_id = :batch_id
                 ORDER BY s.full_name";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':batch_id' => $batchId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching students in batch: " . $e->getMessage());
        return [];
    }
}

/**
 * Get unassigned students in a department (students with batch_id IS NULL)
 */
/**
 * Get unassigned students in a department (students with batch_id IS NULL)
 */
public function getUnassignedStudents($departmentId) {
    try {
        $query = "SELECT s.* 
                 FROM students s
                 WHERE s.department_id = :dept_id AND s.batch_id IS NULL
                 ORDER BY s.full_name";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':dept_id' => $departmentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching unassigned students: " . $e->getMessage());
        return [];
    }
}
/**
 * Assign a student to a batch
 */
/**
 * Assign a student to a batch
 */
public function assignStudentToBatch($studentId, $batchId) {
    try {
        if ($batchId === null) {
            // Remove student from batch (set batch_id to NULL)
            $query = "UPDATE students SET batch_id = NULL WHERE id = :student_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':student_id' => $studentId]);
        } else {
            // Assign student to a specific batch
            $query = "UPDATE students SET batch_id = :batch_id WHERE id = :student_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':batch_id' => $batchId,
                ':student_id' => $studentId
            ]);
        }
        
        return ['success' => true, 'message' => 'Student assignment updated successfully'];
    } catch (PDOException $e) {
        error_log("Error assigning student to batch: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error updating student assignment'];
    }
}
/**
 * Bulk assign multiple students to a batch
 */
public function bulkAssignStudentsToBatch($studentIds, $batchId) {
    try {
        // Create placeholders for IN clause
        $placeholders = str_repeat('?,', count($studentIds) - 1) . '?';
        
        $query = "UPDATE students SET batch_id = ? WHERE id IN ($placeholders)";
        
        // Prepare and execute
        $stmt = $this->db->prepare($query);
        
        // Build parameters array: batchId first, then student ids
        $params = array_merge([$batchId], $studentIds);
        
        $stmt->execute($params);
        
        return ['success' => true, 'message' => count($studentIds) . ' students assigned successfully'];
    } catch (PDOException $e) {
        error_log("Error in bulk assigning students to batch: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error in bulk assignment'];
    }
}

/**
 * Create a new batch
 */
public function createBatch($data) {
    try {
        $query = "INSERT INTO batches (batch_name, batch_year, department_id, 
                  title_deadline, proposal_deadline, final_report_deadline, defense_deadline, created_by) 
                  VALUES (:batch_name, :batch_year, :department_id, 
                  :title_deadline, :proposal_deadline, :final_report_deadline, :defense_deadline, :created_by)";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':batch_name' => $data['batch_name'],
            ':batch_year' => $data['batch_year'],
            ':department_id' => $data['department_id'],
            ':title_deadline' => $data['title_deadline'],
            ':proposal_deadline' => $data['proposal_deadline'],
            ':final_report_deadline' => $data['final_report_deadline'],
            ':defense_deadline' => $data['defense_deadline'],
            ':created_by' => $data['created_by']
        ]);
        
        return ['success' => true, 'message' => 'Batch created successfully'];
    } catch (PDOException $e) {
        error_log("Error creating batch: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error creating batch'];
    }
}

/**
 * Update a batch
 */
public function updateBatch($batchId, $data) {
    try {
        $query = "UPDATE batches SET 
                 batch_name = :batch_name,
                 batch_year = :batch_year,
                 title_deadline = :title_deadline,
                 proposal_deadline = :proposal_deadline,
                 final_report_deadline = :final_report_deadline,
                 defense_deadline = :defense_deadline
                 WHERE id = :batch_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':batch_name' => $data['batch_name'],
            ':batch_year' => $data['batch_year'],
            ':title_deadline' => $data['title_deadline'],
            ':proposal_deadline' => $data['proposal_deadline'],
            ':final_report_deadline' => $data['final_report_deadline'],
            ':defense_deadline' => $data['defense_deadline'],
            ':batch_id' => $batchId
        ]);
        
        return ['success' => true, 'message' => 'Batch updated successfully'];
    } catch (PDOException $e) {
        error_log("Error updating batch: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error updating batch'];
    }
}

/**
 * Delete a batch
 */
public function deleteBatch($batchId) {
    try {
        // First check if batch has students
        $checkQuery = "SELECT COUNT(*) as student_count FROM students WHERE batch_id = :batch_id";
        $checkStmt = $this->db->prepare($checkQuery);
        $checkStmt->execute([':batch_id' => $batchId]);
        $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['student_count'] > 0) {
            return ['success' => false, 'message' => 'Cannot delete batch with assigned students'];
        }
        
        // Delete batch
        $query = "DELETE FROM batches WHERE id = :batch_id";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':batch_id' => $batchId]);
        
        return ['success' => true, 'message' => 'Batch deleted successfully'];
    } catch (PDOException $e) {
        error_log("Error deleting batch: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error deleting batch'];
    }
}

    /**
     * Get advanced statistics for department
     */
    public function getDepartmentAdvancedStats($departmentId) {
        try {
            $query = "SELECT 
                     d.id, d.dept_code, d.dept_name,
                     COUNT(DISTINCT b.id) as batch_count,
                     COUNT(DISTINCT s.id) as student_count,
                     COUNT(DISTINCT t.id) as teacher_count,
                     COUNT(DISTINCT p.id) as project_count,
                     AVG(bs.average_grade) as avg_department_grade,
                     SUM(CASE WHEN s.batch_id IS NULL THEN 1 ELSE 0 END) as unassigned_students,
                     (SELECT COUNT(*) FROM projects WHERE status = 'completed' AND department_id = d.id) as completed_projects
                     FROM departments d
                     LEFT JOIN batches b ON d.id = b.department_id
                     LEFT JOIN students s ON d.id = s.department_id
                     LEFT JOIN teachers t ON d.id = t.department_id
                     LEFT JOIN projects p ON d.id = p.department_id
                     LEFT JOIN batch_statistics bs ON b.id = bs.batch_id
                     WHERE d.id = :dept_id
                     GROUP BY d.id";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([':dept_id' => $departmentId]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching department stats: " . $e->getMessage());
            return null;
        }
    }
}
?>