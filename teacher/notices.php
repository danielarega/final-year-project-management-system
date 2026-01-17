<?php
// File: student/notices.php (similar for teacher/notices.php)
require_once '../includes/config/constants.php';
require_once '../includes/classes/Database.php';
require_once '../includes/classes/Session.php';
require_once '../includes/classes/Auth.php';
require_once '../includes/classes/NoticeManager.php';

Session::init();
$auth = new Auth();
$auth->requireRole(['student']); // For teacher version, change to 'teacher'

$user = $auth->getUser();
$userType = $user['user_type'];
$noticeManager = new NoticeManager();

// Mark notice as read if viewing single notice
$noticeId = $_GET['view'] ?? 0;
if ($noticeId) {
    $noticeManager->markAsRead($noticeId, $user['user_id'], $userType);
}

// Get all notices for the department
$notices = $noticeManager->getDepartmentNotices($user['department_id'], $userType, 50);

// Get unread count
$unreadCount = $noticeManager->getUnreadCount($user['department_id'], $user['user_id'], $userType);
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
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        .notice-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .notice-urgent { border-left-color: #dc3545; }
        .notice-high { border-left-color: #fd7e14; }
        .notice-medium { border-left-color: #ffc107; }
        .notice-low { border-left-color: #28a745; }
        .unread-notice {
            background-color: #f8f9fa;
            border-left-width: 6px;
        }
    </style>
</head>
<body>
    <!-- Include student/teacher sidebar -->
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
                <small><?php echo ucfirst($userType); ?></small>
            </div>
            
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
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
                    <h3 class="mb-0 d-inline">
                        <i class="fas fa-bullhorn"></i> Notices
                        <?php if ($unreadCount > 0): ?>
                            <span class="badge bg-danger"><?php echo $unreadCount; ?> unread</span>
                        <?php endif; ?>
                    </h3>
                </div>
                <div>
                    <span class="badge bg-primary">
                        Total: <?php echo count($notices); ?> notices
                    </span>
                </div>
            </div>
        </nav>
        
        <!-- Notices List -->
        <div class="row">
            <div class="col-md-12">
                <?php if (empty($notices)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No notices available at the moment.
                    </div>
                <?php else: ?>
                    <!-- Filter Buttons -->
                    <div class="mb-4">
                        <div class="btn-group" role="group">
                            <a href="notices.php" class="btn btn-outline-primary active">All Notices</a>
                            <a href="notices.php?filter=unread" class="btn btn-outline-danger">
                                Unread <span class="badge bg-danger"><?php echo $unreadCount; ?></span>
                            </a>
                            <a href="notices.php?filter=urgent" class="btn btn-outline-danger">Urgent</a>
                        </div>
                    </div>
                    
                    <!-- Notices -->
                    <?php foreach ($notices as $notice): 
                        $isRead = $notice['is_read'] > 0;
                        $priorityClass = 'notice-' . $notice['priority'];
                    ?>
                    <div class="card notice-card <?php echo $priorityClass; ?> <?php echo !$isRead ? 'unread-notice' : ''; ?>">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h5 class="card-title">
                                        <?php if (!$isRead): ?>
                                            <span class="badge bg-danger me-2">NEW</span>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($notice['title']); ?>
                                    </h5>
                                    <h6 class="card-subtitle mb-2 text-muted">
                                        <i class="fas fa-user-tie"></i> 
                                        Posted by: <?php echo htmlspecialchars($notice['created_by_name']); ?>
                                        | 
                                        <i class="fas fa-calendar"></i> 
                                        <?php echo date('F j, Y h:i A', strtotime($notice['created_at'])); ?>
                                    </h6>
                                </div>
                                <div>
                                    <span class="badge bg-<?php echo $notice['priority'] === 'urgent' ? 'danger' : ($notice['priority'] === 'high' ? 'warning' : 'info'); ?>">
                                        <?php echo ucfirst($notice['priority']); ?> Priority
                                    </span>
                                    <?php if ($notice['batch_name']): ?>
                                        <span class="badge bg-secondary ms-1">
                                            <?php echo htmlspecialchars($notice['batch_name']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="card-text mb-3">
                                <?php echo nl2br(htmlspecialchars($notice['content'])); ?>
                            </div>
                            
                            <div class="text-muted small">
                                <?php if ($isRead): ?>
                                    <i class="fas fa-check-circle text-success"></i> 
                                    Read on <?php echo date('M d, Y', strtotime($notice['read_at'] ?? $notice['created_at'])); ?>
                                <?php else: ?>
                                    <i class="fas fa-envelope text-danger"></i> Unread
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>