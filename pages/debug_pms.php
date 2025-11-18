<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include_once(dirname(__DIR__) . '/includes/init.php');

$pdo = $GLOBALS['pdo'] ?? null;
if (!$pdo) {
    die("Database connection not available.");
}

echo "<h1>PMS Debug Information</h1>";

// Check car_pms_records table structure
echo "<h2>car_pms_records table structure:</h2>";
try {
    $stmt = $pdo->query("DESCRIBE car_pms_records");
    echo "<pre>";
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    echo "</pre>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

// Check pms_inquiries table structure
echo "<h2>pms_inquiries table structure:</h2>";
try {
    $stmt = $pdo->query("DESCRIBE pms_inquiries");
    echo "<pre>";
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    echo "</pre>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

// Check pms_inquiries data
echo "<h2>pms_inquiries data:</h2>";
try {
    $stmt = $pdo->query("SELECT * FROM pms_inquiries LIMIT 5");
    echo "<pre>";
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    echo "</pre>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

// Check car_pms_records data
echo "<h2>car_pms_records data (first 5, excluding receipt blob):</h2>";
try {
    $stmt = $pdo->query("
        SELECT
            pms_id,
            customer_id,
            plate_number,
            model,
            transmission,
            engine_type,
            color,
            current_odometer,
            pms_info,
            pms_date,
            next_pms_due,
            request_status,
            created_at,
            updated_at,
            service_notes_findings,
            CASE WHEN uploaded_receipt IS NULL THEN 0 ELSE 1 END AS has_receipt
        FROM car_pms_records
        ORDER BY created_at DESC
        LIMIT 5
    ");
    echo "<pre>";
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    echo "</pre>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

// Try the join query
echo "<h2>Join query test:</h2>";
try {
    $stmt = $pdo->prepare("
        SELECT
            pi.id as inquiry_id,
            pi.pms_id,
            pi.status,
            cpr.plate_number,
            cpr.model
        FROM pms_inquiries pi
        LEFT JOIN car_pms_records cpr ON pi.pms_id = cpr.pms_id
        LIMIT 5
    ");
    $stmt->execute();
    echo "<pre>";
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    echo "</pre>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>

