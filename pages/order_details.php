<?php
session_start();
include_once(dirname(__DIR__) . '/includes/database/db_conn.php');

// Check if user is logged in and is a customer
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Customer') {
    header("Location: login.php");
    exit;
}

// Fetch user details
$stmt = $connect->prepare("SELECT * FROM accounts WHERE Id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$displayName = !empty($user['FirstName']) ? $user['FirstName'] : $user['Username'];

// Check if customer profile exists, if not create a basic one for testing
$cusStmt = $connect->prepare("SELECT cusID FROM customer_information WHERE account_id = ?");
$cusStmt->execute([$_SESSION['user_id']]);
$customerProfile = $cusStmt->fetch(PDO::FETCH_ASSOC);

if (!$customerProfile) {
    // Create a basic customer profile for testing
    $insertStmt = $connect->prepare("INSERT INTO customer_information (account_id, lastname, firstname, birthday, created_at) VALUES (?, ?, ?, ?, NOW())");
    $insertStmt->execute([
        $_SESSION['user_id'],
        $user['LastName'] ?? 'Customer',
        $user['FirstName'] ?? 'Test',
        '1990-01-01' // Default birthday
    ]);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - Mitsubishi Motors</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', 'Segoe UI', sans-serif;
        }

        body {
            background: #ffffff;
            min-height: 100vh;
            color: white;
        }

        .header {
            background: #000000;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 215, 0, 0.2);
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .logo {
            width: 60px;
            height: auto;
            filter: drop-shadow(0 0 10px rgba(255, 215, 0, 0.3));
        }

        .brand-text {
            font-size: 1.4rem;
            font-weight: 700;
            background: linear-gradient(45deg, #ffd700, #ffed4e);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .user-section {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(45deg, #ffd700, #ffed4e);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #b80000;
            font-size: 1.2rem;
        }

        .welcome-text {
            font-size: 1rem;
            font-weight: 500;
        }

        .logout-btn {
            background: linear-gradient(45deg, #d60000, #b30000);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(214, 0, 0, 0.3);
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(214, 0, 0, 0.5);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 30px;
        }

        .back-btn {
            display: inline-block;
            margin-bottom: 30px;
            background: #E60012;
            color: #ffffff;
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: #ffd700;
            color: #1a1a1a;
        }

        .page-title {
            font-size: 2.5rem;
            margin-bottom: 30px;
            background: #E60012;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 800;
            text-align: center;
        }

        .orders-container {
            background: #808080;
            border-radius: 20px;
            padding: 30px;
            backdrop-filter: blur(20px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 215, 0, 0.1);
        }

        .loading {
            text-align: center;
            padding: 50px;
            color: #ffd700;
        }

        .no-orders {
            text-align: center;
            padding: 50px;
            color: #ccc;
        }

        .order-card {
            background: rgba(255, 255, 255, 0.52);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 215, 0, 0.1);
            transition: all 0.3s ease;
        }

        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .order-info h3 {
            color: #ffd700;
            font-size: 1.3rem;
            margin-bottom: 5px;
        }

        .order-info p {
            color: #000000ff;
            font-size: 0.9rem;
        }

        .order-status {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
        }

        .status-pending {
            background: rgba(255, 193, 7, 0.2);
            color: #ffc107;
        }

        .status-confirmed {
            background: rgba(40, 167, 69, 0.2);
            color: #28a745;
        }

        .status-processing {
            background: rgba(23, 162, 184, 0.2);
            color: #17a2b8;
        }

        .status-completed {
            background: rgba(76, 175, 80, 0.2);
            color: #4caf50;
        }

        .status-cancelled {
            background: rgba(220, 53, 69, 0.2);
            color: #dc3545;
        }

        .status-overdue {
            background: rgba(220, 53, 69, 0.2);
            color: #dc3545;
        }

        .status-partial {
            background: rgba(255, 193, 7, 0.2);
            color: #ffc107;
        }

        .order-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .detail-item {
            background: rgba(0, 0, 0, 0.50);
            padding: 15px;
            border-radius: 10px;
        }

        .detail-label {
            color: #E60012;
            font-weight: 600;
            font-size: 0.85rem;
            margin-bottom: 5px;
        }

        .detail-value {
            color: white;
            font-size: 1rem;
        }

        .progress-bar {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            height: 20px;
            margin: 15px 0;
            overflow: hidden;
        }

        .progress-fill {
            background: linear-gradient(45deg, #ffd700, #ffed4e);
            height: 100%;
            border-radius: 10px;
            transition: width 0.3s ease;
        }

        .order-actions {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .btn-primary {
            background: linear-gradient(45deg, #ffd700, #ffed4e);
            color: #1a1a1a;
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: #ffd700;
            border: 1px solid rgba(255, 215, 0, 0.3);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(255, 215, 0, 0.3);
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            overflow-y: auto;
            padding: 1rem;
        }

        .modal-content {
            background: rgba(255, 255, 255, 0.1);
            margin: 2rem auto;
            padding: 2rem;
            border-radius: 20px;
            width: 100%;
            max-width: 600px;
            min-height: auto;
            max-height: calc(100vh - 4rem);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 215, 0, 0.1);
            overflow-y: auto;
            position: relative;
        }

        /* Responsive Modal Styles */
        @media (max-width: 768px) {
            .modal {
                padding: 0.5rem;
            }
            
            .modal-content {
                margin: 1rem auto;
                padding: 1.5rem;
                max-height: calc(100vh - 2rem);
                border-radius: 15px;
            }
        }

        @media (max-width: 480px) {
            .modal {
                padding: 0.25rem;
            }
            
            .modal-content {
                margin: 0.5rem auto;
                padding: 1rem;
                max-height: calc(100vh - 1rem);
                border-radius: 10px;
            }
        }

        @media (max-height: 600px) {
             .modal-content {
                 margin: 0.5rem auto;
                 max-height: calc(100vh - 1rem);
             }
         }

         .modal-header {
             display: flex;
             justify-content: space-between;
             align-items: center;
             margin-bottom: 1.25rem;
             flex-shrink: 0;
         }

         .modal-footer {
             display: flex;
             gap: 1rem;
             justify-content: flex-end;
             margin-top: 1.5rem;
             padding-top: 1rem;
             border-top: 1px solid rgba(255, 215, 0, 0.2);
             flex-shrink: 0;
         }

         /* Responsive Modal Footer */
         @media (max-width: 480px) {
             .modal-footer {
                 flex-direction: column-reverse;
                 gap: 0.75rem;
             }
             
             .modal-footer .btn {
                 width: 100%;
                 justify-content: center;
             }
         }

        .modal-title {
            color: #ffd700;
            font-size: 1.5rem;
            font-weight: 700;
        }

        .close {
            color: #ccc;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: #ffd700;
        }

        /* Responsive Close Button */
        @media (max-width: 480px) {
            .close {
                font-size: 24px;
                padding: 0.25rem;
            }
            
            .modal-title {
                font-size: 1.25rem;
            }
        }

        /* Ensure proper touch targets on mobile */
        @media (max-width: 768px) {
            .close {
                min-width: 44px;
                min-height: 44px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            color: #ffd700;
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid rgba(255, 215, 0, 0.3);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            font-size: 1rem;
            box-sizing: border-box;
        }

        .form-input:focus {
            outline: none;
            border-color: #ffd700;
            box-shadow: 0 0 0 2px rgba(255, 215, 0, 0.2);
        }

        .form-select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid rgba(255, 215, 0, 0.3);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            font-size: 1rem;
            box-sizing: border-box;
        }

        .form-textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid rgba(255, 215, 0, 0.3);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            font-size: 1rem;
            resize: vertical;
            min-height: 100px;
            box-sizing: border-box;
        }

        .form-help {
            color: #ccc;
            font-size: 0.8rem;
            margin-top: 0.25rem;
            display: block;
        }

        /* Responsive Form Styles */
        @media (max-width: 768px) {
            .form-group {
                margin-bottom: 1rem;
            }
            
            .form-label {
                font-size: 0.85rem;
                margin-bottom: 0.4rem;
            }
            
            .form-input,
            .form-select,
            .form-textarea {
                padding: 0.6rem;
                font-size: 0.9rem;
            }
            
            .form-help {
                font-size: 0.75rem;
            }
        }

        @media (max-width: 480px) {
            .form-group {
                margin-bottom: 0.8rem;
            }
            
            .form-input,
            .form-select,
            .form-textarea {
                padding: 0.5rem;
                font-size: 0.85rem;
            }
            
            .form-textarea {
                min-height: 80px;
            }
        }

        /* Expanded Details Styles */
        .order-details-expanded {
            margin-top: 20px;
            border-top: 1px solid rgba(255, 215, 0, 0.2);
            padding-top: 20px;
        }

        .details-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 1px solid rgba(255, 215, 0, 0.2);
        }

        .tab-btn {
            background: transparent;
            border: none;
            color: #000000;
            padding: 10px 20px;
            cursor: pointer;
            font-weight: 600;
            border-bottom: 2px solid transparent;
            transition: all 0.3s ease;
        }

        .tab-btn.active {
            color: #E60012;
            border-bottom-color: #E60012;
        }

        .tab-btn:hover {
            color: #ffd700;
        }

        .tab-content {
            padding: 20px 0;
        }

        .overview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .overview-item {
            background: hsla(0, 0%, 0%, 0.50);
            padding: 20px;
            border-radius: 10px;
            border: 1px solid rgba(255, 215, 0, 0.1);
        }

        .overview-item h4 {
            color: #ffd700;
            margin-bottom: 15px;
            font-size: 1.1rem;
            font-weight: 700;
        }

        .overview-item p {
            margin-bottom: 8px;
            color: #ccc;
        }

        .overview-item strong {
            color: white;
        }

        /* Payment Tables */
        .payment-history-table,
        .payment-schedule-table {
            overflow-x: auto;
            border-radius: 10px;
            background: rgba(0, 0, 0, 0.50);
        }

        .payment-history-table table,
        .payment-schedule-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .payment-history-table th,
        .payment-schedule-table th,
        .payment-history-table td,
        .payment-schedule-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 215, 0, 0.1);
        }

        .payment-history-table th,
        .payment-schedule-table th {
            background: rgba(255, 215, 0, 0.1);
            color: #ffd700;
            font-weight: 700;
            font-size: 0.9rem;
        }

        .payment-history-table td,
        .payment-schedule-table td {
            color: #ffff;
        }

        .amortization-summary {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .summary-item {
            text-align: center;
        }

        .summary-label {
            color: #ffd700;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .summary-value {
            color: white;
            font-size: 1.1rem;
            font-weight: 700;
        }

        .overdue {
            background: rgba(220, 53, 69, 0.1) !important;
        }

        .overdue-text {
            color: #dc3545;
            font-weight: 600;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending {
            background: rgba(255, 193, 7, 0.2);
            color: #ffc107;
        }

        .status-confirmed {
            background: rgba(40, 167, 69, 0.2);
            color: #28a745;
        }

        .status-paid {
            background: rgba(76, 175, 80, 0.2);
            color: #4caf50;
        }

        .status-overdue {
            background: rgba(220, 53, 69, 0.2);
            color: #dc3545;
        }

        .status-partial {
            background: rgba(255, 152, 0, 0.2);
            color: #ff9800;
        }

        .status-failed {
            background: rgba(220, 53, 69, 0.2);
            color: #dc3545;
        }

        .status-cancelled {
            background: rgba(158, 158, 158, 0.2);
            color: #9e9e9e;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #ccc;
            font-style: italic;
        }

        .error {
            text-align: center;
            padding: 20px;
            color: #ff6b6b;
            background: rgba(220, 53, 69, 0.1);
            border-radius: 10px;
            border: 1px solid rgba(220, 53, 69, 0.2);
        }

        @media (max-width: 768px) {
            .container {
                padding: 20px 15px;
            }

            .page-title {
                font-size: 2rem;
            }

            .orders-container {
                padding: 20px;
            }

            .order-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .order-details {
                grid-template-columns: 1fr;
            }

            .order-actions {
                flex-direction: column;
            }

            .btn {
                justify-content: center;
            }

            .overview-grid {
                grid-template-columns: 1fr;
            }

            .details-tabs {
                flex-wrap: wrap;
            }

            .tab-btn {
                flex: 1;
                min-width: 120px;
            }

            .payment-history-table,
            .payment-schedule-table {
                font-size: 0.85rem;
            }

            .payment-history-table th,
            .payment-schedule-table th,
            .payment-history-table td,
            .payment-schedule-table td {
                padding: 8px 10px;
            }
        }
    </style>
</head>

<body>
    <header class="header">
        <div class="logo-section">
            <img src="../includes/images/mitsubishi_logo.png" alt="Mitsubishi Logo" class="logo">
            <div class="brand-text">MITSUBISHI MOTORS</div>
        </div>
        <div class="user-section">
            <div class="user-avatar"><?php echo strtoupper(substr($displayName, 0, 1)); ?></div>
            <span class="welcome-text">Welcome, <?php echo htmlspecialchars($displayName); ?>!</span>
            <button class="logout-btn" onclick="window.location.href='logout.php'"><i class="fas fa-sign-out-alt"></i> Logout</button>
        </div>
    </header>

    <div class="container">
        <a href="customer.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        <h1 class="page-title">My Orders</h1>

        <div class="orders-container">
            <div id="ordersContent">
                <div class="loading">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; margin-bottom: 15px;"></i>
                    <p>Loading your orders...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div id="paymentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Submit Payment</h2>
                <span class="close" onclick="closePaymentModal()">&times;</span>
            </div>
            <form id="paymentForm">
                <input type="hidden" id="paymentOrderId" name="order_id">
                <div class="form-group">
                    <label class="form-label">Amount *</label>
                    <input type="number" id="paymentAmount" name="amount" class="form-input" step="0.01" min="1" readonly required>
                    <small class="form-help">Payment amount is automatically set based on your monthly payment term</small>
                </div>
                <div class="form-group">
                    <label class="form-label">Payment Method *</label>
                    <select id="paymentMethod" name="payment_method" class="form-select" required>
                        <option value="">Select Payment Method</option>
                        <option value="Cash">Cash</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="Check">Check</option>
                        <option value="Credit Card">Credit Card</option>
                        <option value="Online Payment">Online Payment</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Reference Number</label>
                    <input type="text" id="referenceNumber" name="reference_number" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Bank Name</label>
                    <input type="text" id="bankName" name="bank_name" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Payment Receipt *</label>
                    <input type="file" id="paymentReceipt" name="payment_receipt" class="form-input" accept="image/*,.pdf" required>
                    <small class="form-help">Upload your payment receipt (JPG, PNG, or PDF format)</small>
                </div>
                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <textarea id="paymentNotes" name="notes" class="form-textarea" placeholder="Additional notes..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closePaymentModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Payment</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Load orders on page load
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 DOM Content Loaded, initializing page');
            console.log('📡 Current URL:', window.location.href);
            console.log('👤 User agent:', navigator.userAgent);
            loadOrders();
        });

        function loadOrders() {
            console.log('🔄 Starting loadOrders function');
            
            fetch('../includes/backend/order_backend.php?action=get_customer_orders', {
                method: 'GET',
                credentials: 'include',
                cache: 'no-store',
                headers: {
                    'Accept': 'application/json'
                }
            })
                .then(response => {
                    console.log('📡 Received response:', response);
                    console.log('📊 Response status:', response.status);
                    console.log('📋 Response headers:', response.headers);

                    // Handle unauthorized explicitly
                    if (response.status === 401 || response.status === 403) {
                        return response.text().then(text => {
                            console.warn('🔒 Unauthorized response text:', text);
                            let message = 'Unauthorized access. Please log in again.';
                            try {
                                if (text && text.trim()) {
                                    const j = JSON.parse(text);
                                    if (j && (j.error || j.message)) {
                                        message = j.error || j.message;
                                    }
                                }
                            } catch (e) {
                                // ignore parse error here
                            }
                            throw new Error(message);
                        });
                    }
                    
                    // Clone response to read text first
                    return response.clone().text().then(text => {
                        console.log('📝 Raw response text:', text);
                        const trimmed = (text || '').trim();
                        if (!trimmed) {
                            throw new Error('Empty response body');
                        }
                        
                        // Try to parse JSON
                        try {
                            const jsonData = JSON.parse(trimmed);
                            console.log('✅ Successfully parsed JSON:', jsonData);
                            return jsonData;
                        } catch (parseError) {
                            console.error('❌ JSON Parse Error:', parseError);
                            console.error('🔍 Failed to parse text:', trimmed);
                            throw new Error('Invalid JSON response: ' + parseError.message);
                        }
                    });
                })
                .then(data => {
                    console.log('📦 Processing data:', data);
                    
                    if (data.success) {
                        console.log('✅ Data success, orders:', data.data);
                        displayOrders(data.data);
                    } else {
                        console.error('❌ Data error:', data.error);
                        showError(data.error || 'Failed to load orders');
                    }
                })
                .catch(error => {
                    console.error('🚨 Fetch error:', error);
                    console.error('🔍 Error stack:', error.stack);
                    // Provide a more helpful message for auth issues
                    if (/unauthorized/i.test(error.message)) {
                        showError('You are not logged in or your session expired. Please log in again.');
                    } else if (/empty response body/i.test(error.message)) {
                        showError('Server returned an empty response. Please try again.');
                    } else {
                        showError('Connection error: ' + error.message);
                    }
                });
        }

        function displayOrders(orders) {
            console.log('🎨 Starting displayOrders function with orders:', orders);
            const container = document.getElementById('ordersContent');
            console.log('📍 Container element:', container);

            if (orders.length === 0) {
                console.log('📭 No orders found, showing empty state');
                container.innerHTML = `
                    <div class="no-orders">
                        <i class="fas fa-shopping-cart" style="font-size: 3rem; margin-bottom: 20px; color: #ffd700;"></i>
                        <h3 style="color: #ffd700; margin-bottom: 15px;">No Orders Found</h3>
                        <p>You haven't placed any orders yet. Visit our car menu to explore available vehicles.</p>
                        <a href="car_menu.php" class="btn btn-primary" style="margin-top: 20px;">
                            <i class="fas fa-car"></i> Browse Cars
                        </a>
                    </div>
                `;
                return;
            }

            let html = '';
            orders.forEach(order => {
                const statusClass = `status-${order.status.toLowerCase().replace(' ', '-')}`;
                const progressPercent = order.payment_progress || 0;

                html += `
                    <div class="order-card" data-order-id="${order.id}">
                        <div class="order-header">
                            <div class="order-info">
                                <h3>${order.model_name}</h3>
                                <p>Order #${order.order_number} • ${new Date(order.created_at).toLocaleDateString()}</p>
                            </div>
                            <div class="order-status ${statusClass}">${order.status}</div>
                        </div>
                        
                        <div class="order-details">
                            <div class="detail-item">
                                <div class="detail-label">Vehicle</div>
                                <div class="detail-value">${order.model_name}${order.variant ? ' - ' + order.variant : ''}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Total Amount</div>
                                <div class="detail-value">₱${parseFloat(order.total_amount).toLocaleString('en-US', {minimumFractionDigits: 2})}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Amount Paid</div>
                                <div class="detail-value">₱${parseFloat(order.total_paid || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Remaining Balance</div>
                                <div class="detail-value">₱${parseFloat(order.remaining_balance || order.total_amount).toLocaleString('en-US', {minimumFractionDigits: 2})}</div>
                            </div>
                        </div>
                        
                        <div style="margin: 15px 0;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                                <span style="color: #ffd700; font-weight: 600;">Payment Progress</span>
                                <span style="color: white;">${progressPercent}%</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: ${progressPercent}%"></div>
                            </div>
                        </div>
                        
                        <div class="order-actions">
                            <button class="btn btn-primary" onclick="openPaymentModal(${order.id})">
                                <i class="fas fa-credit-card"></i> Make Payment
                            </button>
                            <button class="btn btn-secondary toggle-details" onclick="viewOrderDetails(${order.id})">
                                <i class="fas fa-chevron-down"></i> View Details
                            </button>
                            <button class="btn btn-secondary" onclick="viewPaymentHistory(${order.id})">
                                <i class="fas fa-history"></i> Payment History
                            </button>
                        </div>
                        
                        <div class="order-details-expanded" style="display: none;">
                            <div class="order-details-content">
                                <!-- Details will be loaded here -->
                            </div>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
        }

        function openPaymentModal(orderId) {
            console.log('💳 Opening payment modal for order ID:', orderId);
            document.getElementById('paymentOrderId').value = orderId;
            
            // Fetch order details to get monthly payment amount
            console.log('📡 Fetching order details for payment modal...');
            fetch(`../includes/backend/order_backend.php?action=get_order_details&order_id=${orderId}`, {
                method: 'GET',
                credentials: 'include',
                cache: 'no-store',
                headers: { 'Accept': 'application/json' }
            })
                .then(response => {
                    if (response.status === 401 || response.status === 403) {
                        return response.text().then(text => {
                            let message = 'Unauthorized access. Please log in again.';
                            try {
                                if (text && text.trim()) {
                                    const j = JSON.parse(text);
                                    if (j && (j.error || j.message)) message = j.error || j.message;
                                }
                            } catch (e) {}
                            throw new Error(message);
                        });
                    }
                    return response.clone().text().then(text => {
                        const trimmed = (text || '').trim();
                        if (!trimmed) throw new Error('Empty response body');
                        try { return JSON.parse(trimmed); }
                        catch (e) { throw new Error('Invalid JSON response: ' + e.message); }
                    });
                })
                .then(data => {
                    if (data.success && data.data) {
                        const order = data.data;
                        // Auto-populate payment amount with monthly payment if available
                        if (order.monthly_payment && parseFloat(order.monthly_payment) > 0) {
                            document.getElementById('paymentAmount').value = parseFloat(order.monthly_payment).toFixed(2);
                        } else {
                            // If no monthly payment, clear the field
                            document.getElementById('paymentAmount').value = '';
                        }
                    }
                })
                .catch(error => {
                    console.error('Error fetching order details:', error);
                    // Still show modal even if fetch fails
                });
            
            document.getElementById('paymentModal').style.display = 'block';
        }

        function closePaymentModal() {
            document.getElementById('paymentModal').style.display = 'none';
            document.getElementById('paymentForm').reset();
        }

        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            console.log('💳 Payment form submitted');
            e.preventDefault();
            
            // Validate file upload
            const receiptFile = document.getElementById('paymentReceipt').files[0];
            console.log('📎 Receipt file:', receiptFile);
            if (!receiptFile) {
                console.error('❌ No receipt file selected');
                alert('Please upload a payment receipt.');
                return;
            }
            
            // Check file size (max 5MB)
            if (receiptFile.size > 5 * 1024 * 1024) {
                alert('Receipt file size must be less than 5MB.');
                return;
            }
            
            // Check file type
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
            if (!allowedTypes.includes(receiptFile.type)) {
                alert('Please upload a valid image (JPG, PNG) or PDF file.');
                return;
            }

            const formData = new FormData(this);
            formData.append('action', 'submit_payment');
            console.log('📋 Form data prepared:', formData);
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Uploading...';
            submitBtn.disabled = true;
            console.log('⏳ Submit button disabled, starting upload');

            fetch('../includes/backend/order_backend.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'include',
                    cache: 'no-store'
                })
                .then(response => {
                    console.log('📡 Payment submission response:', response);
                    console.log('📊 Response status:', response.status);
                    
                    if (response.status === 401 || response.status === 403) {
                        return response.text().then(text => {
                            let message = 'Unauthorized access. Please log in again.';
                            try {
                                if (text && text.trim()) {
                                    const j = JSON.parse(text);
                                    if (j && (j.error || j.message)) message = j.error || j.message;
                                }
                            } catch (e) {}
                            throw new Error(message);
                        });
                    }
                    
                    return response.clone().text().then(text => {
                        console.log('📝 Raw payment response:', text);
                        const trimmed = (text || '').trim();
                        if (!trimmed) throw new Error('Empty response body');
                        try {
                            const jsonData = JSON.parse(trimmed);
                            console.log('✅ Payment response JSON:', jsonData);
                            return jsonData;
                        } catch (parseError) {
                            console.error('❌ Payment JSON Parse Error:', parseError);
                            console.error('🔍 Failed to parse payment response:', trimmed);
                            throw new Error('Invalid JSON response: ' + parseError.message);
                        }
                    });
                })
                .then(data => {
                    console.log('📦 Payment data processed:', data);
                    if (data.success) {
                        console.log('✅ Payment submitted successfully:', data.payment_number);
                        alert('Payment submitted successfully! Reference: ' + data.payment_number + '\nYour payment will be reviewed by our team.');
                        closePaymentModal();
                        loadOrders(); // Reload orders
                    } else {
                        console.error('❌ Payment submission failed:', data.error);
                        alert('Error: ' + (data.error || 'Failed to submit payment'));
                    }
                })
                .catch(error => {
                    console.error('🚨 Payment submission error:', error);
                    console.error('🔍 Error stack:', error.stack);
                    if (/unauthorized/i.test(error.message)) {
                        alert('You are not logged in or your session expired. Please log in again.');
                    } else if (/empty response body/i.test(error.message)) {
                        alert('Server returned an empty response. Please try again.');
                    } else {
                        alert('Connection error: ' + error.message);
                    }
                })
                .finally(() => {
                    submitBtn.textContent = originalText;
                    submitBtn.disabled = false;
                });
        });

        function viewOrderDetails(orderId) {
            const orderCard = document.querySelector(`[data-order-id="${orderId}"]`);
            const detailsSection = orderCard.querySelector('.order-details-expanded');
            
            if (detailsSection.style.display === 'none' || !detailsSection.style.display) {
                loadOrderDetails(orderId);
                detailsSection.style.display = 'block';
                orderCard.querySelector('.toggle-details').innerHTML = '<i class="fas fa-chevron-up"></i> Hide Details';
            } else {
                detailsSection.style.display = 'none';
                orderCard.querySelector('.toggle-details').innerHTML = '<i class="fas fa-chevron-down"></i> View Details';
            }
        }

        function loadOrderDetails(orderId) {
            console.log('📋 Loading order details for ID:', orderId);
            const detailsContainer = document.querySelector(`[data-order-id="${orderId}"] .order-details-content`);
            console.log('📍 Details container:', detailsContainer);
            detailsContainer.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Loading details...</div>';
            
            console.log('📡 Making parallel API calls for order details...');
            Promise.all([
                fetch(`../includes/backend/order_backend.php?action=get_order_details&order_id=${orderId}`, { method: 'GET', credentials: 'include', cache: 'no-store', headers: { 'Accept': 'application/json' } }),
                fetch(`../includes/backend/order_backend.php?action=get_payment_history&order_id=${orderId}`, { method: 'GET', credentials: 'include', cache: 'no-store', headers: { 'Accept': 'application/json' } }),
                fetch(`../includes/backend/order_backend.php?action=get_payment_schedule&order_id=${orderId}`, { method: 'GET', credentials: 'include', cache: 'no-store', headers: { 'Accept': 'application/json' } })
            ])
            .then(responses => {
                console.log('📡 Received all responses:', responses);
                return Promise.all(responses.map((r, index) => {
                    console.log(`📊 Response ${index} status:`, r.status);
                    if (r.status === 401 || r.status === 403) {
                        return r.text().then(text => {
                            let message = 'Unauthorized access. Please log in again.';
                            try {
                                if (text && text.trim()) {
                                    const j = JSON.parse(text);
                                    if (j && (j.error || j.message)) message = j.error || j.message;
                                }
                            } catch (e) {}
                            throw new Error(message);
                        });
                    }
                    return r.clone().text().then(text => {
                        const trimmed = (text || '').trim();
                        if (!trimmed) throw new Error('Empty response body');
                        try {
                            const json = JSON.parse(trimmed);
                            console.log(`📦 Response ${index} data:`, json);
                            return json;
                        } catch (parseError) {
                            console.error('❌ JSON Parse Error:', parseError);
                            console.error('🔍 Failed to parse text:', trimmed);
                            throw new Error('Invalid JSON response: ' + parseError.message);
                        }
                    });
                }));
            })
            .then(([orderDetails, paymentHistory, paymentSchedule]) => {
                console.log('📋 Order details:', orderDetails);
                console.log('💰 Payment history:', paymentHistory);
                console.log('📅 Payment schedule:', paymentSchedule);
                
                if (orderDetails.success && paymentHistory.success && paymentSchedule.success) {
                    console.log('✅ All API calls successful, displaying details');
                    displayOrderDetails(orderId, orderDetails.data, paymentHistory.data, paymentSchedule.data);
                } else {
                    console.error('❌ One or more API calls failed');
                    console.error('Order details success:', orderDetails.success);
                    console.error('Payment history success:', paymentHistory.success);
                    console.error('Payment schedule success:', paymentSchedule.success);
                    detailsContainer.innerHTML = '<div class="error">Failed to load order details</div>';
                }
            })
            .catch(error => {
                console.error('🚨 Error loading order details:', error);
                console.error('🔍 Error stack:', error.stack);
                detailsContainer.innerHTML = '<div class="error">Connection error: ' + error.message + '</div>';
            });
        }

        function displayOrderDetails(orderId, order, payments, schedule) {
            const detailsContainer = document.querySelector(`[data-order-id="${orderId}"] .order-details-content`);
            
            let html = `
                <div class="expanded-details">
                    <div class="details-tabs">
                        <button class="tab-btn active" onclick="showTab(${orderId}, 'overview')">Overview</button>
                        <button class="tab-btn" onclick="showTab(${orderId}, 'payments')">Payment History</button>
                        <button class="tab-btn" onclick="showTab(${orderId}, 'schedule')">Payment Schedule</button>
                        <button class="tab-btn" onclick="showTab(${orderId}, 'amortization')">Amortization</button>
                    </div>
                    
                    <div class="tab-content" id="overview-${orderId}">
                        <div class="overview-grid">
                            <div class="overview-item">
                                <h4>Vehicle Information</h4>
                                <p><strong>Model:</strong> ${order.model_name}</p>
                                <p><strong>Variant:</strong> ${order.variant || 'N/A'}</p>
                                <p><strong>Year:</strong> ${order.year_model}</p>
                                <p><strong>Color:</strong> ${order.vehicle_color || 'N/A'}</p>
                            </div>
                            <div class="overview-item">
                                <h4>Pricing Details</h4>
                                <p><strong>Base Price:</strong> ₱${parseFloat(order.base_price).toLocaleString('en-US', {minimumFractionDigits: 2})}</p>
                                <p><strong>Discount:</strong> ₱${parseFloat(order.discount_amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</p>
                                <p><strong>Total Price:</strong> ₱${parseFloat(order.total_amount).toLocaleString('en-US', {minimumFractionDigits: 2})}</p>
                            </div>
                            <div class="overview-item">
                                <h4>Payment Information</h4>
                                <p><strong>Payment Method:</strong> ${order.payment_method}</p>
                                <p><strong>Down Payment:</strong> ₱${parseFloat(order.down_payment || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</p>
                                <p><strong>Monthly Payment:</strong> ₱${parseFloat(order.monthly_payment || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</p>
                                <p><strong>Financing Term:</strong> ${order.financing_term || 'N/A'}</p>
                            </div>
                            <div class="overview-item">
                                <h4>Delivery Information</h4>
                                <p><strong>Expected Delivery:</strong> ${order.delivery_date ? new Date(order.delivery_date).toLocaleDateString() : 'TBD'}</p>
                                <p><strong>Actual Delivery:</strong> ${order.actual_delivery_date ? new Date(order.actual_delivery_date).toLocaleDateString() : 'Pending'}</p>
                                <p><strong>Address:</strong> ${order.delivery_address || 'N/A'}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="tab-content" id="payments-${orderId}" style="display: none;">
                        ${displayPaymentHistory(payments)}
                    </div>
                    
                    <div class="tab-content" id="schedule-${orderId}" style="display: none;">
                        ${displayPaymentSchedule(schedule)}
                    </div>
                    
                    <div class="tab-content" id="amortization-${orderId}" style="display: none;">
                        ${displayAmortizationTable(order, schedule)}
                    </div>
                </div>
            `;
            
            detailsContainer.innerHTML = html;
        }

        function displayPaymentHistory(payments) {
            if (payments.length === 0) {
                return '<div class="no-data">No payment history found.</div>';
            }
            
            let html = '<div class="payment-history-table"><table><thead><tr><th>Date</th><th>Payment #</th><th>Amount</th><th>Type</th><th>Method</th><th>Total Paid</th><th>Remaining Balance</th><th>Status</th></tr></thead><tbody>';
            
            payments.forEach(payment => {
                const statusClass = payment.status.toLowerCase();
                html += `
                    <tr>
                        <td>${new Date(payment.payment_date).toLocaleDateString()}</td>
                        <td>${payment.payment_number || 'N/A'}</td>
                        <td>₱${parseFloat(payment.amount_paid).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                        <td>${payment.payment_type || 'Payment'}</td>
                        <td>${payment.payment_method || 'N/A'}</td>
                        <td>₱${parseFloat(payment.total_paid || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                        <td>₱${parseFloat(payment.remaining_balance || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                        <td><span class="status-badge status-${statusClass}">${payment.status}</span></td>
                    </tr>
                `;
            });
            
            html += '</tbody></table></div>';
            return html;
        }

        function displayPaymentSchedule(schedule) {
            if (schedule.length === 0) {
                return '<div class="no-data">No payment schedule found.</div>';
            }
            
            let html = '<div class="payment-schedule-table"><table><thead><tr><th>Payment #</th><th>Due Date</th><th>Amount Due</th><th>Amount Paid</th><th>Balance</th><th>Days Overdue</th><th>Status</th></tr></thead><tbody>';
            
            const today = new Date();
            
            schedule.forEach(payment => {
                const statusClass = payment.status.toLowerCase();
                const dueDate = new Date(payment.due_date);
                const balance = parseFloat(payment.amount_due) - parseFloat(payment.amount_paid || 0);
                
                // Calculate days overdue
                let daysOverdue = 0;
                if (payment.status === 'Pending' && dueDate < today) {
                    daysOverdue = Math.floor((today - dueDate) / (1000 * 60 * 60 * 24));
                }
                
                const overdueClass = daysOverdue > 0 ? 'overdue' : '';
                
                html += `
                    <tr class="${overdueClass}">
                        <td>${payment.payment_number}</td>
                        <td>${dueDate.toLocaleDateString()}</td>
                        <td>₱${parseFloat(payment.amount_due).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                        <td>₱${parseFloat(payment.amount_paid || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                        <td>₱${balance.toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                        <td>${daysOverdue > 0 ? `<span class="overdue-text">${daysOverdue} days</span>` : '-'}</td>
                        <td><span class="status-badge status-${statusClass}">${payment.status}</span></td>
                    </tr>
                `;
            });
            
            html += '</tbody></table></div>';
            return html;
        }
        
        function displayAmortizationTable(order, schedule) {
            if (order.payment_method !== 'financing' || !order.financing_term || !order.monthly_payment) {
                return '<div class="no-data">Amortization table is only available for financing orders.</div>';
            }
            
            const principal = parseFloat(order.total_price) - parseFloat(order.down_payment || 0);
            const monthlyPayment = parseFloat(order.monthly_payment);
            const annualRate = 0.12; // 12% annual interest rate
            const monthlyRate = annualRate / 12;
            const months = parseInt(order.financing_term.match(/\d+/)[0]);
            
            let html = `
                <div class="amortization-summary">
                    <div class="summary-grid">
                        <div class="summary-item">
                            <div class="summary-label">Loan Amount</div>
                            <div class="summary-value">₱${principal.toLocaleString('en-US', {minimumFractionDigits: 2})}</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-label">Monthly Payment</div>
                            <div class="summary-value">₱${monthlyPayment.toLocaleString('en-US', {minimumFractionDigits: 2})}</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-label">Interest Rate</div>
                            <div class="summary-value">${(annualRate * 100).toFixed(1)}% per annum</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-label">Term</div>
                            <div class="summary-value">${months} months</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-label">Total Payments</div>
                            <div class="summary-value">₱${(monthlyPayment * months).toLocaleString('en-US', {minimumFractionDigits: 2})}</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-label">Total Interest</div>
                            <div class="summary-value">₱${((monthlyPayment * months) - principal).toLocaleString('en-US', {minimumFractionDigits: 2})}</div>
                        </div>
                    </div>
                </div>
                <div class="payment-schedule-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Payment #</th>
                                <th>Due Date</th>
                                <th>Monthly Payment</th>
                                <th>Principal</th>
                                <th>Interest</th>
                                <th>Remaining Balance</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            let remainingBalance = principal;
            
            for (let i = 1; i <= months; i++) {
                const interestPayment = remainingBalance * monthlyRate;
                const principalPayment = monthlyPayment - interestPayment;
                remainingBalance = Math.max(0, remainingBalance - principalPayment);
                
                // Find corresponding schedule entry
                const scheduleEntry = schedule.find(s => s.payment_number == i);
                const status = scheduleEntry ? scheduleEntry.status : 'Pending';
                const dueDate = scheduleEntry ? new Date(scheduleEntry.due_date).toLocaleDateString() : 'N/A';
                const statusClass = status.toLowerCase();
                
                html += `
                    <tr>
                        <td>${i}</td>
                        <td>${dueDate}</td>
                        <td>₱${monthlyPayment.toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                        <td>₱${principalPayment.toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                        <td>₱${interestPayment.toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                        <td>₱${remainingBalance.toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                        <td><span class="status-badge status-${statusClass}">${status}</span></td>
                    </tr>
                `;
            }
            
            html += '</tbody></table></div>';
            return html;
        }

        function showTab(orderId, tabName) {
            const orderCard = document.querySelector(`[data-order-id="${orderId}"]`);
            
            // Hide all tab contents
            orderCard.querySelectorAll('.tab-content').forEach(content => {
                content.style.display = 'none';
            });
            
            // Remove active class from all tabs
            orderCard.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab content
            const selectedContent = orderCard.querySelector(`#${tabName}-${orderId}`);
            if (selectedContent) {
                selectedContent.style.display = 'block';
            }
            
            // Add active class to selected tab
            event.target.classList.add('active');
        }

        function viewPaymentHistory(orderId) {
            viewOrderDetails(orderId);
            setTimeout(() => showTab(orderId, 'payments'), 100);
        }

        function showError(message) {
            console.error('🚨 Showing error to user:', message);
            console.error('📍 Error container:', document.getElementById('ordersContent'));
            document.getElementById('ordersContent').innerHTML = `
                <div class="no-orders">
                    <i class="fas fa-exclamation-triangle" style="font-size: 3rem; margin-bottom: 20px; color: #ff6b6b;"></i>
                    <h3 style="color: #ff6b6b; margin-bottom: 15px;">Error Loading Orders</h3>
                    <p>${message}</p>
                    <button class="btn btn-primary" onclick="loadOrders()" style="margin-top: 20px;">
                        <i class="fas fa-redo"></i> Try Again
                    </button>
                </div>
            `;
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('paymentModal');
            if (event.target == modal) {
                closePaymentModal();
            }
        }
    </script>
</body>

</html>