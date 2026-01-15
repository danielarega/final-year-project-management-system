-- Create database
CREATE DATABASE fypms_db;
USE fypms_db;

-- SuperAdmin table (NEW - Manages all departments)
CREATE TABLE super_admins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Departments table
CREATE TABLE departments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    dept_code VARCHAR(20) UNIQUE NOT NULL,
    dept_name VARCHAR(100) NOT NULL,
    dept_type ENUM('technology', 'business', 'economics') DEFAULT 'business',
    created_by INT, -- super_admin_id
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES super_admins(id)
);

-- Admins table (Department Heads)
CREATE TABLE admins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    department_id INT NOT NULL,
    created_by INT, -- super_admin_id
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (created_by) REFERENCES super_admins(id)
);

-- Teachers table
CREATE TABLE teachers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_id VARCHAR(30) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    department_id INT NOT NULL,
    max_students INT DEFAULT 5,
    created_by INT, -- admin_id
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (created_by) REFERENCES admins(id)
);

-- Students table
CREATE TABLE students (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id VARCHAR(30) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    department_id INT NOT NULL,
    batch_id INT,
    created_by INT, -- admin_id
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (created_by) REFERENCES admins(id)
);

-- Batches table
CREATE TABLE batches (
    id INT PRIMARY KEY AUTO_INCREMENT,
    batch_name VARCHAR(50) NOT NULL,
    batch_year YEAR NOT NULL,
    department_id INT NOT NULL,
    title_deadline DATE,
    proposal_deadline DATE,
    final_report_deadline DATE,
    defense_deadline DATE,
    created_by INT, -- admin_id
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (created_by) REFERENCES admins(id)
);

-- Update students table with batch foreign key
ALTER TABLE students ADD FOREIGN KEY (batch_id) REFERENCES batches(id);

-- Insert initial super admin (password: admin123)
INSERT INTO super_admins (username, password, full_name, email) 
VALUES ('superadmin', '$2y$10$YourHashedPasswordHere', 'System Super Admin', 'superadmin@arsi.edu.et');

-- Create default departments
INSERT INTO departments (dept_code, dept_name, dept_type, created_by) 
VALUES 
('CBE', 'College of Business and Economics', 'business', 1),
('CS', 'Computer Science', 'technology', 1),
('ACCN', 'Accounting', 'business', 1),
('ECON', 'Economics', 'economics', 1);