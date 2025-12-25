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
        $userId = verifyJWT($token);
        if ($userId) {
            error_log('JWT auth successful for user: ' . $userId);
            return $userId;
        } else {
            error_log('JWT auth failed for token: ' . substr($token, 0, 20) . '...');
        }
    }
    
    // Check for session
    if (isset($_SESSION['user_id'])) {
        error_log('Session auth successful for user: ' . $_SESSION['user_id']);
        return $_SESSION['user_id'];
    }
    
    error_log('No valid authentication found');
    return false;
}

/**
 * Simple JWT verification
 * In production, use a proper JWT library
 */
function verifyJWT($token) {
    // Simple JWT implementation for demo
    $parts = explode('.', $token);
    if (count($parts) !== 3) return false;
    
    try {
        $payload = json_decode(base64_decode($parts[1]), true);
        
        // Check if payload is valid
        if (!$payload || !isset($payload['user_id'])) {
            return false;
        }
        
        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return false;
        }
        
        // Return user ID if valid
        return $payload['user_id'];
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Generate JWT token
 * In production, use a proper JWT library
 */
function generateJWT($userId) {
    $header = json_encode(['typ' => 'JWT', 'alg' => 'none']);
    $payload = json_encode([
        'user_id' => (int)$userId,
        'exp' => time() + (24 * 60 * 60), // 24 hours
        'iat' => time()
    ]);
    
    $token = base64_encode($header) . '.' . base64_encode($payload) . '.signature';
    
    return ['token' => $token];
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
