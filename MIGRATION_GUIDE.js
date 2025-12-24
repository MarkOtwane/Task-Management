/**
 * Migration Guide: From localStorage to Backend
 *
 * This guide helps you replace localStorage-based task storage
 * with the new PostgreSQL backend API
 */

/**
 * STEP 1: Update Your HTML
 *
 * Add the TaskAPI script to your <head> section:
 */

/*
<head>
    <!-- Other head elements -->
    <script src="/backend/TaskAPI.js"></script>
</head>
*/

/**
 * STEP 2: Initialize API Client
 *
 * Replace your localStorage initialization with the API client
 */

// OLD: localStorage approach
// const tasks = JSON.parse(localStorage.getItem('tasks')) || [];

// NEW: Backend API approach
const api = new TaskAPI("http://localhost:8000/backend");

/**
 * STEP 3: Replace localStorage.getItem() calls
 */

// Example 1: Get all tasks
// OLD:
// function getTasks() {
//     return JSON.parse(localStorage.getItem('tasks')) || [];
// }

// NEW:
async function getTasks() {
	try {
		return await api.getTasks();
	} catch (error) {
		console.error("Failed to get tasks:", error);
		return [];
	}
}

// Example 2: Get user data
// OLD:
// const userData = JSON.parse(localStorage.getItem('user')) || {};

// NEW:
async function getCurrentUser() {
	try {
		// User info is stored in session after login
		const userId = sessionStorage.getItem("userId");
		return { id: userId };
	} catch (error) {
		console.error("Failed to get user:", error);
		return null;
	}
}

/**
 * STEP 4: Replace localStorage.setItem() calls
 */

// Example 1: Create task
// OLD:
// function createTask(task) {
//     let tasks = JSON.parse(localStorage.getItem('tasks')) || [];
//     task.id = Date.now();
//     tasks.push(task);
//     localStorage.setItem('tasks', JSON.stringify(tasks));
//     return task;
// }

// NEW:
async function createTask(task) {
	try {
		const result = await api.createTask({
			title: task.title,
			description: task.description,
			category: task.category,
			priority: task.priority || "medium",
			due_date: task.due_date || null,
		});
		console.log("Task created:", result);
		return result;
	} catch (error) {
		console.error("Failed to create task:", error);
		throw error;
	}
}

// Example 2: Save user data
// OLD:
// localStorage.setItem('user', JSON.stringify(userData));

// NEW:
async function loginUser(email, password) {
	try {
		const result = await api.login(email, password);
		sessionStorage.setItem("userId", result.user_id);
		return result;
	} catch (error) {
		console.error("Login failed:", error);
		throw error;
	}
}

/**
 * STEP 5: Replace localStorage.removeItem() calls
 */

// Example 1: Delete task
// OLD:
// function deleteTask(taskId) {
//     let tasks = JSON.parse(localStorage.getItem('tasks')) || [];
//     tasks = tasks.filter(t => t.id !== taskId);
//     localStorage.setItem('tasks', JSON.stringify(tasks));
// }

// NEW:
async function deleteTask(taskId) {
	try {
		await api.deleteTask(taskId);
		console.log("Task deleted");
	} catch (error) {
		console.error("Failed to delete task:", error);
		throw error;
	}
}

// Example 2: Logout
// OLD:
// localStorage.removeItem('user');

// NEW:
async function logoutUser() {
	try {
		await api.logout();
		sessionStorage.removeItem("userId");
	} catch (error) {
		console.error("Logout failed:", error);
	}
}

/**
 * STEP 6: Handle Async Operations
 *
 * The backend is async, so you need to use await/async
 */

// OLD: Synchronous
// const tasks = getTasks();
// displayTasks(tasks);

// NEW: Asynchronous
// async function loadAndDisplay() {
//     const tasks = await getTasks();
//     displayTasks(tasks);
// }

/**
 * STEP 7: Update Event Handlers
 */

// OLD: Form submission
/*
document.getElementById('create-task-form').addEventListener('submit', (e) => {
    e.preventDefault();
    const task = {
        title: document.getElementById('title').value,
        description: document.getElementById('description').value
    };
    createTask(task);
    displayTasks(getTasks());
});
*/

// NEW: Form submission with async/await
/*
document.getElementById('create-task-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    try {
        const task = {
            title: document.getElementById('title').value,
            description: document.getElementById('description').value
        };
        await createTask(task);
        const tasks = await getTasks();
        displayTasks(tasks);
    } catch (error) {
        console.error('Error:', error);
        showErrorMessage('Failed to create task');
    }
});
*/

/**
 * STEP 8: Complete Migration Checklist
 */

/*
☐ 1. Include TaskAPI.js script in HTML
☐ 2. Initialize API client: const api = new TaskAPI('...')
☐ 3. Replace getTasks() calls
☐ 4. Replace createTask() calls  
☐ 5. Replace updateTask() calls
☐ 6. Replace deleteTask() calls
☐ 7. Replace login/register calls
☐ 8. Update event handlers to be async
☐ 9. Add error handling to all API calls
☐ 10. Test all functionality
☐ 11. Migrate old localStorage tasks (optional)
☐ 12. Remove localStorage code
☐ 13. Remove old database initialization code
☐ 14. Test on different browsers
☐ 15. Deploy and monitor
*/

/**
 * STEP 9: Migration from Old Data (Optional)
 *
 * If you want to keep your old tasks
 */

async function migrateOldTasks() {
	try {
		// Get old tasks from localStorage
		const oldTasks = JSON.parse(localStorage.getItem("tasks")) || [];

		console.log(`Migrating ${oldTasks.length} old tasks...`);

		// Create each task in the new backend
		for (const oldTask of oldTasks) {
			await api.createTask({
				title: oldTask.title,
				description: oldTask.description,
				category: oldTask.category || "general",
				priority: oldTask.priority || "medium",
				status: oldTask.status || "pending",
				due_date: oldTask.due_date || null,
			});
		}

		console.log("Migration complete!");

		// Optional: Clear localStorage after successful migration
		// localStorage.removeItem('tasks');
	} catch (error) {
		console.error("Migration failed:", error);
	}
}

/**
 * STEP 10: Complete Modernized Example
 *
 * Here's how your code should look after migration
 */

// Initialize API
const app = {
	api: new TaskAPI("http://localhost:8000/backend"),
	currentUser: null,

	// Initialize the app
	async init() {
		console.log("Initializing Task Manager...");
		this.setupEventListeners();
		// Check if user is logged in
		const userId = sessionStorage.getItem("userId");
		if (userId) {
			this.currentUser = { id: userId };
			await this.loadTasks();
		} else {
			this.showLoginForm();
		}
	},

	// Setup event listeners
	setupEventListeners() {
		// Form submission
		const form = document.getElementById("create-task-form");
		if (form) {
			form.addEventListener("submit", (e) => this.handleCreateTask(e));
		}

		// Logout button
		const logoutBtn = document.getElementById("logout-btn");
		if (logoutBtn) {
			logoutBtn.addEventListener("click", () => this.handleLogout());
		}
	},

	// Load and display tasks
	async loadTasks() {
		try {
			const tasks = await this.api.getTasks();
			this.displayTasks(tasks);
		} catch (error) {
			console.error("Failed to load tasks:", error);
			this.showError("Failed to load tasks");
		}
	},

	// Handle task creation
	async handleCreateTask(e) {
		e.preventDefault();
		try {
			const title = document.getElementById("task-title").value;
			const description = document.getElementById("task-description").value;

			await this.api.createTask({
				title,
				description,
				priority: "medium",
			});

			document.getElementById("create-task-form").reset();
			await this.loadTasks();
		} catch (error) {
			console.error("Failed to create task:", error);
			this.showError("Failed to create task");
		}
	},

	// Handle logout
	async handleLogout() {
		try {
			await this.api.logout();
			sessionStorage.removeItem("userId");
			this.currentUser = null;
			this.showLoginForm();
		} catch (error) {
			console.error("Logout failed:", error);
			this.showError("Logout failed");
		}
	},

	// Display tasks
	displayTasks(tasks) {
		const container = document.getElementById("tasks-container");
		if (!container) return;

		container.innerHTML = "";
		tasks.forEach((task) => {
			const el = document.createElement("div");
			el.className = "task-card";
			el.innerHTML = `
                <h3>${task.title}</h3>
                <p>${task.description || "No description"}</p>
                <button onclick="app.completeTask(${task.id})">Complete</button>
                <button onclick="app.deleteTask(${task.id})">Delete</button>
            `;
			container.appendChild(el);
		});
	},

	// Complete task
	async completeTask(taskId) {
		try {
			await this.api.updateTask(taskId, { status: "completed" });
			await this.loadTasks();
		} catch (error) {
			console.error("Failed to complete task:", error);
			this.showError("Failed to complete task");
		}
	},

	// Delete task
	async deleteTask(taskId) {
		if (!confirm("Are you sure?")) return;
		try {
			await this.api.deleteTask(taskId);
			await this.loadTasks();
		} catch (error) {
			console.error("Failed to delete task:", error);
			this.showError("Failed to delete task");
		}
	},

	// Show error
	showError(message) {
		const alert = document.createElement("div");
		alert.className = "error-alert";
		alert.textContent = message;
		document.body.appendChild(alert);
		setTimeout(() => alert.remove(), 3000);
	},

	// Show login form
	showLoginForm() {
		document.getElementById("app-content").innerHTML = `
            <form id="login-form">
                <input type="email" placeholder="Email" required>
                <input type="password" placeholder="Password" required>
                <button type="submit">Login</button>
            </form>
        `;
		document.getElementById("login-form").addEventListener("submit", (e) => this.handleLogin(e));
	},
};

// Start the app when DOM is ready
document.addEventListener("DOMContentLoaded", () => app.init());

/**
 * TROUBLESHOOTING DURING MIGRATION
 */

/*
Problem: "TaskAPI is not defined"
Solution: Make sure <script src="/backend/TaskAPI.js"></script> is in your HTML

Problem: "401 Unauthorized"
Solution: User needs to login first. Check sessionStorage.getItem('userId')

Problem: "Tasks are empty after migration"
Solution: You need to be logged in. Call api.login() first

Problem: "Mixed content error"
Solution: If using HTTPS frontend, backend needs HTTPS too

Problem: "CORS error"
Solution: Check that API is running on http://localhost:8000

Problem: "Tasks disappear on refresh"
Solution: This is expected - database stores data, not browser cache

Problem: "Cannot read property 'getTasks' of undefined"
Solution: Make sure API client is initialized before using it
*/

/**
 * KEY DIFFERENCES: localStorage vs Backend
 */

/*
localStorage approach:
- Synchronous (instant)
- Data stored locally
- No server needed
- Limited to ~5MB
- Lost when cache is cleared

Backend API approach:
- Asynchronous (requires await/async)
- Data stored on server
- Accessible from any device
- Unlimited storage
- Persistent across devices
- More secure
- Scalable
*/

/**
 * BEST PRACTICES
 */

/*
1. Always use try/catch with await
2. Show loading indicators during API calls
3. Handle network errors gracefully
4. Validate data before sending
5. Use meaningful error messages
6. Test thoroughly before deploying
7. Keep API client initialization in one place
8. Use constants for API endpoints
9. Log important events for debugging
10. Test with poor network conditions
*/

/**
 * NEED HELP?
 *
 * See:
 * - QUICK_START.md - Integration guide
 * - INTEGRATION_EXAMPLES.js - More code examples
 * - FAQ_TROUBLESHOOTING.md - Common issues
 */
