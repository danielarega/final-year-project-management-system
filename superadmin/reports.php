<!-- superadmin/reports.php -->
<?php
require_once '../includes/config/constants.php';
require_once '../includes/classes/Database.php';
require_once '../includes/classes/Session.php';
require_once '../includes/classes/Auth.php';
require_once '../includes/classes/DepartmentManager.php';
require_once '../includes/classes/BatchManager.php';

Session::init();
$auth = new Auth();
$auth->requireRole(['superadmin']);

$user = $auth->getUser();
$deptManager = new DepartmentManager();
$batchManager = new BatchManager();

// Get department statistics
$deptStats = $deptManager->getDepartmentStatistics();
$batchStats = $batchManager->getBatchStatistics();

// Calculate totals
$totalDepartments = count($deptStats);
$totalAdmins = array_sum(array_column($deptStats, 'admin_count'));
$totalTeachers = array_sum(array_column($deptStats, 'teacher_count'));
$totalStudents = array_sum(array_column($deptStats, 'student_count'));
$totalBatches = array_sum(array_column($deptStats, 'batch_count'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - FYPMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stat-card {
            border-radius: 10px;
            padding: 20px;
            color: white;
            margin-bottom: 20px;
        }
        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <?php include 'dashboard.php'; ?>
    
    <div class="main-content">
        <nav class="navbar navbar-light bg-light mb-4 rounded">
            <div class="container-fluid">
                <h3 class="mb-0">System Reports & Analytics</h3>
                <div>
                    <button class="btn btn-primary btn-sm" onclick="window.print()">
                        <i class="fas fa-print"></i> Print Report
                    </button>
                    <button class="btn btn-success btn-sm ms-2" id="exportBtn">
                        <i class="fas fa-download"></i> Export
                    </button>
                </div>
            </div>
        </nav>
        
        <!-- Summary Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3><?php echo $totalDepartments; ?></h3>
                            <h6>Departments</h6>
                        </div>
                        <i class="fas fa-building fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3><?php echo $totalBatches; ?></h3>
                            <h6>Batches</h6>
                        </div>
                        <i class="fas fa-calendar-alt fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3><?php echo $totalStudents; ?></h3>
                            <h6>Students</h6>
                        </div>
                        <i class="fas fa-user-graduate fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #ec4899 0%, #db2777 100%);">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3><?php echo $totalTeachers; ?></h3>
                            <h6>Teachers</h6>
                        </div>
                        <i class="fas fa-chalkboard-teacher fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Charts -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="chart-container">
                    <h5>Department-wise Student Distribution</h5>
                    <canvas id="deptStudentChart"></canvas>
                </div>
            </div>
            <div class="col-md-6">
                <div class="chart-container">
                    <h5>Batch Statistics</h5>
                    <canvas id="batchChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Detailed Reports -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Department Detailed Report</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="deptReportTable" class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Department</th>
                                        <th>Code</th>
                                        <th>Admins</th>
                                        <th>Teachers</th>
                                        <th>Students</th>
                                        <th>Batches</th>
                                        <th>Student/Batch Avg.</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($deptStats as $dept): 
                                        $avgStudentsPerBatch = $dept['batch_count'] > 0 ? 
                                            round($dept['student_count'] / $dept['batch_count'], 1) : 0;
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($dept['dept_name']); ?></td>
                                        <td><?php echo htmlspecialchars($dept['dept_code']); ?></td>
                                        <td><span class="badge bg-primary"><?php echo $dept['admin_count']; ?></span></td>
                                        <td><span class="badge bg-info"><?php echo $dept['teacher_count']; ?></span></td>
                                        <td><span class="badge bg-success"><?php echo $dept['student_count']; ?></span></td>
                                        <td><span class="badge bg-warning"><?php echo $dept['batch_count']; ?></span></td>
                                        <td>
                                            <span class="badge bg-<?php echo $avgStudentsPerBatch > 20 ? 'danger' : ($avgStudentsPerBatch > 10 ? 'warning' : 'success'); ?>">
                                                <?php echo $avgStudentsPerBatch; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th>Total</th>
                                        <th></th>
                                        <th><?php echo $totalAdmins; ?></th>
                                        <th><?php echo $totalTeachers; ?></th>
                                        <th><?php echo $totalStudents; ?></th>
                                        <th><?php echo $totalBatches; ?></th>
                                        <th><?php echo $totalBatches > 0 ? round($totalStudents / $totalBatches, 1) : 0; ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Batch Report -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Batch Detailed Report</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="batchReportTable" class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Batch Name</th>
                                        <th>Year</th>
                                        <th>Department</th>
                                        <th>Students</th>
                                        <th>Title Deadline</th>
                                        <th>Proposal Deadline</th>
                                        <th>Final Deadline</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($batchStats as $batch): 
                                        $today = new DateTime();
                                        $status = 'Active';
                                        $statusClass = 'success';
                                        
                                        if ($batch['final_report_deadline']) {
                                            $deadline = new DateTime($batch['final_report_deadline']);
                                            if ($deadline < $today) {
                                                $status = 'Completed';
                                                $statusClass = 'secondary';
                                            }
                                        }
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($batch['batch_name']); ?></td>
                                        <td><?php echo $batch['batch_year']; ?></td>
                                        <td><?php echo htmlspecialchars($batch['dept_name']); ?></td>
                                        <td><span class="badge bg-primary"><?php echo $batch['student_count']; ?></span></td>
                                        <td>
                                            <?php if ($batch['title_deadline']): ?>
                                                <?php echo date('M d, Y', strtotime($batch['title_deadline'])); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Not set</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($batch['proposal_deadline']): ?>
                                                <?php echo date('M d, Y', strtotime($batch['proposal_deadline'])); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Not set</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($batch['final_report_deadline']): ?>
                                                <?php echo date('M d, Y', strtotime($batch['final_report_deadline'])); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Not set</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $statusClass; ?>"><?php echo $status; ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize DataTables
            $('#deptReportTable').DataTable({
                pageLength: 10,
                responsive: true,
                order: [[4, 'desc']] // Sort by student count
            });
            
            $('#batchReportTable').DataTable({
                pageLength: 10,
                responsive: true,
                order: [[1, 'desc']] // Sort by year
            });
            
            // Export button
            $('#exportBtn').click(function() {
                alert('Export functionality will be implemented in the next update.');
            });
            
            // Charts
            // Department Student Distribution Chart
            var deptStudentCtx = document.getElementById('deptStudentChart').getContext('2d');
            var deptStudentChart = new Chart(deptStudentCtx, {
                type: 'bar',
                data: {
                    labels: [<?php echo implode(',', array_map(function($dept) {
                        return "'" . addslashes($dept['dept_name']) . "'";
                    }, $deptStats)); ?>],
                    datasets: [{
                        label: 'Number of Students',
                        data: [<?php echo implode(',', array_column($deptStats, 'student_count')); ?>],
                        backgroundColor: [
                            '#667eea', '#764ba2', '#10b981', '#f59e0b', 
                            '#ec4899', '#8b5cf6', '#06b6d4', '#f97316'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    }
                }
            });