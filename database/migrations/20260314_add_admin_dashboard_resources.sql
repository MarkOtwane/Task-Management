-- Migration: Add admin dashboard resources
-- Date: 2026-03-14
CREATE TABLE
    IF NOT EXISTS projects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description LONGTEXT,
        client_name VARCHAR(255),
        owner_id INT NULL,
        status ENUM (
            'planning',
            'active',
            'on_hold',
            'completed',
            'archived'
        ) DEFAULT 'planning',
        priority ENUM ('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
        start_date DATE,
        end_date DATE,
        budget DECIMAL(12, 2) DEFAULT NULL,
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE SET NULL,
        FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE CASCADE,
        INDEX idx_projects_owner_id (owner_id),
        INDEX idx_projects_created_by (created_by),
        INDEX idx_projects_status (status),
        INDEX idx_projects_priority (priority)
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE
    IF NOT EXISTS design_projects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description LONGTEXT,
        client_name VARCHAR(255),
        designer_id INT NULL,
        status ENUM (
            'concept',
            'in_progress',
            'review',
            'approved',
            'completed',
            'archived'
        ) DEFAULT 'concept',
        due_date DATE,
        image_path VARCHAR(500),
        notes LONGTEXT,
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (designer_id) REFERENCES users (id) ON DELETE SET NULL,
        FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE CASCADE,
        INDEX idx_design_projects_designer_id (designer_id),
        INDEX idx_design_projects_created_by (created_by),
        INDEX idx_design_projects_status (status),
        INDEX idx_design_projects_due_date (due_date)
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE
    IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NOT NULL,
        setting_key VARCHAR(100) NOT NULL,
        setting_value LONGTEXT,
        setting_type ENUM ('text', 'number', 'boolean', 'json') DEFAULT 'text',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (admin_id) REFERENCES users (id) ON DELETE CASCADE,
        UNIQUE KEY unique_admin_setting (admin_id, setting_key),
        INDEX idx_settings_admin_id (admin_id),
        INDEX idx_settings_setting_key (setting_key)
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO
    settings (
        admin_id,
        setting_key,
        setting_value,
        setting_type
    )
SELECT
    1,
    'company_name',
    'TaskFlow',
    'text'
WHERE
    NOT EXISTS (
        SELECT
            1
        FROM
            settings
        WHERE
            admin_id = 1
            AND setting_key = 'company_name'
    );

INSERT INTO
    settings (
        admin_id,
        setting_key,
        setting_value,
        setting_type
    )
SELECT
    1,
    'default_task_priority',
    'medium',
    'text'
WHERE
    NOT EXISTS (
        SELECT
            1
        FROM
            settings
        WHERE
            admin_id = 1
            AND setting_key = 'default_task_priority'
    );

INSERT INTO
    settings (
        admin_id,
        setting_key,
        setting_value,
        setting_type
    )
SELECT
    1,
    'email_notifications_enabled',
    'true',
    'boolean'
WHERE
    NOT EXISTS (
        SELECT
            1
        FROM
            settings
        WHERE
            admin_id = 1
            AND setting_key = 'email_notifications_enabled'
    );

INSERT INTO
    settings (
        admin_id,
        setting_key,
        setting_value,
        setting_type
    )
SELECT
    1,
    'dashboard_preferences',
    '{"showAnalytics":true,"showRecentSubmissions":true}',
    'json'
WHERE
    NOT EXISTS (
        SELECT
            1
        FROM
            settings
        WHERE
            admin_id = 1
            AND setting_key = 'dashboard_preferences'
    );