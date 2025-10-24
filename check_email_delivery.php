<?php
/**
 * Email Delivery Checker
 * 
 * This checks WHERE the emails are going and WHY you're not receiving them
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head>";
echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
.container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
h1 { color: #d60000; border-bottom: 3px solid #d60000; padding-bottom: 10px; }
h2 { color: #333; margin-top: 30px; border-left: 4px solid #d60000; padding-left: 10px; }
.success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; }
.error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; }
.warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 5px; margin: 10px 0; }
.info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0; }
pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; border: 1px solid #dee2e6; }
table { width: 100%; border-collapse: collapse; margin: 15px 0; }
table th { background: #d60000; color: white; padding: 12px; text-align: left; }
table td { padding: 10px; border-bottom: 1px solid #ddd; }
table tr:hover { background: #f8f9fa; }
.check { color: #28a745; font-weight: bold; }
.cross { color: #dc3545; font-weight: bold; }
.step { background: #e9ecef; padding: 10px 15px; margin: 10px 0; border-left: 4px solid #6c757d; }
</style>";
echo "</head><body><div class='container'>";

echo "<h1>📧 Email Delivery Diagnostic Report</h1>";

// Load dependencies
require_once 'includes/database/db_conn.php';
require_once 'includes/utils/EnvLoader.php';
\Mitsubishi\Utils\EnvLoader::load();

// Get customer 99 details
$stmt = $connect->prepare("
    SELECT a.Id, a.Email, a.Username, ci.firstname, ci.lastname, ci.mobile_number
    FROM accounts a
    INNER JOIN customer_information ci ON a.Id = ci.account_id
    WHERE a.Id = 99
");
$stmt->execute();
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    echo "<div class='error'><strong>❌ Customer ID 99 not found!</strong><br>Please create a customer with ID 99 first.</div>";
    echo "</div></body></html>";
    exit;
}

echo "<h2>👤 Customer Information</h2>";
echo "<table>";
echo "<tr><th>Field</th><th>Value</th></tr>";
echo "<tr><td>Customer ID</td><td>{$customer['Id']}</td></tr>";
echo "<tr><td>Name</td><td>{$customer['firstname']} {$customer['lastname']}</td></tr>";
echo "<tr><td>Email</td><td><strong>{$customer['Email']}</strong></td></tr>";
echo "<tr><td>Mobile</td><td>{$customer['mobile_number']}</td></tr>";
echo "</table>";

// Check 1: Email Configuration
echo "<h2>⚙️ Email Configuration</h2>";
$config = [
    'SMTP_HOST' => getenv('SMTP_HOST'),
    'SMTP_PORT' => getenv('SMTP_PORT'),
    'SMTP_ENCRYPTION' => getenv('SMTP_ENCRYPTION'),
    'GMAIL_EMAIL' => getenv('GMAIL_EMAIL'),
    'GMAIL_PASSWORD' => getenv('GMAIL_PASSWORD') ? '✓ Configured' : '✗ NOT SET',
    'GMAIL_FROM_EMAIL' => getenv('GMAIL_FROM_EMAIL'),
    'GMAIL_FROM_NAME' => getenv('GMAIL_FROM_NAME'),
];

echo "<table>";
echo "<tr><th>Setting</th><th>Value</th></tr>";
foreach ($config as $key => $value) {
    $status = !empty($value) && $value !== '✗ NOT SET' ? "<span class='check'>✓</span>" : "<span class='cross'>✗</span>";
    echo "<tr><td>$status $key</td><td>$value</td></tr>";
}
echo "</table>";

// Check 2: Recent notification logs
echo "<h2>📋 Recent Email Logs (Customer 99)</h2>";
try {
    $stmt = $connect->query("
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
        ORDER BY sent_at DESC
        LIMIT 10
    ");
    
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($logs) > 0) {
        echo "<table>";
        echo "<tr><th>Type</th><th>Email Status</th><th>SMS Status</th><th>Sent At</th><th>Error</th></tr>";
        foreach ($logs as $log) {
            $emailClass = $log['email_status'] === 'sent' ? 'check' : ($log['email_status'] === 'failed' ? 'cross' : '');
            $smsClass = $log['sms_status'] === 'sent' ? 'check' : ($log['sms_status'] === 'failed' ? 'cross' : '');
            echo "<tr>";
            echo "<td>{$log['notification_type']}</td>";
            echo "<td><span class='$emailClass'>{$log['email_status']}</span></td>";
            echo "<td><span class='$smsClass'>{$log['sms_status']}</span></td>";
            echo "<td>{$log['sent_at']}</td>";
            echo "<td>" . ($log['email_error'] ?: '-') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Count statuses
        $sentCount = 0;
        $failedCount = 0;
        foreach ($logs as $log) {
            if ($log['email_status'] === 'sent') $sentCount++;
            if ($log['email_status'] === 'failed') $failedCount++;
        }
        
        if ($sentCount > 0 && $failedCount === 0) {
            echo "<div class='warning'>";
            echo "<strong>⚠️ Emails show as 'sent' but you're not receiving them?</strong><br><br>";
            echo "This means the SMTP server accepted the emails, but they may be:<br>";
            echo "1. <strong>In your SPAM/JUNK folder</strong> ← Check this first!<br>";
            echo "2. Blocked by recipient's email provider<br>";
            echo "3. Delayed in delivery (can take 5-10 minutes)<br>";
            echo "4. Sender domain has poor reputation<br>";
            echo "</div>";
        }
        
    } else {
        echo "<div class='info'>No email logs found yet for customer 99.</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>Could not read logs: " . $e->getMessage() . "</div>";
}

// Check 3: Send a test email NOW
echo "<h2>🧪 Live Email Test</h2>";
echo "<div class='step'>Sending test email to: <strong>{$customer['Email']}</strong></div>";

try {
    require_once 'includes/backend/GmailMailer.php';
    $mailer = new \Mitsubishi\Backend\GmailMailer();
    
    $testSubject = "🔔 Test Email - " . date('H:i:s');
    $testBody = "
    <html>
    <body style='font-family: Arial, sans-serif; padding: 20px;'>
        <div style='background: linear-gradient(135deg, #d60000 0%, #b30000 100%); color: white; padding: 20px; border-radius: 8px;'>
            <h1 style='margin: 0;'>✅ Email System Working!</h1>
        </div>
        <div style='padding: 20px; background: #f8f9fa; margin-top: 20px; border-radius: 8px;'>
            <p>Hello {$customer['firstname']},</p>
            <p>This is a <strong>test email</strong> from the Mitsubishi notification system.</p>
            <p><strong>If you receive this email, the notification system is working correctly!</strong></p>
            <hr>
            <p style='color: #666; font-size: 12px;'>
                Sent at: " . date('Y-m-d H:i:s') . "<br>
                From: " . getenv('GMAIL_FROM_EMAIL') . "<br>
                To: {$customer['Email']}
            </p>
        </div>
    </body>
    </html>
    ";
    
    $result = $mailer->sendEmail($customer['Email'], $testSubject, $testBody);
    
    if ($result['success']) {
        echo "<div class='success'>";
        echo "<strong>✅ Test email sent successfully!</strong><br><br>";
        echo "Message ID: " . ($result['message_id'] ?? 'N/A') . "<br>";
        echo "Recipient: {$customer['Email']}<br>";
        echo "Subject: $testSubject<br>";
        echo "Sent at: " . date('Y-m-d H:i:s') . "<br><br>";
        echo "<strong>⏰ Wait 1-2 minutes, then check:</strong><br>";
        echo "1. Your inbox<br>";
        echo "2. <strong>SPAM/JUNK folder</strong> ← Most likely here!<br>";
        echo "3. Promotions tab (if using Gmail)<br>";
        echo "</div>";
    } else {
        echo "<div class='error'>";
        echo "<strong>❌ Test email failed!</strong><br><br>";
        echo "Error: " . ($result['error'] ?? 'Unknown error') . "<br>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>❌ Exception occurred!</strong><br><br>";
    echo "Error: " . $e->getMessage() . "<br>";
    echo "</div>";
}

// Troubleshooting guide
echo "<h2>🔍 Why Am I Not Receiving Emails?</h2>";

echo "<div class='info'>";
echo "<h3>Most Common Reasons (in order):</h3>";
echo "<ol>";
echo "<li><strong>Emails are in SPAM/JUNK folder</strong> (90% of cases)<br>";
echo "   → Check your spam folder NOW<br>";
echo "   → Mark as 'Not Spam' if found there<br>";
echo "   → Add no-reply@mitsubishiautoxpress.com to contacts</li><br>";

echo "<li><strong>Email provider is blocking the sender</strong><br>";
echo "   → New domains often get flagged<br>";
echo "   → Hostinger emails may have low reputation initially<br>";
echo "   → Try sending to a different email (Gmail, Yahoo)</li><br>";

echo "<li><strong>Delivery is delayed</strong><br>";
echo "   → Can take 5-10 minutes<br>";
echo "   → Check again in a few minutes</li><br>";

echo "<li><strong>Wrong email address</strong><br>";
echo "   → Verify: {$customer['Email']}<br>";
echo "   → Make sure it's correct and active</li><br>";

echo "<li><strong>SPF/DKIM not configured</strong><br>";
echo "   → Login to Hostinger cPanel<br>";
echo "   → Go to 'Email Deliverability'<br>";
echo "   → Fix any issues shown</li>";
echo "</ol>";
echo "</div>";

echo "<h2>✅ Next Steps</h2>";
echo "<div class='step'>";
echo "<strong>Step 1:</strong> Check your SPAM/JUNK folder for emails from no-reply@mitsubishiautoxpress.com<br><br>";
echo "<strong>Step 2:</strong> If found in spam, mark as 'Not Spam' and add to contacts<br><br>";
echo "<strong>Step 3:</strong> Try sending to a different email address (Gmail, Yahoo, Outlook)<br><br>";
echo "<strong>Step 4:</strong> Check Hostinger cPanel → Track Delivery to see server logs<br><br>";
echo "<strong>Step 5:</strong> If still not working, run: <a href='test_smtp_detailed.php'>test_smtp_detailed.php</a> for detailed SMTP debug<br>";
echo "</div>";

echo "<h2>🔧 Quick Fixes to Try</h2>";
echo "<div class='warning'>";
echo "<strong>Fix 1: Add to Safe Senders</strong><br>";
echo "Add 'no-reply@mitsubishiautoxpress.com' to your email contacts/safe senders list<br><br>";

echo "<strong>Fix 2: Check Email Deliverability in Hostinger</strong><br>";
echo "1. Login to Hostinger cPanel<br>";
echo "2. Go to 'Email' → 'Email Deliverability'<br>";
echo "3. Click 'Manage' next to mitsubishiautoxpress.com<br>";
echo "4. Fix any issues with SPF or DKIM records<br><br>";

echo "<strong>Fix 3: Test with Different Email Provider</strong><br>";
echo "Update customer 99's email to a Gmail or Yahoo address and test again<br>";
echo "</div>";

echo "</div></body></html>";

