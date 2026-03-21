<?php
/**
 * MySQL Database Configuration
 * TaskFlow Multi-User Application Backend
 */

// Load environment variables from .env file
function loadEnv($path) {
    if (!file_exists($path)) {
        return;
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if (!empty($key) && !isset($_ENV[$key])) {
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }
}

loadEnv(__DIR__ . '/../../.env');

// Database credentials (with fallback defaults)
// Allow values from a .env file (constants) or from environment variables (getenv)
// Database configuration from environment or defaults
define('DB_HOST', getenv('DB_HOST') ?: 'mysql');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'taskflow');
define('DB_USER', getenv('DB_USER') ?: 'taskflow_user');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: 'taskflow_password');
define('DB_CHARSET', 'utf8mb4');

// Application configuration
define('APP_ENV', getenv('APP_ENV') ?: 'development');
define('APP_DEBUG', getenv('APP_DEBUG') ?: true);
define('SESSION_TIMEOUT', getenv('SESSION_TIMEOUT') ?: 3600);
define('RESEND_API_KEY', getenv('RESEND_API_KEY') ?: '');

// Security settings
define('JWT_SECRET', getenv('JWT_SECRET') ?: 'your_secret_key_change_this');
define('PASSWORD_MIN_LENGTH', 8);
define('PASSWORD_ALGO', PASSWORD_BCRYPT);
define('PASSWORD_COST', 10);

// File upload settings
define('MAX_UPLOAD_SIZE', getenv('MAX_UPLOAD_SIZE') ?: 10485760); // 10MB
define('UPLOAD_DIR', __DIR__ . '/../../uploads');
define('ALLOWED_EXTENSIONS', ['pdf', 'doc', 'docx', 'txt', 'xlsx', 'xls', 'jpg', 'jpeg', 'png', 'gif']);

// Create database connection
function getDatabase() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            
            $pdo = new PDO(
                $dsn,
                DB_USER,
                DB_PASSWORD,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_STRINGIFY_FETCHES => false,
                ]
            );
            
            // Set timezone and charset
            $pdo->exec("SET time_zone = '+00:00'");
            $pdo->exec("SET NAMES utf8mb4");
            
            error_log('[Database] Connected to MySQL: ' . DB_NAME . ' on ' . DB_HOST);
        } catch (PDOException $e) {
            error_log('Database connection error: ' . $e->getMessage());
            http_response_code(500);
            die(json_encode(['error' => 'Database connection failed', 'debug' => APP_DEBUG ? $e->getMessage() : null]));
        }
    }
    
    return $pdo;
}

// Get database instance immediately for later use
$pdo = getDatabase();
