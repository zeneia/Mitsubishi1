<?php
// Suppress PHP warnings and errors that could corrupt JSON output
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);
ob_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
	ob_end_clean();
	exit(0);
}

include_once(dirname(__DIR__) . '/includes/init.php');

// Check if user is Sales Agent
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'SalesAgent') {
	ob_end_clean();
	http_response_code(403);
	echo json_encode(['error' => 'Access denied. Sales Agent role required.']);
	exit();
}

// Check database connection
if (!$pdo) {
	ob_end_clean();
	http_response_code(500);
	echo json_encode(['error' => 'Database connection not available']);
	exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// For POST requests, also check the action in the request body
if ($method === 'POST' && empty($action)) {
	$input = json_decode(file_get_contents('php://input'), true);
	$action = $input['action'] ?? '';
}

try {
	switch ($method) {
		case 'GET':
			handleGetRequest($action, $pdo);
			break;
		case 'POST':
			handlePostRequest($action, $pdo);
			break;
		case 'PUT':
			handlePutRequest($action, $pdo);
			break;
		default:
			ob_end_clean();
			http_response_code(405);
			echo json_encode(['error' => 'Method not allowed']);
			break;
	}
} catch (Exception $e) {
	ob_end_clean();
	http_response_code(500);
	echo json_encode(['error' => $e->getMessage()]);
}

function handleGetRequest($action, $pdo)
{
	switch ($action) {
		case 'statistics':
			getStatistics($pdo);
			break;
		case 'applications':
			getApplications($pdo);
			break;
		case 'application':
			getApplicationDetails($pdo);
			break;
		case 'download':
			downloadDocument($pdo);
			break;
		default:
			ob_end_clean();
			http_response_code(400);
			echo json_encode(['error' => 'Invalid action']);
			break;
	}
}

function handlePostRequest($action, $pdo)
{
	switch ($action) {
		case 'approve':
			approveApplication($pdo);
			break;
		case 'approve_enhanced':
			approveApplicationEnhanced($pdo);
			break;
		case 'reject':
			rejectApplication($pdo);
			break;
		case 'update_status':
			updateApplicationStatus($pdo);
			break;
		default:
			ob_end_clean();
			http_response_code(400);
			echo json_encode(['error' => 'Invalid action']);
			break;
	}
}

function handlePutRequest($action, $pdo)
{
	switch ($action) {
		case 'update_status':
			updateApplicationStatus($pdo);
			break;
		case 'approve':
			approveApplication($pdo);
			break;
		case 'approve_enhanced':
			approveApplicationEnhanced($pdo);
			break;
		case 'reject':
			rejectApplication($pdo);
			break;
		default:
			ob_end_clean();
			http_response_code(400);
			echo json_encode(['error' => 'Invalid PUT action']);
			break;
	}
}

function getStatistics($pdo)
{
	$agentId = $_SESSION['user_id'];
	
	// Get statistics filtered by agent's assigned customers
	$sql = "SELECT la.status, COUNT(*) as count
			FROM loan_applications la
			INNER JOIN customer_information ci ON la.customer_id = ci.account_id
			WHERE ci.agent_id = :agent_id
			GROUP BY la.status";
	$stmt = $pdo->prepare($sql);
	$stmt->bindParam(':agent_id', $agentId, PDO::PARAM_INT);
	$stmt->execute();
	$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$statistics = [
		'Pending' => 0,
		'Under Review' => 0,
		'Approved' => 0,
		'Rejected' => 0,
		'Completed' => 0
	];

	foreach ($results as $row) {
		$statistics[$row['status']] = (int)$row['count'];
	}

	ob_end_clean();
	echo json_encode(['success' => true, 'data' => $statistics]);
}

function getApplications($pdo)
{
	$search = $_GET['search'] ?? '';
	$status = $_GET['status'] ?? 'all';
	$date_range = $_GET['date_range'] ?? 'all';
	$agentId = $_SESSION['user_id'];


	// Get loan application data filtered by agent's assigned customers
	$sql = "SELECT la.* FROM loan_applications la
			INNER JOIN customer_information ci ON la.customer_id = ci.account_id
			WHERE ci.agent_id = ?";
	$params = [$agentId];

	// Enhanced search functionality including mobile numbers
	if (!empty($search)) {
		// Search in customer information by account_id, names, and mobile numbers (filtered by agent)
		$customerSearchSql = "
			SELECT DISTINCT ci.account_id as id FROM customer_information ci
			LEFT JOIN accounts acc ON ci.account_id = acc.Id
			WHERE ci.agent_id = ? AND (
				LOWER(ci.firstname) LIKE LOWER(?) OR LOWER(ci.lastname) LIKE LOWER(?)
				OR LOWER(CONCAT(ci.firstname, ' ', ci.lastname)) LIKE LOWER(?)
				OR ci.mobile_number LIKE ?
				OR LOWER(acc.FirstName) LIKE LOWER(?) OR LOWER(acc.LastName) LIKE LOWER(?)
				OR LOWER(CONCAT(acc.FirstName, ' ', acc.LastName)) LIKE LOWER(?)
				OR LOWER(acc.Username) LIKE LOWER(?) OR LOWER(acc.Email) LIKE LOWER(?)
			)
			UNION
			SELECT DISTINCT ci.cusID as id FROM customer_information ci
			LEFT JOIN accounts acc ON ci.account_id = acc.Id
			WHERE ci.agent_id = ? AND (
				LOWER(ci.firstname) LIKE LOWER(?) OR LOWER(ci.lastname) LIKE LOWER(?)
				OR LOWER(CONCAT(ci.firstname, ' ', ci.lastname)) LIKE LOWER(?)
				OR ci.mobile_number LIKE ?
				OR LOWER(acc.FirstName) LIKE LOWER(?) OR LOWER(acc.LastName) LIKE LOWER(?)
				OR LOWER(CONCAT(acc.FirstName, ' ', acc.LastName)) LIKE LOWER(?)
				OR LOWER(acc.Username) LIKE LOWER(?) OR LOWER(acc.Email) LIKE LOWER(?)
			)
		";

		$searchTerm = "%{$search}%";
		$customerStmt = $pdo->prepare($customerSearchSql);
		$customerStmt->execute([
			$agentId,        // 1st ? - ci.agent_id (first UNION)
			$searchTerm,     // 2nd ? - ci.firstname
			$searchTerm,     // 3rd ? - ci.lastname  
			$searchTerm,     // 4th ? - CONCAT(ci.firstname, ' ', ci.lastname)
			$searchTerm,     // 5th ? - ci.mobile_number
			$searchTerm,     // 6th ? - acc.FirstName
			$searchTerm,     // 7th ? - acc.LastName
			$searchTerm,     // 8th ? - CONCAT(acc.FirstName, ' ', acc.LastName)
			$searchTerm,     // 9th ? - acc.Username
			$searchTerm,     // 10th ? - acc.Email
			$agentId,        // 11th ? - ci.agent_id (second UNION)
			$searchTerm,     // 12th ? - ci.firstname
			$searchTerm,     // 13th ? - ci.lastname
			$searchTerm,     // 14th ? - CONCAT(ci.firstname, ' ', ci.lastname)
			$searchTerm,     // 15th ? - ci.mobile_number
			$searchTerm,     // 16th ? - acc.FirstName
			$searchTerm,     // 17th ? - acc.LastName
			$searchTerm,     // 18th ? - CONCAT(acc.FirstName, ' ', acc.LastName)
			$searchTerm,     // 19th ? - acc.Username
			$searchTerm      // 20th ? - acc.Email
		]);
		$customerIds = $customerStmt->fetchAll(PDO::FETCH_COLUMN);

		// Also search in vehicle names
		$vehicleSearchSql = "
			SELECT DISTINCT id FROM vehicles 
			WHERE LOWER(model_name) LIKE LOWER(?) OR LOWER(variant) LIKE LOWER(?) 
			OR LOWER(CONCAT(model_name, ' ', variant)) LIKE LOWER(?)
			OR LOWER(CONCAT(model_name, ' ', variant, ' (', year_model, ')')) LIKE LOWER(?)
		";
		$vehicleStmt = $pdo->prepare($vehicleSearchSql);
		$vehicleStmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
		$vehicleIds = $vehicleStmt->fetchAll(PDO::FETCH_COLUMN);

		// Build search conditions
		$searchConditions = [];
		$searchParams = [];

		// Search by application ID
		$searchConditions[] = "LOWER(id) LIKE LOWER(?)";
		$searchParams[] = $searchTerm;

		// Search by customer IDs
		if (!empty($customerIds)) {
			$placeholders = str_repeat('?,', count($customerIds) - 1) . '?';
			$searchConditions[] = "customer_id IN ($placeholders)";
			$searchParams = array_merge($searchParams, $customerIds);
		}

		// Search by vehicle IDs
		if (!empty($vehicleIds)) {
			$placeholders = str_repeat('?,', count($vehicleIds) - 1) . '?';
			$searchConditions[] = "vehicle_id IN ($placeholders)";
			$searchParams = array_merge($searchParams, $vehicleIds);
		}

		if (!empty($searchConditions)) {
			$sql .= " AND (" . implode(" OR ", $searchConditions) . ")";
			$params = array_merge($params, $searchParams);
		}
	}

	// Add status filter
	if ($status !== 'all') {
		$sql .= " AND la.status = ?";
		$params[] = $status;
	}

	// Add date range filter
	if ($date_range !== 'all') {
		switch ($date_range) {
			case 'today':
				$sql .= " AND DATE(la.application_date) = CURDATE()";
				break;
			case 'week':
				$sql .= " AND la.application_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
				break;
			case 'month':
				$sql .= " AND la.application_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
				break;
		}
	}

	$sql .= " ORDER BY la.application_date DESC";

	$stmt = $pdo->prepare($sql);
	$stmt->execute($params);
	$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

	// Enhance each application with additional data
	foreach ($applications as &$app) {
		$app = enhanceApplicationData($app, $pdo);
	}

	ob_end_clean();
	echo json_encode(['success' => true, 'data' => $applications]);
}

function getCustomerData($customerId, $pdo)
{
	if (!$customerId) return ['name' => 'Unknown Customer', 'mobile_number' => 'N/A', 'monthly_income' => 0];

	// First, try to find customer_information by account_id (since loan_applications.customer_id seems to reference account_id)
	$stmt = $pdo->prepare("SELECT ci.*, acc.Email, acc.Username, acc.FirstName, acc.LastName FROM customer_information ci 
						   LEFT JOIN accounts acc ON ci.account_id = acc.Id 
						   WHERE ci.account_id = ?");
	$stmt->execute([$customerId]);
	$customer = $stmt->fetch(PDO::FETCH_ASSOC);

	if ($customer) {
		// Build name from customer_information first, then fallback to accounts
		$firstName = $customer['firstname'] ?: $customer['FirstName'] ?: '';
		$lastName = $customer['lastname'] ?: $customer['LastName'] ?: '';
		$name = trim($firstName . ' ' . $lastName);

		// If no name found, use username or email
		if (empty($name) || $name === ' ') {
			$name = $customer['Username'] ?: $customer['Email'] ?: 'Customer #' . $customerId;
		}

		return [
			'name' => $name,
			'email' => $customer['Email'] ?: 'N/A',
			'mobile_number' => $customer['mobile_number'] ?: 'N/A',
			'monthly_income' => (float)($customer['monthly_income'] ?: 0),
			'full_data' => $customer
		];
	}

	// If not found by account_id, try by cusID (original approach)
	$stmt = $pdo->prepare("SELECT ci.*, acc.Email, acc.Username, acc.FirstName, acc.LastName FROM customer_information ci 
						   LEFT JOIN accounts acc ON ci.account_id = acc.Id 
						   WHERE ci.cusID = ?");
	$stmt->execute([$customerId]);
	$customer = $stmt->fetch(PDO::FETCH_ASSOC);

	if ($customer) {
		$firstName = $customer['firstname'] ?: $customer['FirstName'] ?: '';
		$lastName = $customer['lastname'] ?: $customer['LastName'] ?: '';
		$name = trim($firstName . ' ' . $lastName);

		if (empty($name) || $name === ' ') {
			$name = $customer['Username'] ?: $customer['Email'] ?: 'Customer #' . $customerId;
		}

		return [
			'name' => $name,
			'email' => $customer['Email'] ?: 'N/A',
			'mobile_number' => $customer['mobile_number'] ?: 'N/A',
			'monthly_income' => (float)($customer['monthly_income'] ?: 0),
			'full_data' => $customer
		];
	}

	// If no customer_information record found, try accounts table directly
	$stmt = $pdo->prepare("SELECT * FROM accounts WHERE Id = ?");
	$stmt->execute([$customerId]);
	$account = $stmt->fetch(PDO::FETCH_ASSOC);

	if ($account) {
		$name = trim(($account['FirstName'] ?: '') . ' ' . ($account['LastName'] ?: ''));
		if (empty($name) || $name === ' ') {
			$name = $account['Username'] ?: $account['Email'] ?: 'Customer #' . $customerId;
		}

		return [
			'name' => $name,
			'email' => $account['Email'] ?: 'N/A',
			'mobile_number' => 'N/A', // No mobile in accounts table
			'monthly_income' => 0, // No income in accounts table
			'full_data' => $account
		];
	}

	return [
		'name' => 'Customer #' . $customerId,
		'email' => 'N/A',
		'mobile_number' => 'N/A',
		'monthly_income' => 0
	];
}

function getVehicleData($vehicleId, $pdo)
{
	if (!$vehicleId) return ['name' => 'Unknown Vehicle', 'base_price' => 0];

	$stmt = $pdo->prepare("SELECT * FROM vehicles WHERE id = ?");
	$stmt->execute([$vehicleId]);
	$vehicle = $stmt->fetch(PDO::FETCH_ASSOC);

	if ($vehicle) {
		$vehicleName = $vehicle['model_name'];
		if ($vehicle['variant']) {
			$vehicleName .= ' ' . $vehicle['variant'];
		}
		if ($vehicle['year_model']) {
			$vehicleName .= ' (' . $vehicle['year_model'] . ')';
		}

		return [
			'name' => $vehicleName,
			'base_price' => (float)($vehicle['base_price'] ?? 0), // Return base_price instead of calculated price
			'full_data' => $vehicle,
			// Additional vehicle details for enhanced display
			'engine_type' => $vehicle['engine_type'] ?? 'N/A',
			'transmission' => $vehicle['transmission'] ?? 'N/A',
			'fuel_type' => $vehicle['fuel_type'] ?? 'N/A',
			'seating_capacity' => $vehicle['seating_capacity'] ?? 'N/A',
			'category' => $vehicle['category'] ?? 'N/A',
			'key_features' => $vehicle['key_features'] ?? 'N/A'
		];
	}

	return [
		'name' => 'Vehicle #' . $vehicleId,
		'base_price' => 0,
		'engine_type' => 'N/A',
		'transmission' => 'N/A',
		'fuel_type' => 'N/A',
		'seating_capacity' => 'N/A',
		'category' => 'N/A',
		'key_features' => 'N/A'
	];
}

function getReviewerData($reviewerId, $pdo)
{
	if (!$reviewerId) return null;

	$stmt = $pdo->prepare("SELECT sap.*, acc.FirstName, acc.LastName FROM sales_agent_profiles sap 
						   LEFT JOIN accounts acc ON sap.account_id = acc.Id 
						   WHERE sap.agent_profile_id = ?");
	$stmt->execute([$reviewerId]);
	$reviewer = $stmt->fetch(PDO::FETCH_ASSOC);

	if ($reviewer) {
		$name = trim(($reviewer['FirstName'] ?: '') . ' ' . ($reviewer['LastName'] ?: ''));
		if (empty($name) || $name === ' ') {
			$name = $reviewer['agent_id_number'] ?: 'Agent #' . $reviewerId;
		}

		return [
			'name' => $name,
			'agent_id_number' => $reviewer['agent_id_number'] ?: ''
		];
	}

	return null;
}

function enhanceApplicationData($application, $pdo)
{
	// Get customer information
	$customerData = getCustomerData($application['customer_id'], $pdo);
	$vehicleData = getVehicleData($application['vehicle_id'], $pdo);
	$reviewerData = $application['reviewed_by'] ? getReviewerData($application['reviewed_by'], $pdo) : null;

	return [
		'id' => $application['id'],
		'application_date' => $application['application_date'],
		'status' => $application['status'],
		'notes' => $application['notes'],
		'reviewed_at' => $application['reviewed_at'],
		'approval_notes' => $application['approval_notes'],
		'customer_id' => $application['customer_id'],
		'customer_name' => $customerData['name'] ?? 'Unknown Customer',
		'customer_email' => $customerData['email'] ?? 'N/A',
		'mobile_number' => $customerData['mobile_number'] ?? 'N/A',
		'monthly_income' => $customerData['monthly_income'] ?? 0,
		'vehicle_id' => $application['vehicle_id'],
		'vehicle_name' => $vehicleData['name'] ?? 'Unknown Vehicle',
		'loan_amount' => $vehicleData['base_price'] ?? 0, // Use loan_amount as field name
		'base_price' => $vehicleData['base_price'] ?? 0, // Keep both for compatibility
		'reviewer_name' => $reviewerData['name'] ?? '',
		'agent_id_number' => $reviewerData['agent_id_number'] ?? '',
		// Add vehicle details for display
		'vehicle_engine_type' => $vehicleData['engine_type'] ?? 'N/A',
		'vehicle_transmission' => $vehicleData['transmission'] ?? 'N/A',
		'vehicle_fuel_type' => $vehicleData['fuel_type'] ?? 'N/A',
		'vehicle_seating_capacity' => $vehicleData['seating_capacity'] ?? 'N/A',
		'vehicle_category' => $vehicleData['category'] ?? 'N/A',
		// Payment plan details
		'down_payment' => $application['down_payment'] ?? 0,
		'financing_term' => $application['financing_term'] ?? 0,
		'monthly_payment' => $application['monthly_payment'] ?? 0,
		'total_amount' => $application['total_amount'] ?? 0,
		'interest_rate' => $application['interest_rate'] ?? 0
	];
}

function getApplicationDetails($pdo)
{
	$applicationId = $_GET['id'] ?? 0;
	$agentId = $_SESSION['user_id'];

	if (!$applicationId) {
		ob_end_clean();
		http_response_code(400);
		echo json_encode(['error' => 'Application ID is required']);
		return;
	}

	// Get application details filtered by agent's assigned customers
	$stmt = $pdo->prepare("SELECT la.* FROM loan_applications la
			INNER JOIN customer_information ci ON la.customer_id = ci.account_id
			WHERE la.id = ? AND ci.agent_id = ?");
	$stmt->execute([$applicationId, $agentId]);
	$application = $stmt->fetch(PDO::FETCH_ASSOC);

	if (!$application) {
		ob_end_clean();
		http_response_code(404);
		echo json_encode(['error' => 'Application not found or access denied']);
		return;
	}

	// Get detailed information for each related entity
	$customerData = getCustomerData($application['customer_id'], $pdo);
	$vehicleData = getVehicleData($application['vehicle_id'], $pdo);
	$reviewerData = $application['reviewed_by'] ? getReviewerData($application['reviewed_by'], $pdo) : null;

	// Override vehicle pricing with stored application prices for consistency
	if (isset($application['vehicle_base_price'])) {
		$vehicleData['base_price'] = (float)$application['vehicle_base_price'];
		$vehicleData['promotional_price'] = $application['vehicle_promotional_price'] ? (float)$application['vehicle_promotional_price'] : null;
		$vehicleData['effective_price'] = (float)$application['vehicle_effective_price'];
	}

	// Prepare document list based on applicant type
	$applicantType = $application['applicant_type'] ?? 'EMPLOYED';
	
	// Universal documents (required for all applicant types)
	$documents = [
		'valid_id' => [
			'name' => '2 Valid IDs (Gov\'t Issued)',
			'filename' => $application['valid_id_filename'],
			'type' => $application['valid_id_type'],
			'available' => !empty($application['valid_id_file']),
			'required' => true
		],
		'proof_billing' => [
			'name' => 'Proof of Billing (Original)',
			'filename' => $application['proof_billing_filename'],
			'type' => $application['proof_billing_type'],
			'available' => !empty($application['proof_billing_file']),
			'required' => true
		]
	];
	
	// Add documents based on applicant type
	switch ($applicantType) {
		case 'EMPLOYED':
			$documents['income_source'] = [
				'name' => 'COEC or 3 Months Latest Payslip',
				'filename' => $application['income_source_filename'],
				'type' => $application['income_source_type'],
				'available' => !empty($application['income_source_file']),
				'required' => true
			];
			$documents['itr'] = [
				'name' => 'ITR (2316)',
				'filename' => $application['itr_filename'],
				'type' => $application['itr_type'],
				'available' => !empty($application['itr_file']),
				'required' => true
			];
			// Optional documents for EMPLOYED
			$documents['employment_certificate'] = [
				'name' => 'Employment Certificate (Optional)',
				'filename' => $application['employment_certificate_filename'],
				'type' => $application['employment_certificate_type'],
				'available' => !empty($application['employment_certificate_file']),
				'required' => false
			];
			$documents['payslip'] = [
				'name' => 'Additional Payslip (Optional)',
				'filename' => $application['payslip_filename'],
				'type' => $application['payslip_type'],
				'available' => !empty($application['payslip_file']),
				'required' => false
			];
			$documents['ada_pdc'] = [
				'name' => 'ADA/PDC (Optional)',
				'filename' => $application['ada_pdc_filename'],
				'type' => $application['ada_pdc_type'],
				'available' => !empty($application['ada_pdc_file']),
				'required' => false
			];
			break;
			
		case 'BUSINESS':
			$documents['bank_statement'] = [
				'name' => 'Bank Statement (Latest 3 Months)',
				'filename' => $application['bank_statement_filename'],
				'type' => $application['bank_statement_type'],
				'available' => !empty($application['bank_statement_file']),
				'required' => true
			];
			$documents['itr'] = [
				'name' => 'ITR (1701)',
				'filename' => $application['itr_filename'],
				'type' => $application['itr_type'],
				'available' => !empty($application['itr_file']),
				'required' => true
			];
			$documents['dti_permit'] = [
				'name' => 'DTI Permit / Business Registration',
				'filename' => $application['dti_permit_filename'],
				'type' => $application['dti_permit_type'],
				'available' => !empty($application['dti_permit_file']),
				'required' => true
			];
			$documents['ada_pdc'] = [
				'name' => 'ADA/PDC (Optional)',
				'filename' => $application['ada_pdc_filename'],
				'type' => $application['ada_pdc_type'],
				'available' => !empty($application['ada_pdc_file']),
				'required' => false
			];
			break;
			
		case 'OFW':
			$documents['remittance_proof'] = [
				'name' => 'Proof of Remittance (Latest 3 Months)',
				'filename' => $application['remittance_proof_filename'],
				'type' => $application['remittance_proof_type'],
				'available' => !empty($application['remittance_proof_file']),
				'required' => true
			];
			$documents['contract'] = [
				'name' => 'Latest Contract',
				'filename' => $application['contract_filename'],
				'type' => $application['contract_type'],
				'available' => !empty($application['contract_file']),
				'required' => true
			];
			$documents['spa'] = [
				'name' => 'SPA (Special Power of Attorney)',
				'filename' => $application['spa_filename'],
				'type' => $application['spa_type'],
				'available' => !empty($application['spa_file']),
				'required' => true
			];
			$documents['ada_pdc'] = [
				'name' => 'ADA/PDC (Optional)',
				'filename' => $application['ada_pdc_filename'],
				'type' => $application['ada_pdc_type'],
				'available' => !empty($application['ada_pdc_file']),
				'required' => false
			];
			break;
	}

	// Build comprehensive response
	$response = [
		'id' => $application['id'],
		'application_date' => $application['application_date'],
		'status' => $application['status'],
		'applicant_type' => $applicantType,
		'notes' => $application['notes'],
		'reviewed_at' => $application['reviewed_at'],
		'approval_notes' => $application['approval_notes'],
		'customer_id' => $application['customer_id'],
		'customer_name' => $customerData['name'],
		'customer_email' => $customerData['email'] ?? 'N/A',
		'vehicle_id' => $application['vehicle_id'],
		'vehicle_name' => $vehicleData['name'],
		'loan_amount' => $vehicleData['base_price'] ?? 0, // Use loan_amount as field name
		'base_price' => $vehicleData['base_price'] ?? 0, // Keep both for compatibility
		// Vehicle pricing fields from stored application data
		'vehicle_base_price' => $vehicleData['base_price'] ?? 0,
		'vehicle_promotional_price' => $vehicleData['promotional_price'] ?? null,
		'vehicle_effective_price' => $vehicleData['effective_price'] ?? ($vehicleData['base_price'] ?? 0),
		'reviewer_name' => $reviewerData['name'] ?? '',
		// Personal information from application
		'first_name' => $application['first_name'] ?? '',
		'last_name' => $application['last_name'] ?? '',
		'middle_name' => $application['middle_name'] ?? '',
		'suffix' => $application['suffix'] ?? '',
		'email' => $application['email'] ?? '',
		'mobile_number' => $application['mobile_number'] ?? '',
		'date_of_birth' => $application['date_of_birth'] ?? '',
		'age' => $application['age'] ?? 0,
		'gender' => $application['gender'] ?? '',
		'civil_status' => $application['civil_status'] ?? '',
		'nationality' => $application['nationality'] ?? '',
		'address' => $application['address'] ?? '',
		// Loan application specific fields
		'down_payment' => $application['down_payment'] ?? 0,
		'financing_term' => $application['financing_term'] ?? 0,
		'monthly_payment' => $application['monthly_payment'] ?? 0,
		'total_amount' => $application['total_amount'] ?? 0,
		'interest_rate' => $application['interest_rate'] ?? 0,
		'loan_purpose' => $application['loan_purpose'] ?? '',
		'preferred_color' => $application['preferred_color'] ?? '',
		// Employment information
		'employment_status' => $application['employment_status'] ?? '',
		'company_name' => $application['company_name'] ?? '',
		'position' => $application['position'] ?? '',
		'years_employed' => $application['years_employed'] ?? 0,
		'monthly_income' => $application['monthly_income'] ?? 0,
		'other_income' => $application['other_income'] ?? 0,
		'company_address' => $application['company_address'] ?? '',
		'company_contact' => $application['company_contact'] ?? '',
		'documents' => $documents
	];

	// Add detailed customer information if available
	if (isset($customerData['full_data'])) {
		$customer = $customerData['full_data'];
		$response['customer_details'] = [
			'firstname' => $customer['firstname'] ?? '',
			'lastname' => $customer['lastname'] ?? '',
			'middlename' => $customer['middlename'] ?? '',
			'suffix' => $customer['suffix'] ?? '',
			'nationality' => $customer['nationality'] ?? '',
			'birthday' => $customer['birthday'] ?? '',
			'age' => $customer['age'] ?? '',
			'gender' => $customer['gender'] ?? '',
			'civil_status' => $customer['civil_status'] ?? '',
			'mobile_number' => $customer['mobile_number'] ?? '',
			'employment_status' => $customer['employment_status'] ?? '',
			'company_name' => $customer['company_name'] ?? '',
			'position' => $customer['position'] ?? '',
			'monthly_income' => $customer['monthly_income'] ?? 0,
			'valid_id_type' => $customer['valid_id_type'] ?? '',
			'valid_id_number' => $customer['valid_id_number'] ?? ''
		];
	}

	// Add detailed vehicle information if available
	if (isset($vehicleData['full_data'])) {
		$vehicle = $vehicleData['full_data'];
		$response['vehicle_details'] = [
			'model_name' => $vehicle['model_name'] ?? '',
			'variant' => $vehicle['variant'] ?? '',
			'year_model' => $vehicle['year_model'] ?? '',
			'category' => $vehicle['category'] ?? '',
			'engine_type' => $vehicle['engine_type'] ?? '',
			'transmission' => $vehicle['transmission'] ?? '',
			'fuel_type' => $vehicle['fuel_type'] ?? '',
			'seating_capacity' => $vehicle['seating_capacity'] ?? '',
			'key_features' => $vehicle['key_features'] ?? '',
			'base_price' => $vehicle['base_price'] ?? 0,
			'promotional_price' => $vehicle['promotional_price'] ?? 0,
			'min_downpayment_percentage' => $vehicle['min_downpayment_percentage'] ?? 0,
			'financing_terms' => $vehicle['financing_terms'] ?? '',
			'color_options' => $vehicle['color_options'] ?? ''
		];
	}

	ob_end_clean();
	echo json_encode(['success' => true, 'data' => $response]);
}

function downloadDocument($pdo)
{
	$applicationId = $_GET['id'] ?? 0;
	$documentType = $_GET['type'] ?? '';

	if (!$applicationId || !$documentType) {
		ob_end_clean();
		http_response_code(400);
		echo json_encode(['error' => 'Application ID and document type are required']);
		return;
	}

	$validTypes = [
		'valid_id', 'proof_billing', 'income_source', 'employment_certificate', 'payslip', 'itr', 
		'bank_statement', 'company_id', 'dti_permit', 'ada_pdc',
		'remittance_proof', 'contract', 'spa'
	];
	if (!in_array($documentType, $validTypes)) {
		ob_end_clean();
		http_response_code(400);
		echo json_encode(['error' => 'Invalid document type']);
		return;
	}

	$fileColumn = $documentType . '_file';
	$filenameColumn = $documentType . '_filename';
	$typeColumn = $documentType . '_type';

	$sql = "SELECT {$fileColumn} as file_data, {$filenameColumn} as filename, {$typeColumn} as file_type 
            FROM loan_applications WHERE id = ?";

	$stmt = $pdo->prepare($sql);
	$stmt->execute([$applicationId]);
	$result = $stmt->fetch(PDO::FETCH_ASSOC);

	if (!$result || empty($result['file_data'])) {
		ob_end_clean();
		http_response_code(404);
		echo json_encode(['error' => 'Document not found']);
		return;
	}

	header('Content-Type: ' . ($result['file_type'] ?: 'application/octet-stream'));
	header('Content-Disposition: attachment; filename="' . ($result['filename'] ?: 'document') . '"');
	header('Content-Length: ' . strlen($result['file_data']));

	if (ob_get_level()) {
		ob_end_clean();
	}

	echo $result['file_data'];
	exit;
}

function approveApplication($pdo)
{
	$input = json_decode(file_get_contents('php://input'), true);
	$applicationId = $input['id'] ?? 0;
	$notes = $input['notes'] ?? '';
	$agentId = $_SESSION['user_id'];

	if (!$applicationId) {
		ob_end_clean();
		http_response_code(400);
		echo json_encode(['error' => 'Application ID is required']);
		return;
	}

	$agentStmt = $pdo->prepare("SELECT agent_profile_id FROM sales_agent_profiles WHERE account_id = ?");
	$agentStmt->execute([$agentId]);
	$agentProfile = $agentStmt->fetch(PDO::FETCH_ASSOC);

	if (!$agentProfile) {
		ob_end_clean();
		http_response_code(400);
		echo json_encode(['error' => 'Agent profile not found']);
		return;
	}

	try {
		// Start transaction
		$pdo->beginTransaction();

		// Get loan application details with stored vehicle prices (filtered by agent)
		$loanStmt = $pdo->prepare("SELECT la.*, ci.cusID, ci.account_id, v.model_name, v.variant, v.stock_quantity, v.popular_color
									 FROM loan_applications la
									 JOIN customer_information ci ON la.customer_id = ci.account_id
									 JOIN vehicles v ON la.vehicle_id = v.id
									 WHERE la.id = ? AND ci.agent_id = ?");
		$loanStmt->execute([$applicationId, $agentId]);
		$loanData = $loanStmt->fetch(PDO::FETCH_ASSOC);

		if (!$loanData) {
			throw new Exception('Loan application not found or access denied');
		}

		// Check if vehicle has sufficient stock
		if ($loanData['stock_quantity'] <= 0) {
			throw new Exception('Vehicle is out of stock');
		}

		// Update loan application status
		$sql = "UPDATE loan_applications 
	            SET status = 'Approved', reviewed_by = ?, reviewed_at = NOW(), approval_notes = ?, updated_at = NOW()
	            WHERE id = ?";

		$stmt = $pdo->prepare($sql);
		$success = $stmt->execute([$agentProfile['agent_profile_id'], $notes, $applicationId]);

		if (!$success) {
			throw new Exception('Failed to approve application');
		}

		// Create order from approved loan application using stored vehicle prices
		$orderNumber = generateLoanOrderNumber();
		// Use stored effective price from loan application for consistency
		$effectivePrice = $loanData['vehicle_effective_price'] ?? 
						 (($loanData['vehicle_promotional_price'] && $loanData['vehicle_promotional_price'] > 0 && $loanData['vehicle_promotional_price'] < $loanData['vehicle_base_price']) 
							? $loanData['vehicle_promotional_price'] 
							: $loanData['vehicle_base_price']);

		$orderSql = "INSERT INTO orders (
						order_number, customer_id, sales_agent_id, vehicle_id, client_type,
						vehicle_model, vehicle_variant, vehicle_color, model_year,
						base_price, discount_amount, total_price, payment_method,
						down_payment, financing_term, monthly_payment, order_status,
						order_notes, created_at, order_date
					) VALUES (?, ?, ?, ?, 'handled', ?, ?, ?, YEAR(NOW()), ?, 0, ?, 'financing', ?, ?, ?, 'confirmed', ?, NOW(), NOW())";

		$orderStmt = $pdo->prepare($orderSql);
		$orderSuccess = $orderStmt->execute([
			$orderNumber,
			$loanData['cusID'],
			$agentId,
			$loanData['vehicle_id'],
			$loanData['model_name'],
			$loanData['variant'],
			$loanData['popular_color'] ?? 'Standard',
			$effectivePrice,
			$effectivePrice,
			$loanData['down_payment'] ?? 0,
			$loanData['financing_term'] ?? 12,
			$loanData['monthly_payment'] ?? 0,
			'Order created from approved loan application #' . $applicationId . '. ' . $notes
		]);

		if (!$orderSuccess) {
			throw new Exception('Failed to create order');
		}

		$orderId = $pdo->lastInsertId();

		// Generate payment schedule if financing terms are available
		if (($loanData['down_payment'] ?? 0) > 0 && ($loanData['financing_term'] ?? 0) > 0 && ($loanData['monthly_payment'] ?? 0) > 0) {
			$paymentScheduleSql = "INSERT INTO payment_schedule (order_id, payment_number, due_date, amount_due, status) VALUES (?, ?, ?, ?, 'pending')";
			$paymentScheduleStmt = $pdo->prepare($paymentScheduleSql);
			
			for ($i = 1; $i <= ($loanData['financing_term'] ?? 12); $i++) {
				$dueDate = date('Y-m-d', strtotime("+$i month"));
				$paymentScheduleStmt->execute([$orderId, $i, $dueDate, $loanData['monthly_payment'] ?? 0]);
			}
		}

		// Decrease vehicle inventory
		$inventoryStmt = $pdo->prepare("UPDATE vehicles SET stock_quantity = stock_quantity - 1 WHERE id = ?");
		$inventorySuccess = $inventoryStmt->execute([$loanData['vehicle_id']]);

		if (!$inventorySuccess) {
			throw new Exception('Failed to update inventory');
		}

		// Commit transaction
		$pdo->commit();

		// Notify customer and admins (in-app notifications)
		require_once dirname(__DIR__) . '/includes/api/notification_api.php';
		createNotification($loanData['account_id'], null, 'Loan Application Approved & Order Created',
						  'Your loan application #' . $applicationId . ' has been approved and order #' . $orderNumber . ' has been created.',
						  'loan', $applicationId);
		createNotification(null, 'Admin', 'Loan Application Approved & Order Created',
						  'Loan application #' . $applicationId . ' has been approved and order #' . $orderNumber . ' has been created.',
						  'loan', $applicationId);

		// Send email and SMS notifications
		try {
			require_once dirname(__DIR__) . '/includes/services/NotificationService.php';
			$notificationService = new NotificationService($pdo);
			$notificationService->sendLoanApprovalNotification($applicationId, $orderNumber);
		} catch (Exception $notifError) {
			// Log error but don't fail the approval
			error_log("Loan approval notification error: " . $notifError->getMessage());
		}

		ob_end_clean();
		echo json_encode([
			'success' => true, 
			'message' => 'Application approved successfully and order created',
			'data' => [
				'order_id' => $orderId,
				'order_number' => $orderNumber,
				'remaining_stock' => $loanData['stock_quantity'] - 1
			]
		]);

	} catch (Exception $e) {
		// Rollback transaction on error
		$pdo->rollback();
		ob_end_clean();
		http_response_code(400);
		echo json_encode(['error' => $e->getMessage()]);
	}
}

function approveApplicationEnhanced($pdo)
{
	$input = json_decode(file_get_contents('php://input'), true);
	$applicationId = $input['id'] ?? 0;
	$notes = $input['notes'] ?? '';
	$agentId = $_SESSION['user_id'];

	if (!$applicationId) {
		ob_end_clean();
		http_response_code(400);
		echo json_encode(['error' => 'Application ID is required']);
		return;
	}

	$agentStmt = $pdo->prepare("SELECT agent_profile_id FROM sales_agent_profiles WHERE account_id = ?");
	$agentStmt->execute([$agentId]);
	$agentProfile = $agentStmt->fetch(PDO::FETCH_ASSOC);

	if (!$agentProfile) {
		ob_end_clean();
		http_response_code(400);
		echo json_encode(['error' => 'Agent profile not found']);
		return;
	}

	try {
		// Start transaction
		$pdo->beginTransaction();

		// Get loan application details with stored vehicle prices (filtered by agent)
		$loanStmt = $pdo->prepare("SELECT la.*, ci.cusID, ci.account_id, v.model_name, v.variant, v.stock_quantity, v.popular_color
									 FROM loan_applications la
									 JOIN customer_information ci ON la.customer_id = ci.account_id
									 JOIN vehicles v ON la.vehicle_id = v.id
									 WHERE la.id = ? AND ci.agent_id = ?");
		$loanStmt->execute([$applicationId, $agentId]);
		$loanData = $loanStmt->fetch(PDO::FETCH_ASSOC);

		if (!$loanData) {
			throw new Exception('Loan application not found or access denied');
		}

		// Check if vehicle has sufficient stock
		if ($loanData['stock_quantity'] <= 0) {
			throw new Exception('Vehicle is out of stock');
		}

		// Validate financing terms using centralized calculator
		require_once dirname(__DIR__) . '/includes/payment_calculator.php';
		
		// Use stored effective price from loan application for consistency
		$effectivePrice = $loanData['vehicle_effective_price'] ?? 
						 (($loanData['vehicle_promotional_price'] && $loanData['vehicle_promotional_price'] > 0 && $loanData['vehicle_promotional_price'] < $loanData['vehicle_base_price']) 
							? $loanData['vehicle_promotional_price'] 
							: $loanData['vehicle_base_price']);
		
		$calculator = new PaymentCalculator($pdo);
		$validationResult = $calculator->calculatePlan(
			$effectivePrice,
			$loanData['down_payment'],
			$loanData['financing_term']
		);
		
		// Verify calculated monthly payment matches application
		$calculatedPayment = $validationResult['monthly_payment'];
		$applicationPayment = round(floatval($loanData['monthly_payment']), 2);
		$paymentDifference = abs($calculatedPayment - $applicationPayment);
		
		if ($paymentDifference > 1.0) { // Allow ₱1 tolerance for rounding
			// Fallback: recompute using stored application interest_rate if available
			$altPayment = null;
			$storedRate = isset($loanData['interest_rate']) ? floatval($loanData['interest_rate']) : 0.0;
			if ($storedRate > 0) {
				// Support both percent (e.g., 10.5) and decimal (e.g., 0.105)
				$annualRateDecimal = ($storedRate > 1.0) ? ($storedRate / 100.0) : $storedRate;
				$principal = max(0.0, floatval($effectivePrice) - floatval($loanData['down_payment']));
				// Reuse calculator's amortization to ensure identical rounding
				$altAmort = $calculator->calculateAmortization($principal, $annualRateDecimal, intval($loanData['financing_term']));
				$altPayment = $altAmort['monthly_payment'];
				if (abs($altPayment - $applicationPayment) <= 1.0) {
					// Accept and use the payment computed from stored rate
					$calculatedPayment = $altPayment;
					$paymentDifference = 0.0;
				}
			}
		}
		
		if ($paymentDifference > 1.0) {
			// Attempt automatic reconciliation for legacy records within a safe threshold (<= 5%)
			$differencePercent = ($calculatedPayment > 0) ? ($paymentDifference / $calculatedPayment) : 1.0;
			if ($differencePercent <= 0.05) {
				// Update application with authoritative server-calculated values and proceed
				$upd = $pdo->prepare("UPDATE loan_applications SET monthly_payment = ?, total_amount = ?, interest_rate = ?, updated_at = NOW() WHERE id = ?");
				$upd->execute([
					$validationResult['monthly_payment'],
					$validationResult['total_amount'],
					$validationResult['interest_rate_percent'],
					$applicationId
				]);
				// Align comparisons to validated value for subsequent use
				$applicationPayment = $validationResult['monthly_payment'];
				$calculatedPayment = $validationResult['monthly_payment'];
				$paymentDifference = 0.0;
				// Record reconciliation in notes for audit trail
				$notes = trim(($notes ? ($notes . ' ') : '') . '(Auto-reconciled payment to server-calculated values during approval)');
			} else {
				throw new Exception(sprintf(
					'Payment mismatch: Application shows ₱%.2f but calculated payment is ₱%.2f',
					$applicationPayment,
					$calculatedPayment
				));
			}
		}

		// Update loan application status
		$sql = "UPDATE loan_applications 
	            SET status = 'Approved', reviewed_by = ?, reviewed_at = NOW(), approval_notes = ?, updated_at = NOW()
	            WHERE id = ?";

		$stmt = $pdo->prepare($sql);
		$success = $stmt->execute([$agentProfile['agent_profile_id'], $notes, $applicationId]);

		if (!$success) {
			throw new Exception('Failed to approve application');
		}

		// Create order from approved loan application
		$orderNumber = generateLoanOrderNumber();

		$orderSql = "INSERT INTO orders (
						order_number, customer_id, sales_agent_id, vehicle_id, client_type,
						vehicle_model, vehicle_variant, vehicle_color, model_year,
						base_price, discount_amount, total_price, payment_method,
						down_payment, financing_term, monthly_payment, order_status,
						order_notes, created_at, order_date
					) VALUES (?, ?, ?, ?, 'handled', ?, ?, ?, YEAR(NOW()), ?, 0, ?, 'financing', ?, ?, ?, 'confirmed', ?, NOW(), NOW())";

		$orderStmt = $pdo->prepare($orderSql);
		$orderSuccess = $orderStmt->execute([
			$orderNumber,
			$loanData['cusID'],
			$agentId,
			$loanData['vehicle_id'],
			$loanData['model_name'],
			$loanData['variant'],
			$loanData['popular_color'] ?? 'Standard',
			$effectivePrice,
			$effectivePrice,
			$loanData['down_payment'],
			$loanData['financing_term'],
			$calculatedPayment, // Use validated payment
			'Order created from enhanced approved loan application #' . $applicationId . '. Financing validated. ' . $notes
		]);

		if (!$orderSuccess) {
			throw new Exception('Failed to create order');
		}

		$orderId = $pdo->lastInsertId();

		// Decrease vehicle inventory
		$inventoryStmt = $pdo->prepare("UPDATE vehicles SET stock_quantity = stock_quantity - 1 WHERE id = ?");
		$inventorySuccess = $inventoryStmt->execute([$loanData['vehicle_id']]);

		if (!$inventorySuccess) {
			throw new Exception('Failed to update inventory');
		}

		// Commit transaction
		$pdo->commit();

		// Notify customer and admins (in-app notifications)
		require_once dirname(__DIR__) . '/includes/api/notification_api.php';
		createNotification($loanData['account_id'], null, 'Loan Application Approved & Order Created',
					  'Your loan application #' . $applicationId . ' has been approved with validated financing terms and order #' . $orderNumber . ' has been created.',
					  'loan', $applicationId);
		createNotification(null, 'Admin', 'Loan Application Approved & Order Created',
					  'Loan application #' . $applicationId . ' has been approved with enhanced validation and order #' . $orderNumber . ' has been created.',
					  'loan', $applicationId);

		// Send email and SMS notifications
		try {
			require_once dirname(__DIR__) . '/includes/services/NotificationService.php';
			$notificationService = new NotificationService($pdo);
			$notificationService->sendLoanApprovalNotification($applicationId, $orderNumber);
		} catch (Exception $notifError) {
			// Log error but don't fail the approval
			error_log("Loan approval notification error: " . $notifError->getMessage());
		}

		ob_end_clean();
		echo json_encode([
			'success' => true, 
			'message' => 'Application approved successfully with validated financing terms and order created',
			'data' => [
				'order_id' => $orderId,
				'order_number' => $orderNumber,
				'remaining_stock' => $loanData['stock_quantity'] - 1,
				'validated_payment' => $calculatedPayment,
				'financing_validated' => true
			]
		]);

	} catch (Exception $e) {
		// Rollback transaction on error
		$pdo->rollback();
		ob_end_clean();
		http_response_code(400);
		echo json_encode(['error' => $e->getMessage(), 'validation_failed' => true]);
	}
}

function rejectApplication($pdo)
{
	$input = json_decode(file_get_contents('php://input'), true);
	$applicationId = $input['id'] ?? 0;
	$notes = $input['notes'] ?? '';
	$agentId = $_SESSION['user_id'];

	if (!$applicationId) {
		ob_end_clean();
		http_response_code(400);
		echo json_encode(['error' => 'Application ID is required']);
		return;
	}

	$agentStmt = $pdo->prepare("SELECT agent_profile_id FROM sales_agent_profiles WHERE account_id = ?");
	$agentStmt->execute([$agentId]);
	$agentProfile = $agentStmt->fetch(PDO::FETCH_ASSOC);

	if (!$agentProfile) {
		ob_end_clean();
		http_response_code(400);
		echo json_encode(['error' => 'Agent profile not found']);
		return;
	}

	$sql = "UPDATE loan_applications 
            SET status = 'Rejected', reviewed_by = ?, reviewed_at = NOW(), approval_notes = ?, updated_at = NOW()
            WHERE id = ?";

	$stmt = $pdo->prepare($sql);
	$success = $stmt->execute([$agentProfile['agent_profile_id'], $notes, $applicationId]);

	if ($success) {
		// Notify customer and admins (in-app notifications)
		require_once dirname(__DIR__) . '/includes/api/notification_api.php';
		$stmt = $pdo->prepare("SELECT la.customer_id FROM loan_applications la
			INNER JOIN customer_information ci ON la.customer_id = ci.account_id
			WHERE la.id = ? AND ci.agent_id = ?");
		$stmt->execute([$applicationId, $agentId]);
		$customerId = $stmt->fetchColumn();
		createNotification($customerId, null, 'Loan Application Rejected', 'Your loan application #' . $applicationId . ' has been rejected.', 'loan', $applicationId);
		createNotification(null, 'Admin', 'Loan Application Rejected', 'Loan application #' . $applicationId . ' has been rejected.', 'loan', $applicationId);

		// Send email and SMS notifications
		try {
			require_once dirname(__DIR__) . '/includes/services/NotificationService.php';
			$notificationService = new NotificationService($pdo);
			$notificationService->sendLoanRejectionNotification($applicationId, $notes);
		} catch (Exception $notifError) {
			// Log error but don't fail the rejection
			error_log("Loan rejection notification error: " . $notifError->getMessage());
		}

		ob_end_clean();
		echo json_encode(['success' => true, 'message' => 'Application rejected successfully']);
	} else {
		throw new Exception('Failed to reject application');
	}
}

function updateApplicationStatus($pdo)
{
	$input = json_decode(file_get_contents('php://input'), true);
	$applicationId = $input['id'] ?? 0;
	$status = $input['status'] ?? '';
	$agentId = $_SESSION['user_id'];

	if (!$applicationId || !$status) {
		ob_end_clean();
		http_response_code(400);
		echo json_encode(['error' => 'Application ID and status are required']);
		return;
	}

	$validStatuses = ['Pending', 'Under Review', 'Approved', 'Rejected', 'Completed'];
	if (!in_array($status, $validStatuses)) {
		ob_end_clean();
		http_response_code(400);
		echo json_encode(['error' => 'Invalid status']);
		return;
	}

	$agentStmt = $pdo->prepare("SELECT agent_profile_id FROM sales_agent_profiles WHERE account_id = ?");
	$agentStmt->execute([$agentId]);
	$agentProfile = $agentStmt->fetch(PDO::FETCH_ASSOC);

	if (!$agentProfile) {
		ob_end_clean();
		http_response_code(400);
		echo json_encode(['error' => 'Agent profile not found']);
		return;
	}

	$sql = "UPDATE loan_applications 
            SET status = ?, reviewed_by = ?, reviewed_at = NOW(), updated_at = NOW()
            WHERE id = ?";

	$stmt = $pdo->prepare($sql);
	$success = $stmt->execute([$status, $agentProfile['agent_profile_id'], $applicationId]);

	if ($success) {
		// Notify customer and admins
		require_once dirname(__DIR__) . '/includes/api/notification_api.php';
		$stmt = $pdo->prepare("SELECT customer_id FROM loan_applications WHERE id = ?");
		$stmt->execute([$applicationId]);
		$customerId = $stmt->fetchColumn();
		createNotification($customerId, null, 'Loan Application Status Updated', 'Your loan application #' . $applicationId . ' status changed to ' . $status . '.', 'loan', $applicationId);
		createNotification(null, 'Admin', 'Loan Application Status Updated', 'Loan application #' . $applicationId . ' status changed to ' . $status . '.', 'loan', $applicationId);
		ob_end_clean();
		echo json_encode(['success' => true, 'message' => 'Application status updated successfully']);
	} else {
		throw new Exception('Failed to update application status');
	}
}

/**
 * Generate order number for loan-based orders
 */
function generateLoanOrderNumber() {
    $now = new DateTime();
    $year = $now->format('y');
    $month = $now->format('m');
    $day = $now->format('d');
    $random = str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
    return "LOAN-{$year}{$month}{$day}{$random}";
}

function getCustomerDetails($pdo, $customerId) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$customerId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
