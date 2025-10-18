# Gmail SMTP Configuration Guide

## 📧 Gmail SMTP Setup Complete!

Your Mitsubishi Dealership application has been configured to use Gmail SMTP for sending emails.

**✨ NO INSTALLATION REQUIRED!** Everything is included - just upload and use!

---

## ✅ What Has Been Configured

### 1. **Gmail Account Details**
- **Email Address**: mitsubishiautoxpress@gmail.com
- **Sender Name**: Mitsubishiautoxpress
- **SMTP Host**: smtp.gmail.com
- **SMTP Port**: 587 (TLS)
- **Encryption**: TLS

### 2. **Files Created/Updated**

#### New Files:
- ✅ `.env` - Contains Gmail credentials (NEVER commit to Git!)
- ✅ `.env.example` - Template for environment variables
- ✅ `includes/utils/EnvLoader.php` - Loads environment variables
- ✅ `includes/backend/GmailMailer.php` - Gmail SMTP mailer class
- ✅ `includes/phpmailer/PHPMailer.php` - PHPMailer library (included, no installation needed!)
- ✅ `tests/test_send_email.php` - Email testing script
- ✅ `.gitignore` - Updated to exclude sensitive files
- ✅ `GMAIL_SETUP_GUIDE.md` - This guide

#### Updated Files:
- ✅ `config/email_config.php` - Updated to use Gmail SMTP
- ✅ `api/send_email_api.php` - Updated to use GmailMailer class

---

## 🚀 Deployment Steps

### **Step 1: Upload Files to Your Server**

**✨ NO INSTALLATION NEEDED!** Just upload these files:

- `.env` - Your Gmail credentials
- `includes/utils/EnvLoader.php` - Environment loader
- `includes/backend/GmailMailer.php` - Gmail mailer
- `includes/phpmailer/PHPMailer.php` - PHPMailer library (already included!)
- `config/email_config.php` - Updated configuration
- `api/send_email_api.php` - Updated API
- `tests/test_send_email.php` - Test script

**That's it!** Everything is ready to use - no Composer, no installation, no dependencies!

---

## 🔐 Gmail Security Requirements

### **Your Gmail Account Must Have:**

1. **2-Step Verification Enabled** ✅
   - Go to: https://myaccount.google.com/security
   - Enable "2-Step Verification"

2. **App Password Generated** ✅
   - Your app password: `rkob ukdt awdq bjte`
   - This is already configured in the `.env` file
   - **IMPORTANT**: Never share this password or commit it to version control!

### **Gmail Sending Limits:**
- **Free Gmail Account**: 500 emails per day
- **Google Workspace**: 2,000 emails per day
- **Rate Limit**: ~100 emails per hour recommended

---

## 🧪 Testing the Email Configuration

### **Method 1: Using the Test Script**

1. Open `tests/test_send_email.php`
2. Update line 23 with your test email:
   ```php
   $test_recipient = 'your-email@example.com';
   ```
3. Run the test via browser:
   ```
   http://your-domain.com/tests/test_send_email.php
   ```
   Or via command line (if you have SSH access):
   ```bash
   php tests/test_send_email.php
   ```

### **Method 2: Using the Admin Panel**

1. Log in to the admin panel
2. Navigate to **Email Management**
3. Compose a test email
4. Send to your own email address
5. Check your inbox (and spam folder)

---

## 📝 How to Use Gmail SMTP in Your Code

### **Example 1: Simple Email**

```php
<?php
require_once 'includes/backend/GmailMailer.php';

$mailer = new \Mitsubishi\Backend\GmailMailer();

$result = $mailer->sendEmail(
    'recipient@example.com',
    'Test Subject',
    'This is the email body',
    ['priority' => 'normal']
);

if ($result['success']) {
    echo "Email sent successfully!";
} else {
    echo "Error: " . $result['error'];
}
?>
```

### **Example 2: Email with Options**

```php
<?php
$options = [
    'priority' => 'high',           // 'low', 'normal', 'high', 'urgent'
    'cc' => 'cc@example.com',       // Carbon copy
    'bcc' => 'bcc@example.com',     // Blind carbon copy
    'reply_to' => 'reply@example.com',
    'attachments' => [
        ['path' => '/path/to/file.pdf', 'name' => 'document.pdf']
    ]
];

$result = $mailer->sendEmail(
    'recipient@example.com',
    'Subject with Attachments',
    'Email body here',
    $options
);
?>
```

### **Example 3: Using the API Endpoint**

```javascript
// From your frontend JavaScript
const formData = new FormData();
formData.append('recipient', 'customer@example.com');
formData.append('subject', 'Welcome to Mitsubishi');
formData.append('message', 'Thank you for your interest!');
formData.append('email_type', 'customer_service');
formData.append('priority', 'normal');

fetch('/api/send_email_api.php', {
    method: 'POST',
    body: formData
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        console.log('Email sent!', data.email_id);
    } else {
        console.error('Error:', data.message);
    }
});
```

---

## 🔧 Troubleshooting

### **Problem: "Gmail SMTP credentials not configured"**
**Solution**: Check that `.env` file exists and contains correct credentials

### **Problem: "SMTP connection failed"**
**Solutions**:
1. Verify your Gmail app password is correct
2. Check that 2-Step Verification is enabled
3. Ensure port 587 is not blocked by firewall
4. Try using port 465 with SSL instead of TLS

### **Problem: "Authentication failed"**
**Solutions**:
1. Regenerate your Gmail App Password
2. Update the `.env` file with the new password
3. Make sure there are no extra spaces in the password

### **Problem: Emails going to spam**
**Solutions**:
1. Add SPF record to your domain DNS
2. Set up DKIM authentication
3. Use a verified domain email address
4. Avoid spam trigger words in subject/body

### **Problem: "Could not instantiate mail function"**
**Solution**: Check PHP mail configuration in `php.ini`

---

## 📊 Email Logging

All sent emails are logged in the `email_logs` database table with:
- Sender information
- Recipient
- Subject and message
- Email type and priority
- Timestamp
- Status (sent/failed)
- Error messages (if failed)

Query email logs:
```sql
SELECT * FROM email_logs ORDER BY sent_at DESC LIMIT 10;
```

---

## 🔒 Security Best Practices

1. **Never commit `.env` file** - It's already in `.gitignore`
2. **Use App Passwords** - Never use your actual Gmail password
3. **Rotate passwords regularly** - Generate new app passwords periodically
4. **Monitor sending limits** - Track daily email volume
5. **Validate email addresses** - Always validate before sending
6. **Sanitize content** - Prevent email injection attacks
7. **Use HTTPS** - Ensure secure transmission of credentials

---

## 📈 Monitoring & Maintenance

### **Check Email Sending Status**
```php
// Test SMTP connection
$mailer = new \Mitsubishi\Backend\GmailMailer();
$test = $mailer->testConnection();
echo $test['message'];
```

### **Monitor Daily Sending Volume**
```sql
SELECT DATE(sent_at) as date, COUNT(*) as emails_sent 
FROM email_logs 
WHERE status = 'sent' 
GROUP BY DATE(sent_at) 
ORDER BY date DESC;
```

### **Check Failed Emails**
```sql
SELECT * FROM email_logs 
WHERE status = 'failed' 
ORDER BY sent_at DESC;
```

---

## 🆘 Support & Resources

- **PHPMailer Documentation**: https://github.com/PHPMailer/PHPMailer
- **Gmail SMTP Settings**: https://support.google.com/mail/answer/7126229
- **Google App Passwords**: https://support.google.com/accounts/answer/185833
- **Gmail Sending Limits**: https://support.google.com/mail/answer/22839

---

## 📞 Contact

For issues or questions about this configuration:
- Email: mitsubishiautoxpress@gmail.com
- Check error logs: `error_log` in PHP error log file

---

**Last Updated**: 2025-10-17
**Configuration Status**: ✅ Ready for Testing (Pending PHPMailer Installation)

