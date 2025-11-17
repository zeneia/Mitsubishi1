<?php
session_start();
include_once(dirname(__DIR__) . '/includes/init.php');
include_once(dirname(__DIR__) . '/pages/header_ex.php');

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

// Prepare profile image HTML
$profile_image_html = '';
if (!empty($user['ProfileImage'])) {
    $imageData = base64_encode($user['ProfileImage']);
    $profile_image_html = '<img src="data:image/jpeg;base64,' . $imageData . '" alt="User Avatar" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">';
} else {
    $profile_image_html = strtoupper(substr($displayName, 0, 1));
}

// Fetch customer's PMS inquiries
try {
    $stmt_inquiries = $pdo->prepare("
        SELECT 
            pi.id as inquiry_id,
            pi.pms_id,
            pi.status,
            pi.assigned_agent_id,
            pi.created_at,
            pi.updated_at,
            cpr.plate_number,
            cpr.model,
            cpr.pms_info,
            cpr.pms_date,
            cpr.customer_needs,
            cpr.current_odometer,
            (SELECT COUNT(*) FROM pms_messages WHERE inquiry_id = pi.id AND is_read = 0 AND sender_type = 'Agent') as unread_messages,
            (SELECT COUNT(*) FROM pms_messages WHERE inquiry_id = pi.id) as total_messages,
            CONCAT(COALESCE(a.FirstName, ''), ' ', COALESCE(a.LastName, '')) as agent_name
        FROM pms_inquiries pi
        LEFT JOIN car_pms_records cpr ON pi.pms_id = cpr.pms_id
        LEFT JOIN accounts a ON pi.assigned_agent_id = a.Id
        WHERE pi.customer_id = ?
        ORDER BY pi.created_at DESC
    ");
    $stmt_inquiries->execute([$_SESSION['user_id']]);
    $inquiries = $stmt_inquiries->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $inquiries = [];
    error_log("Database error: " . $e->getMessage());
}

$success_message = isset($_GET['success']) ? "Your PMS inquiry has been submitted successfully!" : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My PMS Inquiries - Mitsubishi Motors</title>
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
        .container { max-width: 1000px; margin: 0 auto; padding: 30px 20px; }
        .back-btn { display: inline-block; margin-bottom: 20px; background: #808080; color: #fff; padding: 8px 16px; border-radius: 8px; text-decoration: none; font-weight: 500; }
        .back-btn:hover { background: #E60012; }
        .page-title { font-size: 2rem; font-weight: 800; color: #333; margin-bottom: 10px; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .inquiry-card { background: #fff; border-radius: 12px; padding: 20px; margin-bottom: 20px; border-left: 4px solid #E60012; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .inquiry-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px; }
        .inquiry-title { font-size: 1.1rem; font-weight: 600; color: #333; }
        .inquiry-status { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; }
        .status-open { background: #fff3cd; color: #856404; }
        .status-in-progress { background: #cfe2ff; color: #084298; }
        .status-resolved { background: #d1e7dd; color: #0f5132; }
        .inquiry-details { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-bottom: 15px; font-size: 0.9rem; }
        .detail-item { display: flex; flex-direction: column; }
        .detail-label { color: #666; font-weight: 500; margin-bottom: 3px; }
        .detail-value { color: #333; }
        .inquiry-actions { display: flex; gap: 10px; }
        .btn { padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; text-decoration: none; display: inline-block; }
        .btn-primary { background: #E60012; color: #fff; }
        .btn-primary:hover { background: #b80000; }
        .no-inquiries { text-align: center; padding: 60px 20px; }
        .no-inquiries i { font-size: 3rem; color: #ccc; margin-bottom: 20px; }
        .no-inquiries h3 { color: #666; margin-bottom: 10px; }
        .no-inquiries p { color: #999; margin-bottom: 20px; }
        .new-inquiry-btn { background: #E60012; color: #fff; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600; display: inline-block; }
        .new-inquiry-btn:hover { background: #b80000; }
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

    <div class="container">
        <a href="customer.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        
        <h1 class="page-title"><i class="fas fa-wrench"></i> My PMS Inquiries</h1>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if (empty($inquiries)): ?>
            <div class="no-inquiries">
                <i class="fas fa-inbox"></i>
                <h3>No PMS Inquiries Yet</h3>
                <p>You haven't submitted any PMS inquiries yet.</p>
                <a href="pms_record.php" class="new-inquiry-btn">
                    <i class="fas fa-plus"></i> Submit New PMS Inquiry
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($inquiries as $inquiry): ?>
                <div class="inquiry-card">
                    <div class="inquiry-header">
                        <div>
                            <div class="inquiry-title">
                                <?php echo htmlspecialchars($inquiry['model'] . ' - ' . $inquiry['pms_info']); ?>
                            </div>
                            <small style="color: #999;">Plate: <?php echo htmlspecialchars($inquiry['plate_number']); ?></small>
                        </div>
                        <span class="inquiry-status status-<?php echo strtolower(str_replace(' ', '-', $inquiry['status'])); ?>">
                            <?php echo htmlspecialchars($inquiry['status']); ?>
                        </span>
                    </div>
                    
                    <div class="inquiry-details">
                        <div class="detail-item">
                            <span class="detail-label">PMS Date</span>
                            <span class="detail-value"><?php echo date('M d, Y', strtotime($inquiry['pms_date'])); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Odometer</span>
                            <span class="detail-value"><?php echo number_format($inquiry['current_odometer']); ?> km</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Assigned Agent</span>
                            <span class="detail-value"><?php echo $inquiry['agent_name'] ?: 'Not assigned'; ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Messages</span>
                            <span class="detail-value"><?php echo $inquiry['total_messages']; ?> total <?php if ($inquiry['unread_messages'] > 0): ?><span style="color: #E60012; font-weight: 600;">(<?php echo $inquiry['unread_messages']; ?> unread)</span><?php endif; ?></span>
                        </div>
                    </div>

                    <div class="inquiry-actions">
                        <a href="pms_inquiry_chat.php?inquiry_id=<?php echo $inquiry['inquiry_id']; ?>" class="btn btn-primary">
                            <i class="fas fa-comments"></i> View Messages
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>

