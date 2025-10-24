<?php
// Configure session settings for AJAX requests
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');

// Ensure session is started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include_once(dirname(dirname(__DIR__)) . '/includes/init.php');

// Debug session information
error_log("Payment Backend Debug - Session ID: " . session_id());
error_log("Payment Backend Debug - Session data: " . print_r($_SESSION, true));

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'message' => 'Unauthorized - No user_id in session',
        'debug' => [
            'session_id' => session_id(),
            'session_keys' => array_keys($_SESSION),
            'cookies' => array_keys($_COOKIE)
        ]
    ]);
    exit();
}

$pdo = $GLOBALS['pdo'] ?? null;
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? '';

// Debug: Log the received data
error_log("Payment Backend Debug - Action: '" . $action . "', User ID: " . $user_id . ", User Role: " . $user_role);
error_log("Payment Backend Debug - POST data: " . print_r($_POST, true));
error_log("Payment Backend Debug - GET data: " . print_r($_GET, true));

// Check if action is empty
if (empty($action)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No action specified. Required actions: get_payment_stats, get_agent_payments, get_payment_details, process_payment, get_receipt']);
    exit();
}

try {
    switch ($action) {
        case 'get_payment_stats':
            getPaymentStats();
            break;
        case 'get_agent_payments':
            getAgentPayments();
            break;
        case 'get_payment_details':
            getPaymentDetails();
            break;
        case 'process_payment':
            processPayment();
            break;
        case 'get_receipt':
            getReceipt();
            break;
        default:
            throw new Exception('Invalid action: "' . $action . '". Valid actions are: get_payment_stats, get_agent_payments, get_payment_details, process_payment, get_receipt');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Get payment statistics for the current agent
 */
function getPaymentStats()
{
    global $pdo, $user_id, $user_role;
    
    try {
        // Filter payments by agent-assigned customers
        if ($user_role === 'Admin') {
            // Admin can see all payments
            $sql = "SELECT 
                        COUNT(CASE WHEN ph.status = 'Pending' THEN 1 END) as pending,
                        COUNT(CASE WHEN ph.status = 'Confirmed' THEN 1 END) as confirmed,
                        COUNT(CASE WHEN ph.status = 'Rejected' THEN 1 END) as rejected,
                        COALESCE(SUM(CASE WHEN ph.status = 'Confirmed' THEN ph.amount_paid END), 0) as total_amount
                    FROM payment_history ph";
            $params = [];
        } else {
            // Sales agents can only see payments from their assigned customers
            $sql = "SELECT 
                        COUNT(CASE WHEN ph.status = 'Pending' THEN 1 END) as pending,
                        COUNT(CASE WHEN ph.status = 'Confirmed' THEN 1 END) as confirmed,
                        COUNT(CASE WHEN ph.status = 'Rejected' THEN 1 END) as rejected,
                        COALESCE(SUM(CASE WHEN ph.status = 'Confirmed' THEN ph.amount_paid END), 0) as total_amount
                    FROM payment_history ph
                    INNER JOIN orders o ON ph.order_id = o.order_id
                    WHERE o.sales_agent_id = ?";
            $params = [$user_id];
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $stats
        ]);
        
    } catch (PDOException $e) {
        throw new Exception('Failed to fetch payment statistics: ' . $e->getMessage());
    }
}

/**
 * Get payments for the current agent with filtering and pagination
 */
function getAgentPayments()
{
    global $pdo, $user_id, $user_role;
    
    try {
        // Check if payment_history table exists
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'payment_history'");
        if ($tableCheck->rowCount() == 0) {
            throw new Exception('Payment system not configured. payment_history table does not exist.');
        }
        // Get filter parameters
        $status = $_POST['status'] ?? '';
        $payment_type = $_POST['payment_type'] ?? '';
        $date_from = $_POST['date_from'] ?? '';
        $date_to = $_POST['date_to'] ?? '';
        $page = max(1, intval($_POST['page'] ?? 1));
        $limit = max(1, min(50, intval($_POST['limit'] ?? 10)));
        $offset = ($page - 1) * $limit;
        
        // Build WHERE conditions
        $whereConditions = [];
        $params = [];
        
        // Role-based filtering - filter by agent's assigned customers
        if ($user_role !== 'Admin') {
            $whereConditions[] = "o.sales_agent_id = ?";
            $params[] = $user_id;
        }
        
        if (!empty($status)) {
            $whereConditions[] = "ph.status = ?";
            $params[] = $status;
        }
        
        if (!empty($payment_type)) {
            $whereConditions[] = "ph.payment_type = ?";
            $params[] = $payment_type;
        }
        
        if (!empty($date_from)) {
            $whereConditions[] = "DATE(ph.created_at) >= ?";
            $params[] = $date_from;
        }
        
        if (!empty($date_to)) {
            $whereConditions[] = "DATE(ph.created_at) <= ?";
            $params[] = $date_to;
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        // Get total count - with proper joins for agent filtering
        $countSql = "SELECT COUNT(*) as total
                     FROM payment_history ph
                     INNER JOIN orders o ON ph.order_id = o.order_id
                     $whereClause";
        
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Get paginated results - with proper joins and customer information
        $sql = "SELECT
                    ph.id,
                    ph.payment_number,
                    ph.amount_paid,
                    ph.payment_method,
                    ph.payment_type,
                    ph.status,
                    ph.created_at,
                    ph.order_id,
                    ph.customer_id,
                    o.order_number,
                    o.vehicle_model,
                    o.vehicle_variant,
                    COALESCE(
                        NULLIF(TRIM(CONCAT(COALESCE(ci.firstname, ''), ' ', COALESCE(ci.lastname, ''))), ''),
                        NULLIF(TRIM(CONCAT(COALESCE(a.FirstName, ''), ' ', COALESCE(a.LastName, ''))), ''),
                        a.Username,
                        CONCAT('Customer #', ph.customer_id)
                    ) as customer_name
                FROM payment_history ph
                INNER JOIN orders o ON ph.order_id = o.order_id
                LEFT JOIN customer_information ci ON ph.customer_id = ci.cusID
                LEFT JOIN accounts a ON ci.account_id = a.Id
                $whereClause
                ORDER BY ph.created_at DESC
                LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'payments' => $payments,
                'total' => $total,
                'page' => $page,
                'limit' => $limit
            ]
        ]);
        
    } catch (PDOException $e) {
        throw new Exception('Failed to fetch payments: ' . $e->getMessage());
    }
}

/**
 * Get detailed information for a specific payment
 */
function getPaymentDetails()
{
    global $pdo, $user_id, $user_role;
    
    $payment_id = $_POST['payment_id'] ?? '';
    
    if (empty($payment_id)) {
        throw new Exception('Payment ID is required');
    }
    
    try {
        // Build query with role-based access control
        $sql = "SELECT
                    ph.*,
                    o.order_number,
                    COALESCE(
                        NULLIF(TRIM(CONCAT(COALESCE(ci.firstname, ''), ' ', COALESCE(ci.lastname, ''))), ''),
                        NULLIF(TRIM(CONCAT(COALESCE(a.FirstName, ''), ' ', COALESCE(a.LastName, ''))), ''),
                        a.Username,
                        CONCAT('Customer #', ph.customer_id)
                    ) as customer_name,
                    ci.mobile_number,
                    o.vehicle_model,
                    o.vehicle_variant,
                    o.total_price,
                    CONCAT(proc.FirstName, ' ', proc.LastName) as processed_by_name
                FROM payment_history ph
                LEFT JOIN orders o ON ph.order_id = o.order_id
                LEFT JOIN customer_information ci ON ph.customer_id = ci.cusID
                LEFT JOIN accounts a ON ci.account_id = a.Id
                LEFT JOIN accounts proc ON ph.processed_by = proc.Id
                WHERE ph.id = ?";

        $params = [$payment_id];

        // Add role-based restriction
        if ($user_role !== 'Admin') {
            $sql .= " AND o.sales_agent_id = ?";
            $params[] = $user_id;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$payment) {
            throw new Exception('Payment not found or access denied');
        }
        
        // Convert BLOB to base64 for receipt image
        if ($payment['receipt_image']) {
            $payment['receipt_image'] = base64_encode($payment['receipt_image']);
        }
        
        echo json_encode([
            'success' => true,
            'data' => $payment
        ]);
        
    } catch (PDOException $e) {
        throw new Exception('Failed to fetch payment details: ' . $e->getMessage());
    }
}

/**
 * Process payment (approve or reject)
 */
function processPayment()
{
    global $pdo, $user_id, $user_role;
    
    $payment_id = $_POST['payment_id'] ?? '';
    $process_action = $_POST['process_action'] ?? '';
    $rejection_reason = $_POST['rejection_reason'] ?? '';
    
    if (empty($payment_id) || empty($process_action)) {
        throw new Exception('Payment ID and action are required');
    }
    
    if (!in_array($process_action, ['approve', 'reject'])) {
        throw new Exception('Invalid action');
    }
    
    if ($process_action === 'reject' && empty($rejection_reason)) {
        throw new Exception('Rejection reason is required');
    }
    
    try {
        $pdo->beginTransaction();
        
        // Verify payment exists and user has access
        $verifySql = "SELECT ph.id, ph.status, ph.order_id, ph.amount_paid, ph.payment_type
                      FROM payment_history ph
                      INNER JOIN orders o ON ph.order_id = o.order_id
                      WHERE ph.id = ? AND ph.status = 'Pending'";
        
        $verifyParams = [$payment_id];
        
        // Add role-based restriction
        if ($user_role !== 'Admin') {
            $verifySql .= " AND o.sales_agent_id = ?";
            $verifyParams[] = $user_id;
        }
        
        $verifyStmt = $pdo->prepare($verifySql);
        $verifyStmt->execute($verifyParams);
        $payment = $verifyStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$payment) {
            throw new Exception('Payment not found, already processed, or access denied');
        }
        
        // Update payment status
        $newStatus = $process_action === 'approve' ? 'Confirmed' : 'Failed';
        $updateSql = "UPDATE payment_history 
                      SET status = ?, 
                          processed_by = ?, 
                          rejection_reason = ?
                      WHERE id = ?";
        
        $updateStmt = $pdo->prepare($updateSql);
        $updateStmt->execute([
            $newStatus,
            $user_id,
            $rejection_reason,
            $payment_id
        ]);
        
        // If approved, update payment schedule
        if ($process_action === 'approve') {
            updatePaymentSchedule($payment['order_id'], $payment['amount_paid'], $payment['payment_type']);
        }
        
        // Create notification for customer (in-app)
        $payment_number = $pdo->query("SELECT payment_number FROM payment_history WHERE id = $payment_id")->fetch()['payment_number'];
        $customer_id = $pdo->query("SELECT customer_id FROM payment_history WHERE id = $payment_id")->fetch()['customer_id'];
        createPaymentNotification($customer_id, $payment_number, $newStatus, $payment['amount_paid']);

        // Send email and SMS notifications
        try {
            require_once dirname(__DIR__) . '/services/NotificationService.php';
            $notificationService = new NotificationService($pdo);

            if ($process_action === 'approve') {
                $notificationService->sendPaymentConfirmationNotification($payment_id);
            } else {
                $notificationService->sendPaymentRejectionNotification($payment_id, $rejection_reason);
            }
        } catch (Exception $notifError) {
            // Log error but don't fail the payment processing
            error_log("Payment notification error: " . $notifError->getMessage());
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Payment ' . $process_action . 'd successfully'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw new Exception('Failed to process payment: ' . $e->getMessage());
    }
}

/**
 * Update payment schedule after payment confirmation
 */
function updatePaymentSchedule($order_id, $amount, $payment_type)
{
    global $pdo;

    try {
        // Get the next pending payment in the schedule
        $scheduleSql = "SELECT * FROM payment_schedule
                        WHERE order_id = ? AND status IN ('Pending', 'Partial')
                        ORDER BY due_date ASC
                        LIMIT 1";

        $scheduleStmt = $pdo->prepare($scheduleSql);
        $scheduleStmt->execute([$order_id]);
        $nextPayment = $scheduleStmt->fetch(PDO::FETCH_ASSOC);

        if ($nextPayment) {
            $remaining_amount = $amount;
            $payment_id = $nextPayment['id'];

            // Calculate current balance (amount_due - amount_paid)
            $current_balance = $nextPayment['amount_due'] - $nextPayment['amount_paid'];

            // Update the payment schedule entry
            if ($remaining_amount >= $current_balance) {
                // Full payment of this installment
                $updateSql = "UPDATE payment_schedule
                              SET amount_paid = amount_due,
                                  status = 'Paid',
                                  paid_date = NOW(),
                                  updated_at = NOW()
                              WHERE id = ?";
                $updateStmt = $pdo->prepare($updateSql);
                $updateStmt->execute([$payment_id]);

                $remaining_amount -= $current_balance;

                // If there's remaining amount, apply to next payments
                if ($remaining_amount > 0) {
                    applyRemainingAmount($order_id, $remaining_amount);
                }
            } else {
                // Partial payment
                $new_amount_paid = $nextPayment['amount_paid'] + $remaining_amount;

                $updateSql = "UPDATE payment_schedule
                              SET amount_paid = ?,
                                  status = 'Partial',
                                  updated_at = NOW()
                              WHERE id = ?";
                $updateStmt = $pdo->prepare($updateSql);
                $updateStmt->execute([$new_amount_paid, $payment_id]);
            }
        }

    } catch (PDOException $e) {
        throw new Exception('Failed to update payment schedule: ' . $e->getMessage());
    }
}

/**
 * Apply remaining payment amount to subsequent payments
 */
function applyRemainingAmount($order_id, $remaining_amount)
{
    global $pdo;

    try {
        // Get next pending payments
        $sql = "SELECT * FROM payment_schedule
                WHERE order_id = ? AND status IN ('Pending', 'Partial')
                ORDER BY due_date ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$order_id]);
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($payments as $payment) {
            if ($remaining_amount <= 0) break;

            // Calculate current balance (amount_due - amount_paid)
            $current_balance = $payment['amount_due'] - $payment['amount_paid'];

            if ($remaining_amount >= $current_balance) {
                // Full payment
                $updateSql = "UPDATE payment_schedule
                              SET amount_paid = amount_due,
                                  status = 'Paid',
                                  paid_date = NOW(),
                                  updated_at = NOW()
                              WHERE id = ?";
                $updateStmt = $pdo->prepare($updateSql);
                $updateStmt->execute([$payment['id']]);

                $remaining_amount -= $current_balance;
            } else {
                // Partial payment
                $new_amount_paid = $payment['amount_paid'] + $remaining_amount;

                $updateSql = "UPDATE payment_schedule
                              SET amount_paid = ?,
                                  status = 'Partial',
                                  updated_at = NOW()
                              WHERE id = ?";
                $updateStmt = $pdo->prepare($updateSql);
                $updateStmt->execute([$new_amount_paid, $payment['id']]);

                $remaining_amount = 0;
            }
        }

    } catch (PDOException $e) {
        throw new Exception('Failed to apply remaining amount: ' . $e->getMessage());
    }
}

/**
 * Create notification for payment status change
 */
function createPaymentNotification($customer_id, $payment_number, $status, $amount)
{
    global $pdo;
    
    try {
        $title = "Payment " . ucfirst(strtolower($status));
        $message = "Your payment #{$payment_number} of ₱" . number_format($amount, 2) . " has been {$status}.";
        
        $sql = "INSERT INTO notifications (user_id, title, message, type, related_id) 
                VALUES (?, ?, ?, 'payment', ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$customer_id, $title, $message, $payment_number]);
        
    } catch (PDOException $e) {
        // Log error but don't throw - notification failure shouldn't break payment processing
        error_log('Failed to create payment notification: ' . $e->getMessage());
    }
}

/**
 * Get receipt image for a payment
 * COPIED FROM transactions_backend.php - WORKING VERSION
 */
function getReceipt()
{
    $pdo = $GLOBALS['pdo'];

    $payment_id = (int)($_GET['payment_id'] ?? 0);
    if ($payment_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'payment_id is required']);
        return;
    }

    $sql = "SELECT
                ph.receipt_image,
                ph.receipt_filename
            FROM payment_history ph
            WHERE ph.id = ?";

    $params = [$payment_id];

    // If Sales Agent, ensure the payment belongs to their order
    if (($_SESSION['user_role'] ?? '') === 'Sales Agent' || ($_SESSION['user_role'] ?? '') === 'SalesAgent') {
        $sql .= " AND EXISTS (SELECT 1 FROM orders o WHERE o.order_id = ph.order_id AND o.sales_agent_id = ?)";
        $params[] = $_SESSION['user_id'];
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $receipt = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$receipt) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Payment not found']);
        return;
    }

    // Check if receipt is stored as BLOB (older method)
    if ($receipt['receipt_image']) {
        // Output BLOB image directly
        header_remove('Content-Type');
        $filename = $receipt['receipt_filename'] ?: 'receipt.jpg';
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf'
        ];
        $mime = $mimeTypes[$ext] ?? 'application/octet-stream';

        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . $filename . '"');
        echo $receipt['receipt_image'];
        exit;
    }

    // Check if receipt is stored as file (newer method)
    if ($receipt['receipt_filename']) {
        // Construct file path
        $upload_dir = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'receipts' . DIRECTORY_SEPARATOR;
        $file_path = $upload_dir . $receipt['receipt_filename'];

        // Check if file exists
        if (file_exists($file_path)) {
            // Determine MIME type
            $ext = strtolower(pathinfo($receipt['receipt_filename'], PATHINFO_EXTENSION));
            $mimeTypes = [
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'pdf' => 'application/pdf'
            ];
            $mime = $mimeTypes[$ext] ?? 'application/octet-stream';

            // Output file
            header('Content-Type: ' . $mime);
            header('Content-Disposition: inline; filename="' . basename($receipt['receipt_filename']) . '"');
            header('Content-Length: ' . filesize($file_path));
            readfile($file_path);
            exit;
        }
    }

    // No receipt found
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Receipt not found']);
    return;
}