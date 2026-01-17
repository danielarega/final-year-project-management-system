<!-- File: teacher/submissions.php -->
<?php
require_once '../includes/config/constants.php';
require_once '../includes/classes/Database.php';
require_once '../includes/classes/Session.php';
require_once '../includes/classes/Auth.php';
require_once '../includes/classes/SubmissionManager.php';

Session::init();
$auth = new Auth();
$auth->requireRole(['teacher']);

$user = $auth->getUser();
$submissionManager = new SubmissionManager();

// Get submissions to review
$submissions = $submissionManager->getTeacherSubmissions($user['user_id']);

// Group by status
$pendingSubmissions = array_filter($submissions, function($s) { return $s['status'] === 'pending'; });
$reviewedSubmissions = array_filter($submissions, function($s) { return $s['status'] !== 'pending'; });

$message = '';
$error = '';

// Process actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $submissionId = $_POST['submission_id'] ?? '';
    
    switch ($action) {
        case 'start_review':
            // Mark submission as under review
            // In a real implementation, this would update the status
            $message = 'Started reviewing submission';
            break;
            
        case 'download':
            // File download would be handled here
            break;
    }
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
        .submission-item {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            background-color: white;
            transition: all 0.3s;
        }
        .submission-item:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .submission-item.pending {
            border-left: 4px solid #ffc107;
        }
        .submission-item.reviewed {
            border-left: 4px solid #198754;
        }
        .badge-type {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
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
                        <?php if (count($pendingSubmissions) > 0): ?>
                            <span class="badge bg-danger float-end"><?php echo count($pendingSubmissions); ?></span>
                        <?php endif; ?>
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
                    <a href="my_students.php" class="btn btn-outline-primary btn-sm me-2">
                        <i class="fas fa-arrow-left"></i> Back to My Students
                    </a>
                    <h3 class="mb-0 d-inline">Review Submissions</h3>
                </div>
                <div>
                    <span class="badge bg-warning">
                        Pending: <?php echo count($pendingSubmissions); ?>
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
        
        <!-- Pending Submissions -->
        <div class="card mb-4">
            <div class="card-header bg-warning">
                <h5 class="mb-0 text-white">
                    <i class="fas fa-clock me-2"></i>
                    Pending Review
                    <span class="badge bg-light text-dark"><?php echo count($pendingSubmissions); ?></span>
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($pendingSubmissions)): ?>
                    <div class="alert alert-info text-center py-4">
                        <i class="fas fa-check-circle fa-2x mb-3"></i>
                        <h4>No Pending Submissions</h4>
                        <p class="mb-0">All submissions have been reviewed.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($pendingSubmissions as $submission): ?>
                    <div class="submission-item pending">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h5 class="mb-1"><?php echo htmlspecialchars($submission['student_name']); ?></h5>
                                <p class="mb-1">
                                    <small class="text-muted">
                                        ID: <?php echo htmlspecialchars($submission['student_id']); ?> | 
                                        Submitted: <?php echo date('M d, Y', strtotime($submission['submitted_at'])); ?>
                                    </small>
                                </p>
                            </div>
                            <div>
                                <span class="badge-type bg-<?php 
                                    echo $submission['submission_type'] === 'proposal' ? 'primary' : 
                                        ($submission['submission_type'] === 'progress_report' ? 'info' : 
                                        ($submission['submission_type'] === 'final_report' ? 'danger' : 'warning'));
                                ?>">
                                    <?php echo str_replace('_', ' ', ucfirst($submission['submission_type'])); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <h6><?php echo htmlspecialchars($submission['project_title']); ?></h6>
                            <p class="mb-2">
                                <i class="fas fa-file me-1"></i>
                                <?php echo htmlspecialchars($submission['file_name']); ?>
                                <small class="text-muted ms-2">v<?php echo $submission['version']; ?></small>
                            </p>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                <i class="fas fa-history me-1"></i>
                                Waiting for your review
                            </small>
                            <div class="btn-group btn-group-sm">
                                <form method="POST" action="" class="d-inline">
                                    <input type="hidden" name="submission_id" value="<?php echo $submission['id']; ?>">
                                    <button type="submit" name="action" value="download" class="btn btn-outline-primary">
                                        <i class="fas fa-download"></i> Download
                                    </button>
                                </form>
                                <a href="give_feedback.php?submission_id=<?php echo $submission['id']; ?>" 
                                   class="btn btn-primary">
                                    <i class="fas fa-comment-medical"></i> Give Feedback
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Reviewed Submissions -->
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">
                    <i class="fas fa-check-circle me-2"></i>
                    Reviewed Submissions
                    <span class="badge bg-light text-dark"><?php echo count($reviewedSubmissions); ?></span>
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($reviewedSubmissions)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        No reviewed submissions yet.
                    </div>
                <?php else: ?>
                    <?php foreach ($reviewedSubmissions as $submission): ?>
                    <div class="submission-item reviewed">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h6 class="mb-1"><?php echo htmlspecialchars($submission['student_name']); ?></h6>
                                <p class="mb-1">
                                    <small class="text-muted">
                                        ID: <?php echo htmlspecialchars($submission['student_id']); ?>
                                    </small>
                                </p>
                            </div>
                            <div>
                                <span class="badge bg-<?php echo $submission['status'] === 'approved' ? 'success' : 'danger'; ?>">
                                    <?php echo ucfirst($submission['status']); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="mb-2">
                            <p class="mb-1">
                                <strong><?php echo htmlspecialchars($submission['project_title']); ?></strong>
                            </p>
                            <p class="mb-2">
                                <i class="fas fa-file me-1"></i>
                                <?php echo htmlspecialchars(substr($submission['file_name'], 0, 40)); ?>...
                                <small class="text-muted ms-2">v<?php echo $submission['version']; ?></small>
                            </p>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                <i class="fas fa-calendar me-1"></i>
                                <?php echo date('M d, Y', strtotime($submission['submitted_at'])); ?>
                            </small>
                            <div>
                                <span class="badge bg-info">
                                    Feedback: <?php echo $submission['feedback_count']; ?>
                                </span>
                                <a href="give_feedback.php?submission_id=<?php echo $submission['id']; ?>&view=true" 
                                   class="btn btn-sm btn-outline-success ms-2">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Statistics -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>
                    Submission Statistics
                </h5>
            </div>
            <div class="card-body">
                <?php
                $stats = $submissionManager->getSubmissionStatistics($user['user_id']);
                if (!empty($stats)):
                ?>
                <div class="row">
                    <?php foreach ($stats as $stat): ?>
                    <div class="col-md-3 mb-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-<?php 
                                    echo $stat['submission_type'] === 'proposal' ? 'primary' : 
                                        ($stat['submission_type'] === 'progress_report' ? 'info' : 
                                        ($stat['submission_type'] === 'final_report' ? 'danger' : 'warning'));
                                ?>">
                                    <?php echo $stat['total']; ?>
                                </h3>
                                <h6><?php echo str_replace('_', ' ', ucfirst($stat['submission_type'])); ?></h6>
                                <small class="text-muted">
                                    Pending: <?php echo $stat['pending']; ?> | 
                                    Reviewed: <?php echo $stat['under_review'] + $stat['approved'] + $stat['rejected']; ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-muted text-center">No submission statistics available.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>