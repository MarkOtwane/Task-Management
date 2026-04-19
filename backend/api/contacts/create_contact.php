<?php
/**
 * Create Contact API (Personal Mode Only)
 * Add a new contact for the authenticated user
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

if ($method === 'POST') {
    createContact($pdo, $user);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

/**
 * Create new contact
 */
function createContact($pdo, $user) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate input
    if (!isset($input['name']) || !isset($input['phone'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Name and phone are required']);
        return;
    }
    
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
        
        $stmt = $pdo->prepare("
            INSERT INTO contacts (user_id, name, phone, created_at, updated_at)
            VALUES (?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        
        $stmt->execute([$user['id'], $name, $phone]);
        $contactId = $pdo->lastInsertId();
        
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Contact added successfully',
            'id' => (int)$contactId,
            'name' => $name,
            'phone' => $phone
        ]);
    } catch (PDOException $e) {
        error_log('[contacts.create] Error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create contact']);
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
