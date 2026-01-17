<!-- File: student/submit_document.php -->
<?php
require_once '../includes/config/constants.php';
require_once '../includes/classes/Database.php';
require_once '../includes/classes/Session.php';
require_once '../includes/classes/Auth.php';
require_once '../includes/classes/ProjectManager.php';
require_once '../includes/classes/SubmissionManager.php';
require_once '../includes/helpers/FileUpload.php';

Session::init();
$auth = new Auth();
$auth->requireRole(['student']);

$user = $auth->getUser();
$projectManager = new ProjectManager();
$submissionManager = new SubmissionManager();
$fileUpload = new FileUpload();

// Get student's project
$batchId = $user['batch_id'] ?? null;
$project = $batchId ? $projectManager->getStudentProject($user['user_id'], $batchId) : null;

$message = '';
$error = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_document'])) {
    // Check if student has an approved project
    if (!$project || $project['status'] !== 'approved') {
        $error = 'You need an approved project before submitting documents';
    } else {
        $submissionType = $_POST['submission_type'];
        $description = $_POST['description'] ?? '';
        
        // Handle file upload
        $fileInfo = null;
        if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadResult = $fileUpload->upload($_FILES['document_file'], $user['user_id'], $project['id'], $submissionType);
            
            if (!$uploadResult['success']) {
                $error = $uploadResult['message'];
            } else {
                $fileInfo = [
                    'file_name' => $uploadResult['file_name'],
                    'file_path' => $uploadResult['file_path'],
                    'file_size' => $uploadResult['file_size'],
                    'file_type' => $uploadResult['file_type']
                ];
            }
        } else {
            $error = 'Please select a file to upload';
        }
        
        // If no error, submit document
        if (empty($error)) {
            $data = [
                'student_id' => $user['user_id'],
                'submission_type' => $submissionType,
                'description' => $description
            ];
            
            $result = $submissionManager->submitDocument($data, $fileInfo);
            
            if ($result['success']) {
                $message = $result['message'];
                // Refresh project data
                $project = $projectManager->getStudentProject($user['user_id'], $batchId);
            } else {
                $error = $result['message'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Document - FYPMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .upload-area {
            border: 2px dashed #667eea;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            background-color: #f8f9fa;
            cursor: pointer;
            transition: all 0.3s;
        }
        .upload-area:hover {
            background-color: #e9ecef;
            border-color: #5a67d8;
        }
        .upload-area.dragover {
            background-color: #dbeafe;
            border-color: #4c51bf;
        }
        .file-preview {
            background-color: #f1f5f9;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }
        .submission-type-card {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
            cursor: pointer;
        }
        .submission-type-card:hover {
            border-color: #667eea;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .submission-type-card.selected {
            border-color: #667eea;
            background-color: #ebf4ff;
        }
        .submission-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <!-- Student Sidebar -->
    <div class="sidebar">
        <div class="p-4">
            <h4 class="text-center">FYPMS</h4>
            <hr class="bg-white">
            <div class="text-center mb-4">
                <div class="rounded-circle bg-white d-inline-flex align-items-center justify-content-center" 
                     style="width: 60px; height: 60px;">
                    <i class="fas fa-user-graduate" style="color: #667eea;"></i>
                </div>
                <h6 class="mt-2"><?php echo htmlspecialchars($user['full_name']); ?></h6>
                <small>Student</small>
            </div>
            
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="my_project.php">
                        <i class="fas fa-project-diagram me-2"></i> My Project
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="submit_document.php">
                        <i class="fas fa-file-upload me-2"></i> Submit Document
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="my_submissions.php">
                        <i class="fas fa-history me-2"></i> My Submissions
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
                    <a href="my_project.php" class="btn btn-outline-primary btn-sm me-2">
                        <i class="fas fa-arrow-left"></i> Back to My Project
                    </a>
                    <h3 class="mb-0 d-inline">Submit Document</h3>
                </div>
                <div>
                    <span class="badge bg-primary">
                        Project: <?php echo $project ? htmlspecialchars(substr($project['title'], 0, 30)) . '...' : 'No Project'; ?>
                    </span>
                </div>
            </div>
        </nav>
        
        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (!$project): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                You don't have an approved project yet. Please submit and get your project title approved first.
                <a href="my_project.php" class="alert-link">Go to My Project</a>
            </div>
        <?php else: ?>
            <!-- Project Information -->
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        Project Information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Project Title:</strong> <?php echo htmlspecialchars($project['title']); ?></p>
                            <p><strong>Status:</strong> 
                                <span class="badge bg-<?php echo $project['status'] === 'approved' ? 'success' : ($project['status'] === 'in_progress' ? 'warning' : 'info'); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $project['status'])); ?>
                                </span>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Supervisor:</strong> 
                                <?php echo $project['supervisor_name'] ? htmlspecialchars($project['supervisor_name']) : 'Not Assigned'; ?>
                            </p>
                            <p><strong>Department:</strong> <?php echo htmlspecialchars($project['dept_name']); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Submission Form -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Document Submission</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="" enctype="multipart/form-data" id="submissionForm">
                        <!-- Submission Type Selection -->
                        <div class="mb-4">
                            <label class="form-label"><strong>Select Document Type *</strong></label>
                            <div class="row" id="submissionTypeSelection">
                                <div class="col-md-3 mb-3">
                                    <div class="submission-type-card" data-type="proposal">
                                        <div class="text-center">
                                            <i class="fas fa-file-contract fa-3x mb-3 text-primary"></i>
                                            <h6>Project Proposal</h6>
                                            <small class="text-muted">Initial project proposal document</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="submission-type-card" data-type="progress_report">
                                        <div class="text-center">
                                            <i class="fas fa-chart-line fa-3x mb-3 text-success"></i>
                                            <h6>Progress Report</h6>
                                            <small class="text-muted">Chapter or progress updates</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="submission-type-card" data-type="final_report">
                                        <div class="text-center">
                                            <i class="fas fa-file-alt fa-3x mb-3 text-danger"></i>
                                            <h6>Final Report</h6>
                                            <small class="text-muted">Complete final report</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="submission-type-card" data-type="source_code">
                                        <div class="text-center">
                                            <i class="fas fa-code fa-3x mb-3 text-warning"></i>
                                            <h6>Source Code</h6>
                                            <small class="text-muted">Project source files (ZIP)</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" name="submission_type" id="submissionType" required>
                            <div class="invalid-feedback" id="typeError">Please select a document type</div>
                        </div>
                        
                        <!-- File Upload -->
                        <div class="mb-4">
                            <label class="form-label"><strong>Upload Document *</strong></label>
                            <div class="upload-area" id="uploadArea">
                                <i class="fas fa-cloud-upload-alt fa-3x mb-3 text-primary"></i>
                                <h5>Drag & Drop or Click to Upload</h5>
                                <p class="text-muted">
                                    Maximum file size: 50MB<br>
                                    Allowed formats: PDF, DOC, DOCX, ZIP
                                </p>
                                <input type="file" name="document_file" id="documentFile" class="d-none" accept=".pdf,.doc,.docx,.zip">
                                <button type="button" class="btn btn-primary mt-3" id="browseBtn">
                                    <i class="fas fa-folder-open me-2"></i> Browse Files
                                </button>
                            </div>
                            <div class="file-preview d-none" id="filePreview">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas fa-file text-primary me-2"></i>
                                        <span id="fileName"></span>
                                        <small class="text-muted ms-2" id="fileSize"></small>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-danger" id="removeFile">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Description -->
                        <div class="mb-4">
                            <label class="form-label"><strong>Description (Optional)</strong></label>
                            <textarea class="form-control" name="description" rows="4" 
                                      placeholder="Add any notes or comments about this submission..."></textarea>
                        </div>
                        
                        <!-- Submission Guidelines -->
                        <div class="alert alert-info">
                            <h6><i class="fas fa-lightbulb me-2"></i> Submission Guidelines</h6>
                            <ul class="mb-0">
                                <li>Ensure your file name clearly indicates the content</li>
                                <li>Check file size before uploading (max 50MB)</li>
                                <li>Only submit final versions to avoid confusion</li>
                                <li>Your supervisor will be notified automatically</li>
                            </ul>
                        </div>
                        
                        <!-- Submit Button -->
                        <div class="text-center">
                            <button type="submit" name="submit_document" class="btn btn-primary btn-lg px-5">
                                <i class="fas fa-paper-plane me-2"></i> Submit Document
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Recent Submissions -->
            <?php
            $recentSubmissions = $submissionManager->getProjectSubmissions($project['id']);
            if (!empty($recentSubmissions)):
            ?>
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Recent Submissions</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>File Name</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Version</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentSubmissions as $submission): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $submission['submission_type'] === 'proposal' ? 'primary' : 
                                                ($submission['submission_type'] === 'progress_report' ? 'info' : 
                                                ($submission['submission_type'] === 'final_report' ? 'danger' : 'warning'));
                                        ?>">
                                            <?php echo str_replace('_', ' ', ucfirst($submission['submission_type'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <i class="fas fa-file me-1"></i>
                                        <?php echo htmlspecialchars(substr($submission['file_name'], 0, 40)); ?>...
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($submission['submitted_at'])); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $submission['status'] === 'approved' ? 'success' : 
                                                ($submission['status'] === 'pending' ? 'warning' : 
                                                ($submission['status'] === 'rejected' ? 'danger' : 'info'));
                                        ?>">
                                            <?php echo ucfirst($submission['status']); ?>
                                        </span>
                                    </td>
                                    <td>v<?php echo $submission['version']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Submission Type Selection
            const typeCards = document.querySelectorAll('.submission-type-card');
            const submissionTypeInput = document.getElementById('submissionType');
            
            typeCards.forEach(card => {
                card.addEventListener('click', function() {
                    // Remove selected class from all cards
                    typeCards.forEach(c => c.classList.remove('selected'));
                    // Add selected class to clicked card
                    this.classList.add('selected');
                    // Set hidden input value
                    submissionTypeInput.value = this.dataset.type;
                    // Clear error
                    document.getElementById('typeError').classList.remove('d-block');
                });
            });
            
            // File Upload Handling
            const uploadArea = document.getElementById('uploadArea');
            const fileInput = document.getElementById('documentFile');
            const browseBtn = document.getElementById('browseBtn');
            const filePreview = document.getElementById('filePreview');
            const fileName = document.getElementById('fileName');
            const fileSize = document.getElementById('fileSize');
            const removeFileBtn = document.getElementById('removeFile');
            
            // Browse button click
            browseBtn.addEventListener('click', () => fileInput.click());
            
            // File input change
            fileInput.addEventListener('change', handleFileSelect);
            
            // Drag and drop
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
                handleFiles(files);
            }
            
            function handleFileSelect(e) {
                const files = e.target.files;
                handleFiles(files);
            }
            
            function handleFiles(files) {
                if (files.length === 0) return;
                
                const file = files[0];
                
                // Validate file size (50MB)
                if (file.size > 50 * 1024 * 1024) {
                    alert('File size exceeds 50MB limit');
                    return;
                }
                
                // Validate file type
                const allowedTypes = ['application/pdf', 'application/msword', 
                                     'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                     'application/zip', 'application/x-zip-compressed'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Invalid file type. Allowed: PDF, DOC, DOCX, ZIP');
                    return;
                }
                
                // Show file preview
                fileName.textContent = file.name;
                fileSize.textContent = formatFileSize(file.size);
                filePreview.classList.remove('d-none');
            }
            
            function formatFileSize(bytes) {
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            }
            
            // Remove file
            removeFileBtn.addEventListener('click', function() {
                fileInput.value = '';
                filePreview.classList.add('d-none');
            });
            
            // Form validation
            const form = document.getElementById('submissionForm');
            form.addEventListener('submit', function(e) {
                let valid = true;
                
                // Check submission type
                if (!submissionTypeInput.value) {
                    document.getElementById('typeError').classList.add('d-block');
                    valid = false;
                }
                
                // Check file selected
                if (!fileInput.files.length) {
                    alert('Please select a file to upload');
                    valid = false;
                }
                
                if (!valid) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>