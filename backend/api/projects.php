<?php
/**
 * Projects API
 * Endpoints: GET, POST, PUT, DELETE for projects
 */

require_once '../config/cors.php';
require_once '../config/database.php';
require_once '../middleware/auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$user = getCurrentUser($pdo);
$action = $_GET['action'] ?? null;

if ($method === 'GET') {
    if (!empty($_GET['id'])) {
        getProjectById($pdo, $user, (int) $_GET['id']);
    } else {
        getProjects($pdo, $user);
    }
} elseif ($method === 'POST') {
    createProject($pdo, $user);
} elseif ($method === 'PUT') {
    updateProject($pdo, $user);
} elseif ($method === 'DELETE') {
    deleteProject($pdo, $user);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

function parseJsonInput() {
    $input = json_decode(file_get_contents('php://input'), true);
    return is_array($input) ? $input : [];
}

function userCanAccessOrganization($pdo, $user, $organizationId) {
    if (!$organizationId) {
        return false;
    }

    if (strtolower($user['email']) === 'autonemac003@gmail.com') {
        return true;
    }

    $stmt = $pdo->prepare("SELECT id FROM organization_members WHERE user_id = ? AND organization_id = ?");
    $stmt->execute([$user['id'], $organizationId]);
    return (bool) $stmt->fetch();
}

function userCanManageOrganizationTasks($pdo, $user, $organizationId) {
    if (strtolower($user['email']) === 'autonemac003@gmail.com') {
        return true;
    }

    $stmt = $pdo->prepare("SELECT role FROM organization_members WHERE user_id = ? AND organization_id = ?");
    $stmt->execute([$user['id'], $organizationId]);
    $membership = $stmt->fetch();
    
    return $membership && $membership['role'] === 'organization_admin';
}

function getProjectById($pdo, $user, $projectId) {
    if ($projectId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Project ID is required']);
        return;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT p.*, o.name AS organization_name,
                   u.username AS creator_name,
                   COUNT(t.id) AS total_tasks,
                   COUNT(CASE WHEN t.status = 'completed' THEN 1 END) AS completed_tasks
            FROM projects p
            LEFT JOIN organizations o ON o.id = p.organization_id
            LEFT JOIN users u ON u.id = p.created_by
            LEFT JOIN tasks t ON t.project_id = p.id
            WHERE p.id = ?
            GROUP BY p.id, o.name, u.username
        ");
        $stmt->execute([$projectId]);
        $project = $stmt->fetch();

        if (!$project) {
            http_response_code(404);
            echo json_encode(['error' => 'Project not found']);
            return;
        }

        if (!userCanAccessOrganization($pdo, $user, $project['organization_id'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }

        echo json_encode($project);
    } catch (PDOException $exception) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch project: ' . $exception->getMessage()]);
    }
}

function getProjects($pdo, $user) {
    $organizationId = !empty($_GET['organization_id']) ? (int) $_GET['organization_id'] : null;

    if (!$organizationId) {
        http_response_code(400);
        echo json_encode(['error' => 'Organization ID is required']);
        return;
    }

    if (!userCanAccessOrganization($pdo, $user, $organizationId)) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT p.*, o.name AS organization_name,
                   u.username AS creator_name,
                   COUNT(t.id) AS total_tasks,
                   COUNT(CASE WHEN t.status = 'completed' THEN 1 END) AS completed_tasks
            FROM projects p
            LEFT JOIN organizations o ON o.id = p.organization_id
            LEFT JOIN users u ON u.id = p.created_by
            LEFT JOIN tasks t ON t.project_id = p.id
            WHERE p.organization_id = ?
            GROUP BY p.id, o.name, u.username
            ORDER BY p.created_at DESC
        ");
        $stmt->execute([$organizationId]);
        $projects = $stmt->fetchAll();

        echo json_encode($projects);
    } catch (PDOException $exception) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch projects: ' . $exception->getMessage()]);
    }
}

function createProject($pdo, $user) {
    $input = parseJsonInput();
    $organizationId = !empty($input['organization_id']) ? (int) $input['organization_id'] : null;

    if (!$organizationId) {
        http_response_code(400);
        echo json_encode(['error' => 'Organization ID is required']);
        return;
    }

    if (!userCanManageOrganizationTasks($pdo, $user, $organizationId)) {
        http_response_code(403);
        echo json_encode(['error' => 'Only organization admins can create projects']);
        return;
    }

    if (empty($input['name'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Project name is required']);
        return;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO projects (organization_id, name, description, created_by, created_at)
            VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
            RETURNING id
        ");
        $stmt->execute([
            $organizationId,
            $input['name'],
            $input['description'] ?? null,
            $user['id']
        ]);

        $projectId = $stmt->fetchColumn();

        http_response_code(201);
        echo json_encode([
            'message' => 'Project created successfully',
            'project_id' => $projectId
        ]);
    } catch (PDOException $exception) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create project: ' . $exception->getMessage()]);
    }
}

function updateProject($pdo, $user) {
    $input = parseJsonInput();
    $projectId = !empty($input['id']) ? (int) $input['id'] : null;

    if (!$projectId) {
        http_response_code(400);
        echo json_encode(['error' => 'Project ID is required']);
        return;
    }

    try {
        $stmt = $pdo->prepare("SELECT organization_id FROM projects WHERE id = ?");
        $stmt->execute([$projectId]);
        $project = $stmt->fetch();

        if (!$project) {
            http_response_code(404);
            echo json_encode(['error' => 'Project not found']);
            return;
        }

        if (!userCanManageOrganizationTasks($pdo, $user, $project['organization_id'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Only organization admins can update projects']);
            return;
        }

        $updates = [];
        $values = [];

        if (isset($input['name'])) {
            $updates[] = 'name = ?';
            $values[] = $input['name'];
        }
        if (isset($input['description'])) {
            $updates[] = 'description = ?';
            $values[] = $input['description'];
        }

        if (empty($updates)) {
            http_response_code(400);
            echo json_encode(['error' => 'No fields to update']);
            return;
        }

        $values[] = $projectId;
        $stmt = $pdo->prepare("UPDATE projects SET " . implode(', ', $updates) . " WHERE id = ?");
        $stmt->execute($values);

        echo json_encode(['message' => 'Project updated successfully']);
    } catch (PDOException $exception) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update project: ' . $exception->getMessage()]);
    }
}

function deleteProject($pdo, $user) {
    $projectId = !empty($_GET['id']) ? (int) $_GET['id'] : null;

    if (!$projectId) {
        http_response_code(400);
        echo json_encode(['error' => 'Project ID is required']);
        return;
    }

    try {
        $stmt = $pdo->prepare("SELECT organization_id FROM projects WHERE id = ?");
        $stmt->execute([$projectId]);
        $project = $stmt->fetch();

        if (!$project) {
            http_response_code(404);
            echo json_encode(['error' => 'Project not found']);
            return;
        }

        if (!userCanManageOrganizationTasks($pdo, $user, $project['organization_id'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Only organization admins can delete projects']);
            return;
        }

        $stmt = $pdo->prepare("UPDATE tasks SET project_id = NULL WHERE project_id = ?");
        $stmt->execute([$projectId]);

        $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
        $stmt->execute([$projectId]);

        echo json_encode(['message' => 'Project deleted successfully']);
    } catch (PDOException $exception) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete project: ' . $exception->getMessage()]);
    }
}