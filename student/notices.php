<?php
require_once '../includes/config/constants.php';
require_once '../includes/classes/Database.php';
require_once '../includes/classes/Session.php';
require_once '../includes/classes/Auth.php';
require_once '../includes/classes/NoticeManager.php';

Session::init();
$auth = new Auth();
$auth->requireRole(['student']);

$user = $auth->getUser();
$noticeManager = new NoticeManager();

// Get notices for student's department and batch
$notices = $noticeManager->getDepartmentNotices($user['department_id'], $user['batch_id']);

// Get unread count
$unreadCount = $noticeManager->getUnreadCount($user['user_id'], 'student', $user['department_id'], $user['batch_id']);

// Mark notice as read when viewed
$viewNoticeId = $_GET['view'] ?? null;
if ($viewNoticeId) {
    $noticeManager->markAsRead($viewNoticeId, $user['user_id'], 'student');
    
    // Get the specific notice
    $viewNotice = $noticeManager->getNoticeById($viewNoticeId);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notices - FYPMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .notice-card {
            border-left: 4px solid;
            margin-bottom: 15px;
            transition: all 0.3s;
            cursor: pointer;
        }
        .notice-card:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .priority-urgent { border-left-color: #dc3545; background-color: #f8d7da; }
        .priority-high { border-left-color: #fd7e14; background-color: #fff3cd; }
        .priority-medium { border-left-color: #ffc107; background-color: #fef9e7; }
        .priority-low { border-left-color: #28a745; background-color: #d4edda; }
        .unread { border-left-width: 6px; }
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
                    <a class="nav-link active" href="notices.php">
                        <i class="fas fa-bullhorn me-2"></i> Notices
                        <?php if ($unreadCount > 0): ?>
                            <span class="badge bg-danger float-end"><?php echo $unreadCount; ?></span>
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
                    <a href="dashboard.php" class="btn btn-outline-primary btn-sm me-2">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <h3 class="mb-0 d-inline">Department Notices</h3>
                </div>
                <div>
                    <?php if ($unreadCount > 0): ?>
                        <span class="badge bg-danger">
                            <i class="fas fa-bell"></i> <?php echo $unreadCount; ?> Unread
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </nav>
        
        <?php if ($viewNoticeId && $viewNotice): ?>
            <!-- View Single Notice -->
            <div class="card mb-4">
                <div class="card-header <?php echo 'priority-' . $viewNotice['priority']; ?>">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><?php echo htmlspecialchars($viewNotice['title']); ?></h5>
                        <a href="notices.php" class="btn btn-sm btn-outline-light">
                            <i class="fas fa-arrow-left"></i> Back to List
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <?php echo nl2br(htmlspecialchars($viewNotice['content'])); ?>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Priority:</strong> 
                                <span class="badge bg-<?php echo $viewNotice['priority'] === 'urgent' ? 'danger' : ($viewNotice['priority'] === 'high' ? 'warning' : ($viewNotice['priority'] === 'medium' ? 'info' : 'success')); ?>">
                                    <?php echo ucfirst($viewNotice['priority']); ?>
                                </span>
                            </p>
                            <p><strong>Target Batch:</strong> 
                                <?php echo $viewNotice['batch_name'] ? htmlspecialchars($viewNotice['batch_name']) : 'All Batches'; ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Posted By:</strong> <?php echo htmlspecialchars($viewNotice['created_by_name']); ?></p>
                            <p><strong>Posted On:</strong> <?php echo date('M d, Y h:i A', strtotime($viewNotice['created_at'])); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Notices List -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        All Notices
                        <span class="badge bg-primary"><?php echo count($notices); ?></span>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($notices)): ?>
                        <div class="alert alert-info text-center py-4">
                            <i class="fas fa-bullhorn fa-3x mb-3"></i>
                            <h4>No Notices Available</h4>
                            <p class="mb-0">There are no notices posted for your department and batch yet.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($notices as $notice): 
                            $priorityClass = 'priority-' . $notice['priority'];
                            $isRead = $noticeManager->isNoticeRead($notice['id'], $user['user_id'], 'student');
                            $unreadClass = $isRead ? '' : 'unread';
                        ?>
                        <div class="notice-card <?php echo $priorityClass . ' ' . $unreadClass; ?> p-3" 
                             onclick="window.location.href='notices.php?view=<?php echo $notice['id']; ?>'">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h5 class="mb-1"><?php echo htmlspecialchars($notice['title']); ?></h5>
                                    <p class="mb-2">
                                        <?php echo substr(htmlspecialchars($notice['content']), 0, 200); ?>
                                        <?php if (strlen($notice['content']) > 200): ?>... <span class="text-primary">(click to read more)</span><?php endif; ?>
                                    </p>
                                    <small class="text-muted">
                                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($notice['created_by_name']); ?>
                                        | <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($notice['created_at'])); ?>
                                        <?php if (!$isRead): ?>
                                            | <span class="badge bg-danger">NEW</span>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <div>
                                    <span class="badge bg-<?php echo $notice['priority'] === 'urgent' ? 'danger' : ($notice['priority'] === 'high' ? 'warning' : ($notice['priority'] === 'medium' ? 'info' : 'success')); ?>">
                                        <?php echo ucfirst($notice['priority']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Important Dates -->
        <div class="card mt-4">
            <div class="card-header bg-warning">
                <h5 class="mb-0">
                    <i class="fas fa-calendar-alt me-2"></i>
                    Upcoming Important Dates
                </h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <strong>Title Submission Deadline</strong>
                        <span class="badge bg-danger">7 days</span>
                    </div>
                    <small class="text-muted">Due: Jan 25, 2026</small>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <strong>Proposal Submission</strong>
                        <span class="badge bg-warning">21 days</span>
                    </div>
                    <small class="text-muted">Due: Feb 8, 2026</small>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <strong>Final Report</strong>
                        <span class="badge bg-success">45 days</span>
                    </div>
                    <small class="text-muted">Due: Mar 15, 2026</small>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>