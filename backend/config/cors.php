<?php
/**
 * CORS (Cross-Origin Resource Sharing) Configuration
 */

// Define allowed origins
$allowedOrigins = [
    'https://task-management-neon-one.vercel.app',
    'http://localhost:3000',
    'http://localhost:5173',
    'http://127.0.0.1:3000',
    'http://127.0.0.1:5173',
    'http://localhost:8000',
    'http://127.0.0.1:8000',
];

// Try to load FRONTEND_URL from environment
if (getenv('FRONTEND_URL')) {
    $frontendUrl = rtrim(getenv('FRONTEND_URL'), '/');
    $allowedOrigins[] = $frontendUrl;
} elseif (defined('FRONTEND_URL')) {
    $frontendUrl = rtrim(FRONTEND_URL, '/');
    $allowedOrigins[] = $frontendUrl;
}

$allowedOrigins = array_values(array_unique($allowedOrigins));

// Get the request origin
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

// Set the appropriate CORS header
if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
} elseif (empty($origin)) {
    // Same-origin request
}

header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin');
header('Access-Control-Max-Age: 86400');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Set content type to JSON
header('Content-Type: application/json');
