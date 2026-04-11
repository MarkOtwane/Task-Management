<?php
/**
 * Task Comments API
 * Lightweight comment thread for tasks
 */

require_once '../config/cors.php';
require_once '../config/database.php';
require_once '../middleware/auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$user = getCurrentUser($pdo);

if ($method === 'GET') {
    getTaskComments($pdo, $user);
} elseif ($method === 'POST') {
    createTaskComment($pdo, $user);
} elseif ($method === 'DELETE') {
    deleteTaskComment($pdo, $user);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

function parseJsonInput() {
    $input = json_decode(file_get_contents('php://input'), true);
    return is_array($input) ? $input : [];
}

function canUserViewTask($pdo, $user, $taskId) {
    $stmt = $pdo->prepare("SELECT organization_id, user_id, assigned_to FROM tasks WHERE id = ?");
    $stmt->execute([$taskId]);
    $task = $stmt->fetch();

    if (!$task) {
        return false;
    }

    if ($task['organization_id']) {
        $orgMember = $pdo->prepare("SELECT role FROM organization_members WHERE user_id = ? AND organization_id = ?");
        $orgMember->execute([$user['id'], $task['organization_id']]);
        $member = $orgMember->fetch();

        if ($member && $member['role'] === 'client') {
            return false;
        }

        if (strtolower($user['email']) === 'autonemac003@gmail.com') {
            return true;
        }

        if ($member && $member['role'] === 'organization_admin') {
            return true;
        }

        return (int) $task['assigned_to'] === (int) $user['id'];
    }

    return (int) $task['user_id'] === (int) $user['id'];
}

function canUserManageTask($pdo, $user, $taskId) {
    $stmt = $pdo->prepare("SELECT organization_id, user_id, assigned_to FROM tasks WHERE id = ?");
    $stmt->execute([$taskId]);
    $task = $stmt->fetch();

    if (!$task) {
        return false;
    }

    if ($task['organization_id']) {
        $orgMember = $pdo->prepare("SELECT role FROM organization_members WHERE user_id = ? AND organization_id = ?");
        $orgMember->execute([$user['id'], $task['organization_id']]);
        $member = $orgMember->fetch();

        if (strtolower($user['email']) === 'autonemac003@gmail.com') {
            return true;
        }

        return $member && $member['role'] === 'organization_admin';
    }

    return (int) $task['user_id'] === (int) $user['id'];
}

function getTaskComments($pdo, $user) {
    $taskId = !empty($_GET['task_id']) ? (int) $_GET['task_id'] : null;

    if (!$taskId) {
        http_response_code(400);
        echo json_encode(['error' => 'Task ID is required']);
        return;
    }

    if (!canUserViewTask($pdo, $user, $taskId)) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT tc.*, u.username, u.email
            FROM task_comments tc
            LEFT JOIN users u ON u.id = tc.user_id
            WHERE tc.task_id = ?
            ORDER BY tc.created_at ASC
        ");
        $stmt->execute([$taskId]);
        $comments = $stmt->fetchAll();

        echo json_encode($comments);
    } catch (PDOException $exception) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch comments: ' . $exception->getMessage()]);
    }
}

function createTaskComment($pdo, $user) {
    $input = parseJsonInput();
    $taskId = !empty($input['task_id']) ? (int) $input['task_id'] : null;

    if (!$taskId) {
        http_response_code(400);
        echo json_encode(['error' => 'Task ID is required']);
        return;
    }

    if (!canUserViewTask($pdo, $user, $taskId)) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }

    if (empty($input['message'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Message is required']);
        return;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO task_comments (task_id, user_id, message, created_at)
            VALUES (?, ?, ?, CURRENT_TIMESTAMP)
            RETURNING id
        ");
        $stmt->execute([$taskId, $user['id'], $input['message']]);

        $commentId = $stmt->fetchColumn();

        http_response_code(201);
        echo json_encode([
            'message' => 'Comment added successfully',
            'comment_id' => $commentId
        ]);
    } catch (PDOException $exception) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to add comment: ' . $exception->getMessage()]);
    }
}

function deleteTaskComment($pdo, $user) {
    $commentId = !empty($_GET['id']) ? (int) $_GET['id'] : null;

    if (!$commentId) {
        http_response_code(400);
        echo json_encode(['error' => 'Comment ID is required']);
        return;
    }

    try {
        $stmt = $pdo->prepare("SELECT tc.*, t.organization_id, t.user_id task_owner FROM task_comments tc JOIN tasks t ON t.id = tc.task_id WHERE tc.id = ?");
        $stmt->execute([$commentId]);
        $comment = $stmt->fetch();

        if (!$comment) {
            http_response_code(404);
            echo json_encode(['error' => 'Comment not found']);
            return;
        }

        $isOwner = (int) $comment['user_id'] === (int) $user['id'];
        $isAdmin = strtolower($user['email']) === 'autonemac003@gmail.com';

        if ($comment['organization_id']) {
            $orgMember = $pdo->prepare("SELECT role FROM organization_members WHERE user_id = ? AND organization_id = ?");
            $orgMember->execute([$user['id'], $comment['organization_id']]);
            $member = $orgMember->fetch();
            if ($member && $member['role'] === 'organization_admin') {
                $isAdmin = true;
            }
        }

        if (!$isOwner && !$isAdmin) {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized to delete this comment']);
            return;
        }

        $stmt = $pdo->prepare("DELETE FROM task_comments WHERE id = ?");
        $stmt->execute([$commentId]);

        echo json_encode(['message' => 'Comment deleted successfully']);
    } catch (PDOException $exception) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete comment: ' . $exception->getMessage()]);
    }
}