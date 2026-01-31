<!-- C:\xampp\htdocs\fypms\final-year-project-management-system\superadmin\historical_data.php -->
<?php
require_once '../includes/config/constants.php';
require_once '../includes/classes/Database.php';
require_once '../includes/classes/Session.php';
require_once '../includes/classes/Auth.php';
require_once '../includes/classes/HistoricalDataManager.php';
require_once '../includes/classes/DepartmentManager.php';

Session::init();
$auth = new Auth();
$auth->requireRole(['superadmin']);

$user = $auth->getUser();
$historicalManager = new HistoricalDataManager();
$deptManager = new DepartmentManager();

// Get departments
$departments = $deptManager->getAllDepartments();

// Handle actions
$action = $_GET['action'] ?? '';
$message = '';
$error = '';
$importResult = null;

// Handle CSV import
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['import_historical'])) {
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['csv_file']['tmp_name'];
            $departmentId = $_POST['department_id'];
            $academicYear = $_POST['academic_year'];
            
            $result = $historicalManager->importHistoricalCSV($fileTmpPath, $departmentId, $academicYear, $user['user_id']);
            
            if ($result['success']) {
                $message = $result['message'];
                $importResult = $result;
            } else {
                $error = $result['message'];
            }
        } else {
            $error = 'Please select a valid CSV file';
        }
    }
    
    if (isset($_POST['add_manual_project'])) {
        $data = [
            'original_year' => $_POST['original_year'],
            'department_id' => $_POST['department_id'],
            'project_title' => $_POST['project_title'],
            'student_names' => $_POST['student_names'],
            'student_ids' => $_POST['student_ids'],
            'supervisor_name' => $_POST['supervisor_name'],
            'supervisor_id' => $_POST['supervisor_id'] ?? null,
            'examiner_name' => $_POST['examiner_name'] ?? null,
            'examiner_id' => $_POST['examiner_id'] ?? null,
            'completion_date' => $_POST['completion_date'],
            'archived_by' => $user['user_id']
        ];
        
        $result = $historicalManager->addHistoricalProject($data);
        
        if ($result['success']) {
            $message = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
}

// Get statistics
$stats = $historicalManager->getHistoricalStatistics();

// Search parameters
$searchParams = [
    'year_from' => $_GET['year_from'] ?? 2010,
    'year_to' => $_GET['year_to'] ?? date('Y'),
    'department_id' => $_GET['department_id'] ?? null,
    'search_text' => $_GET['search'] ?? '',
    'limit' => $_GET['limit'] ?? 50,
    'offset' => $_GET['offset'] ?? 0
];

// Perform search
$searchResults = $historicalManager->searchHistoricalProjects($searchParams);
$historicalProjects = $searchResults['success'] ? $searchResults['data'] : [];
$totalProjects = $searchResults['success'] ? $searchResults['total'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historical Data Archive - FYPMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .archive-header {
            background: linear-gradient(135deg, #2c3e50 0%, #4a6491 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .timeline-bar {
            height: 10px;
            background: linear-gradient(90deg, #3498db, #2ecc71, #e74c3c, #f39c12);
            border-radius: 5px;
            margin: 20px 0;
            position: relative;
        }
        .year-marker {
            position: absolute;
            top: 15px;
            transform: translateX(-50%);
            font-size: 12px;
            color: #666;
        }
        .stat-card {
            border-radius: 10px;
            padding: 20px;
            color: white;
            margin-bottom: 20px;
        }
        .search-panel {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include 'dashboard.php'; ?>
    
    <div class="main-content">
        <!-- Header -->
        <nav class="navbar navbar-light bg-light mb-4 rounded">
            <div class="container-fluid">
                <h3 class="mb-0">
                    <i class="fas fa-archive"></i> Historical Data Archive System
                    <small class="text-muted">(2010-2025)</small>
                </h3>
                <div>
                    <button class="btn btn-primary btn-sm me-2" data-bs-toggle="modal" data-bs-target="#importModal">
                        <i class="fas fa-file-import"></i> Import Data
                    </button>
                    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addProjectModal">
                        <i class="fas fa-plus-circle"></i> Add Manual Entry
                    </button>
                </div>
            </div>
        </nav>
        
        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <h5><i class="fas fa-check-circle"></i> Success!</h5>
                <p><?php echo htmlspecialchars($message); ?></p>
                <?php if ($importResult): ?>
                    <div class="mt-2">
                        <strong>Import Details:</strong><br>
                        ✓ Successful: <?php echo $importResult['stats']['success']; ?><br>
                        ✗ Failed: <?php echo $importResult['stats']['failed']; ?>
                    </div>
                <?php endif; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Archive Timeline -->
        <div class="archive-header">
            <h2><i class="fas fa-history"></i> 15-Year Historical Archive</h2>
            <p class="mb-0">Complete archive of final year projects from 2010 to present</p>
            
            <div class="timeline-bar mt-4">
                <?php 
                $startYear = 2010;
                $endYear = date('Y');
                for ($year = $startYear; $year <= $endYear; $year += 5): 
                    $position = (($year - $startYear) / ($endYear - $startYear)) * 100;
                ?>
                <div class="year-marker" style="left: <?php echo $position; ?>%">
                    <?php echo $year; ?>
                </div>
                <?php endfor; ?>
            </div>
            
            <div class="row mt-4">
                <div class="col-md-3 text-center">
                    <h1><?php echo $stats['data']['overall']['total_projects'] ?? 0; ?></h1>
                    <p>Total Projects Archived</p>
                </div>
                <div class="col-md-3 text-center">
                    <h1><?php echo $stats['data']['overall']['earliest_year'] ?? '2010'; ?></h1>
                    <p>Earliest Year</p>
                </div>
                <div class="col-md-3 text-center">
                    <h1><?php echo round($stats['data']['overall']['avg_grade'] ?? 0, 1); ?></h1>
                    <p>Average Grade</p>
                </div>
                <div class="col-md-3 text-center">
                    <h1><?php echo $stats['data']['overall']['completed_projects'] ?? 0; ?></h1>
                    <p>Completed Projects</p>
                </div>
            </div>
        </div>
        
        <!-- Search Panel -->
        <div class="search-panel">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">From Year</label>
                    <select class="form-control" name="year_from">
                        <?php for ($year = 2010; $year <= date('Y'); $year++): ?>
                        <option value="<?php echo $year; ?>" <?php echo $year == $searchParams['year_from'] ? 'selected' : ''; ?>>
                            <?php echo $year; ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">To Year</label>
                    <select class="form-control" name="year_to">
                        <?php for ($year = 2010; $year <= date('Y'); $year++): ?>
                        <option value="<?php echo $year; ?>" <?php echo $year == $searchParams['year_to'] ? 'selected' : ''; ?>>
                            <?php echo $year; ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Department</label>
                    <select class="form-control" name="department_id">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo $dept['id']; ?>" 
                            <?php echo $dept['id'] == $searchParams['department_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dept['dept_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Search Text</label>
                    <div class="input-group">
                        <input type="text" class="form-control" name="search" 
                               placeholder="Title, Students, Supervisor..." 
                               value="<?php echo htmlspecialchars($searchParams['search_text']); ?>">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3><?php echo $stats['data']['overall']['total_projects'] ?? 0; ?></h3>
                            <h6>Total Projects</h6>
                        </div>
                        <i class="fas fa-project-diagram fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3><?php echo $stats['data']['overall']['completed_projects'] ?? 0; ?></h3>
                            <h6>Completed</h6>
                        </div>
                        <i class="fas fa-check-circle fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3><?php echo $stats['data']['overall']['failed_projects'] ?? 0; ?></h3>
                            <h6>Failed</h6>
                        </div>
                        <i class="fas fa-times-circle fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #f39c12 0%, #d35400 100%);">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3><?php echo count($departments); ?></h3>
                            <h6>Departments</h6>
                        </div>
                        <i class="fas fa-building fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Charts -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Projects by Year</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="yearlyChart" height="200"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Grade Distribution</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="gradeChart" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Historical Projects Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    Historical Projects 
                    <span class="badge bg-primary"><?php echo $totalProjects; ?> total</span>
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="historicalTable" class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Project Code</th>
                                <th>Year</th>
                                <th>Department</th>
                                <th>Project Title</th>
                                <th>Students</th>
                                <th>Supervisor</th>
                                <th>Final Grade</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historicalProjects as $index => $project): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td>
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($project['project_code']); ?></span>
                                </td>
                                <td><?php echo $project['original_year']; ?></td>
                                <td>
                                    <span class="badge bg-info"><?php echo htmlspecialchars($project['dept_code']); ?></span>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($project['project_title']); ?></strong>
                                </td>
                                <td>
                                    <small><?php echo htmlspecialchars($project['student_names']); ?></small>
                                </td>
                                <td>
                                    <?php if ($project['supervisor_name']): ?>
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($project['supervisor_name']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($project['final_grade']): ?>
                                    <span class="badge bg-<?php echo $project['final_grade'] >= 50 ? 'success' : 'danger'; ?>">
                                        <?php echo $project['final_grade']; ?>%
                                    </span>
                                    <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $project['final_status'] == 'completed' ? 'success' : 
                                                           ($project['final_status'] == 'failed' ? 'danger' : 'warning'); ?>">
                                        <?php echo ucfirst($project['final_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="historical_details.php?code=<?php echo $project['project_code']; ?>" 
                                           class="btn btn-outline-primary" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button class="btn btn-outline-info" title="Similar Projects"
                                                onclick="findSimilarProjects('<?php echo addslashes($project['project_title']); ?>', <?php echo $project['department_id']; ?>)">
                                            <i class="fas fa-clone"></i>
                                        </button>
                                        <a href="historical_edit.php?code=<?php echo $project['project_code']; ?>" 
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
                
                <!-- Pagination -->
                <?php if ($totalProjects > $searchParams['limit']): ?>
                <nav aria-label="Page navigation" class="mt-3">
                    <ul class="pagination justify-content-center">
                        <?php
                        $totalPages = ceil($totalProjects / $searchParams['limit']);
                        $currentPage = floor($searchParams['offset'] / $searchParams['limit']) + 1;
                        
                        // Previous button
                        if ($currentPage > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php 
                                echo http_build_query(array_merge($searchParams, ['offset' => ($currentPage - 2) * $searchParams['limit']]));
                            ?>">Previous</a>
                        </li>
                        <?php endif;
                        
                        // Page numbers
                        for ($page = 1; $page <= $totalPages; $page++):
                            if ($page == $currentPage): ?>
                            <li class="page-item active">
                                <span class="page-link"><?php echo $page; ?></span>
                            </li>
                            <?php else: ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php 
                                    echo http_build_query(array_merge($searchParams, ['offset' => ($page - 1) * $searchParams['limit']]));
                                ?>"><?php echo $page; ?></a>
                            </li>
                            <?php endif;
                        endfor;
                        
                        // Next button
                        if ($currentPage < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php 
                                echo http_build_query(array_merge($searchParams, ['offset' => $currentPage * $searchParams['limit']]));
                            ?>">Next</a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Import Modal -->
    <div class="modal fade" id="importModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-file-import"></i> Import Historical Data
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Academic Year</label>
                                <select class="form-control" name="academic_year" required>
                                    <option value="">-- Select Year --</option>
                                    <?php for ($year = 2010; $year <= 2025; $year++): ?>
                                    <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Department</label>
                                <select class="form-control" name="department_id" required>
                                    <option value="">-- Select Department --</option>
                                    <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>">
                                        <?php echo htmlspecialchars($dept['dept_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Upload CSV File</label>
                            <div class="input-group">
                                <input type="file" class="form-control" name="csv_file" accept=".csv" required>
                            </div>
                            <small class="text-muted">
                                CSV format: project_title,student_names,student_ids,supervisor_name,supervisor_id,
                                examiner_name,examiner_id,completion_date,title_grade,proposal_grade,etc.
                            </small>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Note:</strong> For bulk import of historical data (2010-2025). 
                            Each row represents one historical project.
                        </div>
                        
                        <div class="text-center">
                            <a href="javascript:void(0)" onclick="downloadHistoricalTemplate()" 
                               class="btn btn-sm btn-outline-success">
                                <i class="fas fa-download"></i> Download CSV Template
                            </a>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="import_historical" class="btn btn-primary">
                            <i class="fas fa-upload"></i> Start Import
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Add Project Modal -->
    <div class="modal fade" id="addProjectModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-plus-circle"></i> Add Historical Project
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Academic Year *</label>
                                <select class="form-control" name="original_year" required>
                                    <option value="">-- Select Year --</option>
                                    <?php for ($year = 2010; $year <= 2025; $year++): ?>
                                    <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Department *</label>
                                <select class="form-control" name="department_id" required>
                                    <option value="">-- Select Department --</option>
                                    <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>">
                                        <?php echo htmlspecialchars($dept['dept_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Project Title *</label>
                            <input type="text" class="form-control" name="project_title" 
                                   placeholder="Enter project title" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Student Names *</label>
                                <textarea class="form-control" name="student_names" rows="2" 
                                          placeholder="Comma-separated student names" required></textarea>
                                <small class="text-muted">e.g., Daniel Arega, Nafyad Tesfaye</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Student IDs *</label>
                                <textarea class="form-control" name="student_ids" rows="2" 
                                          placeholder="Comma-separated student IDs" required></textarea>
                                <small class="text-muted">e.g., UGR13610, UGR13611</small>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Supervisor Name *</label>
                                <input type="text" class="form-control" name="supervisor_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Completion Date</label>
                                <input type="date" class="form-control" name="completion_date">
                            </div>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Note:</strong> This entry will be verified before being added to the official archive.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_manual_project" class="btn btn-success">
                            <i class="fas fa-save"></i> Add to Archive
                        </button>
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
            $('#historicalTable').DataTable({
                pageLength: 10,
                responsive: true,
                order: [[1, 'desc']] // Sort by year descending
            });
            
            // Charts
            <?php if ($stats['success']): ?>
            // Yearly Chart
            var yearlyCtx = document.getElementById('yearlyChart').getContext('2d');
            var yearlyLabels = <?php echo json_encode(array_column($stats['data']['by_year'], 'year')); ?>;
            var yearlyData = <?php echo json_encode(array_column($stats['data']['by_year'], 'count')); ?>;
            
            var yearlyChart = new Chart(yearlyCtx, {
                type: 'line',
                data: {
                    labels: yearlyLabels.reverse(),
                    datasets: [{
                        label: 'Projects per Year',
                        data: yearlyData.reverse(),
                        borderColor: '#3498db',
                        backgroundColor: 'rgba(52, 152, 219, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
            
            // Grade Distribution Chart
            var gradeCtx = document.getElementById('gradeChart').getContext('2d');
            var gradeLabels = <?php echo json_encode(array_column($stats['data']['grade_distribution'], 'grade_range')); ?>;
            var gradeData = <?php echo json_encode(array_column($stats['data']['grade_distribution'], 'count')); ?>;
            
            var gradeChart = new Chart(gradeCtx, {
                type: 'bar',
                data: {
                    labels: gradeLabels,
                    datasets: [{
                        label: 'Number of Projects',
                        data: gradeData,
                        backgroundColor: [
                            '#2ecc71', '#27ae60', '#3498db', '#2980b9', 
                            '#9b59b6', '#8e44ad', '#f1c40f', '#f39c12', 
                            '#e74c3c', '#c0392b'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
            <?php endif; ?>
            
            // Download CSV template
            window.downloadHistoricalTemplate = function() {
                const csvContent = "project_title,student_names,student_ids,supervisor_name,supervisor_id,examiner_name,examiner_id,completion_date,title_grade,proposal_grade,documentation_grade,presentation_grade,advisor_evaluation,implementation_grade,final_presentation_grade,viva_voce_grade\n" +
                    "Online Shopping System for Local Businesses,Daniel Arega,Nafyad Tesfaye,UGR13610,UGR13611,Mr. Duressa Deksiso,1,,2015-06-15,85,82,88,90,87,84,86,88\n" +
                    "Student Management System,Warkineh Lemma,Robsan Hailmikael,UGR13612,UGR13613,Mr. Duressa Deksiso,1,,2016-06-20,78,75,80,82,79,76,78,80\n" +
                    "IoT Based Smart Agriculture,Esrael Belete,Yohannes Tadesse,UGR13616,UGR13617,Mrs. Helen Mekonnen,2,,2018-06-22,90,88,92,94,91,89,91,93";
                
                const blob = new Blob([csvContent], { type: 'text/csv' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'historical_data_template.csv';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
            };
            
            // Find similar projects
            window.findSimilarProjects = function(title, departmentId) {
                alert('Searching for similar projects to: ' + title + '\n\nThis feature would show projects with similar titles to prevent duplication.');
            };
        });
    </script>
</body>
</html>