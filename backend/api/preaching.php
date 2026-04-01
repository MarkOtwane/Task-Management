<?php
/**
 * Preaching Notes API
 * Endpoints:
 * - GET /api/preaching.php                    -> list entries for current user
 * - GET /api/preaching.php?id=123             -> single entry for current user
 * - GET /api/preaching.php?search=grace       -> filtered list by title, preacher, or tags
 * - POST /api/preaching.php                   -> create entry (JSON)
 * - PUT /api/preaching.php                    -> update entry (JSON)
 * - DELETE /api/preaching.php?id=123          -> delete entry
 */

require_once '../config/cors.php';
require_once '../config/database.php';
require_once '../middleware/auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$userId = requireAuth();

if ($method === 'GET') {
    getPreachingEntries($pdo, $userId);
} elseif ($method === 'POST') {
    createPreachingEntry($pdo, $userId);
} elseif ($method === 'PUT') {
    updatePreachingEntry($pdo, $userId);
} elseif ($method === 'DELETE') {
    deletePreachingEntry($pdo, $userId);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

function getPreachingEntries($pdo, $userId) {
    $entryId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $searchTerm = trim($_GET['search'] ?? '');
    $tagFilter = trim($_GET['tag'] ?? '');

    try {
        if ($entryId > 0) {
            $stmt = $pdo->prepare(
                'SELECT id, user_id, title, preacher, content, tags, created_at, updated_at
                 FROM preaching_entries
                 WHERE id = ? AND user_id = ?'
            );
            $stmt->execute([$entryId, $userId]);
            $entry = $stmt->fetch();

            if (!$entry) {
                http_response_code(404);
                echo json_encode(['error' => 'Preaching entry not found']);
                return;
            }

            echo json_encode($entry);
            return;
        }

        $sql = 'SELECT id, user_id, title, preacher, content, tags, created_at, updated_at
                FROM preaching_entries
                WHERE user_id = ?';
        $params = [$userId];

        if ($searchTerm !== '') {
            $sql .= " AND (title ILIKE ? OR preacher ILIKE ? OR COALESCE(tags, '') ILIKE ?)";
            $searchLike = '%' . $searchTerm . '%';
            $params[] = $searchLike;
            $params[] = $searchLike;
            $params[] = $searchLike;
        }

        if ($tagFilter !== '') {
            $sql .= " AND COALESCE(tags, '') ILIKE ?";
            $params[] = '%' . $tagFilter . '%';
        }

        $sql .= ' ORDER BY updated_at DESC, created_at DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $entries = $stmt->fetchAll();

        echo json_encode($entries);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch preaching entries: ' . $e->getMessage()]);
    }
}

function createPreachingEntry($pdo, $userId) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid request body']);
        return;
    }

    $title = trim($input['title'] ?? '');
    $preacher = trim($input['preacher'] ?? '');
    $content = trim($input['content'] ?? '');
    $tags = normalizeTags($input['tags'] ?? '');

    if ($title === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Preaching title is required']);
        return;
    }

    if ($preacher === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Preacher name is required']);
        return;
    }

    if ($content === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Preaching content is required']);
        return;
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO preaching_entries (user_id, title, preacher, content, tags, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
        );
        $stmt->execute([$userId, $title, $preacher, $content, $tags]);

        $entryId = $pdo->lastInsertId();
        $entry = fetchPreachingEntry($pdo, $entryId, $userId);

        http_response_code(201);
        echo json_encode([
            'message' => 'Preaching entry saved successfully',
            'entry' => $entry,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save preaching entry: ' . $e->getMessage()]);
    }
}

function updatePreachingEntry($pdo, $userId) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid request body']);
        return;
    }

    $entryId = (int)($input['id'] ?? 0);
    $title = trim($input['title'] ?? '');
    $preacher = trim($input['preacher'] ?? '');
    $content = trim($input['content'] ?? '');
    $tags = normalizeTags($input['tags'] ?? '');

    if ($entryId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Valid preaching entry id is required']);
        return;
    }

    if ($title === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Preaching title is required']);
        return;
    }

    if ($preacher === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Preacher name is required']);
        return;
    }

    if ($content === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Preaching content is required']);
        return;
    }

    try {
        $updateStmt = $pdo->prepare(
            'UPDATE preaching_entries
             SET title = ?, preacher = ?, content = ?, tags = ?, updated_at = CURRENT_TIMESTAMP
             WHERE id = ? AND user_id = ?'
        );
        $updateStmt->execute([$title, $preacher, $content, $tags, $entryId, $userId]);

        if ($updateStmt->rowCount() === 0) {
            $existsStmt = $pdo->prepare('SELECT id FROM preaching_entries WHERE id = ? AND user_id = ?');
            $existsStmt->execute([$entryId, $userId]);
            if (!$existsStmt->fetch()) {
                http_response_code(404);
                echo json_encode(['error' => 'Preaching entry not found']);
                return;
            }
        }

        $entry = fetchPreachingEntry($pdo, $entryId, $userId);

        echo json_encode([
            'message' => 'Preaching entry updated successfully',
            'entry' => $entry,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update preaching entry: ' . $e->getMessage()]);
    }
}

function deletePreachingEntry($pdo, $userId) {
    $entryId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($entryId <= 0) {
        $input = json_decode(file_get_contents('php://input'), true);
        if (is_array($input)) {
            $entryId = (int)($input['id'] ?? 0);
        }
    }

    if ($entryId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Valid preaching entry id is required']);
        return;
    }

    try {
        $entryStmt = $pdo->prepare('SELECT id FROM preaching_entries WHERE id = ? AND user_id = ?');
        $entryStmt->execute([$entryId, $userId]);
        $entry = $entryStmt->fetch();

        if (!$entry) {
            http_response_code(404);
            echo json_encode(['error' => 'Preaching entry not found']);
            return;
        }

        $deleteStmt = $pdo->prepare('DELETE FROM preaching_entries WHERE id = ? AND user_id = ?');
        $deleteStmt->execute([$entryId, $userId]);

        echo json_encode([
            'message' => 'Preaching entry deleted successfully',
            'id' => $entryId,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete preaching entry: ' . $e->getMessage()]);
    }
}

function fetchPreachingEntry($pdo, $entryId, $userId) {
    $fetchStmt = $pdo->prepare(
        'SELECT id, user_id, title, preacher, content, tags, created_at, updated_at
         FROM preaching_entries
         WHERE id = ? AND user_id = ?'
    );
    $fetchStmt->execute([$entryId, $userId]);
    return $fetchStmt->fetch();
}

function normalizeTags($tagsValue) {
    if (is_array($tagsValue)) {
        $tagsValue = implode(',', $tagsValue);
    }

    $parts = array_filter(array_map('trim', explode(',', (string)$tagsValue)));
    if (empty($parts)) {
        return null;
    }

    $uniqueParts = [];
    foreach ($parts as $part) {
        $key = mb_strtolower($part);
        if (!isset($uniqueParts[$key])) {
            $uniqueParts[$key] = $part;
        }
    }

    return implode(', ', array_values($uniqueParts));
}
