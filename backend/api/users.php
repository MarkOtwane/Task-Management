<?php
/**
 * Users API
 * Handles user management (Admin only)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';
$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// User must be authenticated
$user = getCurrentUser();
if (!$user) {
    sendError('Not authenticated', 401);
}

switch ($action) {
    case 'list':
        if ($method === 'GET') {
            handleListUsers();
        }
        break;
    
    case 'employees':
        if ($method === 'GET') {
            handleListEmployees();
        }
        break;
    
    case 'get':
        if ($method === 'GET' && $userId) {
            handleGetUser($userId);
        }
        break;
    
    case 'update':
        if ($method === 'POST' && $userId) {
            handleUpdateUser($userId);
        }
        break;
    
    case 'create-employee':
        if ($method === 'POST') {
            handleCreateEmployee();
        }
        break;
    
    case 'toggle-status':
        if ($method === 'POST' && $userId) {
            handleToggleUserStatus($userId);
        }
        break;
    
    case 'profile':
        if ($method === 'GET') {
            handleGetUserProfile();
        }
        break;
    
    default:
        sendError('Invalid user endpoint', 400);
}

/**
 * List all users (Admin only)
 */
function handleListUsers() {
    global $user;
    
    if ($user['role'] !== 'admin') {
        sendError('Only admins can view all users', 403);
    }
    
    try {
        $pdo = getDatabase();
        
        $stmt = $pdo->prepare('
            SELECT id, name, email, role, department, status, created_at
            FROM users
            ORDER BY name ASC
        ');
        $stmt->execute();
        
        $users = $stmt->fetchAll();
        
        sendSuccess(['users' => $users]);
        
    } catch (Exception $e) {
        sendError('Failed to retrieve users', 500);
    }
}

/**
 * List all employees (Admin only)
 */
function handleListEmployees() {
    global $user;
    
    if ($user['role'] !== 'admin') {
        sendError('Only admins can view employees', 403);
    }
    
    try {
        $pdo = getDatabase();
        
        $stmt = $pdo->prepare('
            SELECT 
                u.id, u.name, u.email, u.department, u.status, u.created_at,
                COUNT(DISTINCT t.id) as total_tasks_assigned,
                COUNT(DISTINCT CASE WHEN t.status = "completed" THEN t.id END) as completed_tasks
            FROM users u
            LEFT JOIN tasks t ON u.id = t.assigned_to
            WHERE u.role = "employee"
            GROUP BY u.id, u.name, u.email, u.department, u.status, u.created_at
            ORDER BY u.name ASC
        ');
        $stmt->execute();
        
        $employees = $stmt->fetchAll();
        
        sendSuccess(['employees' => $employees]);
        
    } catch (Exception $e) {
        sendError('Failed to retrieve employees', 500);
    }
}

/**
 * Get single user info
 */
function handleGetUser($userId) {
    global $user;
    
    // Users can view their own profile, admins can view anyone
    if ($user['id'] !== $userId && $user['role'] !== 'admin') {
        sendError('Unauthorized', 403);
    }
    
    try {
        $pdo = getDatabase();
        
        $stmt = $pdo->prepare('SELECT id, name, email, role, department, phone, status, created_at FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        
        $userData = $stmt->fetch();
        
        if (!$userData) {
            sendError('User not found', 404);
        }
        
        sendSuccess(['user' => $userData]);
        
    } catch (Exception $e) {
        sendError('Failed to retrieve user', 500);
    }
}

/**
 * Update user info (Admin only or self)
 */
function handleUpdateUser($userId) {
    global $user;
    
    // Users can update their own profile, admins can update anyone
    if ($user['id'] !== $userId && $user['role'] !== 'admin') {
        sendError('Unauthorized', 403);
    }
    
    $input = getJsonInput();
    
    try {
        $pdo = getDatabase();
        
        // Build update query
        $updates = [];
        $params = [];
        
        $allowedFields = ['name', 'email', 'department', 'phone'];
        
        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $updates[] = "$field = ?";
                $params[] = $field === 'email' ? strtolower(trim($input[$field])) : $input[$field];
            }
        }
        
        if (empty($updates)) {
            sendError('No fields to update', 400);
        }
        
        // Email uniqueness check
        if (isset($input['email'])) {
            $emailCheck = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
            $emailCheck->execute([strtolower(trim($input['email'])), $userId]);
            
            if ($emailCheck->fetch()) {
                sendError('Email already in use', 409);
            }
        }
        
        $updates[] = "updated_at = NOW()";
        $params[] = $userId;
        
        $stmt = $pdo->prepare('UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?');
        $stmt->execute($params);
        
        logAction('User updated', $user['id'], ['updated_user_id' => $userId]);
        
        sendSuccess([], 'User updated successfully');
        
    } catch (Exception $e) {
        sendError('Failed to update user', 500);
    }
}

/**
 * Create new employee (Admin only)
 */
function handleCreateEmployee() {
    global $user;
    
    if ($user['role'] !== 'admin') {
        sendError('Only admins can create employees', 403);
    }
    
    $input = getJsonInput();
    
    $required = ['name', 'email'];
    $errors = validateRequired($input, $required);
    
    if (!empty($errors)) {
        sendError('Validation failed', 400, $errors);
    }
    
    if (!isValidEmail($input['email'])) {
        sendError('Invalid email format', 400);
    }
    
    try {
        $pdo = getDatabase();
        
        // Check if email already exists
        $checkStmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $checkStmt->execute([$input['email']]);
        
        if ($checkStmt->fetch()) {
            sendError('Email already registered', 409);
        }
        
        // Generate temporary password
        $tempPassword = bin2hex(random_bytes(6));
        $hashedPassword = hashPassword($tempPassword);
        
        // Insert employee
        $insertStmt = $pdo->prepare('
            INSERT INTO users (name, email, password, role, department, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ');
        
        $insertStmt->execute([
            sanitizeString($input['name']),
            strtolower(trim($input['email'])),
            $hashedPassword,
            'employee',
            isset($input['department']) ? sanitizeString($input['department']) : null,
            'active'
        ]);
        
        $employeeId = $pdo->lastInsertId();
        
        // Send welcome email with temporary password
        sendWelcomeEmail($input['email'], $input['name'], $tempPassword);
        
        logAction('Employee created', $user['id'], ['employee_id' => $employeeId, 'email' => $input['email']]);
        
        sendSuccess(['employee_id' => $employeeId], 'Employee created successfully. Welcome email sent.');
        
    } catch (Exception $e) {
        sendError('Failed to create employee', 500, APP_DEBUG ? $e->getMessage() : null);
    }
}

/**
 * Toggle user status (Admin only)
 */
function handleToggleUserStatus($userId) {
    global $user;
    
    if ($user['role'] !== 'admin') {
        sendError('Only admins can change user status', 403);
    }
    
    $input = getJsonInput();
    
    if (!isset($input['status']) || !in_array($input['status'], ['active', 'inactive'])) {
        sendError('Invalid status', 400);
    }
    
    try {
        $pdo = getDatabase();
        
        $stmt = $pdo->prepare('UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$input['status'], $userId]);
        
        logAction('User status changed', $user['id'], ['user_id' => $userId, 'status' => $input['status']]);
        
        sendSuccess([], 'User status updated');
        
    } catch (Exception $e) {
        sendError('Failed to update user status', 500);
    }
}

/**
 * Get current user profile
 */
function handleGetUserProfile() {
    global $user;
    
    try {
        sendSuccess([
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
                'department' => $user['department'] ?? null,
                'phone' => $user['phone'] ?? null,
                'created_at' => $user['created_at']
            ]
        ]);
        
    } catch (Exception $e) {
        sendError('Failed to retrieve profile', 500);
    }
}

/**
 * Send welcome email to new employee
 */
function sendWelcomeEmail($email, $name, $tempPassword) {
    if (empty(RESEND_API_KEY)) {
        error_log("Employee welcome email for $email - Temp password: $tempPassword");
        return;
    }
    
    try {
        $payload = [
            'from' => 'noreply@taskflow.app',
            'to' => $email,
            'subject' => 'Welcome to TaskFlow',
            'html' => "
                <h2>Welcome to TaskFlow</h2>
                <p>Hi $name,</p>
                <p>Your account has been created by an administrator.</p>
                <p><strong>Temporary Password:</strong> <code>$tempPassword</code></p>
                <p>Please log in and change your password immediately.</p>
                <p>
                    <a href='http://localhost/#login' style='background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>
                        Login to TaskFlow
                    </a>
                </p>
                <p>If you have any questions, please contact your administrator.</p>
            "
        ];
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.resend.com/emails',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . RESEND_API_KEY,
                'Content-Type: application/json'
            ],
        ]);
        
        $response = curl_exec($curl);
        curl_close($curl);
        
        error_log("Welcome email sent to: $email");
    } catch (Exception $e) {
        error_log("Failed to send welcome email: " . $e->getMessage());
    }
}

?>
