<?php
/**
 * GroupManager Class
 * Handles all group-related operations for technology departments
 */
class GroupManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Create a new group
     */
    public function createGroup($data) {
        try {
            // Check if group code already exists
            $checkQuery = "SELECT id FROM groups WHERE group_code = :group_code";
            $stmt = $this->db->prepare($checkQuery);
            $stmt->execute([':group_code' => strtoupper($data['group_code'])]);
            
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Group code already exists'];
            }
            
            // Check if student is already in a group for this batch
            $studentCheck = "SELECT gm.id FROM group_members gm 
                            JOIN groups g ON gm.group_id = g.id 
                            WHERE gm.student_id = :student_id 
                            AND g.batch_id = :batch_id";
            
            $stmt = $this->db->prepare($studentCheck);
            $stmt->execute([
                ':student_id' => $data['created_by'],
                ':batch_id' => $data['batch_id']
            ]);
            
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'You are already in a group for this batch'];
            }
            
            // Create group
            $query = "INSERT INTO groups (group_name, group_code, department_id, batch_id, max_members, created_by) 
                     VALUES (:group_name, :group_code, :department_id, :batch_id, :max_members, :created_by)";
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                ':group_name' => trim($data['group_name']),
                ':group_code' => strtoupper(trim($data['group_code'])),
                ':department_id' => $data['department_id'],
                ':batch_id' => $data['batch_id'],
                ':max_members' => $data['max_members'],
                ':created_by' => $data['created_by']
            ]);
            
            if ($result) {
                $groupId = $this->db->lastInsertId();
                
                // Add creator as leader
                $this->addGroupMember($groupId, $data['created_by'], 'leader');
                
                return ['success' => true, 'message' => 'Group created successfully!', 'group_id' => $groupId];
            }
            
            return ['success' => false, 'message' => 'Failed to create group'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Add member to group
     */
    public function addGroupMember($groupId, $studentId, $role = 'member') {
        try {
            // Check if group exists and has space
            $group = $this->getGroupById($groupId);
            if (!$group) {
                return ['success' => false, 'message' => 'Group not found'];
            }
            
            // Check current member count
            $currentMembers = $this->getGroupMembers($groupId);
            if (count($currentMembers) >= $group['max_members']) {
                return ['success' => false, 'message' => 'Group is full'];
            }
            
            // Check if student is already in a group for this batch
            $checkQuery = "SELECT gm.id FROM group_members gm 
                          JOIN groups g ON gm.group_id = g.id 
                          WHERE gm.student_id = :student_id 
                          AND g.batch_id = :batch_id";
            
            $stmt = $this->db->prepare($checkQuery);
            $stmt->execute([
                ':student_id' => $studentId,
                ':batch_id' => $group['batch_id']
            ]);
            
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Student is already in a group for this batch'];
            }
            
            // Add member
            $query = "INSERT INTO group_members (group_id, student_id, role) 
                     VALUES (:group_id, :student_id, :role)";
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                ':group_id' => $groupId,
                ':student_id' => $studentId,
                ':role' => $role
            ]);
            
            if ($result) {
                return ['success' => true, 'message' => 'Student added to group successfully'];
            }
            
            return ['success' => false, 'message' => 'Failed to add student to group'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Remove member from group
     */
    public function removeGroupMember($groupId, $studentId) {
        try {
            // Check if member is the group leader
            $member = $this->getGroupMember($groupId, $studentId);
            if ($member && $member['role'] === 'leader') {
                return ['success' => false, 'message' => 'Cannot remove group leader'];
            }
            
            $query = "DELETE FROM group_members 
                     WHERE group_id = :group_id 
                     AND student_id = :student_id";
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                ':group_id' => $groupId,
                ':student_id' => $studentId
            ]);
            
            if ($result) {
                return ['success' => true, 'message' => 'Member removed from group'];
            }
            
            return ['success' => false, 'message' => 'Failed to remove member'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get group by ID
     */
   public function getGroupById($groupId) {
    try {
        $query = "SELECT g.*, 
                 d.dept_name, 
                 b.batch_name, b.batch_year,
                 s.full_name as created_by_name
                 FROM groups g
                 JOIN departments d ON g.department_id = d.id
                 JOIN batches b ON g.batch_id = b.id
                 JOIN students s ON g.created_by = s.id
                 WHERE g.id = :group_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([':group_id' => $groupId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error fetching group: " . $e->getMessage());
        return null;
    }
}
    /**
     * Get group members
     */
    public function getGroupMembers($groupId) {
        try {
            $query = "SELECT gm.*, 
                     s.full_name, s.username, s.email,
                     p.title as project_title, p.status as project_status
                     FROM group_members gm
                     JOIN students s ON gm.student_id = s.id
                     LEFT JOIN projects p ON s.id = p.student_id AND p.group_id = :group_id
                     WHERE gm.group_id = :group_id
                     ORDER BY gm.role DESC, gm.joined_at";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([':group_id' => $groupId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching group members: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get specific group member
     */
    public function getGroupMember($groupId, $studentId) {
        try {
            $query = "SELECT gm.* FROM group_members gm 
                     WHERE gm.group_id = :group_id 
                     AND gm.student_id = :student_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':group_id' => $groupId,
                ':student_id' => $studentId
            ]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching group member: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get student's group
     */
    public function getStudentGroup($studentId, $batchId = null) {
    try {
        $whereClause = "WHERE gm.student_id = :student_id";
        $params = [':student_id' => $studentId];
        
        if ($batchId) {
            $whereClause .= " AND g.batch_id = :batch_id";
            $params[':batch_id'] = $batchId;
        }
        
        $query = "SELECT g.*, gm.role,
                 d.dept_name,
                 s.full_name as created_by_name
                 FROM groups g
                 JOIN group_members gm ON g.id = gm.group_id
                 JOIN departments d ON g.department_id = d.id
                 JOIN students s ON g.created_by = s.id
                 $whereClause
                 ORDER BY g.created_at DESC
                 LIMIT 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error fetching student group: " . $e->getMessage());
        return null;
    }
}
    
    /**
     * Get groups by department and batch
     */
    /**
 * Get groups by department and batch
 */
public function getGroupsByDepartmentBatch($departmentId, $batchId) {
    try {
        $query = "SELECT g.*, 
                 (SELECT COUNT(*) FROM group_members gm WHERE gm.group_id = g.id) as member_count,
                 s.full_name as leader_name
                 FROM groups g
                 LEFT JOIN group_members gm ON g.id = gm.group_id AND gm.role = 'leader'
                 LEFT JOIN students s ON gm.student_id = s.id
                 WHERE g.department_id = :department_id 
                 AND g.batch_id = :batch_id
                 ORDER BY g.created_at DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':department_id' => $departmentId,
            ':batch_id' => $batchId
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error fetching groups: " . $e->getMessage());
        return [];
    }
}
    /**
     * Get available groups (with space) for a student
     */
    public function getAvailableGroups($studentId, $departmentId, $batchId) {
    try {
        $query = "SELECT g.*, 
                 (SELECT COUNT(*) FROM group_members gm WHERE gm.group_id = g.id) as member_count,
                 s.full_name as leader_name
                 FROM groups g
                 LEFT JOIN group_members gm ON g.id = gm.group_id AND gm.role = 'leader'
                 LEFT JOIN students s ON gm.student_id = s.id
                 WHERE g.department_id = :department_id 
                 AND g.batch_id = :batch_id
                 AND g.id NOT IN (
                     SELECT group_id FROM group_members WHERE student_id = :student_id
                 )
                 HAVING member_count < g.max_members
                 ORDER BY g.created_at DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':department_id' => $departmentId,
            ':batch_id' => $batchId,
            ':student_id' => $studentId
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error fetching available groups: " . $e->getMessage());
        return [];
    }
}
    
    /**
     * Update group information
     */
    public function updateGroup($groupId, $data) {
        try {
            $query = "UPDATE groups SET 
                     group_name = :group_name,
                     max_members = :max_members
                     WHERE id = :group_id";
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                ':group_name' => trim($data['group_name']),
                ':max_members' => $data['max_members'],
                ':group_id' => $groupId
            ]);
            
            if ($result) {
                return ['success' => true, 'message' => 'Group updated successfully'];
            }
            
            return ['success' => false, 'message' => 'Failed to update group'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Delete group
     */
    public function deleteGroup($groupId) {
        try {
            // Check if group has members
            $members = $this->getGroupMembers($groupId);
            if (count($members) > 0) {
                return ['success' => false, 'message' => 'Cannot delete group with members'];
            }
            
            $query = "DELETE FROM groups WHERE id = :group_id";
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([':group_id' => $groupId]);
            
            if ($result) {
                return ['success' => true, 'message' => 'Group deleted successfully'];
            }
            
            return ['success' => false, 'message' => 'Failed to delete group'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Generate unique group code
     */
    public function generateGroupCode($departmentCode, $batchYear) {
        $year = substr($batchYear, -2);
        $baseCode = $departmentCode . 'G' . $year;
        
        // Try to find a unique code
        for ($i = 1; $i <= 99; $i++) {
            $code = $baseCode . str_pad($i, 2, '0', STR_PAD_LEFT);
            
            $checkQuery = "SELECT id FROM groups WHERE group_code = :code";
            $stmt = $this->db->prepare($checkQuery);
            $stmt->execute([':code' => $code]);
            
            if (!$stmt->fetch()) {
                return $code;
            }
        }
        
        // If all codes are taken, generate random
        return $baseCode . rand(100, 999);
    }
}
?>