<?php
/**
 * Delete Contact API (Personal Mode Only)
 * Delete a contact for the authenticated user
 */

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$user = getCurrentUser($pdo);

if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($method === 'DELETE') {
    deleteContact($pdo, $user);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

/**
 * Delete contact
 */
function deleteContact($pdo, $user) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate input
    if (!isset($input['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'ID is required']);
        return;
    }
    
    $contactId = (int)$input['id'];
    
    try {
        // Ensure contacts table exists
        ensureContactsTableExists($pdo);
        
        // Verify contact belongs to user
        $stmt = $pdo->prepare("SELECT id FROM contacts WHERE id = ? AND user_id = ?");
        $stmt->execute([$contactId, $user['id']]);
        
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => 'Contact not found']);
            return;
        }
        
        // Delete contact
        $stmt = $pdo->prepare("DELETE FROM contacts WHERE id = ? AND user_id = ?");
        $stmt->execute([$contactId, $user['id']]);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Contact deleted successfully'
        ]);
    } catch (PDOException $e) {
        error_log('[contacts.delete] Error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete contact']);
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
?>
