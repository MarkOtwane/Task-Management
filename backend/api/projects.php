<?php
/**
 * Projects API
 * Handles project CRUD operations for the admin dashboard.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';
$projectId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$user = getCurrentUser();
if (!$user) {
    sendError('Not authenticated', 401);
}

switch ($action) {
    case 'create':
        if ($method === 'POST') {
            handleCreateProject();
        }
        break;

    case 'list':
        if ($method === 'GET') {
            handleListProjects();
        }
        break;

    case 'get':
        if ($method === 'GET' && $projectId) {
            handleGetProject($projectId);
        }
        break;

    case 'update':
        if ($method === 'POST' && $projectId) {
            handleUpdateProject($projectId);
        }
        break;

    case 'delete':
        if ($method === 'DELETE' && $projectId) {
            handleDeleteProject($projectId);
        }
        break;

    default:
        sendError('Invalid project endpoint', 400);
}

function requireAdminUser() {
    global $user;

    if ($user['role'] !== 'admin') {
        sendError('Only admins can manage projects', 403);
    }
}

function validateProjectOwner($ownerId) {
    if ($ownerId === null || $ownerId === 0) {
        return null;
    }

    $pdo = getDatabase();
    $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ?');
    $stmt->execute([$ownerId]);

    if (!$stmt->fetch()) {
        sendError('Invalid project owner selected', 400);
    }

    return $ownerId;
}

function handleListProjects() {
    requireAdminUser();

    try {
        $pdo = getDatabase();
        $stmt = $pdo->prepare('
            SELECT
                p.*,
                owner.name AS owner_name,
                owner.email AS owner_email,
                admin.name AS created_by_name
            FROM projects p
            LEFT JOIN users owner ON p.owner_id = owner.id
            INNER JOIN users admin ON p.created_by = admin.id
            ORDER BY
                CASE p.status
                    WHEN "active" THEN 1
                    WHEN "planning" THEN 2
                    WHEN "on_hold" THEN 3
                    WHEN "completed" THEN 4
                    ELSE 5
                END,
                p.end_date IS NULL,
                p.end_date ASC,
                p.created_at DESC
        ');
        $stmt->execute();

        sendSuccess(['projects' => $stmt->fetchAll()]);
    } catch (Exception $e) {
        sendError('Failed to retrieve projects', 500);
    }
}

function handleGetProject($projectId) {
    requireAdminUser();

    try {
        $pdo = getDatabase();
        $stmt = $pdo->prepare('
            SELECT
                p.*,
                owner.name AS owner_name,
                owner.email AS owner_email,
                admin.name AS created_by_name
            FROM projects p
            LEFT JOIN users owner ON p.owner_id = owner.id
            INNER JOIN users admin ON p.created_by = admin.id
            WHERE p.id = ?
        ');
        $stmt->execute([$projectId]);

        $project = $stmt->fetch();
        if (!$project) {
            sendError('Project not found', 404);
        }

        sendSuccess(['project' => $project]);
    } catch (Exception $e) {
        sendError('Failed to retrieve project', 500);
    }
}

function handleCreateProject() {
    global $user;

    requireAdminUser();

    $input = getJsonInput();
    $errors = validateRequired($input, ['title']);
    if (!empty($errors)) {
        sendError('Validation failed', 400, $errors);
    }

    try {
        $pdo = getDatabase();
        $ownerId = validateProjectOwner(isset($input['owner_id']) ? (int) $input['owner_id'] : null);

        $stmt = $pdo->prepare('
            INSERT INTO projects (
                title, description, client_name, owner_id, status, priority, start_date, end_date, budget, created_by, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ');
        $stmt->execute([
            sanitizeString($input['title']),
            isset($input['description']) ? sanitizeString($input['description']) : null,
            isset($input['client_name']) ? sanitizeString($input['client_name']) : null,
            $ownerId,
            $input['status'] ?? 'planning',
            $input['priority'] ?? 'medium',
            $input['start_date'] ?? null,
            $input['end_date'] ?? null,
            isset($input['budget']) && $input['budget'] !== '' ? $input['budget'] : null,
            $user['id'],
        ]);

        $projectId = $pdo->lastInsertId();
        logAction('Project created', $user['id'], ['project_id' => $projectId, 'title' => $input['title']]);
        sendSuccess(['project_id' => $projectId], 'Project created successfully');
    } catch (Exception $e) {
        sendError('Failed to create project', 500, APP_DEBUG ? $e->getMessage() : null);
    }
}

function handleUpdateProject($projectId) {
    global $user;

    requireAdminUser();

    $input = getJsonInput();

    try {
        $pdo = getDatabase();
        $check = $pdo->prepare('SELECT id FROM projects WHERE id = ?');
        $check->execute([$projectId]);
        if (!$check->fetch()) {
            sendError('Project not found', 404);
        }

        $updates = [];
        $params = [];
        $allowedFields = ['title', 'description', 'client_name', 'status', 'priority', 'start_date', 'end_date', 'budget'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $input)) {
                $updates[] = "$field = ?";
                if (in_array($field, ['title', 'description', 'client_name'], true) && $input[$field] !== null) {
                    $params[] = sanitizeString($input[$field]);
                } else {
                    $params[] = $input[$field] === '' ? null : $input[$field];
                }
            }
        }

        if (array_key_exists('owner_id', $input)) {
            $updates[] = 'owner_id = ?';
            $params[] = validateProjectOwner($input['owner_id'] !== null && $input['owner_id'] !== '' ? (int) $input['owner_id'] : null);
        }

        if (empty($updates)) {
            sendError('No fields to update', 400);
        }

        $updates[] = 'updated_at = NOW()';
        $params[] = $projectId;

        $stmt = $pdo->prepare('UPDATE projects SET ' . implode(', ', $updates) . ' WHERE id = ?');
        $stmt->execute($params);

        logAction('Project updated', $user['id'], ['project_id' => $projectId]);
        sendSuccess([], 'Project updated successfully');
    } catch (Exception $e) {
        sendError('Failed to update project', 500, APP_DEBUG ? $e->getMessage() : null);
    }
}

function handleDeleteProject($projectId) {
    global $user;

    requireAdminUser();

    try {
        $pdo = getDatabase();
        $stmt = $pdo->prepare('DELETE FROM projects WHERE id = ?');
        $stmt->execute([$projectId]);

        logAction('Project deleted', $user['id'], ['project_id' => $projectId]);
        sendSuccess([], 'Project deleted successfully');
    } catch (Exception $e) {
        sendError('Failed to delete project', 500);
    }
}

?>