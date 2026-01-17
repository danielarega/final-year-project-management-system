<!-- admin/notices.php -->
<?php
require_once '../includes/config/constants.php';
require_once '../includes/classes/Database.php';
require_once '../includes/classes/Session.php';
require_once '../includes/classes/Auth.php';
require_once '../includes/classes/NoticeManager.php';
require_once '../includes/classes/BatchManager.php';

Session::init();
$auth = new Auth();
$auth->requireRole(['admin']);

$user = $auth->getUser();
$noticeManager = new NoticeManager();
$batchManager = new BatchManager();

// Get batches for notice targeting
$batches = $batchManager->getBatchesByDepartment($user['department_id']);

// Get all notices for the department
$notices = $noticeManager->getDepartmentNotices($user['department_id']);

// Handle actions
$action = $_GET['action'] ?? '';
$noticeId = $_GET['id'] ?? '';
$message = '';
$error = '';

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_notice'])) {
        $data = [
            'title' => trim($_POST['title']),
            'content' => trim($_POST['content']),
            'department_id' => $user['department_id'],
            'batch_id' => $_POST['batch_id'] ?: null,
            'created_by' => $user['user_id'],
            'user_type' => $_POST['user_type'],
            'priority' => $_POST['priority'],
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        
        $result = $noticeManager->createNotice($data);
        if ($result['success']) {
            $message = $result['message'];
            header('Location: notices.php?success=' . urlencode($message));
            exit();
        } else {
            $error = $result['message'];
        }
    }
    elseif (isset($_POST['update_notice'])) {
        $data = [
            'title' => trim($_POST['title']),
            'content' => trim($_POST['content']),
            'batch_id' => $_POST['batch_id'] ?: null,
            'user_type' => $_POST['user_type'],
            'priority' => $_POST['priority'],
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        
        $result = $noticeManager->updateNotice($_POST['notice_id'], $data);
        if ($result['success']) {
            $message = $result['message'];
            header('Location: notices.php?success=' . urlencode($message));
            exit();
        } else {
            $error = $result['message'];
        }
    }
    elseif (isset($_POST['delete_notice'])) {
        $result = $noticeManager->deleteNotice($_POST['notice_id']);
        if ($result['success']) {
            $message = $result['message'];
            header('Location: notices.php?success=' . urlencode($message));
            exit();
        } else {
            $error = $result['message'];
        }
    }
}

// Get notice for editing
$editNotice = null;
if ($action === 'edit' && $noticeId) {
    $editNotice = $noticeManager->getNoticeById($noticeId);
    if (!$editNotice) {
        $error = 'Notice not found';
        $action = '';
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
    <title>Notices Management - FYPMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <style>
        .priority-low { border-left: 3px solid #28a745; }
        .priority-medium { border-left: 3px solid #ffc107; }
        .priority-high { border-left: 3px solid #fd7e14; }
        .priority-urgent { border-left: 3px solid #dc3545; }
        .notice-card {
            transition: transform 0.3s, box-shadow 0.3s;
            margin-bottom: 15px;
        }
        .notice-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <!-- Include admin sidebar -->
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
                    <a class="nav-link" href="projects.php">
                        <i class="fas fa-project-diagram me-2"></i> Projects
                    </a>
                </li>
                <li class="nav-item">
    <a class="nav-link" href="supervisor_assignment.php">
        <i class="fas fa-user-tie me-2"></i> Supervisor Assignment
    </a>
</li>
                <li class="nav-item">
                    <a class="nav-link active" href="notices.php">
                        <i class="fas fa-bullhorn me-2"></i> Notices
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="reports.php">
                        <i class="fas fa-chart-bar me-2"></i> Reports
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
                <h3 class="mb-0">Notices Management</h3>
                <div>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createNoticeModal">
                        <i class="fas fa-plus-circle"></i> Post Notice
                    </button>
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
        
        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3><?php echo count($notices); ?></h3>
                                <h6>Total Notices</h6>
                            </div>
                            <i class="fas fa-bullhorn fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <?php 
                $activeNotices = array_reduce($notices, function($carry, $notice) {
                    return $carry + ($notice['is_active'] ? 1 : 0);
                }, 0);
                ?>
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3><?php echo $activeNotices; ?></h3>
                                <h6>Active Notices</h6>
                            </div>
                            <i class="fas fa-eye fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <?php 
                $urgentNotices = array_reduce($notices, function($carry, $notice) {
                    return $carry + ($notice['priority'] === 'urgent' ? 1 : 0);
                }, 0);
                ?>
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3><?php echo $urgentNotices; ?></h3>
                                <h6>Urgent Notices</h6>
                            </div>
                            <i class="fas fa-exclamation-triangle fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <?php 
                $batchNotices = array_reduce($notices, function($carry, $notice) {
                    return $carry + ($notice['batch_id'] ? 1 : 0);
                }, 0);
                ?>
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3><?php echo $batchNotices; ?></h3>
                                <h6>Batch Specific</h6>
                            </div>
                            <i class="fas fa-calendar-alt fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Notices List -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">All Notices</h5>
            </div>
            <div class="card-body">
                <?php if (empty($notices)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No notices posted yet.
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($notices as $notice): ?>
                        <div class="col-md-6 mb-3">
                            <div class="card notice-card priority-<?php echo $notice['priority']; ?>">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-0"><?php echo htmlspecialchars($notice['title']); ?></h6>
                                        <small class="text-muted">
                                            Posted: <?php echo date('M d, Y h:i A', strtotime($notice['created_at'])); ?>
                                        </small>
                                    </div>
                                    <div>
                                        <span class="badge bg-<?php 
                                            switch($notice['priority']) {
                                                case 'urgent': echo 'danger'; break;
                                                case 'high': echo 'warning'; break;
                                                case 'medium': echo 'info'; break;
                                                default: echo 'success';
                                            }
                                        ?>">
                                            <?php echo ucfirst($notice['priority']); ?>
                                        </span>
                                        <span class="badge bg-<?php echo $notice['is_active'] ? 'success' : 'secondary'; ?>">
                                            <?php echo $notice['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <p class="card-text"><?php echo nl2br(htmlspecialchars(substr($notice['content'], 0, 200))); ?>...</p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <small class="text-muted">
                                                <i class="fas fa-users"></i> 
                                                <?php 
                                                switch($notice['user_type']) {
                                                    case 'all': echo 'All Users'; break;
                                                    case 'teacher': echo 'Teachers Only'; break;
                                                    default: echo ucfirst($notice['user_type']); break;
                                                }
                                                ?>
                                                <?php if ($notice['batch_name']): ?>
                                                    | <i class="fas fa-calendar"></i> <?php echo htmlspecialchars($notice['batch_name']); ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-info" data-bs-toggle="modal" 
                                                    data-bs-target="#viewNoticeModal"
                                                    data-notice-id="<?php echo $notice['id']; ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <a href="notices.php?action=edit&id=<?php echo $notice['id']; ?>" 
                                               class="btn btn-outline-warning">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button class="btn btn-outline-danger" data-bs-toggle="modal" 
                                                    data-bs-target="#deleteNoticeModal"
                                                    data-notice-id="<?php echo $notice['id']; ?>"
                                                    data-notice-title="<?php echo htmlspecialchars($notice['title']); ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Create Notice Modal -->
    <div class="modal fade" id="createNoticeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">Post New Notice</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Title *</label>
                            <input type="text" class="form-control" name="title" required maxlength="200">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Content *</label>
                            <textarea class="form-control" name="content" rows="6" required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Priority</label>
                                <select class="form-control" name="priority" required>
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Target Audience</label>
                                <select class="form-control" name="user_type" required>
                                    <option value="all">All Users</option>
                                    <option value="admin">Admins Only</option>
                                    <option value="teacher">Teachers Only</option>
                                    <option value="student">Students Only</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Specific Batch (Optional)</label>
                                <select class="form-control" name="batch_id">
                                    <option value="">All Batches</option>
                                    <?php foreach ($batches as $batch): ?>
                                    <option value="<?php echo $batch['id']; ?>">
                                        <?php echo htmlspecialchars($batch['batch_name']); ?> - <?php echo $batch['batch_year']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
                                    <label class="form-check-label" for="is_active">
                                        Active (Visible to users)
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_notice" class="btn btn-primary">Post Notice</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- View Notice Modal -->
    <div class="modal fade" id="viewNoticeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Notice Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="noticeDetails">
                    <!-- Will be loaded via AJAX -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Delete Notice Modal -->
    <div class="modal fade" id="deleteNoticeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">Delete Notice</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="notice_id" id="deleteNoticeId">
                        <p>Are you sure you want to delete notice: <strong id="deleteNoticeTitle"></strong>?</p>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            This action cannot be undone.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_notice" class="btn btn-danger">Delete Notice</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // View Notice modal
            $('#viewNoticeModal').on('show.bs.modal', function(event) {
                var button = $(event.relatedTarget);
                var noticeId = button.data('notice-id');
                
                var modal = $(this);
                
                // Load notice details via AJAX
                $.ajax({
                    url: 'ajax_get_notice_details.php',
                    method: 'GET',
                    data: { notice_id: noticeId },
                    success: function(response) {
                        $('#noticeDetails').html(response);
                    },
                    error: function() {
                        $('#noticeDetails').html(
                            '<div class="alert alert-danger">Failed to load notice details.</div>'
                        );
                    }
                });
            });
            
            // Delete Notice modal
            $('#deleteNoticeModal').on('show.bs.modal', function(event) {
                var button = $(event.relatedTarget);
                var noticeId = button.data('notice-id');
                var noticeTitle = button.data('notice-title');
                
                var modal = $(this);
                modal.find('#deleteNoticeId').val(noticeId);
                modal.find('#deleteNoticeTitle').text(noticeTitle);
            });
        });
    </script>
</body>
</html>