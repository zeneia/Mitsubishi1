<?php
include_once(dirname(dirname(__DIR__)) . '/includes/init.php');

// Check if user is Admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: ../../pages/login.php");
    exit();
}

// Use the database connection from init.php
$pdo = $GLOBALS['pdo'] ?? null;

if (!$pdo) {
    die("Database connection not available. Please check your database configuration.");
}

// Get filter parameters
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');

// Calculate date range based on selection
if ($month === 'all') {
    $start_date = "$year-01-01";
    $end_date = "$year-12-31";
    $period_label = "Year $year";
} else {
    $month_padded = str_pad($month, 2, '0', STR_PAD_LEFT);
    $start_date = "$year-$month_padded-01";
    $last_day = date('t', strtotime($start_date));
    $end_date = "$year-$month_padded-$last_day";
    $period_label = date('F Y', strtotime($start_date));
}

// Fetch data directly from database
try {
    // Get summary data
    $period_diff = (strtotime($end_date) - strtotime($start_date)) / (24 * 60 * 60);
    $prev_start = date('Y-m-d', strtotime($start_date) - ($period_diff * 24 * 60 * 60));
    $prev_end = date('Y-m-d', strtotime($start_date) - (24 * 60 * 60));

    $stmt = $pdo->prepare("
        SELECT
            COUNT(DISTINCT o.order_id) as total_transactions,
            COUNT(DISTINCT CASE WHEN o.order_status = 'Completed' THEN o.order_id END) as completed_orders,
            SUM(CASE WHEN o.order_status = 'Completed' THEN o.total_price ELSE 0 END) as total_revenue,
            SUM(CASE WHEN o.order_status = 'Completed' THEN 1 ELSE 0 END) as units_sold,
            AVG(CASE WHEN o.order_status = 'Completed' THEN o.total_price ELSE NULL END) as avg_order_value
        FROM orders o
        WHERE DATE(o.created_at) BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date, $end_date]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT SUM(stock_quantity) as total_inventory FROM vehicles");
    $stmt->execute();
    $inventory = $stmt->fetch(PDO::FETCH_ASSOC);

    $summaryData = [
        'total_revenue' => floatval($current['total_revenue'] ?? 0),
        'units_sold' => intval($current['units_sold'] ?? 0),
        'total_transactions' => intval($current['total_transactions'] ?? 0),
        'completed_orders' => intval($current['completed_orders'] ?? 0),
        'avg_order_value' => floatval($current['avg_order_value'] ?? 0),
        'inventory_units' => intval($inventory['total_inventory'] ?? 0)
    ];

    // Get sales by model
    $stmt = $pdo->prepare("
        SELECT
            o.vehicle_model as model_name,
            COUNT(CASE WHEN o.order_status = 'Completed' THEN o.order_id END) as units_sold,
            SUM(CASE WHEN o.order_status = 'Completed' THEN o.total_price ELSE 0 END) as revenue,
            AVG(CASE WHEN o.order_status = 'Completed' THEN o.total_price ELSE NULL END) as avg_price,
            COALESCE(v.stock_quantity, 0) as current_stock
        FROM orders o
        LEFT JOIN vehicles v ON o.vehicle_model = v.model_name
        WHERE DATE(o.created_at) BETWEEN ? AND ?
        GROUP BY o.vehicle_model, v.stock_quantity
        ORDER BY units_sold DESC
    ");
    $stmt->execute([$start_date, $end_date]);
    $salesByModelRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Aggregate sales by model to avoid duplicates
    $modelMap = [];
    $total_units = 0;
    foreach ($salesByModelRaw as $item) {
        $modelName = $item['model_name'];
        $units = intval($item['units_sold']);
        $total_units += $units;

        if (isset($modelMap[$modelName])) {
            $modelMap[$modelName]['units_sold'] += $units;
            $modelMap[$modelName]['revenue'] += floatval($item['revenue']);
        } else {
            $modelMap[$modelName] = [
                'model_name' => $modelName,
                'units_sold' => $units,
                'revenue' => floatval($item['revenue']),
                'avg_price' => floatval($item['avg_price']),
                'current_stock' => intval($item['current_stock'])
            ];
        }
    }

    // Calculate market share and stock status
    foreach ($modelMap as &$row) {
        $row['market_share'] = $total_units > 0 ? round(($row['units_sold'] / $total_units) * 100, 1) : 0;
        if ($row['current_stock'] <= 0) {
            $row['stock_status'] = 'Out of Stock';
        } elseif ($row['current_stock'] <= 5) {
            $row['stock_status'] = 'Low Stock';
        } else {
            $row['stock_status'] = 'In Stock';
        }
    }

    $salesByModel = array_values($modelMap);
    usort($salesByModel, function($a, $b) {
        return $b['units_sold'] - $a['units_sold'];
    });

    // Get revenue trend (6 months ending with selected month)
    $trend_month = $month === 'all' ? date('m') : $month;
    $trend_month_padded = str_pad($trend_month, 2, '0', STR_PAD_LEFT);
    $trend_end_date = "$year-$trend_month_padded-01";

    $months = [];
    for ($i = 5; $i >= 0; $i--) {
        $date = date('Y-m-01', strtotime("-$i months", strtotime($trend_end_date)));
        $months[] = [
            'year' => date('Y', strtotime($date)),
            'month' => date('n', strtotime($date)),
            'month_name' => date('F', strtotime($date)),
            'revenue' => 0
        ];
    }

    $trend_start_date = date('Y-m-01', strtotime("-5 months", strtotime($trend_end_date)));
    $trend_end_date_last_day = date('Y-m-t', strtotime($trend_end_date));

    $stmt = $pdo->prepare("
        SELECT
            YEAR(o.created_at) as year,
            MONTH(o.created_at) as month,
            MONTHNAME(o.created_at) as month_name,
            SUM(CASE WHEN o.order_status = 'Completed' THEN o.total_price ELSE 0 END) as revenue
        FROM orders o
        WHERE DATE(o.created_at) BETWEEN ? AND ?
        GROUP BY YEAR(o.created_at), MONTH(o.created_at), MONTHNAME(o.created_at)
        ORDER BY YEAR(o.created_at), MONTH(o.created_at)
    ");
    $stmt->execute([$trend_start_date, $trend_end_date_last_day]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($results as $row) {
        foreach ($months as &$month_data) {
            if ($month_data['year'] == $row['year'] && $month_data['month'] == $row['month']) {
                $month_data['revenue'] = floatval($row['revenue']);
                break;
            }
        }
    }
    $revenueTrend = $months;

    // Get sales by agent
    $stmt = $pdo->prepare("
        SELECT
            CONCAT(a.FirstName, ' ', a.LastName) as agent_name,
            a.Email as agent_email,
            COUNT(CASE WHEN o.order_status = 'Completed' THEN o.order_id END) as units_sold,
            SUM(CASE WHEN o.order_status = 'Completed' THEN o.total_price ELSE 0 END) as revenue,
            COUNT(DISTINCT o.order_id) as total_orders,
            AVG(CASE WHEN o.order_status = 'Completed' THEN o.total_price ELSE NULL END) as avg_sale_value
        FROM orders o
        LEFT JOIN accounts a ON o.sales_agent_id = a.Id
        WHERE DATE(o.created_at) BETWEEN ? AND ?
        AND a.Role = 'SalesAgent'
        GROUP BY o.sales_agent_id, a.FirstName, a.LastName, a.Email
        ORDER BY revenue DESC
    ");
    $stmt->execute([$start_date, $end_date]);
    $salesByAgent = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Error fetching sales report data: " . $e->getMessage());
    $summaryData = [
        'total_revenue' => 0,
        'units_sold' => 0,
        'total_transactions' => 0,
        'completed_orders' => 0,
        'avg_order_value' => 0,
        'inventory_units' => 0
    ];
    $salesByModel = [];
    $revenueTrend = [];
    $salesByAgent = [];
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Sales Report - Mitsubishi Motors</title>
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
        
        .section-header {
            background: linear-gradient(135deg, #d32f2f, #b71c1c);
            color: white;
            padding: 15px 20px;
            margin: 30px 0 15px 0;
            border-radius: 8px;
            font-size: 1.2rem;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .kpi-card {
            background: #f8f9fa;
            border-left: 4px solid #d32f2f;
            padding: 15px;
            border-radius: 8px;
        }
        
        .kpi-label {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 5px;
        }
        
        .kpi-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #333;
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
            padding: 10px;
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
        
        .page-break {
            page-break-after: always;
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
        <!-- Header -->
        <div class="header">
            <div class="logo-section">
                <img src="../../includes/images/mitsubishi_logo.png" alt="Mitsubishi Logo" class="logo-img">
                <div class="company-name">MITSUBISHI MOTORS</div>
            </div>
            <div class="tagline">Drive Your Ambition</div>
            <div class="report-title">Comprehensive Sales Report</div>
            <div class="report-meta">Generated: <?php echo date('F d, Y h:i A'); ?></div>
            <div class="report-meta">Report Period: <?php echo htmlspecialchars($period_label); ?></div>
        </div>
        
        <!-- KPI Summary Section -->
        <div class="section-header">
            <i class="fas fa-chart-line"></i>
            <span>Key Performance Indicators</span>
        </div>
        
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-label">Total Revenue</div>
                <div class="kpi-value">₱<?php echo number_format($summaryData['total_revenue'] ?? 0, 2); ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Units Sold</div>
                <div class="kpi-value"><?php echo number_format($summaryData['units_sold'] ?? 0); ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Total Transactions</div>
                <div class="kpi-value"><?php echo number_format($summaryData['total_transactions'] ?? 0); ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Completed Orders</div>
                <div class="kpi-value"><?php echo number_format($summaryData['completed_orders'] ?? 0); ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Average Order Value</div>
                <div class="kpi-value">₱<?php echo number_format($summaryData['avg_order_value'] ?? 0, 2); ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Inventory Units</div>
                <div class="kpi-value"><?php echo number_format($summaryData['inventory_units'] ?? 0); ?></div>
            </div>
        </div>

        <!-- Sales by Model Section -->
        <div class="section-header">
            <i class="fas fa-car"></i>
            <span>Sales Performance by Model</span>
        </div>

        <?php if (!empty($salesByModel)): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Vehicle Model</th>
                        <th class="text-right">Units Sold</th>
                        <th class="text-right">Revenue</th>
                        <th class="text-right">Market Share</th>
                        <th class="text-right">Avg. Price</th>
                        <th class="text-center">Stock Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($salesByModel as $model): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($model['model_name'] ?? 'N/A'); ?></td>
                            <td class="text-right"><?php echo number_format($model['units_sold'] ?? 0); ?></td>
                            <td class="text-right">₱<?php echo number_format($model['revenue'] ?? 0, 2); ?></td>
                            <td class="text-right"><?php echo number_format($model['market_share'] ?? 0, 1); ?>%</td>
                            <td class="text-right">₱<?php echo number_format($model['avg_price'] ?? 0, 2); ?></td>
                            <td class="text-center"><?php echo htmlspecialchars($model['stock_status'] ?? 'N/A'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="no-data">No sales data available for the selected period</div>
        <?php endif; ?>

        <!-- Revenue Trend Section -->
        <div class="section-header">
            <i class="fas fa-chart-bar"></i>
            <span>Revenue Trend (Last 6 Months)</span>
        </div>

        <?php if (!empty($revenueTrend)): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th class="text-right">Revenue</th>
                        <th class="text-right">Growth</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $prevRevenue = null;
                    foreach ($revenueTrend as $trend):
                        $revenue = $trend['revenue'] ?? 0;
                        $growth = '';
                        if ($prevRevenue !== null && $prevRevenue > 0) {
                            $growthPercent = (($revenue - $prevRevenue) / $prevRevenue) * 100;
                            $arrow = $growthPercent >= 0 ? '↑' : '↓';
                            $growth = $arrow . ' ' . number_format(abs($growthPercent), 1) . '%';
                        }
                        $prevRevenue = $revenue;
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($trend['month_name'] ?? 'N/A'); ?></td>
                            <td class="text-right">₱<?php echo number_format($revenue, 2); ?></td>
                            <td class="text-right"><?php echo $growth ?: '-'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="no-data">No revenue trend data available</div>
        <?php endif; ?>

        <!-- Sales Agent Performance Section -->
        <div class="section-header">
            <i class="fas fa-users"></i>
            <span>Sales Agent Performance</span>
        </div>

        <?php if (!empty($salesByAgent)): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Agent Name</th>
                        <th>Email</th>
                        <th class="text-right">Total Orders</th>
                        <th class="text-right">Units Sold</th>
                        <th class="text-right">Total Revenue</th>
                        <th class="text-right">Avg. Sale Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($salesByAgent as $agent): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($agent['agent_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($agent['agent_email'] ?? 'N/A'); ?></td>
                            <td class="text-right"><?php echo number_format($agent['total_orders'] ?? 0); ?></td>
                            <td class="text-right"><?php echo number_format($agent['units_sold'] ?? 0); ?></td>
                            <td class="text-right">₱<?php echo number_format($agent['revenue'] ?? 0, 2); ?></td>
                            <td class="text-right">₱<?php echo number_format($agent['avg_sale_value'] ?? 0, 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="no-data">No agent performance data available for the selected period</div>
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
            window.location.href = 'sales-report.php';
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

