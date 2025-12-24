<?php
/**
 * Task Reflections API
 * Endpoints: GET, POST for task reflections
 */

require_once '../config/cors.php';
require_once '../config/database.php';
require_once '../middleware/auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$userId = requireAuth();

if ($method === 'GET') {
    getReflections($pdo, $userId);
} elseif ($method === 'POST') {
    createReflection($pdo, $userId);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

/**
 * Get reflections for a task
 */
function getReflections($pdo, $userId) {
    $taskId = $_GET['task_id'] ?? null;
    
    if (!$taskId) {
        http_response_code(400);
        echo json_encode(['error' => 'Task ID is required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT tr.* FROM task_reflections tr
            JOIN tasks t ON tr.task_id = t.id
            WHERE tr.task_id = ? AND t.user_id = ?
            ORDER BY tr.created_at DESC
        ");
        $stmt->execute([$taskId, $userId]);
        $reflections = $stmt->fetchAll();
        
        echo json_encode($reflections);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch reflections: ' . $e->getMessage()]);
    }
}

/**
 * Create reflection for task
 */
function createReflection($pdo, $userId) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['task_id']) || !isset($input['reflection_text'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Task ID and reflection text are required']);
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
            INSERT INTO task_reflections (task_id, user_id, reflection_text)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$input['task_id'], $userId, $input['reflection_text']]);
        
        $reflectionId = $pdo->lastInsertId();
        
        http_response_code(201);
        echo json_encode([
            'message' => 'Reflection created successfully',
            'reflection_id' => $reflectionId
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create reflection: ' . $e->getMessage()]);
    }
}
