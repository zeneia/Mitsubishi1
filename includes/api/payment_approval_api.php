<?php
// Payment Approval API
// Handles payment verification and approval operations for loan customers

// Start session and include necessary files
session_start();
require_once dirname(dirname(__DIR__)) . '/includes/database/db_conn.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'Customer';

// Handle GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    try {
        switch ($action) {
            case 'getPendingPayments':
                echo json_encode(getPendingPayments($connect, $user_id, $user_role));
                break;
            case 'getVerifiedPayments':
                echo json_encode(getVerifiedPayments($connect, $user_id, $user_role));
                break;
            case 'getAllLoanCustomers':
                echo json_encode(getAllLoanCustomers($connect, $user_id, $user_role));
                break;
            case 'getPaymentDetails':
                $payment_id = $_GET['payment_id'] ?? 0;
                echo json_encode(getPaymentDetails($connect, $payment_id, $user_id, $user_role));
                break;
            default:
                echo json_encode(['success' => false, 'error' => 'Invalid action']);
        }
    } catch (Exception $e) {
        error_log("Payment API GET Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'approvePayment':
                $payment_id = $_POST['payment_id'] ?? 0;
                echo json_encode(approvePayment($connect, $payment_id, $user_id, $user_role));
                break;
            case 'rejectPayment':
                $payment_id = $_POST['payment_id'] ?? 0;
                $rejection_reason = $_POST['rejection_reason'] ?? '';
                echo json_encode(rejectPayment($connect, $payment_id, $rejection_reason, $user_id, $user_role));
                break;
            default:
                echo json_encode(['success' => false, 'error' => 'Invalid action']);
        }
    } catch (Exception $e) {
        error_log("Payment API POST Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}

/**
 * Get pending payments awaiting verification
 */
function getPendingPayments($connect, $user_id, $user_role) {
    try {
        // Base query - Use orders.sales_agent_id for filtering (same as payment_backend.php)
        $sql = "SELECT ph.*,
                       o.order_number, o.vehicle_model, o.vehicle_variant, o.monthly_payment, o.total_price,
                       ci.firstname, ci.lastname, ci.mobile_number, ci.agent_id,
                       a.FirstName, a.LastName, a.Email, a.Username,
                       agent.FirstName as agent_fname, agent.LastName as agent_lname,
                       v.model_name, v.variant
                FROM payment_history ph
                JOIN orders o ON ph.order_id = o.order_id
                LEFT JOIN customer_information ci ON ph.customer_id = ci.cusID
                LEFT JOIN accounts a ON ci.account_id = a.Id
                LEFT JOIN accounts agent ON o.sales_agent_id = agent.Id
                LEFT JOIN vehicles v ON o.vehicle_id = v.id
                WHERE ph.status = 'Pending'";

        // Add role-based filtering - Use orders.sales_agent_id (same as payment_backend.php)
        // Handle both "SalesAgent" (database format) and "Sales Agent" (display format)
        if ($user_role === 'SalesAgent' || $user_role === 'Sales Agent') {
            $sql .= " AND o.sales_agent_id = ?";
            $params = [$user_id];
        } else {
            $params = [];
        }

        $sql .= " ORDER BY ph.payment_date DESC";

        $stmt = $connect->prepare($sql);
        $stmt->execute($params);
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format the data
        foreach ($payments as &$payment) {
            // Build customer name with fallbacks - FIXED: Check customer_information first
            $customer_name = trim(($payment['firstname'] ?? '') . ' ' . ($payment['lastname'] ?? ''));
            if (empty($customer_name)) {
                $customer_name = trim(($payment['FirstName'] ?? '') . ' ' . ($payment['LastName'] ?? ''));
            }
            if (empty($customer_name)) {
                $customer_name = $payment['Email'] ?? $payment['Username'] ?? 'Customer #' . $payment['customer_id'];
            }
            $payment['customer_name'] = $customer_name;

            $payment['agent_name'] = trim(($payment['agent_fname'] ?? '') . ' ' . ($payment['agent_lname'] ?? ''));
            $payment['vehicle_display'] = ($payment['model_name'] ?? $payment['vehicle_model']) . ' ' . ($payment['variant'] ?? $payment['vehicle_variant'] ?? '');
            $payment['has_receipt'] = !empty($payment['receipt_filename']);

            // Format receipt URL if exists
            if ($payment['has_receipt']) {
                $payment['receipt_url'] = '../../uploads/receipts/' . $payment['receipt_filename'];
            }
        }
        
        return [
            'success' => true,
            'data' => $payments,
            'count' => count($payments)
        ];
        
    } catch (Exception $e) {
        error_log("getPendingPayments Error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Failed to fetch pending payments: ' . $e->getMessage()];
    }
}

/**
 * Get verified/confirmed payments
 */
function getVerifiedPayments($connect, $user_id, $user_role) {
    try {
        // Base query - Use orders.sales_agent_id for filtering (same as payment_backend.php)
        $sql = "SELECT ph.*,
                       o.order_number, o.vehicle_model, o.vehicle_variant, o.monthly_payment,
                       ci.firstname, ci.lastname, ci.mobile_number, ci.agent_id,
                       a.FirstName, a.LastName, a.Email, a.Username,
                       agent.FirstName as agent_fname, agent.LastName as agent_lname,
                       processor.FirstName as processor_fname, processor.LastName as processor_lname,
                       v.model_name, v.variant
                FROM payment_history ph
                JOIN orders o ON ph.order_id = o.order_id
                LEFT JOIN customer_information ci ON ph.customer_id = ci.cusID
                LEFT JOIN accounts a ON ci.account_id = a.Id
                LEFT JOIN accounts agent ON o.sales_agent_id = agent.Id
                LEFT JOIN accounts processor ON ph.processed_by = processor.Id
                LEFT JOIN vehicles v ON o.vehicle_id = v.id
                WHERE ph.status = 'Confirmed'";

        // Add role-based filtering - Use orders.sales_agent_id (same as payment_backend.php)
        // Handle both "SalesAgent" (database format) and "Sales Agent" (display format)
        if ($user_role === 'SalesAgent' || $user_role === 'Sales Agent') {
            $sql .= " AND o.sales_agent_id = ?";
            $params = [$user_id];
        } else {
            $params = [];
        }

        $sql .= " ORDER BY ph.updated_at DESC LIMIT 100";

        $stmt = $connect->prepare($sql);
        $stmt->execute($params);
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format the data
        foreach ($payments as &$payment) {
            // Build customer name with fallbacks - FIXED: Check customer_information first
            $customer_name = trim(($payment['firstname'] ?? '') . ' ' . ($payment['lastname'] ?? ''));
            if (empty($customer_name)) {
                $customer_name = trim(($payment['FirstName'] ?? '') . ' ' . ($payment['LastName'] ?? ''));
            }
            if (empty($customer_name)) {
                $customer_name = $payment['Email'] ?? $payment['Username'] ?? 'Customer #' . $payment['customer_id'];
            }
            $payment['customer_name'] = $customer_name;

            $payment['agent_name'] = trim(($payment['agent_fname'] ?? '') . ' ' . ($payment['agent_lname'] ?? ''));
            $payment['processor_name'] = trim(($payment['processor_fname'] ?? '') . ' ' . ($payment['processor_lname'] ?? ''));
            $payment['vehicle_display'] = ($payment['model_name'] ?? $payment['vehicle_model']) . ' ' . ($payment['variant'] ?? $payment['vehicle_variant'] ?? '');
            $payment['has_receipt'] = !empty($payment['receipt_filename']);

            if ($payment['has_receipt']) {
                $payment['receipt_url'] = '../../uploads/receipts/' . $payment['receipt_filename'];
            }
        }
        
        return [
            'success' => true,
            'data' => $payments,
            'count' => count($payments)
        ];
        
    } catch (Exception $e) {
        error_log("getVerifiedPayments Error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Failed to fetch verified payments: ' . $e->getMessage()];
    }
}

/**
 * Get all loan customers with payment summary
 */
function getAllLoanCustomers($connect, $user_id, $user_role) {
    try {
        // Base query - Use orders.sales_agent_id for filtering (same as payment_backend.php)
        $sql = "SELECT o.order_id, o.order_number, o.customer_id, o.vehicle_model, o.vehicle_variant,
                       o.total_price, o.down_payment, o.monthly_payment, o.financing_term, o.order_date,
                       ci.firstname, ci.lastname, ci.mobile_number, ci.agent_id,
                       a.FirstName, a.LastName, a.Email,
                       agent.FirstName as agent_fname, agent.LastName as agent_lname,
                       v.model_name, v.variant,
                       COUNT(DISTINCT ps.id) as total_payments_due,
                       COUNT(DISTINCT CASE WHEN ph.status = 'Confirmed' THEN ph.id END) as payments_made,
                       SUM(CASE WHEN ps.status = 'Pending' AND ps.due_date < NOW() THEN 1 ELSE 0 END) as overdue_payments,
                       MIN(CASE WHEN ps.status = 'Pending' THEN ps.due_date END) as next_due_date,
                       COALESCE(SUM(CASE WHEN ph.status = 'Confirmed' THEN ph.amount_paid ELSE 0 END), 0) as total_paid
                FROM orders o
                LEFT JOIN customer_information ci ON o.customer_id = ci.cusID
                LEFT JOIN accounts a ON ci.account_id = a.Id
                LEFT JOIN accounts agent ON o.sales_agent_id = agent.Id
                LEFT JOIN vehicles v ON o.vehicle_id = v.id
                LEFT JOIN payment_schedule ps ON o.order_id = ps.order_id
                LEFT JOIN payment_history ph ON o.order_id = ph.order_id
                WHERE o.payment_method = 'financing' AND ci.cusID IS NOT NULL";

        // Add role-based filtering - Use orders.sales_agent_id (same as payment_backend.php)
        // Handle both "SalesAgent" (database format) and "Sales Agent" (display format)
        if ($user_role === 'SalesAgent' || $user_role === 'Sales Agent') {
            $sql .= " AND o.sales_agent_id = ?";
            $params = [$user_id];
        } else {
            $params = [];
        }

        // GROUP BY all non-aggregated columns to comply with ONLY_FULL_GROUP_BY
        $sql .= " GROUP BY o.order_id, o.order_number, o.customer_id, o.vehicle_model, o.vehicle_variant,
                         o.total_price, o.down_payment, o.monthly_payment, o.financing_term, o.order_date,
                         ci.firstname, ci.lastname, ci.mobile_number, ci.agent_id,
                         a.FirstName, a.LastName, a.Email,
                         agent.FirstName, agent.LastName,
                         v.model_name, v.variant
                  ORDER BY o.order_date DESC";
        
        $stmt = $connect->prepare($sql);
        $stmt->execute($params);
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format the data
        foreach ($customers as &$customer) {
            // Build customer name with fallbacks - FIXED: Check customer_information first
            $customer_name = trim(($customer['firstname'] ?? '') . ' ' . ($customer['lastname'] ?? ''));
            if (empty($customer_name)) {
                $customer_name = trim(($customer['FirstName'] ?? '') . ' ' . ($customer['LastName'] ?? ''));
            }
            if (empty($customer_name)) {
                $customer_name = $customer['Email'] ?? 'Customer #' . $customer['customer_id'];
            }
            $customer['customer_name'] = $customer_name;

            $customer['agent_name'] = trim(($customer['agent_fname'] ?? '') . ' ' . ($customer['agent_lname'] ?? ''));
            $customer['vehicle_display'] = ($customer['model_name'] ?? $customer['vehicle_model']) . ' ' . ($customer['variant'] ?? $customer['vehicle_variant'] ?? '');
            
            // Calculate payment status
            $payments_made = (int)($customer['payments_made'] ?? 0);
            $total_due = (int)($customer['total_payments_due'] ?? 0);
            $overdue = (int)($customer['overdue_payments'] ?? 0);

            // If no payment schedule exists, use financing_term as total_due
            if ($total_due === 0 && !empty($customer['financing_term'])) {
                $total_due = (int)$customer['financing_term'];
                $customer['total_payments_due'] = $total_due;
            }

            if ($overdue > 0) {
                $customer['payment_status'] = 'overdue';
                $customer['payment_status_label'] = 'Overdue';
            } elseif ($payments_made >= $total_due && $total_due > 0) {
                $customer['payment_status'] = 'completed';
                $customer['payment_status_label'] = 'Completed';
            } elseif ($payments_made > 0) {
                $customer['payment_status'] = 'in_progress';
                $customer['payment_status_label'] = 'In Progress';
            } else {
                $customer['payment_status'] = 'pending';
                $customer['payment_status_label'] = 'Pending';
            }

            // Calculate progress percentage based on amount paid vs total price (same as order details page)
            $total_price = (float)($customer['total_price'] ?? 0);
            $total_paid = (float)($customer['total_paid'] ?? 0);
            $customer['payment_progress'] = $total_price > 0 ? round(($total_paid / $total_price) * 100, 2) : 0;

            // If no next_due_date from payment_schedule, calculate it based on order date and payments made
            if (empty($customer['next_due_date']) && $payments_made < $total_due && !empty($customer['order_date'])) {
                try {
                    $order_date = new DateTime($customer['order_date']);
                    // Calculate next payment number (payments_made + 1)
                    $next_payment_number = $payments_made + 1;
                    // Add months to order date
                    $order_date->modify("+{$next_payment_number} months");
                    $customer['next_due_date'] = $order_date->format('Y-m-d');
                } catch (Exception $e) {
                    // If date calculation fails, leave it as null
                    $customer['next_due_date'] = null;
                }
            }

            // Calculate balance
            $customer['balance'] = $customer['total_price'] - ($customer['down_payment'] ?? 0) - ($customer['total_paid'] ?? 0);
        }
        
        return [
            'success' => true,
            'data' => $customers,
            'count' => count($customers)
        ];
        
    } catch (Exception $e) {
        error_log("getAllLoanCustomers Error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Failed to fetch loan customers: ' . $e->getMessage()];
    }
}

/**
 * Get detailed payment information
 */
function getPaymentDetails($connect, $payment_id, $user_id, $user_role) {
    try {
        // Base query - Use orders.sales_agent_id for filtering (same as payment_backend.php)
        $sql = "SELECT ph.*,
                       o.order_number, o.vehicle_model, o.vehicle_variant, o.monthly_payment, o.order_id,
                       ci.firstname, ci.lastname, ci.mobile_number, ci.agent_id,
                       a.FirstName, a.LastName, a.Email, a.Username,
                       agent.FirstName as agent_fname, agent.LastName as agent_lname,
                       processor.FirstName as processor_fname, processor.LastName as processor_lname,
                       v.model_name, v.variant
                FROM payment_history ph
                JOIN orders o ON ph.order_id = o.order_id
                LEFT JOIN customer_information ci ON ph.customer_id = ci.cusID
                LEFT JOIN accounts a ON ci.account_id = a.Id
                LEFT JOIN accounts agent ON o.sales_agent_id = agent.Id
                LEFT JOIN accounts processor ON ph.processed_by = processor.Id
                LEFT JOIN vehicles v ON o.vehicle_id = v.id
                WHERE ph.id = ?";

        $params = [$payment_id];

        // Add role-based filtering for Sales Agents - Use orders.sales_agent_id (same as payment_backend.php)
        // Handle both "SalesAgent" (database format) and "Sales Agent" (display format)
        if ($user_role === 'SalesAgent' || $user_role === 'Sales Agent') {
            $sql .= " AND o.sales_agent_id = ?";
            $params[] = $user_id;
        }

        $stmt = $connect->prepare($sql);
        $stmt->execute($params);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$payment) {
            return ['success' => false, 'error' => 'Payment not found or access denied'];
        }

        // Format the data
        // Build customer name with fallbacks - FIXED: Check customer_information first
        $customer_name = trim(($payment['firstname'] ?? '') . ' ' . ($payment['lastname'] ?? ''));
        if (empty($customer_name)) {
            $customer_name = trim(($payment['FirstName'] ?? '') . ' ' . ($payment['LastName'] ?? ''));
        }
        if (empty($customer_name)) {
            $customer_name = $payment['Email'] ?? $payment['Username'] ?? 'Customer #' . $payment['customer_id'];
        }
        $payment['customer_name'] = $customer_name;

        $payment['customer_mobile'] = $payment['mobile_number'] ?? 'N/A';
        $payment['customer_email'] = $payment['Email'] ?? 'N/A';
        $payment['agent_name'] = trim(($payment['agent_fname'] ?? '') . ' ' . ($payment['agent_lname'] ?? ''));
        $payment['processor_name'] = trim(($payment['processor_fname'] ?? '') . ' ' . ($payment['processor_lname'] ?? ''));
        $payment['vehicle_model'] = $payment['model_name'] ?? $payment['vehicle_model'];
        $payment['vehicle_variant'] = $payment['variant'] ?? $payment['vehicle_variant'] ?? '';
        $payment['has_receipt'] = !empty($payment['receipt_filename']);

        if ($payment['has_receipt']) {
            $payment['receipt_url'] = '../../uploads/receipts/' . $payment['receipt_filename'];
        }
        
        return [
            'success' => true,
            'data' => $payment
        ];
        
    } catch (Exception $e) {
        error_log("getPaymentDetails Error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Failed to fetch payment details: ' . $e->getMessage()];
    }
}

/**
 * Approve a payment
 */
function approvePayment($connect, $payment_id, $user_id, $user_role) {
    // Only Sales Agents can approve payments - check this before starting transaction
    // Handle both "SalesAgent" (database format) and "Sales Agent" (display format)
    if ($user_role !== 'SalesAgent' && $user_role !== 'Sales Agent') {
        return ['success' => false, 'error' => 'Unauthorized: Only Sales Agents can approve payments'];
    }

    try {
        $connect->beginTransaction();

        // Get payment details first - Use orders.sales_agent_id (same as payment_backend.php)
        $stmt = $connect->prepare("SELECT ph.*, o.order_id, o.sales_agent_id
                                    FROM payment_history ph
                                    JOIN orders o ON ph.order_id = o.order_id
                                    WHERE ph.id = ? AND ph.status = 'Pending'");
        $stmt->execute([$payment_id]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$payment) {
            throw new Exception('Payment not found or already processed');
        }

        // Check authorization - Sales Agents can only approve payments for their assigned customers
        // Use orders.sales_agent_id (same as payment_backend.php)
        if ($user_role === 'SalesAgent' || $user_role === 'Sales Agent') {
            if ($payment['sales_agent_id'] != $user_id) {
                throw new Exception('Unauthorized: You can only approve payments for your assigned customers');
            }
        }
        
        // Update payment status
        $stmt = $connect->prepare("UPDATE payment_history 
                                    SET status = 'Confirmed', 
                                        processed_by = ?,
                                        updated_at = NOW()
                                    WHERE id = ?");
        $stmt->execute([$user_id, $payment_id]);
        
        // Update payment schedule - mark next pending payment as paid
        $amount_paid = $payment['amount_paid'];
        $order_id = $payment['order_id'];
        
        // Get pending payment schedule entries
        $stmt = $connect->prepare("SELECT * FROM payment_schedule 
                                    WHERE order_id = ? AND status = 'Pending'
                                    ORDER BY due_date ASC");
        $stmt->execute([$order_id]);
        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Apply payment to schedule
        $remaining_amount = $amount_paid;
        foreach ($schedules as $schedule) {
            if ($remaining_amount <= 0) break;

            $amount_due = $schedule['amount_due'];
            $already_paid = $schedule['amount_paid'] ?? 0;
            // Calculate current balance (amount_due - amount_paid)
            $current_balance = $amount_due - $already_paid;

            if ($remaining_amount >= $current_balance) {
                // Full payment for this schedule
                $stmt = $connect->prepare("UPDATE payment_schedule
                                           SET amount_paid = amount_due,
                                               status = 'Paid',
                                               paid_date = NOW(),
                                               updated_at = NOW()
                                           WHERE id = ?");
                $stmt->execute([$schedule['id']]);
                $remaining_amount -= $current_balance;
            } else {
                // Partial payment
                $new_paid = $already_paid + $remaining_amount;
                $stmt = $connect->prepare("UPDATE payment_schedule
                                           SET amount_paid = ?,
                                               status = 'Partial',
                                               updated_at = NOW()
                                           WHERE id = ?");
                $stmt->execute([$new_paid, $schedule['id']]);
                $remaining_amount = 0;
            }
        }
        
        // Create notification for customer (in-app)
        require_once dirname(__DIR__) . '/api/notification_api.php';
        createNotification(
            $payment['customer_id'],
            null,
            'Payment Confirmed',
            'Your payment of ₱' . number_format($payment['amount_paid'], 2) . ' has been confirmed.',
            'payment',
            $payment_id
        );

        // Send email and SMS notifications
        try {
            require_once dirname(__DIR__) . '/services/NotificationService.php';
            $notificationService = new NotificationService($connect);
            $notificationService->sendPaymentConfirmationNotification($payment_id);
        } catch (Exception $notifError) {
            // Log error but don't fail the approval
            error_log("Payment confirmation notification error: " . $notifError->getMessage());
        }

        $connect->commit();

        return [
            'success' => true,
            'message' => 'Payment approved successfully'
        ];
        
    } catch (Exception $e) {
        $connect->rollBack();
        error_log("approvePayment Error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Failed to approve payment: ' . $e->getMessage()];
    }
}

/**
 * Reject a payment
 */
function rejectPayment($connect, $payment_id, $rejection_reason, $user_id, $user_role) {
    // Only Sales Agents can reject payments - check this before starting transaction
    // Handle both "SalesAgent" (database format) and "Sales Agent" (display format)
    if ($user_role !== 'SalesAgent' && $user_role !== 'Sales Agent') {
        return ['success' => false, 'error' => 'Unauthorized: Only Sales Agents can reject payments'];
    }

    try {
        $connect->beginTransaction();

        // Get payment details first - Use orders.sales_agent_id (same as payment_backend.php)
        $stmt = $connect->prepare("SELECT ph.*, o.order_id, o.sales_agent_id
                                    FROM payment_history ph
                                    JOIN orders o ON ph.order_id = o.order_id
                                    WHERE ph.id = ? AND ph.status = 'Pending'");
        $stmt->execute([$payment_id]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$payment) {
            throw new Exception('Payment not found or already processed');
        }

        // Check authorization - Sales Agents can only reject payments for their assigned customers
        // Use orders.sales_agent_id (same as payment_backend.php)
        if ($user_role === 'SalesAgent' || $user_role === 'Sales Agent') {
            if ($payment['sales_agent_id'] != $user_id) {
                throw new Exception('Unauthorized: You can only reject payments for your assigned customers');
            }
        }
        
        // Update payment status
        $updated_notes = ($payment['notes'] ?? '') . "\n\n[REJECTED by " . $user_role . " on " . date('Y-m-d H:i:s') . "]\nReason: " . $rejection_reason;
        
        $stmt = $connect->prepare("UPDATE payment_history 
                                    SET status = 'Failed', 
                                        notes = ?,
                                        processed_by = ?,
                                        updated_at = NOW()
                                    WHERE id = ?");
        $stmt->execute([$updated_notes, $user_id, $payment_id]);
        
        // Create notification for customer (in-app)
        require_once dirname(__DIR__) . '/api/notification_api.php';
        createNotification(
            $payment['customer_id'],
            null,
            'Payment Rejected',
            'Your payment submission has been rejected. Reason: ' . $rejection_reason,
            'payment',
            $payment_id
        );

        // Send email and SMS notifications
        try {
            require_once dirname(__DIR__) . '/services/NotificationService.php';
            $notificationService = new NotificationService($connect);
            $notificationService->sendPaymentRejectionNotification($payment_id, $rejection_reason);
        } catch (Exception $notifError) {
            // Log error but don't fail the rejection
            error_log("Payment rejection notification error: " . $notifError->getMessage());
        }

        $connect->commit();

        return [
            'success' => true,
            'message' => 'Payment rejected successfully'
        ];
        
    } catch (Exception $e) {
        $connect->rollBack();
        error_log("rejectPayment Error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Failed to reject payment: ' . $e->getMessage()];
    }
}
