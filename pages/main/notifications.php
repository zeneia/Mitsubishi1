<?php
session_start();
require_once dirname(__DIR__, 2) . '/includes/database/db_conn.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Allow both Customer and SalesAgent roles to access notifications
if (!in_array($_SESSION['user_role'], ['Customer', 'SalesAgent', 'Admin'])) {
    header('Location: ../login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Notifications - Mitsubishi</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link href="../../includes/css/common-styles.css" rel="stylesheet">
  <style>
      html, body {
      height: 100%;
      width: 100%;
      margin: 0;
      padding: 0;
      overflow: visible !important;
      scroll-behavior: smooth;
    }
    
    .main {
      height: auto !important;
      min-height: 100vh;
    }
    
    .main-content {
      height: auto !important;
      max-height: none !important;
      overflow-y: visible !important;
    }
    
    body {
      zoom: 80%;
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

    .notification-actions {
      display: flex;
      gap: 10px;
    }

    .action-btn {
      padding: 10px 20px;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 600;
      transition: var(--transition);
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .btn-mark-all {
      background: var(--accent-blue);
      color: white;
    }

    .btn-clear {
      background: var(--border-light);
      color: var(--text-dark);
    }

    .notifications-container {
      background: white;
      border-radius: 12px;
      box-shadow: var(--shadow-light);
      overflow: hidden;
    }

    .notifications-header {
      padding: 20px 25px;
      background: var(--primary-light);
      border-bottom: 1px solid var(--border-light);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .filter-tabs {
      display: flex;
      gap: 5px;
    }

    .filter-tab {
      padding: 8px 16px;
      border: none;
      background: transparent;
      cursor: pointer;
      border-radius: 6px;
      font-size: 14px;
      transition: var(--transition);
    }

    .filter-tab.active {
      background: var(--primary-red);
      color: white;
    }

    .notification-list {
      max-height: 70vh;
      overflow-y: auto;
    }

    .notification-item {
      padding: 20px 25px;
      border-bottom: 1px solid var(--border-light);
      cursor: pointer;
      transition: var(--transition);
      position: relative;
    }

    .notification-item:hover {
      background: #f8f9fa;
    }

    .notification-item.unread {
      background: #fff8f0;
      border-left: 4px solid var(--primary-red);
    }

    .notification-content {
      display: flex;
      gap: 15px;
    }

    .notification-icon {
      width: 45px;
      height: 45px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      flex-shrink: 0;
    }

    .notification-icon.test-drive { background: var(--accent-blue); }
    .notification-icon.customer { background: var(--success-green); }
    .notification-icon.order { background: var(--warning-orange); }
    .notification-icon.payment { background: var(--primary-red); }
    .notification-icon.loan-approval { background: #6c5ce7; }

    .notification-details {
      flex: 1;
    }

    .notification-title {
      font-weight: 600;
      color: var(--text-dark);
      margin-bottom: 5px;
    }

    .notification-message {
      color: var(--text-light);
      font-size: 14px;
      line-height: 1.5;
      margin-bottom: 8px;
    }

    .notification-meta {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 12px;
      color: var(--text-light);
    }

    .notification-time {
      display: flex;
      align-items: center;
      gap: 5px;
    }

    .notification-actions-menu {
      display: flex;
      gap: 10px;
    }

    .action-icon {
      width: 30px;
      height: 30px;
      border-radius: 50%;
      border: none;
      background: var(--border-light);
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: var(--transition);
    }

    .action-icon:hover {
      background: var(--primary-red);
      color: white;
    }

    /* Responsive Design */
    @media (max-width: 575px) {
      .page-header {
        flex-direction: column;
        gap: 15px;
        align-items: stretch;
      }

      .notification-actions {
        justify-content: center;
      }

      .notifications-header {
        flex-direction: column;
        gap: 15px;
      }

      .filter-tabs {
        justify-content: center;
        flex-wrap: wrap;
      }

      .notification-content {
        flex-direction: column;
        gap: 10px;
      }

      .notification-icon {
        align-self: center;
        width: 40px;
        height: 40px;
      }

      .notification-meta {
        flex-direction: column;
        gap: 8px;
        align-items: flex-start;
      }
    }

    @media (min-width: 576px) and (max-width: 767px) {
      .notification-content {
        gap: 12px;
      }
    }
  </style>
</head>
<body>
  <?php include '../../includes/components/sidebar.php'; ?>

  <div class="main">
    <?php include '../../includes/components/topbar.php'; ?>

    <div class="main-content">
      <div class="page-header">
        <h1>All Notifications</h1>
        <div class="notification-actions">
          <button class="action-btn btn-mark-all">
            <i class="fas fa-check-double"></i> Mark All Read
          </button>
          <button class="action-btn btn-clear">
            <i class="fas fa-trash"></i> Clear All
          </button>
        </div>
      </div>

      <div class="notifications-container">
        <div class="notifications-header">
          <h2>Notification Center</h2>
          <div class="filter-tabs">
            <?php
              $filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
            ?>
            <button class="filter-tab<?php echo ($filter === 'all') ? ' active' : ''; ?>">All</button>
            <button class="filter-tab<?php echo ($filter === 'unread') ? ' active' : ''; ?>">Unread</button>
            <button class="filter-tab<?php echo ($filter === 'orders') ? ' active' : ''; ?>">Orders</button>
            <button class="filter-tab<?php echo ($filter === 'customers') ? ' active' : ''; ?>">Customers</button>
            <button class="filter-tab<?php echo ($filter === 'system') ? ' active' : ''; ?>">System</button>
          </div>
        </div>

        <div class="notification-list" id="notification-list">
          <?php
            require_once '../../includes/api/notification_api.php';
            $user_id = $_SESSION['user_id'];
            $user_role = $_SESSION['user_role'];
            $filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
            $notifications = getNotifications($user_id, $user_role, $filter);
            if (empty($notifications)) {
              echo '<div style="text-align: center; padding: 40px; color: var(--text-light);">No notifications to display</div>';
            } else {
              foreach ($notifications as $notif) {
                $unread = $notif['is_read'] ? '' : 'unread';
                $icon = 'fas fa-bell';
                $iconClass = '';
                if ($notif['type'] === 'order') {
                    $icon = 'fas fa-shopping-cart';
                    $iconClass = 'order';
                } elseif ($notif['type'] === 'customer') {
                    $icon = 'fas fa-user-plus';
                    $iconClass = 'customer';
                } elseif ($notif['type'] === 'system') {
                    $icon = 'fas fa-cog';
                    $iconClass = 'system';
                } elseif ($notif['type'] === 'payment') {
                    $icon = 'fas fa-credit-card';
                    $iconClass = 'payment';
                } elseif ($notif['type'] === 'testdrive') {
                    $icon = 'fas fa-car';
                    $iconClass = 'testdrive';
                } elseif ($notif['type'] === 'loan_approval') {
                    $icon = 'fas fa-file-invoice-dollar';
                    $iconClass = 'loan-approval';
                }
                
                $formattedTime = date('M j, Y g:i A', strtotime($notif['created_at']));
                
                echo '<div class="notification-item ' . $unread . '" data-id="' . $notif['id'] . '" data-type="' . htmlspecialchars($notif['type']) . '">
                  <div class="notification-content">
                    <div class="notification-icon ' . $iconClass . '">
                      <i class="' . $icon . '"></i>
                    </div>
                    <div class="notification-details">
                      <div class="notification-title">' . htmlspecialchars($notif['title']) . '</div>
                      <div class="notification-message">' . htmlspecialchars($notif['message']) . '</div>
                      <div class="notification-meta">
                        <div class="notification-time">
                          <i class="fas fa-clock"></i> ' . htmlspecialchars($notif['created_at']) . '
                        </div>
                        <div class="notification-actions-menu">
                          <button class="action-icon btn-mark-read" title="Mark as read">
                            <i class="fas fa-check"></i>
                          </button>
                          <button class="action-icon btn-delete" title="Delete">
                            <i class="fas fa-trash"></i>
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>';
              }
            }
          ?>
        </div>
      </div>
    </div>
  </div>

  <script src="../../includes/js/common-scripts.js"></script>
  <script>
    // Function to render notifications
    function renderNotifications(notifications) {
      const notificationList = document.getElementById('notification-list');

      if (!notifications || notifications.length === 0) {
        notificationList.innerHTML = '<div style="text-align: center; padding: 40px; color: var(--text-light);">No notifications to display</div>';
        return;
      }

      let html = '';
      notifications.forEach(notif => {
        const unread = notif.is_read ? '' : 'unread';
        let icon = 'fas fa-bell';
        let iconClass = '';

        if (notif.type === 'order') {
          icon = 'fas fa-shopping-cart';
          iconClass = 'order';
        } else if (notif.type === 'customer') {
          icon = 'fas fa-user-plus';
          iconClass = 'customer';
        } else if (notif.type === 'system') {
          icon = 'fas fa-cog';
          iconClass = 'system';
        } else if (notif.type === 'payment') {
          icon = 'fas fa-credit-card';
          iconClass = 'payment';
        } else if (notif.type === 'testdrive') {
          icon = 'fas fa-car';
          iconClass = 'testdrive';
        } else if (notif.type === 'loan_approval') {
          icon = 'fas fa-file-invoice-dollar';
          iconClass = 'loan-approval';
        }

        html += `<div class="notification-item ${unread}" data-id="${notif.id}" data-type="${notif.type}">
          <div class="notification-content">
            <div class="notification-icon ${iconClass}">
              <i class="${icon}"></i>
            </div>
            <div class="notification-details">
              <div class="notification-title">${escapeHtml(notif.title)}</div>
              <div class="notification-message">${escapeHtml(notif.message)}</div>
              <div class="notification-meta">
                <div class="notification-time">
                  <i class="fas fa-clock"></i> ${escapeHtml(notif.created_at)}
                </div>
                <div class="notification-actions-menu">
                  <button class="action-icon btn-mark-read" title="Mark as read">
                    <i class="fas fa-check"></i>
                  </button>
                  <button class="action-icon btn-delete" title="Delete">
                    <i class="fas fa-trash"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>`;
      });

      notificationList.innerHTML = html;

      // Re-attach event listeners after rendering
      attachNotificationEventListeners();
    }

    // Helper function to escape HTML
    function escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }

    // Function to load notifications with filter
    function loadNotifications(filter) {
      fetch('../../includes/api/notification_action.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=get_notifications&filter=' + encodeURIComponent(filter)
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          renderNotifications(data.notifications);
        } else {
          console.error('Failed to load notifications:', data.message);
        }
      })
      .catch(error => {
        console.error('Error:', error);
      });
    }

    // Filter tabs functionality
    document.querySelectorAll('.filter-tab').forEach(tab => {
      tab.addEventListener('click', function() {
        document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        const filter = this.textContent.toLowerCase();
        loadNotifications(filter);
      });
    });

    // Function to get current active filter
    function getCurrentFilter() {
      const activeTab = document.querySelector('.filter-tab.active');
      return activeTab ? activeTab.textContent.toLowerCase() : 'all';
    }

    // Function to attach event listeners to notification items
    function attachNotificationEventListeners() {
      // Mark as read functionality
      document.querySelectorAll('.btn-mark-read').forEach(btn => {
        btn.addEventListener('click', function(e) {
          e.stopPropagation();
          const item = this.closest('.notification-item');
          const id = item.getAttribute('data-id');
          fetch('../../includes/api/notification_action.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=mark_read&notification_id=' + encodeURIComponent(id)
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              // Reload the current filter
              loadNotifications(getCurrentFilter());
            } else {
              console.error('Failed to mark as read:', data.message);
            }
          })
          .catch(error => {
            console.error('Error:', error);
          });
        });
      });

      // Delete notification
      document.querySelectorAll('.btn-delete').forEach(btn => {
        btn.addEventListener('click', function(e) {
          e.stopPropagation();
          const item = this.closest('.notification-item');
          const id = item.getAttribute('data-id');
          fetch('../../includes/api/notification_action.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=delete&notification_id=' + encodeURIComponent(id)
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              // Reload the current filter
              loadNotifications(getCurrentFilter());
            } else {
              console.error('Failed to delete:', data.message);
            }
          })
          .catch(error => {
            console.error('Error:', error);
          });
        });
      });

      // Notification details modal logic
      document.querySelectorAll('.notification-item').forEach(item => {
        item.addEventListener('click', function() {
          const notifId = item.getAttribute('data-id');
          const title = item.querySelector('.notification-title').textContent;
          const message = item.querySelector('.notification-message').textContent;
          const time = item.querySelector('.notification-time').textContent;
          const type = item.querySelector('.notification-icon i').className.split(' ')[2] || 'System';
          // Set modal fields
          document.getElementById('modalNotificationTitle').textContent = title;
          document.getElementById('modalNotificationMessage').textContent = message;
          document.querySelector('.notification-time').textContent = time;
          document.querySelector('.notification-type').textContent = type;
          // Store notification id for action
          document.getElementById('notificationModal').setAttribute('data-id', notifId);
          // Show modal
          window.openModal('notificationModal');
        });
      });
    }

    // Initial attachment of event listeners
    attachNotificationEventListeners();

    // Mark all as read
    document.querySelector('.btn-mark-all').addEventListener('click', function() {
      fetch('../../includes/api/notification_action.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=mark_all'
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Reload the current filter
          loadNotifications(getCurrentFilter());
        } else {
          console.error('Failed to mark all as read:', data.message);
        }
      })
      .catch(error => {
        console.error('Error:', error);
      });
    });

    // Clear all notifications
    document.querySelector('.btn-clear').addEventListener('click', function() {
      if (confirm('Are you sure you want to clear all notifications?')) {
        fetch('../../includes/api/notification_action.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: 'action=clear_all'
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            // Reload the current filter
            loadNotifications(getCurrentFilter());
          } else {
            console.error('Failed to clear all notifications:', data.message);
          }
        })
        .catch(error => {
          console.error('Error:', error);
        });
      }
    });

    // Modal close logic
    document.getElementById('modalCloseBtn').addEventListener('click', function() {
      document.getElementById('notificationModal').classList.remove('active');
    });
    document.getElementById('modalCancelBtn').addEventListener('click', function() {
      document.getElementById('notificationModal').classList.remove('active');
    });
  </script>
</body>
</html>
