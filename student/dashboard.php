<!-- student/dashboard.php -->
<?php
require_once '../includes/config/constants.php';
require_once '../includes/classes/Database.php';
require_once '../includes/classes/Session.php';
require_once '../includes/classes/Auth.php';

Session::init();
$auth = new Auth();
$auth->requireRole(['student']);

$user = $auth->getUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - FYPMS</title>
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
        .progress-card {
            border-left: 4px solid var(--primary-color);
        }
        .deadline-card {
            border-left: 4px solid #dc3545;
        }
        .stat-card {
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
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
                    <i class="fas fa-user-graduate fa-2x" style="color: #667eea;"></i>
                </div>
                <h5 class="mt-2"><?php echo htmlspecialchars($user['full_name']); ?></h5>
                <small>Student</small>
                <div class="mt-2">
                    <span class="badge bg-light text-dark">ID: <?php echo htmlspecialchars($user['username']); ?></span>
                </div>
            </div>
            
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link active" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="my_project.php">
                        <i class="fas fa-project-diagram me-2"></i> My Project
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="submissions.php">
                        <i class="fas fa-upload me-2"></i> Submissions
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="feedback.php">
                        <i class="fas fa-comments me-2"></i> Feedback
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="group.php">
                        <i class="fas fa-users me-2"></i> Group
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="calendar.php">
                        <i class="fas fa-calendar-alt me-2"></i> Calendar
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
        <nav class="navbar navbar-light bg-light mb-4">
            <div class="container-fluid">
                <span class="navbar-text">
                    <i class="fas fa-calendar-alt me-2"></i>
                    <?php echo date('l, F j, Y'); ?>
                </span>
                <div>
                    <span class="badge bg-primary me-2">
                        <i class="fas fa-bell"></i> 2
                    </span>
                    <a href="profile.php" class="btn btn-outline-primary btn-sm me-2">
                        <i class="fas fa-user-circle"></i> Profile
                    </a>
                    <a href="../logout.php" class="btn btn-outline-danger btn-sm">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </nav>
        
        <h2 class="mb-4">Student Dashboard</h2>
        
        <!-- Welcome Message -->
        <div class="alert alert-success mb-4">
            <h5><i class="fas fa-user-graduate me-2"></i> Welcome back, <?php echo htmlspecialchars($user['full_name']); ?>!</h5>
            <p class="mb-0">You are in the Final Year Project batch 2026. Track your progress and meet your deadlines.</p>
        </div>
        
        <!-- Project Progress -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card progress-card">
                    <div class="card-header">
                        <h5 class="mb-0">Project Progress</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Overall Progress</span>
                                <span>35%</span>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar bg-success" style="width: 35%"></div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="stat-card bg-light">
                                    <h6><i class="fas fa-flag text-primary me-2"></i> Title</h6>
                                    <div class="d-flex justify-content-between">
                                        <span>Status:</span>
                                        <span class="badge bg-success">Approved</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="stat-card bg-light">
                                    <h6><i class="fas fa-file-alt text-info me-2"></i> Proposal</h6>
                                    <div class="d-flex justify-content-between">
                                        <span>Status:</span>
                                        <span class="badge bg-warning">Under Review</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="stat-card bg-light">
                                    <h6><i class="fas fa-book text-success me-2"></i> Chapters</h6>
                                    <div class="d-flex justify-content-between">
                                        <span>Status:</span>
                                        <span class="badge bg-secondary">Not Started</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="stat-card bg-light">
                                    <h6><i class="fas fa-microphone-alt text-danger me-2"></i> Defense</h6>
                                    <div class="d-flex justify-content-between">
                                        <span>Status:</span>
                                        <span class="badge bg-secondary">Pending</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card deadline-card">
                    <div class="card-header">
                        <h5 class="mb-0">Upcoming Deadlines</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <strong>Proposal Submission</strong>
                                <span class="badge bg-danger">URGENT</span>
                            </div>
                            <p class="mb-1 small">Submit complete project proposal</p>
                            <small class="text-muted">
                                <i class="fas fa-clock"></i> Due: Jan 25, 2026 (7 days)
                            </small>
                        </div>
                        <hr>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <strong>Chapter 1-3 Submission</strong>
                                <span class="badge bg-warning">14 days</span>
                            </div>
                            <p class="mb-1 small">Submit first three chapters</p>
                            <small class="text-muted">
                                <i class="fas fa-clock"></i> Due: Feb 8, 2026
                            </small>
                        </div>
                        <hr>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <strong>Final Report</strong>
                                <span class="badge bg-info">45 days</span>
                            </div>
                            <p class="mb-1 small">Complete final report submission</p>
                            <small class="text-muted">
                                <i class="fas fa-clock"></i> Due: Mar 15, 2026
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity & Supervisor Info -->
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Recent Activity</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">
                                        <i class="fas fa-file-upload text-primary me-2"></i>
                                        Proposal Submitted
                                    </h6>
                                    <small>2 days ago</small>
                                </div>
                                <p class="mb-1">You submitted "E-Commerce Platform for Local Businesses" proposal</p>
                            </div>
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">
                                        <i class="fas fa-user-tie text-success me-2"></i>
                                        Supervisor Assigned
                                    </h6>
                                    <small>5 days ago</small>
                                </div>
                                <p class="mb-1">Mr. Duressa Deksiso assigned as your supervisor</p>
                            </div>
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">
                                        <i class="fas fa-flag-checkered text-info me-2"></i>
                                        Title Approved
                                    </h6>
                                    <small>1 week ago</small>
                                </div>
                                <p class="mb-1">Your project title has been approved by department</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Supervisor Information</h5>
                    </div>
                    <div class="card-body text-center">
                        <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mb-3" 
                             style="width: 80px; height: 80px;">
                            <i class="fas fa-user-tie fa-2x"></i>
                        </div>
                        <h5>Mr. Duressa Deksiso</h5>
                        <p class="text-muted">MSc. in Computer Science</p>
                        <div class="mb-3">
                            <i class="fas fa-envelope text-primary me-2"></i>
                            <small>duressa.deksiso@arsi.edu.et</small>
                        </div>
                        <div class="mb-3">
                            <i class="fas fa-phone text-success me-2"></i>
                            <small>+251-XXX-XXXXXX</small>
                        </div>
                        <a href="messages.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-comment-dots me-2"></i> Send Message
                        </a>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="submissions.php?type=proposal" class="btn btn-primary">
                                <i class="fas fa-upload me-2"></i> Submit Proposal
                            </a>
                            <a href="feedback.php" class="btn btn-success">
                                <i class="fas fa-comments me-2"></i> View Feedback
                            </a>
                            <a href="notices.php" class="btn btn-info">
                                <i class="fas fa-bullhorn me-2"></i> Check Notices
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>