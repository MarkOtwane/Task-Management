# Task Management Backend - Setup Guide

## Overview

This is a PHP backend with PostgreSQL database for the Task Management application.

## Requirements

-    **PHP**: 7.4 or higher
-    **PostgreSQL**: 10 or higher
-    **Composer** (optional, for dependencies)

## Installation

### 1. Install PostgreSQL

**On Linux (Ubuntu/Debian):**

```bash
sudo apt update
sudo apt install postgresql postgresql-contrib
sudo systemctl start postgresql
```

**On macOS:**

```bash
brew install postgresql
brew services start postgresql
```

**On Windows:**
Download and install from: https://www.postgresql.org/download/windows/

### 2. Create Database and User

```bash
# Connect to PostgreSQL
sudo -u postgres psql

# Create database
CREATE DATABASE task_management;

# Create user
CREATE USER postgres WITH PASSWORD 'postgres';

# Grant privileges
ALTER ROLE postgres WITH CREATEDB;
GRANT ALL PRIVILEGES ON DATABASE task_management TO postgres;

# Exit
\q
```

### 3. Update Database Configuration

Edit `backend/config/database.php` with your PostgreSQL credentials:

```php
define('DB_HOST', 'localhost');
define('DB_PORT', '5432');
define('DB_NAME', 'task_management');
define('DB_USER', 'postgres');
define('DB_PASSWORD', 'postgres');
```

### 4. Start PHP Development Server

```bash
cd /path/to/Task-Management
php -S localhost:8000
```

Your backend will be available at: `http://localhost:8000/backend/`

## API Endpoints

### Authentication

**Register User**

```
POST /backend/api/auth.php?action=register
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "password123",
  "username": "username"
}
```

**Login User**

```
POST /backend/api/auth.php?action=login
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "password123"
}
```

**Logout User**

```
POST /backend/api/auth.php?action=logout
```

### Tasks

**Get All Tasks**

```
GET /backend/api/tasks.php
```

**Create Task**

```
POST /backend/api/tasks.php
Content-Type: application/json

{
  "title": "Task Title",
  "description": "Task description",
  "category": "work",
  "priority": "high",
  "due_date": "2025-12-31T18:00:00"
}
```

**Update Task**

```
PUT /backend/api/tasks.php
Content-Type: application/json

{
  "id": 1,
  "title": "Updated Title",
  "status": "completed"
}
```

**Delete Task**

```
DELETE /backend/api/tasks.php?id=1
```

### Task Reflections

**Get Reflections**

```
GET /backend/api/reflections.php?task_id=1
```

**Create Reflection**

```
POST /backend/api/reflections.php
Content-Type: application/json

{
  "task_id": 1,
  "reflection_text": "What I learned from this task..."
}
```

### Reminders

**Get Reminders**

```
GET /backend/api/reminders.php
```

**Create Reminder**

```
POST /backend/api/reminders.php
Content-Type: application/json

{
  "task_id": 1,
  "reminder_type": "1_day_before",
  "reminder_time": "2025-12-30T18:00:00"
}
```

### Password Reset

**Request Reset**

```
POST /backend/api/password-reset.php?action=request-reset
Content-Type: application/json

{
  "email": "user@example.com"
}
```

**Verify Token**

```
POST /backend/api/password-reset.php?action=verify-token
Content-Type: application/json

{
  "token": "reset_token_here"
}
```

**Reset Password**

```
POST /backend/api/password-reset.php?action=reset-password
Content-Type: application/json

{
  "token": "reset_token_here",
  "new_password": "new_password_123"
}
```

## Database Schema

### Tables

#### users

-    `id` (PRIMARY KEY)
-    `email` (UNIQUE)
-    `password`
-    `username`
-    `created_at`
-    `updated_at`

#### tasks

-    `id` (PRIMARY KEY)
-    `user_id` (FOREIGN KEY)
-    `title`
-    `description`
-    `category`
-    `priority`
-    `status`
-    `due_date`
-    `created_at`
-    `updated_at`

#### task_reflections

-    `id` (PRIMARY KEY)
-    `task_id` (FOREIGN KEY)
-    `user_id` (FOREIGN KEY)
-    `reflection_text`
-    `created_at`

#### reminders

-    `id` (PRIMARY KEY)
-    `task_id` (FOREIGN KEY)
-    `user_id` (FOREIGN KEY)
-    `reminder_type`
-    `reminder_time`
-    `sent`
-    `created_at`

#### password_reset_tokens

-    `id` (PRIMARY KEY)
-    `user_id` (FOREIGN KEY)
-    `token` (UNIQUE)
-    `expires_at`
-    `created_at`

## Frontend Integration

Update your frontend JavaScript to call the backend APIs instead of localStorage:

### Example: Get Tasks

```javascript
// Old: localStorage
// const tasks = JSON.parse(localStorage.getItem('tasks')) || [];

// New: Backend API
const response = await fetch("http://localhost:8000/backend/api/tasks.php", {
	method: "GET",
	credentials: "include",
});
const tasks = await response.json();
```

### Example: Create Task

```javascript
const response = await fetch("http://localhost:8000/backend/api/tasks.php", {
	method: "POST",
	credentials: "include",
	headers: {
		"Content-Type": "application/json",
	},
	body: JSON.stringify({
		title: "New Task",
		description: "Task description",
		priority: "high",
	}),
});
const result = await response.json();
```

## Troubleshooting

### Database Connection Error

-    Ensure PostgreSQL is running: `sudo systemctl status postgresql`
-    Verify credentials in `backend/config/database.php`
-    Check if database exists: `psql -U postgres -l`

### CORS Errors

-    The backend includes CORS headers for cross-origin requests
-    Ensure frontend URL is allowed or modify `backend/config/cors.php`

### PHP PDO Extension

-    Ensure PHP PDO PostgreSQL extension is installed:
     ```bash
     sudo apt install php-pgsql  # Linux
     brew install php@8.0 --with-pgsql  # macOS
     ```

## Production Considerations

1. Use environment variables for sensitive data (credentials, API keys)
2. Implement proper JWT token authentication
3. Add input validation and sanitization
4. Use prepared statements (already done)
5. Implement rate limiting
6. Add HTTPS enforcement
7. Set up database backups
8. Use connection pooling for better performance
9. Add logging for debugging and monitoring
10. Implement proper error handling and logging

## Additional Resources

-    [PHP Documentation](https://www.php.net/docs.php)
-    [PostgreSQL Documentation](https://www.postgresql.org/docs/)
-    [PDO Documentation](https://www.php.net/manual/en/book.pdo.php)
