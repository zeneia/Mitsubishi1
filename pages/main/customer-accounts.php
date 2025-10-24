<?php
include_once(dirname(dirname(__DIR__)) . '/includes/init.php');

// Check if user is Sales Agent
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'SalesAgent') {
    header("Location: ../../pages/login.php");
    exit();
}

// Get sales agent ID
$sales_agent_id = $_SESSION['user_id'];

// Fetch customer statistics
try {
    // Total customers count
    $stmt = $connect->prepare("
        SELECT COUNT(DISTINCT ci.cusID) as total_customers
        FROM customer_information ci
        INNER JOIN accounts a ON ci.account_id = a.Id
        WHERE a.Role = 'Customer' AND ci.agent_id = :sales_agent_id
    ");
    $stmt->bindParam(':sales_agent_id', $sales_agent_id, PDO::PARAM_INT);
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_customers = $stats['total_customers'] ?? 0;

    // Approved customers
    $stmt = $connect->prepare("
        SELECT COUNT(DISTINCT ci.cusID) as approved_customers
        FROM customer_information ci
        INNER JOIN accounts a ON ci.account_id = a.Id
        WHERE a.Role = 'Customer' AND ci.Status = 'Approved' AND ci.agent_id = :sales_agent_id
    ");
    $stmt->bindParam(':sales_agent_id', $sales_agent_id, PDO::PARAM_INT);
    $stmt->execute();
    $approved_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $approved_customers = $approved_stats['approved_customers'] ?? 0;

    // Pending customers
    $stmt = $connect->prepare("
        SELECT COUNT(DISTINCT ci.cusID) as pending_customers
        FROM customer_information ci
        INNER JOIN accounts a ON ci.account_id = a.Id
        WHERE a.Role = 'Customer' AND ci.Status = 'Pending' AND ci.agent_id = :sales_agent_id
    ");
    $stmt->bindParam(':sales_agent_id', $sales_agent_id, PDO::PARAM_INT);
    $stmt->execute();
    $pending_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $pending_customers = $pending_stats['pending_customers'] ?? 0;

    // Fetch all customers with their information
    $stmt = $connect->prepare("
        SELECT 
            ci.*,
            a.Username,
            a.Email,
            a.Status as AccountStatus,
            a.LastLoginAt,
            CONCAT(ci.firstname, ' ', ci.lastname) as full_name
        FROM customer_information ci
        INNER JOIN accounts a ON ci.account_id = a.Id
        WHERE a.Role = 'Customer' AND ci.agent_id = :sales_agent_id
        ORDER BY ci.created_at DESC
    ");
    $stmt->bindParam(':sales_agent_id', $sales_agent_id, PDO::PARAM_INT);
    $stmt->execute();
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error fetching customer data: " . $e->getMessage());
    $customers = [];
    $total_customers = 0;
    $approved_customers = 0;
    $pending_customers = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Customer Accounts - Mitsubishi</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link href="../../includes/css/common-styles.css" rel="stylesheet">
  <style>
    html, body {
      height: 100%;
      width: 100%;
      margin: 0;
      padding: 0;
      overflow: hidden;
    }
    
    body {
      zoom: 85%;
    }
    .page-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 30px;
    }

    .page-header h1 {
      font-size: 2rem;
      color: var(--text-dark);
      font-weight: 700;
    }

    .add-btn {
      background: linear-gradient(135deg, var(--primary-red), #b91c3c);
      color: white;
      border: none;
      padding: 12px 24px;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      transition: var(--transition);
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .add-btn:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-medium);
    }

    .customer-stats {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }

    .stat-card {
      background: white;
      padding: 25px;
      border-radius: 12px;
      box-shadow: var(--shadow-light);
      text-align: center;
    }

    .stat-number {
      font-size: 2.5rem;
      font-weight: 700;
      color: var(--primary-red);
      margin-bottom: 8px;
    }

    .stat-label {
      color: var(--text-light);
      font-size: 14px;
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .filters-section {
      background: white;
      padding: 20px;
      border-radius: 12px;
      box-shadow: var(--shadow-light);
      margin-bottom: 25px;
    }

    .filter-row {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 15px;
      align-items: end;
    }

    .filter-group {
      display: flex;
      flex-direction: column;
      gap: 5px;
    }

    .filter-group label {
      font-size: 14px;
      font-weight: 600;
      color: var(--text-dark);
    }

    .filter-input, .filter-select {
      padding: 10px 12px;
      border: 1px solid var(--border-light);
      border-radius: 6px;
      font-size: 14px;
    }

    .filter-btn {
      padding: 10px 20px;
      background: var(--accent-blue);
      color: white;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 600;
      height: fit-content;
    }

    .customers-table {
      background: white;
      border-radius: 12px;
      box-shadow: var(--shadow-light);
      overflow: hidden;
      margin-top: 25px;
    }

    .table-header {
      padding: 20px 25px;
      background: var(--primary-light);
      border-bottom: 1px solid var(--border-light);
      display: flex;
      justify-content: between;
      align-items: center;
    }

    .table-header h2 {
      font-size: 1.3rem;
      color: var(--text-dark);
    }

    .table {
      width: 100%;
      border-collapse: collapse;
    }

    .table th,
    .table td {
      padding: 15px 25px;
      text-align: left;
      border-bottom: 1px solid var(--border-light);
    }

    .table th {
      background: var(--primary-light);
      font-weight: 600;
      color: var(--text-dark);
    }

    .table tbody tr:hover {
      background: #f8f9fa;
    }

    .customer-info {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .customer-avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: var(--primary-red);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-weight: bold;
    }

    .customer-details h4 {
      font-size: 14px;
      color: var(--text-dark);
      margin-bottom: 2px;
    }

    .customer-details p {
      font-size: 12px;
      color: var(--text-light);
    }

    .status-badge {
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
    }

    .status-badge.approved {
      background: #d4edda;
      color: #155724;
    }

    .status-badge.pending {
      background: #fff3cd;
      color: #856404;
    }

    .status-badge.rejected {
      background: #f8d7da;
      color: #721c24;
    }

    .action-buttons {
      display: flex;
      gap: 8px;
    }

    .btn-small {
      padding: 6px 12px;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      font-size: 12px;
      transition: var(--transition);
    }

    .btn-view {
      background: var(--success-green);
      color: white;
    }

    .btn-edit {
      background: var(--accent-blue);
      color: white;
    }

    .btn-delete {
      background: var(--primary-red);
      color: white;
    }

    .btn-small:hover {
      transform: translateY(-1px);
    }

    /* Responsive Design */
    @media (max-width: 575px) {
      .page-header {
        flex-direction: column;
        gap: 15px;
        align-items: stretch;
      }

      .customer-stats {
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
      }

      .filter-row {
        grid-template-columns: 1fr;
        gap: 15px;
      }

      .table-responsive {
        overflow-x: auto;
      }

      .table {
        min-width: 700px;
      }

      .table th,
      .table td {
        padding: 10px 15px;
        font-size: 14px;
      }
    }

    @media (min-width: 576px) and (max-width: 767px) {
      .customer-stats {
        grid-template-columns: repeat(2, 1fr);
      }

      .filter-row {
        grid-template-columns: repeat(2, 1fr);
      }

      .table th,
      .table td {
        padding: 12px 20px;
      }
    }

    @media (min-width: 768px) and (max-width: 991px) {
      .customer-stats {
        grid-template-columns: repeat(4, 1fr);
      }

      .filter-row {
        grid-template-columns: repeat(3, 1fr);
      }
    }

    @media (min-width: 992px) and (max-width: 1199px) {
      .filter-row {
        grid-template-columns: repeat(4, 1fr);
      }
    }

    .page-header {
      margin-bottom: 30px;
      padding-bottom: 20px;
      border-bottom: 2px solid var(--border-light);
    }

    .page-title {
      font-size: 28px;
      font-weight: 700;
      color: var(--primary-dark);
      display: flex;
      align-items: center;
      gap: 15px;
    }

    /* Modal Styles from inventory.php */
    .modal-overlay {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: rgba(0, 0, 0, 0.5);
      display: none;
      justify-content: center;
      align-items: center;
      z-index: 1000;
      padding: 10px;
    }

    .modal-overlay.active {
      display: flex;
    }

    .modal {
      background: white;
      border-radius: 12px;
      width: 95%;
      max-width: 1300px; /* Increased from 700px */
      max-height: 95vh; /* Changed back to 95vh for better responsiveness */
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
      animation: slideIn 0.3s ease-out;
      display: flex;
      flex-direction: column;
    }

    .modal-header {
      padding: 20px 25px;
      border-bottom: 1px solid var(--border-light);
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-shrink: 0;
    }

    .modal-header h3 {
      margin: 0;
      font-size: 1.5rem;
      color: var(--text-dark);
    }

    .modal-close {
      background: none;
      border: none;
      font-size: 1.5rem;
      cursor: pointer;
      color: var(--text-light);
      transition: var(--transition);
    }

    .modal-close:hover {
      color: var(--primary-red);
    }

    .modal-body {
      max-height: calc(95vh - 140px); /* Adjusted to account for header and footer */
      overflow-y: auto;
      flex: 1;
      padding: 25px;
    }

    .modal-footer {
      padding: 15px 25px;
      border-top: 1px solid var(--border-light);
      display: flex;
      justify-content: flex-end;
      gap: 10px;
      flex-shrink: 0;
      background: white;
      border-radius: 0 0 12px 12px;
    }

    /* Button Styles */
    .btn {
      padding: 10px 20px;
      border: none;
      border-radius: 6px;
      font-weight: 600;
      cursor: pointer;
      transition: var(--transition);
    }

    .btn-primary {
      background: var(--primary-red);
      color: white;
    }

    .btn-secondary {
      background: #6c757d;
      color: white;
    }

    .btn:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-light);
    }

    @keyframes slideIn {
      from {
        opacity: 0;
        transform: translateY(-30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    /* Additional styles for the form modal */
    .form-group {
      margin-bottom: 20px;
    }

    .form-group label {
      display: block;
      font-weight: 600;
      margin-bottom: 8px;
      color: var(--text-dark);
      font-size: 14px;
    }

    .form-control {
      width: 100%;
      padding: 10px 12px;
      border: 1px solid var(--border-light);
      border-radius: 6px;
      font-size: 14px;
      transition: var(--transition);
    }

    .form-control:focus {
      outline: none;
      border-color: var(--primary-red);
      box-shadow: 0 0 0 3px rgba(214, 0, 0, 0.1);
    }

    .form-row {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 20px;
    }

    .form-row.three-cols {
      grid-template-columns: repeat(3, 1fr);
    }

    .form-section {
      margin-bottom: 30px;
      padding-bottom: 20px;
      border-bottom: 1px solid var(--border-light);
    }

    .form-section:last-child {
      border-bottom: none;
      margin-bottom: 0;
    }

    .form-section h3 {
      font-size: 1.2rem;
      margin-bottom: 15px;
      color: var(--primary-red);
    }

    .search-account-wrapper {
      position: relative;
    }

    .search-results {
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      background: white;
      border: 1px solid var(--border-light);
      border-radius: 6px;
      max-height: 200px;
      overflow-y: auto;
      z-index: 100;
      display: none;
      box-shadow: var(--shadow-medium);
    }

    .search-result-item {
      padding: 12px;
      cursor: pointer;
      transition: var(--transition);
      border-bottom: 1px solid var(--border-light);
    }

    .search-result-item:hover {
      background: var(--primary-light);
    }

    .search-result-item:last-child {
      border-bottom: none;
    }

    .account-info-display {
      background: var(--primary-light);
      padding: 15px;
      border-radius: 6px;
      margin-top: 15px;
      display: none;
    }

    .info-row {
      display: flex;
      justify-content: space-between;
      margin-bottom: 8px;
    }

    .info-row:last-child {
      margin-bottom: 0;
    }

    .info-label {
      font-weight: 600;
      color: var(--text-dark);
    }

    .info-value {
      color: var(--text-light);
    }

    /* Responsive modal adjustments */
    @media (max-height: 768px) {
      .modal {
        max-height: 90vh;
      }
      
      .modal-body {
        max-height: calc(90vh - 140px);
      }
    }

    @media (max-height: 600px) {
      .modal {
        max-height: 85vh;
      }
      
      .modal-body {
        max-height: calc(85vh - 140px);
      }
    }

    /* Fix SweetAlert positioning */
    .swal2-container {
      z-index: 10000 !important;
    }

    .swal2-popup {
      font-size: 14px !important;
    }

    .swal2-title {
      font-size: 20px !important;
    }

    .swal2-content {
      font-size: 14px !important;
    }

    .swal2-actions {
      font-size: 14px !important;
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
          <i class="fas fa-users"></i>
          Customer Account Management
        </h1>
        <button class="add-btn" id="addCustomerBtn">
          <i class="fas fa-plus"></i>
          Add Customer Information
        </button>
      </div>

      <div class="customer-stats">
        <div class="stat-card">
          <div class="stat-number"><?php echo $total_customers; ?></div>
          <div class="stat-label">Total Customers</div>
        </div>
        <div class="stat-card">
          <div class="stat-number"><?php echo $approved_customers; ?></div>
          <div class="stat-label">Approved</div>
        </div>
        <div class="stat-card">
          <div class="stat-number"><?php echo $pending_customers; ?></div>
          <div class="stat-label">Pending</div>
        </div>
        <div class="stat-card">
          <div class="stat-number"><?php echo $total_customers > 0 ? round(($approved_customers / $total_customers) * 100, 1) : 0; ?>%</div>
          <div class="stat-label">Approval Rate</div>
        </div>
      </div>

      <div class="filters-section">
        <div class="filter-row">
          <div class="filter-group">
            <label for="customer-search">Search Customers</label>
            <input type="text" id="customer-search" class="filter-input" placeholder="Name, email or phone">
          </div>
          <div class="filter-group">
            <label for="customer-status">Status</label>
            <select id="customer-status" class="filter-select">
              <option value="all">All Statuses</option>
              <option value="Approved">Approved</option>
              <option value="Pending">Pending</option>
              <option value="Rejected">Rejected</option>
            </select>
          </div>
          <button class="filter-btn" onclick="applyFilters()">Apply Filters</button>
        </div>
      </div>

      <div class="customers-table">
        <div class="table-header">
          <h2>Customer Information Records</h2>
        </div>
        <div class="table-responsive">
          <table class="table">
            <thead>
              <tr>
                <th>Customer Name</th>
                <th>Contact Information</th>
                <th>Employment</th>
                <th>Account Status</th>
                <th>Info Status</th>
                <th>Created Date</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="customersTableBody">
              <?php if (empty($customers)): ?>
              <tr>
                <td colspan="7" style="text-align: center; padding: 40px;">
                  No customer information found. Click "Add Customer Information" to create a new record.
                </td>
              </tr>
              <?php else: ?>
                <?php foreach ($customers as $customer): ?>
                <tr>
                  <td>
                    <div class="customer-info">
                      <div class="customer-avatar">
                        <?php 
                          $fi = isset($customer['firstname']) && $customer['firstname'] !== null ? substr(trim($customer['firstname']), 0, 1) : '';
                          $li = isset($customer['lastname']) && $customer['lastname'] !== null ? substr(trim($customer['lastname']), 0, 1) : '';
                          $initials = strtoupper(($fi . $li) ?: '?');
                          echo $initials;
                        ?>
                      </div>
                      <div class="customer-details">
                        <h4><?php echo htmlspecialchars($customer['full_name'] ?? ''); ?></h4>
                        <p><?php echo htmlspecialchars($customer['Username'] ?? ''); ?></p>
                      </div>
                    </div>
                  </td>
                  <td>
                    <p><?php echo htmlspecialchars($customer['Email'] ?? ''); ?></p>
                    <p><?php echo htmlspecialchars($customer['mobile_number'] ?? 'N/A'); ?></p>
                    <p><?php echo htmlspecialchars($customer['complete_address'] ?? 'N/A'); ?></p>
                  </td>
                  <td>
                    <p><?php echo htmlspecialchars($customer['company_name'] ?? 'N/A'); ?></p>
                    <p><?php echo htmlspecialchars($customer['position'] ?? 'N/A'); ?></p>
                  </td>
                  <td>
                    <span class="status-badge <?php echo strtolower($customer['AccountStatus'] ?? ''); ?>">
                      <?php echo htmlspecialchars($customer['AccountStatus'] ?? ''); ?>
                    </span>
                  </td>
                  <td>
                    <span class="status-badge <?php echo strtolower($customer['Status'] ?? ''); ?>">
                      <?php echo htmlspecialchars($customer['Status'] ?? ''); ?>
                    </span>
                  </td>
                  <td><?php echo !empty($customer['created_at']) ? date('M d, Y', strtotime($customer['created_at'])) : 'N/A'; ?></td>
                  <td>
                    <div class="action-buttons">
                      <button class="btn-small btn-edit" onclick="editCustomer(<?php echo $customer['cusID']; ?>)">
                        <i class="fas fa-edit"></i>
                      </button>
                      <button class="btn-small btn-delete" onclick="deleteCustomer(<?php echo $customer['cusID']; ?>)">
                        <i class="fas fa-trash"></i>
                      </button>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Customer Form Modal -->
      <div class="modal-overlay" id="customerFormModal">
        <div class="modal">
          <div class="modal-header">
            <h3 id="modalTitle">Add Walk-in Customer</h3>
            <button class="modal-close" onclick="closeCustomerModal()">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <form id="customerForm">
            <div class="modal-body">
              <input type="hidden" id="cusID" name="cusID">

              <div class="form-section">
                <h3>Personal Information</h3>
                <div class="form-row">
                  <div class="form-group">
                    <label for="firstname">First Name <span style="color: red;">*</span></label>
                    <input type="text" id="firstname" name="firstname" class="form-control" required>
                  </div>
                  <div class="form-group">
                    <label for="lastname">Last Name <span style="color: red;">*</span></label>
                    <input type="text" id="lastname" name="lastname" class="form-control" required>
                  </div>
                </div>
                
                <div class="form-row three-cols">
                  <div class="form-group">
                    <label for="middlename">Middle Name</label>
                    <input type="text" id="middlename" name="middlename" class="form-control">
                  </div>
                  <div class="form-group">
                    <label for="suffix">Suffix</label>
                    <input type="text" id="suffix" name="suffix" class="form-control" placeholder="Jr., Sr., III">
                  </div>
                  <div class="form-group">
                    <label for="nationality">Nationality</label>
                    <input type="text" id="nationality" name="nationality" class="form-control" value="Filipino">
                  </div>
                </div>

                <div class="form-row three-cols">
                  <div class="form-group">
                    <label for="birthday">Birthday <span style="color: red;">*</span></label>
                    <input type="date" id="birthday" name="birthday" class="form-control" required>
                  </div>
                  <div class="form-group">
                    <label for="age">Age</label>
                    <input type="number" id="age" name="age" class="form-control" readonly>
                  </div>
                  <div class="form-group">
                    <label for="gender">Gender</label>
                    <select id="gender" name="gender" class="form-control">
                      <option value="">Select Gender</option>
                      <option value="Male">Male</option>
                      <option value="Female">Female</option>
                      <option value="Other">Other</option>
                    </select>
                  </div>
                </div>

                <div class="form-row">
                  <div class="form-group">
                    <label for="civil_status">Civil Status</label>
                    <select id="civil_status" name="civil_status" class="form-control">
                      <option value="">Select Status</option>
                      <option value="Single">Single</option>
                      <option value="Married">Married</option>
                      <option value="Widowed">Widowed</option>
                      <option value="Divorced">Divorced</option>
                      <option value="Separated">Separated</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label for="mobile_number">Mobile Number</label>
                    <input type="text" id="mobile_number" name="mobile_number" class="form-control" 
                           placeholder="+63 9XX XXX XXXX"
                           oninput="this.value = this.value.replace(/[^0-9+]/g, '')"
                           onkeydown="if(event.key === 'e' || event.key === 'E') event.preventDefault();" />
                  </div>
                </div>

                <div class="form-row">
                  <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="complete_address">Complete Address</label>
                    <input type="text" id="complete_address" name="complete_address" class="form-control" placeholder="House No., Street, Barangay, City/Municipality, Province, Zip Code">
                  </div>
                </div>
              </div>

              <div class="form-section">
                <h3>Employment & Financial Information</h3>
                <div class="form-row">
                  <div class="form-group">
                    <label for="employment_status">Employment Status</label>
                    <select id="employment_status" name="employment_status" class="form-control">
                      <option value="">Select Status</option>
                      <option value="Employed">Employed</option>
                      <option value="Self-Employed">Self-Employed</option>
                      <option value="Business Owner">Business Owner</option>
                      <option value="Unemployed">Unemployed</option>
                      <option value="Retired">Retired</option>
                      <option value="Student">Student</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label for="company_name">Company Name</label>
                    <input type="text" id="company_name" name="company_name" class="form-control">
                  </div>
                </div>

                <div class="form-row">
                  <div class="form-group">
                    <label for="position">Position/Job Title</label>
                    <input type="text" id="position" name="position" class="form-control">
                  </div>
                  <div class="form-group">
                    <label for="monthly_income">Monthly Income</label>
                    <input type="number" id="monthly_income" name="monthly_income" class="form-control" 
                           step="0.01" placeholder="₱0.00"
                           onkeydown="if(event.key === 'e' || event.key === 'E') event.preventDefault();" />
                  </div>
                </div>

                <div class="form-row">
                  <div class="form-group">
                    <label for="valid_id_type">Valid ID Type <span style="color: red;">*</span></label>
                    <select id="valid_id_type" name="valid_id_type" class="form-control" required>
                      <option value="">Select ID Type</option>
                      <option value="Driver's License">Driver's License</option>
                      <option value="Passport">Passport</option>
                      <option value="SSS ID">SSS ID</option>
                      <option value="UMID">UMID</option>
                      <option value="PhilHealth ID">PhilHealth ID</option>
                      <option value="TIN ID">TIN ID</option>
                      <option value="Postal ID">Postal ID</option>
                      <option value="Voter's ID">Voter's ID</option>
                      <option value="PRC ID">PRC ID</option>
                      <option value="Senior Citizen ID">Senior Citizen ID</option>
                      <option value="Student ID">Student ID</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label for="valid_id_number">Valid ID Number <span style="color: red;">*</span></label>
                    <input type="text" id="valid_id_number" name="valid_id_number" class="form-control" 
                           placeholder="Enter ID number" required>
                  </div>
                </div>

                <div class="form-group">
                  <label for="valid_id_image">Valid ID Image <span style="color: red;">*</span></label>
                  <input type="file" id="valid_id_image" name="valid_id_image" class="form-control" 
                         accept="image/*" onchange="previewValidIdImage(this)" required>
                  <small style="color: #666; font-size: 12px;">Please upload a clear photo of your valid ID (front side only)</small>
                  <div id="validIdImagePreview" style="margin-top: 10px; display: none;">
                    <img id="validIdPreviewImg" style="max-width: 300px; max-height: 200px; border-radius: 8px; border: 1px solid var(--border-light);">
                  </div>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" onclick="closeCustomerModal()">Cancel</button>
              <button type="submit" class="btn btn-primary">
                <span id="submitBtnText">Save Customer Information</span>
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Add SweetAlert CDN -->
      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

      <script src="../../includes/js/common-scripts.js"></script>
      <script>
        // Modal functions updated to match inventory.php style
        function openCustomerModal() {
          document.getElementById('customerFormModal').classList.add('active');
        }

        function closeCustomerModal() {
          document.getElementById('customerFormModal').classList.remove('active');
          document.getElementById('customerForm').reset();
          document.getElementById('validIdImagePreview').style.display = 'none';
        }

        document.addEventListener('DOMContentLoaded', function() {
          // Add customer button
          document.getElementById('addCustomerBtn').addEventListener('click', function() {
            document.getElementById('modalTitle').textContent = 'Add Walk-in Customer';
            document.getElementById('submitBtnText').textContent = 'Save Customer Information';
            document.getElementById('customerForm').reset();
            document.getElementById('cusID').value = '';

            document.getElementById('validIdImagePreview').style.display = 'none';
            document.getElementById('validIdPreviewImg').src = '';

            openCustomerModal();
          });

          // Form submission
          document.getElementById('customerForm').addEventListener('submit', function(e) {
            e.preventDefault();
            handleCustomerSubmit();
          });

          // Calculate age when birthday changes
          document.getElementById('birthday').addEventListener('change', function() {
            const birthday = new Date(this.value);
            const today = new Date();
            let age = today.getFullYear() - birthday.getFullYear();
            const monthDiff = today.getMonth() - birthday.getMonth();

            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthday.getDate())) {
              age--;
            }

            document.getElementById('age').value = age;
          });
        });

        // Handle form submission
        async function handleCustomerSubmit() {
          const form = document.getElementById('customerForm');
          const formData = new FormData(form);

          // Validate required personal information fields
          if (!formData.get('firstname') || !formData.get('lastname') || !formData.get('birthday')) {
            Swal.fire({
              title: 'Error',
              text: 'Please fill in all required personal information fields',
              icon: 'error',
              confirmButtonColor: '#d60000',
              confirmButtonText: 'OK',
              allowOutsideClick: true,
              allowEscapeKey: true,
              backdrop: true,
              heightAuto: false,
              width: '400px'
            });
            return;
          }

          // Validate required ID fields
          if (!formData.get('valid_id_type') || !formData.get('valid_id_number')) {
            Swal.fire({
              title: 'Error',
              text: 'Please fill in valid ID type and number',
              icon: 'error',
              confirmButtonColor: '#d60000',
              confirmButtonText: 'OK',
              allowOutsideClick: true,
              allowEscapeKey: true,
              backdrop: true,
              heightAuto: false,
              width: '400px'
            });
            return;
          }

          // Validate valid ID image for new customers
          if (!formData.get('cusID') && !formData.get('valid_id_image').name) {
            Swal.fire({
              title: 'Error',
              text: 'Please upload a valid ID image',
              icon: 'error',
              confirmButtonColor: '#d60000',
              confirmButtonText: 'OK',
              allowOutsideClick: true,
              allowEscapeKey: true,
              backdrop: true,
              heightAuto: false,
              width: '400px'
            });
            return;
          }

          // Add action
          formData.append('action', formData.get('cusID') ? 'update_customer' : 'add_customer');

          try {
            const response = await fetch('customer-accounts-ajax.php', {
              method: 'POST',
              body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
              Swal.fire({
                title: 'Success',
                text: data.message,
                icon: 'success',
                confirmButtonColor: '#d60000',
                confirmButtonText: 'OK',
                allowOutsideClick: true,
                allowEscapeKey: true,
                backdrop: true,
                heightAuto: false,
                width: '400px'
              }).then(() => {
                closeCustomerModal();
                location.reload();
              });
            } else {
              Swal.fire({
                title: 'Error',
                text: data.message || 'Failed to save customer',
                icon: 'error',
                confirmButtonColor: '#d60000',
                confirmButtonText: 'OK',
                allowOutsideClick: true,
                allowEscapeKey: true,
                backdrop: true,
                heightAuto: false,
                width: '400px'
              });
            }
          } catch (error) {
            console.error('Error:', error);
            Swal.fire({
              title: 'Error',
              text: 'An error occurred while saving',
              icon: 'error',
              confirmButtonColor: '#d60000',
              confirmButtonText: 'OK',
              allowOutsideClick: true,
              allowEscapeKey: true,
              backdrop: true,
              heightAuto: false,
              width: '400px'
            });
          }
        }

        // Simplified editCustomer function for walk-in clients only
        async function editCustomer(cusID) {
          try {
            const response = await fetch(`customer-accounts-ajax.php?action=get_customer&id=${cusID}`);
            const data = await response.json();

            if (data.success) {
              const customer = data.customer;
              document.getElementById('modalTitle').textContent = 'Edit Walk-in Customer';
              document.getElementById('submitBtnText').textContent = 'Update Customer Information';
              document.getElementById('customerForm').reset();
              document.getElementById('cusID').value = customer.cusID;

              // Fill personal information
              document.getElementById('firstname').value = customer.firstname || '';
              document.getElementById('lastname').value = customer.lastname || '';
              document.getElementById('middlename').value = customer.middlename || '';
              document.getElementById('suffix').value = customer.suffix || '';
              document.getElementById('nationality').value = customer.nationality || 'Filipino';
              document.getElementById('birthday').value = customer.birthday || '';

              // Calculate and set age
              if(customer.birthday) {
                const birthdayDate = new Date(customer.birthday);
                const today = new Date();
                let age = today.getFullYear() - birthdayDate.getFullYear();
                const monthDiff = today.getMonth() - birthdayDate.getMonth();
                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthdayDate.getDate())) {
                  age--;
                }
                document.getElementById('age').value = age;
              }

              document.getElementById('gender').value = customer.gender || '';
              document.getElementById('civil_status').value = customer.civil_status || '';
              document.getElementById('mobile_number').value = customer.mobile_number || '';
              document.getElementById('complete_address').value = customer.complete_address || '';

              // Fill employment & financial information
              document.getElementById('employment_status').value = customer.employment_status || '';
              document.getElementById('company_name').value = customer.company_name || '';
              document.getElementById('position').value = customer.position || '';
              document.getElementById('monthly_income').value = customer.monthly_income || '';

              // Fill valid ID information
              document.getElementById('valid_id_type').value = customer.valid_id_type || '';
              document.getElementById('valid_id_number').value = customer.valid_id_number || '';

              // Display existing valid ID image if available
              if (customer.valid_id_image_url) {
                document.getElementById('validIdPreviewImg').src = customer.valid_id_image_url;
                document.getElementById('validIdImagePreview').style.display = 'block';
              }

              // Valid ID image not required when editing
              document.getElementById('valid_id_image').required = false;

              openCustomerModal();
            } else {
              Swal.fire({
                title: 'Error',
                text: data.message || 'Failed to load customer data',
                icon: 'error',
                confirmButtonColor: '#d60000',
                confirmButtonText: 'OK'
              });
            }
          } catch (error) {
            console.error('Error:', error);
            Swal.fire({
              title: 'Error',
              text: 'Failed to load customer data',
              icon: 'error',
              confirmButtonColor: '#d60000',
              confirmButtonText: 'OK'
            });
          }
        }



        // Function to preview valid ID image
        function previewValidIdImage(input) {
          if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
              document.getElementById('validIdPreviewImg').src = e.target.result;
              document.getElementById('validIdImagePreview').style.display = 'block';
            };
            reader.readAsDataURL(input.files[0]);
          }
        }



        // Delete customer function
        function deleteCustomer(cusID) {
          Swal.fire({
            title: 'Delete Customer?',
            text: 'Are you sure you want to delete this customer? This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d60000',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete it',
            cancelButtonText: 'Cancel',
            allowOutsideClick: true,
            allowEscapeKey: true,
            backdrop: true,
            heightAuto: false,
            width: '400px'
          }).then((result) => {
            if (result.isConfirmed) {
              fetch('customer-accounts-ajax.php', {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=delete_customer&cusID=${cusID}`
              })
              .then(response => response.json())
              .then(data => {
                if (data.success) {
                  Swal.fire({
                    title: 'Deleted!',
                    text: data.message,
                    icon: 'success',
                    confirmButtonColor: '#d60000',
                    confirmButtonText: 'OK',
                    allowOutsideClick: true,
                    allowEscapeKey: true,
                    backdrop: true,
                    heightAuto: false,
                    width: '400px'
                  }).then(() => {
                    location.reload();
                  });
                } else {
                  Swal.fire({
                    title: 'Error',
                    text: data.message || 'Failed to delete customer',
                    icon: 'error',
                    confirmButtonColor: '#d60000',
                    confirmButtonText: 'OK',
                    allowOutsideClick: true,
                    allowEscapeKey: true,
                    backdrop: true,
                    heightAuto: false,
                    width: '400px'
                  });
                }
              });
            }
          });
        }

        // Apply filters function
        function applyFilters() {
          const search = document.getElementById('customer-search').value;
          const status = document.getElementById('customer-status').value;
          
          fetch(`customer-accounts-ajax.php?action=get_customers&search=${encodeURIComponent(search)}&status=${status}`)
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                updateCustomersTable(data.customers);
              }
            })
            .catch(error => {
              console.error('Error applying filters:', error);
            });
        }

        // Update customers table
        function updateCustomersTable(customers) {
          const tbody = document.getElementById('customersTableBody');
          tbody.innerHTML = '';
          
          if (customers.length === 0) {
            tbody.innerHTML = `
              <tr>
                <td colspan="7" style="text-align: center; padding: 40px;">
                  No customers found matching your criteria.
                </td>
              </tr>
            `;
            return;
          }
          
          customers.forEach(customer => {
            const row = `
              <tr>
                <td>
                  <div class="customer-info">
                    <div class="customer-avatar">
                      ${customer.firstname.charAt(0).toUpperCase()}${customer.lastname.charAt(0).toUpperCase()
                      }
                    </div>
                    <div class="customer-details">
                      <h4>${customer.full_name}</h4>
                      <p>${customer.Username}</p>
                    </div>
                  </div>
                </td>
                <td>
                  <p>${customer.Email}</p>
                  <p>${customer.mobile_number || 'N/A'}</p>
                  <p>${customer.complete_address || 'N/A'}</p>
                </td>
                <td>
                  <p>${customer.company_name || 'N/A'}</p>
                  <p>${customer.position || 'N/A'}</p>
                </td>
                <td>
                  <span class="status-badge ${customer.AccountStatus.toLowerCase()}">
                    ${customer.AccountStatus}
                  </span>
                </td>
                <td>
                  <span class="status-badge ${customer.Status.toLowerCase()}">
                    ${customer.Status}
                  </span>
                </td>
                <td>${new Date(customer.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</td>
                <td>
                  <div class="action-buttons">
                    <button class="btn-small btn-edit" onclick="editCustomer(${customer.cusID})">
                      <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn-small btn-delete" onclick="deleteCustomer(${customer.cusID})">
                      <i class="fas fa-trash"></i>
                    </button>
                  </div>
                </td>
              </tr>
            `;
            tbody.innerHTML += row;
          });
        }
      </script>
    </div>
  </div>
</body>
</html>
