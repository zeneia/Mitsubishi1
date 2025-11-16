<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Ensure JSON header and fatal error handling are registered BEFORE any includes
if (!headers_sent()) {
    header('Content-Type: application/json');
}

// Start output buffering early to prevent partial/empty responses on errors
// Track output length to detect empty responses
if (!isset($GLOBALS['__ORDER_BACKEND_OUTPUT_LEN__'])) {
    $GLOBALS['__ORDER_BACKEND_OUTPUT_LEN__'] = 0;
}
// Add a flag to mark when we have emitted a JSON response body
if (!isset($GLOBALS['__ORDER_BACKEND_RESP_SENT__'])) {
    $GLOBALS['__ORDER_BACKEND_RESP_SENT__'] = false;
}
if (ob_get_level() === 0) {
    ob_start(function ($buffer) {
        // Count only non-whitespace characters
        $len = strlen(trim($buffer));
        if ($len > 0) {
            $GLOBALS['__ORDER_BACKEND_OUTPUT_LEN__'] += $len;
            error_log('order_backend: buffer callback tracked ' . $len . ' chars, total=' . $GLOBALS['__ORDER_BACKEND_OUTPUT_LEN__']);
        }
        return $buffer; // Always return the buffer unchanged
    });
}

// Register shutdown function for fatal errors and empty output fallback
if (!defined('ORDER_BACKEND_SHUTDOWN')) {
    define('ORDER_BACKEND_SHUTDOWN', 1);
    register_shutdown_function(function () {
        $err = error_get_last();
        $hasFatal = $err && in_array($err['type'] ?? 0, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true);
        $outputLen = (int)($GLOBALS['__ORDER_BACKEND_OUTPUT_LEN__'] ?? 0);

        if ($hasFatal) {
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json');
            }
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            echo json_encode([
                'success' => false,
                'error' => 'Server error occurred while processing request',
                'debug' => [
                    'message' => $err['message'] ?? null,
                    'type' => $err['type'] ?? null,
                    'file' => basename($err['file'] ?? ''),
                    'line' => $err['line'] ?? null
                ]
            ], JSON_INVALID_UTF8_SUBSTITUTE);
            exit;
        }

        // Fallback: If no output was produced at all, emit a minimal JSON error
        $dbg_action = $GLOBALS['__ORDER_BACKEND_ACTION__'] ?? null;
        $dbg_headers_sent = headers_sent();
        $dbg_ob_level = ob_get_level();
        $dbg_method = $_SERVER['REQUEST_METHOD'] ?? null;
        $dbg_uri = $_SERVER['REQUEST_URI'] ?? null;
        $dbg_session_user = isset($_SESSION) && isset($_SESSION['user_id']);
        
        error_log('order_backend: shutdown function called - outputLen=' . $outputLen . ' headers_sent=' . ($dbg_headers_sent ? '1' : '0') . ' action=' . ($dbg_action ?? 'null'));
        
        // Emit fallback only if we truly haven't sent a response and headers are not yet sent
        if ($outputLen === 0 && empty($GLOBALS['__ORDER_BACKEND_RESP_SENT__']) && !headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json');
            error_log('order_backend: EMPTY_OUTPUT_FALLBACK fired action=' . ($dbg_action ?? 'null') . ' ob_level=' . $dbg_ob_level . ' headers_sent=' . ($dbg_headers_sent ? '1' : '0') . ' method=' . $dbg_method . ' uri=' . $dbg_uri . ' session_user_id_set=' . ($dbg_session_user ? '1' : '0'));
            echo json_encode([
                'success' => false,
                'error' => 'The server generated an empty response. Please try again or contact support.',
                'debug' => [
                    'action' => $dbg_action,
                    'ob_level' => $dbg_ob_level,
                    'headers_sent' => $dbg_headers_sent,
                    'method' => $dbg_method,
                    'uri' => $dbg_uri,
                    'session_user_id_set' => $dbg_session_user
                ],
            ], JSON_INVALID_UTF8_SUBSTITUTE);
        } elseif ($outputLen === 0 && empty($GLOBALS['__ORDER_BACKEND_RESP_SENT__']) && headers_sent()) {
            // Headers already sent; do not append to body to avoid corrupting JSON; just log
            error_log('order_backend: Headers sent but no output tracked - possible buffer issue; NOT appending fallback body');
        }
    });
}

session_start();

// Include database connection first
include_once(dirname(__DIR__) . '/database/db_conn.php');

// Only include loan_backend.php for function definitions, not API handling
if (!function_exists('calculateLoanAmortization')) {
    // Define the path to avoid the API section
    $loan_backend_path = dirname(__DIR__) . '/backend/loan_backend.php';
    if (file_exists($loan_backend_path)) {
        // Temporarily set a flag to prevent API processing in loan_backend
        $SKIP_LOAN_API_PROCESSING = true;
        include_once($loan_backend_path);
        unset($SKIP_LOAN_API_PROCESSING);
    }
}

// Re-assert development error visibility in case includes changed settings
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
	error_log('order_backend: No user_id in session');
	http_response_code(403);
	$response = json_encode(['success' => false, 'error' => 'Unauthorized access'], JSON_INVALID_UTF8_SUBSTITUTE);
	$GLOBALS['__ORDER_BACKEND_OUTPUT_LEN__'] += strlen(trim((string)$response));
	echo $response;
	$GLOBALS['__ORDER_BACKEND_RESP_SENT__'] = true;
	// Flush and exit to ensure a response body is sent
	if (ob_get_level() > 0) { 
		ob_end_flush(); 
	}
	exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
// Track action for shutdown debug
$GLOBALS['__ORDER_BACKEND_ACTION__'] = $action;
error_log('order_backend: routing action=' . $action);

switch ($action) {
	case 'get_customer_orders':
		getCustomerOrders();
		break;
	case 'get_order_details':
		getOrderDetails();
		break;
	case 'get_payment_history':
		getPaymentHistory();
		break;
	case 'get_payment_schedule':
		getPaymentSchedule();
		break;
	case 'submit_payment':
		submitPayment();
		break;
	default:
		error_log('order_backend: Invalid action: ' . $action);
		http_response_code(400);
		$response = json_encode(['success' => false, 'error' => 'Invalid action', 'received_action' => $action], JSON_INVALID_UTF8_SUBSTITUTE);
		$GLOBALS['__ORDER_BACKEND_OUTPUT_LEN__'] += strlen(trim((string)$response));
		echo $response;
		$GLOBALS['__ORDER_BACKEND_RESP_SENT__'] = true;
		if (ob_get_level() > 0) { 
			ob_end_flush(); 
		}
		exit;
}

function getCustomerOrders()
{
	global $connect;

	// Debug: Ensure we have proper connection
	if (!$connect) {
		error_log('order_backend:get_customer_orders database connection is null');
		echo json_encode([
			'success' => false,
			'error' => 'Database connection failed'
		]);
		if (ob_get_level() > 0) { 
			ob_end_flush(); 
		}
		exit;
	}

	$account_id = $_SESSION['user_id'];
	error_log('order_backend:get_customer_orders start account_id=' . $account_id);

	try {
		// First get the cusID from customer_information table using account_id
		$cusStmt = $connect->prepare("SELECT cusID FROM customer_information WHERE account_id = ?");
		$cusStmt->execute([$account_id]);
		$customer = $cusStmt->fetch(PDO::FETCH_ASSOC);
		
		if (!$customer) {
			// Check if user exists in accounts table but no customer profile
			$accountStmt = $connect->prepare("SELECT Id, Username, Role FROM accounts WHERE Id = ?");
			$accountStmt->execute([$account_id]);
			$account = $accountStmt->fetch(PDO::FETCH_ASSOC);
			
			if ($account) {
				$response = json_encode([
					'success' => true,
					'data' => [],
					'message' => 'No customer profile found. Please complete your profile first.',
					'debug' => [
						'account_id' => $account_id,
						'account_found' => true,
						'username' => $account['Username'],
						'role' => $account['Role']
					]
				]);
			} else {
				$response = json_encode([
					'success' => false,
					'error' => 'Account not found',
					'debug' => [
						'account_id' => $account_id,
						'session_data' => $_SESSION
					]
				]);
			}
			$GLOBALS['__ORDER_BACKEND_OUTPUT_LEN__'] += strlen(trim((string)$response));
			echo $response;
			if (ob_get_level() > 0) { ob_end_flush(); }
			exit;
		}
		
		$customer_id = $customer['cusID'];
		error_log('order_backend:get_customer_orders resolved customer_id=' . $customer_id);

		// Check if payment_history table exists (hardened against query failures)
		$tableExists = false;
		try {
			$checkTable = $connect->query("SHOW TABLES LIKE 'payment_history'");
			$tableExists = ($checkTable instanceof PDOStatement) ? ($checkTable->rowCount() > 0) : false;
			error_log('order_backend:get_customer_orders payment_history_exists=' . ($tableExists ? '1' : '0'));
		} catch (Throwable $te) {
			// In PHP 8, TypeError/Error won't be caught by Exception; catch Throwable for safety
			error_log('order_backend:get_customer_orders table check error: ' . $te->getMessage());
			$tableExists = false;
		}

		if ($tableExists) {
			$sql = "SELECT 
                        o.order_id as id,
                        o.order_number,
                        o.customer_id,
                        o.sales_agent_id as agent_id,
                        o.vehicle_id,
                        o.vehicle_model as model_name,
                        o.vehicle_variant as variant,
                        o.model_year as year_model,
                        o.base_price,
                        o.discount_amount,
                        o.total_price as total_amount,
                        o.payment_method,
                        o.down_payment,
                        o.financing_term,
                        o.monthly_payment,
                        o.order_status as status,
                        o.delivery_date,
                        o.actual_delivery_date,
                        o.created_at,
                        v.main_image,
                        COALESCE(SUM(ph.amount_paid), 0) as total_paid,
                        (o.total_price - COALESCE(SUM(ph.amount_paid), 0)) as remaining_balance,
                        ROUND((COALESCE(SUM(ph.amount_paid), 0) / o.total_price) * 100, 2) as payment_progress
                    FROM orders o
                    LEFT JOIN vehicles v ON o.vehicle_id = v.id
                    LEFT JOIN payment_history ph ON o.order_id = ph.order_id AND ph.status = 'Confirmed'
                    WHERE o.customer_id = ?
                    GROUP BY o.order_id
                    ORDER BY o.created_at DESC";
		} else {
			// Fallback query without payment_history table
			$sql = "SELECT 
                        o.order_id as id,
                        o.order_number,
                        o.customer_id,
                        o.sales_agent_id as agent_id,
                        o.vehicle_id,
                        o.vehicle_model as model_name,
                        o.vehicle_variant as variant,
                        o.model_year as year_model,
                        o.base_price,
                        o.discount_amount,
                        o.total_price as total_amount,
                        o.payment_method,
                        o.down_payment,
                        o.financing_term,
                        o.monthly_payment,
                        o.order_status as status,
                        o.delivery_date,
                        o.actual_delivery_date,
                        o.created_at,
                        v.main_image,
                        0 as total_paid,
                        o.total_price as remaining_balance,
                        0 as payment_progress
                    FROM orders o
                    LEFT JOIN vehicles v ON o.vehicle_id = v.id
                    WHERE o.customer_id = ?
                    ORDER BY o.created_at DESC";
		}

		error_log('order_backend:get_customer_orders executing orders query (with' . ($tableExists ? '' : 'out') . ' payments)');
		$stmt = $connect->prepare($sql);
		$stmt->execute([$customer_id]);
		$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$errorInfo = $stmt->errorInfo();
		if (!empty($errorInfo[1])) {
			error_log('order_backend:get_customer_orders SQL error: ' . implode(' | ', $errorInfo));
		}

		// Handle main_image - check if it's a file path or binary data
		if (is_array($orders)) {
			foreach ($orders as $idx => $o) {
				if (array_key_exists('main_image', $o) && !is_null($o['main_image']) && $o['main_image'] !== '') {
					// If it looks like a file path, keep it as is
					if (strpos($o['main_image'], 'uploads') !== false || strpos($o['main_image'], '.png') !== false || strpos($o['main_image'], '.jpg') !== false || strpos($o['main_image'], '.jpeg') !== false) {
						// It's a file path, keep as is
						$orders[$idx]['main_image'] = $o['main_image'];
					} else {
						// It's binary data, convert to base64
						$orders[$idx]['main_image'] = base64_encode($o['main_image']);
					}
				}
			}
		}

		error_log('order_backend:get_customer_orders success orders_count=' . count($orders));
		$response = json_encode([
			'success' => true,
			'data' => $orders
		], JSON_INVALID_UTF8_SUBSTITUTE);
		
		// Properly track output length before sending
		$GLOBALS['__ORDER_BACKEND_OUTPUT_LEN__'] += strlen(trim((string)$response));
		echo $response;
		if (ob_get_level() > 0) { ob_end_flush(); }
		exit;
	} catch (Throwable $e) {
		// Catch all throwables to prevent empty-response fallback
		error_log('order_backend:get_customer_orders exception: ' . $e->getMessage());
		$response = json_encode([
			'success' => false,
			'error' => 'Failed to fetch orders: ' . $e->getMessage()
		]);
		$GLOBALS['__ORDER_BACKEND_OUTPUT_LEN__'] += strlen(trim((string)$response));
		echo $response;
		if (ob_get_level() > 0) { ob_end_flush(); }
		exit;
	}
}

function getOrderDetails()
{
	global $connect;

	$order_id = $_GET['order_id'] ?? null;
	$account_id = $_SESSION['user_id'];

	if (!$order_id) {
		echo json_encode(['success' => false, 'error' => 'Order ID required']);
		if (ob_get_level() > 0) { ob_end_flush(); }
		exit;
	}

	try {
		// First get the cusID from customer_information table using account_id
		$cusStmt = $connect->prepare("SELECT cusID FROM customer_information WHERE account_id = ?");
		$cusStmt->execute([$account_id]);
		$customer = $cusStmt->fetch(PDO::FETCH_ASSOC);
		
		if (!$customer) {
			echo json_encode(['success' => false, 'error' => 'Customer profile not found']);
			if (ob_get_level() > 0) { ob_end_flush(); }
			exit;
		}
		
		$customer_id = $customer['cusID'];

		// Check if payment_history table exists
		$tableExists = false;
		try {
			$checkTable = $connect->query("SHOW TABLES LIKE 'payment_history'");
			$tableExists = $checkTable->rowCount() > 0;
		} catch (Exception $e) {
			// Table doesn't exist, continue without payment data
		}

		if ($tableExists) {
			$sql = "SELECT
                        o.order_id as id,
                        o.order_number,
                        o.customer_id,
                        o.sales_agent_id as agent_id,
                        o.vehicle_id,
                        o.vehicle_model as model_name,
                        o.vehicle_variant as variant,
                        o.model_year as year_model,
                        o.vehicle_color,
                        o.delivery_address,
                        o.base_price,
                        o.body_package_price,
                        o.aircon_package_price,
                        o.white_color_surcharge,
                        o.other_charges,
                        o.total_unit_price,
                        o.nominal_discount,
                        o.promo_discount,
                        o.discount_amount,
                        o.amount_to_invoice,
                        o.total_price as total_amount,
                        o.payment_method,
                        o.finance_percentage,
                        o.amount_finance,
                        o.down_payment_percentage,
                        o.down_payment,
                        o.net_down_payment,
                        o.financing_term,
                        o.monthly_payment,
                        o.insurance_premium,
                        o.cptl_premium,
                        o.lto_registration,
                        o.chattel_mortgage_fee,
                        o.chattel_income,
                        o.extended_warranty,
                        o.total_incidentals,
                        o.reservation_fee,
                        o.total_cash_outlay,
                        o.order_status as status,
                        o.delivery_date,
                        o.actual_delivery_date,
                        o.created_at,
                        CONCAT(agent.FirstName, ' ', agent.LastName) as agent_name,
                        agent.Email as agent_email,
                        COALESCE(SUM(ph.amount_paid), 0) as total_paid,
                        (o.total_price - COALESCE(SUM(ph.amount_paid), 0)) as remaining_balance,
                        ROUND((COALESCE(SUM(ph.amount_paid), 0) / o.total_price) * 100, 2) as payment_progress
                    FROM orders o
                    LEFT JOIN accounts agent ON o.sales_agent_id = agent.Id
                    LEFT JOIN payment_history ph ON o.order_id = ph.order_id AND ph.status = 'Confirmed'
                    WHERE o.order_id = ? AND o.customer_id = ?
                    GROUP BY o.order_id, agent.Id";
		} else {
			// Fallback query without payment_history table
			$sql = "SELECT
                        o.order_id as id,
                        o.order_number,
                        o.customer_id,
                        o.sales_agent_id as agent_id,
                        o.vehicle_id,
                        o.vehicle_model as model_name,
                        o.vehicle_variant as variant,
                        o.model_year as year_model,
                        o.vehicle_color,
                        o.delivery_address,
                        o.base_price,
                        o.body_package_price,
                        o.aircon_package_price,
                        o.white_color_surcharge,
                        o.other_charges,
                        o.total_unit_price,
                        o.nominal_discount,
                        o.promo_discount,
                        o.discount_amount,
                        o.amount_to_invoice,
                        o.total_price as total_amount,
                        o.payment_method,
                        o.finance_percentage,
                        o.amount_finance,
                        o.down_payment_percentage,
                        o.down_payment,
                        o.net_down_payment,
                        o.financing_term,
                        o.monthly_payment,
                        o.insurance_premium,
                        o.cptl_premium,
                        o.lto_registration,
                        o.chattel_mortgage_fee,
                        o.chattel_income,
                        o.extended_warranty,
                        o.total_incidentals,
                        o.reservation_fee,
                        o.total_cash_outlay,
                        o.order_status as status,
                        o.delivery_date,
                        o.actual_delivery_date,
                        o.created_at,
                        CONCAT(agent.FirstName, ' ', agent.LastName) as agent_name,
                        agent.Email as agent_email,
                        0 as total_paid,
                        o.total_price as remaining_balance,
                        0 as payment_progress
                    FROM orders o
                    LEFT JOIN accounts agent ON o.sales_agent_id = agent.Id
                    WHERE o.order_id = ? AND o.customer_id = ?";
		}

		$stmt = $connect->prepare($sql);
		$stmt->execute([$order_id, $customer_id]);
		$order = $stmt->fetch(PDO::FETCH_ASSOC);

		if (!$order) {
			echo json_encode(['success' => false, 'error' => 'Order not found']);
			if (ob_get_level() > 0) { ob_end_flush(); }
			exit;
		}

		echo json_encode([
			'success' => true,
			'data' => $order
		]);
		if (ob_get_level() > 0) { ob_end_flush(); }
		exit;
	} catch (Exception $e) {
		echo json_encode([
			'success' => false,
			'error' => 'Failed to fetch order details: ' . $e->getMessage()
		]);
		if (ob_get_level() > 0) { ob_end_flush(); }
		exit;
	}
}

function getPaymentHistory()
{
	global $connect;

	$order_id = $_GET['order_id'] ?? null;
	$account_id = $_SESSION['user_id'];

	if (!$order_id) {
		echo json_encode(['success' => false, 'error' => 'Order ID required']);
		if (ob_get_level() > 0) { ob_end_flush(); }
		exit;
	}

	try {
		// First get the cusID from customer_information table using account_id
		$cusStmt = $connect->prepare("SELECT cusID FROM customer_information WHERE account_id = ?");
		$cusStmt->execute([$account_id]);
		$customer = $cusStmt->fetch(PDO::FETCH_ASSOC);
		
		if (!$customer) {
			echo json_encode(['success' => false, 'error' => 'Customer profile not found']);
			if (ob_get_level() > 0) { ob_end_flush(); }
			exit;
		}

		$customer_id = $customer['cusID'];

		// Verify order belongs to customer
		$verify_sql = "SELECT order_id FROM orders WHERE order_id = ? AND customer_id = ?";
		$verify_stmt = $connect->prepare($verify_sql);
		$verify_stmt->execute([$order_id, $customer_id]);

		if (!$verify_stmt->fetch()) {
			echo json_encode(['success' => false, 'error' => 'Order not found']);
			if (ob_get_level() > 0) { ob_end_flush(); }
			exit;
		}

		// Check if payment_history table exists
		$tableExists = false;
		try {
			$checkTable = $connect->query("SHOW TABLES LIKE 'payment_history'");
			$tableExists = $checkTable->rowCount() > 0;
		} catch (Exception $e) {
			// Table doesn't exist, return empty array
		}

		if ($tableExists) {
			$sql = "SELECT
                        ph.*,
                        CONCAT(processor.FirstName, ' ', processor.LastName) as processed_by_name,
                        (SELECT COALESCE(SUM(ph2.amount_paid), 0)
                         FROM payment_history ph2
                         WHERE ph2.order_id = ph.order_id
                         AND ph2.status = 'Confirmed'
                         AND ph2.payment_date <= ph.payment_date) as total_paid,
                        (SELECT o.total_price - COALESCE(SUM(ph3.amount_paid), 0)
                         FROM orders o
                         LEFT JOIN payment_history ph3 ON ph3.order_id = o.order_id
                            AND ph3.status = 'Confirmed'
                            AND ph3.payment_date <= ph.payment_date
                         WHERE o.order_id = ph.order_id) as remaining_balance
                    FROM payment_history ph
                    LEFT JOIN accounts processor ON ph.processed_by = processor.Id
                    WHERE ph.order_id = ?
                    ORDER BY ph.payment_date DESC";

			$stmt = $connect->prepare($sql);
			$stmt->execute([$order_id]);
			$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
		} else {
			// Return empty array if table doesn't exist
			$payments = [];
		}

		// Return empty array if no payments found
		echo json_encode([
			'success' => true,
			'data' => $payments ?: []
		]);
		if (ob_get_level() > 0) { ob_end_flush(); }
		exit;
	} catch (Exception $e) {
		echo json_encode([
			'success' => false,
			'error' => 'Failed to fetch payment history: ' . $e->getMessage()
		]);
		if (ob_get_level() > 0) { ob_end_flush(); }
		exit;
	}
}

function getPaymentSchedule()
{
	global $connect;

	$order_id = $_GET['order_id'] ?? null;
	$account_id = $_SESSION['user_id'];

	if (!$order_id) {
		echo json_encode(['success' => false, 'error' => 'Order ID required']);
		if (ob_get_level() > 0) { ob_end_flush(); }
		exit;
	}

	try {
		// First get the cusID from customer_information table using account_id
		$cusStmt = $connect->prepare("SELECT cusID FROM customer_information WHERE account_id = ?");
		$cusStmt->execute([$account_id]);
		$customer = $cusStmt->fetch(PDO::FETCH_ASSOC);
		
		if (!$customer) {
			echo json_encode(['success' => false, 'error' => 'Customer profile not found']);
			if (ob_get_level() > 0) { ob_end_flush(); }
			exit;
		}
		
		$customer_id = $customer['cusID'];

		// Verify order belongs to customer
		$verify_sql = "SELECT order_id FROM orders WHERE order_id = ? AND customer_id = ?";
		$verify_stmt = $connect->prepare($verify_sql);
		$verify_stmt->execute([$order_id, $customer_id]);

		if (!$verify_stmt->fetch()) {
			echo json_encode(['success' => false, 'error' => 'Order not found']);
			if (ob_get_level() > 0) { ob_end_flush(); }
			exit;
		}

		// Check if payment_schedule table exists
		$tableExists = false;
		try {
			$checkTable = $connect->query("SHOW TABLES LIKE 'payment_schedule'");
			$tableExists = $checkTable->rowCount() > 0;
		} catch (Exception $e) {
			// Table doesn't exist, return empty array
		}

		if ($tableExists) {
			$sql = "SELECT * FROM payment_schedule 
                    WHERE order_id = ? 
                    ORDER BY payment_number ASC";

			$stmt = $connect->prepare($sql);
			$stmt->execute([$order_id]);
			$schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);
		} else {
			// Return empty array if table doesn't exist
			$schedule = [];
		}
		
		// Return empty array if no schedule found
		echo json_encode([
			'success' => true,
			'data' => $schedule ?: []
		]);
		if (ob_get_level() > 0) { ob_end_flush(); }
		exit;
	} catch (Exception $e) {
		echo json_encode([
			'success' => false,
			'error' => 'Failed to fetch payment schedule: ' . $e->getMessage()
		]);
		if (ob_get_level() > 0) { ob_end_flush(); }
		exit;
	}
}

/**
 * Generate payment schedule for financing orders
 */
function generatePaymentSchedule($order_id, $customer_id, $total_price, $down_payment, $financing_term, $monthly_payment, $order_date = null)
{
	global $connect;
	
	if (!$financing_term || !$monthly_payment) {
		return false;
	}
	
	try {
		// Extract number of months from financing term (e.g., "36 months" -> 36)
		$months = (int) filter_var($financing_term, FILTER_SANITIZE_NUMBER_INT);
		
		if ($months <= 0) {
			return false;
		}
		
		// Calculate loan amount (total price minus down payment)
		$loan_amount = $total_price - ($down_payment ?? 0);
		
		// Use order date or current date as starting point
		$start_date = $order_date ? new DateTime($order_date) : new DateTime();
		
		// Generate payment schedule
		for ($i = 1; $i <= $months; $i++) {
			// Calculate due date (first payment due 30 days after order)
			$due_date = clone $start_date;
			$due_date->add(new DateInterval('P' . ($i * 30) . 'D'));
			
			// Insert payment schedule record
			$sql = "INSERT INTO payment_schedule (
						order_id, customer_id, payment_number, due_date, 
						amount_due, status, created_at
					) VALUES (?, ?, ?, ?, ?, 'Pending', NOW())";
						
			$stmt = $connect->prepare($sql);
			$stmt->execute([
				$order_id,
				$customer_id,
				$i,
				$due_date->format('Y-m-d'),
				$monthly_payment
			]);
		}
		
		return true;
	} catch (Exception $e) {
		error_log("Payment schedule generation failed: " . $e->getMessage());
		return false;
	}
}

/**
 * Calculate amortization details with proper interest calculation
 */
function calculateAmortization($principal, $annual_rate, $months)
{
	// Delegate to centralized helper (expects annual_rate as decimal, e.g., 0.105)
	$amort = calculateLoanAmortization($principal, $annual_rate, $months);
	// Return false when invalid input per previous behavior
	if ($amort['monthly_payment'] === 0.0 && empty($amort['schedule'])) {
		return false;
	}
	return $amort;
}

function submitPayment()
{
	global $connect;

	$order_id = $_POST['order_id'] ?? null;
	$amount = $_POST['amount'] ?? null;
	$payment_method = $_POST['payment_method'] ?? null;
	$reference_number = $_POST['reference_number'] ?? null;
	$bank_name = $_POST['bank_name'] ?? null;
	$notes = $_POST['notes'] ?? '';

	if (!$order_id || !$amount || !$payment_method) {
		echo json_encode(['success' => false, 'error' => 'Required fields missing']);
		return;
	}

	// Validate receipt upload
	if (!isset($_FILES['payment_receipt']) || $_FILES['payment_receipt']['error'] !== UPLOAD_ERR_OK) {
		echo json_encode(['success' => false, 'error' => 'Payment receipt is required']);
		return;
	}

	$account_id = $_SESSION['user_id'];

	try {
		$connect->beginTransaction();

		// First get the cusID from customer_information table using account_id
		$cusStmt = $connect->prepare("SELECT cusID FROM customer_information WHERE account_id = ?");
		$cusStmt->execute([$account_id]);
		$customer = $cusStmt->fetch(PDO::FETCH_ASSOC);
		
		if (!$customer) {
			throw new Exception('Customer profile not found');
		}
		
		$customer_id = $customer['cusID'];

		// Verify order belongs to customer and get payment details
		$verify_sql = "SELECT order_id, monthly_payment, total_price, payment_method FROM orders WHERE order_id = ? AND customer_id = ?";
		$verify_stmt = $connect->prepare($verify_sql);
		$verify_stmt->execute([$order_id, $customer_id]);
		$order = $verify_stmt->fetch(PDO::FETCH_ASSOC);

		if (!$order) {
			throw new Exception('Order not found');
		}

		// Handle file upload
		$receipt_filename = null;
		$upload_file = $_FILES['payment_receipt'];
		
		// Validate file
		$allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
		$max_size = 5 * 1024 * 1024; // 5MB
		
		if (!in_array($upload_file['type'], $allowed_types)) {
			throw new Exception('Invalid file type. Please upload JPG, PNG, or PDF files only.');
		}
		
		if ($upload_file['size'] > $max_size) {
			throw new Exception('File size too large. Maximum size is 5MB.');
		}
		
		// Create uploads directory if it doesn't exist
		$upload_dir = realpath(__DIR__ . '/../../uploads/receipts/');
		if (!$upload_dir) {
			// Directory doesn't exist, create it
			$upload_dir = __DIR__ . '/../../uploads/receipts/';
			if (!file_exists($upload_dir)) {
				mkdir($upload_dir, 0755, true);
				// Set proper permissions for Linux/Unix systems
				chmod($upload_dir, 0755);
			}
			$upload_dir = realpath($upload_dir);
		}
		
		// Ensure directory is writable
		if (!is_writable($upload_dir)) {
			chmod($upload_dir, 0755);
		}
		
		// Add trailing slash for consistency
		$upload_dir = rtrim($upload_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
		
		// Generate unique filename
		$file_extension = pathinfo($upload_file['name'], PATHINFO_EXTENSION);
		$receipt_filename = 'receipt_' . $customer_id . '_' . $order_id . '_' . time() . '.' . $file_extension;
		$upload_path = $upload_dir . $receipt_filename;
		
		// Move uploaded file
		if (!move_uploaded_file($upload_file['tmp_name'], $upload_path)) {
			// Try to set file permissions if move fails
			if (file_exists($upload_path)) {
				chmod($upload_path, 0644);
			}
			throw new Exception('Failed to upload receipt file. Please check server permissions.');
		}
		
		// Set proper file permissions after successful upload
		chmod($upload_path, 0644);

		// Generate payment number
		$payment_number = 'PAY-' . date('Y') . '-' . str_pad($order_id, 3, '0', STR_PAD_LEFT) . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);

		// Determine payment type based on amount and order details
		$payment_type = 'Monthly Payment';
		if ($order['payment_method'] === 'cash' || $amount >= $order['total_price']) {
			$payment_type = 'Full Payment';
		} elseif ($amount < $order['monthly_payment']) {
			$payment_type = 'Partial Payment';
		}

		// Check if payment_history table exists
		$tableExists = false;
		try {
			$checkTable = $connect->query("SHOW TABLES LIKE 'payment_history'");
			$tableExists = $checkTable->rowCount() > 0;
		} catch (Exception $e) {
			// Table doesn't exist
		}

		if (!$tableExists) {
			throw new Exception('Payment system is not yet configured. Please contact administrator.');
		}

		// Insert payment record with receipt filename
        $sql = "INSERT INTO payment_history (
                    order_id, customer_id, payment_number, amount_paid, payment_method, 
                    payment_type, reference_number, bank_name, notes, receipt_filename,
                    payment_date, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'Pending', NOW())";

        $stmt = $connect->prepare($sql);
        $stmt->execute([
            $order_id,
            $customer_id,
            $payment_number,
            $amount,
            $payment_method,
            $payment_type,
            $reference_number,
            $bank_name,
            $notes,
            $receipt_filename
        ]);

        // Note: Payment schedule will be updated only after admin approval

		$connect->commit();

		echo json_encode([
			'success' => true,
			'message' => 'Payment submitted successfully. It will be reviewed by our team.',
			'payment_number' => $payment_number
		]);
		
		// Mark that we've sent a response to prevent shutdown function from adding fallback
		$GLOBALS['__ORDER_BACKEND_RESP_SENT__'] = true;
		exit; // Ensure no further output
	} catch (Exception $e) {
		$connect->rollBack();
		
		// Clean up uploaded file if payment failed
		if (isset($upload_path) && file_exists($upload_path)) {
			unlink($upload_path);
		}
		
		echo json_encode([
			'success' => false,
			'error' => 'Failed to submit payment: ' . $e->getMessage()
		]);
		
		// Mark that we've sent a response to prevent shutdown function from adding fallback
		$GLOBALS['__ORDER_BACKEND_RESP_SENT__'] = true;
		exit; // Ensure no further output
	}
}

/**
 * Update payment schedule when payments are made
 */
function updatePaymentSchedule($order_id, $payment_amount)
{
	global $connect;
	
	try {
		// Get pending payment schedules in order
		$sql = "SELECT * FROM payment_schedule 
				WHERE order_id = ? AND status IN ('Pending', 'Partial') 
				ORDER BY payment_number ASC";
				
		$stmt = $connect->prepare($sql);
		$stmt->execute([$order_id]);
		$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
		
		$remaining_payment = $payment_amount;
		
		foreach ($schedules as $schedule) {
			if ($remaining_payment <= 0) break;
			
			$amount_due = $schedule['amount_due'];
			$amount_paid = $schedule['amount_paid'];
			$balance_due = $amount_due - $amount_paid;
			
			if ($balance_due <= 0) continue;
			
			$payment_for_this_schedule = min($remaining_payment, $balance_due);
			$new_amount_paid = $amount_paid + $payment_for_this_schedule;
			
			// Determine new status
			$new_status = 'Pending';
			if ($new_amount_paid >= $amount_due) {
				$new_status = 'Paid';
			} elseif ($new_amount_paid > 0) {
				$new_status = 'Partial';
			}
			
			// Update the schedule entry
			$update_sql = "UPDATE payment_schedule 
						SET amount_paid = ?, status = ?, updated_at = NOW() 
						WHERE id = ?";
			$update_stmt = $connect->prepare($update_sql);
			$update_stmt->execute([$new_amount_paid, $new_status, $schedule['id']]);
			
			$remaining_payment -= $payment_for_this_schedule;
		}
		
		return true;
	} catch (Exception $e) {
		error_log("Failed to update payment schedule: " . $e->getMessage());
		return false;
	}
}

/**
 * Get pending payments for admin approval
 */
function getPendingPayments()
{
	global $connect;

	try {
		$sql = "SELECT 
					ph.id,
					ph.payment_number,
					ph.order_id,
					ph.amount_paid,
					ph.payment_method,
					ph.payment_type,
					ph.reference_number,
					ph.bank_name,
					ph.notes,
					ph.payment_date,
					ph.created_at,
					CONCAT(ci.firstname, ' ', ci.lastname) as customer_name,
					ci.mobile_number,
					o.vehicle_model,
					o.vehicle_variant,
					o.total_price
				FROM payment_history ph
				INNER JOIN customer_information ci ON ph.customer_id = ci.cusID
				INNER JOIN orders o ON ph.order_id = o.order_id
				WHERE ph.status = 'Pending'
				ORDER BY ph.created_at DESC";

		$stmt = $connect->prepare($sql);
		$stmt->execute();
		$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

		echo json_encode([
			'success' => true,
			'data' => $payments ?: []
		]);
	} catch (Exception $e) {
		echo json_encode([
			'success' => false,
			'error' => 'Failed to fetch pending payments: ' . $e->getMessage()
		]);
	}
}

/**
 * Approve a payment
 */
function approvePayment()
{
	global $connect;

	$payment_id = $_POST['payment_id'] ?? null;
	$admin_notes = $_POST['admin_notes'] ?? '';
	$admin_id = $_SESSION['user_id'];

	if (!$payment_id) {
		echo json_encode(['success' => false, 'error' => 'Payment ID required']);
		return;
	}

	try {
		$connect->beginTransaction();

		// Get payment details
		$payment_sql = "SELECT * FROM payment_history WHERE id = ? AND status = 'Pending'";
		$payment_stmt = $connect->prepare($payment_sql);
		$payment_stmt->execute([$payment_id]);
		$payment = $payment_stmt->fetch(PDO::FETCH_ASSOC);

		if (!$payment) {
			throw new Exception('Payment not found or already processed');
		}

		// Update payment status to Confirmed
		$update_sql = "UPDATE payment_history 
					   SET status = 'Confirmed', 
					   	processed_by = ?, 
					   	notes = CONCAT(COALESCE(notes, ''), '\n\nAdmin Notes: ', ?),
					   	updated_at = NOW()
					   WHERE id = ?";

		$update_stmt = $connect->prepare($update_sql);
		$update_stmt->execute([$admin_id, $admin_notes, $payment_id]);

		// Get order details to check if it's a financing order
		$order_sql = "SELECT payment_method FROM orders WHERE order_id = ?";
		$order_stmt = $connect->prepare($order_sql);
		$order_stmt->execute([$payment['order_id']]);
		$order = $order_stmt->fetch(PDO::FETCH_ASSOC);

		// Update payment schedule if this is a financing order
		if ($order && $order['payment_method'] === 'financing') {
			updatePaymentSchedule($payment['order_id'], $payment['amount_paid']);
		}

		$connect->commit();

		echo json_encode([
			'success' => true,
			'message' => 'Payment approved successfully'
		]);
	} catch (Exception $e) {
		$connect->rollBack();
		echo json_encode([
			'success' => false,
			'error' => 'Failed to approve payment: ' . $e->getMessage()
		]);
	}
}

/**
 * Reject a payment
 */
function rejectPayment()
{
	global $connect;

	$payment_id = $_POST['payment_id'] ?? null;
	$rejection_reason = $_POST['rejection_reason'] ?? '';
	$admin_id = $_SESSION['user_id'];

	if (!$payment_id || !$rejection_reason) {
		echo json_encode(['success' => false, 'error' => 'Payment ID and rejection reason required']);
		return;
	}

	try {
		$connect->beginTransaction();

		// Check if payment exists and is pending
		$check_sql = "SELECT id FROM payment_history WHERE id = ? AND status = 'Pending'";
		$check_stmt = $connect->prepare($check_sql);
		$check_stmt->execute([$payment_id]);

		if (!$check_stmt->fetch()) {
			throw new Exception('Payment not found or already processed');
		}

		// Update payment status to Failed
		$update_sql = "UPDATE payment_history 
					   SET status = 'Failed', 
					   	processed_by = ?, 
					   	notes = CONCAT(COALESCE(notes, ''), '\n\nRejection Reason: ', ?),
					   	updated_at = NOW()
					   WHERE id = ?";

		$update_stmt = $connect->prepare($update_sql);
		$update_stmt->execute([$admin_id, $rejection_reason, $payment_id]);

		$connect->commit();

		echo json_encode([
			'success' => true,
			'message' => 'Payment rejected successfully'
		]);
	} catch (Exception $e) {
		$connect->rollBack();
		echo json_encode([
			'success' => false,
			'error' => 'Failed to reject payment: ' . $e->getMessage()
		]);
	}
}
