<!-- File: student/my_submissions.php -->
<?php
require_once '../includes/config/constants.php';
require_once '../includes/classes/Database.php';
require_once '../includes/classes/Session.php';
require_once '../includes/classes/Auth.php';
require_once '../includes/classes/ProjectManager.php';
require_once '../includes/classes/SubmissionManager.php';

Session::init();
$auth = new Auth();
$auth->requireRole(['student']);

$user = $auth->getUser();
$projectManager = new ProjectManager();
$submissionManager = new SubmissionManager();

// Get student's project
$batchId = $user['batch_id'] ?? null;
$project = $batchId ? $projectManager->getStudentProject($user['user_id'], $batchId) : null;

// Get all submissions if project exists
$submissions = [];
$feedback = [];
$submissionId = $_GET['submission_id'] ?? null;

if ($project) {
    $submissions = $submissionManager->getProjectSubmissions($project['id']);
    
    // Get specific submission feedback if requested
    if ($submissionId) {
        $feedback = $submissionManager->getFeedback($submissionId);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Submissions - FYPMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .submission-card {
            border-left: 4px solid;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            background-color: #f8f9fa;
            transition: all 0.3s;
        }
        .submission-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .type-proposal { border-left-color: #667eea; }
        .type-progress_report { border-left-color: #10b981; }
        .type-final_report { border-left-color: #ef4444; }
        .type-source_code { border-left-color: #f59e0b; }
        .feedback-item {
            background-color: #f1f5f9;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
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
                    <a class="nav-link" href="submit_document.php">
                        <i class="fas fa-file-upload me-2"></i> Submit Document
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="my_submissions.php">
                        <i class="fas fa-history me-2"></i> My Submissions
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
                    <a href="submit_document.php" class="btn btn-outline-primary btn-sm me-2">
                        <i class="fas fa-arrow-left"></i> Back to Submit
                    </a>
                    <h3 class="mb-0 d-inline">My Submissions</h3>
                </div>
                <div>
                    <span class="badge bg-primary">
                        Total: <?php echo count($submissions); ?> Submissions
                    </span>
                </div>
            </div>
        </nav>
        
        <?php if (!$project): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                You don't have an approved project yet. No submissions to display.
            </div>
        <?php elseif (empty($submissions)): ?>
            <div class="alert alert-info text-center py-5">
                <i class="fas fa-inbox fa-3x mb-3"></i>
                <h4>No Submissions Yet</h4>
                <p class="mb-0">You haven't submitted any documents yet.</p>
                <a href="submit_document.php" class="btn btn-primary mt-3">
                    <i class="fas fa-plus-circle"></i> Submit Your First Document
                </a>
            </div>
        <?php else: ?>
            <!-- Submissions List -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">All Submissions</h5>
                </div>
                <div class="card-body">
                    <?php foreach ($submissions as $submission): ?>
                    <div class="submission-card type-<?php echo $submission['submission_type']; ?>">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h5>
                                    <?php echo str_replace('_', ' ', ucfirst($submission['submission_type'])); ?>
                                    <span class="badge bg-light text-dark ms-2">v<?php echo $submission['version']; ?></span>
                                </h5>
                                <p class="mb-1">
                                    <i class="fas fa-file me-1"></i>
                                    <?php echo htmlspecialchars($submission['file_name']); ?>
                                </p>
                                <small class="text-muted">
                                    <i class="fas fa-calendar"></i> 
                                    <?php echo date('M d, Y h:i A', strtotime($submission['submitted_at'])); ?>
                                </small>
                            </div>
                            <div class="text-end">
                                <span class="status-badge bg-<?php 
                                    echo $submission['status'] === 'approved' ? 'success' : 
                                        ($submission['status'] === 'pending' ? 'warning' : 
                                        ($submission['status'] === 'rejected' ? 'danger' : 'info'));
                                ?>">
                                    <?php echo ucfirst($submission['status']); ?>
                                </span>
                                <div class="mt-2">
                                    <a href="my_submissions.php?submission_id=<?php echo $submission['id']; ?>" 
                                       class="btn btn-sm btn-outline-info">
                                        <i class="fas fa-eye"></i> View Feedback
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($submission['description']): ?>
                        <div class="mb-3">
                            <small><strong>Description:</strong> <?php echo htmlspecialchars($submission['description']); ?></small>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($submission['deadline_date']): ?>
                        <div class="mb-2">
                            <small class="text-muted">
                                <i class="fas fa-clock"></i> 
                                Deadline: <?php echo date('M d, Y', strtotime($submission['deadline_date'])); ?>
                            </small>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Feedback Section (if viewing specific submission) -->
            <?php if ($submissionId && !empty($feedback)): ?>
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-comment-dots me-2"></i>
                        Feedback for Submission
                        <?php 
                        $submissionType = '';
                        foreach ($submissions as $s) {
                            if ($s['id'] == $submissionId) {
                                $submissionType = $s['submission_type'];
                                break;
                            }
                        }
                        ?>
                        <small class="opacity-75">(<?php echo str_replace('_', ' ', ucfirst($submissionType)); ?>)</small>
                    </h5>
                </div>
                <div class="card-body">
                    <?php foreach ($feedback as $fb): ?>
                    <div class="feedback-item">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h6 class="mb-1">
                                    <i class="fas fa-user-tie text-primary me-1"></i>
                                    <?php echo htmlspecialchars($fb['teacher_name']); ?>
                                </h6>
                                <small class="text-muted">
                                    <?php echo htmlspecialchars($fb['dept_name']); ?> | 
                                    <?php echo date('M d, Y h:i A', strtotime($fb['given_at'])); ?>
                                </small>
                            </div>
                            <div>
                                <?php if ($fb['marks']): ?>
                                    <span class="badge bg-primary">Marks: <?php echo $fb['marks']; ?></span>
                                <?php endif; ?>
                                <?php if ($fb['grade']): ?>
                                    <span class="badge bg-success">Grade: <?php echo $fb['grade']; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($fb['comments'])); ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <div class="text-center mt-4">
                        <a href="my_submissions.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left"></i> Back to All Submissions
                        </a>
                    </div>
                </div>
            </div>
            <?php elseif ($submissionId): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                No feedback available for this submission yet. Your supervisor will provide feedback soon.
                <a href="my_submissions.php" class="alert-link">Back to submissions</a>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>