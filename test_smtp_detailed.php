<?php
/**
 * Detailed SMTP Test with Debug Output
 * 
 * This script shows the actual SMTP conversation to diagnose delivery issues
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Detailed SMTP Email Test</h1>";
echo "<pre>";

// Load environment
require_once 'includes/utils/EnvLoader.php';
\Mitsubishi\Utils\EnvLoader::load();

echo "=== SMTP Configuration ===\n";
$config = [
    'Host' => getenv('SMTP_HOST'),
    'Port' => getenv('SMTP_PORT'),
    'Encryption' => getenv('SMTP_ENCRYPTION'),
    'Username' => getenv('GMAIL_EMAIL'),
    'Password' => getenv('GMAIL_PASSWORD') ? '***' . substr(getenv('GMAIL_PASSWORD'), -3) : 'NOT SET',
    'From Email' => getenv('GMAIL_FROM_EMAIL'),
    'From Name' => getenv('GMAIL_FROM_NAME'),
];

foreach ($config as $key => $value) {
    echo "$key: $value\n";
}
echo "\n";

// Test 1: Check if SMTP port is reachable
echo "=== Test 1: SMTP Port Connectivity ===\n";
$host = getenv('SMTP_HOST');
$port = getenv('SMTP_PORT');
$encryption = getenv('SMTP_ENCRYPTION');

$connectionString = ($encryption === 'ssl') ? "ssl://$host" : $host;
echo "Attempting to connect to: $connectionString:$port\n";

$errno = 0;
$errstr = '';
$socket = @fsockopen($connectionString, $port, $errno, $errstr, 10);

if ($socket) {
    echo "✅ Successfully connected to SMTP server!\n";
    $response = fgets($socket, 515);
    echo "Server greeting: $response";
    fclose($socket);
} else {
    echo "❌ Failed to connect to SMTP server\n";
    echo "Error ($errno): $errstr\n";
    echo "\nPossible issues:\n";
    echo "- Port $port is blocked by firewall\n";
    echo "- SMTP server is down\n";
    echo "- Wrong host/port configuration\n";
}
echo "\n";

// Test 2: Try sending with full debug output
echo "=== Test 2: Send Test Email with Debug ===\n";

// Get customer 99 email
require_once 'includes/database/db_conn.php';
$stmt = $connect->prepare("
    SELECT a.Email, ci.firstname, ci.lastname
    FROM accounts a
    INNER JOIN customer_information ci ON a.Id = ci.account_id
    WHERE a.Id = 99
");
$stmt->execute();
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    echo "❌ Customer ID 99 not found!\n";
    exit;
}

$recipientEmail = $customer['Email'];
$recipientName = $customer['firstname'] . ' ' . $customer['lastname'];

echo "Recipient: $recipientName <$recipientEmail>\n\n";

// Manual SMTP send with debug output
echo "--- SMTP Conversation ---\n";

try {
    $smtp = fsockopen($connectionString, $port, $errno, $errstr, 30);
    
    if (!$smtp) {
        throw new Exception("Connection failed: $errstr ($errno)");
    }
    
    // Read greeting
    $response = fgets($smtp, 515);
    echo "S: $response";
    
    // Send EHLO
    $domain = getenv('GMAIL_FROM_EMAIL');
    $domain = substr($domain, strpos($domain, '@') + 1);
    fputs($smtp, "EHLO $domain\r\n");
    while ($line = fgets($smtp, 515)) {
        echo "S: $line";
        if (substr($line, 3, 1) === ' ') break;
    }
    
    // Start TLS if needed
    if ($encryption === 'tls') {
        fputs($smtp, "STARTTLS\r\n");
        $response = fgets($smtp, 515);
        echo "S: $response";
        
        if (substr($response, 0, 3) === '220') {
            stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            
            // Send EHLO again after STARTTLS
            fputs($smtp, "EHLO $domain\r\n");
            while ($line = fgets($smtp, 515)) {
                echo "S: $line";
                if (substr($line, 3, 1) === ' ') break;
            }
        }
    }
    
    // Authenticate
    fputs($smtp, "AUTH LOGIN\r\n");
    $response = fgets($smtp, 515);
    echo "S: $response";
    
    fputs($smtp, base64_encode(getenv('GMAIL_EMAIL')) . "\r\n");
    $response = fgets($smtp, 515);
    echo "S: $response";
    
    fputs($smtp, base64_encode(getenv('GMAIL_PASSWORD')) . "\r\n");
    $response = fgets($smtp, 515);
    echo "S: $response";
    
    if (substr($response, 0, 3) !== '235') {
        throw new Exception("Authentication failed: $response");
    }
    
    echo "✅ Authentication successful!\n\n";
    
    // Send MAIL FROM
    fputs($smtp, "MAIL FROM: <" . getenv('GMAIL_FROM_EMAIL') . ">\r\n");
    $response = fgets($smtp, 515);
    echo "S: $response";
    
    // Send RCPT TO
    fputs($smtp, "RCPT TO: <$recipientEmail>\r\n");
    $response = fgets($smtp, 515);
    echo "S: $response";
    
    if (substr($response, 0, 3) !== '250') {
        throw new Exception("Recipient rejected: $response");
    }
    
    // Send DATA
    fputs($smtp, "DATA\r\n");
    $response = fgets($smtp, 515);
    echo "S: $response";
    
    // Build message
    $boundary = md5(time());
    $message = "From: " . getenv('GMAIL_FROM_NAME') . " <" . getenv('GMAIL_FROM_EMAIL') . ">\r\n";
    $message .= "To: $recipientName <$recipientEmail>\r\n";
    $message .= "Subject: Test Email - Notification System\r\n";
    $message .= "MIME-Version: 1.0\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n";
    $message .= "Date: " . date('r') . "\r\n";
    $message .= "\r\n";
    $message .= "<html><body>";
    $message .= "<h1>Test Email</h1>";
    $message .= "<p>Hello $recipientName,</p>";
    $message .= "<p>This is a test email from the Mitsubishi notification system.</p>";
    $message .= "<p>If you receive this, email delivery is working correctly!</p>";
    $message .= "<p><strong>Sent at:</strong> " . date('Y-m-d H:i:s') . "</p>";
    $message .= "</body></html>\r\n";
    
    fputs($smtp, $message);
    fputs($smtp, "\r\n.\r\n");
    $response = fgets($smtp, 515);
    echo "S: $response";
    
    if (substr($response, 0, 3) === '250') {
        echo "\n✅ Email accepted by server!\n";
        echo "Message ID: ";
        if (preg_match('/<([^>]+)>/', $response, $matches)) {
            echo $matches[1] . "\n";
        } else {
            echo "N/A\n";
        }
    } else {
        echo "\n❌ Email rejected by server!\n";
    }
    
    // Send QUIT
    fputs($smtp, "QUIT\r\n");
    $response = fgets($smtp, 515);
    echo "S: $response";
    
    fclose($smtp);
    
    echo "\n--- End SMTP Conversation ---\n\n";
    
} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n\n";
}

// Test 3: Check email logs in database
echo "=== Test 3: Recent Email Logs ===\n";
try {
    $stmt = $connect->query("
        SELECT 
            notification_type,
            email_recipient,
            email_status,
            email_error,
            sent_at
        FROM notification_logs
        WHERE customer_id = 99
        ORDER BY sent_at DESC
        LIMIT 5
    ");
    
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($logs) > 0) {
        foreach ($logs as $log) {
            echo "\n";
            echo "Type: {$log['notification_type']}\n";
            echo "To: {$log['email_recipient']}\n";
            echo "Status: {$log['email_status']}\n";
            if ($log['email_error']) {
                echo "Error: {$log['email_error']}\n";
            }
            echo "Sent: {$log['sent_at']}\n";
        }
    } else {
        echo "No email logs found for customer 99\n";
    }
} catch (Exception $e) {
    echo "Could not read logs: " . $e->getMessage() . "\n";
}

echo "\n=== Troubleshooting Tips ===\n";
echo "If email shows as 'sent' but you don't receive it:\n\n";
echo "1. CHECK SPAM/JUNK FOLDER\n";
echo "   - Most common reason for missing emails\n";
echo "   - Mark as 'Not Spam' if found there\n\n";
echo "2. CHECK EMAIL ADDRESS\n";
echo "   - Verify: $recipientEmail\n";
echo "   - Make sure it's correct and active\n\n";
echo "3. CHECK SENDER REPUTATION\n";
echo "   - New email addresses may be flagged as spam\n";
echo "   - no-reply@mitsubishiautoxpress.com needs to build reputation\n\n";
echo "4. CHECK SPF/DKIM RECORDS\n";
echo "   - Login to Hostinger cPanel\n";
echo "   - Go to Email Deliverability\n";
echo "   - Ensure SPF and DKIM are valid\n\n";
echo "5. CHECK HOSTINGER EMAIL LOGS\n";
echo "   - Login to cPanel\n";
echo "   - Go to Track Delivery\n";
echo "   - See if email was actually sent from server\n\n";
echo "6. TRY DIFFERENT RECIPIENT\n";
echo "   - Send to Gmail, Yahoo, Outlook\n";
echo "   - See if it's a recipient-side issue\n\n";
echo "7. CHECK SENDING LIMITS\n";
echo "   - Hostinger may have hourly/daily limits\n";
echo "   - Check if you've exceeded them\n\n";

echo "</pre>";

