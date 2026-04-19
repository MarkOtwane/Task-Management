<?php
/**
 * Update Contact API (Personal Mode Only)
 * Update an existing contact for the authenticated user
 */

require_once '../../config/cors.php';
require_once '../../config/database.php';
require_once '../../middleware/auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$user = getCurrentUser($pdo);

if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($method === 'PUT') {
    updateContact($pdo, $user);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

/**
 * Update existing contact
 */
function updateContact($pdo, $user) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate input
    if (!isset($input['id']) || !isset($input['name']) || !isset($input['phone'])) {
        http_response_code(400);
        echo json_encode(['error' => 'ID, name, and phone are required']);
        return;
    }
    
    $contactId = (int)$input['id'];
    $name = trim($input['name']);
    $phone = trim($input['phone']);
    
    // Validate name
    if (empty($name) || strlen($name) > 255) {
        http_response_code(400);
        echo json_encode(['error' => 'Name must be between 1 and 255 characters']);
        return;
    }
    
    // Validate phone
    if (empty($phone) || strlen($phone) > 20) {
        http_response_code(400);
        echo json_encode(['error' => 'Phone must be between 1 and 20 characters']);
        return;
    }
    
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
        
        // Update contact
        $stmt = $pdo->prepare("
            UPDATE contacts
            SET name = ?, phone = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ? AND user_id = ?
        ");
        
        $stmt->execute([$name, $phone, $contactId, $user['id']]);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Contact updated successfully',
            'id' => $contactId,
            'name' => $name,
            'phone' => $phone
        ]);
    } catch (PDOException $e) {
        error_log('[contacts.update] Error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update contact']);
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
