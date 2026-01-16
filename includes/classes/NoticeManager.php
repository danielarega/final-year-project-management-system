<?php
/**
 * NoticeManager Class
 * Handles all notice-related operations
 */
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
                ':title' => trim($data['title']),
                ':content' => trim($data['content']),
                ':department_id' => $data['department_id'],
                ':batch_id' => !empty($data['batch_id']) ? $data['batch_id'] : null,
                ':created_by' => $data['created_by'],
                ':user_type' => $data['user_type'] ?? 'all',
                ':priority' => $data['priority'] ?? 'medium',
                ':is_active' => $data['is_active'] ?? true
            ]);
            
            if ($result) {
                $noticeId = $this->db->lastInsertId();
                
                // If notice is for specific users, create read records
                if ($data['user_type'] !== 'all') {
                    $this->markNoticeForUsers($noticeId, $data['department_id'], $data['batch_id'], $data['user_type']);
                }
                
                return ['success' => true, 'message' => 'Notice created successfully', 'id' => $noticeId];
            }
            
            return ['success' => false, 'message' => 'Failed to create notice'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Mark notice for specific users
     */
    private function markNoticeForUsers($noticeId, $departmentId, $batchId, $userType) {
        try {
            // This is a simplified version. In production, you would mark for specific users
            return true;
        } catch (PDOException $e) {
            error_log("Error marking notice for users: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get notices for a department
     */
    public function getDepartmentNotices($departmentId, $batchId = null, $limit = null) {
        try {
            $whereClause = "WHERE n.department_id = :department_id AND n.is_active = 1";
            $params = [':department_id' => $departmentId];
            
            if ($batchId) {
                $whereClause .= " AND (n.batch_id = :batch_id OR n.batch_id IS NULL)";
                $params[':batch_id'] = $batchId;
            }
            
            $limitClause = $limit ? "LIMIT $limit" : "";
            
            $query = "SELECT n.*, 
                     a.full_name as created_by_name,
                     d.dept_name,
                     b.batch_name, b.batch_year
                     FROM notices n
                     LEFT JOIN admins a ON n.created_by = a.id
                     LEFT JOIN departments d ON n.department_id = d.id
                     LEFT JOIN batches b ON n.batch_id = b.id
                     $whereClause
                     ORDER BY 
                         CASE n.priority 
                             WHEN 'urgent' THEN 1
                             WHEN 'high' THEN 2
                             WHEN 'medium' THEN 3
                             WHEN 'low' THEN 4
                         END,
                         n.created_at DESC
                     $limitClause";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching notices: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get notice by ID
     */
    public function getNoticeById($id) {
        try {
            $query = "SELECT n.*, 
                     a.full_name as created_by_name,
                     d.dept_name,
                     b.batch_name, b.batch_year
                     FROM notices n
                     LEFT JOIN admins a ON n.created_by = a.id
                     LEFT JOIN departments d ON n.department_id = d.id
                     LEFT JOIN batches b ON n.batch_id = b.id
                     WHERE n.id = :id";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([':id' => $id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching notice: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Update notice
     */
    public function updateNotice($id, $data) {
        try {
            $query = "UPDATE notices SET 
                     title = :title,
                     content = :content,
                     batch_id = :batch_id,
                     user_type = :user_type,
                     priority = :priority,
                     is_active = :is_active,
                     updated_at = NOW()
                     WHERE id = :id";
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                ':title' => trim($data['title']),
                ':content' => trim($data['content']),
                ':batch_id' => !empty($data['batch_id']) ? $data['batch_id'] : null,
                ':user_type' => $data['user_type'] ?? 'all',
                ':priority' => $data['priority'] ?? 'medium',
                ':is_active' => $data['is_active'] ?? true,
                ':id' => $id
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
     * Delete notice
     */
    public function deleteNotice($id) {
        try {
            $query = "DELETE FROM notices WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([':id' => $id]);
            
            if ($result) {
                return ['success' => true, 'message' => 'Notice deleted successfully'];
            }
            
            return ['success' => false, 'message' => 'Failed to delete notice'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Mark notice as read
     */
    public function markAsRead($noticeId, $userId, $userType) {
        try {
            $query = "INSERT INTO notice_reads (notice_id, user_id, user_type) 
                     VALUES (:notice_id, :user_id, :user_type) 
                     ON DUPLICATE KEY UPDATE read_at = CURRENT_TIMESTAMP";
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                ':notice_id' => $noticeId,
                ':user_id' => $userId,
                ':user_type' => $userType
            ]);
            
            return $result;
            
        } catch (PDOException $e) {
            error_log("Error marking notice as read: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if notice is read by user
     */
    public function isNoticeRead($noticeId, $userId, $userType) {
        try {
            $query = "SELECT id FROM notice_reads 
                     WHERE notice_id = :notice_id 
                     AND user_id = :user_id 
                     AND user_type = :user_type";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':notice_id' => $noticeId,
                ':user_id' => $userId,
                ':user_type' => $userType
            ]);
            
            return (bool)$stmt->fetch();
            
        } catch (PDOException $e) {
            error_log("Error checking notice read status: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get unread notices count for user
     */
    public function getUnreadCount($userId, $userType, $departmentId, $batchId = null) {
        try {
            $whereClause = "WHERE n.department_id = :department_id 
                           AND n.is_active = 1 
                           AND (n.user_type = 'all' OR n.user_type = :user_type)
                           AND nr.id IS NULL";
            
            $params = [
                ':department_id' => $departmentId,
                ':user_type' => $userType
            ];
            
            if ($batchId) {
                $whereClause .= " AND (n.batch_id = :batch_id OR n.batch_id IS NULL)";
                $params[':batch_id'] = $batchId;
            }
            
            $query = "SELECT COUNT(DISTINCT n.id) as count
                     FROM notices n
                     LEFT JOIN notice_reads nr ON n.id = nr.notice_id 
                         AND nr.user_id = :user_id 
                         AND nr.user_type = :user_type
                     $whereClause";
            
            $params[':user_id'] = $userId;
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['count'] ?? 0;
            
        } catch (PDOException $e) {
            error_log("Error fetching unread count: " . $e->getMessage());
            return 0;
        }
    }
}
?>