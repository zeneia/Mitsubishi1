<?php
include_once(dirname(dirname(__DIR__)) . '/includes/init.php');

// Check if user is logged in
if (!isset($_SESSION['user_role'])) {
    header("Location: ../../pages/login.php");
    exit();
}

$user_role = $_SESSION['user_role'];

// Set dynamic page title based on user role
$page_main_title = "Inventory Management"; // Default title
if ($user_role === 'Sales Agent') {
    $page_main_title = "Sales Agent Vehicle Inventory";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo $user_role; ?> Inventory - Mitsubishi</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link href="../../includes/css/common-styles.css" rel="stylesheet">
  <style>
    html, body {
      height: 100%;
      width: 100%;
      margin: 0;
      padding: 0;
      overflow: hidden;
    }
    
    body {
      zoom: 85%;
    }
    .page-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 30px;
    }

    .page-header h1 {
      font-size: 2rem;
      color: var(--text-dark);
      font-weight: 700;
    }

    .add-btn {
      background: linear-gradient(135deg, var(--primary-red), #b91c3c);
      color: white;
      border: none;
      padding: 12px 24px;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      transition: var(--transition);
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .add-btn:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-medium);
    }

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }

    .stat-card {
      background: white;
      padding: 25px;
      border-radius: 12px;
      box-shadow: var(--shadow-light);
      display: flex;
      align-items: center;
      gap: 15px;
    }

    .stat-icon {
      width: 50px;
      height: 50px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 20px;
      color: white;
    }

    .stat-icon.red { background: var(--primary-red); }
    .stat-icon.blue { background: var(--accent-blue); }
    .stat-icon.green { background: var(--success-green); }
    .stat-icon.orange { background: var(--warning-orange); }

    .stat-info h3 {
      font-size: 1.8rem;
      color: var(--text-dark);
      margin-bottom: 5px;
    }

    .stat-info p {
      color: var(--text-light);
      font-size: 14px;
    }

    .inventory-table {
      background: white;
      border-radius: 12px;
      box-shadow: var(--shadow-light);
      overflow: hidden;
    }

    .table-header {
      padding: 20px 25px;
      background: var(--primary-light);
      border-bottom: 1px solid var(--border-light);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .table-header h2 {
      font-size: 1.3rem;
      color: var(--text-dark);
    }

    .search-box {
      display: flex;
      gap: 10px;
    }

    .search-input {
      padding: 8px 12px;
      border: 1px solid var(--border-light);
      border-radius: 6px;
      font-size: 14px;
      width: 200px;
    }

    .filter-btn {
      padding: 8px 16px;
      background: var(--accent-blue);
      color: white;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-size: 14px;
    }

    .table {
      width: 100%;
      border-collapse: collapse;
    }

    .table th,
    .table td {
      padding: 15px 25px;
      text-align: left;
      border-bottom: 1px solid var(--border-light);
    }

    .table th {
      background: var(--primary-light);
      font-weight: 600;
      color: var(--text-dark);
    }

    .table tbody tr:hover {
      background: #f8f9fa;
    }

    .status-badge {
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
    }

    .status-badge.in-stock {
      background: #d4edda;
      color: #155724;
    }

    .status-badge.low-stock {
      background: #fff3cd;
      color: #856404;
    }

    .status-badge.out-of-stock {
      background: #f8d7da;
      color: #721c24;
    }

    .action-buttons {
      display: flex;
      gap: 8px;
    }

    .btn-small {
      padding: 6px 12px;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      font-size: 12px;
      transition: var(--transition);
    }

    .btn-edit {
      background: var(--accent-blue);
      color: white;
    }

    .btn-delete {
      background: var(--primary-red);
      color: white;
    }

    .btn-small:hover {
      transform: translateY(-1px);
    }

    /* Modal Styles */
    .modal-overlay {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: rgba(0, 0, 0, 0.5);
      display: none;
      justify-content: center;
      align-items: center;
      z-index: 1000;
      padding: 20px;
    }

    .modal-overlay.active {
      display: flex;
    }

    .modal {
      background: white;
      border-radius: 12px;
      width: 90%;
      max-width: 700px;
      max-height: 95vh;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
      animation: slideIn 0.3s ease-out;
      display: flex;
      flex-direction: column;
    }

    .modal-header {
      padding: 20px 25px;
      border-bottom: 1px solid var(--border-light);
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-shrink: 0;
    }

    .modal-header h3 {
      margin: 0;
      font-size: 1.5rem;
      color: var(--text-dark);
    }

    .modal-close {
      background: none;
      border: none;
      font-size: 1.5rem;
      cursor: pointer;
      color: var(--text-light);
      transition: var(--transition);
    }

    .modal-close:hover {
      color: var(--primary-red);
    }

    .modal-body {
      max-height: 60vh;
      overflow-y: auto;
      flex: 1;
      padding: 25px;
    }
    .modal-footer {
      padding: 15px 25px;
      border-top: 1px solid var(--border-light);
      display: flex;
      justify-content: flex-end;
      gap: 10px;
      flex-shrink: 0;
      background: white;
      position: sticky;
      bottom: 0;
    }

    .form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
    }

    .form-group {
      margin-bottom: 20px;
    }

    .form-label {
      display: block;
      margin-bottom: 8px;
      font-weight: 500;
      color: var(--text-dark);
    }

    .form-control {
      width: 100%;
      padding: 10px 15px;
      border: 1px solid var(--border-light);
      border-radius: 6px;
      font-size: 14px;
    }

    .form-control:focus {
      outline: none;
      border-color: var(--accent-blue);
      box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
    }

    .form-group.full-width {
      grid-column: span 2;
    }

    @media (max-width: 767px) {
      .form-grid {
        grid-template-columns: 1fr;
      }
      
      .form-group.full-width {
        grid-column: span 1;
      }
    }

    /* Button Styles */
    .btn {
      padding: 10px 20px;
      border: none;
      border-radius: 6px;
      font-weight: 600;
      cursor: pointer;
      transition: var(--transition);
    }

    .btn-primary {
      background: var(--primary-red);
      color: white;
    }

    .btn-secondary {
      background: #6c757d;
      color: white;
    }

    .btn:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-light);
    }

    /* Status Select Colors */
    .form-control.status-select option[value="in-stock"] {
      background-color: #d4edda;
    }

    .form-control.status-select option[value="low-stock"] {
      background-color: #fff3cd;
    }

    .form-control.status-select option[value="out-of-stock"] {
      background-color: #f8d7da;
    }

    /* Responsive Design */
    @media (max-width: 575px) {
      .page-header {
        flex-direction: column;
        gap: 15px;
        align-items: stretch;
      }

      .stats-grid {
        grid-template-columns: 1fr;
        gap: 15px;
      }

      .table-header {
        flex-direction: column;
        gap: 15px;
        align-items: stretch;
      }

      .search-box {
        flex-direction: column;
      }

      .search-input {
        width: 100%;
      }

      .table-responsive {
        overflow-x: auto;
      }

      .table {
        min-width: 600px;
      }

      .table th,
      .table td {
        padding: 10px 15px;
        font-size: 14px;
      }
    }

    @media (min-width: 576px) and (max-width: 767px) {
      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }

      .table th,
      .table td {
        padding: 12px 20px;
      }
    }

    @media (min-width: 768px) and (max-width: 991px) {
      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }
    }

    @media (min-width: 992px) and (max-width: 1199px) {
      .stats-grid {
        grid-template-columns: repeat(4, 1fr);
      }
    }

    .page-header {
      margin-bottom: 30px;
      padding-bottom: 20px;
      border-bottom: 2px solid var(--border-light);
    }

    .page-title {
      font-size: 28px;
      font-weight: 700;
      color: var(--primary-dark);
      display: flex;
      align-items: center;
      gap: 15px;
    }

    .role-indicator {
      background: linear-gradient(135deg, var(--primary-red), #b91c3c);
      color: white;
      padding: 5px 15px;
      border-radius: 20px;
      font-size: 14px;
      font-weight: 600;
    }

    .tab-navigation {
      display: flex;
      gap: 5px;
      margin-bottom: 25px;
      border-bottom: 1px solid var(--border-light);
      flex-wrap: wrap;
    }

    .tab-button {
      padding: 12px 20px;
      background: none;
      border: none;
      border-bottom: 3px solid transparent;
      cursor: pointer;
      font-weight: 600;
      color: var(--text-light);
      transition: var(--transition);
    }

    .tab-button.active {
      color: var(--primary-red);
      border-bottom-color: var(--primary-red);
    }

    .tab-content {
      display: none;
      animation: fadeIn 0.3s ease;
    }

    .tab-content.active {
      display: block;
    }

    .info-cards {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
      gap: 15px;
      margin-bottom: 30px;
    }

    .info-card {
      background-color: white;
      border: 1px solid var(--border-light);
      border-radius: 10px;
      padding: 20px;
      transition: var(--transition);
    }

    .info-card-title {
      font-size: 14px;
      color: var(--text-light);
      margin-bottom: 5px;
    }

    .info-card-value {
      font-size: 20px;
      font-weight: 700;
      color: var(--text-dark);
    }

    .filter-bar {
      display: flex;
      flex-wrap: wrap;
      gap: 15px;
      margin-bottom: 20px;
      align-items: center;
    }

    .search-input {
      flex: 1;
      min-width: 200px;
      position: relative;
    }

    .search-input input {
      width: 100%;
      padding: 10px 15px 10px 40px;
      border: 1px solid var(--border-light);
      border-radius: 8px;
      font-size: 14px;
    }

    .search-input i {
      position: absolute;
      left: 15px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--text-light);
    }

    .filter-select {
      padding: 10px 15px;
      border: 1px solid var(--border-light);
      border-radius: 8px;
      background-color: white;
      min-width: 150px;
    }

    .data-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 30px;
    }

    .data-table th, .data-table td {
      padding: 12px 15px;
      text-align: left;
      border-bottom: 1px solid var(--border-light);
    }

    .data-table th {
      background-color: #f9fafb;
      font-weight: 600;
      color: var(--text-dark);
    }

    .data-table tr:hover {
      background-color: #f9fafb;
    }

    .status {
      padding: 5px 10px;
      border-radius: 15px;
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
      display: inline-block;
    }

    .status.available {
      background-color: #e6f7ed;
      color: #0e7c42;
    }

    .status.low-stock {
      background-color: #fff8e6;
      color: #b78105;
    }

    .status.out-of-stock {
      background-color: #fce8e8;
      color: #b91c1c;
    }

    .status.reserved {
      background-color: #e6eefb;
      color: #1e62cd;
    }

    .table-actions {
      display: flex;
      gap: 8px;
    }

    .btn {
      padding: 8px 16px;
      border: none;
      border-radius: 6px;
      font-size: 12px;
      font-weight: 600;
      cursor: pointer;
      transition: var(--transition);
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .btn-small {
      padding: 5px 10px;
      font-size: 11px;
    }

    .btn-primary {
      background: linear-gradient(135deg, var(--primary-red), #b91c3c);
      color: white;
    }

    .btn-outline {
      background: transparent;
      border: 1px solid var(--border-light);
      color: var(--text-dark);
    }

    .btn-secondary {
      background: var(--border-light);
      color: var(--text-dark);
    }

    .action-area {
      display: flex;
      gap: 15px;
      margin-top: 30px;
      padding-top: 20px;
      border-top: 1px solid var(--border-light);
    }

    .vehicle-card {
  display: flex;
  flex-direction: column;
  justify-content: space-between; /* distributes space so bottom area stays fixed */
  height: 100%; /* ensures consistent height in grid */
  background: #fff;
  border-radius: 10px;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
  padding: 20px;
    }

    .vehicle-card:hover {
      box-shadow: var(--shadow-light);
    }

    .vehicle-header {
      height: 90px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding-bottom: 20px;
      
    }

    .vehicle-name {
      font-size: 18px;
      font-weight: 600;
      color: var(--text-dark);
    }

    .stock-level {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .stock-bar {
      width: 100px;
      height: 8px;
      background: var(--border-light);
      border-radius: 4px;
      overflow: hidden;
    }

    .stock-fill {
      height: 100%;
      background: linear-gradient(135deg, var(--primary-red), #b91c3c);
      border-radius: 4px;
    }


    .vehicle-card p {
      flex-grow: 1; /* Take available space */
      text-align: justify;
      display: flex;
      align-items: flex-start; /* Vertically center */
      justify-content: center;
      margin: 0 10px;
      color: var(--text-light);
    }

    /* Responsive Design */
    @media (max-width: 575px) {
      .info-cards {
        grid-template-columns: 1fr;
      }
      .filter-bar {
        flex-direction: column;
        align-items: stretch;
      }
      .action-area {
        flex-direction: column;
      }
    }

    @media (min-width: 576px) and (max-width: 767px) {
      .info-cards {
        grid-template-columns: repeat(2, 1fr);
      }
    }

    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    /* Loading Spinner */
    .loading-spinner {
      display: none;
      position: fixed;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      z-index: 9999;
    }
    
    .loading-spinner.active {
      display: block;
    }
    
    .spinner {
      border: 4px solid #f3f3f3;
      border-top: 4px solid var(--primary-red);
      border-radius: 50%;
      width: 40px;
      height: 40px;
      animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
    
    /* Toast Notification */
    .toast {
      position: fixed;
      top: 20px;
      right: 20px;
      padding: 15px 20px;
      background: #333;
      color: white;
      border-radius: 8px;
      opacity: 0;
      transform: translateX(100%);
      transition: all 0.3s ease;
      z-index: 9999;
    }
    
    .toast.show {
      opacity: 1;
      transform: translateX(0);
    }
    
    .toast.success {
      background: var(--success-green);
    }
    
    .toast.error {
      background: var(--primary-red);
    }
    
    /* Vehicle Grid */
    .vehicle-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
      gap: 20px;
      margin-top: 20px;
    }
    
    .no-results {
      text-align: center;
      padding: 40px;
      color: var(--text-light);
    }
    
    /* Form Styles */
    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 15px;
      margin-bottom: 15px;
    }
    
    .form-row.full-width {
      grid-template-columns: 1fr;
    }
    
    textarea.form-control {
      resize: vertical;
      min-height: 100px;
    }
    
    .image-upload-section {
      margin-top: 20px;
      padding: 20px;
      background: #f8f9fa;
      border-radius: 8px;
    }
    
    .image-upload-label {
      display: block;
      margin-bottom: 10px;
      font-weight: 600;
    }
    
    .file-input {
      display: block;
      width: 100%;
      padding: 8px;
      border: 1px solid var(--border-light);
      border-radius: 4px;
      margin-bottom: 5px;
    }
    
    .file-info {
      font-size: 12px;
      color: #666;
      margin-bottom: 15px;
      font-style: italic;
    }
    
    /* Delete Confirmation Modal */
    .delete-modal {
      text-align: center;
    }
    
    .delete-modal p {
      margin: 20px 0;
      font-size: 16px;
    }
    
    .delete-modal .vehicle-info {
      background: #f8f9fa;
      padding: 15px;
      border-radius: 8px;
      margin: 20px 0;
    }

    /* Styling for Update Stock Modal Info */
    #updateStockModal .modal-body p {
        margin-bottom: 10px;
        font-size: 1rem;
    }
    #updateStockModal .modal-body strong {
        color: var(--text-dark);
    }

    /* View Details Modal Styles */
    .view-details-modal .modal-body {
      font-size: 1rem;
      max-height: 85vh;
      flex: 1;
    }
    .view-details-modal .detail-section {
      margin-bottom: 20px;
      padding-bottom: 15px;
      border-bottom: 1px solid var(--border-light);
    }
    .view-details-modal .detail-section:last-child {
      border-bottom: none;
      margin-bottom: 0;
    }
    .view-details-modal h4 {
      font-size: 1.1rem;
      color: var(--primary-red);
      margin-bottom: 10px;
    }
    .view-details-modal p {
      margin-bottom: 8px;
      line-height: 1.6;
    }
    .view-details-modal strong {
      color: var(--text-dark);
      margin-right: 5px;
    }
    .view-details-modal .image-gallery {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      margin-top: 10px;
      justify-content: center; /* Center images in the gallery */
    }
    .view-details-modal .image-gallery img {
      max-width: 150px;
      max-height: 100px;
      border-radius: 6px;
      border: 1px solid var(--border-light);
      object-fit: cover;
    }
    .view-details-modal .main-image-container {
      display: flex; /* Use flexbox for centering */
      justify-content: center; /* Center horizontally */
      align-items: center; /* Center vertically if needed, or remove if image should stick to top */
      margin-bottom: 20px; /* Increased margin */
      min-height: 250px; /* Ensure container has some height for centering */
      background-color: #f8f9fa; /* Optional: light background for image area */
      border-radius: 8px;
      padding: 10px;
    }
    .view-details-modal .main-image-container img {
      max-width: 100%;
      max-height: 350px; /* Increased max-height for main image */
      border-radius: 8px;
      border: 1px solid var(--border-light);
      object-fit: contain;
      /* margin-bottom: 15px; Removed as flex centering handles spacing */
    }
  </style>
</head>
<body>
  <?php include '../../includes/components/sidebar.php'; ?>

  <div class="main">
    <?php include '../../includes/components/topbar.php'; ?>

    <div class="main-content">
      <div class="page-header">
        <h1 class="page-title">
          <i class="fas fa-warehouse"></i>
          <?php echo htmlspecialchars($page_main_title); ?>
          <span class="role-indicator"><?php echo htmlspecialchars($user_role); ?> View</span>
        </h1>
        <?php if ($user_role === 'Admin'): ?>
          <button class="add-btn" onclick="openAddModal()">
            <i class="fas fa-plus"></i> Add New Vehicle
          </button>
        <?php endif; ?>
      </div>

      <div class="tab-navigation">
        <button class="tab-button active" data-tab="vehicle-details">Vehicle Details</button>
      </div>

      <!-- Vehicle Details Tab -->
      <div class="tab-content active" id="vehicle-details">
        <!-- Stats Cards -->
        <div class="stats-grid" id="statsGrid">
          <div class="stat-card">
            <div class="stat-icon red">
              <i class="fas fa-car"></i>
            </div>
            <div class="stat-info">
              <h3 id="totalUnits">0</h3>
              <p>Total Units Available</p>
            </div>
          </div>
          <div class="stat-card">
            <div class="stat-icon blue">
              <i class="fas fa-boxes"></i>
            </div>
            <div class="stat-info">
              <h3 id="modelsInStock">0</h3>
              <p>Models in Stock</p>
            </div>
          </div>
          <div class="stat-card">
            <div class="stat-icon orange">
              <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="stat-info">
              <h3 id="lowStockAlerts">0</h3>
              <p>Low Stock Alerts</p>
            </div>
          </div>
          <div class="stat-card">
            <div class="stat-icon green">
              <i class="fas fa-peso-sign"></i>
            </div>
            <div class="stat-info">
              <h3 id="totalValue">₱0</h3>
              <p>Total Inventory Value</p>
            </div>
          </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
          <div class="search-input">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" placeholder="Search vehicles...">
          </div>
          <select class="filter-select" id="categoryFilter">
            <option value="">All Categories</option>
          </select>
          <select class="filter-select" id="statusFilter">
            <option value="">All Status</option>
            <option value="available">Available</option>
            <option value="low-stock">Low Stock</option>
            <option value="out-of-stock">Out of Stock</option>
          </select>
        </div>

        <!-- Vehicle Grid -->
        <div class="vehicle-grid" id="vehicleGrid">
          <!-- Vehicles will be loaded here as cards by JavaScript. This is where the list of cars is displayed. -->
        </div>

        <!-- No Results Message -->
        <div class="no-results" id="noResults" style="display: none;">
          <i class="fas fa-search" style="font-size: 48px; margin-bottom: 20px;"></i>
          <p>No vehicles found matching your criteria.</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Loading Spinner -->
  <div class="loading-spinner" id="loadingSpinner">
    <div class="spinner"></div>
  </div>

  <!-- Toast Notification -->
  <div class="toast" id="toast"></div>

  <!-- Add/Edit Vehicle Modal -->
  <div class="modal-overlay" id="vehicleModal">
    <div class="modal">
      <div class="modal-header">
        <h3 id="modalTitle">Add New Vehicle</h3>
        <button class="modal-close" onclick="closeVehicleModal()">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <form id="vehicleForm" enctype="multipart/form-data">
        <div class="modal-body">
          <input type="hidden" id="vehicleId" name="id">
          <input type="hidden" id="existingView360Images" name="existing_view_360_images" value="">

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Model Name *</label>
              <input type="text" class="form-control" id="modelName" name="model_name" required>
            </div>
            <div class="form-group">
              <label class="form-label">Variant *</label>
              <input type="text" class="form-control" id="variant" name="variant" required>
            </div>
          </div>
          
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Year Model *</label>
              <input type="number" class="form-control" id="yearModel" name="year_model" min="2000" max="2030" required>
            </div>
            <div class="form-group">
              <label class="form-label">Category *</label>
              <select class="form-control" id="category" name="category" required>
                <option value="">Select Category</option>
                <option value="Sedan">Sedan</option>
                <option value="SUV">SUV</option>
                <option value="Pickup">Pickup</option>
                <option value="Hatchback">Hatchback</option>
                <option value="MPV">MPV</option>
              </select>
            </div>
          </div>
          
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Engine Type *</label>
              <input type="text" class="form-control" id="engineType" name="engine_type" required>
            </div>
            <div class="form-group">
              <label class="form-label">Transmission *</label>
              <select class="form-control" id="transmission" name="transmission" required>
                <option value="">Select Transmission</option>
                <option value="Manual">Manual</option>
                <option value="Automatic">Automatic</option>
                <option value="CVT">CVT</option>
              </select>
            </div>
          </div>
          
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Fuel Type *</label>
              <select class="form-control" id="fuelType" name="fuel_type" required>
                <option value="">Select Fuel Type</option>
                <option value="Gasoline">Gasoline</option>
                <option value="Diesel">Diesel</option>
                <option value="Hybrid">Hybrid</option>
                <option value="Electric">Electric</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Seating Capacity *</label>
              <input type="number" class="form-control" id="seatingCapacity" name="seating_capacity" min="2" max="9" required>
            </div>
          </div>
          
          <div class="form-row full-width">
            <div class="form-group">
              <label class="form-label">Key Features</label>
              <textarea class="form-control" id="keyFeatures" name="key_features" rows="3"></textarea>
            </div>
          </div>
          
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Base Price *</label>
              <input type="number" class="form-control" id="basePrice" name="base_price" min="0" step="0.01" required>
            </div>
            <div class="form-group">
              <label class="form-label">Promotional Price</label>
              <input type="number" class="form-control" id="promotionalPrice" name="promotional_price" min="0" step="0.01">
            </div>
          </div>
          
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Min Downpayment % *</label>
              <input type="number" class="form-control" id="minDownpayment" name="min_downpayment_percentage" min="0" max="100" required>
            </div>
            <div class="form-group">
              <label class="form-label">Financing Terms</label>
              <input type="text" class="form-control" id="financingTerms" name="financing_terms">
            </div>
          </div>
          
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Color Options *</label>
              <input type="text" class="form-control" id="colorOptions" name="color_options" placeholder="e.g., White Pearl, Black Mica, Silver" required>
            </div>
            <div class="form-group">
              <label class="form-label">Popular Color</label>
              <input type="text" class="form-control" id="popularColor" name="popular_color">
            </div>
          </div>
          
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Stock Quantity *</label>
              <input type="number" class="form-control" id="stockQuantity" name="stock_quantity" min="0" required>
            </div>
            <div class="form-group">
              <label class="form-label">Min Stock Alert *</label>
              <input type="number" class="form-control" id="minStockAlert" name="min_stock_alert" min="0" required>
            </div>
          </div>
          
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Availability Status *</label>
              <select class="form-control" id="availabilityStatus" name="availability_status" required>
                <option value="available">Available</option>
                <option value="pre-order">Pre-order</option>
                <option value="discontinued">Discontinued</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Expected Delivery Time</label>
              <input type="text" class="form-control" id="expectedDelivery" name="expected_delivery_time" placeholder="e.g., 2-3 weeks">
            </div>
          </div>
          
          <div class="image-upload-section">
            <label class="image-upload-label">Main Image</label>
            <input type="file" class="file-input" id="mainImage" name="main_image" accept="image/*">
            <div class="file-info">Max size: 10MB per file</div>

            <label class="image-upload-label">Additional Images</label>
            <input type="file" class="file-input" id="additionalImages" name="additional_images[]" accept="image/*" multiple>
            <div class="file-info">Max size: 10MB per file</div>

            <label class="image-upload-label" style="margin-top:12px;">3D Models by Color</label>

            <!-- Display existing 3D models -->
            <div id="existingModelsDisplay" style="margin-top: 12px; display: none;">
              <label class="image-upload-label">Current 3D Models:</label>
              <div id="existingModelsList" style="padding: 10px; background: #f5f5f5; border-radius: 4px; margin-bottom: 10px;">
                <!-- Will be populated dynamically -->
              </div>
              <div class="file-info" style="color: #666; margin-top: 5px;">
                Upload new models below to replace these, or leave empty to keep existing models.
              </div>
            </div>

            <div id="colorModelList"></div>
            <button type="button" class="btn btn-secondary" id="addColorModelBtn" style="margin-top:8px;">Add Color & Model</button>
            <div class="file-info">Pair each color with its .glb/.gltf file.</div>

            <!-- Upload Progress Bar -->
            <div id="uploadProgressContainer" style="display: none; margin-top: 15px;">
              <label class="form-label" style="margin-bottom: 8px;">Upload Progress</label>
              <div style="background: #f0f0f0; border-radius: 8px; overflow: hidden; height: 30px; position: relative; border: 1px solid #ddd;">
                <div id="uploadProgressBar" style="
                  width: 0%;
                  height: 100%;
                  background: linear-gradient(90deg, #e60012 0%, #ff3333 100%);
                  transition: width 0.3s ease;
                  display: flex;
                  align-items: center;
                  justify-content: center;
                  position: relative;
                ">
                  <span id="uploadProgressText" style="
                    position: absolute;
                    width: 100%;
                    text-align: center;
                    color: #333;
                    font-weight: 600;
                    font-size: 13px;
                    z-index: 2;
                    mix-blend-mode: difference;
                    color: white;
                  ">0%</span>
                </div>
              </div>
              <div id="uploadProgressDetails" style="
                display: flex;
                justify-content: space-between;
                margin-top: 5px;
                font-size: 12px;
                color: #666;
              ">
                <span id="uploadSpeed">Speed: 0 MB/s</span>
                <span id="uploadETA">Time remaining: calculating...</span>
                <span id="uploadSize">0 MB / 0 MB</span>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" onclick="closeVehicleModal()">Cancel</button>
          <button type="submit" class="btn btn-primary">
            <span id="submitBtnText">Add Vehicle</span>
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Delete Confirmation Modal -->
  <div class="modal-overlay" id="deleteModal">
    <div class="modal delete-modal">
      <div class="modal-header">
        <h3>Confirm Delete</h3>
        <button class="modal-close" onclick="closeDeleteModal()">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div class="modal-body">
        <p>Are you sure you want to delete this vehicle?</p>
        <div class="vehicle-info" id="deleteVehicleInfo"></div>
        <p style="color: var(--primary-red); font-weight: 600;">This action cannot be undone!</p>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
        <button class="btn btn-primary" onclick="confirmDelete()">Delete</button>
      </div>
    </div>
  </div>

  <!-- Update Stock Modal -->
  <div class="modal-overlay" id="updateStockModal">
    <div class="modal">
      <div class="modal-header">
        <h3 id="updateStockModalTitle">Update Stock</h3>
        <button class="modal-close" onclick="closeUpdateStockModal()">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <form id="updateStockForm">
        <div class="modal-body">
          <input type="hidden" id="updateStockVehicleId" name="id">
          <p>Vehicle: <strong id="updateStockVehicleName"></strong></p>
          <p>Current Stock: <strong id="currentStockValue"></strong></p>
          <div class="form-group">
            <label class="form-label" for="newStockQuantity">New Stock Quantity *</label>
            <input type="number" class="form-control" id="newStockQuantity" name="stock_quantity" min="0" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" onclick="closeUpdateStockModal()">Cancel</button>
          <button type="submit" class="btn btn-primary">Update Stock</button>
        </div>
      </form>
    </div>
  </div>

  <!-- View Details Modal -->
  <div class="modal-overlay" id="viewDetailsModal">
    <div class="modal view-details-modal" style="max-width: 900px; max-height: 95vh; min-height: 90vh;"> 
      <div class="modal-header">
        <h3 id="viewDetailsModalTitle">Vehicle Details</h3>
        <button class="modal-close" onclick="closeViewDetailsModal()">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div class="modal-body" id="viewDetailsModalBody">
        <!-- Details will be populated by JavaScript -->
        <div class="main-image-container" id="viewDetailMainImage"></div>
        
        <div class="detail-section">
          <h4 id="viewDetailModelName"></h4>
          <p><strong>Variant:</strong> <span id="viewDetailVariant"></span></p>
          <p><strong>Year Model:</strong> <span id="viewDetailYearModel"></span></p>
          <p><strong>Category:</strong> <span id="viewDetailCategory"></span></p>
        </div>

        <div class="detail-section">
          <h4>Specifications</h4>
          <p><strong>Engine Type:</strong> <span id="viewDetailEngineType"></span></p>
          <p><strong>Transmission:</strong> <span id="viewDetailTransmission"></span></p>
          <p><strong>Fuel Type:</strong> <span id="viewDetailFuelType"></span></p>
          <p><strong>Seating Capacity:</strong> <span id="viewDetailSeatingCapacity"></span> seats</p>
        </div>

        <div class="detail-section">
          <h4>Pricing & Availability</h4>
          <p><strong>Base Price:</strong> ₱<span id="viewDetailBasePrice"></span></p>
          <p><strong>Promotional Price:</strong> ₱<span id="viewDetailPromotionalPrice"></span></p>
          <p><strong>Stock Quantity:</strong> <span id="viewDetailStockQuantity"></span> units</p>
          <p><strong>Availability Status:</strong> <span id="viewDetailAvailabilityStatus"></span></p>
          <p><strong>Expected Delivery:</strong> <span id="viewDetailExpectedDelivery"></span></p>
        </div>
        
        <div class="detail-section">
          <h4>Features & Options</h4>
          <p><strong>Key Features:</strong> <span id="viewDetailKeyFeatures"></span></p>
          <p><strong>Color Options:</strong> <span id="viewDetailColorOptions"></span></p>
          <p><strong>Popular Color:</strong> <span id="viewDetailPopularColor"></span></p>
        </div>

        <div class="detail-section" id="viewDetailAdditionalImagesSection">
          <h4>Additional Images</h4>
          <div class="image-gallery" id="viewDetailAdditionalImages"></div>
        </div>

        <div class="detail-section" id="viewDetail360ImagesSection">
          <h4>360° View Images</h4>
          <div class="image-gallery" id="viewDetail360Images"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeViewDetailsModal()">Close</button>
      </div>
    </div>
  </div>

  <script src="../../includes/js/common-scripts.js"></script>
  <script>
    // Global variables
    let vehicles = [];
    let deleteVehicleId = null;
    let updateStockVehicleData = null; // To store data for stock update modal
    const userRole = '<?php echo $user_role; // Ensure this is properly escaped if used directly in JS, though it's used for conditional rendering in PHP/HTML templates mostly ?>';
    
    // DOM elements
    const vehicleGrid = document.getElementById('vehicleGrid');
    const searchInput = document.getElementById('searchInput');
    const categoryFilter = document.getElementById('categoryFilter');
    const statusFilter = document.getElementById('statusFilter');
    const loadingSpinner = document.getElementById('loadingSpinner');
    const toast = document.getElementById('toast');
    const vehicleForm = document.getElementById('vehicleForm');
    const updateStockForm = document.getElementById('updateStockForm'); // New form
    
    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
      loadVehicles();
      loadCategories();
      loadStats();
      
      // Event listeners
      searchInput.addEventListener('input', debounce(filterVehicles, 300));
      categoryFilter.addEventListener('change', filterVehicles);
      statusFilter.addEventListener('change', filterVehicles);
      vehicleForm.addEventListener('submit', handleVehicleSubmit);
      updateStockForm.addEventListener('submit', handleUpdateStockSubmit); // Listener for new form

      // Color-model add button
      const addBtn = document.getElementById('addColorModelBtn');
      if (addBtn) addBtn.addEventListener('click', addColorModelRow);
    });

    function addColorModelRow(colorVal = '', fileRequired = false) {
      const list = document.getElementById('colorModelList');
      if (!list) return;
      const row = document.createElement('div');
      row.className = 'form-row';
      row.style.alignItems = 'center';
      row.style.gap = '10px';
      row.innerHTML = `
        <div class="form-group" style="flex:1 1 40%">
          <input type="text" class="form-control" name="color_model_colors[]" placeholder="Color name (e.g., White)" value="${colorVal || ''}">
        </div>
        <div class="form-group" style="flex:1 1 50%">
          <input type="file" class="file-input" name="color_model_files[]" accept=".glb,.gltf" ${fileRequired ? 'required' : ''}>
        </div>
        <div class="form-group" style="flex:0 0 auto">
          <button type="button" class="btn btn-secondary" onclick="this.closest('div.form-row').remove()">Remove</button>
        </div>
      `;
      list.appendChild(row);
    }
    
    // Load vehicles from API
    async function loadVehicles() {
      showLoading(true);
      try {
        const response = await fetch('../../api/vehicles.php');
        const data = await response.json();
        
        if (data.success) {
          vehicles = data.data;
          displayVehicles(vehicles);
        } else {
          showToast('Failed to load vehicles', 'error');
        }
      } catch (error) {
        console.error('Error loading vehicles:', error);
        showToast('Error loading vehicles', 'error');
      } finally {
        showLoading(false);
      }
    }
    
    // Load categories
    async function loadCategories() {
      try {
        const response = await fetch('../../api/vehicles.php?categories=1');
        const data = await response.json();
        
        if (data.success) {
          const select = document.getElementById('categoryFilter');
          data.data.forEach(category => {
            const option = document.createElement('option');
            option.value = category;
            option.textContent = category;
            select.appendChild(option);
          });
        }
      } catch (error) {
        console.error('Error loading categories:', error);
      }
    }
    
    // Load statistics
    async function loadStats() {
      try {
        const response = await fetch('../../api/vehicles.php?stats=1');
        const data = await response.json();
        
        if (data.success) {
          document.getElementById('totalUnits').textContent = data.data.total_units;
          document.getElementById('modelsInStock').textContent = data.data.models_in_stock;
          document.getElementById('lowStockAlerts').textContent = data.data.low_stock_alerts;
          document.getElementById('totalValue').textContent = '₱' + formatNumber(data.data.total_value);
        }
      } catch (error) {
        console.error('Error loading stats:', error);
      }
    }
    
    // Display vehicles
    function displayVehicles(vehiclesToDisplay) {
      vehicleGrid.innerHTML = ''; // Clears previous list
      const noResults = document.getElementById('noResults');
      
      if (vehiclesToDisplay.length === 0) {
        noResults.style.display = 'block';
        return;
      }
      
      noResults.style.display = 'none';
      
      vehiclesToDisplay.forEach(vehicle => {
        const card = createVehicleCard(vehicle); // Creates a card for each vehicle
        vehicleGrid.appendChild(card); // Adds the card to the grid
      });
    }
    
    // Create vehicle card
    function createVehicleCard(vehicle) {
      const stockPercentage = (vehicle.stock_quantity / (vehicle.min_stock_alert > 0 ? (vehicle.min_stock_alert * 2) : (vehicle.stock_quantity > 0 ? vehicle.stock_quantity * 2 : 10 ))) * 100; // Avoid division by zero
      const stockStatus = getStockStatus(vehicle);
      
      const card = document.createElement('div');
      card.className = 'vehicle-card';
      // The innerHTML below defines the structure for each vehicle item in the list.
      card.innerHTML = `
        <div class="vehicle-header">
          <div class="vehicle-name">${vehicle.model_name} ${vehicle.variant}</div>
          <div class="stock-level">
            <span>${vehicle.stock_quantity} units</span>
            <div class="stock-bar">
              <div class="stock-fill" style="width: ${Math.min(stockPercentage, 100)}%;"></div>
            </div>
            <span class="status ${stockStatus}">${formatStatus(stockStatus)}</span>
          </div>
        </div>
        <p>${vehicle.key_features || 'No description available'}</p>
        <div class="info-cards" style="grid-template-columns: repeat(3, 1fr); margin: 15px 0;">
          <div class="info-card" style="padding: 10px;">
            <div class="info-card-title">Price</div>
            <div class="info-card-value" style="font-size: 16px;">₱${formatNumber(vehicle.base_price)}</div>
          </div>
          <div class="info-card" style="padding: 10px;">
            <div class="info-card-title">Category</div>
            <div class="info-card-value" style="font-size: 16px;">${vehicle.category}</div>
          </div>
          <div class="info-card" style="padding: 10px;">
            <div class="info-card-title">Year</div>
            <div class="info-card-value" style="font-size: 16px;">${vehicle.year_model}</div>
          </div>
        </div>
        <div class="action-area">
          ${userRole === 'Admin' ? `
            <button class="btn btn-primary" onclick="openEditModal(${vehicle.id})">Edit</button>
            <button class="btn btn-secondary" onclick="openDeleteModal(${vehicle.id})">Delete</button>
            <button class="btn btn-outline" onclick="openUpdateStockModal(${vehicle.id})">Update Stock</button> 
          ` : `
            <button class="btn btn-outline" onclick="viewDetails(${vehicle.id})">View Details</button>
          `}
        </div>
      `;
      
      return card;
    }
    
    // Get stock status
    function getStockStatus(vehicle) {
      if (vehicle.stock_quantity === 0) {
        return 'out-of-stock';
      } else if (vehicle.stock_quantity <= vehicle.min_stock_alert) {
        return 'low-stock';
      }
      return 'available';
    }
    
    // Format status text
    function formatStatus(status) {
      const statusMap = {
        'available': 'Available',
        'low-stock': 'Low Stock',
        'out-of-stock': 'Out of Stock'
      };
      return statusMap[status] || status;
    }
    
    // Filter vehicles
    function filterVehicles() {
      const searchTerm = searchInput.value.toLowerCase();
      const category = categoryFilter.value;
      const status = statusFilter.value;
      
      const filtered = vehicles.filter(vehicle => {
        const matchesSearch = vehicle.model_name.toLowerCase().includes(searchTerm) || 
                            vehicle.variant.toLowerCase().includes(searchTerm);
        const matchesCategory = !category || vehicle.category === category;
        const matchesStatus = !status || getStockStatus(vehicle) === status;
        
        return matchesSearch && matchesCategory && matchesStatus;
      });
      
      displayVehicles(filtered);
    }
    
    // Modal functions
    function openAddModal() {
      document.getElementById('modalTitle').textContent = 'Add New Vehicle';
      document.getElementById('submitBtnText').textContent = 'Add Vehicle';
      document.getElementById('vehicleId').value = '';
      vehicleForm.reset();
      document.getElementById('vehicleModal').classList.add('active');
    }
    
    async function openEditModal(id) {
      showLoading(true);
      try {
        const response = await fetch(`../../api/vehicles.php?id=${id}`);
        const data = await response.json();
        
        if (data.success) {
          const vehicle = data.data;
          document.getElementById('modalTitle').textContent = 'Edit Vehicle';
          document.getElementById('submitBtnText').textContent = 'Update Vehicle';
          document.getElementById('vehicleId').value = vehicle.id;
          
          // Populate form fields
          document.getElementById('modelName').value = vehicle.model_name;
          document.getElementById('variant').value = vehicle.variant;
          document.getElementById('yearModel').value = vehicle.year_model;
          document.getElementById('category').value = vehicle.category;
          document.getElementById('engineType').value = vehicle.engine_type;
          document.getElementById('transmission').value = vehicle.transmission;
          document.getElementById('fuelType').value = vehicle.fuel_type;
          document.getElementById('seatingCapacity').value = vehicle.seating_capacity;
          document.getElementById('keyFeatures').value = vehicle.key_features || '';
          document.getElementById('basePrice').value = vehicle.base_price;
          document.getElementById('promotionalPrice').value = vehicle.promotional_price || '';
          document.getElementById('minDownpayment').value = vehicle.min_downpayment_percentage;
          document.getElementById('financingTerms').value = vehicle.financing_terms || '';
          document.getElementById('colorOptions').value = vehicle.color_options;
          document.getElementById('popularColor').value = vehicle.popular_color || '';
          document.getElementById('stockQuantity').value = vehicle.stock_quantity;
          document.getElementById('minStockAlert').value = vehicle.min_stock_alert;
          document.getElementById('availabilityStatus').value = vehicle.availability_status;
          document.getElementById('expectedDelivery').value = vehicle.expected_delivery_time || '';

          // Handle existing 3D models display
          if (vehicle.view_360_images) {
            try {
              console.log('Raw view_360_images:', vehicle.view_360_images);

              let view360Data;
              if (typeof vehicle.view_360_images === 'string') {
                view360Data = JSON.parse(vehicle.view_360_images);
              } else {
                view360Data = vehicle.view_360_images;
              }

              console.log('Parsed view360Data:', view360Data);

              if (view360Data && (Array.isArray(view360Data) || typeof view360Data === 'object')) {
                // Check if it's not empty
                const hasData = Array.isArray(view360Data) ? view360Data.length > 0 : Object.keys(view360Data).length > 0;

                if (hasData) {
                  displayExistingModels(view360Data);
                  // Store in hidden input for preservation
                  document.getElementById('existingView360Images').value = JSON.stringify(view360Data);
                } else {
                  console.log('view360Data is empty');
                  document.getElementById('existingModelsDisplay').style.display = 'none';
                  document.getElementById('existingView360Images').value = '';
                }
              } else {
                console.log('view360Data is not valid array or object');
                document.getElementById('existingModelsDisplay').style.display = 'none';
                document.getElementById('existingView360Images').value = '';
              }
            } catch (e) {
              console.error('Error parsing view_360_images:', e);
              document.getElementById('existingModelsDisplay').style.display = 'none';
              document.getElementById('existingView360Images').value = '';
            }
          } else {
            // No existing models
            console.log('No view_360_images found');
            document.getElementById('existingModelsDisplay').style.display = 'none';
            document.getElementById('existingView360Images').value = '';
          }

          document.getElementById('vehicleModal').classList.add('active');
        } else {
          showToast('Failed to load vehicle details', 'error');
        }
      } catch (error) {
        console.error('Error loading vehicle:', error);
        showToast('Error loading vehicle details', 'error');
      } finally {
        showLoading(false);
      }
    }
    
    function closeVehicleModal() {
      document.getElementById('vehicleModal').classList.remove('active');
      vehicleForm.reset();

      // Reset hidden fields and clear any remaining data following memory requirements
      document.getElementById('vehicleId').value = '';

      // Clear existing models display
      document.getElementById('existingModelsDisplay').style.display = 'none';
      document.getElementById('existingModelsList').innerHTML = '';
      document.getElementById('existingView360Images').value = '';
      document.getElementById('colorModelList').innerHTML = '';

      // Hide and reset progress bar immediately
      document.getElementById('uploadProgressContainer').style.display = 'none';
      resetProgressBar();

      // Clear any validation states
      const inputs = vehicleForm.querySelectorAll('input, select, textarea');
      inputs.forEach(input => {
        input.classList.remove('error', 'valid');
      });
    }

    // Display existing 3D models in edit modal
    function displayExistingModels(modelsData) {
      console.log('displayExistingModels called with:', modelsData);

      const displaySection = document.getElementById('existingModelsDisplay');
      const modelsList = document.getElementById('existingModelsList');

      if (!displaySection || !modelsList) {
        console.error('Display elements not found!');
        return;
      }

      if (!modelsData || (Array.isArray(modelsData) && modelsData.length === 0)) {
        console.log('No models data to display');
        displaySection.style.display = 'none';
        return;
      }

      displaySection.style.display = 'block';
      modelsList.innerHTML = '';

      // Check if it's color-model mapping format
      const isColorMapping = Array.isArray(modelsData) &&
        modelsData.length > 0 &&
        modelsData[0].color !== undefined &&
        modelsData[0].model !== undefined;

      console.log('Is color mapping format:', isColorMapping);

      if (isColorMapping) {
        // Display color-specific models
        console.log('Displaying color-specific models:', modelsData.length);
        modelsData.forEach((item, index) => {
          const modelDiv = document.createElement('div');
          modelDiv.style.cssText = 'padding: 8px; margin-bottom: 5px; background: white; border-radius: 3px; display: flex; justify-content: space-between; align-items: center;';
          modelDiv.innerHTML = `
            <div>
              <strong style="color: #e60012;">Color:</strong> ${escapeHtml(item.color || 'Unknown')}
              <span style="margin-left: 15px;"><strong>Model:</strong> ${escapeHtml(item.model.split('/').pop())}</span>
            </div>
            <span style="color: #28a745; font-size: 12px;">
              <i class="fas fa-check-circle"></i> Uploaded
            </span>
          `;
          modelsList.appendChild(modelDiv);
        });
      } else if (Array.isArray(modelsData)) {
        // Display generic model list
        console.log('Displaying generic models:', modelsData.length);
        modelsData.forEach((modelPath, index) => {
          const modelDiv = document.createElement('div');
          modelDiv.style.cssText = 'padding: 8px; margin-bottom: 5px; background: white; border-radius: 3px; display: flex; justify-content: space-between; align-items: center;';

          const fileName = modelPath.split('/').pop();
          const isModel = fileName.toLowerCase().endsWith('.glb') || fileName.toLowerCase().endsWith('.gltf');
          const icon = isModel ? 'fa-cube' : 'fa-image';

          modelDiv.innerHTML = `
            <div>
              <i class="fas ${icon}" style="margin-right: 8px; color: #666;"></i>
              <strong>${escapeHtml(fileName)}</strong>
            </div>
            <span style="color: #28a745; font-size: 12px;">
              <i class="fas fa-check-circle"></i> Uploaded
            </span>
          `;
          modelsList.appendChild(modelDiv);
        });
      } else {
        console.log('Unknown data format:', typeof modelsData);
      }

      console.log('Display section visible:', displaySection.style.display);
      console.log('Models list children:', modelsList.children.length);
    }

    // Helper function to escape HTML
    function escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }

    function openDeleteModal(id) {
      deleteVehicleId = id;
      const vehicle = vehicles.find(v => v.id === id);
      document.getElementById('deleteVehicleInfo').innerHTML = `
        <strong>${vehicle.model_name} ${vehicle.variant}</strong><br>
        Stock: ${vehicle.stock_quantity} units<br>
        Price: ₱${formatNumber(vehicle.base_price)}
      `;
      document.getElementById('deleteModal').classList.add('active');
    }
    
    function closeDeleteModal() {
      document.getElementById('deleteModal').classList.remove('active');
      deleteVehicleId = null;
    }

    // New Modal functions for Update Stock
    function openUpdateStockModal(id) {
      const vehicle = vehicles.find(v => v.id === id);
      if (vehicle) {
        updateStockVehicleData = vehicle;
        document.getElementById('updateStockVehicleId').value = vehicle.id;
        document.getElementById('updateStockVehicleName').textContent = `${vehicle.model_name} ${vehicle.variant}`;
        document.getElementById('currentStockValue').textContent = vehicle.stock_quantity;
        document.getElementById('newStockQuantity').value = vehicle.stock_quantity; // Pre-fill with current stock
        document.getElementById('updateStockModal').classList.add('active');
      } else {
        showToast('Vehicle not found for stock update.', 'error');
      }
    }

    function closeUpdateStockModal() {
      document.getElementById('updateStockModal').classList.remove('active');
      updateStockVehicleData = null;
      document.getElementById('updateStockForm').reset();
    }

    // View Details Modal Functions
    async function viewDetails(id) {
      showLoading(true);
      try {
        const response = await fetch(`../../api/vehicles.php?id=${id}&include_images=1`);
        const data = await response.json();

        if (data.success && data.data) {
          const vehicle = data.data;
          document.getElementById('viewDetailModelName').textContent = `${vehicle.model_name || 'N/A'}`;
          document.getElementById('viewDetailVariant').textContent = vehicle.variant || 'N/A';
          document.getElementById('viewDetailYearModel').textContent = vehicle.year_model || 'N/A';
          document.getElementById('viewDetailCategory').textContent = vehicle.category || 'N/A';
          
          document.getElementById('viewDetailEngineType').textContent = vehicle.engine_type || 'N/A';
          document.getElementById('viewDetailTransmission').textContent = vehicle.transmission || 'N/A';
          document.getElementById('viewDetailFuelType').textContent = vehicle.fuel_type || 'N/A';
          document.getElementById('viewDetailSeatingCapacity').textContent = vehicle.seating_capacity || 'N/A';

          document.getElementById('viewDetailBasePrice').textContent = formatNumber(vehicle.base_price || 0);
          document.getElementById('viewDetailPromotionalPrice').textContent = vehicle.promotional_price ? formatNumber(vehicle.promotional_price) : 'N/A';
          document.getElementById('viewDetailStockQuantity').textContent = vehicle.stock_quantity !== null ? vehicle.stock_quantity : 'N/A';
          document.getElementById('viewDetailAvailabilityStatus').textContent = vehicle.availability_status || 'N/A';
          document.getElementById('viewDetailExpectedDelivery').textContent = vehicle.expected_delivery_time || 'N/A';

          document.getElementById('viewDetailKeyFeatures').textContent = vehicle.key_features || 'N/A';
          document.getElementById('viewDetailColorOptions').textContent = vehicle.color_options || 'N/A';
          document.getElementById('viewDetailPopularColor').textContent = vehicle.popular_color || 'N/A';

          const mainImageContainer = document.getElementById('viewDetailMainImage');
mainImageContainer.innerHTML = '';
if (vehicle.main_image) {
  const img = document.createElement('img');
  // Handle different image data formats
  if ((/^[A-Za-z0-9+/=]+$/.test(vehicle.main_image) && vehicle.main_image.length > 100) || vehicle.main_image.startsWith('data:image')) {
    // Base64 or data URL
    img.src = vehicle.main_image.startsWith('data:image') ? vehicle.main_image : 'data:image/jpeg;base64,' + vehicle.main_image;
  } else {
    // File path - convert to web-accessible URL
    let imagePath = vehicle.main_image.replace(/\\/g, '/');
    
    // Convert various absolute path formats to relative web paths
    if (imagePath.includes('/htdocs/')) {
      imagePath = imagePath.replace(/^.*\/htdocs\//, '/');
    } else if (imagePath.startsWith('/xampp/htdocs/')) {
      imagePath = imagePath.replace(/^\/xampp\/htdocs\//, '/');
    } else if (imagePath.startsWith('/var/www/html/')) {
      imagePath = imagePath.replace(/^\/var\/www\/html\//, '/');
    } else if (imagePath.includes('/var/www/html/')) {
      imagePath = imagePath.replace(/^.*\/var\/www\/html\//, '/');
    }
    
    // Ensure path starts with forward slash if it doesn't already
    if (!imagePath.startsWith('/')) {
      imagePath = '/' + imagePath;
    }
    
    img.src = imagePath;
  }
  img.alt = `${vehicle.model_name || 'Vehicle'} Main Image`;
  img.onerror = function() {
    console.error('Main image failed to load:', img.src);
    this.parentNode.innerHTML = '<p style="color: #666; padding: 20px; text-align: center;">Main image not available</p>';
  };
  mainImageContainer.appendChild(img);
} else {
  mainImageContainer.innerHTML = '<p style="color: #666; padding: 20px; text-align: center;">No main image available.</p>';
}

          const additionalImagesContainer = document.getElementById('viewDetailAdditionalImages');
additionalImagesContainer.innerHTML = '';
const additionalImagesSection = document.getElementById('viewDetailAdditionalImagesSection');
if (vehicle.additional_images && Array.isArray(vehicle.additional_images) && vehicle.additional_images.length > 0) {
  vehicle.additional_images.forEach(imageData => {
    const img = document.createElement('img');
    // Handle different image data formats
    if ((/^[A-Za-z0-9+/=]+$/.test(imageData) && imageData.length > 100) || imageData.startsWith('data:image')) {
      img.src = imageData.startsWith('data:image') ? imageData : 'data:image/jpeg;base64,' + imageData;
    } else {
      // File path - convert to web-accessible URL
      let imagePath = imageData.replace(/\\/g, '/');
      
      // Convert various absolute path formats to relative web paths
      if (imagePath.includes('/htdocs/')) {
        imagePath = imagePath.replace(/^.*\/htdocs\//, '/');
      } else if (imagePath.startsWith('/xampp/htdocs/')) {
        imagePath = imagePath.replace(/^\/xampp\/htdocs\//, '/');
      } else if (imagePath.startsWith('/var/www/html/')) {
        imagePath = imagePath.replace(/^\/var\/www\/html\//, '/');
      } else if (imagePath.includes('/var/www/html/')) {
        imagePath = imagePath.replace(/^.*\/var\/www\/html\//, '/');
      }
      
      // Ensure path starts with forward slash if it doesn't already
      if (!imagePath.startsWith('/')) {
        imagePath = '/' + imagePath;
      }
      
      img.src = imagePath;
    }
    img.onerror = function() {
      console.error('Additional image failed to load:', this.src);
      this.style.display = 'none';
    };
    additionalImagesContainer.appendChild(img);
  });
  additionalImagesSection.style.display = 'block';
} else {
  additionalImagesSection.style.display = 'none';
}
          
          const view360ImagesContainer = document.getElementById('viewDetail360Images');
          view360ImagesContainer.innerHTML = '';
          const view360ImagesSection = document.getElementById('viewDetail360ImagesSection');
          if (vehicle.view_360_images && Array.isArray(vehicle.view_360_images) && vehicle.view_360_images.length > 0) {
            vehicle.view_360_images.forEach(filePath => {
              // Check if it's a GLB/GLTF file for 3D models
              if (filePath.toLowerCase().endsWith('.glb') || filePath.toLowerCase().endsWith('.gltf')) {
                const modelViewer = document.createElement('div');
                modelViewer.innerHTML = `<p><strong>3D Model:</strong> ${filePath.split('/').pop()}</p><p><em>3D viewer integration coming soon</em></p>`;
                modelViewer.style.padding = '10px';
                modelViewer.style.border = '1px solid #ddd';
                modelViewer.style.borderRadius = '4px';
                modelViewer.style.margin = '5px';
                view360ImagesContainer.appendChild(modelViewer);
              } else {
                // Regular image file
                const img = document.createElement('img');
                const webPath = filePath.replace(/\//g, '/').replace(/^.*\/htdocs/, '');
                img.src = webPath;
                img.onerror = function() {
                  this.style.display = 'none';
                };
                view360ImagesContainer.appendChild(img);
              }
            });
            view360ImagesSection.style.display = 'block';
          } else {
            view360ImagesSection.style.display = 'none';
          }

          document.getElementById('viewDetailsModal').classList.add('active');
        } else {
          showToast(data.message || 'Failed to load vehicle details.', 'error');
        }
      } catch (error) {
        console.error('Error viewing details:', error);
        showToast('An error occurred while fetching vehicle details.', 'error');
      } finally {
        showLoading(false);
      }
    }

    function closeViewDetailsModal() {
      document.getElementById('viewDetailsModal').classList.remove('active');
    }
    
    // Handle form submission
    async function handleVehicleSubmit(e) {
      e.preventDefault();

      // Validate file sizes before submission
      const mainImage = document.getElementById('mainImage').files[0];
      const additionalImages = document.getElementById('additionalImages').files;

      // Check main image size (10MB limit)
      if (mainImage && mainImage.size > 10 * 1024 * 1024) {
        showToast('Main image file is too large. Maximum size is 10MB.', 'error');
        return;
      }

      // Check additional images size (10MB each)
      for (let i = 0; i < additionalImages.length; i++) {
        if (additionalImages[i].size > 10 * 1024 * 1024) {
          showToast(`Additional image ${i + 1} is too large. Maximum size is 10MB per file.`, 'error');
          return;
        }
      }

      // Check color-model files size (50MB each)
      const colorModelFiles = vehicleForm.querySelectorAll('input[name="color_model_files[]"]');
      for (let i = 0; i < colorModelFiles.length; i++) {
        const file = colorModelFiles[i].files && colorModelFiles[i].files[0];
        if (file && file.size > 50 * 1024 * 1024) {
          showToast(`Color model file ${i + 1} is too large. Maximum size is 50MB per file.`, 'error');
          return;
        }
      }

      const formData = new FormData(vehicleForm);
      const vehicleId = document.getElementById('vehicleId').value;
      const isEdit = vehicleId !== '';

      // Add method override for PUT requests since FormData doesn't support PUT directly
      if (isEdit) {
        formData.append('_method', 'PUT');
      }

      // Calculate total upload size
      const totalSize = calculateFormDataSize(formData);
      const hasLargeFiles = totalSize > 1 * 1024 * 1024; // Show progress for files > 1MB

      if (hasLargeFiles) {
        document.getElementById('uploadProgressContainer').style.display = 'block';
        resetProgressBar();
      }

      showLoading(true);

      // Use XMLHttpRequest for accurate progress tracking
      const xhr = new XMLHttpRequest();

      // Track upload progress
      let startTime = Date.now();
      let lastLoaded = 0;
      let lastTime = startTime;

      xhr.upload.addEventListener('progress', (e) => {
        if (e.lengthComputable) {
          const percentComplete = (e.loaded / e.total) * 100;
          const currentTime = Date.now();
          const timeDiff = (currentTime - lastTime) / 1000; // seconds
          const bytesDiff = e.loaded - lastLoaded;

          // Update progress bar
          updateProgressBar(percentComplete, e.loaded, e.total, bytesDiff, timeDiff);

          lastLoaded = e.loaded;
          lastTime = currentTime;
        }
      });

      // Handle upload completion
      xhr.upload.addEventListener('load', () => {
        // Upload complete, now waiting for server processing
        if (hasLargeFiles) {
          updateProgressBar(100, totalSize, totalSize, 0, 0);
          document.getElementById('uploadProgressText').textContent = 'Processing...';
          document.getElementById('uploadProgressDetails').innerHTML =
            '<span style="color: #e60012; font-weight: 600;">Server is processing your upload...</span>';
        }
      });

      // Handle errors
      xhr.upload.addEventListener('error', () => {
        showToast('Upload failed. Please check your connection and try again.', 'error');
        showLoading(false);
        hideProgressBar();
      });

      xhr.upload.addEventListener('abort', () => {
        showToast('Upload cancelled.', 'error');
        showLoading(false);
        hideProgressBar();
      });

      // Handle response
      xhr.addEventListener('load', () => {
        try {
          const result = JSON.parse(xhr.responseText);

          if (xhr.status === 200 && result.success) {
            showToast(result.message || (isEdit ? 'Vehicle updated successfully!' : 'Vehicle added successfully!'), 'success');

            // Complete the progress bar and hide it after a short delay
            if (hasLargeFiles) {
              document.getElementById('uploadProgressText').textContent = '✓ Complete';
              document.getElementById('uploadProgressDetails').innerHTML =
                '<span style="color: #28a745; font-weight: 600;">Upload successful!</span>';

              // Hide progress bar after 1.5 seconds, then close modal
              setTimeout(() => {
                document.getElementById('uploadProgressContainer').style.display = 'none';
                resetProgressBar();
                closeVehicleModal();
                loadVehicles();
                loadStats();
              }, 1500);
            } else {
              // No large files, close immediately
              closeVehicleModal();
              loadVehicles();
              loadStats();
            }
          } else {
            showToast(result.message || 'Operation failed', 'error');
            hideProgressBar();
          }
        } catch (error) {
          console.error('Error parsing response:', error);
          showToast('An error occurred while processing the response', 'error');
          hideProgressBar();
        } finally {
          showLoading(false);
        }
      });

      // Handle network errors
      xhr.addEventListener('error', () => {
        showToast('Network error. Please check your connection.', 'error');
        showLoading(false);
        hideProgressBar();
      });

      // Send the request
      xhr.open('POST', '../../api/vehicles.php', true);
      xhr.send(formData);
    }

    // Calculate total size of FormData (approximate)
    function calculateFormDataSize(formData) {
      let totalSize = 0;

      // Get all file inputs
      const fileInputs = vehicleForm.querySelectorAll('input[type="file"]');
      fileInputs.forEach(input => {
        if (input.files) {
          for (let i = 0; i < input.files.length; i++) {
            totalSize += input.files[i].size;
          }
        }
      });

      return totalSize;
    }

    // Update progress bar with accurate information
    function updateProgressBar(percentage, loaded, total, bytesDiff, timeDiff) {
      const progressBar = document.getElementById('uploadProgressBar');
      const progressText = document.getElementById('uploadProgressText');
      const speedElement = document.getElementById('uploadSpeed');
      const etaElement = document.getElementById('uploadETA');
      const sizeElement = document.getElementById('uploadSize');

      // Update percentage
      progressBar.style.width = percentage.toFixed(1) + '%';
      progressText.textContent = percentage.toFixed(1) + '%';

      // Calculate upload speed (MB/s)
      let speed = 0;
      if (timeDiff > 0) {
        speed = (bytesDiff / timeDiff) / (1024 * 1024); // Convert to MB/s
      }

      // Calculate ETA
      let eta = 'calculating...';
      if (speed > 0 && loaded < total) {
        const remainingBytes = total - loaded;
        const remainingSeconds = remainingBytes / (speed * 1024 * 1024);
        eta = formatTime(remainingSeconds);
      } else if (loaded >= total) {
        eta = 'complete';
      }

      // Update display
      speedElement.textContent = `Speed: ${speed.toFixed(2)} MB/s`;
      etaElement.textContent = `Time remaining: ${eta}`;
      sizeElement.textContent = `${formatBytes(loaded)} / ${formatBytes(total)}`;
    }

    // Reset progress bar
    function resetProgressBar() {
      const progressBar = document.getElementById('uploadProgressBar');
      const progressText = document.getElementById('uploadProgressText');
      const speedElement = document.getElementById('uploadSpeed');
      const etaElement = document.getElementById('uploadETA');
      const sizeElement = document.getElementById('uploadSize');

      progressBar.style.width = '0%';
      progressText.textContent = '0%';
      speedElement.textContent = 'Speed: 0 MB/s';
      etaElement.textContent = 'Time remaining: calculating...';
      sizeElement.textContent = '0 MB / 0 MB';
    }

    // Hide progress bar
    function hideProgressBar() {
      setTimeout(() => {
        document.getElementById('uploadProgressContainer').style.display = 'none';
        resetProgressBar();
      }, 3000); // Keep visible for 3 seconds after completion
    }

    // Format bytes to human-readable format
    function formatBytes(bytes) {
      if (bytes === 0) return '0 Bytes';

      const k = 1024;
      const sizes = ['Bytes', 'KB', 'MB', 'GB'];
      const i = Math.floor(Math.log(bytes) / Math.log(k));

      return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // Format seconds to human-readable time
    function formatTime(seconds) {
      if (seconds < 1) return 'less than a second';
      if (seconds < 60) return Math.round(seconds) + ' seconds';

      const minutes = Math.floor(seconds / 60);
      const remainingSeconds = Math.round(seconds % 60);

      if (minutes < 60) {
        return `${minutes}m ${remainingSeconds}s`;
      }

      const hours = Math.floor(minutes / 60);
      const remainingMinutes = minutes % 60;
      return `${hours}h ${remainingMinutes}m`;
    }

    // Delete vehicle
    async function confirmDelete() {
      if (!deleteVehicleId) return;
      
      showLoading(true);
      
      try {
        const response = await fetch('../../api/vehicles.php', {
          method: 'DELETE',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: `id=${deleteVehicleId}`
        });
        
        const result = await response.json();
        
        if (result.success) {
          showToast('Vehicle deleted successfully', 'success');
          closeDeleteModal();
          loadVehicles();
          loadStats();
        } else {
          showToast(result.message || 'Failed to delete vehicle', 'error');
        }
      } catch (error) {
        console.error('Error:', error);
        showToast('Error deleting vehicle', 'error');
      } finally {
        showLoading(false);
      }
    }
    
    // Update stock (modified to open modal)
    function updateStock(id) { // This function is now just a wrapper to open the modal
        openUpdateStockModal(id);
    }

    // Handle Update Stock Form Submission
    async function handleUpdateStockSubmit(e) {
      e.preventDefault();
      if (!updateStockVehicleData) return;

      const vehicleId = document.getElementById('updateStockVehicleId').value;
      const newStockQuantity = document.getElementById('newStockQuantity').value;

      if (newStockQuantity === null || newStockQuantity === '') {
        showToast('Please enter a stock quantity.', 'error');
        return;
      }
      
      const quantity = parseInt(newStockQuantity);
      if (isNaN(quantity) || quantity < 0) {
        showToast('Please enter a valid quantity.', 'error');
        return;
      }
      
      showLoading(true);
      
      try {
        const response = await fetch('../../api/vehicles.php', {
          method: 'PUT',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          // Send only id and stock_quantity for stock update
          body: `id=${vehicleId}&stock_quantity=${quantity}` 
        });
        
        const result = await response.json();
        
        if (result.success) {
          showToast('Stock updated successfully', 'success');
          closeUpdateStockModal();
          loadVehicles(); // Reload vehicle list
          loadStats();    // Reload statistics
        } else {
          showToast(result.message || 'Failed to update stock', 'error');
        }
      } catch (error) {
        console.error('Error updating stock:', error);
        showToast('Error updating stock', 'error');
      } finally {
        showLoading(false);
      }
    }
    
    // Utility functions
    function showLoading(show) {
      loadingSpinner.classList.toggle('active', show);
    }
    
    function showToast(message, type = 'info') {
      toast.textContent = message;
      toast.className = `toast ${type} show`;
      
      setTimeout(() => {
        toast.classList.remove('show');
      }, 3000);
    }
    
    function formatNumber(num) {
      return new Intl.NumberFormat('en-PH').format(num);
    }
    
    function debounce(func, wait) {
      let timeout;
      return function executedFunction(...args) {
        const later = () => {
          clearTimeout(timeout);
          func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
      };
    }
    
    // Sales agent functions
    function reserveUnit(id) {
      // This function is no longer called from the UI for Sales Agents
      // but kept here in case it's used elsewhere or for future reference.
      alert(`Reserve functionality for vehicle ID: ${id} - Coming soon!`);
    }
    
    // function viewDetails(id) { // This is now implemented above
    //   alert(`View details for vehicle ID: ${id} - Coming soon!`);
    // }
  </script>
</body>
</html>
