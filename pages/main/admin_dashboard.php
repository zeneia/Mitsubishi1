<?php
// Admin Dashboard Cards
require_once __DIR__ . '/../../includes/database/db_conn.php';

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
                  <button class="btn btn-small btn-outline" onclick="quickApproveAccount('<?php echo $customer['cusID'] ?? ''; ?>', '<?php echo $customer['account_id']; ?>')">Approve</button>
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
          <th>Amount</th>
          <th>Payment Method</th>
          <th>Reference</th>
          <th>Date Submitted</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody id="pendingPaymentsTable">
        <tr>
          <td colspan="7" class="text-center">Loading pending payments...</td>
        </tr>
      </tbody>
    </table>
  </div>

  <div class="tab-content" id="transaction-verified">
    <p>Verified payments content will be displayed here.</p>
  </div>
</div>

<!-- Admin Car Listing Interface -->
<div class="interface-container" id="carListingInterface">
  <div class="interface-header">
    <h2 class="interface-title">
      <i class="fas fa-car-side"></i>
      Admin Car Listing
    </h2>
    <button class="interface-close" id="closeCarListing">&times;</button>
  </div>

  <div class="tab-navigation">
    <button class="tab-button active" data-tab="car-add">Add New Vehicle</button>
  </div>

  <div class="tab-content active" id="car-add">
    <h3 class="section-heading">Add New Vehicle to Inventory</h3>
    <form id="addVehicleForm" enctype="multipart/form-data">
      <fieldset>
        <legend>Vehicle Information</legend>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Model Name <span style="color: red;">*</span></label>
            <input type="text" class="form-input" name="model_name" placeholder="e.g., Montero Sport" required>
          </div>
          <div class="form-group">
            <label class="form-label">Variant <span style="color: red;">*</span></label>
            <input type="text" class="form-input" name="variant" placeholder="e.g., GLS Premium" required>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Year Model <span style="color: red;">*</span></label>
            <select class="form-select" name="year_model" required
            onkeydown="if(event.key === 'e' || event.key === 'E') event.preventDefault();" />
              <option value="">Select year</option>
              <?php
              $currentYear = date('Y');
              for ($year = $currentYear + 1; $year >= $currentYear - 10; $year--) {
                echo "<option value=\"$year\">$year</option>";
              }
              ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Category <span style="color: red;">*</span></label>
            <select class="form-select" name="category" required>
              <option value="">Select category</option>
              <option value="sedan">Sedan</option>
              <option value="suv">SUV</option>
              <option value="pickup">Pickup Truck</option>
              <option value="hatchback">Hatchback</option>
              <option value="mpv">MPV</option>
              <option value="hybrid">Hybrid/Electric</option>
            </select>
          </div>
        </div>
      </fieldset>

      <fieldset>
        <legend>Technical Specifications</legend>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Engine Type <span style="color: red;">*</span></label>
            <input type="text" class="form-input" name="engine_type" placeholder="e.g., 2.4L MIVEC Diesel" required>
          </div>
          <div class="form-group">
            <label class="form-label">Transmission <span style="color: red;">*</span></label>
            <select class="form-select" name="transmission" required>
              <option value="">Select transmission</option>
              <option value="manual">Manual</option>
              <option value="automatic">Automatic</option>
              <option value="cvt">CVT</option>
              <option value="dct">DCT</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Fuel Type <span style="color: red;">*</span></label>
            <select class="form-select" name="fuel_type" required>
              <option value="">Select fuel type</option>
              <option value="gasoline">Gasoline</option>
              <option value="diesel">Diesel</option>
              <option value="hybrid">Hybrid</option>
              <option value="electric">Electric</option>
              <option value="lpg">LPG</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Seating Capacity <span style="color: red;">*</span></label>
            <select class="form-select" name="seating_capacity" required
            onkeydown="if(event.key === 'e' || event.key === 'E') event.preventDefault();">
              <option value="">Select capacity</option>
              <option value="2">2 Seater</option>
              <option value="4">4 Seater</option>
              <option value="5">5 Seater</option>
              <option value="6">6 Seater</option>
              <option value="7">7 Seater</option>
              <option value="8">8 Seater</option>
              <option value="9">9+ Seater</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Key Features</label>
          <textarea class="form-textarea" name="key_features" rows="3" placeholder="List key features and specifications..."></textarea>
        </div>
      </fieldset>

      <fieldset>
        <legend>Pricing Information</legend>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Base Price (₱) <span style="color: red;">*</span></label>
            <input type="number" class="form-input" name="base_price" placeholder="Enter base price" step="0.01" min="0" required
            onkeydown="if(event.key === 'e' || event.key === 'E') event.preventDefault();" >
          </div>
          <div class="form-group">
            <label class="form-label">Promotional Price (₱)</label>
            <input type="number" class="form-input" name="promotional_price" placeholder="Enter promotional price" step="0.01" min="0"
            onkeydown="if(event.key === 'e' || event.key === 'E') event.preventDefault();">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Minimum Down Payment (%)</label>
            <input type="number" class="form-input" name="min_downpayment_percentage" placeholder="e.g., 20" min="0" max="100">
          </div>
          <div class="form-group">
            <label class="form-label">Financing Terms Available</label>
            <input type="text" class="form-input" name="financing_terms" placeholder="e.g., 12, 24, 36, 48, 60 months">
          </div>
        </div>
      </fieldset>

      <fieldset>
        <legend>Color Options</legend>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Available Colors</label>
            <input type="text" class="form-input" name="color_options" placeholder="e.g., White Pearl, Black Mica, Silver Metallic">
          </div>
          <div class="form-group">
            <label class="form-label">Most Popular Color</label>
            <input type="text" class="form-input" name="popular_color" placeholder="Enter most popular color">
          </div>
        </div>
      </fieldset>

      <fieldset>
        <legend>Vehicle Images</legend>
        <div class="form-group">
          <label class="form-label">Main Display Image</label>
          <input type="file" class="form-input" name="main_image" accept="image/*">
          <small style="color: var(--text-light);">Upload main vehicle image (recommended: 1200x800px)</small>
        </div>
        <div class="form-group">
          <label class="form-label">Additional Images</label>
          <input type="file" class="form-input" name="additional_images[]" accept="image/*" multiple>
          <small style="color: var(--text-light);">Upload additional images (interior, exterior, engine, etc.)</small>
        </div>
        <div class="form-group">
          <label class="form-label">360° View Images</label>
          <input type="file" class="form-input" name="view_360_images[]" accept="image/*" multiple>
          <small style="color: var(--text-light);">Upload 360° view images for interactive display</small>
        </div>
      </fieldset>

      <fieldset>
        <legend>Inventory & Availability</legend>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Stock Quantity <span style="color: red;">*</span></label>
            <input type="number" class="form-input" name="stock_quantity" placeholder="Enter available stock" min="0" required
            onkeydown="if(event.key === 'e' || event.key === 'E') event.preventDefault();">
          </div>
          <div class="form-group">
            <label class="form-label">Minimum Stock Alert</label>
            <input type="number" class="form-input" name="min_stock_alert" placeholder="Alert when stock reaches" min="0"
            onkeydown="if(event.key === 'e' || event.key === 'E') event.preventDefault();" />>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Availability Status <span style="color: red;">*</span></label>
            <select class="form-select" name="availability_status" required>
              <option value="available">Available</option>
              <option value="pre-order">Pre-Order Only</option>
              <option value="out-of-stock">Out of Stock</option>
              <option value="discontinued">Discontinued</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Expected Delivery Time</label>
            <input type="text" class="form-input" name="expected_delivery_time" placeholder="e.g., 2-4 weeks">
          </div>
        </div>
      </fieldset>
      <div class="action-area">
        <button type="button" class="btn btn-outline" id="saveDraftBtn">Save as Draft</button>
        <button type="submit" class="btn btn-primary">Add Vehicle to Inventory</button>
        <button type="button" class="btn btn-secondary" id="clearFormBtn">Clear Form</button>
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

.modal-container {
  background: white;
  border-radius: 8px;
  width: 90%;
  max-width: 800px;
  max-height: 95%;
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
}

.modal-footer {
  padding: 20px;
  border-top: 1px solid #dee2e6;
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  background-color: #f8f9fa;
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
            '<tr><td colspan="7" class="text-center">Error loading payments</td></tr>';
        }
      })
      .catch(error => {
        console.error('Error:', error);
        document.getElementById('pendingPaymentsTable').innerHTML = 
          '<tr><td colspan="7" class="text-center">Error loading payments</td></tr>';
      });
  }

  function displayPendingPayments(payments) {
    const tbody = document.getElementById('pendingPaymentsTable');

    if (payments.length === 0) {
      tbody.innerHTML = '<tr><td colspan="7" class="text-center">No pending payments</td></tr>';
      return;
    }

    tbody.innerHTML = payments.map(payment => {
      // Role-based button visibility: Only Sales Agents can approve/reject
      // Handle both "SalesAgent" (database format) and "Sales Agent" (display format)
      const isSalesAgent = window.userRole === 'SalesAgent' || window.userRole === 'Sales Agent';

      return `
        <tr>
          <td>PAY-${payment.id}</td>
          <td>${payment.customer_name}</td>
          <td>₱${parseFloat(payment.amount_paid).toLocaleString()}</td>
          <td>${payment.payment_method}</td>
          <td>${payment.reference_number || 'N/A'}</td>
          <td>${new Date(payment.payment_date).toLocaleDateString()}</td>
          <td class="table-actions">
            ${isSalesAgent ? `
              <button class="btn btn-small btn-success" onclick="approvePayment(${payment.id})">Approve</button>
              <button class="btn btn-small btn-danger" onclick="rejectPayment(${payment.id})">Reject</button>
            ` : ''}
            <button class="btn btn-small btn-info" onclick="viewPaymentDetails(${payment.id})">Details</button>
          </td>
        </tr>
      `;
    }).join('');
  }

  function updatePendingStats(payments) {
    const count = payments.length;
    const totalAmount = payments.reduce((sum, payment) => sum + parseFloat(payment.amount_paid), 0);
    
    document.getElementById('pendingCount').textContent = count;
    document.getElementById('pendingAmount').textContent = '₱' + totalAmount.toLocaleString();
  }

  window.approvePayment = function(paymentId) {
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
  };

  window.rejectPayment = function(paymentId) {
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
  };

  window.viewPaymentDetails = function(paymentId) {
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
  };

  // Load pending payments when the transaction update interface is opened
  document.addEventListener('DOMContentLoaded', function() {
    const transactionUpdateBtn = document.querySelector('[data-interface="transactionUpdateInterface"]');
    if (transactionUpdateBtn) {
      transactionUpdateBtn.addEventListener('click', function() {
        setTimeout(() => {
          loadPendingPayments();
        }, 100);
      });
    }
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
    const carListingInterface = document.getElementById('carListingInterface');

    const closeAccountReview = document.getElementById('closeAccountReview');
    const closeTransactionUpdate = document.getElementById('closeTransactionUpdate');
    const closeCarListing = document.getElementById('closeCarListing');

    // Hide all interfaces
    function hideAllInterfaces() {
      accountReviewInterface.style.display = 'none';
      transactionUpdateInterface.style.display = 'none';
      carListingInterface.style.display = 'none';
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
      hideAllInterfaces();
      carListingInterface.style.display = 'block';
      // If "Add New Vehicle" is the only tab, it's already active by HTML.
      // No specific JS needed here to activate it unless there were multiple tabs.
    });

    // Close buttons
    closeAccountReview.addEventListener('click', function() {
      accountReviewInterface.style.display = 'none';
    });

    closeTransactionUpdate.addEventListener('click', function() {
      transactionUpdateInterface.style.display = 'none';
    });

    closeCarListing.addEventListener('click', function() {
      carListingInterface.style.display = 'none';
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
      fetch(`admin_actions.php?action=get_customer_details&${queryParam}`)
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
      fetch('admin_actions.php?action=reject_customer', {
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
    window.quickApproveAccount = function(cusID, accountId) {
      // Ensure we have at least one ID
      if (!cusID && !accountId) {
        SwalError.fire({
          title: 'Error!',
          text: 'Invalid customer or account ID'
        });
        return;
      }

      if (!confirm('Are you sure you want to approve this customer account?')) {
        return;
      }

      // Create form data
      const formData = new FormData();
      if (cusID) {
        formData.append('customer_id', cusID);
      }
      if (accountId) {
        formData.append('account_id', accountId);
      }
      formData.append('approval_comments', 'Quick approval from admin dashboard');

      // Show loading
      const approveBtn = event.target;
      const originalText = approveBtn.textContent;
      approveBtn.textContent = 'Processing...';
      approveBtn.disabled = true;

      // Submit approval
      fetch('admin_actions.php?action=approve_customer', {
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
      fetch(`admin_actions.php?action=get_customer_details&cusID=${cusID}`)
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
      fetch(`admin_actions.php?action=get_customer_details&accountId=${accountId}`)
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
      fetch('admin_actions.php?action=approve_customer', {
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
      fetch('admin_actions.php?action=approve_customer', {
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