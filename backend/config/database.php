<?php
/**
 * PostgreSQL Database Configuration
 * Task Management System Backend
 * Supports both local and remote (NeonDB) connections
 */

// Load environment variables from .env if it exists
if (file_exists(__DIR__ . '/../.env')) {
    // Use custom parser to handle comments and special characters properly
    $envContent = file_get_contents(__DIR__ . '/../.env');
    $lines = explode("\n", $envContent);
    $env = [];
    
    foreach ($lines as $line) {
        $line = trim($line);
        // Skip empty lines and comments
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        // Parse key=value pairs
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $env[trim($key)] = trim($value);
        }
    }
    
    foreach ($env as $key => $value) {
        define($key, $value);
    }
}

// Database credentials (with fallback defaults)
// Allow values from a .env file (constants) or from environment variables (getenv)
$db_host = getenv('DB_HOST') !== false ? getenv('DB_HOST') : (defined('DB_HOST') ? DB_HOST : 'localhost');
$db_port = getenv('DB_PORT') !== false ? getenv('DB_PORT') : (defined('DB_PORT') ? DB_PORT : '5432');
$db_name = getenv('DB_NAME') !== false ? getenv('DB_NAME') : (defined('DB_NAME') ? DB_NAME : 'task_management');
$db_user = getenv('DB_USER') !== false ? getenv('DB_USER') : (defined('DB_USER') ? DB_USER : 'postgres');
$db_password = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : (defined('DB_PASSWORD') ? DB_PASSWORD : 'postgres');
$db_ssl_mode = getenv('DB_SSL_MODE') !== false ? getenv('DB_SSL_MODE') : (defined('DB_SSL_MODE') ? DB_SSL_MODE : 'prefer');

define('DB_HOST', $db_host);
define('DB_PORT', $db_port);
define('DB_NAME', $db_name);
define('DB_USER', $db_user);
define('DB_PASSWORD', $db_password);
define('DB_SSL_MODE', $db_ssl_mode);

// Build connection string for PDO
$dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";sslmode=" . DB_SSL_MODE;

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

/**
 * Initialize database tables if they don't exist
 */
function initializeDatabase($pdo) {
    try {
        // Users table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id SERIAL PRIMARY KEY,
                email VARCHAR(255) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                username VARCHAR(100),
                role VARCHAR(50) NOT NULL DEFAULT 'member',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS role VARCHAR(50) NOT NULL DEFAULT 'member'");
        $pdo->exec("UPDATE users SET role = 'member' WHERE role IS NULL OR role = ''");
        $pdo->exec("UPDATE users SET role = 'super_admin' WHERE email = 'autonemac003@gmail.com'");

        // Organizations table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS organizations (
                id SERIAL PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Organization members table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS organization_members (
                id SERIAL PRIMARY KEY,
                user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
                organization_id INTEGER REFERENCES organizations(id) ON DELETE CASCADE,
                role VARCHAR(50) NOT NULL DEFAULT 'member',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(user_id, organization_id)
            )
        ");

        // Tasks table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS tasks (
                id SERIAL PRIMARY KEY,
                user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
                organization_id INTEGER REFERENCES organizations(id) ON DELETE SET NULL,
                assigned_to INTEGER REFERENCES users(id) ON DELETE SET NULL,
                assigned_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT,
                category VARCHAR(100),
                priority VARCHAR(50),
                status VARCHAR(50) DEFAULT 'pending',
                due_date TIMESTAMP,
                    meet_link TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $pdo->exec("ALTER TABLE tasks ADD COLUMN IF NOT EXISTS organization_id INTEGER REFERENCES organizations(id) ON DELETE SET NULL");
        $pdo->exec("ALTER TABLE tasks ADD COLUMN IF NOT EXISTS assigned_to INTEGER REFERENCES users(id) ON DELETE SET NULL");
        $pdo->exec("ALTER TABLE tasks ADD COLUMN IF NOT EXISTS assigned_by INTEGER REFERENCES users(id) ON DELETE SET NULL");
        $pdo->exec("ALTER TABLE tasks ADD COLUMN IF NOT EXISTS status VARCHAR(50) DEFAULT 'pending'");
        $pdo->exec("ALTER TABLE tasks ADD COLUMN IF NOT EXISTS meet_link TEXT");
        $pdo->exec("ALTER TABLE tasks ADD COLUMN IF NOT EXISTS submission_type VARCHAR(20)");
        $pdo->exec("ALTER TABLE tasks ADD COLUMN IF NOT EXISTS submission_url TEXT");
        $pdo->exec("ALTER TABLE tasks ADD COLUMN IF NOT EXISTS submitted_at TIMESTAMP");

        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tasks_organization_id ON tasks(organization_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tasks_assigned_to ON tasks(assigned_to)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tasks_assigned_by ON tasks(assigned_by)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tasks_submitted_at ON tasks(submitted_at)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_organization_members_user_id ON organization_members(user_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_organization_members_organization_id ON organization_members(organization_id)");

        // Notifications table
        $pdo->exec(" 
            CREATE TABLE IF NOT EXISTS notifications (
                id SERIAL PRIMARY KEY,
                user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
                organization_id INTEGER REFERENCES organizations(id) ON DELETE CASCADE,
                task_id INTEGER REFERENCES tasks(id) ON DELETE CASCADE,
                message TEXT NOT NULL,
                is_read BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_notifications_user_id ON notifications(user_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_notifications_read_state ON notifications(user_id, is_read)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_notifications_created_at ON notifications(created_at DESC)");

        // Task reflections table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS task_reflections (
                id SERIAL PRIMARY KEY,
                task_id INTEGER REFERENCES tasks(id) ON DELETE CASCADE,
                user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
                reflection_text TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Reminders table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS reminders (
                id SERIAL PRIMARY KEY,
                task_id INTEGER REFERENCES tasks(id) ON DELETE CASCADE,
                user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
                reminder_type VARCHAR(100),
                reminder_time TIMESTAMP,
                sent BOOLEAN DEFAULT false,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Daily diary table (isolated from tasks module)
        $pdo->exec(" 
            CREATE TABLE IF NOT EXISTS diary_entries (
                id SERIAL PRIMARY KEY,
                user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
                entry_date DATE NOT NULL DEFAULT CURRENT_DATE,
                title VARCHAR(255) NOT NULL,
                content TEXT NOT NULL,
                mood VARCHAR(50),
                audio_file_path VARCHAR(500),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $pdo->exec(" 
            CREATE INDEX IF NOT EXISTS idx_diary_entries_user_date
            ON diary_entries(user_id, entry_date DESC, created_at DESC)
        ");

        // Preaching notes table (isolated from tasks and diary modules)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS preaching_entries (
                id SERIAL PRIMARY KEY,
                user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
                title VARCHAR(255) NOT NULL,
                preacher VARCHAR(255) NOT NULL,
                content TEXT NOT NULL,
                tags TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $pdo->exec("
            CREATE INDEX IF NOT EXISTS idx_preaching_entries_user_updated
            ON preaching_entries(user_id, updated_at DESC, created_at DESC)
        ");

        $pdo->exec("
            CREATE INDEX IF NOT EXISTS idx_preaching_entries_user_title
            ON preaching_entries(user_id, title)
        ");

        // Password reset tokens table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS password_reset_tokens (
                id SERIAL PRIMARY KEY,
                user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
                token VARCHAR(255) UNIQUE NOT NULL,
                expires_at TIMESTAMP NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        return true;
    } catch (PDOException $e) {
        error_log('Database initialization error: ' . $e->getMessage());
        return false;
    }
}

// Ensure tables exist (idempotent). Call on every request — CREATE TABLE IF NOT EXISTS is safe.
initializeDatabase($pdo);
