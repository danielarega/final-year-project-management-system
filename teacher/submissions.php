<?php
require_once '../includes/config/constants.php';
require_once '../includes/classes/Database.php';
require_once '../includes/classes/Session.php';
require_once '../includes/classes/Auth.php';
require_once '../includes/classes/SubmissionManager.php';
require_once '../includes/classes/ProjectManager.php';

Session::init();
$auth = new Auth();
$auth->requireRole(['teacher']);

$user = $auth->getUser();
$submissionManager = new SubmissionManager();
$projectManager = new ProjectManager();

// Get submission ID from query string
$submissionId = $_GET['id'] ?? 0;
$action = $_GET['action'] ?? '';

// Get submissions for review
$pendingSubmissions = $submissionManager->getSubmissionsForReview(
    $user['user_id'], 
    $user['department_id'], 
    'submitted'
);

$reviewedSubmissions = $submissionManager->getSubmissionsForReview(
    $user['user_id'], 
    $user['department_id'], 
    'under_review'
);

// Get specific submission for review
$submission = null;
$student = null;
$feedback = [];

if ($submissionId) {
    // Get submission details
    foreach (array_merge($pendingSubmissions, $reviewedSubmissions) as $sub) {
        if ($sub['id'] == $submissionId) {
            $submission = $sub;
            break;
        }
    }
    
    if ($submission) {
        // Get student info
        $student = [
            'name' => $submission['student_name'],
            'id' => $submission['student_id'],
            'project_title' => $submission['title']
        ];
        
        // Get existing feedback
        $feedback = $submissionManager->getSubmissionFeedback($submissionId);
    }
}

// Handle actions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['submit_feedback'])) {
        $data = [
            'submission_id' => $_POST['submission_id'],
            'teacher_id' => $user['user_id'],
            'feedback_type' => $_POST['feedback_type'],
            'comment' => trim($_POST['comment']),
            'page_number' => $_POST['page_number'] ? (int)$_POST['page_number'] : null,
            'line_number' => $_POST['line_number'] ? (int)$_POST['line_number'] : null
        ];
        
        $result = $submissionManager->addFeedback($data);
        
        if ($result['success']) {
            $message = $result['message'];
            header('Location: submissions.php?id=' . $submissionId . '&success=' . urlencode($message));
            exit();
        } else {
            $error = $result['message'];
        }
    }
    elseif (isset($_POST['review_submission'])) {
        $status = $_POST['review_status'];
        $comments = trim($_POST['review_comments']);
        $score = $_POST['review_score'] ? (float)$_POST['review_score'] : null;
        
        $result = $submissionManager->reviewSubmission(
            $_POST['submission_id'],
            $user['user_id'],
            $status,
            $comments,
            $score
        );
        
        if ($result['success']) {
            $message = $result['message'];
            header('Location: submissions.php?success=' . urlencode($message));
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Submissions - FYPMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .submission-card {
            border: 1px solid #ddd;
            border-radius: 10px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .submission-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .submission-card.selected {
            border-color: #667eea;
            background: #f8f9fe;
        }
        .feedback-panel {
            max-height: 400px;
            overflow-y: auto;
        }
        .comment-item {
            border-left: 3px solid #667eea;
            padding-left: 10px;
            margin-bottom: 10px;
        }
        .comment-critical { border-left-color: #dc3545; }
        .comment-suggestion { border-left-color: #28a745; }
    </style>
</head>
<body>
    <!-- Teacher Sidebar -->
    <div class="sidebar">
        <div class="p-4">
            <h4 class="text-center">FYPMS</h4>
            <hr class="bg-white">
            <div class="text-center mb-4">
                <div class="rounded-circle bg-white d-inline-flex align-items-center justify-content-center" 
                     style="width: 60px; height: 60px;">
                    <i class="fas fa-chalkboard-teacher" style="color: #667eea;"></i>
                </div>
                <h6 class="mt-2"><?php echo htmlspecialchars($user['full_name']); ?></h6>
                <small>Teacher / Supervisor</small>
            </div>
            
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="my_students.php">
                        <i class="fas fa-user-graduate me-2"></i> My Students
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="submissions.php">
                        <i class="fas fa-file-upload me-2"></i> Review Submissions
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
                    <h3 class="mb-0 d-inline">Review Student Submissions</h3>
                </div>
                <div>
                    <span class="badge bg-warning me-2">
                        <i class="fas fa-clock"></i> <?php echo count($pendingSubmissions); ?> Pending
                    </span>
                    <span class="badge bg-info">
                        <i class="fas fa-check"></i> <?php echo count($reviewedSubmissions); ?> Reviewed
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
            <!-- Left Column: Submission List -->
            <div class="col-md-4">
                <!-- Pending Submissions -->
                <div class="card mb-4">
                    <div class="card-header bg-warning">
                        <h5 class="mb-0">
                            <i class="fas fa-clock me-2"></i>
                            Pending Review
                            <span class="badge bg-light text-dark"><?php echo count($pendingSubmissions); ?></span>
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($pendingSubmissions)): ?>
                            <div class="p-3 text-center">
                                <i class="fas fa-check-circle fa-2x text-success mb-3"></i>
                                <p class="text-muted">No pending submissions</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($pendingSubmissions as $sub): 
                                    $isSelected = ($submission && $sub['id'] == $submission['id']);
                                ?>
                                <a href="submissions.php?id=<?php echo $sub['id']; ?>" 
                                   class="list-group-item list-group-item-action <?php echo $isSelected ? 'active' : ''; ?>">
                                    <div class="d-flex w-100 justify-content-between">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($sub['student_name']); ?></h6>
                                            <small class="<?php echo $isSelected ? 'text-light' : 'text-muted'; ?>">
                                                <?php echo htmlspecialchars($sub['submission_type']); ?>
                                            </small>
                                        </div>
                                        <small class="<?php echo $isSelected ? 'text-light' : 'text-muted'; ?>">
                                            <?php echo date('M d', strtotime($sub['submitted_date'])); ?>
                                        </small>
                                    </div>
                                    <p class="mb-1 <?php echo $isSelected ? 'text-light' : ''; ?>">
                                        <small><?php echo htmlspecialchars($sub['project_title']); ?></small>
                                    </p>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Reviewed Submissions -->
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-check-circle me-2"></i>
                            Recently Reviewed
                            <span class="badge bg-light text-dark"><?php echo count($reviewedSubmissions); ?></span>
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($reviewedSubmissions)): ?>
                            <div class="p-3 text-center">
                                <i class="fas fa-history fa-2x text-muted mb-3"></i>
                                <p class="text-muted">No reviewed submissions</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($reviewedSubmissions as $sub): 
                                    $isSelected = ($submission && $sub['id'] == $submission['id']);
                                ?>
                                <a href="submissions.php?id=<?php echo $sub['id']; ?>" 
                                   class="list-group-item list-group-item-action <?php echo $isSelected ? 'active' : ''; ?>">
                                    <div class="d-flex w-100 justify-content-between">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($sub['student_name']); ?></h6>
                                            <small class="<?php echo $isSelected ? 'text-light' : 'text-muted'; ?>">
                                                <?php echo htmlspecialchars($sub['submission_type']); ?>
                                            </small>
                                        </div>
                                        <small class="<?php echo $isSelected ? 'text-light' : 'text-muted'; ?>">
                                            <?php echo date('M d', strtotime($sub['review_date'])); ?>
                                        </small>
                                    </div>
                                    <p class="mb-1 <?php echo $isSelected ? 'text-light' : ''; ?>">
                                        <small>
                                            Status: 
                                            <span class="badge bg-<?php 
                                                echo $sub['review_status'] === 'accepted' ? 'success' : 
                                                    ($sub['review_status'] === 'needs_revision' ? 'warning' : 'info'); 
                                            ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $sub['review_status'])); ?>
                                            </span>
                                        </small>
                                    </p>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Right Column: Review Interface -->
            <div class="col-md-8">
                <?php if ($submission): ?>
                    <!-- Submission Details -->
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-file-alt me-2"></i>
                                    Review Submission
                                </h5>
                                <div>
                                    <span class="badge bg-light text-dark me-2">
                                        v<?php echo $submission['version']; ?>
                                    </span>
                                    <a href="../student/download_submission.php?id=<?php echo $submission['id']; ?>" 
                                       class="btn btn-sm btn-light">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <h6>Student Information</h6>
                                    <p class="mb-1">
                                        <strong>Name:</strong> <?php echo htmlspecialchars($submission['student_name']); ?>
                                    </p>
                                    <p class="mb-1">
                                        <strong>Student ID:</strong> <?php echo htmlspecialchars($submission['student_id']); ?>
                                    </p>
                                    <p class="mb-1">
                                        <strong>Project:</strong> <?php echo htmlspecialchars($submission['project_title']); ?>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <h6>Submission Details</h6>
                                    <p class="mb-1">
                                        <strong>Type:</strong> <?php echo htmlspecialchars($submission['submission_type']); ?>
                                    </p>
                                    <p class="mb-1">
                                        <strong>Submitted:</strong> <?php echo date('F j, Y, g:i A', strtotime($submission['submitted_date'])); ?>
                                    </p>
                                    <p class="mb-1">
                                        <strong>File:</strong> <?php echo htmlspecialchars($submission['file_name']); ?>
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Review Form -->
                            <form method="POST" action="" id="reviewForm">
                                <input type="hidden" name="submission_id" value="<?php echo $submission['id']; ?>">
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Review Status *</label>
                                        <select class="form-control" name="review_status" required>
                                            <option value="accepted" <?php echo $submission['review_status'] === 'accepted' ? 'selected' : ''; ?>>
                                                Accepted
                                            </option>
                                            <option value="needs_revision" <?php echo $submission['review_status'] === 'needs_revision' ? 'selected' : ''; ?>>
                                                Needs Revision
                                            </option>
                                            <option value="reviewed" <?php echo $submission['review_status'] === 'reviewed' ? 'selected' : ''; ?>>
                                                Reviewed (No Action)
                                            </option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Score (0-100)</label>
                                        <input type="number" class="form-control" name="review_score" 
                                               min="0" max="100" step="0.5"
                                               value="<?php echo $submission['review_score'] ?? ''; ?>"
                                               placeholder="Optional">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Overall Comments</label>
                                    <textarea class="form-control" name="review_comments" rows="4" 
                                              placeholder="Provide overall feedback on this submission..."><?php 
                                        echo htmlspecialchars($submission['review_comments'] ?? ''); 
                                    ?></textarea>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" name="review_submission" class="btn btn-success">
                                        <i class="fas fa-check-circle me-2"></i> Submit Review
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Add Detailed Feedback -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-comment-dots me-2"></i>
                                Add Detailed Feedback
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="" id="feedbackForm">
                                <input type="hidden" name="submission_id" value="<?php echo $submission['id']; ?>">
                                
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Feedback Type</label>
                                        <select class="form-control" name="feedback_type" required>
                                            <option value="general">General Feedback</option>
                                            <option value="specific">Specific Issue</option>
                                            <option value="critical">Critical Problem</option>
                                            <option value="suggestion">Suggestion</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Page Number</label>
                                        <input type="number" class="form-control" name="page_number" 
                                               min="1" placeholder="Optional">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Line Number</label>
                                        <input type="number" class="form-control" name="line_number" 
                                               min="1" placeholder="Optional">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Feedback/Comment *</label>
                                    <textarea class="form-control" name="comment" rows="4" required 
                                              placeholder="Enter detailed feedback..."></textarea>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" name="submit_feedback" class="btn btn-primary">
                                        <i class="fas fa-paper-plane me-2"></i> Add Feedback
                                    </button>
                                </div>
                            </form>
                            
                            <!-- Previous Feedback -->
                            <?php if (!empty($feedback)): ?>
                            <hr class="my-4">
                            <h6>Previous Feedback</h6>
                            <div class="feedback-panel mt-3">
                                <?php foreach ($feedback as $item): ?>
                                <div class="comment-item <?php echo 'comment-' . $item['feedback_type']; ?>">
                                    <div class="d-flex justify-content-between">
                                        <strong>
                                            <i class="fas fa-user-tie me-2"></i>
                                            <?php echo htmlspecialchars($item['teacher_name']); ?>
                                        </strong>
                                        <small class="text-muted">
                                            <?php echo date('M d, g:i A', strtotime($item['created_at'])); ?>
                                        </small>
                                    </div>
                                    <p class="mb-1"><?php echo nl2br(htmlspecialchars($item['comment'])); ?></p>
                                    <?php if ($item['page_number']): ?>
                                        <small class="text-muted">
                                            Page <?php echo $item['page_number']; ?>
                                            <?php if ($item['line_number']): ?>
                                                , Line <?php echo $item['line_number']; ?>
                                            <?php endif; ?>
                                        </small>
                                    <?php endif; ?>
                                    <?php if ($item['is_resolved']): ?>
                                        <div class="mt-2">
                                            <span class="badge bg-success">
                                                <i class="fas fa-check"></i> Resolved
                                            </span>
                                            <small class="text-muted">
                                                by <?php echo htmlspecialchars($item['resolved_by_name']); ?>
                                                on <?php echo date('M d, Y', strtotime($item['resolved_at'])); ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- No Submission Selected -->
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-file-alt fa-4x text-muted mb-4"></i>
                            <h4>No Submission Selected</h4>
                            <p class="text-muted">Select a submission from the list to review it.</p>
                            <div class="mt-4">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="card text-white bg-warning">
                                            <div class="card-body text-center">
                                                <h1><?php echo count($pendingSubmissions); ?></h1>
                                                <p class="mb-0">Pending Review</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="card text-white bg-info">
                                            <div class="card-body text-center">
                                                <h1><?php echo count($reviewedSubmissions); ?></h1>
                                                <p class="mb-0">Reviewed</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-save draft of feedback
        let feedbackDraft = {
            type: 'general',
            page: '',
            line: '',
            comment: ''
        };
        
        // Load draft from localStorage
        const savedDraft = localStorage.getItem('feedbackDraft');
        if (savedDraft) {
            feedbackDraft = JSON.parse(savedDraft);
            document.querySelector('select[name="feedback_type"]').value = feedbackDraft.type;
            document.querySelector('input[name="page_number"]').value = feedbackDraft.page;
            document.querySelector('input[name="line_number"]').value = feedbackDraft.line;
            document.querySelector('textarea[name="comment"]').value = feedbackDraft.comment;
        }
        
        // Save draft on change
        document.getElementById('feedbackForm').addEventListener('input', function() {
            feedbackDraft = {
                type: this.querySelector('select[name="feedback_type"]').value,
                page: this.querySelector('input[name="page_number"]').value,
                line: this.querySelector('input[name="line_number"]').value,
                comment: this.querySelector('textarea[name="comment"]').value
            };
            localStorage.setItem('feedbackDraft', JSON.stringify(feedbackDraft));
        });
        
        // Clear draft on submit
        document.getElementById('feedbackForm').addEventListener('submit', function() {
            localStorage.removeItem('feedbackDraft');
        });
        
        // Auto-refresh submission list every 2 minutes
        setInterval(() => {
            if (!document.hidden) {
                fetch(window.location.pathname + '?refresh=true')
                    .then(response => response.text())
                    .then(html => {
                        // Update pending submissions count
                        const parser = new DOMParser();
                        const newDoc = parser.parseFromString(html, 'text/html');
                        const newCount = newDoc.querySelector('.badge.bg-warning')?.textContent;
                        if (newCount) {
                            document.querySelector('.badge.bg-warning').textContent = newCount;
                        }
                    });
            }
        }, 120000);
    </script>
</body>
</html>