<?php
require_once '../includes/config/constants.php';
require_once '../includes/classes/Database.php';
require_once '../includes/classes/Session.php';
require_once '../includes/classes/Auth.php';
require_once '../includes/classes/SupervisorManager.php';
require_once '../includes/classes/ProjectManager.php';
require_once '../includes/classes/UserManager.php';

Session::init();
$auth = new Auth();
$auth->requireRole(['admin']);

$user = $auth->getUser();
$supervisorManager = new SupervisorManager();
$projectManager = new ProjectManager();
$userManager = new UserManager();

// Get approved projects without supervisors
$approvedProjects = $projectManager->getDepartmentProjects($user['department_id'], 'approved');
$projectsWithoutSupervisors = array_filter($approvedProjects, function($project) {
    return empty($project['supervisor_id']);
});

// Get department teachers
$teachers = $supervisorManager->getDepartmentTeachers($user['department_id']);

// Get supervisor workload
$workload = $supervisorManager->getSupervisorWorkload($user['department_id']);

$message = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['assign_supervisor'])) {
        $projectId = $_POST['project_id'];
        $teacherId = $_POST['teacher_id'];
        $comments = trim($_POST['comments'] ?? '');
        
        $result = $supervisorManager->assignSupervisor($projectId, $teacherId, $user['user_id'], 'manual', $comments);
        if ($result['success']) {
            $message = $result['message'];
            header('Location: assign_supervisor.php?success=' . urlencode($message));
            exit();
        } else {
            $error = $result['message'];
        }
    }
    elseif (isset($_POST['auto_assign'])) {
        $projectId = $_POST['project_id'];
        
        $result = $supervisorManager->autoAssignSupervisor($projectId, $user['department_id'], $user['user_id']);
        if ($result['success']) {
            $message = $result['message'];
            header('Location: assign_supervisor.php?success=' . urlencode($message));
            exit();
        } else {
            $error = $result['message'];
        }
    }
    elseif (isset($_POST['bulk_auto_assign'])) {
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($projectsWithoutSupervisors as $project) {
            $result = $supervisorManager->autoAssignSupervisor($project['id'], $user['department_id'], $user['user_id']);
            if ($result['success']) {
                $successCount++;
            } else {
                $errorCount++;
            }
        }
        
        $message = "Auto-assigned $successCount supervisors. $errorCount failed.";
        header('Location: assign_supervisor.php?success=' . urlencode($message));
        exit();
    }
}

// Show success message from redirect
if (isset($_GET['success'])) {
    $message = $_GET['success'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supervisor Assignment - FYPMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <style>
        .workload-bar {
            height: 8px;
            border-radius: 4px;
            margin-top: 5px;
        }
        .workload-low { background: #28a745; }
        .workload-medium { background: #ffc107; }
        .workload-high { background: #dc3545; }
        .project-card {
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        .project-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="p-4">
            <h4 class="text-center">FYPMS</h4>
            <hr class="bg-white">
            <div class="text-center mb-4">
                <div class="rounded-circle bg-white d-inline-flex align-items-center justify-content-center" 
                     style="width: 60px; height: 60px;">
                    <i class="fas fa-user-tie" style="color: #667eea;"></i>
                </div>
                <h6 class="mt-2"><?php echo htmlspecialchars($user['full_name']); ?></h6>
                <small>Department Head</small>
            </div>
            
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="projects.php">
                        <i class="fas fa-project-diagram me-2"></i> Projects
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="assign_supervisor.php">
                        <i class="fas fa-user-tie me-2"></i> Assign Supervisor
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="notices.php">
                        <i class="fas fa-bullhorn me-2"></i> Notices
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
    
    <div class="main-content">
        <!-- Header -->
        <nav class="navbar navbar-light bg-light mb-4 rounded">
            <div class="container-fluid">
                <h3 class="mb-0">Supervisor Assignment</h3>
                <div>
                    <?php if (count($projectsWithoutSupervisors) > 0): ?>
                        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" 
                                data-bs-target="#bulkAutoAssignModal">
                            <i class="fas fa-robot"></i> Auto-Assign All
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </nav>
        
        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Left Column: Projects without Supervisors -->
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header bg-warning">
                        <h5 class="mb-0">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Projects Without Supervisors
                            <span class="badge bg-light text-dark"><?php echo count($projectsWithoutSupervisors); ?></span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($projectsWithoutSupervisors)): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> All approved projects have supervisors assigned.
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($projectsWithoutSupervisors as $project): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="project-card">
                                        <h6><?php echo htmlspecialchars($project['title']); ?></h6>
                                        <p class="mb-2">
                                            <strong>Student:</strong> <?php echo htmlspecialchars($project['student_name']); ?><br>
                                            <small class="text-muted">ID: <?php echo htmlspecialchars($project['student_id']); ?></small>
                                        </p>
                                        <?php if ($project['group_name']): ?>
                                            <p class="mb-2">
                                                <strong>Group:</strong> <?php echo htmlspecialchars($project['group_name']); ?>
                                            </p>
                                        <?php endif; ?>
                                        <p class="mb-3">
                                            <small>
                                                <i class="fas fa-calendar"></i> 
                                                Approved: <?php echo date('M d, Y', strtotime($project['approved_at'])); ?>
                                            </small>
                                        </p>
                                        <div class="d-flex gap-2">
                                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" 
                                                    data-bs-target="#assignSupervisorModal"
                                                    data-project-id="<?php echo $project['id']; ?>"
                                                    data-project-title="<?php echo htmlspecialchars($project['title']); ?>"
                                                    data-student-name="<?php echo htmlspecialchars($project['student_name']); ?>">
                                                <i class="fas fa-user-tie"></i> Assign
                                            </button>
                                            <form method="POST" action="" class="d-inline">
                                                <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">
                                                <button type="submit" name="auto_assign" class="btn btn-sm btn-outline-success">
                                                    <i class="fas fa-robot"></i> Auto-Assign
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- All Projects with Supervisors -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>
                            All Projects with Supervisors
                            <span class="badge bg-primary"><?php echo count($approvedProjects) - count($projectsWithoutSupervisors); ?></span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="projectsTable" class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Project</th>
                                        <th>Student</th>
                                        <th>Supervisor</th>
                                        <th>Load</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $assignedProjects = array_filter($approvedProjects, function($project) {
                                        return !empty($project['supervisor_id']);
                                    });
                                    $index = 1;
                                    ?>
                                    <?php foreach ($assignedProjects as $project): 
                                        $teacher = $supervisorManager->getTeacherById($project['supervisor_id']);
                                        $currentLoad = $teacher['current_load'] ?? 0;
                                        $maxStudents = $teacher['max_students'] ?? 5;
                                        $loadPercentage = $maxStudents > 0 ? ($currentLoad / $maxStudents) * 100 : 0;
                                        $loadClass = $loadPercentage < 60 ? 'success' : ($loadPercentage < 85 ? 'warning' : 'danger');
                                    ?>
                                    <tr>
                                        <td><?php echo $index++; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($project['title']); ?></strong>
                                            <?php if ($project['group_name']): ?>
                                                <br><small class="text-muted">Group: <?php echo htmlspecialchars($project['group_name']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($project['student_name']); ?></td>
                                        <td>
                                            <?php if ($project['supervisor_name']): ?>
                                                <?php echo htmlspecialchars($project['supervisor_name']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Not assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($project['supervisor_name']): ?>
                                                <div class="d-flex align-items-center">
                                                    <span class="me-2"><?php echo $currentLoad; ?>/<?php echo $maxStudents; ?></span>
                                                    <div class="progress flex-grow-1" style="height: 8px;">
                                                        <div class="progress-bar bg-<?php echo $loadClass; ?>" 
                                                             style="width: <?php echo $loadPercentage; ?>%"></div>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Not assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-success">Assigned</span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Column: Supervisor Workload -->
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-bar me-2"></i>
                            Supervisor Workload
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($workload)): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i> No teachers available.
                            </div>
                        <?php else: ?>
                            <?php foreach ($workload as $teacher): 
                                $currentLoad = $teacher['current_load'] ?? 0;
                                $maxStudents = $teacher['max_students'] ?? 5;
                                $loadPercentage = $maxStudents > 0 ? ($currentLoad / $maxStudents) * 100 : 0;
                                $loadClass = $loadPercentage < 60 ? 'success' : ($loadPercentage < 85 ? 'warning' : 'danger');
                                $availability = $currentLoad < $maxStudents ? 'Available' : 'Full';
                                $availabilityClass = $currentLoad < $maxStudents ? 'success' : 'danger';
                            ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <strong><?php echo htmlspecialchars($teacher['full_name']); ?></strong>
                                    <span class="badge bg-<?php echo $availabilityClass; ?>">
                                        <?php echo $availability; ?>
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <small>Load: <?php echo $currentLoad; ?>/<?php echo $maxStudents; ?></small>
                                    <small><?php echo round($loadPercentage); ?>%</small>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-<?php echo $loadClass; ?>" 
                                         style="width: <?php echo $loadPercentage; ?>%"></div>
                                </div>
                                <?php if ($teacher['specializations']): ?>
                                    <small class="text-muted">
                                        <i class="fas fa-tags"></i> <?php echo htmlspecialchars($teacher['specializations']); ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                            <hr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Quick Stats -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Quick Statistics</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <span>Total Teachers</span>
                                <strong><?php echo count($teachers); ?></strong>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <span>Available Teachers</span>
                                <strong>
                                    <?php 
                                    $availableTeachers = array_filter($teachers, function($teacher) {
                                        $currentLoad = $teacher['current_load'] ?? 0;
                                        $maxStudents = $teacher['max_students'] ?? 5;
                                        return $currentLoad < $maxStudents;
                                    });
                                    echo count($availableTeachers);
                                    ?>
                                </strong>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <span>Approved Projects</span>
                                <strong><?php echo count($approvedProjects); ?></strong>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <span>Without Supervisors</span>
                                <strong class="text-danger"><?php echo count($projectsWithoutSupervisors); ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Assign Supervisor Modal -->
    <div class="modal fade" id="assignSupervisorModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">Assign Supervisor</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="project_id" id="assignProjectId">
                        <div class="mb-3">
                            <label class="form-label">Project</label>
                            <input type="text" class="form-control" id="assignProjectTitle" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Student</label>
                            <input type="text" class="form-control" id="assignStudentName" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Select Supervisor *</label>
                            <select class="form-control" name="teacher_id" required>
                                <option value="">-- Select a Supervisor --</option>
                                <?php foreach ($teachers as $teacher): 
                                    $currentLoad = $teacher['current_load'] ?? 0;
                                    $maxStudents = $teacher['max_students'] ?? 5;
                                    $isAvailable = $currentLoad < $maxStudents;
                                ?>
                                <option value="<?php echo $teacher['id']; ?>" 
                                        <?php echo !$isAvailable ? 'disabled' : ''; ?>>
                                    <?php echo htmlspecialchars($teacher['full_name']); ?>
                                    (<?php echo $currentLoad; ?>/<?php echo $maxStudents; ?>)
                                    <?php if ($teacher['specializations']): ?>
                                        - <?php echo htmlspecialchars($teacher['specializations']); ?>
                                    <?php endif; ?>
                                    <?php if (!$isAvailable): ?> - FULL<?php endif; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Comments (Optional)</label>
                            <textarea class="form-control" name="comments" rows="3" 
                                      placeholder="Add any comments about this assignment..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="assign_supervisor" class="btn btn-primary">Assign Supervisor</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Bulk Auto-Assign Modal -->
    <div class="modal fade" id="bulkAutoAssignModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">Bulk Auto-Assign Supervisors</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>This will automatically assign supervisors to <strong><?php echo count($projectsWithoutSupervisors); ?></strong> projects without supervisors.</p>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            The system will assign supervisors based on:
                            <ul class="mb-0 mt-2">
                                <li>Current workload (least loaded supervisors first)</li>
                                <li>Supervisor availability</li>
                                <li>Maximum student limits</li>
                            </ul>
                        </div>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Note:</strong> This action cannot be undone. You can manually reassign if needed.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="bulk_auto_assign" class="btn btn-success">
                            <i class="fas fa-robot"></i> Auto-Assign All
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            $('#projectsTable').DataTable({
                pageLength: 10,
                responsive: true,
                order: [[0, 'asc']]
            });
            
            // Assign Supervisor Modal
            $('#assignSupervisorModal').on('show.bs.modal', function(event) {
                var button = $(event.relatedTarget);
                $('#assignProjectId').val(button.data('project-id'));
                $('#assignProjectTitle').val(button.data('project-title'));
                $('#assignStudentName').val(button.data('student-name'));
            });
        });
    </script>
</body>
</html>