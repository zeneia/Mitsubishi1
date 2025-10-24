<?php
/**
 * Payment Reminder Cron Job
 * 
 * This script should be run daily to send payment reminders to customers
 * 
 * Schedule this in crontab (Linux/Mac) or Task Scheduler (Windows):
 * 
 * Linux/Mac crontab:
 * 0 9 * * * php /path/to/Mitsubishi/includes/cron/payment_reminder_cron.php
 * 
 * Windows Task Scheduler:
 * Program: C:\xampp\php\php.exe
 * Arguments: D:\xampp\htdocs\Mitsubishi\includes\cron\payment_reminder_cron.php
 * Schedule: Daily at 9:00 AM
 * 
 * Or trigger manually via API endpoint:
 * POST /api/trigger_payment_reminders.php
 */

// Prevent direct browser access (allow CLI and API only)
if (php_sapi_name() !== 'cli' && !isset($_SERVER['HTTP_X_CRON_KEY'])) {
    // Allow access only with valid cron key
    $validKey = getenv('CRON_SECRET_KEY') ?: 'mitsubishi_cron_2024';
    $providedKey = $_SERVER['HTTP_X_CRON_KEY'] ?? '';
    
    if ($providedKey !== $validKey) {
        http_response_code(403);
        die('Access denied. This script can only be run via CLI or with valid cron key.');
    }
}

// Set timezone
date_default_timezone_set('Asia/Manila');

// Load dependencies
require_once dirname(__DIR__) . '/database/db_conn.php';
require_once dirname(__DIR__) . '/services/NotificationService.php';

// Log start
$logFile = dirname(__DIR__) . '/../logs/payment_reminders.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
    echo $logEntry; // Also output to console
}

logMessage("=== Payment Reminder Cron Job Started ===");

try {
    // Initialize notification service
    $notificationService = new NotificationService($connect);
    
    // Send all due reminders
    logMessage("Checking for payment reminders to send...");
    $result = $notificationService->sendPaymentReminders();
    
    // Log results
    logMessage("Payment reminders sent: " . $result['sent']);
    logMessage("Payment reminders failed: " . $result['failed']);
    logMessage("Payment reminders skipped (already sent today): " . $result['skipped']);
    
    // Calculate total
    $total = $result['sent'] + $result['failed'] + $result['skipped'];
    logMessage("Total payment schedules processed: " . $total);
    
    // Success
    logMessage("=== Payment Reminder Cron Job Completed Successfully ===");
    
    // Return JSON if called via API
    if (php_sapi_name() !== 'cli') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'timestamp' => date('Y-m-d H:i:s'),
            'statistics' => $result
        ]);
    }
    
    exit(0);
    
} catch (Exception $e) {
    // Log error
    $errorMessage = "ERROR: " . $e->getMessage();
    logMessage($errorMessage);
    logMessage("Stack trace: " . $e->getTraceAsString());
    logMessage("=== Payment Reminder Cron Job Failed ===");
    
    // Return error JSON if called via API
    if (php_sapi_name() !== 'cli') {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    
    exit(1);
}

