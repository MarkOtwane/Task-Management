# TaskFlow - QUICK START GUIDE

## 🚀 Getting Started

### Step 1: Setup Environment

```bash
cd /home/king/Desktop/Projects/Task-Management

# Copy environment template
cp .env.example .env

# Create required directories
mkdir -p uploads logs
```

### Step 2: Start Docker

```bash
# Build and start all services
docker-compose up -d

# Verify services are running
docker-compose ps

# Check logs if there are issues
docker-compose logs -f
```

### Step 3: Verify Database

```bash
# Wait 30 seconds for MySQL to fully initialize
# Then check if you can connect
docker exec taskflow-mysql mysql -u taskflow_user -p taskflow -e "SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema='taskflow';"

# Should return 8 tables
```

### Step 4: Test API

```bash
# Test login endpoint
curl -X POST http://localhost/backend/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@taskflow.com","password":"admin123"}'

# You should get a response with token and user info
```

## 🎨 Frontend Development

### Create Mode Selector (welcome.html)

```html
<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="UTF-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1.0" />
		<title>TaskFlow - Welcome</title>
		<style>
			* {
				margin: 0;
				padding: 0;
				box-sizing: border-box;
			}
			body {
				font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
				background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
				min-height: 100vh;
				display: flex;
				justify-content: center;
				align-items: center;
				padding: 20px;
			}
			.container {
				max-width: 800px;
				width: 100%;
			}
			.header {
				text-align: center;
				color: white;
				margin-bottom: 60px;
			}
			.header h1 {
				font-size: 48px;
				margin-bottom: 10px;
			}
			.header p {
				font-size: 18px;
				opacity: 0.95;
			}

			.modes-grid {
				display: grid;
				grid-template-columns: 1fr 1fr;
				gap: 30px;
				margin-bottom: 40px;
			}

			.mode-card {
				background: white;
				border-radius: 15px;
				padding: 40px;
				text-align: center;
				box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
				cursor: pointer;
				transition:
					transform 0.3s ease,
					box-shadow 0.3s ease;
				text-decoration: none;
				color: inherit;
			}

			.mode-card:hover {
				transform: translateY(-10px);
				box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
			}

			.mode-card h2 {
				color: #667eea;
				margin-bottom: 15px;
				font-size: 28px;
			}

			.mode-card p {
				color: #666;
				margin: 15px 0;
				line-height: 1.6;
			}

			.mode-icon {
				font-size: 60px;
				margin-bottom: 20px;
			}

			.btn {
				display: inline-block;
				background: #667eea;
				color: white;
				padding: 12px 30px;
				border-radius: 8px;
				text-decoration: none;
				margin-top: 20px;
				font-weight: 600;
				transition: background 0.3s ease;
			}

			.btn:hover {
				background: #764ba2;
			}

			@media (max-width: 600px) {
				.modes-grid {
					grid-template-columns: 1fr;
				}
				.header h1 {
					font-size: 32px;
				}
			}
		</style>
	</head>
	<body>
		<div class="container">
			<div class="header">
				<h1>TaskFlow</h1>
				<p>Choose how you want to manage your tasks</p>
			</div>

			<div class="modes-grid">
				<a href="index.html" class="mode-card">
					<div class="mode-icon">👤</div>
					<h2>Personal Mode</h2>
					<p>Single-user task management</p>
					<p style="font-size: 14px; color: #999;">
						✓ No login required<br />
						✓ Local storage<br />
						✓ Countdown timers<br />
						✓ Email reminders
					</p>
					<button class="btn">Get Started</button>
				</a>

				<a href="login.html" class="mode-card">
					<div class="mode-icon">👥</div>
					<h2>Organization Mode</h2>
					<p>Multi-user task management</p>
					<p style="font-size: 14px; color: #999;">
						✓ Role-based access<br />
						✓ Admin dashboard<br />
						✓ Task assignments<br />
						✓ Team collaboration
					</p>
					<button class="btn">Login</button>
				</a>
			</div>
		</div>
	</body>
</html>
```

### Create Login Page (login.html)

```html
<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="UTF-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1.0" />
		<title>TaskFlow - Login</title>
		<style>
			* {
				margin: 0;
				padding: 0;
				box-sizing: border-box;
			}
			body {
				font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
				background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
				min-height: 100vh;
				display: flex;
				justify-content: center;
				align-items: center;
				padding: 20px;
			}

			.login-form {
				background: white;
				border-radius: 15px;
				padding: 40px;
				width: 100%;
				max-width: 400px;
				box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
			}

			.login-form h1 {
				color: #667eea;
				margin-bottom: 10px;
				text-align: center;
				font-size: 32px;
			}

			.login-form p {
				color: #999;
				text-align: center;
				margin-bottom: 30px;
				font-size: 14px;
			}

			.form-group {
				margin-bottom: 20px;
			}

			.form-group label {
				display: block;
				margin-bottom: 8px;
				color: #333;
				font-weight: 600;
			}

			.form-group input {
				width: 100%;
				padding: 12px;
				border: 2px solid #e0e0e0;
				border-radius: 8px;
				font-size: 14px;
				transition: border-color 0.3s ease;
			}

			.form-group input:focus {
				outline: none;
				border-color: #667eea;
			}

			.btn-login {
				width: 100%;
				background: #667eea;
				color: white;
				border: none;
				padding: 12px;
				border-radius: 8px;
				font-size: 16px;
				font-weight: 600;
				cursor: pointer;
				transition: background 0.3s ease;
			}

			.btn-login:hover {
				background: #764ba2;
			}

			.auth-switch {
				text-align: center;
				margin-top: 20px;
				color: #999;
				font-size: 14px;
			}

			.auth-switch a {
				color: #667eea;
				text-decoration: none;
				font-weight: 600;
			}

			.error-msg {
				background: #fee;
				color: #c33;
				padding: 12px;
				border-radius: 8px;
				margin-bottom: 20px;
				display: none;
			}
		</style>
	</head>
	<body>
		<div class="login-form">
			<h1>TaskFlow</h1>
			<p>Organization Mode - Sign In</p>

			<div class="error-msg" id="errorMsg"></div>

			<form id="loginForm">
				<div class="form-group">
					<label for="email">Email Address</label>
					<input type="email" id="email" name="email" required />
				</div>

				<div class="form-group">
					<label for="password">Password</label>
					<input type="password" id="password" name="password" required />
				</div>

				<button type="submit" class="btn-login">Sign In</button>
			</form>

			<div class="auth-switch">
				Don't have an account? <a href="register.html">Create one</a><br />
				Forgot password? <a href="#forgot-password">Reset it</a>
			</div>
		</div>

		<script>
			const form = document.getElementById("loginForm");
			const errorMsg = document.getElementById("errorMsg");

			form.addEventListener("submit", async (e) => {
				e.preventDefault();

				const email = document.getElementById("email").value;
				const password = document.getElementById("password").value;

				try {
					const response = await fetch("/backend/api/auth/login", {
						method: "POST",
						headers: { "Content-Type": "application/json" },
						body: JSON.stringify({ email, password }),
					});

					const data = await response.json();

					if (response.ok && data.token) {
						// Store token and user info
						localStorage.setItem("token", data.token);
						localStorage.setItem(
							"user",
							JSON.stringify({
								id: data.user_id,
								name: data.name,
								email: data.email,
								role: data.role,
							}),
						);

						// Redirect based on role
						if (data.role === "admin") {
							window.location.href = "admin-dashboard.html";
						} else {
							window.location.href = "employee-dashboard.html";
						}
					} else {
						errorMsg.textContent = data.error || "Login failed";
						errorMsg.style.display = "block";
					}
				} catch (err) {
					errorMsg.textContent = "Network error. Please try again.";
					errorMsg.style.display = "block";
				}
			});
		</script>
	</body>
</html>
```

## 📡 API Usage Examples

### JavaScript Fetch with Authentication

```javascript
// Get stored token
const token = localStorage.getItem("token");

// Fetch tasks
fetch("/backend/api/tasks/my-tasks", {
	method: "GET",
	headers: {
		Authorization: `Bearer ${token}`,
		"Content-Type": "application/json",
	},
})
	.then((res) => res.json())
	.then((data) => {
		console.log("Tasks:", data.tasks);
		// Render tasks
	});
```

### Create Task

```javascript
const taskData = {
	title: "Complete Project",
	description: "Finish Q1 report",
	assigned_to: 2,
	deadline: "2026-03-15",
	due_time: "17:00",
	reminder_type: "1day",
	priority: "high",
};

fetch("/backend/api/tasks/create", {
	method: "POST",
	headers: {
		Authorization: `Bearer ${token}`,
		"Content-Type": "application/json",
	},
	body: JSON.stringify(taskData),
})
	.then((res) => res.json())
	.then((data) => console.log("Task created:", data));
```

### Submit Task

```javascript
const formData = new FormData();
formData.append("task_id", 1);
formData.append("submission_text", "Completed task");
formData.append("file", fileInput.files[0]); // Optional

fetch("/backend/api/submissions/submit", {
	method: "POST",
	headers: { Authorization: `Bearer ${token}` },
	body: formData,
})
	.then((res) => res.json())
	.then((data) => console.log("Submitted:", data));
```

## 📊 Dashboard Data Structure

### Admin Dashboard Data

```javascript
{
    dashboard: {
        total_tasks: 10,
        completed_tasks: 6,
        pending_tasks: 3,
        in_progress_tasks: 1,
        total_employees: 5,
        total_submissions: 8,
        approved_submissions: 6,
        rejected_submissions: 1,
        pending_submissions: 1,
        completion_rate: 60.0
    }
}
```

### Employee Dashboard Data

```javascript
{
    dashboard: {
        total_tasks: 3,
        completed_tasks: 2,
        pending_tasks: 1,
        pending_submissions: 1,
        approved_submissions: 1,
        rejected_submissions: 0,
        overdue_tasks: 0,
        completion_rate: 66.67
    }
}
```

## 🔒 Security Reminders

1. **Always use HTTPS in production**
2. **Store tokens securely** (localStorage acceptable for demo, use httpOnly cookies in production)
3. **Validate all inputs** on frontend and backend
4. **Use prepared statements** (already implemented in API)
5. **Set proper CORS** for your production domain
6. **Implement rate limiting** for login attempts
7. **Use environment variables** for sensitive data

## 🆘 Common Issues & Fixes

### "Cannot connect to MySQL"

```bash
# Check if MySQL is running
docker-compose ps

# Restart MySQL
docker-compose restart mysql

# Check logs
docker-compose logs mysql
```

### "API returns 401 Unauthorized"

- Check token is stored in localStorage
- Verify token is included in Authorization header
- Check token hasn't expired
- Verify session exists in database

### "404 - Route not found"

- Check URL path is correct
- Verify action parameter is passed
- Check Apache rewrite rules are enabled
- Review PHP error logs

### "CORS error"

- Check apache-config.conf has CORS headers
- Verify frontend URL matches CORS origin
- Check browser console for exact error

## 📞 Support Resources

- **Database**: http://localhost:8081 (phpMyAdmin)
- **API Documentation**: See IMPLEMENTATION_COMPLETE.md
- **Error Logs**: `logs/php_errors.log`
- **Docker Logs**: `docker-compose logs -f`

---

**Status**: Backend Complete ✅  
**Next Step**: Build Frontend Components  
**Estimated Time**: 6-8 hours for full frontend
