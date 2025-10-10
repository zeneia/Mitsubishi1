<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

include_once(dirname(__DIR__) . '/includes/init.php');

// Check if user is Admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied. Admin role required.']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_clients':
            getClientAssignments();
            break;
        case 'get_client_details':
            getClientDetails();
            break;
        case 'reassign_client':
            reassignClient();
            break;
        case 'escalate_client':
            escalateClient();
            break;
        case 'archive_client':
            archiveClient();
            break;
        case 'get_agents':
            getAvailableAgents();
            break;
        case 'get_workload_stats':
            getWorkloadStats();
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

function getClientAssignments() {
    global $pdo;
    
    $search = $_GET['search'] ?? '';
    $agent_filter = $_GET['agent'] ?? '';
    $status_filter = $_GET['status'] ?? '';
    
    $sql = "
        SELECT 
            ci.cusID as client_id,
            ci.firstname,
            ci.lastname,
            a.Email as email,
            ci.mobile_number as phone_number,
            ci.agent_id,
            COALESCE(ci.status, 'Active') as client_status,
            ci.created_at as assignment_date,
            ci.updated_at as last_activity,
            a_agent.FirstName as agent_first_name,
            a_agent.LastName as agent_last_name,
            COALESCE(sap.display_name, CONCAT(a_agent.FirstName, ' ', a_agent.LastName)) as agent_position,
            COUNT(c.conversation_id) as conversation_count,
            MAX(m.created_at) as last_message_at
        FROM customer_information ci
        LEFT JOIN accounts a ON ci.account_id = a.Id
        LEFT JOIN sales_agent_profiles sap ON ci.agent_id = sap.account_id
        LEFT JOIN accounts a_agent ON ci.agent_id = a_agent.Id
        LEFT JOIN conversations c ON ci.cusID = c.customer_id
        LEFT JOIN messages m ON c.conversation_id = m.conversation_id
        WHERE 1=1
    ";
    
    $params = [];
    
    if (!empty($search)) {
        $sql .= " AND (ci.firstname LIKE ? OR ci.lastname LIKE ? OR a.Email LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    if (!empty($agent_filter)) {
        $sql .= " AND ci.agent_id = ?";
        $params[] = $agent_filter;
    }
    
    if (!empty($status_filter)) {
        $sql .= " AND ci.status = ?";
        $params[] = $status_filter;
    }
    
    $sql .= " GROUP BY ci.cusID ORDER BY ci.updated_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the response
    $formatted_clients = array_map(function($client) {
        return [
            'client_id' => $client['client_id'],
            'client_name' => $client['firstname'] . ' ' . $client['lastname'],
            'client_code' => 'CL-' . date('Y', strtotime($client['assignment_date'])) . '-' . str_pad($client['client_id'], 3, '0', STR_PAD_LEFT),
            'email' => $client['email'],
            'phone' => $client['phone_number'],
            'agent_name' => $client['agent_first_name'] . ' ' . $client['agent_last_name'],
            'agent_position' => $client['agent_position'],
            'agent_id' => $client['agent_id'],
            'assignment_date' => $client['assignment_date'],
            'last_activity' => $client['last_activity'],
            'status' => $client['client_status'],
            'conversation_count' => $client['conversation_count'],
            'last_message_at' => $client['last_message_at']
        ];
    }, $clients);
    
    echo json_encode(['success' => true, 'data' => $formatted_clients]);
}

function getClientDetails() {
    global $pdo;
    
    $client_id = $_GET['client_id'] ?? '';
    if (empty($client_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Client ID is required']);
        return;
    }
    
    $sql = "
        SELECT 
            ci.*,
            a.Email as email,
            a_agent.FirstName as agent_first_name,
            a_agent.LastName as agent_last_name,
            COALESCE(sap.display_name, CONCAT(a_agent.FirstName, ' ', a_agent.LastName)) as agent_position,
            a_agent.Email as agent_email
        FROM customer_information ci
        LEFT JOIN accounts a ON ci.account_id = a.Id
        LEFT JOIN sales_agent_profiles sap ON ci.agent_id = sap.account_id
        LEFT JOIN accounts a_agent ON ci.agent_id = a_agent.Id
        WHERE ci.cusID = ?
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$client_id]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$client) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Client not found']);
        return;
    }
    
    // Get conversation history
    $conversation_sql = "
        SELECT 
            c.conversation_id,
            c.status as conversation_status,
            c.created_at as conversation_start,
            c.last_message_at,
            COUNT(m.message_id) as message_count
        FROM conversations c
        LEFT JOIN messages m ON c.conversation_id = m.conversation_id
        WHERE c.customer_id = ?
        GROUP BY c.conversation_id
        ORDER BY c.created_at DESC
    ";
    
    $stmt = $pdo->prepare($conversation_sql);
    $stmt->execute([$client_id]);
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $client['conversations'] = $conversations;
    
    echo json_encode(['success' => true, 'data' => $client]);
}

function reassignClient() {
    global $pdo;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $client_id = $input['client_id'] ?? '';
    $new_agent_id = $input['new_agent_id'] ?? '';
    $reason = $input['reason'] ?? '';
    $notes = $input['notes'] ?? '';
    
    if (empty($client_id) || empty($new_agent_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Client ID and New Agent ID are required']);
        return;
    }
    
    $pdo->beginTransaction();
    
    try {
        // Get current assignment
        $stmt = $pdo->prepare("SELECT agent_id FROM customer_information WHERE cusID = ?");
        $stmt->execute([$client_id]);
        $current_assignment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$current_assignment) {
            throw new Exception('Client not found');
        }
        
        $old_agent_id = $current_assignment['agent_id'];
        
        // Update client assignment
        $stmt = $pdo->prepare("UPDATE customer_information SET agent_id = ?, updated_at = NOW() WHERE cusID = ?");
        $stmt->execute([$new_agent_id, $client_id]);
        
        // Log the reassignment
        $stmt = $pdo->prepare("
            INSERT INTO client_reassignments (client_id, old_agent_id, new_agent_id, reason, notes, reassigned_by, reassigned_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$client_id, $old_agent_id, $new_agent_id, $reason, $notes, $_SESSION['user_id']]);
        
        // Update conversation assignment
        $stmt = $pdo->prepare("UPDATE conversations SET agent_id = ? WHERE customer_id = ? AND status = 'Active'");
        $stmt->execute([$new_agent_id, $client_id]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Client successfully reassigned',
            'data' => [
                'client_id' => $client_id,
                'old_agent_id' => $old_agent_id,
                'new_agent_id' => $new_agent_id
            ]
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function escalateClient() {
    global $pdo;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $client_id = $input['client_id'] ?? '';
    $escalation_reason = $input['escalation_reason'] ?? '';
    $priority = $input['priority'] ?? 'High';
    $notes = $input['notes'] ?? '';
    
    if (empty($client_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Client ID is required']);
        return;
    }
    
    try {
        // Create escalation record
        $stmt = $pdo->prepare("
            INSERT INTO client_escalations (client_id, escalation_reason, priority, notes, escalated_by, escalated_at, status) 
            VALUES (?, ?, ?, ?, ?, NOW(), 'Pending')
        ");
        $stmt->execute([$client_id, $escalation_reason, $priority, $notes, $_SESSION['user_id']]);
        
        $escalation_id = $pdo->lastInsertId();
        
        // Update client status to escalated
        $stmt = $pdo->prepare("UPDATE customer_information SET status = 'Escalated', updated_at = NOW() WHERE cusID = ?");
        $stmt->execute([$client_id]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Client successfully escalated',
            'data' => [
                'escalation_id' => $escalation_id,
                'client_id' => $client_id,
                'priority' => $priority
            ]
        ]);
        
    } catch (Exception $e) {
        throw $e;
    }
}

function archiveClient() {
    global $pdo;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $client_id = $input['client_id'] ?? '';
    $archive_reason = $input['archive_reason'] ?? '';
    
    if (empty($client_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Client ID is required']);
        return;
    }
    
    try {
        // Update client status to archived
        $stmt = $pdo->prepare("UPDATE customer_information SET status = 'Archived', updated_at = NOW() WHERE cusID = ?");
        $stmt->execute([$client_id]);
        
        // Close active conversations
        $stmt = $pdo->prepare("UPDATE conversations SET status = 'Closed' WHERE customer_id = ? AND status = 'Active'");
        $stmt->execute([$client_id]);
        
        // Log the archive action
        $stmt = $pdo->prepare("
            INSERT INTO client_archives (client_id, archive_reason, archived_by, archived_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$client_id, $archive_reason, $_SESSION['user_id']]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Client successfully archived',
            'data' => ['client_id' => $client_id]
        ]);
        
    } catch (Exception $e) {
        throw $e;
    }
}

function getAvailableAgents() {
    global $pdo;
    
    $sql = "
        SELECT 
            a.Id as account_id,
            a.FirstName as first_name,
            a.LastName as last_name,
            COALESCE(sap.display_name, CONCAT(a.FirstName, ' ', a.LastName)) as position,
            COALESCE(sap.status, 'Active') as status,
            COUNT(ci.cusID) as active_clients
        FROM accounts a
        LEFT JOIN sales_agent_profiles sap ON a.Id = sap.account_id
        LEFT JOIN customer_information ci ON a.Id = ci.agent_id AND COALESCE(ci.status, 'Active') IN ('Active', 'Pending')
        WHERE a.Role = 'SalesAgent' AND COALESCE(sap.status, 'Active') = 'Active'
        GROUP BY a.Id
        ORDER BY active_clients ASC, a.FirstName ASC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $agents]);
}

function getWorkloadStats() {
    global $pdo;
    
    $sql = "
        SELECT 
            a.Id as account_id,
            a.FirstName as first_name,
            a.LastName as last_name,
            COUNT(ci.cusID) as active_clients,
            COUNT(CASE WHEN COALESCE(ci.status, 'Active') = 'Active' THEN 1 END) as active_count,
            COUNT(CASE WHEN ci.status = 'Completed' THEN 1 END) as completed_count,
            AVG(DATEDIFF(NOW(), ci.created_at)) as avg_handle_time
        FROM accounts a
        LEFT JOIN sales_agent_profiles sap ON a.Id = sap.account_id
        LEFT JOIN customer_information ci ON a.Id = ci.agent_id
        WHERE a.Role = 'SalesAgent' AND COALESCE(sap.status, 'Active') = 'Active'
        GROUP BY a.Id
        ORDER BY active_clients DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $stats]);
}
?>
