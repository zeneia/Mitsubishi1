<?php
/**
 * Gmail SMTP Email Send Test
 * 
 * This script sends a test email to verify Gmail SMTP is working.
 * 
 * IMPORTANT: Update the $test_recipient variable below with your email address!
 * 
 * Run from command line:
 *   php tests/test_send_email.php
 * 
 * Or access via browser:
 *   http://localhost/Mitsubishi/tests/test_send_email.php
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ============================================
// CONFIGURATION - UPDATE THIS!
// ============================================
$test_recipient = 'melsonbsolutions@gmail.com'; // ← CHANGE THIS TO YOUR EMAIL
// ============================================

echo "=== Gmail SMTP Email Send Test ===\n\n";

// Check if recipient is configured
if ($test_recipient === 'your-test-email@example.com') {
    echo "⚠ ERROR: Please update \$test_recipient in this file with your email address!\n";
    echo "   Edit: tests/test_send_email.php\n";
    echo "   Line: \$test_recipient = 'your-test-email@example.com';\n\n";
    exit(1);
}

// Load environment variables
require_once dirname(__DIR__) . '/includes/utils/EnvLoader.php';
\Mitsubishi\Utils\EnvLoader::load();

// Load GmailMailer (includes PHPMailer automatically - no installation needed!)
require_once dirname(__DIR__) . '/includes/backend/GmailMailer.php';

// Check credentials
$gmail_email = getenv('GMAIL_EMAIL');
$gmail_password = getenv('GMAIL_PASSWORD');

if (empty($gmail_email) || $gmail_email === 'your-email@gmail.com') {
    echo "✗ GMAIL_EMAIL not configured in .env file!\n\n";
    exit(1);
}

if (empty($gmail_password) || $gmail_password === 'xxxx xxxx xxxx xxxx') {
    echo "✗ GMAIL_PASSWORD not configured in .env file!\n\n";
    exit(1);
}

echo "Configuration:\n";
echo "  From: " . $gmail_email . "\n";
echo "  To: " . $test_recipient . "\n\n";

// Prepare test email
$subject = "✅ Gmail SMTP Test - " . date('Y-m-d H:i:s');
$message = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #dc143c, #b91c3c); color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; }
        .success { color: #28a745; font-size: 24px; font-weight: bold; }
        .info { background: #e7f3ff; padding: 15px; border-left: 4px solid #2196F3; margin: 15px 0; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>🚗 Mitsubishi Motors</h1>
            <p>Gmail SMTP Test Email</p>
        </div>
        <div class='content'>
            <h2 class='success'>✓ Success!</h2>
            <p>This is a test email from the Mitsubishi Motors dealership system.</p>
            
            <div class='info'>
                <strong>Test Details:</strong><br>
                Sent: " . date('F j, Y \a\t g:i A') . "<br>
                From: Gmail SMTP<br>
                System: Mitsubishi Dealership Management
            </div>
            
            <p>If you received this email, it means:</p>
            <ul>
                <li>✓ Gmail SMTP is properly configured</li>
                <li>✓ PHPMailer is working correctly</li>
                <li>✓ Email sending functionality is operational</li>
                <li>✓ The system is ready for production use</li>
            </ul>
            
            <p><strong>Next Steps:</strong></p>
            <ol>
                <li>Test email sending from the admin panel</li>
                <li>Verify email logging in the database</li>
                <li>Monitor email delivery rates</li>
                <li>Configure email templates as needed</li>
            </ol>
        </div>
        <div class='footer'>
            <p>This is an automated test email from Mitsubishi Motors</p>
            <p>Dealership Management System</p>
        </div>
    </div>
</body>
</html>
";

// Send email
echo "Sending test email...\n";

try {
    $mailer = new \Mitsubishi\Backend\GmailMailer();
    
    $result = $mailer->sendEmail(
        $test_recipient,
        $subject,
        $message,
        ['priority' => 'normal']
    );
    
    if ($result['success']) {
        echo "\n✓ SUCCESS! Email sent successfully!\n\n";
        echo "Details:\n";
        echo "  Message ID: " . ($result['message_id'] ?? 'N/A') . "\n";
        echo "  Status: " . ($result['message'] ?? 'Sent') . "\n\n";
        echo "Please check your inbox at: " . $test_recipient . "\n";
        echo "(Don't forget to check spam folder if you don't see it)\n\n";
        
        echo "=== Test Completed Successfully ===\n";
    } else {
        echo "\n✗ FAILED to send email\n\n";
        echo "Error: " . ($result['error'] ?? 'Unknown error') . "\n\n";
        
        echo "Common issues:\n";
        echo "1. Check your Gmail App Password is correct\n";
        echo "2. Ensure 2-Step Verification is enabled on your Gmail account\n";
        echo "3. Verify your internet connection\n";
        echo "4. Check if Gmail SMTP is accessible (port 587)\n\n";
        
        echo "For troubleshooting, see: GMAIL_SETUP_GUIDE.md\n\n";
    }
    
} catch (Exception $e) {
    echo "\n✗ EXCEPTION occurred\n\n";
    echo "Error: " . $e->getMessage() . "\n\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString() . "\n\n";
}

?>

