/**
 * Countdown Timer Module
 * Manages countdown timers for tasks with database deadline support
 * Works with both personal mode (localStorage) and organizational mode (backend)
 */

class CountdownTimer {
	constructor() {
		this.timers = new Map();
		this.updateInterval = null;
	}

	/**
	 * Start countdown timer for a task
	 * @param {number|string} taskId - Task unique identifier
	 * @param {string} deadline - Deadline date/time string (ISO 8601 format)
	 * @param {Function} onUpdate - Callback when timer updates
	 * @param {Function} onExpire - Callback when timer expires
	 */
	start(taskId, deadline, onUpdate = null, onExpire = null) {
		if (!deadline) return;

		const deadlineDate = new Date(deadline);
		if (isNaN(deadlineDate.getTime())) {
			console.warn(`Invalid deadline format for task ${taskId}: ${deadline}`);
			return;
		}

		this.timers.set(taskId, {
			deadline: deadlineDate,
			onUpdate,
			onExpire,
			expired: false,
		});

		// Update immediately
		this.updateTimer(taskId);

		// Start interval if not already running
		if (!this.updateInterval) {
			this.updateInterval = setInterval(() => this.updateAllTimers(), 1000);
		}
	}

	/**
	 * Update a single timer
	 * @param {number|string} taskId - Task identifier
	 */
	updateTimer(taskId) {
		const timer = this.timers.get(taskId);
		if (!timer) return;

		const now = new Date();
		const timeDiff = timer.deadline - now;

		const timerState = this.calculateTimerState(timeDiff);

		if (timer.onUpdate) {
			timer.onUpdate(timerState);
		}

		// Handle expiration
		if (timeDiff <= 0 && !timer.expired) {
			timer.expired = true;
			if (timer.onExpire) {
				timer.onExpire(timerState);
			}
		}
	}

	/**
	 * Update all active timers
	 */
	updateAllTimers() {
		this.timers.forEach((timer, taskId) => {
			this.updateTimer(taskId);
		});
	}

	/**
	 * Calculate timer state object
	 * @private
	 */
	calculateTimerState(timeDiff) {
		const isOverdue = timeDiff < 0;
		const absTime = Math.abs(timeDiff);

		const days = Math.floor(absTime / (1000 * 60 * 60 * 24));
		const hours = Math.floor((absTime % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
		const minutes = Math.floor((absTime % (1000 * 60 * 60)) / (1000 * 60));
		const seconds = Math.floor((absTime % (1000 * 60)) / 1000);

		return {
			isOverdue,
			totalSeconds: Math.floor(timeDiff / 1000),
			days,
			hours,
			minutes,
			seconds,
			status: this.getStatus(timeDiff),
			display: this.formatDisplay(days, hours, minutes, seconds, isOverdue),
			percentage: Math.max(0, Math.min(100, (timeDiff / (7 * 24 * 60 * 60 * 1000)) * 100)),
		};
	}

	/**
	 * Get status string for timer
	 * @private
	 */
	getStatus(timeDiff) {
		if (timeDiff < 0) return "overdue";
		if (timeDiff <= 1000 * 60 * 60) return "urgent"; // < 1 hour
		if (timeDiff <= 1000 * 60 * 60 * 24) return "warning"; // < 24 hours
		if (timeDiff <= 1000 * 60 * 60 * 24 * 3) return "alert"; // < 3 days
		return "normal";
	}

	/**
	 * Format timer for display
	 * @private
	 */
	formatDisplay(days, hours, minutes, seconds, isOverdue) {
		if (isOverdue) {
			if (days > 0) return `Overdue by ${days}d`;
			if (hours > 0) return `Overdue by ${hours}h`;
			return `Overdue by ${minutes}m`;
		}

		if (days > 0) return `${days}d ${hours}h`;
		if (hours > 0) return `${hours}h ${minutes}m`;
		return `${minutes}m ${seconds}s`;
	}

	/**
	 * Stop timer for specific task
	 * @param {number|string} taskId - Task identifier
	 */
	stop(taskId) {
		this.timers.delete(taskId);
		if (this.timers.size === 0 && this.updateInterval) {
			clearInterval(this.updateInterval);
			this.updateInterval = null;
		}
	}

	/**
	 * Stop all timers
	 */
	stopAll() {
		this.timers.clear();
		if (this.updateInterval) {
			clearInterval(this.updateInterval);
			this.updateInterval = null;
		}
	}

	/**
	 * Get current timer state
	 * @param {number|string} taskId - Task identifier
	 */
	getState(taskId) {
		const timer = this.timers.get(taskId);
		if (!timer) return null;

		const timeDiff = timer.deadline - new Date();
		return this.calculateTimerState(timeDiff);
	}
}

/**
 * DOM Element Updater for Countdown Timers
 * Updates HTML elements with timer state
 */
class TimerElementUpdater {
	/**
	 * Update a DOM element with timer state
	 * @param {HTMLElement} element - Element to update
	 * @param {Object} timerState - Timer state object from CountdownTimer
	 */
	static updateElement(element, timerState) {
		if (!element) return;

		// Update text content
		element.textContent = timerState.display;

		// Update classes for styling
		element.className = "timer-display timer-" + timerState.status;

		// Update data attributes for styling hooks
		element.setAttribute("data-status", timerState.status);
		element.setAttribute("data-days", timerState.days);
		element.setAttribute("data-hours", timerState.hours);
	}

	/**
	 * Create and update a timer element
	 * @param {Object} options - Configuration options
	 * @returns {HTMLElement} Timer element
	 */
	static createTimerElement(options = {}) {
		const element = document.createElement("div");
		element.className = "timer-display";
		element.style.cssText = options.style || "";

		if (options.className) {
			element.className += " " + options.className;
		}

		return element;
	}

	/**
	 * Render timer in a task card (for dashboards)
	 * @param {HTMLElement} cardElement - Task card element
	 * @param {Object} timerState - Timer state
	 */
	static renderInCard(cardElement, timerState) {
		let timerEl = cardElement.querySelector(".timer-display");
		if (!timerEl) {
			timerEl = TimerElementUpdater.createTimerElement();
			cardElement.appendChild(timerEl);
		}

		TimerElementUpdater.updateElement(timerEl, timerState);
	}
}

/**
 * Personal Mode Timer Integration
 * Manages timers for tasks stored in localStorage
 */
class PersonalModeTimer {
	constructor() {
		this.timer = new CountdownTimer();
		this.taskTimerMap = new Map();
	}

	/**
	 * Initialize timers for all personal mode tasks
	 */
	initializeAllTasks() {
		const tasks = this.getPersonalTasks();
		tasks.forEach((task) => {
			this.attachTimer(task);
		});
	}

	/**
	 * Get tasks from localStorage (personal mode)
	 * @returns {Array} Array of task objects
	 */
	getPersonalTasks() {
		const tasksJSON = localStorage.getItem("tasks");
		return tasksJSON ? JSON.parse(tasksJSON) : [];
	}

	/**
	 * Attach timer to a task
	 * @param {Object} task - Task object with deadline property
	 */
	attachTimer(task) {
		if (!task.deadline) return;

		const onUpdate = (state) => {
			const element = document.querySelector(`[data-task-id="${task.id}"] .task-deadline-timer`);
			if (element) {
				TimerElementUpdater.updateElement(element, state);
			}
		};

		const onExpire = (state) => {
			const taskEl = document.querySelector(`[data-task-id="${task.id}"]`);
			if (taskEl) {
				taskEl.classList.add("task-overdue");
				const notificationEl = taskEl.querySelector(".task-status");
				if (notificationEl) {
					notificationEl.textContent = "⏰ OVERDUE";
					notificationEl.style.color = "#ff6b6b";
				}
			}

			// Local notification
			if (Notification.permission === "granted") {
				new Notification("Task Overdue", {
					body: `Task "${task.title}" is now overdue!`,
					icon: "/logo.png",
				});
			}
		};

		this.timer.start(task.id, task.deadline, onUpdate, onExpire);
		this.taskTimerMap.set(task.id, task.deadline);
	}

	/**
	 * Remove timer for a task
	 * @param {number|string} taskId - Task ID
	 */
	removeTimer(taskId) {
		this.timer.stop(taskId);
		this.taskTimerMap.delete(taskId);
	}

	/**
	 * Update timer when task deadline changes
	 * @param {Object} task - Updated task object
	 */
	updateTimer(task) {
		this.removeTimer(task.id);
		this.attachTimer(task);
	}

	/**
	 * Clean up all timers
	 */
	cleanup() {
		this.timer.stopAll();
		this.taskTimerMap.clear();
	}
}

/**
 * Backend Mode Timer Integration
 * Manages timers for tasks from backend API
 */
class BackendModeTimer {
	constructor() {
		this.timer = new CountdownTimer();
		this.taskTimerMap = new Map();
	}

	/**
	 * Attach timer to a backend task
	 * @param {Object} task - Task object from API with deadline property
	 */
	attachTimer(task) {
		if (!task.deadline) return;

		const onUpdate = (state) => {
			const element = document.querySelector(`[data-task-id="${task.id}"] .task-deadline-timer`);
			if (element) {
				TimerElementUpdater.updateElement(element, state);
			}
		};

		const onExpire = (state) => {
			const taskEl = document.querySelector(`[data-task-id="${task.id}"]`);
			if (taskEl) {
				taskEl.classList.add("task-overdue");
			}

			// Backend notification would come from reminder system
			// This just updates the UI
			if (Notification.permission === "granted") {
				new Notification("Task Overdue", {
					body: `Task "${task.title}" deadline has passed!`,
					icon: "/logo.png",
				});
			}
		};

		this.timer.start(task.id, task.deadline, onUpdate, onExpire);
		this.taskTimerMap.set(task.id, task.deadline);
	}

	/**
	 * Remove timer for a task
	 * @param {number|string} taskId - Task ID
	 */
	removeTimer(taskId) {
		this.timer.stop(taskId);
		this.taskTimerMap.delete(taskId);
	}

	/**
	 * Clean up all timers
	 */
	cleanup() {
		this.timer.stopAll();
		this.taskTimerMap.clear();
	}
}

/**
 * Global Timer Management
 * Singleton for managing timers across the application
 */
class TimerManager {
	static instance = null;
	static personalMode = null;
	static backendMode = null;

	static getInstance() {
		if (!TimerManager.instance) {
			TimerManager.instance = new TimerManager();
		}
		return TimerManager.instance;
	}

	constructor() {
		this.currentMode = this.detectMode();

		if (this.currentMode === "personal") {
			TimerManager.personalMode = new PersonalModeTimer();
		} else if (this.currentMode === "backend") {
			TimerManager.backendMode = new BackendModeTimer();
		}
	}

	/**
	 * Detect current application mode
	 * @returns {string} 'personal' or 'backend'
	 */
	detectMode() {
		const token = localStorage.getItem("token");
		const user = localStorage.getItem("user");

		// If token exists, we're in organization/backend mode
		if (token && user) {
			return "backend";
		}

		// Otherwise personal mode
		return "personal";
	}

	/**
	 * Attach timer to a task
	 * @param {Object} task - Task object
	 */
	attachTimer(task) {
		if (this.currentMode === "personal" && TimerManager.personalMode) {
			TimerManager.personalMode.attachTimer(task);
		} else if (this.currentMode === "backend" && TimerManager.backendMode) {
			TimerManager.backendMode.attachTimer(task);
		}
	}

	/**
	 * Attach timers to multiple tasks
	 * @param {Array} tasks - Array of task objects
	 */
	attachTimers(tasks) {
		tasks.forEach((task) => this.attachTimer(task));
	}

	/**
	 * Remove timer for a task
	 * @param {number|string} taskId - Task ID
	 */
	removeTimer(taskId) {
		if (this.currentMode === "personal" && TimerManager.personalMode) {
			TimerManager.personalMode.removeTimer(taskId);
		} else if (this.currentMode === "backend" && TimerManager.backendMode) {
			TimerManager.backendMode.removeTimer(taskId);
		}
	}

	/**
	 * Get current timer state
	 * @param {number|string} taskId - Task ID
	 * @returns {Object} Timer state
	 */
	getState(taskId) {
		if (this.currentMode === "personal" && TimerManager.personalMode) {
			return TimerManager.personalMode.timer.getState(taskId);
		} else if (this.currentMode === "backend" && TimerManager.backendMode) {
			return TimerManager.backendMode.timer.getState(taskId);
		}
		return null;
	}

	/**
	 * Clean up all timers
	 */
	cleanup() {
		if (TimerManager.personalMode) {
			TimerManager.personalMode.cleanup();
		}
		if (TimerManager.backendMode) {
			TimerManager.backendMode.cleanup();
		}
	}
}

// Initialize on page load
document.addEventListener("DOMContentLoaded", () => {
	TimerManager.getInstance();
});

// Clean up on page unload
window.addEventListener("beforeunload", () => {
	const timerManager = TimerManager.getInstance();
	timerManager.cleanup();
});

// Export for use in other modules
if (typeof module !== "undefined" && module.exports) {
	module.exports = {
		CountdownTimer,
		TimerElementUpdater,
		PersonalModeTimer,
		BackendModeTimer,
		TimerManager,
	};
}
