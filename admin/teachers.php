<!-- admin/teachers.php -->
<?php
require_once '../includes/config/constants.php';
require_once '../includes/classes/Database.php';
require_once '../includes/classes/Session.php';
require_once '../includes/classes/Auth.php';
require_once '../includes/classes/UserManager.php';
require_once '../includes/classes/DepartmentManager.php';

Session::init();
$auth = new Auth();
$auth->requireRole(['admin']);

$user = $auth->getUser();
$userManager = new UserManager();
$deptManager = new DepartmentManager();

// Get all teachers in the admin's department
$teachers = $userManager->getAllUsers('teacher', $user['department_id']);

// Handle actions
$action = $_GET['action'] ?? '';
$teacherId = $_GET['id'] ?? '';
$message = '';
$error = '';

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_teacher'])) {
        $data = [
            'username' => trim($_POST['username']),
            'password' => trim($_POST['password']),
            'full_name' => trim($_POST['full_name']),
            'email' => trim($_POST['email']),
            'department_id' => $user['department_id'],
            'max_students' => $_POST['max_students'] ?? 5,
            'created_by' => $user['user_id']
        ];
        
        $result = $userManager->createTeacher($data);
        if ($result['success']) {
            $message = $result['message'];
            header('Location: teachers.php?success=' . urlencode($message));
            exit();
        } else {
            $error = $result['message'];
        }
    }
    elseif (isset($_POST['update_teacher'])) {
        $data = [
            'full_name' => trim($_POST['full_name']),
            'email' => trim($_POST['email']),
            'max_students' => $_POST['max_students'] ?? 5,
            'status' => $_POST['status']
        ];
        
        $result = $userManager->updateUser($_POST['teacher_id'], 'teacher', $data);
        if ($result) {
            $message = 'Teacher updated successfully';
            header('Location: teachers.php?success=' . urlencode($message));
            exit();
        } else {
            $error = 'Failed to update teacher';
        }
    }
    elseif (isset($_POST['add_specialization'])) {
        $teacherId = $_POST['teacher_id'];
        $specialization = trim($_POST['specialization']);
        $level = $_POST['level'];
        
        $result = $userManager->addTeacherSpecialization($teacherId, $specialization, $level);
        if ($result['success']) {
            $message = $result['message'];
            header('Location: teachers.php?success=' . urlencode($message));
            exit();
        } else {
            $error = $result['message'];
        }
    }
}

// Get teacher for editing
$editTeacher = null;
if ($action === 'edit' && $teacherId) {
    $editTeacher = $userManager->getUserById($teacherId, 'teacher');
    if (!$editTeacher) {
        $error = 'Teacher not found';
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
    <title>Teachers Management - FYPMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <style>
        .specialization-badge {
            background: #e9ecef;
            border: 1px solid #dee2e6;
            border-radius: 15px;
            padding: 3px 10px;
            margin: 2px;
            display: inline-block;
        }
        .level-beginner { border-left: 3px solid #28a745; }
        .level-intermediate { border-left: 3px solid #ffc107; }
        .level-expert { border-left: 3px solid #dc3545; }
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
                    <a class="nav-link" href="batches.php">
                        <i class="fas fa-calendar-alt me-2"></i> Batches
                    </a>
                </li>
                <li class="nav-item">
    <a class="nav-link" href="supervisor_assignment.php">
        <i class="fas fa-user-tie me-2"></i> Supervisor Assignment
    </a>
</li>
                <li class="nav-item">
                    <a class="nav-link active" href="teachers.php">
                        <i class="fas fa-chalkboard-teacher me-2"></i> Teachers
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="projects.php">
                        <i class="fas fa-project-diagram me-2"></i> Projects
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
                <h3 class="mb-0">Teachers Management</h3>
                <div>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createTeacherModal">
                        <i class="fas fa-user-plus"></i> Add Teacher
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
        
        <!-- Teachers Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Teachers List (<?php echo count($teachers); ?>)</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="teachersTable" class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Teacher ID</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Specializations</th>
                                <th>Max Students</th>
                                <th>Current Load</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($teachers as $index => $teacher): 
                                // Get teacher specializations
                                $specializations = $userManager->getTeacherSpecializations($teacher['id']);
                                // Get current load (you'll need to implement this method)
                                $currentLoad = $userManager->getTeacherCurrentLoad($teacher['id']);
                            ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($teacher['username']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($teacher['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($teacher['email']); ?></td>
                                <td>
                                    <?php foreach ($specializations as $spec): ?>
                                        <span class="specialization-badge level-<?php echo $spec['level']; ?>">
                                            <?php echo htmlspecialchars($spec['specialization']); ?>
                                        </span>
                                    <?php endforeach; ?>
                                    <?php if (empty($specializations)): ?>
                                        <span class="text-muted">No specializations</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $teacher['max_students']; ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $currentLoad >= $teacher['max_students'] ? 'danger' : ($currentLoad > 0 ? 'warning' : 'success'); ?>">
                                        <?php echo $currentLoad; ?> students
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $teacher['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($teacher['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" data-bs-toggle="modal" 
                                                data-bs-target="#addSpecializationModal"
                                                data-teacher-id="<?php echo $teacher['id']; ?>"
                                                data-teacher-name="<?php echo htmlspecialchars($teacher['full_name']); ?>"
                                                title="Add Specialization">
                                            <i class="fas fa-tags"></i>
                                        </button>
                                        <button class="btn btn-outline-info" data-bs-toggle="modal" 
                                                data-bs-target="#viewSupervisorModal"
                                                data-teacher-id="<?php echo $teacher['id']; ?>"
                                                data-teacher-name="<?php echo htmlspecialchars($teacher['full_name']); ?>"
                                                title="View Supervising Students">
                                            <i class="fas fa-user-graduate"></i>
                                        </button>
                                        <a href="teachers.php?action=edit&id=<?php echo $teacher['id']; ?>" 
                                           class="btn btn-outline-warning" title="Edit">
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
    
    <!-- Create Teacher Modal -->
    <div class="modal fade" id="createTeacherModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Teacher</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Teacher ID *</label>
                            <input type="text" class="form-control" name="username" 
                                   placeholder="e.g., T001, T002" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password *</label>
                            <input type="password" class="form-control" name="password" required>
                            <small class="text-muted">Default password for teacher</small>
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
                            <label class="form-label">Maximum Students</label>
                            <select class="form-control" name="max_students">
                                <option value="3">3 Students</option>
                                <option value="4">4 Students</option>
                                <option value="5" selected>5 Students</option>
                                <option value="6">6 Students</option>
                                <option value="7">7 Students</option>
                                <option value="8">8 Students</option>
                            </select>
                            <small class="text-muted">Maximum number of students this teacher can supervise</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_teacher" class="btn btn-primary">Add Teacher</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Add Specialization Modal -->
    <div class="modal fade" id="addSpecializationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">Add Specialization</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="teacher_id" id="specTeacherId">
                        <div class="mb-3">
                            <label class="form-label">Teacher</label>
                            <input type="text" class="form-control" id="specTeacherName" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Specialization *</label>
                            <input type="text" class="form-control" name="specialization" 
                                   placeholder="e.g., Web Development, Database Systems, AI/ML" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Expertise Level</label>
                            <select class="form-control" name="level" required>
                                <option value="beginner">Beginner</option>
                                <option value="intermediate" selected>Intermediate</option>
                                <option value="expert">Expert</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_specialization" class="btn btn-primary">Add Specialization</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- View Supervisor Modal -->
    <div class="modal fade" id="viewSupervisorModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Supervisor Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="supervisorDetails">
                        <!-- Will be loaded via AJAX -->
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p>Loading supervisor details...</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
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
            $('#teachersTable').DataTable({
                pageLength: 10,
                responsive: true,
                order: [[2, 'asc']] // Sort by name
            });
            
            // Add Specialization modal setup
            $('#addSpecializationModal').on('show.bs.modal', function(event) {
                var button = $(event.relatedTarget);
                var teacherId = button.data('teacher-id');
                var teacherName = button.data('teacher-name');
                
                var modal = $(this);
                modal.find('#specTeacherId').val(teacherId);
                modal.find('#specTeacherName').val(teacherName);
            });
            
            // View Supervisor modal setup
            $('#viewSupervisorModal').on('show.bs.modal', function(event) {
                var button = $(event.relatedTarget);
                var teacherId = button.data('teacher-id');
                var teacherName = button.data('teacher-name');
                
                var modal = $(this);
                modal.find('.modal-title').text('Supervisor: ' + teacherName);
                
                // Load supervisor details via AJAX
                $.ajax({
                    url: 'ajax_get_supervisor_details.php',
                    method: 'GET',
                    data: { teacher_id: teacherId },
                    success: function(response) {
                        $('#supervisorDetails').html(response);
                    },
                    error: function() {
                        $('#supervisorDetails').html(
                            '<div class="alert alert-danger">Failed to load supervisor details.</div>'
                        );
                    }
                });
            });
        });
    </script>
</body>
</html>