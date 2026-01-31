<?php
/**
 * TemplateManager Class
 * Handles document templates for students
 */
class TemplateManager {
    private $db;
    private $uploadDir;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/fypms/final-year-project-management-system/assets/uploads/templates/';
        
        // Create upload directory if it doesn't exist
        if (!file_exists($this->uploadDir)) {
            mkdir($this->uploadDir, 0777, true);
        }
    }
    
    /**
     * Upload template
     */
    public function uploadTemplate($data, $file) {
        try {
            // Validate file
            $allowedTypes = ['application/pdf', 'application/msword', 
                           'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            
            if (!in_array($file['type'], $allowedTypes)) {
                return ['success' => false, 'message' => 'Invalid file type. Only PDF and Word documents allowed.'];
            }
            
            if ($file['size'] > 10 * 1024 * 1024) { // 10MB
                return ['success' => false, 'message' => 'File too large. Maximum size: 10MB'];
            }
            
            // Generate unique filename
            $fileName = $this->generateTemplateName($file);
            $filePath = $this->uploadDir . $fileName;
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                return ['success' => false, 'message' => 'Failed to upload template.'];
            }
            
            // Insert into database
            $query = "INSERT INTO document_templates (template_name, description, 
                     file_name, file_path, file_size, template_type, 
                     department_id, batch_id, uploaded_by) 
                     VALUES (:name, :description, :file_name, :file_path, 
                     :file_size, :type, :dept_id, :batch_id, :uploaded_by)";
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                ':name' => $data['template_name'],
                ':description' => $data['description'] ?? null,
                ':file_name' => $file['name'],
                ':file_path' => $filePath,
                ':file_size' => $file['size'],
                ':type' => $data['template_type'],
                ':dept_id' => $data['department_id'] ?? null,
                ':batch_id' => $data['batch_id'] ?? null,
                ':uploaded_by' => $data['uploaded_by']
            ]);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Template uploaded successfully',
                    'template_id' => $this->db->lastInsertId()
                ];
            }
            
            return ['success' => false, 'message' => 'Failed to save template'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get templates for student
     */
    public function getTemplatesForStudent($studentId, $departmentId = null, $batchId = null) {
        try {
            // If department/batch not provided, get from student
            if (!$departmentId || !$batchId) {
                $student = $this->getStudentInfo($studentId);
                if ($student) {
                    $departmentId = $student['department_id'];
                    $batchId = $student['batch_id'];
                }
            }
            
            $query = "SELECT dt.*, t.full_name as uploaded_by_name,
                     d.dept_name, b.batch_name
                     FROM document_templates dt
                     LEFT JOIN teachers t ON dt.uploaded_by = t.id
                     LEFT JOIN departments d ON dt.department_id = d.id
                     LEFT JOIN batches b ON dt.batch_id = b.id
                     WHERE dt.is_active = TRUE
                     AND (dt.department_id IS NULL OR dt.department_id = :dept_id)
                     AND (dt.batch_id IS NULL OR dt.batch_id = :batch_id)
                     ORDER BY dt.template_type, dt.template_name";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':dept_id' => $departmentId,
                ':batch_id' => $batchId
            ]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching templates: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get all templates (admin)
     */
    public function getAllTemplates($departmentId = null) {
        try {
            $whereClause = "WHERE dt.is_active = TRUE";
            $params = [];
            
            if ($departmentId) {
                $whereClause .= " AND (dt.department_id IS NULL OR dt.department_id = :dept_id)";
                $params[':dept_id'] = $departmentId;
            }
            
            $query = "SELECT dt.*, t.full_name as uploaded_by_name,
                     d.dept_name, b.batch_name
                     FROM document_templates dt
                     LEFT JOIN teachers t ON dt.uploaded_by = t.id
                     LEFT JOIN departments d ON dt.department_id = d.id
                     LEFT JOIN batches b ON dt.batch_id = b.id
                     $whereClause
                     ORDER BY dt.created_at DESC";
            
            $stmt = $departmentId ? 
                   $this->db->prepare($query) : 
                   $this->db->query($query);
                   
            if ($departmentId) {
                $stmt->execute($params);
            }
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching all templates: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Increment download count
     */
    public function incrementDownloadCount($templateId) {
        try {
            $query = "UPDATE document_templates 
                     SET download_count = download_count + 1 
                     WHERE id = :template_id";
            
            $stmt = $this->db->prepare($query);
            return $stmt->execute([':template_id' => $templateId]);
            
        } catch (PDOException $e) {
            error_log("Error incrementing download count: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete template
     */
    public function deleteTemplate($templateId) {
        try {
            // Get template info first
            $template = $this->getTemplateById($templateId);
            if (!$template) {
                return ['success' => false, 'message' => 'Template not found'];
            }
            
            // Delete file
            if (file_exists($template['file_path'])) {
                unlink($template['file_path']);
            }
            
            // Delete from database
            $query = "DELETE FROM document_templates WHERE id = :template_id";
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([':template_id' => $templateId]);
            
            if ($result) {
                return ['success' => true, 'message' => 'Template deleted successfully'];
            }
            
            return ['success' => false, 'message' => 'Failed to delete template'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Generate template filename
     */
    private function generateTemplateName($file) {
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $timestamp = time();
        $random = bin2hex(random_bytes(4));
        
        return "template_{$timestamp}_{$random}.{$extension}";
    }
    
    /**
     * Get student info
     */
    private function getStudentInfo($studentId) {
        try {
            $query = "SELECT department_id, batch_id FROM students 
                     WHERE id = :student_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([':student_id' => $studentId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            return null;
        }
    }
    
    /**
     * Get template by ID
     */
    private function getTemplateById($templateId) {
        try {
            $query = "SELECT * FROM document_templates WHERE id = :template_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':template_id' => $templateId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            return null;
        }
    }
}
?>