<?php
/**
 * Authentication Middleware
 */

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
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    return $userId;
}
