<?php
// Admin Dashboard Cards
require_once __DIR__ . '/../database/db_conn.php';

// Add Status column to customer_information table if it doesn't exist
try {
  $connect->exec("ALTER TABLE customer_information ADD COLUMN Status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending'");
} catch (PDOException $e) {
  // Column might already exist, ignore error
}

// Function to get pending account approvals
function getPendingApprovals($connect)
{
  $query = "SELECT 
        a.Id as account_id,
        a.Username,
        a.Email,
        a.FirstName,
        a.LastName,
        a.Status as AccountStatus,
        a.CreatedAt as account_created,
        ci.cusID,
        ci.lastname,
        ci.firstname,
        ci.middlename,
        ci.suffix,
        ci.nationality,
        ci.birthday,
        ci.age,
        ci.gender,
        ci.civil_status,
        ci.mobile_number,
        ci.employment_status,
        ci.company_name,
        ci.position,
        ci.monthly_income,
        ci.valid_id_type,
        ci.valid_id_number,
        ci.Status as CustomerStatus,
        ci.customer_type,
        ci.created_at
    FROM accounts a
    LEFT JOIN customer_information ci ON a.Id = ci.account_id
    WHERE a.Role = 'Customer' 
    AND (a.Status = 'Pending' OR a.Status IS NULL)
    AND (ci.Status = 'Pending' OR ci.Status IS NULL OR ci.cusID IS NULL)
    ORDER BY a.CreatedAt DESC";
    
  $stmt = $connect->prepare($query);
  $stmt->execute();
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get approved accounts
function getApprovedAccounts($connect)
{
  // Check if Status column exists in customer_information
  $checkColumn = "SHOW COLUMNS FROM customer_information LIKE 'Status'";
  $stmt = $connect->prepare($checkColumn);
  $stmt->execute();
  $statusColumnExists = $stmt->fetch();

  if ($statusColumnExists) {
    $query = "SELECT 
            ci.cusID,
            ci.account_id,
            ci.firstname,
            ci.lastname,
            ci.middlename,
            ci.suffix,
            ci.nationality,
            ci.birthday,
            ci.age,
            ci.gender,
            ci.civil_status,
            ci.mobile_number,
            ci.employment_status,
            ci.company_name,
            ci.position,
            ci.monthly_income,
            ci.valid_id_type,
            ci.Status,
            ci.created_at,
            a.Username,
            a.Email,
            a.CreatedAt as account_created,
            a.LastLoginAt
        FROM customer_information ci
        INNER JOIN accounts a ON ci.account_id = a.Id
        WHERE a.Role = 'Customer' 
        AND (ci.Status = 'Approved' OR a.Status = 'Approved')
        ORDER BY ci.updated_at DESC, ci.created_at DESC";
  } else {
    // If Status column doesn't exist, show all customers with approved accounts
    $query = "SELECT 
            ci.cusID,
            ci.account_id,
            ci.firstname,
            ci.lastname,
            ci.middlename,
            ci.suffix,
            ci.nationality,
            ci.birthday,
            ci.age,
            ci.gender,
            ci.civil_status,
            ci.mobile_number,
            ci.employment_status,
            ci.company_name,
            ci.position,
            ci.monthly_income,
            ci.valid_id_type,
            'Approved' as Status,
            ci.created_at,
            a.Username,
            a.Email,
            a.CreatedAt as account_created,
            a.LastLoginAt
        FROM customer_information ci
        INNER JOIN accounts a ON ci.account_id = a.Id
        WHERE a.Role = 'Customer' AND a.Status = 'Approved'
        ORDER BY ci.created_at DESC";
  }

  $stmt = $connect->prepare($query);
  $stmt->execute();
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get rejected accounts
function getRejectedAccounts($connect)
{
  // Enhanced query to get all rejected accounts with proper handling of missing customer_information
  $query = "SELECT 
        ci.cusID,
        a.Id as account_id,
        COALESCE(ci.firstname, a.FirstName) as firstname,
        COALESCE(ci.lastname, a.LastName) as lastname,
        ci.middlename,
        ci.suffix,
        ci.nationality,
        ci.birthday,
        ci.age,
        ci.gender,
        ci.civil_status,
        ci.mobile_number,
        ci.employment_status,
        ci.company_name,
        ci.position,
        ci.monthly_income,
        ci.valid_id_type,
        ci.Status as CustomerStatus,
        ci.created_at,
        ci.updated_at,
        a.Username,
        a.Email,
        a.FirstName,
        a.LastName,
        a.CreatedAt as account_created,
        a.Status as AccountStatus,
        aa.description as rejection_reason,
        aa.created_at as rejected_at
    FROM accounts a
    LEFT JOIN customer_information ci ON a.Id = ci.account_id
    LEFT JOIN admin_actions aa ON (
        (ci.cusID IS NOT NULL AND ci.cusID = aa.target_id) OR 
        (ci.cusID IS NULL AND a.Id = aa.target_id)
    ) AND aa.action_type = 'REJECT_CUSTOMER'
    WHERE a.Role = 'Customer' 
    AND (
        (ci.Status = 'Rejected') OR 
        (a.Status = 'Rejected') OR
        (aa.action_type = 'REJECT_CUSTOMER')
    )
    ORDER BY COALESCE(ci.updated_at, a.UpdatedAt, aa.created_at) DESC";

  $stmt = $connect->prepare($query);
  $stmt->execute();
  $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  // Debug: Let's see what we're getting
  error_log("Rejected accounts query results: " . print_r($results, true));
  
  return $results;
}

// Function to check if profile is complete
function isProfileComplete($customer)
{
  // Check required fields from customer_information table
  $requiredCustomerFields = [
    'firstname',
    'lastname',
    'mobile_number',
    'gender',
    'civil_status',
    'employment_status',
    'valid_id_type',
    'valid_id_number'
  ];

  // Check required fields from accounts table
  $requiredAccountFields = [
    'Username',
    'Email'
  ];

  // Check if customer_information record exists 
  if (!isset($customer['cusID'])) {
    return false;
  }

  // Check customer_information required fields
  foreach ($requiredCustomerFields as $field) {
    if (empty($customer[$field])) {
      return false;
    }
  }

  // Check accounts required fields
  foreach ($requiredAccountFields as $field) {
    if (empty($customer[$field])) {
      return false;
    }
  }

  return true;
}

// Function to get account statistics
function getAccountStats($connect)
{
  $stats = array();

  // Check if Status column exists in customer_information
  $checkColumn = "SHOW COLUMNS FROM customer_information LIKE 'Status'";
  $stmt = $connect->prepare($checkColumn);
  $stmt->execute();
  $statusColumnExists = $stmt->fetch();

  if ($statusColumnExists) {
    // Get total customer accounts (base for percentage calculation)
    $query = "SELECT COUNT(*) as total_customers 
              FROM accounts a 
              WHERE a.Role = 'Customer'";
    $stmt = $connect->prepare($query);
    $stmt->execute();
    $stats['total_customers'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_customers'];

    // Pending reviews count - accounts that are still pending
    $query = "SELECT COUNT(*) as pending_count 
                  FROM accounts a 
                  LEFT JOIN customer_information ci ON a.Id = ci.account_id
                  WHERE a.Role = 'Customer' 
                  AND (a.Status = 'Pending' OR a.Status IS NULL)
                  AND (ci.Status = 'Pending' OR ci.Status IS NULL OR ci.cusID IS NULL)";
    $stmt = $connect->prepare($query);
    $stmt->execute();
    $stats['pending_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['pending_count'];

    // Total approved accounts
    $query = "SELECT COUNT(*) as approved_count 
                  FROM accounts a 
                  LEFT JOIN customer_information ci ON a.Id = ci.account_id
                  WHERE a.Role = 'Customer' 
                  AND (ci.Status = 'Approved' OR a.Status = 'Approved')";
    $stmt = $connect->prepare($query);
    $stmt->execute();
    $stats['approved_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['approved_count'];

    // Total rejected accounts - simplified query to match what we're displaying
    $query = "SELECT COUNT(*) as rejected_count 
                  FROM accounts a 
                  LEFT JOIN customer_information ci ON a.Id = ci.account_id
                  LEFT JOIN admin_actions aa ON (
                      (ci.cusID IS NOT NULL AND ci.cusID = aa.target_id) OR 
                      (ci.cusID IS NULL AND a.Id = aa.target_id)
                  ) AND aa.action_type = 'REJECT_CUSTOMER'
                  WHERE a.Role = 'Customer' 
                  AND (
                      (ci.Status = 'Rejected') OR 
                      (a.Status = 'Rejected') OR
                      (aa.action_type = 'REJECT_CUSTOMER')
                  )";
    $stmt = $connect->prepare($query);
    $stmt->execute();
    $stats['rejected_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['rejected_count'];

    // Calculate percentages based on total customer accounts
    $stats['approved_percentage'] = $stats['total_customers'] > 0 ? round(($stats['approved_count'] / $stats['total_customers']) * 100, 1) : 0;
    $stats['rejected_percentage'] = $stats['total_customers'] > 0 ? round(($stats['rejected_count'] / $stats['total_customers']) * 100, 1) : 0;

    // This month rejected count
    $query = "SELECT COUNT(*) as rejected_this_month 
                  FROM accounts a 
                  LEFT JOIN customer_information ci ON a.Id = ci.account_id
                  LEFT JOIN admin_actions aa ON (
                      (ci.cusID IS NOT NULL AND ci.cusID = aa.target_id) OR 
                      (ci.cusID IS NULL AND a.Id = aa.target_id)
                  ) AND aa.action_type = 'REJECT_CUSTOMER'
                  WHERE a.Role = 'Customer' 
                  AND (
                      (ci.Status = 'Rejected') OR 
                      (a.Status = 'Rejected') OR
                      (aa.action_type = 'REJECT_CUSTOMER')
                  )
                  AND (
                      MONTH(ci.updated_at) = MONTH(CURDATE()) AND YEAR(ci.updated_at) = YEAR(CURDATE()) OR
                      MONTH(a.UpdatedAt) = MONTH(CURDATE()) AND YEAR(a.UpdatedAt) = YEAR(CURDATE()) OR
                      MONTH(aa.created_at) = MONTH(CURDATE()) AND YEAR(aa.created_at) = YEAR(CURDATE())
                  )";
    $stmt = $connect->prepare($query);
    $stmt->execute();
    $stats['rejected_this_month'] = $stmt->fetch(PDO::FETCH_ASSOC)['rejected_this_month'];

    // Most common rejection reason
    $query = "SELECT aa.description, COUNT(*) as reason_count
                  FROM admin_actions aa
                  WHERE aa.action_type = 'REJECT_CUSTOMER'
                  GROUP BY aa.description
                  ORDER BY reason_count DESC
                  LIMIT 1";
    $stmt = $connect->prepare($query);
    $stmt->execute();
    $commonReason = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['common_rejection_reason'] = $commonReason ? $commonReason['description'] : 'N/A';
  } else {
    // If Status column doesn't exist, set defaults
    $query = "SELECT COUNT(*) as total_customers 
              FROM accounts a 
              WHERE a.Role = 'Customer'";
    $stmt = $connect->prepare($query);
    $stmt->execute();
    $stats['total_customers'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_customers'];

    $query = "SELECT COUNT(*) as pending_count 
                  FROM customer_information ci 
                  INNER JOIN accounts a ON ci.account_id = a.Id 
                  WHERE a.Role = 'Customer' AND a.Status = 'Pending'";
    $stmt = $connect->prepare($query);
    $stmt->execute();
    $stats['pending_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['pending_count'];

    $stats['approved_count'] = 0;
    $stats['rejected_count'] = 0;
    $stats['approved_percentage'] = 0;
    $stats['rejected_percentage'] = 0;
    $stats['rejected_this_month'] = 0;
    $stats['common_rejection_reason'] = 'N/A';
  }

  // Today's registrations
  $query = "SELECT COUNT(*) as today_count 
              FROM customer_information ci 
              INNER JOIN accounts a ON ci.account_id = a.Id 
              WHERE a.Role = 'Customer' AND DATE(ci.created_at) = CURDATE()";
  $stmt = $connect->prepare($query);
  $stmt->execute();
  $stats['today_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['today_count'];

  return $stats;
}

// Get data for display
$pendingApprovals = getPendingApprovals($connect);
$approvedAccounts = getApprovedAccounts($connect);
$rejectedAccounts = getRejectedAccounts($connect);
$accountStats = getAccountStats($connect);
?>
<div class="dashboard-grid">
  <div class="dashboard-card">
    <div class="card-header">
      <div class="card-icon red">
        <i class="fas fa-user-check"></i>
      </div>
      <div class="card-title">Account Management</div>
    </div>
    <p>Review and approve new customer account registrations.</p>
  </div>

  <div class="dashboard-card">
    <div class="card-header">
      <div class="card-icon blue">
        <i class="fas fa-credit-card"></i>
      </div>
      <div class="card-title">Payment Verification</div>
    </div>
    <p>Verify customer payments and manage transaction records.</p>
  </div>

  <div class="dashboard-card">
    <div class="card-header">
      <div class="card-icon green">
        <i class="fas fa-car-side"></i>
      </div>
      <div class="card-title">Vehicle Inventory</div>
    </div>
    <p>Manage vehicle listings, specifications and pricing information.</p>
  </div>
</div>

<div class="action-buttons">
  <button class="action-btn" id="accountReviewBtn">
    <i class="fas fa-user-check"></i>
    Account Review
  </button>
  <button class="action-btn" id="transactionUpdateBtn">
    <i class="fas fa-credit-card"></i>
    Transaction Update
  </button>
  <button class="action-btn" id="carListingBtn">
    <i class="fas fa-car-side"></i>
    Car Listing
  </button>
</div>

<!-- Admin Account Review Interface -->
<div class="interface-container" id="accountReviewInterface">
  <div class="interface-header">
    <h2 class="interface-title">
      <i class="fas fa-user-check"></i>
      Admin Account Review
    </h2>
    <button class="interface-close" id="closeAccountReview">&times;</button>
  </div>

  <div class="tab-navigation">
    <button class="tab-button active" data-tab="account-pending">Pending Approvals</button>
    <button class="tab-button" data-tab="account-approved">Approved Accounts</button>
    <button class="tab-button" data-tab="account-rejected">Rejected Accounts</button>
  </div>

  <!-- Pending Approvals Tab -->
  <div class="tab-content active" id="account-pending">
    <div class="info-cards">
      <div class="info-card">
        <div class="info-card-title">Pending Reviews</div>
        <div class="info-card-value"><?php echo $accountStats['pending_count']; ?></div>
      </div>
      <div class="info-card">
        <div class="info-card-title">Today's Registrations</div>
        <div class="info-card-value"><?php echo $accountStats['today_count']; ?></div>
      </div>
      <div class="info-card">
        <div class="info-card-title">Incomplete Profiles</div>
        <div class="info-card-value"><?php echo count(array_filter($pendingApprovals, function ($acc) {
                                        return !isProfileComplete($acc);
                                      })); ?></div>
      </div>
    </div>

    <table class="data-table">
      <thead>
        <tr>
          <th>Account ID</th>
          <th>Customer Information</th>
          <th>Account Type</th>
          <th>Registration Date</th>
          <th>Profile Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($pendingApprovals)): ?>
          <?php foreach ($pendingApprovals as $customer): ?>
            <?php $profileComplete = isProfileComplete($customer); ?>
            <tr>
              <td>ACC-<?php echo str_pad($customer['account_id'], 6, '0', STR_PAD_LEFT); ?></td>
              <td>
                <?php if (!empty($customer['firstname']) && !empty($customer['lastname'])): ?>
                  <?php echo htmlspecialchars($customer['firstname'] . ' ' . $customer['lastname']); ?><br>
                <?php elseif (!empty($customer['FirstName']) && !empty($customer['LastName'])): ?>
                  <?php echo htmlspecialchars($customer['FirstName'] . ' ' . $customer['LastName']); ?><br>
                <?php else: ?>
                  <em>Name not provided</em><br>
                <?php endif; ?>
                <small><?php echo htmlspecialchars($customer['Email']); ?><br>
                  <?php echo htmlspecialchars($customer['mobile_number'] ?? 'No phone'); ?></small>
              </td>
              <td>Customer</td>
              <td><?php echo date('M d, Y g:i A', strtotime($customer['account_created'])); ?></td>
              <td>
                <span class="status <?php echo $profileComplete ? 'approved' : 'pending'; ?>">
                  <?php echo $profileComplete ? 'Complete' : 'Incomplete'; ?>
                </span>
              </td>
              <td class="table-actions">
                <button class="btn btn-small btn-primary" onclick="reviewAccount('<?php echo $customer['cusID'] ?? ''; ?>', '<?php echo $customer['account_id']; ?>', <?php echo $profileComplete ? 'true' : 'false'; ?>)">Review</button>
                <?php if ($profileComplete): ?>
                  <button class="btn btn-small btn-outline" onclick="quickApproveAccount('<?php echo $customer['cusID']; ?>')">Approve</button>
                  <button class="btn btn-small btn-danger" onclick="showRejectModal('<?php echo $customer['cusID'] ?? ''; ?>', '<?php echo $customer['account_id']; ?>')">Reject</button>
                <?php else: ?>
                  <button class="btn btn-small btn-secondary" disabled title="Profile incomplete">Approve</button>
                  <button class="btn btn-small btn-danger" onclick="showRejectModal('<?php echo $customer['cusID'] ?? ''; ?>', '<?php echo $customer['account_id']; ?>')">Reject</button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="6" style="text-align: center; padding: 20px;">No pending approvals found.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Approved Accounts Tab -->
  <div class="tab-content" id="account-approved">
    <div class="info-cards">
      <div class="info-card">
        <div class="info-card-title">Total Approved</div>
        <div class="info-card-value"><?php echo $accountStats['approved_count']; ?></div>
      </div>
      <div class="info-card">
        <div class="info-card-title">This Month</div>
        <div class="info-card-value"><?php echo count(array_filter($approvedAccounts, function ($acc) {
                                        return date('Y-m', strtotime($acc['created_at'])) == date('Y-m');
                                      })); ?></div>
      </div>
      <div class="info-card">
        <div class="info-card-title">Approval Rate</div>
        <div class="info-card-value" style="font-size: 14px;">
          <?php echo $accountStats['approved_percentage']; ?>%
        </div>
      </div>
    </div>

    <table class="data-table">
      <thead>
        <tr>
          <th>Customer ID</th>
          <th>Customer Information</th>
          <th>Employment</th>
          <th>Approved Date</th>
          <th>Last Login</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($approvedAccounts)): ?>
          <?php foreach ($approvedAccounts as $customer): ?>
            <tr>
              <td>CUS-<?php echo str_pad($customer['cusID'], 6, '0', STR_PAD_LEFT); ?></td>
              <td>
                <?php echo htmlspecialchars($customer['firstname'] . ' ' . $customer['lastname']); ?><br>
                <small><?php echo htmlspecialchars($customer['Email']); ?><br>
                  <?php echo htmlspecialchars($customer['mobile_number'] ?? 'No phone'); ?></small>
              </td>
              <td>
                <?php echo htmlspecialchars($customer['employment_status'] ?? 'Not specified'); ?><br>
                <small><?php echo htmlspecialchars($customer['company_name'] ?? 'No company'); ?></small>
              </td>
              <td><?php echo date('M d, Y', strtotime($customer['created_at'])); ?></td>
              <td>
                <?php if ($customer['LastLoginAt']): ?>
                  <?php echo date('M d, Y g:i A', strtotime($customer['LastLoginAt'])); ?>
                <?php else: ?>
                  <span style="color: #888;">Never</span>
                <?php endif; ?>
              </td>
              <td class="table-actions">
                <button class="btn btn-small btn-primary" onclick="viewCustomerDetails('<?php echo $customer['cusID']; ?>')">View</button>
                <!-- Removing the Edit button as requested -->
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="6" style="text-align: center; padding: 20px;">No approved accounts found.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Rejected Accounts Tab -->
  <div class="tab-content" id="account-rejected">
    <div class="info-cards">
      <div class="info-card">
        <div class="info-card-title">Total Rejected</div>
        <div class="info-card-value"><?php echo $accountStats['rejected_count']; ?></div>
      </div>
      <div class="info-card">
        <div class="info-card-title">This Month</div>
        <div class="info-card-value"><?php echo $accountStats['rejected_this_month']; ?></div>
      </div>
      <div class="info-card">
        <div class="info-card-title">Rejection Rate</div>
        <div class="info-card-value" style="font-size: 14px;">
          <?php echo $accountStats['rejected_percentage']; ?>%
        </div>
      </div>
    </div>

    <table class="data-table">
      <thead>
        <tr>
          <th>Account ID</th>
          <th>Customer Information</th>
          <th>Rejection Reason</th>
          <th>Rejected Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($rejectedAccounts)): ?>
          <?php foreach ($rejectedAccounts as $customer): ?>
            <tr>
              <td>ACC-<?php echo str_pad($customer['account_id'] ?? 0, 6, '0', STR_PAD_LEFT); ?></td>
              <td>
                <?php if (!empty($customer['firstname']) && !empty($customer['lastname'])): ?>
                  <?php echo htmlspecialchars($customer['firstname'] . ' ' . $customer['lastname']); ?><br>
                <?php elseif (!empty($customer['FirstName']) && !empty($customer['LastName'])): ?>
                  <?php echo htmlspecialchars($customer['FirstName'] . ' ' . $customer['LastName']); ?><br>
                <?php else: ?>
                  <em>Name not provided</em><br>
                <?php endif; ?>
                <small><?php echo htmlspecialchars($customer['Email'] ?? 'No email'); ?><br>
                  <?php echo htmlspecialchars($customer['mobile_number'] ?? 'No phone'); ?></small>
              </td>
              <td>
                <div style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($customer['rejection_reason'] ?? 'No reason provided'); ?>">
                  <?php echo htmlspecialchars($customer['rejection_reason'] ?? 'No reason provided'); ?>
                </div>
              </td>
              <td><?php echo $customer['rejected_at'] ? date('M d, Y g:i A', strtotime($customer['rejected_at'])) : 'Unknown'; ?></td>
              <td class="table-actions">
                <?php if (!empty($customer['cusID']) && $customer['cusID'] > 0): ?>
                  <button class="btn btn-small btn-primary" onclick="viewCustomerDetails('<?php echo $customer['cusID']; ?>')" title="View Details">
                    <i class="fas fa-eye"></i>
                  </button>
                <?php elseif (!empty($customer['account_id'])): ?>
                  <button class="btn btn-small btn-primary" onclick="viewAccountDetails('<?php echo $customer['account_id']; ?>')" title="View Account Details">
                    <i class="fas fa-eye"></i>
                  </button>
                <?php else: ?>
                  <span class="text-muted">No actions available</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="5" style="text-align: center; padding: 20px;">No rejected accounts found.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Admin Transaction Update Interface -->
<div class="interface-container" id="transactionUpdateInterface">
  <div class="interface-header">
    <h2 class="interface-title">
      <i class="fas fa-credit-card"></i>
      Admin Transaction Update
    </h2>
    <button class="interface-close" id="closeTransactionUpdate">&times;</button>
  </div>

  <div class="tab-navigation">
    <button class="tab-button active" data-tab="transaction-pending">Pending Payments</button>
    <button class="tab-button" data-tab="transaction-verified">Verified Payments</button>
    <button class="tab-button" data-tab="transaction-customers">All Loan Customers</button>
  </div>

  <div class="tab-content active" id="transaction-pending">
    <div class="info-cards">
      <div class="info-card">
        <div class="info-card-title">Pending Verifications</div>
        <div class="info-card-value" id="pendingCount">Loading...</div>
      </div>
      <div class="info-card">
        <div class="info-card-title">Total Pending Amount</div>
        <div class="info-card-value" id="pendingAmount">Loading...</div>
      </div>
    </div>

    <table class="data-table">
      <thead>
        <tr>
          <th>Payment ID</th>
          <th>Customer</th>
          <th>Agent</th>
          <th>Vehicle</th>
          <th>Amount</th>
          <th>Method</th>
          <th>Reference</th>
          <th>Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody id="pendingPaymentsTable">
        <tr>
          <td colspan="9" class="text-center">Loading pending payments...</td>
        </tr>
      </tbody>
    </table>
  </div>

  <div class="tab-content" id="transaction-verified">
    <div class="info-cards">
      <div class="info-card">
        <div class="info-card-title">Total Verified</div>
        <div class="info-card-value" id="verifiedCount">0</div>
      </div>
      <div class="info-card">
        <div class="info-card-title">Total Verified Amount</div>
        <div class="info-card-value" id="verifiedAmount">₱0.00</div>
      </div>
    </div>

    <table class="data-table">
      <thead>
        <tr>
          <th>Payment ID</th>
          <th>Customer</th>
          <th>Agent</th>
          <th>Vehicle</th>
          <th>Amount</th>
          <th>Payment Date</th>
          <th>Verified By</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody id="verifiedPaymentsTable">
        <tr>
          <td colspan="8" class="text-center">Loading verified payments...</td>
        </tr>
      </tbody>
    </table>
  </div>

  <div class="tab-content" id="transaction-customers">
    <div class="info-cards">
      <div class="info-card">
        <div class="info-card-title">Total Loan Customers</div>
        <div class="info-card-value" id="loanCustomersCount">0</div>
      </div>
      <div class="info-card">
        <div class="info-card-title">Active Loans</div>
        <div class="info-card-value" id="activeLoansCount">0</div>
      </div>
      <div class="info-card">
        <div class="info-card-title">Overdue Payments</div>
        <div class="info-card-value" id="overdueCount">0</div>
      </div>
    </div>

    <table class="data-table">
      <thead>
        <tr>
          <th>Order #</th>
          <th>Customer</th>
          <th>Agent</th>
          <th>Vehicle</th>
          <th>Monthly Payment</th>
          <th>Progress</th>
          <th>Next Due</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody id="loanCustomersTable">
        <tr>
          <td colspan="9" class="text-center">Loading loan customers...</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>



<!-- Add/Edit Vehicle Modal -->
<div class="modal-overlay" id="adminVehicleModal">
  <div class="modal">
    <div class="modal-header">
      <h3 id="adminModalTitle">Add New Vehicle</h3>
      <button class="modal-close" onclick="closeAdminVehicleModal()">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <form id="addVehicleForm" enctype="multipart/form-data">
      <div class="modal-body">
        <input type="hidden" id="adminVehicleId" name="id">
        <input type="hidden" id="adminExistingView360Images" name="existing_view_360_images" value="">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Model Name *</label>
            <input type="text" class="form-control" id="adminModelName" name="model_name" required>
          </div>
          <div class="form-group">
            <label class="form-label">Variant *</label>
            <input type="text" class="form-control" id="adminVariant" name="variant" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Year Model *</label>
            <input type="number" class="form-control" id="adminYearModel" name="year_model" min="2000" max="2030" required>
          </div>
          <div class="form-group">
            <label class="form-label">Category *</label>
            <select class="form-control" id="adminCategory" name="category" required>
              <option value="">Select Category</option>
              <option value="Sedan">Sedan</option>
              <option value="SUV">SUV</option>
              <option value="Pickup">Pickup</option>
              <option value="Hatchback">Hatchback</option>
              <option value="MPV">MPV</option>
            </select>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Engine Type *</label>
            <input type="text" class="form-control" id="adminEngineType" name="engine_type" required>
          </div>
          <div class="form-group">
            <label class="form-label">Transmission *</label>
            <select class="form-control" id="adminTransmission" name="transmission" required>
              <option value="">Select Transmission</option>
              <option value="Manual">Manual</option>
              <option value="Automatic">Automatic</option>
              <option value="CVT">CVT</option>
            </select>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Fuel Type *</label>
            <select class="form-control" id="adminFuelType" name="fuel_type" required>
              <option value="">Select Fuel Type</option>
              <option value="Gasoline">Gasoline</option>
              <option value="Diesel">Diesel</option>
              <option value="Hybrid">Hybrid</option>
              <option value="Electric">Electric</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Seating Capacity *</label>
            <input type="number" class="form-control" id="adminSeatingCapacity" name="seating_capacity" min="2" max="9" required>
          </div>
        </div>

        <div class="form-row full-width">
          <div class="form-group">
            <label class="form-label">Key Features</label>
            <textarea class="form-control" id="adminKeyFeatures" name="key_features" rows="3"></textarea>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Base Price *</label>
            <input type="number" class="form-control" id="adminBasePrice" name="base_price" min="0" step="0.01" required>
          </div>
          <div class="form-group">
            <label class="form-label">Promotional Price</label>
            <input type="number" class="form-control" id="adminPromotionalPrice" name="promotional_price" min="0" step="0.01">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Min Downpayment % *</label>
            <input type="number" class="form-control" id="adminMinDownpayment" name="min_downpayment_percentage" min="0" max="100" required>
          </div>
          <div class="form-group">
            <label class="form-label">Financing Terms</label>
            <input type="text" class="form-control" id="adminFinancingTerms" name="financing_terms">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Color Options *</label>
            <input type="text" class="form-control" id="adminColorOptions" name="color_options" placeholder="e.g., White Pearl, Black Mica, Silver" required>
          </div>
          <div class="form-group">
            <label class="form-label">Popular Color</label>
            <input type="text" class="form-control" id="adminPopularColor" name="popular_color">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Stock Quantity *</label>
            <input type="number" class="form-control" id="adminStockQuantity" name="stock_quantity" min="0" required>
          </div>
          <div class="form-group">
            <label class="form-label">Min Stock Alert *</label>
            <input type="number" class="form-control" id="adminMinStockAlert" name="min_stock_alert" min="0" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Availability Status *</label>
            <select class="form-control" id="adminAvailabilityStatus" name="availability_status" required>
              <option value="available">Available</option>
              <option value="pre-order">Pre-order</option>
              <option value="discontinued">Discontinued</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Expected Delivery Time</label>
            <input type="text" class="form-control" id="adminExpectedDelivery" name="expected_delivery_time" placeholder="e.g., 2-3 weeks">
          </div>
        </div>

        <div class="image-upload-section">
          <label class="image-upload-label">Main Image</label>
          <input type="file" class="file-input" id="adminMainImage" name="main_image" accept="image/*">
          <div class="file-info">Max size: 10MB per file</div>

          <label class="image-upload-label">Additional Images</label>
          <input type="file" class="file-input" id="adminAdditionalImages" name="additional_images[]" accept="image/*" multiple>
          <div class="file-info">Max size: 10MB per file</div>

          <label class="image-upload-label" style="margin-top:12px;">3D Models by Color</label>

          <!-- Display existing 3D models -->
          <div id="adminExistingModelsDisplay" style="margin-top: 12px; display: none;">
            <label class="image-upload-label">Current 3D Models:</label>
            <div id="adminExistingModelsList" style="padding: 10px; background: #f5f5f5; border-radius: 4px; margin-bottom: 10px;">
              <!-- Will be populated dynamically -->
            </div>
            <div class="file-info" style="color: #666; margin-top: 5px;">
              Upload new models below to replace these, or leave empty to keep existing models.
            </div>
          </div>

          <div id="adminColorModelList"></div>
          <button type="button" class="btn btn-secondary" id="adminAddColorModelBtn" style="margin-top:8px;">Add Color & Model</button>
          <div class="file-info">Pair each color with its .glb/.gltf file.</div>

          <!-- Upload Progress Bar -->
          <div id="adminUploadProgressContainer" style="display: none; margin-top: 15px;">
            <label class="form-label" style="margin-bottom: 8px;">Upload Progress</label>
            <div style="background: #f0f0f0; border-radius: 8px; overflow: hidden; height: 30px; position: relative; border: 1px solid #ddd;">
              <div id="adminUploadProgressBar" style="
                width: 0%;
                height: 100%;
                background: linear-gradient(90deg, #e60012 0%, #ff3333 100%);
                transition: width 0.3s ease;
                display: flex;
                align-items: center;
                justify-content: center;
                position: relative;
              ">
                <span id="adminUploadProgressText" style="
                  position: absolute;
                  width: 100%;
                  text-align: center;
                  color: #333;
                  font-weight: 600;
                  font-size: 13px;
                  z-index: 2;
                  mix-blend-mode: difference;
                  color: white;
                ">0%</span>
              </div>
            </div>
            <div id="adminUploadProgressDetails" style="
              display: flex;
              justify-content: space-between;
              margin-top: 5px;
              font-size: 12px;
              color: #666;
            ">
              <span id="adminUploadSpeed">Speed: 0 MB/s</span>
              <span id="adminUploadETA">Time remaining: calculating...</span>
              <span id="adminUploadSize">0 MB / 0 MB</span>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeAdminVehicleModal()">Cancel</button>
        <button type="submit" class="btn btn-primary">
          <span id="adminSubmitBtnText">Add Vehicle</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Customer Review Modal -->
<div id="customerReviewModal" class="modal-overlay" style="display: none;">
  <div class="modal-container">
    <div class="modal-header">
      <h3>Customer Information</h3>
      <button type="button" class="modal-close" onclick="closeCustomerReviewModal()">&times;</button>
    </div>
    <div class="modal-body">
      <div id="customerReviewContent">
        <!-- Customer details will be loaded here -->
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" onclick="closeCustomerReviewModal()">Close</button>
    </div>
  </div>
</div>

<!-- Reject Customer Modal -->
<div id="rejectCustomerModal" class="modal-overlay" style="display: none;">
  <div class="modal-container" style="max-width: 500px;">
    <div class="modal-header">
      <h3>Reject Customer</h3>
      <button type="button" class="modal-close" onclick="closeRejectModal()">&times;</button>
    </div>
    <div class="modal-body">
      <form id="rejectCustomerForm">
        <input type="hidden" id="rejectCustomerId" name="customer_id">
        <input type="hidden" id="rejectAccountId" name="account_id">
        
        <div class="form-group">
          <label class="form-label">Reason for Rejection <span style="color: red;">*</span></label>
          <select class="form-select" name="rejection_reason" id="rejectionReason" required>
            <option value="">Select Reason</option>
            <option value="incomplete_documents">Incomplete Documents</option>
            <option value="invalid_information">Invalid Information</option>
            <option value="failed_verification">Failed Verification</option>
            <option value="duplicate_account">Duplicate Account</option>
            <option value="other">Other</option>
          </select>
        </div>
        
        <div class="form-group">
          <label class="form-label">Additional Comments</label>
          <textarea class="form-input" name="rejection_comments" id="rejectionComments" rows="4" placeholder="Enter additional comments or specific details..."></textarea>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" onclick="closeRejectModal()">Cancel</button>
      <button type="button" class="btn btn-danger" onclick="submitRejection()">Confirm Rejection</button>
    </div>
  </div>
</div>

<style>
/* Modal Styles */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.5);
  z-index: 1000;
  display: none;
  align-items: center;
  justify-content: center;
}

.modal-overlay.active {
  display: flex;
}

.modal-container {
  background: white;
  border-radius: 8px;
  width: 90%;
  max-width: 800px;
  max-height: 95%;
  overflow-y: auto;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
}

.modal {
  background: white;
  border-radius: 8px;
  width: 90%;
  max-width: 900px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
}

.modal-header {
  padding: 20px;
  border-bottom: 1px solid #eee;
  display: flex;
  justify-content: space-between;
  align-items: center;
  background-color: #f8f9fa;
  color: #333;
  border-radius: 8px 8px 0 0;
  border-bottom: 2px solid #dee2e6;
}

.modal-header h3 {
  margin: 0;
  font-size: 1.5rem;
  color: #495057;
}

.modal-close {
  background: none;
  border: none;
  font-size: 24px;
  cursor: pointer;
  color: #6c757d;
  padding: 0;
  width: 30px;
  height: 30px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 4px;
  transition: all 0.2s ease;
}

.modal-close:hover {
  background-color: #e9ecef;
  color: #495057;
}

.modal-body {
  padding: 20px;
  max-height: calc(90vh - 140px);
  overflow-y: auto;
}

.modal-footer {
  padding: 20px;
  border-top: 1px solid #dee2e6;
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  background-color: #f8f9fa;
}

.image-upload-section {
  margin-top: 20px;
  padding-top: 20px;
  border-top: 1px solid #dee2e6;
}

.image-upload-label {
  display: block;
  font-weight: 600;
  margin-top: 15px;
  margin-bottom: 8px;
  color: #495057;
}

.file-input {
  display: block;
  width: 100%;
  padding: 8px;
  border: 1px solid #ced4da;
  border-radius: 4px;
  font-size: 14px;
}

.file-info {
  font-size: 12px;
  color: #6c757d;
  margin-top: 4px;
  margin-bottom: 8px;
}

.form-control {
  width: 100%;
  padding: 8px 12px;
  border: 1px solid #ced4da;
  border-radius: 4px;
  font-size: 14px;
}

.form-control:focus {
  outline: none;
  border-color: #dc143c;
  box-shadow: 0 0 0 0.2rem rgba(220, 20, 60, 0.25);
}

.customer-details-card {
  background: #f8f9fa;
  border: 1px solid #dee2e6;
  border-radius: 6px;
  padding: 15px;
  margin-bottom: 20px;
}

.customer-details-row {
  display: flex;
  margin-bottom: 10px;
}

.customer-details-label {
  font-weight: bold;
  min-width: 150px;
  color: #495057;
}

.customer-details-value {
  color: #212529;
}

.status-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 4px;
  font-size: 0.875rem;
  font-weight: 500;
}

.status-pending {
  background-color: #fff3cd;
  color: #856404;
  border: 1px solid #ffeaa7;
}

.status-approved {
  background-color: #d4edda;
  color: #155724;
  border: 1px solid #c3e6cb;
}

.status-rejected {
  background-color: #f8d7da;
  color: #721c24;
  border: 1px solid #f5c6cb;
}
</style>

<script>
  // Include SweetAlert2 from CDN instead of local node_modules
  if (!document.querySelector('script[src*="sweetalert2"]')) {
    const swalScript = document.createElement('script');
    swalScript.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
    document.head.appendChild(swalScript);

    // Also include SweetAlert2 CSS
    const swalCSS = document.createElement('link');
    swalCSS.rel = 'stylesheet';
    swalCSS.href = 'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css';
    document.head.appendChild(swalCSS);
  }

  // Wait for SweetAlert2 to load before defining custom configurations
  setTimeout(() => {
    // Custom SweetAlert2 configurations with 2 second duration
    const SwalSuccess = Swal.mixin({
      toast: true,
      position: 'top-end',
      showConfirmButton: false,
      timer: 2000,
      timerProgressBar: true,
      icon: 'success',
      didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer)
        toast.addEventListener('mouseleave', Swal.resumeTimer)
      },
      customClass: {
        popup: 'colored-toast'
      }
    });

  // Payment Approval Functions
  function loadPendingPayments() {
    fetch('../includes/api/payment_approval_api.php?action=getPendingPayments')
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          displayPendingPayments(data.data);
          updatePendingStats(data.data);
        } else {
          console.error('Error loading pending payments:', data.error);
          document.getElementById('pendingPaymentsTable').innerHTML = 
            '<tr><td colspan="9" class="text-center">Error loading payments</td></tr>';
        }
      })
      .catch(error => {
        console.error('Error:', error);
        document.getElementById('pendingPaymentsTable').innerHTML = 
          '<tr><td colspan="9" class="text-center">Error loading payments</td></tr>';
      });
  }

  function displayPendingPayments(payments) {
    const tbody = document.getElementById('pendingPaymentsTable');
    
    if (payments.length === 0) {
      tbody.innerHTML = '<tr><td colspan="9" class="text-center">No pending payments</td></tr>';
      return;
    }

    tbody.innerHTML = payments.map(payment => `
      <tr>
        <td>${payment.payment_number || 'PAY-' + payment.id}</td>
        <td>${payment.customer_name}<br><small>${payment.Email || ''}</small></td>
        <td>${payment.agent_name || 'N/A'}</td>
        <td>${payment.vehicle_display || 'N/A'}</td>
        <td>₱${parseFloat(payment.amount_paid).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
        <td>${payment.payment_method}</td>
        <td>${payment.reference_number || 'N/A'}</td>
        <td>${new Date(payment.payment_date).toLocaleDateString()}</td>
        <td class="table-actions">
          <button class="btn btn-small btn-success" onclick="approvePayment(${payment.id})" title="Approve Payment">
            <i class="fas fa-check"></i>
          </button>
          <button class="btn btn-small btn-danger" onclick="rejectPayment(${payment.id})" title="Reject Payment">
            <i class="fas fa-times"></i>
          </button>
          <button class="btn btn-small btn-info" onclick="viewPaymentDetails(${payment.id})" title="View Details">
            <i class="fas fa-eye"></i>
          </button>
        </td>
      </tr>
    `).join('');
  }

  function updatePendingStats(payments) {
    const count = payments.length;
    const totalAmount = payments.reduce((sum, payment) => sum + parseFloat(payment.amount_paid), 0);
    
    document.getElementById('pendingCount').textContent = count;
    document.getElementById('pendingAmount').textContent = '₱' + totalAmount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
  }

  function loadVerifiedPayments() {
    fetch('../includes/api/payment_approval_api.php?action=getVerifiedPayments')
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          displayVerifiedPayments(data.data);
          updateVerifiedStats(data.data);
        } else {
          console.error('Error loading verified payments:', data.error);
          document.getElementById('verifiedPaymentsTable').innerHTML = 
            '<tr><td colspan="8" class="text-center">Error loading payments</td></tr>';
        }
      })
      .catch(error => {
        console.error('Error:', error);
        document.getElementById('verifiedPaymentsTable').innerHTML = 
          '<tr><td colspan="8" class="text-center">Error loading payments</td></tr>';
      });
  }

  function displayVerifiedPayments(payments) {
    const tbody = document.getElementById('verifiedPaymentsTable');
    
    if (payments.length === 0) {
      tbody.innerHTML = '<tr><td colspan="8" class="text-center">No verified payments found</td></tr>';
      return;
    }

    tbody.innerHTML = payments.map(payment => `
      <tr>
        <td>${payment.payment_number || 'PAY-' + payment.id}</td>
        <td>${payment.customer_name}<br><small>${payment.Email || ''}</small></td>
        <td>${payment.agent_name || 'N/A'}</td>
        <td>${payment.vehicle_display || 'N/A'}</td>
        <td>₱${parseFloat(payment.amount_paid).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
        <td>${new Date(payment.payment_date).toLocaleDateString()}</td>
        <td>${payment.processor_name || 'N/A'}<br><small>${payment.updated_at ? new Date(payment.updated_at).toLocaleDateString() : ''}</small></td>
        <td class="table-actions">
          <button class="btn btn-small btn-info" onclick="viewPaymentDetails(${payment.id})" title="View Details">
            <i class="fas fa-eye"></i> Details
          </button>
        </td>
      </tr>
    `).join('');
  }

  function updateVerifiedStats(payments) {
    const count = payments.length;
    const totalAmount = payments.reduce((sum, payment) => sum + parseFloat(payment.amount_paid), 0);
    
    document.getElementById('verifiedCount').textContent = count;
    document.getElementById('verifiedAmount').textContent = '₱' + totalAmount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
  }

  function loadLoanCustomers() {
    fetch('../includes/api/payment_approval_api.php?action=getAllLoanCustomers')
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          displayLoanCustomers(data.data);
          updateLoanCustomerStats(data.data);
        } else {
          console.error('Error loading loan customers:', data.error);
          document.getElementById('loanCustomersTable').innerHTML = 
            '<tr><td colspan="9" class="text-center">Error loading loan customers</td></tr>';
        }
      })
      .catch(error => {
        console.error('Error:', error);
        document.getElementById('loanCustomersTable').innerHTML = 
          '<tr><td colspan="9" class="text-center">Error loading loan customers</td></tr>';
      });
  }

  function displayLoanCustomers(customers) {
    const tbody = document.getElementById('loanCustomersTable');
    
    if (customers.length === 0) {
      tbody.innerHTML = '<tr><td colspan="9" class="text-center">No loan customers found</td></tr>';
      return;
    }

    tbody.innerHTML = customers.map(customer => {
      const statusClass = customer.payment_status === 'overdue' ? 'status rejected' : 
                         customer.payment_status === 'completed' ? 'status approved' : 
                         customer.payment_status === 'in_progress' ? 'status pending' : 
                         'status';
      
      const progressColor = customer.payment_status === 'overdue' ? '#dc3545' : 
                           customer.payment_status === 'completed' ? '#28a745' : 
                           '#ffc107';
      
      return `
        <tr>
          <td>${customer.order_number}</td>
          <td>${customer.customer_name}<br><small>${customer.Email || ''}</small></td>
          <td>${customer.agent_name || 'N/A'}</td>
          <td>${customer.vehicle_display}</td>
          <td>₱${parseFloat(customer.monthly_payment || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
          <td>
            <div style="display: flex; align-items: center; gap: 0.5rem;">
              <div style="flex: 1; background: #e0e0e0; border-radius: 0.5rem; height: 1.25rem; overflow: hidden;">
                <div style="width: ${customer.payment_progress}%; background: ${progressColor}; height: 100%; transition: width 0.3s;"></div>
              </div>
              <span style="font-size: 0.875rem; font-weight: 600;">${customer.payment_progress}%</span>
            </div>
            <small>${customer.payments_made || 0} of ${customer.total_payments_due || 0} payments</small>
          </td>
          <td>${customer.next_due_date ? new Date(customer.next_due_date).toLocaleDateString() : 'N/A'}</td>
          <td><span class="${statusClass}">${customer.payment_status_label}</span></td>
          <td class="table-actions">
            <button class="btn btn-small btn-primary" onclick="viewLoanDetails(${customer.order_id})" title="View Loan Details">
              <i class="fas fa-file-invoice"></i> Details
            </button>
          </td>
        </tr>
      `;
    }).join('');
  }

  function updateLoanCustomerStats(customers) {
    const totalCount = customers.length;
    const activeCount = customers.filter(c => c.payment_status === 'in_progress' || c.payment_status === 'pending').length;
    const overdueCount = customers.filter(c => c.payment_status === 'overdue').length;
    
    document.getElementById('loanCustomersCount').textContent = totalCount;
    document.getElementById('activeLoansCount').textContent = activeCount;
    document.getElementById('overdueCount').textContent = overdueCount;
  }

  function viewLoanDetails(orderId) {
    // Implementation for viewing full loan details with payment schedule
    Swal.fire({
      title: 'Loan Details',
      text: 'Loading loan details...',
      icon: 'info',
      confirmButtonColor: '#dc143c'
    });
    // TODO: Implement full loan details view with payment schedule
  }

  function approvePayment(paymentId) {
    Swal.fire({
      title: 'Approve Payment?',
      text: 'Are you sure you want to approve this payment?',
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#dc143c',
      cancelButtonColor: '#6c757d',
      confirmButtonText: 'Yes, approve it!'
    }).then((result) => {
      if (result.isConfirmed) {
        const formData = new FormData();
        formData.append('action', 'approvePayment');
        formData.append('payment_id', paymentId);
        
        fetch('../includes/api/payment_approval_api.php', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            SwalSuccess.fire({
              title: 'Approved!',
              text: 'Payment has been approved successfully.'
            });
            loadPendingPayments(); // Refresh the list
          } else {
            SwalError.fire({
              title: 'Error!',
              text: data.error || 'Failed to approve payment'
            });
          }
        })
        .catch(error => {
          console.error('Error:', error);
          SwalError.fire({
            title: 'Error!',
            text: 'Network error occurred'
          });
        });
      }
    });
  }

  function rejectPayment(paymentId) {
    Swal.fire({
      title: 'Reject Payment?',
      text: 'Please provide a reason for rejection:',
      input: 'textarea',
      inputPlaceholder: 'Enter rejection reason...',
      inputValidator: (value) => {
        if (!value) {
          return 'You need to provide a reason for rejection!';
        }
      },
      showCancelButton: true,
      confirmButtonColor: '#dc143c',
      cancelButtonColor: '#6c757d',
      confirmButtonText: 'Reject Payment'
    }).then((result) => {
      if (result.isConfirmed) {
        const formData = new FormData();
        formData.append('action', 'rejectPayment');
        formData.append('payment_id', paymentId);
        formData.append('rejection_reason', result.value);
        
        fetch('../includes/api/payment_approval_api.php', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            SwalSuccess.fire({
              title: 'Rejected!',
              text: 'Payment has been rejected.'
            });
            loadPendingPayments(); // Refresh the list
          } else {
            SwalError.fire({
              title: 'Error!',
              text: data.error || 'Failed to reject payment'
            });
          }
        })
        .catch(error => {
          console.error('Error:', error);
          SwalError.fire({
            title: 'Error!',
            text: 'Network error occurred'
          });
        });
      }
    });
  }

  function viewPaymentDetails(paymentId) {
    fetch(`../includes/api/payment_approval_api.php?action=getPaymentDetails&payment_id=${paymentId}`)
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          const payment = data.data;
          
          // Build receipt section
          let receiptSection = '';
          if (payment.has_receipt && payment.receipt_filename) {
            const receiptUrl = payment.receipt_url;
            const isImage = /\.(jpg|jpeg|png|gif)$/i.test(payment.receipt_filename);
            
            if (isImage) {
              receiptSection = `
                <p><strong>Receipt:</strong></p>
                <div style="margin: 10px 0; text-align: center;">
                  <img src="${receiptUrl}" alt="Payment Receipt" style="max-width: 100%; max-height: 200px; border: 1px solid #ddd; border-radius: 4px; cursor: pointer;" onclick="window.open('${receiptUrl}', '_blank')" />
                  <br><small style="color: #666;">Click to view full size</small>
                </div>
              `;
            } else {
              receiptSection = `
                <p><strong>Receipt:</strong> 
                  <a href="${receiptUrl}" target="_blank" style="color: #dc143c; text-decoration: none;">
                    📄 ${payment.receipt_filename}
                  </a>
                </p>
              `;
            }
          } else {
            receiptSection = '<p><strong>Receipt:</strong> <span style="color: #999;">No receipt uploaded</span></p>';
          }
          
          Swal.fire({
            title: 'Payment Details',
            html: `
              <div style="text-align: left; max-height: 500px; overflow-y: auto;">
                <p><strong>Payment Number:</strong> ${payment.payment_number}</p>
                <p><strong>Customer:</strong> ${payment.customer_name}</p>
                <p><strong>Mobile:</strong> ${payment.customer_mobile}</p>
                <p><strong>Email:</strong> ${payment.customer_email}</p>
                <p><strong>Vehicle:</strong> ${payment.vehicle_model} ${payment.vehicle_variant}</p>
                <p><strong>Amount:</strong> ₱${parseFloat(payment.amount_paid).toLocaleString()}</p>
                <p><strong>Payment Method:</strong> ${payment.payment_method}</p>
                <p><strong>Payment Type:</strong> ${payment.payment_type}</p>
                <p><strong>Reference Number:</strong> ${payment.reference_number || 'N/A'}</p>
                <p><strong>Bank:</strong> ${payment.bank_name || 'N/A'}</p>
                ${receiptSection}
                <p><strong>Date Submitted:</strong> ${new Date(payment.payment_date).toLocaleString()}</p>
                <p><strong>Status:</strong> <span style="padding: 2px 8px; border-radius: 4px; background: ${payment.status === 'Pending' ? '#ffc107' : payment.status === 'Confirmed' ? '#28a745' : '#dc3545'}; color: white; font-size: 12px;">${payment.status}</span></p>
                <p><strong>Notes:</strong> ${payment.notes || 'None'}</p>
                ${payment.processed_by_name ? `<p><strong>Processed By:</strong> ${payment.processed_by_name}</p>` : ''}
              </div>
            `,
            width: '700px',
            confirmButtonColor: '#dc143c'
          });
        } else {
          SwalError.fire({
            title: 'Error!',
            text: 'Failed to load payment details'
          });
        }
      })
      .catch(error => {
        console.error('Error:', error);
        SwalError.fire({
          title: 'Error!',
          text: 'Network error occurred'
        });
      });
  }

  // Load data when the transaction update interface is opened
  document.addEventListener('DOMContentLoaded', function() {
    const transactionUpdateBtn = document.getElementById('transactionUpdateBtn');
    if (transactionUpdateBtn) {
      transactionUpdateBtn.addEventListener('click', function() {
        setTimeout(() => {
          loadPendingPayments();
          loadVerifiedPayments();
          loadLoanCustomers();
        }, 100);
      });
    }
    
    // Handle tab switching to reload data
    const transactionTabs = document.querySelectorAll('#transactionUpdateInterface .tab-button');
    transactionTabs.forEach(tab => {
      tab.addEventListener('click', function() {
        const tabId = this.getAttribute('data-tab');
        if (tabId === 'transaction-pending') {
          loadPendingPayments();
        } else if (tabId === 'transaction-verified') {
          loadVerifiedPayments();
        } else if (tabId === 'transaction-customers') {
          loadLoanCustomers();
        }
      });
    });
  });

    const SwalError = Swal.mixin({
      toast: true,
      position: 'top-end',
      showConfirmButton: false,
      timer: 2000,
      timerProgressBar: true,
      icon: 'error',
      didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer)
        toast.addEventListener('mouseleave', Swal.resumeTimer)
      },
      customClass: {
        popup: 'colored-toast'
      }
    });

    const SwalConfirm = Swal.mixin({
      customClass: {
        confirmButton: 'btn btn-primary',
        cancelButton: 'btn btn-secondary',
        denyButton: 'btn btn-outline'
      },
      buttonsStyling: false
    });

    // Store these in window object for global access
    window.SwalSuccess = SwalSuccess;
    window.SwalError = SwalError;
    window.SwalConfirm = SwalConfirm;
  }, 1000);

  // Admin-specific JavaScript
  document.addEventListener('DOMContentLoaded', function() {
    // Interface toggle buttons
    const accountReviewBtn = document.getElementById('accountReviewBtn');
    const transactionUpdateBtn = document.getElementById('transactionUpdateBtn');
    const carListingBtn = document.getElementById('carListingBtn');

    const accountReviewInterface = document.getElementById('accountReviewInterface');
    const transactionUpdateInterface = document.getElementById('transactionUpdateInterface');

    const closeAccountReview = document.getElementById('closeAccountReview');
    const closeTransactionUpdate = document.getElementById('closeTransactionUpdate');

    // Hide all interfaces
    function hideAllInterfaces() {
      accountReviewInterface.style.display = 'none';
      transactionUpdateInterface.style.display = 'none';
    }

    // Toggle interfaces
    accountReviewBtn.addEventListener('click', function() {
      hideAllInterfaces();
      accountReviewInterface.style.display = 'block';
    });

    transactionUpdateBtn.addEventListener('click', function() {
      hideAllInterfaces();
      transactionUpdateInterface.style.display = 'block';
    });

    carListingBtn.addEventListener('click', function() {
      openAddVehicleModal();
    });

    // Modal functions for admin vehicle form
    window.openAddVehicleModal = function() {
      document.getElementById('adminModalTitle').textContent = 'Add New Vehicle';
      document.getElementById('adminSubmitBtnText').textContent = 'Add Vehicle';
      document.getElementById('adminVehicleId').value = '';
      document.getElementById('addVehicleForm').reset();
      document.getElementById('adminColorModelList').innerHTML = '';
      document.getElementById('adminVehicleModal').classList.add('active');
      document.getElementById('adminVehicleModal').style.display = 'flex';
    };

    window.closeAdminVehicleModal = function() {
      document.getElementById('adminVehicleModal').classList.remove('active');
      document.getElementById('adminVehicleModal').style.display = 'none';

      // Clear existing models display
      document.getElementById('adminExistingModelsDisplay').style.display = 'none';
      document.getElementById('adminExistingModelsList').innerHTML = '';
      document.getElementById('adminExistingView360Images').value = '';
      document.getElementById('adminColorModelList').innerHTML = '';

      // Hide and reset progress bar immediately
      document.getElementById('adminUploadProgressContainer').style.display = 'none';
      resetAdminProgressBar();
    };

    // Color-model add button for admin form
    const adminAddBtn = document.getElementById('adminAddColorModelBtn');
    if (adminAddBtn) {
      adminAddBtn.addEventListener('click', function() {
        addAdminColorModelRow();
      });
    }

    function addAdminColorModelRow(colorVal = '', fileRequired = false) {
      const list = document.getElementById('adminColorModelList');
      if (!list) return;
      const row = document.createElement('div');
      row.className = 'form-row';
      row.style.alignItems = 'center';
      row.style.gap = '10px';
      row.innerHTML = `
        <div class="form-group" style="flex:1 1 40%">
          <input type="text" class="form-control" name="color_model_colors[]" placeholder="Color name (e.g., White)" value="${colorVal || ''}">
        </div>
        <div class="form-group" style="flex:1 1 50%">
          <input type="file" class="file-input" name="color_model_files[]" accept=".glb,.gltf" ${fileRequired ? 'required' : ''}>
        </div>
        <div class="form-group" style="flex:0 0 auto">
          <button type="button" class="btn btn-secondary" onclick="this.closest('div.form-row').remove()">Remove</button>
        </div>
      `;
      list.appendChild(row);
    }

    // Form submission handler for admin vehicle form
    const adminVehicleForm = document.getElementById('addVehicleForm');
    if (adminVehicleForm) {
      adminVehicleForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        // Validate color-model files size (50MB each)
        const colorModelFiles = adminVehicleForm.querySelectorAll('input[name="color_model_files[]"]');
        for (let i = 0; i < colorModelFiles.length; i++) {
          const f = colorModelFiles[i].files && colorModelFiles[i].files[0];
          if (f && f.size > 50 * 1024 * 1024) {
            Swal.fire({
              icon: 'error',
              title: 'File Too Large',
              text: `Color model file ${i + 1} is too large. Maximum size is 50MB per file.`
            });
            return;
          }
        }

        const formData = new FormData(adminVehicleForm);
        const submitBtn = adminVehicleForm.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;

        // Calculate total upload size
        const totalSize = calculateAdminFormDataSize(formData);
        const hasLargeFiles = totalSize > 1 * 1024 * 1024; // Show progress for files > 1MB

        if (hasLargeFiles) {
          document.getElementById('adminUploadProgressContainer').style.display = 'block';
          resetAdminProgressBar();
        }

        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding Vehicle...';
        submitBtn.disabled = true;

        // Use XMLHttpRequest for accurate progress tracking
        const xhr = new XMLHttpRequest();

        // Track upload progress
        let startTime = Date.now();
        let lastLoaded = 0;
        let lastTime = startTime;

        xhr.upload.addEventListener('progress', (e) => {
          if (e.lengthComputable) {
            const percentComplete = (e.loaded / e.total) * 100;
            const currentTime = Date.now();
            const timeDiff = (currentTime - lastTime) / 1000; // seconds
            const bytesDiff = e.loaded - lastLoaded;

            // Update progress bar
            updateAdminProgressBar(percentComplete, e.loaded, e.total, bytesDiff, timeDiff);

            lastLoaded = e.loaded;
            lastTime = currentTime;
          }
        });

        // Handle upload completion
        xhr.upload.addEventListener('load', () => {
          // Upload complete, now waiting for server processing
          if (hasLargeFiles) {
            updateAdminProgressBar(100, totalSize, totalSize, 0, 0);
            document.getElementById('adminUploadProgressText').textContent = 'Processing...';
            document.getElementById('adminUploadProgressDetails').innerHTML =
              '<span style="color: #e60012; font-weight: 600;">Server is processing your upload...</span>';
          }
        });

        // Handle errors
        xhr.upload.addEventListener('error', () => {
          Swal.fire({
            icon: 'error',
            title: 'Upload Failed',
            text: 'Please check your connection and try again.'
          });
          submitBtn.innerHTML = originalText;
          submitBtn.disabled = false;
          hideAdminProgressBar();
        });

        xhr.upload.addEventListener('abort', () => {
          Swal.fire({
            icon: 'error',
            title: 'Upload Cancelled',
            text: 'The upload was cancelled.'
          });
          submitBtn.innerHTML = originalText;
          submitBtn.disabled = false;
          hideAdminProgressBar();
        });

        // Handle response
        xhr.addEventListener('load', () => {
          try {
            const result = JSON.parse(xhr.responseText);

            if (xhr.status === 200 && result.success) {
              // Complete the progress bar and hide it after a short delay
              if (hasLargeFiles) {
                document.getElementById('adminUploadProgressText').textContent = '✓ Complete';
                document.getElementById('adminUploadProgressDetails').innerHTML =
                  '<span style="color: #28a745; font-weight: 600;">Upload successful!</span>';

                // Hide progress bar after 1.5 seconds
                setTimeout(() => {
                  document.getElementById('adminUploadProgressContainer').style.display = 'none';
                  resetAdminProgressBar();
                }, 1500);
              }

              SwalSuccess.fire({
                title: 'Success!',
                text: result.message || 'Vehicle added successfully!'
              });

              adminVehicleForm.reset();
              document.getElementById('adminColorModelList').innerHTML = '';
              closeAdminVehicleModal();

              setTimeout(() => {
                location.reload();
              }, 2000);
            } else {
              Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: result.message || 'Failed to add vehicle'
              });
              hideAdminProgressBar();
            }
          } catch (error) {
            console.error('Error parsing response:', error);
            Swal.fire({
              icon: 'error',
              title: 'Error!',
              text: 'An error occurred while processing the response'
            });
            hideAdminProgressBar();
          } finally {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
          }
        });

        // Handle network errors
        xhr.addEventListener('error', () => {
          Swal.fire({
            icon: 'error',
            title: 'Network Error',
            text: 'Please check your connection.'
          });
          submitBtn.innerHTML = originalText;
          submitBtn.disabled = false;
          hideAdminProgressBar();
        });

        // Send the request
        xhr.open('POST', '../../api/vehicles.php', true);
        xhr.send(formData);
      });
    }

    // Helper functions for admin progress bar
    function calculateAdminFormDataSize(formData) {
      let totalSize = 0;
      const fileInputs = adminVehicleForm.querySelectorAll('input[type="file"]');
      fileInputs.forEach(input => {
        if (input.files) {
          for (let i = 0; i < input.files.length; i++) {
            totalSize += input.files[i].size;
          }
        }
      });
      return totalSize;
    }

    function updateAdminProgressBar(percentage, loaded, total, bytesDiff, timeDiff) {
      const progressBar = document.getElementById('adminUploadProgressBar');
      const progressText = document.getElementById('adminUploadProgressText');
      const speedElement = document.getElementById('adminUploadSpeed');
      const etaElement = document.getElementById('adminUploadETA');
      const sizeElement = document.getElementById('adminUploadSize');

      progressBar.style.width = percentage.toFixed(1) + '%';
      progressText.textContent = percentage.toFixed(1) + '%';

      let speed = 0;
      if (timeDiff > 0) {
        speed = (bytesDiff / timeDiff) / (1024 * 1024);
      }

      let eta = 'calculating...';
      if (speed > 0 && loaded < total) {
        const remainingBytes = total - loaded;
        const remainingSeconds = remainingBytes / (speed * 1024 * 1024);
        eta = formatAdminTime(remainingSeconds);
      } else if (loaded >= total) {
        eta = 'complete';
      }

      speedElement.textContent = `Speed: ${speed.toFixed(2)} MB/s`;
      etaElement.textContent = `Time remaining: ${eta}`;
      sizeElement.textContent = `${formatAdminBytes(loaded)} / ${formatAdminBytes(total)}`;
    }

    function resetAdminProgressBar() {
      const progressBar = document.getElementById('adminUploadProgressBar');
      const progressText = document.getElementById('adminUploadProgressText');
      const speedElement = document.getElementById('adminUploadSpeed');
      const etaElement = document.getElementById('adminUploadETA');
      const sizeElement = document.getElementById('adminUploadSize');

      progressBar.style.width = '0%';
      progressText.textContent = '0%';
      speedElement.textContent = 'Speed: 0 MB/s';
      etaElement.textContent = 'Time remaining: calculating...';
      sizeElement.textContent = '0 MB / 0 MB';
    }

    function hideAdminProgressBar() {
      setTimeout(() => {
        document.getElementById('adminUploadProgressContainer').style.display = 'none';
        resetAdminProgressBar();
      }, 3000);
    }

    function formatAdminBytes(bytes) {
      if (bytes === 0) return '0 Bytes';
      const k = 1024;
      const sizes = ['Bytes', 'KB', 'MB', 'GB'];
      const i = Math.floor(Math.log(bytes) / Math.log(k));
      return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    function formatAdminTime(seconds) {
      if (seconds < 1) return 'less than a second';
      if (seconds < 60) return Math.round(seconds) + ' seconds';
      const minutes = Math.floor(seconds / 60);
      const remainingSeconds = Math.round(seconds % 60);
      if (minutes < 60) {
        return `${minutes}m ${remainingSeconds}s`;
      }
      const hours = Math.floor(minutes / 60);
      const remainingMinutes = minutes % 60;
      return `${hours}h ${remainingMinutes}m`;
    }

    // Close buttons
    closeAccountReview.addEventListener('click', function() {
      accountReviewInterface.style.display = 'none';
    });

    closeTransactionUpdate.addEventListener('click', function() {
      transactionUpdateInterface.style.display = 'none';
    });

    // Tab switching functionality
    document.querySelectorAll('.tab-button').forEach(button => {
      button.addEventListener('click', function() {
        const tabName = this.getAttribute('data-tab');
        const container = this.closest('.interface-container');

        // Remove active class from all tabs and content
        container.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
        container.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));

        // Add active class to clicked tab and corresponding content
        this.classList.add('active');
        container.querySelector('#' + tabName).classList.add('active');
      });
    });

    // Global variables to store current customer data
    let currentCustomerId = null;
    let currentAccountId = null;

    // Review account function with robust error handling
    window.reviewAccount = function(cusID, accountId, isComplete) {
      // Store current customer data
      currentCustomerId = cusID;
      currentAccountId = accountId;

      // Show loading in modal
      document.getElementById('customerReviewContent').innerHTML = '<div style="text-align: center; padding: 20px;"><p>Loading customer information...</p></div>';
      document.getElementById('customerReviewModal').style.display = 'flex';

      // Use either cusID or accountId for fetching details
      const queryParam = cusID ? `cusID=${cusID}` : `accountId=${accountId}`;

      // Fetch customer details via AJAX
      fetch(`../../pages/main/admin_actions.php?action=get_customer_details&${queryParam}`)
        .then(response => {
          if (!response.ok) {
            throw new Error(`Server error: ${response.status}`);
          }
          return response.text();
        })
        .then(text => {
          // Handle empty response
          if (!text || !text.trim()) {
            throw new Error('Empty response received from server');
          }

          // Clean the response text by removing any non-JSON content
          let cleanText = text.trim();
          
          // Find the JSON part if there's extra output
          const jsonStart = cleanText.indexOf('{');
          const jsonEnd = cleanText.lastIndexOf('}') + 1;
          
          if (jsonStart === -1 || jsonEnd <= jsonStart) {
            throw new Error('Invalid response format - no JSON data found');
          }

          cleanText = cleanText.substring(jsonStart, jsonEnd);

          // Parse JSON response
          let data;
          try {
            data = JSON.parse(cleanText);
          } catch (parseError) {
            throw new Error('Unable to parse server response');
          }

          // Check for success and valid data
          if (!data) {
            throw new Error('No data received from server');
          }

          if (data.success === false) {
            throw new Error(data.message || 'Failed to fetch customer details');
          }

          if (!data.customer) {
            throw new Error('Customer data is missing from response');
          }

          displayCustomerDetails(data.customer);
        })
        .catch(error => {
          const errorMessage = error.message || 'An unexpected error occurred';
          document.getElementById('customerReviewContent').innerHTML =
            `<div style="text-align: center; padding: 20px; color: #dc3545;">
              <p><strong>Error Loading Customer Information</strong></p>
              <p style="font-size: 0.9em; margin-top: 10px;">${errorMessage}</p>
              <button type="button" class="btn btn-secondary" style="margin-top: 15px;" onclick="closeCustomerReviewModal()">Close</button>
            </div>`;
        });
    };

    function displayCustomerDetails(customer) {
      const content = `
        <div class="customer-details-card">
          <h4>Account Information</h4>
          <div class="customer-details-row">
            <span class="customer-details-label">Username:</span>
            <span class="customer-details-value">${customer.Username || customer.username || 'Not provided'}</span>
          </div>
          <div class="customer-details-row">
            <span class="customer-details-label">Email:</span>
            <span class="customer-details-value">${customer.Email || customer.email || 'Not provided'}</span>
          </div>
          <div class="customer-details-row">
            <span class="customer-details-label">Account Status:</span>
            <span class="customer-details-value">
              <span class="status-badge status-${(customer.Status || customer.AccountStatus || 'pending').toLowerCase()}">${(customer.Status || customer.AccountStatus || 'pending').toUpperCase()}</span>
            </span>
          </div>
          <div class="customer-details-row">
            <span class="customer-details-label">Registration Date:</span>
            <span class="customer-details-value">${customer.CreatedAt || customer.created_at ? new Date(customer.CreatedAt || customer.created_at).toLocaleDateString() : 'Not available'}</span>
          </div>
          
          <h4 style="margin-top: 20px;">Personal Information</h4>
          <div class="customer-details-row">
            <span class="customer-details-label">Full Name:</span>
            <span class="customer-details-value">${[
              customer.firstname || customer.FirstName,
              customer.middlename,
              customer.lastname || customer.LastName,
              customer.suffix
            ].filter(Boolean).join(' ') || 'Not provided'}</span>
          </div>
          <div class="customer-details-row">
            <span class="customer-details-label">Birthday:</span>
            <span class="customer-details-value">${customer.birthday ? new Date(customer.birthday).toLocaleDateString() : 'Not provided'}</span>
          </div>
          <div class="customer-details-row">
            <span class="customer-details-label">Age:</span>
            <span class="customer-details-value">${customer.age || 'Not provided'}</span>
          </div>
          <div class="customer-details-row">
            <span class="customer-details-label">Gender:</span>
            <span class="customer-details-value">${customer.gender || 'Not provided'}</span>
          </div>
          <div class="customer-details-row">
            <span class="customer-details-label">Civil Status:</span>
            <span class="customer-details-value">${customer.civil_status || 'Not provided'}</span>
          </div>
          <div class="customer-details-row">
            <span class="customer-details-label">Nationality:</span>
            <span class="customer-details-value">${customer.nationality || 'Not provided'}</span>
          </div>
          <div class="customer-details-row">
            <span class="customer-details-label">Mobile Number:</span>
            <span class="customer-details-value">${customer.mobile_number || 'Not provided'}</span>
          </div>
          
          <h4 style="margin-top: 20px;">Employment Information</h4>
          <div class="customer-details-row">
            <span class="customer-details-label">Employment Status:</span>
            <span class="customer-details-value">${customer.employment_status || 'Not provided'}</span>
          </div>
          <div class="customer-details-row">
            <span class="customer-details-label">Company Name:</span>
            <span class="customer-details-value">${customer.company_name || 'Not provided'}</span>
          </div>
          <div class="customer-details-row">
            <span class="customer-details-label">Position:</span>
            <span class="customer-details-value">${customer.position || 'Not provided'}</span>
          </div>
          <div class="customer-details-row">
            <span class="customer-details-label">Monthly Income:</span>
            <span class="customer-details-value">${customer.monthly_income ? '₱' + parseFloat(customer.monthly_income).toLocaleString() : 'Not provided'}</span>
          </div>
          
          <h4 style="margin-top: 20px;">Identification</h4>
          <div class="customer-details-row">
            <span class="customer-details-label">Valid ID Type:</span>
            <span class="customer-details-value">${customer.valid_id_type || 'Not provided'}</span>
          </div>
          <div class="customer-details-row">
            <span class="customer-details-label">Valid ID Number:</span>
            <span class="customer-details-value">${customer.valid_id_number || 'Not provided'}</span>
          </div>
          <div class="customer-details-row">
            <span class="customer-details-label">Customer Type:</span>
            <span class="customer-details-value">${customer.customer_type || 'Not provided'}</span>
          </div>
        </div>
      `;
      
      document.getElementById('customerReviewContent').innerHTML = content;
    }

    // Function to close modal
    window.closeCustomerReviewModal = function() {
      document.getElementById('customerReviewModal').style.display = 'none';
      currentCustomerId = null;
      currentAccountId = null;
    };

    // Function to show reject modal - updated to accept parameters
    window.showRejectModal = function(cusID, accountId) {
      currentCustomerId = cusID;
      currentAccountId = accountId;
      document.getElementById('rejectCustomerId').value = cusID || '';
      document.getElementById('rejectAccountId').value = accountId || '';
      document.getElementById('rejectCustomerModal').style.display = 'flex';
    };

    // Function to close reject modal
    window.closeRejectModal = function() {
      document.getElementById('rejectCustomerModal').style.display = 'none';
      document.getElementById('rejectCustomerForm').reset();
      currentCustomerId = null;
      currentAccountId = null;
    };

    // Function to submit rejection
    window.submitRejection = function() {
      const form = document.getElementById('rejectCustomerForm');
      const formData = new FormData(form);
      
      // Validate required fields
      const reason = document.getElementById('rejectionReason').value;
      if (!reason) {
        SwalError.fire({
          title: 'Validation Error',
          text: 'Please select a reason for rejection.'
        });
        return;
      }

      // Show loading
      const submitBtn = event.target;
      const originalText = submitBtn.textContent;
      submitBtn.textContent = 'Processing...';
      submitBtn.disabled = true;

      // Submit rejection
      fetch('../../pages/main/admin_actions.php?action=reject_customer', {
        method: 'POST',
        body: formData
      })
      .then(response => {
        if (!response.ok) {
          throw new Error(`Server error: ${response.status}`);
        }
        return response.text();
      })
      .then(text => {
        // Handle empty response
        if (!text || !text.trim()) {
          throw new Error('Empty response received from server');
        }

        // Parse JSON with error handling
        let data;
        try {
          data = JSON.parse(text);
        } catch (parseError) {
          throw new Error('Unable to parse server response');
        }

        // Validate response
        if (!data) {
          throw new Error('No data received from server');
        }

        if (data.success === false) {
          throw new Error(data.message || 'Failed to reject customer');
        }

        SwalSuccess.fire({
          title: 'Success!',
          text: 'Customer has been rejected successfully!'
        });
        closeRejectModal();
        closeCustomerReviewModal();
        setTimeout(() => location.reload(), 2000);
      })
      .catch(error => {
        const errorMessage = error.message || 'An unexpected error occurred';
        SwalError.fire({
          title: 'Error!',
          text: errorMessage
        });
      })
      .finally(() => {
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
      });
    };

    // Function to quick approve a customer
    window.quickApproveAccount = function(cusID) {
      if (!cusID) {
        SwalError.fire({
          title: 'Error!',
          text: 'Invalid customer ID'
        });
        return;
      }

      if (!confirm('Are you sure you want to approve this customer account?')) {
        return;
      }

      // Create form data
      const formData = new FormData();
      formData.append('customer_id', cusID);
      formData.append('approval_comments', 'Quick approval from admin dashboard');

      // Show loading
      const approveBtn = event.target;
      const originalText = approveBtn.textContent;
      approveBtn.textContent = 'Processing...';
      approveBtn.disabled = true;

      // Submit approval
      fetch('../../pages/main/admin_actions.php?action=approve_customer', {
        method: 'POST',
        body: formData
      })
      .then(response => {
        if (!response.ok) {
          throw new Error(`Server error: ${response.status}`);
        }
        return response.text();
      })
      .then(text => {
        // Handle empty response
        if (!text || !text.trim()) {
          throw new Error('Empty response received from server');
        }

        // Parse JSON with error handling
        let data;
        try {
          data = JSON.parse(text);
        } catch (parseError) {
          throw new Error('Unable to parse server response');
        }

        // Validate response
        if (!data) {
          throw new Error('No data received from server');
        }

        if (data.success === false) {
          throw new Error(data.message || 'Failed to approve customer');
        }

        SwalSuccess.fire({
          title: 'Success!',
          text: 'Customer approved successfully!'
        });
        setTimeout(() => location.reload(), 2000);
      })
      .catch(error => {
        const errorMessage = error.message || 'An unexpected error occurred';
        SwalError.fire({
          title: 'Error!',
          text: errorMessage
        });
      })
      .finally(() => {
        approveBtn.textContent = originalText;
        approveBtn.disabled = false;
      });
    };

    // Function to view customer details (for approved accounts)
    window.viewCustomerDetails = function(cusID) {
      if (!cusID) {
        SwalError.fire({
          title: 'Error!',
          text: 'Invalid customer ID'
        });
        return;
      }

      // Show loading in modal
      document.getElementById('customerReviewContent').innerHTML = '<div style="text-align: center; padding: 20px;"><p>Loading customer information...</p></div>';
      document.getElementById('customerReviewModal').style.display = 'flex';

      // Fetch customer details
      fetch(`../../pages/main/admin_actions.php?action=get_customer_details&cusID=${cusID}`)
        .then(response => {
          if (!response.ok) {
            throw new Error(`Server error: ${response.status}`);
          }
          return response.text();
        })
        .then(text => {
          // Handle empty response
          if (!text || !text.trim()) {
            throw new Error('Empty response received from server');
          }

          // Parse JSON with proper error handling
          let data;
          try {
            // Clean any non-JSON content
            let cleanText = text.trim();
            const jsonStart = cleanText.indexOf('{');
            const jsonEnd = cleanText.lastIndexOf('}') + 1;
            
            if (jsonStart !== -1 && jsonEnd > jsonStart) {
              cleanText = cleanText.substring(jsonStart, jsonEnd);
            }
            
            data = JSON.parse(cleanText);
          } catch (parseError) {
            throw new Error('Unable to parse server response');
          }

          // Validate response data
          if (!data) {
            throw new Error('No data received from server');
          }

          if (data.success === false) {
            throw new Error(data.message || 'Failed to fetch customer details');
          }

          if (!data.customer) {
            throw new Error('Customer data is missing from response');
          }

          displayCustomerDetails(data.customer);
        })
        .catch(error => {
          const errorMessage = error.message || 'An unexpected error occurred';
          document.getElementById('customerReviewContent').innerHTML =
            `<div style="text-align: center; padding: 20px; color: #dc3545;">
              <p><strong>Error Loading Customer Information</strong></p>
              <p style="font-size: 0.9em; margin-top: 10px;">${errorMessage}</p>
              <button type="button" class="btn btn-secondary" style="margin-top: 15px;" onclick="closeCustomerReviewModal()">Close</button>
            </div>`;
          SwalError.fire({
            title: 'Error!',
            text: errorMessage
          });
        });
    };

    // Function to view account details (for accounts without customer_information)
    window.viewAccountDetails = function(accountId) {
      if (!accountId) {
        SwalError.fire({
          title: 'Error!',
          text: 'Invalid account ID'
        });
        return;
      }

      // Show loading in modal
      document.getElementById('customerReviewContent').innerHTML = '<div style="text-align: center; padding: 20px;"><p>Loading account information...</p></div>';
      document.getElementById('customerReviewModal').style.display = 'flex';

      // Fetch account details
      fetch(`../../pages/main/admin_actions.php?action=get_customer_details&accountId=${accountId}`)
        .then(response => {
          if (!response.ok) {
            throw new Error(`Server error: ${response.status}`);
          }
          return response.text();
        })
        .then(text => {
          // Handle empty response
          if (!text || !text.trim()) {
            throw new Error('Empty response received from server');
          }

          // Parse JSON with proper error handling
          let data;
          try {
            // Clean any non-JSON content
            let cleanText = text.trim();
            const jsonStart = cleanText.indexOf('{');
            const jsonEnd = cleanText.lastIndexOf('}') + 1;
            
            if (jsonStart !== -1 && jsonEnd > jsonStart) {
              cleanText = cleanText.substring(jsonStart, jsonEnd);
            }
            
            data = JSON.parse(cleanText);
          } catch (parseError) {
            throw new Error('Unable to parse server response');
          }

          // Validate response data
          if (!data) {
            throw new Error('No data received from server');
          }

          if (data.success === false) {
            throw new Error(data.message || 'Failed to fetch account details');
          }

          if (!data.customer) {
            throw new Error('Account data is missing from response');
          }

          displayCustomerDetails(data.customer);
        })
        .catch(error => {
          const errorMessage = error.message || 'An unexpected error occurred';
          document.getElementById('customerReviewContent').innerHTML =
            `<div style="text-align: center; padding: 20px; color: #dc3545;">
              <p><strong>Error Loading Account Information</strong></p>
              <p style="font-size: 0.9em; margin-top: 10px;">${errorMessage}</p>
              <button type="button" class="btn btn-secondary" style="margin-top: 15px;" onclick="closeCustomerReviewModal()">Close</button>
            </div>`;
          SwalError.fire({
            title: 'Error!',
            text: errorMessage
          });
        });
    };

    // Function to re-approve a rejected customer
    window.reapproveCustomer = function(cusID) {
      if (!cusID) {
        SwalError.fire({
          title: 'Error!',
          text: 'Invalid customer ID'
        });
        return;
      }

      if (!confirm('Are you sure you want to re-approve this customer account?')) {
        return;
      }

      // Create form data
      const formData = new FormData();
      formData.append('customer_id', cusID);
      formData.append('approval_comments', 'Re-approved after previous rejection');

      // Show loading
      const reapproveBtn = event.target;
      const originalText = reapproveBtn.innerHTML;
      reapproveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
      reapproveBtn.disabled = true;

      // Submit re-approval
      fetch('../../pages/main/admin_actions.php?action=approve_customer', {
        method: 'POST',
        body: formData
      })
      .then(response => {
        if (!response.ok) {
          throw new Error(`Server error: ${response.status}`);
        }
        return response.text();
      })
      .then(text => {
        // Handle empty response
        if (!text || !text.trim()) {
          throw new Error('Empty response received from server');
        }

        // Parse JSON with error handling
        let data;
        try {
          data = JSON.parse(text);
        } catch (parseError) {
          throw new Error('Unable to parse server response');
        }

        // Validate response
        if (!data) {
          throw new Error('No data received from server');
        }

        if (data.success === false) {
          throw new Error(data.message || 'Failed to re-approve customer');
        }

        SwalSuccess.fire({
          title: 'Success!',
          text: 'Customer re-approved successfully!'
        });
        setTimeout(() => location.reload(), 2000);
      })
      .catch(error => {
        const errorMessage = error.message || 'An unexpected error occurred';
        SwalError.fire({
          title: 'Error!',
          text: errorMessage
        });
      })
      .finally(() => {
        reapproveBtn.innerHTML = originalText;
        reapproveBtn.disabled = false;
      });
    };


    // Function to re-approve an account (for accounts without customer_information)
    window.reapproveAccount = function(accountId) {
      if (!accountId) {
        SwalError.fire({
          title: 'Error!',
          text: 'Invalid account ID'
        });
        return;
      }

      if (!confirm('Are you sure you want to re-approve this account?')) {
        return;
      }

      // Create form data
      const formData = new FormData();
      formData.append('account_id', accountId);
      formData.append('approval_comments', 'Re-approved account after previous rejection');

      // Show loading
      const reapproveBtn = event.target;
      const originalText = reapproveBtn.innerHTML;
      reapproveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
      reapproveBtn.disabled = true;

      // Submit re-approval
      fetch('../../pages/main/admin_actions.php?action=approve_customer', {
        method: 'POST',
        body: formData
      })
      .then(response => {
        if (!response.ok) {
          throw new Error(`Server error: ${response.status}`);
        }
        return response.text();
      })
      .then(text => {
        // Handle empty response
        if (!text || !text.trim()) {
          throw new Error('Empty response received from server');
        }

        // Parse JSON with error handling
        let data;
        try {
          data = JSON.parse(text);
        } catch (parseError) {
          throw new Error('Unable to parse server response');
        }

        // Validate response
        if (!data) {
          throw new Error('No data received from server');
        }

        if (data.success === false) {
          throw new Error(data.message || 'Failed to re-approve account');
        }

        SwalSuccess.fire({
          title: 'Success!',
          text: 'Account re-approved successfully!'
        });
        setTimeout(() => location.reload(), 2000);
      })
      .catch(error => {
        const errorMessage = error.message || 'An unexpected error occurred';
        SwalError.fire({
          title: 'Error!',
          text: errorMessage
        });
      })
      .finally(() => {
        reapproveBtn.innerHTML = originalText;
        reapproveBtn.disabled = false;
      });
    };

  });

</script>

<style>
  /* Custom SweetAlert2 Toast Styles - Mitsubishi Brand Colors */
  .colored-toast.swal2-icon-success {
    background: linear-gradient(135deg, #dc143c, #b91c3c) !important;
    color: white !important;
  }

  .colored-toast.swal2-icon-error {
    background: linear-gradient(135deg, #8b0000, #a52a2a) !important;
    color: white !important;
  }

  .colored-toast.swal2-icon-warning {
    background: linear-gradient(135deg, #ffd700, #ffb347) !important;
    color: #8b0000 !important;
  }

  .colored-toast.swal2-icon-info {
    background: linear-gradient(135deg, #2c3e50, #34495e) !important;
    color: white !important;
  }

  .colored-toast.swal2-icon-question {
    background: linear-gradient(135deg, #34495e, #2c3e50) !important;
    color: white !important;
  }

  .colored-toast .swal2-title {
    color: white !important;
    font-weight: 600 !important;
  }

  .colored-toast .swal2-close {
    color: white !important;
  }

  .colored-toast .swal2-html-container {
    color: white !important;
  }

  .colored-toast .swal2-icon {
    border-color: white !important;
    color: white !important;
  }

  .colored-toast .swal2-success-ring,
  .colored-toast .swal2-success-line-tip,
  .colored-toast .swal2-success-line-long {
    background-color: white !important;
  }

  .colored-toast .swal2-error-x {
    color: white !important;
  }

  .colored-toast .swal2-warning {
    border-color: #8b0000 !important;
    color: #8b0000 !important;
  }

  .colored-toast .swal2-info {
    border-color: white !important;
    color: white !important;
  }

  .colored-toast .swal2-question {
    border-color: white !important;
    color: white !important;
  }

  .colored-toast .swal2-timer-progress-bar {
    background: rgba(255, 255, 255, 0.6) !important;
  }

  /* Ensure toast container has proper styling */
  .swal2-container .colored-toast {
    backdrop-filter: blur(10px);
    box-shadow: 0 8px 32px rgba(220, 20, 60, 0.3) !important;
    border: 1px solid rgba(255, 255, 255, 0.2);
  }

  .swal2-container .colored-toast.swal2-icon-error {
    box-shadow: 0 8px 32px rgba(139, 0, 0, 0.3) !important;
  }

  .swal2-container .colored-toast.swal2-icon-warning {
    box-shadow: 0 8px 32px rgba(255, 215, 0, 0.3) !important;
  }

  /* Payment Approval Button Styles */
  .btn-success {
    background-color: #28a745;
    border-color: #28a745;
    color: white;
  }

  .btn-success:hover {
    background-color: #218838;
    border-color: #1e7e34;
  }

  .btn-danger {
    background-color: #dc3545;
    border-color: #dc3545;
    color: white;
  }

  .btn-danger:hover {
    background-color: #c82333;
    border-color: #bd2130;
  }

  .btn-info {
    background-color: #17a2b8;
    border-color: #17a2b8;
    color: white;
  }

  .btn-info:hover {
    background-color: #138496;
    border-color: #117a8b;
  }

  .table-actions {
    white-space: nowrap;
  }

  .table-actions .btn {
    margin-right: 5px;
  }

  .text-center {
    text-align: center;
  }
</style>