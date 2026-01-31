<?php
require_once '../includes/config/constants.php';
require_once '../includes/classes/Database.php';
require_once '../includes/classes/Session.php';
require_once '../includes/classes/Auth.php';
require_once '../includes/classes/TemplateManager.php';

Session::init();
$auth = new Auth();
$auth->requireRole(['student', 'teacher', 'admin']);

$user = $auth->getUser();
$templateId = $_GET['id'] ?? 0;

if (!$templateId) {
    header('Location: submissions.php?error=Invalid template ID');
    exit();
}

try {
    $templateManager = new TemplateManager();
    
    // Get template details
    $db = Database::getInstance()->getConnection();
    $query = "SELECT * FROM document_templates WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->execute([':id' => $templateId]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$template) {
        header('Location: submissions.php?error=Template not found');
        exit();
    }
    
    // Check if template is active
    if (!$template['is_active']) {
        header('Location: submissions.php?error=Template is not available');
        exit();
    }
    
    // Check access permissions
    if ($user['user_type'] === 'student') {
        // Check if template is for student's department/batch
        $studentQuery = "SELECT department_id, batch_id FROM students 
                        WHERE id = :student_id";
        $studentStmt = $db->prepare($studentQuery);
        $studentStmt->execute([':student_id' => $user['user_id']]);
        $student = $studentStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($template['department_id'] && $template['department_id'] != $student['department_id']) {
            header('Location: submissions.php?error=Access denied');
            exit();
        }
        if ($template['batch_id'] && $template['batch_id'] != $student['batch_id']) {
            header('Location: submissions.php?error=Access denied');
            exit();
        }
    }
    
    // Check if file exists
    if (!file_exists($template['file_path'])) {
        header('Location: submissions.php?error=File not found on server');
        exit();
    }
    
    // Increment download count
    $templateManager->incrementDownloadCount($templateId);
    
    // Send file
    header('Content-Description: File Transfer');
    header('Content-Type: ' . mime_content_type($template['file_path']));
    header('Content-Disposition: attachment; filename="' . basename($template['file_name']) . '"');
    header('Content-Length: ' . filesize($template['file_path']));
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Expires: 0');
    
    readfile($template['file_path']);
    exit();
    
} catch (PDOException $e) {
    header('Location: submissions.php?error=Database error');
    exit();
}
?>