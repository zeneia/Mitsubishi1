<?php
session_start();
include_once(dirname(__DIR__) . '/includes/database/db_conn.php');

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    // Get form data
    $plate_number = $_POST['plate_number'] ?? null;
    $model = $_POST['model'] ?? null;
    $transmission = $_POST['transmission'] ?? null;
    $engine_type = $_POST['engine_type'] ?? null;
    $color = $_POST['color'] ?? null;

    // Sanitize odometer value - remove any non-numeric characters
    $current_odometer = $_POST['current_odometer'] ?? null;
    if ($current_odometer !== null) {
        $current_odometer = preg_replace('/\D/', '', $current_odometer);
        $current_odometer = $current_odometer === '' ? null : intval($current_odometer);
    }

    $pms_info = $_POST['pms_info'] ?? null;
    $pms_date = $_POST['pms_date'] ?? null;
    $next_pms_due = $_POST['next_pms_due'] ?? null;
    $service_notes_findings = $_POST['service_notes_findings'] ?? null;
    $service_others = $_POST['service_others'] ?? null;
    
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
            echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, PDF, and DOCX files are allowed.']);
            exit;
        }
    }
    
    // Prepare SQL statement
    $sql = "INSERT INTO car_pms_records (
        plate_number, model, transmission, engine_type, color, current_odometer,
        pms_info, pms_date, next_pms_due, service_oil_change, service_oil_filter_replacement,
        service_air_filter_replacement, service_tire_rotation, service_fluid_top_up,
        service_spark_plug_check, service_others, service_notes_findings, uploaded_receipt
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $connect->prepare($sql);
    $result = $stmt->execute([
        $plate_number, $model, $transmission, $engine_type, $color, $current_odometer,
        $pms_info, $pms_date, $next_pms_due, $service_oil_change, $service_oil_filter_replacement,
        $service_air_filter_replacement, $service_tire_rotation, $service_fluid_top_up,
        $service_spark_plug_check, $service_others, $service_notes_findings, $uploaded_receipt
    ]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'PMS record saved successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save PMS record']);
    }
    
} catch (PDOException $e) {
    error_log("Database error in process_pms.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("General error in process_pms.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while processing the request']);
}
?>
