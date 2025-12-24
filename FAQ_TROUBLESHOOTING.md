# Frequently Asked Questions & Troubleshooting

## Installation & Setup

### Q: How do I install PostgreSQL?

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

-    Download from https://www.postgresql.org/download/windows/
-    Run installer and remember your postgres password

### Q: I forgot my PostgreSQL password. How do I reset it?

**Linux/macOS:**

```bash
sudo -u postgres psql
ALTER USER postgres PASSWORD 'new_password';
\q
```

**Windows:**

-    Reinstall PostgreSQL or use pgAdmin to reset

### Q: Do I need to install Composer or other dependencies?

No! The backend uses only PHP's built-in PDO extension. No external dependencies required.

## Database Issues

### Q: I get "could not connect to database" error

**Solution:**

1. Check if PostgreSQL is running:

     ```bash
     sudo systemctl status postgresql  # Linux
     brew services list | grep postgres  # macOS
     ```

2. Verify database exists:

     ```bash
     psql -U postgres -l
     ```

3. Check credentials in `backend/config/database.php`

4. Try connecting manually:
     ```bash
     psql -h localhost -U postgres -d task_management
     ```

### Q: I get "relation does not exist" error

**Solution:**

-    The database tables haven't been created yet
-    Make any API call to trigger table creation
-    Or manually run the CREATE TABLE statements from `backend/config/database.php`

### Q: How do I back up my database?

```bash
# Backup
pg_dump -U postgres task_management > backup.sql

# Restore
psql -U postgres task_management < backup.sql
```

## API & Backend Issues

### Q: I get CORS errors in the browser

**Causes:**

-    Frontend running on different port than expected
-    CORS headers not being sent

**Solutions:**

1. Check `backend/config/cors.php` is included in every endpoint
2. For development, CORS allows all origins - no config needed
3. Check browser console for exact error message

### Q: API returns 401 Unauthorized

**Causes:**

-    User not logged in
-    Session cookie not being sent
-    Missing credentials in fetch request

**Solutions:**

```javascript
// Make sure to include cookies
const response = await fetch(url, {
	method: "GET",
	credentials: "include", // This is important!
});
```

### Q: I can't log in even with correct credentials

**Solutions:**

1. Check user exists: `psql -U postgres -d task_management -c "SELECT * FROM users;"`
2. Check password is correct (it's hashed with bcrypt)
3. Try registering a new account instead
4. Check PHP error logs: `tail -f /var/log/php*.log`

### Q: API endpoint returns empty response

**Solutions:**

1. Check user_id in session:

     ```bash
     curl -c cookies.txt -X POST http://localhost:8000/backend/api/auth.php?action=login \
       -H "Content-Type: application/json" \
       -d '{"email":"test@example.com","password":"test123"}'
     ```

2. Make sure to login first before accessing protected endpoints

3. Include cookies in subsequent requests:
     ```bash
     curl -b cookies.txt http://localhost:8000/backend/api/tasks.php
     ```

## Frontend Integration Issues

### Q: My JavaScript API client is undefined

**Solution:**
Make sure to include the script BEFORE using it:

```html
<script src="/backend/TaskAPI.js"></script>
<script>
	// Now TaskAPI is available
	const api = new TaskAPI("http://localhost:8000/backend");
</script>
```

### Q: Tasks from localStorage aren't in the backend

This is expected! localStorage and backend are separate systems:

-    Old tasks are in localStorage (browser storage)
-    New tasks are in backend (database)

**Options:**

1. Use backend from now on (old data will be lost on clear)
2. Migrate old tasks to backend manually
3. Keep both systems for transition period

### Q: How do I migrate from localStorage to backend?

```javascript
const api = new TaskAPI("http://localhost:8000/backend");

// Get old tasks from localStorage
const oldTasks = JSON.parse(localStorage.getItem("tasks")) || [];

// Create them in backend
for (const task of oldTasks) {
	await api.createTask({
		title: task.title,
		description: task.description,
		priority: task.priority || "medium",
		status: task.status || "pending",
		due_date: task.dueDate || null,
	});
}

// Now you can clear localStorage
localStorage.removeItem("tasks");
```

## PHP & Server Issues

### Q: PHP reports "Class PDO not found"

**Solution:**
Install PDO PostgreSQL extension:

```bash
# Linux
sudo apt install php-pgsql

# macOS
brew install php@8.0 --with-pgsql

# Then restart PHP server
php -S localhost:8000
```

### Q: Port 8000 is already in use

**Solutions:**

```bash
# Use different port
php -S localhost:8001

# Or find what's using port 8000
lsof -i :8000
# Kill the process
kill -9 <PID>
```

### Q: "Cannot modify header information" error

**Cause:** Output was sent before headers

**Solution:**

-    Make sure no whitespace or output before `<?php` tag
-    Check file encoding is UTF-8 without BOM
-    Make sure database.php header calls are at top of file

### Q: I see a blank page instead of the API documentation

**Solutions:**

1. Check PHP is running: `php -S localhost:8000` should show output
2. Check for PHP errors: Look at console where you started PHP server
3. Try accessing directly: `curl http://localhost:8000/backend/index.php`

## Data & Security Questions

### Q: How is my password stored?

Your password is hashed using bcrypt:

```php
$hashed = password_hash($password, PASSWORD_BCRYPT);
```

Only the hash is stored, never the plain password. Even we can't see it!

### Q: How long do password reset tokens last?

Currently 1 hour. You can change this in `backend/api/password-reset.php`:

```php
$expiresAt = date('Y-m-d H:i:s', time() + 3600); // Change 3600 to different seconds
```

### Q: Can users see other users' tasks?

No! The database enforces this with user_id:

```php
// User can only see their own tasks
SELECT * FROM tasks WHERE user_id = ? AND user_id = ?
```

### Q: How do I delete a user and their data?

```bash
psql -U postgres -d task_management
DELETE FROM users WHERE id = <user_id>;  # This cascades and deletes all their data
```

## Performance & Optimization

### Q: Can I add more fields to tasks?

Yes! Add columns to the tasks table:

```bash
psql -U postgres -d task_management
ALTER TABLE tasks ADD COLUMN tags VARCHAR(255);
ALTER TABLE tasks ADD COLUMN estimated_time INTEGER;
```

Then update the API to handle new fields.

### Q: How many tasks can I store?

With PostgreSQL, you can store millions of tasks. The database will handle it efficiently.

### Q: Does the backend cache data?

Currently no, but you can add caching later:

-    Redis for session caching
-    Memcached for query results

### Q: Can I add database indexing?

Yes, for better performance:

```sql
CREATE INDEX idx_user_tasks ON tasks(user_id);
CREATE INDEX idx_task_status ON tasks(status);
```

## Production Deployment

### Q: Can I use this in production?

With some improvements:

1. Use environment variables for credentials
2. Set up HTTPS/SSL
3. Implement rate limiting
4. Add logging and monitoring
5. Set up automated backups
6. Use a proper web server (Nginx, Apache) instead of PHP built-in server

### Q: How do I deploy to production?

See the "Production Considerations" section in BACKEND_SETUP.md for detailed steps.

### Q: Should I change the default database credentials?

Yes! In production:

1. Change the postgres password
2. Create a separate user for the app (not postgres)
3. Store credentials in environment variables

## Need More Help?

1. **Check Documentation:**

     - QUICK_START.md - Fast setup guide
     - BACKEND_SETUP.md - Complete reference
     - INTEGRATION_EXAMPLES.js - Code examples

2. **Test the API:**

     - Visit http://localhost:8000/backend/ for interactive docs
     - Use curl to test endpoints

3. **Check Logs:**

     - Browser console for JavaScript errors
     - PHP console for server errors
     - PostgreSQL logs in /var/log/

4. **Reset Everything:**
     ```bash
     # Drop and recreate database
     dropdb -U postgres task_management
     createdb -U postgres task_management
     # Restart PHP server
     php -S localhost:8000
     ```

---

**Still stuck?** Make sure to:

-    Read the error message carefully
-    Check all three documentation files
-    Review the integration examples
-    Test with curl before testing in frontend
