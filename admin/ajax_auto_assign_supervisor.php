<!-- admin/ajax_auto_assign_supervisor.php -->
<?php
require_once '../includes/config/constants.php';
require_once '../includes/classes/Database.php';
require_once '../includes/classes/Session.php';
require_once '../includes/classes/Auth.php';
require_once '../includes/classes/SupervisorManager.php';

Session::init();
$auth = new Auth();
$auth->requireRole(['admin']);

$user = $auth->getUser();
$supervisorManager = new SupervisorManager();

$projectId = $_POST['project_id'] ?? 0;

if ($projectId) {
    $result = $supervisorManager->autoAssignSupervisor($projectId, $user['user_id']);
    header('Content-Type: application/json');
    echo json_encode($result);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid project ID']);
}
?>