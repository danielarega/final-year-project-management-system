<?php
require_once '../includes/config/constants.php';
require_once '../includes/classes/Database.php';
require_once '../includes/classes/Session.php';
require_once '../includes/classes/Auth.php';
require_once '../includes/classes/GroupManager.php';

Session::init();
$auth = new Auth();
$auth->requireRole(['student']);

$user = $auth->getUser();
$groupManager = new GroupManager();

// Get student's batch
$batchId = $user['batch_id'] ?? null;

if (!$batchId) {
    header('Location: dashboard.php?error=You are not assigned to any batch. Please contact your department.');
    exit();
}

// Get student's group
$group = $groupManager->getStudentGroup($user['user_id'], $batchId);

// Get available groups
$availableGroups = $groupManager->getAvailableGroups($user['user_id'], $user['department_id'], $batchId);

// Get group members if in a group
$groupMembers = $group ? $groupManager->getGroupMembers($group['id']) : [];

$message = '';
$error = '';

// Handle group actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_group'])) {
        $groupName = trim($_POST['group_name']);
        $groupCode = trim($_POST['group_code']);
        $maxMembers = (int)$_POST['max_members'];
        
        if (empty($groupName) || empty($groupCode)) {
            $error = 'Group name and code are required';
        } elseif ($maxMembers < 2 || $maxMembers > 5) {
            $error = 'Maximum members must be between 2 and 5';
        } else {
            $data = [
                'group_name' => $groupName,
                'group_code' => $groupCode,
                'department_id' => $user['department_id'],
                'batch_id' => $batchId,
                'max_members' => $maxMembers,
                'created_by' => $user['user_id']
            ];
            
            $result = $groupManager->createGroup($data);
            if ($result['success']) {
                $message = $result['message'];
                header('Location: group.php?success=' . urlencode($message));
                exit();
            } else {
                $error = $result['message'];
            }
        }
    }
    elseif (isset($_POST['join_group'])) {
        $groupId = $_POST['group_id'];
        
        $result = $groupManager->addGroupMember($groupId, $user['user_id']);
        if ($result['success']) {
            $message = $result['message'];
            header('Location: group.php?success=' . urlencode($message));
            exit();
        } else {
            $error = $result['message'];
        }
    }
    elseif (isset($_POST['leave_group'])) {
        if ($group) {
            // Check if user is the group leader
            $userRole = null;
            foreach ($groupMembers as $member) {
                if ($member['student_id'] == $user['user_id']) {
                    $userRole = $member['role'];
                    break;
                }
            }
            
            if ($userRole === 'leader') {
                $error = 'Group leader cannot leave the group. Please transfer leadership first or contact admin.';
            } else {
                $result = $groupManager->removeGroupMember($group['id'], $user['user_id']);
                if ($result['success']) {
                    $message = $result['message'];
                    header('Location: group.php?success=' . urlencode($message));
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
    <title>Group Management - FYPMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .group-card {
            transition: transform 0.3s, box-shadow 0.3s;
            border: 1px solid #ddd;
        }
        .group-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .member-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        .leader-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .member-badge {
            background: #6c757d;
        }
        .group-full {
            opacity: 0.7;
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
                    <a class="nav-link active" href="group.php">
                        <i class="fas fa-users me-2"></i> Group
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
                    <h3 class="mb-0 d-inline">Group Management</h3>
                </div>
                <div>
                    <?php if (!$group): ?>
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
        
        <div class="row">
            <!-- My Group Section -->
            <div class="col-md-8">
                <?php if ($group): ?>
                    <!-- Group Details Card -->
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-users me-2"></i>
                                    My Group: <?php echo htmlspecialchars($group['group_name']); ?>
                                </h5>
                                <span class="badge bg-light text-dark"><?php echo htmlspecialchars($group['group_code']); ?></span>
                            </div>
                        </div>
                        <div class="card-body">
                           <div class="row">
    <div class="col-md-6">
        <p><strong>Group Code:</strong> <?php echo htmlspecialchars($group['group_code'] ?? 'N/A'); ?></p>
        <p><strong>Created By:</strong> <?php echo htmlspecialchars($group['created_by_name'] ?? 'Unknown'); ?></p>
        <p><strong>Created On:</strong> <?php echo date('F j, Y', strtotime($group['created_at'] ?? 'now')); ?></p>
    </div>
    <div class="col-md-6">
        <p><strong>Maximum Members:</strong> <?php echo $group['max_members'] ?? 0; ?></p>
        <p><strong>Current Members:</strong> <?php echo count($groupMembers); ?></p>
        <p><strong>Department:</strong> <?php echo htmlspecialchars($group['dept_name'] ?? 'Not specified'); ?></p>
    </div>
</div>
                            
                            <!-- Group Members -->
                            <h6 class="mt-4 mb-3">Group Members</h6>
                            <div class="row">
                                <?php foreach ($groupMembers as $member): 
                                    $isCurrentUser = $member['student_id'] == $user['user_id'];
                                    $initials = strtoupper(substr($member['full_name'], 0, 2));
                                ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card group-card">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center">
                                                <div class="member-avatar <?php echo $member['role'] === 'leader' ? 'leader-badge' : 'member-badge'; ?> me-3">
                                                    <?php echo $initials; ?>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1">
                                                        <?php echo htmlspecialchars($member['full_name']); ?>
                                                        <?php if ($isCurrentUser): ?>
                                                            <span class="badge bg-info">You</span>
                                                        <?php endif; ?>
                                                    </h6>
                                                    <p class="mb-1 small">
                                                        <i class="fas fa-id-card"></i> <?php echo htmlspecialchars($member['username']); ?><br>
                                                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($member['email']); ?>
                                                    </p>
                                                    <span class="badge bg-<?php echo $member['role'] === 'leader' ? 'primary' : 'secondary'; ?>">
                                                        <?php echo ucfirst($member['role']); ?>
                                                    </span>
                                                    
                                                    <?php if ($member['project_title']): ?>
                                                        <div class="mt-2">
                                                            <small class="text-muted">
                                                                <i class="fas fa-project-diagram"></i> 
                                                                <?php echo htmlspecialchars($member['project_title']); ?>
                                                            </small>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Group Actions -->
                            <div class="mt-4">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    <strong>Note:</strong> 
                                    <?php if (count($groupMembers) < $group['max_members']): ?>
                                        You have <?php echo $group['max_members'] - count($groupMembers); ?> spot(s) available. 
                                        Share your group code with classmates to invite them.
                                    <?php else: ?>
                                        Your group is full. No more members can be added.
                                    <?php endif; ?>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <!-- Leave Group Button (only for non-leaders) -->
                                    <?php 
                                    $userRole = null;
                                    foreach ($groupMembers as $member) {
                                        if ($member['student_id'] == $user['user_id']) {
                                            $userRole = $member['role'];
                                            break;
                                        }
                                    }
                                    ?>
                                    
                                    <?php if ($userRole !== 'leader'): ?>
                                    <form method="POST" action="" class="d-inline">
                                        <button type="submit" name="leave_group" class="btn btn-outline-danger" 
                                                onclick="return confirm('Are you sure you want to leave this group?')">
                                            <i class="fas fa-sign-out-alt"></i> Leave Group
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    
                                    <a href="my_project.php" class="btn btn-primary">
                                        <i class="fas fa-project-diagram"></i> Submit Project Title
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Available Groups -->
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-users me-2"></i>
                                Available Groups
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($availableGroups)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-users-slash fa-4x text-muted mb-3"></i>
                                    <h4>No Groups Available</h4>
                                    <p class="text-muted">All groups are full or no groups have been created yet.</p>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createGroupModal">
                                        <i class="fas fa-plus-circle"></i> Create Your Own Group
                                    </button>
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($availableGroups as $availableGroup): 
                                        $memberCount = $availableGroup['member_count'];
                                        $isFull = $memberCount >= $availableGroup['max_members'];
                                    ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="card group-card <?php echo $isFull ? 'group-full' : ''; ?>">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-3">
                                                    <div>
                                                        <h5 class="card-title"><?php echo htmlspecialchars($availableGroup['group_name']); ?></h5>
                                                        <p class="card-text">
                                                            <small class="text-muted">
                                                                Code: <?php echo htmlspecialchars($availableGroup['group_code']); ?>
                                                            </small>
                                                        </p>
                                                    </div>
                                                    <span class="badge bg-<?php echo $isFull ? 'danger' : 'success'; ?>">
                                                        <?php echo $memberCount; ?>/<?php echo $availableGroup['max_members']; ?>
                                                    </span>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <p class="mb-1">
                                                        <i class="fas fa-user-tie"></i> 
                                                        Leader: <?php echo htmlspecialchars($availableGroup['leader_name']); ?>
                                                    </p>
                                                    <p class="mb-1">
                                                        <i class="fas fa-calendar"></i> 
                                                        Created: <?php echo date('M d, Y', strtotime($availableGroup['created_at'])); ?>
                                                    </p>
                                                </div>
                                                
                                                <?php if (!$isFull): ?>
                                                <form method="POST" action="">
                                                    <input type="hidden" name="group_id" value="<?php echo $availableGroup['id']; ?>">
                                                    <button type="submit" name="join_group" class="btn btn-primary w-100">
                                                        <i class="fas fa-sign-in-alt"></i> Join Group
                                                    </button>
                                                </form>
                                                <?php else: ?>
                                                <button class="btn btn-secondary w-100" disabled>
                                                    <i class="fas fa-ban"></i> Group Full
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Right Column: Information & Actions -->
            <div class="col-md-4">
                <!-- Group Guidelines -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            Group Guidelines
                        </h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled">
                            <li class="mb-2">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                Groups are required for technology department projects
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                Maximum 5 members per group
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                Minimum 2 members per group
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                Group leader creates the group
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                Members can join using group code
                            </li>
                            <li>
                                <i class="fas fa-check-circle text-success me-2"></i>
                                Once in a group, submit your project title
                            </li>
                        </ul>
                    </div>
                </div>
                
                <!-- Quick Stats -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-bar me-2"></i>
                            Quick Stats
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Available Groups</span>
                                <strong><?php echo count($availableGroups); ?></strong>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-success" 
                                     style="width: <?php echo min(100, count($availableGroups) * 20); ?>%"></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Your Status</span>
                                <strong><?php echo $group ? 'In Group' : 'Not in Group'; ?></strong>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Total Students in Batch</span>
                                <strong>50</strong>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Create Group Info -->
                <?php if (!$group): ?>
                <div class="card">
                    <div class="card-header bg-warning">
                        <h5 class="mb-0">
                            <i class="fas fa-plus-circle me-2"></i>
                            Create Your Own Group
                        </h5>
                    </div>
                    <div class="card-body">
                        <p>If you can't find a suitable group, create your own:</p>
                        <ul>
                            <li>Choose a unique group name</li>
                            <li>Create a group code (others will use this to join)</li>
                            <li>Set maximum members (2-5)</li>
                            <li>You'll become the group leader</li>
                        </ul>
                        <button class="btn btn-warning w-100" data-bs-toggle="modal" data-bs-target="#createGroupModal">
                            <i class="fas fa-plus-circle"></i> Create New Group
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
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
                            <small class="text-muted">Choose a descriptive name for your group</small>
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
                            <small class="text-muted">Unique code that others will use to join your group</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Maximum Members</label>
                            <select class="form-control" name="max_members" required>
                                <option value="2">2 Members</option>
                                <option value="3" selected>3 Members</option>
                                <option value="4">4 Members</option>
                                <option value="5">5 Members</option>
                            </select>
                            <small class="text-muted">Technology departments require 3-5 members</small>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Note:</strong> You will become the group leader. 
                            Share your group code with classmates to invite them.
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
            const departmentCode = 'CS'; // In real app, get from user data
            const year = '26';
            const randomNum = Math.floor(Math.random() * 90) + 10; // 10-99
            
            const groupCode = departmentCode + 'G' + year + randomNum;
            document.querySelector('input[name="group_code"]').value = groupCode;
        });
        
        // Auto-focus on group name input
        const createGroupModal = document.getElementById('createGroupModal');
        if (createGroupModal) {
            createGroupModal.addEventListener('shown.bs.modal', function() {
                const groupNameInput = this.querySelector('input[name="group_name"]');
                if (groupNameInput) groupNameInput.focus();
            });
        }
    </script>
</body>
</html>