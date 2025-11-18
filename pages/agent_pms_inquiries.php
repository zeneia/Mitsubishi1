<?php
session_start();
include_once(dirname(__DIR__) . '/includes/init.php');

// Check if user is logged in and is a sales agent
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'SalesAgent') {
    header("Location: login.php");
    exit;
}

$pdo = $GLOBALS['pdo'] ?? null;
if (!$pdo) {
    die("Database connection not available.");
}

// Get sales agent ID
$agent_id = $_SESSION['user_id'];

// Fetch agent details
$stmt = $pdo->prepare("SELECT * FROM accounts WHERE Id = ?");
$stmt->execute([$agent_id]);
$agent = $stmt->fetch(PDO::FETCH_ASSOC);
$displayName = !empty($agent['FirstName']) ? $agent['FirstName'] : $agent['Username'];

// Prepare profile image HTML
$profile_image_html = '';
if (!empty($agent['ProfileImage'])) {
    $imageData = base64_encode($agent['ProfileImage']);
    $profile_image_html = '<img src="data:image/jpeg;base64,' . $imageData . '" alt="User Avatar" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">';
} else {
    $profile_image_html = strtoupper(substr($displayName, 0, 1));
}

// Fetch ALL PMS inquiries (both assigned and unassigned)
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
} catch (PDOException $e) {
    $inquiries = [];
    error_log("Database error fetching PMS inquiries: " . $e->getMessage());
}

// Handle inquiry assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        if ($_POST['action'] === 'assign') {
            $inquiry_id = (int)$_POST['inquiry_id'];
            
            $stmt = $pdo->prepare("
                UPDATE pms_inquiries 
                SET assigned_agent_id = ?, status = 'In Progress'
                WHERE id = ?
            ");
            $stmt->execute([$agent_id, $inquiry_id]);
            
            echo json_encode(['success' => true, 'message' => 'Inquiry assigned successfully']);
        } elseif ($_POST['action'] === 'update_status') {
            $inquiry_id = (int)$_POST['inquiry_id'];
            $status = $_POST['status'];
            
            $stmt = $pdo->prepare("
                UPDATE pms_inquiries 
                SET status = ?
                WHERE id = ?
            ");
            $stmt->execute([$status, $inquiry_id]);
            
            echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PMS Inquiries Management - Mitsubishi Motors</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', 'Segoe UI', sans-serif; }
        body { background: #f5f5f5; color: #333; min-height: 100vh; }
        .header { background: #000; padding: 20px 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255, 215, 0, 0.2); }
        .logo-section { display: flex; align-items: center; gap: 20px; }
        .logo { width: 60px; height: auto; }
        .brand-text { font-size: 1.4rem; font-weight: 700; color: #ffd700; }
        .user-section { display: flex; align-items: center; gap: 20px; }
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; background: #ffd700; display: flex; align-items: center; justify-content: center; font-weight: bold; color: #000; }
        .welcome-text { color: #fff; font-size: 1rem; }
        .logout-btn { background: #E60012; color: #fff; border: none; padding: 10px 20px; border-radius: 20px; cursor: pointer; font-weight: 600; }
        .container { max-width: 1200px; margin: 0 auto; padding: 30px 20px; }
        .page-title { font-size: 2rem; font-weight: 800; color: #333; margin-bottom: 30px; }
        .inquiry-card { background: #fff; border-radius: 12px; padding: 20px; margin-bottom: 20px; border-left: 4px solid #E60012; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .inquiry-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px; }
        .inquiry-title { font-size: 1.1rem; font-weight: 600; color: #333; }
        .inquiry-status { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; }
        .status-open { background: #fff3cd; color: #856404; }
        .status-in-progress { background: #cfe2ff; color: #084298; }
        .status-resolved { background: #d1e7dd; color: #0f5132; }
        .inquiry-details { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 15px; font-size: 0.9rem; }
        .detail-item { display: flex; flex-direction: column; }
        .detail-label { color: #666; font-weight: 500; margin-bottom: 3px; }
        .detail-value { color: #333; }
        .inquiry-actions { display: flex; gap: 10px; }
        .btn { padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; text-decoration: none; display: inline-block; }
        .btn-primary { background: #E60012; color: #fff; }
        .btn-primary:hover { background: #b80000; }
        .btn-secondary { background: #808080; color: #fff; }
        .btn-secondary:hover { background: #666; }
        .back-btn { display: inline-block; margin-bottom: 20px; background: #808080; color: #fff; padding: 8px 16px; border-radius: 8px; text-decoration: none; font-weight: 500; }
        .no-inquiries { text-align: center; padding: 60px 20px; }
        .no-inquiries i { font-size: 3rem; color: #ccc; margin-bottom: 20px; }
        .no-inquiries h3 { color: #666; margin-bottom: 10px; }
        .no-inquiries p { color: #999; }
        .badge { display: inline-block; background: #E60012; color: #fff; padding: 2px 8px; border-radius: 12px; font-size: 0.8rem; font-weight: 600; }
        @media (max-width: 768px) {
            .header { flex-direction: column; gap: 15px; }
            .user-section { flex-direction: column; width: 100%; }
            .inquiry-details { grid-template-columns: 1fr; }
            .inquiry-actions { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo-section">
            <img src="../includes/images/mitsubishi_logo.png" alt="Mitsubishi Logo" class="logo">
            <div class="brand-text">MITSUBISHI MOTORS</div>
        </div>
        <div class="user-section">
            <div class="user-avatar"><?php echo $profile_image_html; ?></div>
            <span class="welcome-text">Welcome, <?php echo htmlspecialchars($displayName); ?>!</span>
            <button class="logout-btn" onclick="window.location.href='logout.php'">
                <i class="fas fa-sign-out-alt"></i> Logout
            </button>
        </div>
    </div>

    <div style="max-width: 1200px; margin: 0 auto; padding: 20px 20px 0;">
        <a href="/pages/main/dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>

    <div class="container">
        <h1 class="page-title"><i class="fas fa-wrench"></i> PMS Inquiries Management</h1>

        <?php if (empty($inquiries)): ?>
            <div class="no-inquiries">
                <i class="fas fa-inbox"></i>
                <h3>No PMS Inquiries</h3>
                <p>There are no PMS inquiries to manage at this time.</p>
            </div>
        <?php else: ?>
            <?php foreach ($inquiries as $inquiry): ?>
                <?php
                // Handle NULL values
                $model = $inquiry['model'] ?? 'Unknown Model';
                $pms_info = $inquiry['pms_info'] ?? 'PMS Service';
                $plate_number = $inquiry['plate_number'] ?? 'N/A';
                $customer_name = trim(($inquiry['FirstName'] ?? '') . ' ' . ($inquiry['LastName'] ?? ''));
                if (empty($customer_name)) {
                    $customer_name = 'Unknown Customer';
                }
                $email = $inquiry['Email'] ?? 'N/A';
                ?>
                <div class="inquiry-card">
                    <div class="inquiry-header">
                        <div>
                            <div class="inquiry-title">
                                <?php echo htmlspecialchars($model . ' - ' . $pms_info); ?>
                            </div>
                            <small style="color: #999;">Plate: <?php echo htmlspecialchars($plate_number); ?></small>
                        </div>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <span class="inquiry-status status-<?php echo strtolower(str_replace(' ', '-', $inquiry['status'])); ?>">
                                <?php echo htmlspecialchars($inquiry['status']); ?>
                            </span>
                        </div>
                    </div>

                    <div class="inquiry-details">
                        <div class="detail-item">
                            <span class="detail-label">Customer</span>
                            <span class="detail-value"><?php echo htmlspecialchars($customer_name); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Contact</span>
                            <span class="detail-value"><?php echo htmlspecialchars($email); ?></span>
                        </div>
                    </div>

                    <div class="inquiry-actions">
                        <a href="agent_pms_chat.php?inquiry_id=<?php echo $inquiry['inquiry_id']; ?>" class="btn btn-primary">
                            <i class="fas fa-comments"></i> View Messages
                        </a>
                        <?php if (!$inquiry['assigned_agent_id']): ?>
                            <button class="btn btn-secondary" onclick="assignInquiry(<?php echo $inquiry['inquiry_id']; ?>)">
                                <i class="fas fa-user-check"></i> Assign to Me
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script>
        function assignInquiry(inquiryId) {
            fetch('agent_pms_inquiries.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=assign&inquiry_id=' + inquiryId
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.error);
                }
            });
        }
    </script>
</body>
</html>

