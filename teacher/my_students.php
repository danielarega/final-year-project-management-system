<?php
require_once '../includes/config/constants.php';
require_once '../includes/classes/Database.php';
require_once '../includes/classes/Session.php';
require_once '../includes/classes/Auth.php';
require_once '../includes/classes/SupervisorManager.php';

Session::init();
$auth = new Auth();
$auth->requireRole(['teacher']);

$user = $auth->getUser();
$supervisorManager = new SupervisorManager();

// Get teacher's assigned projects
$teacherProjects = $supervisorManager->getTeacherProjects($user['user_id']);

// Group projects by status
$projectsByStatus = [
    'approved' => array_filter($teacherProjects, function($p) { return $p['status'] === 'approved'; }),
    'in_progress' => array_filter($teacherProjects, function($p) { return $p['status'] === 'in_progress'; }),
    'completed' => array_filter($teacherProjects, function($p) { return $p['status'] === 'completed'; })
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Students - FYPMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
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
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
        }
        .status-approved { background: #d4edda; color: #155724; }
        .status-in_progress { background: #d1ecf1; color: #0c5460; }
        .status-completed { background: #c3e6cb; color: #155724; }
    </style>
</head>
<body>
    <!-- Teacher Sidebar -->
    <div class="sidebar">
        <div class="p-4">
            <h4 class="text-center">FYPMS</h4>
            <hr class="bg-white">
            <div class="text-center mb-4">
                <div class="rounded-circle bg-white d-inline-flex align-items-center justify-content-center" 
                     style="width: 60px; height: 60px;">
                    <i class="fas fa-chalkboard-teacher" style="color: #667eea;"></i>
                </div>
                <h6 class="mt-2"><?php echo htmlspecialchars($user['full_name']); ?></h6>
                <small>Teacher / Supervisor</small>
            </div>
            
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="my_students.php">
                        <i class="fas fa-user-graduate me-2"></i> My Students
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="submissions.php">
                        <i class="fas fa-file-upload me-2"></i> Submissions
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
                    <a href="dashboard.php" class="btn btn-outline-primary btn-sm me-2">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <h3 class="mb-0 d-inline">My Students</h3>
                </div>
                <div>
                    <span class="badge bg-primary">
                        Total: <?php echo count($teacherProjects); ?> Students
                    </span>
                </div>
            </div>
        </nav>
        
        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3><?php echo count($teacherProjects); ?></h3>
                                <h6>Total Students</h6>
                            </div>
                            <i class="fas fa-users fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3><?php echo count($projectsByStatus['approved']); ?></h3>
                                <h6>Approved</h6>
                            </div>
                            <i class="fas fa-check-circle fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3><?php echo count($projectsByStatus['in_progress']); ?></h3>
                                <h6>In Progress</h6>
                            </div>
                            <i class="fas fa-spinner fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-dark">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3><?php echo count($projectsByStatus['completed']); ?></h3>
                                <h6>Completed</h6>
                            </div>
                            <i class="fas fa-flag-checkered fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Students List -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">All Assigned Students</h5>
            </div>
            <div class="card-body">
                <?php if (empty($teacherProjects)): ?>
                    <div class="alert alert-info text-center py-4">
                        <i class="fas fa-user-graduate fa-3x mb-3"></i>
                        <h4>No Students Assigned</h4>
                        <p class="mb-0">You don't have any students assigned to you yet.</p>
                    </div>
                <?php else: ?>
                    <!-- Tabs for different statuses -->
                    <ul class="nav nav-tabs" id="studentsTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="all-tab" data-bs-toggle="tab" 
                                    data-bs-target="#all" type="button">
                                All (<?php echo count($teacherProjects); ?>)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="approved-tab" data-bs-toggle="tab" 
                                    data-bs-target="#approved" type="button">
                                Approved (<?php echo count($projectsByStatus['approved']); ?>)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="inprogress-tab" data-bs-toggle="tab" 
                                    data-bs-target="#inprogress" type="button">
                                In Progress (<?php echo count($projectsByStatus['in_progress']); ?>)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="completed-tab" data-bs-toggle="tab" 
                                    data-bs-target="#completed" type="button">
                                Completed (<?php echo count($projectsByStatus['completed']); ?>)
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content p-3" id="studentsTabContent">
                        <!-- All Students Tab -->
                        <div class="tab-pane fade show active" id="all" role="tabpanel">
                            <div class="row">
                                <?php foreach ($teacherProjects as $project): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="student-card">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <h6><?php echo htmlspecialchars($project['student_name']); ?></h6>
                                                <p class="mb-1 small text-muted">
                                                    ID: <?php echo htmlspecialchars($project['student_id']); ?>
                                                </p>
                                            </div>
                                            <span class="status-badge status-<?php echo $project['status']; ?>">
                                                <?php echo str_replace('_', ' ', ucfirst($project['status'])); ?>
                                            </span>
                                        </div>
                                        
                                        <h6 class="mb-2"><?php echo htmlspecialchars($project['title']); ?></h6>
                                        
                                        <div class="row mb-2">
                                            <div class="col-6">
                                                <small>
                                                    <i class="fas fa-calendar"></i> 
                                                    <?php echo date('M d, Y', strtotime($project['submitted_at'])); ?>
                                                </small>
                                            </div>
                                            <div class="col-6 text-end">
                                                <small>
                                                    <i class="fas fa-users"></i> 
                                                    <?php echo $project['group_name'] ? htmlspecialchars($project['group_name']) : 'Individual'; ?>
                                                </small>
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex gap-2">
                                            <a href="submissions.php?student_id=<?php echo $project['student_id']; ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-file-upload"></i> View Submissions
                                            </a>
                                            <a href="feedback.php?project_id=<?php echo $project['id']; ?>" 
                                               class="btn btn-sm btn-outline-success">
                                                <i class="fas fa-comment"></i> Give Feedback
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Approved Tab -->
                        <div class="tab-pane fade" id="approved" role="tabpanel">
                            <?php if (empty($projectsByStatus['approved'])): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> No approved projects yet.
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($projectsByStatus['approved'] as $project): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="student-card">
                                            <h6><?php echo htmlspecialchars($project['student_name']); ?></h6>
                                            <p class="mb-2 small text-muted">
                                                ID: <?php echo htmlspecialchars($project['student_id']); ?>
                                            </p>
                                            <h6 class="mb-2"><?php echo htmlspecialchars($project['title']); ?></h6>
                                            <div class="alert alert-info">
                                                <small>
                                                    <i class="fas fa-info-circle"></i>
                                                    Title approved. Waiting for student to start documentation.
                                                </small>
                                            </div>
                                            <a href="submissions.php?student_id=<?php echo $project['student_id']; ?>" 
                                               class="btn btn-sm btn-primary">
                                                <i class="fas fa-eye"></i> View Details
                                            </a>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- In Progress Tab -->
                        <div class="tab-pane fade" id="inprogress" role="tabpanel">
                            <?php if (empty($projectsByStatus['in_progress'])): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> No projects in progress.
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($projectsByStatus['in_progress'] as $project): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="student-card">
                                            <h6><?php echo htmlspecialchars($project['student_name']); ?></h6>
                                            <p class="mb-2 small text-muted">
                                                ID: <?php echo htmlspecialchars($project['student_id']); ?>
                                            </p>
                                            <h6 class="mb-2"><?php echo htmlspecialchars($project['title']); ?></h6>
                                            <div class="alert alert-warning">
                                                <small>
                                                    <i class="fas fa-clock"></i>
                                                    Project documentation in progress.
                                                </small>
                                            </div>
                                            <a href="submissions.php?student_id=<?php echo $project['student_id']; ?>" 
                                               class="btn btn-sm btn-warning">
                                                <i class="fas fa-upload"></i> Check Submissions
                                            </a>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Completed Tab -->
                        <div class="tab-pane fade" id="completed" role="tabpanel">
                            <?php if (empty($projectsByStatus['completed'])): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> No completed projects.
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($projectsByStatus['completed'] as $project): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="student-card">
                                            <h6><?php echo htmlspecialchars($project['student_name']); ?></h6>
                                            <p class="mb-2 small text-muted">
                                                ID: <?php echo htmlspecialchars($project['student_id']); ?>
                                            </p>
                                            <h6 class="mb-2"><?php echo htmlspecialchars($project['title']); ?></h6>
                                            <div class="alert alert-success">
                                                <small>
                                                    <i class="fas fa-check-circle"></i>
                                                    Project completed successfully.
                                                </small>
                                            </div>
                                            <a href="feedback.php?project_id=<?php echo $project['id']; ?>" 
                                               class="btn btn-sm btn-success">
                                                <i class="fas fa-history"></i> View History
                                            </a>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Quick Stats -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">Supervisor Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($user['full_name']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                        <p><strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Total Students:</strong> <?php echo count($teacherProjects); ?></p>
                        <p><strong>Current Load:</strong> <?php echo count($teacherProjects); ?> students</p>
                        <p><strong>Status:</strong> <span class="badge bg-success">Active</span></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>