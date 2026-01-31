<?php
require_once '../includes/config/constants.php';
require_once '../includes/classes/Database.php';
require_once '../includes/classes/Session.php';
require_once '../includes/classes/Auth.php';
require_once '../includes/classes/SubmissionManager.php';

Session::init();
$auth = new Auth();
$auth->requireRole(['student', 'teacher', 'admin']);

$user = $auth->getUser();
$submissionId = $_GET['id'] ?? 0;

if (!$submissionId) {
    header('Location: submissions.php?error=Invalid submission ID');
    exit();
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Get submission details with access control
    $query = "SELECT s.*, st.type_name, p.title as project_title 
              FROM submissions s
              JOIN submission_types st ON s.submission_type_id = st.id
              JOIN projects p ON s.project_id = p.id
              WHERE s.id = :id";
    
    $stmt = $db->prepare($query);
    $stmt->execute([':id' => $submissionId]);
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$submission) {
        header('Location: submissions.php?error=Submission not found');
        exit();
    }
    
    // Check access permissions
    $hasAccess = false;
    
    if ($user['user_type'] === 'student' && $submission['student_id'] == $user['user_id']) {
        $hasAccess = true;
    } elseif ($user['user_type'] === 'teacher') {
        // Check if teacher is supervisor
        $checkQuery = "SELECT id FROM projects 
                      WHERE id = :project_id 
                      AND supervisor_id = :teacher_id";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->execute([
            ':project_id' => $submission['project_id'],
            ':teacher_id' => $user['user_id']
        ]);
        $hasAccess = (bool)$checkStmt->fetch();
    } elseif ($user['user_type'] === 'admin') {
        // Check if admin is from same department
        $checkQuery = "SELECT department_id FROM projects WHERE id = :project_id";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->execute([':project_id' => $submission['project_id']]);
        $projectDept = $checkStmt->fetch(PDO::FETCH_ASSOC);
        $hasAccess = ($projectDept && $projectDept['department_id'] == $user['department_id']);
    }
    
    if (!$hasAccess) {
        header('Location: dashboard.php?error=Access denied');
        exit();
    }
    
    // Check if file exists
    if (!file_exists($submission['file_path'])) {
        header('Location: submissions.php?error=File not found on server');
        exit();
    }
    
    // Log download (for teachers/admins)
    if ($user['user_type'] !== 'student') {
        $logQuery = "INSERT INTO activity_logs (user_id, user_type, action, details) 
                     VALUES (:user_id, :user_type, :action, :details)";
        $logStmt = $db->prepare($logQuery);
        $logStmt->execute([
            ':user_id' => $user['username'],
            ':user_type' => $user['user_type'],
            ':action' => 'download_submission',
            ':details' => "Downloaded submission: {$submission['file_name']} (ID: {$submissionId})"
        ]);
    }
    
    // Send file
    header('Content-Description: File Transfer');
    header('Content-Type: ' . mime_content_type($submission['file_path']));
    header('Content-Disposition: attachment; filename="' . basename($submission['file_name']) . '"');
    header('Content-Length: ' . filesize($submission['file_path']));
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Expires: 0');
    
    readfile($submission['file_path']);
    exit();
    
} catch (PDOException $e) {
    header('Location: submissions.php?error=Database error');
    exit();
}
?>