<?php
include_once(dirname(dirname(__DIR__)) . '/includes/init.php');

// Check if user is Sales Agent
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'SalesAgent') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$sales_agent_id = $_SESSION['user_id'];

// Handle different actions
$action = $_REQUEST['action'] ?? '';

switch($action) {
    case 'search_accounts':
        searchCustomerAccounts();
        break;
    case 'add_customer':
        addCustomer();
        break;
    case 'update_customer':
        updateCustomer();
        break;
    case 'get_customer':
        getCustomer();
        break;
    case 'delete_customer':
        deleteCustomer();
        break;
    case 'get_customers':
        getCustomers();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function searchCustomerAccounts() {
    global $connect;
    
    $term = $_GET['term'] ?? '';
    
    try {
        $stmt = $connect->prepare("
            SELECT Id, Username, Email, FirstName, LastName, DateOfBirth, Status
            FROM accounts
            WHERE Role = 'Customer'
            AND (Username LIKE :term OR Email LIKE :term OR FirstName LIKE :term OR LastName LIKE :term OR CONCAT(FirstName, ' ', LastName) LIKE :term)
            LIMIT 10
        ");
        
        $searchTerm = "%{$term}%";
        $stmt->bindParam(':term', $searchTerm);
        $stmt->execute();
        
        $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'accounts' => $accounts]);
    } catch (PDOException $e) {
        error_log("Search accounts error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

function addCustomer() {
    global $connect, $sales_agent_id;

    try {
        $connect->beginTransaction();

        // Validate required fields for walk-in customers
        if (empty($_POST['firstname']) || empty($_POST['lastname']) || empty($_POST['birthday'])) {
            throw new Exception('Required fields are missing');
        }

        // Validate valid ID fields
        if (empty($_POST['valid_id_type']) || empty($_POST['valid_id_number'])) {
            throw new Exception('Valid ID information is required');
        }

        // Generate unique username and email for walk-in customer
        $timestamp = time();
        $random = mt_rand(1000, 9999);
        $username = 'walkin_' . $timestamp . '_' . $random;
        $email = !empty($_POST['email']) ? $_POST['email'] : ('walkin_' . $timestamp . '@temp.mitsubishi.com');

        // Check if email already exists (if provided)
        if (!empty($_POST['email'])) {
            $checkEmail = $connect->prepare("SELECT Id FROM accounts WHERE Email = ?");
            $checkEmail->execute([$_POST['email']]);
            if ($checkEmail->fetch()) {
                throw new Exception('Email address is already registered');
            }
        }

        // Create account first
        $stmt = $connect->prepare("
            INSERT INTO accounts (Username, Email, Role, FirstName, LastName, DateOfBirth, Status, CreatedAt)
            VALUES (?, ?, 'Customer', ?, ?, ?, 'Approved', NOW())
        ");

        $stmt->execute([
            $username,
            $email,
            $_POST['firstname'],
            $_POST['lastname'],
            $_POST['birthday']
        ]);

        $account_id = $connect->lastInsertId();

        // Calculate age
        $birthday = new DateTime($_POST['birthday']);
        $today = new DateTime();
        $age = $today->diff($birthday)->y;

        // Handle valid ID image upload
        $valid_id_image = null;
        if (isset($_FILES['valid_id_image']) && $_FILES['valid_id_image']['error'] === UPLOAD_ERR_OK) {
            $valid_id_image = file_get_contents($_FILES['valid_id_image']['tmp_name']);
        }

        // Note: profile_image is not stored in customer_information table
        // It would need to be stored in accounts.ProfileImage if needed

        // Create customer information - assign to current sales agent
        // Walk-in customers are automatically approved since agent verified them in person
        $stmt = $connect->prepare("
            INSERT INTO customer_information (
                account_id, agent_id, firstname, lastname, middlename, suffix, nationality,
                birthday, age, gender, civil_status, mobile_number, complete_address,
                employment_status, company_name, position, monthly_income,
                valid_id_type, valid_id_image, valid_id_number,
                Status, customer_type
            ) VALUES (
                :account_id, :agent_id, :firstname, :lastname, :middlename, :suffix, :nationality,
                :birthday, :age, :gender, :civil_status, :mobile_number, :complete_address,
                :employment_status, :company_name, :position, :monthly_income,
                :valid_id_type, :valid_id_image, :valid_id_number,
                'Approved', 'Walk In'
            )
        ");

        $stmt->execute([
            ':account_id' => $account_id,
            ':agent_id' => $sales_agent_id,
            ':firstname' => $_POST['firstname'],
            ':lastname' => $_POST['lastname'],
            ':middlename' => $_POST['middlename'] ?: null,
            ':suffix' => $_POST['suffix'] ?: null,
            ':nationality' => $_POST['nationality'] ?: 'Filipino',
            ':birthday' => $_POST['birthday'],
            ':age' => $age,
            ':gender' => $_POST['gender'] ?: null,
            ':civil_status' => $_POST['civil_status'] ?: null,
            ':mobile_number' => $_POST['mobile_number'] ?: null,
            ':complete_address' => $_POST['complete_address'] ?: null,
            ':employment_status' => $_POST['employment_status'] ?: null,
            ':company_name' => $_POST['company_name'] ?: null,
            ':position' => $_POST['position'] ?: null,
            ':monthly_income' => $_POST['monthly_income'] ?: null,
            ':valid_id_type' => $_POST['valid_id_type'],
            ':valid_id_image' => $valid_id_image,
            ':valid_id_number' => $_POST['valid_id_number']
        ]);

        $connect->commit();

        require_once(dirname(dirname(__DIR__)) . '/includes/api/notification_api.php');
        createNotification($account_id, null, 'Customer Info Approved', 'Your customer information has been verified and approved by Sales Agent.', 'customer');
        createNotification(null, 'SalesAgent', 'Walk-in Customer Added', 'Walk-in customer added and approved: ' . $_POST['firstname'] . ' ' . $_POST['lastname'], 'customer');

        echo json_encode(['success' => true, 'message' => 'Walk-in customer added and approved successfully']);

    } catch (Exception $e) {
        if ($connect->inTransaction()) {
            $connect->rollBack();
        }
        error_log("Add customer error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function updateCustomer() {
    global $connect;
    
    try {
        $connect->beginTransaction();
        
        // Validate required fields
        if (empty($_POST['cusID']) || empty($_POST['firstname']) || empty($_POST['lastname']) || empty($_POST['birthday'])) {
            throw new Exception('Required fields are missing');
        }
        
        // Calculate age
        $birthday = new DateTime($_POST['birthday']);
        $today = new DateTime();
        $age = $today->diff($birthday)->y;
        
        // Update customer information
        $stmt = $connect->prepare("
            UPDATE customer_information SET
                firstname = :firstname,
                lastname = :lastname,
                middlename = :middlename,
                suffix = :suffix,
                nationality = :nationality,
                birthday = :birthday,
                age = :age,
                gender = :gender,
                civil_status = :civil_status,
                mobile_number = :mobile_number,
                complete_address = :complete_address,
                employment_status = :employment_status,
                company_name = :company_name,
                position = :position,
                monthly_income = :monthly_income,
                valid_id_type = :valid_id_type,
                valid_id_number = :valid_id_number
            WHERE cusID = :cusID
        ");
        
        $stmt->execute([
            ':cusID' => $_POST['cusID'],
            ':firstname' => $_POST['firstname'],
            ':lastname' => $_POST['lastname'],
            ':middlename' => $_POST['middlename'] ?: null,
            ':suffix' => $_POST['suffix'] ?: null,
            ':nationality' => $_POST['nationality'] ?: null,
            ':birthday' => $_POST['birthday'],
            ':age' => $age,
            ':gender' => $_POST['gender'] ?: null,
            ':civil_status' => $_POST['civil_status'] ?: null,
            ':mobile_number' => $_POST['mobile_number'] ?: null,
            ':complete_address' => $_POST['complete_address'] ?: null,
            ':employment_status' => $_POST['employment_status'] ?: null,
            ':company_name' => $_POST['company_name'] ?: null,
            ':position' => $_POST['position'] ?: null,
            ':monthly_income' => $_POST['monthly_income'] ?: null,
            ':valid_id_type' => $_POST['valid_id_type'] ?: null,
            ':valid_id_number' => $_POST['valid_id_number'] ?: null
        ]);
        
        $connect->commit();
require_once(dirname(dirname(__DIR__)) . '/includes/api/notification_api.php');
createNotification($_POST['cusID'], null, 'Customer Info Updated', 'Your customer information has been updated by Sales Agent.', 'customer');
createNotification(null, 'SalesAgent', 'Customer Info Updated', 'Customer info updated for customer ID ' . $_POST['cusID'], 'customer');
echo json_encode(['success' => true, 'message' => 'Customer information updated successfully']);
        
    } catch (Exception $e) {
        $connect->rollBack();
        error_log("Update customer error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getCustomer() {
    global $connect;
    
    $cusID = $_GET['id'] ?? 0;
    
    try {
        $stmt = $connect->prepare("
            SELECT 
                ci.*, 
                a.Username, 
                a.Email, 
                a.Status as AccountStatus,
                a.FirstName AS AccountFirstName,
                a.LastName AS AccountLastName,
                a.DateOfBirth AS AccountDateOfBirth
            FROM customer_information ci
            INNER JOIN accounts a ON ci.account_id = a.Id
            WHERE ci.cusID = :cusID
        ");
        
        $stmt->bindParam(':cusID', $cusID);
        $stmt->execute();
        
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($customer) {
            echo json_encode(['success' => true, 'customer' => $customer]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Customer not found']);
        }
        
    } catch (PDOException $e) {
        error_log("Get customer error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

function deleteCustomer() {
    global $connect;
    
    $cusID = $_POST['cusID'] ?? 0;
    
    try {
        $stmt = $connect->prepare("DELETE FROM customer_information WHERE cusID = :cusID");
        $stmt->bindParam(':cusID', $cusID);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            require_once(dirname(dirname(__DIR__)) . '/includes/api/notification_api.php');
            createNotification(null, 'SalesAgent', 'Customer Info Deleted', 'Customer info deleted for customer ID ' . $_POST['cusID'], 'customer');
            createNotification(null, 'Admin', 'Customer Info Deleted', 'A customer account was deleted by Sales Agent. Customer ID: ' . $_POST['cusID'], 'customer');
            echo json_encode(['success' => true, 'message' => 'Customer deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Customer not found']);
        }
        
    } catch (PDOException $e) {
        error_log("Delete customer error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

function getCustomers() {
    global $connect;
    
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? 'all';
    $sales_agent_id = $_SESSION['user_id'];
    
    try {
        $query = "
            SELECT
                ci.*,
                a.Username,
                a.Email,
                a.Status as AccountStatus,
                a.LastLoginAt,
                CONCAT(ci.firstname, ' ', ci.lastname) as full_name
            FROM customer_information ci
            INNER JOIN accounts a ON ci.account_id = a.Id
            WHERE a.Role = 'Customer' AND ci.agent_id = :agent_id
        ";
        
        $params = [':agent_id' => $sales_agent_id];
        
        // Add search filter
        if (!empty($search)) {
            $query .= " AND (
                ci.firstname LIKE :search OR
                ci.lastname LIKE :search OR
                a.Email LIKE :search OR
                ci.mobile_number LIKE :search OR
                ci.complete_address LIKE :search
            )";
            $params[':search'] = "%{$search}%";
        }
        
        // Add status filter
        if ($status !== 'all') {
            $query .= " AND ci.Status = :status";
            $params[':status'] = $status;
        }
        
        $query .= " ORDER BY ci.created_at DESC";
        
        $stmt = $connect->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'customers' => $customers]);
        
    } catch (PDOException $e) {
        error_log("Get customers error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}
?>