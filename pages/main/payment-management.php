<?php
// Configure session settings for better AJAX compatibility
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');

// Ensure session is started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include_once(dirname(dirname(__DIR__)) . '/includes/init.php');

// Check if user is Sales Agent or Admin
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['SalesAgent', 'Admin'])) {
    header("Location: ../../pages/login.php");
    exit();
}

// Use the database connection from init.php
$pdo = $GLOBALS['pdo'] ?? null;

if (!$pdo) {
    die("Database connection not available. Please check your database configuration.");
}

// Get agent ID from session
$agent_id = $_SESSION['user_id'] ?? null;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Payment Management - Sales Agent</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../includes/css/common-styles.css" rel="stylesheet">
    <link href="../../includes/css/orders-styles.css" rel="stylesheet">
    <style>
    
        body{
            zoom: 85%;
        }
        .payment-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .payment-stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 25px;
            color: white;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .payment-stat-card:hover {
            transform: translateY(-5px);
        }

        .payment-stat-card.pending {
            background: #F9A825;
        }

        .payment-stat-card.confirmed {
            background: #43A047;
        }

        .payment-stat-card.failed {
            background: #E53935;
        }

        .payment-stat-card.total {
            background: #1E88E5;
            color: #ffffffff;
        }

        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
        }

        .stat-icon {
            
            font-size: 2rem;
            opacity: 0.8;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-label {
            
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .payment-filters {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }

        .filter-group select,
        .filter-group input {
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: #667eea;
        }

        .filter-actions {
            display: flex;
            gap: 10px;
        }

        .btn-filter {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #007BFF;
            color: white;
        }

        .btn-secondary {
            background: #f8f9fa;
            color: #6c757d;
            border: 2px solid #e1e5e9;
        }

        .btn-filter:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .payments-table-container {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .table-header {
            background: #707070ff;
            color: white;
            padding: 20px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-title {
            font-size: 1.2rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .payments-table {
            width: 100%;
            border-collapse: collapse;
        }

        .payments-table th,
        .payments-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e1e5e9;
        }

        .payments-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        .payments-table tbody tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-confirmed {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-failed {
            background: #f8d7da;
            color: #721c24;
        }

        .payment-actions {
            display: flex;
            gap: 8px;
        }

        .btn-action {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: all 0.3s ease;
        }

        .btn-view {
            background: #17a2b8;
            color: white;
        }

        .btn-approve {
            background: #28a745;
            color: white;
        }

        .btn-reject {
            background: #dc3545;
            color: white;
        }

        .btn-action:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            gap: 10px;
        }

        .pagination button {
            padding: 8px 12px;
            border: 1px solid #dee2e6;
            background: white;
            cursor: pointer;
            border-radius: 4px;
            transition: all 0.3s ease;
        }

        .pagination button:hover {
            background: #e9ecef;
        }

        .pagination button.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        /* Payment Detail Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 25px;
            border-radius: 15px 15px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-body {
            padding: 25px;
        }

        .payment-detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .detail-group {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }

        .detail-label {
            font-weight: 600;
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }

        .detail-value {
            font-size: 1rem;
            color: #333;
        }

        .receipt-preview {
            text-align: center;
            margin: 20px 0;
        }

        .receipt-preview img {
            max-width: 100%;
            max-height: 300px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            padding: 20px 25px;
            border-top: 1px solid #e1e5e9;
        }

        .close {
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            opacity: 0.7;
        }

        @media (max-width: 768px) {
            .payment-stats {
                grid-template-columns: 1fr;
            }

            .filter-row {
                grid-template-columns: 1fr;
            }

            .payments-table-container {
                overflow-x: auto;
            }

            .payments-table {
                min-width: 800px;
            }
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
                    <i class="fas fa-credit-card icon-gradient"></i>
                    Payment Management
                </h1>
            </div>

            <!-- Payment Statistics -->
            <div class="payment-stats">
                <div class="payment-stat-card pending">
                    <div class="stat-header">
                        <i class="fas fa-clock stat-icon"></i>
                    </div>
                    <div class="stat-value" id="pendingCount">-</div>
                    <div class="stat-label">Pending Payments</div>
                </div>
                <div class="payment-stat-card confirmed">
                    <div class="stat-header">
                        <i class="fas fa-check-circle stat-icon"></i>
                    </div>
                    <div class="stat-value" id="confirmedCount">-</div>
                    <div class="stat-label">Confirmed Payments</div>
                </div>
                <div class="payment-stat-card failed">
                    <div class="stat-header">
                        <i class="fas fa-times-circle stat-icon"></i>
                    </div>
                    <div class="stat-value" id="failedCount">-</div>
                    <div class="stat-label">Failed Payments</div>
                </div>
                <div class="payment-stat-card total">
                    <div class="stat-header">
                        <i class="fas fa-money-bill-wave stat-icon"></i>
                    </div>
                    <div class="stat-value" id="totalAmount">₱0</div>
                    <div class="stat-label">Total Amount</div>
                </div>
            </div>

            <!-- Payment Filters -->
            <div class="payment-filters">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="statusFilter">Payment Status</label>
                        <select id="statusFilter">
                            <option value="">All Statuses</option>
                            <option value="Pending">Pending</option>
                            <option value="Confirmed">Confirmed</option>
                            <option value="Failed">Failed</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="paymentTypeFilter">Payment Type</label>
                        <select id="paymentTypeFilter">
                            <option value="">All Types</option>
                            <option value="Full Payment">Full Payment</option>
                            <option value="Monthly Payment">Monthly Payment</option>
                            <option value="Partial Payment">Partial Payment</option>
                            <option value="Down Payment">Down Payment</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="dateFromFilter">Date From</label>
                        <input type="date" id="dateFromFilter">
                    </div>
                    <div class="filter-group">
                        <label for="dateToFilter">Date To</label>
                        <input type="date" id="dateToFilter">
                    </div>
                    <div class="filter-actions">
                        <button class="btn-filter btn-primary" onclick="applyFilters()">
                            <i class="fas fa-search"></i> Filter
                        </button>
                        <button class="btn-filter btn-secondary" onclick="clearFilters()">
                            <i class="fas fa-times"></i> Clear
                        </button>
                    </div>
                </div>
            </div>

            <!-- Payments Table -->
            <div class="payments-table-container">
                <div class="table-header">
                    <div class="table-title">
                        <i class="fas fa-list"></i>
                        Payment Records
                    </div>
                    <button class="btn-filter btn-secondary" onclick="refreshPayments()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
                <div id="paymentsTableContainer">
                    <table class="payments-table">
                        <thead>
                            <tr>
                                <th>Payment #</th>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Vehicle</th>
                                <th>Amount</th>
                                <th>Type</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="paymentsTableBody">
                            <tr>
                                <td colspan="10" class="empty-state">
                                    <i class="fas fa-credit-card"></i>
                                    <p>Loading payment records...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="pagination" id="paginationContainer">
                    <!-- Pagination will be generated by JavaScript -->
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Detail Modal -->
    <div id="paymentDetailModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Payment Details</h2>
                <span class="close" onclick="closePaymentModal()">&times;</span>
            </div>
            <div class="modal-body" id="paymentDetailContent">
                <!-- Payment details will be loaded here -->
            </div>
            <div class="modal-actions">
                <button class="btn-filter btn-primary" id="approvePaymentBtn" onclick="approvePayment()" style="display: none;">
                    <i class="fas fa-check"></i> Approve Payment
                </button>
                <button class="btn-filter" id="rejectPaymentBtn" onclick="rejectPayment()" style="display: none; background: #dc3545; color: white;">
                    <i class="fas fa-times"></i> Reject Payment
                </button>
                <button class="btn-filter btn-secondary" onclick="closePaymentModal()">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </div>
    </div>

    <script src="../../includes/jquery/dist/jquery.min.js"></script>
    <script>
        let currentPage = 1;
        let currentPaymentId = null;
        const itemsPerPage = 10;

        $(document).ready(function() {
            loadPaymentStats();
            loadPayments();
        });

        function loadPaymentStats() {
            $.ajax({
                url: '../../includes/backend/payment_backend.php',
                method: 'POST',
                data: {
                    action: 'get_payment_stats'
                },
                dataType: 'json',
                xhrFields: {
                    withCredentials: true
                },
                success: function(response) {
                    if (response.success) {
                        const stats = response.data;
                        $('#pendingCount').text(stats.pending || 0);
                        $('#confirmedCount').text(stats.confirmed || 0);
                        $('#failedCount').text(stats.rejected || 0);
                        $('#totalAmount').text('₱' + (stats.total_amount || 0).toLocaleString());
                    } else {
                        console.error('Backend error:', response.message);
                        if (response.debug) {
                            console.error('Debug info:', response.debug);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Failed to load payment statistics:', xhr.responseText);
                    console.error('Status:', status, 'Error:', error);
                    try {
                        const errorResponse = JSON.parse(xhr.responseText);
                        if (errorResponse.debug) {
                            console.error('Debug info:', errorResponse.debug);
                        }
                    } catch (e) {
                        // Ignore JSON parse errors
                    }
                }
            });
        }

        function loadPayments(page = 1) {
            const filters = {
                status: $('#statusFilter').val(),
                payment_type: $('#paymentTypeFilter').val(),
                date_from: $('#dateFromFilter').val(),
                date_to: $('#dateToFilter').val(),
                page: page,
                limit: itemsPerPage
            };

            $.ajax({
                url: '../../includes/backend/payment_backend.php',
                method: 'POST',
                data: {
                    action: 'get_agent_payments',
                    ...filters
                },
                dataType: 'json',
                xhrFields: {
                    withCredentials: true
                },
                success: function(response) {
                    if (response.success) {
                        displayPayments(response.data.payments);
                        displayPagination(response.data.total, page);
                    } else {
                        displayEmptyState('No payment records found.');
                        console.error('Backend error:', response.message);
                        if (response.debug) {
                            console.error('Debug info:', response.debug);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    displayEmptyState('Failed to load payment records.');
                    console.error('Failed to load payment records:', xhr.responseText);
                    console.error('Status:', status, 'Error:', error);
                    try {
                        const errorResponse = JSON.parse(xhr.responseText);
                        if (errorResponse.debug) {
                            console.error('Debug info:', errorResponse.debug);
                        }
                    } catch (e) {
                        // Ignore JSON parse errors
                    }
                }
            });
        }

        function displayPayments(payments) {
            const tbody = $('#paymentsTableBody');
            tbody.empty();

            if (payments.length === 0) {
                displayEmptyState('No payment records found.');
                return;
            }

            payments.forEach(payment => {
                const row = `
                    <tr>
                        <td><strong>${payment.payment_number}</strong></td>
                        <td>${payment.order_number || 'Order #' + payment.order_id}</td>
                        <td>${payment.customer_name || 'Customer #' + payment.customer_id}</td>
                        <td>${payment.vehicle_model ? payment.vehicle_model + ' ' + (payment.vehicle_variant || '') : 'N/A'}</td>
                        <td><strong>₱${parseFloat(payment.amount_paid).toLocaleString()}</strong></td>
                        <td>${payment.payment_type}</td>
                        <td>${payment.payment_method}</td>
                        <td><span class="status-badge status-${payment.status.toLowerCase()}">${payment.status}</span></td>
                        <td>${new Date(payment.created_at).toLocaleDateString()}</td>
                        <td>
                            <div class="payment-actions">
                                <button class="btn-action btn-view" onclick="viewPaymentDetails(${payment.id})">
                                    <i class="fas fa-eye"></i> View
                                </button>
                                ${payment.status === 'Pending' ? `
                                    <button class="btn-action btn-approve" onclick="quickApprove(${payment.id})">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button class="btn-action btn-reject" onclick="quickReject(${payment.id})">
                                        <i class="fas fa-times"></i>
                                    </button>
                                ` : ''}
                            </div>
                        </td>
                    </tr>
                `;
                tbody.append(row);
            });
        }

        function displayEmptyState(message) {
            const tbody = $('#paymentsTableBody');
            tbody.html(`
                <tr>
                    <td colspan="10" class="empty-state">
                        <i class="fas fa-credit-card"></i>
                        <p>${message}</p>
                    </td>
                </tr>
            `);
        }

        function displayPagination(total, currentPage) {
            const totalPages = Math.ceil(total / itemsPerPage);
            const container = $('#paginationContainer');
            container.empty();

            if (totalPages <= 1) return;

            // Previous button
            if (currentPage > 1) {
                container.append(`<button onclick="loadPayments(${currentPage - 1})">Previous</button>`);
            }

            // Page numbers
            for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) {
                const activeClass = i === currentPage ? 'active' : '';
                container.append(`<button class="${activeClass}" onclick="loadPayments(${i})">${i}</button>`);
            }

            // Next button
            if (currentPage < totalPages) {
                container.append(`<button onclick="loadPayments(${currentPage + 1})">Next</button>`);
            }
        }

        function viewPaymentDetails(paymentId) {
            currentPaymentId = paymentId;
            
            $.ajax({
                url: '../../includes/backend/payment_backend.php',
                method: 'POST',
                data: {
                    action: 'get_payment_details',
                    payment_id: paymentId
                },
                dataType: 'json',
                xhrFields: {
                    withCredentials: true
                },
                success: function(response) {
                    if (response.success) {
                        displayPaymentDetails(response.data);
                        $('#paymentDetailModal').show();
                    } else {
                        alert('Failed to load payment details: ' + (response.message || 'Unknown error'));
                        console.error('Backend error:', response.message);
                        if (response.debug) {
                            console.error('Debug info:', response.debug);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    alert('Failed to load payment details.');
                    console.error('AJAX error:', xhr.responseText);
                    console.error('Status:', status, 'Error:', error);
                    try {
                        const errorResponse = JSON.parse(xhr.responseText);
                        if (errorResponse.debug) {
                            console.error('Debug info:', errorResponse.debug);
                        }
                    } catch (e) {
                        // Ignore JSON parse errors
                    }
                }
            });
        }

        function displayPaymentDetails(payment) {
            const content = $('#paymentDetailContent');
            
            const receiptHtml = payment.receipt_image ? 
                `<div class="receipt-preview">
                    <h4>Payment Receipt</h4>
                    <img src="data:image/jpeg;base64,${payment.receipt_image}" alt="Payment Receipt">
                </div>` : 
                '<p><em>No receipt uploaded</em></p>';

            content.html(`
                <div class="payment-detail-grid">
                    <div class="detail-group">
                        <div class="detail-label">Payment Number</div>
                        <div class="detail-value">${payment.payment_number}</div>
                    </div>
                    <div class="detail-group">
                        <div class="detail-label">Order Number</div>
                        <div class="detail-value">${payment.order_number}</div>
                    </div>
                    <div class="detail-group">
                        <div class="detail-label">Customer</div>
                        <div class="detail-value">${payment.customer_name}</div>
                    </div>
                    <div class="detail-group">
                        <div class="detail-label">Vehicle</div>
                        <div class="detail-value">${payment.vehicle_model} ${payment.vehicle_variant}</div>
                    </div>
                    <div class="detail-group">
                        <div class="detail-label">Amount</div>
                        <div class="detail-value"><strong>₱${parseFloat(payment.amount_paid).toLocaleString()}</strong></div>
                    </div>
                    <div class="detail-group">
                        <div class="detail-label">Payment Type</div>
                        <div class="detail-value">${payment.payment_type}</div>
                    </div>
                    <div class="detail-group">
                        <div class="detail-label">Payment Method</div>
                        <div class="detail-value">${payment.payment_method}</div>
                    </div>
                    <div class="detail-group">
                        <div class="detail-label">Status</div>
                        <div class="detail-value"><span class="status-badge status-${payment.status.toLowerCase()}">${payment.status}</span></div>
                    </div>
                    ${payment.reference_number ? `
                        <div class="detail-group">
                            <div class="detail-label">Reference Number</div>
                            <div class="detail-value">${payment.reference_number}</div>
                        </div>
                    ` : ''}
                    ${payment.bank_name ? `
                        <div class="detail-group">
                            <div class="detail-label">Bank Name</div>
                            <div class="detail-value">${payment.bank_name}</div>
                        </div>
                    ` : ''}
                    <div class="detail-group">
                        <div class="detail-label">Submitted Date</div>
                        <div class="detail-value">${new Date(payment.created_at).toLocaleString()}</div>
                    </div>
                    ${payment.processed_at ? `
                        <div class="detail-group">
                            <div class="detail-label">Processed Date</div>
                            <div class="detail-value">${new Date(payment.processed_at).toLocaleString()}</div>
                        </div>
                    ` : ''}
                </div>
                ${payment.notes ? `
                    <div class="detail-group" style="grid-column: 1 / -1;">
                        <div class="detail-label">Notes</div>
                        <div class="detail-value">${payment.notes}</div>
                    </div>
                ` : ''}
                ${payment.rejection_reason ? `
                    <div class="detail-group" style="grid-column: 1 / -1; background: #f8d7da; color: #721c24;">
                        <div class="detail-label">Rejection Reason</div>
                        <div class="detail-value">${payment.rejection_reason}</div>
                    </div>
                ` : ''}
                ${receiptHtml}
            `);

            // Show/hide action buttons based on status
            if (payment.status === 'Pending') {
                $('#approvePaymentBtn').show();
                $('#rejectPaymentBtn').show();
            } else {
                $('#approvePaymentBtn').hide();
                $('#rejectPaymentBtn').hide();
            }
        }

        function closePaymentModal() {
            $('#paymentDetailModal').hide();
            currentPaymentId = null;
        }

        function quickApprove(paymentId) {
            if (confirm('Are you sure you want to approve this payment?')) {
                processPayment(paymentId, 'approve');
            }
        }

        function quickReject(paymentId) {
            const reason = prompt('Please enter rejection reason:');
            if (reason) {
                processPayment(paymentId, 'reject', reason);
            }
        }

        function approvePayment() {
            if (currentPaymentId && confirm('Are you sure you want to approve this payment?')) {
                processPayment(currentPaymentId, 'approve');
            }
        }

        function rejectPayment() {
            if (currentPaymentId) {
                const reason = prompt('Please enter rejection reason:');
                if (reason) {
                    processPayment(currentPaymentId, 'reject', reason);
                }
            }
        }

        function processPayment(paymentId, action, reason = '') {
            $.ajax({
                url: '../../includes/backend/payment_backend.php',
                method: 'POST',
                data: {
                    action: 'process_payment',
                    payment_id: paymentId,
                    process_action: action,
                    rejection_reason: reason
                },
                dataType: 'json',
                xhrFields: {
                    withCredentials: true
                },
                success: function(response) {
                    if (response.success) {
                        alert(`Payment ${action}d successfully!`);
                        closePaymentModal();
                        loadPaymentStats();
                        loadPayments(currentPage);
                    } else {
                        alert(response.message || `Failed to ${action} payment.`);
                        console.error('Backend error:', response.message);
                        if (response.debug) {
                            console.error('Debug info:', response.debug);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    alert(`Failed to ${action} payment.`);
                    console.error('AJAX error:', xhr.responseText);
                    console.error('Status:', status, 'Error:', error);
                    try {
                        const errorResponse = JSON.parse(xhr.responseText);
                        if (errorResponse.debug) {
                            console.error('Debug info:', errorResponse.debug);
                        }
                    } catch (e) {
                        // Ignore JSON parse errors
                    }
                }
            });
        }

        function applyFilters() {
            currentPage = 1;
            loadPayments(1);
        }

        function clearFilters() {
            $('#statusFilter').val('');
            $('#paymentTypeFilter').val('');
            $('#dateFromFilter').val('');
            $('#dateToFilter').val('');
            currentPage = 1;
            loadPayments(1);
        }


        function refreshPayments() {
            loadPaymentStats();
            loadPayments(currentPage);
        }

        // Close modal when clicking outside
        $(window).click(function(event) {
            if (event.target.id === 'paymentDetailModal') {
                closePaymentModal();
            }
        });
    </script>
</body>

</html>