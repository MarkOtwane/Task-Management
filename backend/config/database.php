<?php
/**
 * PostgreSQL Database Configuration
 * Task Management System Backend
 * Supports both local and remote (NeonDB) connections
 */

// Load environment variables from .env if it exists
if (file_exists(__DIR__ . '/../.env')) {
    $env = parse_ini_file(__DIR__ . '/../.env');
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
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Tasks table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS tasks (
                id SERIAL PRIMARY KEY,
                user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
                title VARCHAR(255) NOT NULL,
                description TEXT,
                category VARCHAR(100),
                priority VARCHAR(50),
                status VARCHAR(50) DEFAULT 'pending',
                due_date TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

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
