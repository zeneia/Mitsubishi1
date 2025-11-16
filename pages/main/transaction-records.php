<?php
include_once(dirname(dirname(__DIR__)) . '/includes/init.php');

// Check if user is Admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: ../../pages/login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Transaction Records - Mitsubishi</title>
  
  <?php
  // Mobile Responsiveness Fix
  $css_path = '../../css/';
  $js_path = '../../js/';
  include '../../includes/components/mobile-responsive-include.php';
  ?>
  
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
    
    /* REMOVED zoom: 85% - causes mobile layout issues, not supported by Firefox */
    
    /* Admin Transaction Records Specific Styles */
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

    .tab-navigation {
      display: flex;
      gap: 5px;
      margin-bottom: 25px;
      border-bottom: 1px solid var(--border-light);
      flex-wrap: wrap;
    }

    .tab-button {
      padding: 12px 20px;
      background: none;
      border: none;
      border-bottom: 3px solid transparent;
      cursor: pointer;
      font-weight: 600;
      color: var(--text-light);
      transition: var(--transition);
    }

    .tab-button.active {
      color: var(--primary-red);
      border-bottom-color: var(--primary-red);
    }

    .tab-button:hover:not(.active) {
      color: var(--text-dark);
      background-color: var(--border-light);
    }

    .tab-content {
      display: none;
      animation: fadeIn 0.3s ease;
    }

    .tab-content.active {
      display: block;
    }

    .info-cards {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
      gap: 15px;
      margin-bottom: 30px;
    }

    .info-card {
      background-color: white;
      border: 1px solid var(--border-light);
      border-radius: 10px;
      padding: 20px;
      transition: var(--transition);
    }

    .info-card:hover {
      box-shadow: var(--shadow-light);
    }

    .info-card-title {
      font-size: 14px;
      color: var(--text-light);
      margin-bottom: 5px;
    }

    .info-card-value {
      font-size: 20px;
      font-weight: 700;
      color: var(--text-dark);
    }

    .filter-bar {
      display: flex;
      flex-wrap: wrap;
      gap: 15px;
      margin-bottom: 20px;
      align-items: center;
    }

    .search-input {
      flex: 1;
      min-width: 200px;
      position: relative;
    }

    .search-input input {
      width: 100%;
      padding: 10px 15px 10px 40px;
      border: 1px solid var(--border-light);
      border-radius: 8px;
      font-size: 14px;
    }

    .search-input i {
      position: absolute;
      left: 15px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--text-light);
    }

    .filter-select {
      padding: 10px 15px;
      border: 1px solid var(--border-light);
      border-radius: 8px;
      background-color: white;
      min-width: 150px;
    }

    .data-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 30px;
    }

    .data-table th, .data-table td {
      padding: 12px 15px;
      text-align: left;
      border-bottom: 1px solid var(--border-light);
    }

    .data-table th {
      background-color: #f9fafb;
      font-weight: 600;
      color: var(--text-dark);
    }

    .data-table tr:hover {
      background-color: #f9fafb;
    }

    .data-table .status {
      padding: 5px 10px;
      border-radius: 15px;
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
      display: inline-block;
    }

    .status.pending {
      background-color: #fff8e6;
      color: #b78105;
    }

    .status.approved {
      background-color: #e6f7ed;
      color: #0e7c42;
    }

    .status.completed {
      background-color: #e6eefb;
      color: #1e62cd;
    }

    .status.overdue {
      background-color: #fce8e8;
      color: #b91c1c;
    }

    .table-actions {
      display: flex;
      gap: 8px;
    }

    .btn {
      padding: 8px 16px;
      border: none;
      border-radius: 6px;
      font-size: 12px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      text-transform: uppercase;
      letter-spacing: 0.5px;
      position: relative;
      overflow: hidden;
      transform: translateY(0);
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .btn::before {
      content: '';
      position: absolute;
      top: 50%;
      left: 50%;
      width: 0;
      height: 0;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.3);
      transform: translate(-50%, -50%);
      transition: width 0.6s, height 0.6s;
    }

    .btn:active::before {
      width: 300px;
      height: 300px;
    }

    .btn-small {
      padding: 6px 12px;
      font-size: 11px;
    }

    .btn-primary {
      background: linear-gradient(135deg, var(--primary-red), #b91c3c);
      color: white;
      border: 1px solid transparent;
    }

    .btn-primary:hover {
      background: linear-gradient(135deg, #b91c3c, var(--primary-red));
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(220, 20, 60, 0.4);
    }

    .btn-primary:active {
      transform: translateY(0);
      box-shadow: 0 2px 4px rgba(220, 20, 60, 0.3);
    }

    .btn-outline {
      background: white;
      border: 1.5px solid #e0e0e0;
      color: var(--text-dark);
    }

    .btn-outline:hover {
      background: #f8f9fa;
      border-color: var(--primary-red);
      color: var(--primary-red);
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .btn-outline:active {
      transform: translateY(0);
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
      background: #f0f0f0;
    }

    .btn-secondary {
      background: #E60012;
      color: #ffffff;
    }

    .btn-secondary:hover {
      background: #c00010;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(230, 0, 18, 0.4);
    }

    .btn-secondary:active {
      transform: translateY(0);
      box-shadow: 0 2px 4px rgba(230, 0, 18, 0.3);
    }

    .btn i {
      transition: transform 0.3s ease;
    }

    .btn:hover i {
      transform: scale(1.1);
    }

    .action-area {
      display: flex;
      gap: 15px;
      margin-top: 30px;
      padding-top: 20px;
      border-top: 1px solid var(--border-light);
    }

    /* Sales Chart */
    .chart-container {
      background: white;
      border-radius: 10px;
      padding: 20px;
      margin-bottom: 30px;
      border: 1px solid var(--border-light);
      box-shadow: var(--shadow-light);
      height: 400px;
    }

    /* Reports display */
    .reports-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }

    .report-card {
      background: white;
      border-radius: 10px;
      padding: 20px;
      border: 1px solid var(--border-light);
      transition: var(--transition);
      cursor: pointer;
    }

    .report-card:hover {
      transform: translateY(-5px);
      box-shadow: var(--shadow-medium);
    }

    .report-card h3 {
      font-size: 18px;
      margin-bottom: 10px;
      color: var(--text-dark);
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .report-card p {
      color: var(--text-light);
      font-size: 14px;
      margin-bottom: 15px;
    }

    .transaction-details {
      background: white;
      border-radius: 10px;
      padding: 25px;
      margin-top: 20px;
      border: 1px solid var(--border-light);
      box-shadow: var(--shadow-light);
      display: none; /* Hidden initially */
      animation: fadeIn 0.3s ease;
    }

    .transaction-details.active {
      display: block;
    }

    .transaction-details h3 {
      margin-bottom: 20px;
      padding-bottom: 10px;
      border-bottom: 1px solid var(--border-light);
      font-size: 18px;
      color: var(--text-dark);
      font-weight: 600;
    }

    .details-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 20px;
      margin-bottom: 20px;
    }

    .detail-item {
      margin-bottom: 15px;
    }

    .detail-label {
      font-size: 12px;
      color: var(--text-light);
      margin-bottom: 5px;
    }

    .detail-value {
      font-size: 16px;
      font-weight: 600;
      color: var(--text-dark);
    }

    /* Responsive Design */
    @media (max-width: 575px) {
      .info-cards {
        grid-template-columns: 1fr;
      }
      .filter-bar {
        flex-direction: column;
        align-items: stretch;
      }
      .action-area {
        flex-direction: column;
      }
      .details-grid {
        grid-template-columns: 1fr;
      }
      .reports-grid {
        grid-template-columns: 1fr;
      }
    }

    @media (min-width: 576px) and (max-width: 767px) {
      .info-cards {
        grid-template-columns: repeat(2, 1fr);
      }
      .details-grid {
        grid-template-columns: 1fr;
      }
    }

    @media (min-width: 768px) and (max-width: 991px) {
      .info-cards {
        grid-template-columns: repeat(2, 1fr);
      }
    }

    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    /* Notification Styles */
    .notification {
      position: fixed;
      top: 20px;
      right: 20px;
      background: white;
      border-radius: 8px;
      padding: 1rem 1.5rem;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
      display: flex;
      align-items: center;
      gap: 0.75rem;
      z-index: 10002;
      min-width: 300px;
      max-width: 500px;
      animation: slideIn 0.3s ease-out;
    }

    @keyframes slideIn {
      from {
        transform: translateX(100%);
        opacity: 0;
      }
      to {
        transform: translateX(0);
        opacity: 1;
      }
    }

    .notification-success {
      border-left: 4px solid #28a745;
      color: #155724;
    }

    .notification-success i {
      color: #28a745;
    }

    .notification-error {
      border-left: 4px solid #dc3545;
      color: #721c24;
    }

    .notification-error i {
      color: #dc3545;
    }

    .notification-warning {
      border-left: 4px solid #fd7e14;
      color: #856404;
    }

    .notification-warning i {
      color: #fd7e14;
    }

    .notification-info {
      border-left: 4px solid #007bff;
      color: #004085;
    }

    .notification-info i {
      color: #007bff;
    }

    .notification-close {
      background: none;
      border: none;
      cursor: pointer;
      color: #666;
      margin-left: auto;
      padding: 0.25rem;
      border-radius: 4px;
      transition: all 0.2s ease;
    }

    .notification-close:hover {
      background: #f5f5f5;
      color: #333;
    }

    /* Receipt Modal Styles */
    .receipt-modal-overlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.5);
      z-index: 10001;
      align-items: center;
      justify-content: center;
      padding: 1rem;
    }

    .receipt-modal-overlay.active {
      display: flex;
    }

    .receipt-modal {
      background: white;
      border-radius: 10px;
      padding: 30px;
      max-width: 800px;
      width: 100%;
      max-height: 90vh;
      overflow-y: auto;
      position: relative;
    }

    .receipt-modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
      border-bottom: 1px solid var(--border-light);
      padding-bottom: 15px;
    }

    .receipt-modal-header h3 {
      margin: 0;
      font-size: 1.25rem;
      color: var(--text-dark);
    }

    .receipt-modal-close {
      background: none;
      border: none;
      font-size: 1.5rem;
      cursor: pointer;
      color: var(--text-light);
      padding: 0;
      width: 30px;
      height: 30px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .receipt-modal-close:hover {
      color: var(--text-dark);
    }

    .receipt-modal-body {
      text-align: center;
    }

    .receipt-modal-body img {
      max-width: 100%;
      height: auto;
      border-radius: 8px;
      box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }

    .receipt-loading {
      text-align: center;
      padding: 40px;
      color: var(--text-light);
    }

    .receipt-loading i {
      font-size: 2rem;
      margin-bottom: 15px;
    }

    .receipt-error {
      text-align: center;
      padding: 40px;
      color: var(--text-light);
    }

    .receipt-error i {
      font-size: 3rem;
      margin-bottom: 15px;
      color: #999;
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
          <i class="fas fa-file-invoice"></i>
          Admin Transaction Records
        </h1>
      </div>

      <div class="tab-navigation">
        <button class="tab-button active" data-tab="transaction-completed">Completed Transactions</button>
        <button class="tab-button" data-tab="transaction-pending">Pending Transactions</button>
      </div>

      <!-- Completed Transactions Tab -->
      <div class="tab-content active" id="transaction-completed">
        <div class="info-cards">
          <div class="info-card">
            <div class="info-card-title">Total Transactions</div>
            <div class="info-card-value" id="completed-total">-</div>
          </div>
          <div class="info-card">
            <div class="info-card-title">This Month</div>
            <div class="info-card-value" id="completed-month">-</div>
          </div>
          <div class="info-card">
            <div class="info-card-title">Total Sales Value</div>
            <div class="info-card-value" id="completed-total-sales">-</div>
          </div>
          <div class="info-card">
            <div class="info-card-title">Average Sale</div>
            <div class="info-card-value" id="completed-avg-sale">-</div>
          </div>
        </div>

        <div class="filter-bar">
          <div class="search-input">
            <i class="fas fa-search"></i>
            <input type="text" id="completed-search" placeholder="Search transactions...">
          </div>
          <select class="filter-select" id="completed-model">
            <option value="">All Models</option>
            <option value="montero">Montero Sport</option>
            <option value="xpander">Xpander</option>
            <option value="mirage">Mirage G4</option>
            <option value="strada">Strada</option>
          </select>
          <select class="filter-select" id="completed-agent">
            <option value="">All Agents</option>
            <option value="carlos">Carlos Mendoza</option>
            <option value="ana">Ana Santos</option>
            <option value="juan">Juan Reyes</option>
          </select>
          <select class="filter-select" id="completed-date">
            <option value="">Date Range</option>
            <option value="today">Today</option>
            <option value="week">This Week</option>
            <option value="month">This Month</option>
            <option value="quarter">This Quarter</option>
          </select>
        </div>

        <table class="data-table">
          <thead>
            <tr>
              <th>Transaction ID</th>
              <th>Customer Name</th>
              <th>Vehicle Name</th>
              <th>Total Amount</th>
              <th>Agent Name</th>
            </tr>
          </thead>
          <tbody id="completed-tbody">
            <tr><td colspan="5" style="text-align:center;color:var(--text-light);">Loading...</td></tr>
          </tbody>
        </table>
        <div class="pagination" style="display:flex;align-items:center;gap:12px;justify-content:flex-end;margin-top:10px;">
          <button class="btn btn-outline" id="completed-prev">Prev</button>
          <span id="completed-page-info" style="color:var(--text-light);">Page 1</span>
          <button class="btn btn-outline" id="completed-next">Next</button>
        </div>

        <div class="action-area">
          <button class="btn btn-primary" id="btn-export-completed">
            <i class="fas fa-file-pdf"></i> Export to PDF
          </button>
          <!-- <button class="btn btn-secondary">Generate Report</button>
          <button class="btn btn-outline">Print Summary</button> -->
        </div>

        <!-- Transaction Details Pane (Hidden by default) -->
        <div class="transaction-details" id="transactionDetails">
          <h3>Transaction Details: <span id="txnIdDisplay">TXN-2024-089</span></h3>

          <div class="details-grid">
            <div class="detail-item">
              <div class="detail-label">Client Name</div>
              <div class="detail-value">Emily Torres</div>
            </div>
            <div class="detail-item">
              <div class="detail-label">Contact Information</div>
              <div class="detail-value">emily@email.com<br>+63 917 123 4567</div>
            </div>
            <div class="detail-item">
              <div class="detail-label">Vehicle</div>
              <div class="detail-value">Montero Sport GLS Premium 2024</div>
            </div>
            <div class="detail-item">
              <div class="detail-label">Specifications</div>
              <div class="detail-value">White Pearl, 4WD, Diesel</div>
            </div>
            <div class="detail-item">
              <div class="detail-label">Sales Price</div>
              <div class="detail-value">₱2,398,000</div>
            </div>
            <div class="detail-item">
              <div class="detail-label">Payment Method</div>
              <div class="detail-value">Bank Financing (BDO)</div>
            </div>
            <div class="detail-item">
              <div class="detail-label">Sales Agent</div>
              <div class="detail-value">Carlos Mendoza</div>
            </div>
            <div class="detail-item">
              <div class="detail-label">Commission</div>
              <div class="detail-value">₱59,950</div>
            </div>
            <div class="detail-item">
              <div class="detail-label">Order Date</div>
              <div class="detail-value">Mar 15, 2024</div>
            </div>
            <div class="detail-item">
              <div class="detail-label">Completion Date</div>
              <div class="detail-value">Mar 23, 2024</div>
            </div>
          </div>

          <!-- Financial Breakdown Section -->
          <h4 style="margin-top: 30px; margin-bottom: 15px; color: var(--primary-red);">💰 Financial Breakdown</h4>
          <div class="details-grid">
            <div class="detail-item">
              <div class="detail-label">Base Price (SRP)</div>
              <div class="detail-value" id="txn-base-price">-</div>
            </div>
            <div class="detail-item">
              <div class="detail-label">Total Unit Price</div>
              <div class="detail-value" id="txn-total-unit-price">-</div>
            </div>
            <div class="detail-item">
              <div class="detail-label">Nominal Discount</div>
              <div class="detail-value" id="txn-nominal-discount">-</div>
            </div>
            <div class="detail-item">
              <div class="detail-label">Promo Discount</div>
              <div class="detail-value" id="txn-promo-discount">-</div>
            </div>
            <div class="detail-item">
              <div class="detail-label">Amount to Invoice</div>
              <div class="detail-value" id="txn-amount-to-invoice" style="font-weight: bold;">-</div>
            </div>
            <div class="detail-item">
              <div class="detail-label">Down Payment</div>
              <div class="detail-value" id="txn-down-payment">-</div>
            </div>
            <div class="detail-item">
              <div class="detail-label">Net Down Payment</div>
              <div class="detail-value" id="txn-net-down-payment">-</div>
            </div>
            <div class="detail-item">
              <div class="detail-label">Total Incidentals</div>
              <div class="detail-value" id="txn-total-incidentals">-</div>
            </div>
            <div class="detail-item">
              <div class="detail-label">Reservation Fee</div>
              <div class="detail-value" id="txn-reservation-fee">-</div>
            </div>
            <div class="detail-item">
              <div class="detail-label" style="font-weight: bold; color: var(--primary-red);">Total Cash Outlay</div>
              <div class="detail-value" id="txn-total-cash-outlay" style="font-weight: bold; color: var(--primary-red);">-</div>
            </div>
          </div>

          <div class="action-area">
            <button class="btn btn-primary">Print Invoice</button>
            <button class="btn btn-secondary">Export Details</button>
            <button class="btn btn-outline" onclick="closeTransactionDetails()">Close Details</button>
          </div>
        </div>
      </div>

      <!-- Pending Transactions Tab -->
      <div class="tab-content" id="transaction-pending">
        <div class="info-cards">
          <div class="info-card">
            <div class="info-card-title">Total Pending</div>
            <div class="info-card-value" id="pending-total">-</div>
          </div>
          <div class="info-card">
            <div class="info-card-title">Financing Approval</div>
            <div class="info-card-value" id="pending-month">-</div>
          </div>
          <div class="info-card">
            <div class="info-card-title">Document Verification</div>
            <div class="info-card-value" id="pending-docs">-</div>
          </div>
          <div class="info-card">
            <div class="info-card-title">Pending Value</div>
            <div class="info-card-value" id="pending-value">-</div>
          </div>
        </div>
        
        <table class="data-table">
          <thead>
            <tr>
              <th>Transaction ID</th>
              <th>Date</th>
              <th>Customer Name</th>
              <th>Vehicle Name</th>
              <th>Amount</th>
              <th>Agent Name</th>
              <th>View Receipt</th>
            </tr>
          </thead>
          <tbody id="pending-tbody">
            <tr><td colspan="7" style="text-align:center;color:var(--text-light);">Loading...</td></tr>
          </tbody>
        </table>
        
        <div class="pagination" style="display:flex;align-items:center;gap:12px;justify-content:flex-end;margin-top:10px;">
          <button class="btn btn-outline" id="pending-prev">Prev</button>
          <span id="pending-page-info" style="color:var(--text-light);">Page 1</span>
          <button class="btn btn-outline" id="pending-next">Next</button>
        </div>

        <div class="action-area">
          <!-- <button class="btn btn-primary">Send Bulk Reminders</button> -->
          <button class="btn btn-secondary" id="btn-export-pending">
            <i class="fas fa-file-pdf"></i> Export to PDF
          </button>
        </div>
      </div>

    </div>
  </div>

  <!-- Receipt Viewer Modal -->
  <div class="receipt-modal-overlay" id="receiptModal">
    <div class="receipt-modal">
      <div class="receipt-modal-header">
        <h3>Payment Receipt</h3>
        <button class="receipt-modal-close" onclick="closeReceiptModal()">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div class="receipt-modal-body">
        <div class="receipt-loading" id="receiptLoading">
          <i class="fas fa-spinner fa-spin"></i>
          <p>Loading receipt...</p>
        </div>
        <div class="receipt-error" id="receiptError" style="display: none;">
          <i class="fas fa-file-image"></i>
          <p>No receipt uploaded</p>
        </div>
        <img id="receiptImage" src="" alt="Payment Receipt" style="display: none;">
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
          
          // Hide transaction details when switching tabs
          document.getElementById('transactionDetails').style.display = 'none';
        });
      });

      // Backend endpoint
      const TXN_API = '../../includes/backend/transactions_backend.php';
      const PAGE_LIMIT = 10;
      let completedPage = 1, completedTotal = 0;
      let pendingPage = 1, pendingTotal = 0;

      // Load stats
      async function loadStats(status) {
        const fd = new FormData();
        fd.append('action', 'get_stats');
        fd.append('status', status);
        const res = await fetch(TXN_API, { method: 'POST', body: fd });
        const json = await res.json();
        if (!json.success) return;
        const d = json.data;
        if (status === 'completed') {
          document.getElementById('completed-total').textContent = d.total.toLocaleString();
          document.getElementById('completed-month').textContent = d.this_month.toLocaleString();
          document.getElementById('completed-total-sales').textContent = `₱${Number(d.total_sales_value).toLocaleString(undefined,{maximumFractionDigits:0})}`;
          document.getElementById('completed-avg-sale').textContent = `₱${Number(d.avg_sale).toLocaleString(undefined,{maximumFractionDigits:0})}`;
        } else if (status === 'pending') {
          document.getElementById('pending-total').textContent = d.total.toLocaleString();
          document.getElementById('pending-month').textContent = d.this_month.toLocaleString();
          // Simple derivations for placeholders
          document.getElementById('pending-docs').textContent = '-';
          document.getElementById('pending-value').textContent = '-';
        }
      }

      // Render rows helper
      function renderCompletedRows(list) {
        const tbody = document.getElementById('completed-tbody');
        if (!list || list.length === 0) {
          tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--text-light);">No transactions found</td></tr>';
          return;
        }
        tbody.innerHTML = list.map(item => {
          const price = `₱${Number(item.sale_price||0).toLocaleString(undefined,{maximumFractionDigits:0})}`;
          const vm = `${item.vehicle_model||''} ${item.variant?(' '+item.variant):''}`;
          const txn = item.transaction_id || '';
          return `
            <tr>
              <td>${txn}</td>
              <td>${item.client_name||'N/A'}</td>
              <td>${vm}</td>
              <td>${price}</td>
              <td>${item.agent_name||''}</td>
            </tr>`;
        }).join('');
      }

      function renderPendingRows(list) {
        const tbody = document.getElementById('pending-tbody');
        if (!list || list.length === 0) {
          tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;color:var(--text-light);">No pending transactions</td></tr>';
          return;
        }
        tbody.innerHTML = list.map(item => {
          const price = `₱${Number(item.sale_price||0).toLocaleString(undefined,{maximumFractionDigits:0})}`;
          const vm = `${item.vehicle_model||''} ${item.variant?(' '+item.variant):''}`;
          const date = item.latest_payment_date ? new Date(item.latest_payment_date).toLocaleDateString() : (item.date_completed ? new Date(item.date_completed).toLocaleDateString() : '-');
          const paymentType = item.latest_payment_type || '-';
          const refNumber = item.latest_payment_reference || '-';
          const hasReceipt = item.receipt_filename && item.latest_payment_id;

          return `
            <tr>
              <td>${item.transaction_id||''}</td>
              <td>${date}</td>
              <td>${item.client_name||'N/A'}</td>
              <td>${vm}</td>
              <td>${price}</td>
              <td>${item.agent_name||''}</td>
              <td class="table-actions">
                <button class="btn btn-small btn-outline" onclick="viewReceipt(${item.latest_payment_id || 0})">
                  <i class="fas fa-receipt"></i> View
                </button>
                <button class="btn btn-small btn-primary" onclick="viewAllReceipts(${item.order_id})">
                  <i class="fas fa-list"></i> All
                </button>
              </td>
            </tr>`;
        }).join('');
      }

      // Load lists
      async function loadTransactions(status, page=1) {
        const fd = new FormData();
        fd.append('action', 'get_transactions');
        fd.append('status', status);
        if (status === 'completed') {
          fd.append('search', document.getElementById('completed-search').value || '');
          fd.append('model', document.getElementById('completed-model').value || '');
          const agentId = document.getElementById('completed-agent').value || '';
          if (agentId) fd.append('agent_id', agentId);
          const { from, to } = getDateRange(document.getElementById('completed-date').value || '');
          if (from) fd.append('date_from', from);
          if (to) fd.append('date_to', to);
        }
        fd.append('page', String(page));
        fd.append('limit', String(PAGE_LIMIT));
        const res = await fetch(TXN_API, { method: 'POST', body: fd });
        const json = await res.json();
        if (!json.success) return;
        const list = json.data?.transactions || [];
        if (status === 'completed') {
          completedTotal = json.data?.total || 0;
          completedPage = page;
          renderCompletedRows(list);
          updatePageInfo('completed', completedPage, completedTotal, PAGE_LIMIT);
        } else {
          pendingTotal = json.data?.total || 0;
          pendingPage = page;
          renderPendingRows(list);
          updatePageInfo('pending', pendingPage, pendingTotal, PAGE_LIMIT);
        }
      }

      function updatePageInfo(kind, page, total, limit) {
        const totalPages = Math.max(1, Math.ceil(total / limit));
        const infoEl = document.getElementById(kind + '-page-info');
        if (infoEl) infoEl.textContent = `Page ${page} of ${totalPages}`;
        const prevBtn = document.getElementById(kind + '-prev');
        const nextBtn = document.getElementById(kind + '-next');
        if (prevBtn) prevBtn.disabled = page <= 1;
        if (nextBtn) nextBtn.disabled = page >= totalPages;
      }

      function getDateRange(preset) {
        const today = new Date();
        today.setHours(0,0,0,0);
        let from = null, to = null;
        if (preset === 'today') {
          from = toISO(today);
          to = toISO(today);
        } else if (preset === 'week') {
          const first = new Date(today);
          first.setDate(today.getDate() - 6);
          from = toISO(first);
          to = toISO(today);
        } else if (preset === 'month') {
          const first = new Date(today.getFullYear(), today.getMonth(), 1);
          from = toISO(first);
          to = toISO(today);
        } else if (preset === 'quarter') {
          const q = Math.floor(today.getMonth() / 3);
          const first = new Date(today.getFullYear(), q * 3, 1);
          from = toISO(first);
          to = toISO(today);
        }
        return { from, to };
      }

      function toISO(d) {
        const y = d.getFullYear();
        const m = String(d.getMonth()+1).padStart(2,'0');
        const da = String(d.getDate()).padStart(2,'0');
        return `${y}-${m}-${da}`;
      }

      // Initialize
      async function loadFilters() {
        try {
          const fd = new FormData();
          fd.append('action', 'get_filters');
          const res = await fetch(TXN_API, { method: 'POST', body: fd });
          const json = await res.json();
          if (!json.success) return;
          const agents = json.data?.agents || [];
          const models = json.data?.models || [];
          const agentSel = document.getElementById('completed-agent');
          if (agentSel) {
            agentSel.innerHTML = '<option value="">All Agents</option>' + agents.map(a => `<option value="${a.Id}">${a.name}</option>`).join('');
          }
          const modelSel = document.getElementById('completed-model');
          if (modelSel && models.length) {
            modelSel.innerHTML = '<option value="">All Models</option>' + models.map(m => `<option value="${m}">${m}</option>`).join('');
          }
        } catch (e) { console.error(e); }
      }

      loadFilters().then(() => {
        loadStats('completed');
        loadStats('pending');
        loadTransactions('completed', 1);
        loadTransactions('pending', 1);
      });

      // Filters
      const completedSearch = document.getElementById('completed-search');
      const completedModel = document.getElementById('completed-model');
      if (completedSearch) completedSearch.addEventListener('input', () => { loadTransactions('completed', 1); });
      if (completedModel) completedModel.addEventListener('change', () => { loadTransactions('completed', 1); });
      const completedAgent = document.getElementById('completed-agent');
      const completedDate = document.getElementById('completed-date');
      if (completedAgent) completedAgent.addEventListener('change', () => { loadTransactions('completed', 1); });
      if (completedDate) completedDate.addEventListener('change', () => { loadTransactions('completed', 1); });

      // Pagination buttons
      const cPrev = document.getElementById('completed-prev');
      const cNext = document.getElementById('completed-next');
      if (cPrev) cPrev.addEventListener('click', () => { if (completedPage > 1) loadTransactions('completed', completedPage - 1); });
      if (cNext) cNext.addEventListener('click', () => { const totalPages = Math.max(1, Math.ceil(completedTotal / PAGE_LIMIT)); if (completedPage < totalPages) loadTransactions('completed', completedPage + 1); });
      const pPrev = document.getElementById('pending-prev');
      const pNext = document.getElementById('pending-next');
      if (pPrev) pPrev.addEventListener('click', () => { if (pendingPage > 1) loadTransactions('pending', pendingPage - 1); });
      if (pNext) pNext.addEventListener('click', () => { const totalPages = Math.max(1, Math.ceil(pendingTotal / PAGE_LIMIT)); if (pendingPage < totalPages) loadTransactions('pending', pendingPage + 1); });

      // Export buttons - PDF export
      function buildQuery(params) {
        const esc = encodeURIComponent;
        return Object.keys(params).filter(k => params[k] !== undefined && params[k] !== null && params[k] !== '').map(k => esc(k) + '=' + esc(params[k])).join('&');
      }
      function exportNow(status) {
        const params = { status };
        if (status === 'completed') {
          params.search = document.getElementById('completed-search').value || '';
          params.model = document.getElementById('completed-model').value || '';
          params.agent_id = document.getElementById('completed-agent').value || '';
          const { from, to } = getDateRange(document.getElementById('completed-date').value || '');
          if (from) params.date_from = from;
          if (to) params.date_to = to;
        }
        const url = 'transaction_records_pdf.php?' + buildQuery(params);
        window.open(url, '_blank');
      }
      const btnExportCompleted = document.getElementById('btn-export-completed');
      if (btnExportCompleted) btnExportCompleted.addEventListener('click', () => exportNow('completed'));
      const btnExportPending = document.getElementById('btn-export-pending');
      if (btnExportPending) btnExportPending.addEventListener('click', () => exportNow('pending'));

      // Initialize functions for transaction details view
      window.viewTransaction = async function(btnEl) {
        const orderId = btnEl?.getAttribute('data-order-id');
        const txnId = btnEl?.getAttribute('data-txn') || '';
        if (txnId) document.getElementById('txnIdDisplay').textContent = txnId;

        try {
          if (orderId) {
            const fd = new FormData();
            fd.append('action', 'get_transaction_details');
            fd.append('order_id', orderId);
            const res = await fetch(TXN_API, { method: 'POST', body: fd });
            const json = await res.json();
            if (json.success) {
              const d = json.data;
              // Map a few key fields into the details panel
              document.querySelector('#transactionDetails .detail-value:nth-of-type(1)')?.innerHTML;
              // Fill known labels explicitly
              const container = document.getElementById('transactionDetails');
              const map = {
                clientName: `${d.firstname||''} ${d.lastname||''}`.trim(),
                contactInfo: `${d.email||''}<br>${d.mobile_number||''}`,
                vehicle: `${d.vehicle_model||d.model_name||''} ${d.vehicle_variant||d.variant||''} ${d.model_year||''}`.trim(),
                specifications: '',
                salesPrice: `₱${Number(d.total_price||0).toLocaleString(undefined,{maximumFractionDigits:0})}`,
                paymentMethod: d.payment_method||'',
                agent: d.agent_name||'',
                commission: '',
                orderDate: d.created_at ? new Date(d.created_at).toLocaleDateString() : '',
                completionDate: d.actual_delivery_date ? new Date(d.actual_delivery_date).toLocaleDateString() : ''
              };
              // Apply to existing static layout using order of .detail-item blocks
              const items = container.querySelectorAll('.detail-item .detail-value');
              if (items.length >= 10) {
                items[0].innerHTML = map.clientName;
                items[1].innerHTML = map.contactInfo;
                items[2].innerHTML = map.vehicle;
                items[3].innerHTML = map.specifications;
                items[4].innerHTML = map.salesPrice;
                items[5].innerHTML = map.paymentMethod;
                items[6].innerHTML = map.agent;
                items[7].innerHTML = map.commission;
                items[8].innerHTML = map.orderDate;
                items[9].innerHTML = map.completionDate;
              }

              // Populate financial breakdown fields
              document.getElementById('txn-base-price').innerHTML = `₱${Number(d.base_price||0).toLocaleString(undefined,{maximumFractionDigits:2})}`;
              document.getElementById('txn-total-unit-price').innerHTML = `₱${Number(d.total_unit_price||0).toLocaleString(undefined,{maximumFractionDigits:2})}`;
              document.getElementById('txn-nominal-discount').innerHTML = `₱${Number(d.nominal_discount||0).toLocaleString(undefined,{maximumFractionDigits:2})}`;
              document.getElementById('txn-promo-discount').innerHTML = `₱${Number(d.promo_discount||0).toLocaleString(undefined,{maximumFractionDigits:2})}`;
              document.getElementById('txn-amount-to-invoice').innerHTML = `₱${Number(d.amount_to_invoice||0).toLocaleString(undefined,{maximumFractionDigits:2})}`;
              document.getElementById('txn-down-payment').innerHTML = `₱${Number(d.down_payment||0).toLocaleString(undefined,{maximumFractionDigits:2})}`;
              document.getElementById('txn-net-down-payment').innerHTML = `₱${Number(d.net_down_payment||0).toLocaleString(undefined,{maximumFractionDigits:2})}`;
              document.getElementById('txn-total-incidentals').innerHTML = `₱${Number(d.total_incidentals||0).toLocaleString(undefined,{maximumFractionDigits:2})}`;
              document.getElementById('txn-reservation-fee').innerHTML = `₱${Number(d.reservation_fee||0).toLocaleString(undefined,{maximumFractionDigits:2})}`;
              document.getElementById('txn-total-cash-outlay').innerHTML = `₱${Number(d.total_cash_outlay||0).toLocaleString(undefined,{maximumFractionDigits:2})}`;
            }
          }
        } catch (e) {
          console.error(e);
        }

        document.getElementById('transactionDetails').style.display = 'block';
        document.getElementById('transactionDetails').scrollIntoView({ behavior: 'smooth' });
      };
      
      window.closeTransactionDetails = function() {
        document.getElementById('transactionDetails').style.display = 'none';
      };

      // Receipt viewing functions
      window.viewReceipt = async function(paymentId) {
        const modal = document.getElementById('receiptModal');
        const loading = document.getElementById('receiptLoading');
        const error = document.getElementById('receiptError');
        const image = document.getElementById('receiptImage');

        // Show modal
        modal.classList.add('active');
        loading.style.display = 'block';
        error.style.display = 'none';
        image.style.display = 'none';

        if (!paymentId || paymentId === 0) {
          // No receipt available
          loading.style.display = 'none';
          error.style.display = 'block';
          return;
        }

        try {
          // Fetch receipt
          const response = await fetch(`${TXN_API}?action=get_receipt&payment_id=${paymentId}`);

          if (!response.ok) {
            throw new Error('Receipt not found');
          }

          // Convert to blob and create object URL
          const blob = await response.blob();
          const imageUrl = URL.createObjectURL(blob);

          // Display image
          image.src = imageUrl;
          loading.style.display = 'none';
          image.style.display = 'block';
        } catch (err) {
          console.error('Error loading receipt:', err);
          loading.style.display = 'none';
          error.style.display = 'block';
        }
      };

      window.closeReceiptModal = function() {
        const modal = document.getElementById('receiptModal');
        const image = document.getElementById('receiptImage');

        modal.classList.remove('active');

        // Clean up object URL to prevent memory leaks
        if (image.src && image.src.startsWith('blob:')) {
          URL.revokeObjectURL(image.src);
        }
        image.src = '';
      };

      // Notification System
      function showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
          <span>${message}</span>
          <button onclick="this.parentElement.remove()" class="notification-close">
            <i class="fas fa-times"></i>
          </button>
        `;

        // Add to page
        document.body.appendChild(notification);

        // Auto remove after 5 seconds
        setTimeout(() => {
          if (notification.parentElement) {
            notification.remove();
          }
        }, 5000);
      }

      function showSuccess(message) {
        showNotification(message, 'success');
      }

      function showError(message) {
        showNotification(message, 'error');
      }

      function showWarning(message) {
        showNotification(message, 'warning');
      }

      function showInfo(message) {
        showNotification(message, 'info');
      }

      window.viewAllReceipts = async function(orderId) {
        if (!orderId) {
          showError('Invalid order');
          return;
        }

        try {
          const res = await fetch(`${TXN_API}?action=get_all_receipts&order_id=${orderId}`);
          const json = await res.json();

          if (!json.success || !json.data || json.data.length === 0) {
            showWarning('No receipts found for this order');
            return;
          }

          const receipts = json.data;
          let tableRows = receipts.map(r => {
            const date = r.payment_date ? new Date(r.payment_date).toLocaleDateString() : '-';
            const amount = `₱${Number(r.amount_paid||0).toLocaleString(undefined,{maximumFractionDigits:0})}`;
            return `
              <tr>
                <td>${r.payment_number||''}</td>
                <td>${date}</td>
                <td>${r.payment_type||''}</td>
                <td>${amount}</td>
                <td>${r.reference_number||'-'}</td>
                <td>
                  <button class="btn btn-small btn-outline" onclick="viewReceipt(${r.id || 0})">
                    <i class="fas fa-eye"></i> View
                  </button>
                </td>
              </tr>
            `;
          }).join('');

          // Show receipts in a modal-style overlay
          const overlay = document.createElement('div');
          overlay.id = 'receiptsOverlay';
          overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
          `;

          overlay.innerHTML = `
            <div style="background: white; border-radius: 0.625rem; padding: 1.875rem; max-width: 60rem; width: 100%; max-height: 90%; overflow-y: auto;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; border-bottom: 0.0625rem solid #e5e7eb; padding-bottom: 0.625rem;">
                <h3 style="margin: 0; font-size: 1.25rem; color: #1f2937;">All Payment Receipts</h3>
                <button onclick="closeReceiptsOverlay()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280;">&times;</button>
              </div>
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Payment #</th>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Reference</th>
                    <th>Receipt</th>
                  </tr>
                </thead>
                <tbody>
                  ${tableRows}
                </tbody>
              </table>
            </div>
          `;

          document.body.appendChild(overlay);

          overlay.addEventListener('click', function(e) {
            if (e.target === overlay) {
              closeReceiptsOverlay();
            }
          });
        } catch (error) {
          console.error('Error loading receipts:', error);
          showError('Failed to load receipts');
        }
      };

      window.closeReceiptsOverlay = function() {
        const overlay = document.getElementById('receiptsOverlay');
        if (overlay) {
          overlay.remove();
        }
      };

      // Close receipt modal when clicking outside
      const receiptModal = document.getElementById('receiptModal');
      if (receiptModal) {
        receiptModal.addEventListener('click', function(e) {
          if (e.target === receiptModal) {
            closeReceiptModal();
          }
        });
      }
    });
  </script>
</body>
</html>
