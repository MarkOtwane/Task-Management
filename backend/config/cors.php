<?php
/**
 * CORS (Cross-Origin Resource Sharing) Configuration
 */

// CORS configuration
// Prefer a configured FRONTEND_URL or fall back to request Origin when allowed
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = [];
// Try to load FRONTEND_URL from environment or .env constants
if (getenv('FRONTEND_URL') !== false) {
    $allowedOrigins[] = rtrim(getenv('FRONTEND_URL'), '/');
} elseif (defined('FRONTEND_URL')) {
    $allowedOrigins[] = rtrim(FRONTEND_URL, '/');
}

// If no configured allowed origin, allow same-origin requests only
if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} elseif (empty($allowedOrigins) && $origin) {
    // No configured FRONTEND_URL — allow the request origin (useful for testing)
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    // As a safe default, do not allow wildcard when credentials are used
    header('Access-Control-Allow-Origin: null');
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
