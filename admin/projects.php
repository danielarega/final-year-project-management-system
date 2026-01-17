<?php
require_once '../includes/config/constants.php';
require_once '../includes/classes/Database.php';
require_once '../includes/classes/Session.php';
require_once '../includes/classes/Auth.php';
require_once '../includes/classes/ProjectManager.php';

Session::init();
$auth = new Auth();
$auth->requireRole(['admin']);

$user = $auth->getUser();
$projectManager = new ProjectManager();

// Get pending titles and all projects
$pendingTitles = $projectManager->getPendingTitles($user['department_id']);
$allProjects = $projectManager->getDepartmentProjects($user['department_id']);
$projectStats = $projectManager->getProjectStatistics($user['department_id']);

// Handle actions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_title'])) {
        $projectId = $_POST['project_id'];
        $comments = trim($_POST['comments'] ?? '');
        
        $result = $projectManager->approveTitle($projectId, $user['user_id'], $comments);
        if ($result['success']) {
            $message = $result['message'];
            header('Location: projects.php?success=' . urlencode($message));
            exit();
        } else {
            $error = $result['message'];
        }
    }
    elseif (isset($_POST['reject_title'])) {
        $projectId = $_POST['project_id'];
        $comments = trim($_POST['comments'] ?? '');
        
        if (empty($comments)) {
            $error = 'Comments are required when rejecting a title';
        } else {
            $result = $projectManager->rejectTitle($projectId, $user['user_id'], $comments);
            if ($result['success']) {
                $message = $result['message'];
                header('Location: projects.php?success=' . urlencode($message));
                exit();
            } else {
                $error = $result['message'];
            }
        }
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
    <title>Project Management - FYPMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <style>
        .project-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: 600;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .status-in_progress { background: #d1ecf1; color: #0c5460; }
        .status-completed { background: #c3e6cb; color: #155724; }
        .stat-card {
            border-radius: 10px;
            padding: 15px;
            color: white;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <!-- Include admin sidebar -->
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
                    <a class="nav-link" href="batches.php">
                        <i class="fas fa-calendar-alt me-2"></i> Batches
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="students.php">
                        <i class="fas fa-user-graduate me-2"></i> Students
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="projects.php">
                        <i class="fas fa-project-diagram me-2"></i> Projects
                    </a>
                </li>
                <li class="nav-item">
    <a class="nav-link" href="supervisor_assignment.php">
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
                <h3 class="mb-0">Project Title Management</h3>
                <div>
                    <a href="dashboard.php" class="btn btn-outline-primary btn-sm me-2">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>
        </nav>
        
        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="text-center">
                        <h3><?php echo $projectStats['total_count'] ?? 0; ?></h3>
                        <h6>Total Projects</h6>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card" style="background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);">
                    <div class="text-center">
                        <h3><?php echo $projectStats['pending_count'] ?? 0; ?></h3>
                        <h6>Pending</h6>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                    <div class="text-center">
                        <h3><?php echo $projectStats['approved_count'] ?? 0; ?></h3>
                        <h6>Approved</h6>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card" style="background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);">
                    <div class="text-center">
                        <h3><?php echo $projectStats['rejected_count'] ?? 0; ?></h3>
                        <h6>Rejected</h6>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);">
                    <div class="text-center">
                        <h3><?php echo $projectStats['in_progress_count'] ?? 0; ?></h3>
                        <h6>In Progress</h6>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card" style="background: linear-gradient(135deg, #6f42c1 0%, #6610f2 100%);">
                    <div class="text-center">
                        <h3><?php echo $projectStats['completed_count'] ?? 0; ?></h3>
                        <h6>Completed</h6>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Pending Titles -->
        <div class="card mb-4">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">
                    <i class="fas fa-clock me-2"></i>
                    Pending Titles for Approval
                    <span class="badge bg-light text-dark"><?php echo count($pendingTitles); ?></span>
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($pendingTitles)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> No pending titles to review.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table id="pendingTitlesTable" class="table table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Student</th>
                                    <th>Title</th>
                                    <th>Batch</th>
                                    <th>Group</th>
                                    <th>Submitted</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingTitles as $index => $title): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($title['student_name']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($title['student_id']); ?></small>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($title['title']); ?></strong>
                                        <?php if ($title['description']): ?>
                                            <br><small class="text-muted"><?php echo substr(htmlspecialchars($title['description']), 0, 100); ?>...</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $title['batch_name']; ?> - <?php echo $title['batch_year']; ?></span>
                                    </td>
                                    <td>
                                        <?php if ($title['group_name']): ?>
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($title['group_name']); ?></span><br>
                                            <small><?php echo htmlspecialchars($title['group_code']); ?></small>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Individual</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($title['submitted_at'])); ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <!-- View Details Button -->
                                            <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" 
                                                    data-bs-target="#viewTitleModal"
                                                    data-title="<?php echo htmlspecialchars($title['title']); ?>"
                                                    data-description="<?php echo htmlspecialchars($title['description'] ?? 'No description'); ?>"
                                                    data-student="<?php echo htmlspecialchars($title['student_name']); ?>"
                                                    data-batch="<?php echo $title['batch_name']; ?> - <?php echo $title['batch_year']; ?>"
                                                    data-group="<?php echo $title['group_name'] ? htmlspecialchars($title['group_name']) . ' (' . htmlspecialchars($title['group_code']) . ')' : 'Individual'; ?>"
                                                    data-submitted="<?php echo date('M d, Y h:i A', strtotime($title['submitted_at'])); ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            
                                            <!-- Approve Button -->
                                            <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" 
                                                    data-bs-target="#approveModal"
                                                    data-project-id="<?php echo $title['id']; ?>"
                                                    data-title="<?php echo htmlspecialchars($title['title']); ?>"
                                                    data-student="<?php echo htmlspecialchars($title['student_name']); ?>">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            
                                            <!-- Reject Button -->
                                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" 
                                                    data-bs-target="#rejectModal"
                                                    data-project-id="<?php echo $title['id']; ?>"
                                                    data-title="<?php echo htmlspecialchars($title['title']); ?>"
                                                    data-student="<?php echo htmlspecialchars($title['student_name']); ?>">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- All Projects -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    All Projects
                    <span class="badge bg-primary"><?php echo count($allProjects); ?></span>
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="allProjectsTable" class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Student</th>
                                <th>Title</th>
                                <th>Batch</th>
                                <th>Status</th>
                                <th>Supervisor</th>
                                <th>Submitted</th>
                                <th>Reviewed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allProjects as $index => $project): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo htmlspecialchars($project['student_name']); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($project['title']); ?></strong>
                                    <?php if ($project['admin_comments']): ?>
                                        <br><small class="text-muted"><?php echo substr(htmlspecialchars($project['admin_comments']), 0, 50); ?>...</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $project['batch_name']; ?></span>
                                </td>
                                <td>
                                    <span class="project-status status-<?php echo $project['status']; ?>">
                                        <?php echo str_replace('_', ' ', ucfirst($project['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($project['supervisor_name']): ?>
                                        <?php echo htmlspecialchars($project['supervisor_name']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Not assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo date('M d, Y', strtotime($project['submitted_at'])); ?>
                                </td>
                                <td>
                                    <?php echo $project['reviewed_at'] ? date('M d, Y', strtotime($project['reviewed_at'])) : 'Not reviewed'; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- View Title Modal -->
    <div class="modal fade" id="viewTitleModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Project Title Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><strong>Title</strong></label>
                        <p id="modalTitle"></p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><strong>Description</strong></label>
                        <p id="modalDescription" class="text-muted"></p>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><strong>Student</strong></label>
                            <p id="modalStudent"></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><strong>Batch</strong></label>
                            <p id="modalBatch"></p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><strong>Group</strong></label>
                            <p id="modalGroup"></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><strong>Submitted On</strong></label>
                            <p id="modalSubmitted"></p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Approve Modal -->
    <div class="modal fade" id="approveModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">Approve Project Title</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="project_id" id="approveProjectId">
                        <div class="mb-3">
                            <label class="form-label">Student</label>
                            <input type="text" class="form-control" id="approveStudent" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" class="form-control" id="approveTitle" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Comments (Optional)</label>
                            <textarea class="form-control" name="comments" rows="3" 
                                      placeholder="Add any comments or suggestions..."></textarea>
                        </div>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> This will approve the title and allow the student to proceed.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="approve_title" class="btn btn-success">Approve Title</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Reject Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">Reject Project Title</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                    <div class="modal-body">
                        <input type="hidden" name="project_id" id="rejectProjectId">
                        <div class="mb-3">
                            <label class="form-label">Student</label>
                            <input type="text" class="form-control" id="rejectStudent" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" class="form-control" id="rejectTitle" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Rejection Reason *</label>
                            <textarea class="form-control" name="comments" rows="4" 
                                      placeholder="Explain why this title is being rejected..." required></textarea>
                            <small class="text-muted">This feedback will be shown to the student</small>
                        </div>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> The student will need to resubmit a new title.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="reject_title" class="btn btn-danger">Reject Title</button>
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
            // Initialize DataTables
            $('#pendingTitlesTable').DataTable({
                pageLength: 10,
                responsive: true,
                order: [[5, 'desc']] // Sort by submission date
            });
            
            $('#allProjectsTable').DataTable({
                pageLength: 15,
                responsive: true,
                order: [[6, 'desc']] // Sort by submission date
            });
            
            // View Title Modal
            $('#viewTitleModal').on('show.bs.modal', function(event) {
                var button = $(event.relatedTarget);
                $('#modalTitle').text(button.data('title'));
                $('#modalDescription').text(button.data('description'));
                $('#modalStudent').text(button.data('student'));
                $('#modalBatch').text(button.data('batch'));
                $('#modalGroup').text(button.data('group'));
                $('#modalSubmitted').text(button.data('submitted'));
            });
            
            // Approve Modal
            $('#approveModal').on('show.bs.modal', function(event) {
                var button = $(event.relatedTarget);
                $('#approveProjectId').val(button.data('project-id'));
                $('#approveStudent').val(button.data('student'));
                $('#approveTitle').val(button.data('title'));
            });
            
            // Reject Modal
            $('#rejectModal').on('show.bs.modal', function(event) {
                var button = $(event.relatedTarget);
                $('#rejectProjectId').val(button.data('project-id'));
                $('#rejectStudent').val(button.data('student'));
                $('#rejectTitle').val(button.data('title'));
            });
        });
    </script>
    <!-- Supervisor Assignment Modal -->
<div class="modal fade" id="assignSupervisorModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assign Supervisor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="assignProjectId">
                <input type="hidden" id="assignBatchId">
                <input type="hidden" id="assignDepartmentId">
                
                <div class="alert alert-info" id="assignSupervisorAlert">
                    <i class="fas fa-info-circle"></i> 
                    Select a supervisor from the list below or use auto-assign.
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Available Supervisors</label>
                    <div id="supervisorsList">
                        <!-- Supervisors will be loaded here -->
                        <div class="text-center py-3">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p>Loading available supervisors...</p>
                        </div>
                    </div>
                </div>
                
                <div id="noSupervisors" style="display: none;">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        No supervisors available for this batch. Please add more teachers or check their availability.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" id="autoAssignBtn">
                    <i class="fas fa-robot"></i> Auto Assign
                </button>
                <button type="button" class="btn btn-primary" id="assignSelectedBtn" disabled>
                    <i class="fas fa-user-tie"></i> Assign Selected
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Supervisor Assignment
let selectedSupervisorId = null;

// When assign button is clicked in projects table
$(document).on('click', '.assign-supervisor-btn', function() {
    var projectId = $(this).data('project-id');
    var batchId = $(this).data('batch-id');
    var departmentId = $(this).data('department-id');
    
    $('#assignProjectId').val(projectId);
    $('#assignBatchId').val(batchId);
    $('#assignDepartmentId').val(departmentId);
    
    // Reset and load supervisors
    selectedSupervisorId = null;
    $('#assignSelectedBtn').prop('disabled', true);
    loadAvailableSupervisors(batchId, departmentId);
    
    $('#assignSupervisorModal').modal('show');
});

function loadAvailableSupervisors(batchId, departmentId) {
    $('#supervisorsList').html(`
        <div class="text-center py-3">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p>Loading available supervisors...</p>
        </div>
    `);
    $('#noSupervisors').hide();
    
    $.ajax({
        url: 'ajax_get_available_supervisors.php',
        method: 'GET',
        data: {
            batch_id: batchId,
            department_id: departmentId
        },
        success: function(response) {
            if (response.success && response.data && response.data.length > 0) {
                let html = '';
                response.data.forEach(function(supervisor) {
                    html += `
                    <div class="card mb-2 supervisor-card" data-supervisor-id="${supervisor.id}">
                        <div class="card-body">
                            <div class="form-check">
                                <input class="form-check-input supervisor-radio" type="radio" 
                                       name="supervisor" id="supervisor_${supervisor.id}" 
                                       value="${supervisor.id}">
                                <label class="form-check-label" for="supervisor_${supervisor.id}">
                                    <strong>${supervisor.full_name}</strong><br>
                                    <small class="text-muted">
                                        Available slots: ${supervisor.available_slots} | 
                                        Current load: ${supervisor.current_load}/${supervisor.max_students}<br>
                                        Specializations: ${supervisor.specializations || 'None'}
                                    </small>
                                </label>
                            </div>
                        </div>
                    </div>
                    `;
                });
                $('#supervisorsList').html(html);
                $('#noSupervisors').hide();
            } else {
                $('#supervisorsList').html('');
                $('#noSupervisors').show();
            }
        },
        error: function() {
            $('#supervisorsList').html('<div class="alert alert-danger">Error loading supervisors.</div>');
            $('#noSupervisors').hide();
        }
    });
}

// When a supervisor is selected
$(document).on('change', '.supervisor-radio', function() {
    selectedSupervisorId = $(this).val();
    $('#assignSelectedBtn').prop('disabled', false);
});

// Assign selected supervisor
$('#assignSelectedBtn').click(function() {
    if (!selectedSupervisorId) {
        alert('Please select a supervisor');
        return;
    }
    
    const projectId = $('#assignProjectId').val();
    const teacherId = selectedSupervisorId;
    
    $.ajax({
        url: 'ajax_assign_supervisor.php',
        method: 'POST',
        data: {
            project_id: projectId,
            teacher_id: teacherId
        },
        success: function(response) {
            if (response.success) {
                alert(response.message);
                $('#assignSupervisorModal').modal('hide');
                location.reload(); // Reload the page to see the update
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function() {
            alert('Error assigning supervisor. Please try again.');
        }
    });
});

// Auto assign
$('#autoAssignBtn').click(function() {
    const projectId = $('#assignProjectId').val();
    
    $.ajax({
        url: 'ajax_auto_assign_supervisor.php',
        method: 'POST',
        data: {
            project_id: projectId
        },
        success: function(response) {
            if (response.success) {
                alert(response.message);
                $('#assignSupervisorModal').modal('hide');
                location.reload();
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function() {
            alert('Error auto-assigning supervisor. Please try again.');
        }
    });
});
</script>
</body>
</html>