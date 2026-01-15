<!-- superadmin/departments.php -->
<?php
require_once '../includes/config/constants.php';
require_once '../includes/classes/Database.php';
require_once '../includes/classes/Session.php';
require_once '../includes/classes/Auth.php';
require_once '../includes/classes/DepartmentManager.php';
require_once '../includes/classes/UserManager.php';

Session::init();
$auth = new Auth();
$auth->requireRole(['superadmin']);

$user = $auth->getUser();
$deptManager = new DepartmentManager();
$userManager = new UserManager();

// Get all departments with statistics
$departments = $deptManager->getAllDepartments(true);

// Handle actions
$action = $_GET['action'] ?? '';
$deptId = $_GET['id'] ?? '';
$message = '';
$error = '';

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_department'])) {
        $data = [
            'dept_code' => trim($_POST['dept_code']),
            'dept_name' => trim($_POST['dept_name']),
            'dept_type' => $_POST['dept_type'],
            'created_by' => $user['user_id']
        ];
        
        $result = $deptManager->createDepartment($data);
        if ($result['success']) {
            $message = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
    elseif (isset($_POST['update_department'])) {
        $data = [
            'dept_code' => trim($_POST['dept_code']),
            'dept_name' => trim($_POST['dept_name']),
            'dept_type' => $_POST['dept_type']
        ];
        
        $result = $deptManager->updateDepartment($_POST['dept_id'], $data);
        if ($result['success']) {
            $message = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
    elseif (isset($_POST['delete_department'])) {
        $result = $deptManager->deleteDepartment($_POST['dept_id']);
        if ($result['success']) {
            $message = $result['message'];
            // Refresh page to show updated list
            header('Location: departments.php?success=' . urlencode($message));
            exit();
        } else {
            $error = $result['message'];
        }
    }
}

// Get department for editing
$editDepartment = null;
if ($action === 'edit' && $deptId) {
    $editDepartment = $deptManager->getDepartmentById($deptId);
    if (!$editDepartment) {
        $error = 'Department not found';
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
    <title>Department Management - FYPMS</title>
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
        .stat-card {
            border-radius: 10px;
            padding: 15px;
            color: white;
            margin-bottom: 20px;
        }
        .badge-type {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
        }
        .badge-technology { background: #667eea; }
        .badge-business { background: #10b981; }
        .badge-economics { background: #f59e0b; }
    </style>
</head>
<body>
    <!-- Sidebar (same as dashboard) -->
    <div class="sidebar">
        <div class="p-4">
            <h4 class="text-center">FYPMS</h4>
            <hr class="bg-white">
            <div class="text-center mb-4">
                <div class="rounded-circle bg-white d-inline-flex align-items-center justify-content-center" 
                     style="width: 60px; height: 60px;">
                    <i class="fas fa-user-shield" style="color: #667eea;"></i>
                </div>
                <h6 class="mt-2"><?php echo htmlspecialchars($user['full_name']); ?></h6>
                <small>Super Admin</small>
            </div>
            
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="departments.php">
                        <i class="fas fa-building me-2"></i> Departments
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="department_heads.php">
                        <i class="fas fa-user-tie me-2"></i> Department Heads
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="batches.php">
                        <i class="fas fa-calendar-alt me-2"></i> All Batches
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
                <h3 class="mb-0">Department Management</h3>
                <div>
                    <a href="dashboard.php" class="btn btn-outline-primary btn-sm me-2">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createDeptModal">
                        <i class="fas fa-plus-circle"></i> Add Department
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
        
        <!-- Department Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3><?php echo count($departments); ?></h3>
                            <h6>Total Departments</h6>
                        </div>
                        <i class="fas fa-building fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <?php 
                $techCount = array_reduce($departments, function($carry, $dept) {
                    return $carry + ($dept['dept_type'] === 'technology' ? 1 : 0);
                }, 0);
                ?>
                <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #5a6fd8 100%);">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3><?php echo $techCount; ?></h3>
                            <h6>Technology</h6>
                        </div>
                        <i class="fas fa-laptop-code fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <?php 
                $businessCount = array_reduce($departments, function($carry, $dept) {
                    return $carry + ($dept['dept_type'] === 'business' ? 1 : 0);
                }, 0);
                ?>
                <div class="stat-card" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3><?php echo $businessCount; ?></h3>
                            <h6>Business</h6>
                        </div>
                        <i class="fas fa-chart-line fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <?php 
                $econCount = array_reduce($departments, function($carry, $dept) {
                    return $carry + ($dept['dept_type'] === 'economics' ? 1 : 0);
                }, 0);
                ?>
                <div class="stat-card" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3><?php echo $econCount; ?></h3>
                            <h6>Economics</h6>
                        </div>
                        <i class="fas fa-money-bill-wave fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Departments Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Departments List</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="departmentsTable" class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Admins</th>
                                <th>Teachers</th>
                                <th>Students</th>
                                <th>Created By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($departments as $index => $dept): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($dept['dept_code']); ?></strong>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($dept['dept_name']); ?></strong>
                                </td>
                                <td>
                                    <span class="badge badge-type badge-<?php echo $dept['dept_type']; ?>">
                                        <?php echo ucfirst($dept['dept_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?php echo $dept['admin_count']; ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $dept['teacher_count']; ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-success"><?php echo $dept['student_count']; ?></span>
                                </td>
                                <td>
                                    <small><?php echo htmlspecialchars($dept['created_by_name']); ?></small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="department_heads.php?dept_id=<?php echo $dept['id']; ?>" 
                                           class="btn btn-outline-primary" title="Manage Department Heads">
                                            <i class="fas fa-user-tie"></i>
                                        </a>
                                        <a href="departments.php?action=edit&id=<?php echo $dept['id']; ?>" 
                                           class="btn btn-outline-info" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-danger" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deleteDeptModal"
                                                data-dept-id="<?php echo $dept['id']; ?>"
                                                data-dept-name="<?php echo htmlspecialchars($dept['dept_name']); ?>"
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
    
    <!-- Create Department Modal -->
    <div class="modal fade" id="createDeptModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">Create New Department</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Department Code *</label>
                            <input type="text" class="form-control" name="dept_code" 
                                   placeholder="e.g., CS, ACCN, ECON" required maxlength="20">
                            <small class="text-muted">Unique code for the department</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Department Name *</label>
                            <input type="text" class="form-control" name="dept_name" 
                                   placeholder="e.g., Computer Science" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Department Type *</label>
                            <select class="form-control" name="dept_type" required>
                                <option value="technology">Technology</option>
                                <option value="business">Business</option>
                                <option value="economics">Economics</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_department" class="btn btn-primary">Create Department</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Department Modal -->
    <?php if ($action === 'edit' && $editDepartment): ?>
    <div class="modal fade show" id="editDeptModal" tabindex="-1" style="display: block; padding-right: 17px;">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Department</h5>
                        <a href="departments.php" class="btn-close"></a>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="dept_id" value="<?php echo $editDepartment['id']; ?>">
                        <div class="mb-3">
                            <label class="form-label">Department Code *</label>
                            <input type="text" class="form-control" name="dept_code" 
                                   value="<?php echo htmlspecialchars($editDepartment['dept_code']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Department Name *</label>
                            <input type="text" class="form-control" name="dept_name" 
                                   value="<?php echo htmlspecialchars($editDepartment['dept_name']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Department Type *</label>
                            <select class="form-control" name="dept_type" required>
                                <option value="technology" <?php echo $editDepartment['dept_type'] === 'technology' ? 'selected' : ''; ?>>Technology</option>
                                <option value="business" <?php echo $editDepartment['dept_type'] === 'business' ? 'selected' : ''; ?>>Business</option>
                                <option value="economics" <?php echo $editDepartment['dept_type'] === 'economics' ? 'selected' : ''; ?>>Economics</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Created By</label>
                            <input type="text" class="form-control" 
                                   value="<?php echo htmlspecialchars($editDepartment['created_by_name']); ?>" disabled>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="departments.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" name="update_department" class="btn btn-primary">Update Department</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal-backdrop fade show"></div>
    <?php endif; ?>
    
    <!-- Delete Department Modal -->
    <div class="modal fade" id="deleteDeptModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">Delete Department</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="dept_id" id="deleteDeptId">
                        <p>Are you sure you want to delete department: <strong id="deleteDeptName"></strong>?</p>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Warning:</strong> This action cannot be undone. Make sure the department has no users assigned.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_department" class="btn btn-danger">Delete Department</button>
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
            $('#departmentsTable').DataTable({
                pageLength: 10,
                responsive: true,
                order: [[2, 'asc']] // Sort by department name
            });
            
            // Delete modal setup
            $('#deleteDeptModal').on('show.bs.modal', function(event) {
                var button = $(event.relatedTarget);
                var deptId = button.data('dept-id');
                var deptName = button.data('dept-name');
                
                var modal = $(this);
                modal.find('#deleteDeptId').val(deptId);
                modal.find('#deleteDeptName').text(deptName);
            });
            
            // Show edit modal if edit action
            <?php if ($action === 'edit' && $editDepartment): ?>
            $(document).ready(function() {
                $('#editDeptModal').modal('show');
            });
            <?php endif; ?>
        });
    </script>
</body>
</html>