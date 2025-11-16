<?php
session_start();
include_once('includes/database/db_conn.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die("Please login first");
}

echo "<h2>Test Drive Request Debug</h2>";

// Get the request ID from URL
$request_id = isset($_GET['request_id']) ? (int)$_GET['request_id'] : 57;

echo "<h3>Request ID: $request_id</h3>";

// Show table structure
echo "<h3>test_drive_requests Table Structure:</h3>";
$stmt = $connect->query("DESCRIBE test_drive_requests");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
foreach ($columns as $col) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
    echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
    echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
    echo "<td>" . htmlspecialchars($col['Key']) . "</td>";
    echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
    echo "</tr>";
}
echo "</table>";

// Show actual data
echo "<h3>Test Drive Request Data:</h3>";
$stmt = $connect->prepare("
    SELECT tdr.*, 
           v.model_name, 
           v.variant, 
           v.year_model,
           ci.agent_id,
           CONCAT(agent.FirstName, ' ', agent.LastName) as agent_name,
           CONCAT(customer.FirstName, ' ', customer.LastName) as customer_account_name
    FROM test_drive_requests tdr 
    LEFT JOIN vehicles v ON tdr.vehicle_id = v.id 
    LEFT JOIN customer_information ci ON tdr.account_id = ci.account_id
    LEFT JOIN accounts agent ON ci.agent_id = agent.Id
    LEFT JOIN accounts customer ON tdr.account_id = customer.Id
    WHERE tdr.id = ?
");
$stmt->execute([$request_id]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if ($request) {
    echo "<table border='1' cellpadding='5'>";
    foreach ($request as $key => $value) {
        echo "<tr>";
        echo "<td><strong>" . htmlspecialchars($key) . "</strong></td>";
        if ($key === 'drivers_license') {
            echo "<td>[BLOB DATA - " . strlen($value) . " bytes]</td>";
        } else {
            echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
    
    // Show notes field specifically
    echo "<h3>Notes Field Content:</h3>";
    echo "<pre>" . htmlspecialchars($request['notes'] ?? 'NULL') . "</pre>";
    
    // Try to extract license number
    echo "<h3>License Number Extraction:</h3>";
    $license_number = 'N/A';
    if (!empty($request['notes'])) {
        if (preg_match('/License Number:\s*(.+?)(?:\n|$)/i', $request['notes'], $matches)) {
            $license_number = trim($matches[1]);
            echo "<p>Found: <strong>" . htmlspecialchars($license_number) . "</strong></p>";
        } else {
            echo "<p>Pattern not found in notes</p>";
        }
    } else {
        echo "<p>Notes field is empty</p>";
    }
} else {
    echo "<p>No request found with ID: $request_id</p>";
}
?>

