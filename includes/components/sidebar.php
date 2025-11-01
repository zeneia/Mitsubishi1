<?php
// Instead of starting session here, check if there's already a session
// No output should come before this point - not even whitespace

// Get current page name for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// Make sure we're getting the role correctly
$user_role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'SalesAgent';

// Uncomment this for debugging the current role value
// echo "<!-- Current Role: " . htmlspecialchars($user_role) . " -->";
?>

<style>
  /* Sidebar container styles */
  .sidebar {
    height: 100vh;
    min-height: 100%; /* Ensure minimum height is full height */
    display: flex;
    flex-direction: column;
    overflow-y: auto;
    position: fixed; /* Fix sidebar position */
    width: 280px; /* Standard width for sidebar */
    left: 0;
    top: 0;
    bottom: 0;
    z-index: 1000;
  }

  .sidebar-header {
    flex-shrink: 0;
  }

  .menu {
    flex: 1 1 auto; /* Allow menu to grow and shrink but prioritize growth */
    display: flex;
    flex-direction: column;
    overflow-y: auto;
    padding-bottom: 20px; /* Add padding at bottom to ensure last items are visible */
  }

  /* Custom scrollbar styling for sidebar */
  .sidebar::-webkit-scrollbar,
  .menu::-webkit-scrollbar {
    width: 4px;
  }

  .sidebar::-webkit-scrollbar-track,
  .menu::-webkit-scrollbar-track {
    background: transparent;
  }

  .sidebar::-webkit-scrollbar-thumb,
  .menu::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 2px;
    transition: background 0.3s ease;
  }

  .sidebar::-webkit-scrollbar-thumb:hover,
  .menu::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.4);
  }

  /* Firefox scrollbar styling */
  .sidebar,
  .menu {
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.2) transparent;
  }

  /* Ensure menu group grows to fill space */
  .menu-group {
    display: flex;
    flex-direction: column;
  }

  /* Handle different screen sizes */
  @media (min-width: 992px) {
    body {
      padding-left: 280px; /* Offset body content for fixed sidebar */
    }
    
    .sidebar {
      box-shadow: 2px 0 5px rgba(0,0,0,0.1); /* Add shadow for visual separation */
    }
  }

  /* Handle mobile view */
  @media (max-width: 991px) {
    .sidebar {
      transform: translateX(-100%);
      transition: transform 0.3s ease;
    }
    
    .sidebar.active {
      transform: translateX(0);
    }
    
    /* Hide scrollbar on mobile for cleaner look */
    .sidebar::-webkit-scrollbar,
    .menu::-webkit-scrollbar {
      width: 0px;
      background: transparent;
    }
  }

  @media (max-width: 991px) {
    body {
      padding-left: 0;
    }
  }
</style>

<button class="menu-toggle" onclick="toggleSidebar()">
  <i class="fas fa-bars"></i>
</button>

<div class="sidebar-overlay" onclick="toggleSidebar()"></div>

<div class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <div class="logo">
      <img src="../../includes/images/mitsubishi_logo.png" alt="Mitsubishi Logo" />
      <div class="logo-text">
        <h1>MITSUBISHI</h1>
        <p>Motors</p>
      </div>
    </div>
  </div>

  <div class="menu">
    <?php if ($user_role === 'Admin'): ?>
      <!-- Admin Menu -->
      <div class="menu-group">
        <a href="dashboard.php" class="menu-item <?php echo $current_page == 'dashboard' ? 'active' : ''; ?>">
          <i class="fas fa-tachometer-alt"></i>
          <span>Dashboard</span>
        </a>
      </div>

      <div class="menu-group">
        <div class="menu-group-label">Product & Inventory</div>
        <a href="product-list.php" class="menu-item <?php echo $current_page == 'product-list' ? 'active' : ''; ?>">
          <i class="fas fa-list"></i>
          <span>Product List</span>
        </a>
        <a href="inventory.php" class="menu-item <?php echo $current_page == 'inventory' ? 'active' : ''; ?>">
          <i class="fas fa-warehouse"></i>
          <span>Inventory</span>
        </a>
      </div>

      <div class="menu-group">
        <div class="menu-group-label">User Management</div>
        <a href="accounts.php" class="menu-item <?php echo $current_page == 'accounts' ? 'active' : ''; ?>">
          <i class="fas fa-users-cog"></i>
          <span>Account Control</span>
        </a>
      </div>

      <div class="menu-group">
        <div class="menu-group-label">Customer Inquiries</div>
        <a href="inquiries.php" class="menu-item <?php echo $current_page == 'inquiries' ? 'active' : ''; ?>">
          <i class="fas fa-question-circle"></i>
          <span>Inquiry Records</span>
        </a>
        <!-- <a href="inquiry-reports.php" class="menu-item <?php echo $current_page == 'inquiry-reports' ? 'active' : ''; ?>">
          <i class="fas fa-chart-pie"></i>
          <span>Inquiry Analytics</span>
        </a> -->
      </div>

      <div class="menu-group">
        <div class="menu-group-label">Communications</div>
        <a href="sms.php" class="menu-item <?php echo $current_page == 'sms' ? 'active' : ''; ?>" aria-current="<?php echo $current_page == 'sms' ? 'page' : 'false'; ?>">
          <i class="fas fa-comment-dots"></i>
          <span>SMS</span>
        </a>
        <a href="email-management.php" class="menu-item <?php echo $current_page == 'email-management' ? 'active' : ''; ?>">
          <i class="fas fa-envelope"></i>
          <span>Email Management</span>
        </a>
      </div>

      <div class="menu-group">
        <div class="menu-group-label">Service Management</div>
        <a href="pms-tracking.php" class="menu-item <?php echo $current_page == 'pms-tracking' ? 'active' : ''; ?>">
          <i class="fas fa-calendar-check"></i>
          <span>PMS Tracking</span>
        </a>
      </div>

      <div class="menu-group">
        <div class="menu-group-label">Reports & Analytics</div>
        <a href="transaction-records.php" class="menu-item <?php echo $current_page == 'transaction-records' ? 'active' : ''; ?>">
          <i class="fas fa-file-invoice"></i>
          <span>Transaction Records</span>
        </a>
        <a href="sales-report.php" class="menu-item <?php echo $current_page == 'sales-report' ? 'active' : ''; ?>">
          <i class="fas fa-chart-line"></i>
          <span>Sales Report</span>
        </a>
       
      </div>

      <div class="menu-group">
        <div class="menu-group-label">System Settings</div>
        <a href="settings.php" class="menu-item <?php echo $current_page == 'settings' ? 'active' : ''; ?>">
          <i class="fas fa-cog"></i>
          <span>Settings</span>
        </a>
      </div>
      
      <div class="menu-group">
        <div class="menu-group-label">Sales Agent Activity</div>
        <a href="solved-units.php" class="menu-item <?php echo $current_page == 'solved-units' ? 'active' : ''; ?>">
          <i class="fas fa-check-square"></i>
          <span>Sold Units</span>
        </a>
        <a href="handled-clients.php" class="menu-item <?php echo $current_page == 'handled-clients' ? 'active' : ''; ?>">
          <i class="fas fa-user-friends"></i>
          <span>Handled Clients</span>
        </a>
      </div>

    <?php elseif ($user_role === 'SalesAgent'): ?>
      <!-- Sales Agent Menu -->
      <div class="menu-group">
        <a href="dashboard.php" class="menu-item <?php echo $current_page == 'dashboard' ? 'active' : ''; ?>">
          <i class="fas fa-tachometer-alt"></i>
          <span>Dashboard</span>
        </a>
      </div>

      <div class="menu-group">
        <div class="menu-group-label">Inventory & Orders</div>
        <a href="inventory.php" class="menu-item <?php echo $current_page == 'inventory' ? 'active' : ''; ?>">
          <i class="fas fa-warehouse"></i>
          <span>Inventory</span>
        </a>
        <a href="orders.php" class="menu-item <?php echo $current_page == 'orders' ? 'active' : ''; ?>">
          <i class="fas fa-shopping-cart"></i>
          <span>Orders</span>
        </a>
        <a href="payment-management.php" class="menu-item <?php echo $current_page == 'payment-management' ? 'active' : ''; ?>">
          <i class="fas fa-credit-card"></i>
          <span>Payment Management</span>
        </a>
      </div>

      <div class="menu-group">
        <div class="menu-group-label">Customer Management</div>
        <a href="customer-accounts.php" class="menu-item <?php echo $current_page == 'customer-accounts' ? 'active' : ''; ?>">
          <i class="fas fa-users"></i>
          <span>Customer Accounts</span>
        </a>
        <a href="customer-chats.php" class="menu-item <?php echo $current_page == 'customer-chats' ? 'active' : ''; ?>">
          <i class="fas fa-comments"></i>
          <span>Customer Chats</span>
        </a>
        <a href="sms.php" class="menu-item <?php echo $current_page == 'sms' ? 'active' : ''; ?>" aria-current="<?php echo $current_page == 'sms' ? 'page' : 'false'; ?>">
          <i class="fas fa-comment-dots"></i>
          <span>SMS</span>
        </a>
        <a href="email-management.php" class="menu-item <?php echo $current_page == 'email-management' ? 'active' : ''; ?>">
          <i class="fas fa-envelope"></i>
          <span>Email Management</span>
        </a>
      </div>

      <div class="menu-group">
        <div class="menu-group-label">Customer Inquiries</div>
        <a href="inquiries.php" class="menu-item <?php echo $current_page == 'inquiries' ? 'active' : ''; ?>">
          <i class="fas fa-question-circle"></i>
          <span>Vehicle Inquiries</span>
        </a>
      </div>

      <div class="menu-group">
        <div class="menu-group-label">Service Management</div>
        <a href="pms-requests.php" class="menu-item <?php echo $current_page == 'pms-requests' ? 'active' : ''; ?>">
          <i class="fas fa-tools"></i>
          <span>PMS Request Approval</span>
        </a>
      </div>

      <div class="menu-group">
        <div class="menu-group-label">Loan Management</div>
        <a href="loan-applications.php" class="menu-item <?php echo $current_page == 'loan-applications' ? 'active' : ''; ?>">
          <i class="fas fa-file-contract"></i>
          <span>Loan Application Approval</span>
        </a>
        <a href="loan-status.php" class="menu-item <?php echo $current_page == 'loan-status' ? 'active' : ''; ?>">
          <i class="fas fa-tasks"></i>
          <span>Loan Status Management</span>
        </a>
      </div>

    <?php else: ?>
      <!-- Fallback Menu (should not happen) -->
      <div class="menu-group">
        <p style="color: red; padding: 15px;">Unknown role: <?php echo htmlspecialchars($user_role); ?></p>
        <a href="dashboard.php" class="menu-item">
          <i class="fas fa-tachometer-alt"></i>
          <span>Dashboard</span>
        </a>
      </div>
    <?php endif; ?>
  </div>
</div>