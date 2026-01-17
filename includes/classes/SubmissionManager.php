<!-- File: includes/classes/SubmissionManager.php -->
<?php
/**
 * SubmissionManager Class
 * Handles document submissions and feedback
 */
class SubmissionManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Submit a document
     */
    public function submitDocument($data, $fileInfo = null) {
        try {
            // Check if student has an approved project
            $project = $this->getStudentProject($data['student_id']);
            if (!$project || $project['status'] !== 'approved') {
                return ['success' => false, 'message' => 'No approved project found'];
            }
            
            // Check deadline for submission type
            $batchInfo = $this->getBatchInfo($project['batch_id']);
            $deadline = $this->getSubmissionDeadline($batchInfo, $data['submission_type']);
            
            if ($deadline && strtotime(date('Y-m-d')) > strtotime($deadline)) {
                return ['success' => false, 'message' => 'Submission deadline has passed'];
            }
            
            // Check for existing submission of same type (for versioning)
            $existing = $this->getLatestSubmission($project['id'], $data['submission_type']);
            $version = $existing ? $existing['version'] + 1 : 1;
            
            // Insert submission
            $query = "INSERT INTO submissions 
                     (project_id, student_id, submission_type, file_name, file_path, 
                      file_size, file_type, version, status, description, deadline_date) 
                     VALUES 
                     (:project_id, :student_id, :submission_type, :file_name, :file_path, 
                      :file_size, :file_type, :version, 'pending', :description, :deadline)";
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                ':project_id' => $project['id'],
                ':student_id' => $data['student_id'],
                ':submission_type' => $data['submission_type'],
                ':file_name' => $fileInfo ? $fileInfo['file_name'] : '',
                ':file_path' => $fileInfo ? $fileInfo['file_path'] : '',
                ':file_size' => $fileInfo ? $fileInfo['file_size'] : 0,
                ':file_type' => $fileInfo ? $fileInfo['file_type'] : '',
                ':version' => $version,
                ':description' => $data['description'] ?? '',
                ':deadline' => $deadline
            ]);
            
            if ($result) {
                $submissionId = $this->db->lastInsertId();
                
                // Update project status based on submission type
                $this->updateProjectStatus($project['id'], $data['submission_type']);
                
                // Log submission history
                $this->logSubmissionHistory($submissionId, 'created', 'New submission created', $data['student_id']);
                
                // Notify supervisor
                $this->notifySupervisor($project['supervisor_id'], $project['id'], $data['submission_type']);
                
                return ['success' => true, 'message' => 'Document submitted successfully', 'submission_id' => $submissionId];
            }
            
            return ['success' => false, 'message' => 'Failed to submit document'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get student's project
     */
    private function getStudentProject($studentId) {
        try {
            $query = "SELECT * FROM projects 
                     WHERE student_id = :student_id 
                     AND status IN ('approved', 'in_progress', 'proposal_submitted', 'proposal_approved')
                     ORDER BY submitted_at DESC LIMIT 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([':student_id' => $studentId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching student project: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get batch information
     */
    private function getBatchInfo($batchId) {
        try {
            $query = "SELECT * FROM batches WHERE id = :batch_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':batch_id' => $batchId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching batch info: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get submission deadline based on type
     */
    private function getSubmissionDeadline($batchInfo, $submissionType) {
        if (!$batchInfo) return null;
        
        switch($submissionType) {
            case 'proposal':
                return $batchInfo['proposal_deadline'];
            case 'final_report':
                return $batchInfo['final_report_deadline'];
            default:
                return null;
        }
    }
    
    /**
     * Get latest submission of a type
     */
    public function getLatestSubmission($projectId, $submissionType) {
        try {
            $query = "SELECT * FROM submissions 
                     WHERE project_id = :project_id 
                     AND submission_type = :submission_type
                     ORDER BY version DESC LIMIT 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':project_id' => $projectId,
                ':submission_type' => $submissionType
            ]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching latest submission: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Update project status based on submission
     */
    private function updateProjectStatus($projectId, $submissionType) {
        $statusMap = [
            'proposal' => 'proposal_submitted',
            'progress_report' => 'in_progress',
            'final_report' => 'final_submitted'
        ];
        
        if (isset($statusMap[$submissionType])) {
            try {
                $query = "UPDATE projects SET status = :status WHERE id = :project_id";
                $stmt = $this->db->prepare($query);
                $stmt->execute([
                    ':status' => $statusMap[$submissionType],
                    ':project_id' => $projectId
                ]);
            } catch (PDOException $e) {
                error_log("Error updating project status: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Log submission history
     */
    private function logSubmissionHistory($submissionId, $action, $reason, $changedBy) {
        try {
            $query = "INSERT INTO submission_history 
                     (submission_id, previous_status, new_status, changed_by, change_reason) 
                     VALUES (:submission_id, NULL, :action, :changed_by, :reason)";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':submission_id' => $submissionId,
                ':action' => $action,
                ':changed_by' => $changedBy,
                ':reason' => $reason
            ]);
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Error logging submission history: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Notify supervisor about new submission
     */
    private function notifySupervisor($supervisorId, $projectId, $submissionType) {
        try {
            // Get supervisor info
            $query = "SELECT id, email FROM teachers WHERE id = :supervisor_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':supervisor_id' => $supervisorId]);
            $supervisor = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($supervisor) {
                // Create notification
                $notificationQuery = "INSERT INTO notifications 
                                    (user_id, user_type, title, message, type, link) 
                                    VALUES (:user_id, 'teacher', :title, :message, 'info', :link)";
                
                $notificationStmt = $this->db->prepare($notificationQuery);
                $notificationStmt->execute([
                    ':user_id' => $supervisorId,
                    ':title' => 'New ' . str_replace('_', ' ', $submissionType) . ' Submitted',
                    ':message' => 'A student has submitted a ' . $submissionType . ' for your review.',
                    ':link' => 'teacher/submissions.php?project_id=' . $projectId
                ]);
                
                // In production, you would also send email here
            }
            
        } catch (PDOException $e) {
            error_log("Error notifying supervisor: " . $e->getMessage());
        }
    }
    
    /**
     * Get all submissions for a project
     */
    public function getProjectSubmissions($projectId) {
        try {
            $query = "SELECT s.*, 
                     st.full_name as student_name,
                     p.title as project_title
                     FROM submissions s
                     JOIN students st ON s.student_id = st.id
                     JOIN projects p ON s.project_id = p.id
                     WHERE s.project_id = :project_id
                     ORDER BY s.submitted_at DESC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([':project_id' => $projectId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching project submissions: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get submissions for teacher to review
     */
    public function getTeacherSubmissions($teacherId) {
        try {
            $query = "SELECT s.*, 
                     st.full_name as student_name, st.username as student_id,
                     p.title as project_title, p.id as project_id,
                     (SELECT COUNT(*) FROM feedback f WHERE f.submission_id = s.id) as feedback_count
                     FROM submissions s
                     JOIN projects p ON s.project_id = p.id
                     JOIN students st ON s.student_id = st.id
                     WHERE p.supervisor_id = :teacher_id
                     AND s.status IN ('pending', 'under_review')
                     ORDER BY s.submitted_at DESC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([':teacher_id' => $teacherId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching teacher submissions: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Give feedback on submission
     */
    public function giveFeedback($data) {
        try {
            // Check if feedback already exists
            $checkQuery = "SELECT id FROM feedback 
                          WHERE submission_id = :submission_id 
                          AND teacher_id = :teacher_id";
            
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->execute([
                ':submission_id' => $data['submission_id'],
                ':teacher_id' => $data['teacher_id']
            ]);
            
            if ($checkStmt->fetch()) {
                return ['success' => false, 'message' => 'Feedback already given for this submission'];
            }
            
            // Get submission info for department_id
            $submissionQuery = "SELECT s.*, p.department_id 
                               FROM submissions s
                               JOIN projects p ON s.project_id = p.id
                               WHERE s.id = :submission_id";
            
            $submissionStmt = $this->db->prepare($submissionQuery);
            $submissionStmt->execute([':submission_id' => $data['submission_id']]);
            $submission = $submissionStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$submission) {
                return ['success' => false, 'message' => 'Submission not found'];
            }
            
            // Insert feedback
            $query = "INSERT INTO feedback 
                     (submission_id, teacher_id, comments, marks, grade, status, department_id) 
                     VALUES 
                     (:submission_id, :teacher_id, :comments, :marks, :grade, 'submitted', :department_id)";
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                ':submission_id' => $data['submission_id'],
                ':teacher_id' => $data['teacher_id'],
                ':comments' => $data['comments'],
                ':marks' => $data['marks'] ?? null,
                ':grade' => $data['grade'] ?? null,
                ':department_id' => $submission['department_id']
            ]);
            
            if ($result) {
                // Update submission status
                $this->updateSubmissionStatus($data['submission_id'], $data['status'] ?? 'under_review');
                
                // Log history
                $this->logSubmissionHistory($data['submission_id'], 'feedback_given', 
                                           'Feedback provided by teacher', $data['teacher_id']);
                
                // Notify student
                $this->notifyStudent($submission['student_id'], $data['submission_id']);
                
                return ['success' => true, 'message' => 'Feedback submitted successfully'];
            }
            
            return ['success' => false, 'message' => 'Failed to submit feedback'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Update submission status
     */
    private function updateSubmissionStatus($submissionId, $status) {
        try {
            $query = "UPDATE submissions SET status = :status WHERE id = :submission_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':status' => $status,
                ':submission_id' => $submissionId
            ]);
        } catch (PDOException $e) {
            error_log("Error updating submission status: " . $e->getMessage());
        }
    }
    
    /**
     * Notify student about feedback
     */
    private function notifyStudent($studentId, $submissionId) {
        try {
            // Create notification
            $query = "INSERT INTO notifications 
                     (user_id, user_type, title, message, type, link) 
                     VALUES (:user_id, 'student', :title, :message, 'success', :link)";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':user_id' => $studentId,
                ':title' => 'Feedback Received',
                ':message' => 'Your submission has been reviewed and feedback is available.',
                ':link' => 'student/my_submissions.php?submission_id=' . $submissionId
            ]);
            
        } catch (PDOException $e) {
            error_log("Error notifying student: " . $e->getMessage());
        }
    }
    
    /**
     * Get feedback for submission
     */
    public function getFeedback($submissionId) {
        try {
            $query = "SELECT f.*, 
                     t.full_name as teacher_name,
                     t.email as teacher_email,
                     d.dept_name
                     FROM feedback f
                     JOIN teachers t ON f.teacher_id = t.id
                     JOIN departments d ON f.department_id = d.id
                     WHERE f.submission_id = :submission_id
                     ORDER BY f.given_at DESC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([':submission_id' => $submissionId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching feedback: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get submission statistics for dashboard
     */
    public function getSubmissionStatistics($teacherId = null) {
        try {
            $whereClause = "";
            $params = [];
            
            if ($teacherId) {
                $whereClause = "WHERE p.supervisor_id = :teacher_id";
                $params[':teacher_id'] = $teacherId;
            }
            
            $query = "SELECT 
                     submission_type,
                     COUNT(*) as total,
                     SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                     SUM(CASE WHEN status = 'under_review' THEN 1 ELSE 0 END) as under_review,
                     SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                     SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
                     FROM submissions s
                     JOIN projects p ON s.project_id = p.id
                     $whereClause
                     GROUP BY submission_type";
            
            $stmt = $teacherId ? 
                   $this->db->prepare($query) : 
                   $this->db->query($query);
                   
            if ($teacherId) {
                $stmt->execute($params);
            }
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching submission statistics: " . $e->getMessage());
            return [];
        }
    }
}
?>