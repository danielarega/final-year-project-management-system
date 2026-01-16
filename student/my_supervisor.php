<?php
require_once '../includes/config/constants.php';
require_once '../includes/classes/Database.php';
require_once '../includes/classes/Session.php';
require_once '../includes/classes/Auth.php';
require_once '../includes/classes/ProjectManager.php';
require_once '../includes/classes/SupervisorManager.php';

Session::init();
$auth = new Auth();
$auth->requireRole(['student']);

$user = $auth->getUser();
$projectManager = new ProjectManager();
$supervisorManager = new SupervisorManager();

// Get student's project
$batchId = $user['batch_id'] ?? null;
$project = $batchId ? $projectManager->getStudentProject($user['user_id'], $batchId) : null;

// Get supervisor info if assigned
$supervisor = null;
$assignmentHistory = [];
if ($project && $project['supervisor_id']) {
    $supervisor = $supervisorManager->getTeacherById($project['supervisor_id']);
    $assignmentHistory = $supervisorManager->getAssignmentHistory($project['id']);
}

$message = '';
$error = '';

// Handle contact form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    // In a real system, this would send an email or store in messaging system
    $message = 'Message sent to supervisor (demo mode)';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Supervisor - FYPMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .supervisor-card {
            border: 2px solid #667eea;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }
        .history-item {
            border-left: 3px solid #667eea;
            padding-left: 15px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <!-- Student Sidebar -->
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
                    <a class="nav-link" href="my_project.php">
                        <i class="fas fa-project-diagram me-2"></i> My Project
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="my_supervisor.php">
                        <i class="fas fa-user-tie me-2"></i> My Supervisor
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
                    <a href="my_project.php" class="btn btn-outline-primary btn-sm me-2">
                        <i class="fas fa-arrow-left"></i> Back to My Project
                    </a>
                    <h3 class="mb-0 d-inline">My Supervisor</h3>
                </div>
                <div>
                    <span class="badge bg-primary">
                        Project Status: <?php echo $project ? ucfirst($project['status']) : 'No Project'; ?>
                    </span>
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
        
        <?php if (!$project): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                You don't have an approved project yet. Please submit and get your project title approved first.
            </div>
        <?php elseif (!$supervisor): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                Your project "<strong><?php echo htmlspecialchars($project['title']); ?></strong>" has been approved,
                but a supervisor hasn't been assigned yet. Please check back later.
            </div>
        <?php else: ?>
            <!-- Supervisor Information -->
            <div class="supervisor-card text-center">
                <div class="mb-4">
                    <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mb-3" 
                         style="width: 100px; height: 100px;">
                        <i class="fas fa-user-tie fa-3x"></i>
                    </div>
                    <h3><?php echo htmlspecialchars($supervisor['full_name']); ?></h3>
                    <p class="text-muted">Your Project Supervisor</p>
                    
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <p><strong><i class="fas fa-envelope"></i> Email</strong></p>
                            <p><?php echo htmlspecialchars($supervisor['email']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong><i class="fas fa-building"></i> Department</strong></p>
                            <p><?php echo htmlspecialchars($supervisor['dept_name']); ?></p>
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h5><?php echo $supervisor['current_load'] ?? 0; ?></h5>
                                    <small>Current Students</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h5><?php echo $supervisor['max_students'] ?? 5; ?></h5>
                                    <small>Max Capacity</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h5>
                                        <?php 
                                        $loadPercentage = $supervisor['max_students'] > 0 ? 
                                            (($supervisor['current_load'] ?? 0) / $supervisor['max_students']) * 100 : 0;
                                        echo round($loadPercentage);
                                        ?>%
                                    </h5>
                                    <small>Workload</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Contact Form -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-comment-dots me-2"></i>
                        Contact Supervisor
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label">Subject</label>
                            <input type="text" class="form-control" name="subject" 
                                   placeholder="Regarding project progress..." required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Message</label>
                            <textarea class="form-control" name="message" rows="5" 
                                      placeholder="Write your message to supervisor..." required></textarea>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            This message will be sent to <?php echo htmlspecialchars($supervisor['email']); ?>
                        </div>
                        <button type="submit" name="send_message" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Send Message
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Project Information -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        Project & Assignment Details
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6>Project Information</h6>
                            <p><strong>Title:</strong> <?php echo htmlspecialchars($project['title']); ?></p>
                            <p><strong>Status:</strong> 
                                <span class="badge bg-<?php echo $project['status'] === 'approved' ? 'success' : ($project['status'] === 'in_progress' ? 'warning' : 'info'); ?>">
                                    <?php echo ucfirst($project['status']); ?>
                                </span>
                            </p>
                            <p><strong>Approved On:</strong> 
                                <?php echo $project['approved_at'] ? date('M d, Y', strtotime($project['approved_at'])) : 'Not approved'; ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <h6>Assignment History</h6>
                            <?php if (empty($assignmentHistory)): ?>
                                <p class="text-muted">No assignment history available.</p>
                            <?php else: ?>
                                <?php foreach ($assignmentHistory as $history): ?>
                                <div class="history-item mb-3">
                                    <p class="mb-1">
                                        <strong><?php echo htmlspecialchars($history['teacher_name']); ?></strong>
                                        <small class="text-muted">
                                            (<?php echo $history['assignment_type']; ?>)
                                        </small>
                                    </p>
                                    <p class="mb-1 small">
                                        Assigned by: <?php echo htmlspecialchars($history['assigned_by_name']); ?>
                                    </p>
                                    <p class="mb-1 small">
                                        <i class="fas fa-calendar"></i> 
                                        <?php echo date('M d, Y h:i A', strtotime($history['assignment_date'])); ?>
                                    </p>
                                    <?php if ($history['comments']): ?>
                                        <p class="mb-0 small">
                                            <i class="fas fa-comment"></i> 
                                            <?php echo htmlspecialchars($history['comments']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Guidelines -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-lightbulb me-2"></i>
                    Working with Your Supervisor
                </h5>
            </div>
            <div class="card-body">
                <ul>
                    <li class="mb-2">
                        <strong>Regular Communication:</strong> Keep your supervisor updated about your progress
                    </li>
                    <li class="mb-2">
                        <strong>Seek Guidance:</strong> Don't hesitate to ask for help when you're stuck
                    </li>
                    <li class="mb-2">
                        <strong>Meet Deadlines:</strong> Submit work on time as agreed with your supervisor
                    </li>
                    <li class="mb-2">
                        <strong>Incorporate Feedback:</strong> Carefully consider and implement supervisor's suggestions
                    </li>
                    <li>
                        <strong>Professionalism:</strong> Maintain professional communication at all times
                    </li>
                </ul>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>