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

// Get all notices for department
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
            'batch_id' => !empty($_POST['batch_id']) ? $_POST['batch_id'] : null,
            'created_by' => $user['user_id'],
            'user_type' => $_POST['user_type'] ?? 'all',
            'priority' => $_POST['priority'] ?? 'medium',
            'is_active' => isset($_POST['is_active']) ? 1 : 1
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
            'batch_id' => !empty($_POST['batch_id']) ? $_POST['batch_id'] : null,
            'user_type' => $_POST['user_type'] ?? 'all',
            'priority' => $_POST['priority'] ?? 'medium',
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
    <title>Notice Management - FYPMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <style>
        .notice-card {
            border-left: 4px solid;
            margin-bottom: 15px;
        }
        .priority-urgent { border-left-color: #dc3545; }
        .priority-high { border-left-color: #fd7e14; }
        .priority-medium { border-left-color: #ffc107; }
        .priority-low { border-left-color: #28a745; }
        .notice-badge {
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 11px;
            margin: 2px;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
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
                    <a class="nav-link" href="assign_supervisor.php">
                        <i class="fas fa-user-tie me-2"></i> Assign Supervisor
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="notices.php">
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
                <h3 class="mb-0">Notice Management</h3>
                <div>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createNoticeModal">
                        <i class="fas fa-plus-circle"></i> Create Notice
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
        
        <!-- Notices List -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">All Notices (<?php echo count($notices); ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (empty($notices)): ?>
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle fa-2x mb-3"></i>
                        <h4>No Notices Yet</h4>
                        <p>Create your first notice to share information with students and teachers.</p>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createNoticeModal">
                            <i class="fas fa-plus-circle"></i> Create First Notice
                        </button>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($notices as $notice): 
                            $priorityClass = 'priority-' . $notice['priority'];
                            $isActive = $notice['is_active'] ? 'Active' : 'Inactive';
                            $statusClass = $notice['is_active'] ? 'success' : 'secondary';
                        ?>
                        <div class="col-md-6 mb-3">
                            <div class="card notice-card <?php echo $priorityClass; ?>">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <h5 class="card-title"><?php echo htmlspecialchars($notice['title']); ?></h5>
                                            <div class="mb-2">
                                                <span class="badge bg-<?php echo $statusClass; ?>">
                                                    <?php echo $isActive; ?>
                                                </span>
                                                <span class="badge bg-<?php echo $notice['priority'] === 'urgent' ? 'danger' : ($notice['priority'] === 'high' ? 'warning' : ($notice['priority'] === 'medium' ? 'info' : 'success')); ?>">
                                                    <?php echo ucfirst($notice['priority']); ?>
                                                </span>
                                                <?php if ($notice['batch_name']): ?>
                                                    <span class="badge bg-primary">
                                                        <?php echo htmlspecialchars($notice['batch_name']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-info" data-bs-toggle="modal" 
                                                    data-bs-target="#viewNoticeModal"
                                                    data-title="<?php echo htmlspecialchars($notice['title']); ?>"
                                                    data-content="<?php echo htmlspecialchars($notice['content']); ?>"
                                                    data-priority="<?php echo $notice['priority']; ?>"
                                                    data-batch="<?php echo $notice['batch_name'] ? htmlspecialchars($notice['batch_name']) : 'All Batches'; ?>"
                                                    data-created="<?php echo date('M d, Y h:i A', strtotime($notice['created_at'])); ?>"
                                                    data-created-by="<?php echo htmlspecialchars($notice['created_by_name']); ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <a href="notices.php?action=edit&id=<?php echo $notice['id']; ?>" 
                                               class="btn btn-outline-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-danger" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#deleteNoticeModal"
                                                    data-notice-id="<?php echo $notice['id']; ?>"
                                                    data-notice-title="<?php echo htmlspecialchars($notice['title']); ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <p class="card-text">
                                        <?php echo substr(htmlspecialchars($notice['content']), 0, 150); ?>
                                        <?php if (strlen($notice['content']) > 150): ?>...<?php endif; ?>
                                    </p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($notice['created_by_name']); ?>
                                            | <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($notice['created_at'])); ?>
                                        </small>
                                        <small>
                                            <i class="fas fa-users"></i> 
                                            <?php echo $notice['user_type'] === 'all' ? 'All Users' : ucfirst($notice['user_type']); ?>
                                        </small>
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
                        <h5 class="modal-title">Create New Notice</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Title *</label>
                            <input type="text" class="form-control" name="title" 
                                   placeholder="Enter notice title" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Content *</label>
                            <textarea class="form-control" name="content" rows="6" 
                                      placeholder="Enter notice content..." required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Priority</label>
                                <select class="form-control" name="priority">
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Target Audience</label>
                                <select class="form-control" name="user_type">
                                    <option value="all" selected>All Users</option>
                                    <option value="student">Students Only</option>
                                    <option value="teacher">Teachers Only</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Target Batch (Optional)</label>
                                <select class="form-control" name="batch_id">
                                    <option value="">All Batches</option>
                                    <?php foreach ($batches as $batch): ?>
                                    <option value="<?php echo $batch['id']; ?>">
                                        <?php echo htmlspecialchars($batch['batch_name']); ?> - <?php echo $batch['batch_year']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Leave empty for all batches</small>
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
                        <button type="submit" name="create_notice" class="btn btn-primary">Create Notice</button>
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
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><strong>Title</strong></label>
                        <p id="modalNoticeTitle" class="fs-5"></p>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label"><strong>Priority</strong></label>
                            <p id="modalNoticePriority"></p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><strong>Target Batch</strong></label>
                            <p id="modalNoticeBatch"></p>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><strong>Content</strong></label>
                        <div id="modalNoticeContent" class="p-3 bg-light rounded"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label"><strong>Created By</strong></label>
                            <p id="modalNoticeCreatedBy"></p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><strong>Created On</strong></label>
                            <p id="modalNoticeCreated"></p>
                        </div>
                    </div>
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
                            <strong>Warning:</strong> This action cannot be undone.
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
    
    <!-- Edit Notice Modal -->
    <?php if ($action === 'edit' && $editNotice): ?>
    <div class="modal fade show" id="editNoticeModal" tabindex="-1" style="display: block; padding-right: 17px;">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Notice</h5>
                        <a href="notices.php" class="btn-close"></a>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="notice_id" value="<?php echo $editNotice['id']; ?>">
                        <div class="mb-3">
                            <label class="form-label">Title *</label>
                            <input type="text" class="form-control" name="title" 
                                   value="<?php echo htmlspecialchars($editNotice['title']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Content *</label>
                            <textarea class="form-control" name="content" rows="6" required><?php echo htmlspecialchars($editNotice['content']); ?></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Priority</label>
                                <select class="form-control" name="priority">
                                    <option value="low" <?php echo $editNotice['priority'] === 'low' ? 'selected' : ''; ?>>Low</option>
                                    <option value="medium" <?php echo $editNotice['priority'] === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                    <option value="high" <?php echo $editNotice['priority'] === 'high' ? 'selected' : ''; ?>>High</option>
                                    <option value="urgent" <?php echo $editNotice['priority'] === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Target Audience</label>
                                <select class="form-control" name="user_type">
                                    <option value="all" <?php echo $editNotice['user_type'] === 'all' ? 'selected' : ''; ?>>All Users</option>
                                    <option value="student" <?php echo $editNotice['user_type'] === 'student' ? 'selected' : ''; ?>>Students Only</option>
                                    <option value="teacher" <?php echo $editNotice['user_type'] === 'teacher' ? 'selected' : ''; ?>>Teachers Only</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Target Batch (Optional)</label>
                                <select class="form-control" name="batch_id">
                                    <option value="">All Batches</option>
                                    <?php foreach ($batches as $batch): ?>
                                    <option value="<?php echo $batch['id']; ?>" 
                                        <?php echo $editNotice['batch_id'] == $batch['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($batch['batch_name']); ?> - <?php echo $batch['batch_year']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="is_active_edit" 
                                           <?php echo $editNotice['is_active'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_active_edit">
                                        Active (Visible to users)
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Created:</strong> <?php echo date('M d, Y h:i A', strtotime($editNotice['created_at'])); ?> 
                            by <?php echo htmlspecialchars($editNotice['created_by_name']); ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="notices.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" name="update_notice" class="btn btn-primary">Update Notice</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal-backdrop fade show"></div>
    <?php endif; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // View Notice Modal
            $('#viewNoticeModal').on('show.bs.modal', function(event) {
                var button = $(event.relatedTarget);
                $('#modalNoticeTitle').text(button.data('title'));
                $('#modalNoticeContent').html(button.data('content').replace(/\n/g, '<br>'));
                $('#modalNoticePriority').text(button.data('priority').charAt(0).toUpperCase() + button.data('priority').slice(1));
                $('#modalNoticeBatch').text(button.data('batch'));
                $('#modalNoticeCreated').text(button.data('created'));
                $('#modalNoticeCreatedBy').text(button.data('created-by'));
            });
            
            // Delete Notice Modal
            $('#deleteNoticeModal').on('show.bs.modal', function(event) {
                var button = $(event.relatedTarget);
                var modal = $(this);
                modal.find('#deleteNoticeId').val(button.data('notice-id'));
                modal.find('#deleteNoticeTitle').text(button.data('notice-title'));
            });
            
            // Show edit modal if edit action
            <?php if ($action === 'edit' && $editNotice): ?>
            $(document).ready(function() {
                $('#editNoticeModal').modal('show');
            });
            <?php endif; ?>
        });
    </script>
</body>
</html>