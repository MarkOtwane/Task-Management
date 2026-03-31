<?php
/**
 * Daily Diary API
 * Endpoints:
 * - GET /api/diary.php           -> list entries for current user
 * - GET /api/diary.php?id=123    -> single entry for current user
 * - POST /api/diary.php          -> create entry (JSON or multipart/form-data)
 * - PUT /api/diary.php           -> update entry (JSON)
 * - DELETE /api/diary.php?id=123 -> delete entry
 */

require_once '../config/cors.php';
require_once '../config/database.php';
require_once '../middleware/auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$userId = requireAuth();

if ($method === 'GET') {
    getDiaryEntries($pdo, $userId);
} elseif ($method === 'POST') {
    createDiaryEntry($pdo, $userId);
} elseif ($method === 'PUT') {
    updateDiaryEntry($pdo, $userId);
} elseif ($method === 'DELETE') {
    deleteDiaryEntry($pdo, $userId);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

function getDiaryEntries($pdo, $userId) {
    $entryId = $_GET['id'] ?? null;

    try {
        if ($entryId) {
            $stmt = $pdo->prepare(
                'SELECT id, user_id, entry_date, title, content, mood, audio_file_path, created_at
                 FROM diary_entries
                 WHERE id = ? AND user_id = ?'
            );
            $stmt->execute([$entryId, $userId]);
            $entry = $stmt->fetch();

            if (!$entry) {
                http_response_code(404);
                echo json_encode(['error' => 'Diary entry not found']);
                return;
            }

            echo json_encode($entry);
            return;
        }

        $stmt = $pdo->prepare(
            'SELECT id, user_id, entry_date, title, content, mood, audio_file_path, created_at
             FROM diary_entries
             WHERE user_id = ?
             ORDER BY entry_date DESC, created_at DESC'
        );
        $stmt->execute([$userId]);
        $entries = $stmt->fetchAll();

        echo json_encode($entries);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch diary entries: ' . $e->getMessage()]);
    }
}

function createDiaryEntry($pdo, $userId) {
    $isMultipart = isset($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== false;

    $title = null;
    $content = null;
    $entryDate = null;
    $mood = null;
    $audioFilePath = null;

    if ($isMultipart) {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $entryDate = trim($_POST['entry_date'] ?? '');
        $mood = trim($_POST['mood'] ?? '');

        if (!empty($_FILES['audio_note']) && $_FILES['audio_note']['error'] !== UPLOAD_ERR_NO_FILE) {
            $audioFilePath = processAudioUpload($_FILES['audio_note'], $userId);
            if ($audioFilePath === false) {
                return;
            }
        }
    } else {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid request body']);
            return;
        }

        $title = trim($input['title'] ?? '');
        $content = trim($input['content'] ?? '');
        $entryDate = trim($input['entry_date'] ?? '');
        $mood = trim($input['mood'] ?? '');
    }

    if ($content === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Diary content is required']);
        return;
    }

    if ($entryDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $entryDate)) {
        $entryDate = date('Y-m-d');
    }

    if ($title === '') {
        $title = date('F j, Y', strtotime($entryDate));
    }

    if ($mood === '') {
        $mood = null;
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO diary_entries (user_id, entry_date, title, content, mood, audio_file_path)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $entryDate, $title, $content, $mood, $audioFilePath]);

        $entryId = $pdo->lastInsertId();

        $fetchStmt = $pdo->prepare(
            'SELECT id, user_id, entry_date, title, content, mood, audio_file_path, created_at
             FROM diary_entries
             WHERE id = ? AND user_id = ?'
        );
        $fetchStmt->execute([$entryId, $userId]);
        $entry = $fetchStmt->fetch();

        http_response_code(201);
        echo json_encode([
            'message' => 'Diary entry saved successfully',
            'entry' => $entry,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save diary entry: ' . $e->getMessage()]);
    }
}

function updateDiaryEntry($pdo, $userId) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid request body']);
        return;
    }

    $entryId = (int)($input['id'] ?? 0);
    $title = trim($input['title'] ?? '');
    $content = trim($input['content'] ?? '');
    $entryDate = trim($input['entry_date'] ?? '');
    $mood = trim($input['mood'] ?? '');

    if ($entryId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Valid diary entry id is required']);
        return;
    }

    if ($content === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Diary content is required']);
        return;
    }

    if ($entryDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $entryDate)) {
        $entryDate = date('Y-m-d');
    }

    if ($title === '') {
        $title = date('F j, Y', strtotime($entryDate));
    }

    if ($mood === '') {
        $mood = null;
    }

    try {
        $updateStmt = $pdo->prepare(
            'UPDATE diary_entries
             SET entry_date = ?, title = ?, content = ?, mood = ?
             WHERE id = ? AND user_id = ?'
        );
        $updateStmt->execute([$entryDate, $title, $content, $mood, $entryId, $userId]);

        if ($updateStmt->rowCount() === 0) {
            $existsStmt = $pdo->prepare('SELECT id FROM diary_entries WHERE id = ? AND user_id = ?');
            $existsStmt->execute([$entryId, $userId]);
            if (!$existsStmt->fetch()) {
                http_response_code(404);
                echo json_encode(['error' => 'Diary entry not found']);
                return;
            }
        }

        $fetchStmt = $pdo->prepare(
            'SELECT id, user_id, entry_date, title, content, mood, audio_file_path, created_at
             FROM diary_entries
             WHERE id = ? AND user_id = ?'
        );
        $fetchStmt->execute([$entryId, $userId]);
        $entry = $fetchStmt->fetch();

        echo json_encode([
            'message' => 'Diary entry updated successfully',
            'entry' => $entry,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update diary entry: ' . $e->getMessage()]);
    }
}

function deleteDiaryEntry($pdo, $userId) {
    $entryId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($entryId <= 0) {
        $input = json_decode(file_get_contents('php://input'), true);
        if (is_array($input)) {
            $entryId = (int)($input['id'] ?? 0);
        }
    }

    if ($entryId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Valid diary entry id is required']);
        return;
    }

    try {
        $entryStmt = $pdo->prepare('SELECT audio_file_path FROM diary_entries WHERE id = ? AND user_id = ?');
        $entryStmt->execute([$entryId, $userId]);
        $entry = $entryStmt->fetch();

        if (!$entry) {
            http_response_code(404);
            echo json_encode(['error' => 'Diary entry not found']);
            return;
        }

        $deleteStmt = $pdo->prepare('DELETE FROM diary_entries WHERE id = ? AND user_id = ?');
        $deleteStmt->execute([$entryId, $userId]);

        $audioPath = $entry['audio_file_path'] ?? null;
        if (!empty($audioPath)) {
            $fullAudioPath = __DIR__ . '/../../' . ltrim($audioPath, '/');
            if (is_file($fullAudioPath)) {
                @unlink($fullAudioPath);
            }
        }

        echo json_encode([
            'message' => 'Diary entry deleted successfully',
            'id' => $entryId,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete diary entry: ' . $e->getMessage()]);
    }
}

function processAudioUpload($audioFile, $userId) {
    if ($audioFile['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'Failed to upload audio file']);
        return false;
    }

    $maxAudioSize = 10 * 1024 * 1024;
    if ($audioFile['size'] > $maxAudioSize) {
        http_response_code(400);
        echo json_encode(['error' => 'Audio file must be 10MB or smaller']);
        return false;
    }

    $allowedMimeToExt = [
        'audio/webm' => 'webm',
        'audio/wav' => 'wav',
        'audio/x-wav' => 'wav',
        'audio/mpeg' => 'mp3',
        'audio/mp4' => 'm4a',
        'audio/ogg' => 'ogg',
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $audioFile['tmp_name']);
    finfo_close($finfo);

    if (!isset($allowedMimeToExt[$mimeType])) {
        http_response_code(400);
        echo json_encode(['error' => 'Unsupported audio format']);
        return false;
    }

    $uploadsDir = __DIR__ . '/../../uploads/diary';
    if (!is_dir($uploadsDir) && !mkdir($uploadsDir, 0755, true)) {
        http_response_code(500);
        echo json_encode(['error' => 'Could not prepare upload directory']);
        return false;
    }

    $extension = $allowedMimeToExt[$mimeType];
    $fileName = 'diary_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $destination = $uploadsDir . '/' . $fileName;

    if (!move_uploaded_file($audioFile['tmp_name'], $destination)) {
        http_response_code(500);
        echo json_encode(['error' => 'Could not store audio file']);
        return false;
    }

    return 'uploads/diary/' . $fileName;
}