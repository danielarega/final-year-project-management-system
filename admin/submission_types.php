<?php
require_once '../includes/config/constants.php';
require_once '../includes/classes/Database.php';
require_once '../includes/classes/Session.php';
require_once '../includes/classes/Auth.php';
require_once '../includes/classes/SubmissionManager.php';

Session::init();
$auth = new Auth();
$auth->requireRole(['admin']);

$user = $auth->getUser();
$submissionManager = new SubmissionManager();

// Get submission types for this department
$db = Database::getInstance()->getConnection();
$query = "SELECT st.*, d.dept_name, b.batch_name, 
         COUNT(s.id) as submission_count
         FROM submission_types st
         LEFT JOIN departments d ON st.department_id = d.id
         LEFT JOIN batches b ON st.batch_id = b.id
         LEFT JOIN submissions s ON st.id = s.submission_type_id
         WHERE (st.department_id IS NULL OR st.department_id = :dept_id)
         GROUP BY st.id
         ORDER BY st.display_order";

$stmt = $db->prepare($query);
$stmt->execute([':dept_id' => $user['department_id']]);
$submissionTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle actions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_type'])) {
        $data = [
            'type_name' => trim($_POST['type_name']),
            'description' => trim($_POST['description']),
            'allowed_extensions' => trim($_POST['allowed_extensions']),
            'max_file_size' => (int)$_POST['max_file_size'],
            'is_required' => isset($_POST['is_required']) ? 1 : 0,
            'department_id' => $_POST['department_id'] ?: null,
            'batch_id' => $_POST['batch_id'] ?: null,
            'display_order' => (int)$_POST['display_order']
        ];
        
        // Check if type already exists
        $checkQuery = "SELECT id FROM submission_types 
                      WHERE type_name = :name 
                      AND (department_id IS NULL OR department_id = :dept_id)";
        
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->execute([
            ':name' => $data['type_name'],
            ':dept_id' => $data['department_id']
        ]);
        
        if ($checkStmt->fetch()) {
            $error = 'Submission type already exists for this department.';
        } else {
            $insertQuery = "INSERT INTO submission_types (type_name, description, 
                           allowed_extensions, max_file_size, is_required, 
                           department_id, batch_id, display_order) 
                           VALUES (:name, :desc, :ext, :size, :required, 
                           :dept_id, :batch_id, :order)";
            
            $stmt = $db->prepare($insertQuery);
            $result = $stmt->execute([
                ':name' => $data['type_name'],
                ':desc' => $data['description'],
                ':ext' => $data['allowed_extensions'],
                ':size' => $data['max_file_size'],
                ':required' => $data['is_required'],
                ':dept_id' => $data['department_id'],
                ':batch_id' => $data['batch_id'],
                ':order' => $data['display_order']
            ]);
            
            if ($result) {
                $message = 'Submission type created successfully.';
                header('Location: submission_types.php?success=' . urlencode($message));
                exit();
            } else {
                $error = 'Failed to create submission type.';
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
    <title>Submission Types - FYPMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
</head>
<body>
    <!-- Admin Sidebar -->
    <div class="sidebar">
        <div class="p-4">
            <h4 class="text-center">FYPMS</h4>
            <hr class="bg-white">
            <div class="text-center mb-4">
                <div class="rounded-circle bg-white d-inline-flex align-items-center justify-content-center" 
                     style="width: 60px; height: 60px;">
                    <i class="fas fa-user-tie" style="color: #667eea;"></i>
                </div>
                <h6 class="mt-2"><?php echo htmlspecialchars($user['full_name']); ?></h6>
                <small>Department Head</small>
            </div>
            
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="projects.php">
                        <i class="fas fa-project-diagram me-2"></i> Projects
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="submission_types.php">
                        <i class="fas fa-file-upload me-2"></i> Submission Types
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="templates.php">
                        <i class="fas fa-file-download me-2"></i> Templates
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
                    <h3 class="mb-0 d-inline">Submission Types Management</h3>
                </div>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createTypeModal">
                    <i class="fas fa-plus-circle"></i> Add Type
                </button>
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
        
        <!-- Submission Types Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Available Submission Types
                    <span class="badge bg-primary"><?php echo count($submissionTypes); ?></span>
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="typesTable" class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Type Name</th>
                                <th>Description</th>
                                <th>Department</th>
                                <th>Batch</th>
                                <th>File Types</th>
                                <th>Max Size</th>
                                <th>Required</th>
                                <th>Submissions</th>
                                <th>Order</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($submissionTypes as $index => $type): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($type['type_name']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($type['description']); ?></td>
                                <td>
                                    <?php if ($type['department_id']): ?>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($type['dept_name']); ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">All Departments</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($type['batch_id']): ?>
                                        <span class="badge bg-warning"><?php echo htmlspecialchars($type['batch_name']); ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">All Batches</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted"><?php echo strtoupper($type['allowed_extensions']); ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?php echo $type['max_file_size']; ?> MB</span>
                                </td>
                                <td>
                                    <?php if ($type['is_required']): ?>
                                        <span class="badge bg-danger">Required</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Optional</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $type['submission_count']; ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?php echo $type['display_order']; ?></span>
                                </td>
                                <td>
                                    <?php if ($type['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-info" data-bs-toggle="modal" 
                                                data-bs-target="#editTypeModal"
                                                data-type-id="<?php echo $type['id']; ?>"
                                                data-type-name="<?php echo htmlspecialchars($type['type_name']); ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-outline-danger" 
                                                onclick="deleteType(<?php echo $type['id']; ?>, '<?php echo htmlspecialchars($type['type_name']); ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Statistics -->
        <div class="row mt-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h3><?php echo count($submissionTypes); ?></h3>
                        <p class="mb-0">Total Types</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <?php 
                $requiredCount = array_reduce($submissionTypes, function($carry, $type) {
                    return $carry + ($type['is_required'] ? 1 : 0);
                }, 0);
                ?>
                <div class="card bg-danger text-white">
                    <div class="card-body text-center">
                        <h3><?php echo $requiredCount; ?></h3>
                        <p class="mb-0">Required Types</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <?php 
                $totalSubmissions = array_reduce($submissionTypes, function($carry, $type) {
                    return $carry + $type['submission_count'];
                }, 0);
                ?>
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h3><?php echo $totalSubmissions; ?></h3>
                        <p class="mb-0">Total Submissions</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <?php 
                $activeCount = array_reduce($submissionTypes, function($carry, $type) {
                    return $carry + ($type['is_active'] ? 1 : 0);
                }, 0);
                ?>
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <h3><?php echo $activeCount; ?></h3>
                        <p class="mb-0">Active Types</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Create Type Modal -->
    <div class="modal fade" id="createTypeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">Create New Submission Type</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Type Name *</label>
                                <input type="text" class="form-control" name="type_name" 
                                       placeholder="e.g., Project Proposal, Chapter 1, Final Report" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Display Order</label>
                                <input type="number" class="form-control" name="display_order" 
                                       value="0" min="0" max="100">
                                <small class="text-muted">Lower numbers appear first</small>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3" 
                                      placeholder="Describe what students should submit..."></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Allowed File Extensions *</label>
                                <input type="text" class="form-control" name="allowed_extensions" 
                                       value="pdf,doc,docx,zip" required>
                                <small class="text-muted">Comma-separated: pdf,doc,docx,zip</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Maximum File Size (MB) *</label>
                                <input type="number" class="form-control" name="max_file_size" 
                                       value="50" min="1" max="100" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Department</label>
                                <select class="form-control" name="department_id">
                                    <option value="">All Departments</option>
                                    <option value="<?php echo $user['department_id']; ?>" selected>
                                        Current Department Only
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Batch</label>
                                <select class="form-control" name="batch_id">
                                    <option value="">All Batches</option>
                                    <!-- You would populate this with batches from database -->
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" name="is_required" id="isRequired" checked>
                            <label class="form-check-label" for="isRequired">
                                This submission is required for students
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_type" class="btn btn-primary">Create Type</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Type Modal (Placeholder) -->
    <div class="modal fade" id="editTypeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Submission Type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        Edit functionality will be available in the next update.
                    </div>
                    <p>You will be able to:</p>
                    <ul>
                        <li>Update type name and description</li>
                        <li>Change file requirements</li>
                        <li>Modify department/batch assignment</li>
                        <li>Toggle active status</li>
                        <li>View submission statistics</li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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
            // Initialize DataTable
            $('#typesTable').DataTable({
                pageLength: 10,
                responsive: true,
                order: [[9, 'asc']] // Sort by display order
            });
            
            // Delete type confirmation
            window.deleteType = function(typeId, typeName) {
                if (confirm(`Delete submission type: ${typeName}?\n\nThis will not delete existing submissions.`)) {
                    // In next version, implement actual deletion
                    alert('Delete functionality will be available in the next update.');
                }
            };
            
            // Edit type modal
            $('#editTypeModal').on('show.bs.modal', function(event) {
                var button = $(event.relatedTarget);
                var typeName = button.data('type-name');