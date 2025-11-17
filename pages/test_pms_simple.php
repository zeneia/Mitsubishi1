<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include_once(dirname(__DIR__) . '/includes/init.php');

// Check if user is logged in and is a customer
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Customer') {
    header("Location: login.php");
    exit;
}

$pdo = $GLOBALS['pdo'] ?? null;
if (!$pdo) {
    die("Database connection not available.");
}

// Fetch user details
$stmt = $pdo->prepare("SELECT * FROM accounts WHERE Id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$displayName = !empty($user['FirstName']) ? $user['FirstName'] : $user['Username'];

?>
<!DOCTYPE html>
<html>
<head>
    <title>Test PMS Page</title>
</head>
<body>
    <h1>Test PMS Page</h1>
    <p>User: <?php echo htmlspecialchars($displayName); ?></p>
    <p>User ID: <?php echo $_SESSION['user_id']; ?></p>
    <p>This is a simple test page to verify the PMS system is working.</p>
    <a href="customer.php">Back to Dashboard</a>
</body>
</html>

