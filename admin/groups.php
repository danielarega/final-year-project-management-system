<?php
require_once '../includes/config/constants.php';
require_once '../includes/classes/Database.php';
require_once '../includes/classes/Session.php';
require_once '../includes/classes/Auth.php';
require_once '../includes/classes/GroupManager.php';
require_once '../includes/classes/BatchManager.php';

Session::init();
$auth = new Auth();
$auth->requireRole(['admin']);

$user = $auth->getUser();
$groupManager = new GroupManager();
$batchManager = new BatchManager();

// Get batches for filtering
$batches = $batchManager->getBatchesByDepartment($user['department_id']);

// Get selected batch or default to first batch
$selectedBatchId = $_GET['batch_id'] ?? ($batches[0]['id'] ?? 0);
$selectedBatch = $batchManager->getBatchById($selectedBatchId);

// Get groups for selected batch
$groups = [];
if ($selectedBatch) {
    $groups = $groupManager->getGroupsByDepartmentBatch($user['department_id'], $selectedBatchId);
}

// Get all students in batch for group assignment
$batchStudents = $selectedBatch ? $batchManager->getStudentsInBatch($selectedBatchId) : [];

$message = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_group'])) {
        $data = [
            'group_name' => trim($_POST['group_name']),
            'group_code' => trim($_POST['group_code']),
            'department_id' => $user['department_id'],
            'batch_id' => $selectedBatchId,
            'max_members' => (int)$_POST['max_members'],
            'created_by' => $user['user_id'] // Admin creating group
        ];
        
        $result = $groupManager->createGroup($data);
        if ($result['success']) {
            $message = $result['message'];
            header('Location: groups.php?batch_id=' . $selectedBatchId . '&success=' . urlencode($message));
            exit();
        } else {
            $error = $result['message'];
        }
    }
    elseif (isset($_POST['add_member'])) {
        $groupId = $_POST['group_id'];
        $studentId = $_POST['student_id'];
        
        $result = $groupManager->addGroupMember($groupId, $studentId);
        if ($result['success']) {
            $message = $result['message'];
            header('Location: groups.php?batch_id=' . $selectedBatchId . '&success=' . urlencode($message));
            exit();
        } else {
            $error = $result['message'];
        }
    }
    elseif (isset($_POST['remove_member'])) {
        $groupId = $_POST['group_id'];
        $studentId = $_POST['student_id'];
        
        $result = $groupManager->removeGroupMember($groupId, $studentId);
        if ($result['success']) {
            $message = $result['message'];
            header('Location: groups.php?batch_id=' . $selectedBatchId . '&success=' . urlencode($message));
            exit();
        } else {
            $error = $result['message'];
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
    <title>Group Management - FYPMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .group-card {
            border: 1px solid #ddd;
            border-radius: 10px;
            margin-bottom: 20px;
            overflow: hidden;
        }
        .group-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
        }
        .member-list {
            max-height: 300px;
            overflow-y: auto;
        }
        .member-item {
            border-bottom: 1px solid #eee;
            padding: 10px 15px;
        }
        .member-item:last-child {
            border-bottom: none;
        }
        .member-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            margin-right: 10px;
        }
        .leader-avatar { background: #667eea; }
        .member-avatar-default { background: #6c757d; }
    </style>
</head>
<body>
    <!-- Include admin sidebar -->
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
                    <a class="nav-link" href="batches.php">
                        <i class="fas fa-calendar-alt me-2"></i> Batches
                    </a>
                </li>
                <li class="nav-item">
    <a class="nav-link" href="supervisor_assignment.php">
        <i class="fas fa-user-tie me-2"></i> Supervisor Assignment
    </a>
</li>
                <li class="nav-item">
                    <a class="nav-link" href="projects.php">
                        <i class="fas fa-project-diagram me-2"></i> Projects
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="groups.php">
                        <i class="fas fa-users me-2"></i> Groups
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="notices.php">
                        <i class="fas fa-bullhorn me-2"></i> Notices
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
                    <a href="projects.php" class="btn btn-outline-primary btn-sm me-2">
                        <i class="fas fa-arrow-left"></i> Back to Projects
                    </a>
                    <h3 class="mb-0 d-inline">Group Management</h3>
                </div>
                <div>
                    <?php if ($selectedBatch): ?>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createGroupModal">
                            <i class="fas fa-plus-circle"></i> Create Group
                        </button>
                    <?php endif; ?>
                </div>
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
        
        <!-- Batch Selection -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Select Batch</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <div class="btn-group" role="group">
                            <?php foreach ($batches as $batch): ?>
                                <a href="groups.php?batch_id=<?php echo $batch['id']; ?>" 
                                   class="btn btn-outline-primary <?php echo $batch['id'] == $selectedBatchId ? 'active' : ''; ?>">
                                    <?php echo htmlspecialchars($batch['batch_name']); ?> - <?php echo $batch['batch_year']; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-md-4 text-end">
                        <?php if ($selectedBatch): ?>
                            <span class="badge bg-info">
                                <?php echo count($batchStudents); ?> Students in Batch
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (!$selectedBatch): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> No batch selected or batch not found.
            </div>
        <?php else: ?>
            <!-- Batch Info -->
            <div class="alert alert-info mb-4">
                <h5><i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($selectedBatch['batch_name']); ?> - <?php echo $selectedBatch['batch_year']; ?></h5>
                <p class="mb-0">Managing groups for this batch. Students can also create their own groups.</p>
            </div>
            
            <!-- Groups List -->
            <div class="row">
                <?php if (empty($groups)): ?>
                    <div class="col-md-12">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> No groups created yet for this batch.
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($groups as $group): 
                        $members = $groupManager->getGroupMembers($group['id']);
                        $availableSlots = $group['max_members'] - $group['member_count'];
                    ?>
                    <div class="col-md-6 mb-4">
                        <div class="group-card">
                            <div class="group-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="mb-1"><?php echo htmlspecialchars($group['group_name']); ?></h5>
                                        <small>Code: <?php echo htmlspecialchars($group['group_code']); ?></small>
                                    </div>
                                    <span class="badge bg-light text-dark">
                                        <?php echo $group['member_count']; ?>/<?php echo $group['max_members']; ?> members
                                    </span>
                                </div>
                            </div>
                            
                            <div class="p-3">
                                <!-- Group Info -->
                                <div class="mb-3">
                                    <p class="mb-1">
                                        <i class="fas fa-user-tie"></i> 
                                        <strong>Leader:</strong> <?php echo htmlspecialchars($group['leader_name']); ?>
                                    </p>
                                    <p class="mb-1">
                                        <i class="fas fa-calendar"></i> 
                                        <strong>Created:</strong> <?php echo date('M d, Y', strtotime($group['created_at'])); ?>
                                    </p>
                                </div>
                                
                                <!-- Members List -->
                                <h6>Group Members</h6>
                                <div class="member-list">
                                    <?php if (empty($members)): ?>
                                        <div class="alert alert-warning mb-0">
                                            <small>No members in this group</small>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($members as $member): 
                                            $initials = strtoupper(substr($member['full_name'], 0, 2));
                                        ?>
                                        <div class="member-item">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div class="d-flex align-items-center">
                                                    <div class="member-avatar <?php echo $member['role'] === 'leader' ? 'leader-avatar' : 'member-avatar-default'; ?>">
                                                        <?php echo $initials; ?>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($member['full_name']); ?></h6>
                                                        <small><?php echo htmlspecialchars($member['username']); ?></small>
                                                    </div>
                                                </div>
                                                <div>
                                                    <span class="badge bg-<?php echo $member['role'] === 'leader' ? 'primary' : 'secondary'; ?>">
                                                        <?php echo ucfirst($member['role']); ?>
                                                    </span>
                                                    <?php if ($member['role'] !== 'leader'): ?>
                                                    <form method="POST" action="" class="d-inline" onsubmit="return confirm('Remove this member?')">
                                                        <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                                                        <input type="hidden" name="student_id" value="<?php echo $member['student_id']; ?>">
                                                        <button type="submit" name="remove_member" class="btn btn-sm btn-outline-danger">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </form>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Add Member Form -->
                                <?php if ($availableSlots > 0): ?>
                                <div class="mt-3">
                                    <form method="POST" action="" class="row g-2">
                                        <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                                        <div class="col-md-8">
                                            <select class="form-control form-control-sm" name="student_id" required>
                                                <option value="">Select student to add...</option>
                                                <?php foreach ($batchStudents as $student): 
                                                    // Check if student is already in this group
                                                    $isInGroup = false;
                                                    foreach ($members as $member) {
                                                        if ($member['student_id'] == $student['id']) {
                                                            $isInGroup = true;
                                                            break;
                                                        }
                                                    }
                                                    if (!$isInGroup):
                                                ?>
                                                <option value="<?php echo $student['id']; ?>">
                                                    <?php echo htmlspecialchars($student['full_name']); ?> 
                                                    (<?php echo htmlspecialchars($student['username']); ?>)
                                                </option>
                                                <?php endif; endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <button type="submit" name="add_member" class="btn btn-success btn-sm w-100">
                                                <i class="fas fa-plus"></i> Add
                                            </button>
                                        </div>
                                    </form>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-warning mt-3 mb-0">
                                    <small><i class="fas fa-info-circle"></i> Group is full</small>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Ungrouped Students -->
            <div class="card">
                <div class="card-header bg-warning">
                    <h5 class="mb-0">
                        <i class="fas fa-user-clock me-2"></i>
                        Students Not in Groups
                        <span class="badge bg-light text-dark">
                            <?php 
                            $ungroupedCount = 0;
                            foreach ($batchStudents as $student) {
                                $isGrouped = false;
                                foreach ($groups as $group) {
                                    $members = $groupManager->getGroupMembers($group['id']);
                                    foreach ($members as $member) {
                                        if ($member['student_id'] == $student['id']) {
                                            $isGrouped = true;
                                            break 2;
                                        }
                                    }
                                }
                                if (!$isGrouped) $ungroupedCount++;
                            }
                            echo $ungroupedCount;
                            ?>
                        </span>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if ($ungroupedCount === 0): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> All students are in groups.
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($batchStudents as $student): 
                                $isGrouped = false;
                                foreach ($groups as $group) {
                                    $members = $groupManager->getGroupMembers($group['id']);
                                    foreach ($members as $member) {
                                        if ($member['student_id'] == $student['id']) {
                                            $isGrouped = true;
                                            break 2;
                                        }
                                    }
                                }
                                if (!$isGrouped):
                            ?>
                            <div class="col-md-4 mb-3">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($student['full_name']); ?></h6>
                                                <small><?php echo htmlspecialchars($student['username']); ?></small>
                                            </div>
                                            <span class="badge bg-secondary">Not in group</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Create Group Modal -->
    <div class="modal fade" id="createGroupModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">Create New Group</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Group Name *</label>
                            <input type="text" class="form-control" name="group_name" 
                                   placeholder="e.g., Tech Innovators, Code Masters" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Group Code *</label>
                            <div class="input-group">
                                <input type="text" class="form-control" name="group_code" 
                                       placeholder="e.g., CSG01" required>
                                <button type="button" class="btn btn-outline-secondary" id="generateCodeBtn">
                                    <i class="fas fa-sync-alt"></i> Generate
                                </button>
                            </div>
                            <small class="text-muted">Unique code that students will use to join</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Maximum Members</label>
                            <select class="form-control" name="max_members" required>
                                <option value="3">3 Members</option>
                                <option value="4" selected>4 Members</option>
                                <option value="5">5 Members</option>
                            </select>
                            <small class="text-muted">Technology departments require 3-5 members per group</small>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Note:</strong> This group will be created in 
                            <strong><?php echo htmlspecialchars($selectedBatch['batch_name']); ?> - <?php echo $selectedBatch['batch_year']; ?></strong>.
                            Students can join using the group code.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_group" class="btn btn-primary">Create Group</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Generate group code
        document.getElementById('generateCodeBtn')?.addEventListener('click', function() {
            const departmentCode = 'CS'; // In real app, get from department data
            const year = '26';
            const randomNum = Math.floor(Math.random() * 90) + 10; // 10-99
            
            const groupCode = departmentCode + 'G' + year + randomNum;
            document.querySelector('input[name="group_code"]').value = groupCode;
        });
    </script>
</body>
</html>