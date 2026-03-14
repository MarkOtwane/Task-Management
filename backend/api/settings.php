<?php
/**
 * Settings API
 * Handles admin dashboard settings retrieval and updates.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

$user = getCurrentUser();
if (!$user) {
    sendError('Not authenticated', 401);
}

switch ($action) {
    case 'list':
        if ($method === 'GET') {
            handleListSettings();
        }
        break;

    case 'update':
        if ($method === 'POST') {
            handleUpdateSettings();
        }
        break;

    default:
        sendError('Invalid settings endpoint', 400);
}

function requireAdminSettingsUser() {
    global $user;

    if ($user['role'] !== 'admin') {
        sendError('Only admins can manage settings', 403);
    }
}

function decodeSettingValue($value, $type) {
    switch ($type) {
        case 'boolean':
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        case 'number':
            return strpos((string) $value, '.') !== false ? (float) $value : (int) $value;
        case 'json':
            $decoded = json_decode($value, true);
            return $decoded === null && strtolower(trim((string) $value)) !== 'null' ? $value : $decoded;
        default:
            return $value;
    }
}

function encodeSettingValue($value) {
    if (is_bool($value)) {
        return ['value' => $value ? 'true' : 'false', 'type' => 'boolean'];
    }

    if (is_int($value) || is_float($value)) {
        return ['value' => (string) $value, 'type' => 'number'];
    }

    if (is_array($value) || is_object($value)) {
        return ['value' => json_encode($value), 'type' => 'json'];
    }

    return ['value' => sanitizeString((string) $value), 'type' => 'text'];
}

function handleListSettings() {
    global $user;

    requireAdminSettingsUser();

    try {
        $pdo = getDatabase();
        $stmt = $pdo->prepare('
            SELECT setting_key, setting_value, setting_type, updated_at
            FROM settings
            WHERE admin_id = ?
            ORDER BY setting_key ASC
        ');
        $stmt->execute([$user['id']]);
        $rows = $stmt->fetchAll();

        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = decodeSettingValue($row['setting_value'], $row['setting_type']);
        }

        sendSuccess(['settings' => $settings, 'items' => $rows]);
    } catch (Exception $e) {
        sendError('Failed to retrieve settings', 500);
    }
}

function handleUpdateSettings() {
    global $user;

    requireAdminSettingsUser();

    $input = getJsonInput();
    $settingsPayload = isset($input['settings']) && is_array($input['settings']) ? $input['settings'] : $input;

    if (!is_array($settingsPayload) || empty($settingsPayload)) {
        sendError('No settings provided', 400);
    }

    try {
        $pdo = getDatabase();
        $stmt = $pdo->prepare('
            INSERT INTO settings (admin_id, setting_key, setting_value, setting_type, created_at, updated_at)
            VALUES (?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_type = VALUES(setting_type), updated_at = NOW()
        ');

        $saved = [];
        foreach ($settingsPayload as $key => $value) {
            if (!is_string($key) || trim($key) === '') {
                continue;
            }

            $encoded = encodeSettingValue($value);
            $stmt->execute([
                $user['id'],
                trim($key),
                $encoded['value'],
                $encoded['type'],
            ]);

            $saved[trim($key)] = $value;
        }

        logAction('Settings updated', $user['id'], ['keys' => array_keys($saved)]);
        sendSuccess(['settings' => $saved], 'Settings updated successfully');
    } catch (Exception $e) {
        sendError('Failed to update settings', 500, APP_DEBUG ? $e->getMessage() : null);
    }
}

?>