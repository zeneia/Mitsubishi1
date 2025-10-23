<?php
include_once(dirname(dirname(__DIR__)) . '/includes/init.php');

// Check access: allow Admin and SalesAgent
$role = $_SESSION['user_role'] ?? '';
if (!isset($_SESSION['user_id']) || !in_array($role, ['Admin', 'SalesAgent'])) {
    header("Location: ../../pages/login.php");
    exit();
}
// Current user id for filtering
$currentUserId = $_SESSION['user_id'] ?? null;

// Use the database connection from init.php
$pdo = $GLOBALS['pdo'] ?? null;

// Check if database connection exists
if (!$pdo) {
  die("Database connection not available. Please check your database configuration.");
}

// Function to get inquiries with account information
function getInquiriesWithAccounts($pdo) {
  try {
    $query = "SELECT 
        i.Id,
        i.AccountId,
        i.FullName,
        i.Email,
        i.PhoneNumber,
        i.VehicleModel,
        i.VehicleVariant,
        i.VehicleYear,
        i.VehicleColor,
        i.TradeInVehicleDetails,
        i.FinancingRequired,
        i.Comments,
        i.InquiryDate,
        a.Username,
        a.FirstName,
        a.LastName,
        a.Status as AccountStatus,
        agent_acc.FirstName as AgentFirstName,
        agent_acc.LastName as AgentLastName
    FROM inquiries i
    LEFT JOIN accounts a ON i.AccountId = a.Id
    LEFT JOIN customer_information ci ON i.AccountId = ci.account_id
    LEFT JOIN accounts agent_acc ON ci.agent_id = agent_acc.Id";
    // Apply SalesAgent filter
    if (($_SESSION['user_role'] ?? '') === 'SalesAgent') {
      $query .= " WHERE (ci.agent_id = :agent_id OR i.CreatedBy = :agent_id)";
    }
    $query .= " ORDER BY i.InquiryDate DESC";
    
    $stmt = $pdo->prepare($query);
    if (($_SESSION['user_role'] ?? '') === 'SalesAgent') {
      $stmt->bindValue(':agent_id', $_SESSION['user_id'], PDO::PARAM_INT);
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  } catch (PDOException $e) {
    error_log("Error fetching inquiries: " . $e->getMessage());
    return [];
  }
}

// Function to get inquiry statistics
function getInquiryStats($pdo) {
  $stats = [];
  
  try {
    $role = $_SESSION['user_role'] ?? '';
    $isAgent = ($role === 'SalesAgent');
    $filterJoin = $isAgent ? " LEFT JOIN customer_information ci ON i.AccountId = ci.account_id" : "";
    $filterWhere = $isAgent ? " WHERE (ci.agent_id = :agent_id OR i.CreatedBy = :agent_id)" : "";

    // Total inquiries
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM inquiries i" . $filterJoin . $filterWhere);
    if ($isAgent) { $stmt->bindValue(':agent_id', $_SESSION['user_id'], PDO::PARAM_INT); }
    $stmt->execute();
    $stats['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Today's inquiries
    $stmt = $pdo->prepare("SELECT COUNT(*) as today FROM inquiries i" . $filterJoin . ($filterWhere ? $filterWhere . " AND DATE(i.InquiryDate) = CURDATE()" : " WHERE DATE(i.InquiryDate) = CURDATE()"));
    if ($isAgent) { $stmt->bindValue(':agent_id', $_SESSION['user_id'], PDO::PARAM_INT); }
    $stmt->execute();
    $stats['today'] = $stmt->fetch(PDO::FETCH_ASSOC)['today'];
    
    // This week's inquiries
    $stmt = $pdo->prepare("SELECT COUNT(*) as this_week FROM inquiries i" . $filterJoin . ($filterWhere ? $filterWhere . " AND YEARWEEK(i.InquiryDate) = YEARWEEK(NOW())" : " WHERE YEARWEEK(i.InquiryDate) = YEARWEEK(NOW())"));
    if ($isAgent) { $stmt->bindValue(':agent_id', $_SESSION['user_id'], PDO::PARAM_INT); }
    $stmt->execute();
    $stats['this_week'] = $stmt->fetch(PDO::FETCH_ASSOC)['this_week'];
    
    // This month's inquiries
    $stmt = $pdo->prepare("SELECT COUNT(*) as this_month FROM inquiries i" . $filterJoin . ($filterWhere ? $filterWhere . " AND YEAR(i.InquiryDate) = YEAR(NOW()) AND MONTH(i.InquiryDate) = MONTH(NOW())" : " WHERE YEAR(i.InquiryDate) = YEAR(NOW()) AND MONTH(i.InquiryDate) = MONTH(NOW())"));
    if ($isAgent) { $stmt->bindValue(':agent_id', $_SESSION['user_id'], PDO::PARAM_INT); }
    $stmt->execute();
    $stats['this_month'] = $stmt->fetch(PDO::FETCH_ASSOC)['this_month'];
    
    // Inquiries requiring financing
    $stmt = $pdo->prepare("SELECT COUNT(*) as financing FROM inquiries i" . $filterJoin . ($filterWhere ? $filterWhere . " AND i.FinancingRequired IS NOT NULL AND i.FinancingRequired != ''" : " WHERE i.FinancingRequired IS NOT NULL AND i.FinancingRequired != ''"));
    if ($isAgent) { $stmt->bindValue(':agent_id', $_SESSION['user_id'], PDO::PARAM_INT); }
    $stmt->execute();
    $stats['financing'] = $stmt->fetch(PDO::FETCH_ASSOC)['financing'];
    
    // Inquiries with trade-in
    $stmt = $pdo->prepare("SELECT COUNT(*) as trade_in FROM inquiries i" . $filterJoin . ($filterWhere ? $filterWhere . " AND i.TradeInVehicleDetails IS NOT NULL AND i.TradeInVehicleDetails != ''" : " WHERE i.TradeInVehicleDetails IS NOT NULL AND i.TradeInVehicleDetails != ''"));
    if ($isAgent) { $stmt->bindValue(':agent_id', $_SESSION['user_id'], PDO::PARAM_INT); }
    $stmt->execute();
    $stats['trade_in'] = $stmt->fetch(PDO::FETCH_ASSOC)['trade_in'];
    
    // Most popular vehicle model
    $stmt = $pdo->prepare("SELECT i.VehicleModel, COUNT(*) as count FROM inquiries i" . $filterJoin . $filterWhere . " GROUP BY i.VehicleModel ORDER BY count DESC LIMIT 1");
    if ($isAgent) { $stmt->bindValue(':agent_id', $_SESSION['user_id'], PDO::PARAM_INT); }
    $stmt->execute();
    $popular = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['popular_model'] = $popular ? $popular['VehicleModel'] : 'N/A';
    
  } catch (PDOException $e) {
    error_log("Error getting inquiry stats: " . $e->getMessage());
    $stats = [
      'total' => 0,
      'today' => 0,
      'this_week' => 0,
      'this_month' => 0,
      'financing' => 0,
      'trade_in' => 0,
      'popular_model' => 'N/A'
    ];
  }
  
  return $stats;
}

// Get data for display
$inquiries = getInquiriesWithAccounts($pdo);
$stats = getInquiryStats($pdo);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Vehicle Inquiries - Mitsubishi</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link href="../../includes/css/common-styles.css" rel="stylesheet">
  <link href="../../includes/css/orders-styles.css" rel="stylesheet">
  <style>
    html, body {
      height: 100%;
      width: 100%;
      margin: 0;
      padding: 0;
    }
    
    body {
      zoom: 85%;
    }

    .inquiry-stats {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }

    .stat-card {
      background: white;
      border-radius: 12px;
      padding: 25px;
      box-shadow: 0 4px 6px rgba(0,0,0,0.05);
      border-left: 4px solid var(--primary-red);
      display: flex;
      align-items: center;
      gap: 20px;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .stat-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(0,0,0,0.1);
    }

    .stat-icon {
      width: 60px;
      height: 60px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      color: white;
    }

    .stat-icon.red { background: linear-gradient(135deg, #d60000, #ff4444); }
    .stat-icon.blue { background: linear-gradient(135deg, #1e40af, #3b82f6); }
    .stat-icon.green { background: linear-gradient(135deg, #059669, #10b981); }
    .stat-icon.orange { background: linear-gradient(135deg, #ea580c, #f97316); }
    .stat-icon.purple { background: linear-gradient(135deg, #7c3aed, #a855f7); }

    .inquiry-actions-enhanced {
      display: flex;
      gap: 8px;
      justify-content: center;
    }

    .inquiry-info {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }

    .inquiry-id {
      font-weight: 600;
      color: var(--primary-red);
      font-size: 0.95rem;
    }

    .inquiry-date {
      color: var(--text-light);
      font-size: 0.85rem;
    }

    .customer-info {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }

    .customer-name {
      font-weight: 600;
      color: var(--text-dark);
    }

    .customer-contact {
      color: var(--text-light);
      font-size: 0.85rem;
    }

    .vehicle-info {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }

    .vehicle-model {
      font-weight: 600;
      color: var(--text-dark);
    }

    .vehicle-details {
      color: var(--text-light);
      font-size: 0.85rem;
    }

    .inquiry-type-badge {
      background: var(--primary-red);
      color: white;
      padding: 4px 12px;
      border-radius: 20px;
      font-weight: 600;
      font-size: 0.75rem;
      text-transform: uppercase;
      text-align: center;
      min-width: 80px;
    }

    .inquiry-type-badge.financing { background: #ea580c; }
    .inquiry-type-badge.trade-in { background: #1e40af; }
    .inquiry-type-badge.general { background: #6b7280; }

    .modal-body {
      max-height: 70vh;
      overflow-y: auto;
    }

    .inquiry-details-section {
      margin-bottom: 25px;
      padding-bottom: 20px;
      border-bottom: 1px solid #eee;
    }

    .inquiry-details-section:last-child {
      border-bottom: none;
      margin-bottom: 0;
    }

    .inquiry-details-section h3 {
      color: var(--primary-red);
      margin-bottom: 15px;
      font-size: 1.1rem;
      font-weight: 600;
    }

    .detail-row {
      display: flex;
      justify-content: space-between;
      margin-bottom: 10px;
    }

    .detail-label {
      font-weight: 600;
      color: var(--text-dark);
      min-width: 120px;
    }

    .detail-value {
      color: var(--text-light);
      flex: 1;
      text-align: right;
    }

    .message-content {
      background: #f8f9fa;
      padding: 15px;
      border-radius: 8px;
      border-left: 4px solid var(--primary-red);
      margin-top: 10px;
      font-style: italic;
      line-height: 1.6;
    }

    .form-section {
      margin-bottom: 20px;
    }

    .form-section h3 {
      color: var(--primary-red);
      margin-bottom: 15px;
      font-size: 1.1rem;
      font-weight: 600;
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
          <i class="fas fa-question-circle icon-gradient"></i>
          Vehicle Inquiries Management
        </h1>
        <?php if ($role === 'SalesAgent'): ?>
        <button class="add-order-btn" id="addNewInquiryBtn" style="padding: 12px 24px; font-size: 1rem;">
          <i class="fas fa-plus-circle"></i> Add New Inquiry
        </button>
        <?php endif; ?>
      </div>

      <!-- Inquiry Statistics -->
      <div class="inquiry-stats">
        <div class="stat-card">
          <div class="stat-icon red">
            <i class="fas fa-question-circle"></i>
          </div>
          <div class="stat-info">
            <h3><?php echo $stats['total']; ?></h3>
            <p>Total Inquiries</p>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon orange">
            <i class="fas fa-calendar-day"></i>
          </div>
          <div class="stat-info">
            <h3><?php echo $stats['today']; ?></h3>
            <p>Today's Inquiries</p>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon blue">
            <i class="fas fa-calendar-week"></i>
          </div>
          <div class="stat-info">
            <h3><?php echo $stats['this_week']; ?></h3>
            <p>This Week</p>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon green">
            <i class="fas fa-credit-card"></i>
          </div>
          <div class="stat-info">
            <h3><?php echo $stats['financing']; ?></h3>
            <p>Need Financing</p>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon purple">
            <i class="fas fa-exchange-alt"></i>
          </div>
          <div class="stat-info">
            <h3><?php echo $stats['trade_in']; ?></h3>
            <p>With Trade-In</p>
          </div>
        </div>
      </div>

      <!-- Filters Section -->
      <div class="filters-section">
        <div class="filter-row">
          <div class="filter-group">
            <label for="inquiry-search">Search Inquiries</label>
            <input type="text" id="inquiry-search" class="filter-input" placeholder="Customer name, email, or vehicle model">
          </div>
          <div class="filter-group">
            <label for="vehicle-model-filter">Vehicle Model</label>
            <select id="vehicle-model-filter" class="filter-select">
              <option value="all">All Models</option>
              <?php
              // Get unique vehicle models from inquiries
              $models = array_unique(array_column($inquiries, 'VehicleModel'));
              foreach ($models as $model) {
                if (!empty($model)) {
                  echo "<option value=\"" . htmlspecialchars($model) . "\">" . htmlspecialchars($model) . "</option>";
                }
              }
              ?>
            </select>
          </div>
          <div class="filter-group">
            <label for="date-range-filter">Date Range</label>
            <select id="date-range-filter" class="filter-select">
              <option value="all">All Time</option>
              <option value="today">Today</option>
              <option value="week">This Week</option>
              <option value="month">This Month</option>
            </select>
          </div>
          <div class="filter-group">
            <label for="inquiry-type-filter">Type</label>
            <select id="inquiry-type-filter" class="filter-select">
              <option value="all">All Types</option>
              <option value="financing">Need Financing</option>
              <option value="trade-in">With Trade-In</option>
              <option value="general">General Inquiry</option>
            </select>
          </div>
          <button class="filter-btn">Apply Filters</button>
        </div>
      </div>

      <!-- Inquiries Table -->
      <div class="client-orders-section">
        <div class="section-header">
          <h2 class="section-title">
            <i class="fas fa-list"></i>
            <span id="sectionTitle">All Vehicle Inquiries</span>
          </h2>
        </div>
        <div class="table-responsive">
          <table class="table">
            <thead>
              <tr>
                <th>Inquiry Details</th>
                <th>Customer Information</th>
                <th>Vehicle Interest</th>
                <th>Type</th>
                <th>Agent Name</th>
                <th>Comments Preview</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="inquiriesTableBody">
              <?php foreach ($inquiries as $inquiry): ?>
              <tr>
                <td>
                  <div class="inquiry-info">
                    <span class="inquiry-id">INQ-<?php echo str_pad($inquiry['Id'], 5, '0', STR_PAD_LEFT); ?></span>
                    <span class="inquiry-date"><?php echo date('M d, Y g:i A', strtotime($inquiry['InquiryDate'])); ?></span>
                  </div>
                </td>
                <td>
                  <div class="customer-info">
                    <span class="customer-name"><?php echo htmlspecialchars($inquiry['FullName']); ?></span>
                    <span class="customer-contact"><?php echo htmlspecialchars($inquiry['Email']); ?></span>
                    <?php if ($inquiry['PhoneNumber']): ?>
                      <span class="customer-contact"><?php echo htmlspecialchars($inquiry['PhoneNumber']); ?></span>
                    <?php endif; ?>
                  </div>
                </td>
                <td>
                  <div class="vehicle-info">
                    <span class="vehicle-model"><?php echo htmlspecialchars($inquiry['VehicleModel']); ?></span>
                    <?php if ($inquiry['VehicleVariant']): ?>
                      <span class="vehicle-details"><?php echo htmlspecialchars($inquiry['VehicleVariant']); ?></span>
                    <?php endif; ?>
                    <span class="vehicle-details"><?php echo htmlspecialchars($inquiry['VehicleYear']) . ' - ' . htmlspecialchars($inquiry['VehicleColor']); ?></span>
                  </div>
                </td>
                <td>
                  <?php
                  $type = 'general';
                  if (!empty($inquiry['FinancingRequired'])) {
                    $type = 'financing';
                  } elseif (!empty($inquiry['TradeInVehicleDetails'])) {
                    $type = 'trade-in';
                  }
                  ?>
                  <span class="inquiry-type-badge <?php echo $type; ?>">
                    <?php 
                    switch($type) {
                      case 'financing': echo 'Financing'; break;
                      case 'trade-in': echo 'Trade-In'; break;
                      default: echo 'General'; break;
                    }
                    ?>
                  </span>
                </td>
                <td>
                  <div class="agent-info">
                    <?php 
                    if (!empty($inquiry['AgentFirstName']) || !empty($inquiry['AgentLastName'])) {
                      echo htmlspecialchars(trim($inquiry['AgentFirstName'] . ' ' . $inquiry['AgentLastName']));
                    } else {
                      echo '<span style="color: #999; font-style: italic;">Unassigned</span>';
                    }
                    ?>
                  </div>
                </td>
                <td>
                  <div class="message-preview" title="<?php echo htmlspecialchars($inquiry['Comments']); ?>">
                    <?php echo htmlspecialchars(substr($inquiry['Comments'], 0, 50)) . (strlen($inquiry['Comments']) > 50 ? '...' : ''); ?>
                  </div>
                </td>
                <td>
                  <div class="inquiry-actions-enhanced">
                    <button class="btn-small btn-view" title="View Details" onclick="viewInquiryDetails(<?php echo $inquiry['Id']; ?>)">
                      <i class="fas fa-eye"></i>
                    </button>
                    <?php if ($role === 'SalesAgent'): ?>
                    <button class="btn-small btn-edit" title="Respond" onclick="respondToInquiry(<?php echo $inquiry['Id']; ?>)">
                      <i class="fas fa-reply"></i>
                    </button>
                    <?php endif; ?>
                    <button class="btn-small btn-danger" title="Delete" onclick="deleteInquiry(<?php echo $inquiry['Id']; ?>)">
                      <i class="fas fa-trash"></i>
                    </button>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Inquiry Details Modal -->
  <div class="modal-overlay" id="inquiryDetailsModal">
    <div class="modal">
      <div class="modal-header">
        <h3 id="inquiryDetailsTitle">Inquiry Details</h3>
        <button class="modal-close" onclick="closeInquiryDetailsModal()">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div class="modal-body" id="inquiryDetailsContent">
        <!-- Content will be loaded dynamically -->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeInquiryDetailsModal()">Close</button>
        <?php if ($role === 'SalesAgent'): ?>
        <button type="button" class="btn btn-primary" onclick="respondToCurrentInquiry()">Respond to Inquiry</button>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Response Modal -->
  <div class="modal-overlay" id="responseModal">
    <div class="modal">
      <div class="modal-header">
        <h3 id="responseModalTitle">Respond to Inquiry</h3>
        <button class="modal-close" onclick="closeResponseModal()">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <form id="responseForm">
        <div class="modal-body">
          <input type="hidden" id="inquiryId" name="inquiry_id">
          
          <div class="form-section">
            <h3>Inquiry Information</h3>
            <div id="inquirySummary">
              <!-- Inquiry summary will be loaded here -->
            </div>
          </div>

          <div class="form-section">
            <h3>Response Details</h3>
            <div class="form-row">
              <div class="form-group">
                <label for="response_type">Response Type *</label>
                <select id="response_type" name="response_type" class="form-control" required>
                  <option value="">Select Response Type</option>
                  <option value="email">Email Response</option>
                  <option value="phone">Phone Call Scheduled</option>
                  <option value="appointment">Appointment Scheduled</option>
                  <option value="information">Information Provided</option>
                </select>
              </div>
            </div>
            <div class="form-group">
              <label for="response_message">Response Message *</label>
              <textarea id="response_message" name="response_message" class="form-control" rows="5" placeholder="Enter your response to the customer..." required></textarea>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label for="follow_up_date">Follow-up Date</label>
                <input type="date" id="follow_up_date" name="follow_up_date" class="form-control">
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" onclick="closeResponseModal()">Cancel</button>
          <button type="submit" class="btn btn-primary">
            <span id="responseSubmitBtnText">Send Response</span>
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Add New Inquiry Modal -->
  <div class="modal-overlay" id="addInquiryModal">
    <div class="modal" style="max-width: 800px;">
      <div class="modal-header">
        <h3>Add New Inquiry</h3>
        <button class="modal-close" onclick="closeAddInquiryModal()">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <form id="addInquiryForm">
        <div class="modal-body">
          <div class="form-section">
            <h3>Customer Information</h3>
            <div class="form-row">
              <div class="form-group">
                <label for="add_full_name">Full Name *</label>
                <input type="text" id="add_full_name" name="full_name" class="form-control" required>
              </div>
              <div class="form-group">
                <label for="add_email">Email Address *</label>
                <input type="email" id="add_email" name="email" class="form-control" required>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label for="add_phone">Phone Number *</label>
                <input type="tel" id="add_phone" name="phone_number" class="form-control" required>
              </div>
              <div class="form-group">
                <label for="add_account_id">Link to Account (Optional)</label>
                <select id="add_account_id" name="account_id" class="form-control">
                  <option value="">Select existing account (optional)</option>
                  <!-- Options will be loaded dynamically -->
                </select>
              </div>
            </div>
          </div>

          <div class="form-section">
            <h3>Vehicle Information</h3>
            <div class="form-row">
              <div class="form-group">
                <label for="add_vehicle_model">Vehicle Model *</label>
                <select id="add_vehicle_model" name="vehicle_model" class="form-control" required>
                  <option value="">Select Vehicle Model</option>
                  <!-- Options will be loaded dynamically -->
                </select>
              </div>
              <div class="form-group">
                <label for="add_vehicle_variant">Vehicle Variant</label>
                <input type="text" id="add_vehicle_variant" name="vehicle_variant" class="form-control">
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label for="add_vehicle_year">Vehicle Year</label>
                <input type="number" id="add_vehicle_year" name="vehicle_year" class="form-control" min="2020" max="2030">
              </div>
              <div class="form-group">
                <label for="add_vehicle_color">Preferred Color</label>
                <input type="text" id="add_vehicle_color" name="vehicle_color" class="form-control">
              </div>
            </div>
          </div>

          <div class="form-section">
            <h3>Additional Information</h3>
            <div class="form-row">
              <div class="form-group">
                <label for="add_financing_required">Financing Required</label>
                <select id="add_financing_required" name="financing_required" class="form-control">
                  <option value="">Select Option</option>
                  <option value="Yes">Yes</option>
                  <option value="No">No</option>
                  <option value="Maybe">Maybe</option>
                </select>
              </div>
              <div class="form-group">
                <label for="add_trade_in">Trade-in Vehicle Details</label>
                <input type="text" id="add_trade_in" name="trade_in_vehicle_details" class="form-control" placeholder="Make, Model, Year">
              </div>
            </div>
            <div class="form-group">
              <label for="add_comments">Comments/Special Requests</label>
              <textarea id="add_comments" name="comments" class="form-control" rows="4" placeholder="Any specific requirements or questions..."></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" onclick="closeAddInquiryModal()">Cancel</button>
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add Inquiry
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Add SweetAlert CDN -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="../../includes/js/common-scripts.js"></script>

  <!-- Delete Confirmation Modal -->
  <div class="modal-overlay" id="deleteConfirmModal">
    <div class="modal" style="max-width: 500px;">
      <div class="modal-header">
        <h3>Delete Inquiry</h3>
        <button class="modal-close" onclick="closeDeleteModal()">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div class="modal-body">
        <div style="text-align: center; padding: 20px;">
          <div style="font-size: 48px; color: #dc2626; margin-bottom: 20px;">
            <i class="fas fa-trash-alt"></i>
          </div>
          <h3 style="color: #dc2626; margin-bottom: 15px;">Confirm Deletion</h3>
          <p style="color: var(--text-light); margin-bottom: 20px;">
            Are you sure you want to delete this inquiry? This action cannot be undone.
          </p>
          <div id="deleteInquiryInfo" style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: left;">
            <!-- Inquiry info will be populated here -->
          </div>
          <input type="hidden" id="deleteInquiryId" value="">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
        <button type="button" class="btn btn-danger" onclick="confirmDeleteInquiry()">
          <i class="fas fa-trash"></i> Delete Inquiry
        </button>
      </div>
    </div>
  </div>

  <script>
    // Store inquiries data for JavaScript access
    const inquiriesData = <?php echo json_encode($inquiries); ?>;
    let currentInquiryId = null;

    // Modal functions
    function viewInquiryDetails(inquiryId) {
      console.log('viewInquiryDetails called with ID:', inquiryId);
      const inquiry = inquiriesData.find(i => i.Id == inquiryId);
      if (!inquiry) {
        console.error('Inquiry not found:', inquiryId);
        return;
      }

      currentInquiryId = inquiryId;
      console.log('Set currentInquiryId to:', currentInquiryId);
      
      const content = `
        <div class="inquiry-details-section">
          <h3>Customer Information</h3>
          <div class="detail-row">
            <span class="detail-label">Name:</span>
            <span class="detail-value">${inquiry.FullName}</span>
          </div>
          <div class="detail-row">
            <span class="detail-label">Email:</span>
            <span class="detail-value">${inquiry.Email}</span>
          </div>
          <div class="detail-row">
            <span class="detail-label">Phone:</span>
            <span class="detail-value">${inquiry.PhoneNumber || 'Not provided'}</span>
          </div>
          ${inquiry.Username ? `
            <div class="detail-row">
              <span class="detail-label">Account:</span>
              <span class="detail-value">${inquiry.Username}</span>
            </div>
          ` : ''}
        </div>

        <div class="inquiry-details-section">
          <h3>Vehicle Interest</h3>
          <div class="detail-row">
            <span class="detail-label">Model:</span>
            <span class="detail-value">${inquiry.VehicleModel}</span>
          </div>
          <div class="detail-row">
            <span class="detail-label">Variant:</span>
            <span class="detail-value">${inquiry.VehicleVariant || 'Not specified'}</span>
          </div>
          <div class="detail-row">
            <span class="detail-label">Year:</span>
            <span class="detail-value">${inquiry.VehicleYear}</span>
          </div>
          <div class="detail-row">
            <span class="detail-label">Color:</span>
            <span class="detail-value">${inquiry.VehicleColor}</span>
          </div>
        </div>

        ${inquiry.FinancingRequired ? `
          <div class="inquiry-details-section">
            <h3>Financing Requirements</h3>
            <div class="message-content">
              ${inquiry.FinancingRequired}
            </div>
          </div>
        ` : ''}

        ${inquiry.TradeInVehicleDetails ? `
          <div class="inquiry-details-section">
            <h3>Trade-In Vehicle</h3>
            <div class="message-content">
              ${inquiry.TradeInVehicleDetails}
            </div>
          </div>
        ` : ''}

        <div class="inquiry-details-section">
          <h3>Inquiry Details</h3>
          <div class="detail-row">
            <span class="detail-label">Date:</span>
            <span class="detail-value">${new Date(inquiry.InquiryDate).toLocaleString()}</span>
          </div>
          ${inquiry.Comments ? `
            <div style="margin-top: 15px;">
              <strong>Comments:</strong>
              <div class="message-content">
                ${inquiry.Comments}
              </div>
            </div>
          ` : ''}
        </div>
      `;

      document.getElementById('inquiryDetailsContent').innerHTML = content;
      document.getElementById('inquiryDetailsTitle').textContent = `Inquiry INQ-${String(inquiry.Id).padStart(5, '0')}`;
      document.getElementById('inquiryDetailsModal').classList.add('active');
    }

    function closeInquiryDetailsModal() {
      console.log('closeInquiryDetailsModal called');
      document.getElementById('inquiryDetailsModal').classList.remove('active');
      // Don't reset currentInquiryId here to preserve it for respondToCurrentInquiry
      console.log('Modal closed, currentInquiryId preserved:', currentInquiryId);
    }

    function respondToInquiry(inquiryId) {
      const inquiry = inquiriesData.find(i => i.Id == inquiryId);
      if (!inquiry) return;

      currentInquiryId = inquiryId;
      
      // Populate inquiry summary
      const summarContent = `
        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
          <strong>Customer:</strong> ${inquiry.FullName} (${inquiry.Email})<br>
          <strong>Vehicle:</strong> ${inquiry.VehicleModel} ${inquiry.VehicleVariant || ''} - ${inquiry.VehicleYear}<br>
          <strong>Color:</strong> ${inquiry.VehicleColor}<br>
          ${inquiry.Comments ? `<strong>Comments:</strong> <em>"${inquiry.Comments}"</em>` : ''}
        </div>
      `;

      document.getElementById('inquirySummary').innerHTML = summarContent;
      document.getElementById('inquiryId').value = inquiryId;
      
      document.getElementById('responseModal').classList.add('active');
    }

    function respondToCurrentInquiry() {
      console.log('respondToCurrentInquiry called, currentInquiryId:', currentInquiryId);
      
      if (currentInquiryId) {
        closeInquiryDetailsModal();
        setTimeout(() => {
          respondToInquiry(currentInquiryId);
        }, 100); // Small delay to ensure modal closes properly
      } else {
        console.error('No current inquiry ID set');
        Swal.fire({
          title: 'Error!',
          text: 'No inquiry selected. Please try again.',
          icon: 'error',
          confirmButtonColor: '#d60000'
        });
      }
    }

    function closeResponseModal() {
      console.log('closeResponseModal called');
      document.getElementById('responseModal').classList.remove('active');
      document.getElementById('responseForm').reset();
      currentInquiryId = null; // Reset here after response modal is closed
      console.log('Response modal closed, currentInquiryId reset');
    }

    function deleteInquiry(inquiryId) {
      const inquiry = inquiriesData.find(i => i.Id == inquiryId);
      if (!inquiry) return;

      // Populate delete modal with inquiry information
      const deleteInfo = `
        <div style="margin-bottom: 10px;">
          <strong>Inquiry ID:</strong> INQ-${String(inquiry.Id).padStart(5, '0')}
        </div>
        <div style="margin-bottom: 10px;">
          <strong>Customer:</strong> ${inquiry.FullName}
        </div>
        <div style="margin-bottom: 10px;">
          <strong>Email:</strong> ${inquiry.Email}
        </div>
        <div style="margin-bottom: 10px;">
          <strong>Vehicle:</strong> ${inquiry.VehicleModel} ${inquiry.VehicleVariant || ''}
        </div>
        <div>
          <strong>Date:</strong> ${new Date(inquiry.InquiryDate).toLocaleString()}
        </div>
      `;

      document.getElementById('deleteInquiryInfo').innerHTML = deleteInfo;
      document.getElementById('deleteInquiryId').value = inquiryId;
      document.getElementById('deleteConfirmModal').classList.add('active');
    }

    function closeDeleteModal() {
      document.getElementById('deleteConfirmModal').classList.remove('active');
      document.getElementById('deleteInquiryId').value = '';
    }

    function confirmDeleteInquiry() {
      const inquiryId = document.getElementById('deleteInquiryId').value;
      if (!inquiryId) return;

      // Show loading state
      const deleteBtn = document.querySelector('#deleteConfirmModal .btn-danger');
      const originalText = deleteBtn.innerHTML;
      deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
      deleteBtn.disabled = true;

      // Call the backend to delete the inquiry
      fetch('inquiry_actions.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          action: 'delete',
          inquiry_id: inquiryId
        })
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Close delete modal
          closeDeleteModal();
          
          // Show success message
          Swal.fire({
            title: 'Deleted!',
            text: 'Inquiry has been deleted successfully',
            icon: 'success',
            confirmButtonColor: '#d60000'
          }).then(() => {
            location.reload();
          });
        } else {
          throw new Error(data.message || 'Failed to delete inquiry');
        }
      })
      .catch(error => {
        Swal.fire({
          title: 'Error!',
          text: 'Failed to delete inquiry: ' + error.message,
          icon: 'error',
          confirmButtonColor: '#d60000'
        });
      })
      .finally(() => {
        deleteBtn.innerHTML = originalText;
        deleteBtn.disabled = false;
      });
    }

    // Handle response form submission
    function handleResponseSubmit() {
      const form = document.getElementById('responseForm');
      const formData = new FormData(form);
      
      // Validate required fields
      const responseType = formData.get('response_type');
      const responseMessage = formData.get('response_message');
      
      if (!responseType) {
        Swal.fire({
          title: 'Error!',
          text: 'Please select a response type',
          icon: 'error',
          confirmButtonColor: '#d60000'
        });
        return;
      }
      
      if (!responseMessage || responseMessage.trim().length < 10) {
        Swal.fire({
          title: 'Error!',
          text: 'Please enter a response message (at least 10 characters)',
          icon: 'error',
          confirmButtonColor: '#d60000'
        });
        return;
      }
      
      // Show loading state
      const submitBtn = document.querySelector('#responseForm button[type="submit"]');
      const originalText = submitBtn.innerHTML;
      submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
      submitBtn.disabled = true;
      
      // Send to inquiry_actions.php
      fetch('inquiry_actions.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Close response modal
          closeResponseModal();
          
          // Show success message
          Swal.fire({
            title: 'Success!',
            text: 'Response sent successfully',
            icon: 'success',
            confirmButtonColor: '#d60000'
          }).then(() => {
            location.reload();
          });
        } else {
          throw new Error(data.message || 'Failed to send response');
        }
      })
      .catch(error => {
        Swal.fire({
          title: 'Error!',
          text: 'Failed to send response: ' + error.message,
          icon: 'error',
          confirmButtonColor: '#d60000'
        });
      })
      .finally(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
      });
    }

    // Add New Inquiry Modal Functions
    function openAddInquiryModal() {
      // Load vehicle models and accounts
      loadVehicleModels();
      loadAccounts();
      
      // Clear form
      document.getElementById('addInquiryForm').reset();
      
      // Show modal
      document.getElementById('addInquiryModal').classList.add('active');
    }

    function closeAddInquiryModal() {
      document.getElementById('addInquiryModal').classList.remove('active');
      document.getElementById('addInquiryForm').reset();
    }

    function loadVehicleModels() {
      fetch('../../api/vehicles.php')
        .then(response => response.json())
        .then(data => {
          const select = document.getElementById('add_vehicle_model');
          select.innerHTML = '<option value="">Select Vehicle Model</option>';
          
          if (data.success && data.data) {
            // Get unique models
            const uniqueModels = [...new Set(data.data.map(v => v.model_name))];
            uniqueModels.forEach(model => {
              const option = document.createElement('option');
              option.value = model;
              option.textContent = model;
              select.appendChild(option);
            });
          }
        })
        .catch(error => {
          console.error('Error loading vehicle models:', error);
        });
    }

    function loadAccounts() {
      fetch('inquiry_actions.php?action=get_accounts')
        .then(response => response.json())
        .then(data => {
          const select = document.getElementById('add_account_id');
          select.innerHTML = '<option value="">Select existing account (optional)</option>';
          
          if (data.success && data.accounts) {
            data.accounts.forEach(account => {
              const option = document.createElement('option');
              option.value = account.Id;
              option.textContent = `${account.FirstName} ${account.LastName} (${account.Username})`;
              select.appendChild(option);
            });
          }
        })
        .catch(error => {
          console.error('Error loading accounts:', error);
        });
    }

    function handleAddInquirySubmit() {
      const form = document.getElementById('addInquiryForm');
      const formData = new FormData(form);
      
      // Add action parameter
      formData.append('action', 'add_inquiry');
      
      // Validate required fields
      const fullName = formData.get('full_name');
      const email = formData.get('email');
      const phoneNumber = formData.get('phone_number');
      const vehicleModel = formData.get('vehicle_model');
      
      if (!fullName || !email || !phoneNumber || !vehicleModel) {
        Swal.fire({
          title: 'Error!',
          text: 'Please fill in all required fields',
          icon: 'error',
          confirmButtonColor: '#d60000'
        });
        return;
      }
      
      // Show loading state
      const submitBtn = form.querySelector('button[type="submit"]');
      const originalText = submitBtn.innerHTML;
      submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
      submitBtn.disabled = true;
      
      // Send to inquiry_actions.php
      fetch('inquiry_actions.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Close modal
          closeAddInquiryModal();
          
          // Show success message
          Swal.fire({
            title: 'Success!',
            text: 'New inquiry has been added successfully',
            icon: 'success',
            confirmButtonColor: '#d60000'
          }).then(() => {
            location.reload();
          });
        } else {
          throw new Error(data.message || 'Failed to add inquiry');
        }
      })
      .catch(error => {
        Swal.fire({
          title: 'Error!',
          text: 'Failed to add inquiry: ' + error.message,
          icon: 'error',
          confirmButtonColor: '#d60000'
        });
      })
      .finally(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
      });
    }

    // Initialize page
    document.addEventListener('DOMContentLoaded', function() {
      // Add New Inquiry button event listener
      document.getElementById('addNewInquiryBtn').addEventListener('click', function() {
        openAddInquiryModal();
      });

      // Add New Inquiry form submission
      document.getElementById('addInquiryForm').addEventListener('submit', function(e) {
        e.preventDefault();
        handleAddInquirySubmit();
      });

      // Response form submission
      document.getElementById('responseForm').addEventListener('submit', function(e) {
        e.preventDefault();
        handleResponseSubmit();
      });

      // Filter functionality
      let filterTimeout;
      function setupFilter(elementId) {
        document.getElementById(elementId).addEventListener('change', function() {
          clearTimeout(filterTimeout);
          filterTimeout = setTimeout(applyFilters, 300);
        });
      }

      setupFilter('vehicle-model-filter');
      setupFilter('date-range-filter');
      setupFilter('inquiry-type-filter');

      // Search functionality
      document.getElementById('inquiry-search').addEventListener('input', function() {
        clearTimeout(filterTimeout);
        filterTimeout = setTimeout(applyFilters, 300);
      });

      function applyFilters() {
        const searchTerm = document.getElementById('inquiry-search').value.toLowerCase();
        const modelFilter = document.getElementById('vehicle-model-filter').value;
        const dateFilter = document.getElementById('date-range-filter').value;
        const typeFilter = document.getElementById('inquiry-type-filter').value;

        const rows = document.querySelectorAll('#inquiriesTableBody tr');
        let visibleCount = 0;

        rows.forEach(row => {
          const customerName = row.querySelector('.customer-name').textContent.toLowerCase();
          const customerEmail = row.querySelector('.customer-contact').textContent.toLowerCase();
          const vehicleModel = row.querySelector('.vehicle-model').textContent.toLowerCase();
          const inquiryType = row.querySelector('.inquiry-type-badge').textContent.toLowerCase();
          const inquiryDate = new Date(row.cells[0].querySelector('.inquiry-date').textContent);

          const matchesSearch = !searchTerm || 
            customerName.includes(searchTerm) || 
            customerEmail.includes(searchTerm) || 
            vehicleModel.includes(searchTerm);

          const matchesModel = modelFilter === 'all' || 
            vehicleModel.includes(modelFilter.toLowerCase());

          const matchesType = typeFilter === 'all' || 
            inquiryType.includes(typeFilter);

          let matchesDate = true;
          if (dateFilter !== 'all') {
            const today = new Date();
            const todayStart = new Date(today.setHours(0, 0, 0, 0));
            
            switch (dateFilter) {
              case 'today':
                matchesDate = inquiryDate >= todayStart;
                break;
              case 'week':
                const weekStart = new Date(today.setDate(today.getDate() - today.getDay()));
                matchesDate = inquiryDate >= weekStart;
                break;
              case 'month':
                const monthStart = new Date(today.getFullYear(), today.getMonth(), 1);
                matchesDate = inquiryDate >= monthStart;
                break;
            }
          }

          if (matchesSearch && matchesModel && matchesType && matchesDate) {
            row.style.display = '';
            visibleCount++;
          } else {
            row.style.display = 'none';
          }
        });

        // Update section title
        document.getElementById('sectionTitle').textContent = 
          visibleCount === inquiriesData.length ? 
          'All Vehicle Inquiries' : 
          `Filtered Inquiries (${visibleCount} of ${inquiriesData.length})`;
      }

      // Apply filters button
      document.querySelector('.filter-btn').addEventListener('click', applyFilters);
    });
  </script>
</body>
</html>