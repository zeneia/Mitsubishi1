<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once dirname(dirname(__DIR__)) . '/includes/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Get database connection
    $connect = $GLOBALS['pdo'] ?? null;
    
    if (!$connect) {
        throw new Exception('Database connection not available');
    }
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    $validation_type = $input['validation_type'] ?? '';
    $client_type = $input['client_type'] ?? '';
    
    $errors = [];
    
    // Validate based on client type and validation request
    if ($client_type === 'handled') {
        // For handled clients, validate the customer exists
        if (!empty($input['customer_id'])) {
            $customer_id = $input['customer_id'];
            
            // Check if customer exists and is approved
            $stmt = $connect->prepare("SELECT ci.cusID, ci.Status, a.Email, ci.mobile_number 
                                     FROM customer_information ci 
                                     LEFT JOIN accounts a ON ci.account_id = a.Id 
                                     WHERE ci.cusID = ? AND ci.Status = 'Approved'");
            $stmt->execute([$customer_id]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$customer) {
                $errors[] = 'Customer not found or not approved';
            }
        } else {
            $errors[] = 'Customer ID is required for handled clients';
        }
        
    } else if ($client_type === 'walkin') {
        // For walk-in clients, validate the input data
        $email = trim($input['manual_email'] ?? '');
        $mobile = trim($input['manual_mobile'] ?? '');
        
        // Check for duplicate email in accounts table
        if (!empty($email)) {
            $stmt = $connect->prepare("SELECT Id FROM accounts WHERE Email = ? AND Id != ?");
            $stmt->execute([$email, $input['exclude_account_id'] ?? 0]);
            if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                $errors[] = 'Email address is already registered';
            }
        }
        
        // Check for duplicate mobile number in customer_information table
        if (!empty($mobile)) {
            $stmt = $connect->prepare("SELECT cusID FROM customer_information WHERE mobile_number = ? AND cusID != ?");
            $stmt->execute([$mobile, $input['exclude_customer_id'] ?? 0]);
            if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                $errors[] = 'Mobile number is already registered';
            }
        }
        
        // Validate required walk-in fields
        $required_fields = ['manual_firstname', 'manual_lastname', 'manual_mobile', 'manual_birthday'];
        foreach ($required_fields as $field) {
            if (empty($input[$field])) {
                $field_name = ucwords(str_replace('_', ' ', str_replace('manual_', '', $field)));
                $errors[] = $field_name . ' is required';
            }
        }
    }
    
    // Additional validation for duplicate orders (same customer, same vehicle within short timeframe)
    if (!empty($input['customer_id']) && !empty($input['vehicle_model']) && !empty($input['vehicle_variant'])) {
        $customer_id = $input['customer_id'];
        $vehicle_model = $input['vehicle_model'];
        $vehicle_variant = $input['vehicle_variant'];
        
        // Check for orders in the last 5 minutes with same customer and vehicle
        $stmt = $connect->prepare("
            SELECT order_id, order_number, created_at 
            FROM orders 
            WHERE customer_id = ? 
            AND vehicle_model = ? 
            AND vehicle_variant = ? 
            AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            AND order_status != 'cancelled'
        ");
        $stmt->execute([$customer_id, $vehicle_model, $vehicle_variant]);
        $recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($recent_orders)) {
            $order = $recent_orders[0];
            $errors[] = 'A recent order for the same customer and vehicle was already created. Please wait a few minutes or check order #' . $order['order_number'];
        }
    }
    
    // Return validation results
    if (empty($errors)) {
        echo json_encode([
            'success' => true,
            'message' => 'Validation passed',
            'data' => ['can_proceed' => true]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Validation failed',
            'data' => [
                'can_proceed' => false,
                'errors' => $errors
            ]
        ]);
    }
    
} catch (Exception $e) {
    error_log("Order validation error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Validation error: ' . $e->getMessage()
    ]);
}
?>
