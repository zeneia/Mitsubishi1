# Notification System - Deployment Checklist

## Pre-Deployment

- [ ] Review all code changes
- [ ] Verify `.env` file has correct email and SMS credentials
- [ ] Backup database before creating new table
- [ ] Test on staging/development environment first

---

## Database Setup

- [ ] Run `includes/database/setup_notification_logs.php` to create table
- [ ] Verify table created: `SHOW TABLES LIKE 'notification_logs';`
- [ ] Check table structure: `DESCRIBE notification_logs;`
- [ ] Verify foreign key constraint to `accounts` table

---

## File Deployment

### New Files to Upload:
- [ ] `includes/services/NotificationService.php`
- [ ] `includes/database/create_notification_logs_table.sql`
- [ ] `includes/database/setup_notification_logs.php`
- [ ] `includes/sms_templates/templates.php`
- [ ] `includes/email_templates/notifications/loan_approval.php`
- [ ] `includes/email_templates/notifications/loan_rejection.php`
- [ ] `includes/email_templates/notifications/test_drive_approval.php`
- [ ] `includes/email_templates/notifications/test_drive_rejection.php`
- [ ] `includes/email_templates/notifications/payment_confirmation.php`
- [ ] `includes/email_templates/notifications/payment_rejection.php`
- [ ] `includes/email_templates/notifications/payment_reminder.php`
- [ ] `includes/cron/payment_reminder_cron.php`
- [ ] `api/trigger_payment_reminders.php`

### Modified Files to Upload:
- [ ] `api/loan-applications.php`
- [ ] `pages/test/test_drive_management.php`
- [ ] `includes/api/payment_approval_api.php`
- [ ] `includes/backend/payment_backend.php`

### Documentation Files (optional):
- [ ] `NOTIFICATION_SYSTEM_IMPLEMENTATION.md`
- [ ] `DEPLOYMENT_CHECKLIST.md`
- [ ] `DEVELOPER_QUICK_REFERENCE.md`

---

## Configuration Verification

- [ ] Verify SMTP settings in `.env`:
  ```
  SMTP_HOST=smtp.hostinger.com
  SMTP_PORT=465
  SMTP_ENCRYPTION=ssl
  GMAIL_EMAIL=no-reply@mitsubishiautoxpress.com
  GMAIL_PASSWORD=<password>
  ```

- [ ] Verify PhilSMS settings in `config/philsms.php`:
  ```php
  'api_token' => '<your_token>',
  'default_sender_id' => 'Mitsubishi'
  ```

- [ ] Test email sending manually:
  ```php
  require_once 'includes/backend/GmailMailer.php';
  $mailer = new \Mitsubishi\Backend\GmailMailer();
  $mailer->sendEmail('test@example.com', 'Test', 'Test message');
  ```

- [ ] Test SMS sending manually:
  ```php
  require_once 'includes/backend/PhilSmsSender.php';
  PhilSmsSender::sendSms('+639123456789', 'Test message');
  ```

---

## Functional Testing

### Test Loan Notifications:
- [ ] Login as Sales Agent
- [ ] Approve a loan application
- [ ] Verify customer receives email
- [ ] Verify customer receives SMS
- [ ] Check `notification_logs` table for entry
- [ ] Reject a loan application
- [ ] Verify rejection email and SMS sent

### Test Test Drive Notifications:
- [ ] Login as Sales Agent
- [ ] Approve a test drive request
- [ ] Verify email contains gate pass number
- [ ] Verify SMS contains gate pass number
- [ ] Check `notification_logs` table
- [ ] Reject a test drive request
- [ ] Verify rejection notifications sent

### Test Payment Notifications:
- [ ] Login as Sales Agent or Admin
- [ ] Approve a pending payment
- [ ] Verify confirmation email and SMS
- [ ] Check `notification_logs` table
- [ ] Reject a payment
- [ ] Verify rejection email and SMS with reason

### Test Payment Reminders:
- [ ] Manually trigger reminders:
  ```bash
  curl -X POST https://your-domain.com/api/trigger_payment_reminders.php \
    -H "X-Cron-Key: mitsubishi_cron_2024"
  ```
- [ ] Check response JSON
- [ ] Verify reminders sent for due payments
- [ ] Check `logs/payment_reminders.log`
- [ ] Verify no duplicate reminders sent on same day

---

## Cron Job Setup (Production)

### For cPanel/Hostinger:
- [ ] Login to cPanel
- [ ] Go to "Cron Jobs"
- [ ] Add new cron job:
  - **Minute:** 0
  - **Hour:** 9
  - **Day:** *
  - **Month:** *
  - **Weekday:** *
  - **Command:** `php /home/u205309581/public_html/includes/cron/payment_reminder_cron.php`
- [ ] Save cron job
- [ ] Wait for next scheduled run or trigger manually
- [ ] Check `logs/payment_reminders.log` for execution

### Alternative: Webhook/External Cron Service:
- [ ] Use service like cron-job.org or EasyCron
- [ ] Set URL: `https://your-domain.com/api/trigger_payment_reminders.php`
- [ ] Add header: `X-Cron-Key: mitsubishi_cron_2024`
- [ ] Schedule: Daily at 9:00 AM (Asia/Manila timezone)

---

## Monitoring Setup

### Create Logs Directory:
- [ ] Create `logs/` directory if not exists
- [ ] Set permissions: `chmod 755 logs/`
- [ ] Create `.htaccess` in logs to prevent web access:
  ```apache
  Deny from all
  ```

### Database Monitoring Queries:
- [ ] Save these queries for regular monitoring:

**Recent Notifications:**
```sql
SELECT * FROM notification_logs 
ORDER BY sent_at DESC 
LIMIT 50;
```

**Failed Notifications:**
```sql
SELECT * FROM notification_logs 
WHERE email_status = 'failed' OR sms_status = 'failed'
ORDER BY sent_at DESC;
```

**Daily Statistics:**
```sql
SELECT 
    DATE(sent_at) as date,
    notification_type,
    COUNT(*) as total,
    SUM(CASE WHEN email_status = 'sent' THEN 1 ELSE 0 END) as email_sent,
    SUM(CASE WHEN sms_status = 'sent' THEN 1 ELSE 0 END) as sms_sent
FROM notification_logs
WHERE sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY DATE(sent_at), notification_type
ORDER BY date DESC, notification_type;
```

---

## Post-Deployment Verification

### Day 1:
- [ ] Monitor error logs for any issues
- [ ] Check first cron job execution
- [ ] Verify at least one notification sent successfully
- [ ] Check database logs for entries

### Week 1:
- [ ] Review notification success rate
- [ ] Check for any failed notifications
- [ ] Verify payment reminders running daily
- [ ] Monitor customer feedback

### Month 1:
- [ ] Analyze notification statistics
- [ ] Review and optimize templates if needed
- [ ] Check SMS and email credit usage
- [ ] Gather user feedback

---

## Rollback Plan (If Issues Occur)

### Quick Rollback:
1. [ ] Restore modified files from backup:
   - `api/loan-applications.php`
   - `pages/test/test_drive_management.php`
   - `includes/api/payment_approval_api.php`
   - `includes/backend/payment_backend.php`

2. [ ] Disable cron job temporarily

3. [ ] Keep `notification_logs` table (data is valuable)

4. [ ] Investigate issues in development environment

### Partial Rollback (Keep Some Features):
- [ ] Comment out notification code in specific files
- [ ] Keep database table for future use
- [ ] Disable only problematic notification types

---

## Success Criteria

- [ ] ✅ All notification types working (11 types)
- [ ] ✅ Email delivery rate > 95%
- [ ] ✅ SMS delivery rate > 95%
- [ ] ✅ No errors in main workflows (loan, test drive, payment)
- [ ] ✅ Cron job running daily without errors
- [ ] ✅ Notifications logged in database
- [ ] ✅ No duplicate notifications sent
- [ ] ✅ Customer feedback is positive

---

## Contact Information

**Technical Support:**
- Developer: [Your Name]
- Email: [Your Email]
- Phone: [Your Phone]

**Emergency Contacts:**
- Database Admin: [Name/Contact]
- Server Admin: [Name/Contact]
- SMS Provider Support: PhilSMS

---

## Notes

- All notifications use graceful error handling - they won't break main operations
- Failed notifications are logged for review
- System automatically retries on next cron run for payment reminders
- Email templates are responsive and mobile-friendly
- SMS messages are optimized for single-segment delivery (160 chars)

---

**Deployment Date:** _______________  
**Deployed By:** _______________  
**Verified By:** _______________  
**Status:** ⬜ Pending | ⬜ In Progress | ⬜ Complete | ⬜ Issues Found

