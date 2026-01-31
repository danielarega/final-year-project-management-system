<!-- C:\xampp\htdocs\fypms\final-year-project-management-system\includes\classes\HistoricalDataManager.php -->
<?php
class HistoricalDataManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Add historical project manually
     */
    public function addHistoricalProject($data) {
        try {
            $this->db->beginTransaction();
            
            // Generate project code
            $projectCode = $this->generateProjectCode($data['department_id'], $data['original_year']);
            
            $query = "INSERT INTO historical_projects 
                     (project_code, original_year, department_id, project_title, 
                      student_names, student_ids, supervisor_name, supervisor_id,
                      examiner_name, examiner_id, completion_date, archived_by,
                      data_source, verification_status)
                     VALUES 
                     (:project_code, :year, :dept_id, :title, 
                      :student_names, :student_ids, :supervisor_name, :supervisor_id,
                      :examiner_name, :examiner_id, :completion_date, :archived_by,
                      'manual', 'pending')";
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                ':project_code' => $projectCode,
                ':year' => $data['original_year'],
                ':dept_id' => $data['department_id'],
                ':title' => $data['project_title'],
                ':student_names' => $data['student_names'],
                ':student_ids' => $data['student_ids'],
                ':supervisor_name' => $data['supervisor_name'] ?? null,
                ':supervisor_id' => $data['supervisor_id'] ?? null,
                ':examiner_name' => $data['examiner_name'] ?? null,
                ':examiner_id' => $data['examiner_id'] ?? null,
                ':completion_date' => $data['completion_date'] ?? null,
                ':archived_by' => $data['archived_by']
            ]);
            
            $projectId = $this->db->lastInsertId();
            
            // Add grades if provided
            if (isset($data['grades'])) {
                $this->addHistoricalGrades($projectCode, $data['grades']);
            }
            
            // Add to alumni if students not already there
            $this->addToAlumni($projectCode, $data);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Historical project added successfully',
                'project_code' => $projectCode,
                'project_id' => $projectId
            ];
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Import historical data from CSV
     */
    public function importHistoricalCSV($filePath, $departmentId, $academicYear, $importedBy) {
        try {
            $this->db->beginTransaction();
            
            // Create migration log
            $logQuery = "INSERT INTO data_migration_logs 
                        (migration_type, source_description, performed_by, status)
                        VALUES ('csv_import', :description, :user_id, 'processing')";
            
            $logStmt = $this->db->prepare($logQuery);
            $logStmt->execute([
                ':description' => "CSV import for department $departmentId, year $academicYear",
                ':user_id' => $importedBy
            ]);
            
            $logId = $this->db->lastInsertId();
            
            // Process CSV
            $successCount = 0;
            $failCount = 0;
            $errors = [];
            
            if (($handle = fopen($filePath, "r")) !== FALSE) {
                $row = 1;
                $headers = fgetcsv($handle, 1000, ",");
                
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    $rowData = array_combine($headers, $data);
                    
                    try {
                        // Validate required fields
                        $required = ['project_title', 'student_names', 'student_ids', 'supervisor_name'];
                        foreach ($required as $field) {
                            if (empty($rowData[$field])) {
                                throw new Exception("Missing required field: $field");
                            }
                        }
                        
                        // Add historical project
                        $projectData = [
                            'original_year' => $academicYear,
                            'department_id' => $departmentId,
                            'project_title' => trim($rowData['project_title']),
                            'student_names' => trim($rowData['student_names']),
                            'student_ids' => trim($rowData['student_ids']),
                            'supervisor_name' => trim($rowData['supervisor_name']),
                            'supervisor_id' => $rowData['supervisor_id'] ?? null,
                            'examiner_name' => $rowData['examiner_name'] ?? null,
                            'examiner_id' => $rowData['examiner_id'] ?? null,
                            'completion_date' => $rowData['completion_date'] ?? null,
                            'archived_by' => $importedBy
                        ];
                        
                        // Add optional grades
                        $grades = [];
                        $gradeFields = [
                            'title_grade', 'proposal_grade', 'documentation_grade',
                            'presentation_grade', 'advisor_evaluation',
                            'implementation_grade', 'final_presentation_grade', 'viva_voce_grade'
                        ];
                        
                        foreach ($gradeFields as $field) {
                            if (isset($rowData[$field]) && is_numeric($rowData[$field])) {
                                $grades[$field] = $rowData[$field];
                            }
                        }
                        
                        if (!empty($grades)) {
                            $projectData['grades'] = $grades;
                        }
                        
                        $result = $this->addHistoricalProject($projectData);
                        
                        if ($result['success']) {
                            $successCount++;
                        } else {
                            throw new Exception($result['message']);
                        }
                        
                    } catch (Exception $e) {
                        $failCount++;
                        $errors[] = "Row $row: " . $e->getMessage();
                    }
                    
                    $row++;
                }
                fclose($handle);
            }
            
            // Update migration log
            $updateQuery = "UPDATE data_migration_logs 
                           SET total_records = :total,
                               successful_records = :success,
                               failed_records = :failed,
                               error_log = :errors,
                               end_time = NOW(),
                               duration_seconds = TIMESTAMPDIFF(SECOND, start_time, NOW()),
                               status = :status
                           WHERE id = :log_id";
            
            $updateStmt = $this->db->prepare($updateQuery);
            $updateStmt->execute([
                ':total' => $successCount + $failCount,
                ':success' => $successCount,
                ':failed' => $failCount,
                ':errors' => implode('\n', $errors),
                ':status' => $failCount == 0 ? 'completed' : 'completed_with_errors',
                ':log_id' => $logId
            ]);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => "CSV import completed: $successCount successful, $failCount failed",
                'log_id' => $logId,
                'stats' => ['success' => $successCount, 'failed' => $failCount]
            ];
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => 'Import error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Advanced search for historical projects
     */
    public function searchHistoricalProjects($searchParams) {
        try {
            $query = "SELECT hp.*, d.dept_name, d.dept_code 
                     FROM historical_projects hp
                     LEFT JOIN departments d ON hp.department_id = d.id
                     WHERE 1=1";
            
            $params = [];
            
            // Year range
            if (isset($searchParams['year_from'])) {
                $query .= " AND hp.original_year >= :year_from";
                $params[':year_from'] = $searchParams['year_from'];
            }
            if (isset($searchParams['year_to'])) {
                $query .= " AND hp.original_year <= :year_to";
                $params[':year_to'] = $searchParams['year_to'];
            }
            
            // Department
            if (isset($searchParams['department_id'])) {
                $query .= " AND hp.department_id = :dept_id";
                $params[':dept_id'] = $searchParams['department_id'];
            }
            
            // Status
            if (isset($searchParams['status'])) {
                $query .= " AND hp.final_status = :status";
                $params[':status'] = $searchParams['status'];
            }
            
            // Text search
            if (isset($searchParams['search_text']) && !empty($searchParams['search_text'])) {
                $searchText = $searchParams['search_text'];
                $query .= " AND (hp.project_title LIKE :search_text 
                               OR hp.student_names LIKE :search_text 
                               OR hp.student_ids LIKE :search_text
                               OR hp.supervisor_name LIKE :search_text)";
                $params[':search_text'] = "%$searchText%";
            }
            
            // Grade range
            if (isset($searchParams['min_grade'])) {
                $query .= " AND hp.final_grade >= :min_grade";
                $params[':min_grade'] = $searchParams['min_grade'];
            }
            if (isset($searchParams['max_grade'])) {
                $query .= " AND hp.final_grade <= :max_grade";
                $params[':max_grade'] = $searchParams['max_grade'];
            }
            
            // Order by
            $orderBy = $searchParams['order_by'] ?? 'original_year';
            $orderDir = $searchParams['order_dir'] ?? 'DESC';
            $query .= " ORDER BY hp.$orderBy $orderDir";
            
            // Pagination
            $limit = $searchParams['limit'] ?? 50;
            $offset = $searchParams['offset'] ?? 0;
            $query .= " LIMIT :limit OFFSET :offset";
            
            $stmt = $this->db->prepare($query);
            
            // Bind parameters
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            // Bind limit and offset as integers
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            
            $stmt->execute();
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get total count for pagination
            $countQuery = "SELECT COUNT(*) as total FROM historical_projects hp WHERE 1=1";
            $countStmt = $this->db->prepare($countQuery);
            $countStmt->execute($params);
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            return [
                'success' => true,
                'data' => $results,
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset
            ];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Search error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get historical statistics
     */
    public function getHistoricalStatistics($departmentId = null) {
        try {
            $stats = [];
            
            // Overall statistics
            $query = "SELECT 
                     COUNT(*) as total_projects,
                     AVG(final_grade) as avg_grade,
                     MIN(original_year) as earliest_year,
                     MAX(original_year) as latest_year,
                     SUM(CASE WHEN final_status = 'completed' THEN 1 ELSE 0 END) as completed_projects,
                     SUM(CASE WHEN final_status = 'failed' THEN 1 ELSE 0 END) as failed_projects
                     FROM historical_projects";
            
            $params = [];
            if ($departmentId) {
                $query .= " WHERE department_id = :dept_id";
                $params[':dept_id'] = $departmentId;
            }
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            $stats['overall'] = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Year-wise statistics
            $yearQuery = "SELECT original_year as year, 
                         COUNT(*) as count, 
                         AVG(final_grade) as avg_grade,
                         AVG(semester1_total) as avg_semester1,
                         AVG(semester2_total) as avg_semester2
                         FROM historical_projects";
            
            if ($departmentId) {
                $yearQuery .= " WHERE department_id = :dept_id";
            }
            
            $yearQuery .= " GROUP BY original_year ORDER BY original_year DESC";
            
            $yearStmt = $this->db->prepare($yearQuery);
            $yearStmt->execute($params);
            $stats['by_year'] = $yearStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Department-wise statistics (for super admin)
            if (!$departmentId) {
                $deptQuery = "SELECT d.id, d.dept_code, d.dept_name,
                             COUNT(hp.id) as project_count,
                             AVG(hp.final_grade) as avg_grade,
                             MIN(hp.original_year) as earliest_year,
                             MAX(hp.original_year) as latest_year
                             FROM departments d
                             LEFT JOIN historical_projects hp ON d.id = hp.department_id
                             GROUP BY d.id
                             ORDER BY d.dept_name";
                
                $deptStmt = $this->db->query($deptQuery);
                $stats['by_department'] = $deptStmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            // Grade distribution
            $gradeQuery = "SELECT 
                          CASE 
                            WHEN final_grade >= 90 THEN 'A+ (90-100)'
                            WHEN final_grade >= 85 THEN 'A (85-89)'
                            WHEN final_grade >= 80 THEN 'A- (80-84)'
                            WHEN final_grade >= 75 THEN 'B+ (75-79)'
                            WHEN final_grade >= 70 THEN 'B (70-74)'
                            WHEN final_grade >= 65 THEN 'C+ (65-69)'
                            WHEN final_grade >= 60 THEN 'C (60-64)'
                            WHEN final_grade >= 50 THEN 'D (50-59)'
                            ELSE 'F (Below 50)'
                          END as grade_range,
                          COUNT(*) as count
                          FROM historical_projects";
            
            if ($departmentId) {
                $gradeQuery .= " WHERE department_id = :dept_id";
            }
            
            $gradeQuery .= " GROUP BY grade_range ORDER BY MIN(final_grade) DESC";
            
            $gradeStmt = $this->db->prepare($gradeQuery);
            $gradeStmt->execute($params);
            $stats['grade_distribution'] = $gradeStmt->fetchAll(PDO::FETCH_ASSOC);
            
            return ['success' => true, 'data' => $stats];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Statistics error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Check for similar projects (duplication prevention)
     */
    public function findSimilarProjects($projectTitle, $departmentId, $year = null, $threshold = 70) {
        try {
            // Use MySQL FULLTEXT search for similarity
            $query = "SELECT hp.*, d.dept_name,
                     MATCH(hp.project_title) AGAINST(:title) as relevance_score,
                     (LENGTH(:title) - LENGTH(REPLACE(LOWER(:title), LOWER(hp.project_title), ''))) / 
                     LENGTH(:title) * 100 as similarity_percent
                     FROM historical_projects hp
                     LEFT JOIN departments d ON hp.department_id = d.id
                     WHERE hp.department_id = :dept_id
                     AND (MATCH(hp.project_title) AGAINST(:title) > 0
                          OR LOWER(hp.project_title) LIKE LOWER(CONCAT('%', :title_like, '%')))";
            
            $params = [
                ':title' => $projectTitle,
                ':title_like' => $projectTitle,
                ':dept_id' => $departmentId
            ];
            
            if ($year) {
                $query .= " AND hp.original_year = :year";
                $params[':year'] = $year;
            }
            
            $query .= " HAVING similarity_percent >= :threshold 
                       OR relevance_score > 0
                       ORDER BY similarity_percent DESC, relevance_score DESC
                       LIMIT 10";
            
            $stmt = $this->db->prepare($query);
            $params[':threshold'] = $threshold;
            $stmt->execute($params);
            
            $similarProjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'similar_projects' => $similarProjects,
                'count' => count($similarProjects)
            ];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Similarity check error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Track project progress (for active projects with historical comparison)
     */
    public function calculateProgressMetrics($projectId, $currentData) {
        try {
            // Get historical averages for comparison
            $avgQuery = "SELECT 
                        AVG(semester1_total) as avg_semester1,
                        AVG(semester2_total) as avg_semester2,
                        AVG(final_grade) as avg_final,
                        STDDEV(final_grade) as grade_stddev
                        FROM historical_projects
                        WHERE department_id = :dept_id
                        AND original_year >= YEAR(CURDATE()) - 5";
            
            $avgStmt = $this->db->prepare($avgQuery);
            $avgStmt->execute([':dept_id' => $currentData['department_id']]);
            $historicalAvg = $avgStmt->fetch(PDO::FETCH_ASSOC);
            
            // Calculate current progress
            $currentProgress = $this->calculateCurrentProgress($currentData);
            
            // Compare with historical data
            $comparison = [
                'current' => $currentProgress,
                'historical_average' => $historicalAvg,
                'percentile' => $this->calculatePercentile($currentProgress['final_grade'] ?? 0, 
                                                          $historicalAvg['avg_final'] ?? 0, 
                                                          $historicalAvg['grade_stddev'] ?? 1),
                'risk_level' => $this->assessRiskLevel($currentProgress, $historicalAvg)
            ];
            
            return ['success' => true, 'comparison' => $comparison];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Progress calculation error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Generate project code
     */
    private function generateProjectCode($departmentId, $year) {
        try {
            // Get department code
            $deptQuery = "SELECT dept_code FROM departments WHERE id = :dept_id";
            $deptStmt = $this->db->prepare($deptQuery);
            $deptStmt->execute([':dept_id' => $departmentId]);
            $deptCode = $deptStmt->fetch(PDO::FETCH_ASSOC)['dept_code'];
            
            // Get count for this year and department
            $countQuery = "SELECT COUNT(*) as count FROM historical_projects 
                          WHERE department_id = :dept_id AND original_year = :year";
            $countStmt = $this->db->prepare($countQuery);
            $countStmt->execute([':dept_id' => $departmentId, ':year' => $year]);
            $count = $countStmt->fetch(PDO::FETCH_ASSOC)['count'] + 1;
            
            return strtoupper($deptCode) . '-' . $year . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
            
        } catch (PDOException $e) {
            // Fallback code
            return 'PROJ-' . $year . '-' . uniqid();
        }
    }
    
    /**
     * Add grades for historical project
     */
    private function addHistoricalGrades($projectCode, $grades) {
        try {
            $gradeWeights = [
                'title_grade' => ['type' => 'title', 'weight' => 10],
                'proposal_grade' => ['type' => 'proposal', 'weight' => 15],
                'documentation_grade' => ['type' => 'documentation', 'weight' => 25],
                'presentation_grade' => ['type' => 'presentation', 'weight' => 35],
                'advisor_evaluation' => ['type' => 'advisor_eval', 'weight' => 15],
                'implementation_grade' => ['type' => 'implementation', 'weight' => 50],
                'final_presentation_grade' => ['type' => 'final_presentation', 'weight' => 30],
                'viva_voce_grade' => ['type' => 'viva_voce', 'weight' => 20]
            ];
            
            foreach ($grades as $field => $score) {
                if (isset($gradeWeights[$field])) {
                    $weightInfo = $gradeWeights[$field];
                    
                    $query = "INSERT INTO historical_grades 
                             (project_code, component_type, component_name, 
                              max_score, score_obtained, weight_percentage, weighted_score)
                             VALUES 
                             (:project_code, :type, :name, 100, :score, :weight, 
                              (:score * :weight / 100))";
                    
                    $stmt = $this->db->prepare($query);
                    $stmt->execute([
                        ':project_code' => $projectCode,
                        ':type' => $weightInfo['type'],
                        ':name' => ucfirst(str_replace('_', ' ', $field)),
                        ':score' => $score,
                        ':weight' => $weightInfo['weight']
                    ]);
                }
            }
            
            return true;
            
        } catch (PDOException $e) {
            throw new Exception("Grade insertion failed: " . $e->getMessage());
        }
    }
    
    /**
     * Add students to alumni
     */
    private function addToAlumni($projectCode, $projectData) {
        try {
            // Parse student IDs and names
            $studentIds = explode(',', $projectData['student_ids']);
            $studentNames = explode(',', $projectData['student_names']);
            
            for ($i = 0; $i < count($studentIds); $i++) {
                $studentId = trim($studentIds[$i]);
                $studentName = trim($studentNames[$i] ?? '');
                
                if (empty($studentId) || empty($studentName)) continue;
                
                // Check if alumni already exists
                $checkQuery = "SELECT id FROM historical_alumni WHERE student_id = :student_id";
                $checkStmt = $this->db->prepare($checkQuery);
                $checkStmt->execute([':student_id' => $studentId]);
                
                if (!$checkStmt->fetch()) {
                    // Add new alumni record
                    $insertQuery = "INSERT INTO historical_alumni 
                                   (student_id, full_name, department_id, graduation_year, 
                                    project_title, project_code, final_grade)
                                   VALUES 
                                   (:student_id, :full_name, :dept_id, :grad_year, 
                                    :project_title, :project_code, :final_grade)";
                    
                    $insertStmt = $this->db->prepare($insertQuery);
                    $insertStmt->execute([
                        ':student_id' => $studentId,
                        ':full_name' => $studentName,
                        ':dept_id' => $projectData['department_id'],
                        ':grad_year' => $projectData['original_year'],
                        ':project_title' => $projectData['project_title'],
                        ':project_code' => $projectCode,
                        ':final_grade' => $projectData['final_grade'] ?? null
                    ]);
                }
            }
            
            return true;
            
        } catch (PDOException $e) {
            // Log but don't fail the whole operation
            error_log("Alumni insertion failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Calculate current progress
     */
    private function calculateCurrentProgress($data) {
        // This would integrate with the active project tracking system
        // Placeholder implementation
        return [
            'semester1_progress' => 0,
            'semester2_progress' => 0,
            'overall_progress' => 0,
            'expected_completion_date' => null,
            'risk_factors' => []
        ];
    }
    
    /**
     * Calculate percentile
     */
    private function calculatePercentile($currentGrade, $historicalAvg, $stdDev) {
        if ($stdDev == 0) return 50;
        $zScore = ($currentGrade - $historicalAvg) / $stdDev;
        return round(50 * (1 + erf($zScore / sqrt(2))), 2);
    }
    
    /**
     * Assess risk level
     */
    private function assessRiskLevel($currentProgress, $historicalAvg) {
        // Simple risk assessment based on comparison with historical averages
        $riskScore = 0;
        
        if (($currentProgress['semester1_progress'] ?? 0) < ($historicalAvg['avg_semester1'] ?? 0) * 0.8) {
            $riskScore += 2;
        }
        
        if (($currentProgress['overall_progress'] ?? 0) < 30) {
            $riskScore += 1;
        }
        
        if ($riskScore >= 3) return 'high';
        if ($riskScore >= 2) return 'medium';
        return 'low';
    }
}

// Helper function for percentile calculation
function erf($x) {
    $pi = 3.141592653589793238;
    $a = (8*($pi - 3))/(3*$pi*(4 - $pi));
    $x2 = $x * $x;
    $ax2 = $a * $x2;
    $num = (4/$pi) + $ax2;
    $denom = 1 + $ax2;
    $inner = (-$x2)*$num/$denom;
    $erf2 = 1 - exp($inner);
    return sqrt($erf2);
}
?>