<!-- admin/batches.php -->
<?php
require_once '../includes/config/constants.php';
require_once '../includes/classes/Database.php';
require_once '../includes/classes/Session.php';
require_once '../includes/classes/Auth.php';
require_once '../includes/classes/BatchManager.php';

Session::init();
$auth = new Auth();
$auth->requireRole(['admin']);

$user = $auth->getUser();
$batchManager = new BatchManager();

// Get batches for the admin's department
$batches = $batchManager->getBatchesByDepartment($user['department_id'], true);

// Handle actions
$action = $_GET['action'] ?? '';
$batchId = $_GET['id'] ?? '';
$message = '';
$error = '';

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_batch'])) {
        $data = [
            'batch_name' => trim($_POST['batch_name']),
            'batch_year' => $_POST['batch_year'],
            'department_id' => $user['department_id'],
            'title_deadline' => $_POST['title_deadline'] ?? null,
            'proposal_deadline' => $_POST['proposal_deadline'] ?? null,
            'final_report_deadline' => $_POST['final_report_deadline'] ?? null,
            'defense_deadline' => $_POST['defense_deadline'] ?? null,
            'created_by' => $user['user_id']
        ];
        
        $result = $batchManager->createBatch($data);
        if ($result['success']) {
            $message = $result['message'];
            // Refresh page
            header('Location: batches.php?success=' . urlencode($message));
            exit();
        } else {
            $error = $result['message'];
        }
    }
    elseif (isset($_POST['update_batch'])) {
        $data = [
            'batch_name' => trim($_POST['batch_name']),
            'batch_year' => $_POST['batch_year'],
            'title_deadline' => $_POST['title_deadline'] ?? null,
            'proposal_deadline' => $_POST['proposal_deadline'] ?? null,
            'final_report_deadline' => $_POST['final_report_deadline'] ?? null,
            'defense_deadline' => $_POST['defense_deadline'] ?? null
        ];
        
        $result = $batchManager->updateBatch($_POST['batch_id'], $data);
        if ($result['success']) {
            $message = $result['message'];
            header('Location: batches.php?success=' . urlencode($message));
            exit();
        } else {
            $error = $result['message'];
        }
    }
    elseif (isset($_POST['delete_batch'])) {
        $result = $batchManager->deleteBatch($_POST['batch_id']);
        if ($result['success']) {
            $message = $result['message'];
            header('Location: batches.php?success=' . urlencode($message));
            exit();
        } else {
            $error = $result['message'];
        }
    }
}

// Get batch for editing
$editBatch = null;
if ($action === 'edit' && $batchId) {
    $editBatch = $batchManager->getBatchById($batchId);
    if (!$editBatch) {
        $error = 'Batch not found';
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
    <title>Batch Management - FYPMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
        }
        .sidebar {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            min-height: 100vh;
            position: fixed;
            width: 250px;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            border-radius: 5px;
            margin: 5px 0;
        }
        .nav-link:hover, .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        .deadline-badge {
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 11px;
            margin: 2px;
        }
        .deadline-overdue { background: #dc3545; color: white; }
        .deadline-urgent { background: #ffc107; color: black; }
        .deadline-upcoming { background: #28a745; color: white; }
        .deadline-future { background: #6c757d; color: white; }
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
                    <a class="nav-link" href="teachers.php">
                        <i class="fas fa-chalkboard-teacher me-2"></i> Teachers
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="students.php">
                        <i class="fas fa-user-graduate me-2"></i> Students
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="batches.php">
                        <i class="fas fa-calendar-alt me-2"></i> Batches
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="notices.php">
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
                <h3 class="mb-0">Batch Management</h3>
                <div>
                    <a href="dashboard.php" class="btn btn-outline-primary btn-sm me-2">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createBatchModal">
                        <i class="fas fa-plus-circle"></i> Create Batch
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
        
        <!-- Batch Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3><?php echo count($batches); ?></h3>
                                <h6>Total Batches</h6>
                            </div>
                            <i class="fas fa-calendar-alt fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <?php 
                $totalStudents = array_reduce($batches, function($carry, $batch) {
                    return $carry + ($batch['student_count'] ?? 0);
                }, 0);
                ?>
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3><?php echo $totalStudents; ?></h3>
                                <h6>Total Students</h6>
                            </div>
                            <i class="fas fa-user-graduate fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <?php 
                $currentYear = date('Y');
                $currentYearBatches = array_reduce($batches, function($carry, $batch) use ($currentYear) {
                    return $carry + ($batch['batch_year'] == $currentYear ? 1 : 0);
                }, 0);
                ?>
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3><?php echo $currentYearBatches; ?></h3>
                                <h6><?php echo $currentYear; ?> Batches</h6>
                            </div>
                            <i class="fas fa-calendar-check fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <?php 
                $activeBatches = array_reduce($batches, function($carry, $batch) {
                    // Consider batches with students as active
                    return $carry + (($batch['student_count'] ?? 0) > 0 ? 1 : 0);
                }, 0);
                ?>
                <div class="card bg-warning text-dark">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3><?php echo $activeBatches; ?></h3>
                                <h6>Active Batches</h6>
                            </div>
                            <i class="fas fa-running fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Batches Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Batches List</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="batchesTable" class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Batch Name</th>
                                <th>Year</th>
                                <th>Students</th>
                                <th>Deadlines</th>
                                <th>Created By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($batches as $index => $batch): 
                                // Determine deadline status
                                $today = new DateTime();
                                $deadlines = [];
                                
                                if ($batch['title_deadline']) {
                                    $deadlineDate = new DateTime($batch['title_deadline']);
                                    $diff = $today->diff($deadlineDate)->days;
                                    $status = $deadlineDate < $today ? 'overdue' : ($diff <= 7 ? 'urgent' : ($diff <= 30 ? 'upcoming' : 'future'));
                                    $deadlines[] = ['type' => 'Title', 'date' => $batch['title_deadline'], 'status' => $status];
                                }
                                
                                if ($batch['proposal_deadline']) {
                                    $deadlineDate = new DateTime($batch['proposal_deadline']);
                                    $diff = $today->diff($deadlineDate)->days;
                                    $status = $deadlineDate < $today ? 'overdue' : ($diff <= 7 ? 'urgent' : ($diff <= 30 ? 'upcoming' : 'future'));
                                    $deadlines[] = ['type' => 'Proposal', 'date' => $batch['proposal_deadline'], 'status' => $status];
                                }
                                
                                if ($batch['final_report_deadline']) {
                                    $deadlineDate = new DateTime($batch['final_report_deadline']);
                                    $diff = $today->diff($deadlineDate)->days;
                                    $status = $deadlineDate < $today ? 'overdue' : ($diff <= 7 ? 'urgent' : ($diff <= 30 ? 'upcoming' : 'future'));
                                    $deadlines[] = ['type' => 'Final', 'date' => $batch['final_report_deadline'], 'status' => $status];
                                }
                            ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($batch['batch_name']); ?></strong>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $batch['batch_year']; ?></span>
                                </td>
                                <td>
                                    <a href="batch_students.php?batch_id=<?php echo $batch['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-users"></i> <?php echo $batch['student_count'] ?? 0; ?> Students
                                    </a>
                                </td>
                                <td>
                                    <?php foreach ($deadlines as $deadline): ?>
                                        <span class="deadline-badge deadline-<?php echo $deadline['status']; ?>" 
                                              title="<?php echo $deadline['type']; ?>: <?php echo date('M d, Y', strtotime($deadline['date'])); ?>">
                                            <?php echo substr($deadline['type'], 0, 1); ?>
                                        </span>
                                    <?php endforeach; ?>
                                    <?php if (empty($deadlines)): ?>
                                        <span class="text-muted">No deadlines set</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small><?php echo htmlspecialchars($batch['created_by_name'] ?? 'System'); ?></small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="batch_students.php?batch_id=<?php echo $batch['id']; ?>" 
                                           class="btn btn-outline-primary" title="Manage Students">
                                            <i class="fas fa-users"></i>
                                        </a>
                                        <a href="batches.php?action=edit&id=<?php echo $batch['id']; ?>" 
                                           class="btn btn-outline-info" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-danger" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deleteBatchModal"
                                                data-batch-id="<?php echo $batch['id']; ?>"
                                                data-batch-name="<?php echo htmlspecialchars($batch['batch_name']); ?>"
                                                title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Create Batch Modal -->
    <div class="modal fade" id="createBatchModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="" id="createBatchForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Create New Batch</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Batch Name *</label>
                                <input type="text" class="form-control" name="batch_name" 
                                       placeholder="e.g., 2026 Regular, 2026 Extension" required>
                                <small class="text-muted">e.g., Regular, Extension, Summer</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Academic Year *</label>
                                <select class="form-control" name="batch_year" required>
                                    <option value="">-- Select Year --</option>
                                    <?php 
                                    $currentYear = date('Y');
                                    for ($year = $currentYear - 2; $year <= $currentYear + 2; $year++): ?>
                                        <option value="<?php echo $year; ?>" <?php echo $year == $currentYear ? 'selected' : ''; ?>>
                                            <?php echo $year; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        
                        <h6 class="mt-4 mb-3">Deadlines (Optional)</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Title Submission Deadline</label>
                                <input type="date" class="form-control" name="title_deadline" 
                                       min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Proposal Submission Deadline</label>
                                <input type="date" class="form-control" name="proposal_deadline" 
                                       min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Final Report Deadline</label>
                                <input type="date" class="form-control" name="final_report_deadline" 
                                       min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Defense Deadline</label>
                                <input type="date" class="form-control" name="defense_deadline" 
                                       min="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_batch" class="btn btn-primary">Create Batch</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Batch Modal -->
    <?php if ($action === 'edit' && $editBatch): ?>
    <div class="modal fade show" id="editBatchModal" tabindex="-1" style="display: block; padding-right: 17px;">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Batch</h5>
                        <a href="batches.php" class="btn-close"></a>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="batch_id" value="<?php echo $editBatch['id']; ?>">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Batch Name *</label>
                                <input type="text" class="form-control" name="batch_name" 
                                       value="<?php echo htmlspecialchars($editBatch['batch_name']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Academic Year *</label>
                                <select class="form-control" name="batch_year" required>
                                    <?php 
                                    $currentYear = date('Y');
                                    for ($year = $currentYear - 2; $year <= $currentYear + 2; $year++): ?>
                                        <option value="<?php echo $year; ?>" 
                                            <?php echo $year == $editBatch['batch_year'] ? 'selected' : ''; ?>>
                                            <?php echo $year; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        
                        <h6 class="mt-4 mb-3">Deadlines</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Title Submission Deadline</label>
                                <input type="date" class="form-control" name="title_deadline" 
                                       value="<?php echo $editBatch['title_deadline'] ? date('Y-m-d', strtotime($editBatch['title_deadline'])) : ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Proposal Submission Deadline</label>
                                <input type="date" class="form-control" name="proposal_deadline" 
                                       value="<?php echo $editBatch['proposal_deadline'] ? date('Y-m-d', strtotime($editBatch['proposal_deadline'])) : ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Final Report Deadline</label>
                                <input type="date" class="form-control" name="final_report_deadline" 
                                       value="<?php echo $editBatch['final_report_deadline'] ? date('Y-m-d', strtotime($editBatch['final_report_deadline'])) : ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Defense Deadline</label>
                                <input type="date" class="form-control" name="defense_deadline" 
                                       value="<?php echo $editBatch['defense_deadline'] ? date('Y-m-d', strtotime($editBatch['defense_deadline'])) : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="mt-4 p-3 bg-light rounded">
                            <h6>Batch Information</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Department:</strong> <?php echo htmlspecialchars($editBatch['dept_name']); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Created By:</strong> <?php echo htmlspecialchars($editBatch['created_by_name']); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="batches.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" name="update_batch" class="btn btn-primary">Update Batch</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal-backdrop fade show"></div>
    <?php endif; ?>
    
    <!-- Delete Batch Modal -->
    <div class="modal fade" id="deleteBatchModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">Delete Batch</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="batch_id" id="deleteBatchId">
                        <p>Are you sure you want to delete batch: <strong id="deleteBatchName"></strong>?</p>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Warning:</strong> This action cannot be undone. Make sure the batch has no students assigned.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_batch" class="btn btn-danger">Delete Batch</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            $('#batchesTable').DataTable({
                pageLength: 10,
                responsive: true,
                order: [[2, 'desc']] // Sort by year descending
            });
            
            // Delete modal setup
            $('#deleteBatchModal').on('show.bs.modal', function(event) {
                var button = $(event.relatedTarget);
                var batchId = button.data('batch-id');
                var batchName = button.data('batch-name');
                
                var modal = $(this);
                modal.find('#deleteBatchId').val(batchId);
                modal.find('#deleteBatchName').text(batchName);
            });
            
            // Show edit modal if edit action
            <?php if ($action === 'edit' && $editBatch): ?>
            $(document).ready(function() {
                $('#editBatchModal').modal('show');
            });
            <?php endif; ?>
            
            // Set minimum dates for deadlines
            var today = new Date().toISOString().split('T')[0];
            $('input[type="date"]').attr('min', today);
        });
    </script>
</body>
</html>