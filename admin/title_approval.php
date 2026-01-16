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

// Get pending titles for admin's department
$pendingTitles = $projectManager->getPendingTitles($user['department_id']);

// Get all projects for statistics
$allProjects = $projectManager->getDepartmentProjects($user['department_id']);

// Get project statistics
$stats = $projectManager->getProjectStatistics($user['department_id']);

$message = '';
$error = '';

// Handle approve/reject actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_title']) || isset($_POST['reject_title'])) {
        $projectId = $_POST['project_id'];
        $comments = trim($_POST['comments'] ?? '');
        $action = isset($_POST['approve_title']) ? 'approve' : 'reject';
        
        if ($action === 'approve') {
            $result = $projectManager->approveTitle($projectId, $user['user_id'], $comments);
            if ($result['success']) {
                $message = $result['message'];
            } else {
                $error = $result['message'];
            }
        } elseif ($action === 'reject') {
            if (empty($comments)) {
                $error = 'Comments are required when rejecting a title';
            } else {
                $result = $projectManager->rejectTitle($projectId, $user['user_id'], $comments);
                if ($result['success']) {
                    $message = $result['message'];
                } else {
                    $error = $result['message'];
                }
            }
        }
        
        // Refresh page
        if ($message || $error) {
            header('Location: title_approval.php?' . ($message ? 'success=' . urlencode($message) : 'error=' . urlencode($error)));
            exit();
        }
    }
}

// Show success/error messages from redirect
if (isset($_GET['success'])) {
    $message = $_GET['success'];
}
if (isset($_GET['error'])) {
    $error = $_GET['error'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Title Approval - FYPMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <style>
        .project-card {
            border-left: 4px solid;
            transition: transform 0.3s;
        }
        .project-card:hover {
            transform: translateY(-2px);
        }
        .status-pending { border-left-color: #ffc107; }
        .status-approved { border-left-color: #198754; }
        .status-rejected { border-left-color: #dc3545; }
        .status-in_progress { border-left-color: #0dcaf0; }
        .status-completed { border-left-color: #6f42c1; }
        
        .stat-card {
            border-radius: 10px;
            padding: 20px;
            color: white;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <!-- Admin Sidebar -->
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
                    <a class="nav-link" href="teachers.php">
                        <i class="fas fa-chalkboard-teacher me-2"></i> Teachers
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="title_approval.php">
                        <i class="fas fa-check-circle me-2"></i> Title Approval
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
                    <a href="dashboard.php" class="btn btn-outline-primary btn-sm me-2">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <h3 class="mb-0 d-inline">Project Title Approval</h3>
                </div>
                <div>
                    <span class="badge bg-danger me-2">
                        <i class="fas fa-clock"></i> <?php echo count($pendingTitles); ?> Pending
                    </span>
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
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3><?php echo $stats['total_count'] ?? 0; ?></h3>
                            <h6>Total</h6>
                        </div>
                        <i class="fas fa-project-diagram fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card" style="background: linear-gradient(135deg, #ffc107 0%, #ff9900 100%);">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3><?php echo $stats['pending_count'] ?? 0; ?></h3>
                            <h6>Pending</h6>
                        </div>
                        <i class="fas fa-clock fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card" style="background: linear-gradient(135deg, #198754 0%, #146c43 100%);">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3><?php echo $stats['approved_count'] ?? 0; ?></h3>
                            <h6>Approved</h6>
                        </div>
                        <i class="fas fa-check-circle fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card" style="background: linear-gradient(135deg, #dc3545 0%, #b02a37 100%);">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3><?php echo $stats['rejected_count'] ?? 0; ?></h3>
                            <h6>Rejected</h6>
                        </div>
                        <i class="fas fa-times-circle fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card" style="background: linear-gradient(135deg, #0dcaf0 0%, #0aa2c0 100%);">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3><?php echo $stats['in_progress_count'] ?? 0; ?></h3>
                            <h6>In Progress</h6>
                        </div>
                        <i class="fas fa-spinner fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card" style="background: linear-gradient(135deg, #6f42c1 0%, #5936a3 100%);">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3><?php echo $stats['completed_count'] ?? 0; ?></h3>
                            <h6>Completed</h6>
                        </div>
                        <i class="fas fa-flag-checkered fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Pending Titles Section -->
        <div class="card mb-4">
            <div class="card-header bg-warning">
                <h5 class="mb-0">
                    <i class="fas fa-clock me-2"></i>
                    Pending Titles for Approval
                    <span class="badge bg-light text-dark"><?php echo count($pendingTitles); ?></span>
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($pendingTitles)): ?>
                    <div class="alert alert-success text-center py-4">
                        <i class="fas fa-check-circle fa-3x mb-3"></i>
                        <h4>No Pending Titles</h4>
                        <p class="mb-0">All titles have been reviewed.</p>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($pendingTitles as $title): ?>
                        <div class="col-md-6 mb-4">
                            <div class="card project-card status-pending h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <h5 class="card-title"><?php echo htmlspecialchars($title['title']); ?></h5>
                                            <p class="card-text text-muted">
                                                <small><?php echo htmlspecialchars($title['description'] ?? 'No description provided'); ?></small>
                                            </p>
                                        </div>
                                        <span class="badge bg-warning">Pending</span>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <p class="mb-1">
                                            <i class="fas fa-user-graduate"></i> 
                                            <strong>Student:</strong> <?php echo htmlspecialchars($title['student_name']); ?>
                                            <small class="text-muted">(<?php echo htmlspecialchars($title['student_id']); ?>)</small>
                                        </p>
                                        <p class="mb-1">
                                            <i class="fas fa-envelope"></i> 
                                            <strong>Email:</strong> <?php echo htmlspecialchars($title['student_email']); ?>
                                        </p>
                                        <p class="mb-1">
                                            <i class="fas fa-calendar-alt"></i> 
                                            <strong>Batch:</strong> <?php echo htmlspecialchars($title['batch_name']); ?> - <?php echo $title['batch_year']; ?>
                                        </p>
                                        <p class="mb-1">
                                            <i class="fas fa-users"></i> 
                                            <strong>Group:</strong> 
                                            <?php if ($title['group_name']): ?>
                                                <?php echo htmlspecialchars($title['group_name']); ?> (<?php echo htmlspecialchars($title['group_code']); ?>)
                                            <?php else: ?>
                                                Individual
                                            <?php endif; ?>
                                        </p>
                                        <p class="mb-0">
                                            <i class="fas fa-calendar-day"></i> 
                                            <strong>Submitted:</strong> <?php echo date('F j, Y g:i A', strtotime($title['submitted_at'])); ?>
                                        </p>
                                    </div>
                                    
                                    <div class="d-flex gap-2 mt-3">
                                        <button class="btn btn-success btn-sm" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#approveModal"
                                                data-project-id="<?php echo $title['id']; ?>"
                                                data-project-title="<?php echo htmlspecialchars($title['title']); ?>"
                                                data-student-name="<?php echo htmlspecialchars($title['student_name']); ?>">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                        <button class="btn btn-danger btn-sm"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#rejectModal"
                                                data-project-id="<?php echo $title['id']; ?>"
                                                data-project-title="<?php echo htmlspecialchars($title['title']); ?>"
                                                data-student-name="<?php echo htmlspecialchars($title['student_name']); ?>">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                        <button class="btn btn-info btn-sm"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#viewDetailsModal"
                                                data-project-id="<?php echo $title['id']; ?>">
                                            <i class="fas fa-eye"></i> Details
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- All Projects Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    All Projects in Department
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="projectsTable" class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Student</th>
                                <th>Title</th>
                                <th>Group</th>
                                <th>Batch</th>
                                <th>Status</th>
                                <th>Submitted</th>
                                <th>Reviewed</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allProjects as $index => $project): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($project['student_name']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($project['student_id']); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($project['title']); ?></strong><br>
                                    <small class="text-muted"><?php echo substr($project['description'] ?? 'No description', 0, 50); ?>...</small>
                                </td>
                                <td>
                                    <?php if ($project['group_name']): ?>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($project['group_name']); ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Individual</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($project['batch_name']); ?><br>
                                    <small><?php echo $project['batch_year']; ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-<?php 
                                    switch($project['status']) {
                                        case 'pending': echo 'warning'; break;
                                        case 'approved': echo 'success'; break;
                                        case 'rejected': echo 'danger'; break;
                                        case 'in_progress': echo 'info'; break;
                                        case 'completed': echo 'primary'; break;
                                        default: echo 'secondary';
                                    }
                                    ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $project['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo date('M d, Y', strtotime($project['submitted_at'])); ?>
                                </td>
                                <td>
                                    <?php echo $project['reviewed_at'] ? date('M d, Y', strtotime($project['reviewed_at'])) : 'Not reviewed'; ?>
                                </td>
                                                                <td>
                                                                    <div class="btn-group btn-group-sm">
                                                                        <button class="btn btn-outline-info" 
                                                                                data-bs-toggle="modal" 
                                                                                data-bs-target="#viewDetailsModal"
                                                                                data-project-id="<?php echo $project['id']; ?>">
                                                                            <i class="fas fa-eye"></i>
                                                                        </button>
                                                                        <?php if ($project['status'] === 'pending'): ?>
                                                                            <button class="btn btn-outline-success btn-sm"
                                                                                    data-bs-toggle="modal"
                                                                                    data-bs-target="#approveModal"
                                                                                    data-project-id="<?php echo $project['id']; ?>"
                                                                                    data-project-title="<?php echo htmlspecialchars($project['title']); ?>"
                                                                                    data-student-name="<?php echo htmlspecialchars($project['student_name']); ?>">
                                                                                <i class="fas fa-check"></i>
                                                                            </button>
                                                                            <button class="btn btn-outline-danger btn-sm"
                                                                                    data-bs-toggle="modal"
                                                                                    data-bs-target="#rejectModal"
                                                                                    data-project-id="<?php echo $project['id']; ?>"
                                                                                    data-project-title="<?php echo htmlspecialchars($project['title']); ?>"
                                                                                    data-student-name="<?php echo htmlspecialchars($project['student_name']); ?>">
                                                                                <i class="fas fa-times"></i>
                                                                            </button>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div> <!-- .main-content -->
                                    
                                    <!-- Approve Modal -->
                                    <div class="modal fade" id="approveModal" tabindex="-1" aria-labelledby="approveModalLabel" aria-hidden="true">
                                      <div class="modal-dialog">
                                        <form method="post" class="modal-content">
                                          <div class="modal-header">
                                            <h5 class="modal-title" id="approveModalLabel">Approve Project Title</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                          </div>
                                          <div class="modal-body">
                                            <input type="hidden" name="project_id" id="approve_project_id" value="">
                                            <p><strong id="approve_project_title"></strong></p>
                                            <div class="mb-3">
                                              <label for="approve_comments" class="form-label">Comments (optional)</label>
                                              <textarea name="comments" id="approve_comments" class="form-control" rows="3"></textarea>
                                            </div>
                                          </div>
                                          <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" name="approve_title" class="btn btn-success">Approve</button>
                                          </div>
                                        </form>
                                      </div>
                                    </div>
                                
                                    <!-- Reject Modal -->
                                    <div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
                                      <div class="modal-dialog">
                                        <form method="post" class="modal-content">
                                          <div class="modal-header">
                                            <h5 class="modal-title" id="rejectModalLabel">Reject Project Title</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                          </div>
                                          <div class="modal-body">
                                            <input type="hidden" name="project_id" id="reject_project_id" value="">
                                            <p><strong id="reject_project_title"></strong></p>
                                            <div class="mb-3">
                                              <label for="reject_comments" class="form-label">Comments (required)</label>
                                              <textarea name="comments" id="reject_comments" class="form-control" rows="4" required></textarea>
                                            </div>
                                          </div>
                                          <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" name="reject_title" class="btn btn-danger">Reject</button>
                                          </div>
                                        </form>
                                      </div>
                                    </div>
                                
                                    <!-- View Details Modal -->
                                    <div class="modal fade" id="viewDetailsModal" tabindex="-1" aria-labelledby="viewDetailsModalLabel" aria-hidden="true">
                                      <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                          <div class="modal-header">
                                            <h5 class="modal-title" id="viewDetailsModalLabel">Project Details</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                          </div>
                                          <div class="modal-body" id="viewDetailsBody">
                                            <p class="text-center">Loading...</p>
                                          </div>
                                          <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                          </div>
                                        </div>
                                      </div>
                                    </div>
                                
                                    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
                                    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
                                    <script>
                                    // Populate approve/reject modals
                                    var approveModal = document.getElementById('approveModal');
                                    approveModal && approveModal.addEventListener('show.bs.modal', function (event) {
                                        var button = event.relatedTarget;
                                        document.getElementById('approve_project_id').value = button.getAttribute('data-project-id') || '';
                                        document.getElementById('approve_project_title').textContent = button.getAttribute('data-project-title') || '';
                                        document.getElementById('approve_comments').value = '';
                                    });
                                
                                    var rejectModal = document.getElementById('rejectModal');
                                    rejectModal && rejectModal.addEventListener('show.bs.modal', function (event) {
                                        var button = event.relatedTarget;
                                        document.getElementById('reject_project_id').value = button.getAttribute('data-project-id') || '';
                                        document.getElementById('reject_project_title').textContent = button.getAttribute('data-project-title') || '';
                                        document.getElementById('reject_comments').value = '';
                                    });
                                
                                    // Load details via AJAX (simple)
                                    var detailsModal = document.getElementById('viewDetailsModal');
                                    detailsModal && detailsModal.addEventListener('show.bs.modal', function (event) {
                                        var button = event.relatedTarget;
                                        var projectId = button.getAttribute('data-project-id');
                                        var body = document.getElementById('viewDetailsBody');
                                        if (!projectId) { body.innerHTML = '<p class="text-danger">No project id provided.</p>'; return; }
                                        body.innerHTML = '<p class="text-center">Loading...</p>';
                                        // Replace with your detail endpoint if available
                                        $.get('project_details.php', { id: projectId })
                                            .done(function (res) { body.innerHTML = res; })
                                            .fail(function () { body.innerHTML = '<p class="text-danger">Unable to load details.</p>'; });
                                    });
                                    </script>
                                </body>
                                </html>