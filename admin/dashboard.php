<?php
require_once '../includes/config/constants.php';
require_once '../includes/classes/Database.php';
require_once '../includes/classes/Session.php';
require_once '../includes/classes/Auth.php';
require_once '../includes/classes/UserManager.php';
require_once '../includes/classes/BatchManager.php'; // ADD THIS LINE

Session::init();
$auth = new Auth();
$auth->requireRole(['admin']);

$user = $auth->getUser();
$userManager = new UserManager();
$batchManager = new BatchManager(); // ADD THIS LINE

// Get department-specific statistics
$stats = $userManager->getUserStatistics($user['department_id']);

// Get advanced statistics
$advancedStats = $batchManager->getDepartmentAdvancedStats($user['department_id']);

// If advancedStats is null, initialize with default values
if (!$advancedStats) {
    $advancedStats = [
        'student_count' => 0,
        'batch_count' => 0,
        'teacher_count' => 0,
        'project_count' => 0,
        'avg_department_grade' => 0,
        'unassigned_students' => 0,
        'completed_projects' => 0
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - FYPMS</title>
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
        .stat-card.teacher { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .stat-card.student { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .stat-card.project { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        .stat-card.batch { background: linear-gradient(135deg, #30cfd0 0%, #330867 100%); }
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
                    <i class="fas fa-user-tie fa-2x" style="color: #667eea;"></i>
                </div>
                <h5 class="mt-2"><?php echo htmlspecialchars($user['full_name']); ?></h5>
                <small>Department Head</small>
            </div>
            
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link active" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="batches.php">
                        <i class="fas fa-calendar-alt me-2"></i> Batches
                    </a>
                </li>
                <li class="nav-item">
    <a class="nav-link" href="import_students.php">
        <i class="fas fa-file-import me-2"></i> Import Students
    </a>
</li>
<li class="nav-item">
    <a class="nav-link" href="department_config.php">
        <i class="fas fa-sliders-h me-2"></i> Department Config
    </a>
</li>
                <li class="nav-item">
                    <a class="nav-link" href="teachers.php">
                        <i class="fas fa-chalkboard-teacher me-2"></i> Teachers
                    </a>
                </li>
                <li class="nav-item">
    <a class="nav-link" href="supervisor_assignment.php">
        <i class="fas fa-user-tie me-2"></i> Supervisor Assignment
    </a>
</li>
                <li class="nav-item">
                    <a class="nav-link" href="students.php">
                        <i class="fas fa-user-graduate me-2"></i> Students
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="projects.php">
                        <i class="fas fa-project-diagram me-2"></i> Projects
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="notices.php">
                        <i class="fas fa-bullhorn me-2"></i> Notices
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
        
        <h2 class="mb-4">Admin Dashboard</h2>
        
        <!-- Statistics Cards -->
        <!-- C:\xampp\htdocs\fypms\final-year-project-management-system\admin\dashboard.php (ENHANCED VERSION) -->
<!-- Add this section after the Statistics Cards in your existing dashboard.php -->

<!-- Advanced Statistics Section -->
<!-- Update the display section to use $advancedStats correctly -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Department Performance Dashboard</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 text-center">
                        <div class="mb-3">
                            <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center" 
                                 style="width: 80px; height: 80px;">
                                <i class="fas fa-users fa-2x"></i>
                            </div>
                            <h3 class="mt-2"><?php echo $advancedStats['student_count'] ?? 0; ?></h3>
                            <h6>Total Students</h6>
                        </div>
                    </div>
                    <div class="col-md-3 text-center">
                        <div class="mb-3">
                            <div class="rounded-circle bg-success text-white d-inline-flex align-items-center justify-content-center" 
                                 style="width: 80px; height: 80px;">
                                <i class="fas fa-calendar-check fa-2x"></i>
                            </div>
                            <h3 class="mt-2"><?php echo $advancedStats['batch_count'] ?? 0; ?></h3>
                            <h6>Active Batches</h6>
                        </div>
                    </div>
                    <div class="col-md-3 text-center">
                        <div class="mb-3">
                            <div class="rounded-circle bg-info text-white d-inline-flex align-items-center justify-content-center" 
                                 style="width: 80px; height: 80px;">
                                <i class="fas fa-project-diagram fa-2x"></i>
                            </div>
                            <h3 class="mt-2"><?php echo $advancedStats['project_count'] ?? 0; ?></h3>
                            <h6>Total Projects</h6>
                        </div>
                    </div>
                    <div class="col-md-3 text-center">
                        <div class="mb-3">
                            <div class="rounded-circle bg-warning text-white d-inline-flex align-items-center justify-content-center" 
                                 style="width: 80px; height: 80px;">
                                <i class="fas fa-chart-line fa-2x"></i>
                            </div>
                            <h3 class="mt-2">
                                <?php 
                                $totalProjects = $advancedStats['project_count'] ?? 0;
                                $completedProjects = $advancedStats['completed_projects'] ?? 0;
                                $completionRate = ($totalProjects > 0) ? 
                                    ($completedProjects / $totalProjects * 100) : 0;
                                echo round($completionRate, 1); ?>%
                            </h3>
                            <h6>Completion Rate</h6>
                        </div>
                    </div>
                </div>
                
                <!-- Progress Bars -->
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Student Assignment Progress</span>
                                <span>
                                    <?php 
                                    $totalStudents = $advancedStats['student_count'] ?? 0;
                                    $unassignedStudents = $advancedStats['unassigned_students'] ?? 0;
                                    $assignedStudents = $totalStudents - $unassignedStudents;
                                    $assignedRate = ($totalStudents > 0) ? 
                                        ($assignedStudents / $totalStudents * 100) : 0;
                                    echo round($assignedRate, 1); ?>%
                                </span>
                            </div>
                            <div class="progress" style="height: 15px;">
                                <div class="progress-bar bg-success" style="width: <?php echo $assignedRate; ?>%"></div>
                            </div>
                            <small class="text-muted">
                                <?php echo $assignedStudents; ?> assigned / 
                                <?php echo $unassignedStudents; ?> unassigned
                            </small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Project Completion Status</span>
                                <span>
                                    <?php echo $completedProjects; ?> / <?php echo $totalProjects; ?>
                                </span>
                            </div>
                            <div class="progress" style="height: 15px;">
                                <div class="progress-bar bg-info" style="width: <?php echo $completionRate; ?>%"></div>
                            </div>
                            <small class="text-muted">
                                <?php echo $completedProjects; ?> completed projects
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
        <!-- Quick Actions -->
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Pending Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <a href="students.php?action=import" class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">
                                        <i class="fas fa-file-import text-primary me-2"></i>
                                        Import Students
                                    </h6>
                                    <small>3 days ago</small>
                                </div>
                                <p class="mb-1">Import student list for 2026 batch</p>
                            </a>
                            <a href="teachers.php?action=create" class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">
                                        <i class="fas fa-user-plus text-success me-2"></i>
                                        Add New Teacher
                                    </h6>
                                    <small>1 week ago</small>
                                </div>
                                <p class="mb-1">Add new faculty members for supervision</p>
                            </a>
                            <a href="batches.php?action=create" class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">
                                        <i class="fas fa-calendar-plus text-info me-2"></i>
                                        Create New Batch
                                    </h6>
                                    <small>2 weeks ago</small>
                                </div>
                                <p class="mb-1">Setup new academic year batch</p>
                            </a>
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
                            <a href="teachers.php?action=create" class="btn btn-primary">
                                <i class="fas fa-user-plus me-2"></i> Add Teacher
                            </a>
                            <a href="students.php?action=import" class="btn btn-success">
                                <i class="fas fa-file-import me-2"></i> Import Students
                            </a>
                            <a href="batches.php?action=create" class="btn btn-info">
                                <i class="fas fa-calendar-plus me-2"></i> Create Batch
                            </a>
                            <a href="notices.php?action=create" class="btn btn-warning">
                                <i class="fas fa-bullhorn me-2"></i> Post Notice
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">Upcoming Deadlines</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Title Submission</span>
                                <span class="badge bg-warning">7 days</span>
                            </div>
                            <small class="text-muted">Due: Jan 25, 2026</small>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Proposal Submission</span>
                                <span class="badge bg-info">21 days</span>
                            </div>
                            <small class="text-muted">Due: Feb 8, 2026</small>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Final Report</span>
                                <span class="badge bg-success">45 days</span>
                            </div>
                            <small class="text-muted">Due: Mar 15, 2026</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>