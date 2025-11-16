<?php
include_once(dirname(dirname(__DIR__)) . '/includes/init.php');

// Check if user is Admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
  header("Location: ../../pages/login.php");
  exit();
}

// Use the database connection from init.php (which uses db_conn.php)
$pdo = $GLOBALS['pdo'] ?? null;

// Check if database connection exists
if (!$pdo) {
  die("Database connection not available. Please check your database configuration.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Sales Report & Analytics - Admin Dashboard</title>
  
  <?php
  // Mobile Responsiveness Fix
  $css_path = '../../css/';
  $js_path = '../../js/';
  include '../../includes/components/mobile-responsive-include.php';
  ?>
  
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link href="../../includes/css/common-styles.css" rel="stylesheet">
  <link href="../../includes/css/orders-styles.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    /* Override global overflow hidden to allow page scrolling */
    html, body {
      overflow: visible !important;
      height: auto !important;
      scroll-behavior: smooth;
    }

    /* REMOVED zoom: 85% - causes mobile layout issues, not supported by Firefox */
    
    .main {
      height: auto !important;
      min-height: 100vh;
    }
    
    .main-content {
      height: auto !important;
      max-height: none !important;
      overflow-y: visible !important;
    }
    
    /* Page wrapper for better content flow */
    .sales-report-wrapper {
      min-height: 100vh;
      padding-bottom: 40px;
    }
    
    /* Ensure all sections have proper spacing */
    .report-section {
      margin-bottom: 32px;
    }
    
    /* Add smooth scroll to anchor links */
    a[href^="#"] {
      scroll-behavior: smooth;
    }
    
    /* Improve scrollbar appearance */
    ::-webkit-scrollbar {
      width: 8px;
    }
    
    ::-webkit-scrollbar-track {
      background: #f1f1f1;
      border-radius: 4px;
    }
    
    ::-webkit-scrollbar-thumb {
      background: #c1c1c1;
      border-radius: 4px;
    }
    
    ::-webkit-scrollbar-thumb:hover {
      background: #a8a8a8;
    }
    
    .report-controls {
      background: white;
      border-radius: 12px;
      padding: 24px;
      margin-bottom: 24px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    .control-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 16px;
      margin-bottom: 20px;
    }
    
    .control-group {
      display: flex;
      flex-direction: column;
    }
    
    .control-label {
      font-weight: 600;
      color: #2c3e50;
      margin-bottom: 8px;
      font-size: 0.9rem;
    }
    
    .control-select, .control-input {
      padding: 12px;
      border: 2px solid #e1e8ed;
      border-radius: 8px;
      background: white;
      font-size: 0.9rem;
      transition: all 0.3s ease;
    }
    
    .control-select:focus, .control-input:focus {
      outline: none;
      border-color: #3498db;
      box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
    }
    
    .action-buttons {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
    }
    
    .btn-generate {
      background: linear-gradient(135deg, #3498db, #2980b9);
      color: white;
      border: none;
      padding: 12px 24px;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .btn-generate:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(52, 152, 219, 0.3);
    }
    
    .btn-export {
      background: linear-gradient(135deg, #27ae60, #219a52);
      color: white;
      border: none;
      padding: 12px 24px;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .btn-export:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(39, 174, 96, 0.3);
    }
    
    .kpi-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 20px;
      margin-bottom: 32px;
    }
    
    .kpi-card {
      background: white;
      border-radius: 16px;
      padding: 24px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      border-left: 4px solid;
      position: relative;
      overflow: hidden;
    }
    
    .kpi-card::before {
      content: '';
      position: absolute;
      top: 0;
      right: 0;
      width: 60px;
      height: 60px;
      opacity: 0.1;
      border-radius: 50%;
    }
    
    .kpi-card.revenue {
      border-left-color: #27ae60;
    }
    
    .kpi-card.revenue::before {
      background: #27ae60;
    }
    
    .kpi-card.units {
      border-left-color: #3498db;
    }
    
    .kpi-card.units::before {
      background: #3498db;
    }
    
    .kpi-card.inventory {
      border-left-color: #f39c12;
    }
    
    .kpi-card.inventory::before {
      background: #f39c12;
    }
    
    .kpi-card.growth {
      border-left-color: #9b59b6;
    }
    
    .kpi-card.growth::before {
      background: #9b59b6;
    }
    
    .kpi-card.customers {
      border-left-color: #e74c3c;
    }
    
    .kpi-card.customers::before {
      background: #e74c3c;
    }
    
    .kpi-card.conversion {
      border-left-color: #1abc9c;
    }
    
    .kpi-card.conversion::before {
      background: #1abc9c;
    }
    
    .kpi-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 16px;
    }
    
    .kpi-icon {
      width: 48px;
      height: 48px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.2rem;
      color: white;
    }
    
    .kpi-value {
      font-size: 2.2rem;
      font-weight: 800;
      color: #2c3e50;
      margin-bottom: 4px;
    }
    
    .kpi-label {
      color: #7f8c8d;
      font-size: 0.9rem;
      font-weight: 500;
      margin-bottom: 8px;
    }
    
    .kpi-change {
      display: flex;
      align-items: center;
      gap: 4px;
      font-size: 0.8rem;
      font-weight: 600;
    }
    
    .kpi-change.positive {
      color: #27ae60;
    }
    
    .kpi-change.negative {
      color: #e74c3c;
    }
    
    .charts-section {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 24px;
      margin-bottom: 32px;
    }
    
    .chart-card {
      background: white;
      border-radius: 16px;
      padding: 24px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    .chart-header {
      display: flex;
      justify-content: between;
      align-items: center;
      margin-bottom: 20px;
    }
    
    .chart-title {
      font-size: 1.1rem;
      font-weight: 700;
      color: #2c3e50;
      margin: 0;
    }
    
    .chart-subtitle {
      font-size: 0.8rem;
      color: #7f8c8d;
      margin: 4px 0 0 0;
    }
    
    .chart-container {
      position: relative;
      height: 320px;
      width: 100%;
    }
    
    .performance-section {
      background: white;
      border-radius: 16px;
      padding: 24px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    .section-header-enhanced {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 24px;
      padding-bottom: 16px;
      border-bottom: 2px solid #f8f9fa;
    }
    
    .section-title-enhanced {
      display: flex;
      align-items: center;
      gap: 12px;
      font-size: 1.3rem;
      font-weight: 700;
      color: #2c3e50;
    }
    
    .section-actions {
      display: flex;
      gap: 12px;
    }
    
    .btn-small-enhanced {
      padding: 8px 16px;
      border: none;
      border-radius: 6px;
      font-size: 0.8rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    
    .table-enhanced {
      width: 100%;
      border-collapse: collapse;
      margin-top: 16px;
    }
    
    .table-enhanced th {
      background: linear-gradient(135deg, #f8f9fa, #e9ecef);
      color: #2c3e50;
      font-weight: 700;
      font-size: 0.9rem;
      padding: 16px 12px;
      text-align: left;
      border-bottom: 2px solid #dee2e6;
    }
    
    .table-enhanced td {
      padding: 16px 12px;
      border-bottom: 1px solid #f1f3f4;
      vertical-align: middle;
    }
    
    .table-enhanced tbody tr:hover {
      background: rgba(52, 152, 219, 0.05);
    }
    
    .model-info-enhanced {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    
    .model-image {
      width: 40px;
      height: 40px;
      border-radius: 8px;
      background: linear-gradient(135deg, #3498db, #2980b9);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-weight: bold;
    }
    
    .model-details {
      display: flex;
      flex-direction: column;
    }
    
    .model-name {
      font-weight: 700;
      color: #2c3e50;
      font-size: 0.95rem;
    }
    
    .model-variant {
      font-size: 0.8rem;
      color: #7f8c8d;
    }
    
    .performance-metric {
      display: flex;
      flex-direction: column;
      align-items: center;
    }
    
    .metric-value {
      font-weight: 700;
      font-size: 1rem;
      color: #2c3e50;
    }
    
    .metric-label {
      font-size: 0.75rem;
      color: #7f8c8d;
    }
    
    .status-indicator {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 0.8rem;
      font-weight: 600;
    }
    
    .status-indicator.high-stock {
      background: rgba(39, 174, 96, 0.1);
      color: #27ae60;
    }
    
    .status-indicator.low-stock {
      background: rgba(243, 156, 18, 0.1);
      color: #f39c12;
    }
    
    .status-indicator.out-of-stock {
      background: rgba(231, 76, 60, 0.1);
      color: #e74c3c;
    }
    
    .trend-indicator {
      display: flex;
      align-items: center;
      gap: 4px;
      font-weight: 600;
      font-size: 0.9rem;
    }
    
    .trend-indicator.positive {
      color: #27ae60;
    }
    
    .trend-indicator.negative {
      color: #e74c3c;
    }
    
    /* Stock status indicators */
    .status-indicator {
      padding: 4px 8px;
      border-radius: 12px;
      font-size: 0.85rem;
      font-weight: 500;
    }
    
    .status-indicator.in-stock {
      background: rgba(39, 174, 96, 0.1);
      color: #27ae60;
    }
    
    .status-indicator.low-stock {
      background: rgba(243, 156, 18, 0.1);
      color: #f39c12;
    }
    
    .status-indicator.out-of-stock {
      background: rgba(231, 76, 60, 0.1);
      color: #e74c3c;
    }
    
    /* KPI loading and error states */
    .kpi-change.positive {
      color: #27ae60;
    }
    
    .kpi-change.negative {
      color: #e74c3c;
    }
    
    /* Agent Performance Grid */
    .agent-performance-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 20px;
      margin-top: 16px;
    }
    
    .agent-card {
      background: white;
      border-radius: 12px;
      padding: 20px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      border-left: 4px solid #9b59b6;
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
    }
    
    .agent-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }
    
    .agent-header {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 16px;
    }
    
    .agent-avatar {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      background: linear-gradient(135deg, #9b59b6, #8e44ad);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-weight: bold;
      font-size: 1.2rem;
    }
    
    .agent-info {
      flex: 1;
    }
    
    .agent-name {
      font-weight: 700;
      font-size: 1.1rem;
      color: #2c3e50;
      margin-bottom: 2px;
    }
    
    .agent-email {
      font-size: 0.85rem;
      color: #7f8c8d;
    }
    
    .agent-metrics {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
    }
    
    .agent-metric {
      text-align: center;
      padding: 8px;
      background: #f8f9fa;
      border-radius: 8px;
    }
    
    .agent-metric-value {
      font-size: 1.1rem;
      font-weight: 700;
      color: #2c3e50;
      margin-bottom: 2px;
    }
    
    .agent-metric-label {
      font-size: 0.75rem;
      color: #7f8c8d;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .agent-rank {
      position: absolute;
      top: 12px;
      right: 12px;
      background: #9b59b6;
      color: white;
      padding: 4px 8px;
      border-radius: 12px;
      font-size: 0.75rem;
      font-weight: 600;
    }
    
    .agent-rank.top {
      background: #f39c12;
    }
    
    .agent-rank.second {
      background: #95a5a6;
    }
    
    .agent-rank.third {
      background: #e67e22;
    }
    
    @media (max-width: 768px) {
      .charts-section {
        grid-template-columns: 1fr;
      }
      
      .kpi-grid {
        grid-template-columns: 1fr;
      }
      
      .control-grid {
        grid-template-columns: 1fr;
      }
      
      .action-buttons {
        justify-content: stretch;
      }
      
      .btn-generate, .btn-export {
        flex: 1;
        justify-content: center;
      }
      
      /* Ensure proper scrolling on mobile */
      .main-content {
        padding: 20px;
      }
      
      .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
      }
      
      /* Improve table responsiveness */
      .table-enhanced {
        min-width: 800px;
      }
    }
  </style>
</head>

<body>
  <?php include '../../includes/components/sidebar.php'; ?>

  <div class="main">
    <?php include '../../includes/components/topbar.php'; ?>

    <div class="main-content">
      <div class="sales-report-wrapper">
        <div class="page-header">
          <h1 class="page-title">
            <i class="fas fa-chart-line icon-gradient"></i>
            Sales Report
          </h1>
          <div class="page-subtitle">Generate and view monthly performance reports and KPIs for the dealership</div>
        </div>

        <!-- Enhanced Report Controls -->
        <div class="report-controls report-section">
        <div class="control-grid">
          <div class="control-group">
            <label class="control-label" for="report-year">
              <i class="fas fa-calendar-alt"></i> Report Year
            </label>
            <select id="report-year" class="control-select">
              <option value="2025" selected>2025</option>
              <option value="2024">2024</option>
              <option value="2023">2023</option>
              <option value="2022">2022</option>
            </select>
          </div>
          <div class="control-group">
            <label class="control-label" for="report-month">
              <i class="fas fa-calendar"></i> Report Month
            </label>
            <select id="report-month" class="control-select">
              <option value="all">All Months</option>
              <option value="1">January</option>
              <option value="2">February</option>
              <option value="3">March</option>
              <option value="4">April</option>
              <option value="5">May</option>
              <option value="6">June</option>
              <option value="7">July</option>
              <option value="8">August</option>
              <option value="9">September</option>
              <option value="10">October</option>
              <option value="11">November</option>
              <option value="12">December</option>
            </select>
          </div>

        </div>
        <div class="action-buttons">
          <button class="btn-export" onclick="exportAllReports()">
            <i class="fas fa-file-pdf"></i> Export All Reports to PDF
          </button>
        </div>
        <div id="date-range-indicator" style="margin-top: 15px; padding: 10px; background: #e3f2fd; border-left: 4px solid #2196f3; border-radius: 4px; font-size: 14px; color: #1976d2;">
          <i class="fas fa-info-circle"></i> <strong>Viewing data for:</strong> <span id="current-date-range">Loading...</span>
        </div>
      </div>

      <!-- Enhanced KPI Dashboard -->
      <div class="kpi-grid report-section" id="kpiDashboard">
        <div class="kpi-card revenue">
          <div class="kpi-header">
            <div>
              <div class="kpi-value" id="totalRevenue">₱0</div>
              <div class="kpi-label">Monthly Revenue</div>
              <div class="kpi-change" id="revenueGrowth">
                <i class="fas fa-spinner fa-spin"></i> Loading...
              </div>
            </div>
            <div class="kpi-icon" style="background: #27ae60;">
              <i class="fas fa-peso-sign"></i>
            </div>
          </div>
        </div>
        
        <div class="kpi-card units">
          <div class="kpi-header">
            <div>
              <div class="kpi-value" id="unitsSold">0</div>
              <div class="kpi-label">Units Sold</div>
              <div class="kpi-change" id="unitsGrowth">
                <i class="fas fa-spinner fa-spin"></i> Loading...
              </div>
            </div>
            <div class="kpi-icon" style="background: #3498db;">
              <i class="fas fa-car"></i>
            </div>
          </div>
        </div>
        
        <div class="kpi-card inventory">
          <div class="kpi-header">
            <div>
              <div class="kpi-value" id="inventoryUnits">0</div>
              <div class="kpi-label">Inventory Units</div>
              <div class="kpi-change" id="inventoryStatus">
                <i class="fas fa-spinner fa-spin"></i> Loading...
              </div>
            </div>
            <div class="kpi-icon" style="background: #f39c12;">
              <i class="fas fa-warehouse"></i>
            </div>
          </div>
        </div>
        
        <div class="kpi-card customers">
          <div class="kpi-header">
            <div>
              <div class="kpi-value" id="totalTransactions">0</div>
              <div class="kpi-label">Total Orders</div>
              <div class="kpi-change" id="transactionsGrowth">
                <i class="fas fa-spinner fa-spin"></i> Loading...
              </div>
            </div>
            <div class="kpi-icon" style="background: #e74c3c;">
              <i class="fas fa-shopping-cart"></i>
            </div>
          </div>
        </div>
        
        <div class="kpi-card conversion">
          <div class="kpi-header">
            <div>
              <div class="kpi-value" id="completedOrders">0</div>
              <div class="kpi-label">Completed Orders</div>
              <div class="kpi-change" id="completionRate">
                <i class="fas fa-spinner fa-spin"></i> Loading...
              </div>
            </div>
            <div class="kpi-icon" style="background: #1abc9c;">
              <i class="fas fa-check-circle"></i>
            </div>
          </div>
        </div>
        
        <div class="kpi-card growth">
          <div class="kpi-header">
            <div>
              <div class="kpi-value" id="avgOrderValue">₱0</div>
              <div class="kpi-label">Avg. Order Value</div>
              <div class="kpi-change" id="avgOrderGrowth">
                <i class="fas fa-spinner fa-spin"></i> Loading...
              </div>
            </div>
            <div class="kpi-icon" style="background: #9b59b6;">
              <i class="fas fa-calculator"></i>
            </div>
          </div>
        </div>
      </div>

      <!-- Enhanced Charts Section -->
      <div class="charts-section report-section">
        <div class="chart-card">
          <div class="chart-header">
            <div>
              <h3 class="chart-title">Sales by Model</h3>
              <p class="chart-subtitle">Current month distribution</p>
            </div>
          </div>
          <div class="chart-container">
            <canvas id="salesByModelChart"></canvas>
          </div>
        </div>
        
        <div class="chart-card">
          <div class="chart-header">
            <div>
              <h3 class="chart-title">Revenue Trend</h3>
              <p class="chart-subtitle">6-month performance</p>
            </div>
          </div>
          <div class="chart-container">
            <canvas id="revenueChart"></canvas>
          </div>
        </div>
      </div>

      <!-- Yearly Sales Comparison Section -->
      <div class="performance-section report-section">
        <div class="section-header-enhanced">
          <h2 class="section-title-enhanced">
            <i class="fas fa-calendar-alt"></i>
            <span>Yearly Sales Comparison</span>
          </h2>
          <div class="section-actions">
            <button class="btn-small-enhanced" style="background: #e67e22; color: white;" onclick="refreshYearlyComparison()">
              <i class="fas fa-sync-alt"></i> Refresh
            </button>
          </div>
        </div>

        <div class="chart-card" style="margin-top: 20px;">
          <div class="chart-header">
            <div>
              <h3 class="chart-title">Year-over-Year Revenue Comparison</h3>
              <p class="chart-subtitle" id="yearComparisonSubtitle">Comparing current year with previous year</p>
            </div>
          </div>
          <div class="chart-container" style="height: 400px;">
            <canvas id="yearlyComparisonChart"></canvas>
          </div>
        </div>

        <!-- Yearly Summary Cards -->
        <div class="kpi-grid" style="margin-top: 24px; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
          <div class="kpi-card" style="border-left-color: #3498db;">
            <div class="kpi-header">
              <div>
                <div class="kpi-value" id="currentYearRevenue">₱0</div>
                <div class="kpi-label" id="currentYearLabel">Current Year Revenue</div>
              </div>
              <div class="kpi-icon" style="background: #3498db;">
                <i class="fas fa-chart-line"></i>
              </div>
            </div>
          </div>

          <div class="kpi-card" style="border-left-color: #95a5a6;">
            <div class="kpi-header">
              <div>
                <div class="kpi-value" id="previousYearRevenue">₱0</div>
                <div class="kpi-label" id="previousYearLabel">Previous Year Revenue</div>
              </div>
              <div class="kpi-icon" style="background: #95a5a6;">
                <i class="fas fa-chart-line"></i>
              </div>
            </div>
          </div>

          <div class="kpi-card" style="border-left-color: #27ae60;">
            <div class="kpi-header">
              <div>
                <div class="kpi-value" id="yearlyRevenueGrowth">0%</div>
                <div class="kpi-label">Revenue Growth</div>
                <div class="kpi-change" id="yearlyRevenueGrowthIndicator">
                  <i class="fas fa-spinner fa-spin"></i> Loading...
                </div>
              </div>
              <div class="kpi-icon" style="background: #27ae60;">
                <i class="fas fa-percentage"></i>
              </div>
            </div>
          </div>

          <div class="kpi-card" style="border-left-color: #9b59b6;">
            <div class="kpi-header">
              <div>
                <div class="kpi-value" id="yearlyUnitsGrowth">0%</div>
                <div class="kpi-label">Units Growth</div>
                <div class="kpi-change" id="yearlyUnitsGrowthIndicator">
                  <i class="fas fa-spinner fa-spin"></i> Loading...
                </div>
              </div>
              <div class="kpi-icon" style="background: #9b59b6;">
                <i class="fas fa-car"></i>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Sales Agent Performance Section -->
      <div class="performance-section report-section">
        <div class="section-header-enhanced">
          <h2 class="section-title-enhanced">
            <i class="fas fa-users"></i>
            <span>Sales Agent Performance</span>
          </h2>
          <div class="section-actions">
            <button class="btn-small-enhanced" style="background: #9b59b6; color: white;" onclick="refreshAgentData()">
              <i class="fas fa-sync-alt"></i> Refresh
            </button>
          </div>
        </div>
        
        <div class="agent-performance-grid" id="agentPerformanceGrid">
          <div style="text-align: center; padding: 40px; color: var(--text-light); grid-column: 1 / -1;">
            <i class="fas fa-spinner fa-spin" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
            <p>Loading agent performance data...</p>
          </div>
        </div>
      </div>

      <!-- Enhanced Sales Performance Table -->
      <div class="performance-section report-section">
        <div class="section-header-enhanced">
          <h2 class="section-title-enhanced">
            <i class="fas fa-chart-bar"></i>
            <span>Sales Performance by Model</span>
          </h2>
          <div class="section-actions">
            <button class="btn-small-enhanced" style="background: #3498db; color: white;" onclick="refreshData()">
              <i class="fas fa-sync-alt"></i> Refresh
            </button>
          </div>
        </div>
        
        <div class="table-responsive">
          <table class="table-enhanced">
            <thead>
              <tr>
                <th>Vehicle Model</th>
                <th>Units Sold</th>
                <th>Revenue</th>
                <th>Market Share</th>
                <th>Growth Trend</th>
                <th>Inventory Status</th>
                <th>Avg. Price</th>
              </tr>
            </thead>
            <tbody id="performanceTableBody">
              <tr>
                <td colspan="8" style="text-align: center; padding: 40px; color: var(--text-light);">
                  <i class="fas fa-spinner fa-spin" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
                  <p>Loading performance data...</p>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      </div> <!-- Close sales-report-wrapper -->
    </div>
  </div>

  <!-- Add SweetAlert CDN -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="../../includes/js/common-scripts.js"></script>
  
  <script>
    // Global variables for charts
    let salesByModelChart = null;
    let revenueChart = null;
    let yearlyComparisonChart = null;

    // Helper function to get filter parameters
    function getFilterParams() {
      const year = document.getElementById('report-year').value;
      const month = document.getElementById('report-month').value;
      return { year, month };
    }

    // Initialize dashboard
    document.addEventListener('DOMContentLoaded', function() {
      // Set current month as default
      const currentMonth = new Date().getMonth() + 1;
      document.getElementById('report-month').value = currentMonth;

      // Set current year as default
      const currentYear = new Date().getFullYear();
      document.getElementById('report-year').value = currentYear;

      // Add event listeners for filter changes
      document.getElementById('report-year').addEventListener('change', function() {
        loadDashboardData();
      });

      document.getElementById('report-month').addEventListener('change', function() {
        loadDashboardData();
      });

      // Load all data
      loadDashboardData();
    });

    // Load all dashboard data
    async function loadDashboardData() {
      try {
        const { year, month } = getFilterParams();
        console.log('Loading dashboard data for:', { year, month });

        await Promise.all([
          loadKPIData(),
          loadChartData(),
          loadPerformanceTable(),
          loadAgentPerformance(),
          loadYearlyComparison()
        ]);

        console.log('Dashboard data loaded successfully');
      } catch (error) {
        console.error('Error loading dashboard data:', error);
        showErrorMessage('Failed to load dashboard data');
      }
    }

    // Load KPI summary data
    async function loadKPIData() {
      try {
        const { year, month } = getFilterParams();
        const url = `../../api/sales-report.php?action=summary&year=${year}&month=${month}`;
        console.log('Fetching KPI data from:', url);

        const response = await fetch(url);
        const data = await response.json();

        console.log('KPI data received:', {
          year: year,
          month: month,
          date_range: `${data.start_date} to ${data.end_date}`,
          total_revenue: data.total_revenue,
          units_sold: data.units_sold,
          total_transactions: data.total_transactions
        });

        // Update date range indicator
        if (data.start_date && data.end_date) {
          const startDate = new Date(data.start_date);
          const endDate = new Date(data.end_date);
          const options = { year: 'numeric', month: 'long', day: 'numeric' };
          const rangeText = `${startDate.toLocaleDateString('en-US', options)} to ${endDate.toLocaleDateString('en-US', options)}`;
          document.getElementById('current-date-range').textContent = rangeText;
        }

        if (data.error) {
          throw new Error(data.error);
        }
        
        // Update KPI values
        document.getElementById('totalRevenue').textContent = formatCurrency(data.total_revenue);
        document.getElementById('unitsSold').textContent = data.units_sold;
        document.getElementById('inventoryUnits').textContent = data.inventory_units;
        document.getElementById('totalTransactions').textContent = data.total_transactions;
        document.getElementById('completedOrders').textContent = data.completed_orders;
        document.getElementById('avgOrderValue').textContent = formatCurrency(data.avg_order_value);
        
        // Update growth indicators
        updateGrowthIndicator('revenueGrowth', data.revenue_growth);
        updateGrowthIndicator('unitsGrowth', data.units_growth);
        updateGrowthIndicator('transactionsGrowth', data.transactions_growth);
        
        // Update inventory status
        document.getElementById('inventoryStatus').innerHTML = `
          <i class="fas fa-warehouse"></i> Current Stock
        `;
        
        // Update completion rate
        const completionRate = data.total_transactions > 0 ? 
          Math.round((data.completed_orders / data.total_transactions) * 100) : 0;
        document.getElementById('completionRate').innerHTML = `
          <i class="fas fa-check-circle"></i> ${completionRate}% Completion
        `;
        
        // Update average order growth
        document.getElementById('avgOrderGrowth').innerHTML = `
          <i class="fas fa-calculator"></i> Per Order
        `;
        
      } catch (error) {
        console.error('Error loading KPI data:', error);
        showKPIError();
      }
    }

    // Load chart data
    async function loadChartData() {
      try {
        const { year, month } = getFilterParams();
        console.log('Fetching chart data for:', { year, month });

        const [salesByModelResponse, revenueTrendResponse] = await Promise.all([
          fetch(`../../api/sales-report.php?action=by-model&year=${year}&month=${month}`),
          fetch(`../../api/sales-report.php?action=revenue-trend&year=${year}&month=${month}`)
        ]);
        
        const salesByModelData = await salesByModelResponse.json();
        const revenueTrendData = await revenueTrendResponse.json();
        
        if (salesByModelData.error || revenueTrendData.error) {
          throw new Error(salesByModelData.error || revenueTrendData.error);
        }
        
        // Initialize charts with real data
        initializeSalesByModelChart(salesByModelData);
        initializeRevenueChart(revenueTrendData);
        
      } catch (error) {
        console.error('Error loading chart data:', error);
        // Initialize with empty data
        initializeSalesByModelChart([]);
        initializeRevenueChart([]);
      }
    }

    // Load performance table data
    async function loadPerformanceTable() {
      try {
        const { year, month } = getFilterParams();
        const response = await fetch(`../../api/sales-report.php?action=by-model&year=${year}&month=${month}`);
        const data = await response.json();
        
        if (data.error) {
          throw new Error(data.error);
        }
        
        populatePerformanceTable(data);
        
      } catch (error) {
        console.error('Error loading performance table:', error);
        showTableError();
      }
    }

    // Load agent performance data
    async function loadAgentPerformance() {
      try {
        const { year, month } = getFilterParams();
        const response = await fetch(`../../api/sales-report.php?action=by-agent&year=${year}&month=${month}`);
        const data = await response.json();
        
        if (data.error) {
          throw new Error(data.error);
        }
        
        populateAgentPerformance(data);
        
      } catch (error) {
        console.error('Error loading agent performance:', error);
        showAgentError();
      }
    }

    // Populate agent performance grid
    function populateAgentPerformance(data) {
      const grid = document.getElementById('agentPerformanceGrid');
      
      if (data.length === 0) {
        grid.innerHTML = `
          <div style="text-align: center; padding: 40px; color: var(--text-light); grid-column: 1 / -1;">
            <i class="fas fa-user-times" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
            <p>No agent performance data available for the selected period</p>
          </div>
        `;
        return;
      }
      
      grid.innerHTML = data.map((agent, index) => {
        const initials = agent.agent_name.split(' ').map(name => name.charAt(0)).join('').toUpperCase();
        const rankClass = index === 0 ? 'top' : index === 1 ? 'second' : index === 2 ? 'third' : '';
        const rankText = index === 0 ? '#1 Top Performer' : `#${index + 1}`;
        
        return `
          <div class="agent-card">
            <div class="agent-rank ${rankClass}">${rankText}</div>
            <div class="agent-header">
              <div class="agent-avatar">${initials}</div>
              <div class="agent-info">
                <div class="agent-name">${agent.agent_name}</div>
                <div class="agent-email">${agent.agent_email || 'No email'}</div>
              </div>
            </div>
            <div class="agent-metrics">
              <div class="agent-metric">
                <div class="agent-metric-value">${agent.total_orders}</div>
                <div class="agent-metric-label">Transactions</div>
              </div>
              <div class="agent-metric">
                <div class="agent-metric-value">${agent.units_sold}</div>
                <div class="agent-metric-label">Units Sold</div>
              </div>
              <div class="agent-metric">
                <div class="agent-metric-value">${formatCurrency(agent.revenue)}</div>
                <div class="agent-metric-label">Total Sales</div>
              </div>
              <div class="agent-metric">
                <div class="agent-metric-value">${formatCurrency(agent.avg_sale_value)}</div>
                <div class="agent-metric-label">Avg Sale</div>
              </div>
            </div>
          </div>
        `;
      }).join('');
    }

    // Show agent performance error
    function showAgentError() {
      const grid = document.getElementById('agentPerformanceGrid');
      if (grid) {
        grid.innerHTML = `
          <div style="text-align: center; padding: 40px; color: var(--text-light); grid-column: 1 / -1;">
            <i class="fas fa-exclamation-triangle" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5; color: #e74c3c;"></i>
            <p>Error loading agent performance data. Please try refreshing the page.</p>
          </div>
        `;
      }
    }

    // Refresh agent data
    function refreshAgentData() {
      const grid = document.getElementById('agentPerformanceGrid');
      grid.innerHTML = `
        <div style="text-align: center; padding: 40px; color: var(--text-light); grid-column: 1 / -1;">
          <i class="fas fa-spinner fa-spin" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
          <p>Refreshing agent performance data...</p>
        </div>
      `;
      
      loadAgentPerformance().then(() => {
        // Show success message
        const notification = document.createElement('div');
        notification.style.cssText = `
          position: fixed;
          top: 20px;
          right: 20px;
          background: #27ae60;
          color: white;
          padding: 12px 20px;
          border-radius: 6px;
          font-size: 14px;
          z-index: 1000;
          opacity: 0;
          transition: opacity 0.3s ease;
        `;
        notification.textContent = 'Agent data refreshed successfully!';
        document.body.appendChild(notification);
        
        // Animate in
        setTimeout(() => notification.style.opacity = '1', 100);
        
        // Remove after 3 seconds
        setTimeout(() => {
          notification.style.opacity = '0';
          setTimeout(() => document.body.removeChild(notification), 300);
        }, 3000);
      }).catch(error => {
        console.error('Error refreshing agent data:', error);
      });
    }

    // Load yearly comparison data
    async function loadYearlyComparison() {
      try {
        const { year } = getFilterParams();
        const response = await fetch(`../../api/sales-report.php?action=yearly-comparison&year=${year}`);
        const data = await response.json();

        if (data.error) {
          throw new Error(data.error);
        }

        // Update summary cards
        updateYearlySummary(data.summary);

        // Initialize chart
        initializeYearlyComparisonChart(data.months);

      } catch (error) {
        console.error('Error loading yearly comparison:', error);
        showYearlyComparisonError();
      }
    }

    // Update yearly summary cards
    function updateYearlySummary(summary) {
      // Update labels
      document.getElementById('currentYearLabel').textContent = `${summary.current_year} Revenue`;
      document.getElementById('previousYearLabel').textContent = `${summary.previous_year} Revenue`;
      document.getElementById('yearComparisonSubtitle').textContent =
        `Comparing ${summary.current_year} with ${summary.previous_year}`;

      // Update values
      document.getElementById('currentYearRevenue').textContent = formatCurrency(summary.current_total_revenue);
      document.getElementById('previousYearRevenue').textContent = formatCurrency(summary.previous_total_revenue);
      document.getElementById('yearlyRevenueGrowth').textContent = summary.total_revenue_growth + '%';
      document.getElementById('yearlyUnitsGrowth').textContent = summary.total_units_growth + '%';

      // Update growth indicators
      const revenueGrowthClass = summary.total_revenue_growth >= 0 ? 'positive' : 'negative';
      const revenueGrowthIcon = summary.total_revenue_growth >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
      document.getElementById('yearlyRevenueGrowthIndicator').innerHTML = `
        <i class="fas ${revenueGrowthIcon}"></i> ${Math.abs(summary.total_revenue_growth)}% vs ${summary.previous_year}
      `;
      document.getElementById('yearlyRevenueGrowthIndicator').className = `kpi-change ${revenueGrowthClass}`;

      const unitsGrowthClass = summary.total_units_growth >= 0 ? 'positive' : 'negative';
      const unitsGrowthIcon = summary.total_units_growth >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
      document.getElementById('yearlyUnitsGrowthIndicator').innerHTML = `
        <i class="fas ${unitsGrowthIcon}"></i> ${Math.abs(summary.total_units_growth)}% vs ${summary.previous_year}
      `;
      document.getElementById('yearlyUnitsGrowthIndicator').className = `kpi-change ${unitsGrowthClass}`;
    }

    // Initialize yearly comparison chart
    function initializeYearlyComparisonChart(data) {
      const ctx = document.getElementById('yearlyComparisonChart').getContext('2d');

      // Destroy existing chart if it exists
      if (yearlyComparisonChart) {
        yearlyComparisonChart.destroy();
      }

      // Prepare chart data
      const labels = data.map(item => item.month_name);
      const currentYearData = data.map(item => item.current_revenue / 1000000); // Convert to millions
      const previousYearData = data.map(item => item.previous_revenue / 1000000);

      yearlyComparisonChart = new Chart(ctx, {
        type: 'line',
        data: {
          labels: labels,
          datasets: [
            {
              label: data[0].current_year + ' Revenue',
              data: currentYearData,
              borderColor: '#3498db',
              backgroundColor: 'rgba(52, 152, 219, 0.1)',
              borderWidth: 3,
              fill: true,
              tension: 0.4,
              pointRadius: 5,
              pointHoverRadius: 7,
              pointBackgroundColor: '#3498db',
              pointBorderColor: '#fff',
              pointBorderWidth: 2
            },
            {
              label: data[0].previous_year + ' Revenue',
              data: previousYearData,
              borderColor: '#95a5a6',
              backgroundColor: 'rgba(149, 165, 166, 0.1)',
              borderWidth: 3,
              fill: true,
              tension: 0.4,
              pointRadius: 5,
              pointHoverRadius: 7,
              pointBackgroundColor: '#95a5a6',
              pointBorderColor: '#fff',
              pointBorderWidth: 2,
              borderDash: [5, 5]
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: {
            mode: 'index',
            intersect: false,
          },
          plugins: {
            legend: {
              display: true,
              position: 'top',
              labels: {
                boxWidth: 12,
                padding: 15,
                font: {
                  size: 12,
                  weight: '600'
                }
              }
            },
            tooltip: {
              enabled: true,
              backgroundColor: 'rgba(44, 62, 80, 0.95)',
              titleColor: '#fff',
              bodyColor: '#fff',
              titleFont: {
                size: 13,
                weight: 'bold'
              },
              bodyFont: {
                size: 12
              },
              padding: 12,
              borderColor: '#3498db',
              borderWidth: 2,
              displayColors: true,
              callbacks: {
                label: function(context) {
                  const value = context.raw.toFixed(2);
                  return `${context.dataset.label}: ₱${value}M`;
                },
                afterLabel: function(context) {
                  // Calculate growth percentage
                  const currentIndex = context.dataIndex;
                  const currentValue = currentYearData[currentIndex];
                  const previousValue = previousYearData[currentIndex];

                  if (previousValue > 0) {
                    const growth = ((currentValue - previousValue) / previousValue * 100).toFixed(1);
                    const arrow = growth >= 0 ? '↑' : '↓';
                    const color = growth >= 0 ? '+' : '';
                    return `${arrow} ${color}${growth}% YoY`;
                  }
                  return '';
                }
              }
            }
          },
          scales: {
            y: {
              beginAtZero: true,
              grid: {
                color: 'rgba(0, 0, 0, 0.05)',
                drawBorder: false
              },
              ticks: {
                callback: function(value) {
                  return '₱' + value.toFixed(1) + 'M';
                },
                font: {
                  size: 11
                },
                color: '#7f8c8d'
              }
            },
            x: {
              grid: {
                display: false,
                drawBorder: false
              },
              ticks: {
                font: {
                  size: 11
                },
                color: '#7f8c8d'
              }
            }
          }
        }
      });
    }

    // Refresh yearly comparison data
    function refreshYearlyComparison() {
      loadYearlyComparison().then(() => {
        showNotification('Yearly comparison data refreshed successfully!', '#e67e22');
      }).catch(error => {
        console.error('Error refreshing yearly comparison:', error);
      });
    }

    // Show yearly comparison error
    function showYearlyComparisonError() {
      console.error('Failed to load yearly comparison data');
    }

    // Helper function to show notifications
    function showNotification(message, color) {
      const notification = document.createElement('div');
      notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${color};
        color: white;
        padding: 12px 20px;
        border-radius: 6px;
        font-size: 14px;
        z-index: 1000;
        opacity: 0;
        transition: opacity 0.3s ease;
      `;
      notification.textContent = message;
      document.body.appendChild(notification);

      // Animate in
      setTimeout(() => notification.style.opacity = '1', 100);

      // Remove after 3 seconds
      setTimeout(() => {
        notification.style.opacity = '0';
        setTimeout(() => document.body.removeChild(notification), 300);
      }, 3000);
    }

    // Centralized export function for all reports
    function exportAllReports() {
      // Get current filter values
      const year = document.getElementById('report-year').value;
      const month = document.getElementById('report-month').value;

      // Build query parameters
      const params = new URLSearchParams({
        year: year,
        month: month
      });

      // Open PDF export in new window
      const url = 'sales_report_pdf.php?' + params.toString();
      window.open(url, '_blank');
    }

    // Initialize sales by model chart
    function initializeSalesByModelChart(data) {
      const ctx = document.getElementById('salesByModelChart').getContext('2d');
      
      // Destroy existing chart if it exists
      if (salesByModelChart) {
        salesByModelChart.destroy();
      }
      
      // Prepare chart data - aggregate by model name to avoid duplicates
      let labels, values, aggregatedData;
      
      if (data.length > 0) {
        // Aggregate data by model name to combine duplicate entries
        const modelMap = new Map();
        
        data.forEach(item => {
          const modelName = item.model_name;
          if (modelMap.has(modelName)) {
            // Add to existing model data
            const existing = modelMap.get(modelName);
            existing.units_sold += item.units_sold;
            existing.revenue += item.revenue;
          } else {
            // Create new model entry
            modelMap.set(modelName, {
              model_name: modelName,
              units_sold: item.units_sold,
              revenue: item.revenue
            });
          }
        });
        
        // Convert map to array and sort by units_sold
        aggregatedData = Array.from(modelMap.values())
          .sort((a, b) => b.units_sold - a.units_sold);
        
        labels = aggregatedData.map(item => item.model_name);
        values = aggregatedData.map(item => item.units_sold);
        
        console.log('Aggregated Chart Data:', { labels, values, aggregatedData });
      } else {
        labels = ['No Data'];
        values = [1];
        aggregatedData = [];
      }
      
      const colors = ['#3498db', '#27ae60', '#f39c12', '#9b59b6', '#e74c3c', '#1abc9c', '#34495e'];
      
      salesByModelChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
          labels: labels,
          datasets: [{
            data: values,
            backgroundColor: colors.slice(0, labels.length),
            borderWidth: 3,
            borderColor: '#fff',
            hoverBorderWidth: 4
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          // Fix potential rotation/mirroring issues
          rotation: 0,
          circumference: 360,
          plugins: {
            legend: {
              position: 'bottom',
              labels: {
                boxWidth: 14,
                padding: 20,
                font: {
                  size: 12,
                  weight: '600'
                },
                generateLabels: function(chart) {
                  const data = chart.data;
                  if (data.labels.length && data.datasets.length) {
                    return data.labels.map((label, i) => {
                      const meta = chart.getDatasetMeta(0);
                      const style = meta.controller.getStyle(i);
                      return {
                        text: label,
                        fillStyle: style.backgroundColor,
                        strokeStyle: style.borderColor,
                        lineWidth: style.borderWidth,
                        hidden: isNaN(data.datasets[0].data[i]) || meta.data[i].hidden,
                        index: i
                      };
                    });
                  }
                  return [];
                }
              }
            },
            tooltip: {
              enabled: false, // Disable default tooltip
              external: function(context) {
                // Custom tooltip implementation
                const chart = context.chart;
                const tooltip = context.tooltip;
                
                // Get or create custom tooltip element
                let tooltipEl = chart.canvas.parentNode.querySelector('.custom-tooltip');
                if (!tooltipEl) {
                  tooltipEl = document.createElement('div');
                  tooltipEl.className = 'custom-tooltip';
                  tooltipEl.style.cssText = `
                    position: absolute;
                    background: rgba(0,0,0,0.8);
                    color: white;
                    padding: 8px 12px;
                    border-radius: 4px;
                    border: 1px solid #3498db;
                    font-size: 12px;
                    pointer-events: none;
                    z-index: 1000;
                    transition: opacity 0.2s ease;
                  `;
                  chart.canvas.parentNode.appendChild(tooltipEl);
                }
                
                // Hide tooltip if not hovering
                if (tooltip.opacity === 0) {
                  tooltipEl.style.opacity = '0';
                  return;
                }
                
                // Get mouse position relative to chart
                const chartArea = chart.chartArea;
                const canvasPosition = Chart.helpers.getRelativePosition(tooltip, chart);
                
                // Calculate center of the doughnut
                const centerX = (chartArea.left + chartArea.right) / 2;
                const centerY = (chartArea.top + chartArea.bottom) / 2;
                
                const deltaX = canvasPosition.x - centerX;
                const deltaY = canvasPosition.y - centerY;
                
                // Calculate distance from center to check if we're in the doughnut area
                const distance = Math.sqrt(deltaX * deltaX + deltaY * deltaY);
                const outerRadius = Math.min(chartArea.right - chartArea.left, chartArea.bottom - chartArea.top) / 2;
                const innerRadius = outerRadius * 0.5; // Doughnut inner radius
                
                // Check if mouse is within the doughnut ring
                if (distance < innerRadius || distance > outerRadius) {
                  tooltipEl.style.opacity = '0';
                  return;
                }
                
                // Calculate angle from center to mouse position
                // Chart.js starts from top (12 o'clock) and goes clockwise
                let angle = Math.atan2(deltaX, -deltaY); // Note: Y is negated because canvas Y increases downward
                
                // Convert to degrees and normalize to 0-360 (starting from top)
                angle = (angle * 180 / Math.PI + 360) % 360;
                
                // Calculate cumulative angles for each segment
                const total = values.reduce((sum, val) => sum + val, 0);
                let startAngle = 0;
                let hoveredIndex = -1;
                
                for (let i = 0; i < values.length; i++) {
                  const segmentAngle = (values[i] / total) * 360;
                  const endAngle = startAngle + segmentAngle;
                  
                  // Check if current angle falls within this segment
                  if (angle >= startAngle && angle < endAngle) {
                    hoveredIndex = i;
                    break;
                  }
                  
                  startAngle = endAngle;
                }
                
                // Fallback: if no segment found, find the closest one
                if (hoveredIndex === -1) {
                  let minDistance = 360;
                  startAngle = 0;
                  
                  for (let i = 0; i < values.length; i++) {
                    const segmentAngle = (values[i] / total) * 360;
                    const midAngle = startAngle + segmentAngle / 2;
                    const distance = Math.min(
                      Math.abs(angle - midAngle),
                      Math.abs(angle - midAngle + 360),
                      Math.abs(angle - midAngle - 360)
                    );
                    
                    if (distance < minDistance) {
                      minDistance = distance;
                      hoveredIndex = i;
                    }
                    
                    startAngle += segmentAngle;
                  }
                }
                
                // Ensure we have a valid index
                if (hoveredIndex === -1 || hoveredIndex >= values.length) {
                  hoveredIndex = 0;
                }
                
                // Get data for the hovered segment
                const modelName = labels[hoveredIndex] || 'Unknown';
                const units = values[hoveredIndex] || 0;
                const percentage = total > 0 ? ((units / total) * 100).toFixed(1) : '0.0';
                
                // Update tooltip content
                tooltipEl.innerHTML = `${modelName}: ${units} units (${percentage}%)`;
                
                // Position tooltip near mouse
                const canvasRect = chart.canvas.getBoundingClientRect();
                tooltipEl.style.opacity = '1';
                tooltipEl.style.left = canvasRect.left + canvasPosition.x + 10 + 'px';
                tooltipEl.style.top = canvasRect.top + canvasPosition.y - 30 + 'px';
                
                // Enhanced debug log
                console.log('Custom Tooltip Debug:', {
                  angle: angle.toFixed(1) + '°',
                  hoveredIndex,
                  modelName,
                  units,
                  percentage: percentage + '%',
                  distance: distance.toFixed(1),
                  innerRadius: innerRadius.toFixed(1),
                  outerRadius: outerRadius.toFixed(1),
                  mousePos: { x: canvasPosition.x.toFixed(1), y: canvasPosition.y.toFixed(1) },
                  center: { x: centerX.toFixed(1), y: centerY.toFixed(1) },
                  segmentRanges: (() => {
                    let start = 0;
                    return values.map((val, i) => {
                      const segAngle = (val / total) * 360;
                      const range = `${start.toFixed(1)}° - ${(start + segAngle).toFixed(1)}°`;
                      start += segAngle;
                      return `${labels[i]}: ${range}`;
                    });
                  })()
                });
              }
            }
          }
        }
      });
    }

    // Initialize revenue chart as bar graph
    function initializeRevenueChart(data) {
      const ctx = document.getElementById('revenueChart').getContext('2d');

      // Destroy existing chart if it exists
      if (revenueChart) {
        revenueChart.destroy();
      }

      // Prepare chart data
      const labels = data.length > 0 ? data.map(item => item.month_name) : ['No Data'];
      const values = data.length > 0 ? data.map(item => (item.revenue / 1000000)) : [0]; // Convert to millions

      // Create gradient for bars
      const gradient = ctx.createLinearGradient(0, 0, 0, 400);
      gradient.addColorStop(0, 'rgba(52, 152, 219, 0.9)');
      gradient.addColorStop(1, 'rgba(52, 152, 219, 0.6)');

      // Create dynamic colors based on growth
      const backgroundColors = values.map((value, index) => {
        if (index === 0) return 'rgba(52, 152, 219, 0.8)';
        const previous = values[index - 1];
        if (value > previous) {
          return 'rgba(39, 174, 96, 0.8)'; // Green for growth
        } else if (value < previous) {
          return 'rgba(231, 76, 60, 0.8)'; // Red for decline
        } else {
          return 'rgba(52, 152, 219, 0.8)'; // Blue for no change
        }
      });

      const borderColors = values.map((value, index) => {
        if (index === 0) return '#3498db';
        const previous = values[index - 1];
        if (value > previous) {
          return '#27ae60'; // Green for growth
        } else if (value < previous) {
          return '#e74c3c'; // Red for decline
        } else {
          return '#3498db'; // Blue for no change
        }
      });

      revenueChart = new Chart(ctx, {
        type: 'bar',
        data: {
          labels: labels,
          datasets: [{
            label: 'Revenue',
            data: values,
            backgroundColor: backgroundColors,
            borderColor: borderColors,
            borderWidth: 2,
            borderRadius: 8,
            borderSkipped: false,
            hoverBackgroundColor: values.map((value, index) => {
              if (index === 0) return 'rgba(52, 152, 219, 1)';
              const previous = values[index - 1];
              if (value > previous) return 'rgba(39, 174, 96, 1)';
              else if (value < previous) return 'rgba(231, 76, 60, 1)';
              else return 'rgba(52, 152, 219, 1)';
            }),
            hoverBorderColor: borderColors,
            hoverBorderWidth: 3
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: {
            mode: 'index',
            intersect: false,
          },
          plugins: {
            legend: {
              display: false
            },
            tooltip: {
              enabled: true,
              backgroundColor: 'rgba(44, 62, 80, 0.95)',
              titleColor: '#fff',
              bodyColor: '#fff',
              titleFont: {
                size: 13,
                weight: 'bold'
              },
              bodyFont: {
                size: 12
              },
              padding: 12,
              borderColor: '#3498db',
              borderWidth: 2,
              displayColors: true,
              callbacks: {
                title: function(context) {
                  return context[0].label;
                },
                label: function(context) {
                  const value = context.raw.toFixed(2);
                  return `Revenue: ₱${value}M`;
                },
                afterLabel: function(context) {
                  // Show growth percentage if not first point
                  if (context.dataIndex > 0) {
                    const current = context.raw;
                    const previous = context.dataset.data[context.dataIndex - 1];
                    const growth = ((current - previous) / previous * 100).toFixed(1);
                    const arrow = growth >= 0 ? '↑' : '↓';
                    const color = growth >= 0 ? '+' : '';
                    return `${arrow} ${color}${growth}% from previous month`;
                  }
                  return '';
                }
              }
            }
          },
          scales: {
            y: {
              beginAtZero: true,
              grid: {
                color: 'rgba(0, 0, 0, 0.05)',
                drawBorder: false,
                lineWidth: 1
              },
              border: {
                display: false
              },
              ticks: {
                callback: function(value) {
                  return '₱' + value.toFixed(1) + 'M';
                },
                font: {
                  size: 11,
                  family: "'Inter', 'Segoe UI', sans-serif"
                },
                color: '#7f8c8d',
                padding: 8
              }
            },
            x: {
              grid: {
                display: false,
                drawBorder: false
              },
              border: {
                display: false
              },
              ticks: {
                font: {
                  size: 11,
                  family: "'Inter', 'Segoe UI', sans-serif"
                },
                color: '#7f8c8d',
                padding: 8
              }
            }
          },
          animation: {
            duration: 1500,
            easing: 'easeInOutQuart',
            delay: (context) => {
              let delay = 0;
              if (context.type === 'data' && context.mode === 'default') {
                delay = context.dataIndex * 100;
              }
              return delay;
            }
          }
        }
      });
    }

    // Populate performance table
    function populatePerformanceTable(data) {
      const tbody = document.getElementById('performanceTableBody');
      
      if (data.length === 0) {
        tbody.innerHTML = `
          <tr>
            <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-light);">
              <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
              <p>No sales data available for the selected period</p>
            </td>
          </tr>
        `;
        return;
      }
      
      tbody.innerHTML = data.map(item => {
        const stockStatusClass = item.stock_status.toLowerCase().replace(' ', '-');
        const modelInitials = item.model_name.split(' ').map(word => word.charAt(0)).join('').toUpperCase();
        
        return `
          <tr>
            <td>
              <div class="model-info-enhanced">
                <div class="model-image">${modelInitials}</div>
                <div class="model-details">
                  <span class="model-name">${item.model_name}</span>
                  <span class="model-variant">Current Model</span>
                </div>
              </div>
            </td>
            <td>
              <div class="performance-metric">
                <span class="metric-value">${item.units_sold}</span>
                <span class="metric-label">${item.market_share}% of total</span>
              </div>
            </td>
            <td class="metric-value">${formatCurrency(item.revenue)}</td>
            <td>
              <div class="performance-metric">
                <span class="metric-value">${item.market_share}%</span>
                <span class="metric-label">Market share</span>
              </div>
            </td>
            <td class="metric-value">${formatCurrency(item.avg_price)}</td>
            <td>
              <span class="status-indicator ${stockStatusClass}">
                <i class="fas fa-circle"></i> ${item.stock_status} (${item.current_stock})
              </span>
            </td>
            <td>
              <div class="order-actions-enhanced">
                <button class="btn-small btn-view" title="View Details" onclick="viewModelDetails('${item.model_name}')">
                  <i class="fas fa-eye"></i>
                </button>
              
              </div>
            </td>
          </tr>
        `;
      }).join('');
    }

    // Utility functions
    function formatCurrency(amount) {
      if (amount === null || amount === undefined || isNaN(amount)) {
        return '₱0';
      }
      
      // Convert to millions if amount is large
      if (amount >= 1000000) {
        return '₱' + (amount / 1000000).toFixed(1) + 'M';
      } else if (amount >= 1000) {
        return '₱' + (amount / 1000).toFixed(0) + 'K';
      } else {
        return '₱' + amount.toLocaleString();
      }
    }

    function updateGrowthIndicator(elementId, growth) {
      const element = document.getElementById(elementId);
      if (!element) return;
      
      const isPositive = growth >= 0;
      const iconClass = isPositive ? 'fa-arrow-up' : 'fa-arrow-down';
      const textClass = isPositive ? 'positive' : 'negative';
      const sign = isPositive ? '+' : '';
      
      element.className = `kpi-change ${textClass}`;
      element.innerHTML = `
        <i class="fas ${iconClass}"></i> ${sign}${growth.toFixed(1)}% vs last month
      `;
    }

    function showKPIError() {
      const kpiCards = ['totalRevenue', 'unitsSold', 'inventoryUnits', 'totalTransactions', 'completedOrders', 'avgOrderValue'];
      kpiCards.forEach(id => {
        const element = document.getElementById(id);
        if (element) {
          element.textContent = 'Error';
        }
      });
      
      const growthCards = ['revenueGrowth', 'unitsGrowth', 'transactionsGrowth', 'inventoryStatus', 'completionRate', 'avgOrderGrowth'];
      growthCards.forEach(id => {
        const element = document.getElementById(id);
        if (element) {
          element.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Error loading';
        }
      });
    }

    function showTableError() {
      const tbody = document.getElementById('performanceTableBody');
      if (tbody) {
        tbody.innerHTML = `
          <tr>
            <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-light);">
              <i class="fas fa-exclamation-triangle" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5; color: #e74c3c;"></i>
              <p>Error loading performance data. Please try refreshing the page.</p>
            </td>
          </tr>
        `;
      }
    }

    function showErrorMessage(message) {
      Swal.fire({
        title: 'Error',
        text: message,
        icon: 'error',
        confirmButtonColor: '#e74c3c'
      });
    }

    function generateReport() {
      const year = document.getElementById('report-year').value;
      const month = document.getElementById('report-month').value;
      const type = document.getElementById('report-type').value;
      
      Swal.fire({
        title: 'Generating Report...',
        text: 'Please wait while we compile your report data',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => {
          Swal.showLoading();
        }
      });
      
      // Calculate date range based on selection
      let startDate, endDate;
      if (month === 'all') {
        startDate = `${year}-01-01`;
        endDate = `${year}-12-31`;
      } else {
        const monthPadded = month.padStart(2, '0');
        startDate = `${year}-${monthPadded}-01`;
        const lastDay = new Date(year, month, 0).getDate();
        endDate = `${year}-${monthPadded}-${lastDay.toString().padStart(2, '0')}`;
      }
      
      // Reload data with new filters
      Promise.all([
        fetch(`../../api/sales-report.php?action=summary&start_date=${startDate}&end_date=${endDate}`),
        fetch(`../../api/sales-report.php?action=by-model&start_date=${startDate}&end_date=${endDate}`),
        fetch(`../../api/sales-report.php?action=revenue-trend&year=${year}`)
      ]).then(async responses => {
        const [summaryData, modelData, trendData] = await Promise.all(
          responses.map(r => r.json())
        );
        
        // Update dashboard with filtered data
        if (!summaryData.error) {
          // Update KPI values with filtered data
          document.getElementById('totalRevenue').textContent = formatCurrency(summaryData.total_revenue);
          document.getElementById('unitsSold').textContent = summaryData.units_sold;
          document.getElementById('totalTransactions').textContent = summaryData.total_transactions;
          document.getElementById('completedOrders').textContent = summaryData.completed_orders;
          
          updateGrowthIndicator('revenueGrowth', summaryData.revenue_growth);
          updateGrowthIndicator('unitsGrowth', summaryData.units_growth);
          updateGrowthIndicator('transactionsGrowth', summaryData.transactions_growth);
        }
        
        if (!modelData.error) {
          initializeSalesByModelChart(modelData);
          populatePerformanceTable(modelData);
        }
        
        if (!trendData.error) {
          initializeRevenueChart(trendData);
        }
        
        Swal.fire({
          title: 'Report Generated!',
          html: `
            <div style="text-align: center;">
              <h3 style="color: #27ae60; margin: 10px 0;">Sales Report</h3>
              <p><strong>Period:</strong> ${month === 'all' ? 'Full Year' : 'Month ' + month} ${year}</p>
              <p><strong>Type:</strong> ${type.charAt(0).toUpperCase() + type.slice(1)} Report</p>
              <p><strong>Generated:</strong> ${new Date().toLocaleDateString()}</p>
            </div>
          `,
          icon: 'success',
          confirmButtonColor: '#27ae60'
        });
      }).catch(error => {
        console.error('Error generating report:', error);
        Swal.fire({
          title: 'Generation Failed',
          text: 'Unable to generate report. Please try again.',
          icon: 'error',
          confirmButtonColor: '#e74c3c'
        });
      });
    }

    function viewModelDetails(model) {
      Swal.fire({
        title: 'Model Performance Details',
        html: `
          <div style="text-align: left;">
            <h4>${model.charAt(0).toUpperCase() + model.slice(1)} Performance</h4>
            <p><strong>Top Selling Variant:</strong> GLS Premium</p>
            <p><strong>Average Sale Price:</strong> ₱2,398,000</p>
            <p><strong>Best Sales Month:</strong> February 2024</p>
            <p><strong>Customer Satisfaction:</strong> 4.8/5</p>
            <p><strong>Inventory Turnover:</strong> 12.5 days</p>
          </div>
        `,
        icon: 'info',
        confirmButtonColor: '#3498db'
      });
    }

    function generateModelReport(model) {
      Swal.fire({
        title: 'Generate Model Report',
        text: `Generate detailed report for ${model}?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3498db',
        cancelButtonColor: '#95a5a6',
        confirmButtonText: 'Generate Report',
        cancelButtonText: 'Cancel'
      }).then((result) => {
        if (result.isConfirmed) {
          Swal.fire({
            title: 'Report Generated!',
            text: `Detailed report for ${model} has been generated and will be downloaded shortly.`,
            icon: 'success',
            confirmButtonColor: '#27ae60'
          });
        }
      });
    }

    function refreshData() {
      Swal.fire({
        title: 'Refreshing Data...',
        text: 'Fetching latest sales information',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => {
          Swal.showLoading();
        }
      });
      
      // Reload all dashboard data including agent performance
      loadDashboardData().then(() => {
        Swal.fire({
          title: 'Data Refreshed!',
          text: 'Sales report has been updated with the latest information',
          icon: 'success',
          confirmButtonColor: '#3498db',
          timer: 1500
        });
      }).catch(error => {
        Swal.fire({
          title: 'Refresh Failed',
          text: 'Unable to refresh data. Please try again.',
          icon: 'error',
          confirmButtonColor: '#e74c3c'
        });
      });
    }



    // ...existing code...
  </script>
</body>
</html>
