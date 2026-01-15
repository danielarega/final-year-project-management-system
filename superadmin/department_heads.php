<!-- superadmin/department_heads.php -->
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

// Get department ID from query string
$deptId = $_GET['dept_id'] ?? 0;
$department = $deptManager->getDepartmentById($deptId);

if (!$department) {
    header('Location: departments.php?error=Department not found');
    exit();
}

// Get department heads and available admins
$departmentHeads = $deptManager->getDepartmentHeads($deptId);
$allAdmins = $userManager->getAllUsers('admin'); // Get all admins

// Handle actions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['assign_head'])) {
        $adminId = $_POST['admin_id'];
        $result = $deptManager->assignDepartmentHead($deptId, $adminId);
        
        if ($result['success']) {
            $message = $result['message'];
            // Refresh page
            header('Location: department_heads.php?dept_id=' . $deptId . '&success=' . urlencode($message));
            exit();
        } else {
            $error = $result['message'];
        }
    }
    elseif (isset($_POST['create_admin'])) {
        // Create new admin and assign as department head
        $data = [
            'username' => trim($_POST['username']),
            'password' => trim($_POST['password']),
            'full_name' => trim($_POST['full_name']),
            'email' => trim($_POST['email']),
            'department_id' => $deptId,
            'created_by' => $user['user_id']
        ];
        
        $result = $userManager->createDepartmentHead($data);
        if ($result['success']) {
            $message = 'Admin created and assigned as department head successfully';
            header('Location: department_heads.php?dept_id=' . $deptId . '&success=' . urlencode($message));
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
    <title>Department Heads - FYPMS</title>
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
        .dept-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
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
                    <a class="nav-link" href="departments.php">
                        <i class="fas fa-building me-2"></i> Departments
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="department_heads.php?dept_id=<?php echo $deptId; ?>">
                        <i class="fas fa-user-tie me-2"></i> Department Heads
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="batches.php">
                        <i class="fas fa-calendar-alt me-2"></i> All Batches
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
                    <a href="departments.php" class="btn btn-outline-primary btn-sm me-2">
                        <i class="fas fa-arrow-left"></i> Back to Departments
                    </a>
                    <h3 class="mb-0 d-inline">Department Heads</h3>
                </div>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#assignHeadModal">
                    <i class="fas fa-user-plus"></i> Assign Head
                </button>
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
        
        <!-- Department Info -->
        <div class="dept-header">
            <h2><?php echo htmlspecialchars($department['dept_name']); ?></h2>
            <p class="mb-0">
                <strong>Code:</strong> <?php echo htmlspecialchars($department['dept_code']); ?> | 
                <strong>Type:</strong> <?php echo ucfirst($department['dept_type']); ?>
            </p>
        </div>
        
        <!-- Department Heads List -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Current Department Heads</h5>
            </div>
            <div class="card-body">
                <?php if (empty($departmentHeads)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No department heads assigned yet.
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($departmentHeads as $head): ?>
                        <div class="col-md-6 mb-3">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-3" 
                                             style="width: 50px; height: 50px;">
                                            <i class="fas fa-user-tie"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($head['full_name']); ?></h6>
                                            <p class="mb-1 small">
                                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($head['username']); ?><br>
                                                <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($head['email']); ?>
                                            </p>
                                            <span class="badge bg-<?php echo $head['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                <?php echo ucfirst($head['status']); ?>
                                            </span>
                                            <?php if ($head['last_login']): ?>
                                                <small class="text-muted d-block">
                                                    Last login: <?php echo date('M d, Y', strtotime($head['last_login'])); ?>
                                                </small>
                                            <?php endif; ?>
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
        
        <!-- Available Admins -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">Available Administrators</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Current Department</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allAdmins as $index => $admin): 
                                $currentDept = $deptManager->getDepartmentById($admin['department_id']);
                            ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo htmlspecialchars($admin['username']); ?></td>
                                <td><?php echo htmlspecialchars($admin['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($admin['email']); ?></td>
                                <td>
                                    <?php if ($currentDept): ?>
                                        <?php echo htmlspecialchars($currentDept['dept_name']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Not assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $admin['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($admin['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Assign Head Modal -->
    <div class="modal fade" id="assignHeadModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Assign Department Head</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Tabs for existing admin or create new -->
                    <ul class="nav nav-tabs" id="assignHeadTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="existing-tab" data-bs-toggle="tab" 
                                    data-bs-target="#existing" type="button">
                                <i class="fas fa-user-check"></i> Assign Existing Admin
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="create-tab" data-bs-toggle="tab" 
                                    data-bs-target="#create" type="button">
                                <i class="fas fa-user-plus"></i> Create New Admin
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content p-3" id="assignHeadTabsContent">
                        <!-- Existing Admin Tab -->
                        <div class="tab-pane fade show active" id="existing" role="tabpanel">
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label class="form-label">Select Administrator</label>
                                    <select class="form-control" name="admin_id" required>
                                        <option value="">-- Select an Admin --</option>
                                        <?php foreach ($allAdmins as $admin): 
                                            if ($admin['department_id'] != $deptId): // Only show admins not already in this department
                                        ?>
                                        <option value="<?php echo $admin['id']; ?>">
                                            <?php echo htmlspecialchars($admin['full_name']); ?> 
                                            (<?php echo htmlspecialchars($admin['username']); ?>)
                                        </option>
                                        <?php endif; endforeach; ?>
                                    </select>
                                    <small class="text-muted">Select an existing administrator to assign as department head</small>
                                </div>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> This will reassign the selected admin to this department.
                                </div>
                                <div class="text-end">
                                    <button type="submit" name="assign_head" class="btn btn-primary">
                                        <i class="fas fa-user-tie"></i> Assign as Department Head
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Create New Admin Tab -->
                        <div class="tab-pane fade" id="create" role="tabpanel">
                            <form method="POST" action="">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Username *</label>
                                        <input type="text" class="form-control" name="username" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Password *</label>
                                        <input type="password" class="form-control" name="password" required>
                                        <small class="text-muted">Minimum 6 characters</small>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Full Name *</label>
                                        <input type="text" class="form-control" name="full_name" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Email *</label>
                                        <input type="email" class="form-control" name="email" required>
                                    </div>
                                </div>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> A new administrator account will be created and automatically assigned as department head.
                                </div>
                                <div class="text-end">
                                    <button type="submit" name="create_admin" class="btn btn-success">
                                        <i class="fas fa-plus-circle"></i> Create & Assign
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>