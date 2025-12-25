<?php
/**
 * Authentication Middleware
 */

// Ensure session cookie allows cross-site requests from the frontend
// when using credentialed fetch requests. Set SameSite=None and Secure
// for HTTPS deployments. These options require PHP 7.3+.
if (PHP_VERSION_ID >= 70300) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? '') == 443;
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'None'
    ]);
} else {
    // Fallback for older PHP versions: set cookie params without samesite
    session_set_cookie_params(0, '/', '', true, true);
}

session_start();

/**
 * Verify JWT token or session
 * Returns user_id if valid, false otherwise
 */
function verifyAuth() {
    // Check for JWT token in Authorization header
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        $token = str_replace('Bearer ', '', $headers['Authorization']);
        $userId = verifyJWT($token);
        if ($userId) {
            error_log('JWT auth successful for user: ' . $userId);
            return $userId;
        } else {
            error_log('JWT auth failed for token: ' . substr($token, 0, 20) . '...');
        }
    }
    
    // Check for session
    if (isset($_SESSION['user_id'])) {
        error_log('Session auth successful for user: ' . $_SESSION['user_id']);
        return $_SESSION['user_id'];
    }
    
    error_log('No valid authentication found');
    return false;
}

/**
 * Verify JWT token
 * Returns user_id if valid, false otherwise
 */
function verifyJWT($token) {
	$parts = explode('.', $token);
	if (count($parts) !== 3) {
		error_log('Invalid JWT token format: ' . $token);
		return false;
	}
	
	list($header, $payload, $signature) = $parts;
	
	try {
		// Decode payload
		$payloadData = json_decode(base64_decode(str_pad($payload, strlen($payload) % 4, '=', STR_PAD_RIGHT)), true);
		
		// Check if payload is valid
		if (!$payloadData || !isset($payloadData['user_id'])) {
			error_log('Invalid JWT payload: ' . json_encode($payloadData));
			return false;
		}
		
		// Check expiration
		if (isset($payloadData['exp']) && $payloadData['exp'] < time()) {
			error_log('JWT token expired: ' . $payloadData['exp'] . ' < ' . time());
			return false;
		}
		
		// Verify signature using a simple HMAC approach
		$secret = getenv('JWT_SECRET') ?: 'default-secret-key-change-in-production';
		$expectedSignature = hash_hmac('sha256', $header . '.' . $payload, $secret, true);
		$expectedSignature = base64_encode($expectedSignature);
		
		if (!hash_equals($expectedSignature, $signature)) {
			error_log('Invalid JWT signature: expected ' . $expectedSignature . ', got ' . $signature);
			return false;
		}
		
		error_log('JWT verification successful for user: ' . $payloadData['user_id']);
		return $payloadData['user_id'];
	} catch (Exception $e) {
		error_log('JWT verification error: ' . $e->getMessage());
		return false;
	}
}

/**
 * Generate JWT token
 * Returns token array with token string
 */
function generateJWT($userId) {
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $payload = json_encode([
        'user_id' => (int)$userId,
        'exp' => time() + (24 * 60 * 60), // 24 hours
        'iat' => time()
    ]);
    
    $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    
    // Create signature using HMAC
    $secret = getenv('JWT_SECRET') ?: 'default-secret-key-change-in-production';
    $signature = hash_hmac('sha256', $base64Header . '.' . $base64Payload, $secret, true);
    $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    
    $token = $base64Header . '.' . $base64Payload . '.' . $base64Signature;
    
    error_log('JWT generated successfully for user: ' . $userId);
    return ['token' => $token];
}

/**
 * Require authentication
 */
function requireAuth() {
    $userId = verifyAuth();
    if (!$userId) {
        error_log('Authentication failed - no valid session or token');
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized - Please log in again']);
        exit;
    }
    error_log('Authentication successful for user: ' . $userId);
    return $userId;
}
