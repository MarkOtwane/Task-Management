<?php
/**
 * Common Helper Functions
 * TaskFlow Backend Core Functions
 */

/**
 * Send JSON response with appropriate status code
 */
function sendJson($data, $statusCode = 200) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

/**
 * Send error response
 */
function sendError($message, $statusCode = 400, $errors = null) {
    $response = ['error' => $message];
    if ($errors && APP_DEBUG) {
        $response['details'] = $errors;
    }
    sendJson($response, $statusCode);
}

/**
 * Send success response
 */
function sendSuccess($data, $message = null) {
    $response = ['success' => true];
    if ($message) {
        $response['message'] = $message;
    }
    if (is_array($data)) {
        $response = array_merge($response, $data);
    }
    sendJson($response, 200);
}

/**
 * Get JSON input from request body
 */
function getJsonInput() {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    return $data ?? [];
}

/**
 * Validate email format
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Hash password using bcrypt
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_ALGO, ['cost' => PASSWORD_COST]);
}

/**
 * Verify password against hash
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Generate secure random token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Generate 6-digit OTP
 */
function generateOTP() {
    return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * Get bearer token from Authorization header
 */
function getBearerToken() {
    $headers = getallheaders();
    if (!isset($headers['Authorization'])) {
        return null;
    }
    
    $matches = [];
    if (preg_match('/Bearer\s+([^\s]+)/', $headers['Authorization'], $matches)) {
        return $matches[1];
    }
    
    return null;
}

/**
 * Validate JWT token (simplified - for production, use a library)
 */
function validateJWT($token) {
    // For now, return token - implement proper JWT validation later
    return $token;
}

/**
 * Get current user from JWT token
 */
function getCurrentUser() {
    $token = getBearerToken();
    if (!$token) {
        return null;
    }
    
    // In production, validate JWT properly
    // For now, fetch from sessions table
    try {
        $pdo = getDatabase();
        $stmt = $pdo->prepare('SELECT u.* FROM users u INNER JOIN sessions s ON u.id = s.user_id WHERE s.token = ? AND s.expires_at > NOW()');
        $stmt->execute([$token]);
        return $stmt->fetch();
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Validate file upload
 */
function validateFileUpload($file) {
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return ['valid' => false, 'error' => 'No file provided'];
    }
    
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return ['valid' => false, 'error' => 'File size exceeds maximum limit'];
    }
    
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    if (!in_array(strtolower($ext), ALLOWED_EXTENSIONS)) {
        return ['valid' => false, 'error' => 'File type not allowed'];
    }
    
    return ['valid' => true];
}

/**
 * Save uploaded file
 */
function saveUploadedFile($file, $subfolder = 'submissions') {
    $validation = validateFileUpload($file);
    if (!$validation['valid']) {
        return ['success' => false, 'error' => $validation['error']];
    }
    
    // Create upload directory if it doesn't exist
    $uploadPath = UPLOAD_DIR . '/' . $subfolder;
    if (!is_dir($uploadPath)) {
        mkdir($uploadPath, 0755, true);
    }
    
    // Generate unique filename
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newFilename = uniqid() . '_' . time() . '.' . $ext;
    $filePath = $uploadPath . '/' . $newFilename;
    
    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        return [
            'success' => true,
            'path' => 'uploads/' . $subfolder . '/' . $newFilename,
            'filename' => $newFilename
        ];
    }
    
    return ['success' => false, 'error' => 'Failed to save file'];
}

/**
 * Validate required fields
 */
function validateRequired($data, $fields) {
    $errors = [];
    foreach ($fields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            $errors[$field] = ucfirst($field) . ' is required';
        }
    }
    return $errors;
}

/**
 * Sanitize string input
 */
function sanitizeString($str) {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

/**
 * Log action to error log
 */
function logAction($action, $userId = null, $details = []) {
    $logMessage = date('Y-m-d H:i:s') . ' | Action: ' . $action;
    if ($userId) {
        $logMessage .= ' | User ID: ' . $userId;
    }
    if (!empty($details)) {
        $logMessage .= ' | Details: ' . json_encode($details);
    }
    error_log($logMessage);
}

/**
 * Set up CORS headers
 */
function setCorsHeaders() {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowedOrigins = [
        'https://task-management-neon-one.vercel.app',
        'http://localhost:3000',
        'http://localhost:5173',
        'http://127.0.0.1:3000',
        'http://127.0.0.1:5173',
        'http://localhost:8000',
        'http://127.0.0.1:8000',
    ];

    if (!empty($origin) && in_array($origin, $allowedOrigins, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Vary: Origin');
    }

    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin');
    header('Access-Control-Max-Age: 3600');
    header('Content-Type: application/json; charset=utf-8');
}

// Set CORS headers on automatic load
setCorsHeaders();

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Set error handling
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
}

ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/php_errors.log');

// Create logs directory if it doesn't exist
$logsDir = __DIR__ . '/../../logs';
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
}

// Create uploads directory if it doesn't exist
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}
?>
