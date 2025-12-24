<?php
/**
 * Tasks API
 * Endpoints: GET, POST, PUT, DELETE for tasks
 */

require_once '../config/cors.php';
require_once '../config/database.php';
require_once '../middleware/auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$userId = requireAuth();

if ($method === 'GET') {
    getTasks($pdo, $userId);
} elseif ($method === 'POST') {
    createTask($pdo, $userId);
} elseif ($method === 'PUT') {
    updateTask($pdo, $userId);
} elseif ($method === 'DELETE') {
    deleteTask($pdo, $userId);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

/**
 * Get all tasks for user
 */
function getTasks($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM tasks 
            WHERE user_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$userId]);
        $tasks = $stmt->fetchAll();
        
        echo json_encode($tasks);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch tasks: ' . $e->getMessage()]);
    }
}

/**
 * Create new task
 */
function createTask($pdo, $userId) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['title'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Title is required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO tasks (user_id, title, description, category, priority, status, due_date)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $userId,
            $input['title'],
            $input['description'] ?? null,
            $input['category'] ?? null,
            $input['priority'] ?? 'medium',
            $input['status'] ?? 'pending',
            isset($input['due_date']) ? new DateTime($input['due_date']) : null
        ]);
        
        $taskId = $pdo->lastInsertId();
        
        http_response_code(201);
        echo json_encode([
            'message' => 'Task created successfully',
            'task_id' => $taskId
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create task: ' . $e->getMessage()]);
    }
}

/**
 * Update task
 */
function updateTask($pdo, $userId) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Task ID is required']);
        return;
    }
    
    // Verify ownership
    $stmt = $pdo->prepare("SELECT id FROM tasks WHERE id = ? AND user_id = ?");
    $stmt->execute([$input['id'], $userId]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }
    
    try {
        $updates = [];
        $values = [];
        
        $allowedFields = ['title', 'description', 'category', 'priority', 'status', 'due_date'];
        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $updates[] = "$field = ?";
                $values[] = $input[$field];
            }
        }
        
        if (empty($updates)) {
            http_response_code(400);
            echo json_encode(['error' => 'No fields to update']);
            return;
        }
        
        $updates[] = "updated_at = CURRENT_TIMESTAMP";
        $values[] = $input['id'];
        
        $sql = "UPDATE tasks SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        
        echo json_encode(['message' => 'Task updated successfully']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update task: ' . $e->getMessage()]);
    }
}

/**
 * Delete task
 */
function deleteTask($pdo, $userId) {
    $taskId = $_GET['id'] ?? null;
    
    if (!$taskId) {
        http_response_code(400);
        echo json_encode(['error' => 'Task ID is required']);
        return;
    }
    
    // Verify ownership
    $stmt = $pdo->prepare("SELECT id FROM tasks WHERE id = ? AND user_id = ?");
    $stmt->execute([$taskId, $userId]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ? AND user_id = ?");
        $stmt->execute([$taskId, $userId]);
        
        echo json_encode(['message' => 'Task deleted successfully']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete task: ' . $e->getMessage()]);
    }
}
