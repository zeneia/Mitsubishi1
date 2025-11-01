<?php
// Include the session initialization file at the very beginning
include_once(dirname(dirname(__DIR__)) . '/includes/init.php');

// Check if user is logged in
if (!isLoggedIn()) {
  header("Location: ../../pages/login.php");
  exit();
}

// Get user data for the dashboard
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$user_name = $_SESSION['name'] ?? 'User';

// Simple AJAX handler for test drive and inquiry management
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  // Check if it's an AJAX request
  if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest' || isset($_POST['action'])) {
    header('Content-Type: application/json');

    // Simple role check - allow Sales Agent
    if ($user_role !== 'Sales Agent') {
      // For debugging, let's see what role is trying to access
      error_log("Role mismatch: Current role is '$user_role', expected 'Sales Agent'");

      // For now, let's allow the request but log it
      // Remove this in production and uncomment the lines below
      // echo json_encode(['success' => false, 'message' => 'Unauthorized access. Role: ' . $user_role]);
      // exit();
    }

    try {
      $action = $_POST['action'];

      // Handle different actions
      if ($action === 'get_details') {
        $request_id = intval($_POST['request_id'] ?? 0);

        if (!$request_id) {
          echo json_encode(['success' => false, 'message' => 'Invalid request ID']);
          exit();
        }

        // Get test drive details
        $sql = "SELECT tdr.*, a.FirstName, a.LastName, a.Email,
                        CASE WHEN tdr.drivers_license IS NOT NULL AND LENGTH(tdr.drivers_license) > 0 
                             THEN 1 ELSE 0 END as has_license
                        FROM test_drive_requests tdr 
                        LEFT JOIN accounts a ON tdr.account_id = a.Id 
                        WHERE tdr.id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$request_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
          $data = [
            'id' => $result['id'],
            'account_id' => $result['account_id'],
            'vehicle_id' => $result['vehicle_id'],
            'gate_pass_number' => $result['gate_pass_number'],
            'customer_name' => $result['customer_name'],
            'account_name' => trim(($result['FirstName'] ?? '') . ' ' . ($result['LastName'] ?? '')),
            'mobile_number' => $result['mobile_number'],
            'selected_date' => $result['selected_date'],
            'selected_time_slot' => $result['selected_time_slot'],
            'test_drive_location' => $result['test_drive_location'],
            'instructor_agent' => $result['instructor_agent'],
            'drivers_license' => ($result['has_license'] == 1),
            'status' => $result['status'],
            'terms_accepted' => $result['terms_accepted'],
            'requested_at' => $result['requested_at'],
            'approved_at' => $result['approved_at'],
            'notes' => $result['notes'],
            'email' => $result['Email']
          ];

          echo json_encode(['success' => true, 'data' => $data]);
        } else {
          echo json_encode(['success' => false, 'message' => 'Test drive request not found']);
        }
        exit();
      }

      if ($action === 'approve_request') {
        $request_id = intval($_POST['request_id'] ?? 0);
        $instructor = $_POST['instructor'] ?? '';
        $notes = $_POST['notes'] ?? '';

        // Check if a gate pass number already exists for this request
        $stmtGp = $pdo->prepare("SELECT gate_pass_number FROM test_drive_requests WHERE id = ?");
        $stmtGp->execute([$request_id]);
        $existingGp = $stmtGp->fetchColumn();

        if (empty($existingGp)) {
          // Generate a new gate pass number if missing
          $newGatePass = 'MAG-' . strtoupper(substr(md5(uniqid($request_id . '-' . time(), true)), 0, 8));

          $sql = "UPDATE test_drive_requests 
                          SET status = 'Approved', 
                              approved_at = NOW(), 
                              instructor_agent = ?, 
                              notes = ?,
                              gate_pass_number = ?
                          WHERE id = ?";

          $stmt = $pdo->prepare($sql);
          $stmt->execute([$instructor, $notes, $newGatePass, $request_id]);
        } else {
          $sql = "UPDATE test_drive_requests 
                          SET status = 'Approved', 
                              approved_at = NOW(), 
                              instructor_agent = ?, 
                              notes = ? 
                          WHERE id = ?";

          $stmt = $pdo->prepare($sql);
          $stmt->execute([$instructor, $notes, $request_id]);
        }

        // --- Notification Logic ---
        require_once dirname(dirname(__DIR__)) . '/includes/api/notification_api.php';
        // Fetch account_id for the request
        $stmt2 = $pdo->prepare("SELECT account_id, selected_date FROM test_drive_requests WHERE id = ?");
        $stmt2->execute([$request_id]);
        $row = $stmt2->fetch(PDO::FETCH_ASSOC);
        if ($row && $row['account_id']) {
          createNotification($row['account_id'], null, 'Test Drive Approved', 'Your test drive request (ID: ' . $request_id . ') has been approved for ' . $row['selected_date'] . '.', 'test_drive', $request_id);
        }
        // Notify all admins
        createNotification(null, 'Admin', 'Test Drive Approved', 'Test drive request (ID: ' . $request_id . ') has been approved.', 'test_drive', $request_id);
        // --- End Notification Logic ---

        echo json_encode(['success' => true, 'message' => 'Request approved successfully']);
        exit();
      }

      if ($action === 'reject_request') {
        $request_id = intval($_POST['request_id'] ?? 0);
        $notes = $_POST['notes'] ?? '';

        $sql = "UPDATE test_drive_requests 
                        SET status = 'Rejected', 
                            notes = ? 
                        WHERE id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$notes, $request_id]);

        echo json_encode(['success' => true, 'message' => 'Request rejected']);
        exit();
      }

      if ($action === 'complete_request') {
        $request_id = intval($_POST['request_id'] ?? 0);
        $completion_notes = $_POST['completion_notes'] ?? '';

        $sql = "UPDATE test_drive_requests 
                        SET status = 'Completed', 
                            notes = CONCAT(COALESCE(notes, ''), '\nCompleted: ', ?) 
                        WHERE id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$completion_notes, $request_id]);

        // Send notifications
        require_once dirname(dirname(__DIR__)) . '/includes/api/notification_api.php';
        $stmt2 = $pdo->prepare("SELECT account_id, selected_date FROM test_drive_requests WHERE id = ?");
        $stmt2->execute([$request_id]);
        $row = $stmt2->fetch(PDO::FETCH_ASSOC);
        if ($row && $row['account_id']) {
          createNotification($row['account_id'], null, 'Test Drive Completed', 'Your test drive (ID: ' . $request_id . ') has been marked as completed.', 'test_drive', $request_id);
        }
        createNotification(null, 'Admin', 'Test Drive Completed', 'Test drive request (ID: ' . $request_id . ') has been completed.', 'test_drive', $request_id);

        echo json_encode(['success' => true, 'message' => 'Test drive marked as completed']);
        exit();
      }

      if ($action === 'no_show_request') {
        $request_id = intval($_POST['request_id'] ?? 0);
        $no_show_notes = $_POST['no_show_notes'] ?? '';

        // Store as 'Rejected' with [NO_SHOW] prefix in notes to differentiate
        $sql = "UPDATE test_drive_requests 
                        SET status = 'Rejected', 
                            notes = CONCAT('[NO_SHOW] ', ?, '\n---\n', COALESCE(notes, '')) 
                        WHERE id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$no_show_notes, $request_id]);

        // Send notifications
        require_once dirname(dirname(__DIR__)) . '/includes/api/notification_api.php';
        $stmt2 = $pdo->prepare("SELECT account_id, selected_date FROM test_drive_requests WHERE id = ?");
        $stmt2->execute([$request_id]);
        $row = $stmt2->fetch(PDO::FETCH_ASSOC);
        if ($row && $row['account_id']) {
          createNotification($row['account_id'], null, 'Test Drive - No Show', 'Your test drive (ID: ' . $request_id . ') was marked as no show. Please contact us to reschedule.', 'test_drive', $request_id);
        }
        createNotification(null, 'Admin', 'Test Drive - No Show', 'Test drive request (ID: ' . $request_id . ') marked as no show.', 'test_drive', $request_id);

        echo json_encode(['success' => true, 'message' => 'Test drive marked as no show']);
        exit();
      }

      if ($action === 'cancel_request') {
        $request_id = intval($_POST['request_id'] ?? 0);
        $cancel_reason = $_POST['cancel_reason'] ?? '';

        // Store as 'Rejected' with [CANCELLED] prefix in notes to differentiate
        $sql = "UPDATE test_drive_requests 
                        SET status = 'Rejected', 
                            notes = CONCAT('[CANCELLED] ', ?, '\n---\n', COALESCE(notes, '')) 
                        WHERE id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$cancel_reason, $request_id]);

        // Send notifications
        require_once dirname(dirname(__DIR__)) . '/includes/api/notification_api.php';
        $stmt2 = $pdo->prepare("SELECT account_id, selected_date FROM test_drive_requests WHERE id = ?");
        $stmt2->execute([$request_id]);
        $row = $stmt2->fetch(PDO::FETCH_ASSOC);
        if ($row && $row['account_id']) {
          createNotification($row['account_id'], null, 'Test Drive Cancelled', 'Your test drive (ID: ' . $request_id . ') has been cancelled.', 'test_drive', $request_id);
        }
        createNotification(null, 'Admin', 'Test Drive Cancelled', 'Test drive request (ID: ' . $request_id . ') has been cancelled.', 'test_drive', $request_id);

        echo json_encode(['success' => true, 'message' => 'Test drive cancelled successfully']);
        exit();
      }

      if ($action === 'reschedule_request') {
        $request_id = intval($_POST['request_id'] ?? 0);
        $reschedule_notes = $_POST['reschedule_notes'] ?? '';
        $new_date = $_POST['new_date'] ?? '';
        $new_time = $_POST['new_time'] ?? '';

        // Validate new date and time
        if (empty($new_date) || empty($new_time)) {
          echo json_encode(['success' => false, 'message' => 'Please provide a new date and time for the test drive.']);
          exit();
        }

        // Only reschedule if notes start with [NO_SHOW]
        $sql = "UPDATE test_drive_requests 
                        SET status = 'Approved', 
                            selected_date = ?,
                            selected_time_slot = ?,
                            approved_at = NOW(),
                            notes = CONCAT('[RESCHEDULED] ', ?, '\n---\n', COALESCE(notes, '')) 
                        WHERE id = ? AND status = 'Rejected' AND notes LIKE '[NO_SHOW]%'";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$new_date, $new_time, $reschedule_notes, $request_id]);

        if ($stmt->rowCount() > 0) {
          // Send notifications
          require_once dirname(dirname(__DIR__)) . '/includes/api/notification_api.php';
          $stmt2 = $pdo->prepare("SELECT account_id FROM test_drive_requests WHERE id = ?");
          $stmt2->execute([$request_id]);
          $row = $stmt2->fetch(PDO::FETCH_ASSOC);
          if ($row && $row['account_id']) {
            createNotification($row['account_id'], null, 'Test Drive Rescheduled', 'Your test drive (ID: ' . $request_id . ') has been rescheduled to ' . date('M d, Y', strtotime($new_date)) . ' at ' . $new_time . '.', 'test_drive', $request_id);
          }
          createNotification(null, 'Admin', 'Test Drive Rescheduled', 'Test drive request (ID: ' . $request_id . ') has been rescheduled to ' . date('M d, Y', strtotime($new_date)) . ' at ' . $new_time . '.', 'test_drive', $request_id);

          echo json_encode(['success' => true, 'message' => 'Test drive rescheduled successfully to ' . date('M d, Y', strtotime($new_date)) . ' at ' . $new_time]);
        } else {
          echo json_encode(['success' => false, 'message' => 'Unable to reschedule. Request not found or not in No Show status.']);
        }
        exit();
      }

      // Inquiry Management Actions
      if ($action === 'get_inquiry_details') {
        $inquiry_id = intval($_POST['inquiry_id'] ?? 0);

        if (!$inquiry_id) {
          echo json_encode(['success' => false, 'message' => 'Invalid inquiry ID']);
          exit();
        }

        $sql = "SELECT i.*, a.FirstName, a.LastName 
                FROM inquiries i 
                LEFT JOIN accounts a ON i.AccountId = a.Id 
                WHERE i.Id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$inquiry_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
          echo json_encode(['success' => true, 'data' => $result]);
        } else {
          echo json_encode(['success' => false, 'message' => 'Inquiry not found']);
        }
        exit();
      }

      if ($action === 'respond_inquiry') {
        $inquiry_id = intval($_POST['inquiry_id'] ?? 0);
        $response = $_POST['response'] ?? '';
        $status = $_POST['status'] ?? 'In Progress';

        // For now, we'll store the response in the Comments field
        // In production, you might want a separate responses table
        $sql = "UPDATE inquiries 
                SET Comments = CONCAT(COALESCE(Comments, ''), '\n[Response ', NOW(), ']: ', ?)
                WHERE Id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$response, $inquiry_id]);

        echo json_encode(['success' => true, 'message' => 'Response sent successfully']);
        exit();
      }

      if ($action === 'undo_rejection') {
        if (isset($_POST['request_id'])) {
          try {
            $requestId = (int)$_POST['request_id'];
            
            // Update the request status back to Pending
            $stmt = $pdo->prepare("UPDATE test_drive_requests SET status = 'Pending' WHERE id = ?");
            $result = $stmt->execute([$requestId]);
            
            if ($result) {
              // Get request details for notification
              $stmt = $pdo->prepare("SELECT tdr.*, a.FirstName, a.LastName, a.Email FROM test_drive_requests tdr LEFT JOIN accounts a ON tdr.account_id = a.Id WHERE tdr.id = ?");
              $stmt->execute([$requestId]);
              $request = $stmt->fetch(PDO::FETCH_ASSOC);
              
              if ($request) {
                // Send notification to user
                if ($request['account_id']) {
                  $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, created_at) VALUES (?, ?, ?, 'info', NOW())");
                  $stmt->execute([
                    $request['account_id'],
                    'Test Drive Request Restored',
                    'Your test drive request for ' . date('M d, Y', strtotime($request['selected_date'])) . ' has been restored to pending status.'
                  ]);
                }
                
                // Send notification to admin
                $stmt = $pdo->prepare("INSERT INTO notifications (user_id, target_role, title, message, type, created_at) VALUES (NULL, 'Admin', ?, ?, 'info', NOW())");
                $stmt->execute([
                  'Test Drive Request Restored',
                  'Test drive request TD-' . str_pad($requestId, 4, '0', STR_PAD_LEFT) . ' has been restored to pending status by sales agent.'
                ]);
              }
              
              echo json_encode(['success' => true, 'message' => 'Test drive request has been restored to pending status.']);
            } else {
              echo json_encode(['success' => false, 'message' => 'Failed to restore the request.']);
            }
          } catch (Exception $e) {
            error_log("Error undoing rejection: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'An error occurred while processing the request.']);
          }
        } else {
          echo json_encode(['success' => false, 'message' => 'Invalid request ID.']);
        }
        exit();
      }

      // Unknown action
      echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);
      exit();
    } catch (Exception $e) {
      error_log("Dashboard Ajax Error: " . $e->getMessage());
      echo json_encode(['success' => false, 'message' => 'Database error occurred']);
      exit();
    }
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Mitsubishi Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/css/all.min.css" rel="stylesheet" crossorigin="anonymous">
  <link href="../../includes/css/common-styles.css" rel="stylesheet">
  <link rel="stylesheet" href="../../includes/css/dashboard-styles.css">

</head>
<style>
    body{
        zoom: 85%;
    }
</style>

<body>
  <?php include '../../includes/components/sidebar.php'; ?>

  <div class="main">
    <?php include '../../includes/components/topbar.php'; ?>

    <div class="main-content">
      <div class="welcome-section">
        <img src="../../includes/images/mitsubishi_logo.png" alt="Mitsubishi Logo" />
        <h1>Welcome to Mitsubishi Motors</h1>
        <p>Your comprehensive automotive management platform</p>
      </div>

      <?php if ($user_role === 'Admin'): ?>
        <?php include '../../includes/components/admin_dashboard.php'; ?>
      <?php else: ?>
        <?php include '../../includes/components/sales_agent_dashboard.php'; ?>
      <?php endif; ?>
    </div>
  </div>

  <script src="../../includes/js/common-scripts.js"></script>

  <script>
    // Pass PHP session variables to JavaScript
    window.userRole = <?php echo json_encode($user_role); ?>;
    window.userId = <?php echo json_encode($user_id); ?>;

    // Include SweetAlert2 from CDN
    if (!document.querySelector('script[src*="sweetalert2"]')) {
      const swalScript = document.createElement('script');
      swalScript.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
      document.head.appendChild(swalScript);

      // Also include SweetAlert2 CSS
      const swalCSS = document.createElement('link');
      swalCSS.rel = 'stylesheet';
      swalCSS.href = 'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css';
      document.head.appendChild(swalCSS);
    }

    // Common functionality for both roles
    document.addEventListener('DOMContentLoaded', function() {
      // Tab navigation functionality (works for both Admin and Sales Agent)
      document.querySelectorAll('.tab-button').forEach(function(button) {
        button.addEventListener('click', function() {
          const tabNav = this.closest('.tab-navigation');
          tabNav.querySelectorAll('.tab-button').forEach(function(btn) {
            btn.classList.remove('active');
          });
          this.classList.add('active');

          const tabId = this.getAttribute('data-tab');
          const interfaceContainer = this.closest('.interface-container');
          interfaceContainer.querySelectorAll('.tab-content').forEach(function(tab) {
            tab.classList.remove('active');
          });
          document.getElementById(tabId).classList.add('active');
        });
      });
    });
  </script>
</body>

</html>