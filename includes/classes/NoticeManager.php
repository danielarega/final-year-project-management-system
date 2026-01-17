<!-- includes/classes/NoticeManager.php -->
<?php
class NoticeManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Create a new notice
     */
    public function createNotice($data) {
        try {
            $query = "INSERT INTO notices (title, content, department_id, batch_id, 
                     created_by, user_type, priority, is_active) 
                     VALUES (:title, :content, :department_id, :batch_id, 
                     :created_by, :user_type, :priority, :is_active)";
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                ':title' => $data['title'],
                ':content' => $data['content'],
                ':department_id' => $data['department_id'],
                ':batch_id' => $data['batch_id'],
                ':created_by' => $data['created_by'],
                ':user_type' => $data['user_type'],
                ':priority' => $data['priority'],
                ':is_active' => $data['is_active']
            ]);
            
            if ($result) {
                return ['success' => true, 'message' => 'Notice posted successfully', 'id' => $this->db->lastInsertId()];
            }
            
            return ['success' => false, 'message' => 'Failed to post notice'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Update a notice
     */
    public function updateNotice($noticeId, $data) {
        try {
            $query = "UPDATE notices SET 
                     title = :title,
                     content = :content,
                     batch_id = :batch_id,
                     user_type = :user_type,
                     priority = :priority,
                     is_active = :is_active,
                     updated_at = NOW()
                     WHERE id = :notice_id";
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                ':title' => $data['title'],
                ':content' => $data['content'],
                ':batch_id' => $data['batch_id'],
                ':user_type' => $data['user_type'],
                ':priority' => $data['priority'],
                ':is_active' => $data['is_active'],
                ':notice_id' => $noticeId
            ]);
            
            if ($result) {
                return ['success' => true, 'message' => 'Notice updated successfully'];
            }
            
            return ['success' => false, 'message' => 'Failed to update notice'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Delete a notice
     */
    public function deleteNotice($noticeId) {
        try {
            $query = "DELETE FROM notices WHERE id = :notice_id";
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([':notice_id' => $noticeId]);
            
            if ($result) {
                return ['success' => true, 'message' => 'Notice deleted successfully'];
            }
            
            return ['success' => false, 'message' => 'Failed to delete notice'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get notice by ID
     */
    public function getNoticeById($noticeId) {
        try {
            $query = "SELECT n.*, 
                     d.dept_name,
                     b.batch_name, b.batch_year,
                     a.full_name as created_by_name
                     FROM notices n
                     JOIN departments d ON n.department_id = d.id
                     LEFT JOIN batches b ON n.batch_id = b.id
                     LEFT JOIN admins a ON n.created_by = a.id
                     WHERE n.id = :notice_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([':notice_id' => $noticeId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching notice: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get all notices for a department
     */
    /**
 * Get department notices for a specific role
 */
public function getDepartmentNotices($departmentId, $role = null, $limit = null) {
    try {
        $query = "SELECT n.*, 
                 b.batch_name, b.batch_year,
                 a.full_name as created_by_name
                 FROM notices n
                 LEFT JOIN batches b ON n.batch_id = b.id
                 LEFT JOIN admins a ON n.created_by = a.id
                 WHERE n.department_id = :department_id
                 AND n.is_active = TRUE";
        
        // Add role filter if provided
        if ($role && $role !== 'all') {
            $query .= " AND (n.user_type = 'all' OR n.user_type = :user_type)";
        }
        
        $query .= " ORDER BY 
                     CASE n.priority 
                         WHEN 'urgent' THEN 1
                         WHEN 'high' THEN 2
                         WHEN 'medium' THEN 3
                         WHEN 'low' THEN 4
                     END,
                     n.created_at DESC";
        
        // Add limit if provided
        if ($limit) {
            $query .= " LIMIT :limit";
        }
        
        $stmt = $this->db->prepare($query);
        $params = [':department_id' => $departmentId];
        
        if ($role && $role !== 'all') {
            $params[':user_type'] = $role;
        }
        
        if ($limit) {
            $params[':limit'] = (int)$limit;
        }
        
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error fetching department notices: " . $e->getMessage());
        return [];
    }
}
    
    /**
     * Get active notices for a user
     */
    public function getUserNotices($userId, $userType, $departmentId, $batchId = null) {
        try {
            $whereClause = "WHERE n.department_id = :department_id 
                           AND n.is_active = TRUE
                           AND (n.user_type = 'all' OR n.user_type = :user_type)";
            
            $params = [
                ':department_id' => $departmentId,
                ':user_type' => $userType
            ];
            
            if ($batchId) {
                $whereClause .= " AND (n.batch_id IS NULL OR n.batch_id = :batch_id)";
                $params[':batch_id'] = $batchId;
            }
            
            // Check if notice has been read
            $whereClause .= " AND NOT EXISTS (
                SELECT 1 FROM notice_reads nr 
                WHERE nr.notice_id = n.id 
                AND nr.user_id = :user_id 
                AND nr.user_type = :user_type2
            )";
            
            $params[':user_id'] = $userId;
            $params[':user_type2'] = $userType;
            
            $query = "SELECT n.*, 
                     b.batch_name,
                     a.full_name as created_by_name
                     FROM notices n
                     LEFT JOIN batches b ON n.batch_id = b.id
                     LEFT JOIN admins a ON n.created_by = a.id
                     $whereClause
                     ORDER BY 
                         CASE n.priority 
                             WHEN 'urgent' THEN 1
                             WHEN 'high' THEN 2
                             WHEN 'medium' THEN 3
                             WHEN 'low' THEN 4
                         END,
                         n.created_at DESC
                     LIMIT 10";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching user notices: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Mark notice as read for a user
     */
    public function markNoticeAsRead($noticeId, $userId, $userType) {
        try {
            // Check if already read
            $checkQuery = "SELECT id FROM notice_reads 
                          WHERE notice_id = :notice_id 
                          AND user_id = :user_id 
                          AND user_type = :user_type";
            
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->execute([
                ':notice_id' => $noticeId,
                ':user_id' => $userId,
                ':user_type' => $userType
            ]);
            
            if ($checkStmt->fetch()) {
                return ['success' => true, 'message' => 'Already marked as read'];
            }
            
            // Mark as read
            $query = "INSERT INTO notice_reads (notice_id, user_id, user_type) 
                     VALUES (:notice_id, :user_id, :user_type)";
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                ':notice_id' => $noticeId,
                ':user_id' => $userId,
                ':user_type' => $userType
            ]);
            
            if ($result) {
                return ['success' => true, 'message' => 'Notice marked as read'];
            }
            
            return ['success' => false, 'message' => 'Failed to mark notice as read'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get notice statistics
     */
    public function getNoticeStatistics($departmentId) {
        try {
            $query = "SELECT 
                     COUNT(*) as total,
                     COUNT(CASE WHEN is_active = TRUE THEN 1 END) as active,
                     COUNT(CASE WHEN priority = 'urgent' THEN 1 END) as urgent,
                     COUNT(CASE WHEN batch_id IS NOT NULL THEN 1 END) as batch_specific,
                     COUNT(CASE WHEN user_type = 'student' THEN 1 END) as student_notices,
                     COUNT(CASE WHEN user_type = 'teacher' THEN 1 END) as teacher_notices
                     FROM notices 
                     WHERE department_id = :department_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([':department_id' => $departmentId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching notice statistics: " . $e->getMessage());
            return null;
        }
    }
    /**
 * Get count of unread notices for a user
 */
public function getUnreadCount($userId, $userRole = null, $departmentId = null) {
    try {
        $params = [':user_id' => $userId];
        
        // Base query to get relevant notices
        $sql = "SELECT COUNT(DISTINCT n.id) as unread_count
                FROM notices n
                LEFT JOIN notice_reads nr ON n.id = nr.notice_id AND nr.user_id = :user_id
                WHERE n.status = 'active'
                AND (n.published_at <= NOW() OR n.published_at IS NULL)
                AND nr.id IS NULL"; // Not read by this user
        
        // Add conditions based on user role and department
        if ($userRole === 'student') {
            // Students see notices for their department and all-department notices
            $sql .= " AND (n.target_audience LIKE '%students%' OR n.target_audience = 'all')";
            if ($departmentId) {
                $sql .= " AND (n.department_id = :department_id OR n.department_id IS NULL)";
                $params[':department_id'] = $departmentId;
            }
        } elseif ($userRole === 'teacher') {
            // Teachers see notices for their department and all-department notices
            $sql .= " AND (n.target_audience LIKE '%teachers%' OR n.target_audience = 'all')";
            if ($departmentId) {
                $sql .= " AND (n.department_id = :department_id OR n.department_id IS NULL)";
                $params[':department_id'] = $departmentId;
            }
        } elseif ($userRole === 'admin' || $userRole === 'hod') {
            // Admins/HODs see all notices
            // No additional filters needed
        } else {
            // Default: show notices for all users
            $sql .= " AND (n.target_audience = 'all')";
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['unread_count'] ?? 0;
        
    } catch (PDOException $e) {
        error_log("Error in getUnreadCount: " . $e->getMessage());
        return 0;
    }
}
  /**
 * Mark a notice as read for a user
 */
public function markAsRead($noticeId, $userId) {
    try {
        // Check if already read
        $checkSql = "SELECT id FROM notice_reads WHERE notice_id = :notice_id AND user_id = :user_id";
        $checkStmt = $this->db->prepare($checkSql);
        $checkStmt->execute([
            ':notice_id' => $noticeId,
            ':user_id' => $userId
        ]);
        
        if ($checkStmt->rowCount() === 0) {
            // Mark as read
            $insertSql = "INSERT INTO notice_reads (notice_id, user_id, read_at) 
                         VALUES (:notice_id, :user_id, NOW())";
            $insertStmt = $this->db->prepare($insertSql);
            $insertStmt->execute([
                ':notice_id' => $noticeId,
                ':user_id' => $userId
            ]);
            return true;
        }
        
        return true; // Already read
        
    } catch (PDOException $e) {
        error_log("Error in markAsRead: " . $e->getMessage());
        return false;
    }
}

/**
 * Mark all notices as read for a user
 */
public function markAllAsRead($userId, $userRole = null, $departmentId = null) {
    try {
        // Get all unread notice IDs for this user
        $params = [':user_id' => $userId];
        
        $sql = "SELECT n.id
                FROM notices n
                LEFT JOIN notice_reads nr ON n.id = nr.notice_id AND nr.user_id = :user_id
                WHERE n.status = 'active'
                AND (n.published_at <= NOW() OR n.published_at IS NULL)
                AND nr.id IS NULL";
        
        // Add role-based filters if provided
        if ($userRole === 'student') {
            $sql .= " AND (n.target_audience LIKE '%students%' OR n.target_audience = 'all')";
            if ($departmentId) {
                $sql .= " AND (n.department_id = :department_id OR n.department_id IS NULL)";
                $params[':department_id'] = $departmentId;
            }
        } elseif ($userRole === 'teacher') {
            $sql .= " AND (n.target_audience LIKE '%teachers%' OR n.target_audience = 'all')";
            if ($departmentId) {
                $sql .= " AND (n.department_id = :department_id OR n.department_id IS NULL)";
                $params[':department_id'] = $departmentId;
            }
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $notices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Mark each notice as read
        foreach ($notices as $notice) {
            $this->markAsRead($notice['id'], $userId);
        }
        
        return ['success' => true, 'message' => 'All notices marked as read'];
        
    } catch (PDOException $e) {
        error_log("Error in markAllAsRead: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to mark all notices as read'];
    }
}
/**
     * Get read statistics for a notice
     */
    public function getNoticeReadStats($noticeId) {
        try {
            $query = "SELECT 
                     COUNT(CASE WHEN user_type = 'student' THEN 1 END) as students_read,
                     COUNT(CASE WHEN user_type = 'teacher' THEN 1 END) as teachers_read,
                     COUNT(CASE WHEN user_type = 'admin' THEN 1 END) as admins_read
                     FROM notice_reads 
                     WHERE notice_id = :notice_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([':notice_id' => $noticeId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching notice read stats: " . $e->getMessage());
            return ['students_read' => 0, 'teachers_read' => 0, 'admins_read' => 0];
        }
    }
}
?>