<?php
session_start();
include_once(dirname(__DIR__) . '/includes/database/db_conn.php');

// Allow both Customer and SalesAgent access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['Customer', 'SalesAgent'])) {
    if (isset($_GET['partial'])) {
        http_response_code(401);
        exit('Unauthorized');
    }
    header("Location: login.php");
    exit;
}

// Handle partial content requests
if (isset($_GET['partial'])) {
    $partial = $_GET['partial'];
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['user_role'];
    include_once(dirname(__DIR__) . '/includes/api/notification_api.php');
    
    if ($partial === 'unread') {
        $notifications = getNotifications($user_id, $user_role, 'unread');
        if (empty($notifications)) {
            echo '<div class="no-notifications">No unread notifications</div>';
        } else {
            echo '<div class="notifications-list">';
            foreach ($notifications as $notif) {
                displayNotificationItem($notif, true);
            }
            echo '</div>';
        }
    }
    exit;
}

// Fetch user details with better query to get more information
$stmt = $connect->prepare("SELECT a.*, ci.firstname, ci.lastname 
                          FROM accounts a 
                          LEFT JOIN customer_information ci ON a.Id = ci.account_id 
                          WHERE a.Id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Determine display name with better fallback logic
$displayName = '';
if (!empty($user['firstname']) && !empty($user['lastname'])) {
    $displayName = $user['firstname'] . ' ' . $user['lastname'];
} elseif (!empty($user['FirstName']) && !empty($user['LastName'])) {
    $displayName = $user['FirstName'] . ' ' . $user['LastName'];
} elseif (!empty($user['FirstName'])) {
    $displayName = $user['FirstName'];
} elseif (!empty($user['Username'])) {
    $displayName = $user['Username'];
} else {
    $displayName = 'User';
}

// Prepare profile image HTML
$profile_image_html = '';
if (!empty($user['ProfileImage'])) {
    $imageData = base64_encode($user['ProfileImage']);
    $imageMimeType = 'image/jpeg';
    $profile_image_html = '<img src="data:' . $imageMimeType . ';base64,' . $imageData . '" alt="User Avatar" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">';
} else {
    // Show initial if no profile image
    $profile_image_html = strtoupper(substr($displayName, 0, 1));
}

// Determine dashboard URL based on role
$dashboardUrl = ($_SESSION['user_role'] === 'Customer') ? 'customer.php' : 'sales.php';
$dashboardLabel = ($_SESSION['user_role'] === 'Customer') ? 'Customer Dashboard' : 'Sales Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Mitsubishi Motors</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Common styles */
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
            font-family: 'Inter', 'Segoe UI', sans-serif; }

        body { 
        background: #f5f5f5;
        color: #333333;
        min-height: 100vh;
        }

        .header { background: rgba(0, 0, 0, 0.4); 
        background: #ffffff;
        padding: 20px 30px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 3px solid #E60012;
        position: relative;
        z-index: 10; }

        .logo-section { display: flex; align-items: center; gap: 20px; }

        .logo { width: 60px; }

        .brand-text { font-size: 1.4rem; 
            font-weight: 700; background: #E60012; 
            -webkit-background-clip: text; 
            -webkit-text-fill-color: transparent; }

        .user-section { display: flex; align-items: center; gap: 20px; }

        .user-avatar { width: 40px; 
            height: 40px; 
            border-radius: 50%; 
            background: #E60012; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-weight: bold; 
            color: #ffffff; 
            font-size: 1.2rem; }

        .welcome-text { font-size: 1.1rem; }
        .logout-btn { background: linear-gradient(45deg, #d60000, #b30000); 
            color: white; 
            border: none; 
            padding: 12px 24px; 
            border-radius: 25px; 
            cursor: pointer; 
            font-size: 0.9rem; 
            font-weight: 600; 
            transition: all 0.3s ease; }

        .container { 
            max-width: 900px; 
            margin: 0 auto; 
            padding: 50px 30px 60px; 
            height: auto !important;
            min-height: 100vh;
        }
        .back-btn { 
            display: inline-block; 
            margin-bottom: 30px; 
            background: #E60012; 
            color: #ffffff; 
            padding: 10px 20px; 
            border-radius: 10px; 
            text-decoration: none; 
            font-weight: 600; 
            transition: all 0.3s ease; }

        .back-btn:hover { background: #ffd700; color: #1a1a1a; }
        .page-title { text-align: center; 
            font-size: 2.8rem; 
            color: #E60012; 
            margin-bottom: 40px; }

        /* User Info Debug (remove in production) */
        .debug-info {
            background: rgba(255, 255, 0, 0.1);
            border: 1px solid rgba(255, 255, 0, 0.3);
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-size: 0.8rem;
            display: block; /* Temporarily enabled to show debug info */
        }

        /* Custom scrollbar styling */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.1);
        }
        
        ::-webkit-scrollbar-thumb {
            background: rgba(255, 215, 0, 0.6);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 215, 0, 0.8);
        }

        /* Notifications List */
        .notifications-container { margin-top: 20px; }

        .notification-tabs { display: flex; 
            border-bottom: 1px solid rgba(255, 255, 255, 0.61); 
            margin-bottom: 20px; }

        .tab-button { 
            padding: 10px 20px; 
            background: none; 
            border: none; 
            color: #000000; 
            cursor: pointer; 
            border-bottom: 2px solid transparent;
            opacity: 0.7;
            transition: all 0.3s ease;
        }
        .tab-button.active { 
            opacity: 1; 
            border-bottom-color: #E60012;
            color: #E60012;
        }
        .tab-content { display: none; }

        .tab-content.active { display: block; }

        .notifications-list { display: flex; flex-direction: column; gap: 10px; }

        .notification-item { 
            display: flex; 
            flex-direction: column;
            background: rgba(194, 194, 194, 0.48); 
            border-radius: 10px; 
            border-left: 5px solid #E60012; 
            overflow: hidden;
            transition: all 0.3s ease;
            color: #333
        }
        .notification-item.read { opacity: 0.7; 
            color: #666;
        }

        .notification-item.unread { border-left-width: 8px; 
            font-weight: 600;
            color: #222;
        }
        .notification-header { 
            display: flex; 
            align-items: center; 
            gap: 20px; 
            padding: 15px 20px;
            cursor: pointer;
        }
        .notification-icon { font-size: 1.8rem; 
            color: #E60012; min-width: 30px; text-align: center; }

        .notification-item.approved .notification-icon { color: #28a745; }
        .notification-item.reminder .notification-icon { color: #ffc107; }
        .notification-item.loan-approval .notification-icon { color: #6c5ce7; }
        .notification-content { flex: 1; }
        .notification-content h3 { 
            margin: 0 0 5px; 
            font-size: 1.1rem; 
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .notification-meta { 
            display: flex; 
            justify-content: space-between;
            align-items: center;
            margin-top: 5px;
        }
        .notification-time { font-size: 0.9rem; opacity: 0.7; }
        .notification-details { 
            padding: 0 20px 15px 70px; 
            display: none;
            border-top: 1px solid rgba(255,255,255,0.1);
            margin-top: 10px;
            padding-top: 15px;
        }
        .notification-item.expanded .notification-details {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        .mark-read-btn {
            background: none;
            border: 1px solid rgba(170, 35, 35, 0.69);
            color: #E60012;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .mark-read-btn:hover {
            background: rgba(170, 35, 35, 0.81);
        }
        .no-notifications {
            text-align: center;
            padding: 40px 20px;
            opacity: 0.7;
            font-style: italic;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

            /* Tablet */
    @media (max-width: 1024px) {
        .container {
            max-width: 95%;
        }
    }

    /* Phones */
    @media (max-width: 768px) {
        .header {
            flex-direction: column;
            gap: 15px;
            padding: 15px 20px;
        }

        .user-section {
            flex-direction: column;
            gap: 12px;
            text-align: center;
            width: 100%;
        }

        .container {
            padding: 20px 15px;
        }

        .form-container {
            padding: 20px;
        }

        .form-grid {
            grid-template-columns: 1fr;
        }
    }

    /* Large Desktops */
    @media (min-width: 1200px) {
        .container {
            max-width: 1100px;
        }

        .inquiry-card {
            max-width: 100%;
        }

        .form-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }


    </style>
</head>
<body>
    <header class="header">
        <div class="logo-section">
            <img src="../includes/images/mitsubishi_logo.png" alt="Mitsubishi Logo" class="logo">
            <div class="brand-text">MITSUBISHI MOTORS</div>
        </div>
        <div class="user-section">
            <div class="user-avatar"><?php echo $profile_image_html; ?></div>
            <span class="welcome-text">Welcome, <?php echo htmlspecialchars($displayName); ?>!</span>
            <button class="logout-btn" onclick="window.location.href='logout.php'"><i class="fas fa-sign-out-alt"></i> Logout</button>
        </div>
    </header>

    <div class="container">
        <!-- Debug Info (remove in production) -->
        <?php /*
        <div class="debug-info">
            <strong>Debug Info:</strong><br>
            User ID: <?php echo htmlspecialchars($_SESSION['user_id'] ?? 'Not set'); ?><br>
            User Role: <?php echo htmlspecialchars($_SESSION['user_role'] ?? 'Not set'); ?><br>
            Display Name: <?php echo htmlspecialchars($displayName); ?><br>
            Username: <?php echo htmlspecialchars($user['Username'] ?? 'Not set'); ?><br>
            FirstName: <?php echo htmlspecialchars($user['FirstName'] ?? 'Not set'); ?><br>
            LastName: <?php echo htmlspecialchars($user['LastName'] ?? 'Not set'); ?><br>
            firstname: <?php echo htmlspecialchars($user['firstname'] ?? 'Not set'); ?><br>
            lastname: <?php echo htmlspecialchars($user['lastname'] ?? 'Not set'); ?>
        </div>
        */ ?>
        
        <a href="<?php echo htmlspecialchars($dashboardUrl); ?>" class="back-btn"><i class="fas fa-arrow-left"></i> Back </a>
        <h1 class="page-title">Notifications</h1>
        <div class="notifications-container">
            <div class="notification-tabs">
                <button class="tab-button active" data-tab="unread">Unread</button>
                <button class="tab-button" data-tab="all">All Notifications</button>
            </div>
            
            <div class="tab-content active" id="unread-tab">
                <?php
                include_once(dirname(__DIR__) . '/includes/api/notification_api.php');
                $user_id = $_SESSION['user_id'];
                $user_role = $_SESSION['user_role'];
                $unreadNotifications = getNotifications($user_id, $user_role, 'unread');
                
                if (empty($unreadNotifications)) {
                    echo '<div class="no-notifications">No unread notifications</div>';
                } else {
                    echo '<div class="notifications-list">';
                    foreach ($unreadNotifications as $notif) {
                        displayNotificationItem($notif, true);
                    }
                    echo '</div>';
                }
                ?>
            </div>
            
            <div class="tab-content" id="all-tab">
                <?php
                $allNotifications = getNotifications($user_id, $user_role, 'all');
                
                if (empty($allNotifications)) {
                    echo '<div class="no-notifications">No notifications found</div>';
                } else {
                    echo '<div class="notifications-list">';
                    foreach ($allNotifications as $notif) {
                        displayNotificationItem($notif, $notif['is_read'] == 0);
                    }
                    echo '</div>';
                }
                ?>
            </div>
        </div>
        
        <?php
        function displayNotificationItem($notif, $isUnread) {
            $typeClass = '';
            $icon = 'bell';
            
            if ($notif['type'] === 'order' || $notif['type'] === 'approved') {
                $typeClass = 'approved';
                $icon = 'check-circle';
            } elseif ($notif['type'] === 'loan_approval') {
                $typeClass = 'loan-approval';
                $icon = 'file-invoice-dollar';
            } elseif ($notif['type'] === 'reminder' || $notif['type'] === 'payment') {
                $typeClass = 'reminder';
                $icon = 'clock';
            } elseif ($notif['type'] === 'account') {
                $icon = 'user-check';
            }
            
            $timeAgo = getTimeAgo($notif['created_at']);
            $readClass = $isUnread ? 'unread' : 'read';
            
            echo '<div class="notification-item ' . $typeClass . ' ' . $readClass . '" data-id="' . $notif['id'] . '">';
            echo '  <div class="notification-header">';
            echo '    <i class="fas fa-' . $icon . ' notification-icon"></i>';
            echo '    <div class="notification-content">';
            echo '      <h3>' . htmlspecialchars($notif['title']) . 
                 '        <span class="notification-time">' . htmlspecialchars($timeAgo) . '</span>';
            echo '      </h3>';
            echo '      <div class="notification-meta">';
            echo '        <p>' . htmlspecialchars($notif['message']) . '</p>';
            if ($isUnread) {
                echo '    <button class="mark-read-btn" data-id="' . $notif['id'] . '">Mark as Read</button>';
            }
            echo '      </div>';
            echo '    </div>';
            echo '  </div>';
            echo '  <div class="notification-details">';
            echo '    <p><strong>Type:</strong> ' . ucfirst(htmlspecialchars($notif['type'])) . '</p>';
            echo '    <p><strong>Date:</strong> ' . date('M d, Y h:i A', strtotime($notif['created_at'])) . '</p>';
            if (!empty($notif['related_id'])) {
                echo '<p><strong>Related ID:</strong> ' . htmlspecialchars($notif['related_id']) . '</p>';
            }
            echo '  </div>';
            echo '</div>';
        }
        
        function getTimeAgo($datetime) {
            $time = strtotime($datetime);
            $timeDiff = time() - $time;
            
            if ($timeDiff < 60) {
                return $timeDiff . ' seconds ago';
            } elseif ($timeDiff < 3600) {
                $mins = floor($timeDiff / 60);
                return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
            } elseif ($timeDiff < 86400) {
                $hours = floor($timeDiff / 3600);
                return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
            } elseif ($timeDiff < 604800) {
                $days = floor($timeDiff / 86400);
                return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
            } else {
                return date('M d, Y', $time);
            }
        }
        ?>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    $(document).ready(function() {
        // Tab switching
        $('.tab-button').on('click', function() {
            const tabId = $(this).data('tab');
            
            // Update active tab
            $('.tab-button').removeClass('active');
            $(this).addClass('active');
            
            // Show corresponding content
            $('.tab-content').removeClass('active');
            $('#' + tabId + '-tab').addClass('active');
        });
        
        // Toggle notification details
        $(document).on('click', '.notification-header', function() {
            const $notification = $(this).closest('.notification-item');
            $notification.toggleClass('expanded');
            
            // Mark as read when expanded
            if ($notification.hasClass('expanded') && $notification.hasClass('unread')) {
                markAsRead($notification.data('id'), $notification);
            }
        });
        
        // Mark as read button
        $(document).on('click', '.mark-read-btn', function(e) {
            e.stopPropagation();
            const $btn = $(this);
            const $notification = $btn.closest('.notification-item');
            markAsRead($btn.data('id'), $notification);
        });
        
        function markAsRead(notificationId, $notification) {
            $.ajax({
                url: '../includes/api/notification_action.php',
                type: 'POST',
                data: {
                    action: 'mark_read',
                    notification_id: notificationId
                },
                success: function(response) {
                    try {
                        const result = typeof response === 'string' ? JSON.parse(response) : response;
                        if (result.success) {
                            // If on unread tab, remove the notification from the list
                            if ($('#unread-tab').hasClass('active')) {
                                $notification.fadeOut(300, function() {
                                    $(this).remove();
                                    // If no more unread notifications, show message
                                    if ($('#unread-tab .notifications-list').children().length === 0) {
                                        $('#unread-tab .notifications-list').html('<div class="no-notifications">No unread notifications</div>');
                                    }
                                });
                            } else {
                                // On all tab, just update the UI
                                $notification.removeClass('unread').addClass('read');
                                $notification.find('.mark-read-btn').remove();
                            }
                            
                            // Update unread count in the tab if needed
                            updateUnreadCount();
                            
                            // Refresh the unread tab content
                            loadUnreadNotifications();
                        }
                    } catch (e) {
                        console.error('Error processing response:', e);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error marking notification as read:', error);
                }
            });
        }
        
        function loadUnreadNotifications() {
            $.get('?partial=unread', function(html) {
                $('#unread-tab').html(html);
                // If we're currently on the unread tab, update the content
                if ($('#unread-tab').hasClass('active')) {
                    $('.notifications-list').fadeOut(200, function() {
                        $(this).replaceWith($('#unread-tab .notifications-list'));
                        $('.notifications-list').fadeIn(200);
                    });
                }
            });
        }
        
        function updateUnreadCount() {
            // This would be called when notifications are marked as read to update the UI
            // You can implement this if you want to show unread count in the tab
        }
    });
    </script>
</body>
</html>
