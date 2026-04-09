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
			"X-Requested-With": "XMLHttpRequest",
			...options.headers,
		};

		// Add JWT token if available
		const token = localStorage.getItem("auth_token");
		if (token) {
			headers["Authorization"] = `Bearer ${token}`;
			console.log("Using auth token:", token.substring(0, 20) + "...");
		} else {
			console.log("No auth token found in localStorage");
		}

		const config = {
			method: options.method || "GET",
			headers,
			credentials: "include", // Include cookies for session
			...options,
		};

		// Log the full request details for debugging
		console.log("Request details:", {
			method: config.method,
			url: url,
			headers: headers,
			body: options.body ? JSON.parse(options.body) : null,
		});

		// Ensure the token is sent with the request (except for auth endpoints)
		const isAuthEndpoint = endpoint.includes("auth.php");
		if (!token && !isAuthEndpoint) {
			console.error("No JWT token found. Redirecting to login.");
			if (window.location.pathname !== "/") {
				window.location.href = "/";
			}
			throw new Error("No JWT token found. Please log in again.");
		}

		console.log(`Making ${config.method} request to: ${url}`);
		console.log("Request headers:", headers);

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

			console.log("Response status:", response.status);
			console.log("Response data:", data);

			if (!response.ok) {
				const error = typeof data === "object" ? data.error : data;

				// Handle specific authentication errors
				if (response.status === 401) {
					console.error("Authentication failed - clearing token");
					localStorage.removeItem("auth_token");
				}

				throw new Error(error || `HTTP ${response.status}`);
			}

			return data;
		} catch (error) {
			console.error("API Error:", error);
			if (error.name === "TypeError" && error.message.includes("fetch")) {
				throw new Error("Network error: Unable to connect to the server");
			}
			throw error;
		}
	}

	/**
	 * Make multipart/form-data API request
	 * @param {string} endpoint - API endpoint
	 * @param {FormData} formData - Multipart payload
	 * @returns {Promise} - Response JSON
	 */
	async requestFormData(endpoint, formData) {
		const url = `${this.baseURL}${endpoint}`;

		const headers = {
			"X-Requested-With": "XMLHttpRequest",
		};

		const token = localStorage.getItem("auth_token");
		if (token) {
			headers["Authorization"] = `Bearer ${token}`;
		}

		const config = {
			method: "POST",
			headers,
			credentials: "include",
			body: formData,
		};

		const response = await fetch(url, config);
		const contentType = response.headers.get("content-type");
		let data = null;

		if (contentType && contentType.includes("application/json")) {
			data = await response.json();
		} else {
			data = await response.text();
		}

		if (!response.ok) {
			const error = typeof data === "object" ? data.error : data;
			if (response.status === 401) {
				localStorage.removeItem("auth_token");
			}
			throw new Error(error || `HTTP ${response.status}`);
		}

		return data;
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
			localStorage.setItem("auth_token", result.token);
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
			localStorage.setItem("auth_token", result.token);
		}

		return result;
	}

	/**
	 * Logout user
	 */
	async logout() {
		// Remove JWT token
		localStorage.removeItem("auth_token");

		return this.request("/api/auth.php?action=logout", {
			method: "POST",
		});
	}

	// ===== TASKS =====

	/**
	 * Get all tasks
	 */
	async getTasks({ mode = "personal", organizationId = null } = {}) {
		const query = new URLSearchParams();
		query.set("mode", mode);
		if (organizationId) {
			query.set("organization_id", organizationId);
		}

		return this.request(`/api/tasks.php?${query.toString()}`, {
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

	async submitTask(id) {
		return this.request("/api/tasks.php", {
			method: "PUT",
			body: JSON.stringify({ id, action: "submit" }),
		});
	}

	async reviewTask(id, reviewAction) {
		return this.request("/api/tasks.php", {
			method: "PUT",
			body: JSON.stringify({ id, action: "review", reviewAction }),
		});
	}

	// ===== ORGANIZATIONS =====

	async getOrganizations() {
		return this.request("/api/organizations.php", {
			method: "GET",
		});
	}

	async createOrganization(name) {
		return this.request("/api/organizations.php", {
			method: "POST",
			body: JSON.stringify({ name }),
		});
	}

	async addOrganizationMember({ organizationId, userId = null, email = null, role = "member" }) {
		return this.request("/api/organizations.php?action=add-member", {
			method: "POST",
			body: JSON.stringify({
				organization_id: organizationId,
				user_id: userId,
				email,
				role,
			}),
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

	// ===== DIARY =====

	/**
	 * Get all diary entries for current user
	 */
	async getDiaryEntries() {
		return this.request("/api/diary.php", {
			method: "GET",
		});
	}

	/**
	 * Get a single diary entry by id
	 */
	async getDiaryEntry(id) {
		return this.request(`/api/diary.php?id=${id}`, {
			method: "GET",
		});
	}

	/**
	 * Create a diary entry, optionally with a voice note file/blob
	 */
	async createDiaryEntry({ title, content, entry_date, mood, audioBlob, audioFilename }) {
		if (audioBlob) {
			const formData = new FormData();
			formData.append("title", title || "");
			formData.append("content", content || "");
			formData.append("entry_date", entry_date || "");
			formData.append("mood", mood || "");
			formData.append("audio_note", audioBlob, audioFilename || "voice-note.webm");
			return this.requestFormData("/api/diary.php", formData);
		}

		return this.request("/api/diary.php", {
			method: "POST",
			body: JSON.stringify({
				title,
				content,
				entry_date,
				mood,
			}),
		});
	}

	/**
	 * Update a diary entry by id
	 */
	async updateDiaryEntry(id, { title, content, entry_date, mood }) {
		return this.request("/api/diary.php", {
			method: "PUT",
			body: JSON.stringify({
				id,
				title,
				content,
				entry_date,
				mood,
			}),
		});
	}

	/**
	 * Delete a diary entry by id
	 */
	async deleteDiaryEntry(id) {
		return this.request(`/api/diary.php?id=${id}`, {
			method: "DELETE",
		});
	}

	// ===== PREACHING =====

	/**
	 * Get all preaching entries for current user
	 */
	async getPreachingEntries(filters = {}) {
		const searchParams = new URLSearchParams();
		if (filters.search) searchParams.set("search", filters.search);
		if (filters.tag) searchParams.set("tag", filters.tag);
		const query = searchParams.toString();
		return this.request(`/api/preaching.php${query ? `?${query}` : ""}`, {
			method: "GET",
		});
	}

	/**
	 * Get a single preaching entry by id
	 */
	async getPreachingEntry(id) {
		return this.request(`/api/preaching.php?id=${id}`, {
			method: "GET",
		});
	}

	/**
	 * Create a preaching entry
	 */
	async createPreachingEntry({ title, preacher, content, tags = "" }) {
		return this.request("/api/preaching.php", {
			method: "POST",
			body: JSON.stringify({
				title,
				preacher,
				content,
				tags,
			}),
		});
	}

	/**
	 * Update a preaching entry by id
	 */
	async updatePreachingEntry(id, { title, preacher, content, tags = "" }) {
		return this.request("/api/preaching.php", {
			method: "PUT",
			body: JSON.stringify({
				id,
				title,
				preacher,
				content,
				tags,
			}),
		});
	}

	/**
	 * Delete a preaching entry by id
	 */
	async deletePreachingEntry(id) {
		return this.request(`/api/preaching.php?id=${id}`, {
			method: "DELETE",
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
