# Quick Start Guide - Backend Setup

## Overview

Your Task Management application now has a complete PHP backend with PostgreSQL database!

## Directory Structure

```
backend/
├── api/                          # API endpoints
│   ├── auth.php                  # User authentication
│   ├── tasks.php                 # Task management
│   ├── reflections.php           # Task reflections
│   ├── reminders.php             # Task reminders
│   └── password-reset.php        # Password reset
├── config/                       # Configuration
│   ├── database.php              # Database connection
│   └── cors.php                  # CORS headers
├── middleware/                   # Middleware
│   └── auth.php                  # Authentication middleware
├── TaskAPI.js                    # JavaScript client
├── TaskAPI.php                   # PHP client
├── index.php                     # API documentation
├── setup.sh                      # Automated setup script
└── .env.example                  # Environment variables
```

## Step-by-Step Setup

### 1. Install PostgreSQL

**Linux (Ubuntu/Debian):**

```bash
sudo apt update
sudo apt install postgresql postgresql-contrib
sudo systemctl start postgresql
```

**macOS:**

```bash
brew install postgresql
brew services start postgresql
```

**Windows:**

-    Download from: https://www.postgresql.org/download/windows/
-    Run the installer and note your postgres password

### 2. Create Database

```bash
# Method 1: Automatic (Linux/macOS)
cd /path/to/Task-Management/backend
bash setup.sh

# Method 2: Manual
sudo -u postgres psql
CREATE DATABASE task_management;
\q
```

### 3. Update Configuration

Edit `backend/config/database.php` and update credentials if needed:

```php
define('DB_HOST', 'localhost');
define('DB_PORT', '5432');
define('DB_NAME', 'task_management');
define('DB_USER', 'postgres');
define('DB_PASSWORD', 'postgres');
```

### 4. Start PHP Server

```bash
cd /path/to/Task-Management
php -S localhost:8000
```

Visit: http://localhost:8000/backend/

### 5. Integrate with Frontend

Add this to your `index.html` in the `<head>`:

```html
<script src="/backend/TaskAPI.js"></script>
```

Then use in your JavaScript:

```javascript
// Initialize API
const api = new TaskAPI("http://localhost:8000/backend");

// Register
const result = await api.register("user@example.com", "password123", "username");

// Login
const result = await api.login("user@example.com", "password123");

// Get all tasks
const tasks = await api.getTasks();

// Create task
const result = await api.createTask({
	title: "My Task",
	description: "Task description",
	priority: "high",
	due_date: "2025-12-31T18:00:00",
});

// Update task
const result = await api.updateTask(1, {
	status: "completed",
	title: "Updated Title",
});

// Delete task
const result = await api.deleteTask(1);
```

## API Features

### ✅ User Authentication

-    Register new users
-    Login/logout
-    Session-based authentication
-    Password hashing with bcrypt

### ✅ Task Management

-    Create, read, update, delete tasks
-    Organize by category, priority, status
-    Set due dates
-    User-specific task isolation

### ✅ Task Reflections

-    Add reflections to completed tasks
-    Track learning and insights
-    Reflect on task outcomes

### ✅ Reminders

-    Create task reminders
-    Multiple reminder types (1 day before, 30 min before, custom)
-    Track sent reminders

### ✅ Password Reset

-    Request password reset via email
-    Token-based verification
-    Secure password updates

## Database Tables

The backend automatically creates these tables:

1. **users** - User accounts and authentication
2. **tasks** - Task records with metadata
3. **task_reflections** - Reflections on tasks
4. **reminders** - Task reminders
5. **password_reset_tokens** - Password reset tokens

## Testing the API

### Test Registration

```bash
curl -X POST http://localhost:8000/backend/api/auth.php?action=register \
  -H 'Content-Type: application/json' \
  -d '{
    "email": "test@example.com",
    "password": "test123",
    "username": "testuser"
  }'
```

### Test Login

```bash
curl -X POST http://localhost:8000/backend/api/auth.php?action=login \
  -H 'Content-Type: application/json' \
  -c cookies.txt \
  -d '{
    "email": "test@example.com",
    "password": "test123"
  }'
```

### Test Get Tasks

```bash
curl -X GET http://localhost:8000/backend/api/tasks.php \
  -b cookies.txt
```

### Test Create Task

```bash
curl -X POST http://localhost:8000/backend/api/tasks.php \
  -H 'Content-Type: application/json' \
  -b cookies.txt \
  -d '{
    "title": "Test Task",
    "description": "This is a test task",
    "priority": "high"
  }'
```

## Troubleshooting

### Database Connection Error

1. Check PostgreSQL is running: `sudo systemctl status postgresql`
2. Verify credentials in `backend/config/database.php`
3. Check database exists: `psql -U postgres -l`

### CORS Errors

The backend includes CORS headers for all requests. If you see CORS errors:

1. Check that your frontend URL matches the request origin
2. Verify `backend/config/cors.php` is included in all API files

### PHP PDO PostgreSQL Extension

If you see "could not find driver" errors:

```bash
# Linux
sudo apt install php-pgsql

# macOS
brew install php@8.0 --with-pgsql
```

### Port Already in Use

If port 8000 is taken:

```bash
php -S localhost:8001  # Use different port
```

## Next Steps

1. **Update Frontend Integration** - Modify your JavaScript to use the API client
2. **Implement Error Handling** - Handle API errors gracefully in your frontend
3. **Add Loading States** - Show loading indicators during API calls
4. **Production Deployment** - Set up proper hosting with SSL/TLS
5. **Database Backups** - Implement regular database backups

## Documentation Files

-    `BACKEND_SETUP.md` - Complete backend setup guide
-    `POSTGRES_SETUP.md` - PostgreSQL installation guide
-    `backend/index.php` - API documentation dashboard

## Support

For issues or questions:

1. Check the documentation files
2. Review the API endpoint comments in the PHP files
3. Check browser console for JavaScript errors
4. Check PHP error logs for server-side issues

---

**Your backend is now ready to power your Task Management application!** 🚀
