<?php
/**
 * Tasks API
 * Endpoints: GET, POST, PUT, DELETE for tasks
 */

require_once '../config/cors.php';
require_once '../config/database.php';
require_once '../middleware/auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$user = getCurrentUser($pdo);

function logTaskDebug($event, $user, $organizationId = null, $payload = null) {
	error_log('[tasks] ' . $event . ' userId=' . ($user['id'] ?? 'unknown') . ' role=' . ($user['role'] ?? 'unknown') . ' orgId=' . ($organizationId ?? 'null') . ' payload=' . json_encode($payload));
}

logTaskDebug('request', $user, $_GET['organization_id'] ?? null, [
	'method' => $method,
	'query' => $_GET,
]);

if ($method === 'GET') {
	getTasks($pdo, $user);
} elseif ($method === 'POST') {
	createTask($pdo, $user);
} elseif ($method === 'PUT') {
	updateTask($pdo, $user);
} elseif ($method === 'DELETE') {
	deleteTask($pdo, $user);
} else {
	http_response_code(405);
	echo json_encode(['error' => 'Method not allowed']);
}

function normalizeDateTimeValue($value) {
	if (empty($value)) {
		return null;
	}

	try {
		$dt = new DateTime($value);
		return $dt->format('Y-m-d H:i:s');
	} catch (Exception $exception) {
		return null;
	}
}

function parseJsonInput() {
	$input = json_decode(file_get_contents('php://input'), true);
	return is_array($input) ? $input : [];
}

function getTaskRecord($pdo, $taskId) {
	$stmt = $pdo->prepare("
		SELECT t.*, o.name AS organization_name,
		       assignee.username AS assigned_to_name, assignee.email AS assigned_to_email,
		       assigner.username AS assigned_by_name, assigner.email AS assigned_by_email
		FROM tasks t
		LEFT JOIN organizations o ON o.id = t.organization_id
		LEFT JOIN users assignee ON assignee.id = t.assigned_to
		LEFT JOIN users assigner ON assigner.id = t.assigned_by
		WHERE t.id = ?
	");
	$stmt->execute([$taskId]);
	return $stmt->fetch();
}

function userCanAccessTaskOrganization($pdo, $user, $organizationId) {
	if (!$organizationId) {
		return true;
	}

	return userCanAccessOrganization($pdo, $user, $organizationId);
}

function getTasks($pdo, $user) {
	$mode = $_GET['mode'] ?? 'personal';
	$organizationId = !empty($_GET['organization_id']) ? (int) $_GET['organization_id'] : null;

	if ($mode === 'organization' && !$organizationId) {
		logTaskDebug('getTasks.fallback_to_personal_missing_organization', $user, null, [
			'mode' => $mode,
		]);
		$mode = 'personal';
	}

	try {
		logTaskDebug('getTasks.start', $user, $organizationId, [
			'mode' => $mode,
		]);

		if ($mode === 'organization' && $organizationId) {
			if (!userCanAccessTaskOrganization($pdo, $user, $organizationId)) {
				http_response_code(403);
				echo json_encode(['error' => 'Unauthorized to view this organization']);
				return;
			}

			if (userCanManageOrganizationTasks($pdo, $user, $organizationId)) {
				$stmt = $pdo->prepare("
					SELECT t.*, o.name AS organization_name,
					       assignee.username AS assigned_to_name, assignee.email AS assigned_to_email,
					       assigner.username AS assigned_by_name, assigner.email AS assigned_by_email
					FROM tasks t
					LEFT JOIN organizations o ON o.id = t.organization_id
					LEFT JOIN users assignee ON assignee.id = t.assigned_to
					LEFT JOIN users assigner ON assigner.id = t.assigned_by
					WHERE t.organization_id = ?
					ORDER BY t.created_at DESC
				");
				$stmt->execute([$organizationId]);
			} else {
				$stmt = $pdo->prepare("
					SELECT t.*, o.name AS organization_name,
					       assignee.username AS assigned_to_name, assignee.email AS assigned_to_email,
					       assigner.username AS assigned_by_name, assigner.email AS assigned_by_email
					FROM tasks t
					LEFT JOIN organizations o ON o.id = t.organization_id
					LEFT JOIN users assignee ON assignee.id = t.assigned_to
					LEFT JOIN users assigner ON assigner.id = t.assigned_by
					WHERE t.organization_id = ? AND t.assigned_to = ?
					ORDER BY t.created_at DESC
				");
				$stmt->execute([$organizationId, $user['id']]);
			}
		} else {
			$stmt = $pdo->prepare("
				SELECT t.*, o.name AS organization_name,
				       assignee.username AS assigned_to_name, assignee.email AS assigned_to_email,
				       assigner.username AS assigned_by_name, assigner.email AS assigned_by_email
				FROM tasks t
				LEFT JOIN organizations o ON o.id = t.organization_id
				LEFT JOIN users assignee ON assignee.id = t.assigned_to
				LEFT JOIN users assigner ON assigner.id = t.assigned_by
				WHERE t.organization_id IS NULL AND t.user_id = ?
				ORDER BY t.created_at DESC
			");
			$stmt->execute([$user['id']]);
		}

		$tasks = $stmt->fetchAll();
		logTaskDebug('getTasks.success', $user, $organizationId, [
			'mode' => $mode,
			'count' => count($tasks),
		]);
		echo json_encode($tasks);
	} catch (PDOException $exception) {
		logTaskDebug('getTasks.error', $user, $organizationId, [
			'mode' => $mode,
			'error' => $exception->getMessage(),
		]);
		http_response_code(500);
		echo json_encode(['error' => 'Failed to fetch tasks: ' . $exception->getMessage()]);
	}
}

function createTask($pdo, $user) {
	$input = parseJsonInput();
	$requestedOrganizationId = !empty($input['organization_id']) ? (int) $input['organization_id'] : null;

	logTaskDebug('createTask.attempt', $user, $requestedOrganizationId, $input);

	if (empty($input['title'])) {
		http_response_code(400);
		echo json_encode(['error' => 'Title is required']);
		return;
	}

	$organizationId = !empty($input['organization_id']) ? (int) $input['organization_id'] : null;
	$assignedTo = !empty($input['assigned_to']) ? (int) $input['assigned_to'] : null;
	$assignedBy = (int) $user['id'];
	$status = $input['status'] ?? 'pending';
	$dueDate = normalizeDateTimeValue($input['due_date'] ?? null);

	if ($organizationId) {
		if (!userCanManageOrganizationTasks($pdo, $user, $organizationId)) {
			logTaskDebug('createTask.denied_not_org_admin', $user, $organizationId, $input);
			http_response_code(403);
			echo json_encode(['error' => 'Only organization admins can create organization tasks']);
			return;
		}

		if ($assignedTo) {
			$membership = getOrganizationMembership($pdo, $assignedTo, $organizationId);
			if (!$membership) {
				logTaskDebug('createTask.denied_assignee_not_member', $user, $organizationId, [
					'assigned_to' => $assignedTo,
				]);
				http_response_code(400);
				echo json_encode(['error' => 'Assigned user must belong to the organization']);
				return;
			}
		}

		if (!$assignedTo) {
			$creatorMembership = getOrganizationMembership($pdo, $user['id'], $organizationId);
			if (!$creatorMembership) {
				logTaskDebug('createTask.denied_default_assignee_not_member', $user, $organizationId, [
					'suggested_assigned_to' => $user['id'],
				]);
				http_response_code(400);
				echo json_encode(['error' => 'Assigned user must belong to the organization']);
				return;
			}

			$assignedTo = (int) $user['id'];
		}
	} else {
		$organizationId = null;
		$assignedTo = (int) $user['id'];
		$assignedBy = (int) $user['id'];
	}

	try {
		$stmt = $pdo->prepare("
			INSERT INTO tasks (
				user_id,
				organization_id,
				assigned_to,
				assigned_by,
				title,
				description,
				category,
				priority,
				status,
				due_date
			) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
		");
		$stmt->execute([
			$user['id'],
			$organizationId,
			$assignedTo,
			$assignedBy,
			$input['title'],
			$input['description'] ?? null,
			$input['category'] ?? null,
			$input['priority'] ?? 'medium',
			$status,
			$dueDate,
		]);

		$taskId = $pdo->lastInsertId();
		$task = getTaskRecord($pdo, $taskId);

		logTaskDebug('createTask.success', $user, $organizationId, [
			'task_id' => (int) $taskId,
			'assigned_to' => $assignedTo,
			'status' => $status,
		]);

		http_response_code(201);
		echo json_encode([
			'message' => 'Task created successfully',
			'task_id' => $taskId,
			'task' => $task,
		]);
	} catch (PDOException $exception) {
		logTaskDebug('createTask.error', $user, $organizationId, [
			'error' => $exception->getMessage(),
			'input' => $input,
		]);
		http_response_code(500);
		echo json_encode(['error' => 'Failed to create task: ' . $exception->getMessage()]);
	}
}

function updateTask($pdo, $user) {
	$input = parseJsonInput();
	$taskId = $input['id'] ?? null;

	if (!$taskId) {
		http_response_code(400);
		echo json_encode(['error' => 'Task ID is required']);
		return;
	}

	$task = getTaskRecord($pdo, $taskId);
	if (!$task) {
		http_response_code(404);
		echo json_encode(['error' => 'Task not found']);
		return;
	}

	if (!empty($input['action']) && $input['action'] === 'submit') {
		submitTask($pdo, $user, $task);
		return;
	}

	if (!empty($input['action']) && $input['action'] === 'review') {
		reviewTask($pdo, $user, $task, $input);
		return;
	}

	try {
		$updates = [];
		$values = [];

		if ((int) $task['organization_id'] > 0) {
			if (userCanManageOrganizationTasks($pdo, $user, $task['organization_id'])) {
				$allowedFields = ['title', 'description', 'category', 'priority', 'status', 'due_date', 'assigned_to'];
				foreach ($allowedFields as $field) {
					if (array_key_exists($field, $input)) {
						if ($field === 'due_date') {
							$values[] = normalizeDateTimeValue($input[$field]);
						} elseif ($field === 'assigned_to') {
							$assignedTo = !empty($input[$field]) ? (int) $input[$field] : null;
							if ($assignedTo) {
								$membership = getOrganizationMembership($pdo, $assignedTo, $task['organization_id']);
								if (!$membership) {
									logTaskDebug('updateTask.denied_assignee_not_member', $user, $task['organization_id'], [
										'task_id' => (int) $taskId,
										'assigned_to' => $assignedTo,
									]);
									http_response_code(400);
									echo json_encode(['error' => 'Assigned user must belong to the organization']);
									return;
								}
							}

							logTaskDebug('taskAssignment.update', $user, $task['organization_id'], [
								'task_id' => (int) $taskId,
								'assigned_to' => $assignedTo,
							]);
							$values[] = $assignedTo;
						} else {
							$values[] = $input[$field];
						}

						$updates[] = "$field = ?";
					}
				}
			} elseif ((int) $task['assigned_to'] === (int) $user['id']) {
				$allowedStatus = ['pending', 'in_progress', 'submitted'];
				if (!array_key_exists('status', $input)) {
					http_response_code(403);
					echo json_encode(['error' => 'Only task assignees can update status']);
					return;
				}

				if (!in_array($input['status'], $allowedStatus, true)) {
					http_response_code(400);
					echo json_encode(['error' => 'Invalid status transition']);
					return;
				}

				$updates[] = 'status = ?';
				$values[] = $input['status'];
			} else {
				http_response_code(403);
				echo json_encode(['error' => 'Unauthorized']);
				return;
			}
		} else {
			if ((int) $task['user_id'] !== (int) $user['id']) {
				http_response_code(403);
				echo json_encode(['error' => 'Unauthorized']);
				return;
			}

			$allowedFields = ['title', 'description', 'category', 'priority', 'status', 'due_date'];
			foreach ($allowedFields as $field) {
				if (array_key_exists($field, $input)) {
					$values[] = $field === 'due_date' ? normalizeDateTimeValue($input[$field]) : $input[$field];
					$updates[] = "$field = ?";
				}
			}
		}

		if (empty($updates)) {
			http_response_code(400);
			echo json_encode(['error' => 'No fields to update']);
			return;
		}

		$updates[] = 'updated_at = CURRENT_TIMESTAMP';
		$values[] = $taskId;

		$sql = 'UPDATE tasks SET ' . implode(', ', $updates) . ' WHERE id = ?';
		$stmt = $pdo->prepare($sql);
		$stmt->execute($values);

		logTaskDebug('updateTask.success', $user, $task['organization_id'] ?? null, [
			'task_id' => (int) $taskId,
			'updates' => $updates,
		]);

		echo json_encode(['message' => 'Task updated successfully']);
	} catch (PDOException $exception) {
		logTaskDebug('updateTask.error', $user, $task['organization_id'] ?? null, [
			'task_id' => (int) $taskId,
			'error' => $exception->getMessage(),
		]);
		http_response_code(500);
		echo json_encode(['error' => 'Failed to update task: ' . $exception->getMessage()]);
	}
}

function submitTask($pdo, $user, $task) {
	$isOrganizationTask = (int) $task['organization_id'] > 0;
	$canSubmit = $isOrganizationTask
		? ((int) $task['assigned_to'] === (int) $user['id'])
		: (((int) $task['assigned_to'] === (int) $user['id']) || ((int) $task['user_id'] === (int) $user['id']));

	if (!$canSubmit) {
		http_response_code(403);
		echo json_encode(['error' => 'Only the assigned user can submit this task']);
		return;
	}

	try {
		$stmt = $pdo->prepare("UPDATE tasks SET status = 'submitted', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
		$stmt->execute([$task['id']]);

		logTaskDebug('submitTask.success', $user, $task['organization_id'] ?? null, [
			'task_id' => (int) $task['id'],
			'new_status' => 'submitted',
		]);

		echo json_encode(['message' => 'Task submitted successfully']);
	} catch (PDOException $exception) {
		logTaskDebug('submitTask.error', $user, $task['organization_id'] ?? null, [
			'task_id' => (int) $task['id'],
			'error' => $exception->getMessage(),
		]);
		http_response_code(500);
		echo json_encode(['error' => 'Failed to submit task: ' . $exception->getMessage()]);
	}
}

function reviewTask($pdo, $user, $task, $input) {
	if ((int) $task['organization_id'] <= 0 || !userCanManageOrganizationTasks($pdo, $user, $task['organization_id'])) {
		http_response_code(403);
		echo json_encode(['error' => 'Only organization admins can review tasks']);
		return;
	}

	$reviewAction = $input['reviewAction'] ?? null;
	if (!in_array($reviewAction, ['approve', 'reject'], true)) {
		http_response_code(400);
		echo json_encode(['error' => 'Review action must be approve or reject']);
		return;
	}

	$status = $reviewAction === 'approve' ? 'completed' : 'in_progress';

	try {
		$stmt = $pdo->prepare("UPDATE tasks SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
		$stmt->execute([$status, $task['id']]);

		logTaskDebug('reviewTask.success', $user, $task['organization_id'] ?? null, [
			'task_id' => (int) $task['id'],
			'review_action' => $reviewAction,
			'new_status' => $status,
		]);

		echo json_encode(['message' => 'Task reviewed successfully', 'status' => $status]);
	} catch (PDOException $exception) {
		logTaskDebug('reviewTask.error', $user, $task['organization_id'] ?? null, [
			'task_id' => (int) $task['id'],
			'error' => $exception->getMessage(),
		]);
		http_response_code(500);
		echo json_encode(['error' => 'Failed to review task: ' . $exception->getMessage()]);
	}
}

function deleteTask($pdo, $user) {
	$taskId = $_GET['id'] ?? null;

	if (!$taskId) {
		http_response_code(400);
		echo json_encode(['error' => 'Task ID is required']);
		return;
	}

	$task = getTaskRecord($pdo, $taskId);
	if (!$task) {
		http_response_code(404);
		echo json_encode(['error' => 'Task not found']);
		return;
	}

	if ((int) $task['organization_id'] > 0) {
		if (!userCanManageOrganizationTasks($pdo, $user, $task['organization_id'])) {
			http_response_code(403);
			echo json_encode(['error' => 'Only organization admins can delete organization tasks']);
			return;
		}
	} elseif ((int) $task['user_id'] !== (int) $user['id']) {
		http_response_code(403);
		echo json_encode(['error' => 'Unauthorized']);
		return;
	}

	try {
		$stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
		$stmt->execute([$taskId]);

		echo json_encode(['message' => 'Task deleted successfully']);
	} catch (PDOException $exception) {
		http_response_code(500);
		echo json_encode(['error' => 'Failed to delete task: ' . $exception->getMessage()]);
	}
}