<?php
/**
 * Email Notification Test Script
 * 
 * This script tests the email notification system to diagnose issues
 * Run this from browser: http://localhost/Mitsubishi/test_email_notification.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Email Notification Test</h1>";
echo "<pre>";

// Step 1: Check database connection
echo "=== STEP 1: Database Connection ===\n";
try {
    require_once 'includes/database/db_conn.php';
    echo "✅ Database connected successfully\n\n";
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n\n";
    exit;
}

// Step 2: Check if notification_logs table exists
echo "=== STEP 2: Check notification_logs Table ===\n";
try {
    $stmt = $connect->query("SHOW TABLES LIKE 'notification_logs'");
    if ($stmt->rowCount() > 0) {
        echo "✅ notification_logs table exists\n\n";
    } else {
        echo "❌ notification_logs table does NOT exist\n";
        echo "   Please create it using the SQL query provided\n\n";
    }
} catch (Exception $e) {
    echo "❌ Error checking table: " . $e->getMessage() . "\n\n";
}

// Step 3: Check .env configuration
echo "=== STEP 3: Email Configuration ===\n";
require_once 'includes/utils/EnvLoader.php';
\Mitsubishi\Utils\EnvLoader::load();

$config = [
    'SMTP_HOST' => getenv('SMTP_HOST'),
    'SMTP_PORT' => getenv('SMTP_PORT'),
    'SMTP_ENCRYPTION' => getenv('SMTP_ENCRYPTION'),
    'GMAIL_EMAIL' => getenv('GMAIL_EMAIL'),
    'GMAIL_PASSWORD' => getenv('GMAIL_PASSWORD') ? '***configured***' : 'NOT SET',
    'GMAIL_FROM_EMAIL' => getenv('GMAIL_FROM_EMAIL'),
    'GMAIL_FROM_NAME' => getenv('GMAIL_FROM_NAME'),
];

foreach ($config as $key => $value) {
    $status = !empty($value) ? '✅' : '❌';
    echo "$status $key: $value\n";
}
echo "\n";

// Step 4: Test GmailMailer directly
echo "=== STEP 4: Test GmailMailer ===\n";
try {
    require_once 'includes/backend/GmailMailer.php';
    $mailer = new \Mitsubishi\Backend\GmailMailer();
    echo "✅ GmailMailer initialized successfully\n";
    
    // Try sending a test email
    $testEmail = getenv('GMAIL_EMAIL'); // Send to yourself for testing
    echo "Attempting to send test email to: $testEmail\n";
    
    $result = $mailer->sendEmail(
        $testEmail,
        'Test Email - Notification System',
        '<h1>Test Email</h1><p>This is a test email from the notification system.</p>'
    );
    
    if ($result['success']) {
        echo "✅ Test email sent successfully!\n";
        echo "   Message ID: " . ($result['message_id'] ?? 'N/A') . "\n";
    } else {
        echo "❌ Test email failed!\n";
        echo "   Error: " . ($result['error'] ?? 'Unknown error') . "\n";
    }
    echo "\n";
    
} catch (Exception $e) {
    echo "❌ GmailMailer error: " . $e->getMessage() . "\n\n";
}

// Step 5: Check for a real customer to test with
echo "=== STEP 5: Find Test Customer (ID 99) ===\n";
try {
    $stmt = $connect->prepare("
        SELECT
            a.Id as customer_id,
            a.Email,
            ci.mobile_number,
            ci.firstname,
            ci.lastname
        FROM accounts a
        INNER JOIN customer_information ci ON a.Id = ci.account_id
        WHERE a.Id = ?
        AND a.Role = 'Customer'
    ");
    $stmt->execute([99]);
    
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($customer) {
        echo "✅ Found test customer:\n";
        echo "   ID: {$customer['customer_id']}\n";
        echo "   Name: {$customer['firstname']} {$customer['lastname']}\n";
        echo "   Email: {$customer['Email']}\n";
        echo "   Mobile: {$customer['mobile_number']}\n\n";
        
        // Step 6: Test NotificationService with real customer
        echo "=== STEP 6: Test NotificationService ===\n";
        
        // Find a loan application for this customer
        $loanStmt = $connect->prepare("
            SELECT id, customer_id 
            FROM loan_applications 
            WHERE customer_id = ? 
            LIMIT 1
        ");
        $loanStmt->execute([$customer['customer_id']]);
        $loan = $loanStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($loan) {
            echo "Found loan application ID: {$loan['id']}\n";
            echo "Testing loan approval notification...\n";
            
            try {
                require_once 'includes/services/NotificationService.php';
                $notificationService = new NotificationService($connect);
                
                $result = $notificationService->sendLoanApprovalNotification($loan['id'], 'TEST-ORDER-001');
                
                echo "\nNotification Result:\n";
                echo "   Success: " . ($result['success'] ? 'YES' : 'NO') . "\n";
                echo "   Email Status: " . ($result['email_status'] ?? 'N/A') . "\n";
                echo "   SMS Status: " . ($result['sms_status'] ?? 'N/A') . "\n";
                
                if (!empty($result['email_error'])) {
                    echo "   Email Error: " . $result['email_error'] . "\n";
                }
                if (!empty($result['sms_error'])) {
                    echo "   SMS Error: " . $result['sms_error'] . "\n";
                }
                
            } catch (Exception $e) {
                echo "❌ NotificationService error: " . $e->getMessage() . "\n";
            }
        } else {
            echo "⚠️  No loan applications found for this customer\n";
            echo "   You can still test by creating a loan application\n";
        }
        
    } else {
        echo "❌ No customers found in database\n";
        echo "   Please create a customer account first\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error finding customer: " . $e->getMessage() . "\n";
}

// Step 7: Check recent notification logs
echo "\n=== STEP 7: Recent Notification Logs ===\n";
try {
    $stmt = $connect->query("
        SELECT 
            id,
            customer_id,
            notification_type,
            email_status,
            sms_status,
            email_error,
            sms_error,
            sent_at
        FROM notification_logs
        ORDER BY sent_at DESC
        LIMIT 5
    ");
    
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($logs) > 0) {
        echo "Recent notifications:\n";
        foreach ($logs as $log) {
            echo "\n";
            echo "  ID: {$log['id']}\n";
            echo "  Type: {$log['notification_type']}\n";
            echo "  Email: {$log['email_status']}";
            if ($log['email_error']) {
                echo " - Error: {$log['email_error']}";
            }
            echo "\n";
            echo "  SMS: {$log['sms_status']}";
            if ($log['sms_error']) {
                echo " - Error: {$log['sms_error']}";
            }
            echo "\n";
            echo "  Sent: {$log['sent_at']}\n";
        }
    } else {
        echo "No notification logs found yet\n";
    }
    
} catch (Exception $e) {
    echo "⚠️  Could not read notification logs: " . $e->getMessage() . "\n";
    echo "   (This is OK if the table doesn't exist yet)\n";
}

echo "\n=== TEST COMPLETE ===\n";
echo "</pre>";

