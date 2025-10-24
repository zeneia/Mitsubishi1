<?php
session_start();
include_once(dirname(__DIR__) . '/database/db_conn.php');

// Check if user is a sales agent
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'SalesAgent') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_all_orders':
        getAllOrders();
        break;
    case 'get_order_statistics':
        getOrderStatistics();
        break;
    case 'get_order_details':
        getOrderDetails();
        break;
    case 'delete_order':
        deleteOrder();
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        exit;
}

function getAllOrders()
{
    global $connect;
    
    try {
        // Get filter parameters
        $search = $_GET['search'] ?? '';
        $status = $_GET['status'] ?? 'all';
        $clientType = $_GET['client_type'] ?? 'all';
        $dateRange = $_GET['date_range'] ?? 'all';
        
        $sql = "SELECT 
                    o.order_id,
                    o.order_number,
                    o.customer_id,
                    o.sales_agent_id,
                    o.vehicle_id,
                    o.client_type,
                    o.vehicle_model,
                    o.vehicle_variant,
                    o.vehicle_color,
                    o.model_year,
                    o.base_price,
                    o.discount_amount,
                    o.total_price,
                    o.payment_method,
                    o.down_payment,
                    o.financing_term,
                    o.monthly_payment,
                    o.order_status,
                    o.delivery_date,
                    o.actual_delivery_date,
                    o.delivery_address,
                    o.order_notes,
                    o.created_at,
                    o.order_date,
                    ci.firstname,
                    ci.lastname,
                    acc.Email as email,
                    ci.mobile_number as phone,
                    CONCAT(agent.FirstName, ' ', agent.LastName) as agent_name,
                    v.main_image as vehicle_image
                FROM orders o
                LEFT JOIN customer_information ci ON o.customer_id = ci.cusID
                LEFT JOIN accounts acc ON ci.account_id = acc.Id
                LEFT JOIN accounts agent ON o.sales_agent_id = agent.Id
                LEFT JOIN vehicles v ON o.vehicle_id = v.id
                WHERE o.sales_agent_id = ?";
        
        $params = [$_SESSION['user_id']];
        
        // Add search filter
        if (!empty($search)) {
            $sql .= " AND (o.order_number LIKE ? OR 
                          CONCAT(ci.firstname, ' ', ci.lastname) LIKE ? OR 
                          o.vehicle_model LIKE ? OR 
                          acc.Email LIKE ?)";
            $searchParam = "%$search%";
            $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
        }
        
        // Add status filter
        if ($status !== 'all') {
            $sql .= " AND o.order_status = ?";
            $params[] = $status;
        }
        
        // Add client type filter
        if ($clientType !== 'all') {
            $sql .= " AND o.client_type = ?";
            $params[] = $clientType;
        }
        
        // Add date range filter
        switch ($dateRange) {
            case 'today':
                $sql .= " AND DATE(o.created_at) = CURDATE()";
                break;
            case 'week':
                $sql .= " AND o.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
                break;
            case 'month':
                $sql .= " AND o.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                break;
        }
        
        $sql .= " ORDER BY o.created_at DESC";
        
        $stmt = $connect->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format the data for frontend
        $formattedOrders = [];
        foreach ($orders as $order) {
            // Initialize default values
            $customerName = 'Unknown Customer';
            $customerEmail = 'No email provided';
            $customerNote = 'Customer information';
            
            // Check if we have customer data (works for both handled and walk-in clients)
            if ($order['firstname'] && $order['lastname']) {
                $customerName = trim($order['firstname'] . ' ' . $order['lastname']);
                $customerEmail = $order['email'] ?? 'No email provided';
                
                if ($order['client_type'] === 'handled') {
                    $customerNote = 'Client since: ' . date('M Y', strtotime($order['created_at']));
                } else if ($order['client_type'] === 'walkin') {
                    $customerNote = 'Walk-in customer - ' . date('M j, Y', strtotime($order['created_at']));
                }
            } else {
                // Fallback for cases where customer data is missing
                if ($order['client_type'] === 'walkin') {
                    $customerName = 'Walk-in Customer';
                    $customerEmail = 'walkin.customer@temp.com';
                    $customerNote = 'Walk-in customer - data incomplete';
                } else {
                    $customerName = 'Handled Customer';
                    $customerNote = 'Customer data incomplete';
                }
            }
            
            $formattedOrders[] = [
                'order_id' => $order['order_id'],
                'order_number' => $order['order_number'],
                'order_date' => date('F j, Y', strtotime($order['order_date'] ?? $order['created_at'])),
                'customer_name' => $customerName,
                'customer_email' => $customerEmail,
                'customer_note' => $customerNote,
                'client_type' => $order['client_type'],
                'vehicle_model' => $order['vehicle_model'],
                'vehicle_variant' => $order['vehicle_variant'],
                'vehicle_color' => $order['vehicle_color'],
                'model_year' => $order['model_year'],
                'total_price' => $order['total_price'],
                'order_status' => $order['order_status'],
                'payment_method' => $order['payment_method'],
                'agent_name' => $order['agent_name'],
                'created_at' => $order['created_at']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'data' => $formattedOrders
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to fetch orders: ' . $e->getMessage()
        ]);
    }
}

function getOrderStatistics()
{
    global $connect;
    
    try {
        // Get total orders count for current agent
        $totalOrdersStmt = $connect->prepare("SELECT COUNT(*) as total FROM orders WHERE sales_agent_id = ?");
        $totalOrdersStmt->execute([$_SESSION['user_id']]);
        $totalOrders = $totalOrdersStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Get pending orders count for current agent
        $pendingOrdersStmt = $connect->prepare("SELECT COUNT(*) as pending FROM orders WHERE order_status = 'pending' AND sales_agent_id = ?");
        $pendingOrdersStmt->execute([$_SESSION['user_id']]);
        $pendingOrders = $pendingOrdersStmt->fetch(PDO::FETCH_ASSOC)['pending'];
        
        // Get walk-in orders count for current agent
        $walkinOrdersStmt = $connect->prepare("SELECT COUNT(*) as walkin FROM orders WHERE client_type = 'walkin' AND sales_agent_id = ?");
        $walkinOrdersStmt->execute([$_SESSION['user_id']]);
        $walkinOrders = $walkinOrdersStmt->fetch(PDO::FETCH_ASSOC)['walkin'];
        
        // Get handled clients count for current agent
        $handledClientsStmt = $connect->prepare("SELECT COUNT(DISTINCT customer_id) as handled FROM orders WHERE client_type = 'handled' AND sales_agent_id = ?");
        $handledClientsStmt->execute([$_SESSION['user_id']]);
        $handledClients = $handledClientsStmt->fetch(PDO::FETCH_ASSOC)['handled'];
        
        echo json_encode([
            'success' => true,
            'data' => [
                'total_orders' => $totalOrders,
                'pending_orders' => $pendingOrders,
                'walkin_orders' => $walkinOrders,
                'handled_clients' => $handledClients
            ]
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to fetch statistics: ' . $e->getMessage()
        ]);
    }
}

function getOrderDetails()
{
    global $connect;
    
    $order_id = $_GET['order_id'] ?? null;
    
    if (!$order_id) {
        echo json_encode(['success' => false, 'error' => 'Order ID required']);
        return;
    }
    
    try {
        $sql = "SELECT 
                    o.*,
                    ci.firstname,
                    ci.lastname,
                    acc.Email as email,
                    ci.mobile_number as phone,
                    CONCAT(ci.firstname, ' ', ci.lastname) as address,
                    CONCAT(agent.FirstName, ' ', agent.LastName) as agent_name,
                    agent.Email as agent_email,
                    v.main_image as vehicle_image,
                    v.engine_type,
                    v.transmission,
                    v.fuel_type,
                    v.seating_capacity
                FROM orders o
                LEFT JOIN customer_information ci ON o.customer_id = ci.cusID
                LEFT JOIN accounts acc ON ci.account_id = acc.Id
                LEFT JOIN accounts agent ON o.sales_agent_id = agent.Id
                LEFT JOIN vehicles v ON o.vehicle_id = v.id
                WHERE o.order_id = ? AND o.sales_agent_id = ?";
        
        $stmt = $connect->prepare($sql);
        $stmt->execute([$order_id, $_SESSION['user_id']]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            echo json_encode(['success' => false, 'error' => 'Order not found']);
            return;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $order
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to fetch order details: ' . $e->getMessage()
        ]);
    }
}

function deleteOrder()
{
    global $connect;

    $order_id = $_POST['order_id'] ?? null;

    if (!$order_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Order ID required']);
        return;
    }

    try {
        // First verify that the order belongs to the current sales agent
        $checkStmt = $connect->prepare("SELECT order_id FROM orders WHERE order_id = ? AND sales_agent_id = ?");
        $checkStmt->execute([$order_id, $_SESSION['user_id']]);
        $order = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Order not found or you do not have permission to delete this order']);
            return;
        }

        // Begin transaction
        $connect->beginTransaction();

        // Delete related payment history records first (if any)
        $deletePaymentsStmt = $connect->prepare("DELETE FROM payment_history WHERE order_id = ?");
        $deletePaymentsStmt->execute([$order_id]);

        // Delete the order
        $deleteOrderStmt = $connect->prepare("DELETE FROM orders WHERE order_id = ?");
        $deleteOrderStmt->execute([$order_id]);

        // Commit transaction
        $connect->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Order deleted successfully'
        ]);

    } catch (Exception $e) {
        // Rollback transaction on error
        if ($connect->inTransaction()) {
            $connect->rollBack();
        }

        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to delete order: ' . $e->getMessage()
        ]);
    }
}
?>