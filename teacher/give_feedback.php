<!-- File: teacher/give_feedback.php -->
<?php
require_once '../includes/config/constants.php';
require_once '../includes/classes/Database.php';
require_once '../includes/classes/Session.php';
require_once '../includes/classes/Auth.php';
require_once '../includes/classes/SubmissionManager.php';
require_once '../includes/helpers/FileUpload.php';

Session::init();
$auth = new Auth();
$auth->requireRole(['teacher']);

$user = $auth->getUser();
$submissionManager = new SubmissionManager();

// Get submission ID
$submissionId = $_GET['submission_id'] ?? '';
$viewOnly = isset($_GET['view']) && $_GET['view'] === 'true';

// Get submission details
$submission = null;
$student = null;
$project = null;
$existingFeedback = [];

if ($submissionId) {
    // Get submission with project and student info
    $query = "SELECT s.*, 
              st.full_name as student_name, st.username as student_id, st.email as student_email,
              p.title as project_title, p.id as project_id, p.supervisor_id,
              d.dept_name
              FROM submissions s
              JOIN students st ON s.student_id = st.id
              JOIN projects p ON s.project_id = p.id
              JOIN departments d ON p.department_id = d.id
              WHERE s.id = :submission_id";
    
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare($query);
    $stmt->execute([':submission_id' => $submissionId]);
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Check if teacher is the supervisor
    if ($submission && $submission['supervisor_id'] != $user['user_id']) {
        header('Location: submissions.php');
        exit();
    }
    
    // Get existing feedback
    if ($submission) {
        $existingFeedback = $submissionManager->getFeedback($submissionId);
    }
}

$message = '';
$error = '';

// Process feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    if (!$submission) {
        $error = 'Submission not found';
    } else {
        $data = [
            'submission_id' => $submissionId,
            'teacher_id' => $user['user_id'],
            'comments' => trim($_POST['comments']),
            'marks' => !empty($_POST['marks']) ? $_POST['marks'] : null,
            'grade' => $_POST['grade'] ?? null,
            'status' => $_POST['status'] ?? 'under_review'
        ];
        
        $result = $submissionManager->giveFeedback($data);
        
        if ($result['success']) {
            $message = $result['message'];
            // Refresh feedback
            $existingFeedback = $submissionManager->getFeedback($submissionId);
            $viewOnly = true;
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Give Feedback - FYPMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .submission-info {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .feedback-history {
            background-color: #f1f5f9;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .rating-stars {
            color: #ffc107;
            font-size: 24px;
        }
        .grade-badge {
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 16px;
            font-weight: bold;
        }
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
                    <a class="nav-link" href="submissions.php">
                        <i class="fas fa-arrow-left me-2"></i> Back to Submissions
                    </a>
                </li>
            </ul>
        </div>
    </div>
    
    <div class="main-content">
        <!-- Header -->
        <nav class="navbar navbar-light bg-light mb-4 rounded">
            <div class="container-fluid">
                <h3 class="mb-0">
                    <?php echo $viewOnly ? 'View Feedback' : 'Give Feedback'; ?>
                </h3>
            </div>
        </nav>
        
        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (!$submission): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i>
                Submission not found or you don't have permission to access it.
                <a href="submissions.php" class="alert-link">Go back to submissions</a>
            </div>
        <?php else: ?>
            <!-- Submission Information -->
            <div class="submission-info">
                <div class="row">
                    <div class="col-md-6">
                        <h5>Submission Details</h5>
                        <p><strong>Student:</strong> <?php echo htmlspecialchars($submission['student_name']); ?></p>
                        <p><strong>Student ID:</strong> <?php echo htmlspecialchars($submission['student_id']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($submission['student_email']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Project:</strong> <?php echo htmlspecialchars($submission['project_title']); ?></p>
                        <p><strong>Document Type:</strong> 
                            <span class="badge bg-<?php 
                                echo $submission['submission_type'] === 'proposal' ? 'primary' : 
                                    ($submission['submission_type'] === 'progress_report' ? 'info' : 
                                    ($submission['submission_type'] === 'final_report' ? 'danger' : 'warning'));
                            ?>">
                                <?php echo str_replace('_', ' ', ucfirst($submission['submission_type'])); ?>
                            </span>
                        </p>
                        <p><strong>Submitted:</strong> <?php echo date('M d, Y h:i A', strtotime($submission['submitted_at'])); ?></p>
                        <p><strong>Status:</strong> 
                            <span class="badge bg-<?php 
                                echo $submission['status'] === 'approved' ? 'success' : 
                                    ($submission['status'] === 'pending' ? 'warning' : 
                                    ($submission['status'] === 'rejected' ? 'danger' : 'info'));
                            ?>">
                                <?php echo ucfirst($submission['status']); ?>
                            </span>
                        </p>
                    </div>
                </div>
                
                <div class="mt-3">
                    <h6>File: <?php echo htmlspecialchars($submission['file_name']); ?></h6>
                    <?php if ($submission['description']): ?>
                    <p class="mb-0"><strong>Student's Notes:</strong> <?php echo htmlspecialchars($submission['description']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Existing Feedback -->
            <?php if (!empty($existingFeedback) && $viewOnly): ?>
            <div class="feedback-history">
                <h5 class="mb-3">Feedback History</h5>
                <?php foreach ($existingFeedback as $fb): ?>
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h6 class="mb-1">Your Feedback</h6>
                                <small class="text-muted">
                                    Given on: <?php echo date('M d, Y h:i A', strtotime($fb['given_at'])); ?>
                                </small>
                            </div>
                            <div class="text-end">
                                <?php if ($fb['marks']): ?>
                                    <span class="badge bg-primary">Marks: <?php echo $fb['marks']; ?></span>
                                <?php endif; ?>
                                <?php if ($fb['grade']): ?>
                                    <span class="grade-badge bg-success">Grade: <?php echo $fb['grade']; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($fb['comments'])); ?></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <div class="text-center mt-3">
                    <a href="give_feedback.php?submission_id=<?php echo $submissionId; ?>" 
                       class="btn btn-primary">
                        <i class="fas fa-edit"></i> Edit Feedback
                    </a>
                </div>
            </div>
            <?php elseif ($viewOnly): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                No feedback given yet. You can provide feedback by editing this submission.
                <a href="give_feedback.php?submission_id=<?php echo $submissionId; ?>" class="alert-link">Give Feedback</a>
            </div>
            <?php endif; ?>
            
            <!-- Feedback Form (if not viewing only) -->
            <?php if (!$viewOnly): ?>
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-comment-medical me-2"></i>
                        Provide Feedback
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="" id="feedbackForm">
                        <!-- Comments -->
                        <div class="mb-4">
                            <label class="form-label"><strong>Comments *</strong></label>
                            <textarea class="form-control" name="comments" rows="8" required
                                      placeholder="Provide detailed feedback on the submission. Include strengths, areas for improvement, and specific recommendations."></textarea>
                            <small class="text-muted">Be constructive and specific in your feedback</small>
                        </div>
                        
                        <!-- Marks and Grade -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Marks (Optional)</label>
                                <input type="number" class="form-control" name="marks" 
                                       min="0" max="100" step="0.01"
                                       placeholder="Enter marks out of 100">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Grade (Optional)</label>
                                <select class="form-control" name="grade">
                                    <option value="">Select Grade</option>
                                    <option value="A">A - Excellent</option>
                                    <option value="B">B - Good</option>
                                    <option value="C">C - Satisfactory</option>
                                    <option value="D">D - Needs Improvement</option>
                                    <option value="F">F - Fail</option>
                                    <option value="Incomplete">Incomplete</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Status Update -->
                        <div class="mb-4">
                            <label class="form-label"><strong>Update Submission Status *</strong></label>
                            <div class="row">
                                <div class="col-md-4 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="status" 
                                               id="statusApproved" value="approved" checked>
                                        <label class="form-check-label" for="statusApproved">
                                            <span class="badge bg-success">Approve</span>
                                            <small class="d-block text-muted">Submission meets requirements</small>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="status" 
                                               id="statusResubmit" value="resubmit">
                                        <label class="form-check-label" for="statusResubmit">
                                            <span class="badge bg-warning">Request Resubmission</span>
                                            <small class="d-block text-muted">Needs corrections/improvements</small>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="status" 
                                               id="statusRejected" value="rejected">
                                        <label class="form-check-label" for="statusRejected">
                                            <span class="badge bg-danger">Reject</span>
                                            <small class="d-block text-muted">Does not meet requirements</small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Guidelines -->
                        <div class="alert alert-info">
                            <h6><i class="fas fa-lightbulb me-2"></i> Feedback Guidelines</h6>
                            <ul class="mb-0">
                                <li>Be specific about what needs to be improved</li>
                                <li>Provide examples or references when possible</li>
                                <li>Balance positive feedback with constructive criticism</li>
                                <li>Set clear expectations for resubmissions</li>
                            </ul>
                        </div>
                        
                        <!-- Submit Button -->
                        <div class="text-center">
                            <button type="submit" name="submit_feedback" class="btn btn-primary btn-lg px-5">
                                <i class="fas fa-paper-plane me-2"></i> Submit Feedback
                            </button>
                            <a href="submissions.php" class="btn btn-outline-secondary btn-lg ms-3">
                                <i class="fas fa-times me-2"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('feedbackForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const comments = form.querySelector('[name="comments"]').value.trim();
                    if (!comments) {
                        e.preventDefault();
                        alert('Please provide comments for your feedback');
                        return false;
                    }
                    
                    // Confirm before submitting
                    if (!confirm('Are you sure you want to submit this feedback? The student will be notified.')) {
                        e.preventDefault();
                        return false;
                    }
                });
            }
        });
    </script>
</body>
</html>