<!-- admin/batch_students.php -->
<?php
require_once '../includes/config/constants.php';
require_once '../includes/classes/Database.php';
require_once '../includes/classes/Session.php';
require_once '../includes/classes/Auth.php';
require_once '../includes/classes/BatchManager.php';
require_once '../includes/classes/UserManager.php';

Session::init();
$auth = new Auth();
$auth->requireRole(['admin']);

$user = $auth->getUser();
$batchManager = new BatchManager();
$userManager = new UserManager();

// Get batch ID from query string
$batchId = $_GET['batch_id'] ?? 0;
$batch = $batchManager->getBatchById($batchId);

if (!$batch) {
    header('Location: batches.php?error=Batch not found');
    exit();
}

// Get students in this batch and unassigned students
$batchStudents = $batchManager->getStudentsInBatch($batchId);
$unassignedStudents = $batchManager->getUnassignedStudents($user['department_id']);

// Handle actions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['assign_student'])) {
        $studentId = $_POST['student_id'];
        $result = $batchManager->assignStudentToBatch($studentId, $batchId);
        
        if ($result['success']) {
            $message = $result['message'];
            // Refresh page
            header('Location: batch_students.php?batch_id=' . $batchId . '&success=' . urlencode($message));
            exit();
        } else {
            $error = $result['message'];
        }
    }
    elseif (isset($_POST['bulk_assign'])) {
        $studentIds = $_POST['student_ids'] ?? [];
        if (!empty($studentIds)) {
            $result = $batchManager->bulkAssignStudentsToBatch($studentIds, $batchId);
            if ($result['success']) {
                $message = $result['message'];
                header('Location: batch_students.php?batch_id=' . $batchId . '&success=' . urlencode($message));
                exit();
            } else {
                $error = $result['message'];
            }
        } else {
            $error = 'Please select at least one student';
        }
    }
    elseif (isset($_POST['remove_student'])) {
        $studentId = $_POST['remove_student_id'];
        $result = $batchManager->assignStudentToBatch($studentId, null); // Set batch_id to null
        
        if ($result['success']) {
            $message = 'Student removed from batch';
            header('Location: batch_students.php?batch_id=' . $batchId . '&success=' . urlencode($message));
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
    <title>Batch Students - FYPMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
        .batch-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .student-card {
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        .student-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
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
                    <a class="nav-link" href="batches.php">
                        <i class="fas fa-calendar-alt me-2"></i> Batches
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="batch_students.php?batch_id=<?php echo $batchId; ?>">
                        <i class="fas fa-users me-2"></i> Batch Students
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="students.php">
                        <i class="fas fa-user-graduate me-2"></i> All Students
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
                    <a href="batches.php" class="btn btn-outline-primary btn-sm me-2">
                        <i class="fas fa-arrow-left"></i> Back to Batches
                    </a>
                    <h3 class="mb-0 d-inline">Batch Students Management</h3>
                </div>
                <div>
                    <button class="btn btn-primary btn-sm me-2" data-bs-toggle="modal" data-bs-target="#assignStudentModal">
                        <i class="fas fa-user-plus"></i> Assign Student
                    </button>
                    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#bulkAssignModal">
                        <i class="fas fa-users"></i> Bulk Assign
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
        
        <!-- Batch Info -->
        <div class="batch-header">
            <h2><?php echo htmlspecialchars($batch['batch_name']); ?> - <?php echo $batch['batch_year']; ?></h2>
            <div class="row mt-3">
                <div class="col-md-4">
                    <p class="mb-1"><i class="fas fa-building"></i> <strong>Department:</strong> <?php echo htmlspecialchars($batch['dept_name']); ?></p>
                </div>
                <div class="col-md-4">
                    <p class="mb-1"><i class="fas fa-users"></i> <strong>Students:</strong> <?php echo count($batchStudents); ?></p>
                </div>
                <div class="col-md-4">
                    <p class="mb-1"><i class="fas fa-user-tie"></i> <strong>Created By:</strong> <?php echo htmlspecialchars($batch['created_by_name']); ?></p>
                </div>
            </div>
            
            <!-- Deadlines -->
            <?php if ($batch['title_deadline'] || $batch['proposal_deadline'] || $batch['final_report_deadline'] || $batch['defense_deadline']): ?>
            <div class="mt-3">
                <h6><i class="fas fa-calendar-alt"></i> Deadlines:</h6>
                <div class="row">
                    <?php if ($batch['title_deadline']): ?>
                    <div class="col-md-3">
                        <small>Title: <?php echo date('M d, Y', strtotime($batch['title_deadline'])); ?></small>
                    </div>
                    <?php endif; ?>
                    <?php if ($batch['proposal_deadline']): ?>
                    <div class="col-md-3">
                        <small>Proposal: <?php echo date('M d, Y', strtotime($batch['proposal_deadline'])); ?></small>
                    </div>
                    <?php endif; ?>
                    <?php if ($batch['final_report_deadline']): ?>
                    <div class="col-md-3">
                        <small>Final: <?php echo date('M d, Y', strtotime($batch['final_report_deadline'])); ?></small>
                    </div>
                    <?php endif; ?>
                    <?php if ($batch['defense_deadline']): ?>
                    <div class="col-md-3">
                        <small>Defense: <?php echo date('M d, Y', strtotime($batch['defense_deadline'])); ?></small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Batch Students -->
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-user-graduate"></i> Students in this Batch
                            <span class="badge bg-primary"><?php echo count($batchStudents); ?></span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($batchStudents)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No students assigned to this batch yet.
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($batchStudents as $student): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="student-card">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($student['full_name']); ?></h6>
                                                <p class="mb-1 small">
                                                    <i class="fas fa-id-card"></i> <?php echo htmlspecialchars($student['username']); ?><br>
                                                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($student['email']); ?>
                                                </p>
                                                <span class="badge bg-<?php echo $student['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                    <?php echo ucfirst($student['status']); ?>
                                                </span>
                                            </div>
                                            <div>
                                                <form method="POST" action="" style="display: inline;">
                                                    <input type="hidden" name="remove_student_id" value="<?php echo $student['id']; ?>">
                                                    <button type="submit" name="remove_student" class="btn btn-sm btn-outline-danger" 
                                                            onclick="return confirm('Remove student from batch?')">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </form>
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
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-user-clock"></i> Unassigned Students
                            <span class="badge bg-warning"><?php echo count($unassignedStudents); ?></span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($unassignedStudents)): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> All students are assigned to batches.
                            </div>
                        <?php else: ?>
                            <div class="list-group" style="max-height: 400px; overflow-y: auto;">
                                <?php foreach ($unassignedStudents as $student): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($student['full_name']); ?></h6>
                                            <small><?php echo htmlspecialchars($student['username']); ?></small>
                                        </div>
                                        <form method="POST" action="" class="mb-0">
                                            <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                            <button type="submit" name="assign_student" class="btn btn-sm btn-primary">
                                                <i class="fas fa-plus"></i> Add
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Quick Stats -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">Quick Stats</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <span>Batch Students</span>
                                <strong><?php echo count($batchStudents); ?></strong>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <?php 
                                $totalStudents = count($batchStudents) + count($unassignedStudents);
                                $percentage = $totalStudents > 0 ? (count($batchStudents) / $totalStudents * 100) : 0;
                                ?>
                                <div class="progress-bar bg-success" style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <span>Unassigned Students</span>
                                <strong><?php echo count($unassignedStudents); ?></strong>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <span>Total in Department</span>
                                <strong><?php echo $totalStudents; ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Assign Student Modal -->
    <div class="modal fade" id="assignStudentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">Assign Student to Batch</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Select Student</label>
                            <select class="form-control" name="student_id" required>
                                <option value="">-- Select a Student --</option>
                                <?php foreach ($unassignedStudents as $student): ?>
                                <option value="<?php echo $student['id']; ?>">
                                    <?php echo htmlspecialchars($student['full_name']); ?> 
                                    (<?php echo htmlspecialchars($student['username']); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($unassignedStudents)): ?>
                            <div class="alert alert-info mt-2">
                                <i class="fas fa-info-circle"></i> No unassigned students available.
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="assign_student" class="btn btn-primary" 
                                <?php echo empty($unassignedStudents) ? 'disabled' : ''; ?>>
                            Assign to Batch
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Bulk Assign Modal -->
    <div class="modal fade" id="bulkAssignModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">Bulk Assign Students</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <?php if (empty($unassignedStudents)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No unassigned students available for bulk assignment.
                            </div>
                        <?php else: ?>
                            <div class="mb-3">
                                <label class="form-label">Select Students to Assign</label>
                                <div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; border-radius: 5px; padding: 10px;">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="selectAll">
                                        <label class="form-check-label" for="selectAll">
                                            <strong>Select All</strong>
                                        </label>
                                    </div>
                                    <hr>
                                    <?php foreach ($unassignedStudents as $student): ?>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input student-checkbox" type="checkbox" 
                                               name="student_ids[]" value="<?php echo $student['id']; ?>" id="student_<?php echo $student['id']; ?>">
                                        <label class="form-check-label" for="student_<?php echo $student['id']; ?>">
                                            <?php echo htmlspecialchars($student['full_name']); ?> 
                                            (<?php echo htmlspecialchars($student['username']); ?>)
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <small class="text-muted">Select multiple students to assign them all to this batch</small>
                            </div>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> Selected students will be assigned to 
                                <strong><?php echo htmlspecialchars($batch['batch_name']); ?> - <?php echo $batch['batch_year']; ?></strong>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="bulk_assign" class="btn btn-success" 
                                <?php echo empty($unassignedStudents) ? 'disabled' : ''; ?>>
                            <i class="fas fa-users"></i> Assign Selected
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Select all checkboxes
        document.getElementById('selectAll').addEventListener('change', function() {
            var checkboxes = document.querySelectorAll('.student-checkbox');
            checkboxes.forEach(function(checkbox) {
                checkbox.checked = this.checked;
            }.bind(this));
        });
    </script>
</body>
</html>