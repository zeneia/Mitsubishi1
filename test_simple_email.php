<?php
/**
 * Simple Email Test
 * 
 * Tests basic email sending without templates or database
 * This isolates whether the issue is with SMTP or with the notification system
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Simple Email Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #d60000; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; border-radius: 5px; margin: 20px 0; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; border: 1px solid #dee2e6; }
        input[type="email"] { width: 100%; padding: 10px; font-size: 16px; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #d60000; color: white; padding: 12px 30px; border: none; border-radius: 4px; font-size: 16px; cursor: pointer; margin-top: 10px; }
        button:hover { background: #b30000; }
        label { display: block; margin-top: 15px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📧 Simple Email Test</h1>
        
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_email'])) {
            $testEmail = filter_var($_POST['test_email'], FILTER_VALIDATE_EMAIL);
            
            if (!$testEmail) {
                echo "<div class='error'><strong>❌ Invalid email address!</strong><br>Please enter a valid email.</div>";
            } else {
                echo "<div class='info'><strong>🔄 Sending test email to: $testEmail</strong></div>";
                
                try {
                    require_once 'includes/backend/GmailMailer.php';
                    $mailer = new \Mitsubishi\Backend\GmailMailer();
                    
                    $subject = "Test Email - " . date('H:i:s');
                    $body = "
                    <html>
                    <head>
                        <style>
                            body { font-family: Arial, sans-serif; }
                            .header { background: linear-gradient(135deg, #d60000 0%, #b30000 100%); color: white; padding: 30px; text-align: center; }
                            .content { padding: 30px; }
                            .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; font-size: 12px; }
                        </style>
                    </head>
                    <body>
                        <div class='header'>
                            <h1>✅ Email Test Successful!</h1>
                        </div>
                        <div class='content'>
                            <h2>Hello!</h2>
                            <p>This is a <strong>test email</strong> from the Mitsubishi notification system.</p>
                            <p><strong>If you're reading this, email delivery is working correctly!</strong></p>
                            <hr>
                            <p><strong>Test Details:</strong></p>
                            <ul>
                                <li>Sent at: " . date('Y-m-d H:i:s') . "</li>
                                <li>From: " . getenv('GMAIL_FROM_EMAIL') . "</li>
                                <li>To: $testEmail</li>
                                <li>SMTP Host: " . getenv('SMTP_HOST') . "</li>
                                <li>SMTP Port: " . getenv('SMTP_PORT') . "</li>
                            </ul>
                        </div>
                        <div class='footer'>
                            <p>Mitsubishi Motors San Pablo - Notification System Test</p>
                        </div>
                    </body>
                    </html>
                    ";
                    
                    $result = $mailer->sendEmail($testEmail, $subject, $body);
                    
                    if ($result['success']) {
                        echo "<div class='success'>";
                        echo "<h3>✅ Email Sent Successfully!</h3>";
                        echo "<p><strong>Message ID:</strong> " . ($result['message_id'] ?? 'N/A') . "</p>";
                        echo "<p><strong>Recipient:</strong> $testEmail</p>";
                        echo "<p><strong>Subject:</strong> $subject</p>";
                        echo "<p><strong>Sent at:</strong> " . date('Y-m-d H:i:s') . "</p>";
                        echo "<hr>";
                        echo "<h4>⏰ Next Steps:</h4>";
                        echo "<ol>";
                        echo "<li>Wait 1-2 minutes for delivery</li>";
                        echo "<li><strong>Check your INBOX</strong></li>";
                        echo "<li><strong>Check your SPAM/JUNK folder</strong> ← Most likely here!</li>";
                        echo "<li>If using Gmail, check the Promotions tab</li>";
                        echo "<li>If still not there, check 'All Mail' folder</li>";
                        echo "</ol>";
                        echo "<p><strong>💡 Tip:</strong> If found in spam, mark as 'Not Spam' and add sender to contacts.</p>";
                        echo "</div>";
                        
                        echo "<div class='info'>";
                        echo "<h4>📊 Full Response:</h4>";
                        echo "<pre>" . print_r($result, true) . "</pre>";
                        echo "</div>";
                        
                    } else {
                        echo "<div class='error'>";
                        echo "<h3>❌ Email Failed to Send!</h3>";
                        echo "<p><strong>Error:</strong> " . ($result['error'] ?? 'Unknown error') . "</p>";
                        echo "<hr>";
                        echo "<h4>🔧 Possible Fixes:</h4>";
                        echo "<ul>";
                        echo "<li>Check .env file has correct SMTP credentials</li>";
                        echo "<li>Verify email account exists in Hostinger</li>";
                        echo "<li>Try port 587 with TLS instead of 465 with SSL</li>";
                        echo "<li>Check if SMTP is enabled for the email account</li>";
                        echo "</ul>";
                        echo "</div>";
                        
                        echo "<div class='info'>";
                        echo "<h4>📊 Full Response:</h4>";
                        echo "<pre>" . print_r($result, true) . "</pre>";
                        echo "</div>";
                    }
                    
                } catch (Exception $e) {
                    echo "<div class='error'>";
                    echo "<h3>❌ Exception Occurred!</h3>";
                    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
                    echo "<p><strong>File:</strong> " . $e->getFile() . "</p>";
                    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
                    echo "<hr>";
                    echo "<h4>🔧 Common Causes:</h4>";
                    echo "<ul>";
                    echo "<li>GmailMailer.php not found or has errors</li>";
                    echo "<li>.env file missing or not readable</li>";
                    echo "<li>SMTP credentials not configured</li>";
                    echo "<li>PHPMailer library missing</li>";
                    echo "</ul>";
                    echo "</div>";
                    
                    echo "<div class='info'>";
                    echo "<h4>📊 Stack Trace:</h4>";
                    echo "<pre>" . $e->getTraceAsString() . "</pre>";
                    echo "</div>";
                }
            }
        }
        ?>
        
        <form method="POST">
            <label for="test_email">Enter your email address to receive a test email:</label>
            <input 
                type="email" 
                id="test_email" 
                name="test_email" 
                placeholder="your-email@example.com" 
                required
                value="<?php echo isset($_POST['test_email']) ? htmlspecialchars($_POST['test_email']) : ''; ?>"
            >
            <button type="submit">📧 Send Test Email</button>
        </form>
        
        <div class="info" style="margin-top: 30px;">
            <h3>ℹ️ About This Test</h3>
            <p>This test sends a simple email directly using GmailMailer, bypassing:</p>
            <ul>
                <li>Database queries</li>
                <li>NotificationService</li>
                <li>Email templates</li>
                <li>Customer lookups</li>
            </ul>
            <p><strong>Purpose:</strong> Isolate whether the issue is with SMTP configuration or with the notification system.</p>
            <p><strong>If this test works:</strong> SMTP is fine, issue is with notification system integration.</p>
            <p><strong>If this test fails:</strong> SMTP configuration needs to be fixed.</p>
        </div>
        
        <div class="info">
            <h3>🔍 Current SMTP Configuration</h3>
            <?php
            require_once 'includes/utils/EnvLoader.php';
            \Mitsubishi\Utils\EnvLoader::load();
            
            echo "<table style='width: 100%; border-collapse: collapse;'>";
            echo "<tr style='background: #f8f9fa;'><td style='padding: 8px; border: 1px solid #ddd;'><strong>SMTP Host</strong></td><td style='padding: 8px; border: 1px solid #ddd;'>" . getenv('SMTP_HOST') . "</td></tr>";
            echo "<tr><td style='padding: 8px; border: 1px solid #ddd;'><strong>SMTP Port</strong></td><td style='padding: 8px; border: 1px solid #ddd;'>" . getenv('SMTP_PORT') . "</td></tr>";
            echo "<tr style='background: #f8f9fa;'><td style='padding: 8px; border: 1px solid #ddd;'><strong>Encryption</strong></td><td style='padding: 8px; border: 1px solid #ddd;'>" . getenv('SMTP_ENCRYPTION') . "</td></tr>";
            echo "<tr><td style='padding: 8px; border: 1px solid #ddd;'><strong>From Email</strong></td><td style='padding: 8px; border: 1px solid #ddd;'>" . getenv('GMAIL_FROM_EMAIL') . "</td></tr>";
            echo "<tr style='background: #f8f9fa;'><td style='padding: 8px; border: 1px solid #ddd;'><strong>From Name</strong></td><td style='padding: 8px; border: 1px solid #ddd;'>" . getenv('GMAIL_FROM_NAME') . "</td></tr>";
            echo "</table>";
            ?>
        </div>
        
        <div style="margin-top: 30px; padding: 20px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 5px;">
            <h3 style="margin-top: 0;">⚠️ Important Notes</h3>
            <ul>
                <li><strong>Check SPAM folder first!</strong> - 90% of "missing" emails are in spam</li>
                <li>Delivery can take 1-5 minutes</li>
                <li>New sender domains often get flagged as spam initially</li>
                <li>Add no-reply@mitsubishiautoxpress.com to your contacts</li>
                <li>If using Gmail, check Promotions and All Mail tabs</li>
            </ul>
        </div>
    </div>
</body>
</html>

