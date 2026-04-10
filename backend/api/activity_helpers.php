<?php

function normalizeActivityRecipientIds($recipientIds) {
	if (is_numeric($recipientIds)) {
		$recipientIds = [$recipientIds];
	}

	if (!is_array($recipientIds)) {
		return [];
	}

	$normalized = [];
	foreach ($recipientIds as $recipientId) {
		$recipientId = (int) $recipientId;
		if ($recipientId > 0) {
			$normalized[$recipientId] = $recipientId;
		}
	}

	return array_values($normalized);
}

function recordWorkspaceActivity($pdo, $recipientIds, $organizationId, $eventType, $message, $taskId = null, $metadata = []) {
	$recipientIds = normalizeActivityRecipientIds($recipientIds);
	if (empty($recipientIds) || (int) $organizationId <= 0 || trim((string) $message) === '') {
		return;
	}

	$metadataJson = json_encode(is_array($metadata) ? $metadata : [], JSON_UNESCAPED_SLASHES);
	$stmt = $pdo->prepare("\n		INSERT INTO activities (user_id, organization_id, task_id, event_type, message, metadata, created_at)\n		VALUES (?, ?, ?, ?, ?, ?::jsonb, CURRENT_TIMESTAMP)\n	");

	foreach ($recipientIds as $recipientId) {
		try {
			$stmt->execute([
				$recipientId,
				(int) $organizationId,
				$taskId ? (int) $taskId : null,
				$eventType,
				$message,
				$metadataJson,
			]);
		} catch (PDOException $exception) {
			error_log('[activity] recordWorkspaceActivity.error payload=' . json_encode([
				'organization_id' => $organizationId,
				'user_id' => $recipientId,
				'event_type' => $eventType,
				'error' => $exception->getMessage(),
			]));
		}
	}
}

function fetchWorkspaceActivities($pdo, $user, $organizationId = null, $limit = 100) {
	$limit = max(1, min((int) $limit, 200));
	$organizationId = $organizationId ? (int) $organizationId : null;

	if ($organizationId && function_exists('userCanAccessOrganization') && !userCanAccessOrganization($pdo, $user, $organizationId)) {
		http_response_code(403);
		echo json_encode(['error' => 'Unauthorized to view this organization']);
		return null;
	}

	$canViewAll = $organizationId && function_exists('userCanManageOrganizationTasks') && userCanManageOrganizationTasks($pdo, $user, $organizationId);

	if ($organizationId) {
		if ($canViewAll) {
			$stmt = $pdo->prepare("\n				SELECT a.*, u.username AS user_name, u.email AS user_email\n				FROM activities a\n				LEFT JOIN users u ON u.id = a.user_id\n				WHERE a.organization_id = ?\n				ORDER BY a.created_at DESC\n				LIMIT ?\n			");
			$stmt->execute([$organizationId, $limit]);
		} else {
			$stmt = $pdo->prepare("\n				SELECT a.*, u.username AS user_name, u.email AS user_email\n				FROM activities a\n				LEFT JOIN users u ON u.id = a.user_id\n				WHERE a.organization_id = ? AND a.user_id = ?\n				ORDER BY a.created_at DESC\n				LIMIT ?\n			");
			$stmt->execute([$organizationId, (int) $user['id'], $limit]);
		}
	} else {
		$stmt = $pdo->prepare("\n			SELECT a.*, u.username AS user_name, u.email AS user_email\n			FROM activities a\n			LEFT JOIN users u ON u.id = a.user_id\n			WHERE a.user_id = ?\n			ORDER BY a.created_at DESC\n			LIMIT ?\n		");
		$stmt->execute([(int) $user['id'], $limit]);
	}

	return $stmt->fetchAll();
}