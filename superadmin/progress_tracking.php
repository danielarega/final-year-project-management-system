<!-- C:\xampp\htdocs\fypms\final-year-project-management-system\superadmin\progress_tracking.php -->
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

// Get statistics for progress tracking
$stats = $historicalManager->getHistoricalStatistics();

// Calculate weighted progress metrics
$currentYear = date('Y');
$progressData = [];

try {
    $db = Database::getInstance()->getConnection();
    
    // Get active projects progress (this would integrate with active projects system)
    $activeQuery = "SELECT 
                   p.department_id,
                   d.dept_name,
                   COUNT(p.id) as active_projects,
                   AVG(p.progress_percentage) as avg_progress,
                   SUM(CASE WHEN p.progress_percentage >= 75 THEN 1 ELSE 0 END) as on_track,
                   SUM(CASE WHEN p.progress_percentage < 50 THEN 1 ELSE 0 END) as at_risk,
                   AVG(DATEDIFF(p.deadline, CURDATE())) as days_to_deadline
                   FROM projects p
                   LEFT JOIN departments d ON p.department_id = d.id
                   WHERE p.status = 'active'
                   GROUP BY p.department_id";
    
    $activeStmt = $db->query($activeQuery);
    $progressData['active'] = $activeStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get completion trends
    $trendQuery = "SELECT 
                  original_year as year,
                  COUNT(*) as total,
                  AVG(final_grade) as avg_grade,
                  AVG(TIMESTAMPDIFF(DAY, STR_TO_DATE(CONCAT(original_year, '-09-01'), '%Y-%m-%d'), completion_date)) as avg_completion_days
                  FROM historical_projects
                  WHERE original_year >= YEAR(CURDATE()) - 5
                  GROUP BY original_year
                  ORDER BY original_year";
    
    $trendStmt = $db->query($trendQuery);
    $progressData['trends'] = $trendStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get risk factors
    $riskQuery = "SELECT 
                 d.dept_name,
                 COUNT(DISTINCT CASE WHEN hp.final_status = 'failed' THEN hp.id END) as failed_count,
                 COUNT(DISTINCT hp.id) as total_count,
                 ROUND(COUNT(DISTINCT CASE WHEN hp.final_status = 'failed' THEN hp.id END) * 100.0 / COUNT(DISTINCT hp.id), 1) as failure_rate
                 FROM departments d
                 LEFT JOIN historical_projects hp ON d.id = hp.department_id
                 GROUP BY d.id
                 HAVING total_count > 0
                 ORDER BY failure_rate DESC";
    
    $riskStmt = $db->query($riskQuery);
    $progressData['risk_factors'] = $riskStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Progress tracking error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Progress Tracking & Analytics - FYPMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
    <style>
        .progress-tracker {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        .milestone-card {
            border-left: 5px solid #28a745;
            background: #f8fff9;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 8px;
        }
        .milestone-card.warning {
            border-left-color: #ffc107;
            background: #fffdf6;
        }
        .milestone-card.danger {
            border-left-color: #dc3545;
            background: #fff5f5;
        }
        .weight-badge {
            font-size: 12px;
            padding: 3px 8px;
            border-radius: 10px;
            margin-left: 5px;
        }
        .progress-visual {
            height: 20px;
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }
        .progress-segment {
            height: 100%;
            display: inline-block;
            float: left;
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
                    <i class="fas fa-chart-line"></i> Progress Tracking & Analytics
                </h3>
                <div>
                    <button class="btn btn-primary btn-sm" onclick="generateReport()">
                        <i class="fas fa-download"></i> Export Report
                    </button>
                </div>
            </div>
        </nav>
        
        <!-- Progress Overview -->
        <div class="progress-tracker">
            <div class="row">
                <div class="col-md-8">
                    <h2>Real-time Progress Monitoring</h2>
                    <p class="mb-0">Weighted milestone tracking with historical comparison</p>
                </div>
                <div class="col-md-4 text-end">
                    <h1 class="display-4"><?php echo date('Y'); ?></h1>
                    <small>Academic Year</small>
                </div>
            </div>
            
            <!-- Progress Visualization -->
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="progress-visual">
                        <div class="progress-segment" style="width: 10%; background: #3498db;" 
                             title="Title Submission (10%)"></div>
                        <div class="progress-segment" style="width: 15%; background: #2ecc71;" 
                             title="Proposal (15%)"></div>
                        <div class="progress-segment" style="width: 25%; background: #f39c12;" 
                             title="Documentation (25%)"></div>
                        <div class="progress-segment" style="width: 50%; background: #e74c3c;" 
                             title="Implementation (50%)"></div>
                    </div>
                    <div class="text-center mt-2">
                        <small>Semester 1 (50%) | Semester 2 (50%) | Total 100%</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Active Projects Progress -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-running"></i> Active Projects Progress
                            <span class="badge bg-primary">
                                <?php echo array_sum(array_column($progressData['active'] ?? [], 'active_projects')); ?> Active
                            </span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Department</th>
                                        <th>Active Projects</th>
                                        <th>Average Progress</th>
                                        <th>On Track</th>
                                        <th>At Risk</th>
                                        <th>Avg. Days to Deadline</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($progressData['active'])): ?>
                                        <?php foreach ($progressData['active'] as $deptProgress): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($deptProgress['dept_name']); ?></td>
                                            <td><?php echo $deptProgress['active_projects']; ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                                        <div class="progress-bar bg-<?php echo $deptProgress['avg_progress'] >= 70 ? 'success' : 
                                                                                       ($deptProgress['avg_progress'] >= 50 ? 'warning' : 'danger'); ?>" 
                                                             style="width: <?php echo $deptProgress['avg_progress']; ?>%"></div>
                                                    </div>
                                                    <span><?php echo round($deptProgress['avg_progress'], 1); ?>%</span>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-success"><?php echo $deptProgress['on_track']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-danger"><?php echo $deptProgress['at_risk']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $deptProgress['days_to_deadline'] > 30 ? 'success' : 
                                                                       ($deptProgress['days_to_deadline'] > 7 ? 'warning' : 'danger'); ?>">
                                                    <?php echo round($deptProgress['days_to_deadline']); ?> days
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">
                                                <i class="fas fa-info-circle"></i> No active projects data available
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <!-- Risk Assessment -->
                <div class="card mb-4">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="fas fa-exclamation-triangle"></i> Risk Assessment</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($progressData['risk_factors'])): ?>
                            <div class="list-group">
                                <?php foreach ($progressData['risk_factors'] as $risk): ?>
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($risk['dept_name']); ?></h6>
                                        <span class="badge bg-<?php echo $risk['failure_rate'] > 20 ? 'danger' : 
                                                               ($risk['failure_rate'] > 10 ? 'warning' : 'success'); ?>">
                                            <?php echo $risk['failure_rate']; ?>%
                                        </span>
                                    </div>
                                    <p class="mb-1 small">
                                        <?php echo $risk['failed_count']; ?> failed out of <?php echo $risk['total_count']; ?> projects
                                    </p>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No risk assessment data available
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Early Warning System -->
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="fas fa-bell"></i> Early Warnings</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <div class="list-group-item list-group-item-warning">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">Deadline Approaching</h6>
                                    <small>3 days</small>
                                </div>
                                <p class="mb-1 small">5 projects have deadlines within 7 days</p>
                            </div>
                            <div class="list-group-item list-group-item-danger">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">Progress Delayed</h6>
                                    <small>High</small>
                                </div>
                                <p class="mb-1 small">3 projects below 30% progress</p>
                            </div>
                            <div class="list-group-item list-group-item-info">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">Supervisor Overload</h6>
                                    <small>Medium</small>
                                </div>
                                <p class="mb-1 small">2 supervisors have 8+ projects</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Charts Section -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Completion Trends (Last 5 Years)</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="trendChart" height="250"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Milestone Completion Rate</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="milestoneChart" height="250"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Milestone Tracking -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-flag-checkered"></i> Weighted Milestone Tracking
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Semester 1 Milestones -->
                    <div class="col-md-6">
                        <div class="milestone-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6>
                                    <i class="fas fa-flag text-primary"></i> Title Submission
                                    <span class="weight-badge bg-primary">10%</span>
                                </h6>
                                <span class="badge bg-success">85% Complete</span>
                            </div>
                            <div class="progress mt-2" style="height: 10px;">
                                <div class="progress-bar bg-primary" style="width: 85%"></div>
                            </div>
                            <small class="text-muted">Average completion: 7 days before deadline</small>
                        </div>
                        
                        <div class="milestone-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6>
                                    <i class="fas fa-file-alt text-success"></i> Project Proposal
                                    <span class="weight-badge bg-success">15%</span>
                                </h6>
                                <span class="badge bg-warning">65% Complete</span>
                            </div>
                            <div class="progress mt-2" style="height: 10px;">
                                <div class="progress-bar bg-success" style="width: 65%"></div>
                            </div>
                            <small class="text-muted">Average completion: 3 days before deadline</small>
                        </div>
                        
                        <div class="milestone-card warning">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6>
                                    <i class="fas fa-book text-warning"></i> Documentation
                                    <span class="weight-badge bg-warning">25%</span>
                                </h6>
                                <span class="badge bg-danger">40% Complete</span>
                            </div>
                            <div class="progress mt-2" style="height: 10px;">
                                <div class="progress-bar bg-warning" style="width: 40%"></div>
                            </div>
                            <small class="text-muted">⚠️ 35% of projects behind schedule</small>
                        </div>
                    </div>
                    
                    <!-- Semester 2 Milestones -->
                    <div class="col-md-6">
                        <div class="milestone-card danger">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6>
                                    <i class="fas fa-code text-danger"></i> Implementation
                                    <span class="weight-badge bg-danger">50%</span>
                                </h6>
                                <span class="badge bg-danger">25% Complete</span>
                            </div>
                            <div class="progress mt-2" style="height: 10px;">
                                <div class="progress-bar bg-danger" style="width: 25%"></div>
                            </div>
                            <small class="text-muted">🚨 Critical milestone - requires attention</small>
                        </div>
                        
                        <div class="milestone-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6>
                                    <i class="fas fa-microphone-alt text-info"></i> Final Presentation
                                    <span class="weight-badge bg-info">30%</span>
                                </h6>
                                <span class="badge bg-secondary">Not Started</span>
                            </div>
                            <div class="progress mt-2" style="height: 10px;">
                                <div class="progress-bar bg-info" style="width: 0%"></div>
                            </div>
                            <small class="text-muted">Scheduled for next semester</small>
                        </div>
                        
                        <div class="milestone-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6>
                                    <i class="fas fa-comments text-secondary"></i> Viva Voce
                                    <span class="weight-badge bg-secondary">20%</span>
                                </h6>
                                <span class="badge bg-secondary">Not Started</span>
                            </div>
                            <div class="progress mt-2" style="height: 10px;">
                                <div class="progress-bar bg-secondary" style="width: 0%"></div>
                            </div>
                            <small class="text-muted">Scheduled for next semester</small>
                        </div>
                    </div>
                </div>
                
                <!-- Overall Progress -->
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card bg-light">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="mb-1">Overall Weighted Progress</h5>
                                        <p class="mb-0 text-muted">
                                            Based on milestone completion and historical averages
                                        </p>
                                    </div>
                                    <div class="text-end">
                                        <h2 class="mb-0">35%</h2>
                                        <small>Current Academic Year</small>
                                    </div>
                                </div>
                                <div class="progress mt-3" style="height: 20px;">
                                    <div class="progress-bar bg-success" style="width: 10%">Title</div>
                                    <div class="progress-bar bg-info" style="width: 10%">Proposal</div>
                                    <div class="progress-bar bg-warning" style="width: 15%">Documentation</div>
                                    <div class="progress-bar bg-danger" style="width: 65%">Implementation</div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-md-3">
                                        <small class="text-muted">
                                            <i class="fas fa-arrow-up text-success"></i>
                                            +5% vs last year
                                        </small>
                                    </div>
                                    <div class="col-md-3">
                                        <small class="text-muted">
                                            <i class="fas fa-clock text-warning"></i>
                                            12 days behind schedule
                                        </small>
                                    </div>
                                    <div class="col-md-3">
                                        <small class="text-muted">
                                            <i class="fas fa-chart-line text-primary"></i>
                                            On track for 78% completion
                                        </small>
                                    </div>
                                    <div class="col-md-3">
                                        <small class="text-muted">
                                            <i class="fas fa-exclamation-triangle text-danger"></i>
                                            High risk: Implementation
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Performance Comparison -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-bar"></i> Historical Performance Comparison
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <canvas id="comparisonChart" height="300"></canvas>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6>Key Performance Indicators</h6>
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Average Completion Time
                                        <span class="badge bg-primary">42 days</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Success Rate
                                        <span class="badge bg-success">85%</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Average Final Grade
                                        <span class="badge bg-info">78.5%</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Supervisor Satisfaction
                                        <span class="badge bg-warning">82%</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Student Satisfaction
                                        <span class="badge bg-success">88%</span>
                                    </li>
                                </ul>
                                <div class="mt-3">
                                    <small class="text-muted">
                                        <i class="fas fa-lightbulb"></i>
                                        <strong>Insight:</strong> Implementation phase remains the biggest challenge across all departments.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // Trend Chart
            var trendCtx = document.getElementById('trendChart').getContext('2d');
            var trendChart = new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: ['2019', '2020', '2021', '2022', '2023'],
                    datasets: [{
                        label: 'Average Grade',
                        data: [75.2, 76.8, 78.5, 77.9, 79.2],
                        borderColor: '#3498db',
                        backgroundColor: 'rgba(52, 152, 219, 0.1)',
                        borderWidth: 3,
                        yAxisID: 'y',
                        tension: 0.4
                    }, {
                        label: 'Completion Rate',
                        data: [82, 85, 88, 87, 90],
                        borderColor: '#2ecc71',
                        backgroundColor: 'rgba(46, 204, 113, 0.1)',
                        borderWidth: 3,
                        yAxisID: 'y1',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Average Grade'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Completion Rate (%)'
                            },
                            grid: {
                                drawOnChartArea: false,
                            },
                        }
                    }
                }
            });
            
            // Milestone Chart
            var milestoneCtx = document.getElementById('milestoneChart').getContext('2d');
            var milestoneChart = new Chart(milestoneCtx, {
                type: 'bar',
                data: {
                    labels: ['Title', 'Proposal', 'Documentation', 'Implementation', 'Presentation', 'Viva'],
                    datasets: [{
                        label: 'Current Completion',
                        data: [85, 65, 40, 25, 0, 0],
                        backgroundColor: '#3498db',
                        borderColor: '#2980b9',
                        borderWidth: 1
                    }, {
                        label: 'Target',
                        data: [100, 100, 100, 100, 100, 100],
                        backgroundColor: 'rgba(46, 204, 113, 0.3)',
                        borderColor: '#27ae60',
                        borderWidth: 1,
                        type: 'line',
                        fill: false
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            title: {
                                display: true,
                                text: 'Completion Percentage'
                            }
                        }
                    }
                }
            });
            
            // Comparison Chart
            var comparisonCtx = document.getElementById('comparisonChart').getContext('2d');
            var comparisonChart = new Chart(comparisonCtx, {
                type: 'bar',
                data: {
                    labels: ['Computer Science', 'Business', 'Accounting', 'Economics'],
                    datasets: [{
                        label: 'Current Year',
                        data: [82, 78, 76, 80],
                        backgroundColor: 'rgba(52, 152, 219, 0.8)',
                        borderColor: '#2980b9',
                        borderWidth: 2
                    }, {
                        label: '5-Year Average',
                        data: [78.5, 75.2, 73.8, 77.1],
                        backgroundColor: 'rgba(149, 165, 166, 0.8)',
                        borderColor: '#7f8c8d',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: false,
                            min: 60,
                            max: 100,
                            title: {
                                display: true,
                                text: 'Average Grade (%)'
                            }
                        }
                    }
                }
            });
            
            // Export report
            window.generateReport = function() {
                alert('Generating comprehensive progress report...\n\nThis would generate a PDF report with all analytics and recommendations.');
            };
        });
    </script>
</body>
</html>