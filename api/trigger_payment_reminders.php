<?php
/**
 * Trigger Payment Reminders API
 * 
 * Manual trigger endpoint for payment reminder cron job
 * Can be called by admins or automated systems
 * 
 * Usage:
 * POST /api/trigger_payment_reminders.php
 * Headers: X-Cron-Key: <secret_key>
 * 
 * Or for authenticated admin users (no key required):
 * POST /api/trigger_payment_reminders.php
 * (Must be logged in as Admin)
 */

session_start();

// Set timezone
date_default_timezone_set('Asia/Manila');

// Load dependencies
require_once dirname(__DIR__) . '/includes/database/db_conn.php';

// Check authorization
$isAuthorized = false;

// Method 1: Check if user is logged in as Admin
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Admin') {
    $isAuthorized = true;
}

// Method 2: Check for valid cron key in header
if (!$isAuthorized) {
    $validKey = getenv('CRON_SECRET_KEY') ?: 'mitsubishi_cron_2024';
    $providedKey = $_SERVER['HTTP_X_CRON_KEY'] ?? '';
    
    if ($providedKey === $validKey) {
        $isAuthorized = true;
    }
}

// Deny access if not authorized
if (!$isAuthorized) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized. Admin access or valid cron key required.'
    ]);
    exit;
}

// Set header for cron script
$_SERVER['HTTP_X_CRON_KEY'] = getenv('CRON_SECRET_KEY') ?: 'mitsubishi_cron_2024';

// Execute cron script
ob_start();
include dirname(__DIR__) . '/includes/cron/payment_reminder_cron.php';
$output = ob_get_clean();

// The cron script already outputs JSON, so we're done
exit;

