<?php
// Configure session settings
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');

// Enhanced error logging for debugging
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Log script start
error_log("inquiry_actions.php: Script started - " . date('Y-m-d H:i:s'));
error_log("inquiry_actions.php: REQUEST_METHOD = " . $_SERVER['REQUEST_METHOD']);
error_log("inquiry_actions.php: POST data = " . print_r($_POST, true));
error_log("inquiry_actions.php: GET data = " . print_r($_GET, true));

header('Content-Type: application/json');

// Ensure session is started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

error_log("inquiry_actions.php: Session status = " . session_status());
error_log("inquiry_actions.php: Session data = " . print_r($_SESSION, true));

include_once(dirname(dirname(__DIR__)) . '/includes/init.php');
require_once(dirname(dirname(__DIR__)) . '/includes/api/notification_api.php');

error_log("inquiry_actions.php: Files included successfully");

// Check if user is Admin or Sales Agent for inquiry responses
if (!isset($_SESSION['user_role'])) {
    error_log("inquiry_actions.php: No user role found in session");
    http_response_code(403);
    echo json_encode([
        'success' => false, 
        'message' => 'No user role found in session. Please log in again.',
        'error_code' => 'NO_ROLE'
    ]);
    exit();
}

if (!in_array($_SESSION['user_role'], ['Admin', 'SalesAgent'])) {
    error_log("inquiry_actions.php: Invalid role: " . $_SESSION['user_role']);
    http_response_code(403);
    echo json_encode([
        'success' => false, 
        'message' => 'Access denied for role: ' . $_SESSION['user_role'],
        'error_code' => 'INVALID_ROLE'
    ]);
    exit();
}

error_log("inquiry_actions.php: User role validation passed: " . $_SESSION['user_role']);

// Use the database connection from init.php
$pdo = $GLOBALS['pdo'] ?? null;

if (!$pdo) {
    error_log("inquiry_actions.php: Database connection not available");
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection not available']);
    exit();
}

error_log("inquiry_actions.php: Database connection established successfully");

// Determine the action
$action = '';
$input = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
    } else {
        // Check if it's JSON input
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input && isset($input['action'])) {
            $action = $input['action'];
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    $action = $_GET['action'];
}

try {
    error_log("inquiry_actions.php: Processing action: " . $action);
    
    switch ($action) {
        case 'delete':
            error_log("inquiry_actions.php: Handling delete action");
            handleDeleteInquiry($pdo, $input);
            break;
        
        case 'respond':
            error_log("inquiry_actions.php: Handling respond action");
            handleRespondToInquiry($pdo, $_POST);
            break;
            
        case 'get_inquiry':
            error_log("inquiry_actions.php: Handling get_inquiry action");
            handleGetInquiry($pdo, $_GET);
            break;
            
        case 'add_inquiry':
            error_log("inquiry_actions.php: Handling add_inquiry action");
            handleCreateInquiry($pdo, $_POST);
            break;
            
        case 'get_accounts':
            error_log("inquiry_actions.php: Handling get_accounts action");
            handleGetAccounts($pdo);
            break;
            
        default:
            error_log("inquiry_actions.php: Default case - REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // Default to respond action for form submissions
                handleRespondToInquiry($pdo, $_POST);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
            }
            break;
    }
} catch (Exception $e) {
    error_log("inquiry_actions.php: Exception caught: " . $e->getMessage());
    error_log("inquiry_actions.php: Exception trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

function handleDeleteInquiry($pdo, $input) {
    if (!isset($input['inquiry_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Inquiry ID is required']);
        return;
    }

    $inquiryId = (int)$input['inquiry_id'];

    try {
        $stmt = $pdo->prepare("DELETE FROM inquiries WHERE Id = ?");
        $result = $stmt->execute([$inquiryId]);

        if ($result && $stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Inquiry deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Inquiry not found or could not be deleted']);
        }
    } catch (PDOException $e) {
        error_log("Delete inquiry error: " . $e->getMessage());
        throw new Exception('Failed to delete inquiry');
    }
}

function handleRespondToInquiry($pdo, $data) {
    // Validate required fields
    if (!isset($data['inquiry_id'], $data['response_type'], $data['response_message'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Required fields missing']);
        return;
    }

    $inquiryId = (int)$data['inquiry_id'];
    $responseType = trim($data['response_type']);
    $responseMessage = trim($data['response_message']);
    
    // Validate and sanitize follow-up date
    $followUpDate = null;
    if (!empty($data['follow_up_date'])) {
        $dateValue = trim($data['follow_up_date']);
        // Check if the value is 'none' or any invalid placeholder
        if (strtolower($dateValue) !== 'none') {
            // Validate that it's a proper date format (YYYY-MM-DD)
            $dateObj = DateTime::createFromFormat('Y-m-d', $dateValue);
            if ($dateObj && $dateObj->format('Y-m-d') === $dateValue) {
                $followUpDate = $dateValue;
            }
        }
    }

    // Validate message length
    if (strlen($responseMessage) < 10) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Response message too short (minimum 10 characters)']);
        return;
    }

    try {
        // Get the inquiry details
        $stmt = $pdo->prepare("SELECT * FROM inquiries WHERE Id = ?");
        $stmt->execute([$inquiryId]);
        $inquiry = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$inquiry) {
            echo json_encode(['success' => false, 'message' => 'Inquiry not found']);
            return;
        }

        // Create inquiry_responses table if it doesn't exist (simplified version without foreign keys)
        $createResponseTable = "CREATE TABLE IF NOT EXISTS inquiry_responses (
            Id INT PRIMARY KEY AUTO_INCREMENT,
            InquiryId INT NOT NULL,
            ResponseType VARCHAR(100) NOT NULL,
            ResponseMessage TEXT NOT NULL,
            FollowUpDate DATE NULL,
            RespondedBy INT NOT NULL,
            ResponseDate DATETIME DEFAULT CURRENT_TIMESTAMP
        )";
        $pdo->exec($createResponseTable);

        // Insert the response
        $stmt = $pdo->prepare("
            INSERT INTO inquiry_responses (InquiryId, ResponseType, ResponseMessage, FollowUpDate, RespondedBy) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $inquiryId,
            $responseType,
            $responseMessage,
            $followUpDate,
            $_SESSION['user_id']
        ]);

        if ($result) {
            // Try to log the action, but don't fail if it doesn't work
            try {
                logAdminAction($pdo, $_SESSION['user_id'], 'RESPOND_INQUIRY', $inquiryId, 
                    "Responded to inquiry from {$inquiry['FullName']} via {$responseType}");
            } catch (Exception $logError) {
                error_log("Failed to log admin action: " . $logError->getMessage());
            }
            // Enhanced notification logic: Notify customer and optionally admins/sales agents
            if (!empty($inquiry['AccountId'])) {
                createNotification($inquiry['AccountId'], null, 'Inquiry Response', "Your inquiry (ID: $inquiryId) has been responded to. Please check your account for details.", 'inquiry', $inquiryId);
            }
            // Optionally notify Admins and SalesAgents for tracking (extensible for multi-channel)
            createNotification(null, 'Admin', 'Inquiry Responded', "Inquiry (ID: $inquiryId) has been responded to by {$_SESSION['user_id']}.", 'inquiry', $inquiryId);
            createNotification(null, 'SalesAgent', 'Inquiry Responded', "Inquiry (ID: $inquiryId) has been responded to by {$_SESSION['user_id']}.", 'inquiry', $inquiryId);
            // Placeholder for future: queueNotificationForChannels(...)
            echo json_encode([
                'success' => true, 
                'message' => 'Response sent successfully',
                'response_id' => $pdo->lastInsertId()
            ]);
        } else {
            throw new Exception('Failed to save response');
        }
    } catch (PDOException $e) {
        error_log("Respond to inquiry error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    } catch (Exception $e) {
        error_log("General error in handleRespondToInquiry: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    }
}

function handleGetInquiry($pdo, $data) {
    if (!isset($data['inquiry_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Inquiry ID is required']);
        return;
    }

    $inquiryId = (int)$data['inquiry_id'];

    try {
        $stmt = $pdo->prepare("
            SELECT 
                i.*,
                a.Username,
                a.FirstName,
                a.LastName,
                a.Status as AccountStatus
            FROM inquiries i
            LEFT JOIN accounts a ON i.AccountId = a.Id
            WHERE i.Id = ?
        ");
        $stmt->execute([$inquiryId]);
        $inquiry = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($inquiry) {
            // Get responses for this inquiry
            $stmt = $pdo->prepare("
                SELECT 
                    ir.*,
                    a.Username as ResponderUsername,
                    a.FirstName as ResponderFirstName,
                    a.LastName as ResponderLastName
                FROM inquiry_responses ir
                LEFT JOIN accounts a ON ir.RespondedBy = a.Id
                WHERE ir.InquiryId = ?
                ORDER BY ir.ResponseDate DESC
            ");
            $stmt->execute([$inquiryId]);
            $responses = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $inquiry['responses'] = $responses;

            echo json_encode(['success' => true, 'inquiry' => $inquiry]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Inquiry not found']);
        }
    } catch (PDOException $e) {
        error_log("Get inquiry error: " . $e->getMessage());
        throw new Exception('Failed to get inquiry details');
    }
}

function handleCreateInquiry($pdo, $data) {
    error_log("inquiry_actions.php: handleCreateInquiry called with data: " . print_r($data, true));
    
    $requiredFields = ['full_name', 'email', 'vehicle_model', 'vehicle_year', 'vehicle_color'];
    
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            error_log("inquiry_actions.php: Missing required field: " . $field);
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Field '$field' is required"]);
            return;
        }
    }

    error_log("inquiry_actions.php: All required fields validated successfully");

    try {
        error_log("inquiry_actions.php: Preparing SQL statement");
        $stmt = $pdo->prepare("
            INSERT INTO inquiries 
            (AccountId, FullName, Email, PhoneNumber, VehicleModel, VehicleVariant, 
             VehicleYear, VehicleColor, TradeInVehicleDetails, FinancingRequired, Comments, CreatedBy) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        // Handle AccountId properly - use 0 if not provided or empty
        $accountId = 0;
        if (isset($data['account_id']) && !empty(trim($data['account_id']))) {
            $accountId = (int)$data['account_id'];
        }
        
        // Set CreatedBy based on user role - only set if user is SalesAgent or Admin
        $createdBy = null;
        if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
            if ($_SESSION['user_role'] === 'SalesAgent' || $_SESSION['user_role'] === 'Admin') {
                $createdBy = (int)$_SESSION['user_id'];
            }
        }
        
        $params = [
            $accountId,
            trim($data['full_name']),
            trim($data['email']),
            trim($data['phone_number'] ?? ''),
            trim($data['vehicle_model']),
            trim($data['vehicle_variant'] ?? ''),
            (int)$data['vehicle_year'],
            trim($data['vehicle_color']),
            trim($data['trade_in_details'] ?? ''),
            trim($data['financing_required'] ?? ''),
            trim($data['comments'] ?? ''),
            $createdBy
        ];
        
        error_log("inquiry_actions.php: Executing SQL with params: " . print_r($params, true));
        $result = $stmt->execute($params);

        if ($result) {
            $inquiryId = $pdo->lastInsertId();
            error_log("inquiry_actions.php: Inquiry created successfully with ID: " . $inquiryId);
            
            // Try to log admin action (don't fail if it doesn't work)
            try {
                logAdminAction($pdo, $_SESSION['user_id'], 'CREATE_INQUIRY', $inquiryId, 
                    "Created inquiry for {$data['full_name']} - {$data['vehicle_model']}");
            } catch (Exception $e) {
                error_log("inquiry_actions.php: Failed to log admin action: " . $e->getMessage());
            }
            
            // Try to create notifications (don't fail if it doesn't work)
            try {
                createNotification(null, 'Admin', 'New Inquiry Submitted', "A new inquiry (ID: $inquiryId) was submitted by {$data['full_name']}.", 'inquiry', $inquiryId);
                createNotification(null, 'SalesAgent', 'New Inquiry Submitted', "A new inquiry (ID: $inquiryId) was submitted by {$data['full_name']}.", 'inquiry', $inquiryId);
                if (!empty($data['account_id'])) {
                    createNotification($data['account_id'], null, 'Inquiry Submitted', "Your inquiry (ID: $inquiryId) has been received. We will contact you soon.", 'inquiry', $inquiryId);
                }
            } catch (Exception $e) {
                error_log("inquiry_actions.php: Failed to create notifications: " . $e->getMessage());
            }
            
            echo json_encode([
                'success' => true, 
                'message' => 'Inquiry created successfully',
                'inquiry_id' => $inquiryId
            ]);
        } else {
            error_log("inquiry_actions.php: SQL execution returned false");
            throw new Exception('Failed to create inquiry');
        }
    } catch (PDOException $e) {
        error_log("inquiry_actions.php: PDO Exception in handleCreateInquiry: " . $e->getMessage());
        throw new Exception('Failed to create inquiry: ' . $e->getMessage());
    }
}

function logAdminAction($pdo, $adminId, $actionType, $targetId, $description) {
    try {
        // Create admin_actions table if it doesn't exist (simplified version without foreign keys)
        $createTable = "CREATE TABLE IF NOT EXISTS admin_actions (
            id INT PRIMARY KEY AUTO_INCREMENT,
            admin_id INT NOT NULL,
            action_type VARCHAR(100) NOT NULL,
            target_id INT,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $pdo->exec($createTable);

        $stmt = $pdo->prepare("
            INSERT INTO admin_actions (admin_id, action_type, target_id, description) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$adminId, $actionType, $targetId, $description]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Log admin action error: " . $e->getMessage());
        // Don't throw exception here as it's just logging
        return false;
    } catch (Exception $e) {
        error_log("General error in logAdminAction: " . $e->getMessage());
        return false;
    }
}

function handleGetAccounts($pdo) {
    try {
        error_log("handleGetAccounts: Starting to fetch customer accounts");

        $userRole = $_SESSION['user_role'] ?? '';
        $userId = $_SESSION['user_id'] ?? null;

        error_log("handleGetAccounts: User role = $userRole, User ID = $userId");

        // Build query based on user role
        if ($userRole === 'SalesAgent') {
            // For Sales Agents: Only show customers assigned to them
            $stmt = $pdo->prepare("
                SELECT
                    a.Id,
                    a.Username,
                    a.FirstName,
                    a.LastName,
                    a.Email,
                    COALESCE(a.Status, 'N/A') as Status,
                    ci.agent_id
                FROM accounts a
                INNER JOIN customer_information ci ON a.Id = ci.account_id
                WHERE a.Role = 'Customer'
                AND ci.agent_id = :agent_id
                AND (a.Status IN ('Active', 'Approved') OR a.Status IS NULL)
                AND (a.IsDisabled IS NULL OR a.IsDisabled = 0)
                ORDER BY a.FirstName, a.LastName
            ");
            $stmt->bindParam(':agent_id', $userId, PDO::PARAM_INT);
        } else {
            // For Admins: Show all customers
            $stmt = $pdo->prepare("
                SELECT
                    a.Id,
                    a.Username,
                    a.FirstName,
                    a.LastName,
                    a.Email,
                    COALESCE(a.Status, 'N/A') as Status
                FROM accounts a
                WHERE a.Role = 'Customer'
                AND (a.Status IN ('Active', 'Approved') OR a.Status IS NULL)
                AND (a.IsDisabled IS NULL OR a.IsDisabled = 0)
                ORDER BY a.FirstName, a.LastName
            ");
        }

        $stmt->execute();
        $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        error_log("handleGetAccounts: Found " . count($accounts) . " customer accounts for role $userRole");
        if (count($accounts) > 0) {
            error_log("handleGetAccounts: First account: " . print_r($accounts[0], true));
        }

        // Clean up the response - ensure all fields are present
        $cleanedAccounts = array_map(function($account) {
            return [
                'Id' => $account['Id'],
                'Username' => $account['Username'] ?? '',
                'FirstName' => $account['FirstName'] ?? '',
                'LastName' => $account['LastName'] ?? '',
                'Email' => $account['Email'] ?? '',
                'Status' => $account['Status'] ?? 'N/A'
            ];
        }, $accounts);

        echo json_encode([
            'success' => true,
            'accounts' => $cleanedAccounts,
            'count' => count($cleanedAccounts)
        ]);
    } catch (PDOException $e) {
        error_log("Get accounts error: " . $e->getMessage());
        error_log("Get accounts error trace: " . $e->getTraceAsString());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to fetch accounts: ' . $e->getMessage()]);
    }
}
?>
