<?php
session_start();
include_once(dirname(__DIR__) . '/includes/init.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die("Not logged in");
}

$pdo = $GLOBALS['pdo'] ?? null;
if (!$pdo) {
    die("Database connection not available.");
}

$agent_id = $_SESSION['user_id'];

echo "<h1>Agent PMS Inquiries Debug</h1>";
echo "<p>Agent ID: $agent_id</p>";
echo "<p>User Role: " . ($_SESSION['user_role'] ?? 'Not set') . "</p>";

// Test the exact query from agent_pms_inquiries.php
echo "<h2>Testing Exact Query from agent_pms_inquiries.php:</h2>";
try {
    $stmt_inquiries = $pdo->prepare("
        SELECT
            pi.id as inquiry_id,
            pi.pms_id,
            pi.customer_id,
            pi.status,
            pi.created_at,
            pi.updated_at,
            pi.assigned_agent_id,
            cpr.plate_number,
            cpr.model,
            cpr.pms_info,
            cpr.pms_date,
            acc.FirstName,
            acc.LastName,
            acc.Email,
            ci.mobile_number as PhoneNumber
        FROM pms_inquiries pi
        LEFT JOIN car_pms_records cpr ON pi.pms_id = cpr.pms_id
        LEFT JOIN accounts acc ON pi.customer_id = acc.Id
        LEFT JOIN customer_information ci ON pi.customer_id = ci.account_id
        ORDER BY pi.created_at DESC
    ");
    $stmt_inquiries->execute();
    $inquiries = $stmt_inquiries->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p><strong>Total Inquiries Found:</strong> " . count($inquiries) . "</p>";
    
    if (count($inquiries) > 0) {
        echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
        echo "<tr style='background: #f0f0f0;'>";
        echo "<th>ID</th><th>PMS ID</th><th>Model</th><th>PMS Info</th><th>Plate</th>";
        echo "<th>Customer</th><th>Email</th><th>Status</th><th>Assigned Agent</th></tr>";
        
        foreach ($inquiries as $inq) {
            echo "<tr>";
            echo "<td>" . ($inq['inquiry_id'] ?? 'NULL') . "</td>";
            echo "<td>" . ($inq['pms_id'] ?? 'NULL') . "</td>";
            echo "<td>" . ($inq['model'] ?? '<span style="color:red;">NULL</span>') . "</td>";
            echo "<td>" . ($inq['pms_info'] ?? '<span style="color:red;">NULL</span>') . "</td>";
            echo "<td>" . ($inq['plate_number'] ?? '<span style="color:red;">NULL</span>') . "</td>";
            echo "<td>" . (($inq['FirstName'] ?? '') . ' ' . ($inq['LastName'] ?? '')) . "</td>";
            echo "<td>" . ($inq['Email'] ?? '<span style="color:red;">NULL</span>') . "</td>";
            echo "<td>" . ($inq['status'] ?? 'NULL') . "</td>";
            echo "<td>" . ($inq['assigned_agent_id'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Show raw data for first inquiry
        if (count($inquiries) > 0) {
            echo "<h3>Raw Data for First Inquiry:</h3>";
            echo "<pre>";
            print_r($inquiries[0]);
            echo "</pre>";
        }
    } else {
        echo "<p style='color: red;'>No inquiries found!</p>";
    }
} catch (PDOException $e) {
    echo "<p style='color: red;'><strong>Error:</strong> " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

// Check if empty() would return true
echo "<h2>Testing empty() Function:</h2>";
if (isset($inquiries)) {
    echo "<p>count(\$inquiries) = " . count($inquiries) . "</p>";
    echo "<p>empty(\$inquiries) = " . (empty($inquiries) ? 'TRUE' : 'FALSE') . "</p>";
    echo "<p>isset(\$inquiries) = " . (isset($inquiries) ? 'TRUE' : 'FALSE') . "</p>";
}
?>

