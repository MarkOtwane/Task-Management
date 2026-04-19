<?php
/**
 * Get Contacts API (Personal Mode Only)
 * Lists all contacts for the authenticated user
 */

require_once '../config/cors.php';
require_once '../config/database.php';
require_once '../middleware/auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$user = getCurrentUser($pdo);

if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($method === 'GET') {
    getContacts($pdo, $user);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

/**
 * Get all contacts for user
 */
function getContacts($pdo, $user) {
    try {
        // Ensure contacts table exists
        ensureContactsTableExists($pdo);
        
        $stmt = $pdo->prepare("
            SELECT id, user_id, name, phone, created_at, updated_at
            FROM contacts
            WHERE user_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$user['id']]);
        $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $contacts,
            'count' => count($contacts)
        ]);
    } catch (PDOException $e) {
        error_log('[contacts.get] Error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch contacts']);
    }
}

/**
 * Ensure contacts table exists
 */
function ensureContactsTableExists($pdo) {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS contacts (
                id SERIAL PRIMARY KEY,
                user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                name VARCHAR(255) NOT NULL,
                phone VARCHAR(20) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Create indexes
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_contacts_user_id ON contacts(user_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_contacts_user_name ON contacts(user_id, name)");
    } catch (PDOException $e) {
        error_log('[contacts.create_table] Error: ' . $e->getMessage());
    }
}

/**
 * Get current user from JWT or session
 */
function getCurrentUser($pdo) {
    $userId = verifyAuth();
    if (!$userId) {
        return null;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT id, email, role FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return null;
    }
}
?>
