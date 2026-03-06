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