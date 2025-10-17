<?php
session_start();

// Set timezone to Philippines (following project configuration)
date_default_timezone_set('Asia/Manila');

include_once(dirname(__DIR__) . '/includes/database/db_conn.php');

// Check if user is logged in and is a customer
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Customer') {
    header("Location: login.php");
    exit;
}

// Check if customer information is filled out, if not, redirect to verification
$stmt_check_info = $connect->prepare("SELECT cusID FROM customer_information WHERE account_id = ?");
$stmt_check_info->execute([$_SESSION['user_id']]);
if ($stmt_check_info->rowCount() == 0) {
    header("Location: verification.php");
    exit;
}

// Fetch user details
$stmt = $connect->prepare("SELECT * FROM accounts WHERE Id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$displayName = !empty($user['FirstName']) ? $user['FirstName'] : $user['Username'];

// Get vehicle ID if passed from car_details page
$selected_vehicle_id = isset($_GET['vehicle_id']) ? (int)$_GET['vehicle_id'] : null;
$selected_vehicle = null;

if ($selected_vehicle_id) {
    try {
        $stmt_vehicle = $connect->prepare("SELECT * FROM vehicles WHERE id = ? AND availability_status = 'available'");
        $stmt_vehicle->execute([$selected_vehicle_id]);
        $selected_vehicle = $stmt_vehicle->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
    }
}

// Fetch all available vehicles for dropdown
try {
    $stmt_vehicles = $connect->prepare("SELECT id, model_name, variant, year_model, color_options FROM vehicles WHERE availability_status = 'available' ORDER BY model_name, variant");
    $stmt_vehicles->execute();
    $all_vehicles = $stmt_vehicles->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $all_vehicles = [];
    error_log("Database error: " . $e->getMessage());
}

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $required_fields = ['full_name', 'email', 'phone_number', 'vehicle_model', 'vehicle_year', 'vehicle_color', 'agree_terms'];
        $missing_fields = [];
        
        foreach ($required_fields as $field) {
            if (empty($_POST[$field]) || ($_POST[$field] === 'on' && $field === 'agree_terms')) {
                if ($field === 'agree_terms' && !isset($_POST[$field])) {
                    $missing_fields[] = 'Terms Agreement';
                } else if ($field !== 'agree_terms') {
                    $missing_fields[] = ucwords(str_replace('_', ' ', $field));
                }
            }
        }
        
        if (!empty($missing_fields)) {
            throw new Exception("Please fill in all required fields: " . implode(', ', $missing_fields));
        }
        
        // Validate email
        if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Please enter a valid email address.");
        }
        
        // Insert inquiry into database
        $stmt_insert = $connect->prepare("
            INSERT INTO inquiries (
                AccountId, FullName, Email, PhoneNumber, VehicleModel, VehicleVariant, 
                VehicleYear, VehicleColor, TradeInVehicleDetails, FinancingRequired, Comments
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt_insert->execute([
            $_SESSION['user_id'],
            $_POST['full_name'],
            $_POST['email'],
            $_POST['phone_number'],
            $_POST['vehicle_model'],
            $_POST['vehicle_variant'] ?? null,
            $_POST['vehicle_year'],
            $_POST['vehicle_color'],
            !empty($_POST['trade_in_vehicle']) ? $_POST['trade_in_vehicle'] : null,
            !empty($_POST['financing_required']) ? $_POST['financing_required'] : null,
            !empty($_POST['comments']) ? $_POST['comments'] : null
        ]);
        
        $success_message = "Your inquiry has been submitted successfully! We will contact you soon.";
        
        // Clear form data after successful submission
        $_POST = [];
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Inquiry - Mitsubishi Motors</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Inter', 'Segoe UI', sans-serif;
    }

    body {
        background: #f5f5f5;
        color: #333333;
        min-height: 100vh;
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
        color: #ffffff;
        font-size: 1rem;
        font-weight: 500;
    }

    .logout-btn {
        background: #E60012;
        color: white;
        border: none;
        padding: 12px 24px;
        border-radius: 25px;
        cursor: pointer;
        font-size: 0.9rem;
        font-weight: 600;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(230, 0, 18, 0.3);
    }

    .logout-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(230, 0, 18, 0.5);
    }

    .container {
        max-width: 1000px;
        margin: 0 auto;
        padding: 30px 20px;
        position: relative;
        z-index: 5;
    }

    .back-btn {
        display: inline-block;
        margin-bottom: 20px;
        background: #E60012;
        color: #ffffff;
        padding: 8px 16px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 500;
        transition: all 0.3s ease;
        font-size: 0.9rem;
    }

    .back-btn:hover {
        background: #b80000;
        color: #ffffff;
    }

    .inquiry-card {
        background: #ffffff;
        border-radius: 16px;
        border: 1px solid #e0e0e0;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        width: 100%;
    }

    .card-header {
        background: #4A4A4A;
        padding: 25px;
        border-bottom: 1px solid #e0e0e0;
        text-align: center;
    }

    .page-title {
        font-size: 2rem;
        font-weight: 800;
        color: #ffffff;
        margin-bottom: 8px;
    }

    .page-subtitle {
        color: rgba(255, 255, 255, 0.8);
        font-size: 0.9rem;
    }

    .form-container {
        padding: 30px;
    }

    .form-section {
        margin-bottom: 30px;
    }

    .section-title {
        color: #E60012;
        font-size: 1.1rem;
        font-weight: 600;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 15px;
    }

    .form-group {
        display: flex;
        flex-direction: column;
    }

    .form-group.full-width {
        grid-column: 1 / -1;
    }

    .form-label {
        color: #333333;
        font-size: 0.9rem;
        font-weight: 500;
        margin-bottom: 5px;
    }

    .form-label.required::after {
        content: ' *';
        color: #E60012;
    }

    .form-input,
    .form-select,
    .form-textarea {
        background: #ffffff;
        border: 1px solid #cccccc;
        border-radius: 8px;
        padding: 12px;
        color: #333333;
        font-size: 0.9rem;
        transition: all 0.3s ease;
    }

    .form-input:focus,
    .form-select:focus,
    .form-textarea:focus {
        outline: none;
        border-color: #E60012;
        box-shadow: 0 0 0 2px rgba(230, 0, 18, 0.2);
    }

    .form-textarea {
        resize: vertical;
        min-height: 80px;
    }

    .form-select option {
        background: #ffffff;
        color: #333333;
    }

    .checkbox-group {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 20px 0;
    }

    .checkbox-input {
        width: 18px;
        height: 18px;
        accent-color: #E60012;
    }

    .checkbox-label {
        color: #333333;
        font-size: 0.9rem;
        cursor: pointer;
    }

    .submit-btn {
        background: #E60012;
        color: #ffffff;
        border: none;
        padding: 15px 30px;
        border-radius: 10px;
        font-size: 1rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s ease;
        width: 100%;
        text-transform: uppercase;
        letter-spacing: 1px;
        box-shadow: 0 4px 15px rgba(230, 0, 18, 0.3);
    }

    .submit-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(230, 0, 18, 0.5);
    }

    .submit-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }

    .alert {
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-weight: 500;
    }

    .alert-success {
        background: #eaf8ec;
        color: #28a745;
        border: 1px solid #c5e6cb;
    }

    .alert-error {
        background: #fdecea;
        color: #e60012;
        border: 1px solid #f5c6cb;
    }

    .current-date {
        background: #f1f1f1;
        border: 1px solid #e0e0e0;
        padding: 10px;
        border-radius: 6px;
        color: #333333;
        font-weight: 500;
        text-align: center;
    }

    /* Tablet */
    @media (max-width: 1024px) {
        .container {
            max-width: 95%;
        }
    }

    /* Phones */
    @media (max-width: 768px) {
        .header {
            flex-direction: column;
            gap: 15px;
            padding: 15px 20px;
        }

        .user-section {
            flex-direction: column;
            gap: 12px;
            text-align: center;
            width: 100%;
        }

        .container {
            padding: 20px 15px;
        }

        .form-container {
            padding: 20px;
        }

        .form-grid {
            grid-template-columns: 1fr;
        }
    }

    /* Large Desktops */
    @media (min-width: 1200px) {
        .container {
            max-width: 1100px;
        }

        .inquiry-card {
            max-width: 100%;
        }

        .form-grid {
            grid-template-columns: repeat(2, 1fr);
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
            <button class="logout-btn" onclick="window.location.href='logout.php'">
                <i class="fas fa-sign-out-alt"></i> Logout
            </button>
        </div>
    </header>

    <div class="container">
        <a href="<?php echo (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Customer') ? 'customer.php' : ($selected_vehicle_id ? 'car_details.php?id=' . $selected_vehicle_id : 'car_menu.php'); ?>" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back
        </a>

        <div class="inquiry-card">
            <div class="card-header">
                <h1 class="page-title">Vehicle Inquiry</h1>
                <p class="page-subtitle">Tell us about your interest and we'll get back to you soon</p>
            </div>

            <div class="form-container">
                <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <!-- Personal Information -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-user"></i>
                            Personal Information
                        </h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label required">Full Name</label>
                                <input type="text" name="full_name" class="form-input" 
                                       value="<?php echo htmlspecialchars($_POST['full_name'] ?? ($user['FirstName'] . ' ' . $user['LastName'])); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label required">Email</label>
                                <input type="email" name="email" class="form-input" 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? $user['Email']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label required">Phone Number</label>
                                <input type="tel" name="phone_number" class="form-input" 
                                       value="<?php echo htmlspecialchars($_POST['phone_number'] ?? ''); ?>" required>
                            </div>
                        </div>
                    </div>

                    <!-- Vehicle Information -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-car"></i>
                            Vehicle Information
                        </h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label required">Model</label>
                                <select name="vehicle_model" class="form-select" required id="vehicle-model">
                                    <option value="">Select a model</option>
                                    <?php 
                                    $unique_models = [];
                                    foreach ($all_vehicles as $vehicle) {
                                        if (!in_array($vehicle['model_name'], $unique_models)) {
                                            $unique_models[] = $vehicle['model_name'];
                                            $selected = ($selected_vehicle && $selected_vehicle['model_name'] === $vehicle['model_name']) ? 'selected' : '';
                                            echo "<option value='" . htmlspecialchars($vehicle['model_name']) . "' $selected>" . htmlspecialchars($vehicle['model_name']) . "</option>";
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Variant</label>
                                <input type="text" name="vehicle_variant" class="form-input" 
                                       value="<?php echo htmlspecialchars($_POST['vehicle_variant'] ?? ($selected_vehicle['variant'] ?? '')); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label required">Year</label>
                                <select name="vehicle_year" class="form-select" required>
                                    <option value="">Select year</option>
                                    <?php 
                                    $current_year = date('Y');
                                    for ($year = $current_year + 1; $year >= $current_year - 10; $year--) {
                                        $selected = ($selected_vehicle && $selected_vehicle['year_model'] == $year) ? 'selected' : '';
                                        echo "<option value='$year' $selected>$year</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label required">Color</label>
                                <input type="text" name="vehicle_color" class="form-input" 
                                       value="<?php echo htmlspecialchars($_POST['vehicle_color'] ?? ($selected_vehicle['popular_color'] ?? '')); ?>" 
                                       placeholder="e.g., White Pearl, Black, Red" required>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Details -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-info-circle"></i>
                            Additional Details
                        </h3>
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label class="form-label">Trade-In Vehicle (if applicable)</label>
                                <textarea name="trade_in_vehicle" class="form-textarea" 
                                          placeholder="Please describe your current vehicle (make, model, year, condition, etc.)"><?php echo htmlspecialchars($_POST['trade_in_vehicle'] ?? ''); ?></textarea>
                            </div>
                            <div class="form-group full-width">
                                <label class="form-label">Financing Required</label>
                                <textarea name="financing_required" class="form-textarea" 
                                          placeholder="Please describe your financing needs or preferences"><?php echo htmlspecialchars($_POST['financing_required'] ?? ''); ?></textarea>
                            </div>
                            <div class="form-group full-width">
                                <label class="form-label">Comments/Questions</label>
                                <textarea name="comments" class="form-textarea" 
                                          placeholder="Any additional questions or comments"><?php echo htmlspecialchars($_POST['comments'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Date -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-calendar"></i>
                            Date
                        </h3>
                        <div class="current-date" id="current-date">
                            <?php 
                            // Ensure timezone is set to Philippines and force current date
                            date_default_timezone_set('Asia/Manila');
                            echo date('F j, Y'); 
                            ?>
                        </div>
                    </div>

                    <!-- Agreement -->
                    <div class="checkbox-group">
                        <input type="checkbox" name="agree_terms" class="checkbox-input" required id="agree-terms">
                        <label for="agree-terms" class="checkbox-label">
                            I agree to be contacted by Mitsubishi Motors regarding this inquiry and understand that my information will be used in accordance with the privacy policy.
                        </label>
                    </div>

                    <button type="submit" class="submit-btn">
                        <i class="fas fa-paper-plane"></i>
                        Submit Inquiry
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Ensure date is always current - JavaScript fallback
        function updateCurrentDate() {
            // Set timezone to Philippines (UTC+8)
            const now = new Date();
            const philippinesOffset = 8 * 60; // UTC+8 in minutes
            const utc = now.getTime() + (now.getTimezoneOffset() * 60000);
            const philippinesTime = new Date(utc + (philippinesOffset * 60000));
            
            // Format date as "Month Day, Year" (e.g., "December 25, 2024")
            const options = { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                timeZone: 'Asia/Manila'
            };
            
            const formattedDate = philippinesTime.toLocaleDateString('en-US', options);
            
            // Update the date display
            const dateElement = document.getElementById('current-date');
            if (dateElement) {
                dateElement.textContent = formattedDate;
            }
        }
        
        // Update date when page loads
        document.addEventListener('DOMContentLoaded', function() {
            updateCurrentDate();
            
            // Update date every minute to ensure it's always current
            setInterval(updateCurrentDate, 60000); // Update every 60 seconds
        });
        
        // Update date when page gains focus (user returns to tab)
        window.addEventListener('focus', updateCurrentDate);
        
        // Update date when page becomes visible
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                updateCurrentDate();
            }
        });
    </script>
</body>
</html>
