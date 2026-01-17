<!-- File: includes/helpers/FileUpload.php -->
<?php
/**
 * File Upload Helper Class
 * Handles secure file uploads for submissions
 */
class FileUpload {
    private $allowedTypes = [];
    private $maxSize = 0;
    private $uploadDir = '';
    
    public function __construct() {
        // Load from constants
        $this->allowedTypes = explode(',', ALLOWED_TYPES);
        $this->maxSize = MAX_FILE_SIZE;
        $this->uploadDir = UPLOAD_DIR . 'submissions/';
        
        // Create upload directory if it doesn't exist
        if (!file_exists($this->uploadDir)) {
            mkdir($this->uploadDir, 0777, true);
        }
    }
    
    /**
     * Upload a file with validation
     */
    public function upload($file, $studentId, $projectId, $type) {
        try {
            // Validate file
            $validation = $this->validateFile($file);
            if (!$validation['success']) {
                return $validation;
            }
            
            // Generate secure filename
            $fileName = $this->generateFileName($file, $studentId, $projectId, $type);
            $filePath = $this->uploadDir . $fileName;
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                return [
                    'success' => true,
                    'file_name' => $fileName,
                    'file_path' => $filePath,
                    'file_size' => $file['size'],
                    'file_type' => $file['type']
                ];
            }
            
            return ['success' => false, 'message' => 'Failed to upload file'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Upload error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Validate uploaded file
     */
    private function validateFile($file) {
        // Check if file was uploaded
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'No file uploaded or upload error'];
        }
        
        // Check file size
        if ($file['size'] > $this->maxSize) {
            return ['success' => false, 'message' => 'File size exceeds 50MB limit'];
        }
        
        // Check file type using MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $this->allowedTypes)) {
            $allowedExtensions = [];
            foreach ($this->allowedTypes as $mime) {
                if ($mime === 'application/pdf') $allowedExtensions[] = 'PDF';
                if ($mime === 'application/msword') $allowedExtensions[] = 'DOC';
                if ($mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') $allowedExtensions[] = 'DOCX';
                if ($mime === 'application/zip') $allowedExtensions[] = 'ZIP';
                if ($mime === 'application/x-zip-compressed') $allowedExtensions[] = 'ZIP';
            }
            return ['success' => false, 'message' => 'File type not allowed. Allowed types: ' . implode(', ', $allowedExtensions)];
        }
        
        // Check for malicious files
        if (!$this->isFileSafe($file['tmp_name'])) {
            return ['success' => false, 'message' => 'File rejected for security reasons'];
        }
        
        return ['success' => true];
    }
    
    /**
     * Generate secure, unique filename
     */
    private function generateFileName($file, $studentId, $projectId, $type) {
        $originalName = pathinfo($file['name'], PATHINFO_FILENAME);
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        
        // Sanitize filename
        $sanitizedName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $originalName);
        $sanitizedName = substr($sanitizedName, 0, 50);
        
        // Create unique filename
        $uniqueId = uniqid();
        $timestamp = time();
        
        return "ST{$studentId}_PR{$projectId}_{$type}_{$timestamp}_{$uniqueId}.{$extension}";
    }
    
    /**
     * Check if file is safe (basic security check)
     */
    private function isFileSafe($filePath) {
        // Check if file is empty
        if (filesize($filePath) === 0) {
            return false;
        }
        
        // Check for PHP tags in text files
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (in_array($extension, ['php', 'phtml', 'phar'])) {
            return false;
        }
        
        // For ZIP files, check contents
        if ($extension === 'zip') {
            return $this->checkZipSafety($filePath);
        }
        
        return true;
    }
    
    /**
     * Check ZIP file for dangerous contents
     */
    private function checkZipSafety($zipPath) {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) === TRUE) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                
                // Reject dangerous file types in ZIP
                if (in_array($extension, ['php', 'phtml', 'phar', 'exe', 'bat', 'sh'])) {
                    $zip->close();
                    return false;
                }
            }
            $zip->close();
            return true;
        }
        return false;
    }
    
    /**
     * Delete a file
     */
    public function deleteFile($filePath) {
        if (file_exists($filePath)) {
            return unlink($filePath);
        }
        return false;
    }
    
    /**
     * Get file download URL
     */
    public function getDownloadUrl($filePath) {
        $relativePath = str_replace(UPLOAD_DIR, '', $filePath);
        return UPLOAD_URL . $relativePath;
    }
}
?>