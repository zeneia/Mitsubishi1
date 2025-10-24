<?php
/**
 * Setup Notification Logs Table
 * 
 * Run this script once to create the notification_logs table
 * 
 * Usage: php setup_notification_logs.php
 * Or access via browser: http://localhost/Mitsubishi/includes/database/setup_notification_logs.php
 */

require_once __DIR__ . '/db_conn.php';

try {
    // Read SQL file
    $sql = file_get_contents(__DIR__ . '/create_notification_logs_table.sql');
    
    // Execute SQL
    $connect->exec($sql);
    
    echo "✅ SUCCESS: notification_logs table created successfully!\n";
    echo "\nTable structure:\n";
    echo "- customer_id (FK to accounts)\n";
    echo "- notification_type (loan_approval, test_drive_approval, etc.)\n";
    echo "- channel (email, sms, both)\n";
    echo "- email_status, sms_status (sent, failed, skipped)\n";
    echo "- related_id, related_table (for tracking source records)\n";
    echo "- Timestamps and error logging\n";
    
} catch (PDOException $e) {
    echo "❌ ERROR: Failed to create notification_logs table\n";
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

