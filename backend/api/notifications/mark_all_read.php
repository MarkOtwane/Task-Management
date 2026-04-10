<?php
/**
 * Mark all notifications as read for the authenticated user.
 * Route: POST /api/notifications/mark_all_read.php
 */

require_once '../../config/cors.php';
require_once '../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($pdo) || !($pdo instanceof PDO)) {
    error_log('mark_all_read.php: Database connection ($pdo) not available');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
$input = is_array($input) ? $input : [];
$organizationId = isset($input['organization_id']) ? (int) $input['organization_id'] : null;

try {
    if ($organizationId) {
        $stmt = $pdo->prepare(
            'UPDATE notifications SET is_read = TRUE WHERE user_id = ? AND organization_id = ? AND is_read = FALSE'
        );
        $stmt->execute([$userId, $organizationId]);
    } else {
        $stmt = $pdo->prepare(
            'UPDATE notifications SET is_read = TRUE WHERE user_id = ? AND is_read = FALSE'
        );
        $stmt->execute([$userId]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Notifications marked as read',
        'updated' => $stmt->rowCount(),
    ]);
} catch (PDOException $exception) {
    error_log('mark_all_read.php SQL error: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to update notifications']);
}
