<?php
/**
 * Activities API
 */

require_once '../config/cors.php';
require_once '../config/database.php';
require_once '../middleware/auth.php';
require_once __DIR__ . '/activity_helpers.php';

$user = getCurrentUser($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
	http_response_code(405);
	echo json_encode(['error' => 'Method not allowed']);
	exit;
}

$organizationId = !empty($_GET['organization_id']) ? (int) $_GET['organization_id'] : null;
$limit = !empty($_GET['limit']) ? (int) $_GET['limit'] : 100;

$activities = fetchWorkspaceActivities($pdo, $user, $organizationId, $limit);
if ($activities === null) {
	exit;
}

echo json_encode(['activities' => $activities]);