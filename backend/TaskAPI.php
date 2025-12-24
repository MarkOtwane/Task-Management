<?php
/**
 * Frontend API Client
 * Include this in your HTML or JavaScript module
 * 
 * Usage in JavaScript:
 * const api = new TaskAPI('http://localhost:8000/backend');
 * const tasks = await api.getTasks();
 */

class TaskAPI {
    constructor(baseURL = 'http://localhost:8000/backend') {
        this.baseURL = baseURL;
        this.headers = {
            'Content-Type': 'application/json'
        };
    }

    /**
     * Make API request
     */
    async request(endpoint, options = {}) {
        const url = `${this.baseURL}${endpoint}`;
        const config = {
            ...options,
            headers: {
                ...this.headers,
                ...options.headers
            },
            credentials: 'include' // Include cookies for session
        };

        try {
            const response = await fetch(url, config);
            
            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.error || `HTTP ${response.status}`);
            }
            
            return await response.json();
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }

    // ===== AUTH ENDPOINTS =====

    async register(email, password, username = '') {
        return this.request('/api/auth.php?action=register', {
            method: 'POST',
            body: JSON.stringify({ email, password, username })
        });
    }

    async login(email, password) {
        return this.request('/api/auth.php?action=login', {
            method: 'POST',
            body: JSON.stringify({ email, password })
        });
    }

    async logout() {
        return this.request('/api/auth.php?action=logout', {
            method: 'POST'
        });
    }

    // ===== TASKS ENDPOINTS =====

    async getTasks() {
        return this.request('/api/tasks.php');
    }

    async createTask(taskData) {
        return this.request('/api/tasks.php', {
            method: 'POST',
            body: JSON.stringify(taskData)
        });
    }

    async updateTask(id, taskData) {
        return this.request('/api/tasks.php', {
            method: 'PUT',
            body: JSON.stringify({ id, ...taskData })
        });
    }

    async deleteTask(id) {
        return this.request(`/api/tasks.php?id=${id}`, {
            method: 'DELETE'
        });
    }

    // ===== REFLECTIONS ENDPOINTS =====

    async getReflections(taskId) {
        return this.request(`/api/reflections.php?task_id=${taskId}`);
    }

    async createReflection(taskId, reflectionText) {
        return this.request('/api/reflections.php', {
            method: 'POST',
            body: JSON.stringify({ task_id: taskId, reflection_text: reflectionText })
        });
    }

    // ===== REMINDERS ENDPOINTS =====

    async getReminders() {
        return this.request('/api/reminders.php');
    }

    async createReminder(taskId, reminderType, reminderTime) {
        return this.request('/api/reminders.php', {
            method: 'POST',
            body: JSON.stringify({ 
                task_id: taskId, 
                reminder_type: reminderType,
                reminder_time: reminderTime
            })
        });
    }

    // ===== PASSWORD RESET ENDPOINTS =====

    async requestPasswordReset(email) {
        return this.request('/api/password-reset.php?action=request-reset', {
            method: 'POST',
            body: JSON.stringify({ email })
        });
    }

    async verifyResetToken(token) {
        return this.request('/api/password-reset.php?action=verify-token', {
            method: 'POST',
            body: JSON.stringify({ token })
        });
    }

    async resetPassword(token, newPassword) {
        return this.request('/api/password-reset.php?action=reset-password', {
            method: 'POST',
            body: JSON.stringify({ token, new_password: newPassword })
        });
    }
}

// Export for use in modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = TaskAPI;
}
