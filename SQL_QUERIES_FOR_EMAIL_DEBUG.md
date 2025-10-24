# SQL Queries for Email Debugging

Run these queries in phpMyAdmin to diagnose email issues.

## 1. Check Customer 99's Email Address

```sql
SELECT 
    a.Id,
    a.Email,
    a.Username,
    ci.firstname,
    ci.lastname,
    ci.mobile_number
FROM accounts a
INNER JOIN customer_information ci ON a.Id = ci.account_id
WHERE a.Id = 99;
```

**What to check:**
- Is the email address correct?
- Is it a real, active email address?
- Can you access this email inbox?

---

## 2. Check All Email Logs for Customer 99

```sql
SELECT 
    id,
    notification_type,
    email_recipient,
    email_status,
    email_subject,
    email_error,
    sms_status,
    sent_at
FROM notification_logs
WHERE customer_id = 99
ORDER BY sent_at DESC;
```

**What to look for:**
- `email_status = 'sent'` → Email was sent successfully to SMTP server
- `email_status = 'failed'` → Email failed, check `email_error` column
- `email_status = 'skipped'` → Customer has no email address

---

## 3. Count Email Statuses

```sql
SELECT 
    email_status,
    COUNT(*) as count
FROM notification_logs
WHERE customer_id = 99
GROUP BY email_status;
```

**Interpretation:**
- If all are 'sent' but you don't receive them → Check spam folder
- If all are 'failed' → Check `email_error` for the reason
- If all are 'skipped' → Customer has no email address

---

## 4. Get Latest Email Error

```sql
SELECT 
    notification_type,
    email_error,
    sent_at
FROM notification_logs
WHERE customer_id = 99
AND email_status = 'failed'
ORDER BY sent_at DESC
LIMIT 1;
```

**Common errors:**
- "SMTP authentication failed" → Wrong email/password in .env
- "SMTP connection failed" → Port blocked or wrong host
- "No recipients specified" → Customer has no email

---

## 5. Check All Recent Emails (All Customers)

```sql
SELECT 
    nl.id,
    nl.customer_id,
    a.Email as customer_email,
    nl.notification_type,
    nl.email_status,
    nl.sms_status,
    nl.sent_at
FROM notification_logs nl
INNER JOIN accounts a ON nl.customer_id = a.Id
ORDER BY nl.sent_at DESC
LIMIT 20;
```

**What to check:**
- Are ANY emails being sent successfully?
- Is it just customer 99 or all customers?

---

## 6. Update Customer 99's Email (For Testing)

If you want to test with a different email address:

```sql
UPDATE accounts
SET Email = 'your-test-email@gmail.com'
WHERE Id = 99;
```

**Replace** `your-test-email@gmail.com` with an email you can access.

Then trigger a notification and check if you receive it.

---

## 7. Check if notification_logs Table Exists

```sql
SHOW TABLES LIKE 'notification_logs';
```

If this returns 0 rows, the table doesn't exist. Create it using the SQL provided earlier.

---

## 8. View Table Structure

```sql
DESCRIBE notification_logs;
```

Should show columns:
- id
- customer_id
- notification_type
- email_status
- email_recipient
- email_error
- sms_status
- etc.

---

## Common Scenarios & Solutions

### Scenario 1: email_status = 'sent' but not receiving

**Cause:** Email was sent to SMTP server successfully, but:
- Going to spam/junk folder (most common)
- Blocked by recipient's email provider
- Delayed in delivery

**Solution:**
1. Check spam/junk folder
2. Add sender to safe senders list
3. Try different email provider (Gmail, Yahoo)
4. Check Hostinger email deliverability settings

---

### Scenario 2: email_status = 'failed'

**Cause:** Email failed to send, check `email_error` column

**Common errors and fixes:**

| Error | Cause | Fix |
|-------|-------|-----|
| SMTP authentication failed | Wrong credentials | Check .env file |
| SMTP connection failed | Port blocked | Try port 587 instead of 465 |
| No recipients specified | No email address | Update customer email |
| Unknown email error | GmailMailer issue | Check PHP error logs |

---

### Scenario 3: email_status = 'skipped'

**Cause:** Customer has no email address in database

**Solution:**
```sql
UPDATE accounts
SET Email = 'customer@example.com'
WHERE Id = 99;
```

---

### Scenario 4: No logs at all

**Cause:** Notifications not being triggered

**Check:**
1. Is notification_logs table created?
2. Are you actually approving/rejecting loans/payments?
3. Check PHP error logs for exceptions

---

## Testing Workflow

### Step 1: Verify Customer Email
```sql
SELECT Email FROM accounts WHERE Id = 99;
```

### Step 2: Trigger a Notification
- Approve a loan for customer 99
- OR approve a payment for customer 99
- OR approve a test drive for customer 99

### Step 3: Check Logs Immediately
```sql
SELECT * FROM notification_logs 
WHERE customer_id = 99 
ORDER BY sent_at DESC 
LIMIT 1;
```

### Step 4: Interpret Results

**If email_status = 'sent':**
- ✅ System is working
- 📧 Check your spam folder
- ⏰ Wait 2-5 minutes for delivery

**If email_status = 'failed':**
- ❌ Check email_error column
- 🔧 Fix the issue shown
- 🔄 Try again

**If no log entry:**
- ❌ Notification not triggered
- 🔍 Check if NotificationService is integrated
- 📝 Check PHP error logs

---

## Quick Diagnostic Checklist

- [ ] notification_logs table exists
- [ ] Customer 99 has valid email address
- [ ] .env has correct SMTP settings
- [ ] Email shows as 'sent' in logs
- [ ] Checked spam/junk folder
- [ ] Tried different email address
- [ ] Checked Hostinger email deliverability
- [ ] Waited 5-10 minutes for delivery
- [ ] Added sender to safe senders list

---

## Still Not Working?

Run these diagnostic scripts:
1. `check_email_delivery.php` - Comprehensive check
2. `test_smtp_detailed.php` - Detailed SMTP debug
3. `test_email_notification.php` - Full system test

Check PHP error logs:
- XAMPP: `C:\xampp\apache\logs\error.log`
- Hostinger: cPanel → Error Logs

Contact Hostinger support if SMTP issues persist.

