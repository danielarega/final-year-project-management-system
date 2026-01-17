<?php
// File: admin/supervisor_assignment.php
require_once '../includes/config/constants.php';
require_once '../includes/classes/Database.php';
require_once '../includes/classes/Session.php';
require_once '../includes/classes/Auth.php';
require_once '../includes/classes/SupervisorManager.php';
require_once '../includes/classes/ProjectManager.php';
require_once '../includes/classes/BatchManager.php';

Session::init();
$auth = new Auth();
$auth->requireRole(['admin']);

$user = $auth->getUser();
$supervisorManager = new SupervisorManager();
$projectManager = new ProjectManager();
$batchManager = new BatchManager();

// Get batches for filtering
$batches = $batchManager->getBatchesByDepartment($user['department_id']);

// Get selected batch or default to first batch
$selectedBatchId = $_GET['batch_id'] ?? ($batches[0]['id'] ?? 0);
$selectedBatch = $batchManager->getBatchById($selectedBatchId);

// Get projects needing supervisors and teacher workload
$projectsNeedingSupervisors = [];
$teacherWorkload = [];
$availableTeachers = [];

if ($selectedBatch) {
    $projectsNeedingSupervisors = $supervisorManager->getProjectsNeedingSupervisors(
        $user['department_id'], 
        $selectedBatchId
    );
    $teacherWorkload = $supervisorManager->getTeacherWorkload($user['department_id']);
}

$message = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['assign_supervisor'])) {
        $data = [
            'project_id' => $_POST['project_id'],
            'teacher_id' => $_POST['teacher_id'],
            'assigned_by' => $user['user_id'],
            'assignment_type' => 'manual',
            'comments' => $_POST['comments'] ?? null
        ];
        
        $result = $supervisorManager->assignSupervisor($data);
        if ($result['success']) {
            $message = $result['message'];
            header('Location: supervisor_assignment.php?batch_id=' . $selectedBatchId . '&success=' . urlencode($message));
            exit();
        } else {
            $error = $result['message'];
        }
    }
    elseif (isset($_POST['auto_assign'])) {
        $result = $supervisorManager->autoAssignSupervisors($user['department_id'], $user['user_id']);
        if ($result['success']) {
            $message = $result['message'];
            header('Location: supervisor_assignment.php?batch_id=' . $selectedBatchId . '&success=' . urlencode($message));
            exit();
        } else {
            $error = $result['message'];
        }
    }
}

// Show success message from redirect
if (isset($_GET['success'])) {
    $message = $_GET['success'];
}

// Get available teachers for a specific project (for modal)
$availableTeachersForProject = [];
if (isset($_GET['project_id'])) {
    $availableTeachersForProject = $supervisorManager->getAvailableTeachers($_GET['project_id']);
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
        .workload-indicator {
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
            transform: translateY(-2px);
        }
        .teacher-card {
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .specialization-badge {
            background: #667eea;
            color: white;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 11px;
            margin-right: 5px;
            margin-bottom: 5px;
            display: inline-block;
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
                    <a class="nav-link active" href="supervisor_assignment.php">
                        <i class="fas fa-user-tie me-2"></i> Supervisor Assignment
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
                <div>
                    <a href="projects.php" class="btn btn-outline-primary btn-sm me-2">
                        <i class="fas fa-arrow-left"></i> Back to Projects
                    </a>
                    <h3 class="mb-0 d-inline">Supervisor Assignment</h3>
                </div>
                <div>
                    <?php if ($selectedBatch && !empty($projectsNeedingSupervisors)): ?>
                        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#autoAssignModal">
                            <i class="fas fa-robot"></i> Auto Assign
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </nav>
        
        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?php echo nl2br(htmlspecialchars($message)); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Batch Selection -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Select Batch</h5>
            </div>
            <div class="card-body">
                <div class="btn-group" role="group">
                    <?php foreach ($batches as $batch): ?>
                        <a href="supervisor_assignment.php?batch_id=<?php echo $batch['id']; ?>" 
                           class="btn btn-outline-primary <?php echo $batch['id'] == $selectedBatchId ? 'active' : ''; ?>">
                            <?php echo htmlspecialchars($batch['batch_name']); ?> - <?php echo $batch['batch_year']; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <?php if (!$selectedBatch): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> No batch selected or batch not found.
            </div>
        <?php else: ?>
            <!-- Statistics -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3><?php echo count($projectsNeedingSupervisors); ?></h3>
                                    <h6>Projects Needing Supervisors</h6>
                                </div>
                                <i class="fas fa-project-diagram fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3><?php echo count($teacherWorkload); ?></h3>
                                    <h6>Available Teachers</h6>
                                </div>
                                <i class="fas fa-chalkboard-teacher fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <?php 
                    $availableSlots = 0;
                    foreach ($teacherWorkload as $teacher) {
                        $max = $teacher['max_students'] ?? 5;
                        $current = $teacher['current_load'] ?? 0;
                        $availableSlots += max(0, $max - $current);
                    }
                    ?>
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3><?php echo $availableSlots; ?></h3>
                                    <h6>Available Slots</h6>
                                </div>
                                <i class="fas fa-user-plus fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- Left Column: Projects Needing Supervisors -->
                <div class="col-md-8">
                    <div class="card mb-4">
                        <div class="card-header bg-warning">
                            <h5 class="mb-0">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                Projects Needing Supervisors
                                <span class="badge bg-light text-dark"><?php echo count($projectsNeedingSupervisors); ?></span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($projectsNeedingSupervisors)): ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle"></i> All projects have supervisors assigned.
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($projectsNeedingSupervisors as $project): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="project-card">
                                            <h6><?php echo htmlspecialchars($project['title']); ?></h6>
                                            
                                            <div class="mb-2">
                                                <small class="text-muted">
                                                    <i class="fas fa-user-graduate"></i> 
                                                    <?php echo htmlspecialchars($project['student_name']); ?>
                                                </small>
                                            </div>
                                            
                                            <div class="mb-2">
                                                <?php if ($project['group_name']): ?>
                                                    <span class="badge bg-primary">
                                                        <i class="fas fa-users"></i> 
                                                        <?php echo htmlspecialchars($project['group_name']); ?>
                                                    </span>
                                                    <small class="text-muted">
                                                        (<?php echo $project['group_member_count']; ?> members)
                                                    </small>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Individual Project</span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <small class="text-muted">
                                                    <i class="fas fa-calendar"></i> 
                                                    <?php echo $project['batch_name']; ?> - <?php echo $project['batch_year']; ?>
                                                </small>
                                            </div>
                                            
                                            <div class="d-flex justify-content-between">
                                                <small class="text-muted">
                                                    Submitted: <?php echo date('M d, Y', strtotime($project['submitted_at'])); ?>
                                                </small>
                                                <button class="btn btn-sm btn-primary" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#assignSupervisorModal"
                                                        data-project-id="<?php echo $project['id']; ?>"
                                                        data-project-title="<?php echo htmlspecialchars($project['title']); ?>"
                                                        data-student-name="<?php echo htmlspecialchars($project['student_name']); ?>">
                                                    <i class="fas fa-user-tie"></i> Assign Supervisor
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column: Teacher Workload -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-chalkboard-teacher me-2"></i>
                                Teacher Workload & Availability
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($teacherWorkload)): ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i> No teachers available.
                                </div>
                            <?php else: ?>
                                <div style="max-height: 600px; overflow-y: auto;">
                                    <?php foreach ($teacherWorkload as $teacher): 
                                        $maxStudents = $teacher['max_students'] ?? 5;
                                        $currentLoad = $teacher['current_load'] ?? 0;
                                        $percentage = $maxStudents > 0 ? ($currentLoad / $maxStudents * 100) : 0;
                                        $workloadClass = $percentage < 60 ? 'workload-low' : ($percentage < 85 ? 'workload-medium' : 'workload-high');
                                    ?>
                                    <div class="teacher-card">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($teacher['full_name']); ?></h6>
                                                <small class="text-muted"><?php echo htmlspecialchars($teacher['username']); ?></small>
                                            </div>
                                            <span class="badge bg-<?php echo $percentage < 85 ? 'success' : 'danger'; ?>">
                                                <?php echo $currentLoad; ?>/<?php echo $maxStudents; ?>
                                            </span>
                                        </div>
                                        
                                        <?php if ($teacher['specializations']): ?>
                                        <div class="mb-2">
                                            <?php 
                                            $specializations = explode(', ', $teacher['specializations']);
                                            foreach ($specializations as $spec):
                                                if (trim($spec)):
                                            ?>
                                                <span class="specialization-badge"><?php echo htmlspecialchars($spec); ?></span>
                                            <?php 
                                                endif;
                                            endforeach; 
                                            ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <div class="workload-indicator <?php echo $workloadClass; ?>" 
                                             style="width: <?php echo min(100, $percentage); ?>%"></div>
                                        
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                Currently supervising <?php echo $teacher['supervised_count']; ?> project(s)
                                            </small>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Assign Supervisor Modal -->
    <div class="modal fade" id="assignSupervisorModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
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
                            <div id="teacherSelection">
                                <!-- Teachers will be loaded here via AJAX -->
                                <div class="text-center py-3">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="mt-2">Loading available teachers...</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Comments (Optional)</label>
                            <textarea class="form-control" name="comments" rows="3" 
                                      placeholder="Add any comments about this assignment..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="assign_supervisor" class="btn btn-primary" id="assignButton" disabled>
                            <i class="fas fa-user-tie"></i> Assign Supervisor
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Auto Assign Modal -->
    <div class="modal fade" id="autoAssignModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">Auto Assign Supervisors</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            This will automatically assign supervisors to all projects that need them.
                        </div>
                        
                        <div class="mb-3">
                            <p><strong>Projects Needing Supervisors:</strong> <?php echo count($projectsNeedingSupervisors); ?></p>
                            <p><strong>Available Teacher Slots:</strong> <?php echo $availableSlots; ?></p>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            The system will assign supervisors based on:
                            <ul class="mb-0 mt-2">
                                <li>Teacher availability and current load</li>
                                <li>Teacher specializations (if available)</li>
                                <li>Balancing workload across all teachers</li>
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="auto_assign" class="btn btn-success">
                            <i class="fas fa-robot"></i> Run Auto Assignment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Handle assign supervisor modal
        $('#assignSupervisorModal').on('show.bs.modal', function(event) {
            var button = $(event.relatedTarget);
            var projectId = button.data('project-id');
            var projectTitle = button.data('project-title');
            var studentName = button.data('student-name');
            
            var modal = $(this);
            modal.find('#assignProjectId').val(projectId);
            modal.find('#assignProjectTitle').val(projectTitle);
            modal.find('#assignStudentName').val(studentName);
            
            // Load available teachers via AJAX
            $.ajax({
                url: 'supervisor_assignment.php?project_id=' + projectId,
                type: 'GET',
                success: function(data) {
                    // This is a simplified version - in production, you'd create a separate API endpoint
                    // For now, we'll just enable the button
                    modal.find('#teacherSelection').html(`
                        <select class="form-control" name="teacher_id" required id="teacherSelect">
                            <option value="">-- Select a Teacher --</option>
                            <?php foreach ($teacherWorkload as $teacher): 
                                $max = $teacher['max_students'] ?? 5;
                                $current = $teacher['current_load'] ?? 0;
                                if ($current < $max):
                            ?>
                            <option value="<?php echo $teacher['id']; ?>">
                                <?php echo htmlspecialchars($teacher['full_name']); ?> 
                                (<?php echo $current; ?>/<?php echo $max; ?> slots)
                                <?php if ($teacher['specializations']): ?>
                                    - <?php echo htmlspecialchars($teacher['specializations']); ?>
                                <?php endif; ?>
                            </option>
                            <?php 
                                endif;
                            endforeach; ?>
                        </select>
                    `);
                    modal.find('#assignButton').prop('disabled', false);
                },
                error: function() {
                    modal.find('#teacherSelection').html(`
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i>
                            Error loading teachers. Please refresh the page.
                        </div>
                    `);
                }
            });
        });
        
        // Enable assign button when teacher is selected
        $(document).on('change', '#teacherSelect', function() {
            $('#assignButton').prop('disabled', !$(this).val());
        });
    </script>
</body>
</html>