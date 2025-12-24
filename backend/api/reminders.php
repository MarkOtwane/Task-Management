<?php
/**
 * Reminders API
 * Endpoints: GET, POST for task reminders
 */

require_once '../config/cors.php';
require_once '../config/database.php';
require_once '../middleware/auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$userId = requireAuth();

if ($method === 'GET') {
    getReminders($pdo, $userId);
} elseif ($method === 'POST') {
    createReminder($pdo, $userId);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

/**
 * Get reminders for user
 */
function getReminders($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("
            SELECT r.* FROM reminders r
            JOIN tasks t ON r.task_id = t.id
            WHERE r.user_id = ? AND r.sent = false
            ORDER BY r.reminder_time ASC
        ");
        $stmt->execute([$userId]);
        $reminders = $stmt->fetchAll();
        
        echo json_encode($reminders);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch reminders: ' . $e->getMessage()]);
    }
}

/**
 * Create reminder for task
 */
function createReminder($pdo, $userId) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['task_id']) || !isset($input['reminder_type']) || !isset($input['reminder_time'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Task ID, reminder type, and reminder time are required']);
        return;
    }
    
    // Verify task ownership
    $stmt = $pdo->prepare("SELECT id FROM tasks WHERE id = ? AND user_id = ?");
    $stmt->execute([$input['task_id'], $userId]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO reminders (task_id, user_id, reminder_type, reminder_time)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $input['task_id'],
            $userId,
            $input['reminder_type'],
            $input['reminder_time']
        ]);
        
        $reminderId = $pdo->lastInsertId();
        
        http_response_code(201);
        echo json_encode([
            'message' => 'Reminder created successfully',
            'reminder_id' => $reminderId
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create reminder: ' . $e->getMessage()]);
    }
}
