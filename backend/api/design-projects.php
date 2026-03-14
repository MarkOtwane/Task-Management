<?php
/**
 * Design Projects API
 * Handles design project CRUD operations and image uploads.
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
            handleCreateDesignProject();
        }
        break;

    case 'list':
        if ($method === 'GET') {
            handleListDesignProjects();
        }
        break;

    case 'get':
        if ($method === 'GET' && $projectId) {
            handleGetDesignProject($projectId);
        }
        break;

    case 'update':
        if ($method === 'POST' && $projectId) {
            handleUpdateDesignProject($projectId);
        }
        break;

    case 'delete':
        if ($method === 'DELETE' && $projectId) {
            handleDeleteDesignProject($projectId);
        }
        break;

    default:
        sendError('Invalid design project endpoint', 400);
}

function requireAdminDesignUser() {
    global $user;

    if ($user['role'] !== 'admin') {
        sendError('Only admins can manage design projects', 403);
    }
}

function getDesignProjectInput() {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        return getJsonInput();
    }

    return $_POST;
}

function getDesignProjectImageFile() {
    if (isset($_FILES['image'])) {
        return $_FILES['image'];
    }

    if (isset($_FILES['preview_image'])) {
        return $_FILES['preview_image'];
    }

    return null;
}

function validateDesigner($designerId) {
    if ($designerId === null || $designerId === 0) {
        return null;
    }

    $pdo = getDatabase();
    $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ? AND role = ?');
    $stmt->execute([$designerId, 'employee']);

    if (!$stmt->fetch()) {
        sendError('Invalid designer selected', 400);
    }

    return $designerId;
}

function handleListDesignProjects() {
    requireAdminDesignUser();

    try {
        $pdo = getDatabase();
        $stmt = $pdo->prepare('
            SELECT
                dp.*,
                designer.name AS designer_name,
                designer.email AS designer_email,
                admin.name AS created_by_name
            FROM design_projects dp
            LEFT JOIN users designer ON dp.designer_id = designer.id
            INNER JOIN users admin ON dp.created_by = admin.id
            ORDER BY
                CASE dp.status
                    WHEN "review" THEN 1
                    WHEN "in_progress" THEN 2
                    WHEN "concept" THEN 3
                    WHEN "approved" THEN 4
                    WHEN "completed" THEN 5
                    ELSE 6
                END,
                dp.due_date IS NULL,
                dp.due_date ASC,
                dp.created_at DESC
        ');
        $stmt->execute();

        sendSuccess(['design_projects' => $stmt->fetchAll()]);
    } catch (Exception $e) {
        sendError('Failed to retrieve design projects', 500);
    }
}

function handleGetDesignProject($projectId) {
    requireAdminDesignUser();

    try {
        $pdo = getDatabase();
        $stmt = $pdo->prepare('
            SELECT
                dp.*,
                designer.name AS designer_name,
                designer.email AS designer_email,
                admin.name AS created_by_name
            FROM design_projects dp
            LEFT JOIN users designer ON dp.designer_id = designer.id
            INNER JOIN users admin ON dp.created_by = admin.id
            WHERE dp.id = ?
        ');
        $stmt->execute([$projectId]);

        $project = $stmt->fetch();
        if (!$project) {
            sendError('Design project not found', 404);
        }

        sendSuccess(['design_project' => $project]);
    } catch (Exception $e) {
        sendError('Failed to retrieve design project', 500);
    }
}

function handleCreateDesignProject() {
    global $user;

    requireAdminDesignUser();

    $input = getDesignProjectInput();
    $errors = validateRequired($input, ['title']);
    if (!empty($errors)) {
        sendError('Validation failed', 400, $errors);
    }

    try {
        $pdo = getDatabase();
        $designerId = validateDesigner(isset($input['designer_id']) ? (int) $input['designer_id'] : null);
        $imagePath = null;

        $imageFile = getDesignProjectImageFile();
        if ($imageFile && !empty($imageFile['tmp_name'])) {
            $upload = saveUploadedFile($imageFile, 'design-projects');
            if (!$upload['success']) {
                sendError($upload['error'], 400);
            }
            $imagePath = $upload['path'];
        }

        $stmt = $pdo->prepare('
            INSERT INTO design_projects (
                title, description, client_name, designer_id, status, due_date, image_path, notes, created_by, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ');
        $stmt->execute([
            sanitizeString($input['title']),
            isset($input['description']) ? sanitizeString($input['description']) : null,
            isset($input['client_name']) ? sanitizeString($input['client_name']) : null,
            $designerId,
            $input['status'] ?? 'concept',
            $input['due_date'] ?? null,
            $imagePath,
            isset($input['notes']) ? sanitizeString($input['notes']) : null,
            $user['id'],
        ]);

        $projectId = $pdo->lastInsertId();
        logAction('Design project created', $user['id'], ['design_project_id' => $projectId, 'title' => $input['title']]);
        sendSuccess(['design_project_id' => $projectId, 'image_path' => $imagePath], 'Design project created successfully');
    } catch (Exception $e) {
        sendError('Failed to create design project', 500, APP_DEBUG ? $e->getMessage() : null);
    }
}

function handleUpdateDesignProject($projectId) {
    global $user;

    requireAdminDesignUser();

    $input = getDesignProjectInput();

    try {
        $pdo = getDatabase();
        $check = $pdo->prepare('SELECT id FROM design_projects WHERE id = ?');
        $check->execute([$projectId]);
        if (!$check->fetch()) {
            sendError('Design project not found', 404);
        }

        $updates = [];
        $params = [];
        $allowedFields = ['title', 'description', 'client_name', 'status', 'due_date', 'notes'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $input)) {
                $updates[] = "$field = ?";
                if (in_array($field, ['title', 'description', 'client_name', 'notes'], true) && $input[$field] !== null) {
                    $params[] = sanitizeString($input[$field]);
                } else {
                    $params[] = $input[$field] === '' ? null : $input[$field];
                }
            }
        }

        if (array_key_exists('designer_id', $input)) {
            $updates[] = 'designer_id = ?';
            $params[] = validateDesigner($input['designer_id'] !== null && $input['designer_id'] !== '' ? (int) $input['designer_id'] : null);
        }

        $imageFile = getDesignProjectImageFile();
        if ($imageFile && !empty($imageFile['tmp_name'])) {
            $upload = saveUploadedFile($imageFile, 'design-projects');
            if (!$upload['success']) {
                sendError($upload['error'], 400);
            }
            $updates[] = 'image_path = ?';
            $params[] = $upload['path'];
        }

        if (empty($updates)) {
            sendError('No fields to update', 400);
        }

        $updates[] = 'updated_at = NOW()';
        $params[] = $projectId;

        $stmt = $pdo->prepare('UPDATE design_projects SET ' . implode(', ', $updates) . ' WHERE id = ?');
        $stmt->execute($params);

        logAction('Design project updated', $user['id'], ['design_project_id' => $projectId]);
        sendSuccess([], 'Design project updated successfully');
    } catch (Exception $e) {
        sendError('Failed to update design project', 500, APP_DEBUG ? $e->getMessage() : null);
    }
}

function handleDeleteDesignProject($projectId) {
    global $user;

    requireAdminDesignUser();

    try {
        $pdo = getDatabase();
        $stmt = $pdo->prepare('DELETE FROM design_projects WHERE id = ?');
        $stmt->execute([$projectId]);

        logAction('Design project deleted', $user['id'], ['design_project_id' => $projectId]);
        sendSuccess([], 'Design project deleted successfully');
    } catch (Exception $e) {
        sendError('Failed to delete design project', 500);
    }
}

?>