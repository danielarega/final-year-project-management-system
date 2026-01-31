<?php
require_once '../includes/config/constants.php';
require_once '../includes/classes/Database.php';
require_once '../includes/classes/Session.php';
require_once '../includes/classes/Auth.php';
require_once '../includes/classes/SubmissionManager.php';
require_once '../includes/classes/TemplateManager.php';
require_once '../includes/classes/ProjectManager.php';

Session::init();
$auth = new Auth();
$auth->requireRole(['student']);

$user = $auth->getUser();
$submissionManager = new SubmissionManager();
$templateManager = new TemplateManager();
$projectManager = new ProjectManager();

// Get student's project
$project = $projectManager->getStudentProject($user['user_id'], $user['batch_id']);

// Get available submission types
$submissionTypes = $submissionManager->getStudentSubmissionTypes(
    $user['user_id'], 
    $user['department_id'], 
    $user['batch_id']
);

// Get student's submissions
$submissions = $project ? $submissionManager->getStudentSubmissions($user['user_id'], $project['id']) : [];

// Get templates
$templates = $templateManager->getTemplatesForStudent($user['user_id']);

// Handle form submissions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['submit_document'])) {
        // Validate project exists
        if (!$project) {
            $error = 'You must have an approved project title before submitting documents.';
        } else {
            // Check if file was uploaded
            if (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
                $error = 'Please select a file to upload.';
            } else {
                $data = [
                    'student_id' => $user['user_id'],
                    'submission_type_id' => $_POST['submission_type_id'],
                    'notes' => $_POST['notes'] ?? null
                ];
                
                $result = $submissionManager->submitDocument($data, $_FILES['document_file']);
                
                if ($result['success']) {
                    $message = $result['message'];
                    header('Location: submissions.php?success=' . urlencode($message));
                    exit();
                } else {
                    $error = $result['message'];
                }
            }
        }
    }
}

// Show success message from redirect
if (isset($_GET['success'])) {
    $message = $_GET['success'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Submissions - FYPMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .submission-card {
            border: 1px solid #ddd;
            border-radius: 10px;
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        .submission-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .submission-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 10px 10px 0 0;
        }
        .file-icon {
            font-size: 40px;
            color: #667eea;
        }
        .status-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 600;
        }
        .status-submitted { background: #cfe2ff; color: #084298; }
        .status-approved { background: #d1e7dd; color: #0a3622; }
        .status-rejected { background: #f8d7da; color: #58151c; }
        .status-resubmit { background: #fff3cd; color: #664d03; }
        .deadline-overdue { color: #dc3545; font-weight: bold; }
        .deadline-upcoming { color: #ffc107; font-weight: bold; }
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
                    <a class="nav-link active" href="submissions.php">
                        <i class="fas fa-file-upload me-2"></i> Submissions
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="feedback.php">
                        <i class="fas fa-comments me-2"></i> Feedback
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
                        <i class="fas fa-arrow-left"></i> Back to Project
                    </a>
                    <h3 class="mb-0 d-inline">Document Submissions</h3>
                </div>
                <?php if ($project): ?>
                    <span class="badge bg-primary">
                        <i class="fas fa-project-diagram"></i> 
                        <?php echo htmlspecialchars($project['title']); ?>
                    </span>
                <?php endif; ?>
            </div>
        </nav>
        
        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (!$project): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> 
                <strong>No Approved Project Found!</strong> 
                You must have an approved project title before you can submit documents. 
                <a href="my_project.php" class="alert-link">Submit your project title first.</a>
            </div>
        <?php else: ?>
            <div class="row">
                <!-- Left Column: Submit New Document -->
                <div class="col-md-8">
                    <!-- Submit Form -->
                    <div class="card mb-4">
                        <div class="submission-header">
                            <h5 class="mb-0">
                                <i class="fas fa-cloud-upload-alt me-2"></i>
                                Submit New Document
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="" enctype="multipart/form-data" id="submitForm">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Document Type *</label>
                                        <select class="form-control" name="submission_type_id" required id="submissionType">
                                            <option value="">-- Select Document Type --</option>
                                            <?php foreach ($submissionTypes as $type): 
                                                $hasSubmission = false;
                                                foreach ($submissions as $sub) {
                                                    if ($sub['submission_type_id'] == $type['id']) {
                                                        $hasSubmission = true;
                                                        break;
                                                    }
                                                }
                                            ?>
                                            <option value="<?php echo $type['id']; ?>" 
                                                    data-deadline="<?php echo $type['deadline_date'] ?? ''; ?>"
                                                    data-status="<?php echo $type['deadline_status'] ?? ''; ?>"
                                                    data-extensions="<?php echo $type['allowed_extensions'] ?? 'pdf,doc,docx,zip'; ?>"
                                                    data-maxsize="<?php echo $type['max_file_size'] ?? 50; ?>">
                                                <?php echo htmlspecialchars($type['type_name']); ?>
                                                <?php if ($type['deadline_date']): ?>
                                                    (Due: <?php echo date('M d, Y', strtotime($type['deadline_date'])); ?>)
                                                <?php endif; ?>
                                                <?php if ($hasSubmission): ?>
                                                    <i class="fas fa-check text-success"></i>
                                                <?php endif; ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Deadline Status</label>
                                        <div id="deadlineInfo" class="form-control" style="height: 38px; line-height: 38px;">
                                            <span class="text-muted">Select document type</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Select File *</label>
                                    <input type="file" class="form-control" name="document_file" required 
                                           accept=".pdf,.doc,.docx,.zip" id="fileInput">
                                    <div class="mt-1">
                                        <small class="text-muted" id="fileRequirements">
                                            Maximum file size: 50MB. Allowed types: PDF, DOC, DOCX, ZIP
                                        </small>
                                        <div id="filePreview" class="mt-2 d-none">
                                            <i class="fas fa-file-alt me-2"></i>
                                            <span id="fileName"></span>
                                            <small class="text-muted" id="fileSize"></small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Notes (Optional)</label>
                                    <textarea class="form-control" name="notes" rows="3" 
                                              placeholder="Add any notes about this submission..."></textarea>
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    <strong>Note:</strong> If you've already submitted this document type, 
                                    uploading a new file will create a new version.
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" name="submit_document" class="btn btn-primary">
                                        <i class="fas fa-paper-plane me-2"></i> Submit Document
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- My Submissions -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-history me-2"></i>
                                My Submissions
                                <span class="badge bg-primary"><?php echo count($submissions); ?></span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($submissions)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-file-alt fa-4x text-muted mb-3"></i>
                                    <h5>No Submissions Yet</h5>
                                    <p class="text-muted">Submit your first document to get started.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Type</th>
                                                <th>File</th>
                                                <th>Version</th>
                                                <th>Submitted</th>
                                                <th>Status</th>
                                                <th>Review</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($submissions as $sub): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($sub['type_name']); ?></strong>
                                                    <?php if ($sub['description']): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($sub['description']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <i class="fas fa-file-alt text-primary me-2"></i>
                                                    <?php echo htmlspecialchars($sub['file_name']); ?>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?php echo round($sub['file_size'] / 1024 / 1024, 2); ?> MB
                                                    </small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary">v<?php echo $sub['version']; ?></span>
                                                </td>
                                                <td>
                                                    <?php echo date('M d, Y', strtotime($sub['submission_date'])); ?>
                                                </td>
                                                <td>
                                                    <span class="status-badge status-<?php echo $sub['status']; ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $sub['status'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($sub['review_status'] !== 'not_reviewed'): ?>
                                                        <span class="badge bg-<?php echo $sub['status_color']; ?>">
                                                            <?php echo ucfirst(str_replace('_', ' ', $sub['review_status'])); ?>
                                                        </span>
                                                        <?php if ($sub['review_score'] > 0): ?>
                                                            <br><small>Score: <?php echo $sub['review_score']; ?>/100</small>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not reviewed</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <!-- Download Button -->
                                                        <a href="download_submission.php?id=<?php echo $sub['id']; ?>" 
                                                           class="btn btn-outline-primary" title="Download">
                                                            <i class="fas fa-download"></i>
                                                        </a>
                                                        <!-- Feedback Button -->
                                                        <a href="feedback.php?submission_id=<?php echo $sub['id']; ?>" 
                                                           class="btn btn-outline-info" title="View Feedback">
                                                            <i class="fas fa-comments"></i>
                                                        </a>
                                                        <!-- Resubmit Button (if rejected or needs revision) -->
                                                        <?php if ($sub['status'] === 'rejected' || $sub['status'] === 'resubmit'): ?>
                                                        <button type="button" class="btn btn-outline-warning" 
                                                                onclick="resubmitDocument(<?php echo $sub['submission_type_id']; ?>)"
                                                                title="Resubmit">
                                                            <i class="fas fa-redo"></i>
                                                        </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column: Templates & Guidelines -->
                <div class="col-md-4">
                    <!-- Document Templates -->
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-file-download me-2"></i>
                                Document Templates
                                <span class="badge bg-light text-dark"><?php echo count($templates); ?></span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($templates)): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> No templates available.
                                </div>
                            <?php else: ?>
                                <div class="list-group">
                                    <?php foreach ($templates as $template): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($template['template_name']); ?></h6>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($template['description'] ?? ''); ?>
                                                </small>
                                            </div>
                                            <div class="text-end">
                                                <small class="text-muted d-block">
                                                    <?php echo round($template['file_size'] / 1024 / 1024, 2); ?> MB
                                                </small>
                                                <a href="download_template.php?id=<?php echo $template['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary mt-1">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Submission Guidelines -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-clipboard-list me-2"></i>
                                Submission Guidelines
                            </h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Maximum file size: <strong>50MB</strong>
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Allowed formats: <strong>PDF, DOC, DOCX, ZIP</strong>
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Naming convention: <strong>Title_YourName_Type</strong>
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Check deadlines before submitting
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    You can submit multiple versions
                                </li>
                                <li>
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Check feedback within 48 hours
                                </li>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- Quick Stats -->
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-chart-bar me-2"></i>
                                Submission Statistics
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php 
                            $stats = $submissionManager->getSubmissionStatistics($user['user_id']);
                            if ($stats):
                            ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Submitted</span>
                                    <strong><?php echo $stats['total_count']; ?></strong>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-primary" 
                                         style="width: <?php echo $stats['total_count'] > 0 ? 100 : 0; ?>%"></div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Approved</span>
                                    <strong><?php echo $stats['approved_count']; ?></strong>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-success" 
                                         style="width: <?php echo $stats['total_count'] > 0 ? ($stats['approved_count'] / $stats['total_count'] * 100) : 0; ?>%"></div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Pending Review</span>
                                    <strong><?php echo $stats['pending_count']; ?></strong>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-warning" 
                                         style="width: <?php echo $stats['total_count'] > 0 ? ($stats['pending_count'] / $stats['total_count'] * 100) : 0; ?>%"></div>
                                </div>
                            </div>
                            <?php else: ?>
                            <p class="text-center text-muted">No submission data available</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Submit Modal -->
    <div class="modal fade" id="submitModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Submit Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="" enctype="multipart/form-data" id="modalSubmitForm">
                        <input type="hidden" name="submission_type_id" id="modalTypeId">
                        <div class="mb-3">
                            <label class="form-label">Selected Type</label>
                            <input type="text" class="form-control" id="modalTypeName" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Deadline</label>
                            <input type="text" class="form-control" id="modalDeadline" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Select File *</label>
                            <input type="file" class="form-control" name="document_file" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="modalSubmitForm" class="btn btn-primary">Submit</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Update file requirements based on selected type
        document.getElementById('submissionType').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const deadline = selectedOption.getAttribute('data-deadline');
            const status = selectedOption.getAttribute('data-status');
            const extensions = selectedOption.getAttribute('data-extensions');
            const maxSize = selectedOption.getAttribute('data-maxsize');
            
            // Update deadline info
            const deadlineInfo = document.getElementById('deadlineInfo');
            if (deadline) {
                let statusClass = '';
                let statusText = '';
                
                if (status === 'overdue') {
                    statusClass = 'deadline-overdue';
                    statusText = 'OVERDUE';
                } else if (status === 'upcoming') {
                    statusClass = 'deadline-upcoming';
                    statusText = 'UPCOMING';
                } else {
                    statusClass = 'text-success';
                    statusText = 'FUTURE';
                }
                
                deadlineInfo.innerHTML = `
                    <i class="fas fa-calendar-alt me-2"></i>
                    Due: ${deadline}
                    <span class="badge bg-${status === 'overdue' ? 'danger' : status === 'upcoming' ? 'warning' : 'success'} float-end">
                        ${statusText}
                    </span>
                `;
            } else {
                deadlineInfo.innerHTML = '<span class="text-muted">No deadline set</span>';
            }
            
            // Update file requirements
            document.getElementById('fileRequirements').innerHTML = 
                `Maximum file size: ${maxSize}MB. Allowed types: ${extensions.toUpperCase()}`;
            
            // Update file input accept attribute
            document.getElementById('fileInput').accept = extensions.split(',').map(ext => `.${ext}`).join(',');
        });
        
        // File preview
        document.getElementById('fileInput').addEventListener('change', function() {
            const file = this.files[0];
            const preview = document.getElementById('filePreview');
            const fileName = document.getElementById('fileName');
            const fileSize = document.getElementById('fileSize');
            
            if (file) {
                preview.classList.remove('d-none');
                fileName.textContent = file.name;
                fileSize.textContent = ` (${(file.size / 1024 / 1024).toFixed(2)} MB)`;
            } else {
                preview.classList.add('d-none');
            }
        });
        
        // Resubmit document
        function resubmitDocument(typeId) {
            document.getElementById('submissionType').value = typeId;
            document.getElementById('submissionType').dispatchEvent(new Event('change'));
            document.getElementById('submitForm').scrollIntoView({ behavior: 'smooth' });
        }
        
        // Form validation
        document.getElementById('submitForm').addEventListener('submit', function(e) {
            const fileInput = document.getElementById('fileInput');
            const typeSelect = document.getElementById('submissionType');
            
            if (!typeSelect.value) {
                e.preventDefault();
                alert('Please select a document type.');
                typeSelect.focus();
                return;
            }
            
            if (!fileInput.files[0]) {
                e.preventDefault();
                alert('Please select a file to upload.');
                fileInput.focus();
                return;
            }
            
            // Show loading
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Uploading...';
            submitBtn.disabled = true;
        });
    </script>
</body>
</html>