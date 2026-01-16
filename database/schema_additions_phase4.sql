-- Additional tables for Phase 4: Supervisor Assignment & Basic Notices

-- Notifications table for user-specific notifications
CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    user_type ENUM('admin', 'teacher', 'student') NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'danger', 'primary') DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    link VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id, user_type, is_read)
);

-- Add email column to admins if not exists
ALTER TABLE admins ADD COLUMN IF NOT EXISTS phone VARCHAR(20) AFTER email;

-- Add email column to teachers if not exists  
ALTER TABLE teachers ADD COLUMN IF NOT EXISTS phone VARCHAR(20) AFTER email;
ALTER TABLE teachers ADD COLUMN IF NOT EXISTS office VARCHAR(100) AFTER phone;
ALTER TABLE teachers ADD COLUMN IF NOT EXISTS qualifications TEXT AFTER office;

-- Add specialization column to teachers for auto-assignment
ALTER TABLE teachers ADD COLUMN IF NOT EXISTS specialization_area VARCHAR(100) AFTER qualifications;

-- Add notification preferences
CREATE TABLE IF NOT EXISTS notification_preferences (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    user_type ENUM('admin', 'teacher', 'student') NOT NULL,
    email_notifications BOOLEAN DEFAULT TRUE,
    sms_notifications BOOLEAN DEFAULT FALSE,
    in_app_notifications BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_pref (user_id, user_type)
);

-- Insert default notification preferences
INSERT INTO notification_preferences (user_id, user_type) 
SELECT id, 'admin' FROM admins 
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

INSERT INTO notification_preferences (user_id, user_type) 
SELECT id, 'teacher' FROM teachers 
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

INSERT INTO notification_preferences (user_id, user_type) 
SELECT id, 'student' FROM students 
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- Add some test notifications
INSERT INTO notifications (user_id, user_type, title, message, type, link) VALUES
(1, 'student', 'Supervisor Assigned', 'Mr. Duressa Deksiso has been assigned as your supervisor for the project "E-Commerce Platform".', 'success', 'my_supervisor.php'),
(1, 'student', 'Project Title Approved', 'Your project title "E-Commerce Platform for Local Businesses" has been approved by the department.', 'success', 'my_project.php'),
(1, 'student', 'New Notice Posted', 'Important deadline notice has been posted by the department head.', 'warning', 'notices.php');

-- Update existing teachers with specialization areas
UPDATE teachers SET specialization_area = 'Software Engineering, Web Development' WHERE id = 1;

-- Add more test teachers
INSERT INTO teachers (username, password, full_name, email, department_id, max_students, specialization_area, created_by) VALUES
('T002', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Alemayehu Bekele', 'alemayehu@arsi.edu.et', 2, 6, 'Database Systems, Data Mining', 1),
('T003', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ms. Tigist Getahun', 'tigist@arsi.edu.et', 2, 5, 'Mobile Development, UI/UX Design', 1),
('T004', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mr. Samuel Kassahun', 'samuel@arsi.edu.et', 2, 4, 'Networking, Cybersecurity', 1);

-- Add teacher specializations
INSERT INTO teacher_specializations (teacher_id, specialization, level) VALUES
(1, 'Web Development', 'expert'),
(1, 'Software Engineering', 'expert'),
(2, 'Database Systems', 'expert'),
(2, 'Data Mining', 'intermediate'),
(3, 'Mobile Development', 'expert'),
(3, 'UI/UX Design', 'intermediate'),
(4, 'Networking', 'expert'),
(4, 'Cybersecurity', 'beginner');

-- Add supervisor availability
INSERT INTO supervisor_availability (teacher_id, batch_id, max_students, is_available) VALUES
(1, 1, 5, TRUE),
(2, 1, 6, TRUE),
(3, 1, 5, TRUE),
(4, 1, 4, TRUE);

-- Add some test notices
INSERT INTO notices (title, content, department_id, batch_id, created_by, user_type, priority) VALUES
('Important: Title Submission Deadline', 'All final year project titles must be submitted by January 25, 2026. Late submissions will not be accepted.', 2, 1, 1, 'student', 'urgent'),
('Supervisor Assignment Schedule', 'Supervisors will be assigned to all approved projects by January 30, 2026.', 2, 1, 1, 'all', 'high'),
('Proposal Writing Workshop', 'A workshop on how to write effective project proposals will be held on January 20, 2026 at 2:00 PM in Room 201.', 2, NULL, 1, 'student', 'medium'),
('System Maintenance', 'The FYPMS will be unavailable on January 18, 2026 from 10:00 PM to 2:00 AM for scheduled maintenance.', 2, NULL, 1, 'all', 'low');