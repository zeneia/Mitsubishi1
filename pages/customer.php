<?php
session_start();
include_once(dirname(__DIR__) . '/includes/database/db_conn.php');
// Check if user is logged in and is a customer
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Customer') {
    header("Location: login.php");
    exit;
}

// Check if customer information is filled out, if not, redirect to verification
$stmt_check_info = $connect->prepare("SELECT cusID, Status FROM customer_information WHERE account_id = ?");
$stmt_check_info->execute([$_SESSION['user_id']]);
$customer_info = $stmt_check_info->fetch(PDO::FETCH_ASSOC);

if (!$customer_info) {
    header("Location: verification.php");
    exit;
}

// Fetch user details
$stmt = $connect->prepare("SELECT * FROM accounts WHERE Id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$displayName = !empty($user['FirstName']) ? $user['FirstName'] : $user['Username'];

// Get unread inquiries count (for customer badge)
$unread_count = 0;
try {
    $stmt = $connect->prepare("SELECT COUNT(*) FROM inquiries WHERE AccountId = ? AND (is_read = 0 OR is_read IS NULL)");
    $stmt->execute([$_SESSION['user_id']]);
    $unread_count = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Database error (unread inquiries): " . $e->getMessage());
    $unread_count = 0;
}

// Fetch latest approved test drive with a gate pass number for this customer
$latest_gatepass = null;
try {
    $stmt_gp = $connect->prepare(
        "SELECT tdr.id, tdr.gate_pass_number, tdr.selected_date, tdr.selected_time_slot, tdr.status, v.model_name, v.variant
         FROM test_drive_requests tdr
         LEFT JOIN vehicles v ON v.id = tdr.vehicle_id
         WHERE tdr.account_id = ?
           AND tdr.status = 'Approved'
           AND tdr.gate_pass_number IS NOT NULL AND tdr.gate_pass_number <> ''
         ORDER BY tdr.approved_at DESC, tdr.requested_at DESC
         LIMIT 1"
    );
    $stmt_gp->execute([$_SESSION['user_id']]);
    $gatepass_result = $stmt_gp->fetch(PDO::FETCH_ASSOC);
    
    // Check if the gatepass is not expired (scheduled date is today or in the future)
    if ($gatepass_result && !empty($gatepass_result['selected_date'])) {
        $scheduled_date = $gatepass_result['selected_date'];
        $today = date('Y-m-d');
        
        // Only show gatepass if the scheduled date is today or in the future
        if ($scheduled_date >= $today) {
            $latest_gatepass = $gatepass_result;
        }
    }
} catch (PDOException $e) {
    error_log("Gatepass fetch error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - Mitsubishi Motors</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../css/customer-admin-styles.css" rel="stylesheet">
    <style>
        /* Override specific styles for dashboard cards while maintaining admin consistency */
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }

        .card {
            background: white;
            border-radius: 15px;
            padding: 0;
            box-shadow: var(--shadow-light);
            border: 2px solid transparent;
            transition: var(--transition);
            position: relative;
            overflow: hidden;

            flex: 1; /* Pushes the button to the bottom */
            padding: 20px;
            display: flex;
            flex-direction: column;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-medium);
            border-color: var(--primary-red);
        }

        .card-header {
            background: #808080;
            height: 60px;
            display: flex;
            align-items: center;
            padding: 0 20px;
            margin: 0;
            border-radius: 15px 15px 0 0;
        }

        .card-icon {
            font-size: 2rem;
            color: white;
            margin: 0;
        }

        .card-content {
            flex: 1; /* Pushes the button to the bottom */
            padding: 20px;
            display: flex;
            flex-direction: column;
        }

        .card h3 {
            color: var(--text-dark);
            margin-bottom: 20px;
            font-size: 1.5rem;
            font-weight: 700;
        }

        .card p {
            line-height: 1.8;
            margin-bottom: 25px;
            color: var(--text-light);
            font-weight: 400;
        }

        .card-btn {
            background: var(--primary-red);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            width: 100%;
            transition: var(--transition);
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: auto
        }

        .card-btn:hover {
            background: #CC0000;
            transform: translateY(-2px);
        }

        /* Header styling to match admin */
        .header {
            background: #000000;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 215, 0, 0.2);
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .logo {
            width: 60px;
            height: auto;
        }

        .brand-text {
            font-size: 1.4rem;
            font-weight: 700;
            background: linear-gradient(45deg, #ffd700, #ffed4e);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .user-section {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(45deg, #ffd700, #ffed4e);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #b80000;
            font-size: 1.2rem;
        }

        .welcome-text {
            color: #ffffff;
            font-size: 1rem;
            font-weight: 500;
        }

        .logout-btn {
            background: var(--primary-red);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            transition: var(--transition);
        }

        .logout-btn:hover {
            background: #CC0000;
            transform: translateY(-1px);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 30px;
        }

        .hero-section {
            text-align: center;
            margin-bottom: 50px;
        }

        .hero-section h1 {
            font-size: 2.5rem;
            margin-bottom: 20px;
            color: var(--text-dark);
            font-weight: 700;
        }

        .hero-section p {
            font-size: 1.1rem;
            color: var(--text-light);
            font-weight: 400;
        }

        /* Remove old background and particle animations */
        body {
            background: var(--primary-light);
            color: var(--text-dark);
        }

        .bg-particles {
            display: none;
        }

        /* Animation on page load */
        .card {
            opacity: 0;
            transform: translateY(30px);
            animation: slideUp 0.6s ease forwards;
        }

        .card:nth-child(1) { animation-delay: 0.1s; }
        .card:nth-child(2) { animation-delay: 0.2s; }
        .card:nth-child(3) { animation-delay: 0.3s; }
        .card:nth-child(4) { animation-delay: 0.4s; }
        .card:nth-child(5) { animation-delay: 0.5s; }
        .card:nth-child(6) { animation-delay: 0.6s; }

        @keyframes slideUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Badge styling */
        .badge {
            background: var(--primary-red);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            position: absolute;
            top: -5px;
            right: -5px;
        }

        /* Responsive design */

        
        @media (max-width: 575px) {
            .header {
                flex-direction: column;
                gap: 12px;
                padding: 15px 20px;
            }

            .user-section {
                flex-direction: column;
                gap: 10px;
                text-align: center;
                width: 100%;
            }

            .page-title{
                font-size: 2rem;
            
            }
            .container {
                padding: 20px 15px;
            }
            
            .dashboard-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .hero-section h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Remove bg-particles div -->
    
    <div class="header">
        <div class="logo-section">
            <img src="../includes/images/mitsubishi_logo.png" alt="Mitsubishi Logo" class="logo">
            <div class="brand-text">MITSUBISHI MOTORS</div>
        </div>
        <div class="user-section">
            <div class="user-avatar">
                <?php echo strtoupper(substr($displayName, 0, 1)); ?>
            </div>
            <span class="welcome-text">Welcome, <?php echo htmlspecialchars($displayName); ?>!</span>
            <button class="logout-btn" onclick="window.location.href='logout.php'">
                <i class="fas fa-sign-out-alt"></i> Logout
            </button>
        </div>
    </div>

    <div class="container">
        <div class="hero-section">
            <h1>Customer Dashboard</h1>
            <p>Explore our vehicles, services, and manage your account with excellence</p>
        </div>

        <div class="dashboard-grid">
            <?php if (!empty($latest_gatepass)): ?>
            <div class="card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-id-badge"></i>
                    </div>
                </div>
                <div class="card-content">
                    <h3>Test Drive Gatepass</h3>
                    <p>
                        <?php
                        $vehicleLabel = '';
                        if (!empty($latest_gatepass['model_name'])) {
                            $vehicleLabel = $latest_gatepass['model_name'] . (!empty($latest_gatepass['variant']) ? ' (' . $latest_gatepass['variant'] . ')' : '');
                            echo 'Vehicle: ' . htmlspecialchars($vehicleLabel) . '<br />';
                        }
                        $dateText = !empty($latest_gatepass['selected_date']) ? date('F j, Y', strtotime($latest_gatepass['selected_date'])) : 'Date not set';
                        $timeRaw = $latest_gatepass['selected_time_slot'] ?? '';
                        if (!empty($timeRaw)) {
                            if (strpos($timeRaw, '-') === false) {
                                $t = strtotime($timeRaw);
                                $timeText = $t ? date('g:i A', $t) : $timeRaw;
                            } else {
                                $timeText = $timeRaw;
                            }
                        } else {
                            $timeText = 'Time not set';
                        }
                        echo 'Scheduled for ' . htmlspecialchars($dateText) . ' at ' . htmlspecialchars($timeText);
                        ?>
                    </p>
                    <p><strong style="color: black;">Gatepass Number:</strong> <?php echo htmlspecialchars($latest_gatepass['gate_pass_number']); ?></p>
                    <div style="display:flex; gap:10px;">
                        <button class="card-btn" onclick="window.open('test_drive_pdf.php?request_id=<?php echo $latest_gatepass['id']; ?>','_blank')">
                            <i class="fas fa-print"></i> Print Gatepass
                        </button>
                        <button class="card-btn" style="background:linear-gradient(45deg,#ccc,#eee); color: black;" onclick="window.location.href='test_drive_success.php?request_id=<?php echo $latest_gatepass['id']; ?>'">
                            <i class="fas fa-eye"></i> View Details
                        </button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-car"></i>
                    </div>
                </div>
                <div class="card-content">
                    <h3>Car Menu</h3>
                    <p>Browse car categories and view available models like Xpander, Mirage, and Triton. Select your preferred vehicle for a quote, test drive, or inquiry.</p>
                    <button class="card-btn" onclick="window.location.href='car_menu.php'">
                        <i class="fas fa-search"></i> Explore Cars
                    </button>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-edit"></i>
                    </div>
                </div>
                <div class="card-content">
                    <h3>Submit Inquiry</h3>
                    <p>Have questions about a specific vehicle? Submit an inquiry and our sales team will get back to you with detailed information.</p>
                    <button class="card-btn" onclick="window.location.href='inquiry.php'">
                        <i class="fas fa-paper-plane"></i> Submit Inquiry
                    </button>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-headset"></i>
                    </div>
                </div>
                <div class="card-content">
                    <h3>Chat Support</h3>
                    <p>Ask questions about cars and talk directly with agents. If an agent is not available, a chatbot is there to assist you.</p>
                    <button class="card-btn" onclick="window.location.href='chat_support.php'">
                        <i class="fas fa-comments"></i> Open Chat
                    </button>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-question-circle"></i>
                    </div>
                </div>
                <div class="card-content">
                    <h3>My Inquiries
                        <?php if (!empty($unread_count) && (int)$unread_count > 0): ?>
                            <span style="background: #ffd700; color: #1a1a1a; font-size: 0.8rem; padding: 2px 8px; border-radius: 12px; margin-left: 8px;">
                                <?php echo (int)$unread_count; ?>
                            </span>
                        <?php endif; ?>
                    </h3>
                    <p>Track your vehicle inquiries and view responses from our sales team.
                        <?php if (!empty($unread_count) && (int)$unread_count > 0): ?>
                            <strong style="color: #ffc107;">&nbsp;<?php echo (int)$unread_count; ?> unread inquiry(ies).</strong>
                        <?php else: ?>
                            <strong style="color: #28a745;">You're all caught up. No unread inquiries.</strong>
                        <?php endif; ?>
                    </p>
                    <button class="card-btn" onclick="window.location.href='my_inquiries.php'">
                        <i class="fas fa-search"></i> View My Inquiries
                    </button>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-life-ring"></i>
                    </div>
                </div>
                <div class="card-content">
                    <h3>Help Center</h3>
                    <p>Find answers to common questions like "How do I use the web system?", "Where can I see my reservation?", and more.</p>
                    <button class="card-btn" onclick="window.location.href='help_center.php'">
                        <i class="fas fa-info-circle"></i> Find Answers
                    </button>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-bell"></i>
                    </div>
                </div>
                <div class="card-content">
                    <h3>Notifications</h3>
                    <p>See all system updates such as account verification, application approval, upcoming payment reminders, and other important alerts.</p>
                    <button class="card-btn" onclick="window.location.href='notifications.php'">
                        <i class="fas fa-eye"></i> View Notifications
                    </button>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-file-invoice-dollar"></i>
                    </div>
                </div>
                <div class="card-content">
                    <h3>Order Details</h3>
                    <p>View your balance, see your payment history, check the dates of your payments, and how much you have paid so far.</p>
                    <button class="card-btn" onclick="window.location.href='order_details.php'">
                        <i class="fas fa-receipt"></i> View My Orders
                    </button>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                </div>
                <div class="card-content">
                    <h3>Requirements Guide</h3>
                    <p>See the needed documents if you want to apply through walk-in. Be prepared with all necessary paperwork.</p>
                    <button class="card-btn" onclick="window.location.href='requirements_guide.php'">
                        <i class="fas fa-book-open"></i> View Guide
                    </button>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-user-cog"></i>
                    </div>
                </div>
                <div class="card-content">
                    <h3>Settings</h3>
                    <p>Update your personal information, preferences, and account settings to keep your profile current.</p>
                    <button class="card-btn" onclick="window.location.href='my_profile.php'">
                        <i class="fas fa-edit"></i> Manage Settings
                    </button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
