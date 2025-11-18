<?php
session_start();
include_once(dirname(__DIR__) . '/includes/database/db_conn.php');

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

    // Prefer dedicated license_number column, fallback to notes pattern for older records
    $license_number = 'N/A';
    if (!empty($request['license_number'])) {
        $license_number = $request['license_number'];
    } elseif (!empty($request['notes'])) {
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

// Set headers for PDF download
header('Content-Type: text/html');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Gate Pass - <?php echo htmlspecialchars($request['gate_pass_number']); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: white;
            color: #333;
        }
        
        .gate-pass {
            max-width: 800px;
            margin: 0 auto;
            border: 2px solid #d32f2f;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .header {
            background: #f5f5f5;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #d32f2f;
        }
        
        .logo-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logo-img {
            width: 40px;
            height: 40px;
        }
        
        .logo {
            width: 40px;
            height: 40px;
            background: #d32f2f;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        .company-name {
            font-size: 1.5rem;
            font-weight: bold;
            color: #333;
        }
        
        .gate-pass-title {
            text-align: right;
        }
        
        .gate-pass-title h1 {
            color: #d32f2f;
            margin: 0;
            font-size: 1.8rem;
        }
        
        .date {
            color: #666;
            margin: 5px 0 0 0;
        }
        
        .body {
            padding: 30px;
            background: #fafafa;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-item {
            background: white;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #d32f2f;
        }
        
        .info-label {
            font-size: 0.9rem;
            color: #666;
            font-weight: bold;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        
        .info-value {
            font-size: 1.1rem;
            color: #333;
            font-weight: bold;
        }
        
        .notice {
            background: rgba(211, 47, 47, 0.1);
            padding: 20px;
            border-radius: 5px;
            border-left: 4px solid #d32f2f;
            text-align: center;
            margin-top: 20px;
        }
        
        .notice-text {
            color: #d32f2f;
            font-weight: bold;
            font-size: 1.1rem;
        }
        
        @media print {
            body { margin: 0; padding: 10px; }
            .gate-pass { max-width: 100%; }
            .return-button { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="gate-pass">
        <div class="header">
            <div class="logo-section">
                <img src="../includes/images/mitsubishi_logo.png" alt="Mitsubishi Logo" class="logo-img">
                <div class="company-name">Mitsubishi</div>
            </div>
            <div class="gate-pass-title">
                <h1>Customer Gate Pass</h1>
                <p class="date">Date: <?php echo date('M j, Y'); ?></p>
            </div>
        </div>
        
        <div class="body">
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Gate Pass No:</div>
                    <div class="info-value"><?php echo htmlspecialchars($request['gate_pass_number']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Customer Name:</div>
                    <div class="info-value"><?php echo htmlspecialchars($request['customer_name']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">License Number:</div>
                    <div class="info-value"><?php echo htmlspecialchars($license_number); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Contact No:</div>
                    <div class="info-value"><?php echo htmlspecialchars($request['mobile_number']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Vehicle:</div>
                    <div class="info-value"><?php echo htmlspecialchars($request['model_name'] . ' ' . ($request['variant'] ? $request['variant'] : '') . ' ' . ($request['year_model'] ? $request['year_model'] : '')); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Schedule Date:</div>
                    <div class="info-value"><?php echo date('M j, Y - D', strtotime($request['selected_date'])); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Time:</div>
                    <div class="info-value"><?php echo htmlspecialchars($request['selected_time_slot']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Location:</div>
                    <div class="info-value"><?php echo htmlspecialchars($request['test_drive_location'] ?: 'Showroom'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Instructor:</div>
                    <div class="info-value"><?php echo htmlspecialchars($request['instructor_agent'] ?: 'To be assigned'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Approved By:</div>
                    <div class="info-value"><?php echo htmlspecialchars($approver_name); ?></div>
                </div>
            </div>
            
            <div class="notice">
                <div class="notice-text">
                    Notice: Please ensure to download the gatepass and present it on the scheduled test drive day.
                </div>
            </div>
          
        </div>
    </div>
    <div style="text-align: center; margin-top: 30px;" class="return-button">
                <button onclick="returnToPage()" style="background: #d32f2f; color: white; border: none; padding: 15px 30px; border-radius: 8px; font-size: 1.1rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 12px rgba(211, 47, 47, 0.3);">
                    <i class="fas fa-arrow-left"></i> Return to Page
                </button>
            </div>
    <script>
        window.onload = function() {
            // Auto-print when page loads
            window.print();
        }
        
        function returnToPage() {
            window.location.href = 'test_drive_success.php?request_id=<?php echo $request['id']; ?>';
        }
        
        // Optional: Add keyboard shortcut for return (Escape key)
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                returnToPage();
            }
        });
    </script>
</body>
</html>