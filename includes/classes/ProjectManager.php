<?php
/**
 * ProjectManager Class
 * Handles all project/title related operations
 */
class ProjectManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Submit a new project title
     */
    public function submitTitle($data) {
        try {
            // Check if student already has a pending/approved project in this batch
            $checkQuery = "SELECT id FROM projects 
                          WHERE student_id = :student_id 
                          AND batch_id = :batch_id 
                          AND status IN ('pending', 'approved', 'in_progress')";
            
            $stmt = $this->db->prepare($checkQuery);
            $stmt->execute([
                ':student_id' => $data['student_id'],
                ':batch_id' => $data['batch_id']
            ]);
            
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'You already have a project in this batch'];
            }
            
            // Check if title already exists in the same batch
            $titleCheck = "SELECT id FROM projects 
                          WHERE LOWER(title) = LOWER(:title) 
                          AND batch_id = :batch_id 
                          AND department_id = :department_id";
            
            $stmt = $this->db->prepare($titleCheck);
            $stmt->execute([
                ':title' => trim($data['title']),
                ':batch_id' => $data['batch_id'],
                ':department_id' => $data['department_id']
            ]);
            
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'This title already exists in your department batch'];
            }
            
            // Insert new project
            $query = "INSERT INTO projects (title, description, student_id, group_id, 
                      department_id, batch_id, supervisor_id, status, submitted_at) 
                      VALUES (:title, :description, :student_id, :group_id, 
                      :department_id, :batch_id, :supervisor_id, 'pending', NOW())";
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                ':title' => trim($data['title']),
                ':description' => $data['description'] ?? null,
                ':student_id' => $data['student_id'],
                ':group_id' => $data['group_id'] ?? null,
                ':department_id' => $data['department_id'],
                ':batch_id' => $data['batch_id'],
                ':supervisor_id' => $data['supervisor_id'] ?? null
            ]);
            
            if ($result) {
                $projectId = $this->db->lastInsertId();
                
                // Log title history
                $this->logTitleHistory($projectId, null, $data['title'], $data['student_id'], 'Initial submission');
                
                return ['success' => true, 'message' => 'Title submitted successfully! Waiting for approval.', 'project_id' => $projectId];
            }
            
            return ['success' => false, 'message' => 'Failed to submit title'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get student's project
     */
    public function getStudentProject($studentId, $batchId) {
        try {
            $query = "SELECT p.*, 
                     d.dept_name, b.batch_name, b.batch_year,
                     t.full_name as supervisor_name,
                     g.group_name, g.group_code,
                     (SELECT COUNT(*) FROM group_members gm WHERE gm.group_id = p.group_id) as group_member_count
                     FROM projects p
                     LEFT JOIN departments d ON p.department_id = d.id
                     LEFT JOIN batches b ON p.batch_id = b.id
                     LEFT JOIN teachers t ON p.supervisor_id = t.id
                     LEFT JOIN groups g ON p.group_id = g.id
                     WHERE p.student_id = :student_id 
                     AND p.batch_id = :batch_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([':student_id' => $studentId, ':batch_id' => $batchId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching student project: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get pending titles for admin's department
     */
    public function getPendingTitles($departmentId) {
        try {
            $query = "SELECT p.*, 
                     s.full_name as student_name, s.username as student_id,
                     s.email as student_email,
                     d.dept_name, b.batch_name, b.batch_year,
                     t.full_name as supervisor_name,
                     g.group_name, g.group_code
                     FROM projects p
                     JOIN students s ON p.student_id = s.id
                     JOIN departments d ON p.department_id = d.id
                     JOIN batches b ON p.batch_id = b.id
                     LEFT JOIN teachers t ON p.supervisor_id = t.id
                     LEFT JOIN groups g ON p.group_id = g.id
                     WHERE p.department_id = :department_id 
                     AND p.status = 'pending'
                     ORDER BY p.submitted_at DESC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([':department_id' => $departmentId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching pending titles: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Approve a title
     */
    public function approveTitle($projectId, $adminId, $comments = null) {
        try {
            $query = "UPDATE projects 
                     SET status = 'approved', 
                     admin_comments = :comments,
                     reviewed_at = NOW(),
                     approved_at = NOW()
                     WHERE id = :project_id";
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                ':comments' => $comments,
                ':project_id' => $projectId
            ]);
            
            if ($result) {
                // Get project for notification
                $project = $this->getProjectById($projectId);
                
                // TODO: Send email notification to student
                // $this->sendNotification($project['student_id'], 'title_approved', $project);
                
                return ['success' => true, 'message' => 'Title approved successfully'];
            }
            
            return ['success' => false, 'message' => 'Failed to approve title'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Reject a title
     */
    public function rejectTitle($projectId, $adminId, $comments) {
        try {
            if (empty(trim($comments))) {
                return ['success' => false, 'message' => 'Comments are required for rejection'];
            }
            
            $query = "UPDATE projects 
                     SET status = 'rejected', 
                     admin_comments = :comments,
                     reviewed_at = NOW()
                     WHERE id = :project_id";
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                ':comments' => $comments,
                ':project_id' => $projectId
            ]);
            
            if ($result) {
                // Get project for notification
                $project = $this->getProjectById($projectId);
                
                // TODO: Send email notification to student
                // $this->sendNotification($project['student_id'], 'title_rejected', $project);
                
                return ['success' => true, 'message' => 'Title rejected'];
            }
            
            return ['success' => false, 'message' => 'Failed to reject title'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get project by ID
     */
    public function getProjectById($projectId) {
        try {
            $query = "SELECT p.*, 
                     s.full_name as student_name, s.username as student_id,
                     s.email as student_email,
                     d.dept_name, b.batch_name, b.batch_year,
                     t.full_name as supervisor_name, t.email as supervisor_email,
                     g.group_name, g.group_code
                     FROM projects p
                     JOIN students s ON p.student_id = s.id
                     JOIN departments d ON p.department_id = d.id
                     JOIN batches b ON p.batch_id = b.id
                     LEFT JOIN teachers t ON p.supervisor_id = t.id
                     LEFT JOIN groups g ON p.group_id = g.id
                     WHERE p.id = :project_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([':project_id' => $projectId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching project: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get all projects for a department
     */
    public function getDepartmentProjects($departmentId, $status = null) {
        try {
            $whereClause = "WHERE p.department_id = :department_id";
            $params = [':department_id' => $departmentId];
            
            if ($status) {
                $whereClause .= " AND p.status = :status";
                $params[':status'] = $status;
            }
            
            $query = "SELECT p.*, 
                     s.full_name as student_name, s.username as student_id,
                     d.dept_name, b.batch_name, b.batch_year,
                     t.full_name as supervisor_name,
                     g.group_name
                     FROM projects p
                     JOIN students s ON p.student_id = s.id
                     JOIN departments d ON p.department_id = d.id
                     JOIN batches b ON p.batch_id = b.id
                     LEFT JOIN teachers t ON p.supervisor_id = t.id
                     LEFT JOIN groups g ON p.group_id = g.id
                     $whereClause
                     ORDER BY p.submitted_at DESC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching department projects: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Update project status
     */
    public function updateProjectStatus($projectId, $status, $comments = null) {
        try {
            $query = "UPDATE projects 
                     SET status = :status, 
                     admin_comments = :comments,
                     reviewed_at = NOW()
                     WHERE id = :project_id";
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                ':status' => $status,
                ':comments' => $comments,
                ':project_id' => $projectId
            ]);
            
            return $result;
            
        } catch (PDOException $e) {
            error_log("Error updating project status: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log title history
     */
    private function logTitleHistory($projectId, $oldTitle, $newTitle, $changedBy, $reason) {
        try {
            $query = "INSERT INTO title_history (project_id, old_title, new_title, changed_by, change_reason) 
                     VALUES (:project_id, :old_title, :new_title, :changed_by, :reason)";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':project_id' => $projectId,
                ':old_title' => $oldTitle,
                ':new_title' => $newTitle,
                ':changed_by' => $changedBy,
                ':reason' => $reason
            ]);
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Error logging title history: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get title history for a project
     */
    public function getTitleHistory($projectId) {
        try {
            $query = "SELECT th.*, s.full_name as changed_by_name 
                     FROM title_history th
                     JOIN students s ON th.changed_by = s.id
                     WHERE th.project_id = :project_id
                     ORDER BY th.changed_at DESC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([':project_id' => $projectId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching title history: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get project statistics for dashboard
     */
    public function getProjectStatistics($departmentId = null) {
        try {
            $whereClause = $departmentId ? "WHERE p.department_id = :department_id" : "";
            $params = $departmentId ? [':department_id' => $departmentId] : [];
            
            $query = "SELECT 
                     COUNT(CASE WHEN p.status = 'pending' THEN 1 END) as pending_count,
                     COUNT(CASE WHEN p.status = 'approved' THEN 1 END) as approved_count,
                     COUNT(CASE WHEN p.status = 'rejected' THEN 1 END) as rejected_count,
                     COUNT(CASE WHEN p.status = 'in_progress' THEN 1 END) as in_progress_count,
                     COUNT(CASE WHEN p.status = 'completed' THEN 1 END) as completed_count,
                     COUNT(*) as total_count
                     FROM projects p
                     $whereClause";
            
            $stmt = $departmentId ? 
                   $this->db->prepare($query) : 
                   $this->db->query($query);
                   
            if ($departmentId) {
                $stmt->execute($params);
            }
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching project statistics: " . $e->getMessage());
            return null;
        }
    }
}
?>