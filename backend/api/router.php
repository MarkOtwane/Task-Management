<?php
/**
 * API Router
 * Main entry point for all API requests
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers.php';

$requestUri = $_SERVER['REQUEST_URI'];
$basePath = '/backend/api';

// Parse the request path
$path = str_replace($basePath, '', parse_url($requestUri, PHP_URL_PATH));
$path = trim($path, '/');

// Extract the resource and action
$parts = explode('/', $path);
$resource = $parts[0] ?? '';
$action = $parts[1] ?? '';
$id = $parts[2] ?? null;

// Route to appropriate handler
try {
    switch ($resource) {
        case 'auth':
            require_once __DIR__ . '/auth.php';
            break;
        
        case 'tasks':
            require_once __DIR__ . '/tasks.php';
            break;
        
        case 'submissions':
            require_once __DIR__ . '/submissions.php';
            break;
        
        case 'dashboard':
            require_once __DIR__ . '/dashboard.php';
            break;
        
        case 'users':
            require_once __DIR__ . '/users.php';
            break;
        
        default:
            sendError('Endpoint not found', 404);
    }
} catch (Exception $e) {
    error_log('Router error: ' . $e->getMessage());
    sendError('Internal server error', 500, APP_DEBUG ? $e->getMessage() : null);
}

?>
