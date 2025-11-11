<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

include_once(dirname(__DIR__) . '/includes/init.php');

// Check if user is Admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Use the database connection from init.php
$pdo = $GLOBALS['pdo'] ?? null;

if (!$pdo) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection not available']);
    exit();
}

// Get request parameters
$action = $_GET['action'] ?? 'summary';
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');

// Calculate date range based on year and month parameters
if ($month === 'all') {
    // If "All Months" is selected, use the entire year
    $start_date = "$year-01-01";
    $end_date = "$year-12-31";
} else {
    // Use specific month
    $month_padded = str_pad($month, 2, '0', STR_PAD_LEFT);
    $start_date = "$year-$month_padded-01";
    $last_day = date('t', strtotime($start_date)); // Get last day of the month
    $end_date = "$year-$month_padded-$last_day";
}

// Log the date range for debugging
error_log("Sales Report API - Action: $action, Year: $year, Month: $month, Date Range: $start_date to $end_date");

try {
    switch ($action) {
        case 'summary':
            echo json_encode(getSalesSummary($pdo, $start_date, $end_date));
            break;
        case 'monthly':
            echo json_encode(getMonthlySales($pdo, $year));
            break;
        case 'by-model':
            echo json_encode(getSalesByModel($pdo, $start_date, $end_date));
            break;
        case 'by-agent':
            echo json_encode(getSalesByAgent($pdo, $start_date, $end_date));
            break;
        case 'revenue-trend':
            echo json_encode(getRevenueTrend($pdo, $year, $month));
            break;
        case 'inventory':
            echo json_encode(getInventoryData($pdo));
            break;
        case 'yearly-comparison':
            echo json_encode(getYearlyComparison($pdo, $year));
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

/**
 * Get sales summary for dashboard KPIs
 */
function getSalesSummary($pdo, $start_date, $end_date) {
    // Calculate previous period for comparison
    $period_diff = (strtotime($end_date) - strtotime($start_date)) / (24 * 60 * 60);
    $prev_start = date('Y-m-d', strtotime($start_date) - ($period_diff * 24 * 60 * 60));
    $prev_end = date('Y-m-d', strtotime($start_date) - (24 * 60 * 60));
    
    // Current period sales
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
    
    // Previous period sales for comparison
    $stmt->execute([$prev_start, $prev_end]);
    $previous = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calculate growth percentages
    $revenue_growth = calculateGrowth($current['total_revenue'], $previous['total_revenue']);
    $units_growth = calculateGrowth($current['units_sold'], $previous['units_sold']);
    $transactions_growth = calculateGrowth($current['total_transactions'], $previous['total_transactions']);
    
    // Get inventory count
    $stmt = $pdo->prepare("SELECT SUM(stock_quantity) as total_inventory FROM vehicles");
    $stmt->execute();
    $inventory = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return [
        'start_date' => $start_date,
        'end_date' => $end_date,
        'total_revenue' => floatval($current['total_revenue'] ?? 0),
        'revenue_growth' => $revenue_growth,
        'units_sold' => intval($current['units_sold'] ?? 0),
        'units_growth' => $units_growth,
        'total_transactions' => intval($current['total_transactions'] ?? 0),
        'transactions_growth' => $transactions_growth,
        'completed_orders' => intval($current['completed_orders'] ?? 0),
        'avg_order_value' => floatval($current['avg_order_value'] ?? 0),
        'inventory_units' => intval($inventory['total_inventory'] ?? 0),
        'period' => [
            'start' => $start_date,
            'end' => $end_date
        ]
    ];
}

/**
 * Get monthly sales data for charts
 */
function getMonthlySales($pdo, $year) {
    $stmt = $pdo->prepare("
        SELECT 
            MONTH(o.created_at) as month,
            MONTHNAME(o.created_at) as month_name,
            SUM(CASE WHEN o.order_status = 'Completed' THEN o.total_price ELSE 0 END) as revenue,
            COUNT(CASE WHEN o.order_status = 'Completed' THEN o.order_id END) as units_sold,
            COUNT(DISTINCT o.order_id) as total_orders
        FROM orders o
        WHERE YEAR(o.created_at) = ?
        GROUP BY MONTH(o.created_at), MONTHNAME(o.created_at)
        ORDER BY MONTH(o.created_at)
    ");
    $stmt->execute([$year]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fill in missing months with zero values
    $months = [];
    for ($i = 1; $i <= 12; $i++) {
        $months[$i] = [
            'month' => $i,
            'month_name' => date('F', mktime(0, 0, 0, $i, 1)),
            'revenue' => 0,
            'units_sold' => 0,
            'total_orders' => 0
        ];
    }
    
    foreach ($results as $row) {
        $months[intval($row['month'])] = [
            'month' => intval($row['month']),
            'month_name' => $row['month_name'],
            'revenue' => floatval($row['revenue']),
            'units_sold' => intval($row['units_sold']),
            'total_orders' => intval($row['total_orders'])
        ];
    }
    
    return array_values($months);
}

/**
 * Get sales data by vehicle model
 */
function getSalesByModel($pdo, $start_date, $end_date) {
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
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate market share
    $total_units = array_sum(array_column($results, 'units_sold'));
    
    foreach ($results as &$row) {
        $row['units_sold'] = intval($row['units_sold']);
        $row['revenue'] = floatval($row['revenue']);
        $row['avg_price'] = floatval($row['avg_price']);
        $row['current_stock'] = intval($row['current_stock']);
        $row['market_share'] = $total_units > 0 ? round(($row['units_sold'] / $total_units) * 100, 1) : 0;
        
        // Determine stock status
        if ($row['current_stock'] <= 0) {
            $row['stock_status'] = 'Out of Stock';
        } elseif ($row['current_stock'] <= 5) {
            $row['stock_status'] = 'Low Stock';
        } else {
            $row['stock_status'] = 'In Stock';
        }
    }
    
    return $results;
}

/**
 * Get sales data by sales agent
 */
function getSalesByAgent($pdo, $start_date, $end_date) {
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
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($results as &$row) {
        $row['units_sold'] = intval($row['units_sold']);
        $row['revenue'] = floatval($row['revenue']);
        $row['total_orders'] = intval($row['total_orders']);
        $row['avg_sale_value'] = floatval($row['avg_sale_value']);
        $row['conversion_rate'] = $row['total_orders'] > 0 ? round(($row['units_sold'] / $row['total_orders']) * 100, 1) : 0;
    }
    
    return $results;
}

/**
 * Get revenue trend for the last 6 months ending with the selected month
 */
function getRevenueTrend($pdo, $year, $month) {
    // If "all" is selected, use current month
    if ($month === 'all') {
        $month = date('m');
    }

    // Create the end date (last day of selected month)
    $month_padded = str_pad($month, 2, '0', STR_PAD_LEFT);
    $end_date = "$year-$month_padded-01";

    // Generate 6 months ending with the selected month
    $months = [];
    for ($i = 5; $i >= 0; $i--) {
        $date = date('Y-m-01', strtotime("-$i months", strtotime($end_date)));
        $months[] = [
            'year' => date('Y', strtotime($date)),
            'month' => date('n', strtotime($date)),
            'month_name' => date('F', strtotime($date)),
            'revenue' => 0
        ];
    }

    // Calculate the start date (6 months before the selected month)
    $start_date = date('Y-m-01', strtotime("-5 months", strtotime($end_date)));
    $end_date_last_day = date('Y-m-t', strtotime($end_date));

    // Get actual revenue data for the 6-month period
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
    $stmt->execute([$start_date, $end_date_last_day]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Merge actual data with all months
    foreach ($results as $row) {
        foreach ($months as &$month_data) {
            if ($month_data['year'] == $row['year'] && $month_data['month'] == $row['month']) {
                $month_data['revenue'] = floatval($row['revenue']);
                break;
            }
        }
    }

    // Add month_year field
    foreach ($months as &$month_data) {
        $month_data['month_year'] = $month_data['month_name'] . ' ' . $month_data['year'];
    }

    return $months;
}

/**
 * Get inventory data
 */
function getInventoryData($pdo) {
    $stmt = $pdo->prepare("
        SELECT 
            model_name,
            variant,
            stock_quantity,
            min_stock_alert,
            base_price,
            promotional_price,
            availability_status,
            CASE 
                WHEN stock_quantity <= 0 THEN 'Out of Stock'
                WHEN stock_quantity <= min_stock_alert THEN 'Low Stock'
                ELSE 'In Stock'
            END as stock_status
        FROM vehicles
        ORDER BY model_name, variant
    ");
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($results as &$row) {
        $row['stock_quantity'] = intval($row['stock_quantity']);
        $row['min_stock_alert'] = intval($row['min_stock_alert']);
        $row['base_price'] = floatval($row['base_price']);
        $row['promotional_price'] = $row['promotional_price'] ? floatval($row['promotional_price']) : null;
    }
    
    return $results;
}

/**
 * Calculate growth percentage
 */
function calculateGrowth($current, $previous) {
    if ($previous == 0) {
        return $current > 0 ? 100 : 0;
    }
    return round((($current - $previous) / $previous) * 100, 1);
}

/**
 * Get yearly comparison data - compares current year with previous year
 */
function getYearlyComparison($pdo, $year) {
    $current_year = intval($year);
    $previous_year = $current_year - 1;

    // Get monthly data for current year
    $stmt = $pdo->prepare("
        SELECT
            MONTH(o.created_at) as month,
            MONTHNAME(o.created_at) as month_name,
            SUM(CASE WHEN o.order_status = 'Completed' THEN o.total_price ELSE 0 END) as revenue,
            COUNT(CASE WHEN o.order_status = 'Completed' THEN o.order_id END) as units_sold
        FROM orders o
        WHERE YEAR(o.created_at) = ?
        GROUP BY MONTH(o.created_at), MONTHNAME(o.created_at)
        ORDER BY MONTH(o.created_at)
    ");

    // Get current year data
    $stmt->execute([$current_year]);
    $current_year_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get previous year data
    $stmt->execute([$previous_year]);
    $previous_year_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Initialize all 12 months with zero values
    $months = [];
    for ($i = 1; $i <= 12; $i++) {
        $months[$i] = [
            'month' => $i,
            'month_name' => date('F', mktime(0, 0, 0, $i, 1)),
            'current_year' => $current_year,
            'previous_year' => $previous_year,
            'current_revenue' => 0,
            'previous_revenue' => 0,
            'current_units' => 0,
            'previous_units' => 0,
            'revenue_growth' => 0,
            'units_growth' => 0
        ];
    }

    // Fill in current year data
    foreach ($current_year_data as $row) {
        $month = intval($row['month']);
        $months[$month]['current_revenue'] = floatval($row['revenue']);
        $months[$month]['current_units'] = intval($row['units_sold']);
    }

    // Fill in previous year data
    foreach ($previous_year_data as $row) {
        $month = intval($row['month']);
        $months[$month]['previous_revenue'] = floatval($row['revenue']);
        $months[$month]['previous_units'] = intval($row['units_sold']);
    }

    // Calculate growth percentages
    foreach ($months as &$month_data) {
        $month_data['revenue_growth'] = calculateGrowth(
            $month_data['current_revenue'],
            $month_data['previous_revenue']
        );
        $month_data['units_growth'] = calculateGrowth(
            $month_data['current_units'],
            $month_data['previous_units']
        );
    }

    // Calculate yearly totals
    $current_total_revenue = array_sum(array_column($months, 'current_revenue'));
    $previous_total_revenue = array_sum(array_column($months, 'previous_revenue'));
    $current_total_units = array_sum(array_column($months, 'current_units'));
    $previous_total_units = array_sum(array_column($months, 'previous_units'));

    return [
        'months' => array_values($months),
        'summary' => [
            'current_year' => $current_year,
            'previous_year' => $previous_year,
            'current_total_revenue' => $current_total_revenue,
            'previous_total_revenue' => $previous_total_revenue,
            'current_total_units' => $current_total_units,
            'previous_total_units' => $previous_total_units,
            'total_revenue_growth' => calculateGrowth($current_total_revenue, $previous_total_revenue),
            'total_units_growth' => calculateGrowth($current_total_units, $previous_total_units)
        ]
    ];
}
?>