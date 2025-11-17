<?php
session_start();
include_once(dirname(__DIR__) . '/includes/database/db_conn.php');
include_once(dirname(__DIR__) . '/pages/header_ex.php');


// Check if user is logged in and is a customer
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Customer') {
    header("Location: login.php");
    exit;
}

// Get request ID from URL
$request_id = isset($_GET['request_id']) ? (int)$_GET['request_id'] : null;

if (!$request_id) {
    header("Location: customer.php");
    exit;
}

try {
    // Fetch test drive request details with approver information
    $stmt = $connect->prepare("
        SELECT tdr.*,
               v.model_name,
               v.variant,
               v.year_model,
               CONCAT(approver.FirstName, ' ', approver.LastName) as approved_by_name,
               CONCAT(agent.FirstName, ' ', agent.LastName) as agent_name
        FROM test_drive_requests tdr
        LEFT JOIN vehicles v ON tdr.vehicle_id = v.id
        LEFT JOIN accounts approver ON tdr.approved_by = approver.Id
        LEFT JOIN customer_information ci ON tdr.account_id = ci.account_id
        LEFT JOIN accounts agent ON ci.agent_id = agent.Id
        WHERE tdr.id = ? AND tdr.account_id = ?
    ");
    $stmt->execute([$request_id, $_SESSION['user_id']]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        header("Location: customer.php");
        exit;
    }

    // Extract license number from notes if available (same logic as PDF)
    $license_number = 'N/A';
    if (!empty($request['notes'])) {
        if (preg_match('/License Number:\s*(.+?)(?:\n|$)/i', $request['notes'], $matches)) {
            $license_number = trim($matches[1]);
        }
    }

    // Determine who approved (use approved_by if available, otherwise use agent)
    $approver_name = !empty($request['approved_by_name']) ? $request['approved_by_name'] :
                     (!empty($request['agent_name']) ? $request['agent_name'] : 'Pending Approval');
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    header("Location: customer.php");
    exit;
}

// Fetch user details
$stmt = $connect->prepare("SELECT * FROM accounts WHERE Id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$displayName = !empty($user['FirstName']) ? $user['FirstName'] : $user['Username'];

// Assign instructor agent (sample)
$instructor_agent = 'Reo Remos';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Drive Request Submitted - Mitsubishi Motors</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', 'Segoe UI', sans-serif; }
        body { background: #ffffff; min-height: 100vh; color: white; }
        
        .header { background: #000000; padding: 20px 30px; display: flex; justify-content: space-between; align-items: center; backdrop-filter: blur(20px); border-bottom: 1px solid rgba(255, 215, 0, 0.2); position: relative; z-index: 10; }
        .logo-section { display: flex; align-items: center; gap: 20px; }
        .logo { width: 60px; height: auto; filter: drop-shadow(0 0 10px rgba(255, 215, 0, 0.3)); }
        .brand-text { font-size: 1.4rem; font-weight: 700; background: linear-gradient(45deg, #ffd700, #ffed4e); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .user-section { display: flex; align-items: center; gap: 20px; }
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(45deg, #ffd700, #ffed4e); display: flex; align-items: center; justify-content: center; font-weight: bold; color: #b80000; font-size: 1.2rem; }
        .welcome-text { font-size: 1rem; font-weight: 500; }
        .logout-btn { background: linear-gradient(45deg, #d60000, #b30000); color: white; border: none; padding: 12px 24px; border-radius: 25px; cursor: pointer; font-size: 0.9rem; font-weight: 600; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(214, 0, 0, 0.3); }
        .logout-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(214, 0, 0, 0.5); }
        
        .container { max-width: 980px; margin: 0 auto; padding: 40px 20px; position: relative; z-index: 5; }

        .success-message {
            background: rgba(76, 175, 79, 0.45);
            border: 1px solid rgba(76, 175, 80, 0.3);
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            margin-bottom: 30px;
        }

        .success-message h1 {
            color: #4CAF50;
            font-size: 1.8rem;
            margin-bottom: 15px;
        }

        .success-message p {
            color: #000000;
            font-size: 1.1rem;
            line-height: 1.6;
            opacity: 0.9;
        }

        .gate-pass-container {
            background: white;
            color: #333;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            margin-bottom: 30px;
        }

        .gate-pass-header {
            background: linear-gradient(135deg, #f5f5f5, #e0e0e0);
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #d32f2f;
        }

        .mitsubishi-logo {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .mitsubishi-logo img {
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.3));
        }

        /* Ensure in-pass logo has a fixed, small size */
        .gate-pass-logo { width: 64px; max-width: 64px; height: auto; display: block; }

        .company-name {
            font-size: 1.2rem;
            font-weight: 700;
            color: #333;
        }

        .gate-pass-title {
            text-align: right;
        }

        .gate-pass-title h2 {
            color: #d32f2f;
            font-size: 1.5rem;
            margin-bottom: 5px;
        }

        .gate-pass-date {
            color: #666;
            font-size: 0.9rem;
        }

        .gate-pass-body {
            padding: 30px;
            background: #f9f9f9;
        }

        .gate-pass-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }

        .info-group {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #d32f2f;
        }

        .info-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 1rem;
            font-weight: 700;
            color: #333;
        }

        .gate-pass-notice {
            background: rgba(211, 47, 47, 0.1);
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #d32f2f;
            margin-bottom: 25px;
        }

        .notice-text {
            color: #d32f2f;
            font-weight: 600;
            text-align: center;
        }

        .download-section {
            text-align: center;
            padding: 20px;
            background: white;
            border-radius: 8px;
        }

        .download-btn {
            background: linear-gradient(45deg, #d32f2f, #b71c1c);
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .download-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(211, 47, 47, 0.3);
        }

        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 15px;
            margin-top: 20px;
            align-items: stretch;
        }

        .action-btn, .print-btn, .back-btn {
            background: linear-gradient(45deg, #ffd700, #ffed4e);
            color: #1a1a1a;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .action-btn:hover, .print-btn:hover, .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 215, 0, 0.4);
        }

        .action-btn.secondary {
            background: transparent;
            color: #ffd700;
            border: 2px solid #ffd700;
        }

        .action-btn.secondary:hover {
            background: #ffd700;
            color: #1a1a1a;
        }

        /* Define back/print specific variants */
        .print-btn { background: linear-gradient(45deg, #4caf50, #2e7d32); color: #fff; }
        .back-btn  { background: transparent; color: #000000; border: 2px solid #808080; }

        @media (max-width: 768px) {
            .gate-pass-info {
                grid-template-columns: 1fr;
            }
            
            .gate-pass-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .action-buttons {
                grid-template-columns: 1fr;
            }
            
            .container {
                padding: 20px 15px;
            }
            
            .gate-pass-body {
                padding: 20px;
            }
        }

        /* Print: Only print the gate pass card and keep the logo small */
        @media print {
            body { background: #ffffff !important; }
            /* Hide non-essential UI during print */
            .header, .success-message, .action-buttons, .logout-btn { display: none !important; }
            .download-btn, .print-btn, .back-btn { display: none !important; }

            /* Focus printing on the gate pass only */
            body * { visibility: hidden; }
            .gate-pass-container, .gate-pass-container * { visibility: visible; }
            .gate-pass-container { position: relative; margin: 0 !important; box-shadow: none !important; border: 1px solid #ddd; }
            .container { padding: 0 !important; max-width: 100% !important; }

            /* Ensure logo never overflows */
            .gate-pass-logo { width: 48px !important; max-width: 48px !important; height: auto !important; }
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
        <div class="success-message">
            <h1>Test Drive Request <?php echo ($request['status'] === 'Approved') ? 'Approved' : 'Submitted Successfully'; ?>!</h1>
            <p>
                <?php if ($request['status'] === 'Approved'): ?>
                    Your test drive request has been approved! Here are your test drive details:
                <?php else: ?>
                    Your test drive request has been received and is pending approval. You will be notified once your request is reviewed.
                <?php endif; ?>
            </p>
        </div>

        <?php if ($request['status'] === 'Approved'): ?>
            <div class="gate-pass-container">
                <div class="gate-pass-header">
                    <img src="../includes/images/mitsubishi_logo.png" alt="Mitsubishi Logo" class="gate-pass-logo">
                    <div class="gate-pass-title">
                        <h2>TEST DRIVE GATE PASS</h2>
                        <p>Gate Pass Number: <?php echo htmlspecialchars($request['gate_pass_number']); ?></p>
                    </div>
                </div>

                <div class="gate-pass-body">
                    <div class="gate-pass-row">
                        <div class="gate-pass-col">
                            <div class="gate-pass-field">
                                <span class="field-label">Customer Name:</span>
                                <span class="field-value"><?php echo htmlspecialchars($request['customer_name']); ?></span>
                            </div>
                            <div class="gate-pass-field">
                                <span class="field-label">License Number:</span>
                                <span class="field-value"><?php echo htmlspecialchars($license_number); ?></span>
                            </div>
                            <div class="gate-pass-field">
                                <span class="field-label">Vehicle:</span>
                                <span class="field-value">
                                    <?php
                                    $vehicleInfo = [];
                                    if (!empty($request['model_name'])) $vehicleInfo[] = $request['model_name'];
                                    if (!empty($request['variant'])) $vehicleInfo[] = $request['variant'];
                                    if (!empty($request['year_model'])) $vehicleInfo[] = $request['year_model'];
                                    echo htmlspecialchars(implode(' ', $vehicleInfo));
                                    ?>
                                </span>
                            </div>
                            <div class="gate-pass-field">
                                <span class="field-label">Scheduled Date:</span>
                                <span class="field-value"><?php echo date('F j, Y', strtotime($request['selected_date'])); ?></span>
                            </div>
                        </div>
                        <div class="gate-pass-col">
                            <div class="gate-pass-field">
                                <span class="field-label">Time Slot:</span>
                                <span class="field-value"><?php echo htmlspecialchars($request['selected_time_slot']); ?></span>
                            </div>
                            <div class="gate-pass-field">
                                <span class="field-label">Location:</span>
                                <span class="field-value"><?php echo !empty($request['test_drive_location']) ? htmlspecialchars($request['test_drive_location']) : 'San Pablo Branch'; ?></span>
                            </div>
                            <div class="gate-pass-field">
                                <span class="field-label">Instructor:</span>
                                <span class="field-value"><?php echo !empty($request['instructor_agent']) ? htmlspecialchars($request['instructor_agent']) : 'To be assigned'; ?></span>
                            </div>
                            <div class="gate-pass-field">
                                <span class="field-label">Approved By:</span>
                                <span class="field-value"><?php echo htmlspecialchars($approver_name); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="gate-pass-footer">
                    <div class="gate-pass-notice">
                        <p><strong>IMPORTANT:</strong> Please present this gate pass and a valid ID at the dealership on your scheduled test drive date and time.</p>
                    </div>
                    <div class="gate-pass-barcode">
                        <!-- Barcode would be generated here -->
                        <div style="text-align: center; padding: 10px 0; background: white; margin: 10px 0;">
                            <?php echo htmlspecialchars($request['gate_pass_number']); ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="action-buttons">
                <button onclick="window.print()" class="print-btn">
                    <i class="fas fa-print"></i> Print Gate Pass
                </button>
                <a href="test_drive_pdf.php?request_id=<?php echo $request['id']; ?>" class="download-btn">
                    <i class="fas fa-download"></i> Download as PDF
                </a>
                <a href="customer.php" class="back-btn">
                    <i class="fas fa-home"></i> Back to Dashboard
                </a>
            </div>
        <?php else: ?>
            <div class="info-box">
                <h3 style = "color: #B80000;"><i class="fas fa-info-circle"></i> Next Steps</h3>
                <ul style = "color: #000000;">
                    <li>Your test drive request is currently under review by our team.</li>
                    <li>You will receive a notification once your request is approved.</li>
                    <li>Please ensure your contact information is up to date.</li>
                </ul>
            </div>
            
            <div class="action-buttons" style="justify-content: center;">
                <a href="customer.php" class="back-btn">
                    <i class="fas fa-home"></i> Back to Dashboard
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Page-specific scripts can go here if needed
    </script>
</body>
</html>
