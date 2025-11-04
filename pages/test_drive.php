<?php
session_start();
include_once(dirname(__DIR__) . '/includes/database/db_conn.php');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Customer') {
    header("Location: login.php");
    exit;
}

// Fetch user details for pre-filling
$stmt = $connect->prepare("SELECT a.*, ci.firstname, ci.lastname, ci.mobile_number 
                          FROM accounts a 
                          LEFT JOIN customer_information ci ON a.Id = ci.account_id 
                          WHERE a.Id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$displayName = !empty($user['FirstName']) ? $user['FirstName'] : $user['Username'];

// Fetch available vehicles from database
try {
    $vehicle_stmt = $connect->prepare("SELECT id, model_name, variant, category, seating_capacity, fuel_type, availability_status, stock_quantity
                                      FROM vehicles
                                      WHERE availability_status = 'available' AND stock_quantity > 0
                                      ORDER BY model_name ASC");
    $vehicle_stmt->execute();
    $available_vehicles = $vehicle_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Failed to fetch vehicles: " . $e->getMessage());
    $available_vehicles = [];
}

// Validate selected vehicle parameter
$selected_vehicle_id = isset($_GET['vehicle_id']) ? (int)$_GET['vehicle_id'] : null;
$selected_vehicle = null;

if ($selected_vehicle_id) {
    // First check if vehicle exists in available vehicles
    $vehicle_exists = false;
    foreach ($available_vehicles as $vehicle) {
        if ((int)$vehicle['id'] === $selected_vehicle_id) {
            $vehicle_exists = true;
            $selected_vehicle = $vehicle;
            break;
        }
    }

    // If not found in available vehicles, check if it exists but doesn't meet criteria
    if (!$vehicle_exists) {
        try {
            $check_stmt = $connect->prepare("SELECT id, model_name, variant, availability_status, stock_quantity FROM vehicles WHERE id = ?");
            $check_stmt->execute([$selected_vehicle_id]);
            $vehicle_check = $check_stmt->fetch(PDO::FETCH_ASSOC);

            if ($vehicle_check) {
                // Vehicle exists but doesn't meet availability criteria
                error_log("Vehicle ID {$selected_vehicle_id} exists but status='{$vehicle_check['availability_status']}', stock={$vehicle_check['stock_quantity']}");
            } else {
                error_log("Vehicle ID {$selected_vehicle_id} does not exist in database");
            }
        } catch (PDOException $e) {
            error_log("Error checking vehicle: " . $e->getMessage());
        }
        $selected_vehicle_id = null;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $required_fields = ['vehicle', 'preferredDate', 'preferredTime', 'licenseNumber', 'phone', 'age', 'experience'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Please fill in all required fields.");
            }
        }
        
        // Handle driver's license file upload
        $license_data = null;
        if (isset($_FILES['licenseImage']) && $_FILES['licenseImage']['error'] === UPLOAD_ERR_OK) {
            $license_data = file_get_contents($_FILES['licenseImage']['tmp_name']);
        } else {
            throw new Exception("Please upload your driver's license image.");
        }
        
        // Check for date and time conflicts
        $conflict_check = $connect->prepare("SELECT COUNT(*) FROM test_drive_requests WHERE selected_date = ? AND selected_time_slot = ? AND status IN ('Pending', 'Approved')");
        $conflict_check->execute([$_POST['preferredDate'], $_POST['preferredTime']]);
        $existing_bookings = $conflict_check->fetchColumn();
        
        if ($existing_bookings > 0) {
            throw new Exception("Sorry, the selected date and time slot is already booked. Please choose a different time slot.");
        }
        
        // Map form fields to database fields
        $customer_name = ($user['firstname'] ?? $user['FirstName'] ?? '') . ' ' . ($user['lastname'] ?? $user['LastName'] ?? '');
        $customer_name = trim($customer_name) ?: $user['Username'];
        
        // Get vehicle details for notes
        $vehicle_id = (int)$_POST['vehicle'];
        $vehicle_info = "Unknown Vehicle";
        
        if ($vehicle_id > 0) {
            try {
                $vehicle_stmt = $connect->prepare("SELECT model_name, variant FROM vehicles WHERE id = ?");
                $vehicle_stmt->execute([$vehicle_id]);
                $vehicle_data = $vehicle_stmt->fetch(PDO::FETCH_ASSOC);
                if ($vehicle_data) {
                    $vehicle_info = $vehicle_data['model_name'];
                    if (!empty($vehicle_data['variant'])) {
                        $vehicle_info .= " (" . $vehicle_data['variant'] . ")";
                    }
                }
            } catch (PDOException $e) {
                error_log("Failed to fetch vehicle details: " . $e->getMessage());
            }
        }
        
        // Build notes with additional info
        $notes = "Test Drive Request Details:\n";
        $notes .= "Vehicle: " . $vehicle_info . "\n";
        $notes .= "License Number: " . $_POST['licenseNumber'] . "\n";
        $notes .= "Age: " . $_POST['age'] . "\n";
        $notes .= "Driving Experience: " . $_POST['experience'] . "\n";
        $notes .= "Duration Requested: 30 minutes\n";
        if (!empty($_POST['specialRequests'])) {
            $notes .= "Special Requests: " . $_POST['specialRequests'] . "\n";
        }
        
        $stmt_insert = $connect->prepare("INSERT INTO test_drive_requests 
            (account_id, vehicle_id, customer_name, mobile_number, 
             selected_date, selected_time_slot, test_drive_location, 
             drivers_license, notes, terms_accepted, status, requested_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 'Pending', NOW())");
        
        $stmt_insert->execute([
            $_SESSION['user_id'],
            $vehicle_id,
            $customer_name,
            $_POST['phone'],
            $_POST['preferredDate'],
            $_POST['preferredTime'],
            "San Pablo branch",
            $license_data,
            $notes
        ]);
        
        $request_id = $connect->lastInsertId();
        
        // Set success message and redirect
        $_SESSION['success_message'] = "Test drive request submitted successfully! You will receive a notification once your request is approved.";
        header("Location: test_drive_success.php?request_id=" . $request_id);
        exit;
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
        error_log("Test drive submission error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Drive - Mitsubishi Motors</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', 'Segoe UI', sans-serif;
        }

        body {
                  /*wag po i-uncomment yung background image kasi pinapatanggal samin yun*/
            background-image: url(../includes/images/dest.png);
            background-color: #DC143C1A;
            background-size: cover; /* scales image to cover the whole area */
            background-position: center; /* centers the image */
            background-repeat: no-repeat;
            
            min-height: 100vh;
            color: white;
            overflow: visible !important;
            height: auto !important;
            scroll-behavior: smooth;
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
            width: 50px;
            height: auto;
        }

        .brand-text {
            font-size: 1.2rem;
            font-weight: 700;
            background: #ffffff;
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .nav-links {
            display: flex;
            gap: 20px;
        }

        .nav-btn {
            background: rgba(252, 39, 39, 0.1);
            color: #ffffff;
            border: 1px solid rgba(255, 215, 0, 0.3);
            padding: 10px 20px;
            border-radius: 15px;
            text-decoration: none;
            transition: all 0.3s;
        }

        .nav-btn:hover {
            background: #E60012;
            transform: translateY(-2px);
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 40px 30px 60px;
            height: auto !important;
            min-height: 100vh;
        }

        .page-header {
            text-align: center;
            margin-bottom: 50px;
        }

        .page-header h1 {
            font-size: 3rem;
            background: #E60012;
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 15px;
        }

        .test-drive-form {
            background: #464646bd;
            box-shadow: 4px 4px 10px rgba(0, 0, 0, 0.5);
            border-radius: 20px;
            padding: 40px;
            backdrop-filter: blur(20px);
            border: 2px solid rgba(116, 116, 116, 0.1);
            height: auto !important;
            max-height: none !important;
            overflow-y: visible !important;
        }

        .form-section {
            margin-bottom: 40px;
        }

        .section-title {
            color: #000000;
            font-size: 1.5rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .selected-vehicle-display {
             margin-bottom: 30px;
         }

         .vehicle-display {
             background: rgba(94, 94, 94, 0.1);
             border: 2px solid #808080;
             border-radius: 15px;
             padding: 25px;
             text-align: center;i
         }

         .vehicle-display h3 {
             color: #E50013E5;
             font-size: 1.8rem;
             margin-bottom: 10px;
             font-weight: 700;
         }

         .vehicle-variant {
             color: rgba(2, 2, 2, 1);
             font-size: 1.1rem;
             margin: 0;
         }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #E60012;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid rgba(0, 0, 0, 0.3);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.08);
            color: #000000;
            font-size: 1rem;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .select-wrapper {
            position: relative;
            display: inline-block;
            width: 100%;
        }

        .form-group select {
            cursor: pointer;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background: rgba(255, 255, 255, 0.08);
            padding-right: 45px;
            border: 2px solid rgba(0, 0, 0, 0.3);
            border-radius: 12px;
            box-sizing: border-box;
            width: 100%;
        }

        .form-group select::-ms-expand {
            display: none;
        }

        .dropdown-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #000000ff;
            pointer-events: none;
            font-size: 1rem;
            transition: transform 0.3s ease;
        }

        .select-wrapper:hover .dropdown-icon {
            transform: translateY(-50%) scale(1.1);
        }

        .form-group {
            position: relative;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #000000ff;
            box-shadow: 0 0 20px rgba(165, 16, 16, 0.6);
            background: rgba(255, 255, 255, 0.12);
            transform: translateY(-2px);
        }

        .form-group input::placeholder,
        .form-group textarea::placeholder {
            color: rgba(0, 0, 0, 1);
        }

        .form-group select option {
            background: #1a1a1a;
            color: white;
            padding: 10px;
        }

        .no-vehicles-message {
            text-align: center;
            padding: 40px 20px;
            background: rgba(255, 69, 0, 0.1);
            border: 2px dashed rgba(255, 69, 0, 0.3);
            border-radius: 15px;
            color: #ff6b35;
        }

        .no-vehicles-message i {
            font-size: 2rem;
            margin-bottom: 15px;
            display: block;
        }

        .no-vehicles-message p {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 500;
        }

        /* Enhanced dropdown animations */
        .form-group {
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #e50013e5;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .file-upload-wrapper {
            position: relative;
            display: inline-block;
            width: 100%;
        }

        .file-upload-wrapper input[type="file"] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
            z-index: 2;
        }

        .file-upload-display {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 15px 20px;
            background: rgba(255, 255, 255, 0.08);
            border: 2px solid rgba(0, 0, 0, 0.3);
            border-radius: 12px;
            color: rgba(0, 0, 0, 0.8);
            font-size: 0.95rem;
            transition: all 0.3s ease;
            cursor: pointer;
            min-height: 50px;
        }

        .file-upload-display:hover {
            background: rgba(255, 255, 255, 0.12);
            border-color: #ffd700;
            transform: translateY(-2px);
            box-shadow: 0 0 20px rgba(255, 8, 0, 0.4);
        }

        .file-upload-display i {
            color: #e60013c0;
            font-size: 1.2rem;
        }

        .file-upload-text {
            font-weight: 500;
        }

        .file-upload-wrapper input[type="file"]:focus + .file-upload-display {
            border-color: #000000ff;
            box-shadow: 0 0 20px rgba(165, 16, 16, 0.6);
            background: rgba(255, 255, 255, 0.12);
        }

        .form-group input:hover,
        .form-group select:hover,
        .form-group textarea:hover {
            border-color: rgba(165, 16, 16, 0.6);
            background: rgba(255, 255, 255, 0.1);
        }

        /* Custom select arrow animation */
        .form-group select:focus {
            background-image: none;
        }

        .btn-schedule {
            background: #E60012;
            color: #ffffff;
            border: none;
            padding: 15px 40px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            font-size: 1.1rem;
            transition: all 0.3s;
        }

        .btn-schedule:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(165, 16, 16, 0.6);
        }

        .info-box {
            background: #808080;
            border: 1px solid rgba(165, 16, 16, 0.6);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 4px 4px 10px rgba(0, 0, 0, 0.3);
        }

        .info-box h3 {
            color: #ffffff;
            margin-bottom: 10px;
        }

        .info-box ul {
            list-style: none;
            padding-left: 0;
        }

        .info-box li {
            color: #000000;
            padding: 5px 0;
            opacity: 0.9;
        }

        .info-box li:before {
            content: "✓ ";
            color: #E60012;
            font-weight: bold;
        }

        .error-message {
            background: rgba(244, 67, 54, 0.2);
            color: #f44336;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #f44336;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .success-message {
            background: rgba(76, 175, 80, 0.2);
            color: #4caf50;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #4caf50;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Custom scrollbar styling for better UX */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.1);
        }
        
        ::-webkit-scrollbar-thumb {
            background: rgba(255, 215, 0, 0.6);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 215, 0, 0.8);
        }

        @media (max-width: 575px) {
            .header {
                padding: 15px 20px;
                flex-direction: column;
                gap: 15px;
            }

            .container {
                padding: 30px 20px;
            }

            .page-header h1 {
                font-size: 2rem;
            }

            .test-drive-form {
                padding: 25px;
            }

            .vehicle-selection {
                grid-template-columns: 1fr;
            }

            .form-grid {
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
        <div class="nav-links">
            <a href="customer.php" class="nav-btn"><i class="fas fa-home"></i> Dashboard</a>
            <a href="logout.php" class="nav-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </header>

    <div class="container">
        <div class="page-header">
            <h1>Schedule a Test Drive</h1>
            <p style = "color: #000000;" >Experience the performance and comfort of Mitsubishi vehicles</p>
        </div>

        <div class="info-box">
            <h3>What to Expect</h3>
            <ul>
                <li>Professional demonstration of vehicle features</li>
                <li>Flexible test drive duration (30-60 minutes)</li>
                <li>No purchase obligation</li>
                <li>Valid driver's license required</li>
                <li>Insurance verification needed</li>
            </ul>
        </div>

        <div class="test-drive-form">
            <?php if (isset($error_message)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="testDriveForm" enctype="multipart/form-data">
                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-car"></i>
                        Selected Vehicle
                    </div>
                    <div class="selected-vehicle-display">
                        <?php
                        if ($selected_vehicle_id && $selected_vehicle) {
                            echo '<div class="vehicle-display">';
                            echo '<h3>' . htmlspecialchars($selected_vehicle['model_name']) . '</h3>';
                            if (!empty($selected_vehicle['variant'])) {
                                echo '<p class="vehicle-variant">' . htmlspecialchars($selected_vehicle['variant']) . '</p>';
                            }
                            echo '<input type="hidden" name="vehicle" value="' . $selected_vehicle_id . '">';
                            echo '</div>';
                        } else {
                            // Show more helpful error message
                            echo '<div class="error-message">';
                            echo '<i class="fas fa-exclamation-triangle"></i> ';
                            if (isset($_GET['vehicle_id'])) {
                                echo 'Sorry, the selected vehicle is currently out of stock or unavailable for test drives. ';
                                echo 'Please <a href="car_menu.php" style="color: #ffd700; text-decoration: underline;">browse our available vehicles</a> and select another one.';
                            } else {
                                echo 'No vehicle selected. Please <a href="car_menu.php" style="color: #ffd700; text-decoration: underline;">browse our vehicles</a> and select one for your test drive.';
                            }
                            echo '</div>';
                        }
                        ?>
                    </div>
                </div>

                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-calendar-alt"></i>
                        Appointment Details
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="preferredDate">Preferred Date</label>
                            <input type="date" id="preferredDate" name="preferredDate" required>
                        </div>
                        <div class="form-group">
                            <label for="preferredTime">Preferred Time</label>
                            <div class="select-wrapper">
                                <select id="preferredTime" name="preferredTime" required>
                                    <option value="">Select time</option>
                                    <option value="09:00">9:00 AM</option>
                                    <option value="10:00">10:00 AM</option>
                                    <option value="11:00">11:00 AM</option>
                                    <option value="14:00">2:00 PM</option>
                                    <option value="15:00">3:00 PM</option>
                                    <option value="16:00">4:00 PM</option>
                                </select>
                                <i class="fas fa-chevron-down dropdown-icon"></i>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="duration">Test Drive Duration</label>
                            <input type="text" id="duration" name="duration" value="30 minutes" readonly>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-user"></i>
                        Personal Information
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="licenseNumber">Driver's License Number</label>
                            <input type="text" id="licenseNumber" name="licenseNumber" required placeholder="Enter license number">
                        </div>
                        <div class="form-group">
                            <label for="licenseImage">Driver's License Image</label>
                            <div class="file-upload-wrapper">
                                <input type="file" id="licenseImage" name="licenseImage" accept="image/*" required>
                                <div class="file-upload-display">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span class="file-upload-text">Choose license image</span>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" required ="(555) 123-4567" 
                                   value="<?php echo htmlspecialchars($user['mobile_number'] ?? ''); ?>"
                                   oninput="this.value = this.value.replace(/[^0-9()+\-\s]/g, '')"
                                   onkeydown="if(event.key === 'e' || event.key === 'E') event.preventDefault();" />
                        </div>
                        <div class="form-group">
                            <label for="age">Age</label>
                            <input type="number" id="age" name="age" min="18" max="100" required placeholder="18+" onkeydown="if(['e','E','+','-','.'].includes(event.key)) event.preventDefault();"/>
                        </div>
                        <div class="form-group">
                            <label for="experience">Driving Experience</label>
                            <div class="select-wrapper">
                                <select id="experience" name="experience" required>
                                    <option value="">Select experience</option>
                                    <option value="1-3">1-3 years</option>
                                    <option value="4-10">4-10 years</option>
                                    <option value="10+">10+ years</option>
                                </select>
                                <i class="fas fa-chevron-down dropdown-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-group">
                        <label for="specialRequests">Special Requests or Questions</label>
                        <textarea id="specialRequests" name="specialRequests" rows="4" placeholder="Any specific features you'd like to test or questions about the vehicle..."></textarea>
                    </div>
                </div>

                <button type="submit" class="btn-schedule">
                    <i class="fas fa-calendar-check"></i> Schedule Test Drive
                </button>
            </form>
        </div>
    </div>

    <script>
        // Set minimum date to tomorrow
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        document.getElementById('preferredDate').min = tomorrow.toISOString().split('T')[0];

        // Vehicle is pre-selected from URL parameter, no selection needed

        // Enhanced form validation with better UX
        document.getElementById('testDriveForm').addEventListener('submit', function(e) {
            let isValid = true;
            let firstError = null;
            
            // Vehicle is pre-selected from URL, no need to check selection
            
            // Validate phone number format
            const phone = document.getElementById('phone');
            const phoneRegex = /^[\d\s\(\)\+\-]{10,}$/;
            if (!phoneRegex.test(phone.value)) {
                showFieldError(phone, 'Please enter a valid phone number.');
                if (!firstError) firstError = phone;
                isValid = false;
            } else {
                clearFieldError(phone);
            }
            
            // Validate age
            const age = document.getElementById('age');
            const ageValue = parseInt(age.value);
            if (ageValue < 18 || ageValue > 100) {
                showFieldError(age, 'You must be at least 18 years old to schedule a test drive.');
                if (!firstError) firstError = age;
                isValid = false;
            } else {
                clearFieldError(age);
            }
            
            // Validate license number
            const license = document.getElementById('licenseNumber');
            if (license.value.trim().length < 5) {
                showFieldError(license, 'Please enter a valid driver\'s license number.');
                if (!firstError) firstError = license;
                isValid = false;
            } else {
                clearFieldError(license);
            }
            
            // Validate license image
            const licenseImage = document.getElementById('licenseImage');
            if (!licenseImage.files || licenseImage.files.length === 0) {
                showFieldError(licenseImage, 'Please upload your driver\'s license image.');
                if (!firstError) firstError = licenseImage;
                isValid = false;
            } else {
                // Validate file type
                const file = licenseImage.files[0];
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                if (!allowedTypes.includes(file.type)) {
                    showFieldError(licenseImage, 'Please upload a valid image file (JPEG, PNG, or GIF).');
                    if (!firstError) firstError = licenseImage;
                    isValid = false;
                } else if (file.size > 5 * 1024 * 1024) { // 5MB limit
                    showFieldError(licenseImage, 'Image file size must be less than 5MB.');
                    if (!firstError) firstError = licenseImage;
                    isValid = false;
                } else {
                    clearFieldError(licenseImage);
                }
            }
            
            // Validate required fields
            const requiredFields = ['preferredDate', 'preferredTime', 'dealership', 'duration', 'experience'];
            requiredFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (!field.value.trim()) {
                    showFieldError(field, 'This field is required.');
                    if (!firstError) firstError = field;
                    isValid = false;
                } else {
                    clearFieldError(field);
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                if (firstError) {
                    firstError.focus();
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                return false;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing Request...';
            
            showNotification('Submitting your test drive request...', 'info');
            
            return true;
        });
        
        // File upload functionality
        document.getElementById('licenseImage').addEventListener('change', function(e) {
            const fileInput = e.target;
            const fileDisplay = fileInput.nextElementSibling;
            const fileText = fileDisplay.querySelector('.file-upload-text');
            const fileIcon = fileDisplay.querySelector('i');
            
            if (fileInput.files && fileInput.files[0]) {
                const fileName = fileInput.files[0].name;
                const fileSize = (fileInput.files[0].size / 1024 / 1024).toFixed(2);
                
                fileText.textContent = `${fileName} (${fileSize} MB)`;
                fileIcon.className = 'fas fa-check-circle';
                fileDisplay.style.borderColor = '#28a745';
                fileDisplay.style.color = '#28a745';
            } else {
                fileText.textContent = 'Choose license image';
                fileIcon.className = 'fas fa-cloud-upload-alt';
                fileDisplay.style.borderColor = '';
                fileDisplay.style.color = '';
            }
        });
        
        // Enhanced field validation feedback
        function showFieldError(field, message) {
            clearFieldError(field);
            field.style.borderColor = '#ff4444';
            field.style.boxShadow = '0 0 10px rgba(255, 68, 68, 0.3)';
            
            const errorDiv = document.createElement('div');
            errorDiv.className = 'field-error';
            errorDiv.textContent = message;
            errorDiv.style.cssText = `
                color: #ff4444;
                font-size: 0.85rem;
                margin-top: 5px;
                display: flex;
                align-items: center;
                gap: 5px;
            `;
            errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
            
            field.parentNode.appendChild(errorDiv);
        }
        
        function clearFieldError(field) {
            field.style.borderColor = '';
            field.style.boxShadow = '';
            
            const existingError = field.parentNode.querySelector('.field-error');
            if (existingError) {
                existingError.remove();
            }
        }
        
        // Notification system
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                border-radius: 8px;
                color: white;
                font-weight: 500;
                z-index: 10000;
                transform: translateX(100%);
                transition: transform 0.3s ease;
                max-width: 300px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            `;
            
            const colors = {
                success: '#28a745',
                error: '#dc3545',
                info: '#17a2b8',
                warning: '#ffc107'
            };
            
            notification.style.backgroundColor = colors[type] || colors.info;
            notification.innerHTML = `<i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'times' : 'info'}-circle"></i> ${message}`;
            
            document.body.appendChild(notification);
            
            // Animate in
            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
            }, 100);
            
            // Auto remove
            setTimeout(() => {
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }, 4000);
        }

        // Auto-format phone number
        document.getElementById('phone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 6) {
                value = value.replace(/(\d{3})(\d{3})(\d{4})/, '($1) $2-$3');
            } else if (value.length >= 3) {
                value = value.replace(/(\d{3})(\d{1,3})/, '($1) $2');
            }
            e.target.value = value;
        });
        
        // Add smooth focus transitions for all form elements
        document.querySelectorAll('input, select, textarea').forEach(element => {
            element.addEventListener('focus', function() {
                this.parentNode.style.transform = 'translateY(-2px)';
            });
            
            element.addEventListener('blur', function() {
                this.parentNode.style.transform = 'translateY(0)';
            });
        });
        
        // Check for date/time conflicts
        function checkTimeSlotAvailability() {
            const dateInput = document.getElementById('preferredDate');
            const timeInput = document.getElementById('preferredTime');
            
            if (dateInput.value && timeInput.value) {
                fetch('check_availability.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `date=${encodeURIComponent(dateInput.value)}&time=${encodeURIComponent(timeInput.value)}`
                })
                .then(response => response.json())
                .then(data => {
                    const conflictMessage = document.getElementById('conflict-message');
                    if (conflictMessage) {
                        conflictMessage.remove();
                    }
                    
                    if (data.conflict) {
                        const message = document.createElement('div');
                        message.id = 'conflict-message';
                        message.style.cssText = `
                            color: #ff4444;
                            background: #fff5f5;
                            border: 1px solid #ffcccc;
                            padding: 10px;
                            border-radius: 5px;
                            margin-top: 10px;
                            font-size: 14px;
                        `;
                        message.innerHTML = '<i class="fas fa-exclamation-triangle"></i> This time slot is already booked. Please select a different time.';
                        timeInput.parentNode.parentNode.appendChild(message);
                        
                        // Disable submit button
                        const submitBtn = document.querySelector('button[type="submit"]');
                        submitBtn.disabled = true;
                        submitBtn.style.opacity = '0.6';
                    } else {
                        // Enable submit button
                        const submitBtn = document.querySelector('button[type="submit"]');
                        submitBtn.disabled = false;
                        submitBtn.style.opacity = '1';
                    }
                })
                .catch(error => {
                    console.error('Error checking availability:', error);
                });
            }
        }
        
        // Add event listeners for date and time changes
        document.getElementById('preferredDate').addEventListener('change', checkTimeSlotAvailability);
        document.getElementById('preferredTime').addEventListener('change', checkTimeSlotAvailability);
    </script>
</body>
</html>

</body>
</html>
