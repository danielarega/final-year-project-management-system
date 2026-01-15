<!-- superadmin/dashboard.php -->
<?php
require_once '../includes/config/constants.php';
require_once '../includes/classes/Database.php';
require_once '../includes/classes/Session.php';
require_once '../includes/classes/Auth.php';
require_once '../includes/classes/UserManager.php';

Session::init();
$auth = new Auth();
$auth->requireRole(['superadmin']);

$user = $auth->getUser();
$userManager = new UserManager();

// Get statistics
$stats = $userManager->getUserStatistics();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard - FYPMS</title>
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
        .stat-card {
            border-radius: 10px;
            padding: 20px;
            color: white;
            margin-bottom: 20px;
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-card.admin { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stat-card.teacher { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .stat-card.student { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .stat-card.department { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="p-4">
            <h4 class="text-center">FYPMS</h4>
            <hr class="bg-white">
            <div class="text-center mb-4">
                <div class="rounded-circle bg-white d-inline-flex align-items-center justify-content-center" 
                     style="width: 80px; height: 80px;">
                    <i class="fas fa-user-shield fa-2x" style="color: #667eea;"></i>
                </div>
                <h5 class="mt-2"><?php echo htmlspecialchars($user['full_name']); ?></h5>
                <small>Super Administrator</small>
            </div>
            
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link active" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="departments.php">
                        <i class="fas fa-building me-2"></i> Departments
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="department_heads.php">
                        <i class="fas fa-user-tie me-2"></i> Department Heads
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="activity_logs.php">
                        <i class="fas fa-history me-2"></i> Activity Logs
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="reports.php">
                        <i class="fas fa-chart-bar me-2"></i> Reports
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="settings.php">
                        <i class="fas fa-cog me-2"></i> Settings
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
        <nav class="navbar navbar-light bg-light mb-4">
            <div class="container-fluid">
                <span class="navbar-text">
                    <i class="fas fa-calendar-alt me-2"></i>
                    <?php echo date('l, F j, Y'); ?>
                </span>
                <div>
                    <a href="profile.php" class="btn btn-outline-primary btn-sm me-2">
                        <i class="fas fa-user-circle"></i> Profile
                    </a>
                    <a href="../logout.php" class="btn btn-outline-danger btn-sm">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </nav>
        
        <h2 class="mb-4">Super Admin Dashboard</h2>
        
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card department">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2>4</h2>
                            <h6>Departments</h6>
                        </div>
                        <i class="fas fa-building fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card admin">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2><?php echo $stats['admins']; ?></h2>
                            <h6>Department Heads</h6>
                        </div>
                        <i class="fas fa-user-tie fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card teacher">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2><?php echo $stats['teachers']; ?></h2>
                            <h6>Teachers</h6>
                        </div>
                        <i class="fas fa-chalkboard-teacher fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card student">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2><?php echo $stats['students']; ?></h2>
                            <h6>Students</h6>
                        </div>
                        <i class="fas fa-user-graduate fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Recent Activity</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>User</th>
                                        <th>Action</th>
                                        <th>Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>2 mins ago</td>
                                        <td>You</td>
                                        <td>Logged in</td>
                                        <td>From IP: <?php echo $_SERVER['REMOTE_ADDR']; ?></td>
                                    </tr>
                                    <tr>
                                        <td>5 mins ago</td>
                                        <td>System</td>
                                        <td>Backup</td>
                                        <td>Daily backup completed</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="department_heads.php?action=create" class="btn btn-primary">
                                <i class="fas fa-plus-circle me-2"></i> Add Department Head
                            </a>
                            <a href="departments.php?action=create" class="btn btn-success">
                                <i class="fas fa-building me-2"></i> Create Department
                            </a>
                            <a href="reports.php" class="btn btn-info">
                                <i class="fas fa-download me-2"></i> Generate Report
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">System Status</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Storage</span>
                                <span>65%</span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-success" style="width: 65%"></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Active Users</span>
                                <span><?php echo array_sum($stats); ?></span>
                            </div>
                        </div>
                        <div class="text-center">
                            <small class="text-muted">
                                <i class="fas fa-check-circle text-success"></i> All systems operational
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>