<?php
/**
 * Password Reset API
 * Endpoints: request-reset, verify-token, reset-password
 */

require_once '../config/cors.php';
require_once '../config/database.php';

// Include mailer functions
require_once 'mailer.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;

if ($method === 'POST') {
    switch ($action) {
        case 'request-reset':
            requestPasswordReset($pdo);
            break;
        case 'verify-token':
            verifyResetToken($pdo);
            break;
        case 'reset-password':
            resetPassword($pdo);
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
 * Request password reset
 */
function requestPasswordReset($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['email'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Email is required']);
        return;
    }
    
    $email = filter_var($input['email'], FILTER_SANITIZE_EMAIL);
    
    // Find user
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        // Don't reveal if email exists
        echo json_encode(['message' => 'If the email exists, a reset link has been sent']);
        return;
    }
    
    // Generate token
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO password_reset_tokens (user_id, token, expires_at)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$user['id'], $token, $expiresAt]);
        
        // Send email with reset code
        $emailResult = sendPasswordResetEmail($email, $token);
        
        if ($emailResult['success']) {
            echo json_encode([
                'message' => 'Reset link sent',
                'token' => $token // In production, send this via email only
            ]);
        } else {
            // If email fails, delete the token and return error
            $stmt = $pdo->prepare("DELETE FROM password_reset_tokens WHERE token = ?");
            $stmt->execute([$token]);
            
            http_response_code(500);
            echo json_encode(['error' => 'Failed to send reset email: ' . $emailResult['message']]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create reset token: ' . $e->getMessage()]);
    }
}

/**
 * Verify reset token
 */
function verifyResetToken($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['token'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Token is required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT id, user_id FROM password_reset_tokens 
            WHERE token = ? AND expires_at > NOW()
        ");
        $stmt->execute([$input['token']]);
        $resetToken = $stmt->fetch();
        
        if (!$resetToken) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid or expired token']);
            return;
        }
        
        echo json_encode([
            'message' => 'Token is valid',
            'user_id' => $resetToken['user_id']
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Token verification failed: ' . $e->getMessage()]);
    }
}

/**
 * Reset password
 */
function resetPassword($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['token']) || !isset($input['new_password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Token and new password are required']);
        return;
    }
    
    try {
        // Verify token
        $stmt = $pdo->prepare("
            SELECT user_id FROM password_reset_tokens 
            WHERE token = ? AND expires_at > NOW()
        ");
        $stmt->execute([$input['token']]);
        $resetToken = $stmt->fetch();
        
        if (!$resetToken) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid or expired token']);
            return;
        }
        
        // Update password
        $hashedPassword = password_hash($input['new_password'], PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashedPassword, $resetToken['user_id']]);
        
        // Delete used token
        $stmt = $pdo->prepare("DELETE FROM password_reset_tokens WHERE token = ?");
        $stmt->execute([$input['token']]);
        
        echo json_encode(['message' => 'Password reset successfully']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Password reset failed: ' . $e->getMessage()]);
    }
}
