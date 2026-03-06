# TaskFlow - System Architecture

## 🏗️ High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                          CLIENT (Browser)                            │
├──────────────────────────────┬──────────────────────────────────────┤
│  Personal Mode (index.html)  │  Organization Mode (login.html)      │
│  ├─ localStorage tasks       │  ├─ Admin Dashboard                  │
│  ├─ Countdown timers         │  ├─ Employee Dashboard               │
│  ├─ Notifications            │  ├─ Task Management                  │
│  └─ Forget Password          │  ├─ Submission System                │
│                              │  └─ User Management                  │
└──────────────────────────────┴──────────────────────────────────────┘
                                    │
                    ┌───────────────┴───────────────┐
                    │                               │
           REST API Calls                  Static HTML/CSS/JS
                    │
┌───────────────────┴───────────────────────────────────────────────────┐
│                    HTTP/HTTPS (Apache 2.4)                            │
├──────────────────────────────────────────────────────────────────────┤
│  ├─ Router.php (Main API Entry Point)                               │
│  ├─ Authentication (auth.php)                                       │
│  ├─ Task Management (tasks.php)                                     │
│  ├─ Submissions (submissions.php)                                   │
│  ├─ Dashboard (dashboard.php)                                       │
│  ├─ Users (users.php)                                               │
│  └─ Helpers (validation, auth, file handling)                       │
└────────────┬────────────────────────────────────────────────────────┘
             │
             │ PDO Prepared Statements
             │
┌────────────┴────────────────────────────────────────────────────────┐
│                     MySQL Database (8 Tables)                       │
├──────────────────────────────────────────────────────────────────────┤
│  ├─ users                 (User accounts & roles)                   │
│  ├─ tasks                 (Task definitions)                        │
│  ├─ submissions           (Task submissions & feedback)             │
│  ├─ sessions              (Login sessions)                          │
│  ├─ password_reset_tokens (Forgot password flow)                    │
│  ├─ notifications         (Notification history)                    │
│  ├─ analytics             (Activity tracking)                       │
│  └─ (triggers for auto-timestamps)                                 │
└──────────────────────────────────────────────────────────────────────┘
```

## 🔄 Request Flow Diagram

### User Login Flow

```
User Input (Email/Password)
        │
        ▼
POST /backend/api/auth/login
        │
        ▼
PHP Router (router.php)
        │
        ▼
Auth Handler (auth.php)
        │
        ├─ Validate Input (email, password format)
        │
        ├─ Query Database (prepared statement)
        │
        ├─ Verify Password (bcrypt)
        │
        ├─ Generate Session Token
        │
        ├─ Store in sessions table
        │
        ▼
Return JSON Response
{
  success: true,
  token: "abc123...",
  user_id: 1,
  role: "admin"
}
        │
        ▼
Store in localStorage
        │
        ▼
Redirect to Dashboard
```

### Task Submission Flow

```
Employee Submits Task (Form + File)
        │
        ▼
POST /backend/api/submissions/submit
        │
        ├─ Parse multipart form data
        │
        ├─ Authenticate request (verify token)
        │
        ├─ Validate input fields
        │
        ├─ Validate file (type, size)
        │
        ├─ Save file to disk
        │
        ├─ Insert into submissions table
        │       │
        │       └─ submission_text, file_path, status, timestamps
        │
        ├─ Send notification to admin
        │
        ▼
Return Success Response
        │
        ▼
Update UI (show submission status)
```

### Admin Approval Flow

```
Admin Reviews Submission
        │
        ▼
POST /backend/api/submissions/approve?id=X
        │
        ├─ Authenticate & check role (admin only)
        │
        ├─ Verify submission exists
        │
        ├─ Update submission status to "approved"
        │
        ├─ Add admin comment
        │
        ├─ Update task status to "completed"
        │
        ├─ Send email notification to employee
        │
        ▼
Return Success Response
        │
        ▼
Dashboard Updates (stats, completion rate)
```

## 📊 Data Model Relationships

```
┌──────────────┐
│    users     │
├──────────────┤
│ id           │ (PK)
│ email        │ (UNIQUE)
│ password     │ (HASHED)
│ role         │ (admin|employee)
│ created_at   │
└────┬─────────┘
     │
     ├──────────────────────────┬────────────────────────┐
     │                          │                        │
     │ assigned_to              │ created_by             │
     │                          │                        │
     ▼                          ▼                        ▼
┌──────────────┐          ┌──────────────┐
│    tasks     │          │   sessions   │
├──────────────┤          ├──────────────┤
│ id           │ (PK)     │ id           │ (PK)
│ title        │          │ user_id      │ (FK→users)
│ assigned_to  │ (FK)     │ token        │ (UNIQUE)
│ created_by   │ (FK)     │ expires_at   │
│ deadline     │          │ created_at   │
│ status       │          └──────────────┘
│ created_at   │
└────┬─────────┘
     │
     │ task_id
     │
     ▼
┌──────────────────────┐
│   submissions        │
├──────────────────────┤
│ id                   │ (PK)
│ task_id              │ (FK→tasks)
│ employee_id          │ (FK→users)
│ submission_text      │
│ file_path            │
│ status               │ (pending|approved|rejected)
│ admin_comment        │
│ admin_id             │ (FK→users)
│ submitted_at         │
│ reviewed_at          │
└──────────────────────┘
```

## 🔐 Security Layers

```
┌─────────────────────────────────────────────────────┐
│ Layer 1: HTTP Protocol                              │
│ ├─ HTTPS/SSL in production                          │
│ ├─ CORS headers validation                          │
│ └─ Security headers (X-Frame-Options, etc.)         │
└─────────────────────────────────────────────────────┘
                         │
┌─────────────────────────┴──────────────────────────┐
│ Layer 2: Authentication & Authorization            │
│ ├─ Token-based authentication (sessions table)     │
│ ├─ Session expiration (configurable)               │
│ ├─ Role-based access control (admin/employee)      │
│ └─ Per-endpoint authorization checks               │
└─────────────────────────────────────────────────────┘
                         │
┌─────────────────────────┴──────────────────────────┐
│ Layer 3: Input Validation                          │
│ ├─ Email format validation                         │
│ ├─ Required field checks                           │
│ ├─ String sanitization (htmlspecialchars)          │
│ ├─ File type & size validation                     │
│ └─ Data type validation                            │
└─────────────────────────────────────────────────────┘
                         │
┌─────────────────────────┴──────────────────────────┐
│ Layer 4: Data Access                               │
│ ├─ Prepared statements (SQL injection prevention)  │
│ ├─ Parameterized queries                          │
│ ├─ PDO with proper attributes                     │
│ └─ Connection string security                      │
└─────────────────────────────────────────────────────┘
                         │
┌─────────────────────────┴──────────────────────────┐
│ Layer 5: Storage Security                          │
│ ├─ Encrypted password storage (bcrypt)             │
│ ├─ Secure file upload location (outside webroot)   │
│ ├─ Environment variables for secrets               │
│ └─ Proper file permissions                         │
└─────────────────────────────────────────────────────┘
```

## 📱 Client-Server Communication

### Request Format

```
POST /backend/api/tasks/create HTTP/1.1
Host: localhost
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
Content-Type: application/json

{
  "title": "Complete Project",
  "description": "Finish Q1 report",
  "assigned_to": 2,
  "deadline": "2026-03-15",
  "due_time": "17:00",
  "priority": "high"
}
```

### Response Format

```
HTTP/1.1 200 OK
Content-Type: application/json
Access-Control-Allow-Origin: *

{
  "success": true,
  "task_id": 42,
  "message": "Task created successfully"
}
```

### Error Response Format

```
HTTP/1.1 400 Bad Request
Content-Type: application/json

{
  "error": "Validation failed",
  "details": {
    "title": "Title is required",
    "assigned_to": "Invalid employee selected"
  }
}
```

## 🔄 API Endpoint Categories

### Authentication (6 endpoints)

```
POST   /auth/register          - Create new account
POST   /auth/login             - User login
POST   /auth/logout            - Terminate session
POST   /auth/forgot-password   - Request password reset
POST   /auth/verify-code       - Verify OTP
POST   /auth/reset-password    - Set new password
```

### Tasks (7 endpoints)

```
POST   /tasks/create           - Create task (admin)
GET    /tasks/list             - List tasks (filtered by role)
GET    /tasks/get              - Get task details
POST   /tasks/update           - Update task (admin)
DELETE /tasks/delete           - Delete task (admin)
GET    /tasks/my-tasks         - Get user's tasks
GET    /tasks/analytics        - Task stats (admin)
```

### Submissions (6 endpoints)

```
POST   /submissions/submit     - Submit task
GET    /submissions/list       - List submissions
GET    /submissions/get        - Get submission details
POST   /submissions/approve    - Approve (admin)
POST   /submissions/reject     - Reject (admin)
GET    /submissions/my-submissions - Employee's submissions
```

### Dashboard (4 endpoints)

```
GET    /dashboard/admin-overview      - Admin stats
GET    /dashboard/employee-dashboard  - Employee stats
GET    /dashboard/recent-submissions  - Recent items
GET    /dashboard/employee-stats      - Employee performance
```

### Users (6 endpoints)

```
GET    /users/list             - All users (admin)
GET    /users/employees        - Employee list (admin)
POST   /users/create-employee  - Create employee (admin)
POST   /users/update           - Update profile
POST   /users/toggle-status    - Enable/disable user (admin)
GET    /users/profile          - Current user info
```

## 💾 Session Management

### Session Lifecycle

```
Login
  │
  ├─ Generate token (32 random bytes in hex = 64 chars)
  │
  ├─ Store in sessions table
  │   │
  │   ├─ user_id
  │   ├─ token
  │   ├─ ip_address
  │   ├─ expires_at (NOW + SESSION_TIMEOUT)
  │   └─ created_at
  │
  ├─ Return token to client
  │
  └─ Client stores in localStorage

API Requests
  │
  ├─ Client includes: Authorization: Bearer {token}
  │
  ├─ Server verifies:
  │   ├─ Token exists in sessions table
  │   ├─ Not expired (expires_at > NOW())
  │   └─ Fetch associated user
  │
  └─ Proceed with request

Logout
  │
  ├─ Delete token from sessions table
  │
  └─ Clear localStorage on client
```

## 📈 Performance Considerations

1. **Database Indexes**
     - PK on all tables
     - FK indexes for joins
     - Index on email (quick lookups)
     - Index on deadline (sorting tasks)
     - Index on status (filtering)

2. **Query Optimization**
     - Prepared statements (execution plan caching)
     - Selective column retrieval
     - Proper pagination (for future)
     - Connection pooling ready

3. **Frontend Caching**
     - localStorage for tokens
     - Browser cache for static assets
     - No-cache for API responses (for accuracy)

## 🚀 Scalability Path

### Current (Development)

```
┌──────────┐
│  Client  │
└────┬─────┘
     │
┌────┴──────────┐
│  Apache+PHP   │  (Single instance)
│  MySQL        │
│  Redis (opt)  │
└────┬──────────┘
     │
  ~50 users
```

### Growth (Production Ready)

```
┌──────────┐ ┌──────────┐
│ Client 1 │ │ Client N │
└────┬─────┘ └────┬─────┘
     │            │
     └────┬───────┘
          │
    ┌─────┴──────────┐
    │  Load Balancer │
    └─────┬──────────┘
          │
    ┌─────┴──────────────┬──────────────┐
    │                    │              │
┌───┴─────┐        ┌────┴────┐    ┌───┴─────┐
│ PHP 1   │        │ PHP 2   │    │ PHP N   │
├─────────┤        ├─────────┤    ├─────────┤
│ Redis   │        │ Redis   │    │ Redis   │
│ Cache   │        │ Cache   │    │ Cache   │
└─────────┘        └─────────┘    └─────────┘
    │                    │              │
    └─────┬──────────────┴──────────────┘
          │
    ┌─────┴─────────┐
    │ MySQL Cluster │
    │ (Replication) │
    └───────────────┘

  ~5,000+ users
```

---

**Architecture Designed for**: Scalability & Security  
**Current Capacity**: ~100+ concurrent users  
**Production Ready**: With minimal configuration changes
