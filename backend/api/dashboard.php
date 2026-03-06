<?php
/**
 * Dashboard API
 * Admin dashboard analytics and statistics
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

// User must be authenticated
$user = getCurrentUser();
if (!$user) {
    sendError('Not authenticated', 401);
}

switch ($action) {
    case 'admin-overview':
        if ($method === 'GET') {
            handleAdminOverview();
        }
        break;
    
    case 'employee-dashboard':
        if ($method === 'GET') {
            handleEmployeeDashboard();
        }
        break;
    
    case 'recent-submissions':
        if ($method === 'GET') {
            handleRecentSubmissions();
        }
        break;
    
    case 'employee-stats':
        if ($method === 'GET') {
            handleEmployeeStats();
        }
        break;
    
    default:
        sendError('Invalid dashboard endpoint', 400);
}

/**
 * Admin dashboard overview
 */
function handleAdminOverview() {
    global $user;
    
    if ($user['role'] !== 'admin') {
        sendError('Only admins can view admin dashboard', 403);
    }
    
    try {
        $pdo = getDatabase();
        
        // Total stats
        $statsData = [
            'total_tasks' => 0,
            'completed_tasks' => 0,
            'pending_tasks' => 0,
            'in_progress_tasks' => 0,
            'total_employees' => 0,
            'total_submissions' => 0,
            'approved_submissions' => 0,
            'rejected_submissions' => 0,
            'pending_submissions' => 0
        ];
        
        // Total tasks
        $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM tasks WHERE created_by = ?');
        $stmt->execute([$user['id']]);
        $statsData['total_tasks'] = (int)$stmt->fetch()['count'];
        
        // Completed tasks
        $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM tasks WHERE created_by = ? AND status = "completed"');
        $stmt->execute([$user['id']]);
        $statsData['completed_tasks'] = (int)$stmt->fetch()['count'];
        
        // Pending tasks
        $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM tasks WHERE created_by = ? AND status = "pending"');
        $stmt->execute([$user['id']]);
        $statsData['pending_tasks'] = (int)$stmt->fetch()['count'];
        
        // In progress tasks
        $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM tasks WHERE created_by = ? AND status = "in_progress"');
        $stmt->execute([$user['id']]);
        $statsData['in_progress_tasks'] = (int)$stmt->fetch()['count'];
        
        // Total employees
        $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM users WHERE role = "employee"');
        $stmt->execute();
        $statsData['total_employees'] = (int)$stmt->fetch()['count'];
        
        // Total submissions
        $stmt = $pdo->prepare('
            SELECT COUNT(*) as count FROM submissions s
            INNER JOIN tasks t ON s.task_id = t.id 
            WHERE t.created_by = ?
        ');
        $stmt->execute([$user['id']]);
        $statsData['total_submissions'] = (int)$stmt->fetch()['count'];
        
        // Approved submissions
        $stmt = $pdo->prepare('
            SELECT COUNT(*) as count FROM submissions s
            INNER JOIN tasks t ON s.task_id = t.id 
            WHERE t.created_by = ? AND s.status = "approved"
        ');
        $stmt->execute([$user['id']]);
        $statsData['approved_submissions'] = (int)$stmt->fetch()['count'];
        
        // Rejected submissions
        $stmt = $pdo->prepare('
            SELECT COUNT(*) as count FROM submissions s
            INNER JOIN tasks t ON s.task_id = t.id 
            WHERE t.created_by = ? AND s.status = "rejected"
        ');
        $stmt->execute([$user['id']]);
        $statsData['rejected_submissions'] = (int)$stmt->fetch()['count'];
        
        // Pending submissions
        $stmt = $pdo->prepare('
            SELECT COUNT(*) as count FROM submissions s
            INNER JOIN tasks t ON s.task_id = t.id 
            WHERE t.created_by = ? AND s.status = "pending"
        ');
        $stmt->execute([$user['id']]);
        $statsData['pending_submissions'] = (int)$stmt->fetch()['count'];
        
        // Completion rate
        $completionRate = $statsData['total_tasks'] > 0 
            ? round(($statsData['completed_tasks'] / $statsData['total_tasks']) * 100, 2) 
            : 0;
        
        $statsData['completion_rate'] = $completionRate;
        
        sendSuccess(['dashboard' => $statsData]);
        
    } catch (Exception $e) {
        sendError('Failed to retrieve dashboard data', 500, APP_DEBUG ? $e->getMessage() : null);
    }
}

/**
 * Employee dashboard overview
 */
function handleEmployeeDashboard() {
    global $user;
    
    if ($user['role'] !== 'employee') {
        sendError('Only employees can view employee dashboard', 403);
    }
    
    try {
        $pdo = getDatabase();
        
        $dashboardData = [
            'total_tasks' => 0,
            'completed_tasks' => 0,
            'pending_tasks' => 0,
            'pending_submissions' => 0,
            'approved_submissions' => 0,
            'rejected_submissions' => 0,
            'overdue_tasks' => 0
        ];
        
        // Total assigned tasks
        $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM tasks WHERE assigned_to = ?');
        $stmt->execute([$user['id']]);
        $dashboardData['total_tasks'] = (int)$stmt->fetch()['count'];
        
        // Completed tasks
        $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM tasks WHERE assigned_to = ? AND status = "completed"');
        $stmt->execute([$user['id']]);
        $dashboardData['completed_tasks'] = (int)$stmt->fetch()['count'];
        
        // Pending tasks
        $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM tasks WHERE assigned_to = ? AND status = "pending"');
        $stmt->execute([$user['id']]);
        $dashboardData['pending_tasks'] = (int)$stmt->fetch()['count'];
        
        // Submissions stats
        $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM submissions WHERE employee_id = ? AND status = "pending"');
        $stmt->execute([$user['id']]);
        $dashboardData['pending_submissions'] = (int)$stmt->fetch()['count'];
        
        $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM submissions WHERE employee_id = ? AND status = "approved"');
        $stmt->execute([$user['id']]);
        $dashboardData['approved_submissions'] = (int)$stmt->fetch()['count'];
        
        $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM submissions WHERE employee_id = ? AND status = "rejected"');
        $stmt->execute([$user['id']]);
        $dashboardData['rejected_submissions'] = (int)$stmt->fetch()['count'];
        
        // Overdue tasks
        $stmt = $pdo->prepare('
            SELECT COUNT(*) as count FROM tasks 
            WHERE assigned_to = ? AND status != "completed" AND deadline < CURDATE()
        ');
        $stmt->execute([$user['id']]);
        $dashboardData['overdue_tasks'] = (int)$stmt->fetch()['count'];
        
        $completionRate = $dashboardData['total_tasks'] > 0 
            ? round(($dashboardData['completed_tasks'] / $dashboardData['total_tasks']) * 100, 2) 
            : 0;
        
        $dashboardData['completion_rate'] = $completionRate;
        
        sendSuccess(['dashboard' => $dashboardData]);
        
    } catch (Exception $e) {
        sendError('Failed to retrieve dashboard data', 500);
    }
}

/**
 * Get recent submissions (Admin)
 */
function handleRecentSubmissions() {
    global $user;
    
    if ($user['role'] !== 'admin') {
        sendError('Only admins can view submissions', 403);
    }
    
    try {
        $pdo = getDatabase();
        
        $stmt = $pdo->prepare('
            SELECT 
                s.*, 
                t.title as task_title,
                emp.name as employee_name,
                emp.email as employee_email
            FROM submissions s
            INNER JOIN tasks t ON s.task_id = t.id
            INNER JOIN users emp ON s.employee_id = emp.id
            WHERE t.created_by = ?
            ORDER BY s.submitted_at DESC
            LIMIT 10
        ');
        $stmt->execute([$user['id']]);
        
        $submissions = $stmt->fetchAll();
        
        sendSuccess(['submissions' => $submissions]);
        
    } catch (Exception $e) {
        sendError('Failed to retrieve submissions', 500);
    }
}

/**
 * Get employee statistics (Admin)
 */
function handleEmployeeStats() {
    global $user;
    
    if ($user['role'] !== 'admin') {
        sendError('Only admins can view employee stats', 403);
    }
    
    try {
        $pdo = getDatabase();
        
        // Get all employees with their task stats
        $stmt = $pdo->prepare('
            SELECT 
                u.id,
                u.name,
                u.email,
                u.department,
                COUNT(DISTINCT t.id) as total_tasks,
                SUM(CASE WHEN t.status = "completed" THEN 1 ELSE 0 END) as completed_tasks,
                SUM(CASE WHEN s.status = "approved" THEN 1 ELSE 0 END) as approved_submissions
            FROM users u
            LEFT JOIN tasks t ON u.id = t.assigned_to
            LEFT JOIN submissions s ON t.id = s.task_id
            WHERE u.role = "employee"
            GROUP BY u.id, u.name, u.email, u.department
            ORDER BY completed_tasks DESC
        ');
        $stmt->execute();
        
        $employees = $stmt->fetchAll();
        
        sendSuccess(['employees' => $employees]);
        
    } catch (Exception $e) {
        sendError('Failed to retrieve employee statistics', 500);
    }
}

?>
