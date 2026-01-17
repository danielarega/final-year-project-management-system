<?php
class SupervisorManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    /**
 * Get teacher dashboard statistics
 */
public function getTeacherDashboardStats($teacherId) {
    try {
        $sql = "SELECT 
                    -- Total projects assigned
                    COUNT(DISTINCT ps.project_id) as total_projects,
                    -- Projects by status
                    SUM(CASE WHEN p.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                    SUM(CASE WHEN p.status = 'approved' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN p.status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN p.status = 'submitted' THEN 1 ELSE 0 END) as submitted,
                    -- Project types
                    SUM(CASE WHEN p.project_type = 'individual' THEN 1 ELSE 0 END) as individual_projects,
                    SUM(CASE WHEN p.project_type = 'group' THEN 1 ELSE 0 END) as group_projects,
                    -- Recent activity
                    MAX(ps.assigned_at) as last_assigned_date,
                    COUNT(DISTINCT CASE 
                        WHEN ps.assigned_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
                        THEN ps.project_id 
                    END) as recent_assignments
                FROM project_supervisors ps
                INNER JOIN projects p ON ps.project_id = p.id
                WHERE ps.teacher_id = :teacher_id
                AND ps.status = 'active'";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':teacher_id' => $teacherId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If no projects, return zeros
        if (!$stats) {
            $stats = [
                'total_projects' => 0,
                'in_progress' => 0,
                'approved' => 0,
                'completed' => 0,
                'submitted' => 0,
                'individual_projects' => 0,
                'group_projects' => 0,
                'last_assigned_date' => null,
                'recent_assignments' => 0
            ];
        }
        
        return $stats;
        
    } catch (PDOException $e) {
        error_log("Error in getTeacherDashboardStats: " . $e->getMessage());
        return [
            'total_projects' => 0,
            'in_progress' => 0,
            'approved' => 0,
            'completed' => 0,
            'submitted' => 0,
            'individual_projects' => 0,
            'group_projects' => 0,
            'last_assigned_date' => null,
            'recent_assignments' => 0
        ];
    }
}

/**
 * Get teacher's upcoming deadlines/tasks
 */
public function getTeacherUpcomingTasks($teacherId) {
    try {
        $sql = "SELECT 
                    p.id as project_id,
                    p.title,
                    p.status,
                    p.submitted_at,
                    -- Get milestone deadlines
                    m.milestone_title,
                    m.deadline_date,
                    DATEDIFF(m.deadline_date, CURDATE()) as days_remaining,
                    -- Student/team info
                    CASE 
                        WHEN p.student_id IS NOT NULL THEN us.full_name
                        WHEN p.team_id IS NOT NULL THEN st.team_name
                        ELSE 'Unknown'
                    END as student_or_team_name
                FROM project_supervisors ps
                INNER JOIN projects p ON ps.project_id = p.id
                LEFT JOIN project_milestones m ON p.id = m.project_id
                LEFT JOIN students s ON p.student_id = s.id
                LEFT JOIN users us ON s.user_id = us.user_id
                LEFT JOIN student_teams st ON p.team_id = st.team_id
                WHERE ps.teacher_id = :teacher_id
                AND ps.status = 'active'
                AND m.deadline_date >= CURDATE()
                AND m.deadline_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                AND m.status != 'completed'
                ORDER BY m.deadline_date ASC
                LIMIT 10";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':teacher_id' => $teacherId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error in getTeacherUpcomingTasks: " . $e->getMessage());
        return [];
    }
}
    /**
     * Get supervisor details including specializations and supervising students
     */
    public function getSupervisorDetails($teacherId) {
        try {
            // Get basic teacher info
            $query = "SELECT t.*, 
                     (SELECT COUNT(*) FROM projects p WHERE p.supervisor_id = t.id AND p.status IN ('approved', 'in_progress')) as current_load
                     FROM teachers t 
                     WHERE t.id = :teacher_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([':teacher_id' => $teacherId]);
            $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$teacher) {
                return null;
            }
            
            // Get specializations
            $specQuery = "SELECT * FROM teacher_specializations 
                         WHERE teacher_id = :teacher_id 
                         ORDER BY level DESC";
            $specStmt = $this->db->prepare($specQuery);
            $specStmt->execute([':teacher_id' => $teacherId]);
            $teacher['specializations'] = $specStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get supervising students
            $studentsQuery = "SELECT p.*, 
                             s.full_name as student_name, s.username as student_id,
                             ps.assignment_date as assigned_date
                             FROM projects p
                             JOIN students s ON p.student_id = s.id
                             JOIN project_supervisors ps ON p.id = ps.project_id
                             WHERE p.supervisor_id = :teacher_id 
                             AND ps.status = 'active'
                             ORDER BY ps.assignment_date DESC";
            
            $studentsStmt = $this->db->prepare($studentsQuery);
            $studentsStmt->execute([':teacher_id' => $teacherId]);
            $teacher['supervising_students'] = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $teacher;
            
        } catch (PDOException $e) {
            error_log("Error fetching supervisor details: " . $e->getMessage());
            return null;
        }
    }
    /**
 * Get all projects assigned to a teacher/supervisor
 */
public function getTeacherProjects($teacherId) {
    try {
        $sql = "SELECT 
                    p.id,
                    p.title,
                    p.description,
                    p.problem_statement,
                    p.project_type,
                    p.specialization_id,
                    sp.specialization_name,
                    p.status as project_status,
                    p.submitted_at,
                    p.approved_at,
                    p.student_id,
                    p.team_id,
                    p.batch_id,
                    b.batch_name,
                    b.batch_year,
                    -- Student information
                    CASE 
                        WHEN p.student_id IS NOT NULL THEN us.full_name
                        WHEN p.team_id IS NOT NULL THEN st.team_name
                        ELSE 'Unknown'
                    END as student_or_team_name,
                    CASE 
                        WHEN p.student_id IS NOT NULL THEN 'Individual'
                        WHEN p.team_id IS NOT NULL THEN 'Group'
                        ELSE 'Unknown'
                    END as project_holder_type,
                    -- Assignment information
                    ps.assigned_at,
                    ps.assignment_type,
                    ps.comments as assignment_comments,
                    ps.status as assignment_status,
                    -- Supervisor assignment
                    u.full_name as assigned_by_name
                FROM project_supervisors ps
                INNER JOIN projects p ON ps.project_id = p.id
                LEFT JOIN specializations sp ON p.specialization_id = sp.specialization_id
                LEFT JOIN batches b ON p.batch_id = b.id
                LEFT JOIN students s ON p.student_id = s.id
                LEFT JOIN users us ON s.user_id = us.user_id
                LEFT JOIN student_teams st ON p.team_id = st.team_id
                LEFT JOIN users u ON ps.assigned_by = u.user_id
                WHERE ps.teacher_id = :teacher_id
                AND ps.status = 'active'
                ORDER BY ps.assigned_at DESC, p.status, p.title";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':teacher_id' => $teacherId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error in getTeacherProjects: " . $e->getMessage());
        return [];
    }
}
    /**
     * Get available supervisors for a batch
     */
    public function getAvailableSupervisors($departmentId, $batchId, $excludeTeacherId = null) {
        try {
            $whereClause = "WHERE t.department_id = :department_id 
                           AND sa.batch_id = :batch_id 
                           AND sa.is_available = TRUE
                           AND t.status = 'active'";
            
            $params = [
                ':department_id' => $departmentId,
                ':batch_id' => $batchId
            ];
            
            if ($excludeTeacherId) {
                $whereClause .= " AND t.id != :exclude_id";
                $params[':exclude_id'] = $excludeTeacherId;
            }
            
            $query = "SELECT t.*, 
                     sa.max_students, sa.current_load,
                     (sa.max_students - sa.current_load) as available_slots,
                     GROUP_CONCAT(DISTINCT ts.specialization SEPARATOR ', ') as specializations
                     FROM teachers t
                     JOIN supervisor_availability sa ON t.id = sa.teacher_id
                     LEFT JOIN teacher_specializations ts ON t.id = ts.teacher_id
                     $whereClause
                     GROUP BY t.id
                     HAVING available_slots > 0
                     ORDER BY available_slots DESC, t.full_name";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching available supervisors: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Assign supervisor to project
     */
    public function assignSupervisor($projectId, $teacherId, $adminId, $assignmentType = 'manual') {
        try {
            // Start transaction
            $this->db->beginTransaction();
            
            // Get project details
            $projectQuery = "SELECT p.*, b.id as batch_id FROM projects p 
                            JOIN batches b ON p.batch_id = b.id
                            WHERE p.id = :project_id";
            $projectStmt = $this->db->prepare($projectQuery);
            $projectStmt->execute([':project_id' => $projectId]);
            $project = $projectStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$project) {
                throw new Exception("Project not found");
            }
            
            // Check if teacher is available
            $availabilityQuery = "SELECT * FROM supervisor_availability 
                                WHERE teacher_id = :teacher_id 
                                AND batch_id = :batch_id
                                AND is_available = TRUE
                                AND current_load < max_students";
            
            $availabilityStmt = $this->db->prepare($availabilityQuery);
            $availabilityStmt->execute([
                ':teacher_id' => $teacherId,
                ':batch_id' => $project['batch_id']
            ]);
            $availability = $availabilityStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$availability) {
                throw new Exception("Teacher is not available or has reached maximum capacity for this batch");
            }
            
            // Update project supervisor
            $updateProjectQuery = "UPDATE projects SET supervisor_id = :teacher_id WHERE id = :project_id";
            $updateProjectStmt = $this->db->prepare($updateProjectQuery);
            $updateProjectStmt->execute([
                ':teacher_id' => $teacherId,
                ':project_id' => $projectId
            ]);
            
            // Insert into project_supervisors for history
            $insertSupervisorQuery = "INSERT INTO project_supervisors 
                                     (project_id, teacher_id, assigned_by, assignment_type, status) 
                                     VALUES (:project_id, :teacher_id, :assigned_by, :assignment_type, 'active')";
            
            $insertSupervisorStmt = $this->db->prepare($insertSupervisorQuery);
            $insertSupervisorStmt->execute([
                ':project_id' => $projectId,
                ':teacher_id' => $teacherId,
                ':assigned_by' => $adminId,
                ':assignment_type' => $assignmentType
            ]);
            
            // Update supervisor availability
            $updateAvailabilityQuery = "UPDATE supervisor_availability 
                                       SET current_load = current_load + 1 
                                       WHERE teacher_id = :teacher_id 
                                       AND batch_id = :batch_id";
            
            $updateAvailabilityStmt = $this->db->prepare($updateAvailabilityQuery);
            $updateAvailabilityStmt->execute([
                ':teacher_id' => $teacherId,
                ':batch_id' => $project['batch_id']
            ]);
            
            $this->db->commit();
            return ['success' => true, 'message' => 'Supervisor assigned successfully'];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Auto-assign supervisor based on availability and specializations
     */
    public function autoAssignSupervisor($projectId, $adminId) {
        try {
            // Get project details
            $projectQuery = "SELECT p.*, p.title, b.id as batch_id, 
                            GROUP_CONCAT(DISTINCT ts.specialization) as project_keywords
                            FROM projects p
                            JOIN batches b ON p.batch_id = b.id
                            LEFT JOIN teacher_specializations ts ON ts.specialization LIKE CONCAT('%', p.title, '%')
                            WHERE p.id = :project_id";
            
            $projectStmt = $this->db->prepare($projectQuery);
            $projectStmt->execute([':project_id' => $projectId]);
            $project = $projectStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$project) {
                throw new Exception("Project not found");
            }
            
            // Get available supervisors
            $availableSupervisors = $this->getAvailableSupervisors(
                $project['department_id'], 
                $project['batch_id']
            );
            
            if (empty($availableSupervisors)) {
                throw new Exception("No available supervisors for this batch");
            }
            
            // Try to match by specialization first
            $projectTitle = strtolower($project['title']);
            $bestMatch = null;
            $bestScore = 0;
            
            foreach ($availableSupervisors as $supervisor) {
                $score = 0;
                
                // Check if supervisor has specializations that match project title
                if (!empty($supervisor['specializations'])) {
                    $specializations = explode(', ', $supervisor['specializations']);
                    foreach ($specializations as $spec) {
                        if (strpos($projectTitle, strtolower($spec)) !== false) {
                            $score += 10; // High score for specialization match
                        }
                    }
                }
                
                // Higher score for more available slots
                $score += $supervisor['available_slots'];
                
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestMatch = $supervisor;
                }
            }
            
            // If no match found, use the one with most available slots
            if (!$bestMatch) {
                $bestMatch = $availableSupervisors[0];
                foreach ($availableSupervisors as $supervisor) {
                    if ($supervisor['available_slots'] > $bestMatch['available_slots']) {
                        $bestMatch = $supervisor;
                    }
                }
            }
            
            // Assign the best match
            return $this->assignSupervisor($projectId, $bestMatch['id'], $adminId, 'auto');
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Get supervisor workload statistics
     */
    public function getSupervisorWorkload($departmentId) {
        try {
            $query = "SELECT t.id, t.full_name, t.max_students,
                     COALESCE(sa.current_load, 0) as current_load,
                     (t.max_students - COALESCE(sa.current_load, 0)) as available_slots,
                     CASE 
                         WHEN COALESCE(sa.current_load, 0) >= t.max_students THEN 'Full'
                         WHEN COALESCE(sa.current_load, 0) >= t.max_students * 0.8 THEN 'High'
                         WHEN COALESCE(sa.current_load, 0) >= t.max_students * 0.5 THEN 'Medium'
                         ELSE 'Low'
                     END as workload_level
                     FROM teachers t
                     LEFT JOIN (
                         SELECT supervisor_id, COUNT(*) as current_load 
                         FROM projects 
                         WHERE status IN ('approved', 'in_progress')
                         GROUP BY supervisor_id
                     ) sa ON t.id = sa.supervisor_id
                     WHERE t.department_id = :department_id
                     ORDER BY current_load DESC, t.full_name";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([':department_id' => $departmentId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error fetching supervisor workload: " . $e->getMessage());
            return [];
        }
    }
    // Add these methods to your SupervisorManager.php file

/**
 * Get projects that need supervisors for a specific department and batch
 */
public function getProjectsNeedingSupervisors($departmentId, $batchId) {
    try {
        $sql = "SELECT 
                    p.id,
                    p.title,
                    p.description,
                    p.problem_statement,
                    p.project_type,
                    p.specialization_id,
                    s.specialization_name,
                    p.student_id,
                    p.team_id,
                    p.batch_id,
                    b.batch_name,
                    b.batch_year,
                    p.submitted_at,
                    p.status,
                    CASE 
                        WHEN p.student_id IS NOT NULL THEN u_student.full_name
                        ELSE NULL
                    END as student_name,
                    CASE 
                        WHEN p.team_id IS NOT NULL THEN st.team_name
                        ELSE NULL
                    END as group_name,
                    CASE 
                        WHEN p.team_id IS NOT NULL THEN (
                            SELECT COUNT(*) FROM team_members tm WHERE tm.team_id = p.team_id
                        )
                        ELSE NULL
                    END as group_member_count
                FROM projects p
                LEFT JOIN project_supervisors ps ON p.id = ps.project_id AND ps.status = 'active'
                LEFT JOIN specializations s ON p.specialization_id = s.specialization_id
                LEFT JOIN batches b ON p.batch_id = b.id
                LEFT JOIN students stu ON p.student_id = stu.id
                LEFT JOIN users u_student ON stu.user_id = u_student.user_id
                LEFT JOIN student_teams st ON p.team_id = st.team_id
                WHERE p.department_id = :department_id
                AND p.batch_id = :batch_id
                AND p.status = 'approved'
                AND ps.id IS NULL  -- No active supervisor assigned
                ORDER BY p.submitted_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':department_id' => $departmentId,
            ':batch_id' => $batchId
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error in getProjectsNeedingSupervisors: " . $e->getMessage());
        return [];
    }
}

/**
 * Get teacher workload for a department
 */
public function getTeacherWorkload($departmentId) {
    try {
        $sql = "SELECT 
                    t.id,
                    t.user_id,
                    u.full_name,
                    u.username,
                    t.max_students,
                    t.department_id,
                    t.specialization_id,
                    s.specialization_name,
                    COUNT(ps.project_id) as current_load,
                    COALESCE(GROUP_CONCAT(DISTINCT ts.specialization SEPARATOR ', '), '') as specializations,
                    COUNT(DISTINCT ps.project_id) as supervised_count
                FROM teachers t
                INNER JOIN users u ON t.user_id = u.user_id
                LEFT JOIN teacher_specializations ts ON t.id = ts.teacher_id
                LEFT JOIN specializations s ON t.specialization_id = s.specialization_id
                LEFT JOIN project_supervisors ps ON t.user_id = ps.teacher_id AND ps.status = 'active'
                WHERE t.department_id = :department_id
                AND u.status = 'active'
                GROUP BY t.id
                ORDER BY current_load ASC, u.full_name ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':department_id' => $departmentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error in getTeacherWorkload: " . $e->getMessage());
        return [];
    }
}

/**
 * Auto assign supervisors to all projects needing supervisors
 */
public function autoAssignSupervisors($departmentId, $adminId) {
    try {
        $this->db->beginTransaction();
        
        // Get all projects needing supervisors for this department
        $projectsQuery = "SELECT p.id, p.title, p.specialization_id, p.batch_id 
                         FROM projects p
                         LEFT JOIN project_supervisors ps ON p.id = ps.project_id AND ps.status = 'active'
                         WHERE p.department_id = :department_id
                         AND p.status = 'approved'
                         AND ps.id IS NULL
                         ORDER BY p.submitted_at ASC";
        
        $projectsStmt = $this->db->prepare($projectsQuery);
        $projectsStmt->execute([':department_id' => $departmentId]);
        $projects = $projectsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $assignedCount = 0;
        $errors = [];
        
        foreach ($projects as $project) {
            // Get available teachers for this project
            $availableTeachers = $this->getAvailableTeachersForProject($project['id']);
            
            if (!empty($availableTeachers)) {
                // Assign the teacher with the lowest current load
                $bestTeacher = $availableTeachers[0];
                
                $assignResult = $this->assignSupervisor($project['id'], $bestTeacher['id'], $adminId, 'auto');
                
                if ($assignResult['success']) {
                    $assignedCount++;
                } else {
                    $errors[] = "Project {$project['id']}: " . $assignResult['message'];
                }
            } else {
                $errors[] = "Project {$project['id']}: No available teachers";
            }
        }
        
        $this->db->commit();
        
        if ($assignedCount > 0) {
            $message = "Successfully auto-assigned supervisors to $assignedCount project(s).";
            if (!empty($errors)) {
                $message .= " " . count($errors) . " project(s) could not be assigned.";
            }
            return ['success' => true, 'message' => $message];
        } else {
            return ['success' => false, 'message' => 'No supervisors could be assigned. ' . implode(', ', $errors)];
        }
        
    } catch (Exception $e) {
        $this->db->rollBack();
        return ['success' => false, 'message' => 'Error in auto assignment: ' . $e->getMessage()];
    }
}

/**
 * Get available teachers for a specific project
 */
public function getAvailableTeachers($projectId) {
    try {
        // Get project details
        $projectQuery = "SELECT p.*, s.specialization_name 
                        FROM projects p
                        LEFT JOIN specializations s ON p.specialization_id = s.specialization_id
                        WHERE p.id = :project_id";
        
        $projectStmt = $this->db->prepare($projectQuery);
        $projectStmt->execute([':project_id' => $projectId]);
        $project = $projectStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$project) {
            return [];
        }
        
        // Get available teachers with capacity
        $sql = "SELECT 
                    t.id,
                    t.user_id,
                    u.full_name,
                    u.username,
                    t.max_students,
                    t.specialization_id,
                    s.specialization_name,
                    COALESCE(GROUP_CONCAT(DISTINCT ts.specialization SEPARATOR ', '), '') as teacher_specializations,
                    COUNT(ps.project_id) as current_load,
                    (t.max_students - COUNT(ps.project_id)) as available_slots
                FROM teachers t
                INNER JOIN users u ON t.user_id = u.user_id
                LEFT JOIN teacher_specializations ts ON t.id = ts.teacher_id
                LEFT JOIN specializations s ON t.specialization_id = s.specialization_id
                LEFT JOIN project_supervisors ps ON t.user_id = ps.teacher_id AND ps.status = 'active'
                WHERE t.department_id = :department_id
                AND u.status = 'active'
                GROUP BY t.id
                HAVING available_slots > 0
                ORDER BY available_slots DESC, current_load ASC, u.full_name ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':department_id' => $project['department_id']]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error in getAvailableTeachers: " . $e->getMessage());
        return [];
    }
}

/**
 * Helper method for auto assignment
 */
private function getAvailableTeachersForProject($projectId) {
    // Similar to getAvailableTeachers but used internally for auto assignment
    return $this->getAvailableTeachers($projectId);
}
    /**
     * Transfer supervisor
     */
    public function transferSupervisor($projectId, $newTeacherId, $adminId, $reason = '') {
        try {
            $this->db->beginTransaction();
            
            // Get current supervisor
            $currentQuery = "SELECT supervisor_id FROM projects WHERE id = :project_id";
            $currentStmt = $this->db->prepare($currentQuery);
            $currentStmt->execute([':project_id' => $projectId]);
            $currentSupervisor = $currentStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$currentSupervisor) {
                throw new Exception("Project not found");
            }
            
            // Mark old supervisor assignment as transferred
            $updateOldQuery = "UPDATE project_supervisors 
                              SET status = 'transferred', 
                                  comments = CONCAT(IFNULL(comments, ''), ' Transferred to new supervisor. Reason: ', :reason)
                              WHERE project_id = :project_id 
                              AND status = 'active'";
            
            $updateOldStmt = $this->db->prepare($updateOldQuery);
            $updateOldStmt->execute([
                ':project_id' => $projectId,
                ':reason' => $reason
            ]);
            
            // Assign new supervisor
            $result = $this->assignSupervisor($projectId, $newTeacherId, $adminId, 'transfer');
            
            if (!$result['success']) {
                throw new Exception($result['message']);
            }
            
            $this->db->commit();
            return ['success' => true, 'message' => 'Supervisor transferred successfully'];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
?>