<?php
session_start();
include_once(dirname(__DIR__) . '/includes/database/db_conn.php');
include_once(dirname(__DIR__) . '/pages/header_ex.php');

// Check if user is logged in and is a customer
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Customer') {
    header("Location: login.php");
    exit;
}

// Get vehicle ID from URL or POST (fallback on submit)
$vehicle_id = isset($_GET['vehicle_id']) ? (int)$_GET['vehicle_id'] : null;
if (!$vehicle_id && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vehicle_id'])) {
    $vehicle_id = (int)$_POST['vehicle_id'];
}
$vehicle = null;

if ($vehicle_id) {
    try {
        $stmt_vehicle = $connect->prepare("SELECT * FROM vehicles WHERE id = ? AND availability_status = 'available'");
        $stmt_vehicle->execute([$vehicle_id]);
        $vehicle = $stmt_vehicle->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
    }
}

// Fetch user and customer details for pre-filling
$stmt = $connect->prepare("SELECT a.*, ci.firstname, ci.lastname, ci.mobile_number
                          FROM accounts a
                          LEFT JOIN customer_information ci ON a.Id = ci.account_id
                          WHERE a.Id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$displayName = !empty($user['FirstName']) ? $user['FirstName'] : $user['Username'];

// Prepare profile image HTML
$profile_image_html = '';
if (!empty($user['ProfileImage'])) {
    $imageData = base64_encode($user['ProfileImage']);
    $imageMimeType = 'image/jpeg';
    $profile_image_html = '<img src="data:' . $imageMimeType . ';base64,' . $imageData . '" alt="User Avatar" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">';
} else {
    // Show initial if no profile image
    $profile_image_html = strtoupper(substr($displayName, 0, 1));
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("QUOTE DEBUG: Form submission detected - POST method");
    error_log("QUOTE DEBUG: POST data: " . print_r($_POST, true));
    error_log("QUOTE DEBUG: Session user_id: " . $_SESSION['user_id']);
    error_log("QUOTE DEBUG: Vehicle ID: " . $vehicle_id);
    
    // We'll auto-generate a payment plan even if inputs are not provided
    $loan_amount = null;
    $down_payment = null;
    $financing_term = null;
    $monthly_payment = null;
    $total_amount = null;
    $total_interest = null;
    $payment_plan_calculated = false;
    $quote_created = false;
    $new_quote_id = null;

    try {
        error_log("QUOTE DEBUG: Starting form processing...");
        
        // Determine pricing and defaults
        if ($vehicle) {
            error_log("QUOTE DEBUG: Vehicle found, processing payment calculation...");
            // Use calculator if available; fallback to simple computation if not
            $calculator = null;
            $calculator_available = false;
            $calc_path = dirname(__DIR__) . '/includes/payment_calculator.php';
            if (file_exists($calc_path)) {
                require_once($calc_path);
                if (class_exists('PaymentCalculator')) {
                    $calculator = new PaymentCalculator($connect);
                    $calculator_available = true;
                }
            }

            $vehicle_price = ($vehicle['promotional_price'] > 0 && $vehicle['promotional_price'] < $vehicle['base_price'])
                ? (float)$vehicle['promotional_price']
                : (float)$vehicle['base_price'];

            // Defaults: min downpayment percentage from vehicle or 20%, and 36 months term
            $default_dp_percent = isset($vehicle['min_downpayment_percentage']) && $vehicle['min_downpayment_percentage'] > 0
                ? (float)$vehicle['min_downpayment_percentage']
                : 20.0;

            $down_payment = (isset($_POST['down_payment']) && $_POST['down_payment'] !== '')
                ? (float)$_POST['down_payment']
                : round($vehicle_price * ($default_dp_percent / 100), 2);

            $financing_term = (isset($_POST['financing_term']) && $_POST['financing_term'] !== '')
                ? (int)$_POST['financing_term']
                : 36;

            // Only compute if down payment is less than vehicle price and term is valid
            if ($down_payment >= 0 && $down_payment < $vehicle_price && $financing_term > 0) {
                try {
                    if ($calculator_available && $calculator) {
                        $payment_plan = $calculator->calculatePlan($vehicle_price, $down_payment, $financing_term);
                        $loan_amount = $payment_plan['loan_amount'];
                        $monthly_payment = $payment_plan['monthly_payment'];
                        $total_amount = $payment_plan['total_amount'];
                        $total_interest = $payment_plan['total_interest'];
                        $payment_plan_calculated = true;
                    } else {
                        // Fallback computation if calculator is not available
                        $loan_amount = max($vehicle_price - $down_payment, 0);
                        $monthly_payment = $financing_term > 0 ? ($loan_amount / $financing_term) : $loan_amount;
                        $total_amount = $loan_amount + $down_payment;
                        $total_interest = 0;
                        $payment_plan_calculated = false;
                    }
                } catch (Exception $e) {
                    error_log("Payment calculation error: " . $e->getMessage());
                    // Fall back to a simple linear estimate without interest if calculator fails
                    $loan_amount = max($vehicle_price - $down_payment, 0);
                    $monthly_payment = $financing_term > 0 ? ($loan_amount / $financing_term) : $loan_amount;
                    $total_amount = $loan_amount + $down_payment;
                    $total_interest = 0;
                    $payment_plan_calculated = false;
                }
            }
        }

        error_log("QUOTE DEBUG: Preparing database insert...");
        error_log("QUOTE DEBUG: Final values - Loan: $loan_amount, DP: $down_payment, Term: $financing_term, Monthly: $monthly_payment");
        
        $stmt_insert = $connect->prepare("INSERT INTO quotes
            (AccountId, VehicleId, FirstName, LastName, Email, PhoneNumber, PreferredLocation, PurchaseTimeframe, Message,
             loan_amount, down_payment, financing_term, monthly_payment, total_amount, total_interest, payment_plan_calculated)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        error_log("QUOTE DEBUG: Executing database insert...");
        $result = $stmt_insert->execute([
            $_SESSION['user_id'],
            $vehicle_id,
            $_POST['first_name'],
            $_POST['last_name'],
            $_POST['email'],
            $_POST['phone'],
            $_POST['location'],
            $_POST['timeframe'] ?? 'Within 30 Days', // Default timeframe if not provided
            $_POST['message'],
            $loan_amount,
            $down_payment,
            $financing_term,
            $monthly_payment,
            $total_amount,
            $total_interest,
            $payment_plan_calculated
        ]);
        
        error_log("QUOTE DEBUG: Database insert result: " . ($result ? 'SUCCESS' : 'FAILED'));

        // Only redirect if the database insert was successful
        if ($result) {
            $newId = $connect->lastInsertId();
            error_log("QUOTE DEBUG: New quote ID: " . $newId);
            
            if ($newId) {
                error_log("QUOTE DEBUG: Checking headers before redirect...");
                // Ensure no output has been sent before redirect
                if (!headers_sent()) {
                    error_log("QUOTE DEBUG: Headers not sent, using PHP redirect to quote_amortization.php?quote_id=" . $newId);
                    header("Location: quote_amortization.php?quote_id=" . $newId);
                    exit;
                } else {
                    error_log("QUOTE DEBUG: Headers already sent, using JavaScript redirect");
                    // Fallback to JavaScript redirect if headers already sent
                    echo "<script type='text/javascript'>";
                    echo "console.log('QUOTE DEBUG: JavaScript redirect triggered');";
                    echo "window.location.href = 'quote_amortization.php?quote_id=" . $newId . "';";
                    echo "</script>";
                    exit;
                }
            } else {
                error_log("QUOTE DEBUG: Failed to get new quote ID from database");
            }
        } else {
            error_log("QUOTE DEBUG: Database insert failed");
        }
    } catch (PDOException $e) {
        error_log("QUOTE DEBUG: Database exception: " . $e->getMessage());
        error_log("QUOTE DEBUG: SQL State: " . $e->getCode());
        // Intentionally do not show user-facing error per requirement (remove failed to submit message)
    } catch (Exception $e) {
        error_log("QUOTE DEBUG: General exception: " . $e->getMessage());
    }
    
    error_log("QUOTE DEBUG: Form processing completed - no redirect occurred");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Quote - Mitsubishi Motors</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', 'Segoe UI', sans-serif; }
        
        body { background: #ffffff; min-height: 100vh; color: white; }
       
        
        .container { max-width: 800px;
            background: #ffffff; 
            margin: 0 auto; 
            padding: 30px 20px; 
            position: relative; 
            z-index: 5; }


        .back-btn { display: inline-block; margin-bottom: 20px; background: #E60012; color: #ffffff; padding: 8px 16px; border-radius: 8px; text-decoration: none; font-weight: 500; transition: all 0.3s ease; font-size: 0.9rem; }
        .back-btn:hover { background: #ffd700; color: #1a1a1a; }

        .quote-card {
            background: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            backdrop-filter: blur(20px);
            border: 2px solid rgba(255, 255, 255, 0.32);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }

        .card-header {
            background:rgba(99, 99, 99, 0.87);
            padding: 30px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 800;
            color: #FFFFFF;
            margin-bottom: 10px;
        }

        .page-subtitle {
            color: rgba(255, 255, 255, 0.7);
            font-size: 1.1rem;
        }

        .vehicle-info {
            background: rgba(255, 215, 0, 0.1);
            padding: 15px 20px;
            margin: 20px 0;
            border-radius: 10px;
            border-left: 4px solid #ffd700;
        }

        .vehicle-name {
            color: #ffd700;
            font-weight: 700;
            font-size: 1.3rem;
        }

        .form-container {
            padding: 30px;
        }

        .form-section {
            margin-bottom: 30px;
        }

        .section-title {
            color: #E60012;
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        label {
            color: #000000;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        input, select, textarea {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(0, 0, 0, 0.67);
            color: #000000;
            padding: 12px 15px;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #ffd700;
            box-shadow: 0 0 0 2px rgba(255, 215, 0, 0.2);
        }

        input::placeholder, textarea::placeholder {
            color: rgba(0, 0, 0, 0.5);
        }

        select option {
            background: #1a1a1a;
            color: white;
        }

        textarea {
            resize: vertical;
            min-height: 100px;
        }

        .submit-btn {
            background: #E60012;
            color: #FFFFFF;
            border: none;
            padding: 15px 40px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 700;
            font-size: 1.1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(255, 215, 0, 0.3);
            width: 100%;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 215, 0, 0.4);
        }

        .error-message {
            background: rgba(244, 67, 54, 0.2);
            color: #f44336;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #f44336;
        }

        @media (max-width: 575px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .page-title {
                font-size: 1.8rem;
            }
            
            .container {
                padding: 15px 10px;
            }
            
            .card-header,
            .form-container {
                padding: 15px;
            }
            
            .vehicle-info {
                margin: 15px 0;
                padding: 12px 15px;
            }
            
            .section-title {
                font-size: 1rem;
            }
            
            input, select, textarea {
                padding: 10px 12px;
                font-size: 0.9rem;
            }
            
            .submit-btn {
                padding: 12px 30px;
                font-size: 1rem;
            }
        }

        @media (min-width: 576px) and (max-width: 767px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .page-title {
                font-size: 2rem;
            }
            
            .container {
                padding: 20px 15px;
            }
            
            .card-header,
            .form-container {
                padding: 20px;
            }
        }

        @media (min-width: 768px) and (max-width: 991px) {
            .page-title {
                font-size: 2.2rem;
            }
            
            .container {
                padding: 25px 20px;
            }
            
            .card-header,
            .form-container {
                padding: 25px;
            }
        }

        @media (min-width: 992px) {
            .page-title {
                font-size: 2.5rem;
            }
            
            .container {
                padding: 30px 20px;
            }
            
            .card-header,
            .form-container {
                padding: 30px;
            }
        }

        .payment-summary {
            background: rgba(255, 215, 0, 0.1);
            border: 1px solid rgba(255, 215, 0, 0.3);
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .summary-item label {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
            margin: 0;
            text-transform: none;
            letter-spacing: normal;
        }

        .summary-item span {
            color: #fff;
            font-weight: 600;
        }

        @media (max-width: 767px) {
            .summary-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>


    <div class="container">
        <a href="<?php echo $vehicle ? 'car_details.php?id=' . $vehicle['id'] : 'car_menu.php'; ?>" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back
        </a>
        
        <div class="quote-card">
            <div class="card-header">
                <h1 class="page-title">Quote</h1>
                <p class="page-subtitle">Get a personalized quote for your dream vehicle</p>
                
                <?php if ($vehicle): ?>
                    <div class="vehicle-info">
                        <div class="vehicle-name"><?php echo htmlspecialchars($vehicle['model_name']); ?></div>
                        <?php if ($vehicle['variant']): ?>
                            <div style="color: rgba(255,255,255,0.8);"><?php echo htmlspecialchars($vehicle['variant']); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="form-container">
                <!-- Error banner intentionally removed per requirement: no auto-generated 'failed to submit' -->
                
                <?php if (isset($quote_created) && $quote_created): ?>
                    <div class="payment-summary" style="display:block; border-left:4px solid #4CAF50; background: rgba(76,175,80,0.1);">
                        <strong style="color:#4CAF50;"><i class="fas fa-check-circle"></i> Quote submitted.</strong>
                        Your calculated price breakdown is shown below.
                    </div>
                <?php endif; ?>
 
                <form method="POST" action="quote_request.php?vehicle_id=<?php echo (int)$vehicle_id; ?>">
                    <input type="hidden" name="vehicle_id" value="<?php echo (int)$vehicle_id; ?>">
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-user"></i>
                            About You
                        </h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="first_name">First Name*</label>
                                <input type="text" id="first_name" name="first_name" 
                                       value="<?php echo htmlspecialchars($user['firstname'] ?? $user['FirstName'] ?? ''); ?>" 
                                       required>
                            </div>
                            <div class="form-group">
                                <label for="last_name">Last Name*</label>
                                <input type="text" id="last_name" name="last_name" 
                                       value="<?php echo htmlspecialchars($user['lastname'] ?? $user['LastName'] ?? ''); ?>" 
                                       required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="email">Email Address*</label>
                                <input type="email" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($user['Email']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone Number*</label>
                                <input type="tel" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($user['mobile_number'] ?? ''); ?>" 
                                       required
                                       oninput="this.value = this.value.replace(/[^0-9+]/g, '')"
                                       onkeydown="if(event.key === 'e' || event.key === 'E') event.preventDefault();" />
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-map-marker-alt"></i>
                            Dealership Preferences
                        </h3>
                        
                        <div class="form-group full-width">
                            <label for="location">Preferred Dealership Location</label>
                            <input type="text" id="location" name="location" value="San Pablo" readonly>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-calendar-alt"></i>
                            About your Quotation
                        </h3>
                        
                        <div class="form-group">
                            <label for="timeframe">Planning to purchase in</label>
                            <select id="timeframe" name="timeframe">
                                <option value="">Select timeframe</option>
                                <option value="Within 30 Days">Within 30 Days</option>
                                <option value="1 - 3 months">1 - 3 Months</option>
                                <option value="3 - 6 months">3 - 6 Months</option>
                                <option value="6 - 11 months">6 - 11 Months</option>
                                <option value="1 - 2 Years">1 - 2 Years</option>
                                <option value="Undecided">Undecided</option>
                            </select>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="message">Message (Optional)</label>
                            <textarea id="message" name="message" 
                                      placeholder="Any specific requirements or questions?"></textarea>
                        </div>
                    </div>

                    <?php if ($vehicle): ?>
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-calculator"></i>
                            Payment Calculator (Optional)
                        </h3>
                        
                        <p style="color: rgba(255,255,255,0.7); margin-bottom: 20px;">Get an instant payment estimate for your selected vehicle</p>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="down_payment">Down Payment (₱)</label>
                                <input type="number" id="down_payment" name="down_payment" min="0" step="0.01" 
                                       placeholder="Enter down payment amount"
                                       onkeydown="if(['e','E','+','-'].includes(event.key)) event.preventDefault();" />
                                <small style="color: rgba(255,255,255,0.6); font-size: 0.8rem;">Minimum recommended: ₱<?php echo number_format(($vehicle['promotional_price'] > 0 && $vehicle['promotional_price'] < $vehicle['base_price'] ? $vehicle['promotional_price'] : $vehicle['base_price']) * 0.2, 2); ?></small>
                            </div>
                            <div class="form-group">
                                <label for="financing_term">Financing Term</label>
                                <select id="financing_term" name="financing_term">
                                    <option value="">Select term</option>
                                    <option value="12">12 months</option>
                                    <option value="24">24 months</option>
                                    <option value="36">36 months</option>
                                    <option value="48">48 months</option>
                                    <option value="60">60 months</option>
                                </select>
                            </div>
                        </div>
                        
                        <div id="paymentSummary" class="payment-summary" style="display: none;">
                            <h4 style="color: #fff; margin-bottom: 15px;">Payment Summary</h4>
                            <div class="summary-grid">
                                <div class="summary-item">
                                    <label>Vehicle Price:</label>
                                    <span id="displayVehiclePrice">₱<?php echo number_format($vehicle['promotional_price'] > 0 && $vehicle['promotional_price'] < $vehicle['base_price'] ? $vehicle['promotional_price'] : $vehicle['base_price'], 2); ?></span>
                                </div>
                                <div class="summary-item">
                                    <label>Down Payment:</label>
                                    <span id="displayDownPayment">₱0.00</span>
                                </div>
                                <div class="summary-item">
                                    <label>Amount to Finance:</label>
                                    <span id="amountToFinance">₱0.00</span>
                                </div>
                                <div class="summary-item">
                                    <label>Monthly Payment:</label>
                                    <span id="monthlyPayment" style="color: #4CAF50; font-weight: bold;">₱0.00</span>
                                </div>
                                <div class="summary-item">
                                    <label>Total Amount:</label>
                                    <span id="totalPayable">₱0.00</span>
                                </div>
                                <div class="summary-item">
                                    <label>Total Interest:</label>
                                    <span id="totalInterest">₱0.00</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <button type="submit" class="submit-btn" onclick="console.log('QUOTE DEBUG: Submit button clicked');">
                        <i class="fas fa-paper-plane"></i> Submit Quote Request
                    </button>
                </form>

                <!-- Post-submit breakdown removed; now handled by quote_amortization.php after redirect -->
            </div>
        </div>
    </div>
    <script>
        console.log('QUOTE DEBUG: JavaScript loaded');
        
        document.addEventListener('DOMContentLoaded', function() {
            console.log('QUOTE DEBUG: DOM Content Loaded');
            
            const downPaymentInput = document.getElementById('down_payment');
            const financingTermSelect = document.getElementById('financing_term');
            const paymentSummary = document.getElementById('paymentSummary');
            const form = document.querySelector('form');
            
            console.log('QUOTE DEBUG: Form element found:', form);
            
            // Add form submission listener
            if (form) {
                form.addEventListener('submit', function(e) {
                    console.log('QUOTE DEBUG: Form submission intercepted');
                    console.log('QUOTE DEBUG: Form data about to be submitted');
                    
                    // Log all form values
                    const formData = new FormData(form);
                    for (let [key, value] of formData.entries()) {
                        console.log('QUOTE DEBUG: ' + key + ':', value);
                    }
                });
            }
            
            <?php if ($vehicle): ?>
            const vehiclePrice = <?php echo $vehicle['promotional_price'] > 0 && $vehicle['promotional_price'] < $vehicle['base_price'] ? $vehicle['promotional_price'] : $vehicle['base_price']; ?>;
            <?php endif; ?>
            
            async function calculatePayment() {
                 const downPayment = parseFloat(downPaymentInput.value) || 0;
                 const term = parseInt(financingTermSelect.value) || 0;
                 
                 if (downPayment > 0 && term > 0 && vehiclePrice > downPayment) {
                     try {
                         // Use centralized payment calculator API
                         const response = await fetch('../includes/payment_calculator.php', {
                             method: 'POST',
                             headers: {
                                 'Content-Type': 'application/json'
                             },
                             body: JSON.stringify({
                                 action: 'calculate',
                                 vehicle_price: vehiclePrice,
                                 down_payment: downPayment,
                                 financing_term: term
                             })
                         });

                         const result = await response.json();

                         if (result.success) {
                             const data = result.data;
                             const loanAmount = vehiclePrice - downPayment;
                             
                             // Update display
                             document.getElementById('displayDownPayment').textContent = '₱' + downPayment.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                             document.getElementById('amountToFinance').textContent = '₱' + loanAmount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                             document.getElementById('monthlyPayment').textContent = '₱' + parseFloat(data.monthly_payment).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                             document.getElementById('totalPayable').textContent = '₱' + parseFloat(data.total_amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                             document.getElementById('totalInterest').textContent = '₱' + parseFloat(data.total_interest).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                             
                             paymentSummary.style.display = 'block';
                         } else {
                             console.error('Payment calculation failed:', result.message);
                             paymentSummary.style.display = 'none';
                         }
                     } catch (error) {
                         console.error('Payment calculation error:', error);
                         paymentSummary.style.display = 'none';
                     }
                 } else {
                     paymentSummary.style.display = 'none';
                 }
             }
            
            if (downPaymentInput && financingTermSelect) {
                downPaymentInput.addEventListener('input', calculatePayment);
                financingTermSelect.addEventListener('change', calculatePayment);
            }
        });
    </script>
</body>
</html>
