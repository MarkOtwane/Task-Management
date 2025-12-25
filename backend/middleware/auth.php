<?php
/**
 * Authentication Middleware
 */

// Ensure session cookie allows cross-site requests from the frontend
// when using credentialed fetch requests. Set SameSite=None and Secure
// for HTTPS deployments. These options require PHP 7.3+.
if (PHP_VERSION_ID >= 70300) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? '') == 443;
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'None'
    ]);
} else {
    // Fallback for older PHP versions: set cookie params without samesite
    session_set_cookie_params(0, '/', '', true, true);
}

session_start();

/**
 * Verify JWT token or session
 * Returns user_id if valid, false otherwise
 */
function verifyAuth() {
    // Check for JWT token in Authorization header
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        $token = str_replace('Bearer ', '', $headers['Authorization']);
        return verifyJWT($token);
    }
    
    // Check for session
    if (isset($_SESSION['user_id'])) {
        return $_SESSION['user_id'];
    }
    
    return false;
}

/**
 * Simple JWT verification
 * In production, use a proper JWT library
 */
function verifyJWT($token) {
    // Placeholder - implement proper JWT verification
    // For now, using session-based auth
    return false;
}

/**
 * Generate JWT token
 * In production, use a proper JWT library
 */
function generateJWT($userId) {
    // Placeholder - implement proper JWT generation
    // For now, using session-based auth
    $_SESSION['user_id'] = $userId;
    return ['token' => null];
}

/**
 * Require authentication
 */
function requireAuth() {
    $userId = verifyAuth();
    if (!$userId) {
        error_log('Authentication failed - no valid session or token');
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized - Please log in again']);
        exit;
    }
    error_log('Authentication successful for user: ' . $userId);
    return $userId;
}
