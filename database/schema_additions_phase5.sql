-- File: database/schema_additions_phase5.sql
-- Document Submission System Tables

-- =========================
-- Submissions table
-- =========================
CREATE TABLE IF NOT EXISTS submissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    project_id INT NOT NULL,
    student_id INT NOT NULL,
    submission_type ENUM('proposal', 'progress_report', 'final_report', 'source_code', 'other') NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    file_type VARCHAR(100) NOT NULL,
    version INT DEFAULT 1,
    status ENUM('pending', 'under_review', 'approved', 'rejected', 'resubmit') DEFAULT 'pending',
    description TEXT,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deadline_date DATE,
    
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    INDEX idx_project_status (project_id, status),
    INDEX idx_student_type (student_id, submission_type)
);

-- =========================
-- Feedback table
-- =========================
CREATE TABLE IF NOT EXISTS feedback (
    id INT PRIMARY KEY AUTO_INCREMENT,
    submission_id INT NOT NULL,
    teacher_id INT NOT NULL,
    comments TEXT NOT NULL,
    marks DECIMAL(5,2) DEFAULT 0.00,
    grade ENUM('A', 'B', 'C', 'D', 'F', 'Incomplete') DEFAULT NULL,
    status ENUM('draft', 'submitted') DEFAULT 'submitted',
    given_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    department_id INT NOT NULL,
    
    FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    UNIQUE KEY unique_submission_teacher (submission_id, teacher_id)
);

-- =========================
-- Submission History table
-- (Tracks resubmissions)
-- =========================
CREATE TABLE IF NOT EXISTS submission_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    submission_id INT NOT NULL,
    previous_status VARCHAR(50),
    new_status VARCHAR(50),
    changed_by INT NOT NULL,
    change_reason TEXT,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE
);

-- =========================
-- Add submission-related columns to projects table
-- =========================
ALTER TABLE projects 
ADD COLUMN IF NOT EXISTS proposal_submitted BOOLEAN DEFAULT FALSE AFTER approved_at,
ADD COLUMN IF NOT EXISTS proposal_approved BOOLEAN DEFAULT FALSE AFTER proposal_submitted,
ADD COLUMN IF NOT EXISTS final_submitted BOOLEAN DEFAULT FALSE AFTER proposal_approved,
ADD COLUMN IF NOT EXISTS final_approved BOOLEAN DEFAULT FALSE AFTER final_submitted,
ADD COLUMN IF NOT EXISTS last_submission_date TIMESTAMP NULL AFTER final_approved;

-- =========================
-- Update project statuses to include submission phases
-- =========================
ALTER TABLE projects 
MODIFY COLUMN status ENUM(
    'pending',
    'approved',
    'rejected',
    'in_progress',
    'proposal_submitted',
    'proposal_approved',
    'progress_submitted',
    'final_submitted',
    'defense_scheduled',
    'defense_completed',
    'completed'
) DEFAULT 'pending';