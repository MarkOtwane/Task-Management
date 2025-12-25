<?php
/**
 * User Authentication API
 * Endpoints: register, login, logout
 */

require_once '../config/cors.php';
require_once '../config/database.php';
require_once '../middleware/auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'register':
            registerUser($pdo, $input);
            break;
        case 'login':
            loginUser($pdo, $input);
            break;
        case 'logout':
            logoutUser();
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

/**
 * Register new user
 */
function registerUser($pdo, $input) {
    if (!isset($input['email']) || !isset($input['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Email and password required']);
        return;
    }
    
    $email = filter_var($input['email'], FILTER_SANITIZE_EMAIL);
    $password = $input['password'];
    $username = $input['username'] ?? $email;
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid email format']);
        return;
    }
    
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'Email already registered']);
        return;
    }
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    
    // Insert user
    try {
        $stmt = $pdo->prepare("
            INSERT INTO users (email, password, username) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$email, $hashedPassword, $username]);
        
        $userId = $pdo->lastInsertId();
        
        $_SESSION['user_id'] = $userId;
        $tokenData = generateJWT($userId);
        
        http_response_code(201);
        echo json_encode([
            'message' => 'User registered successfully',
            'user_id' => $userId,
            'email' => $email,
            'token' => $tokenData['token']
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Registration failed: ' . $e->getMessage()]);
    }
}

/**
 * Login user
 */
function loginUser($pdo, $input) {
    if (!isset($input['email']) || !isset($input['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Email and password required']);
        return;
    }
    
    $email = filter_var($input['email'], FILTER_SANITIZE_EMAIL);
    $password = $input['password'];
    
    // Fetch user
    $stmt = $pdo->prepare("SELECT id, password FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($password, $user['password'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid credentials']);
        return;
    }
    
    $_SESSION['user_id'] = $user['id'];
    $tokenData = generateJWT($user['id']);
    
    echo json_encode([
        'message' => 'Login successful',
        'user_id' => $user['id'],
        'email' => $email,
        'token' => $tokenData['token']
    ]);
}

/**
 * Logout user
 */
function logoutUser() {
    session_destroy();
    echo json_encode(['message' => 'Logged out successfully']);
}
