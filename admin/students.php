<!-- admin/students.php -->
<?php
require_once '../includes/config/constants.php';
require_once '../includes/classes/Database.php';
require_once '../includes/classes/Session.php';
require_once '../includes/classes/Auth.php';
require_once '../includes/classes/UserManager.php';
require_once '../includes/classes/BatchManager.php';

Session::init();
$auth = new Auth();
$auth->requireRole(['admin']);

$user = $auth->getUser();
$userManager = new UserManager();
$batchManager = new BatchManager();

// Get all students in the admin's department
$students = $userManager->getAllUsers('student', $user['department_id']);

// Get batches for batch assignment
$batches = $batchManager->getBatchesByDepartment($user['department_id']);

// Handle actions
$action = $_GET['action'] ?? '';
$studentId = $_GET['id'] ?? '';
$message = '';
$error = '';

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_student'])) {
        $data = [
            'username' => trim($_POST['username']),
            'password' => trim($_POST['password']),
            'full_name' => trim($_POST['full_name']),
            'email' => trim($_POST['email']),
            'department_id' => $user['department_id'],
            'batch_id' => $_POST['batch_id'] ?? null,
            'created_by' => $user['user_id']
        ];
        
        $result = $userManager->createStudent($data);
        if ($result['success']) {
            $message = $result['message'];
            header('Location: students.php?success=' . urlencode($message));
            exit();
        } else {
            $error = $result['message'];
        }
    }
    elseif (isset($_POST['update_student'])) {
        $data = [
            'full_name' => trim($_POST['full_name']),
            'email' => trim($_POST['email']),
            'batch_id' => $_POST['batch_id'] ?? null,
            'status' => $_POST['status']
        ];
        
        $result = $userManager->updateUser($_POST['student_id'], 'student', $data);
        if ($result) {
            $message = 'Student updated successfully';
            header('Location: students.php?success=' . urlencode($message));
            exit();
        } else {
            $error = 'Failed to update student';
        }
    }
    elseif (isset($_POST['assign_batch'])) {
        $studentId = $_POST['student_id'];
        $batchId = $_POST['batch_id'];
        
        $result = $batchManager->assignStudentToBatch($studentId, $batchId);
        if ($result['success']) {
            $message = $result['message'];
            header('Location: students.php?success=' . urlencode($message));
            exit();
        } else {
            $error = $result['message'];
        }
    }
}

// Get student for editing
$editStudent = null;
if ($action === 'edit' && $studentId) {
    $editStudent = $userManager->getUserById($studentId, 'student');
    if (!$editStudent) {
        $error = 'Student not found';
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
    <title>Students Management - FYPMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
</head>
<body>
    <li class="nav-item">
    <a class="nav-link" href="supervisor_assignment.php">
        <i class="fas fa-user-tie me-2"></i> Supervisor Assignment
    </a>
</li>
    <!-- Include your sidebar here (similar to batches.php) -->
    <?php include 'dashboard.php'; ?>
    
    <div class="main-content">
        <!-- Header -->
        <nav class="navbar navbar-light bg-light mb-4 rounded">
            <div class="container-fluid">
                <h3 class="mb-0">Students Management</h3>
                <div>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createStudentModal">
                        <i class="fas fa-user-plus"></i> Add Student
                    </button>
                    <button class="btn btn-success btn-sm ms-2" data-bs-toggle="modal" data-bs-target="#importStudentsModal">
                        <i class="fas fa-file-import"></i> Import
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
        
        <!-- Students Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Students List (<?php echo count($students); ?>)</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="studentsTable" class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Student ID</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Batch</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $index => $student): 
                                // Get batch name
                                $batchName = 'Not Assigned';
                                if ($student['batch_id']) {
                                    $batch = $batchManager->getBatchById($student['batch_id']);
                                    $batchName = $batch ? $batch['batch_name'] . ' - ' . $batch['batch_year'] : 'Unknown';
                                }
                            ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($student['username']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($student['email']); ?></td>
                                <td>
                                    <?php if ($student['batch_id']): ?>
                                        <span class="badge bg-info"><?php echo $batchName; ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Not Assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $student['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($student['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo $student['last_login'] ? date('M d, Y', strtotime($student['last_login'])) : 'Never'; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" data-bs-toggle="modal" 
                                                data-bs-target="#assignBatchModal"
                                                data-student-id="<?php echo $student['id']; ?>"
                                                data-student-name="<?php echo htmlspecialchars($student['full_name']); ?>"
                                                title="Assign Batch">
                                            <i class="fas fa-calendar-alt"></i>
                                        </button>
                                        <a href="students.php?action=edit&id=<?php echo $student['id']; ?>" 
                                           class="btn btn-outline-info" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
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
    
    <!-- Create Student Modal -->
    <div class="modal fade" id="createStudentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Student</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Student ID *</label>
                            <input type="text" class="form-control" name="username" 
                                   placeholder="e.g., UGR13610" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password *</label>
                            <input type="password" class="form-control" name="password" required>
                            <small class="text-muted">Default password for student</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Full Name *</label>
                            <input type="text" class="form-control" name="full_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Batch (Optional)</label>
                            <select class="form-control" name="batch_id">
                                <option value="">-- Not Assigned --</option>
                                <?php foreach ($batches as $batch): ?>
                                <option value="<?php echo $batch['id']; ?>">
                                    <?php echo htmlspecialchars($batch['batch_name']); ?> - <?php echo $batch['batch_year']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_student" class="btn btn-primary">Add Student</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Assign Batch Modal -->
    <div class="modal fade" id="assignBatchModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">Assign Student to Batch</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="student_id" id="assignStudentId">
                        <div class="mb-3">
                            <label class="form-label">Student</label>
                            <input type="text" class="form-control" id="assignStudentName" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Select Batch</label>
                            <select class="form-control" name="batch_id" required>
                                <option value="">-- Select Batch --</option>
                                <?php foreach ($batches as $batch): ?>
                                <option value="<?php echo $batch['id']; ?>">
                                    <?php echo htmlspecialchars($batch['batch_name']); ?> - <?php echo $batch['batch_year']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="assign_batch" class="btn btn-primary">Assign Batch</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Import Students Modal (Placeholder) -->
    <div class="modal fade" id="importStudentsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Import Students</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 
                        Bulk import functionality will be available in the next update.
                    </div>
                    <p>In the future, you will be able to:</p>
                    <ul>
                        <li>Upload CSV/Excel files</li>
                        <li>Map columns to student fields</li>
                        <li>Assign imported students to batches</li>
                        <li>Preview before import</li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Student Modal -->
    <?php if ($action === 'edit' && $editStudent): ?>
    <div class="modal fade show" id="editStudentModal" tabindex="-1" style="display: block; padding-right: 17px;">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Student</h5>
                        <a href="students.php" class="btn-close"></a>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="student_id" value="<?php echo $editStudent['id']; ?>">
                        <div class="mb-3">
                            <label class="form-label">Student ID</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($editStudent['username']); ?>" disabled>
                            <small class="text-muted">Student ID cannot be changed</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Full Name *</label>
                            <input type="text" class="form-control" name="full_name" 
                                   value="<?php echo htmlspecialchars($editStudent['full_name']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" name="email" 
                                   value="<?php echo htmlspecialchars($editStudent['email']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Batch</label>
                            <select class="form-control" name="batch_id">
                                <option value="">-- Not Assigned --</option>
                                <?php foreach ($batches as $batch): ?>
                                <option value="<?php echo $batch['id']; ?>" 
                                    <?php echo $editStudent['batch_id'] == $batch['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($batch['batch_name']); ?> - <?php echo $batch['batch_year']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-control" name="status" required>
                                <option value="active" <?php echo $editStudent['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $editStudent['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="students.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" name="update_student" class="btn btn-primary">Update Student</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal-backdrop fade show"></div>
    <?php endif; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            $('#studentsTable').DataTable({
                pageLength: 10,
                responsive: true,
                order: [[1, 'asc']] // Sort by student ID
            });
            
            // Assign Batch modal setup
            $('#assignBatchModal').on('show.bs.modal', function(event) {
                var button = $(event.relatedTarget);
                var studentId = button.data('student-id');
                var studentName = button.data('student-name');
                
                var modal = $(this);
                modal.find('#assignStudentId').val(studentId);
                modal.find('#assignStudentName').val(studentName);
            });
            
            // Show edit modal if edit action
            <?php if ($action === 'edit' && $editStudent): ?>
            $(document).ready(function() {
                $('#editStudentModal').modal('show');
            });
            <?php endif; ?>
        });
    </script>
</body>
</html>