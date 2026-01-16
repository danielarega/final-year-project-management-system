-- File: database/schema_additions.sql
-- Aligned with database/schema.sql

-- =========================
-- Groups table
-- (For technology departments)
-- =========================
CREATE TABLE IF NOT EXISTS groups (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_name VARCHAR(100) NOT NULL,
    group_code VARCHAR(20) UNIQUE NOT NULL,
    department_id INT NOT NULL,
    batch_id INT NOT NULL,
    max_members INT DEFAULT 5,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES students(id) ON DELETE CASCADE
);

-- =========================
-- Group Members table
-- =========================
CREATE TABLE IF NOT EXISTS group_members (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_id INT NOT NULL,
    student_id INT NOT NULL,
    role ENUM('leader', 'member') DEFAULT 'member',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY unique_group_student (group_id, student_id)
);

-- =========================
-- Projects / Theses table
-- =========================
CREATE TABLE IF NOT EXISTS projects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(300) NOT NULL,
    description TEXT,
    student_id INT NOT NULL,
    group_id INT DEFAULT NULL,
    department_id INT NOT NULL,
    batch_id INT NOT NULL,
    supervisor_id INT DEFAULT NULL,
    status ENUM(
        'pending',
        'approved',
        'rejected',
        'in_progress',
        'completed'
    ) DEFAULT 'pending',
    admin_comments TEXT,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    approved_at TIMESTAMP NULL,

    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE SET NULL,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE,
    FOREIGN KEY (supervisor_id) REFERENCES teachers(id) ON DELETE SET NULL
);

-- =========================
-- Title History table
-- (Tracks title changes)
-- =========================
CREATE TABLE IF NOT EXISTS title_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    project_id INT NOT NULL,
    old_title VARCHAR(300),
    new_title VARCHAR(300) NOT NULL,
    changed_by INT NOT NULL,
    change_reason TEXT,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES students(id) ON DELETE CASCADE
);
