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

$inquiry_id = isset($_GET['inquiry_id']) ? (int)$_GET['inquiry_id'] : 0;

// Fetch inquiry details
try {
    $stmt = $pdo->prepare("
        SELECT pi.*, cpr.plate_number, cpr.model, cpr.pms_info, cpr.customer_needs,
               CONCAT(COALESCE(a.FirstName, ''), ' ', COALESCE(a.LastName, '')) as agent_name
        FROM pms_inquiries pi
        LEFT JOIN car_pms_records cpr ON pi.pms_id = cpr.pms_id
        LEFT JOIN accounts a ON pi.assigned_agent_id = a.Id
        WHERE pi.id = ? AND pi.customer_id = ?
    ");
    $stmt->execute([$inquiry_id, $_SESSION['user_id']]);
    $inquiry = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$inquiry) {
        header("Location: my_pms_inquiries.php");
        exit;
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    header("Location: my_pms_inquiries.php");
    exit;
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

// Handle message submission
$error_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message_text'])) {
    try {
        $message_text = trim($_POST['message_text']);
        if (empty($message_text)) {
            throw new Exception("Message cannot be empty.");
        }
        
        $stmt_msg = $pdo->prepare("
            INSERT INTO pms_messages (inquiry_id, sender_id, sender_type, message_text, message_type)
            VALUES (?, ?, 'Customer', ?, 'text')
        ");
        $stmt_msg->execute([$inquiry_id, $_SESSION['user_id'], $message_text]);
        
        // Redirect to refresh messages
        header("Location: pms_inquiry_chat.php?inquiry_id=" . $inquiry_id);
        exit;
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Fetch messages
try {
    $stmt_msgs = $pdo->prepare("
        SELECT m.*, 
               CASE WHEN m.sender_type = 'Agent' THEN CONCAT(COALESCE(a.FirstName, ''), ' ', COALESCE(a.LastName, ''))
                    ELSE ? END as sender_name
        FROM pms_messages m
        LEFT JOIN accounts a ON m.sender_id = a.Id AND m.sender_type = 'Agent'
        WHERE m.inquiry_id = ?
        ORDER BY m.created_at ASC
    ");
    $stmt_msgs->execute([$displayName, $inquiry_id]);
    $messages = $stmt_msgs->fetchAll(PDO::FETCH_ASSOC);
    
    // Mark agent messages as read
    $stmt_read = $pdo->prepare("
        UPDATE pms_messages SET is_read = 1 
        WHERE inquiry_id = ? AND sender_type = 'Agent' AND is_read = 0
    ");
    $stmt_read->execute([$inquiry_id]);
} catch (PDOException $e) {
    $messages = [];
    error_log("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PMS Inquiry Chat - Mitsubishi Motors</title>
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
        .container { max-width: 900px; margin: 0 auto; padding: 20px; display: flex; gap: 20px; height: calc(100vh - 100px); }
        .sidebar { background: #fff; border-radius: 12px; padding: 20px; width: 300px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); overflow-y: auto; }
        .sidebar-title { font-size: 1.1rem; font-weight: 600; margin-bottom: 15px; color: #333; }
        .inquiry-info { background: #f9f9f9; padding: 15px; border-radius: 8px; margin-bottom: 15px; font-size: 0.9rem; }
        .info-label { color: #666; font-weight: 500; margin-bottom: 3px; }
        .info-value { color: #333; margin-bottom: 10px; }
        .chat-container { flex: 1; background: #fff; border-radius: 12px; display: flex; flex-direction: column; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .chat-header { padding: 20px; border-bottom: 1px solid #eee; }
        .chat-title { font-size: 1.2rem; font-weight: 600; color: #333; }
        .chat-subtitle { font-size: 0.9rem; color: #666; margin-top: 5px; }
        .messages-area { flex: 1; padding: 20px; overflow-y: auto; display: flex; flex-direction: column; gap: 15px; }
        .message { display: flex; gap: 10px; margin-bottom: 10px; }
        .message.customer { justify-content: flex-end; }
        .message-bubble { max-width: 70%; padding: 12px 16px; border-radius: 12px; word-wrap: break-word; }
        .message.customer .message-bubble { background: #E60012; color: #fff; }
        .message.agent .message-bubble { background: #f0f0f0; color: #333; }
        .message-time { font-size: 0.8rem; color: #999; margin-top: 3px; }
        .input-area { padding: 20px; border-top: 1px solid #eee; display: flex; gap: 10px; }
        .input-area textarea { flex: 1; padding: 12px; border: 1px solid #ddd; border-radius: 8px; resize: none; font-family: inherit; }
        .input-area button { background: #E60012; color: #fff; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .input-area button:hover { background: #b80000; }
        .back-btn { display: inline-block; margin-bottom: 20px; background: #808080; color: #fff; padding: 8px 16px; border-radius: 8px; text-decoration: none; font-weight: 500; }
        @media (max-width: 1024px) {
            .container { flex-direction: column; height: auto; }
            .sidebar { width: 100%; }
            .message-bubble { max-width: 100%; }
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

    <div style="max-width: 1200px; margin: 0 auto; padding: 20px;">
        <a href="my_pms_inquiries.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Inquiries</a>
    </div>

    <div class="container">
        <div class="sidebar">
            <div class="sidebar-title"><i class="fas fa-info-circle"></i> Inquiry Details</div>
            <div class="inquiry-info">
                <div class="info-label">Vehicle</div>
                <div class="info-value"><?php echo htmlspecialchars($inquiry['model']); ?></div>
                
                <div class="info-label">Plate Number</div>
                <div class="info-value"><?php echo htmlspecialchars($inquiry['plate_number']); ?></div>
                
                <div class="info-label">PMS Type</div>
                <div class="info-value"><?php echo htmlspecialchars($inquiry['pms_info']); ?></div>
                
                <div class="info-label">Status</div>
                <div class="info-value" style="color: #E60012; font-weight: 600;"><?php echo htmlspecialchars($inquiry['status']); ?></div>
                
                <div class="info-label">Assigned Agent</div>
                <div class="info-value"><?php echo $inquiry['agent_name'] ?: 'Not assigned'; ?></div>
            </div>
            
            <div class="sidebar-title" style="margin-top: 20px;"><i class="fas fa-comment-dots"></i> Your Needs</div>
            <div class="inquiry-info">
                <div class="info-value"><?php echo nl2br(htmlspecialchars($inquiry['customer_needs'])); ?></div>
            </div>
        </div>

        <div class="chat-container">
            <div class="chat-header">
                <div class="chat-title">PMS Inquiry Chat</div>
                <div class="chat-subtitle">Communicate with our service team about your PMS request</div>
            </div>

            <div class="messages-area" id="messagesArea">
                <?php foreach ($messages as $msg): ?>
                    <div class="message <?php echo $msg['sender_type'] === 'Customer' ? 'customer' : 'agent'; ?>">
                        <div>
                            <div class="message-bubble">
                                <?php echo nl2br(htmlspecialchars($msg['message_text'])); ?>
                            </div>
                            <div class="message-time">
                                <?php echo date('M d, Y g:i A', strtotime($msg['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="input-area">
                <form method="POST" style="display: flex; gap: 10px; width: 100%;">
                    <textarea name="message_text" placeholder="Type your message here..." rows="3" required></textarea>
                    <button type="submit"><i class="fas fa-paper-plane"></i> Send</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Auto-scroll to bottom of messages
        const messagesArea = document.getElementById('messagesArea');
        if (messagesArea) {
            messagesArea.scrollTop = messagesArea.scrollHeight;
        }
    </script>
</body>
</html>

