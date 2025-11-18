<?php
session_start();
$_SESSION['user_id'] = 117;

include 'includes/init.php';

$agentId = $_SESSION['user_id'];
echo "Agent ID: " . $agentId . "\n";

// Test the query
$stmt = $pdo->prepare("SELECT * FROM pms_inquiries WHERE assigned_agent_id = ? OR assigned_agent_id IS NULL LIMIT 5");
$stmt->execute([$agentId]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Results found: " . count($results) . "\n";
foreach($results as $row) {
  echo "ID: " . $row['id'] . ", assigned_agent_id: " . ($row['assigned_agent_id'] ?? 'NULL') . "\n";
}

// Now test with just NULL
echo "\n--- Testing with just NULL ---\n";
$stmt = $pdo->prepare("SELECT * FROM pms_inquiries WHERE assigned_agent_id IS NULL LIMIT 5");
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Results found: " . count($results) . "\n";

// Test with agent ID only
echo "\n--- Testing with agent ID only ---\n";
$stmt = $pdo->prepare("SELECT * FROM pms_inquiries WHERE assigned_agent_id = ? LIMIT 5");
$stmt->execute([$agentId]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Results found: " . count($results) . "\n";

