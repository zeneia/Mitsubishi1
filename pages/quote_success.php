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
$quote = null;
$vehicle = null;

if ($quote_id) {
    try {
        // Fetch quote details
        $stmt_quote = $connect->prepare("SELECT q.*, v.model_name, v.variant 
                                        FROM quotes q 
                                        LEFT JOIN vehicles v ON q.VehicleId = v.id 
                                        WHERE q.Id = ? AND q.AccountId = ?");
        $stmt_quote->execute([$quote_id, $_SESSION['user_id']]);
        $quote = $stmt_quote->fetch(PDO::FETCH_ASSOC);
        
        if (!$quote) {
            header("Location: car_menu.php");
            exit;
        }
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        header("Location: car_menu.php");
        exit;
    }
}

// Fetch user details
$stmt = $connect->prepare("SELECT * FROM accounts WHERE Id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$displayName = !empty($user['FirstName']) ? $user['FirstName'] : $user['Username'];

// Generate quote number for display
$quote_number = 'MQAV-' . date('Ymd', strtotime($quote['RequestedAt'])) . '-' . str_pad($quote['Id'], 4, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quote Submitted - Mitsubishi Motors</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', 'Segoe UI', sans-serif; }
        body { background: linear-gradient(135deg, #0f0f0f 0%, #1a1a1a 25%, #2d1b1b 50%, #8b0000 75%, #b80000 100%); min-height: 100vh; color: white; }
        .header { background: rgba(0, 0, 0, 0.4); padding: 20px 30px; display: flex; justify-content: space-between; align-items: center; backdrop-filter: blur(20px); border-bottom: 1px solid rgba(255, 215, 0, 0.2); position: relative; z-index: 10; }
        .logo-section { display: flex; align-items: center; gap: 20px; }
        .logo { width: 60px; height: auto; filter: drop-shadow(0 0 10px rgba(255, 215, 0, 0.3)); }
        .brand-text { font-size: 1.4rem; font-weight: 700; background: linear-gradient(45deg, #ffd700, #ffed4e); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .user-section { display: flex; align-items: center; gap: 20px; }
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(45deg, #ffd700, #ffed4e); display: flex; align-items: center; justify-content: center; font-weight: bold; color: #b80000; font-size: 1.2rem; }
        .welcome-text { font-size: 1rem; font-weight: 500; }
        .logout-btn { background: linear-gradient(45deg, #d60000, #b30000); color: white; border: none; padding: 12px 24px; border-radius: 25px; cursor: pointer; font-size: 0.9rem; font-weight: 600; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(214, 0, 0, 0.3); }
        .logout-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(214, 0, 0, 0.5); }
        .container { max-width: 800px; margin: 0 auto; padding: 30px 20px; position: relative; z-index: 5; }

        .success-card {
            background: hsla(0, 0%, 100%, 0.96);
            border-radius: 16px;
            overflow: hidden;
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            text-align: center;
        }

        .success-header {
            background: linear-gradient(135deg, rgba(76, 175, 79, 1), rgba(76, 175, 79, 1));
            padding: 40px 30px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .success-icon {
            font-size: 4rem;
            color: #4CAF50;
            margin-bottom: 20px;
            animation: checkmark 0.6s ease-in-out;
        }

        @keyframes checkmark {
            0% { transform: scale(0); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }

        .success-title {
            font-size: 2.5rem;
            font-weight: 800;
            color: #4CAF50;
            margin-bottom: 10px;
        }

        .success-subtitle {
            color: rgba(255, 255, 255, 0.8);
            font-size: 1.1rem;
        }

        .quote-details {
            padding: 30px;
        }

        .quote-number {
            background: #E60012;
            color: #FFFFFF;
            padding: 15px 25px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 1.2rem;
            margin-bottom: 30px;
            display: inline-block;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-item {
            background: rgba(156, 156, 156, 0.36);
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid #818181ff;
        }

        .info-label {
            color: #ffd700;
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .info-value {
            color: white;
            font-size: 1.1rem;
            font-weight: 500;
        }

        .payment-summary {
            background: rgba(76, 175, 80, 0.1);
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            border-left: 4px solid #4CAF50;
        }

        .payment-summary h3 {
            color: #4CAF50;
            margin-bottom: 20px;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .payment-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .payment-item {
            background: rgba(255, 255, 255, 0.05);
            padding: 15px;
            border-radius: 8px;
            border-left: 3px solid #4CAF50;
        }

        .payment-item.highlight {
            background: rgba(76, 175, 80, 0.15);
            border-left: 3px solid #66BB6A;
        }

        .payment-label {
            color: #4CAF50;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .payment-value {
            color: #000000d3;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .payment-item.highlight .payment-value {
            font-size: 1.2rem;
            font-weight: 700;
        }

        .next-steps {
            background: rgba(123, 255, 0, 0.1);
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            border-left: 4px solid #31850bff;
        }

        .next-steps h3 {
            color: #E60012;
            margin-bottom: 15px;
            font-size: 1.3rem;
        }

        .next-steps ul {
            list-style: none;
            padding: 0;
        }

        .next-steps li {
            padding: 8px 0;
            color: #000000;
            position: relative;
            padding-left: 25px;
        }

        .next-steps li::before {
            content: '✓';
            position: absolute;
            left: 0;
            color: #4CAF50;
            font-weight: bold;
        }

        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .action-btn {
            background: #E60012;
            color: #FFFFFF;
            border: none;
            padding: 15px 25px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(255, 215, 0, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 215, 0, 0.4);
        }

        .action-btn.secondary {
            background: transparent;
            color: #000000;
            border: 2px solid #808080;
        }

        .action-btn.secondary:hover {
            background: #E60012;
            color: #FFFFFF;
        }

        @media (max-width: 575px) {
            .success-title {
                font-size: 1.6rem;
            }
            
            .container {
                padding: 15px 10px;
            }
            
            .success-header,
            .quote-details {
                padding: 20px 15px;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .action-buttons {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            
            .quote-number {
                font-size: 1rem;
                padding: 12px 20px;
            }
            
            .success-icon {
                font-size: 3rem;
            }
        }

        @media (min-width: 576px) and (max-width: 767px) {
            .success-title {
                font-size: 1.8rem;
            }
            
            .container {
                padding: 20px 15px;
            }
            
            .success-header,
            .quote-details {
                padding: 25px 20px;
            }
            
            .info-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .action-buttons {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (min-width: 768px) and (max-width: 991px) {
            .success-title {
                font-size: 2rem;
            }
            
            .container {
                padding: 25px 20px;
            }
            
            .info-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (min-width: 992px) {
            .success-title {
                font-size: 2.2rem;
            }
            
            .container {
                padding: 30px 20px;
            }
        }

        @media (max-width: 768px) {
            .success-title {
                font-size: 2rem;
            }
            
            .container {
                padding: 20px 15px;
            }
            
            .success-header,
            .quote-details {
                padding: 25px 20px;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                grid-template-columns: 1fr;
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
        <div class="success-card">
            <div class="success-header">
                <div class="success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h1 class="success-title">Quote Submitted Successfully!</h1>
                <p class="success-subtitle">Your quote request has been received and is being processed</p>
            </div>

            <div class="quote-details">
                <div class="quote-number">
                    Quote Number: <?php echo htmlspecialchars($quote_number); ?>
                </div>

                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Issue Date</div>
                        <div class="info-value"><?php echo date('d/m/Y', strtotime($quote['RequestedAt'])); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Status</div>
                        <div class="info-value"><?php echo htmlspecialchars($quote['QuoteStatus']); ?></div>
                    </div>
                    <?php if ($quote['model_name']): ?>
                    <div class="info-item">
                        <div class="info-label">Vehicle</div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($quote['model_name']); ?>
                            <?php if ($quote['variant']): ?>
                                <br><small style="opacity: 0.8;"><?php echo htmlspecialchars($quote['variant']); ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="info-item">
                        <div class="info-label">Purchase Timeframe</div>
                        <div class="info-value"><?php echo htmlspecialchars($quote['PurchaseTimeframe']); ?></div>
                    </div>
                </div>

                <?php if ($quote['payment_plan_calculated'] == 1 && $quote['monthly_payment'] > 0): ?>
                <div class="payment-summary">
                    <h3><i class="fas fa-calculator"></i> Payment Plan Summary</h3>
                    <div class="payment-grid">
                        <div class="payment-item">
                            <div class="payment-label">Down Payment</div>
                            <div class="payment-value">₱<?php echo number_format($quote['down_payment'], 2); ?></div>
                        </div>
                        <div class="payment-item">
                            <div class="payment-label">Loan Amount</div>
                            <div class="payment-value">₱<?php echo number_format($quote['loan_amount'], 2); ?></div>
                        </div>
                        <div class="payment-item">
                            <div class="payment-label">Financing Term</div>
                            <div class="payment-value"><?php echo $quote['financing_term']; ?> months</div>
                        </div>
                        <div class="payment-item highlight">
                            <div class="payment-label">Monthly Payment</div>
                            <div class="payment-value">₱<?php echo number_format($quote['monthly_payment'], 2); ?></div>
                        </div>
                        <div class="payment-item">
                            <div class="payment-label">Total Amount</div>
                            <div class="payment-value">₱<?php echo number_format($quote['total_amount'], 2); ?></div>
                        </div>
                        <div class="payment-item">
                            <div class="payment-label">Total Interest</div>
                            <div class="payment-value">₱<?php echo number_format($quote['total_interest'], 2); ?></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="next-steps">
                    <h3><i class="fas fa-list-check"></i> What happens next?</h3>
                    <ul>
                        <li>Our sales team will review your quote request within 24 hours</li>
                        <li>A dedicated sales agent will be assigned to your case</li>
                        <li>You'll receive a detailed quote via email and phone call</li>
                        <li>Schedule a test drive or visit our showroom for more details</li>
                    </ul>
                </div>

                <div class="action-buttons">
                    <a href="quote_display.php?quote_id=<?php echo $quote['Id']; ?>" class="action-btn">
                        <i class="fas fa-file-invoice"></i>
                        View Quote Document
                    </a>
                    <a href="customer.php" class="action-btn secondary">
                        <i class="fas fa-home"></i>
                        Back to Dashboard
                    </a>
                    <a href="car_menu.php" class="action-btn secondary">
                        <i class="fas fa-car"></i>
                        Browse More Cars
                    </a>
                    <a href="notifications.php" class="action-btn secondary">
                        <i class="fas fa-bell"></i>
                        Check Notifications
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
