/*
  MIGRATE_LOCAL_TO_BACKEND.js

  Usage (in your browser on the running app page at https://task-management-bxpk.onrender.com):
  1. Open DevTools -> Console
  2. Paste this entire script and press Enter
  3. Follow the console output. It will register/login each local user and create their tasks on the backend.

  IMPORTANT:
  - This script uses credentials stored in localStorage (if any) to register/login.
  - Make a backup of localStorage before running.
  - If a user already exists on the backend, registration will fail with 409 and the script will try to login.
  - After successful migration you can manually clear localStorage keys if desired.
*/

(async function migrateLocalStorageToBackend() {
	const API_BASE = "https://task-management-bxpk.onrender.com/backend";
	function log(...args) {
		console.log("[MIGRATE]", ...args);
	}

	// Backup localStorage keys used
	const backup = {
		users: localStorage.getItem("users"),
		tasks: localStorage.getItem("tasks"),
		currentUser: localStorage.getItem("currentUser"),
	};

	log("Backup of keys created in `backup` variable (not saved to server).");

	const users = JSON.parse(localStorage.getItem("users") || "[]");
	const tasks = JSON.parse(localStorage.getItem("tasks") || "[]");

	if (users.length === 0) {
		log("No local users found in localStorage.users. If you only have anonymous tasks, consider creating an account first.");
	}

	async function apiRequest(path, opts = {}) {
		const res = await fetch(API_BASE + path, {
			credentials: "include",
			headers: { "Content-Type": "application/json" },
			...opts,
		});
		const ctype = res.headers.get("content-type") || "";
		const body = ctype.includes("application/json") ? await res.json() : await res.text();
		if (!res.ok) throw { status: res.status, body };
		return body;
	}

	// Helper: register or login user and return session info
	async function ensureUserSession(user) {
		try {
			log(`Registering user ${user.email} ...`);
			const reg = await apiRequest("/api/auth.php?action=register", {
				method: "POST",
				body: JSON.stringify({ email: user.email, password: user.password, username: user.name || user.username || "" }),
			});
			log("Registered:", reg);
			return reg;
		} catch (err) {
			if (err && err.status === 409) {
				log(`User ${user.email} already exists — attempting login.`);
				const login = await apiRequest("/api/auth.php?action=login", { method: "POST", body: JSON.stringify({ email: user.email, password: user.password }) });
				log("Logged in:", login);
				return login;
			}
			throw err;
		}
	}

	// For each user, ensure session and then create tasks that belong to them
	for (const user of users) {
		try {
			const session = await ensureUserSession(user);
			log(`Session for ${user.email}:`, session);

			// Find tasks owned by this local user (local tasks store `userId` that matches local user.id)
			const userTasks = tasks.filter((t) => String(t.userId) === String(user.id));
			log(`Found ${userTasks.length} tasks for ${user.email}`);

			for (const t of userTasks) {
				try {
					// Map fields to backend API expected fields
					const dueDateIso =
						t.dueDate && (t.dueTime || "").length ? new Date(t.dueDate + "T" + (t.dueTime || "00:00")).toISOString() : t.dueDate ? new Date(t.dueDate).toISOString() : null;

					const payload = {
						title: t.title || "Untitled Task",
						description: t.description || null,
						category: t.plan || null,
						priority: t.priority || "medium",
						status: t.completed ? "completed" : t.status || "pending",
						due_date: dueDateIso,
					};

					const created = await apiRequest("/api/tasks.php", { method: "POST", body: JSON.stringify(payload) });
					log(`Created task '${t.title}' -> server id:`, created.task_id || created.id || created);
				} catch (taskErr) {
					console.error("[MIGRATE] Failed creating task for", user.email, t, taskErr);
				}
			}

			// Logout after migrating user's tasks (session cookie cleared)
			try {
				await apiRequest("/api/auth.php?action=logout", { method: "POST" });
				log(`Logged out ${user.email}`);
			} catch (loErr) {
				console.warn("[MIGRATE] Logout failed (non-fatal):", loErr);
			}
		} catch (err) {
			console.error("[MIGRATE] Error with user", user.email, err);
		}
	}

	log("Migration finished. Review server to verify tasks.");
	log("If migration looks good, you may clear localStorage tasks/users manually (or keep for a while).");

	// Expose backup to console for manual retrieval
	window.__MIGRATION_BACKUP = backup;
	log("Backup available at window.__MIGRATION_BACKUP");
})();
