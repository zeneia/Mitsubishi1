<?php
session_start();
include_once(dirname(__DIR__) . '/includes/database/db_conn.php');

// Check if user is logged in and is a customer
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Customer') {
    header("Location: login.php");
    exit;
}

// Fetch user details for header
$stmt = $connect->prepare("SELECT * FROM accounts WHERE Id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$displayName = !empty($user['FirstName']) ? $user['FirstName'] : $user['Username'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help Center - Mitsubishi Motors</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../css/customer-admin-styles.css" rel="stylesheet">
    <style>
        /* ==========================================
           CSS VARIABLES & DESIGN TOKENS
           ========================================== */
        :root {
            /* Colors */
            --color-primary: #e60012;
            --color-primary-hover: #cc0010;
            --color-background: #f6f7f9;
            --color-white: #fff;
            --color-text-primary: #1a1a1a;
            --color-text-secondary: #333;
            --color-text-muted: #666;
            --color-border-light: #e0e0e0;
            --color-border-medium: #ddd;
            --color-border-dark: #ccc;
            --color-highlight-bg: #f8f9fa;
            --color-highlight-border: #f0f0f0;
            
            /* Typography Scale */
            --font-size-xs: 0.875rem;      /* 14px */
            --font-size-sm: 1rem;          /* 16px */
            --font-size-md: 1.125rem;      /* 18px */
            --font-size-lg: 1.25rem;       /* 20px */
            --font-size-xl: 1.5rem;        /* 24px */
            --font-size-2xl: 1.625rem;     /* 26px */
            --font-size-3xl: 2rem;         /* 32px */
            
            /* Spacing Scale (0.25rem = 4px base) */
            --space-1: 0.25rem;   /* 4px */
            --space-2: 0.5rem;    /* 8px */
            --space-3: 0.75rem;   /* 12px */
            --space-4: 1rem;      /* 16px */
            --space-5: 1.25rem;   /* 20px */
            --space-6: 1.5rem;    /* 24px */
            --space-7: 1.75rem;   /* 28px */
            --space-8: 2rem;      /* 32px */
            --space-10: 2.5rem;   /* 40px */
            --space-12: 3rem;     /* 48px */
            
            /* Border Radius */
            --radius-sm: 0.375rem;  /* 6px */
            --radius-md: 0.5rem;    /* 8px */
            --radius-lg: 0.75rem;   /* 12px */
            --radius-full: 50%;
            
            /* Shadows */
            --shadow-sm: 0 0.125rem 0.5rem rgba(0, 0, 0, 0.08);
            --shadow-md: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.12);
            
            /* Transitions */
            --transition-fast: 0.2s ease;
            --transition-base: 0.3s ease;
            --transition-slow: 0.4s ease;
            
            /* Layout */
            --container-max-width: 75rem;  /* 1200px */
            --header-height: 4rem;
        }

        /* ==========================================
           RESET & BASE STYLES
           ========================================== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', 'Segoe UI', sans-serif;
        }
        
        body {
            background: #ffffff;
            min-height: 100vh;
            color: white;
        }

        /* ==========================================
           HEADER
           ========================================== */
        header {
            background: #000000;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 215, 0, 0.2);
        }

        header .logo-section {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        header .logo-section img {
            width: 60px;
            height: auto;
            filter: drop-shadow(0 0 10px rgba(255, 215, 0, 0.3));
        }

        header .user-section {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .brand-text {
            font-size: 1.4rem;
            font-weight: 700;
            background: linear-gradient(45deg, #ffd700, #ffed4e);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        header .user-section .welcome-text {
            color: #ffffff;
            font-size: 1rem;
            font-weight: 500
            
        }

        header .user-section .user-avatar {
            width: 2.25rem;
            height: 2.25rem;
            background: var(--color-primary);
            border-radius: var(--radius-full);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--color-white);
            font-weight: 700;
            font-size: var(--font-size-sm);
        }

        header .logout-btn {
            background: var(--color-primary);
            color: var(--color-white);
            border: none;
            padding: var(--space-2) var(--space-4);
            border-radius: var(--radius-sm);
            font-weight: 700;
            font-size: var(--font-size-xs);
            cursor: pointer;
            transition: background var(--transition-fast);
        }

        header .logout-btn:hover {
            background: var(--color-primary-hover);
        }

        /* ==========================================
           CONTAINER
           ========================================== */
        .container {
            max-width: var(--container-max-width);
            margin: 0 auto;
            padding: var(--space-4);
            width: 100%;
        }

        /* ==========================================
           BACK BUTTON
           ========================================== */
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            margin-bottom: var(--space-5);
            padding: var(--space-3) var(--space-5);
            background: var(--color-highlight-bg);
            border: 0.0625rem solid var(--color-border-dark);
            border-radius: var(--radius-md);
            text-decoration: none;
            color: var(--color-text-secondary);
            font-size: var(--font-size-xs);
            font-weight: 500;
            transition: all var(--transition-fast);
        }

        .back-btn:hover {
            background: var(--color-border-light);
            transform: translateX(-0.25rem);
        }

        /* ==========================================
           PAGE TITLE
           ========================================== */
        .page-title {
            text-align: center;
            font-size: var(--font-size-2xl);
            font-weight: 700;
            margin: 0 0 var(--space-3) 0;
            color: var(--color-text-primary);
            line-height: 1.2;
        }

        .page-subtitle {
            text-align: center;
            color: var(--color-text-muted);
            margin: 0 0 var(--space-8) 0;
            font-size: var(--font-size-sm);
            line-height: 1.5;
        }

        /* ==========================================
           CATEGORY TABS
           ========================================== */
        .category-tabs {
            display: flex;
            justify-content: center;
            gap: var(--space-2);
            margin-bottom: var(--space-8);
            flex-wrap: wrap;
        }

        .category-tab {
            background: var(--color-white);
            color: var(--color-text-secondary);
            border: 0.0625rem solid var(--color-border-medium);
            padding: var(--space-3) var(--space-5);
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: all var(--transition-base);
            font-weight: 500;
            font-size: var(--font-size-sm);
            white-space: nowrap;
        }

        .category-tab.active,
        .category-tab:hover {
            background: var(--color-primary);
            color: var(--color-white);
            border-color: var(--color-primary);
            transform: translateY(-0.125rem);
            box-shadow: var(--shadow-sm);
        }

        /* ==========================================
           ACCORDION
           ========================================== */
        .accordion {
            display: flex;
            flex-direction: column;
            gap: var(--space-4);
        }

        .accordion-item {
            background: var(--color-white);
            border-radius: var(--radius-lg);
            border: 0.0625rem solid var(--color-border-light);
            overflow: hidden;
            transition: all var(--transition-base);
            box-shadow: var(--shadow-sm);
        }

        .accordion-item:hover {
            border-color: var(--color-primary);
            box-shadow: var(--shadow-md);
        }

        .accordion-header {
            padding: var(--space-5) var(--space-6);
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: var(--space-4);
            background: var(--color-white);
            border-bottom: 0.0625rem solid var(--color-highlight-border);
            transition: background var(--transition-fast);
        }

        .accordion-header:hover {
            background: var(--color-highlight-bg);
        }

        .accordion-header h3 {
            margin: 0;
            font-size: var(--font-size-lg);
            color: var(--color-text-primary);
            font-weight: 600;
            line-height: 1.3;
        }

        .accordion-icon {
            font-size: var(--font-size-lg);
            transition: transform var(--transition-base);
            color: var(--color-primary);
            flex-shrink: 0;
        }

        .accordion-content {
            padding: 0 var(--space-6);
            max-height: 0;
            overflow: hidden;
            transition: max-height var(--transition-slow), padding var(--transition-slow);
            background: var(--color-white);
        }

        .accordion-content .content-inner {
            padding: var(--space-5) 0;
        }

        .accordion-content p {
            line-height: 1.7;
            margin: 0 0 var(--space-4) 0;
            font-size: var(--font-size-sm);
            color: var(--color-text-secondary);
        }

        .accordion-content ul {
            margin: var(--space-4) 0;
            padding-left: var(--space-5);
        }

        .accordion-content li {
            margin-bottom: var(--space-2);
            line-height: 1.7;
            color: var(--color-text-secondary);
            font-size: var(--font-size-sm);
        }

        .accordion-content .highlight {
            background: var(--color-highlight-bg);
            padding: var(--space-4);
            border-radius: var(--radius-md);
            margin: var(--space-4) 0;
            border-left: 0.25rem solid var(--color-primary);
            font-size: var(--font-size-sm);
        }

        .accordion-content .steps {
            background: var(--color-highlight-bg);
            padding: var(--space-5);
            border-radius: var(--radius-md);
            margin: var(--space-4) 0;
        }

        .accordion-content .step {
            display: flex;
            align-items: flex-start;
            gap: var(--space-4);
            margin-bottom: var(--space-3);
        }

        .accordion-content .step:last-child {
            margin-bottom: 0;
        }

        .accordion-content .step-number {
            background: var(--color-primary);
            color: var(--color-white);
            min-width: 1.75rem;
            height: 1.75rem;
            border-radius: var(--radius-full);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: var(--font-size-sm);
            flex-shrink: 0;
        }

        .accordion-item.active .accordion-icon {
            transform: rotate(180deg);
        }

        /* ==========================================
           CONTACT INFO
           ========================================== */
        .contact-info {
            background: var(--color-highlight-bg);
            padding: var(--space-5);
            border-radius: var(--radius-md);
            margin: var(--space-4) 0;
            text-align: center;
            border: 0.0625rem solid var(--color-border-light);
        }

        .contact-info .phone {
            font-size: var(--font-size-lg);
            font-weight: 700;
            color: var(--color-primary);
            margin-bottom: var(--space-2);
        }

        .contact-info p {
            margin: 0;
            font-size: var(--font-size-sm);
            color: var(--color-text-muted);
        }

        /* ==========================================
           RESPONSIVE DESIGN - MOBILE FIRST
           ========================================== */
        
        /* Small devices (360px and up) - Base styles above */
        
        /* Medium devices (768px and up) */

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
            .page-title {
                font-size: 2rem;
            
            }
        }

        @media (min-width: 48rem) {
            :root {
                --header-height: 4.5rem;
            }
            
            header {
                padding: var(--space-4) var(--space-6);
                flex-wrap: nowrap;
            }
            
            header .logo-section {
                font-size: var(--font-size-md);
            }
            
            header .logo-section img {
                height: 2rem;
            }
            
            .container {
                padding: var(--space-6);
            }
            
            .page-title {
                font-size: var(--font-size-3xl);
            }
            
            .page-subtitle {
                font-size: var(--font-size-md);
            }
            
            .category-tab {
                padding: var(--space-3) var(--space-6);
                font-size: var(--font-size-sm);
            }
            
            .accordion-header h3 {
                font-size: var(--font-size-xl);
            }
        }
        
        /* Large devices (1024px and up) */
        @media (min-width: 64rem) {
            .container {
                padding: var(--space-8) var(--space-6);
            }
            
            .accordion {
                gap: var(--space-5);
            }
            
            .accordion-header {
                padding: var(--space-6) var(--space-8);
            }
            
            .accordion-content {
                padding: 0 var(--space-8);
            }
            
            .accordion-content .content-inner {
                padding: var(--space-6) 0;
            }
        }
        
        /* Extra large devices (1440px and up) */
        @media (min-width: 90rem) {
            :root {
                --font-size-2xl: 1.875rem;  /* 30px */
                --font-size-3xl: 2.25rem;   /* 36px */
            }
            
            header {
                padding: var(--space-5) var(--space-8);
            }
            
            .container {
                padding: var(--space-10) var(--space-8);
            }
        }
        
        /* Small mobile adjustments (max-width: 480px) */
        @media (max-width: 30rem) {
            header {
                padding: var(--space-2) var(--space-3);
            }
            
            header .logo-section {
                font-size: var(--font-size-sm);
            }
            
            header .logo-section img {
                height: 1.5rem;
            }
            
            header .user-section {
                gap: var(--space-2);
            }
            
            header .user-section .welcome-text {
                font-size: var(--font-size-xs);
            }
            
            header .user-section .user-avatar {
                width: 2rem;
                height: 2rem;
                font-size: var(--font-size-xs);
            }
            
            header .logout-btn {
                padding: var(--space-2) var(--space-3);
                font-size: 0.75rem;
            }
            
            .container {
                padding: var(--space-3);
            }
            
            .page-title {
                font-size: var(--font-size-xl);
            }
            
            .page-subtitle {
                font-size: var(--font-size-xs);
            }
            
            .category-tabs {
                gap: var(--space-2);
            }
            
            .category-tab {
                padding: var(--space-2) var(--space-4);
                font-size: var(--font-size-xs);
            }
            
            .accordion-header {
                padding: var(--space-4) var(--space-4);
            }
            
            .accordion-header h3 {
                font-size: var(--font-size-md);
            }
            
            .accordion-content {
                padding: 0 var(--space-4);
            }
            
            .accordion-content .content-inner {
                padding: var(--space-4) 0;
            }
            
            .accordion-content .steps {
                padding: var(--space-4);
            }
            
            .accordion-content .step {
                gap: var(--space-3);
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
            <div class="user-avatar"><?php echo strtoupper(substr($displayName, 0, 1)); ?></div>
            <span class="welcome-text">Welcome, <?php echo htmlspecialchars($displayName); ?>!</span>
            <button class="logout-btn" onclick="window.location.href='logout.php'"><i class="fas fa-sign-out-alt"></i> Logout</button>
        </div>
    </header>

    <div class="container">
        <a href="customer.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        <h1 class="page-title">Help Center</h1>
        <p class="page-subtitle">Find answers to common questions and get help with our services</p>

        <div class="category-tabs">
            <button class="category-tab active" data-category="general">General</button>
            <button class="category-tab" data-category="account">Account</button>
            <button class="category-tab" data-category="payment">Payment</button>
            <button class="category-tab" data-category="reservation">Reservation</button>
            <button class="category-tab" data-category="support">Support</button>
        </div>

        <div class="accordion" id="faq-accordion">
            <!-- General Category -->
            <div class="accordion-item" data-category="general">
                <div class="accordion-header">
                    <h3>How do I manage my notifications?</h3>
                    <i class="fas fa-chevron-down accordion-icon"></i>
                </div>
                <div class="accordion-content">
                    <div class="content-inner">
                        <p>To manage your notifications effectively, follow these steps:</p>
                        <div class="steps">
                            <div class="step">
                                <div class="step-number">1</div>
                                <div>Go to your Dashboard and click on "Notifications"</div>
                            </div>
                            <div class="step">
                                <div class="step-number">2</div>
                                <div>Review all your current notifications including account verification, payment reminders, and system updates</div>
                            </div>
                            <div class="step">
                                <div class="step-number">3</div>
                                <div>You can mark notifications as read or delete them as needed</div>
                            </div>
                        </div>
                        <div class="highlight">
                            <strong>Tip:</strong> Important notifications like payment due dates and appointment reminders cannot be deleted to ensure you don't miss critical information.
                        </div>
                    </div>
                </div>
            </div>

            <div class="accordion-item" data-category="general">
                <div class="accordion-header">
                    <h3>How do I use this web system?</h3>
                    <i class="fas fa-chevron-down accordion-icon"></i>
                </div>
                <div class="accordion-content">
                    <div class="content-inner">
                        <p>Our web system is designed to be user-friendly and intuitive. Here's a comprehensive guide:</p>
                        <div class="steps">
                            <div class="step">
                                <div class="step-number">1</div>
                                <div><strong>Dashboard Navigation:</strong> From your main dashboard, you'll see cards for different services</div>
                            </div>
                            <div class="step">
                                <div class="step-number">2</div>
                                <div><strong>Car Menu:</strong> Browse vehicles by category, view detailed specifications, and submit inquiries</div>
                            </div>
                            <div class="step">
                                <div class="step-number">3</div>
                                <div><strong>Chat Support:</strong> Get real-time help from our agents or chatbot</div>
                            </div>
                            <div class="step">
                                <div class="step-number">4</div>
                                <div><strong>Order Management:</strong> Track your payments, view balances, and manage reservations</div>
                            </div>
                        </div>
                        <p>Each section has clear navigation buttons and helpful tooltips to guide you through the process.</p>
                    </div>
                </div>
            </div>

            <!-- Account Category -->
            <div class="accordion-item" data-category="account">
                <div class="accordion-header">
                    <h3>Where can I see my balance?</h3>
                    <i class="fas fa-chevron-down accordion-icon"></i>
                </div>
                <div class="accordion-content">
                    <div class="content-inner">
                        <p>Your account balance and financial information can be found in the Order Details section:</p>
                        <div class="steps">
                            <div class="step">
                                <div class="step-number">1</div>
                                <div>Click on "Order Details" from your dashboard</div>
                            </div>
                            <div class="step">
                                <div class="step-number">2</div>
                                <div>View your current balance, payment history, and due dates</div>
                            </div>
                            <div class="step">
                                <div class="step-number">3</div>
                                <div>Check how much you've paid so far and remaining balance</div>
                            </div>
                        </div>
                        <div class="highlight">
                            <strong>Note:</strong> Your balance is updated in real-time after each payment is processed.
                        </div>
                    </div>
                </div>
            </div>

            <div class="accordion-item" data-category="account">
                <div class="accordion-header">
                    <h3>Is my data safe and private?</h3>
                    <i class="fas fa-chevron-down accordion-icon"></i>
                </div>
                <div class="accordion-content">
                    <div class="content-inner">
                        <p>Yes, your data security and privacy are our top priorities. We implement multiple layers of protection:</p>
                        <ul>
                            <li><strong>Encryption:</strong> All data is encrypted both in transit and at rest</li>
                            <li><strong>Secure Authentication:</strong> Password protection and session management</li>
                            <li><strong>Privacy Policy:</strong> We never share your personal information with third parties without consent</li>
                            <li><strong>Regular Security Updates:</strong> Our systems are continuously monitored and updated</li>
                            <li><strong>Access Control:</strong> Only authorized personnel can access customer data</li>
                        </ul>
                        <div class="highlight">
                            <strong>Your Rights:</strong> You can request to view, update, or delete your personal data at any time by contacting our support team.
                        </div>
                    </div>
                </div>
            </div>

            <div class="accordion-item" data-category="account">
                <div class="accordion-header">
                    <h3>How can I update my credentials?</h3>
                    <i class="fas fa-chevron-down accordion-icon"></i>
                </div>
                <div class="accordion-content">
                    <div class="content-inner">
                        <p>You can update your personal information and account settings easily:</p>
                        <div class="steps">
                            <div class="step">
                                <div class="step-number">1</div>
                                <div>Navigate to "Settings" from your dashboard</div>
                            </div>
                            <div class="step">
                                <div class="step-number">2</div>
                                <div>Click on "Manage Settings" or go to "My Profile"</div>
                            </div>
                            <div class="step">
                                <div class="step-number">3</div>
                                <div>Update your personal details, contact information, or password</div>
                            </div>
                            <div class="step">
                                <div class="step-number">4</div>
                                <div>Save your changes and verify the update via email if required</div>
                            </div>
                        </div>
                        <p><strong>What you can update:</strong></p>
                        <ul>
                            <li>Name and contact information</li>
                            <li>Email address and phone number</li>
                            <li>Password and security settings</li>
                            <li>Notification preferences</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Reservation Category -->
            <div class="accordion-item" data-category="reservation">
                <div class="accordion-header">
                    <h3>Where can I see my reservations?</h3>
                    <i class="fas fa-chevron-down accordion-icon"></i>
                </div>
                <div class="accordion-content">
                    <div class="content-inner">
                        <p>All your reservations and bookings are consolidated in one convenient location:</p>
                        <div class="steps">
                            <div class="step">
                                <div class="step-number">1</div>
                                <div>Go to "Order Details" from your dashboard</div>
                            </div>
                            <div class="step">
                                <div class="step-number">2</div>
                                <div>View all your reservations including:</div>
                            </div>
                        </div>
                        <ul>
                            <li>Vehicle purchase applications</li>
                            <li>Test drive appointments</li>
                            <li>Service center bookings</li>
                            <li>Financing applications</li>
                        </ul>
                        <div class="highlight">
                            <strong>Status Tracking:</strong> Each reservation shows its current status - pending, approved, completed, or cancelled.
                        </div>
                    </div>
                </div>
            </div>

            <div class="accordion-item" data-category="reservation">
                <div class="accordion-header">
                    <h3>How would I know if my request has been approved?</h3>
                    <i class="fas fa-chevron-down accordion-icon"></i>
                </div>
                <div class="accordion-content">
                    <div class="content-inner">
                        <p>You'll be notified about your request status through multiple channels:</p>
                        <div class="steps">
                            <div class="step">
                                <div class="step-number">1</div>
                                <div><strong>Notifications:</strong> Check your dashboard notifications for real-time updates</div>
                            </div>
                            <div class="step">
                                <div class="step-number">2</div>
                                <div><strong>Email Updates:</strong> You'll receive email confirmations for status changes</div>
                            </div>
                            <div class="step">
                                <div class="step-number">3</div>
                                <div><strong>Order Details:</strong> View detailed status in your Order Details section</div>
                            </div>
                        </div>
                        <p><strong>Status Types:</strong></p>
                        <ul>
                            <li><strong>Pending:</strong> Under review by our team</li>
                            <li><strong>Approved:</strong> Request accepted, next steps will be provided</li>
                            <li><strong>Requires Action:</strong> Additional information needed from you</li>
                            <li><strong>Completed:</strong> Process finished successfully</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="accordion-item" data-category="reservation">
                <div class="accordion-header">
                    <h3>After reserving, what should I do next?</h3>
                    <i class="fas fa-chevron-down accordion-icon"></i>
                </div>
                <div class="accordion-content">
                    <div class="content-inner">
                        <p>After making a reservation, here are the next steps to ensure a smooth process:</p>
                        <div class="steps">
                            <div class="step">
                                <div class="step-number">1</div>
                                <div><strong>Check Your Notifications:</strong> Look for confirmation and next steps</div>
                            </div>
                            <div class="step">
                                <div class="step-number">2</div>
                                <div><strong>Prepare Documents:</strong> Gather required documents (see Requirements Guide)</div>
                            </div>
                            <div class="step">
                                <div class="step-number">3</div>
                                <div><strong>Wait for Contact:</strong> Our team will reach out within 24-48 hours</div>
                            </div>
                            <div class="step">
                                <div class="step-number">4</div>
                                <div><strong>Follow Instructions:</strong> Complete any additional steps as guided</div>
                            </div>
                        </div>
                        <div class="highlight">
                            <strong>Pro Tip:</strong> Keep your phone accessible as our team may call to schedule appointments or clarify details.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Category -->
            <div class="accordion-item" data-category="payment">
                <div class="accordion-header">
                    <h3>How do I make payments?</h3>
                    <i class="fas fa-chevron-down accordion-icon"></i>
                </div>
                <div class="accordion-content">
                    <div class="content-inner">
                        <p>We offer multiple convenient payment options:</p>
                        <ul>
                            <li><strong>Online Banking:</strong> Direct bank transfers</li>
                            <li><strong>Credit/Debit Cards:</strong> Visa, Mastercard accepted</li>
                            <li><strong>Dealership Payments:</strong> Pay directly at our service centers</li>
                            <li><strong>Financing Options:</strong> Installment plans available</li>
                        </ul>
                        <div class="highlight">
                            <strong>Payment Schedule:</strong> Your payment due dates and amounts are clearly shown in your Order Details section.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Support Category -->
            <div class="accordion-item" data-category="support">
                <div class="accordion-header">
                    <h3>How can I contact customer support?</h3>
                    <i class="fas fa-chevron-down accordion-icon"></i>
                </div>
                <div class="accordion-content">
                    <div class="content-inner">
                        <p>We offer multiple ways to get help when you need it:</p>
                        <div class="steps">
                            <div class="step">
                                <div class="step-number">1</div>
                                <div><strong>Live Chat:</strong> Use our Chat Support for immediate assistance</div>
                            </div>
                            <div class="step">
                                <div class="step-number">2</div>
                                <div><strong>Phone Support:</strong> Call our hotline for direct conversation</div>
                            </div>
                            <div class="step">
                                <div class="step-number">3</div>
                                <div><strong>Email:</strong> Send detailed inquiries for complex issues</div>
                            </div>
                        </div>
                        <div class="contact-info">
                            <div class="phone">📞 1-800-MITSUBISHI (1-800-648-7824)</div>
                            <p>Available 9 AM - 6 PM EST, Monday to Friday</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="accordion-item" data-category="support">
                <div class="accordion-header">
                    <h3>What if I encounter technical issues?</h3>
                    <i class="fas fa-chevron-down accordion-icon"></i>
                </div>
                <div class="accordion-content">
                    <div class="content-inner">
                        <p>If you experience technical problems with the website:</p>
                        <div class="steps">
                            <div class="step">
                                <div class="step-number">1</div>
                                <div>Try refreshing your browser or clearing your cache</div>
                            </div>
                            <div class="step">
                                <div class="step-number">2</div>
                                <div>Check your internet connection</div>
                            </div>
                            <div class="step">
                                <div class="step-number">3</div>
                                <div>Try using a different browser or device</div>
                            </div>
                            <div class="step">
                                <div class="step-number">4</div>
                                <div>Contact our technical support team if issues persist</div>
                            </div>
                        </div>
                        <p><strong>When contacting support, please provide:</strong></p>
                        <ul>
                            <li>Your account email</li>
                            <li>Description of the problem</li>
                            <li>Browser and device information</li>
                            <li>Screenshot if applicable</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Category filtering
        const categoryTabs = document.querySelectorAll('.category-tab');
        const accordionItems = document.querySelectorAll('.accordion-item');

        categoryTabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const category = tab.getAttribute('data-category');
                
                // Update active tab
                categoryTabs.forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                
                // Filter accordion items
                accordionItems.forEach(item => {
                    if (category === 'general' || item.getAttribute('data-category') === category) {
                        item.style.display = 'block';
                    } else {
                        item.style.display = 'none';
                        item.classList.remove('active');
                        const content = item.querySelector('.accordion-content');
                        content.style.maxHeight = '0px';
                    }
                });
            });
        });

        // Accordion functionality
        const accordionHeaders = document.querySelectorAll('.accordion-header');
        accordionHeaders.forEach(header => {
            header.addEventListener('click', () => {
                const item = header.parentElement;
                const content = item.querySelector('.accordion-content');
                const isActive = item.classList.contains('active');
                
                // Close all other items
                accordionItems.forEach(otherItem => {
                    if (otherItem !== item) {
                        otherItem.classList.remove('active');
                        const otherContent = otherItem.querySelector('.accordion-content');
                        otherContent.style.maxHeight = '0px';
                    }
                });
                
                // Toggle current item
                if (isActive) {
                    item.classList.remove('active');
                    content.style.maxHeight = '0px';
                } else {
                    item.classList.add('active');
                    content.style.maxHeight = content.scrollHeight + 'px';
                }
            });
        });

        // Auto-adjust content height on window resize
        window.addEventListener('resize', () => {
            accordionItems.forEach(item => {
                if (item.classList.contains('active')) {
                    const content = item.querySelector('.accordion-content');
                    content.style.maxHeight = content.scrollHeight + 'px';
                }
            });
        });
    </script>
</body>
</html>
