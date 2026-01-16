<?php
/**
 * SupervisorManager Class
 * Handles supervisor assignment and management
 */
class SupervisorManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Get all teachers in department
     */
    public function getDepartmentTeachers($departmentId) {
        try {
            $query = "SELECT t.*, 
                     (SELECT COUNT(*) FROM projects p WHERE p.supervisor_id = t.id AND p.status IN ('approved', 'in_progress')) as current_load,
                     (SELECT GROUP_CONCAT(specialization SEPARATOR ', ') FROM teacher_specializations ts WHERE ts.teacher_id = t.id) as specializations
                     FROM teachers t
                     WHERE t.department_id = :department_id 
                     AND t.status = 'active'
                     ORDER BY t.full_name";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([':department_id' => $departmentId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching department teachers: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Assign supervisor to project
     */
    public function assignSupervisor($projectId, $teacherId, $assignedBy, $assignmentType = 'manual', $comments = '') {
        try {
            // Check teacher's current load
            $teacher = $this->getTeacherById($teacherId);
            if (!$teacher) {
                return ['success' => false, 'message' => 'Teacher not found'];
            }
            
            $currentLoad = $teacher['current_load'] ?? 0;
            $maxStudents = $teacher['max_students'] ?? 5;
            
            if ($currentLoad >= $maxStudents) {
                return ['success' => false, 'message' => 'Teacher has reached maximum student limit'];
            }
            
            // Check if project already has an active supervisor
            $existingQuery = "SELECT id FROM project_supervisors 
                             WHERE project_id = :project_id 
                             AND status = 'active'";
            
            $stmt = $this->db->prepare($existingQuery);
            $stmt->execute([':project_id' => $projectId]);
            
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Project already has an active supervisor'];
            }
            
            // Assign supervisor to project
            $query = "INSERT INTO project_supervisors (project_id, teacher_id, assigned_by, 
                      assignment_type, status, comments) 
                      VALUES (:project_id, :teacher_id, :assigned_by, 
                      :assignment_type, 'active', :comments)";
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                ':project_id' => $projectId,
                ':teacher_id' => $teacherId,
                ':assigned_by' => $assignedBy,
                ':assignment_type' => $assignmentType,
                ':comments' => $comments
            ]);
            
            if ($result) {
                // Update project table
                $updateQuery = "UPDATE projects SET supervisor_id = :teacher_id WHERE id = :project_id";
                $updateStmt = $this->db->prepare($updateQuery);
                $updateStmt->execute([
                    ':teacher_id' => $teacherId,
                    ':project_id' => $projectId
                ]);
                
                return ['success' => true, 'message' => 'Supervisor assigned successfully'];
            }
            
            return ['success' => false, 'message' => 'Failed to assign supervisor'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Auto-assign supervisor based on workload and specialization
     */
    public function autoAssignSupervisor($projectId, $departmentId, $assignedBy) {
        try {
            // Get project details
            $projectQuery = "SELECT p.*, s.full_name as student_name 
                            FROM projects p
                            JOIN students s ON p.student_id = s.id
                            WHERE p.id = :project_id";
            
            $stmt = $this->db->prepare($projectQuery);
            $stmt->execute([':project_id' => $projectId]);
            $project = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$project) {
                return ['success' => false, 'message' => 'Project not found'];
            }
            
            // Find available teachers with least load
            $teachersQuery = "SELECT t.*, 
                             (SELECT COUNT(*) FROM projects p2 WHERE p2.supervisor_id = t.id AND p2.status IN ('approved', 'in_progress')) as current_load
                             FROM teachers t
                             WHERE t.department_id = :department_id 
                             AND t.status = 'active'
                             AND (t.max_students IS NULL OR t.max_students > 
                                 (SELECT COUNT(*) FROM projects p3 WHERE p3.supervisor_id = t.id AND p3.status IN ('approved', 'in_progress')))
                             ORDER BY current_load, t.full_name
                             LIMIT 1";
            
            $stmt = $this->db->prepare($teachersQuery);
            $stmt->execute([':department_id' => $departmentId]);
            $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$teacher) {
                return ['success' => false, 'message' => 'No available supervisor found'];
            }
            
            // Assign the teacher
            return $this->assignSupervisor($projectId, $teacher['id'], $assignedBy, 'auto', 'Auto-assigned based on workload');
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get teacher by ID
     */
    public function getTeacherById($teacherId) {
        try {
            $query = "SELECT t.*, 
                     (SELECT COUNT(*) FROM projects p WHERE p.supervisor_id = t.id AND p.status IN ('approved', 'in_progress')) as current_load,
                     d.dept_name
                     FROM teachers t
                     LEFT JOIN departments d ON t.department_id = d.id
                     WHERE t.id = :teacher_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([':teacher_id' => $teacherId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching teacher: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get projects supervised by teacher
     */
    public function getTeacherProjects($teacherId, $status = null) {
        try {
            $whereClause = "WHERE p.supervisor_id = :teacher_id";
            $params = [':teacher_id' => $teacherId];
            
            if ($status) {
                $whereClause .= " AND p.status = :status";
                $params[':status'] = $status;
            }
            
            $query = "SELECT p.*, 
                     s.full_name as student_name, s.username as student_id,
                     d.dept_name, b.batch_name, b.batch_year,
                     g.group_name, g.group_code
                     FROM projects p
                     JOIN students s ON p.student_id = s.id
                     JOIN departments d ON p.department_id = d.id
                     JOIN batches b ON p.batch_id = b.id
                     LEFT JOIN groups g ON p.group_id = g.id
                     $whereClause
                     ORDER BY p.submitted_at DESC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching teacher projects: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get supervisor assignment history
     */
    public function getAssignmentHistory($projectId) {
        try {
            $query = "SELECT ps.*, 
                     t.full_name as teacher_name,
                     a.full_name as assigned_by_name
                     FROM project_supervisors ps
                     JOIN teachers t ON ps.teacher_id = t.id
                     JOIN admins a ON ps.assigned_by = a.id
                     WHERE ps.project_id = :project_id
                     ORDER BY ps.assignment_date DESC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([':project_id' => $projectId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching assignment history: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Transfer supervisor
     */
    public function transferSupervisor($projectId, $newTeacherId, $assignedBy, $reason = '') {
        try {
            // Mark current assignment as transferred
            $updateQuery = "UPDATE project_supervisors 
                           SET status = 'transferred', 
                           comments = CONCAT(IFNULL(comments, ''), ' Transferred: ', :reason)
                           WHERE project_id = :project_id 
                           AND status = 'active'";
            
            $stmt = $this->db->prepare($updateQuery);
            $stmt->execute([
                ':reason' => $reason,
                ':project_id' => $projectId
            ]);
            
            // Assign new supervisor
            return $this->assignSupervisor($projectId, $newTeacherId, $assignedBy, 'manual', 
                                          'Transferred from previous supervisor. Reason: ' . $reason);
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get supervisor workload statistics
     */
    public function getSupervisorWorkload($departmentId) {
        try {
            $query = "SELECT 
                     t.id, t.full_name, t.email,
                     t.max_students,
                     COUNT(p.id) as current_load,
                     GROUP_CONCAT(DISTINCT 
                         CASE WHEN p.status = 'approved' THEN 'Approved' 
                              WHEN p.status = 'in_progress' THEN 'In Progress' 
                              WHEN p.status = 'completed' THEN 'Completed' 
                         END
                         SEPARATOR ', ') as project_statuses,
                     (SELECT GROUP_CONCAT(specialization SEPARATOR ', ') 
                      FROM teacher_specializations ts 
                      WHERE ts.teacher_id = t.id) as specializations
                     FROM teachers t
                     LEFT JOIN projects p ON t.id = p.supervisor_id 
                         AND p.status IN ('approved', 'in_progress', 'completed')
                     WHERE t.department_id = :department_id
                     GROUP BY t.id
                     ORDER BY current_load DESC, t.full_name";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([':department_id' => $departmentId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching supervisor workload: " . $e->getMessage());
            return [];
        }
    }
}
?>