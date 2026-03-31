<?php
/**
 * Backend Index - API Documentation
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Management API</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 20px;
            text-align: center;
        }
        
        .header h1 {
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
        }
        
        .content {
            padding: 40px;
        }
        
        .section {
            margin-bottom: 40px;
        }
        
        .section h2 {
            color: #333;
            margin-bottom: 20px;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        
        .endpoint {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 5px;
        }
        
        .endpoint-method {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 3px;
            font-weight: bold;
            color: white;
            margin-bottom: 10px;
        }
        
        .method-get { background: #61affe; }
        .method-post { background: #49cc90; }
        .method-put { background: #fca130; }
        .method-delete { background: #f93e3e; }
        
        .endpoint-path {
            font-family: 'Courier New', monospace;
            background: white;
            padding: 10px;
            border-radius: 3px;
            margin-bottom: 10px;
            overflow-x: auto;
        }
        
        .endpoint-description {
            color: #666;
            margin-bottom: 10px;
            font-size: 0.95em;
        }
        
        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 3px;
            background: #10b981;
            color: white;
            font-size: 0.85em;
        }
        
        .footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            color: #666;
            font-size: 0.9em;
        }
        
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .info-box strong {
            color: #1976d2;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 Task Management API</h1>
            <p>Backend API Documentation</p>
        </div>
        
        <div class="content">
            <div class="info-box">
                <strong>✓ Backend Status:</strong> Online and Ready
                <br><strong>Database:</strong> PostgreSQL Connected
                <br><strong>API Base URL:</strong> /backend/api/
            </div>
            
            <!-- Authentication -->
            <div class="section">
                <h2>🔐 Authentication</h2>
                
                <div class="endpoint">
                    <div class="endpoint-method method-post">POST</div>
                    <span class="status">Session-based</span>
                    <div class="endpoint-path">/api/auth.php?action=register</div>
                    <div class="endpoint-description">Create a new user account</div>
                </div>
                
                <div class="endpoint">
                    <div class="endpoint-method method-post">POST</div>
                    <span class="status">Session-based</span>
                    <div class="endpoint-path">/api/auth.php?action=login</div>
                    <div class="endpoint-description">Authenticate user with email and password</div>
                </div>
                
                <div class="endpoint">
                    <div class="endpoint-method method-post">POST</div>
                    <span class="status">Session-based</span>
                    <div class="endpoint-path">/api/auth.php?action=logout</div>
                    <div class="endpoint-description">End user session</div>
                </div>
            </div>
            
            <!-- Tasks -->
            <div class="section">
                <h2>✅ Tasks</h2>
                
                <div class="endpoint">
                    <div class="endpoint-method method-get">GET</div>
                    <span class="status">Authenticated</span>
                    <div class="endpoint-path">/api/tasks.php</div>
                    <div class="endpoint-description">Retrieve all tasks for the current user</div>
                </div>
                
                <div class="endpoint">
                    <div class="endpoint-method method-post">POST</div>
                    <span class="status">Authenticated</span>
                    <div class="endpoint-path">/api/tasks.php</div>
                    <div class="endpoint-description">Create a new task</div>
                </div>
                
                <div class="endpoint">
                    <div class="endpoint-method method-put">PUT</div>
                    <span class="status">Authenticated</span>
                    <div class="endpoint-path">/api/tasks.php</div>
                    <div class="endpoint-description">Update an existing task</div>
                </div>
                
                <div class="endpoint">
                    <div class="endpoint-method method-delete">DELETE</div>
                    <span class="status">Authenticated</span>
                    <div class="endpoint-path">/api/tasks.php?id=1</div>
                    <div class="endpoint-description">Delete a task</div>
                </div>
            </div>
            
            <!-- Reflections -->
            <div class="section">
                <h2>💭 Task Reflections</h2>
                
                <div class="endpoint">
                    <div class="endpoint-method method-get">GET</div>
                    <span class="status">Authenticated</span>
                    <div class="endpoint-path">/api/reflections.php?task_id=1</div>
                    <div class="endpoint-description">Get reflections for a specific task</div>
                </div>
                
                <div class="endpoint">
                    <div class="endpoint-method method-post">POST</div>
                    <span class="status">Authenticated</span>
                    <div class="endpoint-path">/api/reflections.php</div>
                    <div class="endpoint-description">Create a new reflection for a task</div>
                </div>
            </div>

            <!-- Daily Diary -->
            <div class="section">
                <h2>📓 Daily Diary</h2>

                <div class="endpoint">
                    <div class="endpoint-method method-get">GET</div>
                    <span class="status">Authenticated</span>
                    <div class="endpoint-path">/api/diary.php</div>
                    <div class="endpoint-description">Get diary history for current user</div>
                </div>

                <div class="endpoint">
                    <div class="endpoint-method method-get">GET</div>
                    <span class="status">Authenticated</span>
                    <div class="endpoint-path">/api/diary.php?id=1</div>
                    <div class="endpoint-description">Get a single diary entry for current user</div>
                </div>

                <div class="endpoint">
                    <div class="endpoint-method method-post">POST</div>
                    <span class="status">Authenticated</span>
                    <div class="endpoint-path">/api/diary.php</div>
                    <div class="endpoint-description">Create diary entry (JSON or multipart with optional audio_note)</div>
                </div>
            </div>
            
            <!-- Reminders -->
            <div class="section">
                <h2>⏰ Reminders</h2>
                
                <div class="endpoint">
                    <div class="endpoint-method method-get">GET</div>
                    <span class="status">Authenticated</span>
                    <div class="endpoint-path">/api/reminders.php</div>
                    <div class="endpoint-description">Get pending reminders for the current user</div>
                </div>
                
                <div class="endpoint">
                    <div class="endpoint-method method-post">POST</div>
                    <span class="status">Authenticated</span>
                    <div class="endpoint-path">/api/reminders.php</div>
                    <div class="endpoint-description">Create a new reminder for a task</div>
                </div>
            </div>
            
            <!-- Password Reset -->
            <div class="section">
                <h2>🔑 Password Reset</h2>
                
                <div class="endpoint">
                    <div class="endpoint-method method-post">POST</div>
                    <span class="status">Public</span>
                    <div class="endpoint-path">/api/password-reset.php?action=request-reset</div>
                    <div class="endpoint-description">Request a password reset</div>
                </div>
                
                <div class="endpoint">
                    <div class="endpoint-method method-post">POST</div>
                    <span class="status">Public</span>
                    <div class="endpoint-path">/api/password-reset.php?action=verify-token</div>
                    <div class="endpoint-description">Verify a password reset token</div>
                </div>
                
                <div class="endpoint">
                    <div class="endpoint-method method-post">POST</div>
                    <span class="status">Public</span>
                    <div class="endpoint-path">/api/password-reset.php?action=reset-password</div>
                    <div class="endpoint-description">Reset password with valid token</div>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <p>📚 See <strong>BACKEND_SETUP.md</strong> for detailed documentation and setup instructions</p>
        </div>
    </div>
</body>
</html>
