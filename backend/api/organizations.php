<?php
/**
 * Organizations API
 * Endpoints: GET, POST
 */

require_once '../config/cors.php';
require_once '../config/database.php';
require_once '../middleware/auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;
$user = getCurrentUser($pdo);

function logOrganizationDebug($event, $user, $organizationId = null, $payload = null) {
	error_log('[organizations] ' . $event . ' userId=' . ($user['id'] ?? 'unknown') . ' role=' . ($user['role'] ?? 'unknown') . ' orgId=' . ($organizationId ?? 'null') . ' payload=' . json_encode($payload));
}

logOrganizationDebug('request', $user, $_GET['organization_id'] ?? null, [
	'method' => $method,
	'action' => $action,
]);

if ($method === 'GET') {
	getOrganizations($pdo, $user);
} elseif ($method === 'POST' && $action === 'add-member') {
	addOrganizationMember($pdo, $user);
} elseif ($method === 'POST' && $action === 'remove-member') {
	removeOrganizationMember($pdo, $user);
} elseif ($method === 'POST' && $action === 'update-name') {
	updateOrganizationName($pdo, $user);
} elseif ($method === 'POST') {
	createOrganization($pdo, $user);
} else {
	http_response_code(405);
	echo json_encode(['error' => 'Method not allowed']);
}

function createOrganization($pdo, $user) {
	$input = json_decode(file_get_contents('php://input'), true);
	if (!is_array($input)) {
		$input = [];
	}

	logOrganizationDebug('createOrganization.attempt', $user, null, $input);

	if (!isSuperAdminEmail($user['email'])) {
		logOrganizationDebug('createOrganization.denied_not_super_admin_email', $user, null, $input);
		http_response_code(403);
		echo json_encode(['error' => 'Only the super admin can create organizations']);
		return;
	}

	$name = trim($input['name'] ?? '');
	if ($name === '') {
		http_response_code(400);
		echo json_encode(['error' => 'Organization name is required']);
		return;
	}

	try {
		$stmt = $pdo->prepare("INSERT INTO organizations (name, created_by) VALUES (?, ?)");
		$stmt->execute([$name, $user['id']]);
		$organizationId = $pdo->lastInsertId();

		$memberStmt = $pdo->prepare("INSERT INTO organization_members (user_id, organization_id, role) VALUES (?, ?, ?)");
		$memberStmt->execute([$user['id'], $organizationId, 'organization_admin']);

		logOrganizationDebug('createOrganization.success', $user, $organizationId, [
			'name' => $name,
		]);

		http_response_code(201);
		echo json_encode([
			'message' => 'Organization created successfully',
			'organization_id' => $organizationId,
			'organization' => [
				'id' => (int) $organizationId,
				'name' => $name,
				'created_by' => (int) $user['id'],
				'created_at' => date('Y-m-d H:i:s'),
			],
		]);
	} catch (PDOException $exception) {
		logOrganizationDebug('createOrganization.error', $user, null, [
			'error' => $exception->getMessage(),
			'name' => $name,
		]);
		http_response_code(500);
		echo json_encode(['error' => 'Failed to create organization: ' . $exception->getMessage()]);
	}
}

function getOrganizations($pdo, $user) {
	try {
		logOrganizationDebug('getOrganizations.start', $user);

		if (isSuperAdminEmail($user['email'])) {
			$stmt = $pdo->prepare("
				SELECT o.*, creator.username AS created_by_name
				FROM organizations o
				LEFT JOIN users creator ON creator.id = o.created_by
				ORDER BY o.created_at DESC
			");
			$stmt->execute();
		} else {
			$stmt = $pdo->prepare("
				SELECT o.*, om.role AS membership_role, creator.username AS created_by_name
				FROM organizations o
				INNER JOIN organization_members om ON om.organization_id = o.id
				LEFT JOIN users creator ON creator.id = o.created_by
				WHERE om.user_id = ?
				ORDER BY o.created_at DESC
			");
			$stmt->execute([$user['id']]);
		}

		$organizations = $stmt->fetchAll();
		$membersStmt = $pdo->prepare("
			SELECT om.organization_id, om.user_id, om.role, u.email, u.username
			FROM organization_members om
			INNER JOIN users u ON u.id = om.user_id
			WHERE om.organization_id = ?
			ORDER BY u.username ASC, u.email ASC
		");

		foreach ($organizations as &$organization) {
			$membersStmt->execute([$organization['id']]);
			$organization['members'] = $membersStmt->fetchAll();
		}
		unset($organization);

		logOrganizationDebug('getOrganizations.success', $user, null, [
			'count' => count($organizations),
		]);

		echo json_encode($organizations);
	} catch (PDOException $exception) {
		logOrganizationDebug('getOrganizations.error', $user, null, [
			'error' => $exception->getMessage(),
		]);
		http_response_code(500);
		echo json_encode(['error' => 'Failed to fetch organizations: ' . $exception->getMessage()]);
	}
}

function addOrganizationMember($pdo, $user) {
	$input = json_decode(file_get_contents('php://input'), true);
	if (!is_array($input)) {
		$input = [];
	}

	$organizationId = (int) ($input['organization_id'] ?? 0);
	$userId = !empty($input['user_id']) ? (int) $input['user_id'] : null;
	$userEmail = trim($input['email'] ?? '');
	$role = $input['role'] ?? 'member';

	logOrganizationDebug('addOrganizationMember.attempt', $user, $organizationId, $input);

	if ($organizationId <= 0 || !$userId && $userEmail === '') {
		http_response_code(400);
		echo json_encode(['error' => 'Organization ID and user identifier are required']);
		return;
	}

	if (!in_array($role, ['organization_admin', 'member', 'client'], true)) {
		http_response_code(400);
		echo json_encode(['error' => 'Invalid organization role']);
		return;
	}

	if (!userCanManageOrganizationTasks($pdo, $user, $organizationId)) {
		logOrganizationDebug('addOrganizationMember.denied_not_org_admin', $user, $organizationId, $input);
		http_response_code(403);
		echo json_encode(['error' => 'Only organization admins can add members']);
		return;
	}

	try {
		if (!$userId && $userEmail !== '') {
			$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
			$stmt->execute([$userEmail]);
			$foundUser = $stmt->fetch();

			if (!$foundUser) {
				http_response_code(404);
				echo json_encode(['error' => 'User not found']);
				return;
			}

			$userId = (int) $foundUser['id'];
		}

		$insert = $pdo->prepare("
			INSERT INTO organization_members (user_id, organization_id, role)
			VALUES (?, ?, ?)
			ON CONFLICT (user_id, organization_id)
			DO UPDATE SET role = EXCLUDED.role
		");
		$insert->execute([$userId, $organizationId, $role]);

		logOrganizationDebug('addOrganizationMember.success', $user, $organizationId, [
			'user_id' => $userId,
			'role' => $role,
		]);

		echo json_encode([
			'message' => 'Organization member saved successfully',
			'organization_id' => $organizationId,
			'user_id' => $userId,
			'role' => $role,
		]);
	} catch (PDOException $exception) {
		logOrganizationDebug('addOrganizationMember.error', $user, $organizationId, [
			'error' => $exception->getMessage(),
		]);
		http_response_code(500);
		echo json_encode(['error' => 'Failed to save organization member: ' . $exception->getMessage()]);
	}
}

function removeOrganizationMember($pdo, $user) {
	$input = json_decode(file_get_contents('php://input'), true);
	if (!is_array($input)) {
		$input = [];
	}

	$organizationId = (int) ($input['organization_id'] ?? 0);
	$userId = (int) ($input['user_id'] ?? 0);

	logOrganizationDebug('removeOrganizationMember.attempt', $user, $organizationId, $input);

	if ($organizationId <= 0 || $userId <= 0) {
		http_response_code(400);
		echo json_encode(['error' => 'Organization ID and user ID are required']);
		return;
	}

	if (!userCanManageOrganizationTasks($pdo, $user, $organizationId)) {
		logOrganizationDebug('removeOrganizationMember.denied_not_org_admin', $user, $organizationId, $input);
		http_response_code(403);
		echo json_encode(['error' => 'Only organization admins can remove members']);
		return;
	}

	if ((int) $user['id'] === $userId) {
		http_response_code(400);
		echo json_encode(['error' => 'Admins cannot remove themselves']);
		return;
	}

	try {
		$stmt = $pdo->prepare("DELETE FROM organization_members WHERE organization_id = ? AND user_id = ?");
		$stmt->execute([$organizationId, $userId]);

		if ($stmt->rowCount() === 0) {
			http_response_code(404);
			echo json_encode(['error' => 'Member not found in this organization']);
			return;
		}

		logOrganizationDebug('removeOrganizationMember.success', $user, $organizationId, [
			'user_id' => $userId,
		]);

		echo json_encode([
			'message' => 'Organization member removed successfully',
			'organization_id' => $organizationId,
			'user_id' => $userId,
		]);
	} catch (PDOException $exception) {
		logOrganizationDebug('removeOrganizationMember.error', $user, $organizationId, [
			'error' => $exception->getMessage(),
		]);
		http_response_code(500);
		echo json_encode(['error' => 'Failed to remove organization member: ' . $exception->getMessage()]);
	}
}

function updateOrganizationName($pdo, $user) {
	$input = json_decode(file_get_contents('php://input'), true);
	if (!is_array($input)) {
		$input = [];
	}

	$organizationId = (int) ($input['organization_id'] ?? 0);
	$name = trim($input['name'] ?? '');

	logOrganizationDebug('updateOrganizationName.attempt', $user, $organizationId, $input);

	if ($organizationId <= 0 || $name === '') {
		http_response_code(400);
		echo json_encode(['error' => 'Organization ID and name are required']);
		return;
	}

	if (!userCanManageOrganizationTasks($pdo, $user, $organizationId)) {
		logOrganizationDebug('updateOrganizationName.denied_not_org_admin', $user, $organizationId, $input);
		http_response_code(403);
		echo json_encode(['error' => 'Only organization admins can update organization settings']);
		return;
	}

	try {
		$stmt = $pdo->prepare("UPDATE organizations SET name = ? WHERE id = ?");
		$stmt->execute([$name, $organizationId]);

		if ($stmt->rowCount() === 0) {
			http_response_code(404);
			echo json_encode(['error' => 'Organization not found']);
			return;
		}

		logOrganizationDebug('updateOrganizationName.success', $user, $organizationId, [
			'name' => $name,
		]);

		echo json_encode([
			'message' => 'Organization updated successfully',
			'organization_id' => $organizationId,
			'name' => $name,
		]);
	} catch (PDOException $exception) {
		logOrganizationDebug('updateOrganizationName.error', $user, $organizationId, [
			'error' => $exception->getMessage(),
		]);
		http_response_code(500);
		echo json_encode(['error' => 'Failed to update organization: ' . $exception->getMessage()]);
	}
}