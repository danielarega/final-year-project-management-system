<!-- admin/ajax_get_notice_details.php -->
<?php
require_once '../includes/config/constants.php';
require_once '../includes/classes/Database.php';
require_once '../includes/classes/Session.php';
require_once '../includes/classes/Auth.php';
require_once '../includes/classes/NoticeManager.php';

Session::init();
$auth = new Auth();
$auth->requireRole(['admin']);

$user = $auth->getUser();
$noticeManager = new NoticeManager();

$noticeId = $_GET['notice_id'] ?? 0;

if ($noticeId) {
    $notice = $noticeManager->getNoticeById($noticeId);
    $readStats = $noticeManager->getNoticeReadStats($noticeId);
    
    if ($notice) {
        ?>
        <div class="mb-3">
            <h5><?php echo htmlspecialchars($notice['title']); ?></h5>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <span class="badge bg-<?php 
                        switch($notice['priority']) {
                            case 'urgent': echo 'danger'; break;
                            case 'high': echo 'warning'; break;
                            case 'medium': echo 'info'; break;
                            default: echo 'success';
                        }
                    ?> me-2">
                        <?php echo ucfirst($notice['priority']); ?> Priority
                    </span>
                    <span class="badge bg-<?php echo $notice['is_active'] ? 'success' : 'secondary'; ?> me-2">
                        <?php echo $notice['is_active'] ? 'Active' : 'Inactive'; ?>
                    </span>
                    <span class="badge bg-primary me-2">
                        <?php 
                        switch($notice['user_type']) {
                            case 'all': echo 'All Users'; break;
                            case 'teacher': echo 'Teachers Only'; break;
                            default: echo ucfirst($notice['user_type']); break;
                        }
                        ?>
                    </span>
                    <?php if ($notice['batch_name']): ?>
                        <span class="badge bg-info">
                            <i class="fas fa-calendar"></i> <?php echo htmlspecialchars($notice['batch_name']); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card mb-3">
                <div class="card-body">
                    <?php echo nl2br(htmlspecialchars($notice['content'])); ?>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Notice Information</h6>
                        </div>
                        <div class="card-body">
                            <p><strong>Posted By:</strong> <?php echo htmlspecialchars($notice['created_by_name']); ?></p>
                            <p><strong>Posted On:</strong> <?php echo date('F j, Y h:i A', strtotime($notice['created_at'])); ?></p>
                            <?php if ($notice['updated_at'] && $notice['updated_at'] != $notice['created_at']): ?>
                                <p><strong>Last Updated:</strong> <?php echo date('F j, Y h:i A', strtotime($notice['updated_at'])); ?></p>
                            <?php endif; ?>
                            <p><strong>Department:</strong> <?php echo htmlspecialchars($notice['dept_name']); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Read Statistics</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <p><strong>Students Read:</strong> 
                                    <span class="badge bg-primary"><?php echo $readStats['students_read']; ?></span>
                                </p>
                            </div>
                            <div class="mb-3">
                                <p><strong>Teachers Read:</strong> 
                                    <span class="badge bg-info"><?php echo $readStats['teachers_read']; ?></span>
                                </p>
                            </div>
                            <div class="mb-3">
                                <p><strong>Admins Read:</strong> 
                                    <span class="badge bg-success"><?php echo $readStats['admins_read']; ?></span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    } else {
        echo '<div class="alert alert-danger">Notice not found.</div>';
    }
} else {
    echo '<div class="alert alert-danger">Invalid notice ID.</div>';
}
?>