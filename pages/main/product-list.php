<?php
include_once(dirname(dirname(__DIR__)) . '/includes/init.php');

// Check if user is Admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: ../../pages/login.php");
    exit();
}

// Use the database connection from init.php
$pdo = $GLOBALS['pdo'] ?? null;

// Check if database connection exists
if (!$pdo) {
  die("Database connection not available. Please check your database configuration.");
}

// Fetch vehicles for dropdowns
try {
  $stmt = $pdo->query("SELECT * FROM vehicles ORDER BY model_name ASC");
  $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  // Debug: Check what we got from database
  error_log("Product-list.php: Fetched " . count($vehicles) . " vehicles from database");
  if (count($vehicles) > 0) {
    error_log("Product-list.php: First vehicle ID: " . $vehicles[0]['id']);
  } else {
    error_log("Product-list.php: No vehicles found in database");
  }
  
  // Get unique model names for filter dropdown
  $modelStmt = $pdo->query("SELECT DISTINCT model_name FROM vehicles ORDER BY model_name ASC");
  $uniqueModels = $modelStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
  $vehicles = [];
  $uniqueModels = [];
  error_log("Product-list.php: Error fetching data: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Product Deliveries - Mitsubishi</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link href="../../includes/css/common-styles.css" rel="stylesheet">
  <link href="../../includes/css/orders-styles.css" rel="stylesheet">
  <style>
    html, body {
      height: 100%;
      width: 100%;
      margin: 0;
      padding: 0;
    }
    
    body {
      zoom: 85%;
    }

    .delivery-stats {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }

    .stat-card {
      background: white;
      border-radius: 12px;
      padding: 25px;
      box-shadow: 0 4px 6px rgba(0,0,0,0.05);
      border-left: 4px solid var(--primary-red);
      display: flex;
      align-items: center;
      gap: 20px;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .stat-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(0,0,0,0.1);
    }

    .stat-icon {
      width: 60px;
      height: 60px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      color: white;
    }

    .stat-icon.red { background: linear-gradient(135deg, #d60000, #ff4444); }
    .stat-icon.blue { background: linear-gradient(135deg, #1e40af, #3b82f6); }
    .stat-icon.green { background: linear-gradient(135deg, #059669, #10b981); }
    .stat-icon.orange { background: linear-gradient(135deg, #ea580c, #f97316); }

    .delivery-actions-enhanced {
      display: flex;
      gap: 8px;
      justify-content: center;
    }

    .delivery-info {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }

    .delivery-id {
      font-weight: 600;
      color: var(--primary-red);
      font-size: 0.95rem;
    }

    .delivery-date {
      color: var(--text-light);
      font-size: 0.85rem;
    }

    .vehicle-info {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }

    .vehicle-model {
      font-weight: 600;
      color: var(--text-dark);
    }

    .vehicle-details {
      color: var(--text-light);
      font-size: 0.85rem;
    }

    .units-badge {
      background: var(--primary-red);
      color: white;
      padding: 4px 12px;
      border-radius: 20px;
      font-weight: 600;
      font-size: 0.85rem;
      text-align: center;
      min-width: 60px;
    }

    .price {
      font-weight: 700;
      color: var(--success-green);
      font-size: 1.1rem;
    }

    .status-badge {
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 0.8rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .status-badge.delivered {
      background: rgba(16, 185, 129, 0.1);
      color: var(--success-green);
      border: 1px solid rgba(16, 185, 129, 0.3);
    }

    .status-badge.pending {
      background: rgba(245, 158, 11, 0.1);
      color: #d97706;
      border: 1px solid rgba(245, 158, 11, 0.3);
    }

    .status-badge.cancelled {
      background: rgba(239, 68, 68, 0.1);
      color: #dc2626;
      border: 1px solid rgba(239, 68, 68, 0.3);
    }

    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    .modal-body {
      max-height: 70vh;
      overflow-y: auto;
    }

    .form-section {
      margin-bottom: 25px;
      padding-bottom: 20px;
      border-bottom: 1px solid #eee;
    }

    .form-section:last-child {
      border-bottom: none;
      margin-bottom: 0;
    }

    .form-section h3 {
      color: var(--primary-red);
      margin-bottom: 15px;
      font-size: 1.1rem;
      font-weight: 600;
    }

    .search-account-wrapper {
      position: relative;
      width: 100%;
    }

    .search-results {
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      background: white;
      border: 1px solid #ddd;
      border-radius: 4px;
      z-index: 1000;
      max-height: 200px;
      overflow-y: auto;
      display: none;
    }

    .search-result-item {
      padding: 10px;
      cursor: pointer;
      transition: background 0.2s;
    }

    .search-result-item:hover {
      background: rgba(0, 0, 0, 0.05);
    }

    .search-no-results, .search-error {
      padding: 10px;
      text-align: center;
      color: #666;
    }

    .search-loading {
      padding: 10px;
      text-align: center;
      color: var(--primary-red);
    }

    .account-info-display {
      background: #f9f9f9;
      padding: 15px;
      border-radius: 8px;
      margin-top: 10px;
    }

    .info-row {
      display: flex;
      justify-content: space-between;
      margin-bottom: 8px;
    }

    .info-label {
      font-weight: 500;
      color: var(--text-dark);
    }

    .info-value {
      color: var(--text-light);
      overflow-wrap: break-word;
      max-width: 60%;
    }

    /* Vehicle Selection Modal Styles */
    .vehicle-cards-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
      gap: 20px;
      padding: 10px 0;
    }

    .vehicle-card {
      background: white;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 4px 6px rgba(0,0,0,0.1);
      transition: all 0.3s ease;
      cursor: pointer;
      border: 2px solid transparent;
      position: relative;
    }

    .vehicle-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 25px rgba(0,0,0,0.15);
      border-color: var(--primary-red);
    }

    .vehicle-card.selected {
      border-color: var(--primary-red);
      background: rgba(214, 0, 0, 0.05);
    }

    .checkmark {
      position: absolute;
      top: 15px;
      right: 15px;
      width: 25px;
      height: 25px;
      border-radius: 50%;
      background: var(--primary-red);
      display: none;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 16px;
      z-index: 10;
    }

    .vehicle-card.selected .checkmark {
      display: flex;
    }

    .checkmark::before {
      content: '✓';
    }

    .vehicle-image {
      width: 100%;
      height: 200px;
      background: linear-gradient(135deg, #f0f0f0, #e0e0e0);
      display: flex;
      align-items: center;
      justify-content: center;
      color: #666;
      font-size: 48px;
      position: relative;
    }

    .vehicle-image img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .card-image {
      width: 100%;
      height: 200px;
      background: linear-gradient(135deg, #f0f0f0, #e0e0e0);
      display: flex;
      align-items: center;
      justify-content: center;
      color: #666;
      font-size: 48px;
      position: relative;
    }

    .card-image img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .card-content {
      padding: 20px;
    }

    .card-header {
      margin-bottom: 10px;
    }

    .model-name {
      font-size: 1.3rem;
      font-weight: 700;
      color: var(--text-dark);
      margin-bottom: 5px;
    }

    .variant {
      color: var(--primary-red);
      font-weight: 600;
      margin-bottom: 10px;
      font-size: 1rem;
    }

    .year-category {
      display: flex;
      justify-content: space-between;
      margin-bottom: 15px;
      font-size: 0.9rem;
      color: var(--text-light);
    }

    .price-section {
      margin-bottom: 15px;
    }

    .base-price {
      font-size: 1.2rem;
      font-weight: 700;
      color: var(--success-green);
    }

    .promotional-price {
      font-size: 1.1rem;
      font-weight: 600;
      color: var(--primary-red);
      margin-top: 5px;
    }

    .specs-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 8px;
      margin-bottom: 15px;
    }

    .spec-item {
      display: flex;
      flex-direction: column;
      gap: 2px;
    }

    .spec-label {
      font-size: 0.75rem;
      color: var(--text-light);
      text-transform: uppercase;
      font-weight: 600;
    }

    .spec-value {
      font-size: 0.85rem;
      color: var(--text-dark);
    }

    .features {
      margin-bottom: 15px;
    }

    .features h4 {
      font-size: 0.9rem;
      color: var(--text-dark);
      margin-bottom: 5px;
    }

    .features-list {
      font-size: 0.8rem;
      color: var(--text-light);
      line-height: 1.4;
    }

    .card-footer {
      border-top: 1px solid #eee;
      padding-top: 15px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .stock-status {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 0.85rem;
      font-weight: 600;
    }

    .status-indicator {
      width: 8px;
      height: 8px;
      border-radius: 50%;
    }

    .available .status-indicator {
      background-color: var(--success-green);
    }

    .low-stock .status-indicator {
      background-color: #f59e0b;
    }

    .out-of-stock .status-indicator {
      background-color: #ef4444;
    }

    .search-wrapper {
      position: relative;
      display: flex;
      align-items: center;
    }

    .search-icon {
      position: absolute;
      left: 15px;
      color: #666;
      z-index: 1;
    }

    .vehicle-search-input {
      width: 100%;
      padding: 12px 15px 12px 45px;
      border: 2px solid #e0e0e0;
      border-radius: 8px;
      font-size: 1rem;
      transition: border-color 0.3s ease;
    }

    .vehicle-search-input:focus {
      outline: none;
      border-color: var(--primary-red);
    }

    .cards-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
      gap: 20px;
    }

    .cards-grid.limited-width {
      max-width: 800px;
      margin: 0 auto;
    }

    .no-vehicles {
      text-align: center;
      padding: 40px;
      color: var(--text-light);
    }

    .loading-vehicles {
      text-align: center;
      padding: 40px;
      color: var(--primary-red);
    }
    
    /* Ensure modal styles are properly defined */
    .modal-overlay {
      position: fixed !important;
      top: 0 !important;
      left: 0 !important;
      width: 100% !important;
      height: 100% !important;
      background-color: rgba(0, 0, 0, 0.5) !important;
      display: none;
      justify-content: center !important;
      align-items: center !important;
      z-index: 10000 !important;
      backdrop-filter: blur(4px) !important;
    }

    .modal-overlay.active {
      display: flex;
    }

    .modal {
      background: white !important;
      border-radius: 12px !important;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3) !important;
      width: 90% !important;
      max-width: 900px !important;
      max-height: 90vh !important;
      overflow: hidden !important;
      animation: modalSlideIn 0.3s ease !important;
      display: flex !important;
      flex-direction: column !important;
    }

    @keyframes modalSlideIn {
      from {
        opacity: 0;
        transform: translateY(-50px) scale(0.9);
      }
      to {
        opacity: 1;
        transform: translateY(0) scale(1);
      }
    }

    .modal-header {
      padding: 20px 25px !important;
      border-bottom: 1px solid #e9ecef !important;
      display: flex !important;
      justify-content: space-between !important;
      align-items: center !important;
      background: #f8f9fa !important;
      flex-shrink: 0 !important;
    }

    .modal-header h3 {
      font-size: 1.5rem !important;
      color: #333 !important;
      margin: 0 !important;
    }

    .modal-close {
      background: none !important;
      border: none !important;
      font-size: 1.5rem !important;
      cursor: pointer !important;
      color: #666 !important;
      transition: color 0.3s ease !important;
    }

    .modal-close:hover {
      color: #d60000 !important;
    }

    .modal-body {
      overflow-y: auto !important;
      flex: 1 !important;
      padding: 25px !important;
    }

    .modal-footer {
      padding: 15px 25px !important;
      border-top: 1px solid #e9ecef !important;
      display: flex !important;
      justify-content: flex-end !important;
      gap: 10px !important;
      flex-shrink: 0 !important;
      background: white !important;
    }

    /* Additional modal styling fixes */
    .form-section {
      margin-bottom: 25px;
    }

    .form-section h3 {
      color: var(--primary-red);
      font-size: 1.1rem;
      margin-bottom: 15px;
      padding-bottom: 8px;
      border-bottom: 2px solid #f1f3f4;
    }

    .form-row {
      display: flex;
      gap: 20px;
      margin-bottom: 15px;
    }

    .form-group {
      flex: 1;
    }

    .form-group label {
      display: block;
      margin-bottom: 8px;
      font-weight: 600;
      color: #333;
    }

    .form-control {
      width: 100%;
      padding: 12px;
      border: 2px solid #e0e0e0;
      border-radius: 8px;
      font-size: 1rem;
      transition: border-color 0.3s ease;
    }

    .form-control:focus {
      outline: none;
      border-color: var(--primary-red);
      box-shadow: 0 0 0 3px rgba(214, 0, 0, 0.1);
    }

    .form-control[readonly] {
      background-color: #f8f9fa;
      color: #6c757d;
    }

    .vehicle-selection-wrapper {
      position: relative;
    }

    .btn {
      padding: 10px 20px;
      border: none;
      border-radius: 6px;
      font-size: 1rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
    }

    .btn-primary {
      background-color: var(--primary-red);
      color: white;
    }

    .btn-primary:hover {
      background-color: #b30000;
      transform: translateY(-1px);
    }

    .btn-secondary {
      background-color: #6c757d;
      color: white;
    }

    .btn-secondary:hover {
      background-color: #545b62;
    }

    /* Autocomplete dropdown styles for vehicle model field */
    .vehicle-autocomplete-list { position: absolute; top: 100%; left: 0; right: 0; z-index: 1000; background: #fff; border: 1px solid #e5e7eb; border-top: none; max-height: 280px; overflow-y: auto; box-shadow: 0 8px 24px rgba(0,0,0,0.12); border-radius: 0 0 8px 8px; }
    .vehicle-autocomplete-list .autocomplete-item { padding: 10px 12px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f0f0f0; }
    .vehicle-autocomplete-list .autocomplete-item:last-child { border-bottom: none; }
    .vehicle-autocomplete-list .autocomplete-item:hover { background: #f8fafc; }
    .vehicle-autocomplete-list .autocomplete-item .year { color: #6b7280; font-size: 0.85rem; }
    .vehicle-autocomplete-list .autocomplete-item.no-result { color: #6b7280; cursor: default; }
  </style>
</head>
<body>
  <?php include '../../includes/components/sidebar.php'; ?>

  <div class="main">
    <?php include '../../includes/components/topbar.php'; ?>

    <div class="main-content">
      <div class="page-header">
        <h1 class="page-title">
          <i class="fas fa-truck icon-gradient"></i>
          Product Deliveries Management
        </h1>
        <button class="add-order-btn" id="addNewDeliveryBtn" style="padding: 12px 24px; font-size: 1rem;">
          <i class="fas fa-plus-circle"></i> Add New Delivery
        </button>
      </div>

      <!-- Delivery Statistics -->
      <div class="delivery-stats">
        <div class="stat-card">
          <div class="stat-icon red">
            <i class="fas fa-truck"></i>
          </div>
          <div class="stat-info">
            <h3 id="totalDeliveries">0</h3>
            <p>Total Deliveries</p>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon blue">
            <i class="fas fa-calendar-day"></i>
          </div>
          <div class="stat-info">
            <h3 id="todayDeliveries">0</h3>
            <p>Today's Deliveries</p>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon orange">
            <i class="fas fa-clock"></i>
          </div>
          <div class="stat-info">
            <h3 id="pendingDeliveries">0</h3>
            <p>Pending Deliveries</p>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon green">
            <i class="fas fa-peso-sign"></i>
          </div>
          <div class="stat-info">
            <h3 id="monthlyValue">₱0</h3>
            <p>Monthly Value</p>
          </div>
        </div>
      </div>

      <!-- Filters Section -->
      <div class="filters-section">
        <div class="filter-row">
          <div class="filter-group">
            <label for="delivery-search">Search Deliveries</label>
            <input type="text" id="delivery-search" class="filter-input" placeholder="Delivery ID, Model, or Customer">
          </div>
          <div class="filter-group">
            <label for="vehicle-model">Vehicle Model</label>
            <select id="vehicle-model" class="filter-select">
              <option value="all">All Models</option>
              <?php foreach ($uniqueModels as $model): ?>
                <option value="<?php echo htmlspecialchars($model); ?>"><?php echo htmlspecialchars($model); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="filter-group">
            <label for="delivery-date-range">Date Range</label>
            <select id="delivery-date-range" class="filter-select">
              <option value="all">All Time</option>
              <option value="today">Today</option>
              <option value="week">This Week</option>
              <option value="month">This Month</option>
            </select>
          </div>
          <button class="filter-btn" id="applyFiltersBtn">Apply Filters</button>
        </div>
      </div>

      <!-- Deliveries Table -->
      <div class="client-orders-section">
        <div class="section-header">
          <h2 class="section-title">
            <i class="fas fa-list"></i>
            <span id="sectionTitle">All Deliveries</span>
          </h2>
        </div>
        <div class="table-responsive">
          <table class="table">
            <thead>
              <tr>
                <th>Delivery Information</th>
                <th>Vehicle Details</th>
                <th>Units Delivered</th>
                <th>Unit Price</th>
                <th>Total Value</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="deliveriesTableBody">
              <tr>
                <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-light);">
                  <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
                  <p>No deliveries found</p>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>


  <!-- Delivery Form Modal -->

  <div class="modal-overlay" id="deliveryFormModal">
    <div class="modal">
      <div class="modal-header">
        <h3 id="modalTitle">Add New Delivery</h3>
        <button class="modal-close" onclick="closeDeliveryModal()">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <form id="deliveryForm">
        <div class="modal-body">
          <input type="hidden" id="deliveryID" name="id">
          
          <div class="form-section">
            <h3>Delivery Information</h3>
            <div class="form-row">
              <div class="form-group">
                <label for="delivery_date">Delivery Date *</label>
                <input type="date" id="delivery_date" name="delivery_date" class="form-control" required>
              </div>
            </div>
          </div>

          <div class="form-section">
            <h3>Vehicle Information</h3>
            <div class="form-row">
               <div class="form-group">
                <label for="vehicle_model">Vehicle Model *</label>
                <div class="vehicle-selection-wrapper">
                  <input type="text" id="vehicle_model_display" class="form-control" placeholder="Type to search vehicle model..." autocomplete="off" style="cursor: text;">
                  <div id="vehicleAutocomplete" class="vehicle-autocomplete-list" style="display: none;"></div>
                  <input type="hidden" id="vehicle_id" name="vehicle_id">
                  <input type="hidden" id="model_name" name="model_name">
                  <input type="hidden" id="variant" name="variant">
                  <input type="hidden" id="color" name="color">
                </div>
              </div>
            </div>
          </div>

          <div class="form-section">
            <h3>Delivery Details</h3>
            <div class="form-row">
              <div class="form-group">
                <label for="units_delivered">Units Delivered *</label>
                <input type="number" id="units_delivered" name="units_delivered" class="form-control" min="1" required
                onkeydown="if(event.key === 'e' || event.key === 'E') event.preventDefault();" />
              </div>
              <div class="form-group">
                <label for="unit_price">Unit Price (₱) *</label>
                <input type="number" id="unit_price" name="unit_price" class="form-control" step="0.01" required
                onkeydown="if(event.key === 'e' || event.key === 'E') event.preventDefault();" />
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label for="total_value">Total Value (₱)</label>
                <input type="number" id="total_value" name="total_value" class="form-control" step="0.01" readonly>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" onclick="closeDeliveryModal()">Cancel</button>
          <button type="submit" class="btn btn-primary">
            <span id="submitBtnText">Save Delivery</span>
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Vehicle Selection Modal -->
  <div class="modal-overlay" id="vehicleSelectionModal">
    <div class="modal vehicle-selection-modal" style="max-width: 1200px; width: 95%;">
      <div class="modal-header">
        <h3>Select Vehicle</h3>
        <button class="modal-close" onclick="closeVehicleSelectionModal()">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div class="modal-body" style="max-height: 100%; padding: 0; overflow: hidden; display: flex; flex-direction: column; flex: 1;">
        <div class="vehicle-search-section" style="padding: 20px 25px; margin-bottom: 15px;">
          <div class="search-wrapper">
            <i class="fas fa-search search-icon"></i>
            <input type="text" id="vehicleSearchInput" class="vehicle-search-input" placeholder="Search by model name...">
          </div>
        </div>
        <div style="overflow-y: auto; padding: 0 25px 25px;">
          <?php if (empty($vehicles)): ?>
            <div class="no-vehicles">
              <p>No vehicles found. Please add some vehicles to the database.</p>
            </div>
          <?php else: ?>
            <div class="cards-grid <?php echo (count($vehicles) <= 2) ? 'limited-width' : ''; ?>">
              <?php foreach ($vehicles as $vehicle): ?>
                <div class="vehicle-card" data-vehicle-id="<?php echo $vehicle['id']; ?>" onclick="selectVehicleFromCard(<?php echo $vehicle['id']; ?>)">
                  <div class="checkmark"></div>
                  <div class="card-image">
                    <?php if (!empty($vehicle['main_image'])): ?>
                      <?php 
                      // Check if it's a file path or base64 data
                      if (strpos($vehicle['main_image'], 'uploads') !== false || strpos($vehicle['main_image'], '.png') !== false || strpos($vehicle['main_image'], '.jpg') !== false || strpos($vehicle['main_image'], '.jpeg') !== false) {
                          // It's a file path - convert to web path
                          $webPath = str_replace('\\', '/', $vehicle['main_image']);
                          $webPath = preg_replace('/^.*\/htdocs\//', '/', $webPath);
                          echo '<img src="' . htmlspecialchars($webPath) . '" alt="' . htmlspecialchars($vehicle['model_name']) . '">';
                      } else if (preg_match('/^[A-Za-z0-9+\/=]+$/', $vehicle['main_image']) && strlen($vehicle['main_image']) > 100) {
                          // It's base64 data
                          echo '<img src="data:image/jpeg;base64,' . $vehicle['main_image'] . '" alt="' . htmlspecialchars($vehicle['model_name']) . '">';
                      } else {
                          // Try base64_encode for backward compatibility
                          echo '<img src="data:image/jpeg;base64,' . base64_encode($vehicle['main_image']) . '" alt="' . htmlspecialchars($vehicle['model_name']) . '">';
                      }
                      ?>
                    <?php else: ?>
                      <span>🚗 No Image Available</span>
                    <?php endif; ?>
                  </div>

                  <div class="card-content">
                    <div class="card-header">
                      <div class="model-name"><?php echo htmlspecialchars($vehicle['model_name'] ?? 'Unknown Model'); ?></div>
                      <div class="variant"><?php echo htmlspecialchars($vehicle['variant'] ?? 'Standard'); ?></div>
                    </div>

                    <div class="year-category" style="padding-bottom:10px;">
                      <span class="year"><?php echo htmlspecialchars($vehicle['year_model'] ?? 'N/A'); ?></span>
                      <span class="category"><?php echo htmlspecialchars($vehicle['category'] ?? 'Vehicle'); ?></span>
                    </div>

                    <?php if ($vehicle['promotional_price'] || $vehicle['base_price']): ?>
                      <div class="price-section">
                        <?php if ($vehicle['base_price']): ?>
                          <div class="base-price">Base Price: ₱<?php echo number_format($vehicle['base_price'], 2); ?></div>
                        <?php endif; ?>
                        <?php if ($vehicle['promotional_price']): ?>
                          <div class="promotional-price">₱<?php echo number_format($vehicle['promotional_price'], 2); ?></div>
                        <?php endif; ?>
                      </div>
                    <?php endif; ?>

                    <div class="specs-grid">
                      <div class="spec-item">
                        <div class="spec-label">Engine</div>
                        <div class="spec-value"><?php echo htmlspecialchars($vehicle['engine_type'] ?? 'N/A'); ?></div>
                      </div>
                      <div class="spec-item">
                        <div class="spec-label">Transmission</div>
                        <div class="spec-value"><?php echo htmlspecialchars($vehicle['transmission'] ?? 'N/A'); ?></div>
                      </div>
                      <div class="spec-item">
                        <div class="spec-label">Fuel Type</div>
                        <div class="spec-value"><?php echo htmlspecialchars($vehicle['fuel_type'] ?? 'N/A'); ?></div>
                      </div>
                      <div class="spec-item">
                        <div class="spec-label">Seating</div>
                        <div class="spec-value"><?php echo htmlspecialchars($vehicle['seating_capacity'] ?? 'N/A'); ?> seats</div>
                      </div>
                    </div>

                    <?php if (!empty($vehicle['key_features'])): ?>
                      <div class="features">
                        <h4>Key Features</h4>
                        <div class="features-list">
                          <?php echo htmlspecialchars(substr($vehicle['key_features'], 0, 120)) . (strlen($vehicle['key_features']) > 120 ? '...' : ''); ?>
                        </div>
                      </div>
                    <?php endif; ?>

                    <div class="card-footer">
                      <div class="stock-status">
                        <?php
                        $stockClass = 'available';
                        $stockText = 'In Stock';
                        if ($vehicle['stock_quantity'] == 0) {
                          $stockClass = 'out-of-stock';
                          $stockText = 'Out of Stock';
                        } elseif ($vehicle['stock_quantity'] <= ($vehicle['min_stock_alert'] ?? 5)) {
                          $stockClass = 'low-stock';
                          $stockText = 'Low Stock';
                        }
                        ?>
                        <span class="status-indicator <?php echo $stockClass; ?>"></span>
                        <span><?php echo $stockText; ?> (<?php echo $vehicle['stock_quantity']; ?>)</span>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Add SweetAlert CDN -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="../../includes/js/common-scripts.js"></script>

  <!-- PHP Debug Info -->
  <script>
    console.log('PHP Debug Info:');
    console.log('Vehicles count from PHP: <?php echo count($vehicles ?? []); ?>');
    console.log('PDO connection status: <?php echo $pdo ? "Connected" : "Not connected"; ?>');
    <?php if (isset($vehicles) && count($vehicles) > 0): ?>
    console.log('First vehicle from PHP:', <?php echo json_encode($vehicles[0]); ?>);
    <?php else: ?>
    console.log('No vehicles available from PHP');
    <?php endif; ?>
  </script>

  <script>
    // Global function definitions - must be defined early for onclick handlers
    
    // Initialize global vehicle data from PHP with safe encoding
    let globalVehiclesData = [];
    try {
      <?php
      // Clean vehicle data for JavaScript - remove binary fields and problematic content
      $cleanVehicles = [];
      if (isset($vehicles) && is_array($vehicles)) {
        foreach ($vehicles as $vehicle) {
          $cleanVehicles[] = [
            'id' => $vehicle['id'],
            'model_name' => $vehicle['model_name'] ?? '',
            'variant' => $vehicle['variant'] ?? '',
            'year_model' => $vehicle['year_model'] ?? '',
            'category' => $vehicle['category'] ?? '',
            'engine_type' => $vehicle['engine_type'] ?? '',
            'transmission' => $vehicle['transmission'] ?? '',
            'fuel_type' => $vehicle['fuel_type'] ?? '',
            'seating_capacity' => $vehicle['seating_capacity'] ?? 0,
            'base_price' => $vehicle['base_price'] ?? 0,
            'promotional_price' => $vehicle['promotional_price'] ?? 0,
            'popular_color' => $vehicle['popular_color'] ?? '',
            'stock_quantity' => $vehicle['stock_quantity'] ?? 0,
            'availability_status' => $vehicle['availability_status'] ?? 'available'
          ];
        }
      }
      echo "globalVehiclesData = " . json_encode($cleanVehicles) . ";";
      ?>
      
      console.log('Global vehicles data loaded:', globalVehiclesData.length, 'vehicles');
      if (globalVehiclesData.length > 0) {
        console.log('Sample vehicle:', globalVehiclesData[0]);
      }
    } catch(e) {
      console.error('Error loading global vehicles data:', e);
      globalVehiclesData = [];
    }
    
    // Calculate total value function - globally accessible
    function calculateTotalValue() {
      const units = parseFloat(document.getElementById('units_delivered')?.value) || 0;
      const unitPrice = parseFloat(document.getElementById('unit_price')?.value) || 0;
      const totalValue = units * unitPrice;
      const totalField = document.getElementById('total_value');
      if (totalField) {
        totalField.value = totalValue.toFixed(2);
      }
    }
    
    // Vehicle Selection Modal Functions - globally accessible
    function openVehicleSelectionModal() {
      const modal = document.getElementById('vehicleSelectionModal');
      if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
      }
    }
    
    function closeVehicleSelectionModal() {
      const modal = document.getElementById('vehicleSelectionModal');
      if (modal) {
        modal.classList.remove('active');
        const searchInput = document.getElementById('vehicleSearchInput');
        if (searchInput) {
          searchInput.value = '';
        }
        
        // Only restore scroll if no other modals are active
        const deliveryModal = document.getElementById('deliveryFormModal');
        if (!deliveryModal || !deliveryModal.classList.contains('active')) {
          document.body.style.overflow = '';
        }
      }
    }
    
    // Vehicle Selection Function - moved here for global accessibility
    function selectVehicleFromCard(vehicleId) {
      console.log('selectVehicleFromCard called with vehicleId:', vehicleId);
      
      // Remove previous selection
      document.querySelectorAll('.vehicle-card').forEach(card => {
        card.classList.remove('selected');
      });

      // Add selection to clicked card
      const selectedCard = document.querySelector(`[data-vehicle-id="${vehicleId}"]`);
      if (selectedCard) {
        selectedCard.classList.add('selected');
        console.log('Selected card found and marked:', selectedCard);
      } else {
        console.error('Selected card not found for vehicleId:', vehicleId);
      }

      // Use global vehicle data
      console.log('Using global vehicles data:', globalVehiclesData.length, 'vehicles available');
      const vehiclesData = globalVehiclesData || [];
      
      // If no vehicles data available, show error and close modal
      if (vehiclesData.length === 0) {
        console.error('No vehicles data available - check database');
        
        // Close vehicle selection modal
        if (typeof closeVehicleSelectionModal === 'function') {
          closeVehicleSelectionModal();
        }
        
        // Show error message
        if (typeof Swal !== 'undefined') {
          Swal.fire({
            title: 'Database Error',
            html: `
              <div style="text-align: center;">
                <p>Vehicle data is not available. Please ensure:</p>
                <ul style="text-align: left; margin: 10px 0;">
                  <li>Database connection is working</li>
                  <li>Vehicles table exists</li>
                  <li>Sample data is loaded from sample_vehicles.sql</li>
                </ul>
                <p><small>Please contact the administrator to resolve this issue.</small></p>
              </div>
            `,
            icon: 'error',
            confirmButtonColor: '#d60000'
          });
        }
        return;
      }
      
      const vehicle = vehiclesData.find(v => v.id == vehicleId);
      console.log('Found vehicle:', vehicle);
      
      if (vehicle) {
        const modelName = vehicle.model_name || '';
        const variant = vehicle.variant || '';
        // Use promotional price if available and greater than 0, otherwise use base price
        const effectivePrice = (vehicle.promotional_price && vehicle.promotional_price > 0) ? 
                               vehicle.promotional_price : vehicle.base_price;
        const popularColor = vehicle.popular_color || '';

        console.log('Updating form fields with:', { modelName, variant, effectivePrice, popularColor });
        
        // Update the form fields
        document.getElementById('vehicle_model_display').value = `${modelName} - ${variant}`;
        document.getElementById('vehicle_id').value = vehicle.id;
        document.getElementById('model_name').value = modelName;
        document.getElementById('variant').value = variant;
        document.getElementById('unit_price').value = effectivePrice || 0;
        document.getElementById('color').value = popularColor;

        // Calculate total value if units are already entered
        if (typeof calculateTotalValue === 'function') {
          calculateTotalValue();
        }

        console.log('About to close vehicle selection modal');
        // Close vehicle selection modal immediately
        if (typeof closeVehicleSelectionModal === 'function') {
          closeVehicleSelectionModal();
          console.log('Vehicle selection modal close function called');
        } else {
          console.error('closeVehicleSelectionModal function not found');
        }

        // Ensure delivery modal remains active and focused
        setTimeout(() => {
          const deliveryModal = document.getElementById('deliveryFormModal');
          if (deliveryModal && !deliveryModal.classList.contains('active')) {
            deliveryModal.classList.add('active');
            document.body.style.overflow = 'hidden';
            console.log('Restored delivery modal active state');
          }
          
          // Focus on the next logical field (units delivered)
          const unitsField = document.getElementById('units_delivered');
          if (unitsField) {
            unitsField.focus();
            console.log('Focused on units field');
          }
        }, 100);

        // Show brief success message as toast notification
        if (typeof Swal !== 'undefined') {
          Swal.fire({
            title: 'Vehicle Selected!',
            html: `
              <div style="text-align: center;">
                <h3 style="color: var(--primary-red); margin: 10px 0;">${modelName}</h3>
                <p style="color: var(--text-light); margin: 5px 0;">${variant} - ${vehicle.year_model}</p>
                <p style="font-weight: bold; color: var(--success-green); font-size: 1.2rem;">₱${parseFloat(effectivePrice || 0).toLocaleString()}</p>
              </div>
            `,
            icon: 'success',
            timer: 1500,
            showConfirmButton: false,
            toast: true,
            position: 'top-end',
            customClass: {
              popup: 'colored-toast'
            }
          });
        }
      } else {
        console.error('Vehicle not found for ID:', vehicleId);
        console.log('Available vehicle IDs:', vehiclesData.map(v => v.id));
        
        // Close vehicle selection modal even if vehicle not found
        if (typeof closeVehicleSelectionModal === 'function') {
          closeVehicleSelectionModal();
        }
        
        // Show error for specific vehicle not found
        if (typeof Swal !== 'undefined') {
          const availableIds = vehiclesData.map(v => `ID ${v.id}: ${v.model_name} ${v.variant}`).join('<br>');
          Swal.fire({
            title: 'Vehicle Not Found',
            html: `
              <div style="text-align: left;">
                <p><strong>Vehicle ID ${vehicleId} was not found in the database.</strong></p>
                <p>Available vehicles:</p>
                <div style="background: #f8f9fa; padding: 10px; border-radius: 5px; font-size: 0.9rem;">
                  ${availableIds || 'No vehicles available'}
                </div>
                <p style="margin-top: 10px; font-size: 0.9rem; color: #666;">
                  <em>Note: This might happen if the database was recently updated or if sample data needs to be reloaded.</em>
                </p>
              </div>
            `,
            icon: 'error',
            confirmButtonColor: '#d60000',
            width: 500
          });
        }
      }
    }
    
    // Setup total value calculation event handlers
    function setupTotalValueCalculation() {
      const unitsInput = document.getElementById('units_delivered');
      const priceInput = document.getElementById('unit_price');
      
      if (unitsInput) {
        unitsInput.removeEventListener('input', calculateTotalValue);
        unitsInput.addEventListener('input', calculateTotalValue);
      }
      
      if (priceInput) {
        priceInput.removeEventListener('input', calculateTotalValue);
        priceInput.addEventListener('input', calculateTotalValue);
      }
    }
    
    // Modal functions
    function openDeliveryModal() {
      const modal = document.getElementById('deliveryFormModal');
      if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
      }
      
      // Set today's date as default
      const today = new Date().toISOString().split('T')[0];
      document.getElementById('delivery_date').value = today;
      
      // Set up event handlers for total value calculation
      setupTotalValueCalculation();
      
      // Also calculate immediately in case there are pre-filled values
      setTimeout(() => {
        calculateTotalValue();
      }, 100);
    }
    
    function closeDeliveryModal() {
      document.getElementById('deliveryFormModal').classList.remove('active');
      document.body.style.overflow = '';
      
      // Reset form completely
      document.getElementById('deliveryForm').reset();
      document.getElementById('deliveryID').value = '';
      document.getElementById('vehicle_model_display').value = '';
      document.getElementById('vehicle_id').value = '';
      document.getElementById('model_name').value = '';
      document.getElementById('variant').value = '';
      document.getElementById('color').value = '';
      document.getElementById('total_value').value = '';
      
      // Reset modal title and button text
      document.getElementById('modalTitle').textContent = 'Add New Delivery';
      document.getElementById('submitBtnText').textContent = 'Save Delivery';
    }
    
    // Make functions globally accessible
    window.calculateTotalValue = calculateTotalValue;
    window.openVehicleSelectionModal = openVehicleSelectionModal;
    window.closeVehicleSelectionModal = closeVehicleSelectionModal;
    window.setupTotalValueCalculation = setupTotalValueCalculation;
    window.openDeliveryModal = openDeliveryModal;
    window.closeDeliveryModal = closeDeliveryModal;
    window.selectVehicleFromCard = selectVehicleFromCard;
    
    // Test function accessibility
    console.log('Functions made globally accessible:', {
      calculateTotalValue: typeof window.calculateTotalValue,
      openVehicleSelectionModal: typeof window.openVehicleSelectionModal,
      closeVehicleSelectionModal: typeof window.closeVehicleSelectionModal,
      selectVehicleFromCard: typeof window.selectVehicleFromCard
    });
    
    // Debug: Show vehicle data status
    console.log('Global vehicles loaded:', globalVehiclesData.length, 'vehicles');
    if (globalVehiclesData.length > 0) {
      console.log('Sample vehicle IDs:', globalVehiclesData.map(v => v.id).slice(0, 5));
    }

    // Autocomplete setup for vehicle model field
    function setupVehicleAutocomplete() {
      const input = document.getElementById('vehicle_model_display');
      const list = document.getElementById('vehicleAutocomplete');
      const hiddenId = document.getElementById('vehicle_id');
      const hiddenModel = document.getElementById('model_name');
      const hiddenVariant = document.getElementById('variant');
      const hiddenColor = document.getElementById('color');
      const unitPriceInput = document.getElementById('unit_price');

      if (!input || !list) return;

      function normalize(str) { return (str || '').toString().toLowerCase(); }

      function effectivePrice(v) {
        const promo = parseFloat(v.promotional_price || 0);
        const base = parseFloat(v.base_price || 0);
        return (promo && promo > 0) ? promo : base;
      }

      function renderList(items) {
        if (!items || items.length === 0) {
          list.innerHTML = '<div class="autocomplete-item no-result">No matching vehicles</div>';
          list.style.display = 'block';
          return;
        }

        const html = items.map(v => {
          const left = `${v.model_name || ''}${v.variant ? ' - ' + v.variant : ''}`;
          const metaParts = [];
          if (v.year_model) metaParts.push(v.year_model);
          if (v.popular_color) metaParts.push(v.popular_color);
          const right = metaParts.length ? ` <span class=\"year\">${metaParts.join(' · ')}</span>` : '';
          return `<div class=\"autocomplete-item\" data-id=\"${v.id}\" data-model=\"${v.model_name || ''}\" data-variant=\"${v.variant || ''}\" data-color=\"${v.popular_color || ''}\">${left}${right}</div>`;
        }).join('');

        list.innerHTML = html;
        list.style.display = 'block';
      }

      function filter(query) {
        const q = normalize(query);
        if (!q) {
          renderList((globalVehiclesData || []).slice(0, 20));
          return;
        }
        const results = (globalVehiclesData || []).filter(v =>
          normalize(v.model_name).includes(q) ||
          normalize(v.variant).includes(q) ||
          normalize(v.year_model).includes(q) ||
          normalize(v.popular_color).includes(q) ||
          normalize(v.category).includes(q)
        ).slice(0, 50);
        renderList(results);
      }

      function clearSelection() {
        if (hiddenId) hiddenId.value = '';
        if (hiddenModel) hiddenModel.value = '';
        if (hiddenVariant) hiddenVariant.value = '';
        if (hiddenColor) hiddenColor.value = '';
      }

      function selectFromItem(el) {
        const id = el.getAttribute('data-id');
        const model = el.getAttribute('data-model');
        const variant = el.getAttribute('data-variant');
        const color = el.getAttribute('data-color');

        if (hiddenId) hiddenId.value = id || '';
        if (hiddenModel) hiddenModel.value = model || '';
        if (hiddenVariant) hiddenVariant.value = variant || '';
        if (hiddenColor) hiddenColor.value = color || '';

        const label = `${model || ''}${variant ? ' - ' + variant : ''}`;
        input.value = label;

        // Set unit price based on selected vehicle
        const selected = (globalVehiclesData || []).find(v => v.id == id);
        if (selected && unitPriceInput) {
          unitPriceInput.value = effectivePrice(selected) || 0;
        }

        // Recalculate total if needed
        if (typeof calculateTotalValue === 'function') {
          calculateTotalValue();
        }

        list.style.display = 'none';
      }

      // Input events
      input.addEventListener('input', function(e) {
        clearSelection();
        filter(e.target.value);
      });

      input.addEventListener('focus', function() {
        filter(input.value);
      });

      input.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
          list.style.display = 'none';
        }
      });

      input.addEventListener('blur', function() {
        setTimeout(() => { list.style.display = 'none'; }, 150);
      });

      list.addEventListener('mousedown', function(e) {
        const item = e.target.closest('.autocomplete-item');
        if (item && !item.classList.contains('no-result')) {
          e.preventDefault();
          selectFromItem(item);
        }
      });

      // Hide when clicking outside
      document.addEventListener('mousedown', function(e) {
        if (!list.contains(e.target) && e.target !== input) {
          list.style.display = 'none';
        }
      });

      // Ensure the input is editable
      input.removeAttribute('readonly');
      input.style.cursor = 'text';
    }

    // Expose globally
    window.setupVehicleAutocomplete = setupVehicleAutocomplete;
    
    // Enhanced button setup with multiple fallbacks
    
    // Modal functions moved to first script tag
    
    function setupAddButton() {
      const btn = document.getElementById('addNewDeliveryBtn');
      if (btn) {
        // Remove any existing event listeners
        btn.onclick = null;
        
        // Set onclick handler
        btn.onclick = function(e) {
          e.preventDefault();
          e.stopPropagation();
          openDeliveryModal();
        };
        
        // Also add event listener as backup
        btn.removeEventListener('click', openDeliveryModal);
        btn.addEventListener('click', function(e) {
          e.preventDefault();
          e.stopPropagation();
          openDeliveryModal();
        });
        
      } else {
        
      }
    }
    
    // Setup immediately
    setupAddButton();
    
    // Try again after DOM loads
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', setupAddButton);
    }
    
    // Try again after short delays
    setTimeout(setupAddButton, 100);
    setTimeout(setupAddButton, 500);
    setTimeout(setupAddButton, 1000);

    // Vehicle Selection Functions - moved to top of script for global accessibility

    // Make selectVehicleFromCard globally accessible - already done above

    // Ensure global accessibility - removed duplicate calculateTotalValue definition
    
    // Vehicle Selection Modal Functions
    // Vehicle Selection Modal Functions - already defined globally above
    // Removed duplicate definitions

    // Initialize vehicle autocomplete on DOM ready
    document.addEventListener('DOMContentLoaded', function() {
      try { setupVehicleAutocomplete && setupVehicleAutocomplete(); } catch (e) { console.warn('Autocomplete init failed', e); }
    });
  </script>

  <script>
    // Global variables
    let currentDeliveries = [];
    let currentFilters = {
      search: '',
      model: 'all',
      dateRange: 'all'
    };

    // API functions
    async function fetchDeliveries(filters = {}) {
      try {
        const params = new URLSearchParams({
          search: filters.search || '',
          model: filters.model || '',
          date_range: filters.dateRange || '',
          limit: 100
        });

        const response = await fetch(`../../api/deliveries.php?${params}`);
        const data = await response.json();

        if (data.success) {
          currentDeliveries = data.data;
          renderDeliveriesTable(data.data);
        } else {
          renderErrorMessage(data.message || 'Failed to fetch deliveries');
        }
      } catch (error) {
        renderErrorMessage('Network error: Unable to connect to server');
      }
    }

    async function fetchDeliveryStats() {
      try {
        const response = await fetch('../../api/deliveries.php?action=stats');
        const data = await response.json();

        if (data.success) {
          updateStatsDisplay(data.data);
        } else {
          // Set default stats instead of showing error
          updateStatsDisplay({
            total_deliveries: 0,
            today_deliveries: 0,
            pending_deliveries: 0,
            monthly_value: 0
          });
        }
      } catch (error) {
        // Set default stats instead of showing error
        updateStatsDisplay({
          total_deliveries: 0,
          today_deliveries: 0,
          pending_deliveries: 0,
          monthly_value: 0
        });
      }
    }

    async function saveDelivery(deliveryData) {
      try {
        const method = deliveryData.id ? 'PUT' : 'POST';
        const response = await fetch('../../api/deliveries.php', {
          method: method,
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify(deliveryData)
        });

        const data = await response.json();
        
        if (!data.success) {
          throw new Error(data.message || 'Failed to save delivery');
        }

        return data;
      } catch (error) {
        console.error('Error saving delivery:', error);
        throw error;
      }
    }

    async function deleteDelivery(deliveryId) {
      try {
        const response = await fetch(`../../api/deliveries.php?id=${deliveryId}`, {
          method: 'DELETE'
        });

        const data = await response.json();
        
        if (!data.success) {
          throw new Error(data.message || 'Failed to delete delivery');
        }

        return data;
      } catch (error) {
        console.error('Error deleting delivery:', error);
        throw error;
      }
    }

    // UI functions
    function updateStatsDisplay(stats) {
      document.getElementById('totalDeliveries').textContent = stats.total_deliveries || 0;
      document.getElementById('todayDeliveries').textContent = stats.today_deliveries || 0;
      document.getElementById('pendingDeliveries').textContent = stats.pending_deliveries || 0;
      
      const monthlyValue = parseFloat(stats.monthly_value || 0);
      document.getElementById('monthlyValue').textContent = '₱' + monthlyValue.toLocaleString();
    }

    function renderErrorMessage(errorMessage) {
      const tbody = document.getElementById('deliveriesTableBody');
      tbody.innerHTML = `
        <tr>
          <td colspan="6" style="text-align: center; padding: 50px; color: var(--text-light);">
            <i class="fas fa-exclamation-triangle" style="font-size: 64px; margin-bottom: 20px; opacity: 0.5; color: #ef4444;"></i>
            <h3 style="margin: 15px 0 10px 0; color: #ef4444; font-weight: 600;">Error Loading Deliveries</h3>
            <p style="margin: 0; font-size: 0.95rem; color: #666;">${errorMessage}</p>
            <p style="margin: 10px 0 0 0; font-size: 0.9rem; opacity: 0.8;">Please check your authentication or try refreshing the page.</p>
          </td>
        </tr>
      `;
    }

    function renderDeliveriesTable(deliveries) {
      const tbody = document.getElementById('deliveriesTableBody');
      
      if (deliveries.length === 0) {
        tbody.innerHTML = `
          <tr>
            <td colspan="6" style="text-align: center; padding: 50px; color: var(--text-light);">
              <i class="fas fa-truck" style="font-size: 64px; margin-bottom: 20px; opacity: 0.3; color: var(--primary-red);"></i>
              <h3 style="margin: 15px 0 10px 0; color: var(--text-dark); font-weight: 600;">No Deliveries Found</h3>
              <p style="margin: 0; font-size: 0.95rem;">No delivery records match your current search criteria.</p>
              <p style="margin: 10px 0 0 0; font-size: 0.9rem; opacity: 0.8;">Try adjusting your filters or add a new delivery.</p>
            </td>
          </tr>
        `;
        return;
      }

      tbody.innerHTML = deliveries.map(delivery => `
        <tr data-delivery-id="${delivery.id}">
          <td>
            <div class="delivery-info">
              <span class="delivery-id">${delivery.delivery_id}</span>
              <span class="delivery-date">${delivery.delivery_date_formatted}</span>
            </div>
          </td>
          <td>
            <div class="vehicle-info">
              <span class="vehicle-model">${delivery.model_name}</span>
              <span class="vehicle-details">${delivery.vehicle_details}</span>
            </div>
          </td>
          <td><span class="units-badge">${delivery.units_delivered}</span></td>
          <td class="price">₱${parseFloat(delivery.unit_price).toLocaleString()}</td>
          <td class="price">₱${parseFloat(delivery.total_value).toLocaleString()}</td>
          <td>
            <div class="delivery-actions-enhanced">
              <button class="btn-small btn-view" title="View Details" onclick="viewDeliveryDetails(${delivery.id})">
                <i class="fas fa-eye"></i>
              </button>
              <button class="btn-small btn-edit" title="Edit Delivery" onclick="editDelivery(${delivery.id})">
                <i class="fas fa-edit"></i>
              </button>
              <button class="btn-small btn-delete" title="Delete Delivery" onclick="confirmDeleteDelivery(${delivery.id})" style="background: #ef4444;">
                <i class="fas fa-trash"></i>
              </button>
            </div>
          </td>
        </tr>
      `).join('');
    }

    function showError(message) {
      Swal.fire({
        title: 'Error',
        text: message,
        icon: 'error',
        confirmButtonColor: '#d60000'
      });
    }

    function showSuccess(message) {
      Swal.fire({
        title: 'Success!',
        text: message,
        icon: 'success',
        confirmButtonColor: '#d60000',
        timer: 3000,
        showConfirmButton: false
      });
    }

    // Modal functions moved to first script tag - these are duplicates, removing

    // Vehicle Selection Modal Functions moved to first script tag
    // selectVehicleFromCard function moved to first script tag to be available for onclick handlers
    // calculateTotalValue function moved to first script tag

    // Updated form submission
    async function handleDeliverySubmit() {
      const form = document.getElementById('deliveryForm');
      
      // Validate required fields
      const requiredFields = {
        'delivery_date': 'Delivery Date',
        'vehicle_id': 'Vehicle Selection',
        'model_name': 'Vehicle Model',
        'variant': 'Vehicle Variant',
        'units_delivered': 'Units Delivered',
        'unit_price': 'Unit Price'
      };
      
      const missingFields = [];
      for (const [fieldName, displayName] of Object.entries(requiredFields)) {
        const field = document.getElementById(fieldName);
        if (!field || !field.value || field.value.trim() === '') {
          missingFields.push(displayName);
        }
      }
      
      if (missingFields.length > 0) {
        showError(`Please fill in the following required fields: ${missingFields.join(', ')}`);
        return;
      }
      
      // Show loading indicator
      Swal.fire({
        title: 'Saving Delivery...',
        text: 'Please wait while we process your request.',
        allowOutsideClick: false,
        didOpen: () => {
          Swal.showLoading();
        }
      });
      
      const formData = new FormData(form);

      // Convert FormData to JSON object
      const deliveryData = {};
      for (let [key, value] of formData.entries()) {
        deliveryData[key] = value;
      }

      try {
        const result = await saveDelivery(deliveryData);
        
        Swal.fire({
          title: 'Success!',
          html: `
            <div style="text-align: center;">
              <h3 style="color: var(--success-green); margin: 10px 0;">Delivery Saved Successfully</h3>
              <p><strong>Delivery ID:</strong> ${result.data?.delivery_id || 'Generated'}</p>
              <p><strong>Vehicle:</strong> ${deliveryData.model_name} - ${deliveryData.variant}</p>
              <p><strong>Units Delivered:</strong> ${deliveryData.units_delivered}</p>
              <p><strong>Total Value:</strong> ₱${parseFloat(deliveryData.total_value).toLocaleString()}</p>
            </div>
          `,
          icon: 'success',
          confirmButtonColor: '#d60000'
        }).then(() => {
          closeDeliveryModal();
          loadDeliveries();
        });
      } catch (error) {
        console.error('Delivery submission error:', error);
        showError(error.message || 'Failed to save delivery. Please try again.');
      }
    }

    // Updated view/edit/delete functions - make them globally accessible
    function viewDeliveryDetails(deliveryId) {
      const delivery = currentDeliveries.find(d => d.id == deliveryId);
      if (!delivery) {
        showError('Delivery not found');
        return;
      }

      Swal.fire({
        title: `Delivery Details - ${delivery.delivery_id}`,
        html: `
          <div style="text-align: left; margin: 20px 0;">
            <p><strong>Date:</strong> ${delivery.delivery_date_formatted}</p>
            <p><strong>Vehicle:</strong> ${delivery.model_name} - ${delivery.variant}</p>
            <p><strong>Vehicle Details:</strong> ${delivery.vehicle_details}</p>
            <p><strong>Units Delivered:</strong> ${delivery.units_delivered}</p>
            <p><strong>Unit Price:</strong> ₱${parseFloat(delivery.unit_price).toLocaleString()}</p>
            <p><strong>Total Value:</strong> ₱${parseFloat(delivery.total_value).toLocaleString()}</p>
            <p><strong>Created:</strong> ${new Date(delivery.created_at).toLocaleString()}</p>
            ${delivery.updated_at !== delivery.created_at ? 
              `<p><strong>Last Updated:</strong> ${new Date(delivery.updated_at).toLocaleString()}</p>` : ''
            }
          </div>
        `,
        icon: 'info',
        confirmButtonColor: '#d60000',
        width: 600
      });
    }
    
    // Make globally accessible
    window.viewDeliveryDetails = viewDeliveryDetails;

    function editDelivery(deliveryId) {
      const delivery = currentDeliveries.find(d => d.id == deliveryId);
      if (!delivery) {
        showError('Delivery not found');
        return;
      }

      // Populate form with delivery data
      document.getElementById('modalTitle').textContent = 'Edit Delivery';
      document.getElementById('submitBtnText').textContent = 'Update Delivery';
      document.getElementById('deliveryID').value = delivery.id;
      document.getElementById('delivery_date').value = delivery.delivery_date;
      document.getElementById('vehicle_id').value = delivery.vehicle_id;
      document.getElementById('model_name').value = delivery.model_name;
      document.getElementById('variant').value = delivery.variant;
      document.getElementById('color').value = delivery.color || '';
      document.getElementById('vehicle_model_display').value = `${delivery.model_name} - ${delivery.variant}`;
      document.getElementById('units_delivered').value = delivery.units_delivered;
      document.getElementById('unit_price').value = delivery.unit_price;
      document.getElementById('total_value').value = delivery.total_value;

      // Open modal and set up event handlers
      const modal = document.getElementById('deliveryFormModal');
      if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
      }
      
      // Set up event handlers for total value calculation
      setupTotalValueCalculation();
    }
    
    // Make globally accessible
    window.editDelivery = editDelivery;

    async function confirmDeleteDelivery(deliveryId) {
      const delivery = currentDeliveries.find(d => d.id == deliveryId);
      if (!delivery) {
        showError('Delivery not found');
        return;
      }

      const result = await Swal.fire({
        title: 'Delete Delivery?',
        html: `
          <div style="text-align: center;">
            <p>Are you sure you want to delete this delivery?</p>
            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0;">
              <strong>${delivery.delivery_id}</strong><br>
              ${delivery.model_name} - ${delivery.variant}<br>
              <small style="color: #666;">${delivery.delivery_date_formatted}</small>
            </div>
            <p style="color: #dc3545; font-weight: 500;">This action cannot be undone!</p>
          </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
      });

      if (result.isConfirmed) {
        try {
          await deleteDelivery(deliveryId);
          showSuccess('Delivery deleted successfully');
          loadDeliveries();
        } catch (error) {
          showError(error.message);
        }
      }
    }
    
    // Make globally accessible
    window.confirmDeleteDelivery = confirmDeleteDelivery;

    // Load deliveries with current filters
    function loadDeliveries() {
      fetchDeliveries(currentFilters);
      fetchDeliveryStats();
    }

    // Initialize page
    document.addEventListener('DOMContentLoaded', function() {
      // Load initial data
      loadDeliveries();

      // Filter functionality
      document.getElementById('applyFiltersBtn').addEventListener('click', function() {
        currentFilters = {
          search: document.getElementById('delivery-search').value.trim(),
          model: document.getElementById('vehicle-model').value,
          dateRange: document.getElementById('delivery-date-range').value
        };
        loadDeliveries();
      });

      // Real-time search with debounce
      let searchTimeout;
      document.getElementById('delivery-search').addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
          currentFilters.search = this.value.trim();
          loadDeliveries();
        }, 500);
      });

      // Add New Delivery button event listener
      const addDeliveryBtn = document.getElementById('addNewDeliveryBtn');
      if (addDeliveryBtn) {
        addDeliveryBtn.addEventListener('click', openDeliveryModal);
      }

      // Vehicle search functionality
      let vehicleSearchTimeout;
      const vehicleSearchInput = document.getElementById('vehicleSearchInput');
      if (vehicleSearchInput) {
        vehicleSearchInput.addEventListener('input', function() {
          clearTimeout(vehicleSearchTimeout);
          const searchTerm = this.value.trim().toLowerCase();

          vehicleSearchTimeout = setTimeout(() => {
            const vehicleCards = document.querySelectorAll('.vehicle-card');
            vehicleCards.forEach(card => {
              const modelName = card.querySelector('.model-name')?.textContent.toLowerCase() || '';
              const variant = card.querySelector('.variant')?.textContent.toLowerCase() || '';
              const category = card.querySelector('.year-category .category')?.textContent.toLowerCase() || '';
              
              if (modelName.includes(searchTerm) || variant.includes(searchTerm) || category.includes(searchTerm)) {
                card.style.display = 'block';
              } else {
                card.style.display = 'none';
              }
            });
          }, 300);
        });
      }

      // View/Edit button handlers
      document.querySelectorAll('.btn-view').forEach(btn => {
        btn.addEventListener('click', function() {
          const row = this.closest('tr');
          const deliveryID = row.querySelector('.delivery-id').textContent;
          viewDeliveryDetails(deliveryID);
        });
      });

      document.querySelectorAll('.btn-edit').forEach(btn => {
        btn.addEventListener('click', function() {
          const row = this.closest('tr');
          const deliveryID = row.querySelector('.delivery-id').textContent;
          editDelivery(deliveryID);
        });
      });

      // Form submission
      document.getElementById('deliveryForm').addEventListener('submit', function(e) {
        e.preventDefault();
        handleDeliverySubmit();
      });
    });
  </script>
</body>
</html>