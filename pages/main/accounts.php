<?php
// Handle AJAX requests FIRST before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Clean output buffer and start fresh
    while (ob_get_level()) {
        ob_end_clean();
    }
    ob_start();

    // Include dependencies
    include_once(dirname(dirname(__DIR__)) . '/includes/init.php');
    include_once(dirname(dirname(__DIR__)) . '/includes/database/accounts_operations.php');
    include_once(dirname(dirname(__DIR__)) . '/includes/database/customer_operations.php');

    $accountsOp = new AccountsOperations();
    $customerOp = new CustomerOperations();

    // Set JSON header
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'create':
            $result = $accountsOp->createAccount($_POST);
            $message = $result ? 'Account created successfully' : ($accountsOp->getLastError() ?: 'Failed to create account');
            if ($result) {
                require_once(dirname(dirname(__DIR__)) . '/includes/api/notification_api.php');
                createNotification(null, 'Admin', 'Account Created', 'A new account has been created: ' . ($_POST['username'] ?? ''), 'account');
            }
            ob_clean();
            echo json_encode(['success' => $result, 'message' => $message]);
            exit;

        case 'update':
            $result = $accountsOp->updateAccount($_POST['id'], $_POST);
            if ($result) {
                require_once(dirname(dirname(__DIR__)) . '/includes/api/notification_api.php');
                createNotification(null, 'Admin', 'Account Updated', 'Account updated: ' . ($_POST['username'] ?? ''), 'account');
            }
            ob_clean();
            echo json_encode(['success' => $result, 'message' => $result ? 'Account updated successfully' : 'Failed to update account']);
            exit;

        case 'delete':
            $delId = intval($_POST['id'] ?? 0);
            // If deleting a SalesAgent, reassign their customers first
            $acct = $accountsOp->getAccountById($delId);
            if ($acct && ($acct['Role'] ?? '') === 'SalesAgent') {
                $reassigned = $customerOp->reassignCustomersFromAgent($delId);
            }
            $result = $accountsOp->deleteAccount($delId);
            if ($result) {
                require_once(dirname(dirname(__DIR__)) . '/includes/api/notification_api.php');
                createNotification(null, 'Admin', 'Account Deleted', 'Account deleted: ID ' . $delId, 'account');
            }
            ob_clean();
            echo json_encode(['success' => $result, 'message' => $result ? 'Account deleted successfully' : 'Failed to delete account']);
            exit;

        case 'get_account':
            $account = $accountsOp->getAccountById($_POST['id']);
            ob_clean();
            echo json_encode(['success' => !!$account, 'data' => $account]);
            exit;
            
        case 'view_customer':
            $accountId = intval($_POST['account_id'] ?? 0);

            // First, get the account information
            $account = $accountsOp->getAccountById($accountId);
            if (!$account) {
                // Clean all output buffers
                while (ob_get_level()) {
                    ob_end_clean();
                }
                ob_start();
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Account not found']);
                exit;
            }

            // Try to get customer information (may not exist if profile incomplete)
            $customer = $customerOp->getCustomerByAccountId($accountId);

            // Debug logging (goes to error log, not output)
            error_log("view_customer: accountId={$accountId}, has_customer_data=" . ($customer ? 'yes' : 'no'));

            // If customer_information doesn't exist, create a basic data structure from account
            if (!$customer) {
                $customer = [
                    'account_id' => $account['Id'],
                    'Username' => $account['Username'],
                    'Email' => $account['Email'],
                    'Role' => $account['Role'],
                    'FirstName' => $account['FirstName'] ?? '',
                    'LastName' => $account['LastName'] ?? '',
                    'CreatedAt' => $account['CreatedAt'] ?? '',
                    'LastLoginAt' => $account['LastLoginAt'] ?? '',
                    'Status' => 'Incomplete Profile',
                    'profile_incomplete' => true
                ];
            } else {
                // Sanitize all string fields to ensure valid UTF-8
                foreach ($customer as $key => $value) {
                    if (is_string($value)) {
                        // Remove invalid UTF-8 characters and convert to UTF-8
                        $customer[$key] = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
                    }
                }
            }

            // Clean all output buffers and ensure clean JSON response
            while (ob_get_level()) {
                ob_end_clean();
            }
            ob_start();
            header('Content-Type: application/json; charset=UTF-8');

            // Encode with error handling and UTF-8 options
            $jsonData = json_encode(
                ['success' => true, 'data' => $customer],
                JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE
            );

            if ($jsonData === false) {
                // JSON encoding failed - log the error
                error_log("JSON encoding error for account_id={$accountId}: " . json_last_error_msg());
                error_log("Customer data keys: " . implode(', ', array_keys($customer)));

                // Try to identify problematic fields
                foreach ($customer as $key => $value) {
                    $testEncode = json_encode($value);
                    if ($testEncode === false) {
                        error_log("Problematic field: {$key} (type: " . gettype($value) . ")");
                        if (is_string($value)) {
                            error_log("  - Length: " . strlen($value));
                            error_log("  - First 100 chars: " . substr($value, 0, 100));
                        }
                    }
                }

                echo json_encode([
                    'success' => false,
                    'message' => 'Error encoding customer data: ' . json_last_error_msg()
                ]);
            } else {
                echo $jsonData;
            }

            ob_end_flush();
            exit;
            
        case 'view_admin':
            $admin = $accountsOp->getAccountById($_POST['account_id']);
            ob_clean();
            echo json_encode(['success' => !!$admin, 'data' => $admin]);
            exit;

        case 'view_sales_agent':
            // Get account info only (all needed info is in accounts table)
            $account = $accountsOp->getAccountById($_POST['account_id']);
            if (!$account) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Sales agent not found']);
                exit;
            }
            ob_clean();
            echo json_encode([
                'success' => true,
                'account' => $account
            ]);
            exit;

        case 'toggle_disable':
            $id = intval($_POST['id'] ?? 0);
            $disabled = intval($_POST['disabled'] ?? 0) === 1;
            $ok = $accountsOp->setAccountDisabled($id, $disabled);
            // If disabling a SalesAgent, reassign their customers immediately
            $extraMsg = '';
            if ($ok && $disabled) {
                $acct = $accountsOp->getAccountById($id);
                if ($acct && ($acct['Role'] ?? '') === 'SalesAgent') {
                    $recount = $customerOp->reassignCustomersFromAgent($id);
                    if ($recount > 0) {
                        $extraMsg = " ($recount customers reassigned)";
                    }
                }
            }
            ob_clean();
            echo json_encode(['success' => $ok, 'message' => $ok ? (($disabled ? 'Account disabled' : 'Account enabled') . $extraMsg) : 'Failed to update account status']);
            exit;

        case 'reassign_customer':
            // Securely reassign a customer to a selected active Sales Agent
            $acctId = intval($_POST['account_id'] ?? 0);
            $agentId = intval($_POST['agent_id'] ?? 0);
            if ($acctId <= 0 || $agentId <= 0) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
                exit;
            }
            $ok = $customerOp->setCustomerAgentByAccountId($acctId, $agentId);
            if ($ok) {
                // Fetch agent label for response
                $agents = $customerOp->getActiveSalesAgents();
                $label = '';
                foreach ($agents as $ag) {
                    if (intval($ag['Id']) === $agentId) {
                        $label = trim(($ag['FirstName'] ?? '') . ' ' . ($ag['LastName'] ?? ''));
                        break;
                    }
                }
                ob_clean();
                echo json_encode(['success' => true, 'message' => 'Customer reassigned successfully', 'agent_label' => $label]);
            } else {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Failed to reassign customer. Ensure the agent is active.']);
            }
            exit;

        case 'filter_accounts':
            $role = $_POST['role'] ?? 'all';
            $search = $_POST['search'] ?? '';
            $sortBy = $_POST['sort'] ?? 'CreatedAt';
            $sortOrder = $_POST['order'] ?? 'DESC';
            $list = $accountsOp->getAccounts($role !== 'all' ? $role : null, $search, $sortBy, $sortOrder);
            ob_start();
            foreach ($list as $account):
            ?>
            <tr>
              <td><?php echo htmlspecialchars($account['Id']); ?></td>
              <td><?php echo htmlspecialchars(($account['FirstName'] ?? '') . ' ' . ($account['LastName'] ?? '')); ?></td>
              <td><?php echo htmlspecialchars($account['Username']); ?></td>
              <td><?php echo htmlspecialchars($account['Email']); ?></td>
              <td><span class="status <?php echo strtolower($account['Role']); ?>"><?php echo htmlspecialchars($account['Role']); ?></span></td>
              <td><?php echo date('M d, Y', strtotime($account['CreatedAt'])); ?></td>
              <td><?php echo $account['LastLoginAt'] ? date('M d, Y', strtotime($account['LastLoginAt'])) : 'Never'; ?></td>
              <td>
                <?php $isDisabled = intval($account['IsDisabled'] ?? 0) === 1; ?>
                <span class="status <?php echo $isDisabled ? 'error' : 'success'; ?>">
                  <?php echo $isDisabled ? 'Disabled' : 'Active'; ?>
                </span>
              </td>
              <td class="table-actions">
                <button class="btn btn-small btn-view" onclick="viewAccountInfo('<?php echo $account['Role']; ?>', <?php echo $account['Id']; ?>)" title="View Details">
                  <i class="fas fa-eye"></i>
                </button>
                <!--<button class="btn btn-small btn-outline" onclick="editAccount(<?php echo $account['Id']; ?>)">Edit</button>-->
                <?php if ($isDisabled): ?>
                  <button class="btn btn-small btn-primary" onclick="toggleDisable(<?php echo $account['Id']; ?>, false)">Enable</button>
                <?php else: ?>
                  <button class="btn btn-small btn-danger" onclick="toggleDisable(<?php echo $account['Id']; ?>, true)">Disable</button>
                <?php endif; ?>
              </td>
            </tr>
            <?php
            endforeach;
            $rowsHtml = ob_get_clean();
            echo json_encode(['success' => true, 'rowsHtml' => $rowsHtml]);
            exit;

        case 'filter_customer_accounts':
            $search = $_POST['search'] ?? '';
            $sortBy = $_POST['sort'] ?? 'CreatedAt';
            $sortOrder = $_POST['order'] ?? 'DESC';
            $list = $customerOp->listCustomerAccountsWithAgent($search, $sortBy, $sortOrder);
            ob_start();
            foreach ($list as $row):
            ?>
            <tr>
              <td><?php echo htmlspecialchars($row['AccountId']); ?></td>
              <td><?php echo htmlspecialchars(trim(($row['FirstName'] ?? '') . ' ' . ($row['LastName'] ?? ''))); ?></td>
              <td><?php echo htmlspecialchars($row['Username']); ?></td>
              <td><?php echo htmlspecialchars($row['Email']); ?></td>
              <td>
                <?php if (!empty($row['agent_id'])): ?>
                  <span id="agentLabel-<?php echo (int)$row['AccountId']; ?>" title="<?php echo htmlspecialchars($row['AgentUsername'] ?? ''); ?>"><?php echo htmlspecialchars($row['AgentName'] ?? ''); ?></span>
                <?php else: ?>
                  <span id="agentLabel-<?php echo (int)$row['AccountId']; ?>" class="status warning">Unassigned</span>
                <?php endif; ?>
              </td>
              <td><?php echo $row['CreatedAt'] ? date('M d, Y', strtotime($row['CreatedAt'])) : '—'; ?></td>
              <td><?php echo !empty($row['LastLoginAt']) ? date('M d, Y', strtotime($row['LastLoginAt'])) : 'Never'; ?></td>
              <td>
                <?php $isDisabled = intval($row['IsDisabled'] ?? 0) === 1; ?>
                <span class="status <?php echo $isDisabled ? 'error' : 'success'; ?>">
                  <?php echo $isDisabled ? 'Disabled' : 'Active'; ?>
                </span>
              </td>
              <td class="table-actions">
                <button class="btn btn-small btn-view" onclick="viewCustomerInfo(<?php echo (int)$row['AccountId']; ?>)" title="View Customer Details">
                  <i class="fas fa-eye"></i>
                </button>
                <!--<button class="btn btn-small btn-outline" onclick="viewSalesAgentInfo(<?php echo !empty($row['agent_id']) && intval($row['agent_id']) > 0 ? (int)$row['agent_id'] : 'null'; ?>)" title="View Assigned Agent">
                  Agent
                </button>-->
                <button class="btn btn-small btn-primary" onclick="openReassignModal(<?php echo (int)$row['AccountId']; ?>, <?php echo !empty($row['agent_id']) && intval($row['agent_id']) > 0 ? (int)$row['agent_id'] : 'null'; ?>)" title="Reassign to Sales Agent">
                  Reassign
                </button>
              </td>
            </tr>
            <?php
            endforeach;
            $rowsHtml = ob_get_clean();
            echo json_encode(['success' => true, 'rowsHtml' => $rowsHtml]);
            exit;
    }

    // If we reach here, unknown action - return error
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

// Normal page load - include dependencies
include_once(dirname(dirname(__DIR__)) . '/includes/init.php');
include_once(dirname(dirname(__DIR__)) . '/includes/database/accounts_operations.php');
include_once(dirname(dirname(__DIR__)) . '/includes/database/customer_operations.php');

// Check if user is Admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: ../../pages/login.php");
    exit();
}

$accountsOp = new AccountsOperations();
$customerOp = new CustomerOperations();

// Get filter parameters with enhanced search
$role_filter = $_GET['role'] ?? 'all';
$search_filter = $_GET['search'] ?? '';
$sort_by = $_GET['sort'] ?? 'CreatedAt';
$sort_order = $_GET['order'] ?? 'DESC';

// Get accounts and statistics
$accounts = $accountsOp->getAccounts($role_filter !== 'all' ? $role_filter : null, $search_filter, $sort_by, $sort_order);
$stats = $accountsOp->getAccountStats();

// Customer Accounts tab filters
$cust_search = $_GET['cust_search'] ?? '';
$cust_sort = $_GET['cust_sort'] ?? 'CreatedAt'; // CreatedAt | Username | AgentName
$cust_order = $_GET['cust_order'] ?? 'DESC'; // ASC | DESC

// Fetch customer accounts with assigned agent info
$customerAccounts = $customerOp->listCustomerAccountsWithAgent($cust_search, $cust_sort, $cust_order);
// Preload active (not disabled) Sales Agents for the dropdown
$activeAgents = $customerOp->getActiveSalesAgents();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Account Control - Mitsubishi</title>
  
  <?php
  // Mobile Responsiveness Fix
  $css_path = '../../css/';
  $js_path = '../../js/';
  include '../../includes/components/mobile-responsive-include.php';
  ?>
  
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link href="../../includes/css/common-styles.css" rel="stylesheet">
  <link rel="stylesheet" href="../../includes/css/account-styles.css">
  <style>
    /* Page-specific overrides to avoid bottom white space and allow full-page scroll */
    body { overflow-y: auto !important; }
    .main-content { padding-bottom: 0 !important; }

    /* Smaller confirm modal */
    .modal.modal--sm { max-width: 360px; }
    .modal.modal--sm .modal-header { padding: 12px 16px; }
    .modal.modal--sm .modal-header h3 { font-size: 1rem; }
    .modal.modal--sm .modal-body { padding: 14px 16px; }
    .modal.modal--sm .modal-footer { padding: 12px 16px; }
    .modal.modal--sm .btn { padding: 8px 14px; font-size: 0.9rem; }

    /* Real-time filter enhancements */
    .filter-bar {
      transition: opacity 0.3s ease, pointer-events 0.3s ease;
    }

    .filter-bar.filtering {
      opacity: 0.7;
      pointer-events: none;
    }

    /* Consistent styling for all filter elements - override existing styles */
    .filter-bar .search-input input {
      background: white !important;
      border: 1px solid var(--border-light) !important;
      color: var(--text-dark) !important;
      transition: all 0.3s ease !important;
      border-radius: 8px !important;
      font-size: 14px !important;
      padding: 10px 15px 10px 40px !important; /* Extra left padding for icon */
      width: 100% !important;
    }

    .filter-bar .filter-select {
      background: white !important;
      border: 1px solid var(--border-light) !important;
      color: var(--text-dark) !important;
      transition: all 0.3s ease;
      border-radius: 8px;
      font-size: 14px;
      padding: 10px 15px;
    }

    .filter-bar .search-input input:focus,
    .filter-bar .filter-select:focus {
      border-color: var(--primary-red) !important;
      box-shadow: 0 0 0 2px rgba(214, 0, 0, 0.1) !important;
      outline: none;
    }

    /* Clear button styling - make it secondary/outline style */
    .filter-bar .btn-outline {
      background: white !important;
      border: 1px solid var(--border-light) !important;
      color: var(--text-dark) !important;
      transition: all 0.3s ease;
      padding: 10px 15px;
      border-radius: 8px;
      font-size: 14px;
      font-weight: 600;
    }

    .filter-bar .btn-outline:hover {
      background: var(--primary-light) !important;
      border-color: var(--primary-red) !important;
      color: var(--primary-red) !important;
      transform: translateY(-1px);
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    /* Active filter indicator */
    .filter-bar.has-active-filters {
      background: rgba(214, 0, 0, 0.05);
      border-left: 3px solid var(--primary-red);
    }

    /* Info text styling */
    .filter-bar small {
      color: var(--text-light);
      font-size: 12px;
      margin-left: 10px;
      display: flex;
      align-items: center;
      gap: 4px;
    }

    /* Remove pink separators and ensure consistent spacing */
    .filter-bar {
      gap: 15px !important;
      align-items: center;
      padding: 15px;
      background: white;
      border-radius: 8px;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
      border: 1px solid var(--border-light);
    }

    /* Ensure all filter elements have consistent height */
    .filter-bar .search-input input,
    .filter-bar .filter-select,
    .filter-bar .btn-outline {
      height: 44px;
      box-sizing: border-box;
    }

    /* Improve search input icon positioning */
    .filter-bar .search-input {
      position: relative !important;
    }

    .filter-bar .search-input i {
      position: absolute !important;
      left: 15px !important;
      top: 50% !important;
      transform: translateY(-50%) !important;
      color: var(--text-light) !important;
      z-index: 2 !important;
      pointer-events: none !important; /* Prevent icon from interfering with input clicks */
    }
  </style>
</head>
<body>
  <?php include '../../includes/components/sidebar.php'; ?>

  <div class="main">
    <?php include '../../includes/components/topbar.php'; ?>

    <div class="main-content">
      <div class="page-header">
        <h1 class="page-title">
          <i class="fas fa-users-cog"></i>
          Admin Account Control
        </h1>
      </div>

  <!-- Reassign Customer Modal -->
  <div id="reassignModal" class="modal-overlay">
    <div class="modal">
      <div class="modal-header">
        <h3><i class="fas fa-user-exchange"></i> Reassign Customer to Sales Agent</h3>
        <button class="modal-close" onclick="closeModal('reassignModal')">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="reassignAccountId" />
        <div class="form-group">
          <label class="form-label">Select Sales Agent <span class="required">*</span></label>
          <select class="form-select" id="reassignAgentSelect" required>
            <option value="">-- Choose Sales Agent --</option>
            <?php foreach ($activeAgents as $ag): ?>
              <option value="<?php echo (int)$ag['Id']; ?>">
                <?php echo htmlspecialchars(trim(($ag['FirstName'] ?? '') . ' ' . ($ag['LastName'] ?? '')) . ' (' . ($ag['Username'] ?? '') . ')'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <p style="font-size:12px;color:var(--text-muted);margin-top:8px;">
          Only active (enabled) Sales Agent accounts are listed.
        </p>
        <div class="alert alert-success" id="reassignSuccessAlert"></div>
        <div class="alert alert-error" id="reassignErrorAlert"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('reassignModal')">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="submitReassign()">Reassign</button>
      </div>
    </div>
  </div>

      <div class="tab-navigation">
        <button class="tab-button active" data-tab="account-all">All Accounts</button>
        <button class="tab-button" data-tab="customer-accounts">Customer Accounts</button>
        <button class="tab-button" data-tab="account-create">Create Account</button>
      </div>

      <!-- Customer Accounts Tab -->
      <div class="tab-content" id="customer-accounts">
        <h3 class="section-heading">Customer Accounts</h3>
        <div class="filter-bar">
          <div class="search-input">
            <i class="fas fa-search"></i>
            <input type="text" id="custSearchInput" placeholder="Search customers by name, username, email..." value="<?php echo htmlspecialchars($cust_search); ?>">
          </div>
          <select class="filter-select" id="custSortFilter">
            <option value="CreatedAt_DESC" <?php echo ($cust_sort === 'CreatedAt' && strtoupper($cust_order) === 'DESC') ? 'selected' : ''; ?>>Newest First</option>
            <option value="CreatedAt_ASC" <?php echo ($cust_sort === 'CreatedAt' && strtoupper($cust_order) === 'ASC') ? 'selected' : ''; ?>>Oldest First</option>
            <option value="Username_ASC" <?php echo ($cust_sort === 'Username' && strtoupper($cust_order) === 'ASC') ? 'selected' : ''; ?>>Username A-Z</option>
            <option value="Username_DESC" <?php echo ($cust_sort === 'Username' && strtoupper($cust_order) === 'DESC') ? 'selected' : ''; ?>>Username Z-A</option>
            <option value="AgentName_ASC" <?php echo ($cust_sort === 'AgentName' && strtoupper($cust_order) === 'ASC') ? 'selected' : ''; ?>>Agent Name A-Z</option>
            <option value="AgentName_DESC" <?php echo ($cust_sort === 'AgentName' && strtoupper($cust_order) === 'DESC') ? 'selected' : ''; ?>>Agent Name Z-A</option>
          </select>
          <button class="btn btn-outline" onclick="clearCustomerFilters()">
            <i class="fas fa-times"></i> Clear Filters
          </button>
          <small style="color: var(--text-light); font-size: 12px; margin-left: 10px;">
            <i class="fas fa-info-circle"></i> Filters apply automatically
          </small>
        </div>

        <table class="data-table">
          <thead>
            <tr>
              <th>Account ID</th>
              <th>Name</th>
              <th>Username</th>
              <th>Email</th>
              <th>Assigned Sales Agent</th>
              <th>Created</th>
              <th>Last Login</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($customerAccounts as $row): ?>
            <tr>
              <td><?php echo htmlspecialchars($row['AccountId']); ?></td>
              <td><?php echo htmlspecialchars(trim(($row['FirstName'] ?? '') . ' ' . ($row['LastName'] ?? ''))); ?></td>
              <td><?php echo htmlspecialchars($row['Username']); ?></td>
              <td><?php echo htmlspecialchars($row['Email']); ?></td>
              <td>
                <?php if (!empty($row['agent_id'])): ?>
                  <span id="agentLabel-<?php echo (int)$row['AccountId']; ?>" title="<?php echo htmlspecialchars($row['AgentUsername'] ?? ''); ?>"><?php echo htmlspecialchars($row['AgentName'] ?? ''); ?></span>
                <?php else: ?>
                  <span id="agentLabel-<?php echo (int)$row['AccountId']; ?>" class="status warning">Unassigned</span>
                <?php endif; ?>
              </td>
              <td><?php echo $row['CreatedAt'] ? date('M d, Y', strtotime($row['CreatedAt'])) : '—'; ?></td>
              <td><?php echo !empty($row['LastLoginAt']) ? date('M d, Y', strtotime($row['LastLoginAt'])) : 'Never'; ?></td>
              <td>
                <?php $isDisabled = intval($row['IsDisabled'] ?? 0) === 1; ?>
                <span class="status <?php echo $isDisabled ? 'error' : 'success'; ?>">
                  <?php echo $isDisabled ? 'Disabled' : 'Active'; ?>
                </span>
              </td>
              <td class="table-actions">
                <button class="btn btn-small btn-view" onclick="viewCustomerInfo(<?php echo (int)$row['AccountId']; ?>)" title="View Customer Details">
                  <i class="fas fa-eye"></i>
                </button>
                <!--<button class="btn btn-small btn-outline" onclick="viewSalesAgentInfo(<?php echo !empty($row['agent_id']) && intval($row['agent_id']) > 0 ? (int)$row['agent_id'] : 'null'; ?>)" title="View Assigned Agent">
                  Agent
                </button>-->
                <button class="btn btn-small btn-primary" onclick="openReassignModal(<?php echo (int)$row['AccountId']; ?>, <?php echo !empty($row['agent_id']) && intval($row['agent_id']) > 0 ? (int)$row['agent_id'] : 'null'; ?>)" title="Reassign to Sales Agent">
                  Reassign
                </button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- All Accounts Tab -->
      <div class="tab-content active" id="account-all">
        <div class="info-cards">
          <div class="info-card">
            <div class="info-card-title">Total Accounts</div>
            <div class="info-card-value"><?php echo $stats['total'] ?? 0; ?></div>
          </div>
          <div class="info-card">
            <div class="info-card-title">Admins</div>
            <div class="info-card-value"><?php echo $stats['admin'] ?? 0; ?></div>
          </div>
          <div class="info-card">
            <div class="info-card-title">Sales Agents</div>
            <div class="info-card-value"><?php echo $stats['salesagent'] ?? 0; ?></div>
          </div>
          <div class="info-card">
            <div class="info-card-title">Customers</div>
            <div class="info-card-value"><?php echo $stats['customer'] ?? 0; ?></div>
          </div>
        </div>

        <div class="filter-bar">
          <div class="search-input">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" placeholder="Search username" value="<?php echo htmlspecialchars($search_filter); ?>">
          </div>
          <select class="filter-select" id="roleFilter">
            <option value="all" <?php echo $role_filter === 'all' ? 'selected' : ''; ?>>All Roles</option>
            <option value="Admin" <?php echo $role_filter === 'Admin' ? 'selected' : ''; ?>>Admin</option>
            <option value="SalesAgent" <?php echo $role_filter === 'SalesAgent' ? 'selected' : ''; ?>>Sales Agent</option>
            <option value="Customer" <?php echo $role_filter === 'Customer' ? 'selected' : ''; ?>>Customer</option>
          </select>
          <select class="filter-select" id="sortFilter">
            <option value="CreatedAt_DESC" <?php echo ($sort_by === 'CreatedAt' && $sort_order === 'DESC') ? 'selected' : ''; ?>>Newest First</option>
            <option value="CreatedAt_ASC" <?php echo ($sort_by === 'CreatedAt' && $sort_order === 'ASC') ? 'selected' : ''; ?>>Oldest First</option>
            <option value="Username_ASC" <?php echo ($sort_by === 'Username' && $sort_order === 'ASC') ? 'selected' : ''; ?>>Username A-Z</option>
            <option value="Username_DESC" <?php echo ($sort_by === 'Username' && $sort_order === 'DESC') ? 'selected' : ''; ?>>Username Z-A</option>
            <option value="Role_ASC" <?php echo ($sort_by === 'Role' && $sort_order === 'ASC') ? 'selected' : ''; ?>>Role A-Z</option>
          </select>
          <button class="btn btn-outline" onclick="clearFilters()">
            <i class="fas fa-times"></i> Clear Filters
          </button>
          <small style="color: var(--text-light); font-size: 12px; margin-left: 10px;">
            <i class="fas fa-info-circle"></i> Filters apply automatically
          </small>
        </div>

        <table class="data-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Name</th>
              <th>Username</th>
              <th>Email</th>
              <th>Role</th>
              <th>Created</th>
              <th>Last Login</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($accounts as $account): ?>
            <tr>
              <td><?php echo htmlspecialchars($account['Id']); ?></td>
              <td><?php echo htmlspecialchars(($account['FirstName'] ?? '') . ' ' . ($account['LastName'] ?? '')); ?></td>
              <td><?php echo htmlspecialchars($account['Username']); ?></td>
              <td><?php echo htmlspecialchars($account['Email']); ?></td>
              <td><span class="status <?php echo strtolower($account['Role']); ?>"><?php echo htmlspecialchars($account['Role']); ?></span></td>
              <td><?php echo date('M d, Y', strtotime($account['CreatedAt'])); ?></td>
              <td><?php echo $account['LastLoginAt'] ? date('M d, Y', strtotime($account['LastLoginAt'])) : 'Never'; ?></td>
              <td>
                <?php $isDisabled = intval($account['IsDisabled'] ?? 0) === 1; ?>
                <span class="status <?php echo $isDisabled ? 'error' : 'success'; ?>">
                  <?php echo $isDisabled ? 'Disabled' : 'Active'; ?>
                </span>
              </td>
              <td class="table-actions">
                <button class="btn btn-small btn-view" onclick="viewAccountInfo('<?php echo $account['Role']; ?>', <?php echo $account['Id']; ?>)" title="View Details">
                  <i class="fas fa-eye"></i>
                </button>
                <!--<button class="btn btn-small btn-outline" onclick="editAccount(<?php echo $account['Id']; ?>)">Edit</button>-->
                <?php if ($isDisabled): ?>
                  <button class="btn btn-small btn-primary" onclick="toggleDisable(<?php echo $account['Id']; ?>, false)">Enable</button>
                <?php else: ?>
                  <button class="btn btn-small btn-danger" onclick="toggleDisable(<?php echo $account['Id']; ?>, true)">Disable</button>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Create Account Tab -->
      <div class="tab-content" id="account-create">
        <h3 class="section-heading">Create New Account</h3>
        <div class="alert alert-success" id="createSuccessAlert"></div>
        <div class="alert alert-error" id="createErrorAlert"></div>
        <form id="createAccountForm">
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Role <span class="required">*</span></label>
              <select class="form-select" name="role" required>
                <option value="">Select role</option>
                <option value="Admin">Admin</option>
                <option value="SalesAgent">Sales Agent</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Username <span class="required">*</span></label>
              <input type="text" class="form-input" name="username" placeholder="Enter username" required>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">First Name <span class="required">*</span></label>
              <input type="text" class="form-input" name="first_name" placeholder="Enter first name" required>
            </div>
            <div class="form-group">
              <label class="form-label">Last Name <span class="required">*</span></label>
              <input type="text" class="form-input" name="last_name" placeholder="Enter last name" required>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Email Address <span class="required">*</span></label>
              <input type="email" class="form-input" name="email" placeholder="Enter email address" required>
            </div>
            <div class="form-group">
              <label class="form-label">Password <span class="required">*</span></label>
              <input type="password" class="form-input" name="password" placeholder="Enter password" required>
            </div>
          </div>
          <div class="action-area">
            <button type="button" class="btn btn-secondary" onclick="clearCreateForm()">Clear Form</button>
            <button type="submit" class="btn btn-primary">Create Account</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Edit Account Modal - Updated to match topbar.php modal structure -->
  <div id="editAccountModal" class="modal-overlay">
    <div class="modal">
      <div class="modal-header">
        <h3>Edit Account</h3>
        <button class="modal-close" onclick="closeEditModal()">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div class="modal-body">
        <div class="alert alert-success" id="editSuccessAlert"></div>
        <div class="alert alert-error" id="editErrorAlert"></div>
        <form id="editAccountForm">
          <input type="hidden" id="editAccountId" name="id">
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Role <span class="required">*</span></label>
              <select class="form-select" id="editRole" name="role" required>
                <option value="Admin">Admin</option>
                <option value="SalesAgent">Sales Agent</option>
                <option value="Customer">Customer</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Username <span class="required">*</span></label>
              <input type="text" class="form-input" id="editUsername" name="username" required>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">First Name <span class="required">*</span></label>
              <input type="text" class="form-input" id="editFirstName" name="first_name" required>
            </div>
            <div class="form-group">
              <label class="form-label">Last Name <span class="required">*</span></label>
              <input type="text" class="form-input" id="editLastName" name="last_name" required>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Email Address <span class="required">*</span></label>
            <input type="email" class="form-input" id="editEmail" name="email" required>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="submitEditForm()">Update Account</button>
      </div>
    </div>
  </div>

  <!-- Confirm Action Modal -->
  <div id="confirmModal" class="modal-overlay">
    <div class="modal modal--sm">
      <div class="modal-header">
        <h3><i class="fas fa-triangle-exclamation" style="color:var(--primary-red);"></i> Confirm Action</h3>
        <button class="modal-close" onclick="closeModal('confirmModal')">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div class="modal-body">
        <div id="confirmMessage" style="font-size:14px;color:var(--text-dark);"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" id="confirmCancelBtn">Cancel</button>
        <button type="button" class="btn btn-primary" id="confirmOkBtn">OK</button>
      </div>
    </div>
  </div>

  <!-- Customer Information Modal -->
  <div id="customerInfoModal" class="modal-overlay">
    <div class="modal">
      <div class="modal-header">
        <h3><i class="fas fa-user-circle"></i> Customer Information</h3>
        <button class="modal-close" onclick="closeModal('customerInfoModal')">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div class="modal-body">
        <div id="customerInfoContent">
          <!-- Content will be loaded here -->
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('customerInfoModal')">Close</button>
      </div>
    </div>
  </div>

  <!-- Admin Information Modal -->
  <div id="adminInfoModal" class="modal-overlay">
    <div class="modal">
      <div class="modal-header">
        <h3><i class="fas fa-user-shield"></i> Admin Information</h3>
        <button class="modal-close" onclick="closeModal('adminInfoModal')">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div class="modal-body">
        <div id="adminInfoContent">
          <!-- Content will be loaded here -->
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('adminInfoModal')">Close</button>
      </div>
    </div>
  </div>

  <!-- Sales Agent Information Modal -->
  <div id="salesAgentInfoModal" class="modal-overlay">
    <div class="modal">
      <div class="modal-header">
        <h3><i class="fas fa-user-tie"></i> Sales Agent Information</h3>
        <button class="modal-close" onclick="closeModal('salesAgentInfoModal')">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div class="modal-body">
        <div id="salesAgentInfoContent">
          <!-- Content will be loaded here -->
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('salesAgentInfoModal')">Close</button>
      </div>
    </div>
  </div>

  <script src="../../includes/js/common-scripts.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Tab navigation functionality
      document.querySelectorAll('.tab-button').forEach(function(button) {
        button.addEventListener('click', function() {
          // Remove active class from all buttons
          document.querySelectorAll('.tab-button').forEach(function(btn) {
            btn.classList.remove('active');
          });
          // Add active class to clicked button
          this.classList.add('active');
          
          // Get the target tab content id
          const tabId = this.getAttribute('data-tab');
          // Hide all tab contents
          document.querySelectorAll('.tab-content').forEach(function(tab) {
            tab.classList.remove('active');
          });
          // Show the target tab content
          document.getElementById(tabId).classList.add('active');
          // Persist active tab in URL so refresh keeps context
          const params = new URLSearchParams(window.location.search);
          params.set('active_tab', tabId);
          const newUrl = window.location.pathname + '?' + params.toString();
          window.history.replaceState(null, '', newUrl);
        });
      });

      // On load, activate tab from URL if provided
      const params = new URLSearchParams(window.location.search);
      const activeTab = params.get('active_tab');
      if (activeTab) {
        document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
        const btn = document.querySelector(`.tab-button[data-tab="${activeTab}"]`);
        const tab = document.getElementById(activeTab);
        if (btn && tab) {
          btn.classList.add('active');
          tab.classList.add('active');
        }
      }

      // Form submission
      document.getElementById('createAccountForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'create');
        
        fetch('', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            showAlert('createSuccessAlert', data.message);
            this.reset();
            setTimeout(() => location.reload(), 1500);
          } else {
            showAlert('createErrorAlert', data.message);
          }
        })
        .catch(error => {
          showAlert('createErrorAlert', 'An error occurred: ' + error.message);
        });
      });
    });

    // Apply filters function (AJAX, no reload)
    function applyFilters() {
      const role = document.getElementById('roleFilter').value;
      const search = document.getElementById('searchInput').value;
      const sort = document.getElementById('sortFilter').value;
      const [sortBy, sortOrder] = (sort || 'CreatedAt_DESC').split('_');

      const fd = new FormData();
      fd.append('action', 'filter_accounts');
      fd.append('role', role);
      fd.append('search', search);
      fd.append('sort', sortBy);
      fd.append('order', sortOrder);

      const tbody = document.querySelector('#account-all table.data-table tbody');
      if (!tbody) return;
      showFilterLoading();
      fetch('', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
          if (d.success) {
            const input = document.getElementById('searchInput');
            const selStart = input.selectionStart;
            const selEnd = input.selectionEnd;
            tbody.innerHTML = d.rowsHtml || '';
            input.focus();
            if (typeof selStart === 'number' && typeof selEnd === 'number') {
              input.setSelectionRange(selStart, selEnd);
            }
          }
        })
        .finally(() => hideFilterLoading());
    }

    // Clear filters function (AJAX reset)
    function clearFilters() {
      document.getElementById('searchInput').value = '';
      document.getElementById('roleFilter').value = 'all';
      document.getElementById('sortFilter').value = 'CreatedAt_DESC';
      applyFilters();
    }

    // Apply filters for Customer Accounts tab (AJAX, no reload)
    function applyCustomerFilters() {
      const search = document.getElementById('custSearchInput').value;
      const sort = document.getElementById('custSortFilter').value;
      const [sortBy, sortOrder] = (sort || 'CreatedAt_DESC').split('_');

      const fd = new FormData();
      fd.append('action', 'filter_customer_accounts');
      fd.append('search', search);
      fd.append('sort', sortBy);
      fd.append('order', sortOrder);

      const tbody = document.querySelector('#customer-accounts table.data-table tbody');
      if (!tbody) return;
      showFilterLoading();
      fetch('', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
          if (d.success) {
            const input = document.getElementById('custSearchInput');
            const selStart = input.selectionStart;
            const selEnd = input.selectionEnd;
            tbody.innerHTML = d.rowsHtml || '';
            input.focus();
            if (typeof selStart === 'number' && typeof selEnd === 'number') {
              input.setSelectionRange(selStart, selEnd);
            }
          }
        })
        .finally(() => hideFilterLoading());
    }

    // Clear Customer Accounts filters (AJAX reset)
    function clearCustomerFilters() {
      document.getElementById('custSearchInput').value = '';
      document.getElementById('custSortFilter').value = 'CreatedAt_DESC';
      applyCustomerFilters();
    }

    // Modal functions - Updated to match topbar.php
    function openModal(modalId) {
      const modal = document.getElementById(modalId);
      if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
      }
    }

    function closeModal(modalId) {
      const modal = document.getElementById(modalId);
      if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = 'auto';
      }
    }

    // Open Reassign modal and preselect current agent if present
    function openReassignModal(accountId, currentAgentId) {
      const sel = document.getElementById('reassignAgentSelect');
      const acc = document.getElementById('reassignAccountId');
      if (!sel || !acc) return;
      acc.value = accountId;
      sel.value = currentAgentId ? String(currentAgentId) : '';
      const ok = document.getElementById('reassignSuccessAlert');
      const err = document.getElementById('reassignErrorAlert');
      if (ok) ok.classList.remove('active');
      if (err) err.classList.remove('active');
      openModal('reassignModal');
    }

    // Submit reassignment
    function submitReassign() {
      const accountId = document.getElementById('reassignAccountId')?.value;
      const agentId = document.getElementById('reassignAgentSelect')?.value;
      if (!accountId || !agentId) {
        showAlert('reassignErrorAlert', 'Please select a Sales Agent.');
        return;
      }
      const fd = new FormData();
      fd.append('action', 'reassign_customer');
      fd.append('account_id', accountId);
      fd.append('agent_id', agentId);
      fetch('', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
          if (d.success) {
            const label = document.getElementById('agentLabel-' + accountId);
            if (label) {
              label.textContent = d.agent_label || 'Assigned';
              label.classList.remove('warning');
              label.classList.remove('status');
            }
            showAlert('reassignSuccessAlert', d.message || 'Reassigned');
            setTimeout(() => { closeModal('reassignModal'); }, 900);
          } else {
            showAlert('reassignErrorAlert', d.message || 'Failed to reassign');
          }
        })
        .catch(err => showAlert('reassignErrorAlert', 'Error: ' + err.message));
    }

    // Edit account function
    function editAccount(id) {
      const formData = new FormData();
      formData.append('action', 'get_account');
      formData.append('id', id);
      
      fetch('', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          document.getElementById('editAccountId').value = data.data.Id;
          document.getElementById('editUsername').value = data.data.Username;
          document.getElementById('editEmail').value = data.data.Email;
          document.getElementById('editRole').value = data.data.Role;
          document.getElementById('editFirstName').value = data.data.FirstName || '';
          document.getElementById('editLastName').value = data.data.LastName || '';
          openModal('editAccountModal');
        }
      });
    }

    // Close edit modal
    function closeEditModal() {
      closeModal('editAccountModal');
      // Reset form fields
      document.getElementById('editSuccessAlert').classList.remove('active');
      document.getElementById('editErrorAlert').classList.remove('active');
    }

    // Update account form submission
    function submitEditForm() {
      const form = document.getElementById('editAccountForm');
      const formData = new FormData(form);
      formData.append('action', 'update');
      
      fetch('', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          showAlert('editSuccessAlert', data.message);
          setTimeout(() => {
            closeEditModal();
            location.reload();
          }, 1500);
        } else {
          showAlert('editErrorAlert', data.message);
        }
      });
    }

    // Custom confirm modal that returns a Promise<boolean>
    function showConfirm(message) {
      return new Promise((resolve) => {
        const msg = document.getElementById('confirmMessage');
        const okBtn = document.getElementById('confirmOkBtn');
        const cancelBtn = document.getElementById('confirmCancelBtn');

        msg.textContent = message;
        openModal('confirmModal');

        const cleanup = () => {
          okBtn.removeEventListener('click', onOk);
          cancelBtn.removeEventListener('click', onCancel);
        };
        const onOk = () => { cleanup(); closeModal('confirmModal'); resolve(true); };
        const onCancel = () => { cleanup(); closeModal('confirmModal'); resolve(false); };

        okBtn.addEventListener('click', onOk);
        cancelBtn.addEventListener('click', onCancel);
      });
    }

    // Enable/Disable account
    async function toggleDisable(id, disabled) {
      if (disabled) {
        const ok = await showConfirm('Disable this account? The user will not be able to log in.');
        if (!ok) return;
      }
      const formData = new FormData();
      formData.append('action', 'toggle_disable');
      formData.append('id', id);
      formData.append('disabled', disabled ? 1 : 0);
      fetch('', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(d => {
          if (d.success) {
            location.reload();
          } else {
            alert(d.message || 'Failed to update account');
          }
        })
        .catch(err => alert('Error: ' + err.message));
    }

    // View account information based on role
    function viewAccountInfo(role, accountId) {
      switch(role) {
        case 'Customer':
          viewCustomerInfo(accountId);
          break;
        case 'Admin':
          viewAdminInfo(accountId);
          break;
        case 'SalesAgent':
          viewSalesAgentInfo(accountId);
          break;
      }
    }

    // View customer information function
    function viewCustomerInfo(accountId) {
      const formData = new FormData();
      formData.append('action', 'view_customer');
      formData.append('account_id', accountId);

      fetch('', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        // Debug logging
        console.log('Customer data received:', data);
        if (data.data) {
          console.log('Customer data keys:', Object.keys(data.data));
          console.log('Sample fields:', {
            firstname: data.data.firstname,
            lastname: data.data.lastname,
            account_id: data.data.account_id,
            Status: data.data.Status
          });
        }

        if (data.success && data.data) {
          displayCustomerInfo(data.data);
          openModal('customerInfoModal');
        } else {
          console.error('Failed to fetch customer info:', data.message || 'Unknown error');
          displayNoCustomerInfo();
          openModal('customerInfoModal');
        }
      })
      .catch(error => {
        console.error('Error fetching customer info:', error);
        displayNoCustomerInfo();
        openModal('customerInfoModal');
      });
    }

    // View admin information function
    function viewAdminInfo(accountId) {
      const formData = new FormData();
      formData.append('action', 'view_admin');
      formData.append('account_id', accountId);
      
      fetch('', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success && data.data) {
          displayAdminInfo(data.data);
        } else {
          displayNoAdminInfo();
        }
        openModal('adminInfoModal');
      })
      .catch(error => {
        console.error('Error:', error);
        displayNoAdminInfo();
        openModal('adminInfoModal');
      });
    }

    // View sales agent information function
    function viewSalesAgentInfo(accountId) {
      // If no agent is assigned, show the "no agent" message immediately
      if (!accountId || accountId === 'null' || accountId === null) {
        displayNoSalesAgentInfo();
        openModal('salesAgentInfoModal');
        return;
      }

      const formData = new FormData();
      formData.append('action', 'view_sales_agent');
      formData.append('account_id', accountId);

      fetch('', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success && data.account) {
          displaySalesAgentInfo(data.account);
          openModal('salesAgentInfoModal');
        } else {
          console.error('Failed to fetch sales agent info:', data.message || 'Unknown error');
          displayNoSalesAgentInfo();
          openModal('salesAgentInfoModal');
        }
      })
      .catch(error => {
        console.error('Error fetching sales agent info:', error);
        displayNoSalesAgentInfo();
        openModal('salesAgentInfoModal');
      });
    }

    // Display customer information in modal
    function displayCustomerInfo(customer) {
      const content = document.getElementById('customerInfoContent');

      // Check if this is an incomplete profile (account exists but no customer_information)
      if (customer.profile_incomplete) {
        content.innerHTML = `
          <div class="alert alert-warning" style="margin-bottom: 20px;">
            <i class="fas fa-exclamation-triangle"></i> This customer has not completed their profile information yet.
          </div>
          <div class="customer-info-grid">
            <div class="info-section">
              <h4><i class="fas fa-user"></i> Basic Account Information</h4>
              <div class="info-item">
                <span class="info-label">Account ID:</span>
                <span class="info-value">${customer.account_id || 'N/A'}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Username:</span>
                <span class="info-value">${customer.Username || 'N/A'}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Email:</span>
                <span class="info-value">${customer.Email || 'N/A'}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Name:</span>
                <span class="info-value">${customer.FirstName || ''} ${customer.LastName || ''}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Role:</span>
                <span class="info-value"><span class="status customer">${customer.Role || 'Customer'}</span></span>
              </div>
              <div class="info-item">
                <span class="info-label">Account Created:</span>
                <span class="info-value">${customer.CreatedAt ? new Date(customer.CreatedAt).toLocaleDateString() : 'N/A'}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Last Login:</span>
                <span class="info-value">${customer.LastLoginAt ? new Date(customer.LastLoginAt).toLocaleDateString() : 'Never'}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Profile Status:</span>
                <span class="info-value"><span class="status warning">${customer.Status || 'Incomplete Profile'}</span></span>
              </div>
            </div>
          </div>
        `;
        return;
      }

      // Display full customer information for complete profiles
      content.innerHTML = `
        <div class="customer-info-grid">
          <div class="info-section">
            <h4><i class="fas fa-user"></i> Personal Details</h4>
            <div class="info-item">
              <span class="info-label">Full Name:</span>
              <span class="info-value">${customer.firstname || 'N/A'} ${customer.middlename || ''} ${customer.lastname || 'N/A'} ${customer.suffix || ''}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Birthday:</span>
              <span class="info-value">${customer.birthday ? new Date(customer.birthday).toLocaleDateString() : 'N/A'}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Age:</span>
              <span class="info-value">${customer.age || 'N/A'}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Gender:</span>
              <span class="info-value">${customer.gender || 'N/A'}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Civil Status:</span>
              <span class="info-value">${customer.civil_status || 'N/A'}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Nationality:</span>
              <span class="info-value">${customer.nationality || 'N/A'}</span>
            </div>
          </div>

          <div class="info-section">
            <h4><i class="fas fa-phone"></i> Contact Information</h4>
            <div class="info-item">
              <span class="info-label">Mobile Number:</span>
              <span class="info-value">${customer.mobile_number || 'N/A'}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Email:</span>
              <span class="info-value">${customer.Email || 'N/A'}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Username:</span>
              <span class="info-value">${customer.Username || 'N/A'}</span>
            </div>
          </div>

          <div class="info-section">
            <h4><i class="fas fa-briefcase"></i> Employment Details</h4>
            <div class="info-item">
              <span class="info-label">Employment Status:</span>
              <span class="info-value">${customer.employment_status || 'N/A'}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Company:</span>
              <span class="info-value">${customer.company_name || 'N/A'}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Position:</span>
              <span class="info-value">${customer.position || 'N/A'}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Monthly Income:</span>
              <span class="info-value">${customer.monthly_income ? '₱' + parseFloat(customer.monthly_income).toLocaleString() : 'N/A'}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Valid ID Type:</span>
              <span class="info-value">${customer.valid_id_type || 'N/A'}</span>
            </div>
          </div>

          <div class="info-section">
            <h4><i class="fas fa-clock"></i> Account Information</h4>
            <div class="info-item">
              <span class="info-label">Customer ID:</span>
              <span class="info-value">${customer.cusID || 'N/A'}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Account ID:</span>
              <span class="info-value">${customer.account_id || 'N/A'}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Role:</span>
              <span class="info-value"><span class="status customer">${customer.Role || 'Customer'}</span></span>
            </div>
            <div class="info-item">
              <span class="info-label">Status:</span>
              <span class="info-value"><span class="status ${customer.Status ? customer.Status.toLowerCase().replace(' ', '-') : 'pending'}">${customer.Status || 'Pending'}</span></span>
            </div>
            <div class="info-item">
              <span class="info-label">Created:</span>
              <span class="info-value">${customer.created_at ? new Date(customer.created_at).toLocaleDateString() : (customer.CreatedAt ? new Date(customer.CreatedAt).toLocaleDateString() : 'N/A')}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Last Updated:</span>
              <span class="info-value">${customer.updated_at ? new Date(customer.updated_at).toLocaleDateString() : 'N/A'}</span>
            </div>
          </div>
        </div>
      `;
    }

    // Display message when no customer information is available
    function displayNoCustomerInfo() {
      const content = document.getElementById('customerInfoContent');
      content.innerHTML = `
        <div class="no-customer-info">
          <i class="fas fa-info-circle" style="font-size: 48px; color: var(--text-light); margin-bottom: 20px;"></i>
          <h4>No Customer Information Available</h4>
          <p>This customer has not completed their profile information yet.</p>
        </div>
      `;
    }

    // Display admin information in modal
    function displayAdminInfo(admin) {
      const content = document.getElementById('adminInfoContent');
      content.innerHTML = `
        <div class="customer-info-grid">
          <div class="info-section">
            <h4><i class="fas fa-user"></i> Personal Details</h4>
            <div class="info-item">
              <span class="info-label">Full Name:</span>
              <span class="info-value">${admin.FirstName || 'N/A'} ${admin.LastName || 'N/A'}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Username:</span>
              <span class="info-value">${admin.Username}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Email:</span>
              <span class="info-value">${admin.Email}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Date of Birth:</span>
              <span class="info-value">${admin.DateOfBirth ? new Date(admin.DateOfBirth).toLocaleDateString() : 'N/A'}</span>
            </div>
          </div>
          
          <div class="info-section">
            <h4><i class="fas fa-shield-alt"></i> Account Information</h4>
            <div class="info-item">
              <span class="info-label">Account ID:</span>
              <span class="info-value">${admin.Id}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Role:</span>
              <span class="info-value"><span class="status admin">${admin.Role}</span></span>
            </div>
            <div class="info-item">
              <span class="info-label">Created:</span>
              <span class="info-value">${admin.CreatedAt ? new Date(admin.CreatedAt).toLocaleDateString() : 'N/A'}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Last Login:</span>
              <span class="info-value">${admin.LastLoginAt ? new Date(admin.LastLoginAt).toLocaleString() : 'Never'}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Last Updated:</span>
              <span class="info-value">${admin.UpdatedAt ? new Date(admin.UpdatedAt).toLocaleDateString() : 'N/A'}</span>
            </div>
          </div>
        </div>
      `;
    }

    // Display sales agent information in modal
    function displaySalesAgentInfo(account) {
      const content = document.getElementById('salesAgentInfoContent');
      content.innerHTML = `
        <div class="customer-info-grid">
          <div class="info-section">
            <h4><i class="fas fa-user"></i> Personal Details</h4>
            <div class="info-item">
              <span class="info-label">Full Name:</span>
              <span class="info-value">${account.FirstName || 'N/A'} ${account.LastName || 'N/A'}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Username:</span>
              <span class="info-value">${account.Username || 'N/A'}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Email:</span>
              <span class="info-value">${account.Email || 'N/A'}</span>
            </div>
          </div>

          <div class="info-section">
            <h4><i class="fas fa-shield-alt"></i> Account Information</h4>
            <div class="info-item">
              <span class="info-label">Account ID:</span>
              <span class="info-value">${account.Id || 'N/A'}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Role:</span>
              <span class="info-value"><span class="status salesagent">${account.Role || 'SalesAgent'}</span></span>
            </div>
            <div class="info-item">
              <span class="info-label">Account Created:</span>
              <span class="info-value">${account.CreatedAt ? new Date(account.CreatedAt).toLocaleDateString() : 'N/A'}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Last Login:</span>
              <span class="info-value">${account.LastLoginAt ? new Date(account.LastLoginAt).toLocaleString() : 'Never'}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Account Status:</span>
              <span class="info-value"><span class="status ${account.IsDisabled ? 'error' : 'success'}">${account.IsDisabled ? 'Disabled' : 'Active'}</span></span>
            </div>
          </div>
        </div>
      `;
    }

    // Display message when no admin information is available
    function displayNoAdminInfo() {
      const content = document.getElementById('adminInfoContent');
      content.innerHTML = `
        <div class="no-customer-info">
          <i class="fas fa-info-circle" style="font-size: 48px; color: var(--text-light); margin-bottom: 20px;"></i>
          <h4>No Admin Information Available</h4>
          <p>Unable to retrieve admin information at this time.</p>
        </div>
      `;
    }

    // Display message when no sales agent information is available
    function displayNoSalesAgentInfo() {
      const content = document.getElementById('salesAgentInfoContent');
      content.innerHTML = `
        <div class="no-customer-info">
          <i class="fas fa-user-slash" style="font-size: 48px; color: var(--text-light); margin-bottom: 20px;"></i>
          <h4>No Agent Assigned Yet</h4>
          <p>This customer has not been assigned to a sales agent. Use the "Reassign" button to assign them to an agent.</p>
        </div>
      `;
    }

    // Helper functions
    function showAlert(elementId, message) {
      const alert = document.getElementById(elementId);
      alert.textContent = message;
      alert.classList.add('active');
      setTimeout(() => alert.classList.remove('active'), 5000);
    }

    function clearCreateForm() {
      document.getElementById('createAccountForm').reset();
    }

    // Real-time filtering implementation
    let searchTimeout;
    let customerSearchTimeout;
    let isApplyingFilters = false;

    // Show loading indicator
    function showFilterLoading() {
      if (!isApplyingFilters) {
        isApplyingFilters = true;
        // Add a subtle loading indicator to the filter bar
        const filterBars = document.querySelectorAll('.filter-bar');
        filterBars.forEach(bar => {
          bar.style.opacity = '0.7';
          bar.style.pointerEvents = 'none';
        });
      }
    }

    // Hide loading indicator
    function hideFilterLoading() {
      if (isApplyingFilters) {
        isApplyingFilters = false;
        const filterBars = document.querySelectorAll('.filter-bar');
        filterBars.forEach(bar => {
          bar.style.opacity = '1';
          bar.style.pointerEvents = 'auto';
        });
      }
    }

    // Debounced search function for All Accounts
    function debouncedSearch() {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => {
        applyFilters();
      }, 250);
    }

    // Debounced search function for Customer Accounts
    function debouncedCustomerSearch() {
      clearTimeout(customerSearchTimeout);
      customerSearchTimeout = setTimeout(() => {
        applyCustomerFilters();
      }, 250);
    }

    // Real-time search for All Accounts tab
    document.getElementById('searchInput').addEventListener('input', debouncedSearch);
    document.getElementById('searchInput').addEventListener('keypress', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        clearTimeout(searchTimeout);
        applyFilters();
      }
    });

    // Real-time filters for All Accounts tab
    document.getElementById('roleFilter').addEventListener('change', applyFilters);
    document.getElementById('sortFilter').addEventListener('change', applyFilters);

    // Real-time search for Customer Accounts tab
    document.getElementById('custSearchInput').addEventListener('input', debouncedCustomerSearch);
    document.getElementById('custSearchInput').addEventListener('keypress', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        clearTimeout(customerSearchTimeout);
        applyCustomerFilters();
      }
    });

    // Real-time filter for Customer Accounts tab
    document.getElementById('custSortFilter').addEventListener('change', applyCustomerFilters);

    // Check for active filters and apply visual indicators
    function checkActiveFilters() {
      // Check All Accounts filters
      const allAccountsBar = document.querySelector('#account-all .filter-bar');
      const searchInput = document.getElementById('searchInput').value;
      const roleFilter = document.getElementById('roleFilter').value;
      
      if (searchInput || roleFilter !== 'all') {
        allAccountsBar.classList.add('has-active-filters');
      } else {
        allAccountsBar.classList.remove('has-active-filters');
      }

      // Check Customer Accounts filters
      const customerAccountsBar = document.querySelector('#customer-accounts .filter-bar');
      const custSearchInput = document.getElementById('custSearchInput').value;
      
      if (custSearchInput) {
        customerAccountsBar.classList.add('has-active-filters');
      } else {
        customerAccountsBar.classList.remove('has-active-filters');
      }
    }

    // Run check on page load
    checkActiveFilters();

    // Add event listeners to update visual indicators
    document.getElementById('searchInput').addEventListener('input', checkActiveFilters);
    document.getElementById('roleFilter').addEventListener('change', checkActiveFilters);
    document.getElementById('custSearchInput').addEventListener('input', checkActiveFilters);
  </script>
</body>
</html>
