-- Additional tables for Phase 3: Title & Group Management

-- Notices table
CREATE TABLE IF NOT EXISTS notices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    department_id INT NOT NULL,
    batch_id INT DEFAULT NULL,
    created_by INT NOT NULL,
    user_type ENUM('superadmin', 'admin', 'teacher', 'all') DEFAULT 'all',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE CASCADE
);

-- Notices read status
CREATE TABLE IF NOT EXISTS notice_reads (
    id INT PRIMARY KEY AUTO_INCREMENT,
    notice_id INT NOT NULL,
    user_id INT NOT NULL,
    user_type ENUM('admin', 'teacher', 'student') NOT NULL,
    read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (notice_id) REFERENCES notices(id) ON DELETE CASCADE,
    UNIQUE KEY unique_notice_user (notice_id, user_id, user_type)
);

-- Teacher specialization table
CREATE TABLE IF NOT EXISTS teacher_specializations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_id INT NOT NULL,
    specialization VARCHAR(100) NOT NULL,
    level ENUM('beginner', 'intermediate', 'expert') DEFAULT 'intermediate',
    
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_teacher_specialization (teacher_id, specialization)
);

-- Supervisor preferences (teacher availability)
CREATE TABLE IF NOT EXISTS supervisor_availability (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_id INT NOT NULL,
    batch_id INT NOT NULL,
    max_students INT DEFAULT 5,
    current_load INT DEFAULT 0,
    is_available BOOLEAN DEFAULT TRUE,
    preferences TEXT,
    
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE,
    UNIQUE KEY unique_teacher_batch (teacher_id, batch_id)
);

-- Project supervisor assignments
CREATE TABLE IF NOT EXISTS project_supervisors (
    id INT PRIMARY KEY AUTO_INCREMENT,
    project_id INT NOT NULL,
    teacher_id INT NOT NULL,
    assigned_by INT NOT NULL,
    assignment_type ENUM('auto', 'manual') DEFAULT 'manual',
    assignment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'completed', 'transferred') DEFAULT 'active',
    comments TEXT,
    
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES admins(id) ON DELETE CASCADE,
    UNIQUE KEY unique_project_active (project_id) WHERE status = 'active'
);

-- Add some default data for testing
INSERT INTO teacher_specializations (teacher_id, specialization, level) VALUES
(1, 'Web Development', 'expert'),
(1, 'Database Systems', 'expert'),
(1, 'Software Engineering', 'intermediate');

INSERT INTO supervisor_availability (teacher_id, batch_id, max_students, is_available) VALUES
(1, 1, 5, TRUE);

-- Update existing projects with test supervisor
UPDATE projects SET supervisor_id = 1 WHERE status = 'approved' LIMIT 5;