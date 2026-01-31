<!-- C:\xampp\htdocs\fypms\final-year-project-management-system\admin\import_students.php -->
<?php
require_once '../includes/config/constants.php';
require_once '../includes/classes/Database.php';
require_once '../includes/classes/Session.php';
require_once '../includes/classes/Auth.php';
require_once '../includes/classes/BatchManager.php';

Session::init();
$auth = new Auth();
$auth->requireRole(['admin']);

$user = $auth->getUser();
$batchManager = new BatchManager();

// Get batches for the admin's department
$batches = $batchManager->getBatchesByDepartment($user['department_id']);

// Handle import
$message = '';
$error = '';
$importResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_students'])) {
    // Validate file
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['csv_file']['tmp_name'];
        $fileName = $_FILES['csv_file']['name'];
        $fileSize = $_FILES['csv_file']['size'];
        $fileType = $_FILES['csv_file']['type'];
        
        // Check file extension
        $allowedExtensions = ['csv', 'txt'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        if (!in_array($fileExtension, $allowedExtensions)) {
            $error = 'Only CSV files are allowed';
        } elseif ($fileSize > 5 * 1024 * 1024) { // 5MB limit
            $error = 'File size must be less than 5MB';
        } else {
            $batchId = $_POST['batch_id'];
            $departmentId = $user['department_id'];
            
            // Process import
            $result = $batchManager->importStudentsFromCSV($fileTmpPath, $departmentId, $batchId, $user['user_id']);
            
            if ($result['success']) {
                $message = $result['message'];
                $importResult = $result;
            } else {
                $error = $result['message'];
            }
        }
    } else {
        $error = 'Please select a valid CSV file';
    }
}

// Get import history
$importHistory = [];
try {
    $db = Database::getInstance()->getConnection();
    $query = "SELECT sil.*, b.batch_name, a.full_name as imported_by_name 
              FROM student_import_logs sil
              LEFT JOIN batches b ON sil.batch_id = b.id
              LEFT JOIN admins a ON sil.imported_by = a.id
              WHERE sil.department_id = :dept_id
              ORDER BY sil.created_at DESC
              LIMIT 10";
    
    $stmt = $db->prepare($query);
    $stmt->execute([':dept_id' => $user['department_id']]);
    $importHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching import history: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Import Students - FYPMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .upload-area {
            border: 2px dashed #007bff;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: #f8f9fa;
        }
        .upload-area:hover {
            background: #e9ecef;
            border-color: #0056b3;
        }
        .upload-area.dragover {
            background: #d1ecf1;
            border-color: #0c5460;
        }
        .csv-template {
            border-left: 4px solid #28a745;
            background: #f8fff9;
            padding: 15px;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <!-- Include sidebar from dashboard -->
    <?php 
    // We'll include just the sidebar logic
    $page = 'students';
    include 'dashboard.php'; 
    ?>
    
    <div class="main-content">
        <nav class="navbar navbar-light bg-light mb-4 rounded">
            <div class="container-fluid">
                <h3 class="mb-0">Bulk Import Students</h3>
                <div>
                    <a href="students.php" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-arrow-left"></i> Back to Students
                    </a>
                </div>
            </div>
        </nav>
        
        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <h5><i class="fas fa-check-circle"></i> Import Successful</h5>
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
        
        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-file-import"></i> Import Students</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" enctype="multipart/form-data" id="importForm">
                            <div class="mb-4">
                                <label class="form-label">Select Batch</label>
                                <select class="form-control" name="batch_id" required>
                                    <option value="">-- Select Batch --</option>
                                    <?php foreach ($batches as $batch): ?>
                                    <option value="<?php echo $batch['id']; ?>">
                                        <?php echo htmlspecialchars($batch['batch_name']); ?> - <?php echo $batch['batch_year']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Students will be assigned to the selected batch</small>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">Upload CSV File</label>
                                <div class="upload-area" id="uploadArea">
                                    <div id="uploadContent">
                                        <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-3"></i>
                                        <h5>Drag & Drop CSV File</h5>
                                        <p class="text-muted">or click to browse</p>
                                        <small class="text-muted">Maximum file size: 5MB</small>
                                    </div>
                                    <input type="file" name="csv_file" id="csvFile" accept=".csv,.txt" hidden required>
                                    <div id="fileName" class="mt-2"></div>
                                </div>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                <strong>Note:</strong> CSV file should have the following columns in order:
                                <code>student_id, full_name, email</code>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" name="import_students" class="btn btn-primary btn-lg">
                                    <i class="fas fa-upload"></i> Start Import
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- CSV Template -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-file-download"></i> CSV Template</h5>
                    </div>
                    <div class="card-body">
                        <div class="csv-template mb-3">
                            <h6>Download Sample Template:</h6>
                            <a href="javascript:void(0)" onclick="downloadTemplate()" class="btn btn-success btn-sm">
                                <i class="fas fa-download"></i> Download Template.csv
                            </a>
                        </div>
                        
                        <h6>CSV Format Example:</h6>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>student_id</th>
                                        <th>full_name</th>
                                        <th>email</th>
                                        <th>phone (optional)</th>
                                        <th>gender (optional)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>UGR13610</td>
                                        <td>Daniel Arega</td>
                                        <td>daniel@example.com</td>
                                        <td>0912345678</td>
                                        <td>Male</td>
                                    </tr>
                                    <tr>
                                        <td>UGR13611</td>
                                        <td>Nafyad Tesfaye</td>
                                        <td>nafyad@example.com</td>
                                        <td></td>
                                        <td>Male</td>
                                    </tr>
                                    <tr>
                                        <td>UGR13612</td>
                                        <td>Warkineh Lemma</td>
                                        <td>warkineh@example.com</td>
                                        <td>0923456789</td>
                                        <td>Male</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Important Notes:</strong>
                            <ul class="mb-0">
                                <li>First row must be header row with column names</li>
                                <li>Student ID must be unique</li>
                                <li>Email must be valid format</li>
                                <li>Default password will be set to "password"</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <!-- Import History -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-history"></i> Recent Imports</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($importHistory)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No import history found.
                            </div>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($importHistory as $import): ?>
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($import['filename']); ?></h6>
                                        <small><?php echo date('M d', strtotime($import['created_at'])); ?></small>
                                    </div>
                                    <p class="mb-1 small">
                                        <span class="badge bg-<?php echo $import['status'] === 'completed' ? 'success' : ($import['status'] === 'failed' ? 'danger' : 'warning'); ?>">
                                            <?php echo ucfirst($import['status']); ?>
                                        </span>
                                        - <?php echo $import['successful_imports']; ?> successful, 
                                        <?php echo $import['failed_imports']; ?> failed
                                    </p>
                                    <small class="text-muted">
                                        Batch: <?php echo htmlspecialchars($import['batch_name']); ?><br>
                                        By: <?php echo htmlspecialchars($import['imported_by_name']); ?>
                                    </small>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="text-center mt-3">
                                <a href="import_history.php" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-list"></i> View Full History
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Quick Stats -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Import Statistics</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        try {
                            $statsQuery = "SELECT 
                                         SUM(total_records) as total_imported,
                                         SUM(successful_imports) as total_success,
                                         SUM(failed_imports) as total_failed,
                                         COUNT(*) as total_imports
                                         FROM student_import_logs 
                                         WHERE department_id = :dept_id";
                            
                            $statsStmt = $db->prepare($statsQuery);
                            $statsStmt->execute([':dept_id' => $user['department_id']]);
                            $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
                        } catch (PDOException $e) {
                            $stats = ['total_imported' => 0, 'total_success' => 0, 'total_failed' => 0, 'total_imports' => 0];
                        }
                        ?>
                        <div class="text-center">
                            <h1><?php echo $stats['total_imported']; ?></h1>
                            <p class="text-muted">Total Records Imported</p>
                        </div>
                        <div class="row text-center">
                            <div class="col-md-6 mb-3">
                                <div class="p-3 bg-success text-white rounded">
                                    <h4><?php echo $stats['total_success']; ?></h4>
                                    <small>Successful</small>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="p-3 bg-danger text-white rounded">
                                    <h4><?php echo $stats['total_failed']; ?></h4>
                                    <small>Failed</small>
                                </div>
                            </div>
                        </div>
                        <div class="progress mb-3" style="height: 20px;">
                            <?php 
                            $successRate = $stats['total_imported'] > 0 ? 
                                ($stats['total_success'] / $stats['total_imported'] * 100) : 0;
                            ?>
                            <div class="progress-bar bg-success" style="width: <?php echo $successRate; ?>%">
                                <?php echo round($successRate, 1); ?>%
                            </div>
                        </div>
                        <small class="text-muted">Success Rate: <?php echo round($successRate, 1); ?>%</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // File upload drag & drop
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('csvFile');
        const fileName = document.getElementById('fileName');
        const uploadContent = document.getElementById('uploadContent');
        
        uploadArea.addEventListener('click', () => fileInput.click());
        
        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                const file = this.files[0];
                fileName.innerHTML = `
                    <div class="alert alert-success">
                        <i class="fas fa-file-csv"></i> ${file.name}
                        <br><small>Size: ${(file.size / 1024).toFixed(2)} KB</small>
                    </div>
                `;
            }
        });
        
        // Drag & drop events
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            uploadArea.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight() {
            uploadArea.classList.add('dragover');
        }
        
        function unhighlight() {
            uploadArea.classList.remove('dragover');
        }
        
        uploadArea.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            
            if (files.length > 0) {
                fileInput.files = files;
                const event = new Event('change');
                fileInput.dispatchEvent(event);
            }
        }
        
        // Download template
        function downloadTemplate() {
            const csvContent = "student_id,full_name,email,phone,gender\nUGR13610,Daniel Arega,daniel@example.com,0912345678,Male\nUGR13611,Nafyad Tesfaye,nafyad@example.com,,Male\nUGR13612,Warkineh Lemma,warkineh@example.com,0923456789,Male";
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'student_import_template.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>