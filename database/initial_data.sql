-- ============================================
-- TaskFlow Initial Data
-- Sample data for testing
-- ============================================
-- Insert sample admin user (password: admin123 - hashed)
INSERT INTO
    users (name, email, password, role, status)
VALUES
    (
        'Admin User',
        'admin@taskflow.com',
        '$2y$10$YourHashedPasswordHere',
        'admin',
        'active'
    );

-- Insert sample employees (password: emp123 - hashed)
INSERT INTO
    users (name, email, password, role, department, status)
VALUES
    (
        'John Doe',
        'john@taskflow.com',
        '$2y$10$YourHashedPasswordHere',
        'employee',
        'Development',
        'active'
    ),
    (
        'Jane Smith',
        'jane@taskflow.com',
        '$2y$10$YourHashedPasswordHere',
        'employee',
        'Marketing',
        'active'
    ),
    (
        'Bob Johnson',
        'bob@taskflow.com',
        '$2y$10$YourHashedPasswordHere',
        'employee',
        'Design',
        'active'
    );

-- Sample tasks
INSERT INTO
    tasks (
        title,
        description,
        assigned_to,
        created_by,
        deadline,
        due_time,
        reminder_type,
        priority
    )
VALUES
    (
        'Complete Project Report',
        'Finish Q1 performance report with metrics',
        2,
        1,
        DATE_ADD (CURDATE (), INTERVAL 7 DAY),
        '17:00',
        '1day',
        'high'
    ),
    (
        'Review Design Files',
        'Review and approve new landing page design',
        3,
        1,
        DATE_ADD (CURDATE (), INTERVAL 3 DAY),
        '14:00',
        '30min',
        'medium'
    ),
    (
        'Weekly Team Meeting',
        'Prepare agenda and slides for team sync',
        2,
        1,
        DATE_ADD (CURDATE (), INTERVAL 1 DAY),
        '10:00',
        'none',
        'medium'
    );

-- Sample projects
INSERT INTO
    projects (
        title,
        description,
        client_name,
        owner_id,
        status,
        priority,
        start_date,
        end_date,
        budget,
        created_by
    )
VALUES
    (
        'Website Relaunch',
        'Coordinate content, engineering, and launch readiness for the marketing site relaunch.',
        'Acme Corp',
        2,
        'active',
        'high',
        CURDATE (),
        DATE_ADD (CURDATE (), INTERVAL 21 DAY),
        12500.00,
        1
    ),
    (
        'Quarterly Operations Rollout',
        'Track the cross-team rollout plan for internal operations improvements.',
        'Internal',
        3,
        'planning',
        'medium',
        DATE_ADD (CURDATE (), INTERVAL 3 DAY),
        DATE_ADD (CURDATE (), INTERVAL 45 DAY),
        8000.00,
        1
    );

-- Sample design projects
INSERT INTO
    design_projects (
        title,
        description,
        client_name,
        designer_id,
        status,
        due_date,
        notes,
        created_by
    )
VALUES
    (
        'Landing Page Refresh',
        'Produce updated hero concepts and responsive layout directions.',
        'Acme Corp',
        4,
        'review',
        DATE_ADD (CURDATE (), INTERVAL 10 DAY),
        'Need two alternate hero directions with mobile-first revisions.',
        1
    ),
    (
        'Brand Toolkit Update',
        'Refresh the client brand toolkit with updated social templates and iconography.',
        'Nova Studio',
        4,
        'concept',
        DATE_ADD (CURDATE (), INTERVAL 18 DAY),
        'Include export-ready social banner sizes.',
        1
    );

-- Sample admin settings
INSERT INTO
    settings (
        admin_id,
        setting_key,
        setting_value,
        setting_type
    )
VALUES
    (1, 'company_name', 'TaskFlow', 'text'),
    (1, 'default_task_priority', 'medium', 'text'),
    (
        1,
        'email_notifications_enabled',
        'true',
        'boolean'
    ),
    (
        1,
        'dashboard_preferences',
        '{"showAnalytics":true,"showRecentSubmissions":true}',
        'json'
    );