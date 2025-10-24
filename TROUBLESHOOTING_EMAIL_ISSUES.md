# Email Notification Troubleshooting Guide

## Issue: SMS works but Email doesn't send

### Quick Diagnosis Steps

#### Step 1: Run the Test Script
Open in browser: `http://localhost/Mitsubishi/test_email_notification.php`

This will test:
- ✅ Database connection
- ✅ Email configuration
- ✅ GmailMailer functionality
- ✅ NotificationService
- ✅ Recent notification logs

---

#### Step 2: Check Notification Logs (via phpMyAdmin)

Run this query to see what errors are being logged:

```sql
SELECT 
    id,
    customer_id,
    notification_type,
    email_status,
    email_recipient,
    email_error,
    sms_status,
    sms_recipient,
    sms_error,
    sent_at
FROM notification_logs
ORDER BY sent_at DESC
LIMIT 10;
```

**Look for:**
- `email_status = 'failed'` - Email failed to send
- `email_error` column - Contains the error message
- `email_status = 'skipped'` - Customer has no email address

---

#### Step 3: Check Email Configuration

Verify your `.env` file has correct settings:

```env
SMTP_HOST=smtp.hostinger.com
SMTP_PORT=465
SMTP_ENCRYPTION=ssl
GMAIL_EMAIL=no-reply@mitsubishiautoxpress.com
GMAIL_PASSWORD=sshKey123.
GMAIL_FROM_EMAIL=no-reply@mitsubishiautoxpress.com
GMAIL_FROM_NAME=Mitsubishi Motors San Pablo
```

**Common Issues:**
- ❌ Wrong SMTP host
- ❌ Wrong port (should be 465 for SSL or 587 for TLS)
- ❌ Wrong encryption (should be 'ssl' for port 465)
- ❌ Incorrect email/password
- ❌ Email account not configured for SMTP access

---

#### Step 4: Test Email Manually

Create a simple test file `test_simple_email.php`:

```php
<?php
require_once 'includes/backend/GmailMailer.php';

try {
    $mailer = new \Mitsubishi\Backend\GmailMailer();
    
    $result = $mailer->sendEmail(
        'your-email@example.com',  // Change this to your email
        'Test Email',
        '<h1>Hello!</h1><p>This is a test email.</p>'
    );
    
    echo "<pre>";
    print_r($result);
    echo "</pre>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

Run it: `http://localhost/Mitsubishi/test_simple_email.php`

---

## Common Email Issues & Solutions

### Issue 1: SMTP Authentication Failed

**Error:** `SMTP Error: Could not authenticate`

**Solutions:**
1. Check if email/password are correct in `.env`
2. For Hostinger, make sure you created the email account in cPanel
3. Check if "SMTP access" is enabled for the email account
4. Try using the full email address as username

---

### Issue 2: Connection Timeout

**Error:** `SMTP connect() failed` or `Connection timed out`

**Solutions:**
1. Check if port 465 (SSL) or 587 (TLS) is open
2. Try switching between SSL (port 465) and TLS (port 587):
   ```env
   # For SSL
   SMTP_PORT=465
   SMTP_ENCRYPTION=ssl
   
   # OR for TLS
   SMTP_PORT=587
   SMTP_ENCRYPTION=tls
   ```
3. Check firewall settings
4. Contact hosting provider to ensure SMTP is allowed

---

### Issue 3: Email Sent but Not Received

**Symptoms:** `email_status = 'sent'` in logs, but customer doesn't receive email

**Solutions:**
1. Check spam/junk folder
2. Check email server logs in Hostinger cPanel
3. Verify recipient email address is correct
4. Check if sender domain has SPF/DKIM records configured
5. Try sending to a different email address (Gmail, Yahoo, etc.)

---

### Issue 4: Customer Has No Email

**Symptoms:** `email_status = 'skipped'`

**Solution:**
Check if customer has email in database:

```sql
SELECT 
    a.Id,
    a.Email,
    ci.firstname,
    ci.lastname
FROM accounts a
INNER JOIN customer_information ci ON a.Id = ci.account_id
WHERE a.Role = 'Customer'
AND (a.Email IS NULL OR a.Email = '');
```

Update customer email if missing.

---

### Issue 5: Template Loading Error

**Error:** `Call to undefined function getLoanApprovalEmailTemplate()`

**Solution:**
Check if email template files exist:
- `includes/email_templates/notifications/loan_approval.php`
- `includes/email_templates/notifications/loan_rejection.php`
- `includes/email_templates/notifications/test_drive_approval.php`
- `includes/email_templates/notifications/test_drive_rejection.php`
- `includes/email_templates/notifications/payment_confirmation.php`
- `includes/email_templates/notifications/payment_rejection.php`
- `includes/email_templates/notifications/payment_reminder.php`

---

## Enable Debug Mode

To see detailed SMTP debug output, edit `includes/backend/GmailMailer.php`:

**Line 74:** Change from:
```php
$this->mailer->SMTPDebug = SMTP::DEBUG_OFF;
```

To:
```php
$this->mailer->SMTPDebug = SMTP::DEBUG_SERVER;
```

This will show detailed SMTP conversation in error logs.

**Remember to turn it back to `DEBUG_OFF` after troubleshooting!**

---

## Check PHP Error Logs

**XAMPP:** Check `C:\xampp\apache\logs\error.log`

**Hostinger:** Check error logs in cPanel → Error Logs

Look for lines containing:
- `Email send error:`
- `GmailMailer Send Error:`
- `Loan approval notification error:`
- `Payment confirmation notification error:`

---

## Test Hostinger Email Settings

If using Hostinger, verify in cPanel:

1. **Email Accounts** → Check if `no-reply@mitsubishiautoxpress.com` exists
2. **Email Deliverability** → Check SPF and DKIM records
3. **Email Routing** → Should be set to "Remote Mail Exchanger"
4. **Track Delivery** → Check if emails are being sent

---

## Quick Fix Checklist

- [ ] Created `notification_logs` table
- [ ] Verified `.env` file has correct SMTP settings
- [ ] Tested email account login in webmail
- [ ] Ran `test_email_notification.php` script
- [ ] Checked `notification_logs` table for errors
- [ ] Verified customer has valid email address
- [ ] Checked spam folder
- [ ] Enabled SMTP debug mode temporarily
- [ ] Checked PHP error logs
- [ ] Tested with different recipient email address

---

## Still Not Working?

### Get Detailed Error Information

Run this query to see the exact error:

```sql
SELECT 
    notification_type,
    email_error,
    COUNT(*) as error_count
FROM notification_logs
WHERE email_status = 'failed'
GROUP BY notification_type, email_error
ORDER BY error_count DESC;
```

### Contact Support

If emails still don't work after trying all solutions:

1. **Hostinger Support:** Ask them to verify SMTP is working for your email account
2. **Check Quota:** Ensure you haven't exceeded email sending limits
3. **Try Different Email Provider:** Test with Gmail SMTP as alternative

---

## Alternative: Use Gmail SMTP (Temporary Test)

If Hostinger SMTP doesn't work, try Gmail for testing:

```env
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_ENCRYPTION=tls
GMAIL_EMAIL=your-gmail@gmail.com
GMAIL_PASSWORD=your-app-password  # Use App Password, not regular password
GMAIL_FROM_EMAIL=your-gmail@gmail.com
GMAIL_FROM_NAME=Mitsubishi Motors
```

**Note:** You need to create an App Password in Gmail:
1. Go to Google Account → Security
2. Enable 2-Step Verification
3. Generate App Password
4. Use that password in `.env`

---

## Success Indicators

When emails are working correctly, you should see:

1. **In notification_logs table:**
   ```
   email_status = 'sent'
   email_error = NULL
   ```

2. **In test script output:**
   ```
   ✅ Test email sent successfully!
   Message ID: <some-id@mitsubishiautoxpress.com>
   ```

3. **Customer receives email** in inbox (or spam folder)

4. **PHP error logs** show no email-related errors

