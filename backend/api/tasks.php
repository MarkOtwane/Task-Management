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
$action = $_GET['action'] ?? null;

function logTaskDebug($event, $user, $organizationId = null, $payload = null) {
	error_log('[tasks] ' . $event . ' userId=' . ($user['id'] ?? 'unknown') . ' role=' . ($user['role'] ?? 'unknown') . ' orgId=' . ($organizationId ?? 'null') . ' payload=' . json_encode($payload));
}

logTaskDebug('request', $user, $_GET['organization_id'] ?? null, [
	'method' => $method,
	'action' => $action,
	'query' => $_GET,
]);

if ($method === 'GET') {
	if (!empty($_GET['id'])) {
		getTaskById($pdo, $user, (int) $_GET['id']);
	} else {
		getTasks($pdo, $user);
	}
} elseif ($method === 'POST' && $action === 'submit-evidence') {
	submitTaskWithEvidence($pdo, $user);
} elseif ($method === 'POST' && $action === 'meet-link') {
	attachTaskMeetLink($pdo, $user);
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

function normalizeMeetLink($value, &$error = null) {
	if ($value === null) {
		return null;
	}

	$link = trim((string) $value);
	if ($link === '') {
		return null;
	}

	if (!filter_var($link, FILTER_VALIDATE_URL)) {
		$error = 'Meet link must be a valid URL';
		return false;
	}

	$parts = parse_url($link);
	$host = strtolower($parts['host'] ?? '');
	$scheme = strtolower($parts['scheme'] ?? '');

	if (!in_array($scheme, ['http', 'https'], true)) {
		$error = 'Meet link must use http or https';
		return false;
	}

	if ($host !== 'meet.google.com') {
		$error = 'Meet link must be from meet.google.com';
		return false;
	}

	return $link;
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

function createNotificationRecord($pdo, $userId, $message, $organizationId = null, $taskId = null) {
	if (!$userId || !$message) {
		return;
	}

	try {
		$stmt = $pdo->prepare(" 
			INSERT INTO notifications (user_id, organization_id, task_id, message, is_read, created_at)
			VALUES (?, ?, ?, ?, FALSE, CURRENT_TIMESTAMP)
		");
		$stmt->execute([(int) $userId, $organizationId ?: null, $taskId ?: null, $message]);
	} catch (PDOException $exception) {
		error_log('[tasks] createNotificationRecord.error payload=' . json_encode([
			'user_id' => $userId,
			'organization_id' => $organizationId,
			'task_id' => $taskId,
			'error' => $exception->getMessage(),
		]));
	}
}

function buildDisplayName($userRecord) {
	if (!$userRecord) {
		return 'Admin';
	}

	if (!empty($userRecord['username'])) {
		return $userRecord['username'];
	}

	if (!empty($userRecord['email'])) {
		return $userRecord['email'];
	}

	return 'Admin';
}

function sanitizeSubmissionFileName($name) {
	$base = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
	$base = trim($base, '._-');
	return $base !== '' ? $base : 'submission';
}

function userCanAccessTaskOrganization($pdo, $user, $organizationId) {
	if (!$organizationId) {
		return true;
	}

	return userCanAccessOrganization($pdo, $user, $organizationId);
}

function canUserViewTask($pdo, $user, $task) {
	if ((int) $task['organization_id'] > 0) {
		if (!userCanAccessOrganization($pdo, $user, $task['organization_id'])) {
			return false;
		}

		if (userCanManageOrganizationTasks($pdo, $user, $task['organization_id'])) {
			return true;
		}

		return (int) $task['assigned_to'] === (int) $user['id'];
	}

	return (int) $task['user_id'] === (int) $user['id'];
}

function getTaskById($pdo, $user, $taskId) {
	if ($taskId <= 0) {
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

	if (!canUserViewTask($pdo, $user, $task)) {
		http_response_code(403);
		echo json_encode(['error' => 'Unauthorized']);
		return;
	}

	echo json_encode($task);
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
	$rawMeetLink = $input['meet_link'] ?? ($input['meetLink'] ?? null);
	$meetLinkError = null;
	$meetLink = normalizeMeetLink($rawMeetLink, $meetLinkError);
	if ($meetLink === false) {
		http_response_code(400);
		echo json_encode(['error' => $meetLinkError]);
		return;
	}

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
		$meetLink = null;
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
				due_date,
				meet_link
			) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
			$meetLink,
		]);

		$taskId = $pdo->lastInsertId();
		$task = getTaskRecord($pdo, $taskId);

		logTaskDebug('createTask.success', $user, $organizationId, [
			'task_id' => (int) $taskId,
			'assigned_to' => $assignedTo,
			'status' => $status,
		]);

		if ($organizationId && $assignedTo && (int) $assignedTo !== (int) $user['id']) {
			$adminName = buildDisplayName($user);
			createNotificationRecord(
				$pdo,
				$assignedTo,
				'You have been assigned a task by ' . $adminName,
				$organizationId,
				(int) $taskId
			);
		}

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

	$newAssignedTo = isset($input['assigned_to']) ? (!empty($input['assigned_to']) ? (int) $input['assigned_to'] : null) : null;
	if (
		(int) ($task['organization_id'] ?? 0) > 0
		&& $newAssignedTo
		&& $newAssignedTo !== (int) ($task['assigned_to'] ?? 0)
		&& $newAssignedTo !== (int) $user['id']
	) {
		$adminName = buildDisplayName($user);
		createNotificationRecord(
			$pdo,
			$newAssignedTo,
			'You have been assigned a task by ' . $adminName,
			$task['organization_id'] ?? null,
			(int) $taskId
		);
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
				$allowedFields = ['title', 'description', 'category', 'priority', 'status', 'due_date', 'assigned_to', 'meet_link'];
				foreach ($allowedFields as $field) {
					if (array_key_exists($field, $input)) {
						if ($field === 'due_date') {
							$values[] = normalizeDateTimeValue($input[$field]);
						} elseif ($field === 'meet_link') {
							$meetLinkError = null;
							$meetLink = normalizeMeetLink($input[$field], $meetLinkError);
							if ($meetLink === false) {
								http_response_code(400);
								echo json_encode(['error' => $meetLinkError]);
								return;
							}

							$values[] = $meetLink;
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

function attachTaskMeetLink($pdo, $user) {
	$input = parseJsonInput();
	$taskId = !empty($input['id']) ? (int) $input['id'] : (!empty($_GET['id']) ? (int) $_GET['id'] : 0);
	$meetLinkError = null;
	$meetLink = normalizeMeetLink($input['meet_link'] ?? ($input['meetLink'] ?? null), $meetLinkError);

	logTaskDebug('taskMeetLink.attach.attempt', $user, null, [
		'task_id' => $taskId,
		'meet_link' => $input['meet_link'] ?? ($input['meetLink'] ?? null),
	]);

	if ($taskId <= 0) {
		http_response_code(400);
		echo json_encode(['error' => 'Task ID is required']);
		return;
	}

	if ($meetLink === false || !$meetLink) {
		http_response_code(400);
		echo json_encode(['error' => $meetLinkError ?: 'Meet link is required']);
		return;
	}

	$task = getTaskRecord($pdo, $taskId);
	if (!$task) {
		http_response_code(404);
		echo json_encode(['error' => 'Task not found']);
		return;
	}

	if ((int) $task['organization_id'] <= 0) {
		http_response_code(400);
		echo json_encode(['error' => 'Meet links are only supported for organization tasks']);
		return;
	}

	if (!userCanAccessOrganization($pdo, $user, $task['organization_id'])) {
		http_response_code(403);
		echo json_encode(['error' => 'Unauthorized']);
		return;
	}

	if (!userCanManageOrganizationTasks($pdo, $user, $task['organization_id'])) {
		http_response_code(403);
		echo json_encode(['error' => 'Only organization admins can attach a meet link']);
		return;
	}

	try {
		$stmt = $pdo->prepare("UPDATE tasks SET meet_link = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
		$stmt->execute([$meetLink, $taskId]);

		$updatedTask = getTaskRecord($pdo, $taskId);
		logTaskDebug('taskMeetLink.attach.success', $user, $task['organization_id'], [
			'task_id' => $taskId,
			'meet_link' => $meetLink,
		]);

		echo json_encode([
			'message' => 'Meet link attached successfully',
			'task' => $updatedTask,
		]);
	} catch (PDOException $exception) {
		logTaskDebug('taskMeetLink.attach.error', $user, $task['organization_id'], [
			'task_id' => $taskId,
			'error' => $exception->getMessage(),
		]);
		http_response_code(500);
		echo json_encode(['error' => 'Failed to attach meet link: ' . $exception->getMessage()]);
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
		$stmt = $pdo->prepare("UPDATE tasks SET status = 'submitted', submitted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
		$stmt->execute([$task['id']]);

		if (!empty($task['assigned_by']) && (int) $task['assigned_by'] !== (int) $user['id']) {
			createNotificationRecord(
				$pdo,
				(int) $task['assigned_by'],
				'Task submitted: ' . ($task['title'] ?? ('#' . $task['id'])),
				$task['organization_id'] ?? null,
				(int) $task['id']
			);
		}

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

function submitTaskWithEvidence($pdo, $user) {
	$taskId = !empty($_POST['task_id']) ? (int) $_POST['task_id'] : 0;
	$submissionType = strtolower(trim((string) ($_POST['submission_type'] ?? '')));
	$submissionUrl = trim((string) ($_POST['submission_url'] ?? ''));

	if ($taskId <= 0) {
		http_response_code(400);
		echo json_encode(['error' => 'Task ID is required']);
		return;
	}

	if (!in_array($submissionType, ['image', 'video', 'link'], true)) {
		http_response_code(400);
		echo json_encode(['error' => 'submission_type must be image, video, or link']);
		return;
	}

	$task = getTaskRecord($pdo, $taskId);
	if (!$task) {
		http_response_code(404);
		echo json_encode(['error' => 'Task not found']);
		return;
	}

	$isOrganizationTask = (int) $task['organization_id'] > 0;
	$canSubmit = $isOrganizationTask
		? ((int) $task['assigned_to'] === (int) $user['id'])
		: (((int) $task['assigned_to'] === (int) $user['id']) || ((int) $task['user_id'] === (int) $user['id']));

	if (!$canSubmit) {
		http_response_code(403);
		echo json_encode(['error' => 'Only the assigned user can submit this task']);
		return;
	}

	$storedSubmissionUrl = null;

	if ($submissionType === 'link') {
		if (!$submissionUrl || !filter_var($submissionUrl, FILTER_VALIDATE_URL)) {
			http_response_code(400);
			echo json_encode(['error' => 'Link submission requires a valid URL']);
			return;
		}
		$storedSubmissionUrl = $submissionUrl;
	} else {
		if (empty($_FILES['submission_file']) || !isset($_FILES['submission_file']['tmp_name'])) {
			http_response_code(400);
			echo json_encode(['error' => 'A submission file is required for image/video submissions']);
			return;
		}

		$file = $_FILES['submission_file'];
		if (!empty($file['error'])) {
			http_response_code(400);
			echo json_encode(['error' => 'File upload failed']);
			return;
		}

		if ((int) $file['size'] > 20 * 1024 * 1024) {
			http_response_code(400);
			echo json_encode(['error' => 'File exceeds 20MB upload limit']);
			return;
		}

		$allowedExtensions = $submissionType === 'image'
			? ['jpg', 'jpeg', 'png', 'gif', 'webp']
			: ['mp4', 'mov', 'avi', 'mkv', 'webm'];

		$originalName = (string) ($file['name'] ?? 'submission');
		$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
		if (!$extension || !in_array($extension, $allowedExtensions, true)) {
			http_response_code(400);
			echo json_encode(['error' => 'Invalid file type for submission']);
			return;
		}

		$uploadDir = realpath(__DIR__ . '/../../uploads');
		if ($uploadDir === false) {
			$uploadDir = __DIR__ . '/../../uploads';
			if (!is_dir($uploadDir)) {
				mkdir($uploadDir, 0775, true);
			}
		}

		$submissionDir = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . 'task_submissions';
		if (!is_dir($submissionDir)) {
			mkdir($submissionDir, 0775, true);
		}

		$safeName = sanitizeSubmissionFileName(pathinfo($originalName, PATHINFO_FILENAME));
		$fileName = sprintf('task_%d_user_%d_%d_%s.%s', $taskId, (int) $user['id'], time(), $safeName, $extension);
		$targetPath = $submissionDir . DIRECTORY_SEPARATOR . $fileName;

		if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
			http_response_code(500);
			echo json_encode(['error' => 'Failed to store uploaded file']);
			return;
		}

		$storedSubmissionUrl = '/uploads/task_submissions/' . $fileName;
	}

	try {
		$stmt = $pdo->prepare(" 
			UPDATE tasks
			SET status = 'submitted',
			    submission_type = ?,
			    submission_url = ?,
			    submitted_at = CURRENT_TIMESTAMP,
			    updated_at = CURRENT_TIMESTAMP
			WHERE id = ?
		");
		$stmt->execute([$submissionType, $storedSubmissionUrl, $taskId]);

		if (!empty($task['assigned_by']) && (int) $task['assigned_by'] !== (int) $user['id']) {
			createNotificationRecord(
				$pdo,
				(int) $task['assigned_by'],
				'Task submitted: ' . ($task['title'] ?? ('#' . $taskId)),
				$task['organization_id'] ?? null,
				$taskId
			);
		}

		echo json_encode([
			'message' => 'Task submitted successfully',
			'submissionType' => $submissionType,
			'submissionUrl' => $storedSubmissionUrl,
			'submittedAt' => date('c'),
		]);
	} catch (PDOException $exception) {
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

		if (!empty($task['assigned_to'])) {
			createNotificationRecord(
				$pdo,
				(int) $task['assigned_to'],
				'Task ' . ($reviewAction === 'approve' ? 'approved' : 'rejected') . ': ' . ($task['title'] ?? ('#' . $task['id'])),
				$task['organization_id'] ?? null,
				(int) $task['id']
			);
		}

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