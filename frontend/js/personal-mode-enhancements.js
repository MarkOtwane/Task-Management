/**
 * Personal Mode Enhancements
 * Adds countdown timer support and organization mode switcher to personal mode index.html
 */

document.addEventListener("DOMContentLoaded", function () {
	initializePersonalModeEnhancements();
});

/**
 * Initialize personal mode enhancements
 */
function initializePersonalModeEnhancements() {
	ensureFontAwesomeLoaded();

	// Check if user is logged in to organization mode
	const token = localStorage.getItem("token");
	const user = localStorage.getItem("user");

	// Add organization mode switcher if not in organization mode
	if (!token || !user) {
		addOrganizationModeSwitcher();
	} else {
		// If logged in, redirect to appropriate dashboard
		redirectToDashboard(user);
	}

	// Initialize countdown timers for personal tasks
	initializeCountdownTimers();

	// Set up timer updates when tasks are loaded
	setupTimerObserver();
}

/**
 * Ensure Font Awesome is available for injected icon markup.
 */
function ensureFontAwesomeLoaded() {
	const existingLink = document.querySelector('link[data-fa="true"]');
	if (existingLink) return;

	const link = document.createElement("link");
	link.rel = "stylesheet";
	link.href = "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css";
	link.setAttribute("data-fa", "true");
	document.head.appendChild(link);
}

/**
 * Add organization mode switcher button
 */
function addOrganizationModeSwitcher() {
	// Create switcher button
	const switcher = document.createElement("div");
	switcher.id = "org-mode-switcher";
	switcher.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 12px 20px;
        border-radius: 25px;
        color: white;
        font-weight: 600;
        cursor: pointer;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        transition: all 0.3s ease;
        border: none;
    `;

	switcher.innerHTML = `
        <div style="display: flex; align-items: center; gap: 8px;">
			<i class="fa-solid fa-building" aria-hidden="true"></i>
			<span>Organization Mode</span>
        </div>
    `;

	switcher.addEventListener("click", function () {
		window.location.href = "welcome.html";
	});

	document.body.appendChild(switcher);

	// Add hover effect
	switcher.addEventListener("mouseover", function () {
		switcher.style.transform = "translateY(-2px)";
		switcher.style.boxShadow = "0 6px 20px rgba(102, 126, 234, 0.6)";
	});

	switcher.addEventListener("mouseout", function () {
		switcher.style.transform = "translateY(0)";
		switcher.style.boxShadow = "0 4px 15px rgba(102, 126, 234, 0.4)";
	});
}

/**
 * Redirect to appropriate dashboard if logged into organization mode
 */
function redirectToDashboard(userJSON) {
	try {
		const user = JSON.parse(userJSON);

		// Only redirect if we're not already on a dashboard
		const currentPage = window.location.pathname;
		if (currentPage.includes("dashboard")) {
			return;
		}

		// Redirect to appropriate dashboard
		const dashboard = user.role === "admin" ? "admin-dashboard.html" : "employee-dashboard.html";

		// Show a message before redirecting
		const overlay = document.createElement("div");
		overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
        `;

		overlay.innerHTML = `
            <div style="
                background: white;
                padding: 30px;
                border-radius: 10px;
                text-align: center;
                max-width: 400px;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            ">
                <h2 style="color: #333; margin-bottom: 15px;">Welcome Back!</h2>
                <p style="color: #666; margin-bottom: 20px;">You're logged into organization mode. Redirecting to your dashboard...</p>
                <button onclick="window.location.href='${dashboard}'" style="
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    border: none;
                    padding: 12px 30px;
                    border-radius: 5px;
                    cursor: pointer;
                    font-weight: 600;
                    transition: all 0.3s;
                ">Go to Dashboard</button>
            </div>
        `;

		document.body.appendChild(overlay);

		// Auto redirect after 3 seconds
		setTimeout(() => {
			window.location.href = dashboard;
		}, 3000);
	} catch (error) {
		console.error("Error parsing user data:", error);
	}
}

/**
 * Initialize countdown timers for personal mode tasks
 */
function initializeCountdownTimers() {
	// Get all tasks from localStorage
	const tasks = getPersonalTasks();

	if (tasks.length === 0) {
		return;
	}

	// Initialize timer manager
	const timerManager = TimerManager.getInstance();

	// Attach timers to all tasks with deadlines
	tasks.forEach((task) => {
		if (task.deadline && task.deadline.trim()) {
			timerManager.attachTimer(task);
		}
	});
}

/**
 * Get personal tasks from localStorage
 * Supports various storage formats (tasks, taskList, etc.)
 */
function getPersonalTasks() {
	// Try different localStorage keys that might be used
	const possibleKeys = ["tasks", "taskList", "personalTasks", "allTasks"];

	for (const key of possibleKeys) {
		const data = localStorage.getItem(key);
		if (data) {
			try {
				const tasks = JSON.parse(data);
				if (Array.isArray(tasks)) {
					return tasks;
				}
			} catch (e) {
				console.warn(`Failed to parse ${key} from localStorage`);
			}
		}
	}

	return [];
}

/**
 * Set up observer for task DOM changes
 * Reinitialize timers when new tasks are added
 */
function setupTimerObserver() {
	// Find the task container
	const taskContainer = document.querySelector("[data-task-container], .tasks-container, .task-list, #taskList");

	if (!taskContainer) {
		return;
	}

	// Observe for new task elements
	const observer = new MutationObserver((mutations) => {
		mutations.forEach((mutation) => {
			if (mutation.addedNodes.length > 0) {
				// Reinitialize timers when new elements are added
				initializeCountdownTimers();

				// Update timer displays
				updateTimerDisplays();
			}
		});
	});

	observer.observe(taskContainer, {
		childList: true,
		subtree: true,
	});
}

/**
 * Update all timer displays
 */
function updateTimerDisplays() {
	const timerManager = TimerManager.getInstance();
	const tasks = getPersonalTasks();

	tasks.forEach((task) => {
		if (task.deadline) {
			const state = timerManager.getState(task.id);
			if (state) {
				updateTaskTimerDisplay(task.id, state);
			}
		}
	});
}

/**
 * Update displayed timer for a specific task
 */
function updateTaskTimerDisplay(taskId, timerState) {
	const taskElement = document.querySelector(`[data-task-id="${taskId}"], [id="task-${taskId}"]`);

	if (!taskElement) {
		return;
	}

	// Find or create deadline display element
	let deadlineDisplay = taskElement.querySelector(".task-deadline-timer, .deadline-display");

	if (!deadlineDisplay) {
		deadlineDisplay = document.createElement("div");
		deadlineDisplay.className = "task-deadline-timer";
		deadlineDisplay.style.cssText = `
            padding: 8px 12px;
            border-radius: 5px;
            font-weight: 600;
            font-size: 14px;
            margin-top: 8px;
        `;

		// Find a good place to insert it
		const taskContent = taskElement.querySelector(".task-content, .task-body, .task-title");
		if (taskContent) {
			taskContent.parentElement.insertBefore(deadlineDisplay, taskContent.nextSibling);
		} else {
			taskElement.appendChild(deadlineDisplay);
		}
	}

	// Update the display
	TimerElementUpdater.updateElement(deadlineDisplay, timerState);

	// Update task element classes for styling
	taskElement.setAttribute("data-status", timerState.status);

	if (timerState.isOverdue) {
		taskElement.classList.add("task-overdue");
	} else {
		taskElement.classList.remove("task-overdue");
	}
}

/**
 * Add CSS styles for timer displays
 */
function injectTimerStyles() {
	const style = document.createElement("style");
	style.textContent = `
        /* Timer Display Styles */
        .timer-display {
            padding: 8px 12px;
            border-radius: 5px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .timer-normal {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
        }

        .timer-alert {
            background: rgba(255, 159, 64, 0.1);
            color: #ff9f40;
        }

        .timer-warning {
            background: rgba(255, 107, 107, 0.1);
            color: #ff6b6b;
        }

        .timer-urgent {
            background: rgba(255, 71, 87, 0.15);
            color: #ff4757;
            font-weight: 700;
            animation: pulse 1s infinite;
        }

        .timer-overdue {
            background: rgba(255, 0, 0, 0.2);
            color: #ff0000;
            font-weight: 700;
            animation: pulse 0.5s infinite;
        }

        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.7;
            }
        }

        /* Task Overdue Styling */
        .task-overdue {
            opacity: 0.7;
            border-left: 4px solid #ff0000 !important;
        }

        .task-overdue .task-title,
        .task-overdue .task-name {
            text-decoration: line-through;
            color: #999;
        }

        /* Organization Mode Switcher */
        #org-mode-switcher:hover {
            background: linear-gradient(135deg, #5568d3 0%, #6d3a8d 100%);
        }

        @media (max-width: 768px) {
            #org-mode-switcher {
                top: 10px;
                right: 10px;
                padding: 10px 15px;
                font-size: 14px;
            }

            .timer-display {
                font-size: 12px;
                padding: 6px 10px;
            }
        }
    `;

	document.head.appendChild(style);
}

// Inject styles when document is ready
if (document.head) {
	injectTimerStyles();
} else {
	document.addEventListener("DOMContentLoaded", injectTimerStyles);
}

/**
 * Hook into task creation/update to manage timers
 */
window.addEventListener("taskCreated", function (event) {
	const task = event.detail;
	const timerManager = TimerManager.getInstance();
	timerManager.attachTimer(task);
	updateTaskTimerDisplay(task.id, timerManager.getState(task.id));
});

window.addEventListener("taskUpdated", function (event) {
	const task = event.detail;
	const timerManager = TimerManager.getInstance();
	timerManager.removeTimer(task.id);
	if (task.deadline) {
		timerManager.attachTimer(task);
		updateTaskTimerDisplay(task.id, timerManager.getState(task.id));
	}
});

window.addEventListener("taskDeleted", function (event) {
	const taskId = event.detail.id;
	const timerManager = TimerManager.getInstance();
	timerManager.removeTimer(taskId);
});

/**
 * Request notification permission for task reminders
 */
function requestNotificationPermission() {
	if ("Notification" in window && Notification.permission === "default") {
		Notification.requestPermission();
	}
}

// Request notification permission on page load
window.addEventListener("load", requestNotificationPermission);
