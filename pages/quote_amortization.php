<?php
session_start();
include_once(dirname(__DIR__) . '/includes/database/db_conn.php');

// Require logged-in customer
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Customer') {
    header("Location: login.php");
    exit;
}

// Get quote_id
$quote_id = isset($_GET['quote_id']) ? (int)$_GET['quote_id'] : 0;
if ($quote_id <= 0) {
    header("Location: customer.php");
    exit;
}

// Fetch quote joined with vehicle and account to validate ownership and get prices
try {
    $stmt = $connect->prepare("
        SELECT 
            q.*,
            v.model_name, v.variant, v.year_model, v.base_price, v.promotional_price, v.min_downpayment_percentage,
            a.FirstName AS acct_first, a.LastName AS acct_last, a.Email AS acct_email
        FROM quotes q
        LEFT JOIN vehicles v ON v.id = q.VehicleId
        LEFT JOIN accounts a ON a.Id = q.AccountId
        WHERE q.Id = ? AND q.AccountId = ?
        LIMIT 1
    ");
    $stmt->execute([$quote_id, $_SESSION['user_id']]);
    $quote = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$quote) {
        header("Location: customer.php");
        exit;
    }
} catch (PDOException $e) {
    error_log("Quote fetch error: " . $e->getMessage());
    header("Location: customer.php");
    exit;
}

// Compute or re-compute amortization details using centralized calculator
$calc_result = null;
$calc_error = null;

$final_price = 0.0;
if (!empty($quote['base_price'])) {
    $base = (float)$quote['base_price'];
    $promo = isset($quote['promotional_price']) ? (float)$quote['promotional_price'] : 0.0;
    $final_price = ($promo > 0 && $promo < $base) ? $promo : $base;
}

$dp = isset($quote['down_payment']) && $quote['down_payment'] !== null ? (float)$quote['down_payment'] : 0.0;
$term = isset($quote['financing_term']) && (int)$quote['financing_term'] > 0 ? (int)$quote['financing_term'] : 36;

try {
    // Try centralized payment calculator
    $calc_path = dirname(__DIR__) . '/includes/payment_calculator.php';
    if (file_exists($calc_path)) {
        require_once($calc_path);
        if (class_exists('PaymentCalculator')) {
            $calculator = new PaymentCalculator($connect);

            // If down payment is zero (e.g., user didn't provide), apply min DP (vehicle min or config)
            if ($dp <= 0) {
                $minPercent = $calculator->getMinDownPaymentPercent(); // decimal form, e.g., 0.20
                $vehicleMinPercent = isset($quote['min_downpayment_percentage']) && (float)$quote['min_downpayment_percentage'] > 0
                    ? ((float)$quote['min_downpayment_percentage'] / 100.0)
                    : null;
                $usePercent = $vehicleMinPercent !== null ? $vehicleMinPercent : $minPercent;
                $dp = round($final_price * $usePercent, 2);
            }

            $calc_result = $calculator->calculatePlan($final_price, $dp, $term);
        } else {
            $calc_error = "Calculator class not found.";
        }
    } else {
        $calc_error = "Calculator file not found.";
    }
} catch (Exception $e) {
    $calc_error = $e->getMessage();
}

// Fallback calculation if calculator failed
if (!$calc_result) {
    $loan_amount = max($final_price - $dp, 0.0);
    $monthly_payment = $term > 0 ? $loan_amount / $term : $loan_amount;
    $schedule = [];
    $remaining = $loan_amount;
    for ($i = 1; $i <= $term; $i++) {
        $principal_payment = $term > 0 ? ($loan_amount / $term) : $loan_amount;
        $interest_payment = 0.0;
        $remaining = max(0.0, $remaining - $principal_payment);
        $schedule[] = [
            'payment_number' => $i,
            'monthly_payment' => round($monthly_payment, 2),
            'principal_payment' => round($principal_payment, 2),
            'interest_payment' => round($interest_payment, 2),
            'remaining_balance' => round($remaining, 2)
        ];
    }
    $calc_result = [
        'vehicle_price' => round($final_price, 2),
        'down_payment' => round($dp, 2),
        'loan_amount' => round($loan_amount, 2),
        'financing_term' => $term,
        'monthly_payment' => round($monthly_payment, 2),
        'total_amount' => round($dp + ($monthly_payment * $term), 2),
        'total_interest' => 0.0,
        'interest_rate_percent' => 0.0,
        'amortization_schedule' => $schedule
    ];
}

// Build header info
$displayName = !empty($quote['FirstName']) ? $quote['FirstName'] : (!empty($quote['acct_first']) ? $quote['acct_first'] : 'Customer');
$quote_number = 'MQAV-' . date('Ymd', strtotime($quote['RequestedAt'])) . '-' . str_pad($quote['Id'], 4, '0', STR_PAD_LEFT);
$due_date = date('d/m/Y', strtotime($quote['RequestedAt'] . ' + 30 days'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Amortization - Quote <?php echo htmlspecialchars($quote_number); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', 'Segoe UI', sans-serif; }
        body { background: #ffffff; min-height: 100vh; color: white; }
        .header { background: #000000; padding: 20px 30px; display: flex; justify-content: space-between; align-items: center; backdrop-filter: blur(20px); border-bottom: 1px solid rgba(255, 215, 0, 0.2); }
        .logo { width: 60px; height: auto; filter: drop-shadow(0 0 10px rgba(255, 215, 0, 0.3)); }
        .brand-text { font-size: 1.4rem; font-weight: 700; background: linear-gradient(45deg, #ffd700, #ffed4e); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .container { max-width: 1000px; margin: 0 auto; padding: 30px 20px; }
        .back-btn { display: inline-block; margin-bottom: 20px; background: #E60012; color: #ffffff; padding: 8px 16px; border-radius: 8px; text-decoration: none; font-weight: 500; transition: all 0.3s ease; font-size: 0.9rem; }
        .back-btn:hover { background: #ffd700; color: #1a1a1a; }

        .doc-card { background: white; color: #222; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .doc-header { background: linear-gradient(135deg, #d32f2f, #b71c1c); color: white; padding: 20px; text-align: center; position: relative; }
        .doc-title { font-size: 1.8rem; font-weight: 800; }
        .doc-body { padding: 20px; background: #f5f5f5; }

        .info-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 15px; margin-bottom: 20px; }
        .info-item { background: white; padding: 12px; border-radius: 8px; border-left: 4px solid #d32f2f; }
        .info-label { font-size: 0.85rem; font-weight: 600; color: #666; margin-bottom: 4px; }
        .info-value { font-weight: 700; color: #222; }

        .summary { background: #fff; padding: 16px; border-radius: 8px; display: grid; grid-template-columns: repeat(3,1fr); gap: 12px; margin-bottom: 20px; }
        .summary .box { background: #fafafa; border: 1px solid #eee; border-radius: 8px; padding: 12px; }
        .summary .label { font-size: 0.85rem; color: #666; }
        .summary .value { font-weight: 800; color: #b71c1c; font-size: 1.05rem; }

        .table-wrap { background: white; border-radius: 8px; overflow: hidden; border: 1px solid #eee; }
        table { width: 100%; border-collapse: collapse; }
        thead { background: #b71c1c; color: white; }
        th, td { padding: 10px; font-size: 0.92rem; }
        tbody tr:nth-child(even) { background: #fafafa; }
        tbody tr:hover { background: #f0f0f0; }

        .actions { margin-top: 16px; display: grid; grid-template-columns: repeat(2, minmax(180px, 240px)); gap: 10px; }
        .action-btn { background: linear-gradient(45deg, #ffd700, #ffed4e); color: #1a1a1a; border: none; padding: 12px 16px; border-radius: 8px; cursor: pointer; font-weight: 700; text-decoration: none; text-align: center; }
        .action-btn.secondary { background: transparent; color: #ffd700; border: 2px solid #ffd700; }
        .action-btn.secondary:hover { background: #ffd700; color: #1a1a1a; }

        @media (max-width: 767px) {
            .info-grid { grid-template-columns: 1fr; }
            .summary { grid-template-columns: 1fr; }
            .actions { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <header class="header">
        <div style="display:flex; align-items:center; gap: 12px;">
            <img src="../includes/images/mitsubishi_logo.png" alt="Mitsubishi Logo" class="logo">
            <div class="brand-text">MITSUBISHI MOTORS</div>
        </div>
        <div></div>
    </header>

    <div class="container">
        <a href="customer.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>

        <div class="doc-card">
            <div class="doc-header">
                <div class="doc-title"><i class="fas fa-file-invoice-dollar"></i> Full Amortization Breakdown</div>
                <div style="opacity:0.9; margin-top:6px;">Quote <?php echo htmlspecialchars($quote_number); ?></div>
            </div>

            <div class="doc-body">
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Customer</div>
                        <div class="info-value"><?php echo htmlspecialchars($quote['FirstName'] . ' ' . $quote['LastName']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Issue Date</div>
                        <div class="info-value"><?php echo date('d/m/Y', strtotime($quote['RequestedAt'])); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Due Date</div>
                        <div class="info-value"><?php echo htmlspecialchars($due_date); ?></div>
                    </div>
                </div>

                <?php if (!empty($quote['model_name'])): ?>
                <div class="info-grid" style="margin-top: 0;">
                    <div class="info-item">
                        <div class="info-label">Vehicle</div>
                        <div class="info-value"><?php echo htmlspecialchars($quote['model_name']); ?><?php echo $quote['variant'] ? ' - ' . htmlspecialchars($quote['variant']) : ''; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Year</div>
                        <div class="info-value"><?php echo htmlspecialchars($quote['year_model'] ?: date('Y')); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Preferred Dealership</div>
                        <div class="info-value">San Pablo</div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($calc_error): ?>
                    <div style="background: #fff3cd; color: #856404; padding: 12px 16px; border: 1px solid #ffeeba; border-radius: 8px; margin: 12px 0;">
                        <i class="fas fa-exclamation-triangle"></i> Calculation notice: <?php echo htmlspecialchars($calc_error); ?>. A fallback calculation without interest was used.
                    </div>
                <?php endif; ?>

                <div class="summary">
                    <div class="box">
                        <div class="label">Vehicle Price</div>
                        <div class="value">₱<?php echo number_format((float)$calc_result['vehicle_price'], 2); ?></div>
                    </div>
                    <div class="box">
                        <div class="label">Down Payment</div>
                        <div class="value">₱<?php echo number_format((float)$calc_result['down_payment'], 2); ?></div>
                    </div>
                    <div class="box">
                        <div class="label">Loan Amount</div>
                        <div class="value">₱<?php echo number_format((float)$calc_result['loan_amount'], 2); ?></div>
                    </div>
                    <div class="box">
                        <div class="label">Term</div>
                        <div class="value"><?php echo (int)$calc_result['financing_term']; ?> months</div>
                    </div>
                    <div class="box">
                        <div class="label">Monthly Payment</div>
                        <div class="value">₱<?php echo number_format((float)$calc_result['monthly_payment'], 2); ?></div>
                    </div>
                    <div class="box">
                        <div class="label">Total Interest</div>
                        <div class="value">₱<?php echo number_format((float)$calc_result['total_interest'], 2); ?></div>
                    </div>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Monthly Payment</th>
                                <th>Principal</th>
                                <th>Interest</th>
                                <th>Remaining Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($calc_result['amortization_schedule'] as $row): ?>
                                <tr>
                                    <td><?php echo (int)$row['payment_number']; ?></td>
                                    <td>₱<?php echo number_format((float)$row['monthly_payment'], 2); ?></td>
                                    <td>₱<?php echo number_format((float)$row['principal_payment'], 2); ?></td>
                                    <td>₱<?php echo number_format((float)$row['interest_payment'], 2); ?></td>
                                    <td>₱<?php echo number_format((float)$row['remaining_balance'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="actions">
                    <button class="action-btn" onclick="window.print()"><i class="fas fa-print"></i>&nbsp;Print</button>
                    <a class="action-btn secondary" href="quote_display.php?quote_id=<?php echo (int)$quote['Id']; ?>"><i class="fas fa-file-invoice"></i>&nbsp;View Quote</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>