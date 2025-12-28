# SMTP Mailer Service Configuration Guide

## Overview

The TaskFlow application now uses a backend SMTP mailer service for sending emails. This replaces the Resend API integration and provides more control over email sending.

## Features

-    📧 **Password Reset Emails** - Send reset codes securely
-    🔔 **Task Reminder Emails** - Notify users of upcoming tasks
-    ⚠️ **Deadline Alert Emails** - Alert users when tasks are due
-    🔒 **Secure SMTP Connection** - Uses TLS encryption
-    📝 **Professional HTML Templates** - Beautiful, responsive emails

## Supported SMTP Providers

You can use any SMTP provider:

-    **Gmail** (recommended for testing)
-    **SendGrid**
-    **Mailgun**
-    **AWS SES**
-    **Your own mail server**
-    **Any SMTP provider**

## Configuration

### Option 1: Using Environment Variables (Recommended)

Set these environment variables on your server:

```bash
export SMTP_HOST="smtp.gmail.com"
export SMTP_PORT="587"
export SMTP_USER="your-email@gmail.com"
export SMTP_PASS="your-app-password"
export SMTP_FROM_EMAIL="noreply@taskmanagement.com"
export SMTP_FROM_NAME="TaskFlow"
```

### Option 2: Direct Configuration in PHP

Edit `/backend/api/mailer.php` and update the constants:

```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-app-password');
define('SMTP_FROM_EMAIL', 'noreply@taskmanagement.com');
define('SMTP_FROM_NAME', 'TaskFlow');
```

## Gmail Setup (Step-by-Step)

### 1. Create a Gmail Account

-    Sign up for a Gmail account if you don't have one
-    Use a dedicated email for your app (e.g., `taskflow-noreply@gmail.com`)

### 2. Enable App Password

-    Go to [Google Account Security](https://myaccount.google.com/security)
-    Enable 2-factor authentication if not already enabled
-    Scroll to "App passwords"
-    Select "Mail" and "Windows Computer" (or your OS)
-    Google will generate a 16-character password

### 3. Update Configuration

```bash
export SMTP_HOST="smtp.gmail.com"
export SMTP_PORT="587"
export SMTP_USER="taskflow-noreply@gmail.com"
export SMTP_PASS="your-16-character-google-password"
export SMTP_FROM_EMAIL="taskflow-noreply@gmail.com"
export SMTP_FROM_NAME="TaskFlow"
```

## Other SMTP Providers

### SendGrid

```bash
export SMTP_HOST="smtp.sendgrid.net"
export SMTP_PORT="587"
export SMTP_USER="apikey"
export SMTP_PASS="your-sendgrid-api-key"
```

### Mailgun

```bash
export SMTP_HOST="smtp.mailgun.org"
export SMTP_PORT="587"
export SMTP_USER="postmaster@your-domain.mailgun.org"
export SMTP_PASS="your-mailgun-password"
```

### AWS SES

```bash
export SMTP_HOST="email-smtp.your-region.amazonaws.com"
export SMTP_PORT="587"
export SMTP_USER="your-aws-ses-username"
export SMTP_PASS="your-aws-ses-password"
```

## Testing the Configuration

### Test Endpoint

```bash
curl -X POST http://localhost:8000/backend/api/mailer.php?action=test
```

Response if configured:

```json
{
	"success": true,
	"message": "SMTP is configured",
	"status": "configured",
	"host": "smtp.gmail.com",
	"port": 587
}
```

### Send Test Email

```bash
curl -X POST http://localhost:8000/backend/api/mailer.php?action=send-password-reset \
  -H 'Content-Type: application/json' \
  -d '{
    "email": "test@example.com",
    "resetCode": "123456"
  }'
```

## API Endpoints

### Send Password Reset Email

```
POST /backend/api/mailer.php?action=send-password-reset
```

**Request:**

```json
{
	"email": "user@example.com",
	"resetCode": "123456"
}
```

### Send Task Reminder

```
POST /backend/api/mailer.php?action=send-reminder
```

**Request:**

```json
{
	"email": "user@example.com",
	"taskTitle": "Complete project",
	"reminderTime": "1 day before due"
}
```

### Send Deadline Alert

```
POST /backend/api/mailer.php?action=send-deadline
```

**Request:**

```json
{
	"email": "user@example.com",
	"taskTitle": "Complete project",
	"dueDate": "2025-12-31"
}
```

## Troubleshooting

### Email Not Sending

1. **Check SMTP credentials** - Verify username and password
2. **Check firewall** - Port 587 should be open outbound
3. **View error logs** - Check `/var/log/mail.log` or similar
4. **Test connection** - Use telnet to verify SMTP port is reachable:
     ```bash
     telnet smtp.gmail.com 587
     ```

### Gmail Specific Issues

-    **"Invalid credentials"** - Check that App Password (not regular password) is used
-    **"Less secure apps"** - This error is outdated; use App Password instead
-    **2FA not enabled** - Google requires 2FA to generate App Password

### PHP Errors

-    **"Failed to connect"** - SMTP host/port is incorrect
-    **"STARTTLS failed"** - TLS protocol issue; check provider supports TLS
-    **"Authentication failed"** - Username/password incorrect

## Production Considerations

1. **Use environment variables** - Never hardcode credentials in the file
2. **Use dedicated email** - Create separate email for your app
3. **Monitor deliverability** - Check email bounce rates
4. **Add rate limiting** - Prevent email spam abuse
5. **Log all emails** - Store email records in database for audit trail
6. **Use TLS encryption** - Only use port 587 (TLS) not 25 (plain)

## Email Log Storage (Optional)

To track sent emails, add this to `mailer.php`:

```php
function logEmail($toEmail, $subject, $action) {
    $logEntry = [
        'to' => $toEmail,
        'subject' => $subject,
        'action' => $action,
        'timestamp' => date('Y-m-d H:i:s')
    ];

    // Log to database or file
    error_log(json_encode($logEntry));
}
```

## Migration from Resend API

The frontend has been updated to use the SMTP mailer instead of Resend API:

1. ✅ Removed all Resend API references
2. ✅ Updated frontend to call `/backend/api/mailer.php`
3. ✅ Email functions work identically to users
4. ✅ All HTML templates preserved

No changes needed to the rest of the application!

---

**Questions or issues?** Check the logs and verify SMTP credentials are correct.
