<!-- admin/ajax_get_available_supervisors.php -->
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

$batchId = $_GET['batch_id'] ?? 0;
$departmentId = $user['department_id'];

$supervisors = $supervisorManager->getAvailableSupervisors($departmentId, $batchId);

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'data' => $supervisors
]);
?>