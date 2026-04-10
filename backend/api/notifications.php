<?php
/**
 * Notifications API
 * Endpoints:
 * - GET /api/notifications.php?organization_id={id}&limit=20
 * - PUT /api/notifications.php (mark one/all as read)
 * - PATCH /api/notifications.php?action=mark-all-read
 */

require_once '../config/cors.php';
require_once '../config/database.php';
require_once '../middleware/auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$user = getCurrentUser($pdo);
$action = $_GET['action'] ?? null;

if ($method === 'GET') {
	getNotifications($pdo, $user);
} elseif ($method === 'PUT') {
	markNotificationsAsRead($pdo, $user);
} elseif ($method === 'PATCH' && $action === 'mark-all-read') {
	markAllNotificationsAsRead($pdo, $user);
} else {
	http_response_code(405);
	echo json_encode(['error' => 'Method not allowed']);
}

function getNotifications($pdo, $user) {
	$limit = !empty($_GET['limit']) ? (int) $_GET['limit'] : 20;
	$limit = max(1, min($limit, 100));
	$organizationId = !empty($_GET['organization_id']) ? (int) $_GET['organization_id'] : null;

	try {
		if ($organizationId) {
			$stmt = $pdo->prepare("\n				SELECT id, user_id, organization_id, task_id, message, entity_type, entity_id, is_read, created_at\n				FROM notifications\n				WHERE user_id = ? AND organization_id = ?\n				ORDER BY created_at DESC\n				LIMIT ?\n			");
			$stmt->bindValue(1, (int) $user['id'], PDO::PARAM_INT);
			$stmt->bindValue(2, $organizationId, PDO::PARAM_INT);
			$stmt->bindValue(3, $limit, PDO::PARAM_INT);
			$stmt->execute();
		} else {
			$stmt = $pdo->prepare("\n				SELECT id, user_id, organization_id, task_id, message, entity_type, entity_id, is_read, created_at\n				FROM notifications\n				WHERE user_id = ?\n				ORDER BY created_at DESC\n				LIMIT ?\n			");
			$stmt->bindValue(1, (int) $user['id'], PDO::PARAM_INT);
			$stmt->bindValue(2, $limit, PDO::PARAM_INT);
			$stmt->execute();
		}

		$notifications = $stmt->fetchAll();
		$unreadCount = 0;
		foreach ($notifications as $notification) {
			if (empty($notification['is_read'])) {
				$unreadCount++;
			}
		}

		echo json_encode([
			'notifications' => $notifications,
			'unread_count' => $unreadCount,
		]);
	} catch (PDOException $exception) {
		http_response_code(500);
		echo json_encode(['error' => 'Failed to fetch notifications: ' . $exception->getMessage()]);
	}
}

function markAllNotificationsAsRead($pdo, $user) {
	$organizationId = !empty($_GET['organization_id']) ? (int) $_GET['organization_id'] : null;

	try {
		if ($organizationId) {
			$stmt = $pdo->prepare("\n				UPDATE notifications\n				SET is_read = TRUE\n				WHERE user_id = ? AND organization_id = ? AND is_read = FALSE\n			");
			$stmt->execute([(int) $user['id'], $organizationId]);
		} else {
			$stmt = $pdo->prepare("\n				UPDATE notifications\n				SET is_read = TRUE\n				WHERE user_id = ? AND is_read = FALSE\n			");
			$stmt->execute([(int) $user['id']]);
		}

		echo json_encode([
			'success' => true,
			'message' => 'Notifications marked as read',
		]);
	} catch (PDOException $exception) {
		http_response_code(500);
		echo json_encode(['error' => 'Failed to update notifications: ' . $exception->getMessage()]);
	}
}

function markNotificationsAsRead($pdo, $user) {
	$input = json_decode(file_get_contents('php://input'), true);
	$input = is_array($input) ? $input : [];

	$notificationId = !empty($input['notification_id']) ? (int) $input['notification_id'] : null;
	$markAll = !empty($input['mark_all']);
	$organizationId = !empty($input['organization_id']) ? (int) $input['organization_id'] : null;

	try {
		if ($markAll) {
			if ($organizationId) {
				$stmt = $pdo->prepare("\n					UPDATE notifications\n					SET is_read = TRUE\n					WHERE user_id = ? AND organization_id = ? AND is_read = FALSE\n				");
				$stmt->execute([(int) $user['id'], $organizationId]);
			} else {
				$stmt = $pdo->prepare("\n					UPDATE notifications\n					SET is_read = TRUE\n					WHERE user_id = ? AND is_read = FALSE\n				");
				$stmt->execute([(int) $user['id']]);
			}

			echo json_encode(['message' => 'Notifications marked as read']);
			return;
		}

		if (!$notificationId) {
			http_response_code(400);
			echo json_encode(['error' => 'notification_id is required']);
			return;
		}

		$stmt = $pdo->prepare("\n			UPDATE notifications\n			SET is_read = TRUE\n			WHERE id = ? AND user_id = ?\n		");
		$stmt->execute([$notificationId, (int) $user['id']]);

		echo json_encode(['message' => 'Notification marked as read']);
	} catch (PDOException $exception) {
		http_response_code(500);
		echo json_encode(['error' => 'Failed to update notifications: ' . $exception->getMessage()]);
	}
}
