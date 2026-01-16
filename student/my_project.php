<?php
require_once '../includes/config/constants.php';
require_once '../includes/classes/Database.php';
require_once '../includes/classes/Session.php';
require_once '../includes/classes/Auth.php';
require_once '../includes/classes/ProjectManager.php';
require_once '../includes/classes/GroupManager.php';

Session::init();
$auth = new Auth();
$auth->requireRole(['student']);

$user = $auth->getUser();
$projectManager = new ProjectManager();
$groupManager = new GroupManager();

// Get student's batch
$batchId = $user['batch_id'] ?? null;

if (!$batchId) {
    header('Location: dashboard.php?error=You are not assigned to any batch. Please contact your department.');
    exit();
}

// Get student's project
$project = $projectManager->getStudentProject($user['user_id'], $batchId);

// Get student's group (if any)
$group = $groupManager->getStudentGroup($user['user_id'], $batchId);

// Get title history
$titleHistory = $project ? $projectManager->getTitleHistory($project['id']) : [];

$message = '';
$error = '';

// Handle title submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['submit_title'])) {
        $title = trim($_POST['title']);
        $description = trim($_POST['description'] ?? '');
        
        if (empty($title)) {
            $error = 'Project title is required';
        } else {
            $data = [
                'title' => $title,
                'description' => $description,
                'student_id' => $user['user_id'],
                'department_id' => $user['department_id'],
                'batch_id' => $batchId
            ];
            
            // Add group_id if student is in a group
            if ($group) {
                $data['group_id'] = $group['id'];
            }
            
            $result = $projectManager->submitTitle($data);
            if ($result['success']) {
                $message = $result['message'];
                header('Location: my_project.php?success=' . urlencode($message));
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
    <title>My Project - FYPMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .project-status {
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 600;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .status-in_progress { background: #d1ecf1; color: #0c5460; }
        .status-completed { background: #c3e6cb; color: #155724; }
        
        .history-item {
            border-left: 3px solid #667eea;
            padding-left: 15px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <!-- Include student sidebar from dashboard -->
    <?php 
    // We'll use the sidebar from dashboard but modify the active link
    // For simplicity, I'll include the full structure with sidebar
    ?>
    <div class="sidebar">
        <div class="p-4">
            <h4 class="text-center">FYPMS</h4>
            <hr class="bg-white">
            <div class="text-center mb-4">
                <div class="rounded-circle bg-white d-inline-flex align-items-center justify-content-center" 
                     style="width: 60px; height: 60px;">
                    <i class="fas fa-user-graduate" style="color: #667eea;"></i>
                </div>
                <h6 class="mt-2"><?php echo htmlspecialchars($user['full_name']); ?></h6>
                <small>Student</small>
            </div>
            
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="my_project.php">
                        <i class="fas fa-project-diagram me-2"></i> My Project
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="group.php">
                        <i class="fas fa-users me-2"></i> Group
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
                    <h3 class="mb-0 d-inline">My Project</h3>
                </div>
                <div>
                    <span class="badge bg-primary">
                        Batch: <?php 
                        // Get batch info - in real implementation, you'd fetch this from database
                        echo '2026 Regular';
                        ?>
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
        
        <div class="row">
            <!-- Left Column: Project Details -->
            <div class="col-md-8">
                <!-- Current Project Status -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-project-diagram me-2"></i>
                            Project Status
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($project): ?>
                            <div class="row">
                                <div class="col-md-8">
                                    <h4><?php echo htmlspecialchars($project['title']); ?></h4>
                                    <?php if ($project['description']): ?>
                                        <p class="text-muted"><?php echo htmlspecialchars($project['description']); ?></p>
                                    <?php endif; ?>
                                    
                                    <div class="mt-3">
                                        <p>
                                            <strong>Status:</strong> 
                                            <span class="project-status status-<?php echo $project['status']; ?>">
                                                <?php echo str_replace('_', ' ', ucfirst($project['status'])); ?>
                                            </span>
                                        </p>
                                        <p><strong>Submitted:</strong> <?php echo date('F j, Y', strtotime($project['submitted_at'])); ?></p>
                                        
                                        <?php if ($project['reviewed_at']): ?>
                                            <p><strong>Reviewed:</strong> <?php echo date('F j, Y', strtotime($project['reviewed_at'])); ?></p>
                                        <?php endif; ?>
                                        
                                        <?php if ($project['admin_comments']): ?>
                                            <div class="alert alert-info mt-3">
                                                <strong><i class="fas fa-comment"></i> Admin Comments:</strong><br>
                                                <?php echo htmlspecialchars($project['admin_comments']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-4 text-center">
                                    <div class="mb-3">
                                        <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center" 
                                             style="width: 100px; height: 100px; border: 3px solid #667eea;">
                                            <?php if ($project['status'] === 'approved'): ?>
                                                <i class="fas fa-check fa-3x text-success"></i>
                                            <?php elseif ($project['status'] === 'rejected'): ?>
                                                <i class="fas fa-times fa-3x text-danger"></i>
                                            <?php else: ?>
                                                <i class="fas fa-clock fa-3x text-warning"></i>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <?php if ($project['status'] === 'rejected'): ?>
                                        <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#resubmitModal">
                                            <i class="fas fa-redo"></i> Resubmit Title
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if ($group): ?>
                                <hr>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Group:</strong> <?php echo htmlspecialchars($group['group_name']); ?></p>
                                        <p><strong>Group Code:</strong> <?php echo htmlspecialchars($group['group_code']); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Members:</strong> <?php echo $project['group_member_count'] ?? '0'; ?> students</p>
                                        <a href="group.php" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-users"></i> View Group Details
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-file-alt fa-4x text-muted mb-3"></i>
                                <h4>No Project Submitted Yet</h4>
                                <p class="text-muted">Submit your project title to get started</p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#submitTitleModal">
                                    <i class="fas fa-plus-circle"></i> Submit Title
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Title History -->
                <?php if (!empty($titleHistory)): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-history me-2"></i>
                            Title History
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($titleHistory as $history): ?>
                        <div class="history-item">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6><?php echo htmlspecialchars($history['new_title']); ?></h6>
                                    <?php if ($history['old_title']): ?>
                                        <p class="mb-1">
                                            <small>Changed from: <?php echo htmlspecialchars($history['old_title']); ?></small>
                                        </p>
                                    <?php endif; ?>
                                    <?php if ($history['change_reason']): ?>
                                        <p class="mb-1">
                                            <small>Reason: <?php echo htmlspecialchars($history['change_reason']); ?></small>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <div class="text-end">
                                    <small class="text-muted">
                                        <?php echo date('M d, Y h:i A', strtotime($history['changed_at'])); ?><br>
                                        by <?php echo htmlspecialchars($history['changed_by_name']); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Right Column: Actions & Information -->
            <div class="col-md-4">
                <!-- Quick Actions -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-bolt me-2"></i>
                            Quick Actions
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <?php if (!$project || $project['status'] === 'rejected'): ?>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#submitTitleModal">
                                    <i class="fas fa-paper-plane"></i> Submit Title
                                </button>
                            <?php endif; ?>
                            
                            <?php if ($project && $project['status'] === 'approved'): ?>
                                <button class="btn btn-success" disabled>
                                    <i class="fas fa-check"></i> Title Approved
                                </button>
                            <?php endif; ?>
                            
                            <a href="group.php" class="btn btn-outline-primary">
                                <i class="fas fa-users"></i> <?php echo $group ? 'Manage Group' : 'Join/Create Group'; ?>
                            </a>
                            
                            <a href="notices.php" class="btn btn-outline-info">
                                <i class="fas fa-bullhorn"></i> Check Notices
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Guidelines -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            Guidelines
                        </h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled">
                            <li class="mb-2">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                Title must be clear and descriptive
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                Maximum 300 characters for title
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                Technology departments require groups
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                Wait for department approval before proceeding
                            </li>
                            <li>
                                <i class="fas fa-check-circle text-success me-2"></i>
                                Contact your supervisor after approval
                            </li>
                        </ul>
                    </div>
                </div>
                
                <!-- Deadlines -->
                <div class="card">
                    <div class="card-header bg-warning">
                        <h5 class="mb-0">
                            <i class="fas fa-calendar-alt me-2"></i>
                            Important Deadlines
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Title Submission</span>
                                <span class="badge bg-danger">7 days</span>
                            </div>
                            <small class="text-muted">Due: Jan 25, 2026</small>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Proposal Submission</span>
                                <span class="badge bg-warning">21 days</span>
                            </div>
                            <small class="text-muted">Due: Feb 8, 2026</small>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Final Report</span>
                                <span class="badge bg-success">45 days</span>
                            </div>
                            <small class="text-muted">Due: Mar 15, 2026</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Submit Title Modal -->
    <div class="modal fade" id="submitTitleModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">Submit Project Title</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Project Title *</label>
                            <input type="text" class="form-control" name="title" 
                                   placeholder="Enter your project title" required
                                   maxlength="300">
                            <small class="text-muted">Maximum 300 characters. Be clear and descriptive.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description (Optional)</label>
                            <textarea class="form-control" name="description" rows="4" 
                                      placeholder="Brief description of your project..."></textarea>
                            <small class="text-muted">Describe what your project will do.</small>
                        </div>
                        
                        <?php if ($group): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            You are in group: <strong><?php echo htmlspecialchars($group['group_name']); ?></strong> 
                            (<?php echo htmlspecialchars($group['group_code']); ?>)
                        </div>
                        <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            You are not in a group. For technology departments, you need to join/create a group first.
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="submit_title" class="btn btn-primary" 
                                <?php echo !$group ? 'disabled' : ''; ?>>
                            <i class="fas fa-paper-plane"></i> Submit Title
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Resubmit Title Modal -->
    <div class="modal fade" id="resubmitModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">Resubmit Project Title</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            Your previous title was rejected. Please review the comments and submit a new title.
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Previous Title</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($project['title']); ?>" disabled>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Admin Comments</label>
                            <textarea class="form-control" disabled rows="3"><?php echo htmlspecialchars($project['admin_comments']); ?></textarea>
                        </div>
                        
                        <hr>
                        
                        <div class="mb-3">
                            <label class="form-label">New Project Title *</label>
                            <input type="text" class="form-control" name="title" 
                                   placeholder="Enter your new project title" required
                                   maxlength="300">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description (Optional)</label>
                            <textarea class="form-control" name="description" rows="4" 
                                      placeholder="Brief description of your project..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="submit_title" class="btn btn-warning">
                            <i class="fas fa-redo"></i> Resubmit Title
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Character counter for title
        const titleInput = document.querySelector('input[name="title"]');
        if (titleInput) {
            titleInput.addEventListener('input', function() {
                const charCount = this.value.length;
                const maxLength = 300;
                const counter = this.nextElementSibling;
                
                if (counter && counter.classList.contains('text-muted')) {
                    counter.textContent = `${charCount}/${maxLength} characters. Be clear and descriptive.`;
                    
                    if (charCount > maxLength * 0.9) {
                        counter.style.color = '#dc3545';
                    } else if (charCount > maxLength * 0.75) {
                        counter.style.color = '#ffc107';
                    } else {
                        counter.style.color = '#6c757d';
                    }
                }
            });
        }
        
        // Auto-focus on modal input
        const submitModal = document.getElementById('submitTitleModal');
        if (submitModal) {
            submitModal.addEventListener('shown.bs.modal', function() {
                const titleInput = this.querySelector('input[name="title"]');
                if (titleInput) titleInput.focus();
            });
        }
    </script>
</body>
</html>