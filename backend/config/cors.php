<?php
/**
 * CORS (Cross-Origin Resource Sharing) Configuration
 */

// Define allowed origins
$allowedOrigins = [];

// Try to load FRONTEND_URL from environment
if (getenv('FRONTEND_URL')) {
    $frontendUrl = rtrim(getenv('FRONTEND_URL'), '/');
    $allowedOrigins[] = $frontendUrl;
    error_log("CORS: Adding FRONTEND_URL to allowed origins: " . $frontendUrl);
} elseif (defined('FRONTEND_URL')) {
    $frontendUrl = rtrim(FRONTEND_URL, '/');
    $allowedOrigins[] = $frontendUrl;
    error_log("CORS: Adding defined FRONTEND_URL to allowed origins: " . $frontendUrl);
}

// Always allow localhost for development
$allowedOrigins[] = 'http://localhost:3000';
$allowedOrigins[] = 'http://localhost:8000';
$allowedOrigins[] = 'http://127.0.0.1:3000';
$allowedOrigins[] = 'http://127.0.0.1:8000';

// Get the request origin
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

// Set the appropriate CORS header
if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    error_log("CORS: Allowing origin: " . $origin);
} elseif (empty($origin)) {
    // No origin header (same-origin request)
    error_log("CORS: No origin header detected");
} else {
    // Origin not in allowed list - log but still allow for development
    error_log("CORS: Origin not in allowed list: " . $origin . ". Allowed origins: " . implode(', ', $allowedOrigins));
    // Temporarily allow for debugging - in production, you might want to be more restrictive
    header('Access-Control-Allow-Origin: ' . $origin);
}

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 86400');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Set content type to JSON
header('Content-Type: application/json');
