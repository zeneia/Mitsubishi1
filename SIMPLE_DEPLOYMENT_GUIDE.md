# 🚀 Simple Deployment Guide - Gmail SMTP

## ✨ NO INSTALLATION REQUIRED!

Everything is ready to use - just upload and go! No Composer, no dependencies, no installation scripts.

---

## 📦 What's Included

All necessary files are already in your project:

✅ **PHPMailer** - Included in `includes/phpmailer/PHPMailer.php`
✅ **Gmail Configuration** - Set in `.env` file
✅ **Email Mailer** - Ready in `includes/backend/GmailMailer.php`
✅ **API Updated** - `api/send_email_api.php` uses Gmail SMTP
✅ **Test Script** - `tests/test_send_email.php` ready to test

---

## 🎯 Quick Deployment (3 Steps)

### **Step 1: Upload Files to Linux Server**

Upload your entire project to your Linux server using:
- FTP/SFTP
- Git push
- rsync
- cPanel File Manager
- Or any method you prefer

**Important files to upload:**
```
.env                                    (Gmail credentials)
includes/utils/EnvLoader.php           (Environment loader)
includes/backend/GmailMailer.php       (Gmail mailer)
includes/phpmailer/PHPMailer.php       (PHPMailer library)
config/email_config.php                (Updated config)
api/send_email_api.php                 (Updated API)
tests/test_send_email.php              (Test script)
```

### **Step 2: Set File Permissions (Linux)**

If you're on Linux, set proper permissions:

```bash
# Make sure PHP can read the files
chmod 644 .env
chmod 644 includes/utils/EnvLoader.php
chmod 644 includes/backend/GmailMailer.php
chmod 644 includes/phpmailer/PHPMailer.php
chmod 644 config/email_config.php
chmod 644 api/send_email_api.php
chmod 644 tests/test_send_email.php

# Make directories executable
chmod 755 includes/
chmod 755 includes/utils/
chmod 755 includes/backend/
chmod 755 includes/phpmailer/
chmod 755 config/
chmod 755 api/
chmod 755 tests/
```

Or simply:
```bash
find . -type f -name "*.php" -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;
```

### **Step 3: Test Email Sending**

Visit the test script in your browser:
```
https://your-domain.com/tests/test_send_email.php
```

Or via SSH:
```bash
php tests/test_send_email.php
```

**That's it!** ✅

---

## 🔐 Security Check

Make sure `.env` is NOT accessible via web browser:

1. Try visiting: `https://your-domain.com/.env`
2. You should get a 403 Forbidden or 404 Not Found error
3. If you can see the file contents, add this to your `.htaccess`:

```apache
<Files ".env">
    Order allow,deny
    Deny from all
</Files>
```

---

## 📧 Gmail Configuration

Your Gmail SMTP is already configured with:

- **Email**: mitsubishiautoxpress@gmail.com
- **App Password**: rkob ukdt awdq bjte
- **SMTP Host**: smtp.gmail.com
- **SMTP Port**: 587 (TLS)
- **Encryption**: TLS

**All stored securely in `.env` file!**

---

## 🧪 Testing

### **Test 1: Run Test Script**

```bash
php tests/test_send_email.php
```

Expected output:
```
=== Gmail SMTP Email Send Test ===

Configuration:
  From: mitsubishiautoxpress@gmail.com
  To: mitsubishiautoxpress@gmail.com

Sending test email...

✓ SUCCESS! Email sent successfully!

Details:
  Message ID: ...
  Status: Sent

Please check your inbox at: mitsubishiautoxpress@gmail.com
```

### **Test 2: Send from Admin Panel**

1. Log in to your admin panel
2. Go to Email Management
3. Send a test email
4. Check if it arrives

### **Test 3: Check Database Logs**

```sql
SELECT * FROM email_logs ORDER BY sent_at DESC LIMIT 5;
```

You should see your test emails logged.

---

## 💻 Usage in Your Code

### **Simple Example:**

```php
<?php
require_once 'includes/backend/GmailMailer.php';

$mailer = new \Mitsubishi\Backend\GmailMailer();

$result = $mailer->sendEmail(
    'customer@example.com',
    'Welcome!',
    'Thank you for your interest in Mitsubishi Motors!',
    ['priority' => 'normal']
);

if ($result['success']) {
    echo "Email sent!";
} else {
    echo "Error: " . $result['error'];
}
?>
```

### **With Attachments:**

```php
<?php
$options = [
    'priority' => 'high',
    'cc' => 'manager@example.com',
    'attachments' => [
        ['path' => '/path/to/invoice.pdf', 'name' => 'Invoice.pdf']
    ]
];

$result = $mailer->sendEmail(
    'customer@example.com',
    'Your Invoice',
    'Please find attached your invoice.',
    $options
);
?>
```

---

## 🔧 Troubleshooting

### **Problem: "Class not found"**
**Solution**: Make sure you uploaded all files, especially:
- `includes/phpmailer/PHPMailer.php`
- `includes/backend/GmailMailer.php`
- `includes/utils/EnvLoader.php`

### **Problem: "SMTP connection failed"**
**Solutions**:
1. Check `.env` file has correct Gmail credentials
2. Verify port 587 is not blocked on your server
3. Check your server can make outbound connections
4. Try using port 465 with SSL instead

### **Problem: "Authentication failed"**
**Solutions**:
1. Verify Gmail app password in `.env` is correct
2. Make sure 2-Step Verification is enabled on Gmail
3. Regenerate app password if needed

### **Problem: ".env file not found"**
**Solution**: Make sure you uploaded the `.env` file to your server

### **Problem: "Permission denied"**
**Solution**: Set proper file permissions (see Step 2 above)

---

## 📊 File Structure

```
Mitsubishi/
├── .env                                 ← Gmail credentials
├── .env.example                         ← Template
├── includes/
│   ├── utils/
│   │   └── EnvLoader.php               ← Loads .env
│   ├── backend/
│   │   └── GmailMailer.php             ← Gmail mailer
│   └── phpmailer/
│       └── PHPMailer.php               ← PHPMailer library
├── config/
│   └── email_config.php                ← Email config
├── api/
│   └── send_email_api.php              ← Email API
└── tests/
    └── test_send_email.php             ← Test script
```

---

## ✅ Deployment Checklist

- [ ] Upload all files to server
- [ ] Set file permissions (Linux)
- [ ] Verify `.env` file is uploaded
- [ ] Verify `.env` is not web-accessible
- [ ] Run test script
- [ ] Check email received
- [ ] Test from admin panel
- [ ] Verify database logging
- [ ] Check error logs

---

## 🎉 You're Done!

Your Gmail SMTP is configured and ready to use!

**No installation, no Composer, no dependencies - just pure PHP!**

---

## 📚 Additional Documentation

- **GMAIL_SETUP_GUIDE.md** - Detailed setup guide
- **EMAIL_CONFIGURATION_SUMMARY.md** - Quick reference
- **.env.example** - Environment template

---

## 📞 Support

**Email**: mitsubishiautoxpress@gmail.com

**Common Issues**:
- Check PHP error logs: `/var/log/php_errors.log` or similar
- Enable error reporting temporarily to debug
- Verify all files uploaded correctly
- Check file permissions on Linux

---

**Last Updated**: 2025-10-17
**Status**: ✅ Ready for Deployment
**Platform**: Linux & Windows Compatible

