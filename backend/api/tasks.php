<?php
/**
 * Tasks API
 * Handles task CRUD operations, assignment, and status management
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';
$taskId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// User must be authenticated
$user = getCurrentUser();
if (!$user) {
    sendError('Not authenticated', 401);
}

switch ($action) {
    case 'create':
        if ($method === 'POST') {
            handleCreateTask();
        }
        break;
    
    case 'list':
        if ($method === 'GET') {
            handleListTasks();
        }
        break;
    
    case 'get':
        if ($method === 'GET' && $taskId) {
            handleGetTask($taskId);
        }
        break;
    
    case 'update':
        if ($method === 'POST' && $taskId) {
            handleUpdateTask($taskId);
        }
        break;
    
    case 'delete':
        if ($method === 'DELETE' && $taskId) {
            handleDeleteTask($taskId);
        }
        break;
    
    case 'assign':
        if ($method === 'POST' && $taskId) {
            handleAssignTask($taskId);
        }
        break;
    
    case 'my-tasks':
        if ($method === 'GET') {
            handleGetMyTasks($user);
        }
        break;
    
    case 'analytics':
        if ($method === 'GET') {
            handleTaskAnalytics($user);
        }
        break;
    
    default:
        sendError('Invalid task endpoint', 400);
}

/**
 * Create a new task (Admin only)
 */
function handleCreateTask() {
    global $user;
    
    if ($user['role'] !== 'admin') {
        sendError('Only admins can create tasks', 403);
    }
    
    $input = getJsonInput();
    
    $required = ['title', 'assigned_to', 'deadline'];
    $errors = validateRequired($input, $required);
    
    if (!empty($errors)) {
        sendError('Validation failed', 400, $errors);
    }
    
    try {
        $pdo = getDatabase();
        
        // Verify user exists and is an employee
        $userCheck = $pdo->prepare('SELECT id FROM users WHERE id = ? AND role = ?');
        $userCheck->execute([$input['assigned_to'], 'employee']);
        
        if (!$userCheck->fetch()) {
            sendError('Invalid employee selected', 400);
        }
        
        // Insert task
        $stmt = $pdo->prepare('
            INSERT INTO tasks (
                title, description, assigned_to, created_by, deadline, due_time, 
                reminder_type, custom_reminder_time, priority, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ');
        
        $stmt->execute([
            sanitizeString($input['title']),
            isset($input['description']) ? sanitizeString($input['description']) : null,
            $input['assigned_to'],
            $user['id'],
            $input['deadline'],
            isset($input['due_time']) ? $input['due_time'] : null,
            $input['reminder_type'] ?? 'none',
            isset($input['custom_reminder_time']) ? $input['custom_reminder_time'] : null,
            $input['priority'] ?? 'medium',
            'pending'
        ]);
        
        $taskId = $pdo->lastInsertId();
        
        logAction('Task created', $user['id'], ['task_id' => $taskId, 'title' => $input['title']]);
        
        sendSuccess(['task_id' => $taskId], 'Task created successfully');
        
    } catch (Exception $e) {
        sendError('Failed to create task', 500, APP_DEBUG ? $e->getMessage() : null);
    }
}

/**
 * List tasks (with filters for admin and employees)
 */
function handleListTasks() {
    global $user;
    
    try {
        $pdo = getDatabase();
        
        if ($user['role'] === 'admin') {
            // Show all tasks
            $stmt = $pdo->prepare('
                SELECT 
                    t.*,
                    u.name as assigned_to_name,
                    u.email as assigned_to_email,
                    admin.name as created_by_name,
                    (SELECT COUNT(*) FROM submissions WHERE task_id = t.id) as submission_count,
                    (SELECT COUNT(*) FROM submissions WHERE task_id = t.id AND status = "approved") as approved_count
                FROM tasks t
                LEFT JOIN users u ON t.assigned_to = u.id
                LEFT JOIN users admin ON t.created_by = admin.id
                ORDER BY t.deadline ASC
            ');
            $stmt->execute();
        } else {
            // Show only assigned tasks
            $stmt = $pdo->prepare('
                SELECT 
                    t.*,
                    u.name as created_by_name,
                    u.email as created_by_email,
                    (SELECT submission_text FROM submissions WHERE task_id = t.id AND employee_id = ? LIMIT 1) as my_submission,
                    (SELECT status FROM submissions WHERE task_id = t.id AND employee_id = ? LIMIT 1) as submission_status
                FROM tasks t
                LEFT JOIN users u ON t.created_by = u.id
                WHERE t.assigned_to = ?
                ORDER BY t.deadline ASC
            ');
            $stmt->execute([$user['id'], $user['id'], $user['id']]);
        }
        
        $tasks = $stmt->fetchAll();
        
        sendSuccess(['tasks' => $tasks], 'Tasks retrieved');
        
    } catch (Exception $e) {
        sendError('Failed to retrieve tasks', 500);
    }
}

/**
 * Get single task details
 */
function handleGetTask($taskId) {
    global $user;
    
    try {
        $pdo = getDatabase();
        
        $stmt = $pdo->prepare('
            SELECT t.*, u.name as assigned_to_name, admin.name as created_by_name
            FROM tasks t
            LEFT JOIN users u ON t.assigned_to = u.id
            LEFT JOIN users admin ON t.created_by = admin.id
            WHERE t.id = ?
        ');
        $stmt->execute([$taskId]);
        
        $task = $stmt->fetch();
        
        if (!$task) {
            sendError('Task not found', 404);
        }
        
        // Check permission
        if ($user['role'] !== 'admin' && $task['assigned_to'] !== $user['id']) {
            sendError('Unauthorized', 403);
        }
        
        sendSuccess(['task' => $task]);
        
    } catch (Exception $e) {
        sendError('Failed to retrieve task', 500);
    }
}

/**
 * Update task (Admin only)
 */
function handleUpdateTask($taskId) {
    global $user;
    
    if ($user['role'] !== 'admin') {
        sendError('Only admins can update tasks', 403);
    }
    
    $input = getJsonInput();
    
    try {
        $pdo = getDatabase();
        
        // Check task exists
        $check = $pdo->prepare('SELECT id FROM tasks WHERE id = ?');
        $check->execute([$taskId]);
        
        if (!$check->fetch()) {
            sendError('Task not found', 404);
        }
        
        // Build update query
        $updates = [];
        $params = [];
        
        $allowedFields = ['title', 'description', 'deadline', 'due_time', 'reminder_type', 'custom_reminder_time', 'priority', 'status'];
        
        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $updates[] = "$field = ?";
                $params[] = $input[$field];
            }
        }
        
        if (empty($updates)) {
            sendError('No fields to update', 400);
        }
        
        $updates[] = "updated_at = NOW()";
        $params[] = $taskId;
        
        $stmt = $pdo->prepare('UPDATE tasks SET ' . implode(', ', $updates) . ' WHERE id = ?');
        $stmt->execute($params);
        
        logAction('Task updated', $user['id'], ['task_id' => $taskId]);
        
        sendSuccess([], 'Task updated successfully');
        
    } catch (Exception $e) {
        sendError('Failed to update task', 500);
    }
}

/**
 * Delete task (Admin only)
 */
function handleDeleteTask($taskId) {
    global $user;
    
    if ($user['role'] !== 'admin') {
        sendError('Only admins can delete tasks', 403);
    }
    
    try {
        $pdo = getDatabase();
        
        $stmt = $pdo->prepare('DELETE FROM tasks WHERE id = ?');
        $stmt->execute([$taskId]);
        
        logAction('Task deleted', $user['id'], ['task_id' => $taskId]);
        
        sendSuccess([], 'Task deleted successfully');
        
    } catch (Exception $e) {
        sendError('Failed to delete task', 500);
    }
}

/**
 * Assign task to employee
 */
function handleAssignTask($taskId) {
    global $user;
    
    if ($user['role'] !== 'admin') {
        sendError('Only admins can assign tasks', 403);
    }
    
    $input = getJsonInput();
    
    if (!isset($input['assigned_to'])) {
        sendError('Employee ID required', 400);
    }
    
    try {
        $pdo = getDatabase();
        
        $stmt = $pdo->prepare('UPDATE tasks SET assigned_to = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$input['assigned_to'], $taskId]);
        
        logAction('Task reassigned', $user['id'], ['task_id' => $taskId]);
        
        sendSuccess([], 'Task assigned successfully');
        
    } catch (Exception $e) {
        sendError('Failed to assign task', 500);
    }
}

/**
 * Get tasks assigned to current user
 */
function handleGetMyTasks($user) {
    try {
        $pdo = getDatabase();
        
        if ($user['role'] === 'admin') {
            // Get tasks created by admin
            $stmt = $pdo->prepare('
                SELECT t.*, u.name as assigned_to_name,
                       (SELECT COUNT(*) FROM submissions WHERE task_id = t.id) as submission_count
                FROM tasks t
                LEFT JOIN users u ON t.assigned_to = u.id
                WHERE t.created_by = ?
                ORDER BY t.deadline ASC
            ');
            $stmt->execute([$user['id']]);
        } else {
            // Get tasks assigned to employee
            $stmt = $pdo->prepare('
                SELECT t.*,
                       (SELECT submission_text FROM submissions WHERE task_id = t.id AND employee_id = ? LIMIT 1) as submission_text,
                       (SELECT status FROM submissions WHERE task_id = t.id AND employee_id = ? LIMIT 1) as submission_status,
                       (SELECT admin_comment FROM submissions WHERE task_id = t.id AND employee_id = ? LIMIT 1) as admin_comment
                FROM tasks t
                WHERE t.assigned_to = ?
                ORDER BY t.deadline ASC
            ');
            $stmt->execute([$user['id'], $user['id'], $user['id'], $user['id']]);
        }
        
        $tasks = $stmt->fetchAll();
        
        sendSuccess(['tasks' => $tasks]);
        
    } catch (Exception $e) {
        sendError('Failed to retrieve tasks', 500);
    }
}

/**
 * Get task analytics (Admin only)
 */
function handleTaskAnalytics($user) {
    if ($user['role'] !== 'admin') {
        sendError('Only admins can view analytics', 403);
    }
    
    try {
        $pdo = getDatabase();
        
        // Total tasks
        $totalStmt = $pdo->prepare('SELECT COUNT(*) as count FROM tasks WHERE created_by = ?');
        $totalStmt->execute([$user['id']]);
        $total = $totalStmt->fetch()['count'];
        
        // Completed tasks
        $completedStmt = $pdo->prepare('SELECT COUNT(*) as count FROM tasks WHERE created_by = ? AND status = "completed"');
        $completedStmt->execute([$user['id']]);
        $completed = $completedStmt->fetch()['count'];
        
        // Pending tasks
        $pendingStmt = $pdo->prepare('SELECT COUNT(*) as count FROM tasks WHERE created_by = ? AND status = "pending"');
        $pendingStmt->execute([$user['id']]);
        $pending = $pendingStmt->fetch()['count'];
        
        // Total employees
        $employeesStmt = $pdo->prepare('SELECT COUNT(*) as count FROM users WHERE role = "employee"');
        $employeesStmt->execute();
        $totalEmployees = $employeesStmt->fetch()['count'];
        
        // Total submissions
        $submissionsStmt = $pdo->prepare('SELECT COUNT(*) as count FROM submissions');
        $submissionsStmt->execute();
        $totalSubmissions = $submissionsStmt->fetch()['count'];
        
        sendSuccess([
            'analytics' => [
                'total_tasks' => $total,
                'completed_tasks' => $completed,
                'pending_tasks' => $pending,
                'total_employees' => $totalEmployees,
                'total_submissions' => $totalSubmissions,
                'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 0
            ]
        ]);
        
    } catch (Exception $e) {
        sendError('Failed to retrieve analytics', 500);
    }
}

?>
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
    
    // Log the input for debugging
    error_log('Create task input: ' . json_encode($input));
    error_log('User ID: ' . $userId);
    
    if (!isset($input['title'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Title is required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO tasks (user_id, title, description, category, priority, status, due_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        // Normalize due_date to SQL datetime string if provided
        $due_date = null;
        if (isset($input['due_date']) && !empty($input['due_date'])) {
            try {
                $dt = new DateTime($input['due_date']);
                $due_date = $dt->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                $due_date = null;
            }
        }

        $stmt->execute([
            $userId,
            $input['title'],
            $input['description'] ?? null,
            $input['category'] ?? null,
            $input['priority'] ?? 'medium',
            $input['status'] ?? 'pending',
            $due_date
        ]);
        
        $taskId = $pdo->lastInsertId();
        
        // Fetch the newly created task to return complete data
        $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ? AND user_id = ?");
        $stmt->execute([$taskId, $userId]);
        $task = $stmt->fetch();
        
        http_response_code(201);
        echo json_encode([
            'message' => 'Task created successfully',
            'task_id' => $taskId,
            'task' => $task
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
                // Normalize due_date if present
                if ($field === 'due_date' && !empty($input[$field])) {
                    try {
                        $dt = new DateTime($input[$field]);
                        $values[] = $dt->format('Y-m-d H:i:s');
                    } catch (Exception $e) {
                        $values[] = null;
                    }
                } else {
                    $values[] = $input[$field];
                }
                $updates[] = "$field = ?";
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

