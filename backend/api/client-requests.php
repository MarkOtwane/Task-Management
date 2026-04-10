<?php
/**
 * Client Requests API
 */

require_once '../config/cors.php';
require_once '../config/database.php';
require_once '../middleware/auth.php';
require_once __DIR__ . '/activity_helpers.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;
$user = getCurrentUser($pdo);

function createRequestNotification($pdo, $userId, $message, $organizationId, $requestId) {
	if (!$userId || !$message) {
		return;
	}

	$stmt = $pdo->prepare("\n		INSERT INTO notifications (user_id, organization_id, task_id, message, entity_type, entity_id, is_read, created_at)\n		VALUES (?, ?, NULL, ?, 'client_request', ?, FALSE, CURRENT_TIMESTAMP)\n	");
	$stmt->execute([(int) $userId, (int) $organizationId, $message, (int) $requestId]);
}

if ($method === 'GET') {
	getClientRequests($pdo, $user);
} elseif ($method === 'POST') {
	createClientRequest($pdo, $user);
} elseif ($method === 'PUT') {
	updateClientRequestStatus($pdo, $user, $action);
} else {
	http_response_code(405);
	echo json_encode(['error' => 'Method not allowed']);
}

function getClientRequests($pdo, $user) {
	$organizationId = !empty($_GET['organization_id']) ? (int) $_GET['organization_id'] : null;
	$limit = !empty($_GET['limit']) ? max(1, min((int) $_GET['limit'], 100)) : 50;

	try {
		if ($organizationId) {
			if (!userCanAccessOrganization($pdo, $user, $organizationId)) {
				http_response_code(403);
				echo json_encode(['error' => 'Unauthorized']);
				return;
			}

			$membership = getOrganizationMembership($pdo, $user['id'], $organizationId);
			if (!$membership || !in_array($membership['role'], ['organization_admin', 'member', 'client'], true)) {
				http_response_code(403);
				echo json_encode(['error' => 'Unauthorized']);
				return;
			}

			if ($membership['role'] === 'client' && !userIsOrganizationAdmin($pdo, $user, $organizationId)) {
				$stmt = $pdo->prepare("\n					SELECT cr.*, u.username AS client_name, u.email AS client_email\n					FROM client_requests cr\n					LEFT JOIN users u ON u.id = cr.client_id\n					WHERE cr.organization_id = ? AND cr.client_id = ?\n					ORDER BY cr.created_at DESC\n					LIMIT ?\n				");
				$stmt->execute([$organizationId, (int) $user['id'], $limit]);
			} else {
				$stmt = $pdo->prepare("\n					SELECT cr.*, u.username AS client_name, u.email AS client_email\n					FROM client_requests cr\n					LEFT JOIN users u ON u.id = cr.client_id\n					WHERE cr.organization_id = ?\n					ORDER BY cr.created_at DESC\n					LIMIT ?\n				");
				$stmt->execute([$organizationId, $limit]);
			}
		} else {
			$stmt = $pdo->prepare("\n				SELECT cr.*, u.username AS client_name, u.email AS client_email\n				FROM client_requests cr\n				LEFT JOIN users u ON u.id = cr.client_id\n				WHERE cr.client_id = ?\n				ORDER BY cr.created_at DESC\n				LIMIT ?\n			");
			$stmt->execute([(int) $user['id'], $limit]);
		}

		echo json_encode(['requests' => $stmt->fetchAll()]);
	} catch (PDOException $exception) {
		http_response_code(500);
		echo json_encode(['error' => 'Failed to fetch requests: ' . $exception->getMessage()]);
	}
}

function createClientRequest($pdo, $user) {
	$input = json_decode(file_get_contents('php://input'), true);
	$input = is_array($input) ? $input : [];
	$organizationId = !empty($input['organization_id']) ? (int) $input['organization_id'] : 0;
	$title = trim((string) ($input['title'] ?? ''));
	$description = trim((string) ($input['description'] ?? ''));

	if ($organizationId <= 0 || $title === '') {
		http_response_code(400);
		echo json_encode(['error' => 'Organization ID and title are required']);
		return;
	}

	$membership = getOrganizationMembership($pdo, $user['id'], $organizationId);
	if (!$membership || $membership['role'] !== 'client') {
		http_response_code(403);
		echo json_encode(['error' => 'Only client members can submit requests']);
		return;
	}

	try {
		$stmt = $pdo->prepare("\n			INSERT INTO client_requests (client_id, organization_id, title, description, status, created_at, updated_at)\n			VALUES (?, ?, ?, ?, 'pending', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)\n		");
		$stmt->execute([(int) $user['id'], $organizationId, $title, $description !== '' ? $description : null]);
		$requestId = (int) $pdo->lastInsertId();

		$adminStmt = $pdo->prepare("SELECT user_id FROM organization_members WHERE organization_id = ? AND role = 'organization_admin'");
		$adminStmt->execute([$organizationId]);
		$adminIds = array_map(static fn($row) => (int) $row['user_id'], $adminStmt->fetchAll());

		$requestMessage = 'New request from client';
		foreach ($adminIds as $adminId) {
			createRequestNotification($pdo, $adminId, $requestMessage, $organizationId, $requestId);
		}

		recordWorkspaceActivity($pdo, $adminIds, $organizationId, 'client_request_created', $requestMessage, null, [
			'request_id' => $requestId,
			'client_id' => (int) $user['id'],
		]);

		echo json_encode([
			'message' => 'Request submitted successfully',
			'request_id' => $requestId,
		]);
	} catch (PDOException $exception) {
		http_response_code(500);
		echo json_encode(['error' => 'Failed to create request: ' . $exception->getMessage()]);
	}
}

function updateClientRequestStatus($pdo, $user, $action) {
	$input = json_decode(file_get_contents('php://input'), true);
	$input = is_array($input) ? $input : [];
	$requestId = !empty($input['request_id']) ? (int) $input['request_id'] : 0;
	$organizationId = !empty($input['organization_id']) ? (int) $input['organization_id'] : 0;
	$assignedTo = !empty($input['assigned_to']) ? $input['assigned_to'] : null;
	$taskTitle = trim((string) ($input['task_title'] ?? ''));
	$taskDescription = trim((string) ($input['task_description'] ?? ''));

	if ($requestId <= 0 || $organizationId <= 0) {
		http_response_code(400);
		echo json_encode(['error' => 'Request ID and organization ID are required']);
		return;
	}

	if (!userCanManageOrganizationTasks($pdo, $user, $organizationId)) {
		http_response_code(403);
		echo json_encode(['error' => 'Only organization admins can review requests']);
		return;
	}

	$stmt = $pdo->prepare("SELECT * FROM client_requests WHERE id = ? AND organization_id = ?");
	$stmt->execute([$requestId, $organizationId]);
	$request = $stmt->fetch();
	if (!$request) {
		http_response_code(404);
		echo json_encode(['error' => 'Request not found']);
		return;
	}

	if (!in_array($action, ['accept', 'reject'], true)) {
		http_response_code(400);
		echo json_encode(['error' => 'Invalid request action']);
		return;
	}

	try {
		$taskIds = [];
		if ($action === 'accept') {
			$assignees = is_array($assignedTo) ? $assignedTo : ($assignedTo ? [$assignedTo] : []);
			$assignees = array_map('intval', $assignees);
			$assignees = array_values(array_filter(array_unique($assignees)));
			if (empty($assignees)) {
				$assignees = [null];
			}

			foreach ($assignees as $assigneeId) {
				if ($assigneeId) {
					$membership = getOrganizationMembership($pdo, $assigneeId, $organizationId);
					if (!$membership || $membership['role'] !== 'member') {
						http_response_code(400);
						echo json_encode(['error' => 'Assigned users must be organization members']);
						return;
					}
				}

				$createTaskStmt = $pdo->prepare("\n					INSERT INTO tasks (user_id, organization_id, assigned_to, assigned_by, request_id, title, description, category, priority, status, meet_link, due_date)\n					VALUES (?, ?, ?, ?, ?, ?, ?, 'general', 'medium', 'pending', NULL, NULL)\n				");
				$createTaskStmt->execute([
					(int) $user['id'],
					$organizationId,
					$assigneeId ?: null,
					(int) $user['id'],
					$requestId,
					$taskTitle !== '' ? $taskTitle : $request['title'],
					$taskDescription !== '' ? $taskDescription : $request['description'],
				]);
				$taskIds[] = (int) $pdo->lastInsertId();
			}
			$updateStmt = $pdo->prepare("UPDATE client_requests SET status = 'accepted', updated_at = CURRENT_TIMESTAMP WHERE id = ? AND organization_id = ?");
			$updateStmt->execute([$requestId, $organizationId]);
		} else {
			$updateStmt = $pdo->prepare("UPDATE client_requests SET status = 'rejected', updated_at = CURRENT_TIMESTAMP WHERE id = ? AND organization_id = ?");
			$updateStmt->execute([$requestId, $organizationId]);
		}

		$clientMessage = $action === 'accept' ? 'Your request was accepted' : 'Your request was rejected';
		createRequestNotification($pdo, (int) $request['client_id'], $clientMessage, $organizationId, $requestId);
		recordWorkspaceActivity($pdo, [(int) $request['client_id'], (int) $user['id']], $organizationId, 'client_request_' . $action, $clientMessage, $taskIds[0] ?? null, [
			'request_id' => $requestId,
			'task_ids' => $taskIds,
		]);

		echo json_encode([
			'message' => 'Request updated successfully',
			'request_id' => $requestId,
			'status' => $action === 'accept' ? 'accepted' : 'rejected',
			'task_ids' => $taskIds,
		]);
	} catch (PDOException $exception) {
		http_response_code(500);
		echo json_encode(['error' => 'Failed to update request: ' . $exception->getMessage()]);
	}
}