<!-- C:\xampp\htdocs\fypms\final-year-project-management-system\admin\department_config.php -->
<?php
require_once '../includes/config/constants.php';
require_once '../includes/classes/Database.php';
require_once '../includes/classes/Session.php';
require_once '../includes/classes/Auth.php';
require_once '../includes/classes/DepartmentManager.php';
require_once '../includes/classes/BatchManager.php';

Session::init();
$auth = new Auth();
$auth->requireRole(['admin']);

$user = $auth->getUser();
$deptManager = new DepartmentManager();
$batchManager = new BatchManager();

// Get department details
$department = $deptManager->getDepartmentById($user['department_id']);
$deptConfig = $batchManager->getDepartmentConfig($user['department_id']);
$advancedStats = $batchManager->getDepartmentAdvancedStats($user['department_id']);

// Handle configuration updates
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_config'])) {
        $data = [
            'min_group_size' => $_POST['min_group_size'],
            'max_group_size' => $_POST['max_group_size'],
            'grading_template' => $_POST['grading_template'],
            'submission_requirements' => $_POST['submission_requirements']
        ];
        
        $result = $batchManager->updateDepartmentConfig($user['department_id'], $data);
        
        if ($result) {
            $message = 'Department configuration updated successfully';
            // Refresh config
            $deptConfig = $batchManager->getDepartmentConfig($user['department_id']);
        } else {
            $error = 'Failed to update configuration';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Configuration - FYPMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'dashboard.php'; ?>
    
    <div class="main-content">
        <nav class="navbar navbar-light bg-light mb-4 rounded">
            <div class="container-fluid">
                <h3 class="mb-0">Department Configuration</h3>
                <div>
                    <a href="dashboard.php" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-arrow-left"></i> Dashboard
                    </a>
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
        
        <!-- Department Header -->
        <div class="card mb-4" style="border-left: 5px solid #667eea;">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <h3><?php echo htmlspecialchars($department['dept_name']); ?></h3>
                        <p class="mb-1">
                            <i class="fas fa-code me-2"></i> <strong>Code:</strong> <?php echo htmlspecialchars($department['dept_code']); ?> | 
                            <i class="fas fa-tag me-2"></i> <strong>Type:</strong> <?php echo ucfirst($department['dept_type']); ?>
                        </p>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="badge bg-primary p-2">
                            <i class="fas fa-cog"></i> Configuration Panel
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-8">
                <!-- Configuration Form -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-sliders-h"></i> Department Settings</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h6 class="mb-0">Group Size Configuration</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label">Minimum Students per Group</label>
                                                <input type="number" class="form-control" name="min_group_size" 
                                                       value="<?php echo $deptConfig['min_group_size'] ?? 1; ?>" min="1" max="10">
                                                <small class="text-muted">Minimum number of students required per project group</small>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Maximum Students per Group</label>
                                                <input type="number" class="form-control" name="max_group_size" 
                                                       value="<?php echo $deptConfig['max_group_size'] ?? 3; ?>" min="1" max="10">
                                                <small class="text-muted">Maximum number of students allowed per project group</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h6 class="mb-0">Default Values by Department Type</h6>
                                        </div>
                                        <div class="card-body">
                                            <ul class="list-group list-group-flush">
                                                <li class="list-group-item">
                                                    <i class="fas fa-laptop-code text-primary"></i>
                                                    <strong>Technology Departments:</strong> 3-5 students per group
                                                </li>
                                                <li class="list-group-item">
                                                    <i class="fas fa-chart-line text-success"></i>
                                                    <strong>Business Departments:</strong> 1-3 students per group
                                                </li>
                                                <li class="list-group-item">
                                                    <i class="fas fa-money-bill-wave text-warning"></i>
                                                    <strong>Economics Departments:</strong> 1-3 students per group
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">Grading Template</label>
                                <textarea class="form-control" name="grading_template" rows="4" 
                                          placeholder="Define grading criteria and rubrics..."><?php echo htmlspecialchars($deptConfig['grading_template'] ?? ''); ?></textarea>
                                <small class="text-muted">Define the grading criteria, rubrics, and weight distribution</small>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">Submission Requirements</label>
                                <textarea class="form-control" name="submission_requirements" rows="4" 
                                          placeholder="List required documents and formats..."><?php echo htmlspecialchars($deptConfig['submission_requirements'] ?? ''); ?></textarea>
                                <small class="text-muted">Specify required documents, formats, and submission guidelines</small>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" name="update_config" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save"></i> Save Configuration
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <!-- Advanced Statistics -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Department Statistics</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($advancedStats): ?>
                        <div class="text-center mb-4">
                            <h1><?php echo $advancedStats['student_count'] ?? 0; ?></h1>
                            <p class="text-muted">Total Students</p>
                        </div>
                        
                        <div class="row text-center mb-4">
                            <div class="col-6">
                                <div class="p-3 bg-light rounded">
                                    <h4><?php echo $advancedStats['batch_count'] ?? 0; ?></h4>
                                    <small>Active Batches</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-3 bg-light rounded">
                                    <h4><?php echo $advancedStats['teacher_count'] ?? 0; ?></h4>
                                    <small>Teachers</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Project Completion Rate</span>
                                <span>
                                    <?php 
                                    $totalProjects = $advancedStats['project_count'] ?? 0;
                                    $completed = $advancedStats['completed_projects'] ?? 0;
                                    $completionRate = $totalProjects > 0 ? ($completed / $totalProjects * 100) : 0;
                                    echo round($completionRate, 1); ?>%
                                </span>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar bg-success" style="width: <?php echo $completionRate; ?>%"></div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Unassigned Students</span>
                                <span><?php echo $advancedStats['unassigned_students'] ?? 0; ?></span>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <?php 
                                $totalStudents = $advancedStats['student_count'] ?? 1;
                                $unassignedPercent = ($advancedStats['unassigned_students'] ?? 0) / $totalStudents * 100;
                                ?>
                                <div class="progress-bar bg-warning" style="width: <?php echo $unassignedPercent; ?>%"></div>
                            </div>
                        </div>
                        
                        <div class="text-center mt-4">
                            <small class="text-muted">
                                <i class="fas fa-sync-alt"></i> Last updated: <?php echo date('M d, Y H:i'); ?>
                            </small>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Statistics will be available after data collection.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Configuration Summary -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list-check"></i> Current Configuration</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Group Size Range
                                <span class="badge bg-primary">
                                    <?php echo $deptConfig['min_group_size'] ?? 1; ?>-<?php echo $deptConfig['max_group_size'] ?? 3; ?>
                                </span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Department Type
                                <span class="badge bg-info"><?php echo ucfirst($department['dept_type']); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Configuration Status
                                <span class="badge bg-<?php echo $deptConfig ? 'success' : 'warning'; ?>">
                                    <?php echo $deptConfig ? 'Configured' : 'Pending'; ?>
                                </span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Last Updated
                                <small class="text-muted">
                                    <?php echo $deptConfig['updated_at'] ? date('M d, Y', strtotime($deptConfig['updated_at'])) : 'Never'; ?>
                                </small>
                            </li>
                        </ul>
                        <div class="mt-3 text-center">
                            <a href="department_guidelines.php" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-book"></i> View Guidelines
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