-- C:\xampp\htdocs\fypms\final-year-project-management-system\database\historical_schema.sql

-- ============================================
-- HISTORICAL ARCHIVE TABLES
-- ============================================

-- 1. Historical Projects Archive (2010-2025)
CREATE TABLE IF NOT EXISTS historical_projects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    project_code VARCHAR(50) UNIQUE NOT NULL,
    original_year YEAR NOT NULL,
    department_id INT NOT NULL,
    project_title VARCHAR(500) NOT NULL,
    project_description TEXT,
    student_names TEXT NOT NULL,
    student_ids TEXT NOT NULL,
    supervisor_name VARCHAR(100),
    supervisor_id INT,
    examiner_name VARCHAR(100),
    examiner_id INT,
    
    -- Semester 1: Documentation Phase (50%)
    title_grade DECIMAL(5,2),
    proposal_grade DECIMAL(5,2),
    documentation_grade DECIMAL(5,2),
    presentation_grade DECIMAL(5,2),
    advisor_evaluation DECIMAL(5,2),
    semester1_total DECIMAL(5,2),
    semester1_status ENUM('passed', 'failed', 'incomplete') DEFAULT 'incomplete',
    
    -- Semester 2: Implementation Phase (50%)
    implementation_grade DECIMAL(5,2),
    final_presentation_grade DECIMAL(5,2),
    viva_voce_grade DECIMAL(5,2),
    semester2_total DECIMAL(5,2),
    semester2_status ENUM('passed', 'failed', 'incomplete') DEFAULT 'incomplete',
    
    -- Overall
    final_grade DECIMAL(5,2),
    final_status ENUM('completed', 'failed', 'withdrawn', 'deferred') DEFAULT 'completed',
    completion_date DATE,
    
    -- Document Storage References
    proposal_doc_path VARCHAR(500),
    final_report_path VARCHAR(500),
    source_code_path VARCHAR(500),
    presentation_path VARCHAR(500),
    
    -- Metadata
    archived_by INT,
    archive_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    data_source ENUM('manual', 'csv_import', 'system_migration', 'ocr_scanned') DEFAULT 'manual',
    verification_status ENUM('pending', 'verified', 'disputed', 'corrected') DEFAULT 'pending',
    
    -- Indexes
    INDEX idx_historical_year (original_year),
    INDEX idx_historical_department (department_id),
    INDEX idx_historical_supervisor (supervisor_id),
    FULLTEXT idx_historical_title (project_title),
    FULLTEXT idx_historical_students (student_names),
    
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    FOREIGN KEY (supervisor_id) REFERENCES teachers(id) ON DELETE SET NULL,
    FOREIGN KEY (examiner_id) REFERENCES teachers(id) ON DELETE SET NULL,
    FOREIGN KEY (archived_by) REFERENCES admins(id) ON DELETE SET NULL
);

-- 2. Historical Alumni Records
CREATE TABLE IF NOT EXISTS historical_alumni (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id VARCHAR(50) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    gender ENUM('male', 'female'),
    department_id INT NOT NULL,
    graduation_year YEAR NOT NULL,
    project_title VARCHAR(500),
    project_code VARCHAR(50),
    final_grade DECIMAL(5,2),
    current_employment VARCHAR(200),
    current_position VARCHAR(100),
    contact_info JSON,
    
    -- Tracking
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT,
    
    INDEX idx_alumni_year (graduation_year),
    INDEX idx_alumni_department (department_id),
    FULLTEXT idx_alumni_name (full_name),
    
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES admins(id) ON DELETE SET NULL
);

-- 3. Historical Grades Archive
CREATE TABLE IF NOT EXISTS historical_grades (
    id INT PRIMARY KEY AUTO_INCREMENT,
    project_code VARCHAR(50) NOT NULL,
    component_type ENUM('title', 'proposal', 'documentation', 'presentation', 
                       'advisor_eval', 'implementation', 'final_presentation', 'viva_voce'),
    component_name VARCHAR(100),
    max_score INT DEFAULT 100,
    score_obtained DECIMAL(5,2),
    weight_percentage DECIMAL(5,2),
    weighted_score DECIMAL(5,2),
    graded_by INT,
    grading_date DATE,
    comments TEXT,
    
    INDEX idx_grades_project (project_code),
    INDEX idx_grades_type (component_type),
    FOREIGN KEY (graded_by) REFERENCES teachers(id) ON DELETE SET NULL
);

-- 4. Document Archive (for scanned/OCR documents)
CREATE TABLE IF NOT EXISTS historical_documents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    project_code VARCHAR(50) NOT NULL,
    document_type ENUM('proposal', 'final_report', 'presentation', 'source_code', 
                      'certificate', 'other'),
    document_name VARCHAR(200) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size BIGINT,
    file_format VARCHAR(20),
    ocr_text LONGTEXT,
    ocr_confidence DECIMAL(5,2),
    scanned_date DATE,
    uploaded_by INT,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_docs_project (project_code),
    INDEX idx_docs_type (document_type),
    FULLTEXT idx_docs_ocr (ocr_text),
    FOREIGN KEY (uploaded_by) REFERENCES admins(id) ON DELETE SET NULL
);

-- 5. Data Migration Logs
CREATE TABLE IF NOT EXISTS data_migration_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    migration_type ENUM('manual_entry', 'csv_import', 'system_migration', 'ocr_scan'),
    source_description VARCHAR(500),
    total_records INT DEFAULT 0,
    successful_records INT DEFAULT 0,
    failed_records INT DEFAULT 0,
    start_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    end_time TIMESTAMP NULL,
    duration_seconds INT,
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    error_log TEXT,
    performed_by INT,
    
    INDEX idx_migration_type (migration_type),
    INDEX idx_migration_status (status),
    FOREIGN KEY (performed_by) REFERENCES super_admins(id) ON DELETE SET NULL
);

-- 6. Historical Project Categories/Tags
CREATE TABLE IF NOT EXISTS historical_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    parent_category_id INT,
    created_by INT,
    
    INDEX idx_category_parent (parent_category_id),
    FOREIGN KEY (parent_category_id) REFERENCES historical_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES super_admins(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS historical_project_categories (
    project_code VARCHAR(50) NOT NULL,
    category_id INT NOT NULL,
    assigned_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_by INT,
    
    PRIMARY KEY (project_code, category_id),
    FOREIGN KEY (project_code) REFERENCES historical_projects(project_code) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES historical_categories(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES admins(id) ON DELETE SET NULL
);

-- 7. Citation Tracking System
CREATE TABLE IF NOT EXISTS historical_citations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    citing_project_code VARCHAR(50) NOT NULL,
    cited_project_code VARCHAR(50) NOT NULL,
    citation_context TEXT,
    citation_date DATE,
    citation_type ENUM('reference', 'similar_work', 'extension', 'comparison'),
    
    INDEX idx_citing_project (citing_project_code),
    INDEX idx_cited_project (cited_project_code),
    FOREIGN KEY (citing_project_code) REFERENCES historical_projects(project_code) ON DELETE CASCADE,
    FOREIGN KEY (cited_project_code) REFERENCES historical_projects(project_code) ON DELETE CASCADE
);

-- ============================================
-- TRIGGERS FOR AUTOMATIC CALCULATIONS
-- ============================================

-- Trigger to calculate semester totals
DELIMITER $$
CREATE TRIGGER calculate_semester1_total
BEFORE UPDATE ON historical_projects
FOR EACH ROW
BEGIN
    -- Calculate Semester 1 Total (50%)
    SET NEW.semester1_total = (
        COALESCE(NEW.title_grade, 0) * 0.1 +          -- 10%
        COALESCE(NEW.proposal_grade, 0) * 0.15 +      -- 15%
        COALESCE(NEW.documentation_grade, 0) * 0.25 + -- 25%
        COALESCE(NEW.presentation_grade, 0) * 0.35 +  -- 35%
        COALESCE(NEW.advisor_evaluation, 0) * 0.15    -- 15%
    );
    
    -- Determine Semester 1 Status
    IF NEW.semester1_total >= 50 THEN
        SET NEW.semester1_status = 'passed';
    ELSEIF NEW.semester1_total IS NULL THEN
        SET NEW.semester1_status = 'incomplete';
    ELSE
        SET NEW.semester1_status = 'failed';
    END IF;
    
    -- Calculate Semester 2 Total (50%)
    SET NEW.semester2_total = (
        COALESCE(NEW.implementation_grade, 0) * 0.5 +        -- 50%
        COALESCE(NEW.final_presentation_grade, 0) * 0.3 +    -- 30%
        COALESCE(NEW.viva_voce_grade, 0) * 0.2              -- 20%
    );
    
    -- Determine Semester 2 Status
    IF NEW.semester2_total >= 50 THEN
        SET NEW.semester2_status = 'passed';
    ELSEIF NEW.semester2_total IS NULL THEN
        SET NEW.semester2_status = 'incomplete';
    ELSE
        SET NEW.semester2_status = 'failed';
    END IF;
    
    -- Calculate Final Grade
    SET NEW.final_grade = (
        COALESCE(NEW.semester1_total, 0) * 0.5 + 
        COALESCE(NEW.semester2_total, 0) * 0.5
    );
    
    -- Determine Final Status
    IF NEW.final_grade >= 50 THEN
        SET NEW.final_status = 'completed';
    ELSE
        SET NEW.final_status = 'failed';
    END IF;
END$$
DELIMITER ;

-- ============================================
-- STORED PROCEDURES FOR DATA MIGRATION
-- ============================================

DELIMITER $$
CREATE PROCEDURE MigrateHistoricalData(
    IN p_start_year INT,
    IN p_end_year INT,
    IN p_department_id INT,
    IN p_migrated_by INT
)
BEGIN
    DECLARE migration_id INT;
    DECLARE record_count INT DEFAULT 0;
    DECLARE success_count INT DEFAULT 0;
    DECLARE fail_count INT DEFAULT 0;
    DECLARE start_time TIMESTAMP;
    
    SET start_time = NOW();
    
    -- Create migration log entry
    INSERT INTO data_migration_logs 
    (migration_type, source_description, performed_by, status)
    VALUES (
        'system_migration',
        CONCAT('Historical data migration for years ', p_start_year, '-', p_end_year, 
               ', Department ID: ', p_department_id),
        p_migrated_by,
        'processing'
    );
    
    SET migration_id = LAST_INSERT_ID();
    
    -- Here you would add actual migration logic
    -- This is a placeholder for the migration process
    
    -- Update migration log
    UPDATE data_migration_logs 
    SET total_records = record_count,
        successful_records = success_count,
        failed_records = fail_count,
        end_time = NOW(),
        duration_seconds = TIMESTAMPDIFF(SECOND, start_time, NOW()),
        status = IF(fail_count = 0, 'completed', 'completed_with_errors')
    WHERE id = migration_id;
    
    SELECT migration_id, record_count, success_count, fail_count;
END$$
DELIMITER ;

-- ============================================
-- VIEWS FOR REPORTING
-- ============================================

-- View for Department-wise Historical Summary
CREATE VIEW v_department_historical_summary AS
SELECT 
    d.id as department_id,
    d.dept_code,
    d.dept_name,
    COUNT(DISTINCT hp.original_year) as years_covered,
    COUNT(hp.id) as total_projects,
    AVG(hp.final_grade) as avg_grade,
    SUM(CASE WHEN hp.final_status = 'completed' THEN 1 ELSE 0 END) as completed_projects,
    SUM(CASE WHEN hp.final_status = 'failed' THEN 1 ELSE 0 END) as failed_projects,
    MIN(hp.original_year) as earliest_year,
    MAX(hp.original_year) as latest_year
FROM departments d
LEFT JOIN historical_projects hp ON d.id = hp.department_id
GROUP BY d.id;

-- View for Year-wise Statistics
CREATE VIEW v_yearly_historical_stats AS
SELECT 
    original_year as year,
    COUNT(*) as total_projects,
    AVG(final_grade) as avg_grade,
    AVG(semester1_total) as avg_semester1,
    AVG(semester2_total) as avg_semester2,
    SUM(CASE WHEN final_status = 'completed' THEN 1 ELSE 0 END) as passed_count,
    SUM(CASE WHEN final_status = 'failed' THEN 1 ELSE 0 END) as failed_count
FROM historical_projects
GROUP BY original_year
ORDER BY original_year DESC;

-- View for Supervisor Performance History
CREATE VIEW v_supervisor_performance_history AS
SELECT 
    t.id as teacher_id,
    t.full_name,
    t.department_id,
    COUNT(hp.id) as total_projects_supervised,
    AVG(hp.final_grade) as avg_project_grade,
    AVG(hp.advisor_evaluation) as avg_advisor_score,
    MIN(hp.original_year) as first_supervision_year,
    MAX(hp.original_year) as latest_supervision_year
FROM teachers t
LEFT JOIN historical_projects hp ON t.id = hp.supervisor_id
GROUP BY t.id;

-- ============================================
-- SAMPLE DATA FOR TESTING
-- ============================================

-- Insert sample historical categories
INSERT INTO historical_categories (category_name, description) VALUES
('Web Development', 'Projects involving website and web application development'),
('Mobile Applications', 'Android and iOS mobile applications'),
('Database Systems', 'Database design and management systems'),
('AI & Machine Learning', 'Artificial Intelligence and ML projects'),
('Networking & Security', 'Network systems and security projects'),
('Business Applications', 'Business process automation and management systems'),
('Embedded Systems', 'Hardware and embedded systems projects'),
('Data Analytics', 'Data analysis and visualization projects');

-- Insert sample historical projects (2015-2020)
INSERT INTO historical_projects 
(project_code, original_year, department_id, project_title, student_names, student_ids, 
 supervisor_name, final_grade, final_status, completion_date) VALUES
('CS-2015-001', 2015, 2, 'Online Shopping System for Local Businesses', 
 'Daniel Arega, Nafyad Tesfaye', 'UGR13610, UGR13611', 'Mr. Duressa Deksiso', 85.5, 'completed', '2015-06-15'),
('CS-2016-002', 2016, 2, 'Student Management System with SMS Integration', 
 'Warkineh Lemma, Robsan Hailmikael', 'UGR13612, UGR13613', 'Mr. Duressa Deksiso', 78.0, 'completed', '2016-06-20'),
('CBE-2017-001', 2017, 1, 'Market Analysis for Arsi Coffee Export', 
 'Mekdes Abebe, Selamawit Getachew', 'UGR13614, UGR13615', 'Dr. Alemayehu Bekele', 82.5, 'completed', '2017-06-18'),
('CS-2018-003', 2018, 2, 'IoT Based Smart Agriculture System', 
 'Esrael Belete, Yohannes Tadesse', 'UGR13616, UGR13617', 'Mrs. Helen Mekonnen', 90.0, 'completed', '2018-06-22'),
('ACCN-2019-001', 2019, 3, 'Automated Accounting System for SMEs', 
 'Birtukan Mohammed, Solomon Girma', 'UGR13618, UGR13619', 'Dr. Tewodros Abebe', 76.5, 'completed', '2019-06-19'),
('CS-2020-004', 2020, 2, 'E-Learning Platform with Video Conferencing', 
 'Hana Mohammed, Samuel Assefa', 'UGR13620, UGR13621', 'Mr. Duressa Deksiso', 88.0, 'completed', '2020-06-25');

-- Categorize the sample projects
INSERT INTO historical_project_categories (project_code, category_id, assigned_by) VALUES
('CS-2015-001', 1, 1), -- Web Development
('CS-2015-001', 6, 1), -- Business Applications
('CS-2016-002', 1, 1), -- Web Development
('CS-2016-002', 6, 1), -- Business Applications
('CS-2018-003', 8, 1), -- Data Analytics
('CS-2020-004', 1, 1); -- Web Development

-- Insert sample alumni records
INSERT INTO historical_alumni 
(student_id, full_name, email, department_id, graduation_year, project_title, final_grade, current_employment) VALUES
('UGR13610', 'Daniel Arega', 'daniel@example.com', 2, 2015, 'Online Shopping System for Local Businesses', 85.5, 'Software Engineer at Google'),
('UGR13611', 'Nafyad Tesfaye', 'nafyad@example.com', 2, 2015, 'Online Shopping System for Local Businesses', 85.5, 'Full Stack Developer at Microsoft'),
('UGR13612', 'Warkineh Lemma', 'warkineh@example.com', 2, 2016, 'Student Management System with SMS Integration', 78.0, 'System Analyst at Ethiopian Airlines'),
('UGR13614', 'Mekdes Abebe', 'mekdes@example.com', 1, 2017, 'Market Analysis for Arsi Coffee Export', 82.5, 'Business Analyst at Dashen Bank');