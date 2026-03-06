# 🚀 TaskFlow - Quick Start Guide

## Get Started in 5 Minutes

### 1️⃣ Start Docker (1 minute)

```bash
cd /home/king/Desktop/Projects/Task-Management
docker-compose up -d
```

### 2️⃣ Access the Application (1 minute)

```
Welcome Screen: http://localhost/welcome.html
Admin Login: http://localhost/login.html
Email: admin@taskflow.com
Password: admin123
```

### 3️⃣ Test Features (3 minutes)

**Admin Dashboard** → http://localhost/admin-dashboard.html

- Create a task
- Assign to employee
- View submissions
- Approve/reject work

**Employee Dashboard** → http://localhost/employee-dashboard.html

- View assigned tasks
- Submit work with file
- See admin feedback

**Personal Mode** → http://localhost/index.html

- Standalone task manager
- No login required
- Countdown timers
- Offline capable

---

## 📊 Default Test Credentials

| Role     | Email              | Password |
| -------- | ------------------ | -------- |
| Admin    | admin@taskflow.com | admin123 |
| Employee | john@taskflow.com  | emp123   |
| Employee | jane@taskflow.com  | emp123   |
| Employee | bob@taskflow.com   | emp123   |

---

## 🎯 Main Features by Section

### Welcome Screen

- Choose between Personal Mode or Organization Mode
- Beautiful UI with mode selector cards

### Personal Mode (index.html)

- ✅ Standalone task management
- ✅ Countdown timers with live updates
- ✅ Dark/light theme toggle
- ✅ Offline functional with localStorage
- ✅ Organization mode switcher button

### Admin Dashboard (admin-dashboard.html)

- 5 main tabs: Overview, Tasks, Submissions, Employees, Analytics
- Create & manage tasks
- Review & approve submissions
- Add & manage employees
- Real-time analytics & metrics

### Employee Dashboard (employee-dashboard.html)

- Overview of assigned tasks
- List of tasks with countdown timers
- Submit work with file upload
- Track submission status
- View admin feedback

---

## 🔧 Key Endpoints Reference

### Authentication

```
POST /backend/api/auth/register
POST /backend/api/auth/login
POST /backend/api/auth/logout
POST /backend/api/auth/forgot-password
POST /backend/api/auth/verify-code
POST /backend/api/auth/password-reset
```

### Tasks

```
POST /backend/api/tasks/create
GET /backend/api/tasks
GET /backend/api/tasks/{id}
GET /backend/api/tasks/my-tasks
PUT /backend/api/tasks/{id}/update
DELETE /backend/api/tasks/{id}
```

### Submissions

```
POST /backend/api/submissions/submit
GET /backend/api/submissions
GET /backend/api/submissions/{id}
PUT /backend/api/submissions/{id}/approve
PUT /backend/api/submissions/{id}/reject
GET /backend/api/submissions/my-submissions
```

### Dashboards

```
GET /backend/api/dashboard/admin-overview
GET /backend/api/dashboard/employee-dashboard
GET /backend/api/dashboard/recent-submissions
GET /backend/api/dashboard/employee-stats
```

### Users

```
POST /backend/api/users/create-employee
GET /backend/api/users/employees
GET /backend/api/users/{id}
PUT /backend/api/users/{id}/update
PUT /backend/api/users/{id}/toggle-status
```

---

## 🐛 Troubleshooting Quick Fixes

### Services Not Starting?

```bash
docker-compose down
docker-compose up -d
docker-compose logs
```

### Database Connection Error?

```bash
docker-compose restart mysql
# Wait 10 seconds for MySQL to be ready
docker-compose logs mysql
```

### Page Not Loading?

```
1. Hard refresh: Ctrl+Shift+R
2. Clear cookies/cache
3. Check browser console (F12)
4. Verify services: docker-compose ps
```

### Countdown Timer Not Showing?

```
1. Ensure task has deadline date
2. Check countdown-timer.js is loaded
3. Open browser console (F12)
4. Look for timer-related errors
```

### File Upload Failing?

```bash
# Fix permissions
docker exec taskflow-php chmod 755 /var/www/html/uploads
docker restart taskflow-php
```

---

## 📚 Documentation Map

| Document                        | Purpose          | Key Topics                         |
| ------------------------------- | ---------------- | ---------------------------------- |
| README_NEW.md                   | Feature overview | All features, setup, API reference |
| QUICKSTART.md                   | Detailed setup   | Step-by-step with code examples    |
| ARCHITECTURE.md                 | System design    | Diagrams, data flow, tech stack    |
| SUMMARY.md                      | Project overview | Components, file structure         |
| FINAL_IMPLEMENTATION_SUMMARY.md | Complete guide   | Everything, deployment, support    |
| IMPLEMENTATION_PHASE_SUMMARY.md | This phase       | Files created, changes made        |

---

## ⚙️ System Status Commands

```bash
# Check all services running
docker-compose ps

# View live logs
docker-compose logs -f

# Check specific service
docker-compose logs php
docker-compose logs mysql

# Monitor resource usage
docker stats

# Check database
docker exec taskflow-mysql mysql -u taskflow_user -p taskflow

# Access database UI
http://localhost:8081
Username: taskflow_user
Password: [from .env]
```

---

## 🔄 Common Development Tasks

### Make Code Changes

```bash
# Backend (PHP)
Edit files in backend/ folder
Changes apply instantly (Apache auto-reload)

# Frontend (HTML/JS/CSS)
Edit files in root or frontend/ folder
Reload browser (F5)

# Database schema
Edit database/schema.sql
Restart MySQL to apply changes
docker-compose exec mysql mysql < database/schema.sql
```

### Test an API Endpoint

```bash
# Get authentication token
curl -X POST http://localhost/backend/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@taskflow.com","password":"admin123"}'

# Use token in requests
curl -X GET http://localhost/backend/api/tasks \
  -H "Authorization: Bearer [your_token]"
```

### View Error Logs

```bash
# PHP Errors
docker-compose logs php | grep -i error

# MySQL Errors
docker-compose logs mysql | grep -i error

# Browser Console
Press F12 in browser
Check Console tab for JavaScript errors
```

---

## 📱 Mobile Access

The application is fully responsive:

- Admin Dashboard works on tablets
- Employee Dashboard works on mobile
- Personal Mode works on all devices
- Countdown timers update smoothly on mobile

### Access from Mobile

```
If running on desktop:
  http://[your-ip]:80/welcome.html

Example:
  http://192.168.1.100/welcome.html
```

---

## 🎓 For Developers

### Adding a New Task

```bash
# As Admin:
1. Go to admin-dashboard.html
2. Click "Tasks" tab
3. Fill form:
   - Title
   - Description
   - Assign to: [employee name]
   - Deadline: [date]
   - Due Time: [time]
   - Priority: [level]
4. Click "Create Task"
```

### Creating a New Employee

```bash
# As Admin:
1. Go to admin-dashboard.html
2. Click "Employees" tab
3. Fill "Add New Employee" form:
   - Full Name
   - Email
   - Department (optional)
   - Phone (optional)
4. Click "Add Employee"
5. Email sent to employee with temporary password
```

### Submitting a Task

```bash
# As Employee:
1. Go to employee-dashboard.html
2. Click "My Submissions" tab
3. Select task from dropdown
4. Write submission text
5. (Optional) Choose file to upload
6. Click "Submit Task"
```

---

## 🔐 Security Reminders

- ✅ Always use HTTPS in production
- ✅ Change default admin password immediately
- ✅ Use strong database password
- ✅ Keep API keys secure
- ✅ Enable two-factor authentication if available
- ✅ Regular database backups
- ✅ Monitor access logs
- ✅ Update dependencies regularly

---

## 💾 Backup & Recovery

### Create Backup

```bash
# Database backup
docker exec taskflow-mysql mysqldump -u taskflow_user -p taskflow > backup.sql

# File backup
tar -czf uploads_backup.tar.gz uploads/

# Full backup
tar -czf taskflow_backup.tar.gz backend/ database/ frontend/
```

### Restore from Backup

```bash
# Database restore
docker exec -i taskflow-mysql mysql -u taskflow_user -p taskflow < backup.sql

# File restore
tar -xzf uploads_backup.tar.gz
```

---

## 📞 Support Resources

### Check These First

1. **Browser console** (F12) - JavaScript errors
2. **PHP logs** - `docker-compose logs php`
3. **MySQL logs** - `docker-compose logs mysql`
4. **Documentation** - See README_NEW.md
5. **This guide** - Quick troubleshooting above

### API Testing Tools

- **Postman** - Import API from QUICKSTART.md
- **curl** - Command line testing
- **Browser Dev Tools** - Network tab monitoring

### Performance Monitoring

```bash
# Real-time resource usage
docker stats

# Connection count
docker exec taskflow-mysql mysql -u taskflow_user -p taskflow -e "SHOW PROCESSLIST;"

# Database size
docker exec taskflow-mysql mysql -u taskflow_user -p taskflow \
  -e "SELECT table_name, ROUND(((data_length + index_length) / 1024 / 1024), 2) \
  FROM information_schema.TABLES WHERE table_schema = 'taskflow' ORDER BY (data_length + index_length) DESC;"
```

---

## 🎯 Next Steps

1. **Explore Features** - Try both Personal and Organization modes
2. **Create Test Data** - Add tasks, assign employees, submit work
3. **Test Workflows** - Go through complete task submission cycle
4. **Review Code** - Check implementation details
5. **Plan Deployment** - Read FINAL_IMPLEMENTATION_SUMMARY.md
6. **Customize** - Adapt to your organization's needs

---

## 🚀 Launch Command

```bash
cd /home/king/Desktop/Projects/Task-Management
docker-compose up -d
echo "✅ TaskFlow is running!"
echo "📱 Open: http://localhost/welcome.html"
echo "👨‍💼 Admin: admin@taskflow.com / admin123"
```

---

**Questions?** Check the documentation files or review the code comments.

**Ready?** Start with `docker-compose up -d` and visit `http://localhost/welcome.html`!

**Happy Task Managing!** 🎉
