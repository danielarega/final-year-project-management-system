<?php
/**
 * SubmissionManager Class
 * Handles all document submission and feedback operations
 */
class SubmissionManager {
    private $db;
    private $uploadDir;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/fypms/final-year-project-management-system/assets/uploads/submissions/';
        
        // Create upload directory if it doesn't exist
        if (!file_exists($this->uploadDir)) {
            mkdir($this->uploadDir, 0777, true);
        }
    }
    
    /**
     * Submit a document
     */
    public function submitDocument($data, $file) {
        try {
            // Validate file
            $validation = $this->validateFile($file, $data['submission_type_id']);
            if (!$validation['success']) {
                return $validation;
            }
            
            // Check if student has a project
            $project = $this->getStudentProject($data['student_id']);
            if (!$project) {
                return ['success' => false, 'message' => 'No project found. Submit project title first.'];
            }
            
            // Check deadline
            $deadlineCheck = $this->checkDeadline($data['submission_type_id'], $project['batch_id']);
            if (!$deadlineCheck['success']) {
                return $deadlineCheck;
            }
            
            // Generate unique filename
            $fileName = $this->generateFileName($file, $data);
            $filePath = $this->uploadDir . $fileName;
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                return ['success' => false, 'message' => 'Failed to upload file.'];
            }
            
            // Check for existing submission
            $existing = $this->getStudentSubmission($data['student_id'], $data['submission_type_id'], $project['id']);
            
            if ($existing) {
                // Create new version
                $version = $existing['version'] + 1;
                
                // Archive old version
                $this->archiveVersion($existing);
                
                // Update existing submission
                $query = "UPDATE submissions SET 
                         version = :version,
                         file_name = :file_name,
                         file_path = :file_path,
                         file_size = :file_size,
                         file_type = :file_type,
                         status = 'submitted',
                         submission_date = NOW(),
                         submission_notes = :notes,
                         review_status = 'not_reviewed',
                         review_comments = NULL,
                         review_score = 0.00
                         WHERE id = :id";
                
                $stmt = $this->db->prepare($query);
                $result = $stmt->execute([
                    ':version' => $version,
                    ':file_name' => $file['name'],
                    ':file_path' => $filePath,
                    ':file_size' => $file['size'],
                    ':file_type' => $file['type'],
                    ':notes' => $data['notes'] ?? null,
                    ':id' => $existing['id']
                ]);
                
                $submissionId = $existing['id'];
            } else {
                // Create new submission
                $query = "INSERT INTO submissions (project_id, submission_type_id, student_id, 
                         file_name, file_path, file_size, file_type, status, submission_notes) 
                         VALUES (:project_id, :submission_type_id, :student_id, 
                         :file_name, :file_path, :file_size, :file_type, 'submitted', :notes)";
                
                $stmt = $this->db->prepare($query);
                $result = $stmt->execute([
                    ':project_id' => $project['id'],
                    ':submission_type_id' => $data['submission_type_id'],
                    ':student_id' => $data['student_id'],
                    ':file_name' => $file['name'],
                    ':file_path' => $filePath,
                    ':file_size' => $file['size'],
                    ':file_type' => $file['type'],
                    ':notes' => $data['notes'] ?? null
                ]);
                
                $submissionId = $this->db->lastInsertId();
            }
            
            if ($result) {
                return [
                    'success' => true, 
                    'message' => 'Document submitted successfully!',
                    'submission_id' => $submissionId,
                    'file_name' => $file['name']
                ];
            }
            
            return ['success' => false, 'message' => 'Failed to save submission.'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get available submission types for student
     */
    public function getStudentSubmissionTypes($studentId, $departmentId = null, $batchId = null) {
        try {
            // Get student's project
            $project = $this->getStudentProject($studentId);
            if (!$project) {
                return [];
            }
            
            if (!$departmentId) $departmentId = $project['department_id'];
            if (!$batchId) $batchId = $project['batch_id'];
            
            $query = "SELECT st.*, 
                     sd.deadline_date,
                     CASE 
                         WHEN sd.deadline_date < CURDATE() THEN 'overdue'
                         WHEN sd.deadline_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'upcoming'
                         ELSE 'future'
                     END as deadline_status,
                     (SELECT COUNT(*) FROM submissions s 
                      WHERE s.submission_type_id = st.id 
                      AND s.student_id = :student_id) as submission_count
                     FROM submission_types st
                     LEFT JOIN submission_deadlines sd ON st.id = sd.submission_type_id 
                      AND sd.batch_id = :batch_id
                     WHERE (st.department_id IS NULL OR st.department_id = :dept_id)
                     AND (st.batch_id IS NULL OR st.batch_id = :batch_id)
                     AND st.is_active = TRUE
                     ORDER BY st.display_order";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':student_id' => $studentId,
                ':dept_id' => $departmentId,
                ':batch_id' => $batchId
            ]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching submission types: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get student's submissions
     */
    public function getStudentSubmissions($studentId, $projectId = null) {
        try {
            if (!$projectId) {
                $project = $this->getStudentProject($studentId);
                if (!$project) return [];
                $projectId = $project['id'];
            }
            
            $query = "SELECT s.*, st.type_name, st.description,
                     t.full_name as reviewer_name,
                     CASE 
                         WHEN s.review_status = 'accepted' THEN 'success'
                         WHEN s.review_status = 'needs_revision' THEN 'warning'
                         WHEN s.review_status = 'reviewed' THEN 'info'
                         ELSE 'secondary'
                     END as status_color
                     FROM submissions s
                     JOIN submission_types st ON s.submission_type_id = st.id
                     LEFT JOIN teachers t ON s.reviewed_by = t.id
                     WHERE s.project_id = :project_id
                     ORDER BY s.submission_date DESC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([':project_id' => $projectId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching student submissions: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get submission for review (teacher)
     */
    public function getSubmissionsForReview($teacherId, $departmentId = null, $status = 'submitted') {
        try {
            // Get teacher's supervised students
            $query = "SELECT p.*, s.full_name as student_name, s.username as student_id,
                     d.dept_name, b.batch_name,
                     st.type_name as submission_type,
                     sub.*,
                     sub.submission_date as submitted_date
                     FROM projects p
                     JOIN students s ON p.student_id = s.id
                     JOIN departments d ON p.department_id = d.id
                     JOIN batches b ON p.batch_id = b.id
                     JOIN submissions sub ON p.id = sub.project_id
                     JOIN submission_types st ON sub.submission_type_id = st.id
                     WHERE p.supervisor_id = :teacher_id
                     AND sub.status = :status
                     ORDER BY sub.submission_date ASC";
            
            if ($departmentId) {
                $query .= " AND p.department_id = :dept_id";
            }
            
            $stmt = $this->db->prepare($query);
            $params = [':teacher_id' => $teacherId, ':status' => $status];
            if ($departmentId) {
                $params[':dept_id'] = $departmentId;
            }
            
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching submissions for review: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Add feedback to submission
     */
    public function addFeedback($data) {
        try {
            $query = "INSERT INTO submission_feedback (submission_id, teacher_id, 
                     feedback_type, comment, page_number, line_number) 
                     VALUES (:submission_id, :teacher_id, :feedback_type, 
                     :comment, :page_number, :line_number)";
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                ':submission_id' => $data['submission_id'],
                ':teacher_id' => $data['teacher_id'],
                ':feedback_type' => $data['feedback_type'],
                ':comment' => $data['comment'],
                ':page_number' => $data['page_number'] ?? null,
                ':line_number' => $data['line_number'] ?? null
            ]);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Feedback added successfully',
                    'feedback_id' => $this->db->lastInsertId()
                ];
            }
            
            return ['success' => false, 'message' => 'Failed to add feedback'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Review submission (approve/reject)
     */
    public function reviewSubmission($submissionId, $teacherId, $status, $comments = null, $score = null) {
        try {
            $query = "UPDATE submissions SET 
                     reviewed_by = :teacher_id,
                     review_date = NOW(),
                     review_comments = :comments,
                     review_status = :status,
                     review_score = :score,
                     status = :submission_status
                     WHERE id = :submission_id";
            
            // Map review status to submission status
            $submissionStatus = 'under_review';
            if ($status === 'accepted') {
                $submissionStatus = 'approved';
            } elseif ($status === 'needs_revision') {
                $submissionStatus = 'resubmit';
            }
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                ':teacher_id' => $teacherId,
                ':comments' => $comments,
                ':status' => $status,
                ':score' => $score,
                ':submission_status' => $submissionStatus,
                ':submission_id' => $submissionId
            ]);
            
            if ($result) {
                // Get student info for notification
                $studentInfo = $this->getSubmissionStudent($submissionId);
                
                // TODO: Send email notification to student
                // $this->sendReviewNotification($studentInfo, $status, $comments);
                
                return [
                    'success' => true,
                    'message' => 'Submission reviewed successfully'
                ];
            }
            
            return ['success' => false, 'message' => 'Failed to update review'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get submission feedback
     */
    public function getSubmissionFeedback($submissionId) {
        try {
            $query = "SELECT sf.*, t.full_name as teacher_name, 
                     t.email as teacher_email,
                     s.full_name as resolved_by_name
                     FROM submission_feedback sf
                     JOIN teachers t ON sf.teacher_id = t.id
                     LEFT JOIN students s ON sf.resolved_by = s.id
                     WHERE sf.submission_id = :submission_id
                     ORDER BY sf.created_at DESC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([':submission_id' => $submissionId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching feedback: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Mark feedback as resolved
     */
    public function resolveFeedback($feedbackId, $studentId) {
        try {
            $query = "UPDATE submission_feedback SET 
                     is_resolved = TRUE,
                     resolved_by = :student_id,
                     resolved_at = NOW()
                     WHERE id = :feedback_id";
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                ':student_id' => $studentId,
                ':feedback_id' => $feedbackId
            ]);
            
            return $result ? ['success' => true, 'message' => 'Feedback marked as resolved'] 
                          : ['success' => false, 'message' => 'Failed to resolve feedback'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get submission statistics for dashboard
     */
    public function getSubmissionStatistics($studentId = null, $teacherId = null, $departmentId = null) {
        try {
            $whereClause = "WHERE 1=1";
            $params = [];
            
            if ($studentId) {
                $whereClause .= " AND s.student_id = :student_id";
                $params[':student_id'] = $studentId;
            }
            
            if ($teacherId) {
                $whereClause .= " AND p.supervisor_id = :teacher_id";
                $params[':teacher_id'] = $teacherId;
            }
            
            if ($departmentId) {
                $whereClause .= " AND p.department_id = :dept_id";
                $params[':dept_id'] = $departmentId;
            }
            
            $query = "SELECT 
                     COUNT(CASE WHEN sub.status = 'submitted' THEN 1 END) as pending_count,
                     COUNT(CASE WHEN sub.status = 'under_review' THEN 1 END) as under_review_count,
                     COUNT(CASE WHEN sub.status = 'approved' THEN 1 END) as approved_count,
                     COUNT(CASE WHEN sub.status = 'rejected' THEN 1 END) as rejected_count,
                     COUNT(CASE WHEN sub.status = 'resubmit' THEN 1 END) as resubmit_count,
                     COUNT(*) as total_count
                     FROM submissions sub
                     JOIN projects p ON sub.project_id = p.id
                     $whereClause";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching submission statistics: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Check if deadline is passed
     */
    private function checkDeadline($submissionTypeId, $batchId) {
        try {
            $query = "SELECT sd.* FROM submission_deadlines sd
                     WHERE sd.submission_type_id = :type_id
                     AND sd.batch_id = :batch_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':type_id' => $submissionTypeId,
                ':batch_id' => $batchId
            ]);
            
            $deadline = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$deadline) {
                return ['success' => true, 'message' => 'No deadline set'];
            }
            
            $today = new DateTime();
            $deadlineDate = new DateTime($deadline['deadline_date']);
            
            if ($today > $deadlineDate && !$deadline['allow_late_submission']) {
                return [
                    'success' => false,
                    'message' => 'Submission deadline has passed. Late submissions not allowed.'
                ];
            }
            
            return ['success' => true, 'deadline' => $deadline];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Error checking deadline'];
        }
    }
    
    /**
     * Validate uploaded file
     */
    private function validateFile($file, $submissionTypeId) {
        // Check if file was uploaded
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'File upload error.'];
        }
        
        // Get submission type requirements
        $type = $this->getSubmissionType($submissionTypeId);
        if (!$type) {
            return ['success' => false, 'message' => 'Invalid submission type.'];
        }
        
        // Check file size
        $maxSize = ($type['max_file_size'] ?? 50) * 1024 * 1024; // Convert MB to bytes
        if ($file['size'] > $maxSize) {
            return [
                'success' => false, 
                'message' => "File too large. Maximum size: {$type['max_file_size']}MB"
            ];
        }
        
        // Check file extension
        $allowedExtensions = explode(',', $type['allowed_extensions'] ?? 'pdf,doc,docx,zip');
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($fileExtension, $allowedExtensions)) {
            return [
                'success' => false,
                'message' => "Invalid file type. Allowed: " . implode(', ', $allowedExtensions)
            ];
        }
        
        return ['success' => true, 'message' => 'File validation passed'];
    }
    
    /**
     * Generate unique filename
     */
    private function generateFileName($file, $data) {
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $timestamp = time();
        $random = bin2hex(random_bytes(4));
        
        return "sub_{$data['student_id']}_{$data['submission_type_id']}_{$timestamp}_{$random}.{$extension}";
    }
    
    /**
     * Archive old version
     */
    private function archiveVersion($submission) {
        try {
            $query = "INSERT INTO submission_versions (submission_id, version, 
                     file_name, file_path, file_size, submitted_by) 
                     VALUES (:submission_id, :version, :file_name, 
                     :file_path, :file_size, :submitted_by)";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':submission_id' => $submission['id'],
                ':version' => $submission['version'],
                ':file_name' => $submission['file_name'],
                ':file_path' => $submission['file_path'],
                ':file_size' => $submission['file_size'],
                ':submitted_by' => $submission['student_id']
            ]);
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Error archiving version: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get student's project
     */
    private function getStudentProject($studentId) {
        try {
            $query = "SELECT p.* FROM projects p
                     WHERE p.student_id = :student_id
                     AND p.status IN ('approved', 'in_progress')
                     ORDER BY p.id DESC LIMIT 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([':student_id' => $studentId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching student project: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get existing submission
     */
    private function getStudentSubmission($studentId, $typeId, $projectId) {
        try {
            $query = "SELECT * FROM submissions 
                     WHERE student_id = :student_id 
                     AND submission_type_id = :type_id
                     AND project_id = :project_id
                     ORDER BY version DESC LIMIT 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':student_id' => $studentId,
                ':type_id' => $typeId,
                ':project_id' => $projectId
            ]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            return null;
        }
    }
    
    /**
     * Get submission type
     */
    private function getSubmissionType($typeId) {
        try {
            $query = "SELECT * FROM submission_types WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':id' => $typeId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            return null;
        }
    }
    
    /**
     * Get submission student info
     */
    private function getSubmissionStudent($submissionId) {
        try {
            $query = "SELECT s.*, sub.* FROM submissions sub
                     JOIN students s ON sub.student_id = s.id
                     WHERE sub.id = :submission_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([':submission_id' => $submissionId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            return null;
        }
    }
}
?>