<?php
/**
 * Task Management System - SMTP Mailer Service
 * Handles all email sending via SMTP
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ===== SMTP CONFIGURATION =====
// Load environment variables from .env if it exists
$envPath = __DIR__ . '/../../backend/.env';
if (file_exists($envPath)) {
    // Use custom parser to handle comments and special characters properly
    $envContent = file_get_contents($envPath);
    $lines = explode("\n", $envContent);
    
    foreach ($lines as $line) {
        $line = trim($line);
        // Skip empty lines and comments
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        // Parse key=value pairs
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            // Set as environment variable for getenv() to work
            putenv("$key=" . trim($value));
        }
    }
}

// Environment variables loaded successfully

// Update these with your SMTP settings
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_PORT', getenv('SMTP_PORT') ?: 587);
define('SMTP_USER', getenv('SMTP_USER') ?: '');
define('SMTP_PASS', getenv('SMTP_PASS') ?: '');
define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: 'noreply@taskmanagement.com');
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'TaskFlow');

/**
 * Send email via SMTP
 * 
 * @param string $toEmail Recipient email
 * @param string $subject Email subject
 * @param string $htmlBody HTML email body
 * @param string $textBody Plain text email body (optional)
 * @return array Response with success status and message
 */
function sendEmail($toEmail, $subject, $htmlBody, $textBody = '') {
    // Validate email
    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return [
            'success' => false,
            'message' => 'Invalid recipient email address'
        ];
    }

    // Check if SMTP is configured
    if (empty(SMTP_USER) || empty(SMTP_PASS)) {
        return [
            'success' => false,
            'message' => 'SMTP not configured. Please set environment variables.'
        ];
    }

    try {
        // Create connection
        $socket = fsockopen(SMTP_HOST, SMTP_PORT, $errno, $errstr, 10);
         
        if (!$socket) {
            throw new Exception("Failed to connect to SMTP server: $errstr ($errno)");
        }

        // Read server response
        $response = fgets($socket);
        if (strpos($response, '220') === false) {
            throw new Exception("Invalid SMTP response: $response");
        }

        // Send EHLO
        fputs($socket, "EHLO " . gethostname() . "\r\n");
        // Read all EHLO responses (multi-line)
        while (($response = fgets($socket)) && strpos($response, '250') === 0) {
            if (strpos($response, '250 ') === 0) break; // End of EHLO response
        }

        // Start TLS
        fputs($socket, "STARTTLS\r\n");
        $response = fgets($socket);
        if (strpos($response, '220') === false) {
            throw new Exception("STARTTLS failed: $response");
        }

        // Enable encryption
        stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);

        // Re-send EHLO after TLS
        fputs($socket, "EHLO " . gethostname() . "\r\n");
        // Read all EHLO responses (multi-line)
        while (($response = fgets($socket)) && strpos($response, '250') === 0) {
            if (strpos($response, '250 ') === 0) break; // End of EHLO response
        }

        // Authenticate
        fputs($socket, "AUTH LOGIN\r\n");
        fgets($socket);
         
        fputs($socket, base64_encode(SMTP_USER) . "\r\n");
        fgets($socket);
         
        fputs($socket, base64_encode(SMTP_PASS) . "\r\n");
        $response = fgets($socket);
         
        if (strpos($response, '235') === false) {
            throw new Exception("Authentication failed: $response");
        }

        // Build email headers
        $headers = "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">\r\n";
        $headers .= "To: " . $toEmail . "\r\n";
        $headers .= "Subject: " . $subject . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "X-Mailer: TaskFlow Mailer\r\n";

        // Build email body
        $body = $htmlBody;
        
        // Send MAIL FROM
        fputs($socket, "MAIL FROM:<" . SMTP_FROM_EMAIL . ">\r\n");
        fgets($socket);

        // Send RCPT TO
        fputs($socket, "RCPT TO:<" . $toEmail . ">\r\n");
        fgets($socket);

        // Send DATA
        fputs($socket, "DATA\r\n");
        fgets($socket);

        // Send message
        fputs($socket, $headers . "\r\n" . $body . "\r\n.\r\n");
        $response = fgets($socket);

        // Send QUIT
        fputs($socket, "QUIT\r\n");
        fgets($socket);

        // Close connection
        fclose($socket);

        return [
            'success' => true,
            'message' => 'Email sent successfully'
        ];

    } catch (Exception $e) {
        error_log("Email sending error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Failed to send email: ' . $e->getMessage()
        ];
    }
}

/**
 * Send password reset email
 */
function sendPasswordResetEmail($toEmail, $resetCode) {
    $subject = "Password Reset Code - TaskFlow";
    
    $htmlBody = <<<HTML
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
        <div style="text-align: center; border-bottom: 2px solid #667eea; padding-bottom: 20px; margin-bottom: 30px;">
            <h2 style="color: #667eea; margin: 0;">Password Reset Request</h2>
        </div>
        
        <p>Hello,</p>
        
        <p>You requested a password reset for your TaskFlow account.</p>
        
        <div style="background-color: #f3f4f6; padding: 20px; border-radius: 8px; text-align: center; margin: 30px 0;">
            <p style="margin: 0; color: #666; font-size: 14px;">Your reset code:</p>
            <p style="margin: 10px 0; font-size: 32px; font-weight: bold; color: #667eea; letter-spacing: 5px;">${resetCode}</p>
            <p style="margin: 0; color: #999; font-size: 12px;">This code will expire in 10 minutes</p>
        </div>
        
        <p style="color: #666;">If you didn't request this reset, you can safely ignore this email.</p>
        
        <div style="border-top: 1px solid #e5e7eb; padding-top: 20px; margin-top: 30px; color: #999; font-size: 12px;">
            <p style="margin: 0;">This is an automated message from TaskFlow. Please don't reply to this email.</p>
            <p style="margin: 10px 0 0 0;">© 2025 TaskFlow. All rights reserved.</p>
        </div>
    </div>
    HTML;

    return sendEmail($toEmail, $subject, $htmlBody);
}

/**
 * Send task reminder email
 */
function sendTaskReminderEmail($toEmail, $taskTitle, $reminderTime) {
    $subject = "Task Reminder: $taskTitle - TaskFlow";
    
    $htmlBody = <<<HTML
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
        <div style="text-align: center; border-bottom: 2px solid #667eea; padding-bottom: 20px; margin-bottom: 30px;">
            <h2 style="color: #667eea; margin: 0;">🔔 Task Reminder</h2>
        </div>
        
        <p>Hello,</p>
        
        <p>This is a reminder for your upcoming task:</p>
        
        <div style="background-color: #f3f4f6; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <h3 style="margin: 0 0 10px 0; color: #333;">${taskTitle}</h3>
            <p style="margin: 0; color: #666; font-size: 14px;">Reminder time: ${reminderTime}</p>
        </div>
        
        <p style="color: #666;">Please make sure to complete this task on time!</p>
        
        <div style="border-top: 1px solid #e5e7eb; padding-top: 20px; margin-top: 30px; color: #999; font-size: 12px;">
            <p style="margin: 0;">This is an automated reminder from TaskFlow.</p>
            <p style="margin: 10px 0 0 0;">© 2025 TaskFlow. All rights reserved.</p>
        </div>
    </div>
    HTML;

    return sendEmail($toEmail, $subject, $htmlBody);
}

/**
 * Send meeting invitation email
 */
function sendMeetingInvitationEmail($toEmail, $taskTitle, $meetingLink, $dueDate, $dueTime, $inviterName) {
    $subject = "Meeting Invitation: $taskTitle - TaskFlow";
    
    $meetingDateTime = $dueDate && $dueTime ? date('F j, Y \a\t g:i A', strtotime("$dueDate $dueTime")) : 'TBD';
    
    $htmlBody = <<<HTML
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
        <div style="text-align: center; border-bottom: 2px solid #667eea; padding-bottom: 20px; margin-bottom: 30px;">
            <h2 style="color: #667eea; margin: 0;">📅 Meeting Invitation</h2>
        </div>
        
        <p>Hello,</p>
        
        <p><strong>${inviterName}</strong> has invited you to a meeting:</p>
        
        <div style="background-color: #f3f4f6; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <h3 style="margin: 0 0 15px 0; color: #333;">${taskTitle}</h3>
            <p style="margin: 5px 0; color: #666;"><strong>📅 Date & Time:</strong> ${meetingDateTime}</p>
            <p style="margin: 5px 0; color: #666;"><strong>👤 Organizer:</strong> ${inviterName}</p>
        </div>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="${meetingLink}" style="background-color: #667eea; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block;">🔗 Join Meeting</a>
        </div>
        
        <p style="color: #666; font-size: 14px;">Meeting Link: <a href="${meetingLink}" style="color: #667eea;">${meetingLink}</a></p>
        
        <div style="border-top: 1px solid #e5e7eb; padding-top: 20px; margin-top: 30px; color: #999; font-size: 12px;">
            <p style="margin: 0;">This invitation was sent via TaskFlow.</p>
            <p style="margin: 10px 0 0 0;">© 2025 TaskFlow. All rights reserved.</p>
        </div>
    </div>
    HTML;

    return sendEmail($toEmail, $subject, $htmlBody);
}

/**
 * Send task deadline email
 */
function sendTaskDeadlineEmail($toEmail, $taskTitle, $dueDate) {
    $subject = "Task Deadline Alert: $taskTitle - TaskFlow";
    
    $htmlBody = <<<HTML
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
        <div style="text-align: center; border-bottom: 2px solid #ef4444; padding-bottom: 20px; margin-bottom: 30px;">
            <h2 style="color: #ef4444; margin: 0;">⚠️ Task Deadline Alert</h2>
        </div>
        
        <p>Hello,</p>
        
        <p style="color: #ef4444; font-weight: bold;">Your task is due now!</p>
        
        <div style="background-color: #fef2f2; padding: 20px; border-left: 4px solid #ef4444; margin: 20px 0;">
            <h3 style="margin: 0 0 10px 0; color: #991b1b;">${taskTitle}</h3>
            <p style="margin: 0; color: #7f1d1d; font-size: 14px;">Due date: ${dueDate}</p>
        </div>
        
        <p style="color: #666;">Please complete this task as soon as possible!</p>
        
        <div style="border-top: 1px solid #e5e7eb; padding-top: 20px; margin-top: 30px; color: #999; font-size: 12px;">
            <p style="margin: 0;">This is an automated alert from TaskFlow.</p>
            <p style="margin: 10px 0 0 0;">© 2025 TaskFlow. All rights reserved.</p>
        </div>
    </div>
    HTML;

    return sendEmail($toEmail, $subject, $htmlBody);
}

// ===== API ENDPOINTS =====

$requestMethod = $_SERVER['REQUEST_METHOD'];

if ($requestMethod !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get action from query parameter or JSON body
$input = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? $input['action'] ?? null;

if (!$action) {
    http_response_code(400);
    echo json_encode(['error' => 'Action parameter required']);
    exit();
}

// Route to appropriate function
switch ($action) {
    case 'send-password-reset':
        if (!isset($input['email']) || !isset($input['resetCode'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Email and resetCode required']);
            exit();
        }
        
        $result = sendPasswordResetEmail($input['email'], $input['resetCode']);
        echo json_encode($result);
        break;

    case 'send-reminder':
        if (!isset($input['email']) || !isset($input['taskTitle']) || !isset($input['reminderTime'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Email, taskTitle, and reminderTime required']);
            exit();
        }
        
        $result = sendTaskReminderEmail($input['email'], $input['taskTitle'], $input['reminderTime']);
        echo json_encode($result);
        break;

    case 'send-deadline':
        if (!isset($input['email']) || !isset($input['taskTitle']) || !isset($input['dueDate'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Email, taskTitle, and dueDate required']);
            exit();
        }
        
        $result = sendTaskDeadlineEmail($input['email'], $input['taskTitle'], $input['dueDate']);
        echo json_encode($result);
        break;

    case 'send-meeting-invite':
        if (!isset($input['emails']) || !isset($input['taskTitle']) || !isset($input['meetingLink']) || !isset($input['inviterName'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Emails, taskTitle, meetingLink, and inviterName required']);
            exit();
        }
        
        $emails = $input['emails'];
        $taskTitle = $input['taskTitle'];
        $meetingLink = $input['meetingLink'];
        $dueDate = $input['dueDate'] ?? null;
        $dueTime = $input['dueTime'] ?? null;
        $inviterName = $input['inviterName'];
        
        $results = [];
        $successCount = 0;
        
        foreach ($emails as $email) {
            $result = sendMeetingInvitationEmail($email, $taskTitle, $meetingLink, $dueDate, $dueTime, $inviterName);
            $results[] = ['email' => $email, 'result' => $result];
            if ($result['success']) {
                $successCount++;
            }
        }
        
        echo json_encode([
            'success' => $successCount > 0,
            'message' => "Sent $successCount of " . count($emails) . " invitations",
            'results' => $results
        ]);
        break;

    case 'test':
        // Test endpoint - verify SMTP configuration
        if (empty(SMTP_USER) || empty(SMTP_PASS)) {
            echo json_encode([
                'success' => false,
                'message' => 'SMTP not configured. Please set environment variables.',
                'status' => 'unconfigured'
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'message' => 'SMTP is configured',
                'status' => 'configured',
                'host' => SMTP_HOST,
                'port' => SMTP_PORT
            ]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action: ' . $action]);
        break;
}
