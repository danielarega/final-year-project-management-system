-- =========================
-- Document Submission System
-- Phase 5: File uploads, version control, feedback
-- =========================

-- Submission Types (proposal, chapters, final report, etc.)
CREATE TABLE IF NOT EXISTS submission_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type_name VARCHAR(50) NOT NULL,
    description VARCHAR(200),
    allowed_extensions VARCHAR(100),
    max_file_size INT DEFAULT 50, -- in MB
    is_required BOOLEAN DEFAULT TRUE,
    department_id INT, -- NULL for all departments
    batch_id INT, -- NULL for all batches
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE,
    UNIQUE KEY unique_type_dept (type_name, department_id, batch_id)
);

-- Submissions table
CREATE TABLE IF NOT EXISTS submissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    project_id INT NOT NULL,
    submission_type_id INT NOT NULL,
    student_id INT NOT NULL,
    version INT DEFAULT 1,
    file_name VARCHAR(255),
    file_path VARCHAR(500),
    file_size INT, -- in bytes
    file_type VARCHAR(50),
    status ENUM('pending', 'submitted', 'under_review', 'approved', 'rejected', 'resubmit') DEFAULT 'submitted',
    submission_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    submission_notes TEXT,
    
    -- Review fields
    reviewed_by INT,
    review_date TIMESTAMP NULL,
    review_comments TEXT,
    review_status ENUM('not_reviewed', 'reviewed', 'needs_revision', 'accepted') DEFAULT 'not_reviewed',
    review_score DECIMAL(5,2) DEFAULT 0.00,
    
    -- Metadata
    ip_address VARCHAR(45),
    user_agent TEXT,
    
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (submission_type_id) REFERENCES submission_types(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES teachers(id) ON DELETE SET NULL
);

-- Submission Feedback/Comments
CREATE TABLE IF NOT EXISTS submission_feedback (
    id INT PRIMARY KEY AUTO_INCREMENT,
    submission_id INT NOT NULL,
    teacher_id INT NOT NULL,
    feedback_type ENUM('general', 'specific', 'critical', 'suggestion') DEFAULT 'general',
    comment TEXT NOT NULL,
    page_number INT,
    line_number INT,
    is_resolved BOOLEAN DEFAULT FALSE,
    resolved_by INT,
    resolved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (resolved_by) REFERENCES students(id) ON DELETE SET NULL
);

-- Submission Versions (for version control)
CREATE TABLE IF NOT EXISTS submission_versions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    submission_id INT NOT NULL,
    version INT NOT NULL,
    file_name VARCHAR(255),
    file_path VARCHAR(500),
    file_size INT,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    submitted_by INT NOT NULL,
    changes_summary TEXT,
    
    FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE,
    FOREIGN KEY (submitted_by) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY unique_submission_version (submission_id, version)
);

-- Document Templates (for students to download)
CREATE TABLE IF NOT EXISTS document_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    template_name VARCHAR(100) NOT NULL,
    description TEXT,
    file_name VARCHAR(255),
    file_path VARCHAR(500),
    file_size INT,
    template_type ENUM('proposal', 'report', 'thesis', 'other') DEFAULT 'other',
    department_id INT,
    batch_id INT,
    uploaded_by INT,
    download_count INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES teachers(id) ON DELETE CASCADE
);

-- Submission Deadlines (extends batch deadlines)
CREATE TABLE IF NOT EXISTS submission_deadlines (
    id INT PRIMARY KEY AUTO_INCREMENT,
    submission_type_id INT NOT NULL,
    batch_id INT NOT NULL,
    deadline_date DATE NOT NULL,
    late_submission_deadline DATE,
    allow_late_submission BOOLEAN DEFAULT FALSE,
    late_penalty_percentage DECIMAL(5,2) DEFAULT 0.00,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (submission_type_id) REFERENCES submission_types(id) ON DELETE CASCADE,
    FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL,
    UNIQUE KEY unique_type_batch (submission_type_id, batch_id)
);