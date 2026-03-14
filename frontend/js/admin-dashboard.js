(function () {
	const BACKEND_ORIGIN = window.location.hostname.includes("vercel.app") ? "https://task-management-bxpk.onrender.com" : "";
	const API_BASE = `${BACKEND_ORIGIN}/backend/api`;

	const state = {
		token: null,
		user: null,
		currentTab: "overview",
		currentSubmissionId: null,
		overview: {},
		tasks: [],
		submissions: [],
		recentSubmissions: [],
		employees: [],
		users: [],
		projects: [],
		designProjects: [],
		settings: {},
		analytics: [],
	};

	document.addEventListener("DOMContentLoaded", init);

	async function init() {
		if (!restoreSession()) {
			return;
		}

		bindEvents();
		setUserName();
		await loadDashboard();
	}

	function restoreSession() {
		const token = localStorage.getItem("token");
		const storedUser = localStorage.getItem("user");

		if (!token || !storedUser) {
			window.location.href = "login.html";
			return false;
		}

		try {
			state.user = JSON.parse(storedUser);
		} catch {
			localStorage.removeItem("token");
			localStorage.removeItem("user");
			window.location.href = "login.html";
			return false;
		}

		if (state.user.role !== "admin") {
			showAlert("Access denied", "error");
			setTimeout(() => {
				window.location.href = "login.html";
			}, 1200);
			return false;
		}

		state.token = token;
		return true;
	}

	function bindEvents() {
		document.getElementById("createTaskForm")?.addEventListener("submit", handleCreateTask);
		document.getElementById("createProjectForm")?.addEventListener("submit", handleProjectSubmit);
		document.getElementById("createDesignProjectForm")?.addEventListener("submit", handleDesignProjectSubmit);
		document.getElementById("createEmployeeForm")?.addEventListener("submit", handleEmployeeSubmit);
		document.getElementById("editTaskForm")?.addEventListener("submit", handleEditTaskSubmit);
		document.getElementById("settingsForm")?.addEventListener("submit", handleSettingsSubmit);

		document.getElementById("taskSearch")?.addEventListener("input", () => renderTasks());
		document.getElementById("projectSearch")?.addEventListener("input", () => renderProjects());
		document.getElementById("designProjectSearch")?.addEventListener("input", () => renderDesignProjects());
		document.getElementById("submissionSearch")?.addEventListener("input", () => renderSubmissions());
		document.getElementById("employeeSearch")?.addEventListener("input", () => renderEmployees());

		document.querySelectorAll(".modal").forEach((modal) => {
			modal.addEventListener("click", (event) => {
				if (event.target === modal && modal.id !== "confirmModal") {
					closeModal(modal.id);
				}
			});
		});
	}

	function setUserName() {
		document.getElementById("userName").textContent = state.user.name || "Administrator";
	}

	async function loadDashboard() {
		setLoading("recentSubmissions", true);
		setLoading("tasks", true);
		setLoading("projects", true);
		setLoading("designProjects", true);
		setLoading("submissions", true);
		setLoading("employees", true);

		try {
			await Promise.all([fetchOverview(), fetchRecentSubmissions(), fetchTasks(), fetchSubmissions(), fetchEmployees(), fetchUsers()]);
			if (state.currentTab === "analytics") {
				await fetchAnalytics();
			}
		} catch (error) {
			showAlert(error.message || "Failed to load dashboard", "error");
		}
	}

	async function apiCall(endpoint, options = {}) {
		const config = {
			method: options.method || "GET",
			headers: {
				Authorization: `Bearer ${state.token}`,
			},
		};

		if (options.body instanceof FormData) {
			config.body = options.body;
		} else if (options.body !== undefined) {
			config.headers["Content-Type"] = "application/json";
			config.body = JSON.stringify(options.body);
		}

		const response = await fetch(`${API_BASE}${endpoint}`, config);
		const contentType = response.headers.get("content-type") || "";
		const result = contentType.includes("application/json") ? await response.json() : await response.text();

		if (!response.ok) {
			if (response.status === 401) {
				handleUnauthorized();
			}
			throw new Error(typeof result === "string" ? result : result.error || "Request failed");
		}

		return result;
	}

	function handleUnauthorized() {
		localStorage.removeItem("token");
		localStorage.removeItem("user");
		window.location.href = "login.html";
	}

	async function fetchOverview() {
		const result = await apiCall("/dashboard/admin-overview");
		state.overview = result.dashboard || {};
		renderOverview();
		return state.overview;
	}

	async function fetchRecentSubmissions() {
		try {
			const result = await apiCall("/dashboard/recent-submissions");
			state.recentSubmissions = result.submissions || [];
			renderRecentSubmissions();
			return state.recentSubmissions;
		} finally {
			setLoading("recentSubmissions", false);
		}
	}

	async function fetchTasks() {
		try {
			const result = await apiCall("/tasks/list");
			state.tasks = result.tasks || [];
			renderTasks();
			return state.tasks;
		} finally {
			setLoading("tasks", false);
		}
	}

	async function fetchSubmissions() {
		try {
			const result = await apiCall("/submissions/list");
			state.submissions = result.submissions || [];
			renderSubmissions();
			return state.submissions;
		} finally {
			setLoading("submissions", false);
		}
	}

	async function fetchEmployees() {
		try {
			const result = await apiCall("/users/employees");
			state.employees = result.employees || [];
			populateEmployeeSelects();
			renderEmployees();
			return state.employees;
		} finally {
			setLoading("employees", false);
		}
	}

	async function fetchProjects() {
		try {
			const result = await apiCall("/projects/list");
			state.projects = result.projects || [];
			renderProjects();
			return state.projects;
		} finally {
			setLoading("projects", false);
		}
	}

	async function fetchDesignProjects() {
		try {
			const result = await apiCall("/design-projects/list");
			state.designProjects = result.design_projects || [];
			renderDesignProjects();
			return state.designProjects;
		} finally {
			setLoading("designProjects", false);
		}
	}

	async function fetchUsers() {
		const result = await apiCall("/users/list");
		state.users = result.users || [];
		populateUserSelects();
		return state.users;
	}

	async function fetchAnalytics() {
		const result = await apiCall("/dashboard/employee-stats");
		state.analytics = result.employees || [];
		renderAnalytics();
		return state.analytics;
	}

	function renderOverview() {
		const overview = state.overview;
		const totalTasks = Number(overview.total_tasks || 0);
		const completedTasks = Number(overview.completed_tasks || 0);
		const pendingTasks = Number(overview.pending_tasks || 0);
		const totalEmployees = Number(overview.total_employees || 0);
		const totalSubmissions = Number(overview.total_submissions || 0);
		const approvedSubmissions = Number(overview.approved_submissions || 0);
		const rejectedSubmissions = Number(overview.rejected_submissions || 0);
		const completionRate = Number(overview.completion_rate || 0);

		document.getElementById("totalTasks").textContent = totalTasks;
		document.getElementById("completedTasks").textContent = completedTasks;
		document.getElementById("pendingTasks").textContent = pendingTasks;
		document.getElementById("totalEmployees").textContent = totalEmployees;
		document.getElementById("totalSubmissions").textContent = totalSubmissions;
		document.getElementById("avgCompletion").textContent = `${completionRate.toFixed(1)}%`;
		document.getElementById("completionRate").textContent = `${completionRate.toFixed(1)}% completed`;

		const avgTasksPerEmployee = totalEmployees > 0 ? (totalTasks / totalEmployees).toFixed(1) : "0.0";
		document.getElementById("avgTasksPerEmp").textContent = avgTasksPerEmployee;
		document.getElementById("avgCompletionRate").textContent = `${completionRate.toFixed(1)}%`;
		document.getElementById("approvedSubmissions").textContent = approvedSubmissions;
		document.getElementById("rejectedSubmissions").textContent = rejectedSubmissions;
	}

	function renderRecentSubmissions() {
		renderSubmissionTable({
			rows: state.recentSubmissions,
			tableId: "recentSubmissionsTable",
			bodyId: "recentSubmissionsBody",
			emptyId: "recentSubmissionsEmpty",
			includeReviewedOn: false,
		});
	}

	function renderTasks() {
		const query = document.getElementById("taskSearch")?.value.trim().toLowerCase() || "";
		const rows = state.tasks.filter((task) => {
			if (!query) {
				return true;
			}
			const title = String(task.title || "").toLowerCase();
			const assignee = String(task.assigned_to_name || "").toLowerCase();
			return title.includes(query) || assignee.includes(query);
		});

		const table = document.getElementById("tasksTable");
		const body = document.getElementById("tasksBody");
		const empty = document.getElementById("tasksEmpty");

		if (!rows.length) {
			body.innerHTML = "";
			table.style.display = "none";
			empty.style.display = "block";
			return;
		}

		body.innerHTML = rows
			.map(
				(task) => `
					<tr>
						<td>${escapeHtml(task.title || "Untitled task")}</td>
						<td>${escapeHtml(task.assigned_to_name || "Unassigned")}</td>
						<td>${formatDateOnly(task.deadline)}</td>
						<td><span class="status-badge status-${escapeHtml(task.priority || "medium")}">${escapeHtml(task.priority || "medium")}</span></td>
						<td><span class="status-badge status-${escapeHtml(task.status || "pending")}">${escapeHtml(task.status || "pending")}</span></td>
						<td>${Number(task.submission_count || 0)}</td>
						<td>
							<button class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px; margin-right: 8px;" onclick="editTask(${Number(task.id)})">Edit</button>
							<button class="btn btn-danger" style="padding: 6px 12px; font-size: 12px;" onclick="deleteTask(${Number(task.id)})">Delete</button>
						</td>
					</tr>
				`,
			)
			.join("");

		table.style.display = "table";
		empty.style.display = "none";
	}

	function renderSubmissions() {
		const query = document.getElementById("submissionSearch")?.value.trim().toLowerCase() || "";
		const rows = state.submissions.filter((submission) => {
			if (!query) {
				return true;
			}
			const employee = String(submission.employee_name || "").toLowerCase();
			const task = String(submission.task_title || "").toLowerCase();
			return employee.includes(query) || task.includes(query);
		});

		renderSubmissionTable({
			rows,
			tableId: "submissionsTable",
			bodyId: "submissionsBody",
			emptyId: "submissionsEmpty",
			includeReviewedOn: true,
		});
	}

	function renderProjects() {
		const query = document.getElementById("projectSearch")?.value.trim().toLowerCase() || "";
		const rows = state.projects.filter((project) => {
			if (!query) {
				return true;
			}
			const haystack = [project.title, project.client_name, project.owner_name, project.status].join(" ").toLowerCase();
			return haystack.includes(query);
		});

		const table = document.getElementById("projectsTable");
		const body = document.getElementById("projectsBody");
		const empty = document.getElementById("projectsEmpty");

		if (!rows.length) {
			body.innerHTML = "";
			table.style.display = "none";
			empty.style.display = "block";
			return;
		}

		body.innerHTML = rows
			.map(
				(project) => `
					<tr>
						<td>${escapeHtml(project.title || "-")}</td>
						<td>${escapeHtml(project.client_name || "-")}</td>
						<td>${escapeHtml(project.owner_name || "-")}</td>
						<td><span class="status-badge status-${escapeHtml(project.status || "planning")}">${escapeHtml(project.status || "planning")}</span></td>
						<td>${formatDateOnly(project.end_date)}</td>
						<td>${formatCurrency(project.budget)}</td>
						<td>
							<button class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px; margin-right: 8px;" onclick="editProject(${Number(project.id)})">Edit</button>
							<button class="btn btn-danger" style="padding: 6px 12px; font-size: 12px;" onclick="deleteProject(${Number(project.id)})">Delete</button>
						</td>
					</tr>
				`,
			)
			.join("");

		table.style.display = "table";
		empty.style.display = "none";
	}

	function renderDesignProjects() {
		const query = document.getElementById("designProjectSearch")?.value.trim().toLowerCase() || "";
		const rows = state.designProjects.filter((project) => {
			if (!query) {
				return true;
			}
			const haystack = [project.title, project.client_name, project.designer_name, project.status].join(" ").toLowerCase();
			return haystack.includes(query);
		});

		const table = document.getElementById("designProjectsTable");
		const body = document.getElementById("designProjectsBody");
		const empty = document.getElementById("designProjectsEmpty");

		if (!rows.length) {
			body.innerHTML = "";
			table.style.display = "none";
			empty.style.display = "block";
			return;
		}

		body.innerHTML = rows
			.map(
				(project) => `
					<tr>
						<td>${escapeHtml(project.title || "-")}</td>
						<td>${escapeHtml(project.client_name || "-")}</td>
						<td>${escapeHtml(project.designer_name || "-")}</td>
						<td><span class="status-badge status-${escapeHtml(project.status || "concept")}">${escapeHtml(project.status || "concept")}</span></td>
						<td>${formatDateOnly(project.due_date)}</td>
						<td>${project.image_path ? `<a href="${escapeAttribute(buildAssetUrl(project.image_path))}" target="_blank" rel="noopener noreferrer">Preview</a>` : "-"}</td>
						<td>
							<button class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px; margin-right: 8px;" onclick="editDesignProject(${Number(project.id)})">Edit</button>
							<button class="btn btn-danger" style="padding: 6px 12px; font-size: 12px;" onclick="deleteDesignProject(${Number(project.id)})">Delete</button>
						</td>
					</tr>
				`,
			)
			.join("");

		table.style.display = "table";
		empty.style.display = "none";
	}

	function renderSubmissionTable({ rows, tableId, bodyId, emptyId, includeReviewedOn }) {
		const table = document.getElementById(tableId);
		const body = document.getElementById(bodyId);
		const empty = document.getElementById(emptyId);

		if (!rows.length) {
			body.innerHTML = "";
			table.style.display = "none";
			empty.style.display = "block";
			return;
		}

		body.innerHTML = rows
			.map((submission) => {
				const actionLabel = submission.status === "pending" ? "Review" : "View";
				const reviewedOnCell = includeReviewedOn ? `<td>${submission.reviewed_at ? formatDateTime(submission.reviewed_at) : "-"}</td>` : "";

				return `
					<tr>
						<td>${escapeHtml(submission.employee_name || "Unknown")}</td>
						<td>${escapeHtml(submission.task_title || "Unknown")}</td>
						<td><span class="status-badge status-${escapeHtml(submission.status || "pending")}">${escapeHtml(submission.status || "pending")}</span></td>
						<td>${formatDateTime(submission.submitted_at)}</td>
						${reviewedOnCell}
						<td>
							<button class="btn btn-primary" style="padding: 6px 12px; font-size: 12px;" onclick="openSubmissionModal(${Number(submission.id)})">${actionLabel}</button>
						</td>
					</tr>
				`;
			})
			.join("");

		table.style.display = "table";
		empty.style.display = "none";
	}

	function renderEmployees() {
		const query = document.getElementById("employeeSearch")?.value.trim().toLowerCase() || "";
		const rows = state.employees.filter((employee) => {
			if (!query) {
				return true;
			}
			const haystack = [employee.name, employee.email, employee.department, employee.status].join(" ").toLowerCase();
			return haystack.includes(query);
		});

		const table = document.getElementById("employeesTable");
		const body = document.getElementById("employeesBody");
		const empty = document.getElementById("employeesEmpty");

		if (!rows.length) {
			body.innerHTML = "";
			table.style.display = "none";
			empty.style.display = "block";
			return;
		}

		body.innerHTML = rows
			.map(
				(employee) => `
					<tr>
						<td>${escapeHtml(employee.name || "-")}</td>
						<td>${escapeHtml(employee.email || "-")}</td>
						<td>${escapeHtml(employee.department || "-")}</td>
						<td>${Number(employee.total_tasks_assigned || 0)}</td>
						<td>${Number(employee.completed_tasks || 0)}</td>
						<td><span class="status-badge status-${escapeHtml(employee.status || "active")}">${escapeHtml(employee.status || "active")}</span></td>
						<td>
							<button class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px; margin-right: 8px;" onclick="editEmployee(${Number(employee.id)})">Edit</button>
							<button class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;" onclick="toggleEmployeeStatus(${Number(employee.id)}, '${escapeAttribute(employee.status || "active")}')">${employee.status === "active" ? "Deactivate" : "Activate"}</button>
						</td>
					</tr>
				`,
			)
			.join("");

		table.style.display = "table";
		empty.style.display = "none";
	}

	function renderAnalytics() {
		const table = document.getElementById("analyticsTable");
		const body = document.getElementById("analyticsBody");
		const empty = document.getElementById("analyticsEmpty");

		if (!state.analytics.length) {
			body.innerHTML = "";
			table.style.display = "none";
			empty.style.display = "block";
			return;
		}

		body.innerHTML = state.analytics
			.map((employee) => {
				const assigned = Number(employee.total_tasks || 0);
				const completed = Number(employee.completed_tasks || 0);
				const completionRate = assigned > 0 ? ((completed / assigned) * 100).toFixed(1) : "0.0";

				return `
					<tr>
						<td>${escapeHtml(employee.name || "-")}</td>
						<td>${assigned}</td>
						<td>${completed}</td>
						<td>${completionRate}%</td>
						<td>${Number(employee.approved_submissions || 0)}</td>
					</tr>
				`;
			})
			.join("");

		table.style.display = "table";
		empty.style.display = "none";
	}

	function populateEmployeeSelects() {
		["taskAssignee", "editTaskAssignee", "designProjectDesigner"].forEach((selectId) => {
			const select = document.getElementById(selectId);
			if (!select) {
				return;
			}

			const currentValue = select.value;
			select.innerHTML = '<option value="">Select employee...</option>';
			state.employees.forEach((employee) => {
				const option = document.createElement("option");
				option.value = employee.id;
				option.textContent = employee.name;
				select.appendChild(option);
			});
			select.value = currentValue;
		});
	}

	function populateUserSelects() {
		const select = document.getElementById("projectOwner");
		if (!select) {
			return;
		}

		const currentValue = select.value;
		select.innerHTML = '<option value="">Select owner...</option>';
		state.users.forEach((user) => {
			const option = document.createElement("option");
			option.value = user.id;
			option.textContent = `${user.name} (${user.role})`;
			select.appendChild(option);
		});
		select.value = currentValue;
	}

	async function handleCreateTask(event) {
		event.preventDefault();

		try {
			await createTask(readTaskFormValues("task"));
			event.target.reset();
			showAlert("Task created successfully", "success");
			await refreshTaskViews();
		} catch (error) {
			showAlert(error.message || "Failed to create task", "error");
		}
	}

	async function handleProjectSubmit(event) {
		event.preventDefault();

		const form = event.target;
		const projectId = Number(form.dataset.projectId || 0);
		const payload = readProjectFormValues();

		try {
			if (projectId) {
				await updateProject(projectId, payload);
				showAlert("Project updated successfully", "success");
			} else {
				await createProject(payload);
				showAlert("Project created successfully", "success");
			}

			resetProjectForm();
		} catch (error) {
			showAlert(error.message || "Failed to save project", "error");
		}
	}

	async function handleDesignProjectSubmit(event) {
		event.preventDefault();

		const form = event.target;
		const projectId = Number(form.dataset.designProjectId || 0);
		const payload = readDesignProjectFormValues();

		try {
			if (projectId) {
				await updateDesignProject(projectId, payload);
				showAlert("Design project updated successfully", "success");
			} else {
				await createDesignProject(payload);
				showAlert("Design project created successfully", "success");
			}

			resetDesignProjectForm();
		} catch (error) {
			showAlert(error.message || "Failed to save design project", "error");
		}
	}

	async function handleSettingsSubmit(event) {
		event.preventDefault();

		try {
			let dashboardPreferences = {};
			const rawPreferences = document.getElementById("settingDashboardPreferences").value.trim();
			if (rawPreferences) {
				dashboardPreferences = JSON.parse(rawPreferences);
			}

			await updateSettings({
				company_name: document.getElementById("settingCompanyName").value.trim(),
				default_task_priority: document.getElementById("settingDefaultTaskPriority").value,
				email_notifications_enabled: document.getElementById("settingEmailNotificationsEnabled").value === "true",
				dashboard_preferences: dashboardPreferences,
			});
			showAlert("Settings updated successfully", "success");
		} catch (error) {
			showAlert(error.message || "Failed to update settings", "error");
		}
	}

	async function handleEditTaskSubmit(event) {
		event.preventDefault();

		const form = event.target;
		const taskId = Number(form.dataset.taskId || 0);
		if (!taskId) {
			showAlert("No task selected", "error");
			return;
		}

		try {
			const payload = readTaskFormValues("editTask");
			const existingTask = state.tasks.find((task) => Number(task.id) === taskId);

			await updateTask(taskId, payload);

			if (existingTask && Number(existingTask.assigned_to || 0) !== Number(payload.assigned_to || 0)) {
				await apiCall(`/tasks/assign/${taskId}`, {
					method: "POST",
					body: { assigned_to: payload.assigned_to },
				});
			}

			form.reset();
			delete form.dataset.taskId;
			closeModal("editTaskModal");
			showAlert("Task updated successfully", "success");
			await refreshTaskViews();
		} catch (error) {
			showAlert(error.message || "Failed to update task", "error");
		}
	}

	async function handleEmployeeSubmit(event) {
		event.preventDefault();

		const form = event.target;
		const employeeId = Number(form.dataset.empId || 0);
		const payload = {
			name: document.getElementById("empName").value.trim(),
			email: document.getElementById("empEmail").value.trim(),
			department: document.getElementById("empDepartment").value.trim() || null,
			phone: document.getElementById("empPhone").value.trim() || null,
		};

		try {
			if (employeeId) {
				await updateUser(employeeId, payload);
				showAlert("Employee updated successfully", "success");
			} else {
				await createTeamMember(payload);
				showAlert("Employee created successfully", "success");
			}

			resetEmployeeForm();
			await Promise.all([fetchEmployees(), fetchUsers(), fetchOverview()]);
			if (state.currentTab === "analytics") {
				await fetchAnalytics();
			}
		} catch (error) {
			showAlert(error.message || "Failed to save employee", "error");
		}
	}

	function readTaskFormValues(prefix) {
		const assigneeValue = document.getElementById(`${prefix}Assignee`).value;
		return {
			title: document.getElementById(`${prefix}Title`).value.trim(),
			description: document.getElementById(`${prefix}Description`).value.trim() || null,
			assigned_to: Number(assigneeValue),
			deadline: document.getElementById(`${prefix}Deadline`).value,
			due_time: document.getElementById(`${prefix}DueTime`).value || null,
			priority: document.getElementById(`${prefix}Priority`).value,
			reminder_type: document.getElementById(`${prefix}ReminderType`).value,
		};
	}

	function readProjectFormValues() {
		const ownerValue = document.getElementById("projectOwner").value;
		const budgetValue = document.getElementById("projectBudget").value;

		return {
			title: document.getElementById("projectTitle").value.trim(),
			description: document.getElementById("projectDescription").value.trim() || null,
			client_name: document.getElementById("projectClient").value.trim() || null,
			owner_id: ownerValue ? Number(ownerValue) : null,
			status: document.getElementById("projectStatus").value,
			priority: document.getElementById("projectPriority").value,
			start_date: document.getElementById("projectStartDate").value || null,
			end_date: document.getElementById("projectEndDate").value || null,
			budget: budgetValue ? Number(budgetValue) : null,
		};
	}

	function readDesignProjectFormValues() {
		const formData = new FormData();
		const designerValue = document.getElementById("designProjectDesigner").value;
		const imageInput = document.getElementById("designProjectImage");

		formData.append("title", document.getElementById("designProjectTitle").value.trim());
		formData.append("description", document.getElementById("designProjectDescription").value.trim());
		formData.append("client_name", document.getElementById("designProjectClient").value.trim());
		formData.append("status", document.getElementById("designProjectStatus").value);
		formData.append("due_date", document.getElementById("designProjectDueDate").value);
		formData.append("notes", document.getElementById("designProjectNotes").value.trim());

		if (designerValue) {
			formData.append("designer_id", designerValue);
		}
		if (imageInput.files[0]) {
			formData.append("image", imageInput.files[0]);
		}

		return formData;
	}

	function resetEmployeeForm() {
		const form = document.getElementById("createEmployeeForm");
		form.reset();
		delete form.dataset.empId;

		const title = document.querySelector("#employees .form-group .form-title");
		const button = document.querySelector("#createEmployeeForm button[type='submit']");

		if (title) {
			title.textContent = "Add New Employee";
		}
		if (button) {
			button.textContent = "Add Employee";
		}
	}

	function resetProjectForm() {
		const form = document.getElementById("createProjectForm");
		form.reset();
		delete form.dataset.projectId;

		const title = document.querySelector("#projects .form-title");
		const button = document.querySelector("#createProjectForm button[type='submit']");
		if (title) {
			title.textContent = "Manage Projects";
		}
		if (button) {
			button.textContent = "Save Project";
		}
	}

	function resetDesignProjectForm() {
		const form = document.getElementById("createDesignProjectForm");
		form.reset();
		delete form.dataset.designProjectId;

		const title = document.querySelector("#design-projects .form-title");
		const button = document.querySelector("#createDesignProjectForm button[type='submit']");
		if (title) {
			title.textContent = "Manage Design Projects";
		}
		if (button) {
			button.textContent = "Save Design Project";
		}
	}

	async function refreshTaskViews() {
		await Promise.all([fetchOverview(), fetchRecentSubmissions(), fetchTasks(), fetchSubmissions(), fetchEmployees()]);
		if (state.currentTab === "analytics") {
			await fetchAnalytics();
		}
	}

	async function createTask(payload) {
		return apiCall("/tasks/create", {
			method: "POST",
			body: payload,
		});
	}

	async function updateTask(taskId, payload) {
		const updatePayload = {
			title: payload.title,
			description: payload.description,
			deadline: payload.deadline,
			due_time: payload.due_time,
			priority: payload.priority,
			reminder_type: payload.reminder_type,
		};

		return apiCall(`/tasks/update/${taskId}`, {
			method: "POST",
			body: updatePayload,
		});
	}

	async function deleteTask(taskId) {
		const confirmed = await showConfirm("Are you sure you want to delete this task?", "Delete Task");
		if (!confirmed) {
			return;
		}

		try {
			await apiCall(`/tasks/delete/${taskId}`, { method: "DELETE" });
			showAlert("Task deleted successfully", "success");
			await refreshTaskViews();
		} catch (error) {
			showAlert(error.message || "Failed to delete task", "error");
		}
	}

	async function editTask(taskId) {
		try {
			let task = state.tasks.find((item) => Number(item.id) === Number(taskId));
			if (!task) {
				const result = await apiCall(`/tasks/get/${taskId}`);
				task = result.task;
			}

			if (!task) {
				throw new Error("Task not found");
			}

			populateEmployeeSelects();
			document.getElementById("editTaskTitle").value = task.title || "";
			document.getElementById("editTaskDescription").value = task.description || "";
			document.getElementById("editTaskAssignee").value = task.assigned_to || "";
			document.getElementById("editTaskDeadline").value = task.deadline || "";
			document.getElementById("editTaskDueTime").value = task.due_time || "";
			document.getElementById("editTaskPriority").value = task.priority || "medium";
			document.getElementById("editTaskReminderType").value = task.reminder_type || "none";
			document.getElementById("editTaskForm").dataset.taskId = String(taskId);
			document.getElementById("editTaskModal").classList.add("active");
		} catch (error) {
			showAlert(error.message || "Failed to load task", "error");
		}
	}

	async function openSubmissionModal(submissionId) {
		try {
			state.currentSubmissionId = Number(submissionId);
			const result = await apiCall(`/submissions/get/${submissionId}`);
			const submission = result.submission;

			document.getElementById("modalEmpName").textContent = submission.employee_name || "Unknown";
			document.getElementById("modalTaskTitle").textContent = submission.task_title || "Unknown";
			document.getElementById("modalSubmittedDate").textContent = formatDateTime(submission.submitted_at);
			document.getElementById("modalSubmissionText").textContent = submission.submission_text || "No text provided";
			document.getElementById("adminComment").value = submission.admin_comment || "";

			const fileLink = document.getElementById("modalFileLink");
			if (submission.file_path) {
				fileLink.href = `${BACKEND_ORIGIN}/${submission.file_path.replace(/^\//, "")}`;
				fileLink.textContent = "Download file";
				fileLink.style.pointerEvents = "auto";
			} else {
				fileLink.removeAttribute("href");
				fileLink.textContent = "No file attached";
				fileLink.style.pointerEvents = "none";
			}

			document.getElementById("submissionModal").classList.add("active");
		} catch (error) {
			showAlert(error.message || "Failed to load submission", "error");
		}
	}

	async function approveSubmission() {
		if (!state.currentSubmissionId) {
			showAlert("No submission selected", "error");
			return;
		}

		try {
			await apiCall(`/submissions/approve/${state.currentSubmissionId}`, {
				method: "POST",
				body: { comment: document.getElementById("adminComment").value.trim() || null },
			});
			showAlert("Submission approved", "success");
			closeModal("submissionModal");
			await refreshTaskViews();
		} catch (error) {
			showAlert(error.message || "Failed to approve submission", "error");
		}
	}

	async function rejectSubmission() {
		if (!state.currentSubmissionId) {
			showAlert("No submission selected", "error");
			return;
		}

		const comment = document.getElementById("adminComment").value.trim();
		if (!comment) {
			showAlert("Please provide feedback before rejecting", "error");
			return;
		}

		try {
			await apiCall(`/submissions/reject/${state.currentSubmissionId}`, {
				method: "POST",
				body: { comment },
			});
			showAlert("Submission rejected", "success");
			closeModal("submissionModal");
			await refreshTaskViews();
		} catch (error) {
			showAlert(error.message || "Failed to reject submission", "error");
		}
	}

	async function editEmployee(employeeId) {
		try {
			const result = await apiCall(`/users/get/${employeeId}`);
			const user = result.user;

			document.getElementById("empName").value = user.name || "";
			document.getElementById("empEmail").value = user.email || "";
			document.getElementById("empDepartment").value = user.department || "";
			document.getElementById("empPhone").value = user.phone || "";

			const form = document.getElementById("createEmployeeForm");
			form.dataset.empId = String(employeeId);

			const title = document.querySelector("#employees .form-group .form-title");
			const button = document.querySelector("#createEmployeeForm button[type='submit']");
			if (title) {
				title.textContent = "Edit Employee";
			}
			if (button) {
				button.textContent = "Update Employee";
			}

			form.closest(".form-group")?.scrollIntoView({ behavior: "smooth", block: "start" });
		} catch (error) {
			showAlert(error.message || "Failed to load employee", "error");
		}
	}

	async function toggleEmployeeStatus(employeeId, currentStatus) {
		const nextStatus = currentStatus === "active" ? "inactive" : "active";

		try {
			await apiCall(`/users/toggle-status/${employeeId}`, {
				method: "POST",
				body: { status: nextStatus },
			});
			showAlert(`Employee ${nextStatus === "active" ? "activated" : "deactivated"}`, "success");
			await Promise.all([fetchEmployees(), fetchOverview()]);
			if (state.currentTab === "analytics") {
				await fetchAnalytics();
			}
		} catch (error) {
			showAlert(error.message || "Failed to update employee status", "error");
		}
	}

	async function createUser(payload) {
		return apiCall("/users/create-employee", {
			method: "POST",
			body: payload,
		});
	}

	async function updateUser(userId, payload) {
		return apiCall(`/users/update/${userId}`, {
			method: "POST",
			body: payload,
		});
	}

	async function deleteUser() {
		throw new Error("User deletion is not supported by the current backend API.");
	}

	async function createTeamMember(payload) {
		return createUser(payload);
	}

	async function updateTeamMember(teamMemberId, payload) {
		return updateUser(teamMemberId, payload);
	}

	async function deleteTeamMember(teamMemberId) {
		const employee = state.employees.find((item) => Number(item.id) === Number(teamMemberId));
		const currentStatus = employee?.status || "active";
		return apiCall(`/users/toggle-status/${teamMemberId}`, {
			method: "POST",
			body: { status: currentStatus === "active" ? "inactive" : "active" },
		});
	}

	async function fetchProjects() {
		const result = await apiCall("/projects/list");
		state.projects = result.projects || [];
		return state.projects;
	}

	async function createProject(payload) {
		const result = await apiCall("/projects/create", {
			method: "POST",
			body: payload,
		});
		await fetchProjects();
		return result;
	}

	async function updateProject(projectId, payload) {
		const result = await apiCall(`/projects/update/${projectId}`, {
			method: "POST",
			body: payload,
		});
		await fetchProjects();
		return result;
	}

	async function deleteProject(projectId) {
		const confirmed = await showConfirm("Are you sure you want to delete this project?", "Delete Project");
		if (!confirmed) {
			return null;
		}

		const result = await apiCall(`/projects/delete/${projectId}`, {
			method: "DELETE",
		});
		await fetchProjects();
		showAlert("Project deleted successfully", "success");
		return result;
	}

	async function fetchDesignProjects() {
		const result = await apiCall("/design-projects/list");
		state.designProjects = result.design_projects || [];
		return state.designProjects;
	}

	async function createDesignProject(payload) {
		const result = await apiCall("/design-projects/create", {
			method: "POST",
			body: payload,
		});
		await fetchDesignProjects();
		return result;
	}

	async function updateDesignProject(projectId, payload) {
		const result = await apiCall(`/design-projects/update/${projectId}`, {
			method: "POST",
			body: payload,
		});
		await fetchDesignProjects();
		return result;
	}

	async function deleteDesignProject(projectId) {
		const confirmed = await showConfirm("Are you sure you want to delete this design project?", "Delete Design Project");
		if (!confirmed) {
			return null;
		}

		const result = await apiCall(`/design-projects/delete/${projectId}`, {
			method: "DELETE",
		});
		await fetchDesignProjects();
		showAlert("Design project deleted successfully", "success");
		return result;
	}

	async function loadSettings() {
		const result = await apiCall("/settings/list");
		state.settings = result.settings || {};
		applySettingsToForm();
		return state.settings;
	}

	async function updateSettings(payload) {
		const result = await apiCall("/settings/update", {
			method: "POST",
			body: payload,
		});
		await loadSettings();
		return result;
	}

	async function editProject(projectId) {
		try {
			const result = await apiCall(`/projects/get/${projectId}`);
			const project = result.project;
			const form = document.getElementById("createProjectForm");

			document.getElementById("projectTitle").value = project.title || "";
			document.getElementById("projectClient").value = project.client_name || "";
			document.getElementById("projectOwner").value = project.owner_id || "";
			document.getElementById("projectStatus").value = project.status || "planning";
			document.getElementById("projectPriority").value = project.priority || "medium";
			document.getElementById("projectBudget").value = project.budget || "";
			document.getElementById("projectStartDate").value = project.start_date || "";
			document.getElementById("projectEndDate").value = project.end_date || "";
			document.getElementById("projectDescription").value = project.description || "";

			form.dataset.projectId = String(projectId);
			const title = document.querySelector("#projects .form-title");
			const button = document.querySelector("#createProjectForm button[type='submit']");
			if (title) {
				title.textContent = "Edit Project";
			}
			if (button) {
				button.textContent = "Update Project";
			}
			form.closest(".form-group")?.scrollIntoView({ behavior: "smooth", block: "start" });
		} catch (error) {
			showAlert(error.message || "Failed to load project", "error");
		}
	}

	async function editDesignProject(projectId) {
		try {
			const result = await apiCall(`/design-projects/get/${projectId}`);
			const project = result.design_project;
			const form = document.getElementById("createDesignProjectForm");

			document.getElementById("designProjectTitle").value = project.title || "";
			document.getElementById("designProjectClient").value = project.client_name || "";
			document.getElementById("designProjectDesigner").value = project.designer_id || "";
			document.getElementById("designProjectStatus").value = project.status || "concept";
			document.getElementById("designProjectDueDate").value = project.due_date || "";
			document.getElementById("designProjectDescription").value = project.description || "";
			document.getElementById("designProjectNotes").value = project.notes || "";

			form.dataset.designProjectId = String(projectId);
			const title = document.querySelector("#design-projects .form-title");
			const button = document.querySelector("#createDesignProjectForm button[type='submit']");
			if (title) {
				title.textContent = "Edit Design Project";
			}
			if (button) {
				button.textContent = "Update Design Project";
			}
			form.closest(".form-group")?.scrollIntoView({ behavior: "smooth", block: "start" });
		} catch (error) {
			showAlert(error.message || "Failed to load design project", "error");
		}
	}

	function applySettingsToForm() {
		document.getElementById("settingCompanyName").value = state.settings.company_name || "";
		document.getElementById("settingDefaultTaskPriority").value = state.settings.default_task_priority || "medium";
		document.getElementById("settingEmailNotificationsEnabled").value = String(state.settings.email_notifications_enabled ?? true);
		document.getElementById("settingDashboardPreferences").value = JSON.stringify(state.settings.dashboard_preferences || {}, null, 2);
	}

	function switchTab(tabName) {
		state.currentTab = tabName;
		document.querySelectorAll(".tab-content").forEach((tab) => tab.classList.remove("active"));
		document.getElementById(tabName)?.classList.add("active");

		document.querySelectorAll(".tab-btn").forEach((button) => button.classList.remove("active"));
		const activeButton = Array.from(document.querySelectorAll(".tab-btn")).find((button) => (button.getAttribute("onclick") || "").includes(`'${tabName}'`));
		activeButton?.classList.add("active");

		if (tabName === "projects") {
			setLoading("projects", true);
			fetchProjects().catch((error) => showAlert(error.message || "Failed to load projects", "error"));
		}

		if (tabName === "design-projects") {
			setLoading("designProjects", true);
			fetchDesignProjects().catch((error) => showAlert(error.message || "Failed to load design projects", "error"));
		}

		if (tabName === "settings") {
			loadSettings().catch((error) => showAlert(error.message || "Failed to load settings", "error"));
		}

		if (tabName === "analytics") {
			fetchAnalytics().catch((error) => showAlert(error.message || "Failed to load analytics", "error"));
		}
	}

	function closeModal(modalId) {
		document.getElementById(modalId)?.classList.remove("active");
		if (modalId === "submissionModal") {
			state.currentSubmissionId = null;
			document.getElementById("adminComment").value = "";
		}
		if (modalId === "editTaskModal") {
			const form = document.getElementById("editTaskForm");
			form?.reset();
			if (form?.dataset) {
				delete form.dataset.taskId;
			}
		}
	}

	function showAlert(message, type = "info") {
		const alertBox = document.getElementById("alertBox");
		alertBox.textContent = message;
		alertBox.className = `alert show alert-${type}`;
		window.clearTimeout(showAlert.timeoutId);
		showAlert.timeoutId = window.setTimeout(() => {
			alertBox.classList.remove("show");
		}, 4000);
	}

	function showConfirm(message, title = "Confirm Action") {
		return new Promise((resolve) => {
			const modal = document.getElementById("confirmModal");
			const titleEl = document.getElementById("confirmTitle");
			const messageEl = document.getElementById("confirmMessage");
			const yesButton = document.getElementById("confirmYes");
			const noButton = document.getElementById("confirmNo");

			titleEl.textContent = title;
			messageEl.textContent = message;
			modal.style.display = "flex";

			const cleanup = () => {
				yesButton.removeEventListener("click", handleYes);
				noButton.removeEventListener("click", handleNo);
			};

			const handleYes = () => {
				cleanup();
				hideConfirmModal();
				resolve(true);
			};

			const handleNo = () => {
				cleanup();
				hideConfirmModal();
				resolve(false);
			};

			yesButton.addEventListener("click", handleYes);
			noButton.addEventListener("click", handleNo);
		});
	}

	function hideConfirmModal() {
		const modal = document.getElementById("confirmModal");
		if (modal) {
			modal.style.display = "none";
		}
	}

	function setLoading(prefix, isLoading) {
		const loader = document.getElementById(`${prefix}Loading`);
		if (loader) {
			loader.classList.toggle("show", isLoading);
		}
	}

	function formatDateOnly(value) {
		if (!value) {
			return "-";
		}
		const date = new Date(`${value}T00:00:00`);
		return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString();
	}

	function formatCurrency(value) {
		if (value === null || value === undefined || value === "") {
			return "-";
		}
		const amount = Number(value);
		return Number.isNaN(amount) ? String(value) : amount.toLocaleString(undefined, { style: "currency", currency: "USD" });
	}

	function buildAssetUrl(path) {
		return `${BACKEND_ORIGIN}/${String(path || "").replace(/^\//, "")}`;
	}

	function formatDateTime(value) {
		if (!value) {
			return "-";
		}
		const date = new Date(value);
		if (Number.isNaN(date.getTime())) {
			return value;
		}
		return `${date.toLocaleDateString()} ${date.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })}`;
	}

	function escapeHtml(value) {
		return String(value).replaceAll("&", "&amp;").replaceAll("<", "&lt;").replaceAll(">", "&gt;").replaceAll('"', "&quot;").replaceAll("'", "&#39;");
	}

	function escapeAttribute(value) {
		return escapeHtml(value).replaceAll("`", "");
	}

	async function logout() {
		const confirmed = await showConfirm("Are you sure you want to logout?", "Logout");
		if (!confirmed) {
			return;
		}

		localStorage.removeItem("token");
		localStorage.removeItem("user");
		window.location.href = "login.html";
	}

	window.switchTab = switchTab;
	window.closeModal = closeModal;
	window.logout = logout;
	window.openSubmissionModal = openSubmissionModal;
	window.approveSubmission = approveSubmission;
	window.rejectSubmission = rejectSubmission;
	window.editTask = editTask;
	window.deleteTask = deleteTask;
	window.editEmployee = editEmployee;
	window.toggleEmployeeStatus = toggleEmployeeStatus;
	window.editProject = editProject;
	window.editDesignProject = editDesignProject;

	window.fetchUsers = fetchUsers;
	window.createUser = createUser;
	window.updateUser = updateUser;
	window.deleteUser = deleteUser;
	window.fetchTasks = fetchTasks;
	window.createTask = createTask;
	window.updateTask = updateTask;
	window.deleteTaskRecord = deleteTask;
	window.fetchTeam = fetchEmployees;
	window.createTeamMember = createTeamMember;
	window.updateTeamMember = updateTeamMember;
	window.deleteTeamMember = deleteTeamMember;
	window.fetchProjects = fetchProjects;
	window.createProject = createProject;
	window.updateProject = updateProject;
	window.deleteProject = deleteProject;
	window.fetchDesignProjects = fetchDesignProjects;
	window.createDesignProject = createDesignProject;
	window.updateDesignProject = updateDesignProject;
	window.deleteDesignProject = deleteDesignProject;
	window.loadSettings = loadSettings;
	window.updateSettings = updateSettings;
})();
