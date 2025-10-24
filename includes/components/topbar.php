<?php
// Get current page name for dynamic title
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// Fetch logged-in user data
$user_data = null;
$profile_image_html = '<i class="fas fa-user"></i>'; // Default avatar icon

// Debug: Check if session exists
$debug_info = [
    'session_user_id' => $_SESSION['user_id'] ?? 'Not set',
    'session_user_role' => $_SESSION['user_role'] ?? 'Not set',
    'session_username' => $_SESSION['username'] ?? 'Not set'
];

if (isset($_SESSION['user_id'])) {
    try {
        // Added ProfileImage to the SELECT statement
        $stmt = $connect->prepare("SELECT Username, Email, Role, FirstName, LastName, ProfileImage FROM accounts WHERE Id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Debug: Log what we got from database
        $debug_info['db_result'] = $user_data ? 'Found user data' : 'No user data found';
        if ($user_data) {
            $debug_info['db_username'] = $user_data['Username'] ?? 'Not set';
            $debug_info['db_firstname'] = $user_data['FirstName'] ?? 'Not set';
            $debug_info['db_lastname'] = $user_data['LastName'] ?? 'Not set';
            $debug_info['db_role'] = $user_data['Role'] ?? 'Not set';
        }

        if ($user_data && !empty($user_data['ProfileImage'])) {
            $imageData = base64_encode($user_data['ProfileImage']);
            // Defaulting to image/jpeg as MIME type is not stored.
            $imageMimeType = 'image/jpeg'; 
            $profile_image_html = '<img src="data:' . $imageMimeType . ';base64,' . $imageData . '" alt="User Avatar" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">';
        }
    } catch (PDOException $e) {
        error_log("Error fetching user data: " . $e->getMessage());
        $debug_info['db_error'] = $e->getMessage();
    }
}

// Temporarily enable debug output (remove in production)
echo '<!-- DEBUG: ' . json_encode($debug_info) . ' -->';

// Set display name and role
$display_name = 'User'; // Better default fallback
$display_role = 'User'; // Better default fallback

if ($user_data) {
    // Use FirstName + LastName if available, otherwise use Username
    if (!empty($user_data['FirstName']) && !empty($user_data['LastName'])) {
        $display_name = $user_data['FirstName'] . ' ' . $user_data['LastName'];
    } elseif (!empty($user_data['FirstName'])) {
        $display_name = $user_data['FirstName'];
    } else {
        $display_name = $user_data['Username'];
    }
    
    // Set role display
    $display_role = $user_data['Role'];
} elseif (isset($_SESSION['user_role'])) {
    // If user_data failed but we have session info, use that
    $display_role = $_SESSION['user_role'];
    if (isset($_SESSION['username'])) {
        $display_name = $_SESSION['username'];
    }
}

// Define page titles
$page_titles = [
    'dashboard' => 'DASHBOARD',
    'inventory' => 'INVENTORY MANAGEMENT',
    'customer-accounts' => 'CUSTOMER ACCOUNTS',
    'orders' => 'ORDERS MANAGEMENT',
    'payment-management' => 'CUSTOMERS PAYMENT',
    'order-approval' => 'ORDER APPROVAL',
    'customer-chats' => 'CUSTOMER CHATS',
    'sales-report' => 'SALES REPORT',
    'analytics' => 'ANALYTICS DASHBOARD',
    'profile' => 'PROFILE SETTINGS',
    'inquiries' => 'VEHICLE INQUIRIES',
    'pms-requests' => 'PMS MANAGEMENT',
    'product-list' => 'PRODUCT LIST',
    'accounts' => 'ACCOUNT CONTROL',
    'pms-tracking' => 'PMS RECORDS',
    'solved-units' => 'SOLD UNITS RECORDS',
    'handled-clients' => 'HANDLED CLIENTS RECORDS',
    'transaction-records' => 'TRANSACTION RECORDS',
    'loan-applications' => 'LOAN APPROVAL',
    'loan-status' => 'LOAN MANAGEMENT',
    'settings' => 'SETTINGS',
    'notifications' => 'ALL NOTIFICATIONS',
    'sms' => 'SMS',
    'email-management' => 'EMAILS'
];

// Get page title or default
$page_title = isset($page_titles[$current_page]) ? $page_titles[$current_page] : 'Mitsubishi Admin';

// Define notification data based on current page
$notifications = [];
switch($current_page) {
    case 'dashboard':
        $notifications = [
            ['icon' => 'fas fa-car', 'text' => 'New test drive request'],
            ['icon' => 'fas fa-user', 'text' => 'New customer registered'],
            ['icon' => 'fas fa-shopping-cart', 'text' => 'New order placed']
        ];
        break;
    case 'inventory':
        $notifications = [
            ['icon' => 'fas fa-car', 'text' => 'Low stock alert'],
            ['icon' => 'fas fa-exclamation-triangle', 'text' => 'Inventory update needed'],
            ['icon' => 'fas fa-truck', 'text' => 'New delivery arrived']
        ];
        break;
    case 'customer-accounts':
        $notifications = [
            ['icon' => 'fas fa-user-plus', 'text' => 'New customer registered'],
            ['icon' => 'fas fa-edit', 'text' => 'Customer profile updated'],
            ['icon' => 'fas fa-exclamation-circle', 'text' => 'Account verification pending']
        ];
        break;
    case 'orders':
        $notifications = [
            ['icon' => 'fas fa-shopping-cart', 'text' => 'New order received'],
            ['icon' => 'fas fa-check-circle', 'text' => 'Order confirmed'],
            ['icon' => 'fas fa-times-circle', 'text' => 'Order cancelled']
        ];
        break;
    case 'customer-payments':
        $notifications = [
            ['icon' => 'fas fa-money-bill', 'text' => 'Payment received'],
            ['icon' => 'fas fa-exclamation-triangle', 'text' => 'Payment overdue'],
            ['icon' => 'fas fa-credit-card', 'text' => 'Payment failed']
        ];
        break;
    case 'order-approval':
        $notifications = [
            ['icon' => 'fas fa-clock', 'text' => '5 orders pending approval'],
            ['icon' => 'fas fa-exclamation-triangle', 'text' => 'High priority order'],
            ['icon' => 'fas fa-check-circle', 'text' => 'Order approved']
        ];
        break;
    case 'customer-chats':
        $notifications = [
            ['icon' => 'fas fa-comment', 'text' => 'New message from John'],
            ['icon' => 'fas fa-comment', 'text' => 'New message from Maria'],
            ['icon' => 'fas fa-users', 'text' => '5 active chats']
        ];
        break;
    case 'sales-report':
        $notifications = [
            ['icon' => 'fas fa-chart-line', 'text' => 'Monthly report ready'],
            ['icon' => 'fas fa-trophy', 'text' => 'Sales target achieved']
        ];
        break;
    case 'analytics':
        $notifications = [
            ['icon' => 'fas fa-chart-bar', 'text' => 'Weekly report generated'],
            ['icon' => 'fas fa-trending-up', 'text' => 'Traffic spike detected'],
            ['icon' => 'fas fa-target', 'text' => 'Goal achievement alert']
        ];
        break;
    case 'profile':
        $notifications = [
            ['icon' => 'fas fa-user-edit', 'text' => 'Profile updated'],
            ['icon' => 'fas fa-shield-alt', 'text' => 'Security alert'],
            ['icon' => 'fas fa-cog', 'text' => 'Settings changed']
        ];
        break;
    case 'settings':
        $notifications = [
            ['icon' => 'fas fa-cog', 'text' => 'System settings updated'],
            ['icon' => 'fas fa-shield-alt', 'text' => 'Security policy changed'],
            ['icon' => 'fas fa-database', 'text' => 'Backup completed']
        ];
        break;
    default:
        $notifications = [
            ['icon' => 'fas fa-bell', 'text' => 'New notification'],
            ['icon' => 'fas fa-info-circle', 'text' => 'System update'],
            ['icon' => 'fas fa-exclamation-triangle', 'text' => 'Action required']
        ];
}

$notification_count = count($notifications);
// Fetch real notifications for the logged-in user
require_once dirname(__DIR__) . '/api/notification_api.php';
$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['user_role'] ?? null;
$notifications = [];
$notification_count = 0;
if ($user_id && $user_role) {
    // Fetch latest 5 notifications for dropdown
    $notifications = getNotifications($user_id, $user_role, 'unread', 5);
    $notification_count = count($notifications);
}
?>

<div class="topbar">
  <div class="breadcrumb">
    <span class="page-title"><?php echo $page_title; ?></span>
  </div>
  
  <div class="user-section">
    <div class="notifications" id="notificationBtn">
      <i class="fas fa-bell"></i>
      <div class="notification-badge">
          <?php echo $notification_count > 0 ? $notification_count : ''; ?>
          </div>
      <div class=dropdown notification-dropdown" id="notificationDropdown">
        <div class="dropdown-header">Notifications</div>
        <?php if (empty($notifications)): ?>
          <div class="dropdown-item" style="opacity:0.7;">No new notifications</div>
        <?php else: ?>
          <?php foreach($notifications as $notification): ?>
            <div class="dropdown-item" data-notification-item>
              <i class="<?php 
                $icon = 'fas fa-bell';
                if ($notification['type'] === 'order') $icon = 'fas fa-shopping-cart';
                elseif ($notification['type'] === 'customer') $icon = 'fas fa-user-plus';
                elseif ($notification['type'] === 'system') $icon = 'fas fa-cog';
                elseif ($notification['type'] === 'payment') $icon = 'fas fa-credit-card';
                elseif ($notification['type'] === 'testdrive') $icon = 'fas fa-car';
                echo $icon;
              ?>"></i> 
              <?php echo htmlspecialchars($notification['title']); ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
        <div class="dropdown-footer"><a href="notifications.php">View all</a></div>
      </div>
    </div>
    
    <div class="user-info" id="profileBtn">
      <div class="user-avatar">
        <?php echo $profile_image_html; // Display profile image or default icon ?>
      </div>
      <div class="user-details">
        <h4><?php echo htmlspecialchars($display_name); ?></h4>
        <p><?php echo htmlspecialchars($display_role); ?></p>
      </div>
      <i class="fas fa-chevron-down"></i>
      <div class="dropdown profile-dropdown" id="profileDropdown">
        <div class="dropdown-header">Profile</div>
        <div class="dropdown-item" data-profile-item="settings"><i class="fas fa-user-cog"></i> Account Settings</div>
        <div class="dropdown-item" data-profile-item="logout"><i class="fas fa-sign-out-alt"></i> Logout</div>
      </div>
    </div>
  </div>
</div>

<!-- Notification Modal -->
<div class="modal-overlay" id="notificationModal">
  <div class="modal">
    <div class="modal-header">
      <h3>Notification Details</h3>
      <button class="modal-close" id="modalCloseBtn">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <div class="modal-body">
      <div class="notification-detail">
        <div class="notification-icon">
          <i class="fas fa-bell"></i>
        </div>
        <div class="notification-content">
          <h4 id="modalNotificationTitle">Notification Title</h4>
          <p id="modalNotificationMessage">Notification message will appear here.</p>
          <div class="notification-meta">
            <span class="notification-time">Just now</span>
            <span class="notification-type">System</span>
          </div>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn-secondary" id="modalCancelBtn">Close</button>
    </div>
  </div>
</div>

<!-- Add SweetAlert CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
(function() {
  'use strict';
  
  // Prevent multiple initializations
  if (window.topbarInitialized) {
    return;
  }
  window.topbarInitialized = true;

  // Modal functions
  window.openModal = function(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
      modal.classList.add('active');
      document.body.style.overflow = 'hidden';
    }
  };

  window.closeModal = function(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
      modal.classList.remove('active');
      document.body.style.overflow = 'auto';
    }
  };

  // Initialize when DOM is ready
  function initializeTopbar() {
    const notificationBtn = document.getElementById('notificationBtn');
    const notificationDropdown = document.getElementById('notificationDropdown');
    const profileBtn = document.getElementById('profileBtn');
    const profileDropdown = document.getElementById('profileDropdown');
    const modalCloseBtn = document.getElementById('modalCloseBtn');
    const modalCancelBtn = document.getElementById('modalCancelBtn');
    const notificationModal = document.getElementById('notificationModal');

    if (!notificationBtn || !profileBtn) {
      return; // Elements not ready yet
    }

    // Clear any existing event listeners by removing and re-adding elements
    function refreshElement(element) {
      const parent = element.parentNode;
      const newElement = element.cloneNode(true);
      parent.replaceChild(newElement, element);
      return newElement;
    }

    // Refresh elements to clear old listeners
    const freshNotificationBtn = refreshElement(notificationBtn);
    const freshProfileBtn = refreshElement(profileBtn);
    
    // Get refreshed dropdown elements
    const freshNotificationDropdown = document.getElementById('notificationDropdown');
    const freshProfileDropdown = document.getElementById('profileDropdown');

    // Notification button click handler
    freshNotificationBtn.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      
      // Close profile dropdown
      freshProfileDropdown.style.display = 'none';
      
      // Toggle notification dropdown
      const isVisible = freshNotificationDropdown.style.display === 'block';
      freshNotificationDropdown.style.display = isVisible ? 'none' : 'block';
    });

    // Profile button click handler
    freshProfileBtn.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      
      // Close notification dropdown
      freshNotificationDropdown.style.display = 'none';
      
      // Toggle profile dropdown
      const isVisible = freshProfileDropdown.style.display === 'block';
      freshProfileDropdown.style.display = isVisible ? 'none' : 'block';
    });

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
      if (!freshNotificationBtn.contains(e.target) && !freshNotificationDropdown.contains(e.target)) {
        freshNotificationDropdown.style.display = 'none';
      }
      if (!freshProfileBtn.contains(e.target) && !freshProfileDropdown.contains(e.target)) {
        freshProfileDropdown.style.display = 'none';
      }
    });

    // Notification dropdown items
    document.querySelectorAll('[data-notification-item]').forEach(item => {
      item.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // Get notification data
        const icon = this.querySelector('i').className;
        const title = this.textContent.trim();
        
        // Update modal content
        document.getElementById('modalNotificationTitle').textContent = title;
        
        // Set appropriate message based on notification type
        let message = '';
        let notificationType = 'System';
        
        if (title.includes('test drive')) {
          message = 'John Doe has requested a test drive for Montero Sport 2024. The request was submitted on March 15, 2024 at 2:30 PM.';
          notificationType = 'Test Drive';
        } else if (title.includes('customer registered')) {
          message = 'A new customer "Maria Santos" has successfully registered on the platform. Please review the customer details and approve the account.';
          notificationType = 'Customer';
        } else if (title.includes('order')) {
          message = 'A new order #ORD-2024-001 for Xpander 2024 has been placed by Roberto Cruz. The order is pending approval.';
          notificationType = 'Order';
        } else if (title.includes('payment')) {
          message = 'Payment notification: ₱150,000 payment received from customer. Please verify and process accordingly.';
          notificationType = 'Payment';
        } else if (title.includes('stock') || title.includes('inventory')) {
          message = 'Inventory alert: Stock levels are running low for several vehicle models. Please check inventory management.';
          notificationType = 'Inventory';
        } else if (title.includes('message') || title.includes('chat')) {
          message = 'New message received from customer. Please respond to maintain good customer service standards.';
          notificationType = 'Chat';
        } else if (title.includes('report') || title.includes('analytics')) {
          message = 'System report has been generated and is ready for review. Check the analytics dashboard for details.';
          notificationType = 'Report';
        } else {
          message = 'System notification: Please review the details and take appropriate action if required.';
          notificationType = 'System';
        }
        
        document.getElementById('modalNotificationMessage').textContent = message;
        
        // Update notification icon and type
        const modalIcon = document.querySelector('.notification-icon i');
        modalIcon.className = icon;
        document.querySelector('.notification-type').textContent = notificationType;
        
        // Close dropdown and open modal
        freshNotificationDropdown.style.display = 'none';
        window.openModal('notificationModal');
      });
    });

    // Profile dropdown items
    document.querySelectorAll('[data-profile-item]').forEach(item => {
      item.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const action = this.getAttribute('data-profile-item');
        
        if (action === 'settings') {
          window.location.href = 'profile.php';
        } else if (action === 'logout') {
          // Simple SweetAlert without custom CSS overrides
          Swal.fire({
            title: 'Logout Confirmation',
            text: 'Are you sure you want to logout?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#d60000',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, logout',
            cancelButtonText: 'Cancel',
            allowOutsideClick: true,
            allowEscapeKey: true,
            backdrop: true,
            heightAuto: false,
            width: '400px'
          }).then((result) => {
            if (result.isConfirmed) {
              // For Sales Agents, update status before logout
              <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'SalesAgent'): ?>
              // Create a form to submit logout request
              const form = document.createElement('form');
              form.method = 'POST';
              form.action = '../logout.php';
              document.body.appendChild(form);
              form.submit();
              <?php else: ?>
              window.location.href = '../logout.php';
              <?php endif; ?>
            }
          });
        }
        
        freshProfileDropdown.style.display = 'none';
      });
    });

    // Modal close handlers
    if (modalCloseBtn) {
      modalCloseBtn.addEventListener('click', function() {
        window.closeModal('notificationModal');
      });
    }

    if (modalCancelBtn) {
      modalCancelBtn.addEventListener('click', function() {
        window.closeModal('notificationModal');
      });
    }

    // Close modal when clicking outside
    if (notificationModal) {
      notificationModal.addEventListener('click', function(e) {
        if (e.target === this) {
          window.closeModal('notificationModal');
        }
      });
    }

    // Handle "View all" link
    const viewAllLink = document.querySelector('.dropdown-footer a');
    if (viewAllLink) {
      viewAllLink.addEventListener('click', function(e) {
        e.preventDefault();
        window.location.href = 'notifications.php';
      });
    }
  }

  // Initialize immediately if DOM is ready, otherwise wait
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeTopbar);
  } else {
    initializeTopbar();
  }

  // Also try to initialize after a short delay to ensure all elements are present
  setTimeout(initializeTopbar, 100);
})();
</script>
