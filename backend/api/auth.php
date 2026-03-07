<?php
/**
 * Authentication API
 * Handles login, registration, logout, password reset
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Handle different authentication actions
switch ($action) {
    case 'register':
        if ($method === 'POST') {
            handleRegister();
        }
        break;
    
    case 'login':
        if ($method === 'POST') {
            handleLogin();
        }
        break;
    
    case 'logout':
        if ($method === 'POST') {
            handleLogout();
        }
        break;
    
    case 'verify-email':
        if ($method === 'POST') {
            handleEmailVerification();
        }
        break;
    
    case 'forgot-password':
        if ($method === 'POST') {
            handleForgotPassword();
        }
        break;
    
    case 'reset-password':
        if ($method === 'POST') {
            handlePasswordReset();
        }
        break;
    
    case 'verify-code':
        if ($method === 'POST') {
            handleVerifyCode();
        }
        break;
    
    case 'user-info':
        if ($method === 'GET') {
            handleGetUserInfo();
        }
        break;
    
    default:
        sendError('Invalid authentication endpoint', 400);
}

/**
 * Handle user registration
 */
function handleRegister() {
    $input = getJsonInput();
    
    // Validate required fields
    $required = ['name', 'email', 'password', 'password_confirm', 'role'];
    $errors = validateRequired($input, $required);
    
    if (!empty($errors)) {
        sendError('Validation failed', 400, $errors);
    }
    
    // Validate email format
    if (!isValidEmail($input['email'])) {
        sendError('Invalid email format', 400);
    }
    
    // Validate password length
    if (strlen($input['password']) < PASSWORD_MIN_LENGTH) {
        sendError('Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters', 400);
    }
    
    // Check if passwords match
    if ($input['password'] !== $input['password_confirm']) {
        sendError('Passwords do not match', 400);
    }
    
    // Validate role
    if (!in_array($input['role'], ['admin', 'employee'])) {
        sendError('Invalid role specified', 400);
    }
    
    try {
        $pdo = getDatabase();
        
        // Check if user already exists
        $checkStmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $checkStmt->execute([$input['email']]);
        
        if ($checkStmt->fetch()) {
            sendError('Email already registered', 409);
        }
        
        // Hash password
        $hashedPassword = hashPassword($input['password']);
        
        // Insert user
        $insertStmt = $pdo->prepare('
            INSERT INTO users (name, email, password, role, status, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ');
        
        $insertStmt->execute([
            sanitizeString($input['name']),
            strtolower(trim($input['email'])),
            $hashedPassword,
            $input['role'],
            'active'
        ]);
        
        $userId = $pdo->lastInsertId();
        
        logAction('User registered', $userId, ['email' => $input['email'], 'role' => $input['role']]);
        
        // Generate session token
        $token = generateToken();
        $sessionStmt = $pdo->prepare('
            INSERT INTO sessions (user_id, token, ip_address, expires_at, created_at)
            VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), NOW())
        ');
        
        $sessionStmt->execute([
            $userId,
            $token,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            floor(SESSION_TIMEOUT / 60)
        ]);
        
        sendSuccess([
            'user_id' => $userId,
            'token' => $token,
            'role' => $input['role']
        ], 'Registration successful');
        
    } catch (Exception $e) {
        logAction('Registration error', null, ['error' => $e->getMessage()]);
        sendError('Registration failed', 500, APP_DEBUG ? $e->getMessage() : null);
    }
}

/**
 * Handle user login
 */
function handleLogin() {
    $input = getJsonInput();
    
    // Validate required fields
    $errors = validateRequired($input, ['email', 'password']);
    
    if (!empty($errors)) {
        sendError('Email and password are required', 400, $errors);
    }
    
    try {
        $pdo = getDatabase();
        
        // Fetch user by email
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? AND status = ?');
        $stmt->execute([strtolower(trim($input['email'])), 'active']);
        
        $user = $stmt->fetch();
        
        if (!$user || !verifyPassword($input['password'], $user['password'])) {
            logAction('Failed login attempt', null, ['email' => $input['email']]);
            sendError('Invalid email or password', 401);
        }
        
        // Generate session token
        $token = generateToken();
        $sessionStmt = $pdo->prepare('
            INSERT INTO sessions (user_id, token, ip_address, expires_at, created_at)
            VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), NOW())
        ');
        
        $sessionStmt->execute([
            $user['id'],
            $token,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            floor(SESSION_TIMEOUT / 60)
        ]);
        
        // Update last login (optional)
        $updateStmt = $pdo->prepare('UPDATE users SET updated_at = NOW() WHERE id = ?');
        $updateStmt->execute([$user['id']]);
        
        logAction('User login', $user['id'], ['email' => $user['email']]);
        
        sendSuccess([
            'user_id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'token' => $token
        ], 'Login successful');
        
    } catch (Exception $e) {
        logAction('Login error', null, ['email' => $input['email'] ?? 'unknown']);
        sendError('Login failed', 500, APP_DEBUG ? $e->getMessage() : null);
    }
}

/**
 * Handle user logout
 */
function handleLogout() {
    $token = getBearerToken();
    
    if (!$token) {
        sendError('No session token provided', 401);
    }
    
    try {
        $pdo = getDatabase();
        
        // Delete session
        $stmt = $pdo->prepare('DELETE FROM sessions WHERE token = ?');
        $stmt->execute([$token]);
        
        logAction('User logout');
        
        sendSuccess([], 'Logout successful');
        
    } catch (Exception $e) {
        sendError('Logout failed', 500);
    }
}

/**
 * Handle forgot password request
 */
function handleForgotPassword() {
    $input = getJsonInput();
    
    if (!isset($input['email']) || empty($input['email'])) {
        sendError('Email is required', 400);
    }
    
    try {
        $pdo = getDatabase();
        
        // Find user
        $stmt = $pdo->prepare('SELECT id, name, email FROM users WHERE email = ?');
        $stmt->execute([strtolower(trim($input['email']))]);
        
        $user = $stmt->fetch();
        
        if (!$user) {
            // Don't reveal if email exists (security)
            sendSuccess([], 'If email exists, password reset code has been sent');
        }
        
        // Generate reset token and 6-digit code
        $token = generateToken();
        $code = generateOTP();
        
        // Store reset token (valid for 10 minutes)
        $resetStmt = $pdo->prepare('
            INSERT INTO password_reset_tokens (user_id, token, code, expires_at, created_at)
            VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), NOW())
        ');
        
        $resetStmt->execute([$user['id'], $token, $code]);
        
        // Send email with reset code (using Resend API)
        sendPasswordResetEmail($user['email'], $user['name'], $code);
        
        logAction('Password reset requested', $user['id'], ['email' => $user['email']]);
        
        sendSuccess(['token' => $token], 'Password reset code sent to email');
        
    } catch (Exception $e) {
        sendError('Request failed', 500, APP_DEBUG ? $e->getMessage() : null);
    }
}

/**
 * Handle verify password reset code
 */
function handleVerifyCode() {
    $input = getJsonInput();
    
    $errors = validateRequired($input, ['token', 'code']);
    if (!empty($errors)) {
        sendError('Token and code are required', 400, $errors);
    }
    
    try {
        $pdo = getDatabase();
        
        // Verify token and code
        $stmt = $pdo->prepare('
            SELECT * FROM password_reset_tokens 
            WHERE token = ? AND code = ? AND expires_at > NOW()
        ');
        $stmt->execute([$input['token'], $input['code']]);
        
        $resetToken = $stmt->fetch();
        
        if (!$resetToken) {
            sendError('Invalid or expired code', 401);
        }
        
        sendSuccess(['valid' => true], 'Code verified');
        
    } catch (Exception $e) {
        sendError('Verification failed', 500);
    }
}

/**
 * Handle password reset
 */
function handlePasswordReset() {
    $input = getJsonInput();
    
    $errors = validateRequired($input, ['token', 'code', 'password', 'password_confirm']);
    if (!empty($errors)) {
        sendError('All fields are required', 400, $errors);
    }
    
    if ($input['password'] !== $input['password_confirm']) {
        sendError('Passwords do not match', 400);
    }
    
    if (strlen($input['password']) < PASSWORD_MIN_LENGTH) {
        sendError('Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters', 400);
    }
    
    try {
        $pdo = getDatabase();
        
        // Verify token and code
        $stmt = $pdo->prepare('
            SELECT user_id FROM password_reset_tokens 
            WHERE token = ? AND code = ? AND expires_at > NOW()
        ');
        $stmt->execute([$input['token'], $input['code']]);
        
        $resetToken = $stmt->fetch();
        
        if (!$resetToken) {
            sendError('Invalid or expired reset token', 401);
        }
        
        // Update password
        $hashedPassword = hashPassword($input['password']);
        $updateStmt = $pdo->prepare('UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?');
        $updateStmt->execute([$hashedPassword, $resetToken['user_id']]);
        
        // Delete used reset token
        $deleteStmt = $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = ?');
        $deleteStmt->execute([$resetToken['user_id']]);
        
        // Invalidate all sessions for this user (security)
        $invalidateStmt = $pdo->prepare('DELETE FROM sessions WHERE user_id = ?');
        $invalidateStmt->execute([$resetToken['user_id']]);
        
        logAction('Password reset', $resetToken['user_id']);
        
        sendSuccess([], 'Password reset successfully');
        
    } catch (Exception $e) {
        sendError('Password reset failed', 500);
    }
}

/**
 * Get current user info
 */
function handleGetUserInfo() {
    $user = getCurrentUser();
    
    if (!$user) {
        sendError('Not authenticated', 401);
    }
    
    sendSuccess([
        'user_id' => $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
        'department' => $user['department'] ?? null,
        'phone' => $user['phone'] ?? null,
        'created_at' => $user['created_at']
    ]);
}

/**
 * Send password reset email via Resend API
 */
function sendPasswordResetEmail($email, $name, $code) {
    if (empty(RESEND_API_KEY)) {
        // If Resend API not configured, log to console
        error_log("Password reset code for $email: $code");
        return;
    }
    
    try {
        $payload = [
            'from' => 'noreply@taskflow.app',
            'to' => $email,
            'subject' => 'Password Reset Code',
            'html' => "
                <h2>Password Reset Request</h2>
                <p>Hi $name,</p>
                <p>Your password reset code is:</p>
                <h1 style='font-size: 32px; letter-spacing: 5px;'>$code</h1>
                <p>This code expires in 10 minutes.</p>
                <p>If you didn't request this, please ignore this email.</p>
            "
        ];
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.resend.com/emails',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . RESEND_API_KEY,
                'Content-Type: application/json'
            ],
        ]);
        
        $response = curl_exec($curl);
        curl_close($curl);
        
        error_log('Password reset email sent to: ' . $email);
    } catch (Exception $e) {
        error_log('Failed to send password reset email: ' . $e->getMessage());
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
