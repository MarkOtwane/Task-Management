-- ============================================
-- TaskFlow Multi-User Database Schema
-- MySQL Database for Organization Mode
-- ============================================

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'employee') DEFAULT 'employee',
    department VARCHAR(100),
    phone VARCHAR(20),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tasks Table
CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description LONGTEXT,
    assigned_to INT NOT NULL,
    created_by INT NOT NULL,
    deadline DATE NOT NULL,
    due_time TIME,
    reminder_type ENUM('none', '1day', '30min', 'custom') DEFAULT 'none',
    custom_reminder_time TIME,
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_assigned_to (assigned_to),
    INDEX idx_created_by (created_by),
    INDEX idx_deadline (deadline),
    INDEX idx_status (status),
    INDEX idx_priority (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Submissions Table
CREATE TABLE IF NOT EXISTS submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    employee_id INT NOT NULL,
    submission_text LONGTEXT,
    file_path VARCHAR(500),
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    admin_comment LONGTEXT,
    admin_id INT,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_task_id (task_id),
    INDEX idx_employee_id (employee_id),
    INDEX idx_status (status),
    UNIQUE KEY unique_task_employee (task_id, employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notifications Table (for storing reminder/notification history)
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    task_id INT,
    type ENUM('reminder', 'submission', 'approval', 'rejection') DEFAULT 'reminder',
    title VARCHAR(255) NOT NULL,
    message LONGTEXT,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Password Reset Tokens Table
CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    code VARCHAR(6),
    expires_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_token (token),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Session Table for managing user sessions
CREATE TABLE IF NOT EXISTS sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(500) NOT NULL UNIQUE,
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    expires_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_token (token),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Analytics Table for tracking statistics
CREATE TABLE IF NOT EXISTS analytics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    metric_type VARCHAR(100),
    total_tasks INT DEFAULT 0,
    completed_tasks INT DEFAULT 0,
    pending_tasks INT DEFAULT 0,
    total_employees INT DEFAULT 0,
    total_submissions INT DEFAULT 0,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_admin_id (admin_id),
    INDEX idx_recorded_at (recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Projects Table
CREATE TABLE IF NOT EXISTS projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description LONGTEXT,
    client_name VARCHAR(255),
    owner_id INT NULL,
    status ENUM('planning', 'active', 'on_hold', 'completed', 'archived') DEFAULT 'planning',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    start_date DATE,
    end_date DATE,
    budget DECIMAL(12,2) DEFAULT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_projects_owner_id (owner_id),
    INDEX idx_projects_created_by (created_by),
    INDEX idx_projects_status (status),
    INDEX idx_projects_priority (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Design Projects Table
CREATE TABLE IF NOT EXISTS design_projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description LONGTEXT,
    client_name VARCHAR(255),
    designer_id INT NULL,
    status ENUM('concept', 'in_progress', 'review', 'approved', 'completed', 'archived') DEFAULT 'concept',
    due_date DATE,
    image_path VARCHAR(500),
    notes LONGTEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (designer_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_design_projects_designer_id (designer_id),
    INDEX idx_design_projects_created_by (created_by),
    INDEX idx_design_projects_status (status),
    INDEX idx_design_projects_due_date (due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin Settings Table
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    setting_key VARCHAR(100) NOT NULL,
    setting_value LONGTEXT,
    setting_type ENUM('text', 'number', 'boolean', 'json') DEFAULT 'text',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_admin_setting (admin_id, setting_key),
    INDEX idx_settings_admin_id (admin_id),
    INDEX idx_settings_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Basic Indexes for Performance
-- ============================================
-- Most indexes are already created in table definitions above

-- Reminders Table (for storing reminder schedules)
CREATE TABLE IF NOT EXISTS reminders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    reminder_type ENUM('none', '1day', '30min', 'custom') DEFAULT 'none',
    reminder_time DATETIME NOT NULL,
    sent BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_task_id (task_id),
    INDEX idx_user_id (user_id),
    INDEX idx_reminder_time (reminder_time),
    INDEX idx_sent (sent)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Trigger to update task updated_at timestamp
DELIMITER $$
CREATE TRIGGER task_update_trigger
BEFORE UPDATE ON tasks
FOR EACH ROW
BEGIN
    SET NEW.updated_at = CURRENT_TIMESTAMP;
END$$
DELIMITER ;

-- Trigger to update submission updated_at timestamp
DELIMITER $$
CREATE TRIGGER submission_update_trigger
BEFORE UPDATE ON submissions
FOR EACH ROW
BEGIN
    SET NEW.updated_at = CURRENT_TIMESTAMP;
END$$
DELIMITER ;

-- Trigger to update project updated_at timestamp
DELIMITER $$
CREATE TRIGGER project_update_trigger
BEFORE UPDATE ON projects
FOR EACH ROW
BEGIN
    SET NEW.updated_at = CURRENT_TIMESTAMP;
END$$
DELIMITER ;

-- Trigger to update design project updated_at timestamp
DELIMITER $$
CREATE TRIGGER design_project_update_trigger
BEFORE UPDATE ON design_projects
FOR EACH ROW
BEGIN
    SET NEW.updated_at = CURRENT_TIMESTAMP;
END$$
DELIMITER ;
