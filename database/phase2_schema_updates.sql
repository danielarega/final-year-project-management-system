-- C:\xampp\htdocs\fypms\final-year-project-management-system\database\phase2_schema_updates.sql

-- Add missing tables for Phase 2
CREATE TABLE IF NOT EXISTS department_configs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    department_id INT NOT NULL,
    min_group_size INT DEFAULT 1,
    max_group_size INT DEFAULT 3,
    grading_template TEXT,
    submission_requirements TEXT,
    approval_workflow JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS academic_semesters (
    id INT PRIMARY KEY AUTO_INCREMENT,
    batch_id INT NOT NULL,
    semester_number INT DEFAULT 1,
    semester_name VARCHAR(50) NOT NULL,
    start_date DATE,
    end_date DATE,
    title_deadline DATE,
    proposal_deadline DATE,
    documentation_deadline DATE,
    implementation_deadline DATE,
    defense_deadline DATE,
    status ENUM('upcoming', 'active', 'completed') DEFAULT 'upcoming',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS student_import_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    department_id INT NOT NULL,
    batch_id INT,
    filename VARCHAR(255),
    total_records INT DEFAULT 0,
    successful_imports INT DEFAULT 0,
    failed_imports INT DEFAULT 0,
    imported_by INT,
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    error_log TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (batch_id) REFERENCES batches(id),
    FOREIGN KEY (imported_by) REFERENCES admins(id)
);

CREATE TABLE IF NOT EXISTS batch_statistics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    batch_id INT NOT NULL,
    total_students INT DEFAULT 0,
    assigned_students INT DEFAULT 0,
    unassigned_students INT DEFAULT 0,
    active_projects INT DEFAULT 0,
    completed_projects INT DEFAULT 0,
    average_grade DECIMAL(5,2),
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE,
    UNIQUE KEY unique_batch (batch_id)
);

-- Update batches table to include semester support
ALTER TABLE batches 
ADD COLUMN semester_type ENUM('single', 'dual') DEFAULT 'single',
ADD COLUMN current_semester INT DEFAULT 1,
ADD COLUMN academic_year VARCHAR(20) AFTER batch_year;

-- Insert default department configurations
INSERT INTO department_configs (department_id, min_group_size, max_group_size) 
VALUES 
(1, 1, 3), -- CBE: 1-3 students
(2, 3, 5), -- CS: 3-5 students
(3, 1, 3), -- Accounting: 1-3 students
(4, 1, 3); -- Economics: 1-3 students

-- Create indexes for better performance
CREATE INDEX idx_students_batch ON students(batch_id);
CREATE INDEX idx_teachers_department ON teachers(department_id);
CREATE INDEX idx_batches_department ON batches(department_id);
CREATE INDEX idx_academic_semesters_batch ON academic_semesters(batch_id);