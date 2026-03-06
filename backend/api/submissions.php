<?php
/**
 * Submissions API
 * Handles task submissions, file uploads, and approval workflow
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';
$submissionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// User must be authenticated
$user = getCurrentUser();
if (!$user) {
    sendError('Not authenticated', 401);
}

switch ($action) {
    case 'submit':
        if ($method === 'POST') {
            handleSubmitTask();
        }
        break;
    
    case 'list':
        if ($method === 'GET') {
            handleListSubmissions();
        }
        break;
    
    case 'get':
        if ($method === 'GET' && $submissionId) {
            handleGetSubmission($submissionId);
        }
        break;
    
    case 'approve':
        if ($method === 'POST' && $submissionId) {
            handleApproveSubmission($submissionId);
        }
        break;
    
    case 'reject':
        if ($method === 'POST' && $submissionId) {
            handleRejectSubmission($submissionId);
        }
        break;
    
    case 'comment':
        if ($method === 'POST' && $submissionId) {
            handleAddComment($submissionId);
        }
        break;
    
    case 'my-submissions':
        if ($method === 'GET') {
            handleGetMySubmissions($user);
        }
        break;
    
    default:
        sendError('Invalid submission endpoint', 400);
}

/**
 * Submit task (Employee only)
 */
function handleSubmitTask() {
    global $user;
    
    if ($user['role'] !== 'employee') {
        sendError('Only employees can submit tasks', 403);
    }
    
    $input = getJsonInput();
    
    if (!isset($input['task_id'])) {
        sendError('Task ID is required', 400);
    }
    
    try {
        $pdo = getDatabase();
        
        // Verify task is assigned to employee
        $taskCheck = $pdo->prepare('SELECT id FROM tasks WHERE id = ? AND assigned_to = ?');
        $taskCheck->execute([$input['task_id'], $user['id']]);
        
        if (!$taskCheck->fetch()) {
            sendError('Task not found or not assigned to you', 404);
        }
        
        // Check if submission already exists
        $existingCheck = $pdo->prepare('SELECT id FROM submissions WHERE task_id = ? AND employee_id = ?');
        $existingCheck->execute([$input['task_id'], $user['id']]);
        
        $filePath = null;
        
        // Handle file upload if provided
        if (isset($_FILES['file']) && $_FILES['file']['tmp_name']) {
            $fileResult = saveUploadedFile($_FILES['file'], 'submissions');
            if (!$fileResult['success']) {
                sendError($fileResult['error'], 400);
            }
            $filePath = $fileResult['path'];
        }
        
        if ($existingCheck->fetch()) {
            // Update existing submission
            $updateStmt = $pdo->prepare('
                UPDATE submissions 
                SET submission_text = ?, file_path = ?, status = ?, submitted_at = NOW()
                WHERE task_id = ? AND employee_id = ?
            ');
            
            $updateStmt->execute([
                isset($input['submission_text']) ? sanitizeString($input['submission_text']) : null,
                $filePath,
                'pending',
                $input['task_id'],
                $user['id']
            ]);
            
            logAction('Task resubmitted', $user['id'], ['task_id' => $input['task_id']]);
            sendSuccess([], 'Submission updated successfully');
        } else {
            // Create new submission
            $insertStmt = $pdo->prepare('
                INSERT INTO submissions (task_id, employee_id, submission_text, file_path, status, submitted_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ');
            
            $insertStmt->execute([
                $input['task_id'],
                $user['id'],
                isset($input['submission_text']) ? sanitizeString($input['submission_text']) : null,
                $filePath,
                'pending'
            ]);
            
            logAction('Task submitted', $user['id'], ['task_id' => $input['task_id']]);
            sendSuccess([], 'Task submitted successfully');
        }
        
    } catch (Exception $e) {
        sendError('Failed to submit task', 500, APP_DEBUG ? $e->getMessage() : null);
    }
}

/**
 * List submissions (admin sees all, employees see only their own)
 */
function handleListSubmissions() {
    global $user;
    
    try {
        $pdo = getDatabase();
        
        if ($user['role'] === 'admin') {
            // Get all submissions with details
            $stmt = $pdo->prepare('
                SELECT 
                    s.*,
                    t.title as task_title,
                    t.deadline,
                    emp.name as employee_name,
                    emp.email as employee_email,
                    admin.name as admin_name
                FROM submissions s
                INNER JOIN tasks t ON s.task_id = t.id
                INNER JOIN users emp ON s.employee_id = emp.id
                LEFT JOIN users admin ON s.admin_id = admin.id
                ORDER BY s.submitted_at DESC
            ');
            $stmt->execute();
        } else {
            // Get only employee's own submissions
            $stmt = $pdo->prepare('
                SELECT 
                    s.*,
                    t.title as task_title,
                    t.deadline,
                    admin.name as admin_name
                FROM submissions s
                INNER JOIN tasks t ON s.task_id = t.id
                LEFT JOIN users admin ON s.admin_id = admin.id
                WHERE s.employee_id = ?
                ORDER BY s.submitted_at DESC
            ');
            $stmt->execute([$user['id']]);
        }
        
        $submissions = $stmt->fetchAll();
        
        sendSuccess(['submissions' => $submissions]);
        
    } catch (Exception $e) {
        sendError('Failed to retrieve submissions', 500);
    }
}

/**
 * Get single submission
 */
function handleGetSubmission($submissionId) {
    global $user;
    
    try {
        $pdo = getDatabase();
        
        $stmt = $pdo->prepare('
            SELECT 
                s.*,
                t.title as task_title,
                t.description,
                t.deadline,
                emp.name as employee_name,
                emp.email as employee_email,
                admin.name as admin_name
            FROM submissions s
            INNER JOIN tasks t ON s.task_id = t.id
            INNER JOIN users emp ON s.employee_id = emp.id
            LEFT JOIN users admin ON s.admin_id = admin.id
            WHERE s.id = ?
        ');
        $stmt->execute([$submissionId]);
        
        $submission = $stmt->fetch();
        
        if (!$submission) {
            sendError('Submission not found', 404);
        }
        
        // Check permission (admin or the employee who made the submission)
        if ($user['role'] !== 'admin' && $submission['employee_id'] !== $user['id']) {
            sendError('Unauthorized', 403);
        }
        
        sendSuccess(['submission' => $submission]);
        
    } catch (Exception $e) {
        sendError('Failed to retrieve submission', 500);
    }
}

/**
 * Approve submission (Admin only)
 */
function handleApproveSubmission($submissionId) {
    global $user;
    
    if ($user['role'] !== 'admin') {
        sendError('Only admins can approve submissions', 403);
    }
    
    $input = getJsonInput();
    
    try {
        $pdo = getDatabase();
        
        $stmt = $pdo->prepare('
            UPDATE submissions 
            SET status = ?, admin_id = ?, admin_comment = ?, reviewed_at = NOW()
            WHERE id = ?
        ');
        
        $stmt->execute([
            'approved',
            $user['id'],
            isset($input['comment']) ? sanitizeString($input['comment']) : null,
            $submissionId
        ]);
        
        // Also mark the task as completed
        $taskStmt = $pdo->prepare('
            UPDATE tasks SET status = ? WHERE id = (SELECT task_id FROM submissions WHERE id = ?)
        ');
        $taskStmt->execute(['completed', $submissionId]);
        
        logAction('Submission approved', $user['id'], ['submission_id' => $submissionId]);
        
        sendSuccess([], 'Submission approved successfully');
        
    } catch (Exception $e) {
        sendError('Failed to approve submission', 500);
    }
}

/**
 * Reject submission (Admin only)
 */
function handleRejectSubmission($submissionId) {
    global $user;
    
    if ($user['role'] !== 'admin') {
        sendError('Only admins can reject submissions', 403);
    }
    
    $input = getJsonInput();
    
    if (!isset($input['comment'])) {
        sendError('Rejection comment is required', 400);
    }
    
    try {
        $pdo = getDatabase();
        
        $stmt = $pdo->prepare('
            UPDATE submissions 
            SET status = ?, admin_id = ?, admin_comment = ?, reviewed_at = NOW()
            WHERE id = ?
        ');
        
        $stmt->execute([
            'rejected',
            $user['id'],
            sanitizeString($input['comment']),
            $submissionId
        ]);
        
        logAction('Submission rejected', $user['id'], ['submission_id' => $submissionId]);
        
        sendSuccess([], 'Submission rejected');
        
    } catch (Exception $e) {
        sendError('Failed to reject submission', 500);
    }
}

/**
 * Add comment to submission (Admin only)
 */
function handleAddComment($submissionId) {
    global $user;
    
    if ($user['role'] !== 'admin') {
        sendError('Only admins can comment on submissions', 403);
    }
    
    $input = getJsonInput();
    
    if (!isset($input['comment'])) {
        sendError('Comment is required', 400);
    }
    
    try {
        $pdo = getDatabase();
        
        $stmt = $pdo->prepare('
            UPDATE submissions 
            SET admin_comment = ?
            WHERE id = ?
        ');
        
        $stmt->execute([
            sanitizeString($input['comment']),
            $submissionId
        ]);
        
        logAction('Submission comment added', $user['id'], ['submission_id' => $submissionId]);
        
        sendSuccess([], 'Comment added successfully');
        
    } catch (Exception $e) {
        sendError('Failed to add comment', 500);
    }
}

/**
 * Get user's own submissions (Employee only)
 */
function handleGetMySubmissions($user) {
    if ($user['role'] !== 'employee') {
        sendError('Only employees can view their own submissions', 403);
    }
    
    try {
        $pdo = getDatabase();
        
        $stmt = $pdo->prepare('
            SELECT 
                s.*,
                t.title as task_title,
                t.deadline,
                admin.name as admin_name
            FROM submissions s
            INNER JOIN tasks t ON s.task_id = t.id
            LEFT JOIN users admin ON s.admin_id = admin.id
            WHERE s.employee_id = ?
            ORDER BY s.submitted_at DESC
        ');
        $stmt->execute([$user['id']]);
        
        $submissions = $stmt->fetchAll();
        
        sendSuccess(['submissions' => $submissions]);
        
    } catch (Exception $e) {
        sendError('Failed to retrieve submissions', 500);
    }
}

?>
