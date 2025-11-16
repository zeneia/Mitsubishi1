<?php
// Include database connection
require_once dirname(__DIR__) . '/database/db_conn.php';

// Start session for role-based access control
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set content type for JSON responses
header('Content-Type: application/json');

// Role helpers
function isAdminRole() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Admin';
}
function isSalesAgentRole() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Sales Agent';
}
function currentUserId() {
    return $_SESSION['user_id'] ?? null;
}

// Handle different actions
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_pending_requests':
            getPendingRequests();
            break;
        case 'get_approved_requests':
            getApprovedRequests();
            break;
        case 'get_completed_requests':
            getCompletedRequests();
            break;
        case 'get_request_details':
            getRequestDetails();
            break;
        case 'update_request_status':
            updateRequestStatus();
            break;
        case 'approve_request':
            approveRequest();
            break;
        case 'reject_request':
            rejectRequest();
            break;
        case 'complete_request':
            completeRequest();
            break;
        case 'get_stats':
            getTestDriveStats();
            break;
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

function getPendingRequests() {
    global $connect;
    
    $sql = "SELECT 
                tdr.id,
                tdr.gate_pass_number,
                tdr.customer_name,
                tdr.mobile_number,
                tdr.selected_date,
                tdr.selected_time_slot,
                tdr.test_drive_location,
                tdr.requested_at,
                tdr.notes,
                CONCAT(a.FirstName, ' ', a.LastName) as account_name,
                a.Email as account_email,
                ci.lastname as customer_lastname,
                ci.firstname as customer_firstname,
                DATEDIFF(tdr.selected_date, CURDATE()) as days_until_drive
            FROM test_drive_requests tdr
            LEFT JOIN accounts a ON tdr.account_id = a.Id
            LEFT JOIN customer_information ci ON tdr.account_id = ci.account_id
            WHERE tdr.status = 'Pending'";
    $params = [];
    if (isSalesAgentRole()) {
        $sql .= " AND ci.agent_id = ?";
        $params[] = currentUserId();
    }
    $sql .= " ORDER BY tdr.selected_date ASC, tdr.requested_at ASC";
    
    $stmt = $connect->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add priority level based on date
    foreach ($results as &$row) {
        $daysUntil = $row['days_until_drive'];
        if ($daysUntil < 0) {
            $row['priority'] = 'overdue';
        } elseif ($daysUntil <= 1) {
            $row['priority'] = 'urgent';
        } elseif ($daysUntil <= 3) {
            $row['priority'] = 'high';
        } else {
            $row['priority'] = 'normal';
        }
    }
    
    echo json_encode(['success' => true, 'data' => $results]);
}

function getApprovedRequests() {
    global $connect;
    
    $sql = "SELECT 
                tdr.id,
                tdr.gate_pass_number,
                tdr.customer_name,
                tdr.mobile_number,
                tdr.selected_date,
                tdr.selected_time_slot,
                tdr.test_drive_location,
                tdr.instructor_agent,
                tdr.approved_at,
                tdr.notes,
                CONCAT(a.FirstName, ' ', a.LastName) as account_name,
                a.Email as account_email
            FROM test_drive_requests tdr
            LEFT JOIN accounts a ON tdr.account_id = a.Id
            LEFT JOIN customer_information ci ON tdr.account_id = ci.account_id
            WHERE tdr.status = 'Approved'";
    $params = [];
    if (isSalesAgentRole()) {
        $sql .= " AND ci.agent_id = ?";
        $params[] = currentUserId();
    }
    $sql .= " ORDER BY tdr.selected_date ASC";
    
    $stmt = $connect->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $results]);
}

function getCompletedRequests() {
    global $connect;
    
    $sql = "SELECT 
                tdr.id,
                tdr.gate_pass_number,
                tdr.customer_name,
                tdr.mobile_number,
                tdr.selected_date,
                tdr.selected_time_slot,
                tdr.test_drive_location,
                tdr.instructor_agent,
                tdr.approved_at,
                tdr.notes,
                CONCAT(a.FirstName, ' ', a.LastName) as account_name,
                a.Email as account_email
            FROM test_drive_requests tdr
            LEFT JOIN accounts a ON tdr.account_id = a.Id
            LEFT JOIN customer_information ci ON tdr.account_id = ci.account_id
            WHERE tdr.status = 'Completed'";
    $params = [];
    if (isSalesAgentRole()) {
        $sql .= " AND ci.agent_id = ?";
        $params[] = currentUserId();
    }
    $sql .= " ORDER BY tdr.selected_date DESC";
    
    $stmt = $connect->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $results]);
}

function getRequestDetails() {
    global $connect;
    
    $id = $_GET['id'] ?? '';
    if (empty($id)) {
        throw new Exception('Request ID is required');
    }
    
    $sql = "SELECT 
                tdr.*,
                CONCAT(a.FirstName, ' ', a.LastName) as account_name,
                a.Email as account_email,
                ci.*
            FROM test_drive_requests tdr
            LEFT JOIN accounts a ON tdr.account_id = a.Id
            LEFT JOIN customer_information ci ON tdr.account_id = ci.account_id
            WHERE tdr.id = ?";
    $params = [$id];
    if (isSalesAgentRole()) {
        $sql .= " AND ci.agent_id = ?";
        $params[] = currentUserId();
    }
    
    $stmt = $connect->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        throw new Exception('Test drive request not found');
    }
    
    echo json_encode(['success' => true, 'data' => $result]);
}

function updateRequestStatus() {
    global $connect;
    
    $id = $_POST['id'] ?? '';
    $status = $_POST['status'] ?? '';
    $notes = $_POST['notes'] ?? '';
    $instructor_agent = $_POST['instructor_agent'] ?? '';
    
    if (empty($id) || empty($status)) {
        throw new Exception('Request ID and status are required');
    }
    
    $validStatuses = ['Pending', 'Approved', 'Rejected', 'Completed'];
    if (!in_array($status, $validStatuses)) {
        throw new Exception('Invalid status');
    }
    
    // Authorization: Sales Agents may only update requests for their assigned customers
    if (isSalesAgentRole()) {
        $auth = $connect->prepare("SELECT 1
                                   FROM test_drive_requests t
                                   LEFT JOIN customer_information ci ON t.account_id = ci.account_id
                                   WHERE t.id = ? AND ci.agent_id = ?");
        $auth->execute([$id, currentUserId()]);
        if (!$auth->fetch()) {
            http_response_code(403);
            throw new Exception('Unauthorized to modify this request');
        }
    }
    
    $sql = "UPDATE test_drive_requests SET 
                status = ?, 
                notes = ?,
                instructor_agent = ?";
    
    $params = [$status, $notes, $instructor_agent];
    
    if ($status === 'Approved') {
        $sql .= ", approved_at = NOW()";
    }
    
    $sql .= " WHERE id = ?";
    $params[] = $id;
    
    $stmt = $connect->prepare($sql);
    $result = $stmt->execute($params);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Request status updated successfully']);
    } else {
        throw new Exception('Failed to update request status');
    }
}

function approveRequest() {
    global $connect;
    
    $id = $_POST['id'] ?? '';
    $instructor_agent = $_POST['instructor_agent'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    if (empty($id)) {
        throw new Exception('Request ID is required');
    }
    
    // Authorization for Sales Agent
    if (isSalesAgentRole()) {
        $auth = $connect->prepare("SELECT 1
                                   FROM test_drive_requests t
                                   LEFT JOIN customer_information ci ON t.account_id = ci.account_id
                                   WHERE t.id = ? AND ci.agent_id = ?");
        $auth->execute([$id, currentUserId()]);
        if (!$auth->fetch()) {
            http_response_code(403);
            throw new Exception('Unauthorized to approve this request');
        }
    }
    
    $sql = "UPDATE test_drive_requests SET
                status = 'Approved',
                approved_at = NOW(),
                approved_by = ?,
                instructor_agent = ?,
                notes = ?
            WHERE id = ?";

    $stmt = $connect->prepare($sql);
    $result = $stmt->execute([currentUserId(), $instructor_agent, $notes, $id]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Test drive request approved successfully']);
    } else {
        throw new Exception('Failed to approve request');
    }
}

function rejectRequest() {
    global $connect;
    
    $id = $_POST['id'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    if (empty($id)) {
        throw new Exception('Request ID is required');
    }
    
    if (empty($notes)) {
        throw new Exception('Rejection reason is required');
    }
    
    // Authorization for Sales Agent
    if (isSalesAgentRole()) {
        $auth = $connect->prepare("SELECT 1
                                   FROM test_drive_requests t
                                   LEFT JOIN customer_information ci ON t.account_id = ci.account_id
                                   WHERE t.id = ? AND ci.agent_id = ?");
        $auth->execute([$id, currentUserId()]);
        if (!$auth->fetch()) {
            http_response_code(403);
            throw new Exception('Unauthorized to reject this request');
        }
    }
    
    $sql = "UPDATE test_drive_requests SET 
                status = 'Rejected',
                notes = ?
            WHERE id = ?";
    
    $stmt = $connect->prepare($sql);
    $result = $stmt->execute([$notes, $id]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Test drive request rejected']);
    } else {
        throw new Exception('Failed to reject request');
    }
}

function completeRequest() {
    global $connect;
    
    $id = $_POST['id'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    if (empty($id)) {
        throw new Exception('Request ID is required');
    }
    
    // Authorization for Sales Agent
    if (isSalesAgentRole()) {
        $auth = $connect->prepare("SELECT 1
                                   FROM test_drive_requests t
                                   LEFT JOIN customer_information ci ON t.account_id = ci.account_id
                                   WHERE t.id = ? AND ci.agent_id = ?");
        $auth->execute([$id, currentUserId()]);
        if (!$auth->fetch()) {
            http_response_code(403);
            throw new Exception('Unauthorized to complete this request');
        }
    }
    
    $sql = "UPDATE test_drive_requests SET 
                status = 'Completed',
                notes = ?
            WHERE id = ?";
    
    $stmt = $connect->prepare($sql);
    $result = $stmt->execute([$notes, $id]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Test drive marked as completed']);
    } else {
        throw new Exception('Failed to complete request');
    }
}

function getTestDriveStats() {
    global $connect;
    
    $sql = "SELECT 
                COUNT(CASE WHEN status = 'Pending' THEN 1 END) as pending_count,
                COUNT(CASE WHEN status = 'Approved' THEN 1 END) as approved_count,
                COUNT(CASE WHEN status = 'Completed' THEN 1 END) as completed_count,
                COUNT(CASE WHEN status = 'Rejected' THEN 1 END) as rejected_count,
                COUNT(CASE WHEN status = 'Pending' AND DATEDIFF(selected_date, CURDATE()) <= 1 THEN 1 END) as urgent_count,
                COUNT(CASE WHEN status = 'Pending' AND DATE(requested_at) = CURDATE() THEN 1 END) as today_requests
            FROM test_drive_requests tdr";
    $params = [];
    if (isSalesAgentRole()) {
        $sql .= " LEFT JOIN customer_information ci ON tdr.account_id = ci.account_id WHERE ci.agent_id = ?";
        $params[] = currentUserId();
    }
    
    $stmt = $connect->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $result]);
}
?>
