<?php
require_once '../includes/config/constants.php';
require_once '../includes/classes/Database.php';
require_once '../includes/classes/Session.php';
require_once '../includes/classes/Auth.php';
require_once '../includes/classes/SubmissionManager.php';
require_once '../includes/classes/ProjectManager.php';

Session::init();
$auth = new Auth();
$auth->requireRole(['student']);

$user = $auth->getUser();
$submissionManager = new SubmissionManager();
$projectManager = new ProjectManager();

// Get submission ID from query string
$submissionId = $_GET['submission_id'] ?? 0;
$feedbackId = $_GET['feedback_id'] ?? 0;

// Get student's project
$project = $projectManager->getStudentProject($user['user_id'], $user['batch_id']);

// Get all submissions for dropdown
$submissions = $project ? $submissionManager->getStudentSubmissions($user['user_id'], $project['id']) : [];

// Get specific submission and feedback
$submission = null;
$feedback = [];
$specificFeedback = null;

if ($submissionId) {
    // Get submission details
    foreach ($submissions as $sub) {
        if ($sub['id'] == $submissionId) {
            $submission = $sub;
            break;
        }
    }
    
    // Get feedback for this submission
    if ($submission) {
        $feedback = $submissionManager->getSubmissionFeedback($submissionId);
    }
}

if ($feedbackId) {
    // Get specific feedback item
    // This would require a new method in SubmissionManager
}

// Handle marking feedback as resolved
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resolve_feedback'])) {
    $result = $submissionManager->resolveFeedback($_POST['feedback_id'], $user['user_id']);
    
    if ($result['success']) {
        $message = $result['message'];
        header('Location: feedback.php?submission_id=' . $submissionId . '&success=' . urlencode($message));
        exit();
    } else {
        $error = $result['message'];
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
    <title>Feedback - FYPMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .feedback-card {
            border-left: 4px solid #667eea;
            margin-bottom: 20px;
        }
        .feedback-critical { border-left-color: #dc3545; }
        .feedback-suggestion { border-left-color: #28a745; }
        .feedback-general { border-left-color: #17a2b8; }
        .feedback-specific { border-left-color: #ffc107; }
        .feedback-resolved { opacity: 0.7; }
        .comment-bubble {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            position: relative;
        }
        .comment-bubble:after {
            content: '';
            position: absolute;
            left: -10px;
            top: 20px;
            width: 0;
            height: 0;
            border-top: 10px solid transparent;
            border-bottom: 10px solid transparent;
            border-right: 10px solid #f8f9fa;
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
                    <a class="nav-link" href="submissions.php">
                        <i class="fas fa-file-upload me-2"></i> Submissions
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="feedback.php">
                        <i class="fas fa-comments me-2"></i> Feedback
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
                    <a href="submissions.php" class="btn btn-outline-primary btn-sm me-2">
                        <i class="fas fa-arrow-left"></i> Back to Submissions
                    </a>
                    <h3 class="mb-0 d-inline">Feedback & Comments</h3>
                </div>
                <?php if ($project): ?>
                    <span class="badge bg-primary">
                        <i class="fas fa-project-diagram"></i> 
                        <?php echo htmlspecialchars($project['title']); ?>
                    </span>
                <?php endif; ?>
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
        
        <?php if (!$project): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> 
                <strong>No Project Found!</strong> 
                You must have an approved project to receive feedback. 
                <a href="my_project.php" class="alert-link">Submit your project title first.</a>
            </div>
        <?php else: ?>
            <div class="row">
                <!-- Left Column: Feedback List -->
                <div class="col-md-8">
                    <?php if ($submission): ?>
                        <!-- Selected Submission Info -->
                        <div class="card mb-4">
                            <div class="card-header bg-primary text-white">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">
                                        <i class="fas fa-file-alt me-2"></i>
                                        <?php echo htmlspecialchars($submission['type_name']); ?>
                                    </h5>
                                    <div>
                                        <span class="badge bg-light text-dark me-2">
                                            v<?php echo $submission['version']; ?>
                                        </span>
                                        <a href="download_submission.php?id=<?php echo $submission['id']; ?>" 
                                           class="btn btn-sm btn-light">
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>File:</strong> <?php echo htmlspecialchars($submission['file_name']); ?></p>
                                        <p><strong>Submitted:</strong> <?php echo date('F j, Y, g:i A', strtotime($submission['submission_date'])); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Status:</strong> 
                                            <span class="badge bg-<?php 
                                                echo $submission['status'] === 'approved' ? 'success' : 
                                                    ($submission['status'] === 'rejected' ? 'danger' : 
                                                    ($submission['status'] === 'resubmit' ? 'warning' : 'info')); 
                                            ?>">
                                                <?php echo ucfirst($submission['status']); ?>
                                            </span>
                                        </p>
                                        <?php if ($submission['reviewer_name']): ?>
                                            <p><strong>Reviewed by:</strong> <?php echo htmlspecialchars($submission['reviewer_name']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if ($submission['review_comments']): ?>
                                <div class="alert alert-info mt-3">
                                    <h6><i class="fas fa-comment"></i> Overall Review Comments:</h6>
                                    <p class="mb-0"><?php echo htmlspecialchars($submission['review_comments']); ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Feedback List -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-comments me-2"></i>
                                    Detailed Feedback
                                    <span class="badge bg-primary"><?php echo count($feedback); ?></span>
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($feedback)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-comment-slash fa-4x text-muted mb-3"></i>
                                        <h5>No Feedback Yet</h5>
                                        <p class="text-muted">Your supervisor hasn't provided detailed feedback yet.</p>
                                    </div>
                                <?php else: 
                                    $resolvedCount = 0;
                                    foreach ($feedback as $item) {
                                        if ($item['is_resolved']) $resolvedCount++;
                                    }
                                ?>
                                    <div class="mb-3">
                                        <div class="progress" style="height: 10px;">
                                            <div class="progress-bar bg-success" 
                                                 style="width: <?php echo count($feedback) > 0 ? ($resolvedCount / count($feedback) * 100) : 0; ?>%">
                                            </div>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo $resolvedCount; ?> of <?php echo count($feedback); ?> feedback items resolved
                                        </small>
                                    </div>
                                    
                                    <?php foreach ($feedback as $item): 
                                        $feedbackClass = 'feedback-' . $item['feedback_type'];
                                        if ($item['is_resolved']) $feedbackClass .= ' feedback-resolved';
                                    ?>
                                    <div class="card feedback-card <?php echo $feedbackClass; ?>">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <div>
                                                    <h6 class="mb-1">
                                                        <i class="fas fa-user-tie me-2"></i>
                                                        <?php echo htmlspecialchars($item['teacher_name']); ?>
                                                    </h6>
                                                    <small class="text-muted">
                                                        <?php echo date('F j, Y, g:i A', strtotime($item['created_at'])); ?>
                                                        <?php if ($item['page_number']): ?>
                                                            | Page <?php echo $item['page_number']; ?>
                                                            <?php if ($item['line_number']): ?>
                                                                , Line <?php echo $item['line_number']; ?>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                                <div>
                                                    <span class="badge bg-<?php 
                                                        echo $item['feedback_type'] === 'critical' ? 'danger' : 
                                                            ($item['feedback_type'] === 'suggestion' ? 'success' : 
                                                            ($item['feedback_type'] === 'specific' ? 'warning' : 'info')); 
                                                    ?>">
                                                        <?php echo ucfirst($item['feedback_type']); ?>
                                                    </span>
                                                    <?php if ($item['is_resolved']): ?>
                                                        <span class="badge bg-success">
                                                            <i class="fas fa-check"></i> Resolved
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <div class="comment-bubble">
                                                <?php echo nl2br(htmlspecialchars($item['comment'])); ?>
                                            </div>
                                            
                                            <?php if (!$item['is_resolved']): ?>
                                            <div class="mt-3 text-end">
                                                <form method="POST" action="" class="d-inline" 
                                                      onsubmit="return confirm('Mark this feedback as resolved?')">
                                                    <input type="hidden" name="feedback_id" value="<?php echo $item['id']; ?>">
                                                    <button type="submit" name="resolve_feedback" class="btn btn-sm btn-success">
                                                        <i class="fas fa-check me-2"></i> Mark as Resolved
                                                    </button>
                                                </form>
                                                <button type="button" class="btn btn-sm btn-outline-primary ms-2"
                                                        onclick="addResponse(<?php echo $item['id']; ?>)">
                                                    <i class="fas fa-reply me-2"></i> Respond
                                                </button>
                                            </div>
                                            <?php else: ?>
                                            <div class="mt-3">
                                                <small class="text-muted">
                                                    <i class="fas fa-check-circle text-success"></i>
                                                    Resolved by <?php echo htmlspecialchars($item['resolved_by_name']); ?> 
                                                    on <?php echo date('F j, Y', strtotime($item['resolved_at'])); ?>
                                                </small>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Select Submission -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-inbox me-2"></i>
                                    Select Submission to View Feedback
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($submissions)): ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i> 
                                        No submissions found. Submit documents first to receive feedback.
                                    </div>
                                <?php else: ?>
                                    <div class="list-group">
                                        <?php foreach ($submissions as $sub): 
                                            $feedbackCount = count($submissionManager->getSubmissionFeedback($sub['id']));
                                        ?>
                                        <a href="feedback.php?submission_id=<?php echo $sub['id']; ?>" 
                                           class="list-group-item list-group-item-action">
                                            <div class="d-flex w-100 justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($sub['type_name']); ?></h6>
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars($sub['file_name']); ?> | 
                                                        v<?php echo $sub['version']; ?>
                                                    </small>
                                                </div>
                                                <div class="text-end">
                                                    <span class="badge bg-<?php 
                                                        echo $sub['status'] === 'approved' ? 'success' : 
                                                            ($sub['status'] === 'rejected' ? 'danger' : 
                                                            ($sub['status'] === 'resubmit' ? 'warning' : 'info')); 
                                                    ?> me-2">
                                                        <?php echo ucfirst($sub['status']); ?>
                                                    </span>
                                                    <span class="badge bg-primary">
                                                        <?php echo $feedbackCount; ?> feedback
                                                    </span>
                                                </div>
                                            </div>
                                        </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Right Column: Statistics & Actions -->
                <div class="col-md-4">
                    <!-- Feedback Statistics -->
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-chart-pie me-2"></i>
                                Feedback Statistics
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php 
                            $totalFeedback = 0;
                            $criticalCount = 0;
                            $suggestionCount = 0;
                            $resolvedCount = 0;
                            
                            if ($submission) {
                                foreach ($feedback as $item) {
                                    $totalFeedback++;
                                    if ($item['feedback_type'] === 'critical') $criticalCount++;
                                    if ($item['feedback_type'] === 'suggestion') $suggestionCount++;
                                    if ($item['is_resolved']) $resolvedCount++;
                                }
                            } else {
                                // Get total feedback across all submissions
                                foreach ($submissions as $sub) {
                                    $subFeedback = $submissionManager->getSubmissionFeedback($sub['id']);
                                    foreach ($subFeedback as $item) {
                                        $totalFeedback++;
                                        if ($item['feedback_type'] === 'critical') $criticalCount++;
                                        if ($item['feedback_type'] === 'suggestion') $suggestionCount++;
                                        if ($item['is_resolved']) $resolvedCount++;
                                    }
                                }
                            }
                            ?>
                            
                            <div class="text-center mb-3">
                                <h1><?php echo $totalFeedback; ?></h1>
                                <p class="text-muted">Total Feedback Items</p>
                            </div>
                            
                            <div class="row text-center">
                                <div class="col-4">
                                    <div class="border rounded p-2 mb-2">
                                        <h5 class="text-danger mb-1"><?php echo $criticalCount; ?></h5>
                                        <small>Critical</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="border rounded p-2 mb-2">
                                        <h5 class="text-success mb-1"><?php echo $suggestionCount; ?></h5>
                                        <small>Suggestions</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="border rounded p-2 mb-2">
                                        <h5 class="text-primary mb-1"><?php echo $resolvedCount; ?></h5>
                                        <small>Resolved</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-bolt me-2"></i>
                                Quick Actions
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="submissions.php" class="btn btn-primary">
                                    <i class="fas fa-file-upload me-2"></i> Submit New Document
                                </a>
                                <a href="dashboard.php" class="btn btn-outline-primary">
                                    <i class="fas fa-tachometer-alt me-2"></i> Go to Dashboard
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tips for Using Feedback -->
                    <div class="card">
                        <div class="card-header bg-warning">
                            <h5 class="mb-0">
                                <i class="fas fa-lightbulb me-2"></i>
                                Tips for Using Feedback
                            </h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Read all feedback carefully
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Address critical issues first
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Mark items as resolved when fixed
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Ask for clarification if needed
                                </li>
                                <li>
                                    <i class="fas fa-check text-success me-2"></i>
                                    Use feedback to improve your work
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Response Modal -->
    <div class="modal fade" id="responseModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Respond to Feedback</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        Response functionality will be available in the next update.
                        For now, you can mark feedback as resolved or contact your supervisor directly.
                    </div>
                    <p>In the next version, you will be able to:</p>
                    <ul>
                        <li>Reply to specific feedback items</li>
                        <li>Ask for clarification</li>
                        <li>Upload revised documents</li>
                        <li>Track conversation history</li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Open response modal
        function addResponse(feedbackId) {
            const modal = new bootstrap.Modal(document.getElementById('responseModal'));
            modal.show();
        }
        
        // Auto-refresh page every 5 minutes if on feedback page
        if (window.location.search.includes('submission_id')) {
            setTimeout(() => {
                window.location.reload();
            }, 300000); // 5 minutes
        }
    </script>
</body>
</html>