# TaskFlow - Multi-User Organizational Task Management Platform

![TaskFlow Architecture](https://img.shields.io/badge/version-2.0.0-blue)
![License](https://img.shields.io/badge/license-MIT-green)

**TaskFlow** is a comprehensive task management platform that supports both **Personal Mode** (single-user, localStorage-based) and **Organization Mode** (multi-user, database-driven with role-based access control).

## 🌟 Key Features

### Personal Mode (Existing)

- ✅ Task creation and management without login
- ✅ Countdown timer with real-time updates
- ✅ Browser notifications for reminders
- ✅ Email reminders via Resend API
- ✅ Dark/light theme support
- ✅ Mobile responsive design
- ✅ Forgot password system with 6-digit code

### Organization Mode (New)

- ✅ Role-based authentication (Admin & Employee)
- ✅ Admin dashboard with analytics
- ✅ Task assignment to employees
- ✅ Task submission and approval workflow
- ✅ File upload support
- ✅ Admin comments and feedback system
- ✅ Employee dashboard with task tracking
- ✅ Email notifications for all actions
- ✅ Secure session management

## 🏗️ System Architecture

### Tech Stack

- **Frontend**: HTML5, CSS3, JavaScript (Vanilla)
- **Backend**: PHP 8.2 with Apache
- **Database**: MySQL 8.0
- **Containerization**: Docker & Docker Compose
- **Email**: Resend API
- **Security**: bcrypt password hashing, JWT tokens, prepared statements

### Project Structure

```
TaskFlow/
├── docker-compose.yml          # Docker services configuration
├── Dockerfile                  # PHP Apache container
├── apache-config.conf          # Apache virtual host config
├── .env.example               # Environment variables template
├── index.html                 # Personal mode main app
├── login.html                 # Organization mode login
├── register.html              # Organization mode registration
├── admin-dashboard.html       # Admin dashboard
├── employee-dashboard.html    # Employee dashboard
├── frontend/
│   ├── css/
│   │   ├── global.css
│   │   ├── auth.css
│   │   └── dashboard.css
│   └── js/
│       ├── api.js            # API client
│       ├── auth.js           # Authentication logic
│       └── app.js            # Main app logic
├── backend/
│   ├── config/
│   │   └── database.php       # Database configuration
│   ├── api/
│   │   ├── router.php         # API router
│   │   ├── auth.php           # Authentication endpoints
│   │   ├── tasks.php          # Task management endpoints
│   │   ├── submissions.php    # Submission endpoints
│   │   ├── dashboard.php      # Dashboard endpoints
│   │   └── users.php          # User management endpoints
│   ├── helpers.php            # Helper functions
│   └── middleware/
│       └── auth.php           # Authentication middleware
├── database/
│   ├── schema.sql             # Database schema
│   └── initial_data.sql       # Sample data
├── uploads/                   # File upload directory
├── logs/                      # Application logs
└── README.md
```

## 🚀 Quick Start

### Prerequisites

- Docker & Docker Compose
- Git

### Installation

1. **Clone the repository**

     ```bash
     git clone <repository>
     cd TaskFlow
     ```

2. **Create environment file**

     ```bash
     cp .env.example .env
     ```

3. **Configure environment variables** (.env)

     ```env
     DB_HOST=mysql
     DB_PORT=3306
     DB_NAME=taskflow
     DB_USER=taskflow_user
     DB_PASSWORD=taskflow_password
     DB_ROOT_PASSWORD=root_password

     RESEND_API_KEY=your_api_key_here
     APP_ENV=development
     ```

4. **Start Docker containers**

     ```bash
     docker-compose up -d
     ```

5. **Access applications**
     - **Personal Mode**: http://localhost
     - **Organization Mode**: http://localhost/login.html
     - **phpMyAdmin**: http://localhost:8081
     - **API Documentation**: http://localhost/backend

### Default Credentials (Development)

- **Admin Email**: admin@taskflow.com
- **Admin Password**: admin123 (set in initial_data.sql)
- **Employee Emails**: john@taskflow.com, jane@taskflow.com, bob@taskflow.com
- **Employee Password**: emp123

## 📋 Database Schema

### Users Table

- Stores all system users (admins and employees)
- Supports role-based access control
- Tracks creation and update timestamps

### Tasks Table

- Task assignments from admin to employees
- Supports deadline, priority, and reminder settings
- Status tracking (pending, in_progress, completed)

### Submissions Table

- Employee task submissions
- File upload support
- Admin approval/rejection workflow
- Comment system for feedback

### Additional Tables

- **password_reset_tokens**: For forgot password flow
- **sessions**: For session management
- **notifications**: For storing notification history
- **analytics**: For tracking admin activities

## 🔐 Authentication & Security

### Login Flow

1. User enters email and password
2. Server validates credentials using bcrypt
3. Session token is generated and stored in `sessions` table
4. Token is returned to frontend (stored in localStorage)
5. Subsequent API requests include token in Authorization header

### Password Security

- Passwords hashed with bcrypt (cost factor: 10)
- Forgot password uses 6-digit OTP with 10-minute expiration
- Password reset invalidates all user sessions
- Minimum password length: 8 characters

### Authorization

- Role-based access control (RBAC)
- Admins can manage tasks and employees
- Employees can only view assigned tasks
- File upload restrictions by extension

## 📡 API Endpoints

### Authentication

- **POST** `/backend/api/auth/login` - User login
- **POST** `/backend/api/auth/register` - User registration
- **POST** `/backend/api/auth/logout` - User logout
- **POST** `/backend/api/auth/forgot-password` - Request password reset
- **POST** `/backend/api/auth/reset-password` - Reset password
- **GET** `/backend/api/auth/user-info` - Get current user info

### Tasks

- **POST** `/backend/api/tasks/create` - Create task (Admin only)
- **GET** `/backend/api/tasks/list` - List tasks
- **GET** `/backend/api/tasks/get?id={id}` - Get task details
- **POST** `/backend/api/tasks/update?id={id}` - Update task (Admin only)
- **DELETE** `/backend/api/tasks/delete?id={id}` - Delete task (Admin only)
- **GET** `/backend/api/tasks/my-tasks` - Get user's tasks
- **GET** `/backend/api/tasks/analytics` - Get task analytics (Admin only)

### Submissions

- **POST** `/backend/api/submissions/submit` - Submit task
- **GET** `/backend/api/submissions/list` - List submissions
- **GET** `/backend/api/submissions/get?id={id}` - Get submission details
- **POST** `/backend/api/submissions/approve?id={id}` - Approve submission (Admin only)
- **POST** `/backend/api/submissions/reject?id={id}` - Reject submission (Admin only)
- **GET** `/backend/api/submissions/my-submissions` - Get user's submissions

### Dashboard

- **GET** `/backend/api/dashboard/admin-overview` - Admin dashboard stats
- **GET** `/backend/api/dashboard/employee-dashboard` - Employee dashboard stats
- **GET** `/backend/api/dashboard/recent-submissions` - Recent submissions (Admin)
- **GET** `/backend/api/dashboard/employee-stats` - Employee statistics (Admin)

### Users

- **GET** `/backend/api/users/list` - List all users (Admin only)
- **GET** `/backend/api/users/employees` - List employees (Admin only)
- **POST** `/backend/api/users/create-employee` - Create new employee (Admin only)
- **POST** `/backend/api/users/update?id={id}` - Update user info
- **GET** `/backend/api/users/profile` - Get current user profile

## 🎨 User Interface

### Personal Mode

- Single-page application
- No authentication required
- localStorage for data persistence
- Countdown timers with visual indicators
- Browser notification prompts
- Forgot password modal with email verification

### Admin Dashboard

- Overview cards with key metrics
- Task management table with filtering
- Employee list with performance stats
- Submission review interface
- Analytics charts and reports
- User management panel

### Employee Dashboard

- Assigned tasks list with status
- Submission form with file upload
- Task deadline tracking
- Admin comments visibility
- Personal statistics
- Notification history

## 🔔 Notification System

### Browser Notifications

- Automatic request for permission on app load
- Task deadline reminders
- Submission status updates
- Admin feedback alerts

### Email Notifications

- Password reset codes (Resend API)
- New task assignments
- Submission feedback
- Approved/rejected notifications
- Welcome emails for new employees

## 📦 File Upload System

### Supported File Types

- Documents: PDF, DOCX, DOC, TXT, XLSX, XLS
- Images: JPG, JPEG, PNG, GIF

### Upload Limits

- Maximum file size: 10MB
- Stored in `/uploads/submissions/`
- Files named with timestamp and unique ID
- Path stored in database

## 🧪 Testing

### Sample Data

Sample users and tasks are loaded from `database/initial_data.sql`:

**Admin User:**

- Email: admin@taskflow.com
- Password: admin123

**Sample Employees:**

- john@taskflow.com
- jane@taskflow.com
- bob@taskflow.com

### Test Workflow

1. Login as admin
2. Create new task and assign to employee
3. Login as employee
4. View and submit task
5. Login as admin to review submission
6. Approve/reject with comments

## 🔧 Development

### Running in Development Mode

```bash
docker-compose up -d
docker-compose logs -f php  # Watch PHP logs
```

### Debugging

- Query logs in `/logs/php_errors.log`
- Database queries visible in phpMyAdmin
- API responses in browser console
- Session tokens in localStorage

### Making API Calls

```javascript
// Get auth token
const token = localStorage.getItem("token");

// Make authenticated request
fetch("/backend/api/tasks/list", {
	method: "GET",
	headers: {
		Authorization: `Bearer ${token}`,
		"Content-Type": "application/json",
	},
})
	.then((res) => res.json())
	.then((data) => console.log(data));
```

## 🚨 Security Best Practices

- ✅ All passwords hashed with bcrypt
- ✅ SQL injection prevented with prepared statements
- ✅ CORS headers configured
- ✅ Session tokens validated on each request
- ✅ File upload validation (type and size)
- ✅ Role-based access control enforced
- ✅ Sensitive data logged without display in production
- ✅ HTTPS ready (SSL cert files in Dockerfile)

## 📝 Environment Variables

| Variable        | Description               | Example           |
| --------------- | ------------------------- | ----------------- |
| DB_HOST         | MySQL hostname            | mysql             |
| DB_PORT         | MySQL port                | 3306              |
| DB_NAME         | Database name             | taskflow          |
| DB_USER         | Database user             | taskflow_user     |
| DB_PASSWORD     | Database password         | taskflow_password |
| RESEND_API_KEY  | Email API key             | re_xxxxx          |
| APP_ENV         | Environment               | development       |
| APP_DEBUG       | Debug mode                | true              |
| SESSION_TIMEOUT | Session timeout (seconds) | 3600              |
| JWT_SECRET      | JWT signing key           | your_secret       |

## 🤝 Contributing

To contribute to TaskFlow:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit changes (`git commit -m 'Add amazing feature'`)
4. Push to branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📄 License

This project is licensed under the MIT License - see LICENSE file for details.

## 👨‍💻 Support

For issues, questions, or suggestions:

- Open an issue on GitHub
- Contact: support@taskflow.app
- Documentation: See `/docs` folder

## 🔄 Migration from Single-User to Organization Mode

If you have existing personal mode tasks in localStorage:

1. Access personal mode normally at http://localhost
2. Your tasks are stored locally and not affected
3. Register for organization mode separately
4. Both modes work independently
5. No automatic migration needed

## 📈 Roadmap

- [ ] Task categories and tags
- [ ] Advanced filtering and search
- [ ] Dashboard export to PDF
- [ ] Two-factor authentication (2FA)
- [ ] Real-time notifications with WebSockets
- [ ] Mobile app (React Native)
- [ ] Performance analytics dashboard
- [ ] Integration with calendar apps

## 🆘 Troubleshooting

### Docker Compose Issues

```bash
# Restart containers
docker-compose restart

# Rebuild images
docker-compose up -d --build

# Check logs
docker-compose logs -f
```

### Database Connection Issues

```bash
# Check MySQL status
docker-compose ps

# Verify credentials
docker exec taskflow-mysql mysql -u root -p taskflow -e "SELECT 1"
```

### API Not Working

1. Check `/logs/php_errors.log`
2. Verify database schema is created
3. Confirm .env variables are set
4. Check browser console for errors
5. Test with phpMyAdmin

## 📞 Contact

**Project Lead**: TaskFlow Team  
**Email**: team@taskflow.app  
**Website**: https://taskflow.app

---

**TaskFlow v2.0** - Empowering teams to manage tasks efficiently 🚀
