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

echo "<h1>PMS Inquiries Debug</h1>";
echo "<p>Agent ID: $agent_id</p>";

// Check if pms_inquiries table exists
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM pms_inquiries");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p><strong>Total PMS Inquiries in Database:</strong> " . $result['count'] . "</p>";
} catch (PDOException $e) {
    echo "<p style='color: red;'><strong>Error:</strong> " . $e->getMessage() . "</p>";
}

// Show all inquiries
echo "<h2>All PMS Inquiries:</h2>";
try {
    $stmt = $pdo->query("
        SELECT
            pi.id, pi.pms_id, pi.customer_id, pi.assigned_agent_id, pi.status, pi.created_at,
            cpr.plate_number, cpr.model,
            acc.FirstName, acc.LastName
        FROM pms_inquiries pi
        LEFT JOIN car_pms_records cpr ON pi.pms_id = cpr.pms_id
        LEFT JOIN accounts acc ON pi.customer_id = acc.Id
        ORDER BY pi.created_at DESC
    ");
    $inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($inquiries) > 0) {
        echo "<table border='1' cellpadding='10'>";
        echo "<tr><th>ID</th><th>PMS ID</th><th>Customer</th><th>Assigned Agent</th><th>Status</th><th>Created</th></tr>";
        foreach ($inquiries as $inq) {
            echo "<tr>";
            echo "<td>" . $inq['id'] . "</td>";
            echo "<td>" . $inq['pms_id'] . "</td>";
            echo "<td>" . $inq['FirstName'] . " " . $inq['LastName'] . "</td>";
            echo "<td>" . ($inq['assigned_agent_id'] ?? 'NULL') . "</td>";
            echo "<td>" . $inq['status'] . "</td>";
            echo "<td>" . $inq['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No PMS inquiries found in database</p>";
    }
} catch (PDOException $e) {
    echo "<p style='color: red;'><strong>Error:</strong> " . $e->getMessage() . "</p>";
}

// Show inquiries for current agent
echo "<h2>Inquiries for Agent $agent_id:</h2>";
try {
    $stmt = $pdo->prepare("
        SELECT
            pi.id, pi.pms_id, pi.customer_id, pi.assigned_agent_id, pi.status, pi.created_at,
            cpr.plate_number, cpr.model,
            acc.FirstName, acc.LastName
        FROM pms_inquiries pi
        LEFT JOIN car_pms_records cpr ON pi.pms_id = cpr.pms_id
        LEFT JOIN accounts acc ON pi.customer_id = acc.Id
        WHERE pi.assigned_agent_id = ? OR pi.assigned_agent_id IS NULL
        ORDER BY pi.created_at DESC
    ");
    $stmt->execute([$agent_id]);
    $agent_inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p><strong>Count:</strong> " . count($agent_inquiries) . "</p>";
    
    if (count($agent_inquiries) > 0) {
        echo "<table border='1' cellpadding='10'>";
        echo "<tr><th>ID</th><th>PMS ID</th><th>Customer</th><th>Assigned Agent</th><th>Status</th><th>Created</th></tr>";
        foreach ($agent_inquiries as $inq) {
            echo "<tr>";
            echo "<td>" . $inq['id'] . "</td>";
            echo "<td>" . $inq['pms_id'] . "</td>";
            echo "<td>" . $inq['FirstName'] . " " . $inq['LastName'] . "</td>";
            echo "<td>" . ($inq['assigned_agent_id'] ?? 'NULL') . "</td>";
            echo "<td>" . $inq['status'] . "</td>";
            echo "<td>" . $inq['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No inquiries found for this agent</p>";
    }
} catch (PDOException $e) {
    echo "<p style='color: red;'><strong>Error:</strong> " . $e->getMessage() . "</p>";
}
?>

