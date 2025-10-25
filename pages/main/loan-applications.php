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
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Loan Application Approval - Sales Agent</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link href="../../includes/css/common-styles.css" rel="stylesheet">
  <link href="../../includes/css/orders-styles.css" rel="stylesheet">
  <style>
    body{
      zoom: 85%;
    } 
    /* Enable scroll inside table area */
    .table-responsive {
      max-height: 70vh;
      overflow-y: auto;
      overflow-x: auto;
    }
    
    /* Recalculation and Amortization Styles */
    .btn-recalculate {
      background: #007bff;
      color: white;
      border: none;
      padding: 5px 10px;
      border-radius: 4px;
      font-size: 12px;
      cursor: pointer;
      margin-left: 10px;
    }
    
    .btn-recalculate:hover {
      background: #0056b3;
    }
    
    .payment-plan-actions {
      margin-top: 15px;
      text-align: center;
    }
    
    .btn-show-amortization, .btn-toggle-amortization {
      background: #28a745;
      color: white;
      border: none;
      padding: 8px 16px;
      border-radius: 4px;
      cursor: pointer;
      font-size: 14px;
    }
    
    .btn-show-amortization:hover, .btn-toggle-amortization:hover {
      background: #1e7e34;
    }
    
    .amortization-section {
      margin-top: 20px;
      padding: 15px;
      background: #f8f9fa;
      border-radius: 8px;
    }
    
    .amortization-table-container {
      max-height: 400px;
      overflow-y: auto;
      margin-bottom: 15px;
    }
    
    .amortization-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 12px;
    }
    
    .amortization-table th,
    .amortization-table td {
      padding: 8px;
      text-align: right;
      border: 1px solid #dee2e6;
    }
    
    .amortization-table th {
      background: #e9ecef;
      font-weight: 600;
      position: sticky;
      top: 0;
    }
    
    .amortization-table tbody tr:nth-child(even) {
      background: #f8f9fa;
    }
    
    /* Recalculation Modal Styles */
    .recalculate-form {
      display: grid;
      gap: 15px;
      margin-top: 15px;
    }
    
    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 15px;
    }
    
    .form-group {
      display: flex;
      flex-direction: column;
    }
    
    .form-group label {
      font-weight: 600;
      margin-bottom: 5px;
      color: #333;
    }
    
    .form-group input, .form-group select {
      padding: 8px 12px;
      border: 1px solid #ddd;
      border-radius: 4px;
      font-size: 14px;
    }
    
    .validation-message {
      margin-top: 15px;
      padding: 10px;
      border-radius: 4px;
      font-size: 14px;
    }
    
    .validation-success {
      background: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }
    
    .validation-error {
      background: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
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
          <i class="fas fa-file-contract icon-gradient"></i>
          Loan Application Approval
        </h1>
      </div>

      <!-- Sales Agent Statistics -->
      <div class="sales-agent-stats">
        <div class="stat-card">
          <div class="stat-icon orange">
            <i class="fas fa-clock"></i>
          </div>
          <div class="stat-info">
            <h3 id="pendingCount">0</h3>
            <p>Pending Review</p>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon blue">
            <i class="fas fa-file-text"></i>
          </div>
          <div class="stat-info">
            <h3 id="underReviewCount">0</h3>
            <p>Under Review</p>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon green">
            <i class="fas fa-check-circle"></i>
          </div>
          <div class="stat-info">
            <h3 id="approvedCount">0</h3>
            <p>Approved</p>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon red">
            <i class="fas fa-times-circle"></i>
          </div>
          <div class="stat-info">
            <h3 id="rejectedCount">0</h3>
            <p>Rejected</p>
          </div>
        </div>
      </div>

      <div class="filters-section">
        <div class="filter-row">
          <div class="filter-group">
            <label for="application-search">Search Applications</label>
            <input type="text" id="application-search" class="filter-input" placeholder="Customer name, phone number, or vehicle model">
            <div class="search-hint">Try searching "John", "+639123456789", or "Montero"</div>
          </div>
          <div class="filter-group">
            <label for="application-status">Application Status</label>
            <select id="application-status" class="filter-select">
              <option value="all">All Statuses</option>
              <option value="Pending">Pending</option>
              <option value="Under Review">Under Review</option>
              <option value="Approved">Approved</option>
              <option value="Rejected">Rejected</option>
            </select>
          </div>
          <div class="filter-group">
            <label for="application-date">Date Range</label>
            <select id="application-date" class="filter-select">
              <option value="all">All Time</option>
              <option value="today">Today</option>
              <option value="week">This Week</option>
              <option value="month">This Month</option>
            </select>
          </div>
          <button class="filter-btn" onclick="applyFilters()">Apply Filters</button>
          <button class="filter-btn" onclick="refreshData()" style="background: #17a2b8;">
            <i class="fas fa-refresh"></i> Refresh
          </button>
        </div>
      </div>

      <!-- Loan Applications Table -->
      <div class="client-orders-section">
        <div class="section-header">
          <h2 class="section-title">
            <i class="fas fa-list"></i>
            <span id="sectionTitle">Loan Application Management</span>
          </h2>
        </div>
        <div class="table-responsive">
          <table class="table">
            <thead>
              <tr>
                <th>Customer</th>
                <th>Vehicle</th>
                <th>Loan Amount</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="applicationsTableBody">
              <tr>
                <td colspan="5" class="text-center">Loading loan applications...</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Document Review Modal -->
  <div class="modal-overlay" id="documentModal">
    <div class="modal" style="max-width: 800px;">
      <div class="modal-header">
        <h3>Review Loan Application Documents</h3>
        <button class="modal-close" onclick="closeDocumentModal()">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
        <div id="applicationDetailsContainer">
          <!-- Application details will be loaded here -->
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeDocumentModal()">Close</button>
        <button type="button" class="btn btn-success" onclick="approveFromReviewEnhanced()">
          <i class="fas fa-check-double"></i> Approve with Validation
        </button>
        <button type="button" class="btn btn-danger" onclick="rejectFromReview()">
          <i class="fas fa-times"></i> Reject Application
        </button>
      </div>
    </div>
  </div>

  <!-- Add SweetAlert CDN -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="../../includes/js/common-scripts.js"></script>

  <script>
    let allApplications = [];
    let filteredApplications = [];
    let currentApplicationId = null;

    // Load data when page loads
    document.addEventListener('DOMContentLoaded', function() {
      loadStatistics();
      loadLoanApplications();
    });

    function loadStatistics() {
      fetch('../../api/loan-applications.php?action=statistics')
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            document.getElementById('pendingCount').textContent = data.data['Pending'] || 0;
            document.getElementById('underReviewCount').textContent = data.data['Under Review'] || 0;
            document.getElementById('approvedCount').textContent = data.data['Approved'] || 0;
            document.getElementById('rejectedCount').textContent = data.data['Rejected'] || 0;
          }
        })
        .catch(error => {
          console.error('Error loading statistics:', error);
          showError('Failed to load statistics');
        });
    }

    function loadLoanApplications() {
      const search = document.getElementById('application-search').value.trim();
      const status = document.getElementById('application-status').value;
      const dateRange = document.getElementById('application-date').value;

      // Show loading state
      const tbody = document.getElementById('applicationsTableBody');
      tbody.innerHTML = '<tr><td colspan="5" class="text-center">Searching for applications...</td></tr>';

      const params = new URLSearchParams({
        action: 'applications',
        search: search,
        status: status,
        date_range: dateRange
      });

      fetch(`../../api/loan-applications.php?${params}`)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            allApplications = data.data;
            filteredApplications = data.data;
            displayApplications(data.data);

            // Update section title with more natural language
            updateSectionTitle(data.data.length, search, status, dateRange);
          } else {
            showError('Failed to load applications: ' + (data.error || 'Unknown error'));
          }
        })
        .catch(error => {
          console.error('Error loading applications:', error);
          showError('Failed to load loan applications');
        });
    }

    function updateSectionTitle(count, search, status, dateRange) {
      const sectionTitle = document.getElementById('sectionTitle');

      if (count === 0) {
        if (search || status !== 'all' || dateRange !== 'all') {
          sectionTitle.textContent = 'No applications found';
        } else {
          sectionTitle.textContent = 'No loan applications yet';
        }
        return;
      }

      // Create a more natural title
      let title = '';

      if (count === 1) {
        title = 'Found 1 application';
      } else {
        title = `Found ${count} applications`;
      }

      // Add filter context in natural language
      const filters = [];
      if (search) {
        if (search.match(/^\+?\d+/)) {
          filters.push(`with phone "${search}"`);
        } else if (search.match(/^\d+$/)) {
          filters.push(`#${search}`);
        } else {
          filters.push(`for "${search}"`);
        }
      }

      if (status !== 'all') {
        filters.push(`with ${status.toLowerCase()} status`);
      }

      if (dateRange !== 'all') {
        const timeFrames = {
          'today': 'from today',
          'week': 'from this week',
          'month': 'from this month'
        };
        filters.push(timeFrames[dateRange]);
      }

      if (filters.length > 0) {
        if (filters.length === 1) {
          title += ` ${filters[0]}`;
        } else if (filters.length === 2) {
          title += ` ${filters[0]} and ${filters[1]}`;
        } else {
          title += ` ${filters.slice(0, -1).join(', ')}, and ${filters[filters.length - 1]}`;
        }
      }

      sectionTitle.textContent = title;
    }

    function displayApplications(applications) {
      const tbody = document.getElementById('applicationsTableBody');

      if (applications.length === 0) {
        const search = document.getElementById('application-search').value.trim();
        const status = document.getElementById('application-status').value;
        const dateRange = document.getElementById('application-date').value;

        let noResultsMessage = '';

        if (search || status !== 'all' || dateRange !== 'all') {
          noResultsMessage = 'No applications match your search';
          noResultsMessage += '<br><small style="color: #666;">Try different keywords or clear some filters</small>';
        } else {
          noResultsMessage = 'No loan applications submitted yet';
          noResultsMessage += '<br><small style="color: #666;">Applications will appear here once customers submit them</small>';
        }

        tbody.innerHTML = `<tr><td colspan="5" class="text-center">${noResultsMessage}</td></tr>`;
        return;
      }

      tbody.innerHTML = applications.map(app => `
        <tr>
          <td>
            <div class="customer-info">
              <strong>${app.customer_name || 'Unknown Customer'}</strong>
              <div class="customer-meta">
                <span><i class="fas fa-phone"></i> ${app.mobile_number && app.mobile_number !== 'N/A' ? app.mobile_number : 'No phone number'}</span>
                <span><i class="fas fa-money-bill"></i> ${app.monthly_income > 0 ? '₱' + formatNumber(app.monthly_income) + '/month' : 'No income data'}</span>
              </div>
            </div>
          </td>
          <td>
            <div class="vehicle-info">
              <strong>${app.vehicle_name || 'Unknown Vehicle'}</strong>
              <div class="vehicle-meta">
                <span><i class="fas fa-cog"></i> ${app.vehicle_engine_type || 'N/A'} | ${app.vehicle_transmission || 'N/A'}</span>
                <span><i class="fas fa-gas-pump"></i> ${app.vehicle_fuel_type || 'N/A'} | ${app.vehicle_seating_capacity || 'N/A'} seats</span>
              </div>
            </div>
          </td>
          <td>
            <div class="amount-info">
              <strong>${app.base_price > 0 ? '₱' + formatNumber(app.base_price) : 'TBD'}</strong>
            </div>
          </td>
          <td>
            <span class="status-badge status-${app.status.toLowerCase().replace(' ', '-')}">
              ${app.status}
            </span>
          </td>
          <td>
            <div class="action-buttons">
              <button class="btn btn-small btn-info" onclick="reviewDocuments(${app.id})" title="Review Documents">
                <i class="fas fa-eye"></i>
              </button>
              ${app.status === 'Pending' || app.status === 'Under Review' ? `
                <!--<button class="btn btn-small btn-success" onclick="approveApplicationEnhanced(${app.id})" title="Approve with Validation">
                  <i class="fas fa-check-double"></i>
                </button>
                <button class="btn btn-small btn-danger" onclick="rejectApplication(${app.id})" title="Reject">
                  <i class="fas fa-times"></i>
                </button>-->
              ` : ''}
              ${app.status === 'Pending' ? `
                <button class="btn btn-small btn-warning" onclick="markUnderReview(${app.id})" title="Mark Under Review">
                  <i class="fas fa-clock"></i>
                </button>
              ` : ''}
            </div>
          </td>
        </tr>
      `).join('');
    }

    function reviewDocuments(applicationId) {
      currentApplicationId = applicationId;

      fetch(`../../api/loan-applications.php?action=application&id=${applicationId}`)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            displayApplicationDetails(data.data);
            document.getElementById('documentModal').classList.add('active');
          }
        })
        .catch(error => {
          console.error('Error loading application details:', error);
          showError('Failed to load application details');
        });
    }

    function displayApplicationDetails(application) {
      const container = document.getElementById('applicationDetailsContainer');

      // Use customer_details if available, otherwise fallback to basic info
      const customerDetails = application.customer_details || {};
      const vehicleDetails = application.vehicle_details || {};

      container.innerHTML = `
        <div class="detail-grid">
          <div class="detail-item">
            <label>Application ID:</label>
            <span>#${application.id}</span>
          </div>
          <div class="detail-item">
            <label>Application Date:</label>
            <span>${formatDate(application.application_date)}</span>
          </div>
          <div class="detail-item">
            <label>Customer ID:</label>
            <span>#${application.customer_id || 'N/A'}</span>
          </div>
          <div class="detail-item">
            <label>Customer Name:</label>
            <span>${application.customer_name || 'N/A'}</span>
          </div>
          <div class="detail-item">
            <label>Customer Email:</label>
            <span>${application.customer_email || 'N/A'}</span>
          </div>
          <div class="detail-item">
            <label>Mobile Number:</label>
            <span>${customerDetails.mobile_number || 'N/A'}</span>
          </div>
          <div class="detail-item">
            <label>Vehicle:</label>
            <span>${application.vehicle_name}</span>
          </div>
          <div class="detail-item">
            <label>Loan Amount:</label>
            <span>₱${formatNumber(application.base_price || 0)}</span>
          </div>
          <div class="detail-item">
            <label>Applicant Type:</label>
            <span class="applicant-type-badge applicant-${(application.applicant_type || 'EMPLOYED').toLowerCase()}">${application.applicant_type || 'EMPLOYED'}</span>
          </div>
          <div class="detail-item">
            <label>Status:</label>
            <span class="status-badge status-${application.status.toLowerCase().replace(' ', '-')}">${application.status}</span>
          </div>
          <div class="detail-item">
            <label>Reviewed By:</label>
            <span>${application.reviewer_name || 'Not reviewed yet'}</span>
          </div>
        </div>

        <h4 class="section-subtitle">Payment Plan <button class="btn-recalculate" onclick="showRecalculateModal(${application.id})">Recalculate</button></h4>
        <div class="detail-grid" id="paymentPlanGrid-${application.id}">
          <div class="detail-item">
            <label>Down Payment:</label>
            <span>₱${formatNumber(application.down_payment || 0)}</span>
          </div>
          <div class="detail-item">
            <label>Monthly Payment:</label>
            <span>₱${formatNumber(application.monthly_payment || 0)}</span>
          </div>
          <div class="detail-item">
            <label>Financing Term:</label>
            <span>${application.financing_term ? application.financing_term + ' months' : 'N/A'}</span>
          </div>
          <div class="detail-item">
            <label>Total Amount:</label>
            <span>₱${formatNumber(application.total_amount || 0)}</span>
          </div>
          <div class="detail-item">
            <label>Interest Rate:</label>
            <span>${application.interest_rate ? parseFloat(application.interest_rate).toFixed(2) + '%' : '0.00%'} Annual</span>
          </div>
        </div>
        
        <div id="amortizationSection-${application.id}" class="amortization-section" style="display: none;">
          <h4 class="section-subtitle">Amortization Schedule</h4>
          <div class="amortization-table-container">
            <table class="amortization-table" id="amortizationTable-${application.id}">
              <thead>
                <tr>
                  <th>Payment #</th>
                  <th>Payment Date</th>
                  <th>Payment Amount</th>
                  <th>Principal</th>
                  <th>Interest</th>
                  <th>Balance</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
          <button class="btn-toggle-amortization" onclick="toggleAmortization(${application.id})">Hide Schedule</button>
        </div>
        
        <div class="payment-plan-actions">
          <button class="btn-show-amortization" onclick="showAmortization(${application.id})">View Amortization Schedule</button>
        </div>

        <h4 class="section-subtitle">Customer Details</h4>
        <div class="detail-grid">
          ${Object.keys(customerDetails).length > 0 ? `
            <div class="detail-item">
              <label>Middle Name:</label>
              <span>${customerDetails.middlename || 'N/A'}</span>
            </div>
            <div class="detail-item">
              <label>Nationality:</label>
              <span>${customerDetails.nationality || 'N/A'}</span>
            </div>
            <div class="detail-item">
              <label>Age:</label>
              <span>${customerDetails.age || 'N/A'}</span>
            </div>
            <div class="detail-item">
              <label>Gender:</label>
              <span>${customerDetails.gender || 'N/A'}</span>
            </div>
            <div class="detail-item">
              <label>Employment Status:</label>
              <span>${customerDetails.employment_status || 'N/A'}</span>
            </div>
            <div class="detail-item">
              <label>Company:</label>
              <span>${customerDetails.company_name || 'N/A'}</span>
            </div>
            <div class="detail-item">
              <label>Position:</label>
              <span>${customerDetails.position || 'N/A'}</span>
            </div>
            <div class="detail-item">
              <label>Monthly Income:</label>
              <span>₱${formatNumber(customerDetails.monthly_income || 0)}</span>
            </div>
            <div class="detail-item">
              <label>Valid ID Type:</label>
              <span>${customerDetails.valid_id_type || 'N/A'}</span>
            </div>
            <div class="detail-item">
              <label>Valid ID Number:</label>
              <span>${customerDetails.valid_id_number || 'N/A'}</span>
            </div>
          ` : ''}
          ${application.notes ? `
            <div class="detail-item full-width">
              <label>Customer Notes:</label>
              <span>${application.notes}</span>
            </div>
          ` : ''}
          ${application.approval_notes ? `
            <div class="detail-item full-width">
              <label>Review Notes:</label>
              <span>${application.approval_notes}</span>
            </div>
          ` : ''}
        </div>
        

        
        ${Object.keys(vehicleDetails).length > 0 ? `
          <h4 style="margin: 20px 0 10px 0;">Vehicle Details</h4>
          <div class="detail-grid">
            <div class="detail-item">
              <label>Model:</label>
              <span>${vehicleDetails.model_name || 'N/A'}</span>
            </div>
            <div class="detail-item">
              <label>Variant:</label>
              <span>${vehicleDetails.variant || 'N/A'}</span>
            </div>
            <div class="detail-item">
              <label>Year:</label>
              <span>${vehicleDetails.year_model || 'N/A'}</span>
            </div>
            <div class="detail-item">
              <label>Category:</label>
              <span>${vehicleDetails.category || 'N/A'}</span>
            </div>
            <div class="detail-item">
              <label>Engine Type:</label>
              <span>${vehicleDetails.engine_type || 'N/A'}</span>
            </div>
            <div class="detail-item">
              <label>Transmission:</label>
              <span>${vehicleDetails.transmission || 'N/A'}</span>
            </div>
            <div class="detail-item">
              <label>Fuel Type:</label>
              <span>${vehicleDetails.fuel_type || 'N/A'}</span>
            </div>
            <div class="detail-item">
              <label>Seating Capacity:</label>
              <span>${vehicleDetails.seating_capacity || 'N/A'}</span>
            </div>
            <div class="detail-item">
              <label>Base Price:</label>
              <span>₱${formatNumber(vehicleDetails.base_price || 0)}</span>
            </div>
            <div class="detail-item">
              <label>Promotional Price:</label>
              <span>${vehicleDetails.promotional_price > 0 ? '₱' + formatNumber(vehicleDetails.promotional_price) : 'N/A'}</span>
            </div>
            <div class="detail-item">
              <label>Min Down Payment:</label>
              <span>${vehicleDetails.min_downpayment_percentage || 'N/A'}%</span>
            </div>
            <div class="detail-item">
              <label>Financing Terms:</label>
              <span>${vehicleDetails.financing_terms || 'N/A'}</span>
            </div>
            ${vehicleDetails.key_features ? `
              <div class="detail-item full-width">
                <label>Key Features:</label>
                <span>${vehicleDetails.key_features}</span>
              </div>
            ` : ''}
            ${vehicleDetails.color_options ? `
              <div class="detail-item full-width">
                <label>Color Options:</label>
                <span>${vehicleDetails.color_options}</span>
              </div>
            ` : ''}
          </div>
        ` : ''}
        
        <h4 style="margin: 20px 0 10px 0;">Submitted Documents</h4>
        <div class="document-checklist">
          ${Object.entries(application.documents).map(([key, doc]) => `
            <div class="doc-item">
              <i class="fas fa-${doc.available ? 'check-circle' : 'times-circle'}" 
                 style="color: ${doc.available ? '#28a745' : '#dc3545'}"></i>
              <span>${doc.name}</span>
              ${doc.available ? `
                <button class="btn btn-small btn-download" onclick="downloadDocument(${application.id}, '${key}')" style="margin-left: auto;">
                  <i class="fas fa-download"></i> Download
                </button>
              ` : '<span style="margin-left: auto; color: #dc3545; font-size: 0.8rem;">Not submitted</span>'}
            </div>
          `).join('')}
        </div>
      `;
    }

    function downloadDocument(applicationId, documentType) {
      window.open(`../../api/loan-applications.php?action=download&id=${applicationId}&type=${documentType}`, '_blank');
    }

    function approveApplication(applicationId) {
      Swal.fire({
        title: 'Approve Application',
        text: 'Add approval notes (optional):',
        input: 'textarea',
        inputPlaceholder: 'Enter approval notes...',
        showCancelButton: true,
        confirmButtonText: 'Approve',
        confirmButtonColor: '#28a745',
        cancelButtonText: 'Cancel'
      }).then((result) => {
        if (result.isConfirmed) {
          const notes = result.value || '';

          fetch('../../api/loan-applications.php?action=approve_enhanced', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
              },
              body: JSON.stringify({
                id: applicationId,
                notes: notes
              })
            })
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                Swal.fire('Success!', 'Application approved successfully', 'success');
                loadStatistics();
                loadLoanApplications();
              } else {
                throw new Error(data.error || 'Failed to approve application');
              }
            })
            .catch(error => {
              console.error('Error approving application:', error);
              Swal.fire('Error!', 'Failed to approve application', 'error');
            });
        }
      });
    }

    function rejectApplication(applicationId) {
      Swal.fire({
        title: 'Reject Application',
        text: 'Please provide a reason for rejection:',
        input: 'textarea',
        inputPlaceholder: 'Enter rejection reason...',
        inputValidator: (value) => {
          if (!value) {
            return 'You need to provide a reason for rejection!';
          }
        },
        showCancelButton: true,
        confirmButtonText: 'Reject',
        confirmButtonColor: '#dc3545',
        cancelButtonText: 'Cancel'
      }).then((result) => {
        if (result.isConfirmed) {
          const notes = result.value;

          fetch('../../api/loan-applications.php?action=reject', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
              },
              body: JSON.stringify({
                id: applicationId,
                notes: notes
              })
            })
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                Swal.fire('Success!', 'Application rejected successfully', 'success');
                loadStatistics();
                loadLoanApplications();
              } else {
                throw new Error(data.error || 'Failed to reject application');
              }
            })
            .catch(error => {
              console.error('Error rejecting application:', error);
              Swal.fire('Error!', 'Failed to reject application', 'error');
            });
        }
      });
    }

    function markUnderReview(applicationId) {
      fetch('../../api/loan-applications.php?action=update_status', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            id: applicationId,
            status: 'Under Review'
          })
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            Swal.fire('Success!', 'Application marked as under review', 'success');
            loadStatistics();
            loadLoanApplications();
          } else {
            throw new Error(data.error || 'Failed to update status');
          }
        })
        .catch(error => {
          console.error('Error updating status:', error);
          Swal.fire('Error!', 'Failed to update status', 'error');
        });
    }

    function approveFromReview() {
      if (currentApplicationId) {
        const applicationId = currentApplicationId;
        closeDocumentModal();
        approveApplication(applicationId);
      }
    }

    function approveFromReviewEnhanced() {
      if (currentApplicationId) {
        const applicationId = currentApplicationId;
        closeDocumentModal();
        approveApplicationEnhanced(applicationId);
      }
    }

    function rejectFromReview() {
      if (currentApplicationId) {
        const applicationId = currentApplicationId;
        closeDocumentModal();
        rejectApplication(applicationId);
      }
    }

    function closeDocumentModal() {
      document.getElementById('documentModal').classList.remove('active');
      currentApplicationId = null;
    }

    function applyFilters() {
      loadLoanApplications();
    }

    function refreshData() {
      // Clear all filters
      document.getElementById('application-search').value = '';
      document.getElementById('application-status').value = 'all';
      document.getElementById('application-date').value = 'all';

      // Reset section title to something more natural
      document.getElementById('sectionTitle').textContent = 'Loan Applications';

      // Reload data
      loadStatistics();
      loadLoanApplications();
    }

    function showError(message) {
      Swal.fire('Error!', message, 'error');
    }

    function formatDate(dateString) {
      const date = new Date(dateString);
      return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });
    }

    function formatNumber(number) {
      return new Intl.NumberFormat('en-US').format(number);
    }

    // Enhanced Financing Calculator Functions
    function showRecalculateModal(applicationId) {
      // Get current application data
      fetch(`../../api/loan-applications.php?action=application&id=${applicationId}`)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            const app = data.data;
            showRecalculateForm(app);
          } else {
            Swal.fire('Error!', 'Failed to load application data', 'error');
          }
        })
        .catch(error => {
          console.error('Error loading application:', error);
          Swal.fire('Error!', 'Failed to load application data', 'error');
        });
    }

    function showRecalculateForm(application) {
      Swal.fire({
        title: 'Recalculate Payment Plan',
        html: `
          <div class="recalculate-form">
            <div class="form-row">
              <div class="form-group">
                <label>Vehicle Price:</label>
                <input type="number" id="vehiclePrice" value="${application.base_price || 0}" readonly>
              </div>
              <div class="form-group">
                <label>Down Payment (₱):</label>
                <input type="number" id="downPayment" value="${application.down_payment || 0}" min="0">
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Financing Term:</label>
                <select id="financingTerm">
                  <option value="12" ${application.financing_term == 12 ? 'selected' : ''}>12 months</option>
                  <option value="24" ${application.financing_term == 24 ? 'selected' : ''}>24 months</option>
                  <option value="36" ${application.financing_term == 36 ? 'selected' : ''}>36 months</option>
                  <option value="48" ${application.financing_term == 48 ? 'selected' : ''}>48 months</option>
                  <option value="60" ${application.financing_term == 60 ? 'selected' : ''}>60 months</option>
                </select>
              </div>
              <div class="form-group">
                <label>Monthly Income (₱):</label>
                <input type="number" id="monthlyIncome" value="${application.monthly_income || 0}" min="0">
              </div>
            </div>
            <div id="validationResult"></div>
          </div>
        `,
        width: '600px',
        showCancelButton: true,
        confirmButtonText: 'Recalculate',
        cancelButtonText: 'Cancel',
        preConfirm: () => {
          const vehiclePrice = parseFloat(document.getElementById('vehiclePrice').value);
          const downPayment = parseFloat(document.getElementById('downPayment').value);
          const financingTerm = parseInt(document.getElementById('financingTerm').value);
          const monthlyIncome = parseFloat(document.getElementById('monthlyIncome').value);

          return recalculatePaymentPlan(application.id, vehiclePrice, downPayment, financingTerm, monthlyIncome);
        }
      });
    }

    function recalculatePaymentPlan(applicationId, vehiclePrice, downPayment, financingTerm, monthlyIncome) {
      return fetch('../../includes/backend/loan_backend.php?action=recalculate', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          application_id: applicationId,
          vehicle_price: vehiclePrice,
          down_payment: downPayment,
          financing_term: financingTerm,
          monthly_income: monthlyIncome
        })
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Update the payment plan display
          const paymentPlan = {
            down_payment: downPayment,
            monthly_payment: data.data.monthly_payment,
            financing_term: financingTerm,
            total_amount: data.data.total_amount,
            interest_rate: data.data.interest_rate
          };
          updatePaymentPlanDisplay(applicationId, paymentPlan);
          Swal.fire('Success!', 'Payment plan recalculated successfully', 'success');
          return true;
        } else {
          document.getElementById('validationResult').innerHTML = 
            `<div class="validation-error">${data.error}</div>`;
          return false;
        }
      })
      .catch(error => {
        console.error('Error recalculating:', error);
        document.getElementById('validationResult').innerHTML = 
          `<div class="validation-error">Failed to recalculate payment plan</div>`;
        return false;
      });
    }

    function updatePaymentPlanDisplay(applicationId, paymentPlan) {
      const grid = document.getElementById(`paymentPlanGrid-${applicationId}`);
      if (grid) {
        grid.innerHTML = `
          <div class="detail-item">
            <label>Down Payment:</label>
            <span>₱${formatNumber(paymentPlan.down_payment || 0)}</span>
          </div>
          <div class="detail-item">
            <label>Monthly Payment:</label>
            <span>₱${formatNumber(paymentPlan.monthly_payment || 0)}</span>
          </div>
          <div class="detail-item">
            <label>Financing Term:</label>
            <span>${paymentPlan.financing_term || 0} months</span>
          </div>
          <div class="detail-item">
            <label>Total Amount:</label>
            <span>₱${formatNumber(paymentPlan.total_amount || 0)}</span>
          </div>
          <div class="detail-item">
            <label>Interest Rate:</label>
            <span>${parseFloat(paymentPlan.interest_rate || 0).toFixed(2)}% Annual</span>
          </div>
        `;
      }
    }

    function showAmortization(applicationId) {
      // Get current application data and generate amortization
      fetch(`../../api/loan-applications.php?action=application&id=${applicationId}`)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            generateAmortizationSchedule(applicationId, data.data);
          } else {
            Swal.fire('Error!', 'Failed to load application data', 'error');
          }
        })
        .catch(error => {
          console.error('Error loading application:', error);
          Swal.fire('Error!', 'Failed to load application data', 'error');
        });
    }

    function generateAmortizationSchedule(applicationId, application) {
      // Calculate loan amount using vehicle effective price (same hierarchy as customer side)
      // Priority: effective_price > promotional_price > base_price
      let vehiclePrice = parseFloat(application.vehicle_effective_price || 0);
      if (!vehiclePrice || vehiclePrice <= 0) {
        vehiclePrice = parseFloat(application.vehicle_promotional_price || 0);
      }
      if (!vehiclePrice || vehiclePrice <= 0) {
        vehiclePrice = parseFloat(application.base_price || 0);
      }

      const loanAmount = vehiclePrice - parseFloat(application.down_payment || 0);

      fetch('../../includes/backend/loan_backend.php?action=amortization', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          loan_amount: loanAmount,
          annual_rate: parseFloat(application.interest_rate || 0),
          financing_term: parseInt(application.financing_term || 0)
        })
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          displayAmortizationTable(applicationId, data.data.schedule);
        } else {
          Swal.fire('Error!', 'Failed to generate amortization schedule', 'error');
        }
      })
      .catch(error => {
        console.error('Error generating amortization:', error);
        Swal.fire('Error!', 'Failed to generate amortization schedule', 'error');
      });
    }

    function displayAmortizationTable(applicationId, schedule) {
      const tableBody = document.querySelector(`#amortizationTable-${applicationId} tbody`);
      const section = document.getElementById(`amortizationSection-${applicationId}`);
      
      if (tableBody && section) {
        tableBody.innerHTML = '';
        
        schedule.forEach((payment, index) => {
          // Calculate payment date (assuming monthly payments starting from current date)
          const paymentDate = new Date();
          paymentDate.setMonth(paymentDate.getMonth() + payment.payment_number);
          const formattedDate = paymentDate.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
          
          const row = document.createElement('tr');
          row.innerHTML = `
            <td>${payment.payment_number}</td>
            <td>${formattedDate}</td>
            <td>₱${formatNumber(payment.monthly_payment)}</td>
            <td>₱${formatNumber(payment.principal_payment)}</td>
            <td>₱${formatNumber(payment.interest_payment)}</td>
            <td>₱${formatNumber(payment.remaining_balance)}</td>
          `;
          tableBody.appendChild(row);
        });
        
        section.style.display = 'block';
        document.querySelector(`.btn-show-amortization`).style.display = 'none';
      }
    }

    function toggleAmortization(applicationId) {
      const section = document.getElementById(`amortizationSection-${applicationId}`);
      if (section) {
        section.style.display = 'none';
        document.querySelector(`.btn-show-amortization`).style.display = 'inline-block';
      }
    }

    // Enhanced approval function with validation
    function approveApplicationEnhanced(applicationId) {
      // First validate the application
      fetch(`../../includes/backend/loan_backend.php?action=validate`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          application_id: applicationId
        })
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Show validation results and proceed with approval
          Swal.fire({
            title: 'Application Validation',
            html: `
              <div class="validation-success">
                <strong>✓ All validations passed</strong><br>
                • Down payment meets minimum requirement<br>
                • Monthly payment is within income ratio<br>
                • Financing terms are valid<br>
              </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Proceed with Approval',
            cancelButtonText: 'Cancel'
          }).then((result) => {
            if (result.isConfirmed) {
              // Call enhanced approval with validation
              Swal.fire({
                title: 'Add Approval Notes',
                input: 'textarea',
                inputPlaceholder: 'Enter any notes for this approval (optional)...',
                showCancelButton: true,
                confirmButtonText: 'Approve Application',
                cancelButtonText: 'Cancel'
              }).then((notesResult) => {
                if (notesResult.isConfirmed) {
                  const notes = notesResult.value || '';
                  
                  fetch('../../api/loan-applications.php', {
                    method: 'POST',
                    headers: {
                      'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                      action: 'approve_enhanced',
                      id: applicationId,
                      notes: notes
                    })
                  })
                  .then(response => response.json())
                  .then(data => {
                    if (data.success) {
                      Swal.fire({
                        title: 'Success!',
                        text: data.message,
                        icon: 'success'
                      }).then(() => {
                        refreshData();
                      });
                    } else {
                      Swal.fire({
                        title: 'Error!',
                        text: data.error,
                        icon: 'error'
                      });
                    }
                  })
                  .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                      title: 'Error!',
                      text: 'An error occurred while processing the approval.',
                      icon: 'error'
                    });
                  });
                }
              });
            }
          });
        } else {
          // Show validation errors
          Swal.fire({
            title: 'Validation Failed',
            html: `
              <div class="validation-error">
                <strong>⚠ Validation Issues Found:</strong><br>
                ${data.errors.map(error => `• ${error}`).join('<br>')}
              </div>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Approve Anyway',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#dc3545'
          }).then((result) => {
            if (result.isConfirmed) {
              approveApplication(applicationId);
            }
          });
        }
      })
      .catch(error => {
        console.error('Error validating application:', error);
        Swal.fire('Error!', 'Failed to validate application', 'error');
      });
    }
  </script>

  <style>
    .detail-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 15px;
      margin: 15px 0;
    }

    .detail-item {
      display: flex;
      flex-direction: column;
      gap: 5px;
    }

    .detail-item.full-width {
      grid-column: 1 / -1;
    }

    .detail-item label {
      font-weight: 600;
      color: #666;
      font-size: 0.9rem;
    }

    .detail-item span {
      color: #333;
      font-size: 0.95rem;
    }

    .document-checklist {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    .doc-item {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 8px;
      background: #f8f9fa;
      border-radius: 5px;
    }

    .text-center {
      text-align: center;
    }

    .btn-download {
      background: #17a2b8 !important;
      color: white;
    }

    .btn-download:hover {
      background: #138496 !important;
    }

    .status-badge {
      padding: 4px 8px;
      border-radius: 4px;
      font-size: 0.8rem;
      font-weight: bold;
      text-transform: uppercase;
    }

    .status-pending {
      background-color: #ffc107;
      color: #856404;
    }

    .status-under-review {
      background-color: #17a2b8;
      color: white;
    }

    .status-approved {
      background-color: #28a745;
      color: white;
    }

    .status-rejected {
      background-color: #dc3545;
      color: white;
    }

    .applicant-type-badge {
      padding: 4px 8px;
      border-radius: 4px;
      font-size: 0.8rem;
      font-weight: bold;
      text-transform: uppercase;
    }

    .applicant-employed {
      background-color: #e3f2fd;
      color: #1565c0;
    }

    .applicant-business {
      background-color: #f3e5f5;
      color: #7b1fa2;
    }

    .applicant-ofw {
      background-color: #e8f5e8;
      color: #2e7d32;
    }

    .status-completed {
      background-color: #6c757d;
      color: white;
    }

    .modal.active,
    .modal-overlay.active {
      display: flex !important;
    }

    .action-buttons {
      display: flex;
      gap: 5px;
      flex-wrap: wrap;
    }

    .btn-small {
      padding: 5px 8px;
      font-size: 0.8rem;
    }

    .order-info strong,
    .customer-info strong,
    .vehicle-info strong {
      display: block;
      margin-bottom: 5px;
    }

    .order-meta,
    .customer-meta,
    .vehicle-meta {
      display: flex;
      flex-direction: column;
      gap: 2px;
    }

    .order-meta span,
    .customer-meta span,
    .vehicle-meta span {
      font-size: 0.8rem;
      color: #666;
    }

    .filter-input:focus,
    .filter-select:focus {
      outline: none;
      border-color: #b80000;
      box-shadow: 0 0 0 2px rgba(184, 0, 0, 0.2);
    }

    .search-hint {
      font-size: 0.8rem;
      color: #666;
      margin-top: 5px;
      font-style: italic;
    }
  </style>
</body>

</html>