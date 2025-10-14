<?php
session_start();
include_once(dirname(__DIR__) . '/includes/init.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die('Please log in first');
}

echo "<h2>Test Drive Debug Information</h2>";
echo "<p>Logged in as user ID: " . $_SESSION['user_id'] . "</p>";

try {
    // Check test_drive_requests table
    echo "<h3>test_drive_requests table:</h3>";
    $stmt1 = $connect->prepare("SELECT COUNT(*) FROM test_drive_requests WHERE account_id = ?");
    $stmt1->execute([$_SESSION['user_id']]);
    $count1 = $stmt1->fetchColumn();
    echo "<p>Records for current user: " . $count1 . "</p>";
    
    $stmt2 = $connect->prepare("SELECT COUNT(*) FROM test_drive_requests");
    $stmt2->execute();
    $total1 = $stmt2->fetchColumn();
    echo "<p>Total records in table: " . $total1 . "</p>";
    
    if ($count1 > 0) {
        $stmt3 = $connect->prepare("SELECT id, account_id, customer_name, selected_date, status, requested_at FROM test_drive_requests WHERE account_id = ? ORDER BY requested_at DESC");
        $stmt3->execute([$_SESSION['user_id']]);
        $records1 = $stmt3->fetchAll(PDO::FETCH_ASSOC);
        echo "<pre>" . print_r($records1, true) . "</pre>";
        
        // Test the exact query from my_inquiries.php
        echo "<h4>Testing the exact query from my_inquiries.php:</h4>";
        $sql_tdr = "
            SELECT 
                tdr.id,
                tdr.account_id,
                tdr.vehicle_id,
                tdr.gate_pass_number,
                tdr.customer_name,
                tdr.mobile_number,
                tdr.selected_date AS scheduled_date,
                tdr.selected_time_slot AS scheduled_time,
                tdr.test_drive_location,
                tdr.instructor_agent,
                tdr.status AS test_drive_status,
                tdr.requested_at AS created_at,
                tdr.approved_at,
                tdr.notes,
                v.model_name AS vehicle_model,
                v.variant AS vehicle_variant,
                v.main_image AS vehicle_image
            FROM test_drive_requests tdr
            LEFT JOIN vehicles v ON tdr.vehicle_id = v.id
            WHERE tdr.account_id = ?
            ORDER BY tdr.requested_at DESC
        ";
        $stmt_test = $connect->prepare($sql_tdr);
        $stmt_test->execute([$_SESSION['user_id']]);
        $test_records = $stmt_test->fetchAll(PDO::FETCH_ASSOC);
        echo "<p>Records found with JOIN query: " . count($test_records) . "</p>";
        echo "<pre>" . print_r($test_records, true) . "</pre>";
    }
    
    // Check test_drive_bookings table
    echo "<h3>test_drive_bookings table:</h3>";
    $stmt4 = $connect->prepare("SELECT COUNT(*) FROM test_drive_bookings WHERE account_id = ?");
    $stmt4->execute([$_SESSION['user_id']]);
    $count2 = $stmt4->fetchColumn();
    echo "<p>Records for current user: " . $count2 . "</p>";
    
    $stmt5 = $connect->prepare("SELECT COUNT(*) FROM test_drive_bookings");
    $stmt5->execute();
    $total2 = $stmt5->fetchColumn();
    echo "<p>Total records in table: " . $total2 . "</p>";
    
    if ($count2 > 0) {
        $stmt6 = $connect->prepare("SELECT id, account_id, customer_name, selected_date, status, requested_at FROM test_drive_bookings WHERE account_id = ? ORDER BY requested_at DESC");
        $stmt6->execute([$_SESSION['user_id']]);
        $records2 = $stmt6->fetchAll(PDO::FETCH_ASSOC);
        echo "<pre>" . print_r($records2, true) . "</pre>";
    }
    
} catch (PDOException $e) {
    echo "<p>Database error: " . $e->getMessage() . "</p>";
}
?>