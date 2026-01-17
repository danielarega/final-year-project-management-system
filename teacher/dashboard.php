<?php
require_once '../includes/config/constants.php';
require_once '../includes/classes/Database.php';
require_once '../includes/classes/Session.php';
require_once '../includes/classes/Auth.php';
require_once '../includes/classes/SupervisorManager.php';  // Add this line

Session::init();
$auth = new Auth();
$auth->requireRole(['teacher']);

$user = $auth->getUser();

// Initialize SupervisorManager and get assigned projects
$supervisorManager = new SupervisorManager();
$assignedProjects = $supervisorManager->getTeacherProjects($user['user_id']) ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - FYPMS</title>
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
        .stat-card.students { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .stat-card.projects { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        .stat-card.reviews { background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); color: #333; }
        .stat-card.completed { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
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
                    <i class="fas fa-chalkboard-teacher fa-2x" style="color: #667eea;"></i>
                </div>
                <h5 class="mt-2"><?php echo htmlspecialchars($user['full_name']); ?></h5>
                <small>Teacher / Supervisor</small>
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
                    <a class="nav-link" href="projects.php">
                        <i class="fas fa-project-diagram me-2"></i> Projects
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="submissions.php">
                        <i class="fas fa-file-upload me-2"></i> Submissions
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="defenses.php">
                        <i class="fas fa-microphone-alt me-2"></i> Defenses
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="feedback.php">
                        <i class="fas fa-comments me-2"></i> Feedback
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="calendar.php">
                        <i class="fas fa-calendar-alt me-2"></i> Calendar
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
                        <i class="fas fa-bell"></i> 3
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
        
        <h2 class="mb-4">Teacher Dashboard</h2>
        
        <!-- Welcome Message -->
        <div class="alert alert-info mb-4">
            <h5><i class="fas fa-user-tie me-2"></i> Welcome, <?php echo htmlspecialchars($user['full_name']); ?></h5>
            <p class="mb-0">You are currently supervising final year projects. Check pending submissions and provide timely feedback.</p>
        </div>
        
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <!-- Add this to teacher/dashboard.php in the main content area -->

<!-- My Assigned Projects -->
<!-- My Assigned Projects -->
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">
            <i class="fas fa-project-diagram me-2"></i>
            My Assigned Projects
            <span class="badge bg-light text-dark"><?php echo count($assignedProjects); ?></span>
        </h5>
    </div>
    <div class="card-body">
        <?php if (empty($assignedProjects)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> You don't have any assigned projects yet.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Project Title</th>
                            <th>Batch</th>
                            <th>Status</th>
                            <th>Assigned</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assignedProjects as $project): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-2" 
                                         style="width: 30px; height: 30px;">
                                        <?php echo strtoupper(substr($project['student_name'], 0, 2)); ?>
                                    </div>
                                    <div>
                                        <div><?php echo htmlspecialchars($project['student_name']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($project['student_id']); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($project['title']); ?></td>
                            <td>
                                <span class="badge bg-info"><?php echo $project['batch_year']; ?></span>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $project['status'] === 'approved' ? 'success' : 'warning'; ?>">
                                    <?php echo ucfirst($project['status']); ?>
                                </span>
                            </td>
                            <td>
                                <small><?php echo date('M d, Y', strtotime($project['assignment_date'])); ?></small>
                            </td>
                            <td>
                                <a href="project_details.php?id=<?php echo $project['id']; ?>" 
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="text-center mt-3">
                <a href="my_projects.php" class="btn btn-primary">
                    <i class="fas fa-list"></i> View All My Projects
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>
            <div class="col-md-3">
                <div class="stat-card students">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2>8</h2>
                            <h6>Assigned Students</h6>
                        </div>
                        <i class="fas fa-user-graduate fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card projects">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2>5</h2>
                            <h6>Active Projects</h6>
                        </div>
                        <i class="fas fa-project-diagram fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card reviews">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2>3</h2>
                            <h6>Pending Reviews</h6>
                        </div>
                        <i class="fas fa-file-alt fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card completed">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2>12</h2>
                            <h6>Completed</h6>
                        </div>
                        <i class="fas fa-check-circle fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Pending Actions -->
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Pending Submissions for Review</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Project Title</th>
                                        <th>Type</th>
                                        <th>Submitted</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-2" 
                                                     style="width: 30px; height: 30px;">
                                                    DA
                                                </div>
                                                <span>Daniel Arega</span>
                                            </div>
                                        </td>
                                        <td>E-Commerce Platform for Local Businesses</td>
                                        <td><span class="badge bg-primary">Proposal</span></td>
                                        <td>2 days ago</td>
                                        <td>
                                            <a href="#" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i> Review
                                            </a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center me-2" 
                                                     style="width: 30px; height: 30px;">
                                                    NT
                                                </div>
                                                <span>Nafyad Tesfaye</span>
                                            </div>
                                        </td>
                                        <td>Inventory Management System</td>
                                        <td><span class="badge bg-info">Chapter 2</span></td>
                                        <td>1 day ago</td>
                                        <td>
                                            <a href="#" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i> Review
                                            </a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="rounded-circle bg-warning text-white d-flex align-items-center justify-content-center me-2" 
                                                     style="width: 30px; height: 30px;">
                                                    EB
                                                </div>
                                                <span>Esrael Belete</span>
                                            </div>
                                        </td>
                                        <td>Student Performance Analysis</td>
                                        <td><span class="badge bg-warning">Progress Report</span></td>
                                        <td>4 hours ago</td>
                                        <td>
                                            <a href="#" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i> Review
                                            </a>
                                        </td>
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
                        <h5 class="mb-0">Upcoming Defenses</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <strong>Daniel Arega</strong>
                                <span class="badge bg-warning">Tomorrow</span>
                            </div>
                            <p class="mb-1 small">E-Commerce Platform for Local Businesses</p>
                            <small class="text-muted">
                                <i class="fas fa-clock"></i> 10:00 AM | 
                                <i class="fas fa-map-marker-alt"></i> Room 201
                            </small>
                        </div>
                        <hr>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <strong>Warkineh Lemma</strong>
                                <span class="badge bg-info">3 days</span>
                            </div>
                            <p class="mb-1 small">Online Examination System</p>
                            <small class="text-muted">
                                <i class="fas fa-clock"></i> 2:00 PM | 
                                <i class="fas fa-map-marker-alt"></i> Room 305
                            </small>
                        </div>
                        <hr>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <strong>Robsan Hailmikael</strong>
                                <span class="badge bg-success">1 week</span>
                            </div>
                            <p class="mb-1 small">Library Management System</p>
                            <small class="text-muted">
                                <i class="fas fa-clock"></i> 9:30 AM | 
                                <i class="fas fa-map-marker-alt"></i> Room 102
                            </small>
                        </div>
                    </div>
                </div>
                <!-- Add this to teacher/dashboard.php after the Upcoming Defenses section -->

<!-- Recent Notices Section -->
<div class="card mt-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-bullhorn me-2"></i>
            Department Notices
            <?php
            require_once '../includes/classes/NoticeManager.php';
            $noticeManager = new NoticeManager();
            
            // FIXED: Correct parameter order - user_id first, then role, then department_id
            $unreadCount = $noticeManager->getUnreadCount($user['user_id'], 'teacher', $user['department_id']);
            
            if ($unreadCount > 0): ?>
                <span class="badge bg-danger"><?php echo $unreadCount; ?> new</span>
            <?php endif; ?>
        </h5>
    </div>
    <div class="card-body">
        <?php 
        // Get department notices
        // If your getDepartmentNotices() only takes department_id:
        $allNotices = $noticeManager->getDepartmentNotices($user['department_id']);
        
        // If you updated getDepartmentNotices() to accept role and limit:
        // $notices = $noticeManager->getDepartmentNotices($user['department_id'], 'teacher', 3);
        
        // Simple workaround: filter and limit the notices
        $filteredNotices = array_filter($allNotices, function($notice) {
            // Show only notices for teachers or all users
            return in_array($notice['user_type'], ['teacher', 'all']);
        });
        $notices = array_slice($filteredNotices, 0, 3); // Get first 3
        
        if (empty($notices)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No notices at the moment.
            </div>
        <?php else: ?>
            <div class="list-group">
                <?php foreach ($notices as $notice): ?>
                <a href="notices.php?view=<?php echo $notice['id']; ?>" 
                   class="list-group-item list-group-item-action">
                    <div class="d-flex w-100 justify-content-between">
                        <h6 class="mb-1"><?php echo htmlspecialchars($notice['title']); ?></h6>
                        <small class="text-<?php echo $notice['priority'] === 'urgent' ? 'danger' : 'muted'; ?>">
                            <?php echo date('M d', strtotime($notice['created_at'])); ?>
                        </small>
                    </div>
                    <p class="mb-1 small">
                        <?php echo substr(htmlspecialchars($notice['content']), 0, 80); ?>...
                    </p>
                    <small>
                        <span class="badge bg-<?php echo $notice['priority'] === 'urgent' ? 'danger' : ($notice['priority'] === 'high' ? 'warning' : 'info'); ?>">
                            <?php echo ucfirst($notice['priority']); ?>
                        </span>
                        by <?php echo htmlspecialchars($notice['created_by_name'] ?? 'Admin'); ?>
                    </small>
                </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>       <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">Quick Links</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="submissions.php" class="btn btn-primary">
                                <i class="fas fa-file-upload me-2"></i> Check Submissions
                            </a>
                            <a href="feedback.php?action=new" class="btn btn-success">
                                <i class="fas fa-comment-dots me-2"></i> Give Feedback
                            </a>
                            <a href="calendar.php" class="btn btn-info">
                                <i class="fas fa-calendar-alt me-2"></i> View Calendar
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