# Multi-Workplace Organization Mode Integration Guide

## Overview

The Task Management system now supports **two independent task modes**:

1. **Personal Mode** (default): Single-user task management (existing behavior preserved)
2. **Organizational Mode**: Multi-user workspace with role-based task assignment and review workflows

Both modes can coexist. Users switch between them using the **Workspace Mode** selector in the sidebar footer.

---

## Quick Start

### 1. Get Super-Admin Access

The hardcoded super admin account is:

- **Email**: `autonemac003@gmail.com`
- **Role**: `super_admin` (can create organizations)

Register or log in with this email to unlock organization management.

### 2. Create an Organization

1. Switch to **Organizational Mode** in the sidebar
2. Click **+ Create Organization** (only visible to super admin)
3. Enter the organization name
4. You are auto-added as `organization_admin`

### 3. Add Members to the Organization

1. Select the organization from the **Selected Organization** dropdown
2. Click **+ Add Member**
3. Enter the member's email and role:
     - `organization_admin` – Can create, assign, review tasks
     - `member` – Can only see and work on assigned tasks
     - `client` – Read-only access to assigned tasks

### 4. Create and Assign Organization Tasks

1. Click **+ Add Task** while in Organizational Mode
2. Fill in task details (title, description, plan, due date, etc.)
3. In the **Organization** dropdown, select the active organization
4. In the **Assign To** dropdown, pick a member from that organization
5. Save the task

### 5. Task Workflow

#### For Task Assignees (Members/Clients):

- See only tasks assigned to them
- Can update task status: `pending` → `in_progress` → `submitted`
- Click **Submit Task** to mark as ready for review

#### For Organization Admins:

- See all tasks in the organization
- Can create, edit, and delete any task
- Can approve or reject submitted tasks:
     - **Approve** → Task marked `completed`
     - **Reject** → Task reverts to `in_progress` for reassignment

---

## Data Model

### New Database Tables

```sql
organizations
├── id (PRIMARY KEY)
├── name
├── created_by (FK: users.id)
└── created_at

organization_members
├── id (PRIMARY KEY)
├── user_id (FK: users.id)
├── organization_id (FK: organizations.id)
├── role (organization_admin | member | client)
└── created_at

users (MODIFIED)
├── [existing fields]
└── role (super_admin | member)  -- NEW
```

### New Task Fields

```sql
tasks (MODIFIED)
├── [existing fields]
├── organization_id (FK: organizations.id, NULL for personal tasks)
├── assigned_to (FK: users.id)
├── assigned_by (FK: users.id)
└── status (pending | in_progress | submitted | completed | rejected)
```

---

## API Reference

### Organizations

**GET** `/api/organizations.php`

- Returns user's organizations (super admin sees all)

**POST** `/api/organizations.php`

- Create organization (super admin only)
- Body: `{ "name": "..." }`

**POST** `/api/organizations.php?action=add-member`

- Add member to organization (admin only)
- Body: `{ "organization_id": 1, "email": "...", "role": "member" }`

### Tasks (Organizational Mode)

**GET** `/api/tasks.php?mode=organization&organization_id=1`

- List org tasks (admins see all, members see only assigned)

**POST** `/api/tasks.php`

- Create organization task
- Body: `{ "organization_id": 1, "assigned_to": 2, "title": "...", ... }`

**PUT** `/api/tasks.php`

- Update task status or fields (admin edit, member status update only)
- Body: `{ "id": 1, "status": "in_progress" }`

**PUT** `/api/tasks.php`

- Submit task for review
- Body: `{ "id": 1, "action": "submit" }`

**PUT** `/api/tasks.php`

- Review submitted task (admin only)
- Body: `{ "id": 1, "action": "review", "reviewAction": "approve" }`

**DELETE** `/api/tasks.php?id=1`

- Delete organization task (admin only)

---

## Frontend Integration

The dashboard automatically detects organization mode and:

1. **Hides personal task UI** (complete button, reflections not shown for org tasks)
2. **Shows org task UI** (submit button, approve/reject buttons, status badges)
3. **Scopes task lists** based on user role and assignments
4. **Populates assignee selectors** from organization members

### Key Frontend Functions

```javascript
// Switch workspace modes
setOrganizationMode("organization" | "personal");

// Manage organization selection
setSelectedOrganization(organizationId);

// Submit a task for review
submitTaskAction(taskId);

// Approve or reject a submitted task
reviewTaskAction(taskId, "approve" | "reject");

// Load and display organizations
loadOrganizations();

// Check if current user is org admin
isCurrentUserOrganizationAdmin();
```

---

## Common Workflows

### Workflow 1: Assign Task to Employee

1. SuperAdmin creates organization "Acme Corp"
2. SuperAdmin adds `jane@acme.com` as `member`
3. SuperAdmin creates task "Quarterly Report" and assigns to Jane
4. Jane receives task (only sees her assignments in org mode)
5. Jane updates status to `in_progress`, then `submitted` when done
6. SuperAdmin sees "Quarterly Report" as submitted
7. SuperAdmin clicks **Approve** → Task marked completed

### Workflow 2: Client Review Cycle

1. SuperAdmin adds `client@vendor.com` as `client` (read-only)
2. SuperAdmin assigns task "Design Mockup" to Designer (member)
3. Designer completes and submits task
4. SuperAdmin approves task
5. Client can see completed task in their organization view

---

## Role Permissions Matrix

| Action               | Super Admin | Org Admin | Member | Client |
| -------------------- | :---------: | :-------: | :----: | :----: |
| Create Organization  |      ✓      |     ✗     |   ✗    |   ✗    |
| Add/Remove Members   |      ✓      |     ✓     |   ✗    |   ✗    |
| View All Org Tasks   |      ✓      |     ✓     |   ✗    |   ✗    |
| View Assigned Tasks  |      ✓      |     ✓     |   ✓    |   ✓    |
| Create Org Task      |      ✓      |     ✓     |   ✗    |   ✗    |
| Edit Org Task        |      ✓      |     ✓     |   ✗    |   ✗    |
| Update Task Status   |      ✓      |     ✓     |  ✓\*   |   ✗    |
| Submit Task          |      ✓      |     ✓     |  ✓\*   |   ✗    |
| Review/Approve Task  |      ✓      |     ✓     |   ✗    |   ✗    |
| Create Personal Task |      ✓      |     ✓     |   ✓    |   ✓    |

\*Members can only update status of tasks assigned to them.

---

## Troubleshooting

### "Only organization admins can create organization tasks"

- You must be an `organization_admin` for that organization
- Only the organization creator or super admin can promote members to admin

### "Select an organization before saving this task"

- In Organizational Mode, you must pick an organization before creating the task
- Check that your **Selected Organization** dropdown has a value

### Task disappears after save

- Non-admin members only see tasks assigned to them
- Check the task's `assigned_to` field; it should match your user ID
- Organization admins see all org tasks regardless of assignment

### No members in assignee dropdown

- The organization has no members yet
- As an admin, click **+ Add Member** to populate the organization

---

## Implementation Notes

- **Backward Compatibility**: Existing personal tasks (`organization_id = NULL`) work unchanged
- **Database Migration**: Existing tasks are preserved with `organization_id = NULL`
- **Session Auth**: Uses JWT tokens; no changes to authentication flow
- **Offline Support**: Organization mode respects localStorage sync (personal mode already does)

---

## Future Enhancements

- [ ] Email notifications on task assignment/approval
- [ ] Inline task comments/notes
- [ ] Organization-level analytics dashboard
- [ ] Delegation of organization admin role
- [ ] Task templates for repeated workflows
- [ ] Integration with calendar/calendar invites for org tasks
