# NeonDB Setup Guide

## Overview

NeonDB is a managed PostgreSQL service perfect for hosting your Task Management backend. It's fully compatible with the backend code and requires no changes to your API logic.

## Step-by-Step Setup

### 1. Create Your NeonDB Account

1. Go to [neon.tech](https://neon.tech)
2. Click "Sign Up"
3. Create a free account
4. Verify your email

### 2. Create a PostgreSQL Database

1. In the Neon dashboard, click "Create Project"
2. Enter project name: `task-management`
3. Select region: `East US 2` (for best latency)
4. Click "Create Project"

### 3. Get Your Connection String

1. In your project dashboard, you'll see connection strings
2. Look for the **Connection string** option
3. You should see something like:
     ```
     postgresql://neondb_owner:npg_IKyCX2SeJY9r@ep-square-art-a8zobyl9-pooler.eastus2.azure.neon.tech/neondb?sslmode=require&channel_binding=require
     ```

### 4. Extract Connection Details

From your connection string, extract:

- **DB_HOST**: `ep-square-art-a8zobyl9-pooler.eastus2.azure.neon.tech`
- **DB_PORT**: `5432` (default)
- **DB_NAME**: `neondb`
- **DB_USER**: `neondb_owner`
- **DB_PASSWORD**: `npg_IKyCX2SeJY9r`
- **DB_SSL_MODE**: `require`

### 5. Update Backend Configuration

Create a `.env` file in the `backend/` directory:

```bash
cd /path/to/Task-Management/backend
cp .env.example .env
```

Edit `backend/.env` with your NeonDB credentials:

```
DB_HOST=ep-square-art-a8zobyl9-pooler.eastus2.azure.neon.tech
DB_PORT=5432
DB_NAME=neondb
DB_USER=neondb_owner
DB_PASSWORD=npg_IKyCX2SeJY9r
DB_SSL_MODE=require

APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-backend-url.com
FRONTEND_URL=https://task-management-neon-one.vercel.app/
```

### 6. Test Connection Locally

```bash
# Test with psql (if installed)
psql 'postgresql://neondb_owner:npg_IKyCX2SeJY9r@ep-square-art-a8zobyl9-pooler.eastus2.azure.neon.tech/neondb?sslmode=require'
```

Or test with your backend:

```bash
php -S localhost:8000
# Visit http://localhost:8000/backend/
```

### 7. Deploy Backend

You can deploy the backend to any PHP hosting:

**Popular options:**

- **Heroku** (free tier available)
- **Vercel** (with PHP support)
- **PythonAnywhere**
- **AWS** (Elastic Beanstalk)
- **DigitalOcean** (App Platform)
- **Render.com**
- **Railway.app**

## Configuration Files

### `.env` File Structure

```ini
# Database (NeonDB)
DB_HOST=your-neon-host
DB_PORT=5432
DB_NAME=your-database
DB_USER=your-user
DB_PASSWORD=your-password
DB_SSL_MODE=require

# App Settings
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-backend-url.com
FRONTEND_URL=https://your-frontend-url.com
```

### Backend Configuration

The `backend/config/database.php` now:

- ✅ Reads from `.env` file automatically
- ✅ Supports SSL/TLS connections (required for NeonDB)
- ✅ Includes SSL mode in connection string
- ✅ Works with local and remote PostgreSQL

## Connection Details

### Local PostgreSQL

```
DB_HOST=localhost
DB_PORT=5432
DB_SSL_MODE=prefer
```

### NeonDB (Remote)

```
DB_HOST=ep-*.neon.tech
DB_PORT=5432
DB_SSL_MODE=require
```

## Security Best Practices

### 1. Keep Credentials Secret

- Never commit `.env` to Git
- Add `.env` to `.gitignore`:
     ```
     echo ".env" >> .gitignore
     ```

### 2. Rotate Passwords

In NeonDB dashboard:

1. Go to "Connection Details"
2. Click "Reset password"
3. Update `.env` file

### 3. Environment Variables for Production

When deploying, set environment variables in your hosting platform instead of using `.env` file:

**Heroku:**

```bash
heroku config:set DB_HOST=your-host
heroku config:set DB_USER=your-user
heroku config:set DB_PASSWORD=your-password
```

**Vercel:**
Set in project settings → Environment Variables

**Railway:**
Set in Variables tab

**DigitalOcean App Platform:**
Set in App Spec → Services → Variables

## Troubleshooting

### SSL/TLS Connection Errors

**Error:** "SSL: sslv3 alert handshake failure"

**Solution:** Make sure `sslmode=require` is in your connection string

### Connection Timeout

**Error:** "could not connect to server: Connection timed out"

**Solutions:**

1. Check your NeonDB host is correct
2. Verify firewall allows port 5432
3. Test with psql first:
     ```bash
     psql 'postgresql://user:password@host:5432/dbname?sslmode=require'
     ```

### Authentication Failed

**Error:** "FATAL: password authentication failed"

**Solutions:**

1. Copy password exactly from NeonDB (no extra spaces)
2. Check for special characters that need escaping
3. Verify username is correct (usually `neondb_owner`)

### "Unknown database"

**Error:** Database doesn't exist

**Solutions:**

1. Check database name matches exactly
2. In NeonDB dashboard, confirm database exists
3. Create new database if needed

## Performance Tips

### 1. Use Connection Pooling

NeonDB includes a pooler automatically (the `-pooler` in hostname)

### 2. Enable Indexes

The backend automatically creates indexes on important fields

### 3. Monitor Usage

In NeonDB dashboard:

- Check "Storage" usage
- Monitor "Compute credits"
- Review "Recent queries"

### 4. Optimize Queries

The PHP backend uses prepared statements (already optimized)

## Scaling Your Backend

### When to Upgrade

- Storage approaching limit
- Frequent timeout errors
- High query latency

### NeonDB Tiers

- **Free**: Great for development/testing
- **Pro**: For production with guaranteed uptime
- **Custom**: For enterprise needs

## Backup & Recovery

### Automatic Backups

NeonDB automatically backs up your data daily

### Manual Backup

```bash
# Dump database
pg_dump 'postgresql://user:password@host:5432/dbname' > backup.sql

# Restore database
psql 'postgresql://user:password@host:5432/dbname' < backup.sql
```

## Development vs Production

### Development (Local)

```
DB_HOST=localhost
DB_PORT=5432
DB_NAME=task_management
DB_SSL_MODE=prefer
```

### Production (NeonDB)

```
DB_HOST=ep-*.neon.tech
DB_PORT=5432
DB_NAME=neondb
DB_SSL_MODE=require
```

## Environment Variables

The backend now supports environment variables through the `.env` file:

```php
// In database.php
if (file_exists(__DIR__ . '/../.env')) {
    $env = parse_ini_file(__DIR__ . '/../.env');
    foreach ($env as $key => $value) {
        define($key, $value);
    }
}
```

This allows you to:

1. Use local `.env` file for development
2. Set environment variables on production server
3. Keep credentials out of version control

## Deployment Checklist

- [ ] Create NeonDB account
- [ ] Create PostgreSQL project
- [ ] Get connection string
- [ ] Create `.env` file with credentials
- [ ] Update `FRONTEND_URL` in `.env`
- [ ] Test connection locally
- [ ] Deploy backend to hosting
- [ ] Set environment variables on server
- [ ] Test from frontend
- [ ] Monitor database usage

## Next Steps

1. **Local Testing:** Run `php -S localhost:8000` with NeonDB
2. **Deploy Backend:** Choose hosting platform
3. **Update Frontend:** Make sure FRONTEND_URL is correct
4. **Test Integration:** Test API calls from frontend
5. **Monitor:** Watch NeonDB dashboard for usage

## Support

For NeonDB issues:

- Visit [neon.tech docs](https://neon.tech/docs)
- Check NeonDB dashboard for status
- Review connection details

For backend issues:

- Check `FAQ_TROUBLESHOOTING.md`
- Review `BACKEND_SETUP.md`
- Check PHP error logs

---

**Your backend is now connected to NeonDB!** [rocket]
