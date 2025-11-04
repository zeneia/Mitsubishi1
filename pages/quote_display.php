<?php
session_start();
include_once(dirname(__DIR__) . '/includes/database/db_conn.php');

// Check if user is logged in and is a customer
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Customer') {
    header("Location: login.php");
    exit;
}

// Get quote ID from URL
$quote_id = isset($_GET['quote_id']) ? (int)$_GET['quote_id'] : null;

if (!$quote_id) {
    header("Location: customer.php");
    exit;
}

try {
    // Fetch quote details with vehicle and customer information
    $stmt = $connect->prepare("
        SELECT q.*, v.model_name, v.variant, v.year_model, v.base_price, v.promotional_price,
               v.min_downpayment_percentage, v.financing_terms, v.color_options, v.popular_color,
               a.FirstName, a.LastName, a.Email,
               ci.firstname as customer_first, ci.lastname as customer_last, ci.mobile_number
        FROM quotes q 
        LEFT JOIN vehicles v ON q.VehicleId = v.id 
        LEFT JOIN accounts a ON q.AccountId = a.Id
        LEFT JOIN customer_information ci ON q.AccountId = ci.account_id
        WHERE q.Id = ? AND q.AccountId = ?
    ");
    $stmt->execute([$quote_id, $_SESSION['user_id']]);
    $quote = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$quote) {
        header("Location: customer.php");
        exit;
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    header("Location: customer.php");
    exit;
}

// Generate quote number
$quote_number = 'MQAV-' . date('Ymd', strtotime($quote['RequestedAt'])) . '-' . str_pad($quote['Id'], 4, '0', STR_PAD_LEFT);

// Calculate pricing details
$base_price = $quote['base_price'] ?: 0;
$promotional_price = $quote['promotional_price'] ?: 0;
$final_price = $promotional_price > 0 && $promotional_price < $base_price ? $promotional_price : $base_price;

// Calculate down payment
$down_payment_percentage = $quote['min_downpayment_percentage'] ?: 20;
$down_payment = $final_price * ($down_payment_percentage / 100);

// Additional features and discounts (sample data)
$additional_features = 25000;
$discounts = 30000;
$total_price = $final_price + $additional_features - $discounts;

// Calculate financing
$financing_months = 36;
$monthly_payment = ($total_price - $down_payment) / $financing_months;

// Get user display name
$displayName = !empty($quote['customer_first']) ? $quote['customer_first'] : 
               (!empty($quote['FirstName']) ? $quote['FirstName'] : 'Customer');

// Due date (30 days from issue)
$due_date = date('d/m/Y', strtotime($quote['RequestedAt'] . ' + 30 days'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quote <?php echo htmlspecialchars($quote_number); ?> - Mitsubishi Motors</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', 'Segoe UI', sans-serif; }
        body { background: linear-gradient(135deg, #0f0f0f 0%, #1a1a1a 25%, #2d1b1b 50%, #8b0000 75%, #b80000 100%); min-height: 100vh; color: white; }
        
        .header { background: rgba(0, 0, 0, 0.4); padding: 20px 30px; display: flex; justify-content: space-between; align-items: center; backdrop-filter: blur(20px); border-bottom: 1px solid rgba(255, 215, 0, 0.2); position: relative; z-index: 10; }
        .logo-section { display: flex; align-items: center; gap: 20px; }
        .logo { width: 60px; height: auto; filter: drop-shadow(0 0 10px rgba(255, 215, 0, 0.3)); }
        .brand-text { font-size: 1.4rem; font-weight: 700; background: #FFFFFF; -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .user-section { display: flex; align-items: center; gap: 20px; }
        <!--.user-avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(45deg, #ffd700, #ffed4e); display: flex; align-items: center; justify-content: center; font-weight: bold; color: #b80000; font-size: 1.2rem; }-->
        .welcome-text { font-size: 1rem; font-weight: 500; }
        .logout-btn { background: linear-gradient(45deg, #d60000, #b30000); color: white; border: none; padding: 12px 24px; border-radius: 25px; cursor: pointer; font-size: 0.9rem; font-weight: 600; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(214, 0, 0, 0.3); }
        .logout-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(214, 0, 0, 0.5); }
        
        .container { max-width: 900px; margin: 0 auto; padding: 30px 20px; position: relative; z-index: 5; }
        .back-btn { display: inline-block; margin-bottom: 20px; background: rgba(255, 255, 255, 0.1); color: #ffd700; padding: 8px 16px; border-radius: 8px; text-decoration: none; font-weight: 500; transition: all 0.3s ease; font-size: 0.9rem; }
        .back-btn:hover { background: #ffd700; color: #1a1a1a; }

        .quote-document {
            background: white;
            color: #333;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            margin-bottom: 30px;
        }

        .quote-header {
            background: linear-gradient(135deg, #d32f2f, #b71c1c);
            color: white;
            padding: 20px;
            text-align: center;
            position: relative;
        }

        .quote-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .mitsubishi-logo {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            opacity: 0.1;
            font-size: 4rem;
        }

        .quote-body {
            padding: 30px;
            background: #f5f5f5;
        }

        .company-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .company-logo {
            width: 60px;
            height: 60px;
            margin: 0 auto 10px;
            background: #d32f2f;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }

        .company-name {
            font-size: 1.3rem;
            font-weight: 700;
            color: #d32f2f;
            margin-bottom: 5px;
        }

        .quote-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-section {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #d32f2f;
        }

        .info-label {
            font-size: 0.9rem;
            font-weight: 600;
            color: #666;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 1rem;
            font-weight: 700;
            color: #333;
        }

        .customer-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .detail-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .detail-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #d32f2f;
            margin-bottom: 15px;
            border-bottom: 2px solid #d32f2f;
            padding-bottom: 5px;
        }

        .detail-item {
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
        }

        .detail-label {
            font-weight: 600;
            color: #666;
        }

        .detail-value {
            font-weight: 500;
            color: #333;
        }

        .vehicle-specs {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }

        .spec-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .spec-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .spec-value {
            font-size: 1rem;
            font-weight: 700;
            color: #d32f2f;
        }

        .pricing-section {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .pricing-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        .payment-terms, .pricing-details {
            padding: 20px;
            background: #f9f9f9;
            border-radius: 8px;
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: #d32f2f;
            margin-bottom: 15px;
            text-transform: uppercase;
        }

        .price-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }

        .price-item:last-child {
            border-bottom: none;
            font-weight: 700;
            font-size: 1.1rem;
            color: #d32f2f;
            border-top: 2px solid #d32f2f;
            padding-top: 15px;
            margin-top: 15px;
        }

        .total-highlight {
            background: #d32f2f;
            color: white !important;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            font-size: 1.3rem;
            font-weight: 700;
            margin-top: 20px;
        }

        .action-buttons {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 20px;
        }

        .action-btn {
            background: #E60012;
            color: #FFFFFF;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 215, 0, 0.4);
        }

        .action-btn.secondary {
            background: transparent;
            color: #FFFFFF;
            border: 2px solid #808080;
        }

        .action-btn.secondary:hover {
            background: #E60012;
            color: #FFFFFF;
        }

        @media print {
            body { background: white; color: black; }
            .header, .back-btn, .action-buttons { display: none; }
            .container { padding: 0; max-width: 100%; }
        }

        @media (max-width: 575px) {
            .quote-info-grid { 
                grid-template-columns: 1fr; 
                gap: 15px;
            }
            .customer-details { 
                grid-template-columns: 1fr;
                gap: 20px;
            }
            .vehicle-specs { 
                grid-template-columns: 1fr;
                gap: 12px;
            }
            .pricing-grid { 
                grid-template-columns: 1fr;
                gap: 20px;
            }
            .action-buttons { 
                grid-template-columns: 1fr;
                gap: 12px;
            }
            .quote-header {
                padding: 15px;
            }
            .quote-body {
                padding: 20px;
            }
            .quote-title {
                font-size: 1.5rem;
            }
            .container {
                padding: 15px 10px;
            }
        }

        @media (min-width: 576px) and (max-width: 767px) {
            .quote-info-grid { 
                grid-template-columns: repeat(2, 1fr);
            }
            .customer-details { 
                grid-template-columns: 1fr;
            }
            .vehicle-specs { 
                grid-template-columns: repeat(2, 1fr);
            }
            .pricing-grid { 
                grid-template-columns: 1fr;
            }
            .action-buttons { 
                grid-template-columns: repeat(2, 1fr);
            }
            .quote-title {
                font-size: 1.7rem;
            }
            .container {
                padding: 20px 15px;
            }
        }

        @media (min-width: 768px) and (max-width: 991px) {
            .quote-info-grid { 
                grid-template-columns: repeat(3, 1fr);
            }
            .customer-details { 
                grid-template-columns: repeat(2, 1fr);
            }
            .vehicle-specs { 
                grid-template-columns: repeat(3, 1fr);
            }
            .pricing-grid { 
                grid-template-columns: repeat(2, 1fr);
            }
            .action-buttons { 
                grid-template-columns: repeat(2, 1fr);
            }
            .quote-title {
                font-size: 1.8rem;
            }
            .container {
                padding: 25px 20px;
            }
        }

        @media (min-width: 992px) {
            .quote-info-grid { 
                grid-template-columns: repeat(3, 1fr);
            }
            .customer-details { 
                grid-template-columns: repeat(2, 1fr);
            }
            .vehicle-specs { 
                grid-template-columns: repeat(4, 1fr);
            }
            .pricing-grid { 
                grid-template-columns: repeat(2, 1fr);
            }
            .action-buttons { 
                grid-template-columns: repeat(2, 1fr);
            }
            .quote-title {
                font-size: 2rem;
            }
            .container {
                padding: 30px 20px;
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
            <!--<div class="user-avatar"><?php echo strtoupper(substr($displayName, 0, 1)); ?></div>-->
            <span class="welcome-text">Welcome, <?php echo htmlspecialchars($displayName); ?>!</span>
            <button class="logout-btn" onclick="window.location.href='logout.php'"><i class="fas fa-sign-out-alt"></i> Logout</button>
        </div>
    </header>

    <div class="container">
        <a href="quote_success.php?quote_id=<?php echo $quote['Id']; ?>" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back
        </a>

        <div class="quote-document">
            <div class="quote-header">
                <div class="mitsubishi-logo">♦</div>
                <h1 class="quote-title">Quote</h1>
            </div>

            <div class="quote-body">
                <div class="company-header">
                    <div class="company-logo">♦</div>
                    <div class="company-name">MITSUBISHI MOTORS</div>
                </div>

                <div class="quote-info-grid">
                    <div class="info-section">
                        <div class="info-label">QUOTE NUMBER:</div>
                        <div class="info-value"><?php echo htmlspecialchars($quote_number); ?></div>
                    </div>
                    <div class="info-section">
                        <div class="info-label">ISSUE DATE:</div>
                        <div class="info-value"><?php echo date('d/m/Y', strtotime($quote['RequestedAt'])); ?></div>
                    </div>
                    <div class="info-section">
                        <div class="info-label">DUE DATE:</div>
                        <div class="info-value"><?php echo $due_date; ?></div>
                    </div>
                </div>

                <div class="customer-details">
                    <div class="detail-box">
                        <div class="detail-title">TO:</div>
                        <div class="detail-item">
                            <span class="detail-label">Name:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($quote['FirstName'] . ' ' . $quote['LastName']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Email:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($quote['Email']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Phone:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($quote['PhoneNumber']); ?></span>
                        </div>
                    </div>
                    <div class="detail-box">
                        <div class="detail-title">FROM:</div>
                        <div class="detail-item">
                            <span class="detail-value">MITSUBISHI MOTORS SAN PABLO CITY</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-value">Km 85.5 Maharlika Highway, Brgy.San Ignacio, San Pablo City Laguna</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-value">0917 123 0333</span>
                        </div>
                    </div>
                </div>

                <?php if ($quote['model_name']): ?>
                <div class="vehicle-specs">
                    <div class="spec-item">
                        <div class="spec-label">MODEL:</div>
                        <div class="spec-value"><?php echo htmlspecialchars($quote['model_name']); ?><br>
                        <?php echo htmlspecialchars($quote['variant'] ?: 'Standard'); ?></div>
                    </div>
                    <div class="spec-item">
                        <div class="spec-label">VARIANT:</div>
                        <div class="spec-value"><?php echo htmlspecialchars($quote['variant'] ?: 'Standard'); ?></div>
                    </div>
                    <div class="spec-item">
                        <div class="spec-label">COLOR:</div>
                        <div class="spec-value"><?php echo htmlspecialchars($quote['popular_color'] ?: 'Red Metallic'); ?></div>
                    </div>
                    <div class="spec-item">
                        <div class="spec-label">YEAR:</div>
                        <div class="spec-value"><?php echo htmlspecialchars($quote['year_model'] ?: date('Y')); ?></div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="pricing-section">
                    <div class="pricing-grid">
                        <div class="payment-terms">
                            <div class="section-title">Payment Terms:</div>
                            <?php if ($quote['payment_plan_calculated'] == 1 && $quote['monthly_payment'] > 0): ?>
                            <div class="detail-item">
                                <span class="detail-label">Down Payment:</span>
                                <span class="detail-value">₱<?php echo number_format($quote['down_payment'], 2); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Loan Amount:</span>
                                <span class="detail-value">₱<?php echo number_format($quote['loan_amount'], 2); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Financing Term:</span>
                                <span class="detail-value"><?php echo $quote['financing_term']; ?> months</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Monthly Payment:</span>
                                <span class="detail-value">₱<?php echo number_format($quote['monthly_payment'], 2); ?>/month</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Total Interest:</span>
                                <span class="detail-value">₱<?php echo number_format($quote['total_interest'], 2); ?></span>
                            </div>
                            <?php else: ?>
                            <div class="detail-item">
                                <span class="detail-label">Down Payment:</span>
                                <span class="detail-value">₱<?php echo number_format($down_payment, 2); ?> (<?php echo $down_payment_percentage; ?>%)</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Financing Option:</span>
                                <span class="detail-value"><?php echo $financing_months; ?> months at</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label"></span>
                                <span class="detail-value">₱<?php echo number_format($monthly_payment, 2); ?>/month (estimated)</span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="pricing-details">
                            <div class="section-title">Pricing:</div>
                            <?php if ($quote['payment_plan_calculated'] == 1 && $quote['total_amount'] > 0): ?>
                            <div class="price-item">
                                <span>Vehicle Price:</span>
                                <span>₱<?php echo number_format($final_price, 0); ?></span>
                            </div>
                            <div class="price-item">
                                <span>Down Payment:</span>
                                <span>₱<?php echo number_format($quote['down_payment'], 0); ?></span>
                            </div>
                            <div class="price-item">
                                <span>Financed Amount:</span>
                                <span>₱<?php echo number_format($quote['loan_amount'], 0); ?></span>
                            </div>
                            <div class="price-item">
                                <span>Total Amount (with interest):</span>
                                <span>₱<?php echo number_format($quote['total_amount'], 0); ?></span>
                            </div>
                            <?php else: ?>
                            <div class="price-item">
                                <span>Base Price:</span>
                                <span>₱<?php echo number_format($base_price, 0); ?></span>
                            </div>
                            <div class="price-item">
                                <span>Additional Features:</span>
                                <span>₱<?php echo number_format($additional_features, 0); ?></span>
                            </div>
                            <div class="price-item">
                                <span>Discounts:</span>
                                <span>-₱<?php echo number_format($discounts, 0); ?></span>
                            </div>
                            <div class="price-item">
                                <span>Total Price:</span>
                                <span>₱<?php echo number_format($total_price, 0); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="total-highlight">
                        Total Price: ₱<?php echo number_format($total_price, 0); ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="action-buttons">
            <button class="action-btn" onclick="window.print()">
                <i class="fas fa-print"></i> Print Quote
            </button>
            <a href="customer.php" class="action-btn secondary">
                <i class="fas fa-home"></i> Dashboard
            </a>
        </div>
    </div>
</body>
</html>
    </div>
</body>
</html>
