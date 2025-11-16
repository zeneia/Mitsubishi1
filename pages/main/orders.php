<?php
include_once(dirname(dirname(__DIR__)) . '/includes/init.php');

// Check if user is Sales Agent
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'SalesAgent') {
  header("Location: ../../pages/login.php");
  exit();
}

// Use the database connection from init.php (which uses db_conn.php)
$pdo = $GLOBALS['pdo'] ?? null;

// Check if database connection exists
if (!$pdo) {
  die("Database connection not available. Please check your database configuration.");
}

// Fetch vehicles using the existing connection
try {
  $stmt = $pdo->query("SELECT * FROM vehicles ORDER BY created_at DESC");
  $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  // If vehicles table doesn't exist, create empty array
  $vehicles = [];
  error_log("Error fetching vehicles: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Customer Order Management - Sales Agent</title>
  
  <?php
  // Mobile Responsiveness Fix
  $css_path = '../../css/';
  $js_path = '../../js/';
  include '../../includes/components/mobile-responsive-include.php';
  ?>
  
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link href="../../includes/css/common-styles.css" rel="stylesheet">
  <link href="../../includes/css/orders-styles.css" rel="stylesheet">

</head>

<body>
  <?php include '../../includes/components/sidebar.php'; ?>

  <div class="main">
    <?php include '../../includes/components/topbar.php'; ?>

    <div class="main-content">
      <div class="page-header">
        <h1 class="page-title">
          <i class="fas fa-shopping-cart icon-gradient"></i>
          Customer Order Management
        </h1>
        <!-- Moved Add Order Button Here -->
        <button class="add-order-btn" id="addNewOrderBtn" style="padding: 12px 24px; font-size: 1rem;">
          <i class="fas fa-plus-circle"></i> Add New Order
        </button>
      </div>

      <!-- Sales Agent Statistics -->
      <div class="sales-agent-stats">
        <div class="stat-card">
          <div class="stat-icon purple">
            <i class="fas fa-users"></i>
          </div>
          <div class="stat-info">
            <h3>28</h3>
            <p>Handled Clients</p>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon blue">
            <i class="fas fa-shopping-cart"></i>
          </div>
          <div class="stat-info">
            <h3>45</h3>
            <p>Total Orders</p>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon orange">
            <i class="fas fa-clock"></i>
          </div>
          <div class="stat-info">
            <h3>12</h3>
            <p>Pending Orders</p>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon green">
            <i class="fas fa-walking"></i>
          </div>
          <div class="stat-info">
            <h3>8</h3>
            <p>Walk-in Orders</p>
          </div>
        </div>
      </div>

      <div class="filters-section">
        <div class="filter-row">
          <div class="filter-group">
            <label for="order-search">Search Orders</label>
            <input type="text" id="order-search" class="filter-input" placeholder="Order ID or Customer name">
          </div>
          <div class="filter-group">
            <label for="order-status">Order Status</label>
            <select id="order-status" class="filter-select">
              <option value="all">All Statuses</option>
              <option value="pending">Pending</option>
              <option value="confirmed">Confirmed</option>
              <option value="processing">Processing</option>
              <option value="completed">Completed</option>
            </select>
          </div>
          <div class="filter-group">
            <label for="client-type">Client Type</label>
            <select id="client-type" class="filter-select">
              <option value="all">All Clients</option>
              <option value="handled">Handled Clients</option>
              <option value="walkin">Walk-in Clients</option>
            </select>
          </div>
          <div class="filter-group">
            <label for="order-date">Date Range</label>
            <select id="order-date" class="filter-select">
              <option value="all">All Time</option>
              <option value="today">Today</option>
              <option value="week">This Week</option>
              <option value="month">This Month</option>
            </select>
          </div>
          <button class="filter-btn">Apply Filters</button>
        </div>
      </div>

      <!-- Orders Table -->
      <div class="client-orders-section">
        <div class="section-header">
          <h2 class="section-title">
            <i class="fas fa-list"></i>
            <span id="sectionTitle">All Orders</span>
          </h2>
        </div>
        <div class="table-responsive">
          <table class="table">
            <thead>
              <tr>
                <th>Order Information</th>
                <th>Customer</th>
                <th>Client Type</th>
                <th>Vehicle</th>
                <th>Total Price</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="ordersTableBody">
              <!-- Orders will be loaded dynamically -->
            </tbody>
        </table>
      </div>
    </div>



  </div>
  </div>

  <!-- Order Form Modal -->
  <div class="modal-overlay" id="orderFormModal">
    <div class="modal">
      <div class="modal-header">
        <h3 id="modalTitle">Add New Order</h3>
        <button class="modal-close" onclick="closeOrderModal()">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <form id="orderForm">
        <div class="modal-body">
          <input type="hidden" id="orderID" name="orderID">
          <div class="form-section">
            <h3>Customer Information</h3>
            <div class="form-row">
              <div class="form-group">
                <label for="client_type">Client Type</label>
                <select id="client_type" name="client_type" class="form-control" required>
                  <option value="">Select Client Type</option>
                  <option value="handled">Handled Client (Search Existing)</option>
                  <option value="walkin">Walk-in Client (Manual Input)</option>
                </select>
              </div>
            </div>

            <!-- Search Customer Section (for handled clients) -->
            <div id="searchCustomerSection" style="display: none;">
              <div class="form-group">
                <label for="customer_search">Search Customer</label>
                <div class="search-account-wrapper">
                  <input type="text" id="customer_search" class="form-control" placeholder="Search by name, email, or mobile number" autocomplete="off">
                  <input type="hidden" id="customer_id" name="customer_id">
                  <div id="customerSearchResults" class="search-results"></div>
                </div>
              </div>

              <div id="customerInfoDisplay" class="account-info-display">
                <div class="info-row">
                  <span class="info-label">Customer Name:</span>
                  <span class="info-value" id="display_customer_name"></span>
                </div>
                <div class="info-row">
                  <span class="info-label">Email:</span>
                  <span class="info-value" id="display_customer_email"></span>
                </div>
                <div class="info-row">
                  <span class="info-label">Mobile Number:</span>
                  <span class="info-value" id="display_customer_mobile"></span>
                </div>
                <div class="info-row">
                  <span class="info-label">Employment:</span>
                  <span class="info-value" id="display_customer_employment"></span>
                </div>
                <div class="info-row">
                  <span class="info-label">Monthly Income:</span>
                  <span class="info-value" id="display_customer_income"></span>
                </div>
              </div>
            </div>

            <!-- Manual Customer Input Section (for walk-in clients) -->
            <div id="manualCustomerSection" style="display: none;">
              <div class="form-row">
                <div class="form-group">
                  <label for="manual_firstname">First Name *</label>
                  <input type="text" id="manual_firstname" name="manual_firstname" class="form-control" placeholder="Enter first name">
                </div>
                <div class="form-group">
                  <label for="manual_lastname">Last Name *</label>
                  <input type="text" id="manual_lastname" name="manual_lastname" class="form-control" placeholder="Enter last name">
                </div>
              </div>

              <div class="form-row">
                <div class="form-group">
                  <label for="manual_middlename">Middle Name</label>
                  <input type="text" id="manual_middlename" name="manual_middlename" class="form-control" placeholder="Enter middle name (optional)">
                </div>
                <div class="form-group">
                  <label for="manual_suffix">Suffix</label>
                  <input type="text" id="manual_suffix" name="manual_suffix" class="form-control" placeholder="Jr., Sr., III (optional)">
                </div>
              </div>

              <div class="form-row">
                <div class="form-group">
                  <label for="manual_mobile">Mobile Number *</label>
                  <input type="text" id="manual_mobile" name="manual_mobile" class="form-control" placeholder="+63 912 345 6789">
                </div>
                <div class="form-group">
                  <label for="manual_email">Email Address</label>
                  <input type="email" id="manual_email" name="manual_email" class="form-control" placeholder="customer@example.com (optional)">
                </div>
              </div>

              <div class="form-row">
                <div class="form-group">
                  <label for="manual_birthday">Birthday *</label>
                  <input type="date" id="manual_birthday" name="manual_birthday" class="form-control">
                </div>
                <div class="form-group">
                  <label for="manual_age">Age</label>
                  <input type="number" id="manual_age" name="manual_age" class="form-control" placeholder="Age" readonly>
                </div>
              </div>

              <div class="form-row">
                <div class="form-group">
                  <label for="manual_gender">Gender</label>
                  <select id="manual_gender" name="manual_gender" class="form-control">
                    <option value="">Select Gender</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                    <option value="Other">Other</option>
                  </select>
                </div>
                <div class="form-group">
                  <label for="manual_civil_status">Civil Status</label>
                  <select id="manual_civil_status" name="manual_civil_status" class="form-control">
                    <option value="">Select Civil Status</option>
                    <option value="Single">Single</option>
                    <option value="Married">Married</option>
                    <option value="Divorced">Divorced</option>
                    <option value="Widowed">Widowed</option>
                  </select>
                </div>
              </div>

              <div class="form-row">
                <div class="form-group">
                  <label for="manual_nationality">Nationality</label>
                  <input type="text" id="manual_nationality" name="manual_nationality" class="form-control" placeholder="Filipino" value="Filipino">
                </div>
                <div class="form-group">
                  <label for="manual_employment">Employment Status</label>
                  <select id="manual_employment" name="manual_employment" class="form-control">
                    <option value="">Select Employment Status</option>
                    <option value="Employed">Employed</option>
                    <option value="Self-employed">Self-employed</option>
                    <option value="Unemployed">Unemployed</option>
                    <option value="Student">Student</option>
                    <option value="Retired">Retired</option>
                  </select>
                </div>
              </div>

              <div class="form-row">
                <div class="form-group">
                  <label for="manual_company">Company Name</label>
                  <input type="text" id="manual_company" name="manual_company" class="form-control" placeholder="Company name (if employed)">
                </div>
                <div class="form-group">
                  <label for="manual_position">Position</label>
                  <input type="text" id="manual_position" name="manual_position" class="form-control" placeholder="Job position (if employed)">
                </div>
              </div>

              <div class="form-row">
                <div class="form-group">
                  <label for="manual_income">Monthly Income</label>
                  <input type="number" id="manual_income" name="manual_income" class="form-control" placeholder="Monthly income in PHP" step="0.01">
                </div>
                <div class="form-group">
                  <label for="manual_valid_id">Valid ID Type</label>
                  <select id="manual_valid_id" name="manual_valid_id" class="form-control">
                    <option value="">Select ID Type</option>
                    <option value="Driver's License">Driver's License</option>
                    <option value="Passport">Passport</option>
                    <option value="SSS ID">SSS ID</option>
                    <option value="PhilHealth ID">PhilHealth ID</option>
                    <option value="TIN ID">TIN ID</option>
                    <option value="Voter's ID">Voter's ID</option>
                    <option value="Senior Citizen ID">Senior Citizen ID</option>
                    <option value="PWD ID">PWD ID</option>
                    <option value="UMID">UMID</option>
                    <option value="Other">Other</option>
                  </select>
                </div>
              </div>

              <div class="form-group">
                <label for="manual_valid_id_number">Valid ID Number</label>
                <input type="text" id="manual_valid_id_number" name="manual_valid_id_number" class="form-control" placeholder="Enter ID number">
              </div>
            </div>
          </div>
          <div class="form-section">
            <h3>Vehicle Information</h3>
            <div class="form-row">
              <div class="form-group">
                <label for="vehicle_search">Search Vehicle</label>
                <div class="vehicle-dropdown-wrapper" style="position: relative;">
                  <input type="text" id="vehicle_search" class="form-control" placeholder="Type to search vehicle..." autocomplete="off">
                  <input type="hidden" id="vehicle_model" name="vehicle_model">
                  <input type="hidden" id="vehicle_variant" name="vehicle_variant">
                  <input type="hidden" id="selected_vehicle_id" name="selected_vehicle_id">
                  <div id="vehicleDropdownResults" class="vehicle-dropdown-results" style="display: none;"></div>
                </div>
              </div>
              <div class="form-group">
                <label for="vehicle_color">Color</label>
                <select id="vehicle_color" name="vehicle_color" class="form-control" required>
                  <option value="">Select Color</option>
                </select>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label for="model_year">Model Year</label>
                <input type="text" id="model_year" name="model_year" class="form-control" readonly>
              </div>
              <div class="form-group">
                <label for="engine_type">Engine Type</label>
                <input type="text" id="engine_type" name="engine_type" class="form-control" readonly>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label for="transmission">Transmission</label>
                <input type="text" id="transmission" name="transmission" class="form-control" readonly>
              </div>
              <div class="form-group">
                <label for="fuel_type">Fuel Type</label>
                <input type="text" id="fuel_type" name="fuel_type" class="form-control" readonly>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label for="seating_capacity">Seating Capacity</label>
                <input type="number" id="seating_capacity" name="seating_capacity" class="form-control" readonly>
              </div>
              <div class="form-group">
                <label for="base_price">Base Price (₱)</label>
                <input type="number" id="base_price" name="base_price" class="form-control" step="0.01" readonly>
              </div>
            </div>
          </div>

          <!-- Unit Cost Breakdown Section -->
          <div class="form-section">
            <h3>Unit Cost Breakdown</h3>
            <div class="form-row">
              <div class="form-group">
                <label for="body_package_price">Body Package (₱)</label>
                <input type="number" id="body_package_price" name="body_package_price" class="form-control" step="0.01" value="0">
              </div>
              <div class="form-group">
                <label for="aircon_package_price">Aircon Package (₱)</label>
                <input type="number" id="aircon_package_price" name="aircon_package_price" class="form-control" step="0.01" value="0">
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label for="white_color_surcharge">White Color Surcharge (₱)</label>
                <input type="number" id="white_color_surcharge" name="white_color_surcharge" class="form-control" step="0.01" value="0">
              </div>
              <div class="form-group">
                <label for="other_charges">Other Charges (₱)</label>
                <input type="number" id="other_charges" name="other_charges" class="form-control" step="0.01" value="0">
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label for="total_unit_price">Total Unit Price (₱)</label>
                <input type="number" id="total_unit_price" name="total_unit_price" class="form-control" step="0.01" readonly style="background-color: #f0f0f0; font-weight: bold;">
                <small class="form-text text-muted">Auto-calculated: SRP + Body + Aircon + White Color + Others</small>
              </div>
            </div>
          </div>

          <div class="form-section">
            <h3>Order Details</h3>
            <div class="form-row">
              <div class="form-group">
                <label for="order_number">Order Number</label>
                <input type="text" id="order_number" name="order_number" class="form-control" readonly>
              </div>
              <div class="form-group">
                <label for="order_status">Order Status</label>
                <select id="order_status" name="order_status" class="form-control" required>
                  <option value="pending">Pending</option>
                  <option value="confirmed">Confirmed</option>
                  <option value="processing">Processing</option>
                  <option value="completed">Completed</option>
                  <option value="delivered">Delivered</option>
                  <option value="cancelled">Cancelled</option>
                </select>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label for="order_base_price">Base Price (₱)</label>
                <input type="number" id="order_base_price" name="base_price" class="form-control" step="0.01" readonly>
              </div>
              <div class="form-group">
                <label for="nominal_discount">Nominal Discount (₱)</label>
                <input type="number" id="nominal_discount" name="nominal_discount" class="form-control" step="0.01" value="0">
                <small class="form-text text-muted">Standard/regular discount</small>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label for="promo_discount">Promo Discount (₱)</label>
                <input type="number" id="promo_discount" name="promo_discount" class="form-control" step="0.01" value="0">
                <small class="form-text text-muted">Promotional/seasonal discount</small>
              </div>
              <div class="form-group">
                <label for="discount_amount">Total Discount (₱)</label>
                <input type="number" id="discount_amount" name="discount_amount" class="form-control" step="0.01" readonly style="background-color: #f0f0f0;">
                <small class="form-text text-muted">Nominal + Promo</small>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label for="amount_to_invoice">Amount to be Invoiced (₱)</label>
                <input type="number" id="amount_to_invoice" name="amount_to_invoice" class="form-control" step="0.01" readonly style="background-color: #f0f0f0; font-weight: bold;">
                <small class="form-text text-muted">Total Unit Price - Discounts</small>
              </div>
              <div class="form-group">
                <label for="total_price">Total Price (₱)</label>
                <input type="number" id="total_price" name="total_price" class="form-control" step="0.01" readonly style="background-color: #f0f0f0;">
                <small class="form-text text-muted">Same as Amount to Invoice</small>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label for="payment_method">Payment Method</label>
                <select id="payment_method" name="payment_method" class="form-control" required>
                  <option value="">Select Payment Method</option>
                  <option value="cash">Cash</option>
                  <option value="financing">Financing</option>
                  <option value="bank_transfer">Bank Transfer</option>
                  <option value="check">Check</option>
                </select>
              </div>
            </div>

            <!-- Financing Details (shown when financing is selected) -->
            <div id="financingDetails" style="display: none;">
              <div class="form-row">
                <div class="form-group">
                  <label for="finance_percentage">Finance Percentage (%)</label>
                  <input type="number" id="finance_percentage" name="finance_percentage" class="form-control" step="0.01" min="0" max="100" value="80">
                </div>
                <div class="form-group">
                  <label for="amount_finance">Amount to Finance (₱)</label>
                  <input type="number" id="amount_finance" name="amount_finance" class="form-control" step="0.01" readonly style="background-color: #f0f0f0;">
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label for="down_payment_percentage">Down Payment (%)</label>
                  <input type="number" id="down_payment_percentage" name="down_payment_percentage" class="form-control" step="0.01" min="0" max="100" value="20">
                </div>
                <div class="form-group">
                  <label for="down_payment">Down Payment (₱)</label>
                  <input type="number" id="down_payment" name="down_payment" class="form-control" step="0.01" readonly style="background-color: #f0f0f0;">
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label for="net_down_payment">Net Down Payment (₱)</label>
                  <input type="number" id="net_down_payment" name="net_down_payment" class="form-control" step="0.01" readonly style="background-color: #f0f0f0; font-weight: bold;">
                  <small class="form-text text-muted">Down Payment - Discounts</small>
                </div>
                <div class="form-group">
                  <label for="financing_term">Financing Term</label>
                  <select id="financing_term" name="financing_term" class="form-control">
                    <option value="">Select Term</option>
                    <option value="12 months">12 months</option>
                    <option value="24 months">24 months</option>
                    <option value="36 months">36 months</option>
                    <option value="48 months">48 months</option>
                    <option value="60 months">60 months</option>
                    <option value="72 months">72 months</option>
                  </select>
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label for="monthly_payment">Monthly Payment (₱)</label>
                  <input type="number" id="monthly_payment" name="monthly_payment" class="form-control" step="0.01" readonly style="background-color: #f0f0f0;">
                </div>
              </div>
            </div>

            <!-- Incidentals Section -->
            <div class="form-section">
              <h3>Incidentals (Government & Service Fees)</h3>
              <div class="form-row">
                <div class="form-group">
                  <label for="insurance_premium">Insurance Premium (₱)</label>
                  <input type="number" id="insurance_premium" name="insurance_premium" class="form-control" step="0.01" value="0">
                </div>
                <div class="form-group">
                  <label for="cptl_premium">CPTL Premium (₱)</label>
                  <input type="number" id="cptl_premium" name="cptl_premium" class="form-control" step="0.01" value="0">
                  <small class="form-text text-muted">Compulsory Third-Party Liability</small>
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label for="lto_registration">LTO Registration (₱)</label>
                  <input type="number" id="lto_registration" name="lto_registration" class="form-control" step="0.01" value="0">
                </div>
                <div class="form-group">
                  <label for="chattel_mortgage_fee">Chattel Mortgage Fee (₱)</label>
                  <input type="number" id="chattel_mortgage_fee" name="chattel_mortgage_fee" class="form-control" step="0.01" value="0">
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label for="chattel_income">Chattel Income (₱)</label>
                  <input type="number" id="chattel_income" name="chattel_income" class="form-control" step="0.01" value="0">
                </div>
                <div class="form-group">
                  <label for="extended_warranty">Extended Warranty (₱)</label>
                  <input type="number" id="extended_warranty" name="extended_warranty" class="form-control" step="0.01" value="0">
                  <small class="form-text text-muted">2-year extended warranty</small>
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label for="total_incidentals">Total Incidentals (₱)</label>
                  <input type="number" id="total_incidentals" name="total_incidentals" class="form-control" step="0.01" readonly style="background-color: #f0f0f0; font-weight: bold;">
                </div>
              </div>
            </div>

            <!-- Customer Cash Requirement Section -->
            <div class="form-section" style="background-color: #fff3cd; padding: 15px; border-radius: 5px; border: 2px solid #ffc107;">
              <h3 style="color: #856404;">💰 Customer Cash Requirement</h3>
              <div class="form-row">
                <div class="form-group">
                  <label for="reservation_fee">Reservation Fee (₱)</label>
                  <input type="number" id="reservation_fee" name="reservation_fee" class="form-control" step="0.01" value="0">
                </div>
                <div class="form-group">
                  <label for="total_cash_outlay" style="font-weight: bold; font-size: 1.1em;">TOTAL CASH OUTLAY (₱)</label>
                  <input type="number" id="total_cash_outlay" name="total_cash_outlay" class="form-control" step="0.01" readonly style="background-color: #ffc107; font-weight: bold; font-size: 1.2em; color: #000;">
                  <small class="form-text text-muted">Net Down Payment + Incidentals - Reservation</small>
                </div>
              </div>
            </div>

            <!-- Dealer & Expenses Section (Admin Only) -->
            <div class="form-section" id="dealerExpensesSection" style="display: none;">
              <h3>🏢 Dealer Incentives & Expenses (Admin Only)</h3>
              <div class="form-row">
                <div class="form-group">
                  <label for="gross_dealer_incentive_pct">Dealer Incentive (%)</label>
                  <input type="number" id="gross_dealer_incentive_pct" name="gross_dealer_incentive_pct" class="form-control" step="0.01" value="0">
                </div>
                <div class="form-group">
                  <label for="gross_dealer_incentive">Gross Dealer Incentive (₱)</label>
                  <input type="number" id="gross_dealer_incentive" name="gross_dealer_incentive" class="form-control" step="0.01" readonly style="background-color: #f0f0f0;">
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label for="sfm_retain">SFM Retain (₱)</label>
                  <input type="number" id="sfm_retain" name="sfm_retain" class="form-control" step="0.01" value="0">
                </div>
                <div class="form-group">
                  <label for="sfm_additional">SFM Additional (₱)</label>
                  <input type="number" id="sfm_additional" name="sfm_additional" class="form-control" step="0.01" value="0">
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label for="net_dealer_incentive">Net Dealer Incentive (₱)</label>
                  <input type="number" id="net_dealer_incentive" name="net_dealer_incentive" class="form-control" step="0.01" readonly style="background-color: #f0f0f0;">
                </div>
                <div class="form-group">
                  <label for="tipster_fee">Tipster Fee (₱)</label>
                  <input type="number" id="tipster_fee" name="tipster_fee" class="form-control" step="0.01" value="0">
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label for="accessories_cost">Accessories Cost (₱)</label>
                  <input type="number" id="accessories_cost" name="accessories_cost" class="form-control" step="0.01" value="0">
                </div>
                <div class="form-group">
                  <label for="other_expenses">Other Expenses (₱)</label>
                  <input type="number" id="other_expenses" name="other_expenses" class="form-control" step="0.01" value="0">
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label for="se_share">SE Share (₱)</label>
                  <input type="number" id="se_share" name="se_share" class="form-control" step="0.01" value="0">
                </div>
                <div class="form-group">
                  <label for="net_negative">Net Profit/Loss (₱)</label>
                  <input type="number" id="net_negative" name="net_negative" class="form-control" step="0.01" readonly style="background-color: #f0f0f0; font-weight: bold;">
                </div>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label for="delivery_date">Expected Delivery Date</label>
                <input type="date" id="delivery_date" name="delivery_date" class="form-control">
              </div>
              <div class="form-group">
                <label for="actual_delivery_date">Actual Delivery Date</label>
                <input type="date" id="actual_delivery_date" name="actual_delivery_date" class="form-control">
              </div>
            </div>
            <div class="form-group">
              <label for="delivery_address">Delivery Address</label>
              <textarea id="delivery_address" name="delivery_address" class="form-control" rows="2" placeholder="Complete delivery address"></textarea>
            </div>

            <div class="form-group">
              <label for="order_notes">Order Notes</label>
              <textarea id="order_notes" name="order_notes" class="form-control" rows="2" placeholder="General order notes"></textarea>
            </div>

            <div class="form-group">
              <label for="special_instructions">Special Instructions</label>
              <textarea id="special_instructions" name="special_instructions" class="form-control" rows="2" placeholder="Special delivery instructions or customer requests"></textarea>
            </div>

            <div class="form-group">
              <label for="warranty_package">Warranty Package</label>
              <input type="text" id="warranty_package" name="warranty_package" class="form-control" placeholder="e.g., 3-year extended warranty">
            </div>

            <div class="form-group">
              <label for="insurance_details">Insurance Details (Notes)</label>
              <textarea id="insurance_details" name="insurance_details" class="form-control" rows="2" placeholder="Insurance provider and policy details"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" onclick="closeOrderModal()">Cancel</button>
          <button type="submit" class="btn btn-primary">
            <span id="submitBtnText">Save Order</span>
          </button>
        </div>
      </form>
    </div>
  </div>

  <style>
    .vehicle-dropdown-results {
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      background: white;
      border: 1px solid #ddd;
      border-radius: 4px;
      max-height: 400px;
      overflow-y: auto;
      z-index: 1000;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
      margin-top: 4px;
    }

    .vehicle-dropdown-item {
      padding: 12px 16px;
      cursor: pointer;
      border-bottom: 1px solid #f0f0f0;
      transition: background-color 0.2s;
    }

    .vehicle-dropdown-item:hover {
      background-color: #f8f9fa;
    }

    .vehicle-dropdown-item:last-child {
      border-bottom: none;
    }

    .vehicle-dropdown-item.selected {
      background-color: #e8f4f8;
      border-left: 3px solid #d60000;
    }

    .vehicle-item-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 4px;
    }

    .vehicle-item-name {
      font-weight: 600;
      color: #333;
      font-size: 14px;
    }

    .vehicle-item-price {
      color: #d60000;
      font-weight: 600;
      font-size: 14px;
    }

    .vehicle-item-details {
      font-size: 12px;
      color: #666;
      margin-top: 2px;
    }

    .vehicle-item-specs {
      display: flex;
      gap: 12px;
      margin-top: 4px;
      font-size: 11px;
      color: #888;
    }

    .vehicle-item-spec {
      display: flex;
      align-items: center;
      gap: 4px;
    }

    .vehicle-dropdown-empty {
      padding: 20px;
      text-align: center;
      color: #999;
      font-size: 14px;
    }

    .vehicle-dropdown-wrapper {
      position: relative;
    }

    #vehicle_search.has-selection {
      border-color: #28a745;
      background-color: #f0fff4;
    }
  </style>

  <!-- Add SweetAlert CDN -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <script src="../../includes/js/common-scripts.js"></script>
  <script>
    /* 
     * ORDER MANAGEMENT - STATIC DATA ONLY
     * ===================================
     * This system uses only static data, no database calls
     */

    // Vehicle data array
    let vehiclesData = [];

    // Modal functions
    function openOrderModal() {
      document.getElementById('orderFormModal').classList.add('active');
      // Generate order number for new orders
      if (!document.getElementById('orderID').value) {
        generateOrderNumber();
      }
    }

    function closeOrderModal() {
      document.getElementById('orderFormModal').classList.remove('active');
      document.getElementById('orderForm').reset();

      // Hide all customer sections
      document.getElementById('searchCustomerSection').style.display = 'none';
      document.getElementById('manualCustomerSection').style.display = 'none';
      document.getElementById('customerInfoDisplay').style.display = 'none';
      document.getElementById('customerSearchResults').style.display = 'none';
      document.getElementById('financingDetails').style.display = 'none';
      document.getElementById('vehicleDropdownResults').style.display = 'none';

      // Clear hidden fields
      document.getElementById('customer_id').value = '';
      document.getElementById('selected_vehicle_id').value = '';
      document.getElementById('vehicle_search').value = '';
      document.getElementById('vehicle_search').classList.remove('has-selection');
      clearManualCustomerFields();
    }

    // Generate order number
    function generateOrderNumber() {
      const now = new Date();
      const year = now.getFullYear().toString().substr(-2);
      const month = String(now.getMonth() + 1).padStart(2, '0');
      const day = String(now.getDate()).padStart(2, '0');
      const random = Math.floor(Math.random() * 1000).toString().padStart(3, '0');
      const orderNumber = `ORD-${year}${month}${day}${random}`;
      document.getElementById('order_number').value = orderNumber;
    }

    // Load vehicles from database
    function loadVehicles() {
      fetch('../../includes/api/get_vehicles.php')
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            vehiclesData = data.data;
            console.log('Vehicles loaded:', vehiclesData.length);
          } else {
            console.error('Failed to load vehicles:', data.error);
          }
        })
        .catch(error => {
          console.error('Error loading vehicles:', error);
        });
    }

    // Filter and display vehicles in dropdown
    function filterVehicles(searchTerm) {
      const dropdown = document.getElementById('vehicleDropdownResults');
      
      if (searchTerm.length < 1) {
        dropdown.style.display = 'none';
        return;
      }

      const filtered = vehiclesData.filter(vehicle => {
        const searchLower = searchTerm.toLowerCase();
        return (
          vehicle.model_name.toLowerCase().includes(searchLower) ||
          vehicle.variant.toLowerCase().includes(searchLower) ||
          vehicle.category?.toLowerCase().includes(searchLower) ||
          vehicle.year_model?.toString().includes(searchLower)
        );
      });

      if (filtered.length === 0) {
        dropdown.innerHTML = '<div class="vehicle-dropdown-empty">No vehicles found</div>';
        dropdown.style.display = 'block';
        return;
      }

      dropdown.innerHTML = filtered.map(vehicle => {
        const effectivePrice = (vehicle.promotional_price && vehicle.promotional_price > 0 && vehicle.promotional_price < vehicle.base_price) ?
          vehicle.promotional_price : vehicle.base_price;
        
        return `
          <div class="vehicle-dropdown-item" onclick="selectVehicle(${vehicle.id})">
            <div class="vehicle-item-header">
              <span class="vehicle-item-name">${vehicle.model_name} - ${vehicle.variant}</span>
              <span class="vehicle-item-price">₱${parseFloat(effectivePrice).toLocaleString()}</span>
            </div>
            <div class="vehicle-item-details">${vehicle.year_model} | ${vehicle.category || 'Vehicle'}</div>
            <div class="vehicle-item-specs">
              <span class="vehicle-item-spec"><i class="fas fa-cog"></i> ${vehicle.engine_type || 'N/A'}</span>
              <span class="vehicle-item-spec"><i class="fas fa-gas-pump"></i> ${vehicle.fuel_type || 'N/A'}</span>
              <span class="vehicle-item-spec"><i class="fas fa-users"></i> ${vehicle.seating_capacity || 'N/A'} seats</span>
            </div>
          </div>
        `;
      }).join('');

      dropdown.style.display = 'block';
    }

    // Select vehicle from dropdown
    function selectVehicle(vehicleId) {
      const vehicle = vehiclesData.find(v => v.id === vehicleId);
      if (!vehicle) {
        console.error('Vehicle not found:', vehicleId);
        return;
      }

      console.log('Selecting vehicle:', vehicle);

      // Update the search input to show selected vehicle
      const searchInput = document.getElementById('vehicle_search');
      searchInput.value = `${vehicle.model_name} - ${vehicle.variant} (${vehicle.year_model})`;
      searchInput.classList.add('has-selection');

      // Update hidden fields
      document.getElementById('vehicle_model').value = vehicle.model_name;
      document.getElementById('vehicle_variant').value = vehicle.variant;
      document.getElementById('selected_vehicle_id').value = vehicle.id;

      // Populate vehicle details
      document.getElementById('model_year').value = vehicle.year_model;
      document.getElementById('engine_type').value = vehicle.engine_type;
      document.getElementById('transmission').value = vehicle.transmission;
      document.getElementById('fuel_type').value = vehicle.fuel_type;
      document.getElementById('seating_capacity').value = vehicle.seating_capacity;

      // Use promotional price if available and lower than base price, otherwise use base price
      const effectivePrice = (vehicle.promotional_price && vehicle.promotional_price > 0 && vehicle.promotional_price < vehicle.base_price) ?
        vehicle.promotional_price : vehicle.base_price;

      // Update both base price fields (vehicle info and order details)
      document.getElementById('base_price').value = effectivePrice;
      document.getElementById('order_base_price').value = effectivePrice;

      // Populate colors
      const colorSelect = document.getElementById('vehicle_color');
      colorSelect.innerHTML = '<option value="">Select Color</option>';
      if (vehicle.color_options) {
        const colors = vehicle.color_options.split(',');
        colors.forEach(color => {
          const option = document.createElement('option');
          option.value = color.trim();
          option.textContent = color.trim();
          colorSelect.appendChild(option);
        });
      }

      // Calculate total price
      calculateTotalPrice();

      // Hide dropdown
      document.getElementById('vehicleDropdownResults').style.display = 'none';

      // Show success feedback
      Swal.fire({
        title: 'Vehicle Selected!',
        html: `
          <div style="text-align: center;">
            <h3 style="color: var(--primary-red); margin: 10px 0;">${vehicle.model_name}</h3>
            <p style="color: var(--text-light); margin: 5px 0;">${vehicle.variant} - ${vehicle.year_model}</p>
            <p style="font-weight: bold; color: var(--success-green); font-size: 1.2rem;">₱${parseFloat(effectivePrice).toLocaleString()}</p>
          </div>
        `,
        icon: 'success',
        timer: 2000,
        showConfirmButton: false,
        toast: true,
        position: 'top-end',
        customClass: {
          popup: 'colored-toast'
        }
      });
    }


    // Show/hide dealer expenses section based on user role
    document.addEventListener('DOMContentLoaded', function() {
      const userRole = '<?php echo $_SESSION['user_role'] ?? ''; ?>';
      if (userRole === 'Admin') {
        document.getElementById('dealerExpensesSection').style.display = 'block';
      }

      // Attach event listeners to all financial input fields
      const financialInputs = [
        'body_package_price', 'aircon_package_price', 'white_color_surcharge', 'other_charges',
        'nominal_discount', 'promo_discount', 'finance_percentage', 'down_payment_percentage',
        'insurance_premium', 'cptl_premium', 'lto_registration', 'chattel_mortgage_fee',
        'chattel_income', 'extended_warranty', 'reservation_fee', 'gross_dealer_incentive_pct',
        'sfm_retain', 'sfm_additional', 'tipster_fee', 'accessories_cost', 'other_expenses', 'se_share'
      ];

      financialInputs.forEach(inputId => {
        const element = document.getElementById(inputId);
        if (element) {
          element.addEventListener('input', calculateFinancials);
        }
      });
    });

    // Comprehensive financial calculations
    function calculateFinancials() {
      // Get all input values
      const basePrice = parseFloat(document.getElementById('order_base_price').value) || 0;
      const bodyPackage = parseFloat(document.getElementById('body_package_price').value) || 0;
      const airconPackage = parseFloat(document.getElementById('aircon_package_price').value) || 0;
      const whiteColor = parseFloat(document.getElementById('white_color_surcharge').value) || 0;
      const otherCharges = parseFloat(document.getElementById('other_charges').value) || 0;

      const nominalDiscount = parseFloat(document.getElementById('nominal_discount').value) || 0;
      const promoDiscount = parseFloat(document.getElementById('promo_discount').value) || 0;

      const financePercentage = parseFloat(document.getElementById('finance_percentage').value) || 0;
      const downPaymentPercentage = parseFloat(document.getElementById('down_payment_percentage').value) || 0;

      const insurance = parseFloat(document.getElementById('insurance_premium').value) || 0;
      const cptl = parseFloat(document.getElementById('cptl_premium').value) || 0;
      const lto = parseFloat(document.getElementById('lto_registration').value) || 0;
      const chattelFee = parseFloat(document.getElementById('chattel_mortgage_fee').value) || 0;
      const chattelIncome = parseFloat(document.getElementById('chattel_income').value) || 0;
      const warranty = parseFloat(document.getElementById('extended_warranty').value) || 0;

      const reservationFee = parseFloat(document.getElementById('reservation_fee').value) || 0;

      const dealerIncentivePct = parseFloat(document.getElementById('gross_dealer_incentive_pct').value) || 0;
      const sfmRetain = parseFloat(document.getElementById('sfm_retain').value) || 0;
      const sfmAdditional = parseFloat(document.getElementById('sfm_additional').value) || 0;
      const tipsterFee = parseFloat(document.getElementById('tipster_fee').value) || 0;
      const accessoriesCost = parseFloat(document.getElementById('accessories_cost').value) || 0;
      const otherExpenses = parseFloat(document.getElementById('other_expenses').value) || 0;
      const seShare = parseFloat(document.getElementById('se_share').value) || 0;

      // Formula #1: Total Unit Price
      const totalUnitPrice = basePrice + bodyPackage + airconPackage + whiteColor + otherCharges;
      document.getElementById('total_unit_price').value = totalUnitPrice.toFixed(2);

      // Formula #2: Amount to Invoice
      const amountToInvoice = totalUnitPrice - nominalDiscount - promoDiscount;
      document.getElementById('amount_to_invoice').value = Math.max(0, amountToInvoice).toFixed(2);

      // Formula #3: Amount Finance
      const amountFinance = totalUnitPrice * (financePercentage / 100);
      document.getElementById('amount_finance').value = amountFinance.toFixed(2);

      // Formula #4: Down Payment
      const downPayment = totalUnitPrice * (downPaymentPercentage / 100);
      document.getElementById('down_payment').value = downPayment.toFixed(2);

      // Formula #6: Net Down Payment
      const netDownPayment = downPayment - nominalDiscount - promoDiscount;
      document.getElementById('net_down_payment').value = Math.max(0, netDownPayment).toFixed(2);

      // Formula #7: Total Incidentals
      const totalIncidentals = insurance + cptl + lto + chattelFee + chattelIncome + warranty;
      document.getElementById('total_incidentals').value = totalIncidentals.toFixed(2);

      // Formula #8: Total Cash Outlay
      const totalCashOutlay = Math.max(0, netDownPayment) + totalIncidentals - reservationFee;
      document.getElementById('total_cash_outlay').value = Math.max(0, totalCashOutlay).toFixed(2);

      // Formula #9: Gross Dealer Incentive
      const grossDealerIncentive = amountFinance * (dealerIncentivePct / 100);
      document.getElementById('gross_dealer_incentive').value = grossDealerIncentive.toFixed(2);

      // Formula #10: Net Dealer Incentive
      const netDealerIncentive = grossDealerIncentive - sfmRetain;
      document.getElementById('net_dealer_incentive').value = netDealerIncentive.toFixed(2);

      // Formula #13: Net Negative (simplified - full formula in backend)
      const totalExpenses = nominalDiscount + promoDiscount + sfmAdditional + totalIncidentals + tipsterFee + accessoriesCost + otherExpenses;
      const netNegative = totalExpenses + seShare;
      document.getElementById('net_negative').value = netNegative.toFixed(2);

      // Update legacy fields for backward compatibility
      document.getElementById('discount_amount').value = (nominalDiscount + promoDiscount).toFixed(2);
      document.getElementById('total_price').value = Math.max(0, amountToInvoice).toFixed(2);
    }

    // Legacy function for backward compatibility
    function calculateTotalPrice() {
      calculateFinancials();
    }

    async function calculateMonthlyPayment() {
      const amountFinance = parseFloat(document.getElementById('amount_finance').value) || 0;
      const financingTerm = document.getElementById('financing_term').value;

      if (amountFinance > 0 && financingTerm) {
        const months = parseInt(financingTerm.split(' ')[0]);

        try {
          // Use centralized payment calculator with amount_finance
          const response = await fetch('../../includes/payment_calculator.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify({
              action: 'calculate',
              vehicle_price: amountFinance,
              down_payment: 0, // Already factored into amount_finance
              financing_term: months
            })
          });
          
          const result = await response.json();
          
          if (result.success && result.data) {
            document.getElementById('monthly_payment').value = result.data.monthly_payment.toFixed(2);
          } else {
            console.error('Payment calculation failed:', result.error);
            // Try centralized payment calculator API as fallback
            try {
              const response = await fetch('../../includes/payment_calculator.php', {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                  action: 'calculate',
                  vehicle_price: totalPrice,
                  down_payment: downPayment,
                  financing_term: months
                })
              });
              
              const fallbackResult = await response.json();
              if (fallbackResult.success) {
                document.getElementById('monthly_payment').value = fallbackResult.data.monthly_payment.toFixed(2);
              } else {
                throw new Error('Centralized API calculation failed');
              }
            } catch (apiError) {
              console.error('Centralized API fallback failed:', apiError);
              // Final fallback to basic calculation
              const loanAmount = totalPrice - downPayment;
              const interestRate = 0.12; // 12% fallback rate
              const monthlyRate = interestRate / 12;
              
              if (months > 0 && loanAmount > 0) {
                const monthlyPayment = loanAmount * (monthlyRate * Math.pow(1 + monthlyRate, months)) / (Math.pow(1 + monthlyRate, months) - 1);
                document.getElementById('monthly_payment').value = monthlyPayment.toFixed(2);
              }
            }
          }
        } catch (error) {
          console.error('Error calculating payment:', error);
          // Try centralized payment calculator API as fallback
          try {
            const response = await fetch('../../includes/payment_calculator.php', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json'
              },
              body: JSON.stringify({
                action: 'calculate',
                vehicle_price: totalPrice,
                down_payment: downPayment,
                financing_term: months
              })
            });
            
            const result = await response.json();
            if (result.success) {
              document.getElementById('monthly_payment').value = result.data.monthly_payment.toFixed(2);
            } else {
              throw new Error('API calculation failed');
            }
          } catch (apiError) {
            console.error('API fallback failed:', apiError);
            // Final fallback to basic calculation
            const loanAmount = totalPrice - downPayment;
            const interestRate = 0.12; // 12% fallback rate
            const monthlyRate = interestRate / 12;
            
            if (months > 0 && loanAmount > 0) {
              const monthlyPayment = loanAmount * (monthlyRate * Math.pow(1 + monthlyRate, months)) / (Math.pow(1 + monthlyRate, months) - 1);
              document.getElementById('monthly_payment').value = monthlyPayment.toFixed(2);
            }
          }
        }
      }
    }

    // Helper functions
    function clearManualCustomerFields() {
      const manualFields = [
        'manual_firstname', 'manual_lastname', 'manual_middlename', 'manual_suffix',
        'manual_mobile', 'manual_email', 'manual_birthday', 'manual_age',
        'manual_gender', 'manual_civil_status', 'manual_nationality', 'manual_employment',
        'manual_company', 'manual_position', 'manual_income', 'manual_valid_id', 'manual_valid_id_number'
      ];

      manualFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
          field.value = '';
        }
      });
    }

    function filterOrdersByClientType(clientType) {
      console.log('Filtering orders by client type:', clientType);
      // Add actual filtering logic here if needed
    }

    // Function to select customer from search results
    function selectCustomer(customer) {
      document.getElementById('customer_id').value = customer.cusID;
      document.getElementById('customer_search').value = `${customer.firstname} ${customer.lastname}`;
      document.getElementById('customerSearchResults').style.display = 'none';

      // Show customer info
      document.getElementById('display_customer_name').textContent = `${customer.firstname} ${customer.lastname}`;
      document.getElementById('display_customer_email').textContent = customer.email || 'N/A';
      document.getElementById('display_customer_mobile').textContent = customer.mobile_number || 'N/A';
      document.getElementById('display_customer_employment').textContent = customer.employment_status || 'N/A';
      document.getElementById('display_customer_income').textContent = customer.monthly_income ? `₱${parseFloat(customer.monthly_income).toLocaleString()}` : 'N/A';
      document.getElementById('customerInfoDisplay').style.display = 'block';
    }

    // Search customers function - now uses backend API
    function searchCustomers(searchTerm) {
      const searchResults = document.getElementById('customerSearchResults');

      if (searchTerm.length < 2) {
        searchResults.style.display = 'none';
        return;
      }

      // Show loading state
      searchResults.innerHTML = '<div class="search-loading">Searching customers...</div>';
      searchResults.style.display = 'block';

      fetch(`../../includes/api/search_customers.php?search=${encodeURIComponent(searchTerm)}`)
        .then(response => response.json())
        .then(data => {
          if (data.success && data.data.length > 0) {
            searchResults.innerHTML = data.data.map(customer => `
              <div class="search-result-item" onclick="selectCustomer({
                cusID: ${customer.cusID},
                firstname: '${customer.firstname.replace(/'/g, "\\'")}',
                lastname: '${customer.lastname.replace(/'/g, "\\'")}',
                middlename: '${(customer.middlename || '').replace(/'/g, "\\'")}',
                email: '${(customer.email || '').replace(/'/g, "\\'")}',
                mobile_number: '${(customer.mobile_number || '').replace(/'/g, "\\'")}',
                employment_status: '${(customer.employment_status || '').replace(/'/g, "\\'")}',
                monthly_income: ${customer.monthly_income || 0}
              })">
                <strong>${customer.firstname} ${customer.lastname}</strong><br>
                <small>${customer.email || 'No email'} | ${customer.mobile_number || 'No mobile'}</small>
              </div>
            `).join('');
          } else {
            searchResults.innerHTML = '<div class="search-no-results">No customers found</div>';
          }
          searchResults.style.display = 'block';
        })
        .catch(error => {
          console.error('Search error:', error);
          searchResults.innerHTML = '<div class="search-error">Search failed. Please try again.</div>';
          searchResults.style.display = 'block';
        });
    }

    // Handle form submission - now submits to backend
    async function handleOrderSubmit() {
      const form = document.getElementById('orderForm');
      const formData = new FormData(form);

      // Convert FormData to JSON object
      const orderData = {};
      for (let [key, value] of formData.entries()) {
        orderData[key] = value;
      }

      // Pre-validate before any data processing
      Swal.fire({
        title: 'Validating...',
        text: 'Please wait while we validate your order information',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => {
          Swal.showLoading();
        }
      });

      try {
        // Call validation API first
        const validationResponse = await fetch('../../includes/api/validate_order.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            validation_type: 'pre_submit',
            client_type: orderData.client_type,
            customer_id: orderData.customer_id,
            manual_email: orderData.manual_email,
            manual_mobile: orderData.manual_mobile,
            manual_firstname: orderData.manual_firstname,
            manual_lastname: orderData.manual_lastname,
            manual_birthday: orderData.manual_birthday,
            vehicle_model: orderData.vehicle_model,
            vehicle_variant: orderData.vehicle_variant
          })
        });

        const validationResult = await validationResponse.json();

        if (!validationResult.success) {
          Swal.fire({
            title: 'Validation Error',
            html: validationResult.data.errors.join('<br>'),
            icon: 'error',
            confirmButtonColor: '#d60000'
          });
          return;
        }

      } catch (error) {
        console.error('Validation error:', error);
        Swal.fire({
          title: 'Validation Error',
          text: 'Unable to validate order. Please try again.',
          icon: 'error',
          confirmButtonColor: '#d60000'
        });
        return;
      }

      // Validate client type
      if (!orderData.client_type) {
        Swal.fire({
          title: 'Error',
          text: 'Please select a client type first',
          icon: 'error',
          confirmButtonColor: '#d60000'
        });
        return;
      }

      // Validate customer information based on client type
      if (orderData.client_type === 'handled') {
        if (!orderData.customer_id) {
          Swal.fire({
            title: 'Error',
            text: 'Please search and select a customer first',
            icon: 'error',
            confirmButtonColor: '#d60000'
          });
          return;
        }
      } else if (orderData.client_type === 'walkin') {
        const requiredFields = [{
            field: 'manual_firstname',
            name: 'First Name'
          },
          {
            field: 'manual_lastname',
            name: 'Last Name'
          },
          {
            field: 'manual_mobile',
            name: 'Mobile Number'
          },
          {
            field: 'manual_birthday',
            name: 'Birthday'
          }
        ];

        for (let req of requiredFields) {
          if (!orderData[req.field] || orderData[req.field].trim() === '') {
            Swal.fire({
              title: 'Error',
              text: `${req.name} is required for walk-in customers`,
              icon: 'error',
              confirmButtonColor: '#d60000'
            });
            return;
          }
        }
      }

      // Validate vehicle information
      if (!orderData.vehicle_model || !orderData.vehicle_variant) {
        Swal.fire({
          title: 'Error',
          text: 'Please select vehicle model and variant',
          icon: 'error',
          confirmButtonColor: '#d60000'
        });
        return;
      }

      // Show loading state
      Swal.fire({
        title: 'Creating Order...',
        text: 'Please wait while we process your order',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => {
          Swal.showLoading();
        }
      });

      // Submit to backend
      fetch('../../includes/api/create_order.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify(orderData)
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            Swal.fire({
              title: 'Success!',
              html: `
              <div style="text-align: center;">
                <h3 style="color: var(--success-green); margin: 10px 0;">Order Created Successfully</h3>
                <p><strong>Order Number:</strong> ${data.data.order_number}</p>
                <p><strong>Customer ID:</strong> ${data.data.customer_id}</p>
              </div>
            `,
              icon: 'success',
              confirmButtonColor: '#d60000'
            }).then(() => {
              closeOrderModal();
              // Refresh the orders table and statistics
              loadOrders();
              loadOrderStatistics();
            });
          } else {
            Swal.fire({
              title: 'Error',
              text: data.message || 'Failed to create order',
              icon: 'error',
              confirmButtonColor: '#d60000'
            });
          }
        })
        .catch(error => {
          console.error('Submit error:', error);
          Swal.fire({
            title: 'Error',
            text: 'Network error. Please check your connection and try again.',
            icon: 'error',
            confirmButtonColor: '#d60000'
          });
        });
    }

    // View order details function
    function viewOrderDetails(orderID) {
      Swal.fire({
        title: 'Order Details',
        text: `Viewing details for order: ${orderID}`,
        icon: 'info',
        confirmButtonColor: '#d60000'
      });
    }

    // Delete order function
    function deleteOrder(orderID) {
      Swal.fire({
        title: 'Delete Order?',
        text: "This action cannot be undone. Are you sure you want to delete this order?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d60000',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it',
        cancelButtonText: 'Cancel'
      }).then((result) => {
        if (result.isConfirmed) {
          // Show loading state
          Swal.fire({
            title: 'Deleting...',
            text: 'Please wait while we delete the order',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => {
              Swal.showLoading();
            }
          });

          // Use fetch instead of jQuery AJAX
          const formData = new FormData();
          formData.append('action', 'delete_order');
          formData.append('order_id', orderID);

          fetch('../../includes/backend/sales_orders_backend.php', {
            method: 'POST',
            body: formData
          })
          .then(response => response.json())
          .then(response => {
            if (response.success) {
              Swal.fire({
                title: 'Deleted!',
                text: 'Order has been deleted successfully.',
                icon: 'success',
                confirmButtonColor: '#d60000'
              }).then(() => {
                loadOrders();
                loadOrderStatistics();
              });
            } else {
              Swal.fire({
                title: 'Error',
                text: response.message || 'Failed to delete order',
                icon: 'error',
                confirmButtonColor: '#d60000'
              });
            }
          })
          .catch(error => {
            console.error('Delete error:', error);
            Swal.fire({
              title: 'Error',
              text: 'Failed to delete order. Please try again.',
              icon: 'error',
              confirmButtonColor: '#d60000'
            });
          });
        }
      });
    }

    // Initialize dropdown options
    function initializeDropdowns() {
      const colorSelect = document.getElementById('vehicle_color');
      colorSelect.innerHTML = '<option value="">Select Color</option>';
      document.getElementById('order_status').value = 'pending';
    }

    // Load orders from backend
    function loadOrders(filters = {}) {
      const params = new URLSearchParams({
        action: 'get_all_orders',
        search: filters.search || '',
        status: filters.status || 'all',
        client_type: filters.client_type || 'all',
        date_range: filters.date_range || 'all'
      });
      
      fetch(`../../includes/backend/sales_orders_backend.php?${params}`)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            displayOrders(data.data);
            updateSectionTitle(data.data.length, filters);
          } else {
            console.error('Failed to load orders:', data.error);
            document.getElementById('ordersTableBody').innerHTML = '<tr><td colspan="7" class="text-center">Failed to load orders</td></tr>';
          }
        })
        .catch(error => {
          console.error('Error loading orders:', error);
          document.getElementById('ordersTableBody').innerHTML = '<tr><td colspan="7" class="text-center">Error loading orders</td></tr>';
        });
    }

    // Update section title based on filters
    function updateSectionTitle(count, filters) {
      const sectionTitle = document.getElementById('sectionTitle');
      if (!sectionTitle) return;
      
      let title = 'All Orders';
      const activeFilters = [];
      
      if (filters.search) {
        activeFilters.push(`matching "${filters.search}"`);
      }
      
      if (filters.status && filters.status !== 'all') {
        activeFilters.push(`with ${filters.status} status`);
      }
      
      if (filters.client_type && filters.client_type !== 'all') {
        const clientTypeText = filters.client_type === 'walkin' ? 'Walk-in' : 'Handled';
        activeFilters.push(`for ${clientTypeText} clients`);
      }
      
      if (filters.date_range && filters.date_range !== 'all') {
        const dateRangeText = {
          'today': 'from today',
          'week': 'from this week',
          'month': 'from this month'
        };
        activeFilters.push(dateRangeText[filters.date_range]);
      }
      
      if (activeFilters.length > 0) {
        title = `Orders ${activeFilters.join(' ')} (${count})`;
      } else {
        title = `All Orders (${count})`;
      }
      
      sectionTitle.textContent = title;
    }

    // Apply filters function
    function applyFilters() {
      const filters = {
        search: document.getElementById('order-search').value.trim(),
        status: document.getElementById('order-status').value,
        client_type: document.getElementById('client-type').value,
        date_range: document.getElementById('order-date').value
      };
      
      loadOrders(filters);
    }

    // Display orders in table
    function displayOrders(orders) {
      const tbody = document.getElementById('ordersTableBody');
      
      if (orders.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center">No orders found</td></tr>';
        return;
      }
      
      tbody.innerHTML = orders.map(order => {
        const clientTypeBadge = order.client_type === 'walkin' ? 
          '<span class="client-type-badge walkin">Walk-in</span>' : 
          '<span class="client-type-badge handled">Handled Client</span>';
        
        const statusClass = getStatusClass(order.order_status);
        const formattedPrice = new Intl.NumberFormat('en-PH', {
          style: 'currency',
          currency: 'PHP',
          minimumFractionDigits: 0
        }).format(order.total_price);
        
        return `
          <tr>
            <td>
              <div class="order-info">
                <span class="order-id">${order.order_number}</span>
                <span class="order-date">${order.order_date}</span>
              </div>
            </td>
            <td>
              <div class="customer-info">
                <span class="customer-name">${order.customer_name}</span>
                <span class="customer-contact">${order.customer_email}</span>
                <div class="agent-note">${order.customer_note}</div>
              </div>
            </td>
            <td>
              ${clientTypeBadge}
            </td>
            <td>
              <div class="vehicle-info">
                <span class="vehicle-model">${order.vehicle_model}</span>
                <span class="vehicle-details">${order.vehicle_color}, ${order.model_year} ${order.vehicle_variant}</span>
              </div>
            </td>
            <td class="price">${formattedPrice}</td>
            <td><span class="status-badge ${statusClass}">${capitalizeFirst(order.order_status)}</span></td>
            <td>
              <div class="order-actions-enhanced">
                <button class="btn-small btn-view" title="View Details" onclick="viewOrderDetails('${order.order_id}')"><i class="fas fa-eye"></i></button>
                <button class="btn-small btn-delete" title="Delete Order" onclick="deleteOrder('${order.order_id}')"><i class="fas fa-trash"></i></button>
              </div>
            </td>
          </tr>
        `;
      }).join('');
    }

    // Get status class for styling
    function getStatusClass(status) {
      const statusMap = {
        'pending': 'pending',
        'confirmed': 'confirmed',
        'processing': 'processing',
        'completed': 'completed',
        'cancelled': 'cancelled',
        'delivered': 'completed'
      };
      return statusMap[status] || 'pending';
    }

    // Capitalize first letter
    function capitalizeFirst(str) {
      return str.charAt(0).toUpperCase() + str.slice(1);
    }

    // Load order statistics
    function loadOrderStatistics() {
      fetch('../../includes/backend/sales_orders_backend.php?action=get_order_statistics')
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            updateStatistics(data.data);
          } else {
            console.error('Failed to load statistics:', data.error);
          }
        })
        .catch(error => {
          console.error('Error loading statistics:', error);
        });
    }

    // Update statistics display
    function updateStatistics(stats) {
      // Update the statistics cards
      const totalOrdersElement = document.querySelector('.stat-card:nth-child(2) .stat-number');
      const pendingOrdersElement = document.querySelector('.stat-card:nth-child(3) .stat-number');
      const walkinOrdersElement = document.querySelector('.stat-card:nth-child(1) .stat-number');
      const handledClientsElement = document.querySelector('.stat-card:nth-child(4) .stat-number');
      
      if (totalOrdersElement) totalOrdersElement.textContent = stats.total_orders;
      if (pendingOrdersElement) pendingOrdersElement.textContent = stats.pending_orders;
      if (walkinOrdersElement) walkinOrdersElement.textContent = stats.walkin_orders;
      if (handledClientsElement) handledClientsElement.textContent = stats.handled_clients;
    }

    // DOMContentLoaded event
    document.addEventListener('DOMContentLoaded', function() {
      // Initialize vehicles data
      initializeDropdowns();
      loadVehicles();
      
      // Load orders and statistics
      loadOrders();
      loadOrderStatistics();

      // Vehicle search functionality
      let vehicleSearchTimeout;
      document.getElementById('vehicle_search').addEventListener('input', function() {
        clearTimeout(vehicleSearchTimeout);
        const searchTerm = this.value.trim();
        
        // If user clears the input, reset selection
        if (searchTerm === '') {
          document.getElementById('vehicle_search').classList.remove('has-selection');
          document.getElementById('selected_vehicle_id').value = '';
          document.getElementById('vehicle_model').value = '';
          document.getElementById('vehicle_variant').value = '';
          document.getElementById('vehicleDropdownResults').style.display = 'none';
          return;
        }

        vehicleSearchTimeout = setTimeout(() => {
          filterVehicles(searchTerm);
        }, 300);
      });

      // Add New Order button
      document.getElementById('addNewOrderBtn')?.addEventListener('click', function() {
        document.getElementById('modalTitle').textContent = 'Add New Order';
        document.getElementById('submitBtnText').textContent = 'Submit Order';
        document.getElementById('orderID').value = '';

        document.getElementById('orderForm').reset();
        clearManualCustomerFields();
        document.getElementById('customerInfoDisplay').style.display = 'none';
        document.getElementById('customer_search').value = '';
        document.getElementById('customerSearchResults').innerHTML = '';
        document.getElementById('customerSearchResults').style.display = 'none';

        // Set default client type to handled
        const clientTypeSelect = document.getElementById('client_type');
        if (clientTypeSelect) {
          clientTypeSelect.value = 'handled';
          clientTypeSelect.dispatchEvent(new Event('change'));
        }

        generateOrderNumber();
        openOrderModal();
      });

      // View/Edit button handlers
      document.querySelectorAll('.btn-view').forEach(btn => {
        btn.addEventListener('click', function() {
          const row = this.closest('tr');
          const orderID = row.querySelector('.order-id').textContent;
          viewOrderDetails(orderID);
        });
      });

      document.querySelectorAll('.btn-delete').forEach(btn => {
        btn.addEventListener('click', function() {
          const row = this.closest('tr');
          const orderID = row.querySelector('.order-id').textContent;
          deleteOrder(orderID);
        });
      });

      // Client type change handler
      document.getElementById('client_type').addEventListener('change', function() {
        const clientType = this.value;
        const searchSection = document.getElementById('searchCustomerSection');
        const manualSection = document.getElementById('manualCustomerSection');
        const customerInfoDisplay = document.getElementById('customerInfoDisplay');

        searchSection.style.display = 'none';
        manualSection.style.display = 'none';
        customerInfoDisplay.style.display = 'none';

        document.getElementById('customer_search').value = '';
        document.getElementById('customer_id').value = '';
        clearManualCustomerFields();

        if (clientType === 'handled') {
          searchSection.style.display = 'block';
        } else if (clientType === 'walkin') {
          manualSection.style.display = 'block';
        }
      });

      // Calculate age from birthday
      document.getElementById('manual_birthday').addEventListener('change', function() {
        const birthday = new Date(this.value);
        const today = new Date();
        let age = today.getFullYear() - birthday.getFullYear();
        const monthDiff = today.getMonth() - birthday.getMonth();

        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthday.getDate())) {
          age--;
        }

        document.getElementById('manual_age').value = age >= 0 ? age : '';
      });

      // Payment method change handler
      document.getElementById('payment_method').addEventListener('change', function() {
        const financingDetails = document.getElementById('financingDetails');
        if (this.value === 'financing') {
          financingDetails.style.display = 'block';
        } else {
          financingDetails.style.display = 'none';
        }
      });

      // Price calculation event listeners
      document.getElementById('order_base_price').addEventListener('input', calculateTotalPrice);
      document.getElementById('discount_amount').addEventListener('input', calculateTotalPrice);
      document.getElementById('down_payment').addEventListener('input', calculateMonthlyPayment);
      document.getElementById('financing_term').addEventListener('change', calculateMonthlyPayment);

      // Customer search functionality
      let searchTimeout;
      document.getElementById('customer_search').addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const searchTerm = this.value.trim();

        if (searchTerm.length < 2) {
          document.getElementById('customerSearchResults').style.display = 'none';
          return;
        }

        searchTimeout = setTimeout(() => {
          searchCustomers(searchTerm);
        }, 300);
      });

      // Form submission
      document.getElementById('orderForm').addEventListener('submit', function(e) {
        e.preventDefault();
        handleOrderSubmit();
      });

      // Apply Filters button event listener
      document.querySelector('.filter-btn').addEventListener('click', applyFilters);

      // Real-time filtering on input changes
      let filterTimeout;
      
      // Search input real-time filtering
      document.getElementById('order-search').addEventListener('input', function() {
        clearTimeout(filterTimeout);
        filterTimeout = setTimeout(applyFilters, 300); // Debounce for 300ms
      });
      
      // Dropdown filters real-time filtering
      document.getElementById('order-status').addEventListener('change', applyFilters);
      document.getElementById('client-type').addEventListener('change', applyFilters);
      document.getElementById('order-date').addEventListener('change', applyFilters);

      // Click outside to close search results
      document.addEventListener('click', function(e) {
        if (!e.target.closest('.search-account-wrapper')) {
          document.getElementById('customerSearchResults').style.display = 'none';
        }
        if (!e.target.closest('.vehicle-dropdown-wrapper')) {
          document.getElementById('vehicleDropdownResults').style.display = 'none';
        }
      });
    });
  </script>
</body>

</html>
