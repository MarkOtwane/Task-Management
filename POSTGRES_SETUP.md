# PostgreSQL Database Setup

This guide provides step-by-step instructions for setting up PostgreSQL for the Task Management application.

## Quick Start

### 1. Install PostgreSQL

**Linux (Ubuntu/Debian):**

```bash
sudo apt update
sudo apt install postgresql postgresql-contrib -y
sudo systemctl start postgresql
sudo systemctl enable postgresql
```

**macOS (with Homebrew):**

```bash
brew install postgresql
brew services start postgresql
```

**Windows:**

1. Download installer from [postgresql.org](https://www.postgresql.org/download/windows/)
2. Run the installer and follow the setup wizard
3. Note the password you set for the `postgres` user

### 2. Create Database

```bash
# Access PostgreSQL CLI
sudo -u postgres psql

# Create the database
CREATE DATABASE task_management;

# Create a user (if not using default postgres user)
CREATE USER task_user WITH PASSWORD 'secure_password';

# Grant privileges
ALTER ROLE task_user WITH CREATEDB;
GRANT ALL PRIVILEGES ON DATABASE task_management TO task_user;

# Exit psql
\q
```

### 3. Verify Installation

```bash
# Test connection
psql -U postgres -d task_management -h localhost

# You should see: psql (15.x)
# Type \q to exit
```

## Useful PostgreSQL Commands

```bash
# List all databases
\l

# Connect to a database
\c task_management

# List all tables
\dt

# Describe a table
\d tasks

# Exit psql
\q

# Backup database
pg_dump -U postgres task_management > backup.sql

# Restore database
psql -U postgres task_management < backup.sql
```

## Connection String

Use this in your PHP application:

```
pgsql:host=localhost;port=5432;dbname=task_management
```

With credentials:

-    **User:** postgres (or task_user)
-    **Password:** postgres (or your password)

## Troubleshooting

### PostgreSQL Service Not Running

```bash
# Linux
sudo systemctl start postgresql
sudo systemctl status postgresql

# macOS
brew services start postgresql
brew services list
```

### Permission Denied

```bash
# Check PostgreSQL logs
sudo tail -f /var/log/postgresql/postgresql*.log

# Reset postgres user password (Linux)
sudo -u postgres psql
ALTER USER postgres PASSWORD 'new_password';
```

### Cannot Connect

```bash
# Check if PostgreSQL is listening
sudo lsof -i :5432

# Try connecting with host explicitly
psql -h 127.0.0.1 -U postgres -d task_management
```

## Next Steps

1. Update `backend/config/database.php` with your credentials
2. Start the PHP development server
3. The database tables will be created automatically on first API call
