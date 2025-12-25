/**
 * Task Management API Client
 *
 * Usage:
 * const api = new TaskAPI('http://localhost:8000/backend');
 *
 * // Register
 * const result = await api.register('user@example.com', 'password123', 'username');
 *
 * // Login
 * const result = await api.login('user@example.com', 'password123');
 *
 * // Get tasks
 * const tasks = await api.getTasks();
 *
 * // Create task
 * const result = await api.createTask({
 *   title: 'New Task',
 *   description: 'Task description',
 *   priority: 'high',
 *   due_date: '2025-12-31T18:00:00'
 * });
 */

class TaskAPI {
	constructor(baseURL = "http://localhost:8000/backend") {
		this.baseURL = baseURL;
	}

	/**
	 * Make API request
	 * @param {string} endpoint - API endpoint
	 * @param {object} options - Request options
	 * @returns {Promise} - Response JSON
	 */
	async request(endpoint, options = {}) {
		const url = `${this.baseURL}${endpoint}`;

		const headers = {
			"Content-Type": "application/json",
			...options.headers,
		};

		// Add JWT token if available
		const token = localStorage.getItem('auth_token');
		if (token) {
			headers['Authorization'] = `Bearer ${token}`;
		}

		const config = {
			method: options.method || "GET",
			headers,
			credentials: "include", // Include cookies for session
			...options,
		};

		try {
			const response = await fetch(url, config);

			// Handle non-JSON responses
			const contentType = response.headers.get("content-type");
			let data = null;

			if (contentType && contentType.includes("application/json")) {
				data = await response.json();
			} else {
				data = await response.text();
			}

			if (!response.ok) {
				const error = typeof data === "object" ? data.error : data;
				throw new Error(error || `HTTP ${response.status}`);
			}

			return data;
		} catch (error) {
			console.error("API Error:", error);
			throw error;
		}
	}

	// ===== AUTHENTICATION =====

	/**
	 * Register a new user
	 */
	async register(email, password, username = "") {
		const result = await this.request("/api/auth.php?action=register", {
			method: "POST",
			body: JSON.stringify({ email, password, username }),
		});
		
		// Store JWT token
		if (result.token) {
			localStorage.setItem('auth_token', result.token);
		}
		
		return result;
	}

	/**
	 * Login user
	 */
	async login(email, password) {
		const result = await this.request("/api/auth.php?action=login", {
			method: "POST",
			body: JSON.stringify({ email, password }),
		});
		
		// Store JWT token
		if (result.token) {
			localStorage.setItem('auth_token', result.token);
		}
		
		return result;
	}

	/**
	 * Logout user
	 */
	async logout() {
		// Remove JWT token
		localStorage.removeItem('auth_token');
		
		return this.request("/api/auth.php?action=logout", {
			method: "POST",
		});
	}

	// ===== TASKS =====

	/**
	 * Get all tasks
	 */
	async getTasks() {
		return this.request("/api/tasks.php", {
			method: "GET",
		});
	}

	/**
	 * Create a new task
	 */
	async createTask(taskData) {
		return this.request("/api/tasks.php", {
			method: "POST",
			body: JSON.stringify(taskData),
		});
	}

	/**
	 * Update a task
	 */
	async updateTask(id, taskData) {
		return this.request("/api/tasks.php", {
			method: "PUT",
			body: JSON.stringify({ id, ...taskData }),
		});
	}

	/**
	 * Delete a task
	 */
	async deleteTask(id) {
		return this.request(`/api/tasks.php?id=${id}`, {
			method: "DELETE",
		});
	}

	// ===== REFLECTIONS =====

	/**
	 * Get reflections for a task
	 */
	async getReflections(taskId) {
		return this.request(`/api/reflections.php?task_id=${taskId}`, {
			method: "GET",
		});
	}

	/**
	 * Create a reflection for a task
	 */
	async createReflection(taskId, reflectionText) {
		return this.request("/api/reflections.php", {
			method: "POST",
			body: JSON.stringify({
				task_id: taskId,
				reflection_text: reflectionText,
			}),
		});
	}

	// ===== REMINDERS =====

	/**
	 * Get pending reminders
	 */
	async getReminders() {
		return this.request("/api/reminders.php", {
			method: "GET",
		});
	}

	/**
	 * Create a reminder for a task
	 */
	async createReminder(taskId, reminderType, reminderTime) {
		return this.request("/api/reminders.php", {
			method: "POST",
			body: JSON.stringify({
				task_id: taskId,
				reminder_type: reminderType,
				reminder_time: reminderTime,
			}),
		});
	}

	// ===== PASSWORD RESET =====

	/**
	 * Request a password reset
	 */
	async requestPasswordReset(email) {
		return this.request("/api/password-reset.php?action=request-reset", {
			method: "POST",
			body: JSON.stringify({ email }),
		});
	}

	/**
	 * Verify a password reset token
	 */
	async verifyResetToken(token) {
		return this.request("/api/password-reset.php?action=verify-token", {
			method: "POST",
			body: JSON.stringify({ token }),
		});
	}

	/**
	 * Reset password with valid token
	 */
	async resetPassword(token, newPassword) {
		return this.request("/api/password-reset.php?action=reset-password", {
			method: "POST",
			body: JSON.stringify({
				token,
				new_password: newPassword,
			}),
		});
	}
}

// Export for Node.js/modules
if (typeof module !== "undefined" && module.exports) {
	module.exports = TaskAPI;
}
