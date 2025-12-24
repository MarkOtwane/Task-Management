/**
 * Integration Examples - How to Use the Backend API
 * 
 * Copy these examples into your frontend JavaScript to start using the backend
 */

// Initialize the API client
const api = new TaskAPI('http://localhost:8000/backend');

/**
 * ===== USER AUTHENTICATION =====
 */

// Example 1: User Registration
async function registerUser() {
    try {
        const result = await api.register(
            'user@example.com',
            'securePassword123',
            'Username'
        );
        console.log('Registration successful:', result);
        return result;
    } catch (error) {
        console.error('Registration failed:', error.message);
    }
}

// Example 2: User Login
async function loginUser() {
    try {
        const result = await api.login(
            'user@example.com',
            'securePassword123'
        );
        console.log('Login successful:', result);
        // Save user info to session/localStorage if needed
        sessionStorage.setItem('userId', result.user_id);
        return result;
    } catch (error) {
        console.error('Login failed:', error.message);
    }
}

// Example 3: User Logout
async function logoutUser() {
    try {
        const result = await api.logout();
        console.log('Logout successful:', result);
        sessionStorage.removeItem('userId');
        return result;
    } catch (error) {
        console.error('Logout failed:', error.message);
    }
}

/**
 * ===== TASK MANAGEMENT =====
 */

// Example 4: Get All Tasks
async function getAllTasks() {
    try {
        const tasks = await api.getTasks();
        console.log('Tasks retrieved:', tasks);
        // Display tasks in your UI
        displayTasks(tasks);
        return tasks;
    } catch (error) {
        console.error('Failed to get tasks:', error.message);
    }
}

// Example 5: Create a New Task
async function createNewTask(taskData) {
    try {
        const result = await api.createTask({
            title: taskData.title,
            description: taskData.description,
            category: taskData.category || 'general',
            priority: taskData.priority || 'medium',
            status: 'pending',
            due_date: taskData.dueDate || null
        });
        console.log('Task created:', result);
        // Refresh tasks list
        await getAllTasks();
        return result;
    } catch (error) {
        console.error('Failed to create task:', error.message);
    }
}

// Example 6: Update a Task
async function updateTask(taskId, updates) {
    try {
        const result = await api.updateTask(taskId, {
            title: updates.title,
            description: updates.description,
            status: updates.status,
            priority: updates.priority,
            due_date: updates.dueDate
        });
        console.log('Task updated:', result);
        // Refresh tasks list
        await getAllTasks();
        return result;
    } catch (error) {
        console.error('Failed to update task:', error.message);
    }
}

// Example 7: Mark Task as Complete
async function completeTask(taskId) {
    try {
        const result = await api.updateTask(taskId, {
            status: 'completed'
        });
        console.log('Task completed:', result);
        await getAllTasks();
        return result;
    } catch (error) {
        console.error('Failed to complete task:', error.message);
    }
}

// Example 8: Delete a Task
async function deleteTask(taskId) {
    try {
        const result = await api.deleteTask(taskId);
        console.log('Task deleted:', result);
        // Refresh tasks list
        await getAllTasks();
        return result;
    } catch (error) {
        console.error('Failed to delete task:', error.message);
    }
}

/**
 * ===== TASK REFLECTIONS =====
 */

// Example 9: Get Reflections for a Task
async function getTaskReflections(taskId) {
    try {
        const reflections = await api.getReflections(taskId);
        console.log('Reflections retrieved:', reflections);
        return reflections;
    } catch (error) {
        console.error('Failed to get reflections:', error.message);
    }
}

// Example 10: Add a Reflection to a Task
async function addTaskReflection(taskId, reflectionText) {
    try {
        const result = await api.createReflection(taskId, reflectionText);
        console.log('Reflection added:', result);
        // Refresh reflections
        await getTaskReflections(taskId);
        return result;
    } catch (error) {
        console.error('Failed to add reflection:', error.message);
    }
}

/**
 * ===== TASK REMINDERS =====
 */

// Example 11: Get Pending Reminders
async function getPendingReminders() {
    try {
        const reminders = await api.getReminders();
        console.log('Reminders retrieved:', reminders);
        // Check for reminders that should be sent now
        reminders.forEach(reminder => {
            const reminderTime = new Date(reminder.reminder_time);
            if (reminderTime <= new Date()) {
                sendReminderNotification(reminder);
            }
        });
        return reminders;
    } catch (error) {
        console.error('Failed to get reminders:', error.message);
    }
}

// Example 12: Create a Task Reminder
async function createTaskReminder(taskId, reminderType, reminderTime) {
    try {
        const result = await api.createReminder(
            taskId,
            reminderType, // '1_day_before', '30_minutes_before', 'custom'
            reminderTime  // ISO 8601 datetime string
        );
        console.log('Reminder created:', result);
        return result;
    } catch (error) {
        console.error('Failed to create reminder:', error.message);
    }
}

// Example 13: Create Reminder 1 Day Before Due Date
async function createOneDay Before Reminder(taskId, dueDate) {
    const dueDateTime = new Date(dueDate);
    const oneDayBefore = new Date(dueDateTime.getTime() - 24 * 60 * 60 * 1000);
    
    return createTaskReminder(
        taskId,
        '1_day_before',
        oneDayBefore.toISOString()
    );
}

// Example 14: Create Reminder 30 Minutes Before Due Date
async function createThirtyMinutesReminder(taskId, dueDate) {
    const dueDateTime = new Date(dueDate);
    const thirtyMinBefore = new Date(dueDateTime.getTime() - 30 * 60 * 1000);
    
    return createTaskReminder(
        taskId,
        '30_minutes_before',
        thirtyMinBefore.toISOString()
    );
}

/**
 * ===== PASSWORD RESET =====
 */

// Example 15: Request Password Reset
async function requestPasswordReset(email) {
    try {
        const result = await api.requestPasswordReset(email);
        console.log('Reset link sent:', result);
        // In production, the token should be sent via email only
        return result;
    } catch (error) {
        console.error('Failed to request reset:', error.message);
    }
}

// Example 16: Verify Reset Token
async function verifyResetToken(token) {
    try {
        const result = await api.verifyResetToken(token);
        console.log('Token verified:', result);
        return result;
    } catch (error) {
        console.error('Invalid or expired token:', error.message);
    }
}

// Example 17: Reset Password
async function resetPassword(token, newPassword) {
    try {
        const result = await api.resetPassword(token, newPassword);
        console.log('Password reset successful:', result);
        return result;
    } catch (error) {
        console.error('Failed to reset password:', error.message);
    }
}

/**
 * ===== HELPER FUNCTIONS =====
 */

// Display tasks in the UI
function displayTasks(tasks) {
    const tasksContainer = document.getElementById('tasks-container');
    if (!tasksContainer) return;
    
    tasksContainer.innerHTML = '';
    
    tasks.forEach(task => {
        const taskElement = document.createElement('div');
        taskElement.className = 'task-card';
        taskElement.innerHTML = `
            <h3>${task.title}</h3>
            <p>${task.description || 'No description'}</p>
            <span class="priority ${task.priority}">${task.priority}</span>
            <span class="status">${task.status}</span>
            ${task.due_date ? `<p class="due-date">Due: ${new Date(task.due_date).toLocaleDateString()}</p>` : ''}
            <button onclick="completeTask(${task.id})">Complete</button>
            <button onclick="deleteTask(${task.id})">Delete</button>
        `;
        tasksContainer.appendChild(taskElement);
    });
}

// Send reminder notification
function sendReminderNotification(reminder) {
    if (Notification.permission === 'granted') {
        new Notification('Task Reminder', {
            body: `Reminder: Your task is due soon!`,
            icon: '/path/to/icon.png'
        });
    }
}

/**
 * ===== INITIALIZE WITH POLLING =====
 */

// Check for pending reminders every minute
function initializeReminderPolling() {
    setInterval(async () => {
        await getPendingReminders();
    }, 60000); // Check every minute
}

// Request notification permission on page load
function initializeNotifications() {
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
    }
}

/**
 * ===== COMPLETE EXAMPLE FLOW =====
 */

async function completeExampleFlow() {
    try {
        // 1. Register or login
        await registerUser();
        // or
        await loginUser();
        
        // 2. Get all tasks
        const tasks = await getAllTasks();
        
        // 3. Create a new task
        const newTask = await createNewTask({
            title: 'My Important Task',
            description: 'Description here',
            priority: 'high',
            dueDate: new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString()
        });
        
        // 4. Create reminders for the task
        if (newTask.task_id) {
            await createOneDay BeforeReminderReminder(newTask.task_id, newTask.task_id);
        }
        
        // 5. Initialize reminder polling
        initializeReminderPolling();
        
        // 6. Update task when completed
        // await completeTask(newTask.task_id);
        
        // 7. Add reflection when done
        // await addTaskReflection(newTask.task_id, 'Great learning experience!');
        
    } catch (error) {
        console.error('Flow error:', error);
    }
}

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        registerUser,
        loginUser,
        logoutUser,
        getAllTasks,
        createNewTask,
        updateTask,
        completeTask,
        deleteTask,
        getTaskReflections,
        addTaskReflection,
        getPendingReminders,
        createTaskReminder,
        requestPasswordReset,
        verifyResetToken,
        resetPassword
    };
}
