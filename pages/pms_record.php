<?php
session_start();
include_once(dirname(__DIR__) . '/includes/init.php');
include_once(dirname(__DIR__) . '/pages/header_ex.php');

// Check if user is logged in and is a customer
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Customer') {
    header("Location: login.php");
    exit;
}

// Use the database connection from init.php
$pdo = $GLOBALS['pdo'] ?? null;

if (!$pdo) {
    die("Database connection not available. Please check your database configuration.");
}

// For backward compatibility, create $connect variable
$connect = $pdo;

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

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $required_fields = ['plate_number', 'model', 'current_odometer', 'pms_info', 'pms_date'];
        $missing_fields = [];
        
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                $missing_fields[] = ucwords(str_replace('_', ' ', $field));
            }
        }
        
        if (!empty($missing_fields)) {
            throw new Exception("Please fill in all required fields: " . implode(', ', $missing_fields));
        }

        // Sanitize odometer value - remove any non-numeric characters
        $current_odometer = preg_replace('/\D/', '', $_POST['current_odometer']);
        $current_odometer = $current_odometer === '' ? 0 : intval($current_odometer);

        // Handle checkboxes
        $service_oil_change = isset($_POST['service_oil_change']) ? 1 : 0;
        $service_oil_filter_replacement = isset($_POST['service_oil_filter_replacement']) ? 1 : 0;
        $service_air_filter_replacement = isset($_POST['service_air_filter_replacement']) ? 1 : 0;
        $service_tire_rotation = isset($_POST['service_tire_rotation']) ? 1 : 0;
        $service_fluid_top_up = isset($_POST['service_fluid_top_up']) ? 1 : 0;
        $service_spark_plug_check = isset($_POST['service_spark_plug_check']) ? 1 : 0;
        
        // Handle file upload
        $uploaded_receipt = null;
        if (isset($_FILES['uploaded_receipt']) && $_FILES['uploaded_receipt']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            $file_type = $_FILES['uploaded_receipt']['type'];
            
            if (in_array($file_type, $allowed_types)) {
                $uploaded_receipt = file_get_contents($_FILES['uploaded_receipt']['tmp_name']);
            } else {
                throw new Exception('Invalid file type. Only JPG, PNG, PDF, and DOCX files are allowed.');
            }
        }
        
        // Insert PMS record into database with customer_id and default status
        $stmt_insert = $connect->prepare("
            INSERT INTO car_pms_records (
                customer_id, plate_number, model, transmission, engine_type, color, current_odometer,
                pms_info, pms_date, next_pms_due, service_oil_change, service_oil_filter_replacement,
                service_air_filter_replacement, service_tire_rotation, service_fluid_top_up,
                service_spark_plug_check, service_others, service_notes_findings, uploaded_receipt,
                request_status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW(), NOW())
        ");
        
        $stmt_insert->execute([
            $_SESSION['user_id'], // Add customer_id
            $_POST['plate_number'],
            $_POST['model'],
            $_POST['transmission'] ?? null,
            $_POST['engine_type'] ?? null,
            $_POST['color'] ?? null,
            $current_odometer, // Use sanitized odometer value
            $_POST['pms_info'],
            $_POST['pms_date'],
            $_POST['next_pms_due'] ?? null,
            $service_oil_change,
            $service_oil_filter_replacement,
            $service_air_filter_replacement,
            $service_tire_rotation,
            $service_fluid_top_up,
            $service_spark_plug_check,
            $_POST['service_others'] ?? null,
            $_POST['service_notes_findings'] ?? null,
            $uploaded_receipt
        ]);
        
        $success_message = "PMS request has been submitted successfully and is pending approval! You will be notified once the request is processed.";
        
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
    <title>PMS Record - Mitsubishi Motors</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', 'Segoe UI', sans-serif; }
        body { background: #ffffff; min-height: 100vh; color: white; }
        .header { background: #000000; padding: 20px 30px; display: flex; justify-content: space-between; align-items: center; backdrop-filter: blur(20px); border-bottom: 1px solid rgba(255, 215, 0, 0.2); position: relative; z-index: 10; }
        .logo-section { display: flex; align-items: center; gap: 20px; }
        .logo { width: 60px; height: auto; filter: drop-shadow(0 0 10px rgba(255, 215, 0, 0.3)); }
        .brand-text { font-size: 1.4rem; font-weight: 700; background: #ffffff; -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .user-section { display: flex; align-items: center; gap: 5px; }
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(45deg, #ffd700, #ffed4e); display: flex; align-items: center; justify-content: center; font-weight: bold; color: #b80000; font-size: 1.2rem; }
        .welcome-text { font-size: 1rem; font-weight: 500; }
        .logout-btn { background: linear-gradient(45deg, #d60000, #b30000); color: white; border: none; padding: 12px 24px; border-radius: 25px; cursor: pointer; font-size: 0.9rem; font-weight: 600; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(214, 0, 0, 0.3); }
        .logout-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(214, 0, 0, 0.5); }
        .container { max-width: 800px; margin: 0 auto; padding: 30px 20px; position: relative; z-index: 5; }
        .back-btn { display: inline-block; margin-bottom: 20px; background: #808080; color: #ffffff; padding: 8px 16px; border-radius: 8px; text-decoration: none; font-weight: 500; transition: all 0.3s ease; font-size: 0.9rem; }
        .back-btn:hover { background: #E60012; color: #ffffff; }

        .pms-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .card-header {
            background: #808080;
            padding: 25px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 8px;
        }

        .page-subtitle {
            color: #000000;
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
            color: rgba(0, 0, 0, 0.9);
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 5px;
        }

        .form-label.required::after {
            content: ' *';
            color: #ff6b6b;
        }

        .form-input, .form-select, .form-textarea {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(2, 2, 2, 0.2);
            border-radius: 8px;
            padding: 12px;
            color: #000000;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #808080;
            box-shadow: 0 0 0 2px rgba(255, 215, 0, 0.2);
        }

        .form-textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-select option {
            background: #2a2a2a;
            color: white;
        }

        .radio-group {
            display: flex;
            gap: 20px;
            align-items: center;
            margin-top: 5px;
        }

        .radio-group label {
            display: flex;
            align-items: center;
            gap: 5px;
            color: rgba(0, 0, 0, 0.9);
            font-size: 0.9rem;
            cursor: pointer;
        }

        .radio-group input[type="radio"] {
            accent-color: #E60012;
        }

        .checkbox-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-top: 10px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #E60012;
        }

        .checkbox-group label {
            color: rgba(0, 0, 0, 0.9);
            font-size: 0.9rem;
            cursor: pointer;
        }

        .file-upload {
            position: relative;
            display: inline-block;
            width: 100%;
        }

        .file-upload input[type="file"] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .file-upload-label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 20px;
            background: linear-gradient(45deg, #ffd700, #ffed4e);
            color: #1a1a1a;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .file-upload-label:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(255, 215, 0, 0.3);
        }

        .file-name {
            display: block;
            margin-top: 8px;
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.7);
            font-style: italic;
        }

        .submit-btn {
            background: linear-gradient(45deg, #ffd700, #ffed4e);
            color: #1a1a1a;
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
            box-shadow: 0 4px 15px rgba(255, 215, 0, 0.3);
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 215, 0, 0.5);
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .alert-success {
            background: rgba(76, 175, 80, 0.2);
            color: #4CAF50;
            border: 1px solid rgba(76, 175, 80, 0.3);
        }

        .alert-error {
            background: rgba(244, 67, 54, 0.2);
            color: #f44336;
            border: 1px solid rgba(244, 67, 54, 0.3);
        }

        .current-date {
            background: rgba(255, 215, 0, 0.1);
            border: 1px solid rgba(255, 215, 0, 0.3);
            padding: 10px;
            border-radius: 6px;
            color: #ffd700;
            font-weight: 500;
            text-align: center;
        }

        @media (max-width: 768px) {
            .header { flex-direction: column; gap: 15px; padding: 15px 20px; }
            .user-section { flex-direction: column; gap: 12px; text-align: center; width: 100%; }
            .container { padding: 20px 15px; }
            .form-container { padding: 20px; }
            .form-grid { grid-template-columns: 1fr; }
            .radio-group { flex-direction: column; align-items: flex-start; gap: 8px; }
            .checkbox-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>


    <div class="container">
        <a href="<?php echo $selected_vehicle_id ? 'car_details.php?id=' . $selected_vehicle_id : 'car_menu.php'; ?>" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back
        </a>

        <div class="pms-card">
            <div class="card-header">
                <h1 class="page-title"><i class="fas fa-car"></i> CAR PMS RECORD</h1>
                <p class="page-subtitle">Enter your vehicle's preventive maintenance service details</p>
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

                <form method="POST" action="" enctype="multipart/form-data">
                    <!-- Car Details -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-car-side"></i>
                            Car Details
                        </h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label required">Plate Number</label>
                                <input type="text" name="plate_number" class="form-input" 
                                       value="<?php echo htmlspecialchars($_POST['plate_number'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label required">Model</label>
                                <input type="text" name="model" class="form-input" 
                                       value="<?php echo htmlspecialchars($_POST['model'] ?? ($selected_vehicle['model_name'] ?? '')); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Transmission</label>
                                <div class="radio-group">
                                    <label><input type="radio" name="transmission" value="Automatic" 
                                           <?php echo (($_POST['transmission'] ?? '') === 'Automatic') ? 'checked' : ''; ?>> Automatic</label>
                                    <label><input type="radio" name="transmission" value="Manual"
                                           <?php echo (($_POST['transmission'] ?? '') === 'Manual') ? 'checked' : ''; ?>> Manual</label>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Engine Type</label>
                                <div class="radio-group">
                                    <label><input type="radio" name="engine_type" value="Gasoline"
                                           <?php echo (($_POST['engine_type'] ?? '') === 'Gasoline') ? 'checked' : ''; ?>> Gasoline</label>
                                    <label><input type="radio" name="engine_type" value="Diesel"
                                           <?php echo (($_POST['engine_type'] ?? '') === 'Diesel') ? 'checked' : ''; ?>> Diesel</label>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Color</label>
                                <input type="text" name="color" class="form-input" 
                                       value="<?php echo htmlspecialchars($_POST['color'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label required">Current Odometer (km)</label>
                                <input type="text" name="current_odometer" class="form-input"
                                       value="<?php echo htmlspecialchars($_POST['current_odometer'] ?? ''); ?>"
                                       pattern="[0-9]*" inputmode="numeric"
                                       placeholder="e.g., 20000" required>
                                <small style="color: #666; font-size: 0.85em;">Enter numbers only (e.g., 20000)</small>
                            </div>
                            <div class="form-group">
                                <label class="form-label required">PMS Info</label>
                                <select name="pms_info" class="form-select" required>
                                    <option value="">Select PMS Type</option>
                                    <option value="First 1K KM" <?php echo (($_POST['pms_info'] ?? '') === 'First 1K KM') ? 'selected' : ''; ?>>First 1K KM</option>
                                    <option value="5K KM" <?php echo (($_POST['pms_info'] ?? '') === '5K KM') ? 'selected' : ''; ?>>5K KM</option>
                                    <option value="10K KM" <?php echo (($_POST['pms_info'] ?? '') === '10K KM') ? 'selected' : ''; ?>>10K KM</option>
                                    <option value="15K KM" <?php echo (($_POST['pms_info'] ?? '') === '15K KM') ? 'selected' : ''; ?>>15K KM</option>
                                    <option value="20K KM" <?php echo (($_POST['pms_info'] ?? '') === '20K KM') ? 'selected' : ''; ?>>20K KM</option>
                                    <option value="General PMS" <?php echo (($_POST['pms_info'] ?? '') === 'General PMS') ? 'selected' : ''; ?>>General PMS</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label required">PMS Date</label>
                                <input type="date" name="pms_date" class="form-input" 
                                       value="<?php echo htmlspecialchars($_POST['pms_date'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group full-width">
                                <label class="form-label">Next PMS Due</label>
                                <input type="text" name="next_pms_due" class="form-input" 
                                       value="<?php echo htmlspecialchars($_POST['next_pms_due'] ?? ''); ?>"
                                       placeholder="e.g., 25K KM or specific date">
                            </div>
                        </div>
                    </div>

                    <!-- Performed Services -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-tools"></i>
                            Performed Services
                        </h3>
                        <div class="checkbox-grid">
                            <div class="checkbox-group">
                                <input type="checkbox" name="service_oil_change" value="1" id="oil_change"
                                       <?php echo isset($_POST['service_oil_change']) ? 'checked' : ''; ?>>
                                <label for="oil_change">Oil Change</label>
                            </div>
                            <div class="checkbox-group">
                                <input type="checkbox" name="service_fluid_top_up" value="1" id="fluid_top_up"
                                       <?php echo isset($_POST['service_fluid_top_up']) ? 'checked' : ''; ?>>
                                <label for="fluid_top_up">Fluid Top-Up (Coolant, Brake, Power Steering)</label>
                            </div>
                            <div class="checkbox-group">
                                <input type="checkbox" name="service_oil_filter_replacement" value="1" id="oil_filter"
                                       <?php echo isset($_POST['service_oil_filter_replacement']) ? 'checked' : ''; ?>>
                                <label for="oil_filter">Oil Filter Replacement</label>
                            </div>
                            <div class="checkbox-group">
                                <input type="checkbox" name="service_spark_plug_check" value="1" id="spark_plug"
                                       <?php echo isset($_POST['service_spark_plug_check']) ? 'checked' : ''; ?>>
                                <label for="spark_plug">Spark Plug Check</label>
                            </div>
                            <div class="checkbox-group">
                                <input type="checkbox" name="service_air_filter_replacement" value="1" id="air_filter"
                                       <?php echo isset($_POST['service_air_filter_replacement']) ? 'checked' : ''; ?>>
                                <label for="air_filter">Air Filter Cleaning/Replacement</label>
                            </div>
                            <div class="checkbox-group">
                                <input type="checkbox" name="service_tire_rotation" value="1" id="tire_rotation"
                                       <?php echo isset($_POST['service_tire_rotation']) ? 'checked' : ''; ?>>
                                <label for="tire_rotation">Tire Rotation</label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Others</label>
                            <input type="text" name="service_others" class="form-input" 
                                   value="<?php echo htmlspecialchars($_POST['service_others'] ?? ''); ?>"
                                   placeholder="Any other services performed">
                        </div>
                    </div>

                    <!-- Service Notes -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-clipboard-list"></i>
                            Service Notes / Findings
                        </h3>
                        <div class="form-group">
                            <textarea name="service_notes_findings" class="form-textarea" rows="4"
                                      placeholder="Any notes, findings, or recommendations from the service"><?php echo htmlspecialchars($_POST['service_notes_findings'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- File Upload -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-upload"></i>
                            Uploaded Receipt / Job Order
                        </h3>
                        <div class="form-group">
                            <div class="file-upload">
                                <input type="file" id="uploaded_receipt" name="uploaded_receipt" accept=".jpg,.jpeg,.png,.pdf,.docx">
                                <label for="uploaded_receipt" class="file-upload-label">
                                    <i class="fas fa-upload"></i> Upload File
                                </label>
                                <span class="file-name"></span>
                            </div>
                            <small style="color: #000000; margin-top: 5px; display: block;">
                                Accepted formats: JPG, PNG, PDF, DOCX (Max 10MB)
                            </small>
                        </div>
                    </div>

                    <button type="submit" class="submit-btn">
                        <i class="fas fa-save"></i>
                        Save PMS Record
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // File upload handling
        document.getElementById('uploaded_receipt').addEventListener('change', function() {
            const fileName = this.files[0]?.name || '';
            document.querySelector('.file-name').textContent = fileName;
        });

        // Odometer input validation - only allow numbers
        const odometerInput = document.querySelector('input[name="current_odometer"]');
        if (odometerInput) {
            odometerInput.addEventListener('input', function(e) {
                // Remove all non-numeric characters
                this.value = this.value.replace(/\D/g, '');
            });

            odometerInput.addEventListener('paste', function(e) {
                setTimeout(() => {
                    this.value = this.value.replace(/\D/g, '');
                }, 0);
            });
        }
    </script>
</body>
</html>
