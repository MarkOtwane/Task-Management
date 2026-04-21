// ===== PERSONAL CONTACTS MANAGEMENT (Personal Mode Only) =====
let contacts = JSON.parse(localStorage.getItem("contacts")) || [];

/**
 * Get contacts from localStorage
 */
function getStoredContacts() {
	try {
		return JSON.parse(localStorage.getItem("contacts") || "[]");
	} catch (error) {
		console.error("Error reading stored contacts:", error);
		return [];
	}
}

/**
 * Save contacts to localStorage
 */
function saveContactsLocally(contactsToSave) {
	try {
		localStorage.setItem("contacts", JSON.stringify(contactsToSave));
	} catch (error) {
		console.error("Error saving contacts to localStorage:", error);
	}
}

/**
 * Load contacts from backend API
 */
async function loadContacts() {
	try {
		const token = localStorage.getItem("auth_token");
		const response = await fetch("https://task-management-bxpk.onrender.com/backend/api/contacts/get_contacts.php", {
			method: "GET",
			headers: {
				"Content-Type": "application/json",
				Authorization: token ? `Bearer ${token}` : "",
			},
			credentials: "include",
		});

		if (!response.ok) {
			console.warn("Failed to load contacts from backend:", response.status);
			contacts = getStoredContacts();
			return;
		}

		const data = await response.json();
		if (data.success && Array.isArray(data.data)) {
			contacts = data.data;
			saveContactsLocally(contacts);
		} else {
			console.warn("Invalid contacts response:", data);
			contacts = getStoredContacts();
		}
	} catch (error) {
		console.warn("Error loading contacts from backend:", error);
		contacts = getStoredContacts();
	}
}

/**
 * Render contacts list
 */
function renderContacts() {
	const list = document.getElementById("contactsList");
	const emptyState = document.getElementById("emptyContactsState");

	if (!list) return;

	if (contacts.length === 0) {
		list.innerHTML = "";
		if (emptyState) emptyState.style.display = "block";
		return;
	}

	if (emptyState) emptyState.style.display = "none";

	list.innerHTML = contacts
		.map(
			(contact, index) => `
						<div class="contact-item" style="background: var(--bg-secondary); border-radius: 8px; padding: 14px; margin-bottom: 10px; border-left: 4px solid var(--text-accent);">
							<div style="display: flex; justify-content: space-between; align-items: start;">
								<div style="flex: 1;">
									<div style="font-weight: 600; color: var(--text-primary); margin-bottom: 4px;">
										<span style="color: var(--text-tertiary); margin-right: 8px;">${index + 1}.</span>${contact.name}
									</div>
									<div style="color: var(--text-secondary); font-size: 14px; display: flex; align-items: center;">
										<span class="material-icons" style="font-size: 16px; margin-right: 6px; vertical-align: middle;">phone</span>${contact.phone}
									</div>
								</div>
								<div style="display: flex; gap: 8px;">
									<button type="button" class="btn btn-secondary btn-small edit-contact-btn" data-contact-id="${contact.id}" style="padding: 6px 12px; font-size: 13px;">
										<span class="material-icons" style="font-size: 16px;">edit</span>
									</button>
									<button type="button" class="btn btn-danger btn-small delete-contact-btn" data-contact-id="${contact.id}" style="padding: 6px 12px; font-size: 13px;">
										<span class="material-icons" style="font-size: 16px;">delete</span>
									</button>
								</div>
							</div>
						</div>
					`,
		)
		.join("");

	// Attach event listeners to edit and delete buttons
	document.querySelectorAll(".edit-contact-btn").forEach((btn) => {
		btn.addEventListener("click", (e) => {
			e.preventDefault();
			const contactId = parseInt(btn.dataset.contactId);
			const contact = contacts.find((c) => c.id === contactId);
			if (contact) editContact(contact);
		});
	});

	document.querySelectorAll(".delete-contact-btn").forEach((btn) => {
		btn.addEventListener("click", (e) => {
			e.preventDefault();
			const contactId = parseInt(btn.dataset.contactId);
			const contact = contacts.find((c) => c.id === contactId);
			if (contact) {
				showConfirmMessage(`Delete "${contact.name}"?`, "Delete", "Cancel").then((confirmed) => {
					if (confirmed) removeContact(contactId);
				});
			}
		});
	});
}

// Backward-compatible aliases for simpler contact manager implementations.
function addContact(name, phone) {
	return addOrUpdateContact(name, phone);
}

function displayContacts() {
	renderContacts();
}

/**
 * Edit contact - populate form with existing data
 */
function editContact(contact) {
	const form = document.getElementById("addContactForm");
	const nameInput = document.getElementById("contactName");
	const phoneInput = document.getElementById("contactPhone");
	const submitBtn = form.querySelector("button[type='submit']");
	const originalSubmitText = submitBtn.innerHTML;

	// Set form to edit mode
	nameInput.value = contact.name;
	phoneInput.value = contact.phone;
	nameInput.focus();

	// Mark form as editing
	submitBtn.innerHTML = '<span class="material-icons" style="vertical-align: middle; margin-right: 6px;">save</span>Update Contact';
	form.dataset.editingContactId = contact.id;

	// Add cancel edit button
	let cancelBtn = form.querySelector(".cancel-edit-btn");
	if (!cancelBtn) {
		cancelBtn = document.createElement("button");
		cancelBtn.type = "button";
		cancelBtn.className = "btn btn-secondary cancel-edit-btn";
		cancelBtn.innerHTML = '<span class="material-icons" style="vertical-align: middle; margin-right: 6px;">close</span>Cancel';
		cancelBtn.style.marginLeft = "8px";
		submitBtn.parentElement.insertBefore(cancelBtn, submitBtn.nextSibling);

		cancelBtn.addEventListener("click", (e) => {
			e.preventDefault();
			cancelEditContact();
		});
	}
}

/**
 * Cancel edit mode
 */
function cancelEditContact() {
	const form = document.getElementById("addContactForm");
	const submitBtn = form.querySelector("button[type='submit']");
	const cancelBtn = form.querySelector(".cancel-edit-btn");

	form.reset();
	delete form.dataset.editingContactId;
	submitBtn.innerHTML = '<span class="material-icons" style="vertical-align: middle; margin-right: 6px;">add</span>Add Contact';

	if (cancelBtn) cancelBtn.remove();
}

/**
 * Add or update contact
 */
async function addOrUpdateContact(name, phone) {
	if (!name.trim() || !phone.trim()) {
		showNotification("Name and phone are required", "error");
		return;
	}

	const form = document.getElementById("addContactForm");
	const editingContactId = form.dataset.editingContactId ? parseInt(form.dataset.editingContactId) : null;

	try {
		const token = localStorage.getItem("auth_token");
		const endpoint = editingContactId
			? "https://task-management-bxpk.onrender.com/backend/api/contacts/update_contact.php"
			: "https://task-management-bxpk.onrender.com/backend/api/contacts/create_contact.php";

		const method = editingContactId ? "PUT" : "POST";
		const body = editingContactId ? { id: editingContactId, name: name.trim(), phone: phone.trim() } : { name: name.trim(), phone: phone.trim() };

		const response = await fetch(endpoint, {
			method: method,
			headers: {
				"Content-Type": "application/json",
				Authorization: token ? `Bearer ${token}` : "",
			},
			credentials: "include",
			body: JSON.stringify(body),
		});

		if (!response.ok) {
			const error = await response.json();
			showNotification(error.error || "Failed to save contact", "error");
			return;
		}

		const data = await response.json();
		if (data.success) {
			if (editingContactId) {
				// Update existing contact
				const index = contacts.findIndex((c) => c.id === editingContactId);
				if (index !== -1) {
					contacts[index] = { ...contacts[index], name: name.trim(), phone: phone.trim() };
				}
				showNotification("Contact updated successfully", "success");
				cancelEditContact();
			} else {
				// Add new contact
				contacts.unshift({
					id: data.id,
					user_id: currentUser.id,
					name: name.trim(),
					phone: phone.trim(),
					created_at: new Date().toISOString(),
				});
				showNotification("Contact added successfully", "success");
				form.reset();
			}
			saveContactsLocally(contacts);
			renderContacts();
		} else {
			showNotification(data.error || "Failed to save contact", "error");
		}
	} catch (error) {
		console.error("Error saving contact:", error);
		showNotification("Failed to save contact: " + error.message, "error");
	}
}

/**
 * Delete contact
 */
async function removeContact(contactId) {
	try {
		const token = localStorage.getItem("auth_token");
		const response = await fetch("https://task-management-bxpk.onrender.com/backend/api/contacts/delete_contact.php", {
			method: "DELETE",
			headers: {
				"Content-Type": "application/json",
				Authorization: token ? `Bearer ${token}` : "",
			},
			credentials: "include",
			body: JSON.stringify({ id: contactId }),
		});

		if (!response.ok) {
			const error = await response.json();
			showNotification(error.error || "Failed to delete contact", "error");
			return;
		}

		const data = await response.json();
		if (data.success) {
			contacts = contacts.filter((c) => c.id !== contactId);
			saveContactsLocally(contacts);
			renderContacts();
			showNotification("Contact deleted successfully", "success");
		} else {
			showNotification(data.error || "Failed to delete contact", "error");
		}
	} catch (error) {
		console.error("Error deleting contact:", error);
		showNotification("Failed to delete contact: " + error.message, "error");
	}
}

// ===== CONTACTS EVENT LISTENERS =====
document.addEventListener("DOMContentLoaded", () => {
	const addContactForm = document.getElementById("addContactForm");
	if (addContactForm) {
		addContactForm.addEventListener("submit", (e) => {
			e.preventDefault();
			const name = document.getElementById("contactName").value;
			const phone = document.getElementById("contactPhone").value;
			addOrUpdateContact(name, phone);
		});
	}
});

const existingOnload = window.onload;
window.onload = function (event) {
	if (typeof existingOnload === "function") {
		existingOnload(event);
	}
	contacts = JSON.parse(localStorage.getItem("contacts")) || [];
	displayContacts();
};
