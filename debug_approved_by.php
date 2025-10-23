<?php
require_once 'config/database.php';

$stmt = $pdo->query("
    SELECT 
        p.pms_id,
        p.request_status,
        p.approved_by,
        a.Id as account_id,
        a.FirstName,
        a.LastName,
        a.Role,
        sap.display_name,
        sap.account_id as sap_account_id
    FROM car_pms_records p
    LEFT JOIN accounts a ON p.approved_by = a.Id
    LEFT JOIN sales_agent_profiles sap ON a.Id = sap.account_id
    WHERE p.request_status = 'Completed'
    LIMIT 10
");

echo "<h2>Debug: Approved By Data</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr>
    <th>PMS ID</th>
    <th>Status</th>
    <th>approved_by (raw)</th>
    <th>accounts.Id</th>
    <th>FirstName</th>
    <th>LastName</th>
    <th>Role</th>
    <th>display_name</th>
    <th>sap.account_id</th>
</tr>";

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['pms_id']) . "</td>";
    echo "<td>" . htmlspecialchars($row['request_status']) . "</td>";
    echo "<td>" . htmlspecialchars($row['approved_by'] ?? 'NULL') . "</td>";
    echo "<td>" . htmlspecialchars($row['account_id'] ?? 'NULL') . "</td>";
    echo "<td>" . htmlspecialchars($row['FirstName'] ?? 'NULL') . "</td>";
    echo "<td>" . htmlspecialchars($row['LastName'] ?? 'NULL') . "</td>";
    echo "<td>" . htmlspecialchars($row['Role'] ?? 'NULL') . "</td>";
    echo "<td>" . htmlspecialchars($row['display_name'] ?? 'NULL') . "</td>";
    echo "<td>" . htmlspecialchars($row['sap_account_id'] ?? 'NULL') . "</td>";
    echo "</tr>";
}

echo "</table>";

// Also check what's in sales_agent_profiles
echo "<h2>All Sales Agent Profiles</h2>";
$stmt = $pdo->query("
    SELECT 
        sap.agent_id,
        sap.account_id,
        sap.display_name,
        a.FirstName,
        a.LastName,
        a.Role
    FROM sales_agent_profiles sap
    LEFT JOIN accounts a ON sap.account_id = a.Id
");

echo "<table border='1' cellpadding='5'>";
echo "<tr>
    <th>agent_id</th>
    <th>account_id</th>
    <th>display_name</th>
    <th>FirstName</th>
    <th>LastName</th>
    <th>Role</th>
</tr>";

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['agent_id']) . "</td>";
    echo "<td>" . htmlspecialchars($row['account_id']) . "</td>";
    echo "<td>" . htmlspecialchars($row['display_name'] ?? 'NULL') . "</td>";
    echo "<td>" . htmlspecialchars($row['FirstName'] ?? 'NULL') . "</td>";
    echo "<td>" . htmlspecialchars($row['LastName'] ?? 'NULL') . "</td>";
    echo "<td>" . htmlspecialchars($row['Role'] ?? 'NULL') . "</td>";
    echo "</tr>";
}

echo "</table>";

