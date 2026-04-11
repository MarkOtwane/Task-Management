<?php
/**
 * Tasks API
 * Endpoints: GET, POST, PUT, DELETE for tasks
 */

require_once '../config/cors.php';
require_once '../config/database.php';
require_once '../middleware/auth.php';
require_once __DIR__ . '/activity_helpers.php';

$method = $_SERVER['REQUEST_METHOD'];
$user = getCurrentUser($pdo);
$action = $_GET['action'] ?? null;

function logTaskDebug($event, $user, $organizationId = null, $payload = null) {
	error_log('[tasks] ' . $event . ' userId=' . ($user['id'] ?? 'unknown') . ' role=' . ($user['role'] ?? 'unknown') . ' orgId=' . ($organizationId ?? 'null') . ' payload=' . json_encode($payload));
}

function logTaskTimeline($pdo, $taskId, $userId, $action, $message = null) {
	if (!$taskId || !$action) {
		return;
	}
	try {
		$stmt = $pdo->prepare("
			INSERT INTO task_timeline (task_id, user_id, action, message, created_at)
			VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
		");
		$stmt->execute([
			(int) $taskId,
			$userId ? (int) $userId : null,
			$action,
			$message,
		]);
	} catch (PDOException $exception) {
		error_log('[tasks] logTaskTimeline.error payload=' . json_encode([
			'task_id' => $taskId,
			'user_id' => $userId,
			'action' => $action,
			'error' => $exception->getMessage(),
		]));
	}
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
} elseif ($method === 'GET' && $action === 'timeline') {
	getTaskTimeline($pdo, $user);
} elseif ($method === 'GET' && $action === 'member-performance') {
	getMemberPerformance($pdo, $user);
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
		       assigner.username AS assigned_by_name, assigner.email AS assigned_by_email,
		       ts.submission_link,
		       ts.submission_files,
		       ts.review_feedback,
		       ts.submitted_by,
		       ts.submitted_at,
		       submitter.username AS submitted_by_name,
		       submitter.email AS submitted_by_email
		FROM tasks t
		LEFT JOIN organizations o ON o.id = t.organization_id
		LEFT JOIN users assignee ON assignee.id = t.assigned_to
		LEFT JOIN users assigner ON assigner.id = t.assigned_by
		LEFT JOIN task_submissions ts ON ts.task_id = t.id
		LEFT JOIN users submitter ON submitter.id = ts.submitted_by
		WHERE t.id = ?
	");
	$stmt->execute([$taskId]);
	return $stmt->fetch();
}

function createNotificationRecord($pdo, $userId, $message, $organizationId = null, $entityType = 'task', $entityId = null) {
	if (!$userId || !$message) {
		return;
	}

	try {
		$stmt = $pdo->prepare(" 
			INSERT INTO notifications (user_id, organization_id, task_id, message, entity_type, entity_id, is_read, created_at)
			VALUES (?, ?, ?, ?, ?, ?, FALSE, CURRENT_TIMESTAMP)
		");
		$taskId = $entityType === 'task' ? ($entityId ?: null) : null;
		$stmt->execute([(int) $userId, $organizationId ?: null, $taskId, $message, $entityType, $entityId ?: null]);
	} catch (PDOException $exception) {
		error_log('[tasks] createNotificationRecord.error payload=' . json_encode([
			'user_id' => $userId,
			'organization_id' => $organizationId,
			'entity_type' => $entityType,
			'entity_id' => $entityId,
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

		$membership = getOrganizationMembership($pdo, $user['id'], $task['organization_id']);
		if ($membership && $membership['role'] === 'client') {
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

			$membership = getOrganizationMembership($pdo, $user['id'], $organizationId);
			if ($membership && $membership['role'] === 'client') {
				echo json_encode([]);
				return;
			}

			if (userCanManageOrganizationTasks($pdo, $user, $organizationId)) {
				$stmt = $pdo->prepare("
					SELECT t.*, o.name AS organization_name,
					       assignee.username AS assigned_to_name, assignee.email AS assigned_to_email,
					       assigner.username AS assigned_by_name, assigner.email AS assigned_by_email,
					       ts.submission_link,
					       ts.submission_files,
					       ts.review_feedback,
					       ts.submitted_by,
					       ts.submitted_at,
					       submitter.username AS submitted_by_name, submitter.email AS submitted_by_email
					FROM tasks t
					LEFT JOIN organizations o ON o.id = t.organization_id
					LEFT JOIN users assignee ON assignee.id = t.assigned_to
					LEFT JOIN users assigner ON assigner.id = t.assigned_by
					LEFT JOIN task_submissions ts ON ts.task_id = t.id
					LEFT JOIN users submitter ON submitter.id = ts.submitted_by
					WHERE t.organization_id = ?
					ORDER BY t.created_at DESC
				");
				$stmt->execute([$organizationId]);
			} else {
				$stmt = $pdo->prepare("
					SELECT t.*, o.name AS organization_name,
					       assignee.username AS assigned_to_name, assignee.email AS assigned_to_email,
					       assigner.username AS assigned_by_name, assigner.email AS assigned_by_email,
					       ts.submission_link,
					       ts.submission_files,
					       ts.review_feedback,
					       ts.submitted_by,
					       ts.submitted_at,
					       submitter.username AS submitted_by_name, submitter.email AS submitted_by_email
					FROM tasks t
					LEFT JOIN organizations o ON o.id = t.organization_id
					LEFT JOIN users assignee ON assignee.id = t.assigned_to
					LEFT JOIN users assigner ON assigner.id = t.assigned_by
					LEFT JOIN task_submissions ts ON ts.task_id = t.id
					LEFT JOIN users submitter ON submitter.id = ts.submitted_by
					WHERE t.organization_id = ? AND t.assigned_to = ?
					ORDER BY t.created_at DESC
				");
				$stmt->execute([$organizationId, $user['id']]);
			}
		} else {
			$stmt = $pdo->prepare("
				SELECT t.*, o.name AS organization_name,
				       assignee.username AS assigned_to_name, assignee.email AS assigned_to_email,
				       assigner.username AS assigned_by_name, assigner.email AS assigned_by_email,
				       ts.submission_link,
				       ts.submission_files,
				       ts.review_feedback,
				       ts.submitted_by,
				       ts.submitted_at,
				       submitter.username AS submitted_by_name, submitter.email AS submitted_by_email
				FROM tasks t
				LEFT JOIN organizations o ON o.id = t.organization_id
				LEFT JOIN users assignee ON assignee.id = t.assigned_to
				LEFT JOIN users assigner ON assigner.id = t.assigned_by
				LEFT JOIN task_submissions ts ON ts.task_id = t.id
				LEFT JOIN users submitter ON submitter.id = ts.submitted_by
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
			if (!$membership || $membership['role'] === 'client') {
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
			if (!$creatorMembership || $creatorMembership['role'] === 'client') {
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
			$assignedTo = (int) $user['id'];
			$assignedBy = (int) $user['id'];
			$meetLink = null;
		}

		$assignmentMessage = null;
		if ($assignedTo && (int) $assignedTo !== (int) $user['id']) {
			$assigneeStmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
			$assigneeStmt->execute([$assignedTo]);
			$assignee = $assigneeStmt->fetch();
			$assignmentMessage = 'Task assigned to ' . ($assignee['username'] ?? 'user');
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

		logTaskTimeline($pdo, $taskId, (int) $user['id'], 'created', 'Task created');
		if ($assignmentMessage) {
			logTaskTimeline($pdo, $taskId, (int) $user['id'], 'assigned', $assignmentMessage);
		}

		logTaskDebug('createTask.success', $user, $organizationId, [
			'task_id' => (int) $taskId,
			'assigned_to' => $assignedTo,
			'status' => $status,
		]);

		if ($organizationId) {
			$activityRecipients = [(int) $user['id']];
			if ($assignedTo && (int) $assignedTo !== (int) $user['id']) {
				$activityRecipients[] = (int) $assignedTo;
			}
			recordWorkspaceActivity($pdo, $activityRecipients, $organizationId, 'task_created', 'Task created: ' . $input['title'], (int) $taskId, [
				'assigned_to' => $assignedTo,
				'created_by' => (int) $user['id'],
			]);
		}

		if ($organizationId && $assignedTo && (int) $assignedTo !== (int) $user['id']) {
			$adminName = buildDisplayName($user);
			createNotificationRecord(
				$pdo,
				$assignedTo,
				'You have been assigned a task by ' . $adminName,
				$organizationId,
				'task',
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
			'task',
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
								if (!$membership || $membership['role'] === 'client') {
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
				$membership = getOrganizationMembership($pdo, $user['id'], $task['organization_id']);
				if ($membership && $membership['role'] === 'client') {
					http_response_code(403);
					echo json_encode(['error' => 'Client members cannot update organization tasks']);
					return;
				}
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

		$oldAssignedTo = $task['assigned_to'] ?? null;
		$newAssignedTo = $input['assigned_to'] ?? $oldAssignedTo;
		if ($newAssignedTo && (int) $newAssignedTo !== (int) $oldAssignedTo) {
			$assigneeStmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
			$assigneeStmt->execute([$newAssignedTo]);
			$assignee = $assigneeStmt->fetch();
			logTaskTimeline($pdo, (int) $taskId, (int) $user['id'], 'assigned', 'Task assigned to ' . ($assignee['username'] ?? 'user'));
		} else {
			logTaskTimeline($pdo, (int) $taskId, (int) $user['id'], 'updated', 'Task updated');
		}

		if ((int) $task['organization_id'] > 0) {
			$activityRecipients = [(int) $user['id']];
			if (!empty($task['assigned_to']) && (int) $task['assigned_to'] !== (int) $user['id']) {
				$activityRecipients[] = (int) $task['assigned_to'];
			}
			recordWorkspaceActivity($pdo, $activityRecipients, (int) $task['organization_id'], 'task_updated', 'Task updated: ' . ($input['title'] ?? $task['title']), (int) $taskId, [
				'updated_fields' => array_keys($input),
			]);
		}

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
	if ($isOrganizationTask) {
		$membership = getOrganizationMembership($pdo, $user['id'], $task['organization_id']);
		if ($membership && $membership['role'] === 'client') {
			http_response_code(403);
			echo json_encode(['error' => 'Client members cannot submit tasks']);
			return;
		}
	}
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

		if ((int) $task['organization_id'] > 0) {
			$activityRecipients = [(int) $user['id']];
			if (!empty($task['assigned_by'])) {
				$activityRecipients[] = (int) $task['assigned_by'];
			}
			recordWorkspaceActivity($pdo, $activityRecipients, (int) $task['organization_id'], 'task_submitted', buildDisplayName($user) . ' submitted a task', (int) $task['id'], [
				'submitted_by' => (int) $user['id'],
			]);
		}

		if (!empty($task['assigned_by']) && (int) $task['assigned_by'] !== (int) $user['id']) {
			$submitterName = buildDisplayName($user);
			createNotificationRecord(
				$pdo,
				(int) $task['assigned_by'],
				$submitterName . ' submitted a task',
				$task['organization_id'] ?? null,
				'task',
				(int) $task['id']
			);
		}

		logTaskTimeline($pdo, (int) $task['id'], (int) $user['id'], 'submitted', buildDisplayName($user) . ' submitted work');

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
	$submissionLink = trim((string) ($_POST['submission_link'] ?? ($_POST['submission_url'] ?? '')));

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

	$isOrganizationTask = (int) $task['organization_id'] > 0;
	if ($isOrganizationTask) {
		$membership = getOrganizationMembership($pdo, $user['id'], $task['organization_id']);
		if ($membership && $membership['role'] === 'client') {
			http_response_code(403);
			echo json_encode(['error' => 'Client members cannot submit tasks']);
			return;
		}
	}
	$canSubmit = $isOrganizationTask
		? ((int) $task['assigned_to'] === (int) $user['id'])
		: (((int) $task['assigned_to'] === (int) $user['id']) || ((int) $task['user_id'] === (int) $user['id']));

	if (!$canSubmit) {
		http_response_code(403);
		echo json_encode(['error' => 'Only the assigned user can submit this task']);
		return;
	}

	$storedSubmissionFiles = [];
	if ($submissionLink !== '' && !filter_var($submissionLink, FILTER_VALIDATE_URL)) {
		http_response_code(400);
		echo json_encode(['error' => 'submission_link must be a valid URL']);
		return;
	}

	$filesPayload = [];
	if (!empty($_FILES['submission_files']) && isset($_FILES['submission_files']['name']) && is_array($_FILES['submission_files']['name'])) {
		$filesPayload = $_FILES['submission_files'];
	} elseif (!empty($_FILES['submission_file'])) {
		$filesPayload = [
			'name' => [$_FILES['submission_file']['name'] ?? ''],
			'type' => [$_FILES['submission_file']['type'] ?? ''],
			'tmp_name' => [$_FILES['submission_file']['tmp_name'] ?? ''],
			'error' => [$_FILES['submission_file']['error'] ?? 4],
			'size' => [$_FILES['submission_file']['size'] ?? 0],
		];
	}

	if (!empty($filesPayload['name']) && is_array($filesPayload['name'])) {
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

		$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov', 'avi', 'mkv', 'webm'];
		$totalFiles = count($filesPayload['name']);

		for ($index = 0; $index < $totalFiles; $index++) {
			$errorCode = (int) ($filesPayload['error'][$index] ?? 4);
			if ($errorCode === 4) {
				continue;
			}
			if ($errorCode !== 0) {
				http_response_code(400);
				echo json_encode(['error' => 'One or more files failed to upload']);
				return;
			}

			$size = (int) ($filesPayload['size'][$index] ?? 0);
			if ($size > 20 * 1024 * 1024) {
				http_response_code(400);
				echo json_encode(['error' => 'Each file must be 20MB or less']);
				return;
			}

			$originalName = (string) ($filesPayload['name'][$index] ?? 'submission');
			$tmpPath = (string) ($filesPayload['tmp_name'][$index] ?? '');
			$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
			if (!$extension || !in_array($extension, $allowedExtensions, true)) {
				http_response_code(400);
				echo json_encode(['error' => 'Invalid file type in submission_files']);
				return;
			}

			$safeName = sanitizeSubmissionFileName(pathinfo($originalName, PATHINFO_FILENAME));
			$fileName = sprintf('task_%d_user_%d_%d_%d_%s.%s', $taskId, (int) $user['id'], time(), $index, $safeName, $extension);
			$targetPath = $submissionDir . DIRECTORY_SEPARATOR . $fileName;

			if (!move_uploaded_file($tmpPath, $targetPath)) {
				http_response_code(500);
				echo json_encode(['error' => 'Failed to store one or more submission files']);
				return;
			}

			$storedSubmissionFiles[] = '/uploads/task_submissions/' . $fileName;
		}
	}

	if ($submissionLink === '' && empty($storedSubmissionFiles)) {
		http_response_code(400);
		echo json_encode(['error' => 'Provide at least a submission link or one uploaded file']);
		return;
	}

	try {
		$stmt = $pdo->prepare(" 
			UPDATE tasks
			SET status = 'submitted',
			    submission_type = 'mixed',
			    submission_url = ?,
			    submitted_at = CURRENT_TIMESTAMP,
			    updated_at = CURRENT_TIMESTAMP
			WHERE id = ?
		");
		$stmt->execute([$submissionLink !== '' ? $submissionLink : null, $taskId]);

		$submissionFilesJson = json_encode($storedSubmissionFiles);
		$submissionStatement = $pdo->prepare(" 
			INSERT INTO task_submissions (task_id, organization_id, submitted_by, submission_link, submission_files, review_feedback, submitted_at, updated_at)
			VALUES (?, ?, ?, ?, ?, NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
			ON CONFLICT (task_id)
			DO UPDATE SET
				submitted_by = EXCLUDED.submitted_by,
				submission_link = EXCLUDED.submission_link,
				submission_files = EXCLUDED.submission_files,
				review_feedback = NULL,
				submitted_at = CURRENT_TIMESTAMP,
				updated_at = CURRENT_TIMESTAMP
		");
		$submissionStatement->execute([
			$taskId,
			$task['organization_id'] ?? null,
			(int) $user['id'],
			$submissionLink !== '' ? $submissionLink : null,
			$submissionFilesJson,
		]);

		if (!empty($task['assigned_by']) && (int) $task['assigned_by'] !== (int) $user['id']) {
			$submitterName = buildDisplayName($user);
			createNotificationRecord(
				$pdo,
				(int) $task['assigned_by'],
				$submitterName . ' submitted a task',
				$task['organization_id'] ?? null,
				'task',
				$taskId
			);
		}

		if ((int) $task['organization_id'] > 0) {
			$activityRecipients = [(int) $user['id']];
			if (!empty($task['assigned_by'])) {
				$activityRecipients[] = (int) $task['assigned_by'];
			}
			recordWorkspaceActivity($pdo, $activityRecipients, (int) $task['organization_id'], 'task_submitted', buildDisplayName($user) . ' submitted a task', (int) $task['id'], [
				'submission_link' => $submissionLink,
				'submission_files' => $storedSubmissionFiles,
			]);
		}

		echo json_encode([
			'message' => 'Task submitted successfully',
			'submissionLink' => $submissionLink,
			'submissionFiles' => $storedSubmissionFiles,
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
	$feedback = trim((string) ($input['feedback'] ?? ''));

	try {
		$stmt = $pdo->prepare("UPDATE tasks SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
		$stmt->execute([$status, $task['id']]);

		$submissionFeedbackStatement = $pdo->prepare(" 
			UPDATE task_submissions
			SET review_feedback = ?, updated_at = CURRENT_TIMESTAMP
			WHERE task_id = ?
		");
		$submissionFeedbackStatement->execute([$feedback !== '' ? $feedback : null, (int) $task['id']]);

		if (!empty($task['assigned_to'])) {
			$message = $reviewAction === 'approve'
				? 'Your task was approved'
				: ('Your task was rejected' . ($feedback !== '' ? (': ' . $feedback) : ''));
			createNotificationRecord(
				$pdo,
				(int) $task['assigned_to'],
				$message,
				$task['organization_id'] ?? null,
				'task',
				(int) $task['id']
			);
		}

		$activityRecipients = [(int) $user['id']];
		if (!empty($task['assigned_to'])) {
			$activityRecipients[] = (int) $task['assigned_to'];
		}
		recordWorkspaceActivity($pdo, $activityRecipients, (int) $task['organization_id'], 'task_reviewed', 'Task ' . ($reviewAction === 'approve' ? 'approved' : 'rejected'), (int) $task['id'], [
			'review_action' => $reviewAction,
			'feedback' => $feedback,
		]);

		logTaskTimeline($pdo, (int) $task['id'], (int) $user['id'], $reviewAction === 'approve' ? 'approved' : 'rejected', buildDisplayName($user) . ' ' . $reviewAction . 'd the task');

		logTaskDebug('reviewTask.success', $user, $task['organization_id'] ?? null, [
			'task_id' => (int) $task['id'],
			'review_action' => $reviewAction,
			'new_status' => $status,
		]);

		echo json_encode(['message' => 'Task reviewed successfully', 'status' => $status, 'feedback' => $feedback]);
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

		if ((int) $task['organization_id'] > 0) {
			$activityRecipients = [(int) $user['id']];
			if (!empty($task['assigned_to'])) {
				$activityRecipients[] = (int) $task['assigned_to'];
			}
			recordWorkspaceActivity($pdo, $activityRecipients, (int) $task['organization_id'], 'task_deleted', 'Task deleted: ' . $task['title'], (int) $taskId, []);
		}

		echo json_encode(['message' => 'Task deleted successfully']);
	} catch (PDOException $exception) {
		http_response_code(500);
		echo json_encode(['error' => 'Failed to delete task: ' . $exception->getMessage()]);
	}
}

function getTaskTimeline($pdo, $user) {
	$taskId = $_GET['task_id'] ?? null;

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

	if (!canUserViewTask($pdo, $user, $task)) {
		http_response_code(403);
		echo json_encode(['error' => 'Unauthorized']);
		return;
	}

	try {
		$stmt = $pdo->prepare("
			SELECT tl.*, u.username, u.email
			FROM task_timeline tl
			LEFT JOIN users u ON u.id = tl.user_id
			WHERE tl.task_id = ?
			ORDER BY tl.created_at DESC
			LIMIT 50
		");
		$stmt->execute([$taskId]);
		$timeline = $stmt->fetchAll();

		echo json_encode($timeline);
	} catch (PDOException $exception) {
		http_response_code(500);
		echo json_encode(['error' => 'Failed to fetch timeline: ' . $exception->getMessage()]);
	}
}

function getMemberPerformance($pdo, $user) {
	$organizationId = $_GET['organization_id'] ?? null;

	if (!$organizationId) {
		http_response_code(400);
		echo json_encode(['error' => 'Organization ID is required']);
		return;
	}

	if (!userCanManageOrganizationTasks($pdo, $user, $organizationId)) {
		http_response_code(403);
		echo json_encode(['error' => 'Unauthorized']);
		return;
	}

	try {
		$stmt = $pdo->prepare("
			SELECT 
				u.id,
				u.username,
				u.email,
				COUNT(t.id) AS total_assigned_tasks,
				COUNT(CASE WHEN t.status = 'completed' THEN 1 END) AS completed_tasks,
				COUNT(CASE WHEN t.status != 'completed' AND t.due_date < CURRENT_TIMESTAMP THEN 1 END) AS overdue_tasks
			FROM users u
			INNER JOIN organization_members om ON om.user_id = u.id AND om.organization_id = ?
			LEFT JOIN tasks t ON t.assigned_to = u.id AND t.organization_id = ?
			GROUP BY u.id, u.username, u.email
			ORDER BY total_assigned_tasks DESC
		");
		$stmt->execute([$organizationId, $organizationId]);
		$members = $stmt->fetchAll();

		echo json_encode($members);
	} catch (PDOException $exception) {
		http_response_code(500);
		echo json_encode(['error' => 'Failed to fetch member performance: ' . $exception->getMessage()]);
	}
}