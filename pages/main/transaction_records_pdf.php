<?php
include_once(dirname(dirname(__DIR__)) . '/includes/init.php');

// Check if user is Admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: ../../pages/login.php");
    exit();
}

// Get filters from URL parameters
$status = trim(strtolower($_GET['status'] ?? ''));
$search = trim($_GET['search'] ?? '');
$model = trim($_GET['model'] ?? '');
$agent_id = trim($_GET['agent_id'] ?? '');
$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');

// Build query
$where = [];
$params = [];

if ($status !== '') {
    if ($status === 'completed') {
        $where[] = "o.order_status IN ('completed','delivered','paid','Complete','Completed')";
    } elseif ($status === 'pending') {
        $where[] = "o.order_status IN ('pending','Processing','processing','Pending')";
    } else {
        $where[] = 'o.order_status = ?';
        $params[] = $status;
    }
}

if ($search !== '') {
    $where[] = "(o.order_number LIKE ? OR CONCAT(ci.firstname,' ',ci.lastname) LIKE ? OR acc.Email LIKE ?)";
    $like = "%$search%";
    array_push($params, $like, $like, $like);
}

if ($model !== '') {
    $where[] = "(o.vehicle_model = ? OR v.model_name = ?)";
    array_push($params, $model, $model);
}

if ($agent_id !== '') {
    $where[] = 'o.sales_agent_id = ?';
    $params[] = $agent_id;
}

if ($date_from !== '') {
    $where[] = 'DATE(o.created_at) >= ?';
    $params[] = $date_from;
}

if ($date_to !== '') {
    $where[] = 'DATE(o.created_at) <= ?';
    $params[] = $date_to;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Fetch transaction data
try {
    $sql = "SELECT
                o.order_number AS TransactionID,
                CONCAT(ci.firstname,' ',ci.lastname) AS ClientName,
                acc.Email AS Email,
                COALESCE(o.vehicle_model, v.model_name) AS VehicleModel,
                COALESCE(o.vehicle_variant, v.variant) AS Variant,
                o.model_year AS ModelYear,
                o.total_price AS SalePrice,
                CONCAT(agent.FirstName,' ',agent.LastName) AS AgentName,
                o.payment_method AS PaymentMethod,
                o.order_status AS OrderStatus,
                o.created_at AS CreatedAt,
                o.actual_delivery_date AS CompletedAt
            FROM orders o
            LEFT JOIN customer_information ci ON o.customer_id = ci.cusID
            LEFT JOIN accounts acc ON ci.account_id = acc.Id
            LEFT JOIN accounts agent ON o.sales_agent_id = agent.Id
            LEFT JOIN vehicles v ON o.vehicle_id = v.id
            $whereSql
            ORDER BY o.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $transactions = [];
}

// Set headers for PDF download
header('Content-Type: text/html');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Transaction Records - Mitsubishi</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: white;
            color: #333;
        }
        
        .report-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            padding: 20px;
            border-bottom: 3px solid #d32f2f;
            margin-bottom: 30px;
        }
        
        .logo-section {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .logo-img {
            width: 60px;
            height: 60px;
        }
        
        .company-name {
            font-size: 2rem;
            font-weight: bold;
            color: #d32f2f;
        }
        
        .tagline {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 15px;
        }
        
        .report-title {
            font-size: 1.5rem;
            font-weight: bold;
            color: #333;
            margin: 10px 0;
        }
        
        .report-meta {
            font-size: 0.85rem;
            color: #666;
            margin: 5px 0;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
            font-size: 0.85rem;
        }
        
        .data-table th,
        .data-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        
        .data-table th {
            background-color: #d32f2f;
            color: white;
            font-weight: bold;
        }
        
        .data-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .data-table tr:hover {
            background-color: #f5f5f5;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
            font-style: italic;
        }
        
        .return-button {
            text-align: center;
            margin-top: 30px;
        }
        
        @media print {
            body {
                margin: 0;
                padding: 10px;
            }
            .report-container {
                max-width: 100%;
            }
            .return-button {
                display: none !important;
            }
            .data-table {
                font-size: 0.75rem;
            }
            .data-table th,
            .data-table td {
                padding: 6px;
            }
        }
    </style>
</head>
<body>
    <div class="report-container">
        <div class="header">
            <div class="logo-section">
                <img src="../../includes/images/mitsubishi_logo.png" alt="Mitsubishi Logo" class="logo-img">
                <div class="company-name">MITSUBISHI MOTORS</div>
            </div>
            <div class="tagline">Drive Your Ambition</div>
            <div class="report-title">Transaction Records Report</div>
            <div class="report-meta">Generated: <?php echo date('F d, Y h:i A'); ?></div>
            <?php if ($status): ?>
                <div class="report-meta">Status: <?php echo ucfirst($status); ?></div>
            <?php endif; ?>
            <?php if ($date_from || $date_to): ?>
                <div class="report-meta">
                    Date Range: 
                    <?php 
                    if ($date_from && $date_to) {
                        echo date('M d, Y', strtotime($date_from)) . ' - ' . date('M d, Y', strtotime($date_to));
                    } elseif ($date_from) {
                        echo 'From ' . date('M d, Y', strtotime($date_from));
                    } else {
                        echo 'Until ' . date('M d, Y', strtotime($date_to));
                    }
                    ?>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($transactions)): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Transaction ID</th>
                        <th>Client Name</th>
                        <th>Email</th>
                        <th>Vehicle Model</th>
                        <th>Variant</th>
                        <th>Year</th>
                        <th>Sale Price</th>
                        <th>Agent Name</th>
                        <th>Payment Method</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Completed At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['TransactionID'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['ClientName'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['Email'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['VehicleModel'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['Variant'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['ModelYear'] ?? ''); ?></td>
                            <td class="text-right">
                                <?php 
                                if (is_numeric($row['SalePrice'])) {
                                    echo '₱' . number_format($row['SalePrice'], 2);
                                } else {
                                    echo htmlspecialchars($row['SalePrice'] ?? '');
                                }
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['AgentName'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['PaymentMethod'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['OrderStatus'] ?? ''); ?></td>
                            <td>
                                <?php 
                                if ($row['CreatedAt']) {
                                    echo date('M d, Y', strtotime($row['CreatedAt']));
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                if ($row['CompletedAt']) {
                                    echo date('M d, Y', strtotime($row['CompletedAt']));
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="no-data">No transaction records found</div>
        <?php endif; ?>
    </div>
    
    <div class="return-button">
        <button onclick="downloadPDF()" style="background: #28a745; color: white; border: none; padding: 15px 30px; border-radius: 8px; font-size: 1.1rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3); margin-right: 10px;">
            <i class="fas fa-download"></i> Download PDF
        </button>
        <button onclick="returnToPage()" style="background: #d32f2f; color: white; border: none; padding: 15px 30px; border-radius: 8px; font-size: 1.1rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 12px rgba(211, 47, 47, 0.3);">
            <i class="fas fa-arrow-left"></i> Return to Page
        </button>
    </div>

    <script>
        window.onload = function() {
            // Auto-print when page loads for PDF generation
            window.print();
        }

        function downloadPDF() {
            // Trigger print dialog which allows saving as PDF
            window.print();
        }

        function returnToPage() {
            window.location.href = 'transaction-records.php';
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

