<?php
// Sales Agent Dashboard Component - With Backend Integration

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'undo_rejection':
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
                            $stmt = $pdo->prepare("INSERT INTO admin_notifications (title, message, type, created_at) VALUES (?, ?, 'info', NOW())");
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
            exit;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action.']);
            exit;
    }
}

// Fetch data from database
$pending_requests = [];
$approved_requests = [];
$completed_requests = [];
$rejected_requests = [];
$no_show_requests = [];
$cancelled_requests = [];

if (isset($pdo) && $pdo) {
  try {
    // Determine the logged-in sales agent ID
    $agentId = $_SESSION['user_id'] ?? null;
    if (!$agentId) {
      throw new Exception('Sales agent not authenticated');
    }
    // Fetch pending requests
    $stmt = $pdo->prepare("
            SELECT tdr.*, a.FirstName, a.LastName, a.Email 
            FROM test_drive_requests tdr 
            LEFT JOIN accounts a ON tdr.account_id = a.Id 
            LEFT JOIN customer_information ci ON tdr.account_id = ci.account_id
            WHERE tdr.status = 'Pending' 
              AND ci.agent_id = ?
            ORDER BY tdr.requested_at DESC
        ");
    $stmt->execute([$agentId]);
    $pending_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch approved requests
    $stmt = $pdo->prepare("
            SELECT tdr.*, a.FirstName, a.LastName, a.Email 
            FROM test_drive_requests tdr 
            LEFT JOIN accounts a ON tdr.account_id = a.Id 
            LEFT JOIN customer_information ci ON tdr.account_id = ci.account_id
            WHERE tdr.status = 'Approved' 
              AND ci.agent_id = ?
            ORDER BY tdr.selected_date ASC
        ");
    $stmt->execute([$agentId]);
    $approved_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch completed requests
    $stmt = $pdo->prepare("
            SELECT tdr.*, a.FirstName, a.LastName, a.Email 
            FROM test_drive_requests tdr 
            LEFT JOIN accounts a ON tdr.account_id = a.Id 
            LEFT JOIN customer_information ci ON tdr.account_id = ci.account_id
            WHERE tdr.status = 'Completed' 
              AND ci.agent_id = ?
            ORDER BY tdr.approved_at DESC
        ");
    $stmt->execute([$agentId]);
    $completed_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch rejected requests (excluding no show and cancelled)
    $stmt = $pdo->prepare("
            SELECT tdr.*, a.FirstName, a.LastName, a.Email 
            FROM test_drive_requests tdr 
            LEFT JOIN accounts a ON tdr.account_id = a.Id 
            LEFT JOIN customer_information ci ON tdr.account_id = ci.account_id
            WHERE tdr.status = 'Rejected' 
              AND ci.agent_id = ?
              AND (tdr.notes IS NULL OR (tdr.notes NOT LIKE '[NO_SHOW]%' AND tdr.notes NOT LIKE '[CANCELLED]%'))
            ORDER BY tdr.requested_at DESC
        ");
    $stmt->execute([$agentId]);
    $rejected_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch no show requests (Rejected status with [NO_SHOW] prefix)
    $stmt = $pdo->prepare("
            SELECT tdr.*, a.FirstName, a.LastName, a.Email 
            FROM test_drive_requests tdr 
            LEFT JOIN accounts a ON tdr.account_id = a.Id 
            LEFT JOIN customer_information ci ON tdr.account_id = ci.account_id
            WHERE tdr.status = 'Rejected' 
              AND ci.agent_id = ?
              AND tdr.notes IS NOT NULL
              AND tdr.notes LIKE '[NO_SHOW]%'
            ORDER BY tdr.selected_date DESC
        ");
    $stmt->execute([$agentId]);
    $no_show_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch cancelled requests (Rejected status with [CANCELLED] prefix)
    $stmt = $pdo->prepare("
            SELECT tdr.*, a.FirstName, a.LastName, a.Email 
            FROM test_drive_requests tdr 
            LEFT JOIN accounts a ON tdr.account_id = a.Id 
            LEFT JOIN customer_information ci ON tdr.account_id = ci.account_id
            WHERE tdr.status = 'Rejected' 
              AND ci.agent_id = ?
              AND tdr.notes IS NOT NULL
              AND tdr.notes LIKE '[CANCELLED]%'
            ORDER BY tdr.requested_at DESC
        ");
    $stmt->execute([$agentId]);
    $cancelled_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
  } catch (Exception $e) {
    error_log("Error fetching test drive data: " . $e->getMessage());
  }
}

// Calculate statistics
$pending_count = count($pending_requests);
$today = date('Y-m-d');
$urgent_count = 0;
$today_count = 0;

foreach ($pending_requests as $request) {
  // Count urgent requests (within 2 days)
  $daysDiff = (strtotime($request['selected_date']) - strtotime($today)) / (60 * 60 * 24);
  if ($daysDiff <= 2) {
    $urgent_count++;
  }
  // Count today's requests
  if (date('Y-m-d', strtotime($request['requested_at'])) == $today) {
    $today_count++;
  }
}

// Helper function to determine priority
function getTestDrivePriority($selectedDate)
{
  $daysDiff = (strtotime($selectedDate) - strtotime(date('Y-m-d'))) / (60 * 60 * 24);

  if ($daysDiff < 0) return ['label' => 'Overdue', 'class' => 'overdue'];
  if ($daysDiff <= 1) return ['label' => 'Urgent', 'class' => 'urgent'];
  if ($daysDiff <= 3) return ['label' => 'High', 'class' => 'high'];
  return ['label' => 'Normal', 'class' => 'normal'];
}

// Fetch quote requests data from database
$all_quotes = [];
$recent_quotes = [];
$quote_stats = ['total' => 0, 'this_week' => 0, 'this_month' => 0];

if (isset($pdo) && $pdo) {
  try {
    $agentId = $_SESSION['user_id'] ?? null;
    if (!$agentId) {
      throw new Exception('Sales agent not authenticated');
    }

    // Fetch all quote requests for this agent's customers
    $stmt = $pdo->prepare("
      SELECT q.*,
             v.model_name, v.variant, v.year_model, v.base_price, v.promotional_price,
             a.FirstName, a.LastName, a.Email,
             ci.firstname as customer_first, ci.lastname as customer_last, ci.mobile_number
      FROM quotes q
      LEFT JOIN vehicles v ON q.VehicleId = v.id
      LEFT JOIN accounts a ON q.AccountId = a.Id
      LEFT JOIN customer_information ci ON q.AccountId = ci.account_id
      WHERE ci.agent_id = ?
      ORDER BY q.RequestedAt DESC
      LIMIT 100
    ");
    $stmt->execute([$agentId]);
    $all_quotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch recent quotes (last 30 days)
    $stmt = $pdo->prepare("
      SELECT q.*,
             v.model_name, v.variant, v.year_model, v.base_price, v.promotional_price,
             a.FirstName, a.LastName, a.Email,
             ci.firstname as customer_first, ci.lastname as customer_last, ci.mobile_number
      FROM quotes q
      LEFT JOIN vehicles v ON q.VehicleId = v.id
      LEFT JOIN accounts a ON q.AccountId = a.Id
      LEFT JOIN customer_information ci ON q.AccountId = ci.account_id
      WHERE ci.agent_id = ?
        AND q.RequestedAt >= DATE_SUB(NOW(), INTERVAL 30 DAY)
      ORDER BY q.RequestedAt DESC
    ");
    $stmt->execute([$agentId]);
    $recent_quotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate statistics
    $quote_stats['total'] = count($all_quotes);
    foreach ($all_quotes as $quote) {
      $requestDate = strtotime($quote['RequestedAt']);
      if ($requestDate >= strtotime('-7 days')) {
        $quote_stats['this_week']++;
      }
      if ($requestDate >= strtotime('-30 days')) {
        $quote_stats['this_month']++;
      }
    }
  } catch (Exception $e) {
    error_log("Error fetching quote requests: " . $e->getMessage());
  }
}

// Fetch inquiry data from database
$new_inquiries = [];
$recent_inquiries = [];
$all_inquiries = [];

if (isset($pdo) && $pdo) {
  try {
    // Determine the logged-in sales agent ID
    $agentId = $_SESSION['user_id'] ?? null;
    if (!$agentId) {
      throw new Exception('Sales agent not authenticated');
    }
    // Fetch new inquiries (last 7 days) - ONLY those without responses
    $stmt = $pdo->prepare("
      SELECT i.*, a.FirstName, a.LastName 
      FROM inquiries i 
      LEFT JOIN accounts a ON i.AccountId = a.Id 
      LEFT JOIN inquiry_responses ir ON i.Id = ir.InquiryId
      LEFT JOIN customer_information ci ON i.AccountId = ci.account_id
      WHERE i.InquiryDate >= DATE_SUB(NOW(), INTERVAL 7 DAY)
      AND ir.InquiryId IS NULL
      AND ci.agent_id = ?
      ORDER BY i.InquiryDate DESC
    ");
    $stmt->execute([$agentId]);
    $new_inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch recent inquiries (last 30 days) with response status
    $stmt = $pdo->prepare("
      SELECT i.*, a.FirstName, a.LastName,
             (SELECT COUNT(*) FROM inquiry_responses ir WHERE ir.InquiryId = i.Id) as response_count,
             (SELECT MAX(ir.ResponseDate) FROM inquiry_responses ir WHERE ir.InquiryId = i.Id) as last_response_date
      FROM inquiries i 
      LEFT JOIN accounts a ON i.AccountId = a.Id 
      LEFT JOIN customer_information ci ON i.AccountId = ci.account_id
      WHERE i.InquiryDate >= DATE_SUB(NOW(), INTERVAL 30 DAY)
      AND ci.agent_id = ?
      ORDER BY i.InquiryDate DESC
      LIMIT 20
    ");
    $stmt->execute([$agentId]);
    $recent_inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch all inquiries with response status
    $stmt = $pdo->prepare("
      SELECT i.*, a.FirstName, a.LastName,
             (SELECT COUNT(*) FROM inquiry_responses ir WHERE ir.InquiryId = i.Id) as response_count,
             (SELECT MAX(ir.ResponseDate) FROM inquiry_responses ir WHERE ir.InquiryId = i.Id) as last_response_date
      FROM inquiries i 
      LEFT JOIN accounts a ON i.AccountId = a.Id 
      LEFT JOIN customer_information ci ON i.AccountId = ci.account_id
      WHERE ci.agent_id = ?
      ORDER BY i.InquiryDate DESC
      LIMIT 100
    ");
    $stmt->execute([$agentId]);
    $all_inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);
  } catch (Exception $e) {
    error_log("Error fetching inquiry data: " . $e->getMessage());
  }

  // Fetch PMS inquiries for this agent
  try {
    $stmt = $pdo->prepare("
      SELECT
        pi.id as inquiry_id,
        pi.customer_id,
        pi.status,
        pi.created_at,
        pi.assigned_agent_id,
        cpr.plate_number,
        cpr.model,
        acc.FirstName,
        acc.LastName,
        ci.mobile_number,
        (SELECT COUNT(*) FROM pms_messages WHERE inquiry_id = pi.id AND is_read = 0 AND sender_type = 'Customer') as unread_messages
      FROM pms_inquiries pi
      LEFT JOIN car_pms_records cpr ON pi.pms_id = cpr.pms_id
      LEFT JOIN accounts acc ON pi.customer_id = acc.Id
      LEFT JOIN customer_information ci ON pi.customer_id = ci.account_id
      WHERE pi.assigned_agent_id = ? OR pi.assigned_agent_id IS NULL
      ORDER BY pi.created_at DESC
      LIMIT 100
    ");
    $stmt->execute([$agentId]);
    $pms_inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);
  } catch (Exception $e) {
    $pms_inquiries = [];
    error_log("Error fetching PMS inquiry data: " . $e->getMessage());
  }
}

// Calculate PMS statistics
$pms_open_count = 0;
$pms_unread_count = 0;
foreach ($pms_inquiries as $pms) {
  if ($pms['status'] === 'Open') {
    $pms_open_count++;
  }
  if ($pms['unread_messages'] > 0) {
    $pms_unread_count++;
  }
}

// Calculate inquiry statistics
$new_count = count($new_inquiries);
$today_inquiries = 0;
$financing_inquiries = 0;
$tradein_inquiries = 0;

foreach ($new_inquiries as $inquiry) {
  if (date('Y-m-d', strtotime($inquiry['InquiryDate'])) == date('Y-m-d')) {
    $today_inquiries++;
  }
  if (!empty($inquiry['FinancingRequired'])) {
    $financing_inquiries++;
  }
  if (!empty($inquiry['TradeInVehicleDetails'])) {
    $tradein_inquiries++;
  }
}
?>

<div class="dashboard-grid">
  <div class="dashboard-card">
    <div class="card-header">
      <div class="card-icon red">
        <i class="fas fa-car"></i>
      </div>
      <div class="card-title">Test Drive Management</div>
    </div>
    <p>Manage and track customer test drive requests efficiently.</p>
  </div>

  <div class="dashboard-card">
    <div class="card-header">
      <div class="card-icon blue">
        <i class="fas fa-credit-card"></i>
      </div>
      <div class="card-title">Payment Transactions</div>
    </div>
    <p>Verify and manage customer loan payments and transaction records.</p>
  </div>

  <div class="dashboard-card">
    <div class="card-header">
      <div class="card-icon green">
        <i class="fas fa-question-circle"></i>
      </div>
      <div class="card-title">Customer Inquiries</div>
    </div>
    <p>Handle customer questions and provide excellent support services.</p>
  </div>

  <div class="dashboard-card">
    <div class="card-header">
      <div class="card-icon orange">
        <i class="fas fa-wrench"></i>
      </div>
      <div class="card-title">PMS Inquiries</div>
    </div>
    <p>Manage customer preventive maintenance service requests and communicate with customers.</p>
  </div>
</div>

<div class="action-buttons">
  <button class="action-btn" id="testDriveBtn">
    <i class="fas fa-car"></i>
    Test Drive Booking Review
  </button>
  <button class="action-btn" id="paymentTransactionBtn">
    <i class="fas fa-credit-card"></i>
    Payment Transactions
  </button>
  <button class="action-btn" id="quoteRequestBtn">
    <i class="fas fa-file-invoice-dollar"></i>
    Quote Requests
  </button>
  <button class="action-btn" id="inquiryBtn">
    <i class="fas fa-question-circle"></i>
    Inquiry Management
  </button>
  <a href="/pages/agent_pms_inquiries.php" class="action-btn" style="text-decoration: none; display: inline-flex; align-items: center; justify-content: center;">
    <i class="fas fa-wrench"></i>
    PMS Inquiries Management
  </a>
</div>

<!-- Sales Agent Test Drive Interface -->
<div class="interface-container" id="testDriveInterface">
  <div class="interface-header">
    <h2 class="interface-title">
      <i class="fas fa-car"></i>
      Test Drive Booking Review
    </h2>
    <button class="interface-close" id="closeTestDrive">&times;</button>
  </div>

  <div class="tab-navigation">
    <button class="tab-button active" data-tab="testDrive-pending">Pending Requests</button>
    <button class="tab-button" data-tab="testDrive-approved">Approved Bookings</button>
    <button class="tab-button" data-tab="testDrive-completed">Completed Drives</button>
    <button class="tab-button" data-tab="testDrive-noshow">No Show</button>
    <button class="tab-button" data-tab="testDrive-rejected">Rejected Bookings</button>
  </div>

  <div class="tab-content active" id="testDrive-pending">
    <div class="info-cards" id="testDriveStats">
      <div class="info-card">
        <div class="info-card-title">Pending Reviews</div>
        <div class="info-card-value"><?php echo $pending_count; ?></div>
      </div>
      <div class="info-card">
        <div class="info-card-title">Urgent Requests</div>
        <div class="info-card-value"><?php echo $urgent_count; ?></div>
      </div>
      <div class="info-card">
        <div class="info-card-title">Today's Requests</div>
        <div class="info-card-value"><?php echo $today_count; ?></div>
      </div>
    </div>

    <div class="table-container">
      <table class="data-table responsive-table">
        <thead>
          <tr>
            <th>Request ID</th>
            <th>Customer</th>
            <th>Contact</th>
            <th>Preferred Date</th>
            <th>Time Slot</th>
            <th>Priority</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($pending_requests)): ?>
            <tr>
              <td colspan="7" class="text-center">No pending requests found</td>
            </tr>
          <?php else: ?>
            <?php foreach ($pending_requests as $request):
              $customerName = ($request['FirstName'] && $request['LastName'])
                ? $request['FirstName'] . ' ' . $request['LastName']
                : $request['customer_name'];
              $priority = getTestDrivePriority($request['selected_date']);
            ?>
              <tr>
                <td>TD-<?php echo str_pad($request['id'], 4, '0', STR_PAD_LEFT); ?></td>
                <td><?php echo htmlspecialchars($customerName); ?><br><small><?php echo htmlspecialchars($request['Email'] ?? 'N/A'); ?></small></td>
                <td><?php echo htmlspecialchars($request['mobile_number']); ?></td>
                <td><?php echo date('M d, Y', strtotime($request['selected_date'])); ?></td>
                <td><?php echo htmlspecialchars($request['selected_time_slot']); ?></td>
                <td><span class="status <?php echo $priority['class']; ?>"><?php echo $priority['label']; ?></span></td>
                <td class="table-actions">
                  <button class="btn btn-small btn-info" onclick="viewTestDriveDetails(<?php echo $request['id']; ?>)" style="margin-right: 5px;">
                    <i class="fas fa-info-circle"></i> View
                  </button>
                  <button class="btn btn-small btn-primary" onclick="reviewTestDriveRequest(<?php echo $request['id']; ?>)">
                    <i class="fas fa-eye"></i> Review
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="tab-content" id="testDrive-approved">
    <div class="table-container">
      <table class="data-table responsive-table">
        <thead>
          <tr>
            <th>Request ID</th>
            <th>Customer</th>
            <th>Date & Time</th>
            <th>Location</th>
            <th>Instructor</th>
            <th>Approved Date</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($approved_requests)): ?>
            <tr>
              <td colspan="7" class="text-center">No approved bookings found</td>
            </tr>
          <?php else: ?>
            <?php foreach ($approved_requests as $request):
              $customerName = ($request['FirstName'] && $request['LastName'])
                ? $request['FirstName'] . ' ' . $request['LastName']
                : $request['customer_name'];
            ?>
              <tr>
                <td>TD-<?php echo str_pad($request['id'], 4, '0', STR_PAD_LEFT); ?></td>
                <td><?php echo htmlspecialchars($customerName); ?></td>
                <td><?php echo date('M d, Y', strtotime($request['selected_date'])); ?> <?php echo htmlspecialchars($request['selected_time_slot']); ?></td>
                <td><?php echo htmlspecialchars($request['test_drive_location']); ?></td>
                <td><?php echo htmlspecialchars($request['instructor_agent'] ?? 'Not assigned'); ?></td>
                <td><?php echo $request['approved_at'] ? date('M d, Y', strtotime($request['approved_at'])) : 'N/A'; ?></td>
                <td class="table-actions">
                  <button class="btn btn-small btn-info" onclick="viewTestDriveDetails(<?php echo $request['id']; ?>)">
                    <i class="fas fa-info-circle"></i> View
                  </button>
                  <button class="btn btn-small btn-success" onclick="handleTestDriveStatus(<?php echo $request['id']; ?>)">
                    <i class="fas fa-tasks"></i> Update Status
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="tab-content" id="testDrive-completed">
    <div class="table-container">
      <table class="data-table responsive-table">
        <thead>
          <tr>
            <th>Request ID</th>
            <th>Customer</th>
            <th>Date Completed</th>
            <th>Location</th>
            <th>Instructor</th>
            <th>Notes</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($completed_requests)): ?>
            <tr>
              <td colspan="7" class="text-center">No completed drives found</td>
            </tr>
          <?php else: ?>
            <?php foreach ($completed_requests as $request):
              $customerName = ($request['FirstName'] && $request['LastName'])
                ? $request['FirstName'] . ' ' . $request['LastName']
                : $request['customer_name'];
              $notes = $request['notes'] ?? 'No notes';
              $displayNotes = strlen($notes) > 50 ? substr($notes, 0, 50) . '...' : $notes;
            ?>
              <tr>
                <td>TD-<?php echo str_pad($request['id'], 4, '0', STR_PAD_LEFT); ?></td>
                <td><?php echo htmlspecialchars($customerName); ?></td>
                <td><?php echo date('M d, Y', strtotime($request['selected_date'])); ?></td>
                <td><?php echo htmlspecialchars($request['test_drive_location']); ?></td>
                <td><?php echo htmlspecialchars($request['instructor_agent'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($displayNotes); ?></td>
                <td class="table-actions">
                  <button class="btn btn-small btn-info" onclick="viewTestDriveDetails(<?php echo $request['id']; ?>)">
                    <i class="fas fa-info-circle"></i> View Details
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="tab-content" id="testDrive-noshow">
    <div class="table-container">
      <table class="data-table responsive-table">
        <thead>
          <tr>
            <th>Request ID</th>
            <th>Customer</th>
            <th>Contact</th>
            <th>Scheduled Date</th>
            <th>Location</th>
            <th>Instructor</th>
            <th>Notes</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($no_show_requests) && empty($cancelled_requests)): ?>
            <tr>
              <td colspan="8" class="text-center">No no-show or cancelled requests found</td>
            </tr>
          <?php else: ?>
            <?php 
            $combined_requests = array_merge($no_show_requests, $cancelled_requests);
            usort($combined_requests, function($a, $b) {
              return strtotime($b['selected_date']) - strtotime($a['selected_date']);
            });
            foreach ($combined_requests as $request):
              $customerName = ($request['FirstName'] && $request['LastName'])
                ? $request['FirstName'] . ' ' . $request['LastName']
                : $request['customer_name'];
              $notes = $request['notes'] ?? 'No notes provided';
              // Remove the prefix from display notes
              $displayNotes = preg_replace('/^\[(NO_SHOW|CANCELLED)\]\s*/', '', $notes);
              $displayNotes = strlen($displayNotes) > 50 ? substr($displayNotes, 0, 50) . '...' : $displayNotes;
              // Determine status from notes prefix
              $isNoShow = strpos($notes, '[NO_SHOW]') === 0;
              $statusClass = $isNoShow ? 'status-badge no-show' : 'status-badge cancelled';
              $statusLabel = $isNoShow ? 'No Show' : 'Cancelled';
            ?>
              <tr>
                <td>
                  TD-<?php echo str_pad($request['id'], 4, '0', STR_PAD_LEFT); ?>
                  <br><span class="<?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span>
                </td>
                <td><?php echo htmlspecialchars($customerName); ?><br><small><?php echo htmlspecialchars($request['Email'] ?? 'N/A'); ?></small></td>
                <td><?php echo htmlspecialchars($request['mobile_number']); ?></td>
                <td><?php echo date('M d, Y', strtotime($request['selected_date'])); ?><br><?php echo htmlspecialchars($request['selected_time_slot']); ?></td>
                <td><?php echo htmlspecialchars($request['test_drive_location']); ?></td>
                <td><?php echo htmlspecialchars($request['instructor_agent'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($displayNotes); ?></td>
                <td class="table-actions">
                  <button class="btn btn-small btn-info" onclick="viewTestDriveDetails(<?php echo $request['id']; ?>)">
                    <i class="fas fa-info-circle"></i> View
                  </button>
                  <?php if ($isNoShow): ?>
                    <button class="btn btn-small btn-primary" onclick="rescheduleTestDrive(<?php echo $request['id']; ?>)">
                      <i class="fas fa-redo"></i> Reschedule
                    </button>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="tab-content" id="testDrive-rejected">
    <div class="table-container">
      <table class="data-table responsive-table">
        <thead>
          <tr>
            <th>Request ID</th>
            <th>Customer</th>
            <th>Contact</th>
            <th>Preferred Date</th>
            <th>Time Slot</th>
            <th>Rejection Notes</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rejected_requests)): ?>
            <tr>
              <td colspan="7" class="text-center">No rejected requests found</td>
            </tr>
          <?php else: ?>
            <?php foreach ($rejected_requests as $request):
              $customerName = ($request['FirstName'] && $request['LastName'])
                ? $request['FirstName'] . ' ' . $request['LastName']
                : $request['customer_name'];
              $notes = $request['notes'] ?? 'No reason provided';
              $displayNotes = strlen($notes) > 50 ? substr($notes, 0, 50) . '...' : $notes;
            ?>
              <tr>
                <td>TD-<?php echo str_pad($request['id'], 4, '0', STR_PAD_LEFT); ?></td>
                <td><?php echo htmlspecialchars($customerName); ?><br><small><?php echo htmlspecialchars($request['Email'] ?? 'N/A'); ?></small></td>
                <td><?php echo htmlspecialchars($request['mobile_number']); ?></td>
                <td><?php echo date('M d, Y', strtotime($request['selected_date'])); ?></td>
                <td><?php echo htmlspecialchars($request['selected_time_slot']); ?></td>
                <td><?php echo htmlspecialchars($displayNotes); ?></td>
                <td class="table-actions">
                  <button class="btn btn-small btn-warning" onclick="undoTestDriveRejection(<?php echo $request['id']; ?>)">
                    <i class="fas fa-undo"></i> Undo Rejection
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Payment Transaction Interface -->
<div class="interface-container" id="paymentTransactionInterface">
  <div class="interface-header">
    <h2 class="interface-title">
      <i class="fas fa-credit-card"></i>
      Payment Transaction Management
    </h2>
    <button class="interface-close" id="closePaymentTransaction">&times;</button>
  </div>

  <div class="tab-navigation">
    <button class="tab-button active" data-tab="payment-pending">Pending Payments</button>
    <button class="tab-button" data-tab="payment-verified">Verified Payments</button>
    <button class="tab-button" data-tab="payment-customers">My Loan Customers</button>
  </div>

  <div class="tab-content active" id="payment-pending">
    <div class="info-cards">
      <div class="info-card">
        <div class="info-card-title">Pending Verifications</div>
        <div class="info-card-value" id="agentPendingCount">Loading...</div>
      </div>
      <div class="info-card">
        <div class="info-card-title">Total Pending Amount</div>
        <div class="info-card-value" id="agentPendingAmount">Loading...</div>
      </div>
    </div>

    <div class="table-container">
      <table class="data-table responsive-table">
      <thead>
        <tr>
          <th>Payment ID</th>
          <th>Customer</th>
          <th>Vehicle</th>
          <th>Amount</th>
          <th>Method</th>
          <th>Reference</th>
          <th>Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody id="agentPendingPaymentsTable">
        <tr>
          <td colspan="8" class="text-center">Loading pending payments...</td>
        </tr>
      </tbody>
      </table>
    </div>
  </div>

  <div class="tab-content" id="payment-verified">
    <div class="info-cards">
      <div class="info-card">
        <div class="info-card-title">Total Verified</div>
        <div class="info-card-value" id="agentVerifiedCount">0</div>
      </div>
      <div class="info-card">
        <div class="info-card-title">Total Verified Amount</div>
        <div class="info-card-value" id="agentVerifiedAmount">₱0.00</div>
      </div>
    </div>

    <div class="table-container">
      <table class="data-table responsive-table">
      <thead>
        <tr>
          <th>Payment ID</th>
          <th>Customer</th>
          <th>Vehicle</th>
          <th>Amount</th>
          <th>Payment Date</th>
          <th>Verified By</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody id="agentVerifiedPaymentsTable">
        <tr>
          <td colspan="7" class="text-center">Loading verified payments...</td>
        </tr>
      </tbody>
      </table>
    </div>
  </div>

  <div class="tab-content" id="payment-customers">
    <div class="info-cards">
      <div class="info-card">
        <div class="info-card-title">My Loan Customers</div>
        <div class="info-card-value" id="agentLoanCustomersCount">0</div>
      </div>
      <div class="info-card">
        <div class="info-card-title">Active Loans</div>
        <div class="info-card-value" id="agentActiveLoansCount">0</div>
      </div>
      <div class="info-card">
        <div class="info-card-title">Overdue Payments</div>
        <div class="info-card-value" id="agentOverdueCount">0</div>
      </div>
    </div>

    <div class="table-container">
      <table class="data-table responsive-table">
      <thead>
        <tr>
          <th>Order #</th>
          <th>Customer</th>
          <th>Vehicle</th>
          <th>Monthly Payment</th>
          <th>Progress</th>
          <th>Next Due</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody id="agentLoanCustomersTable">
        <tr>
          <td colspan="7" class="text-center">Loading loan customers...</td>
        </tr>
      </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Amortization Management Interface -->
<div class="interface-container" id="amortizationInterface">
  <div class="interface-header">
    <h2 class="interface-title">
      <i class="fas fa-calendar-check"></i>
      Amortization Payment Management
    </h2>
    <button class="interface-close" id="closeAmortization">&times;</button>
  </div>

  <div class="tab-navigation">
    <button class="tab-button active" data-tab="amortization-upcoming">Upcoming Payments</button>
    <button class="tab-button" data-tab="amortization-overdue">Overdue Payments</button>
    <button class="tab-button" data-tab="amortization-recent">Recent Confirmations</button>
  </div>

  <!-- Upcoming Payments Tab -->
  <div class="tab-content active" id="amortization-upcoming">
    <div class="info-cards">
      <div class="info-card">
        <div class="info-card-title">Total Upcoming</div>
        <div class="info-card-value" id="amortUpcomingCount">0</div>
      </div>
      <div class="info-card">
        <div class="info-card-title">Total Amount</div>
        <div class="info-card-value" id="amortUpcomingAmount">₱0.00</div>
      </div>
      <div class="info-card">
        <div class="info-card-title">Due This Week</div>
        <div class="info-card-value" id="amortDueThisWeek">0</div>
      </div>
    </div>

    <div class="table-container">
      <table class="data-table responsive-table">
        <thead>
          <tr>
            <th>Order #</th>
            <th>Customer Name</th>
            <th>Vehicle</th>
            <th>Payment #</th>
            <th>Amount Due</th>
            <th>Due Date</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="amortUpcomingTable">
          <tr>
            <td colspan="8" class="text-center">Loading upcoming payments...</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Overdue Payments Tab -->
  <div class="tab-content" id="amortization-overdue">
    <div class="info-cards">
      <div class="info-card">
        <div class="info-card-title">Total Overdue</div>
        <div class="info-card-value" id="amortOverdueCount">0</div>
      </div>
      <div class="info-card">
        <div class="info-card-title">Total Amount</div>
        <div class="info-card-value" id="amortOverdueAmount">₱0.00</div>
      </div>
      <div class="info-card">
        <div class="info-card-title">Critical (>30 days)</div>
        <div class="info-card-value" id="amortCriticalCount">0</div>
      </div>
    </div>

    <div class="table-container">
      <table class="data-table responsive-table">
        <thead>
          <tr>
            <th>Order #</th>
            <th>Customer Name</th>
            <th>Vehicle</th>
            <th>Payment #</th>
            <th>Amount Due</th>
            <th>Due Date</th>
            <th>Days Overdue</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="amortOverdueTable">
          <tr>
            <td colspan="8" class="text-center">Loading overdue payments...</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Recent Confirmations Tab -->
  <div class="tab-content" id="amortization-recent">
    <div class="info-cards">
      <div class="info-card">
        <div class="info-card-title">Today's Confirmations</div>
        <div class="info-card-value" id="amortTodayCount">0</div>
      </div>
      <div class="info-card">
        <div class="info-card-title">This Week</div>
        <div class="info-card-value" id="amortWeekCount">0</div>
      </div>
      <div class="info-card">
        <div class="info-card-title">Total Amount</div>
        <div class="info-card-value" id="amortRecentAmount">₱0.00</div>
      </div>
    </div>

    <div class="table-container">
      <table class="data-table responsive-table">
        <thead>
          <tr>
            <th>Payment Ref</th>
            <th>Customer Name</th>
            <th>Vehicle</th>
            <th>Amount</th>
            <th>Payment Date</th>
            <th>Processed By</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody id="amortRecentTable">
          <tr>
            <td colspan="7" class="text-center">Loading recent confirmations...</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Quote Requests Interface -->
<div class="interface-container" id="quoteRequestInterface">
  <div class="interface-header">
    <h2 class="interface-title">
      <i class="fas fa-file-invoice-dollar"></i>
      Instant Quote Requests
    </h2>
    <button class="interface-close" id="closeQuoteRequest">&times;</button>
  </div>

  <div class="tab-navigation">
    <button class="tab-button active" data-tab="quote-recent">Recent (30 days)</button>
    <button class="tab-button" data-tab="quote-all">All Quotes</button>
  </div>

  <div class="tab-content active" id="quote-recent">
    <div class="info-cards">
      <div class="info-card">
        <div class="info-card-title">This Week</div>
        <div class="info-card-value"><?php echo $quote_stats['this_week']; ?></div>
      </div>
      <div class="info-card">
        <div class="info-card-title">This Month</div>
        <div class="info-card-value"><?php echo $quote_stats['this_month']; ?></div>
      </div>
      <div class="info-card">
        <div class="info-card-title">Total Quotes</div>
        <div class="info-card-value"><?php echo $quote_stats['total']; ?></div>
      </div>
    </div>

    <div class="table-container">
      <table class="data-table responsive-table">
        <thead>
          <tr>
            <th>Quote ID</th>
            <th>Customer</th>
            <th>Contact</th>
            <th>Vehicle</th>
            <th>Financing Details</th>
            <th>Timeframe</th>
            <th>Requested</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($recent_quotes)): ?>
            <tr>
              <td colspan="7" class="text-center">No recent quote requests found</td>
            </tr>
          <?php else: ?>
            <?php foreach ($recent_quotes as $quote): ?>
              <tr>
                <td>Q-<?php echo str_pad($quote['Id'], 5, '0', STR_PAD_LEFT); ?></td>
                <td>
                  <?php echo htmlspecialchars($quote['FirstName'] . ' ' . $quote['LastName']); ?>
                  <br><small><?php echo htmlspecialchars($quote['Email']); ?></small>
                </td>
                <td><?php echo htmlspecialchars($quote['PhoneNumber']); ?></td>
                <td>
                  <?php echo htmlspecialchars($quote['model_name']); ?>
                  <?php if ($quote['variant']): ?>
                    <br><small><?php echo htmlspecialchars($quote['variant']); ?></small>
                  <?php endif; ?>
                  <br><small><?php echo $quote['year_model']; ?></small>
                </td>
                <td>
                  <?php if ($quote['payment_plan_calculated']): ?>
                    <strong>Down Payment:</strong> ₱<?php echo number_format($quote['down_payment'], 2); ?><br>
                    <strong>Term:</strong> <?php echo $quote['financing_term']; ?> months<br>
                    <strong>Monthly:</strong> ₱<?php echo number_format($quote['monthly_payment'], 2); ?>
                  <?php else: ?>
                    <span class="text-muted">No financing details</span>
                  <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($quote['PurchaseTimeframe']); ?></td>
                <td><?php echo date('M d, Y H:i', strtotime($quote['RequestedAt'])); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="tab-content" id="quote-all">
    <div class="table-container">
      <table class="data-table responsive-table">
        <thead>
          <tr>
            <th>Quote ID</th>
            <th>Customer</th>
            <th>Vehicle</th>
            <th>Location</th>
            <th>Timeframe</th>
            <th>Requested</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($all_quotes)): ?>
            <tr>
              <td colspan="7" class="text-center">No quote requests found</td>
            </tr>
          <?php else: ?>
            <?php foreach ($all_quotes as $quote): ?>
              <tr>
                <td>Q-<?php echo str_pad($quote['Id'], 5, '0', STR_PAD_LEFT); ?></td>
                <td>
                  <?php echo htmlspecialchars($quote['FirstName'] . ' ' . $quote['LastName']); ?>
                  <br><small><?php echo htmlspecialchars($quote['Email']); ?></small>
                </td>
                <td>
                  <?php echo htmlspecialchars($quote['model_name']); ?>
                  <?php if ($quote['variant']): ?>
                    <?php echo htmlspecialchars($quote['variant']); ?>
                  <?php endif; ?>
                  (<?php echo $quote['year_model']; ?>)
                </td>
                <td><?php echo htmlspecialchars($quote['PreferredLocation'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($quote['PurchaseTimeframe']); ?></td>
                <td><?php echo date('M d, Y', strtotime($quote['RequestedAt'])); ?></td>
                <td>
                  <?php
                    $status = $quote['QuoteStatus'] ?? 'Pending';
                    $badgeClass = 'badge-warning';
                    if ($status == 'Completed') $badgeClass = 'badge-success';
                    elseif ($status == 'In Progress') $badgeClass = 'badge-info';
                    elseif ($status == 'Expired') $badgeClass = 'badge-danger';
                  ?>
                  <span class="badge <?php echo $badgeClass; ?>">
                    <?php echo htmlspecialchars($status); ?>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Inquiry Management Interface -->
<div class="interface-container" id="inquiryInterface">
  <div class="interface-header">
    <h2 class="interface-title">
      <i class="fas fa-question-circle"></i>
      Customer Inquiries Management
    </h2>
    <button class="interface-close" id="closeInquiry">&times;</button>
  </div>

  <div class="tab-navigation">
    <button class="tab-button active" data-tab="inquiry-new">Unresponded Inquiries (<?php echo $new_count; ?>)</button>
    <button class="tab-button" data-tab="inquiry-recent">Recent (30 days)</button>
    <button class="tab-button" data-tab="inquiry-all">All Inquiries</button>
  </div>

  <div class="tab-content active" id="inquiry-new">
    <div class="info-cards">
      <div class="info-card">
        <div class="info-card-title">New (Unresponded) This Week</div>
        <div class="info-card-value"><?php echo $new_count; ?></div>
      </div>
      <div class="info-card">
        <div class="info-card-title">Today's Inquiries</div>
        <div class="info-card-value"><?php echo $today_inquiries; ?></div>
      </div>
      <div class="info-card">
        <div class="info-card-title">Financing Requests</div>
        <div class="info-card-value"><?php echo $financing_inquiries; ?></div>
      </div>
      <div class="info-card">
        <div class="info-card-title">Trade-In Requests</div>
        <div class="info-card-value"><?php echo $tradein_inquiries; ?></div>
      </div>
    </div>

    <div class="table-container">
      <table class="data-table responsive-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Customer</th>
            <th>Contact</th>
            <th>Vehicle Interest</th>
            <th>Special Requests</th>
            <th>Date</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($new_inquiries)): ?>
            <tr>
              <td colspan="7" class="text-center">No new inquiries found</td>
            </tr>
          <?php else: ?>
            <?php foreach ($new_inquiries as $inquiry):
              $hasFinancing = !empty($inquiry['FinancingRequired']);
              $hasTradeIn = !empty($inquiry['TradeInVehicleDetails']);
              $specialRequests = [];
              if ($hasFinancing) $specialRequests[] = '<span class="badge badge-info">Financing</span>';
              if ($hasTradeIn) $specialRequests[] = '<span class="badge badge-warning">Trade-In</span>';
            ?>
              <tr>
                <td>INQ-<?php echo str_pad($inquiry['Id'], 5, '0', STR_PAD_LEFT); ?></td>
                <td>
                  <?php echo htmlspecialchars($inquiry['FullName']); ?>
                  <br><small><?php echo htmlspecialchars($inquiry['Email']); ?></small>
                </td>
                <td><?php echo htmlspecialchars($inquiry['PhoneNumber'] ?? 'N/A'); ?></td>
                <td>
                  <?php echo htmlspecialchars($inquiry['VehicleModel']); ?>
                  <?php if ($inquiry['VehicleVariant']): ?>
                    <br><small><?php echo htmlspecialchars($inquiry['VehicleVariant']); ?></small>
                  <?php endif; ?>
                  <br><small><?php echo $inquiry['VehicleYear'] . ' - ' . htmlspecialchars($inquiry['VehicleColor']); ?></small>
                </td>
                <td><?php echo !empty($specialRequests) ? implode(' ', $specialRequests) : '<span class="text-muted">None</span>'; ?></td>
                <td><?php echo date('M d, Y H:i', strtotime($inquiry['InquiryDate'])); ?></td>
                <td class="table-actions">
                  <button class="btn btn-small btn-primary" onclick="viewInquiryDetails(<?php echo $inquiry['Id']; ?>)">
                    <i class="fas fa-eye"></i> View
                  </button>
                  <button class="btn btn-small btn-success" onclick="respondToInquiry(<?php echo $inquiry['Id']; ?>)">
                    <i class="fas fa-reply"></i> Respond
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="tab-content" id="inquiry-recent">
    <div class="table-container">
      <table class="data-table responsive-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Customer</th>
            <th>Vehicle Interest</th>
            <th>Status</th>
            <th>Date</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($recent_inquiries)): ?>
            <tr>
              <td colspan="6" class="text-center">No recent inquiries found</td>
            </tr>
          <?php else: ?>
            <?php foreach ($recent_inquiries as $inquiry): ?>
              <tr>
                <td>INQ-<?php echo str_pad($inquiry['Id'], 5, '0', STR_PAD_LEFT); ?></td>
                <td><?php echo htmlspecialchars($inquiry['FullName']); ?></td>
                <td><?php echo htmlspecialchars($inquiry['VehicleModel']); ?></td>
                <td>
                  <?php if ($inquiry['response_count'] > 0): ?>
                    <span class="badge badge-success">
                      <i class="fas fa-check-circle"></i> Responded (<?php echo $inquiry['response_count']; ?>)
                    </span>
                  <?php else: ?>
                    <span class="badge badge-warning">
                      <i class="fas fa-clock"></i> Pending
                    </span>
                  <?php endif; ?>
                </td>
                <td><?php echo date('M d, Y', strtotime($inquiry['InquiryDate'])); ?></td>
                <td class="table-actions">
                  <button class="btn btn-small btn-info" onclick="viewInquiryDetails(<?php echo $inquiry['Id']; ?>)">
                    <i class="fas fa-info-circle"></i> Details
                  </button>
                  <?php if ($inquiry['response_count'] == 0): ?>
                    <button class="btn btn-small btn-success" onclick="respondToInquiry(<?php echo $inquiry['Id']; ?>)">
                      <i class="fas fa-reply"></i> Respond
                    </button>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="tab-content" id="inquiry-all">
    <div class="table-container">
      <table class="data-table responsive-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Customer</th>
            <th>Email</th>
            <th>Vehicle</th>
            <th>Status</th>
            <th>Date</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($all_inquiries)): ?>
            <tr>
              <td colspan="7" class="text-center">No inquiries found</td>
            </tr>
          <?php else: ?>
            <?php foreach ($all_inquiries as $inquiry): ?>
              <tr>
                <td>INQ-<?php echo str_pad($inquiry['Id'], 5, '0', STR_PAD_LEFT); ?></td>
                <td><?php echo htmlspecialchars($inquiry['FullName']); ?></td>
                <td><?php echo htmlspecialchars($inquiry['Email']); ?></td>
                <td><?php echo htmlspecialchars($inquiry['VehicleModel'] . ' ' . $inquiry['VehicleYear']); ?></td>
                <td>
                  <?php if ($inquiry['response_count'] > 0): ?>
                    <span class="badge badge-success">
                      <i class="fas fa-check-circle"></i> Responded (<?php echo $inquiry['response_count']; ?>)
                    </span>
                    <?php if ($inquiry['last_response_date']): ?>
                      <br><small style="color: #666;">Last: <?php echo date('M d, Y', strtotime($inquiry['last_response_date'])); ?></small>
                    <?php endif; ?>
                  <?php else: ?>
                    <span class="badge badge-warning">
                      <i class="fas fa-clock"></i> Pending
                    </span>
                  <?php endif; ?>
                </td>
                <td><?php echo date('M d, Y', strtotime($inquiry['InquiryDate'])); ?></td>
                <td class="table-actions">
                  <button class="btn btn-small btn-info" onclick="viewInquiryDetails(<?php echo $inquiry['Id']; ?>)">
                    <i class="fas fa-info-circle"></i> View
                  </button>
                  <?php if ($inquiry['response_count'] == 0): ?>
                    <button class="btn btn-small btn-success" onclick="respondToInquiry(<?php echo $inquiry['Id']; ?>)">
                      <i class="fas fa-reply"></i> Respond
                    </button>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Add License Viewer Modal after the inquiry interface -->
<div id="licenseModal" class="license-modal">
  <div class="license-modal-content">
    <button class="license-modal-close" onclick="closeLicenseModal()">&times;</button>
    <img id="licenseImage" class="license-image" src="" alt="Driver's License">
  </div>
</div>

<script>
  // Sales Agent-specific JavaScript
  document.addEventListener('DOMContentLoaded', function() {
    // Interface toggle buttons
    const testDriveBtn = document.getElementById('testDriveBtn');
    const paymentTransactionBtn = document.getElementById('paymentTransactionBtn');
    const amortizationBtn = document.getElementById('amortizationBtn');
    const quoteRequestBtn = document.getElementById('quoteRequestBtn');
    const inquiryBtn = document.getElementById('inquiryBtn');

    const testDriveInterface = document.getElementById('testDriveInterface');
    const paymentTransactionInterface = document.getElementById('paymentTransactionInterface');
    const amortizationInterface = document.getElementById('amortizationInterface');
    const quoteRequestInterface = document.getElementById('quoteRequestInterface');
    const inquiryInterface = document.getElementById('inquiryInterface');

    const closeTestDrive = document.getElementById('closeTestDrive');
    const closePaymentTransaction = document.getElementById('closePaymentTransaction');
    const closeAmortization = document.getElementById('closeAmortization');
    const closeQuoteRequest = document.getElementById('closeQuoteRequest');
    const closeInquiry = document.getElementById('closeInquiry');

    // Hide all interfaces
    function hideAllInterfaces() {
      if (testDriveInterface) testDriveInterface.style.display = 'none';
      if (paymentTransactionInterface) paymentTransactionInterface.style.display = 'none';
      if (amortizationInterface) amortizationInterface.style.display = 'none';
      if (quoteRequestInterface) quoteRequestInterface.style.display = 'none';
      if (inquiryInterface) inquiryInterface.style.display = 'none';
    }

    // Toggle interfaces
    if (testDriveBtn) {
      testDriveBtn.addEventListener('click', function() {
        hideAllInterfaces();
        testDriveInterface.style.display = 'block';
      });
    }

    if (paymentTransactionBtn) {
      paymentTransactionBtn.addEventListener('click', function() {
        hideAllInterfaces();
        paymentTransactionInterface.style.display = 'block';
        // Load payment data when opening interface
        setTimeout(() => {
          loadAgentPendingPayments();
          loadAgentVerifiedPayments();
          loadAgentLoanCustomers();
        }, 100);
      });
    }

    if (amortizationBtn) {
      amortizationBtn.addEventListener('click', function() {
        hideAllInterfaces();
        amortizationInterface.style.display = 'block';
        // Load amortization data when opening interface
        setTimeout(() => {
          loadUpcomingPayments();
          loadOverduePayments();
          loadRecentConfirmations();
        }, 100);
      });
    }

    if (quoteRequestBtn) {
      quoteRequestBtn.addEventListener('click', function() {
        hideAllInterfaces();
        quoteRequestInterface.style.display = 'block';
      });
    }

    if (inquiryBtn) {
      inquiryBtn.addEventListener('click', function() {
        hideAllInterfaces();
        inquiryInterface.style.display = 'block';
      });
    }

    // Close buttons
    if (closeTestDrive) {
      closeTestDrive.addEventListener('click', function() {
        testDriveInterface.style.display = 'none';
      });
    }

    if (closePaymentTransaction) {
      closePaymentTransaction.addEventListener('click', function() {
        paymentTransactionInterface.style.display = 'none';
      });
    }

    if (closeAmortization) {
      closeAmortization.addEventListener('click', function() {
        amortizationInterface.style.display = 'none';
      });
    }

    if (closeQuoteRequest) {
      closeQuoteRequest.addEventListener('click', function() {
        quoteRequestInterface.style.display = 'none';
      });
    }

    if (closeInquiry) {
      closeInquiry.addEventListener('click', function() {
        inquiryInterface.style.display = 'none';
      });
    }
    
    // Handle payment transaction tab switching
    const paymentTabs = document.querySelectorAll('#paymentTransactionInterface .tab-button');
    paymentTabs.forEach(tab => {
      tab.addEventListener('click', function() {
        const tabId = this.getAttribute('data-tab');
        if (tabId === 'payment-pending') {
          loadAgentPendingPayments();
        } else if (tabId === 'payment-verified') {
          loadAgentVerifiedPayments();
        } else if (tabId === 'payment-customers') {
          loadAgentLoanCustomers();
        }
      });
    });

    // Handle amortization tab switching
    const amortizationTabs = document.querySelectorAll('#amortizationInterface .tab-button');
    amortizationTabs.forEach(tab => {
      tab.addEventListener('click', function() {
        const tabId = this.getAttribute('data-tab');
        if (tabId === 'amortization-upcoming') {
          loadUpcomingPayments();
        } else if (tabId === 'amortization-overdue') {
          loadOverduePayments();
        } else if (tabId === 'amortization-recent') {
          loadRecentConfirmations();
        }
      });
    });

    // Tab switching functionality
    document.querySelectorAll('.tab-button').forEach(button => {
      button.addEventListener('click', function() {
        const tabId = this.getAttribute('data-tab');
        const interfaceContainer = this.closest('.interface-container');

        // Remove active class from all tabs and contents in this interface
        interfaceContainer.querySelectorAll('.tab-button').forEach(btn => {
          btn.classList.remove('active');
        });
        interfaceContainer.querySelectorAll('.tab-content').forEach(content => {
          content.classList.remove('active');
        });

        // Add active class to clicked tab and corresponding content
        this.classList.add('active');
        const targetContent = document.getElementById(tabId);
        if (targetContent) {
          targetContent.classList.add('active');
        }
      });
    });

    // Test Drive Management Functions with full backend connectivity
    window.reviewTestDriveRequest = function(requestId) {
  Swal.fire({
    title: '<div class="modal-header-inspired">Review Test Drive Request</div>',
    html: `
      <div class="modal-inspired-content">
        <div class="modal-inspired-section">
          <label class="modal-inspired-label">Action</label>
          <select id="reviewAction" class="modal-inspired-input">
            <option value="">Select action</option>
            <option value="approve">Approve Request</option>
            <option value="reject">Reject Request</option>
          </select>
        </div>
        <div id="instructorField" class="modal-inspired-section" style="display: none;">
          <label class="modal-inspired-label">Assign Instructor</label>
          <input id="instructor" type="text" class="modal-inspired-input" placeholder="Enter instructor name">
        </div>
        <div class="modal-inspired-section">
          <label class="modal-inspired-label">Notes</label>
          <textarea id="reviewNotes" class="modal-inspired-textarea" placeholder="Add any notes or comments"></textarea>
        </div>
      </div>
    `,
        showCancelButton: true,
        confirmButtonText: 'Submit',
        cancelButtonText: 'Cancel',
        preConfirm: () => {
          const action = document.getElementById('reviewAction').value;
          const instructor = document.getElementById('instructor').value;
          const notes = document.getElementById('reviewNotes').value;

          if (!action) {
            Swal.showValidationMessage('Please select an action');
            return false;
          }

          return {
            action,
            instructor,
            notes
          };
        }
      }).then((result) => {
        if (result.isConfirmed) {
          const {
            action,
            instructor,
            notes
          } = result.value;

          const formData = new FormData();
          formData.append('action', action === 'approve' ? 'approve_request' : 'reject_request');
          formData.append('request_id', requestId);
          formData.append('notes', notes);
          if (action === 'approve') {
            formData.append('instructor', instructor);
          }

          fetch('', {
              method: 'POST',
              body: formData
            })
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                Swal.fire('Success!', data.message, 'success').then(() => {
                  location.reload();
                });
              } else {
                Swal.fire('Error!', data.message || 'An error occurred', 'error');
              }
            })
            .catch(error => {
              console.error('Error:', error);
              Swal.fire('Error!', 'An error occurred while processing the request', 'error');
            });
        }
      });

  // Show/hide instructor field based on action
  setTimeout(() => {
    const reviewAction = document.getElementById('reviewAction');
    if (reviewAction) {
      reviewAction.addEventListener('change', function() {
        const instructorField = document.getElementById('instructorField');
        if (this.value === 'approve') {
          instructorField.style.display = 'block';
        } else {
          instructorField.style.display = 'none';
        }
      });
    }
  }, 100);
};

// Modal inspired styles
const modalInspiredStyle = document.createElement('style');
modalInspiredStyle.innerHTML = `
.modal-header-inspired {
  font-size: 1.5rem;
  font-weight: 700;
  color: #23272f;
  background: #fff;
  border-radius: 16px 16px 0 0;
  padding: 18px 0 10px 0;
  text-align: center;
  margin-bottom: 0;
}
.modal-inspired-content {
  background: #fff;
  border-radius: 0 0 16px 16px;
  box-shadow: 0 4px 24px rgba(0,0,0,0.08);
  padding: 24px 24px 10px 24px;
  display: flex;
  flex-direction: column;
  gap: 18px;
}
.modal-inspired-section {
  margin-bottom: 0;
}
.modal-inspired-label {
  display: block;
  margin-bottom: 7px;
  font-weight: 600;
  color: #d60000;
  font-size: 1rem;
}
.modal-inspired-input, .modal-inspired-textarea {
  width: 100%;
  border-radius: 7px;
  border: 1px solid #e0e0e0;
  background: #fafbfc;
  color: #23272f;
  padding: 10px 12px;
  font-size: 1rem;
  margin: 0;
  box-sizing: border-box;
  transition: border-color 0.2s;
}
.modal-inspired-input:focus, .modal-inspired-textarea:focus {
  border-color: #d60000;
  outline: none;
}
.modal-inspired-textarea {
  min-height: 70px;
  resize: vertical;
}
.swal2-popup {
  border-radius: 16px !important;
  background: #fff !important;
  color: #23272f !important;
  box-shadow: 0 8px 32px rgba(0,0,0,0.10) !important;
  padding: 0 !important;
  max-width: 420px !important;
}
.swal2-confirm {
  background: #d60000 !important;
  color: #fff !important;
  font-weight: bold !important;
  border-radius: 7px !important;
  padding: 10px 28px !important;
  font-size: 1.1rem !important;
  border: none !important;
  margin-right: 10px !important;
  box-shadow: 0 2px 8px rgba(214,0,0,0.08);
  transition: background 0.2s;
}
.swal2-confirm:hover {
  background: #a80000 !important;
}
.swal2-cancel {
  background: #f0f0f0 !important;
  color: #23272f !important;
  border-radius: 7px !important;
  padding: 10px 28px !important;
  font-size: 1.1rem !important;
  border: none !important;
}
.swal2-cancel:hover {
  background: #e0e0e0 !important;
}
`;
document.head.appendChild(modalInspiredStyle);


    window.handleTestDriveStatus = function(requestId) {
      Swal.fire({
        title: 'Update Test Drive Status',
        html: `
          <div style="text-align: center; padding: 1rem 0;">
            <p style="margin-bottom: 1.5rem; color: #666;">Choose the appropriate status for this test drive:</p>
            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
              <button id="btnComplete" class="swal2-styled" style="background: #28a745; width: 100%; margin: 0;">
                <i class="fas fa-check-circle"></i> Mark as Completed
              </button>
              <button id="btnNoShow" class="swal2-styled" style="background: #ffc107; color: #000; width: 100%; margin: 0;">
                <i class="fas fa-user-times"></i> Mark as No Show
              </button>
              <button id="btnCancel" class="swal2-styled" style="background: #dc3545; width: 100%; margin: 0;">
                <i class="fas fa-ban"></i> Mark as Cancelled
              </button>
            </div>
          </div>
        `,
        showConfirmButton: false,
        showCancelButton: true,
        cancelButtonText: 'Close',
        didOpen: () => {
          document.getElementById('btnComplete').addEventListener('click', () => {
            Swal.close();
            markTestDriveComplete(requestId);
          });
          document.getElementById('btnNoShow').addEventListener('click', () => {
            Swal.close();
            markTestDriveNoShow(requestId);
          });
          document.getElementById('btnCancel').addEventListener('click', () => {
            Swal.close();
            cancelTestDrive(requestId);
          });
        }
      });
    };

    window.markTestDriveComplete = function(requestId) {
      Swal.fire({
        title: 'Mark Test Drive as Completed',
        html: `
          <div style="text-align: left;">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: bold;">Completion Notes:</label>
            <textarea id="completionNotes" class="swal2-textarea" placeholder="Add completion notes, feedback, or observations"></textarea>
          </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Mark Complete',
        cancelButtonText: 'Cancel',
        preConfirm: () => {
          return document.getElementById('completionNotes').value;
        }
      }).then((result) => {
        if (result.isConfirmed) {
          const formData = new FormData();
          formData.append('action', 'complete_request');
          formData.append('request_id', requestId);
          formData.append('completion_notes', result.value);

          fetch('', {
              method: 'POST',
              body: formData
            })
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                Swal.fire('Success!', data.message, 'success').then(() => {
                  location.reload();
                });
              } else {
                Swal.fire('Error!', data.message || 'An error occurred', 'error');
              }
            })
            .catch(error => {
              console.error('Error:', error);
              Swal.fire('Error!', 'An error occurred while processing the request', 'error');
            });
        }
      });
    };

    window.markTestDriveNoShow = function(requestId) {
      Swal.fire({
        title: 'Mark as No Show',
        html: `
          <div style="text-align: left;">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: bold;">No Show Notes:</label>
            <textarea id="noShowNotes" class="swal2-textarea" placeholder="Add details about the no-show (e.g., time waited, contact attempts made)"></textarea>
          </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Mark as No Show',
        cancelButtonText: 'Cancel',
        icon: 'warning',
        preConfirm: () => {
          return document.getElementById('noShowNotes').value;
        }
      }).then((result) => {
        if (result.isConfirmed) {
          const formData = new FormData();
          formData.append('action', 'no_show_request');
          formData.append('request_id', requestId);
          formData.append('no_show_notes', result.value);

          fetch('', {
              method: 'POST',
              body: formData
            })
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                Swal.fire('Updated!', data.message, 'success').then(() => {
                  location.reload();
                });
              } else {
                Swal.fire('Error!', data.message || 'An error occurred', 'error');
              }
            })
            .catch(error => {
              console.error('Error:', error);
              Swal.fire('Error!', 'An error occurred while processing the request', 'error');
            });
        }
      });
    };

    window.cancelTestDrive = function(requestId) {
      Swal.fire({
        title: 'Cancel Test Drive',
        html: `
          <div style="text-align: left;">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: bold;">Cancellation Reason:</label>
            <textarea id="cancelReason" class="swal2-textarea" placeholder="Please provide the reason for cancellation"></textarea>
          </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Confirm Cancellation',
        cancelButtonText: 'Back',
        icon: 'warning',
        preConfirm: () => {
          const reason = document.getElementById('cancelReason').value;
          if (!reason.trim()) {
            Swal.showValidationMessage('Please provide a cancellation reason');
          }
          return reason;
        }
      }).then((result) => {
        if (result.isConfirmed) {
          const formData = new FormData();
          formData.append('action', 'cancel_request');
          formData.append('request_id', requestId);
          formData.append('cancel_reason', result.value);

          fetch('', {
              method: 'POST',
              body: formData
            })
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                Swal.fire('Cancelled!', data.message, 'success').then(() => {
                  location.reload();
                });
              } else {
                Swal.fire('Error!', data.message || 'An error occurred', 'error');
              }
            })
            .catch(error => {
              console.error('Error:', error);
              Swal.fire('Error!', 'An error occurred while processing the request', 'error');
            });
        }
      });
    };

    window.rescheduleTestDrive = function(requestId) {
      // Get tomorrow's date as minimum
      const tomorrow = new Date();
      tomorrow.setDate(tomorrow.getDate() + 1);
      const minDate = tomorrow.toISOString().split('T')[0];

      Swal.fire({
        title: 'Reschedule Test Drive',
        html: `
          <div style="text-align: left; padding: 0.5rem;">
            <p style="margin-bottom: 1rem; color: #666;">Set a new date and time for this test drive appointment.</p>
            
            <div style="margin-bottom: 1rem;">
              <label style="display: block; margin-bottom: 0.5rem; font-weight: bold; color: #d60000;">
                <i class="fas fa-calendar-alt"></i> New Date:
              </label>
              <input type="date" id="newDate" class="swal2-input" 
                     min="${minDate}"
                     style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 0.5rem; font-size: 1rem; box-sizing: border-box;" 
                     required />
            </div>

            <div style="margin-bottom: 1rem;">
              <label style="display: block; margin-bottom: 0.5rem; font-weight: bold; color: #d60000;">
                <i class="fas fa-clock"></i> Time Slot:
              </label>
              <select id="newTime" class="swal2-select" 
                      style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 0.5rem; font-size: 1rem; box-sizing: border-box;">
                <option value="">Select a time slot</option>
                <option value="9:00 AM - 10:00 AM">9:00 AM - 10:00 AM</option>
                <option value="10:00 AM - 11:00 AM">10:00 AM - 11:00 AM</option>
                <option value="11:00 AM - 12:00 PM">11:00 AM - 12:00 PM</option>
                <option value="1:00 PM - 2:00 PM">1:00 PM - 2:00 PM</option>
                <option value="2:00 PM - 3:00 PM">2:00 PM - 3:00 PM</option>
                <option value="3:00 PM - 4:00 PM">3:00 PM - 4:00 PM</option>
                <option value="4:00 PM - 5:00 PM">4:00 PM - 5:00 PM</option>
              </select>
            </div>

            <div style="margin-bottom: 0.5rem;">
              <label style="display: block; margin-bottom: 0.5rem; font-weight: bold; color: #d60000;">
                <i class="fas fa-sticky-note"></i> Reschedule Notes (Optional):
              </label>
              <textarea id="rescheduleNotes" class="swal2-textarea" 
                        placeholder="Add any notes about the rescheduling (e.g., reason for rescheduling, special instructions)"
                        style="width: 100%; min-height: 4rem; padding: 0.75rem; border: 1px solid #ddd; border-radius: 0.5rem; font-size: 1rem; resize: vertical; box-sizing: border-box;"></textarea>
            </div>
          </div>
        `,
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-calendar-check"></i> Confirm Reschedule',
        cancelButtonText: 'Cancel',
        width: '40rem',
        icon: 'info',
        customClass: {
          confirmButton: 'swal2-confirm',
          cancelButton: 'swal2-cancel'
        },
        preConfirm: () => {
          const newDate = document.getElementById('newDate').value;
          const newTime = document.getElementById('newTime').value;
          const notes = document.getElementById('rescheduleNotes').value;

          if (!newDate) {
            Swal.showValidationMessage('Please select a new date');
            return false;
          }
          if (!newTime) {
            Swal.showValidationMessage('Please select a time slot');
            return false;
          }

          return { newDate, newTime, notes };
        }
      }).then((result) => {
        if (result.isConfirmed) {
          const formData = new FormData();
          formData.append('action', 'reschedule_request');
          formData.append('request_id', requestId);
          formData.append('new_date', result.value.newDate);
          formData.append('new_time', result.value.newTime);
          formData.append('reschedule_notes', result.value.notes);

          fetch('', {
              method: 'POST',
              body: formData
            })
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                Swal.fire('Rescheduled!', data.message, 'success').then(() => {
                  location.reload();
                });
              } else {
                Swal.fire('Error!', data.message || 'An error occurred', 'error');
              }
            })
            .catch(error => {
              console.error('Error:', error);
              Swal.fire('Error!', 'An error occurred while processing the request', 'error');
            });
        }
      });
    };

    window.viewTestDriveDetails = function(requestId) {
      // Fetch detailed information about the test drive
      const formData = new FormData();
      formData.append('action', 'get_details');
      formData.append('request_id', requestId);

      fetch('', { // Changed from 'dashboard.php' to empty string to post to current page
          method: 'POST',
          body: formData
        })
        .then(response => {
          if (!response.ok) {
            throw new Error('Network response was not ok');
          }
          return response.json();
        })
        .then(data => {
          if (data.success) {
            const request = data.data;
            const customerName = request.account_name && request.account_name.trim() !== '' ?
              request.account_name :
              request.customer_name;

            let detailsHtml = `
              <div style="text-align: left; line-height: 1.8;">
                <h3 style="color: #d60000; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px;">
                  <i class="fas fa-user"></i> Customer Information
                </h3>
                <p><strong>Account ID:</strong> ${request.account_id || 'N/A'}</p>
                <p><strong>Name:</strong> ${customerName}</p>
                <p><strong>Email:</strong> ${request.email || 'N/A'}</p>
                <p><strong>Mobile:</strong> ${request.mobile_number}</p>
                
                <h3 style="color: #d60000; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; margin-top: 20px;">
                  <i class="fas fa-car"></i> Test Drive Details
                </h3>
                <p><strong>Date:</strong> ${new Date(request.selected_date).toLocaleDateString()}</p>
                <p><strong>Time:</strong> ${request.selected_time_slot}</p>
                <p><strong>Location:</strong> ${request.test_drive_location}</p>
                <p><strong>Instructor:</strong> ${request.instructor_agent || 'Not assigned'}</p>
                <p><strong>Status:</strong> <span class="status ${request.status.toLowerCase()}">${request.status}</span></p>
                
                <h3 style="color: #d60000; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; margin-top: 20px;">
                  <i class="fas fa-file-alt"></i> Documentation
                </h3>
                <p><strong>Driver's License:</strong> ${request.drivers_license ? 
                  '<span style="color: green;"><i class="fas fa-check-circle"></i> Uploaded</span>' : 
                  '<span style="color: #999;"><i class="fas fa-times-circle"></i> Not uploaded</span>'}</p>
                <p><strong>Terms Accepted:</strong> ${request.terms_accepted == 1 ? 
                  '<span style="color: green;"><i class="fas fa-check-circle"></i> Yes</span>' : 
                  '<span style="color: red;"><i class="fas fa-times-circle"></i> No</span>'}</p>
                
                ${request.notes ? `
                <h3 style="color: #d60000; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; margin-top: 20px;">
                  <i class="fas fa-sticky-note"></i> Notes
                </h3>
                <p style="background: #f8f9fa; padding: 10px; border-radius: 5px;">${request.notes}</p>
                ` : ''}
              </div>
            `;

            // Add view license button if available
            if (request.drivers_license) {
              detailsHtml += `
                <div style="margin-top: 20px;">
                  <button onclick="viewDriversLicense(${request.id})" 
                    style="padding: 10px 20px; background: #007bff; color: white; border: none; 
                           border-radius: 5px; cursor: pointer; font-size: 14px;">
                    <i class="fas fa-id-card"></i> View Driver's License
                  </button>
                </div>
              `;
            }

            Swal.fire({
              title: `Test Drive Details - TD-${String(request.id).padStart(4, '0')}`,
              html: detailsHtml,
              width: '700px',
              confirmButtonText: 'Close',
              confirmButtonColor: '#6c757d'
            });
          } else {
            Swal.fire('Error!', data.message || 'Failed to fetch details', 'error');
          }
        })
        .catch(error => {
          console.error('Error:', error);
          Swal.fire('Error!', 'An error occurred while fetching details. Please try again.', 'error');
        });
    };

    window.viewDriversLicense = function(requestId) {
      // Show the license in a modal
      const licenseModal = document.getElementById('licenseModal');
      const licenseImage = document.getElementById('licenseImage');

      // Set the image source to the view_license.php endpoint
      licenseImage.src = `../test/view_license.php?id=${requestId}&t=${Date.now()}`;

      // Show the modal
      licenseModal.classList.add('show');
      document.body.style.overflow = 'hidden';

      // Close the SweetAlert first
      Swal.close();
    };

    window.closeLicenseModal = function() {
      const licenseModal = document.getElementById('licenseModal');
      licenseModal.classList.remove('show');
      document.body.style.overflow = '';
    };

    // Close license modal when clicking outside
    document.addEventListener('click', function(event) {
      const licenseModal = document.getElementById('licenseModal');
      if (event.target === licenseModal) {
        closeLicenseModal();
      }
    });

    // Inquiry Management Functions
    window.viewInquiryDetails = function(inquiryId) {
      const formData = new FormData();
      formData.append('action', 'get_inquiry_details');
      formData.append('inquiry_id', inquiryId);

      fetch('', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            const inquiry = data.data;

            let detailsHtml = `
              <div style="text-align: left; line-height: 1.8; padding: 0 20px;">
                <h3 style="color: #d60000; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px;">
                  <i class="fas fa-user"></i> Customer Information
                </h3>
                <p><strong>Name:</strong> ${inquiry.FullName}</p>
                <p><strong>Email:</strong> ${inquiry.Email}</p>
                <p><strong>Phone:</strong> ${inquiry.PhoneNumber || 'Not provided'}</p>
                ${inquiry.AccountId ? `<p><strong>Account ID:</strong> ${inquiry.AccountId}</p>` : ''}
                
                <h3 style="color: #d60000; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; margin-top: 20px;">
                  <i class="fas fa-car"></i> Vehicle Interest
                </h3>
                <p><strong>Model:</strong> ${inquiry.VehicleModel}</p>
                ${inquiry.VehicleVariant ? `<p><strong>Variant:</strong> ${inquiry.VehicleVariant}</p>` : ''}
                <p><strong>Year:</strong> ${inquiry.VehicleYear}</p>
                <p><strong>Color:</strong> ${inquiry.VehicleColor}</p>
                
                ${inquiry.FinancingRequired ? `
                <h3 style="color: #d60000; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; margin-top: 20px;">
                  <i class="fas fa-dollar-sign"></i> Financing Requirements
                </h3>
                <div style="background: #f8f9fa; padding: 10px; border-radius: 5px;">
                  ${inquiry.FinancingRequired.replace(/\n/g, '<br>')}
                </div>
                ` : ''}
                
                ${inquiry.TradeInVehicleDetails ? `
                <h3 style="color: #d60000; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; margin-top: 20px;">
                  <i class="fas fa-exchange-alt"></i> Trade-In Vehicle Details
                </h3>
                <div style="background: #fff3cd; padding: 10px; border-radius: 5px;">
                  ${inquiry.TradeInVehicleDetails.replace(/\n/g, '<br>')}
                </div>
                ` : ''}
                
                ${inquiry.Comments ? `
                <h3 style="color: #d60000; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; margin-top: 20px;">
                  <i class="fas fa-comments"></i> Additional Comments
                </h3>
                <div style="background: #e7f3ff; padding: 10px; border-radius: 5px;">
                  ${inquiry.Comments.replace(/\n/g, '<br>')}
                </div>
                ` : ''}
                
                <h3 style="color: #d60000; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; margin-top: 20px;">
                  <i class="fas fa-clock"></i> Inquiry Date
                </h3>
                <p>${new Date(inquiry.InquiryDate).toLocaleString()}</p>
              </div>
            `;

            Swal.fire({
              title: `Inquiry Details - INQ-${String(inquiry.Id).padStart(5, '0')}`,
              html: detailsHtml,
              width: '800px',
              confirmButtonText: 'Close',
              confirmButtonColor: '#6c757d',
              showDenyButton: true,
              denyButtonText: 'Respond',
              denyButtonColor: '#28a745',
              customClass: {
                popup: 'inquiry-details-modal',
                htmlContainer: 'inquiry-details-html-container'
              }
            }).then((result) => {
              if (result.isDenied) {
                respondToInquiry(inquiryId);
              }
            });
          } else {
            Swal.fire('Error!', data.message || 'Failed to fetch inquiry details', 'error');
          }
        })
        .catch(error => {
          console.error('Error:', error);
          Swal.fire('Error!', 'An error occurred while fetching details', 'error');
        });
    };

    window.respondToInquiry = function(inquiryId) {
      Swal.fire({
        title: 'Respond to Inquiry',
        html: `
          <div style="text-align: left; padding: 0 20px;">
            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
              <h4 style="margin: 0 0 10px 0; color: #333; font-size: 14px;">
                <i class="fas fa-info-circle" style="color: #17a2b8;"></i> Response Details
              </h4>
              <div style="color: #666; font-size: 12px;">
                Please select the appropriate response type and craft your message to the customer.
              </div>
            </div>

            <div style="margin-bottom: 20px;">
              <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333; font-size: 13px;">
                <i class="fas fa-tag" style="color: #d60000; margin-right: 5px;"></i>Response Type:
              </label>
              <select id="responseType" style="width: calc(100% - 2px); padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; box-sizing: border-box;">
                <option value="general">General Response</option>
                <option value="pricing">Pricing Information</option>
                <option value="financing">Financing Details</option>
                <option value="tradein">Trade-In Evaluation</option>
                <option value="schedule">Schedule Meeting</option>
              </select>
            </div>

            <div style="margin-bottom: 20px;">
              <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333; font-size: 13px;">
                <i class="fas fa-envelope" style="color: #d60000; margin-right: 5px;"></i>Response Message:
              </label>
              <textarea id="responseMessage" 
                placeholder="Dear Customer,&#10;&#10;Thank you for your inquiry about our vehicles..." 
                style="width: calc(100% - 2px); min-height: 150px; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; resize: vertical; box-sizing: border-box; font-family: inherit;"></textarea>
              <div style="text-align: right; margin-top: 5px; font-size: 11px; color: #999;">
                Be professional and courteous in your response
              </div>
            </div>

            <div style="margin-bottom: 15px;">
              <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333; font-size: 13px;">
                <i class="fas fa-tasks" style="color: #d60000; margin-right: 5px;"></i>Follow-up Action:
              </label>
              <select id="followUp" style="width: calc(100% - 2px); padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; box-sizing: border-box;">
                <option value="none">No follow-up needed</option>
                <option value="call">Schedule a call</option>
                <option value="email">Send follow-up email</option>
                <option value="meeting">Schedule showroom visit</option>
              </select>
            </div>

            <div style="background: #fff3cd; padding: 12px; border-radius: 5px; border-left: 4px solid #ffc107;">
              <div style="font-size: 12px; color: #856404;">
                <i class="fas fa-lightbulb" style="margin-right: 5px;"></i>
                <strong>Tip:</strong> Personalize your response based on the customer's specific inquiry and vehicle interest.
              </div>
            </div>
          </div>
        `,
        width: '800px',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-paper-plane"></i> Send Response',
        cancelButtonText: '<i class="fas fa-times"></i> Cancel',
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        customClass: {
          popup: 'inquiry-response-modal',
          htmlContainer: 'inquiry-modal-html-container',
          title: 'inquiry-response-title',
          confirmButton: 'inquiry-confirm-btn',
          cancelButton: 'inquiry-cancel-btn'
        },
        preConfirm: () => {
          const responseMessage = document.getElementById('responseMessage').value;

          if (!responseMessage.trim()) {
            Swal.showValidationMessage('Please enter a response message');
            return false;
          }

          if (responseMessage.trim().length < 20) {
            Swal.showValidationMessage('Response message is too short. Please provide a more detailed response.');
            return false;
          }

          return {
            type: document.getElementById('responseType').value,
            message: responseMessage,
            followUp: document.getElementById('followUp').value
          };
        }
      }).then((result) => {
        if (result.isConfirmed) {
          const formData = new FormData();
          formData.append('inquiry_id', inquiryId);
          formData.append('response_type', result.value.type);
          formData.append('response_message', result.value.message);
          formData.append('follow_up_date', result.value.followUp);

          fetch('../../pages/main/inquiry_actions.php', {
              method: 'POST',
              body: formData
            })
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                Swal.fire({
                  icon: 'success',
                  title: 'Response Sent!',
                  text: 'Your response has been sent successfully to the customer.',
                  confirmButtonColor: '#28a745'
                }).then(() => {
                  location.reload();
                });
              } else {
                Swal.fire({
                  icon: 'error',
                  title: 'Failed to Send',
                  text: data.message || 'Failed to send response. Please try again.',
                  confirmButtonColor: '#d60000'
                });
              }
            })
            .catch(error => {
              console.error('Error:', error);
              Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'An error occurred while sending the response. Please try again.',
                confirmButtonColor: '#d60000'
              });
            });
        }
      });
    };

    // Undo Test Drive Rejection Function
    window.undoTestDriveRejection = function(requestId) {
      Swal.fire({
        title: 'Undo Rejection',
        text: 'Are you sure you want to undo the rejection of this test drive request? It will be moved back to pending status.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#d60000',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, Undo Rejection',
        cancelButtonText: 'Cancel'
      }).then((result) => {
        if (result.isConfirmed) {
          const formData = new FormData();
          formData.append('action', 'undo_rejection');
          formData.append('request_id', requestId);

          fetch('', {
              method: 'POST',
              body: formData
            })
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                Swal.fire({
                  title: 'Success!',
                  text: data.message,
                  icon: 'success',
                  confirmButtonColor: '#d60000'
                }).then(() => {
                  location.reload();
                });
              } else {
                Swal.fire({
                  title: 'Error!',
                  text: data.message || 'An error occurred',
                  icon: 'error',
                  confirmButtonColor: '#d60000'
                });
              }
            })
            .catch(error => {
              console.error('Error:', error);
              Swal.fire({
                title: 'Error!',
                text: 'An error occurred while processing the request',
                icon: 'error',
                confirmButtonColor: '#d60000'
              });
            });
        }
      });
    };

  });

    // Sales Agent Payment Management Functions
    window.loadAgentPendingPayments = function() {
      fetch('../../includes/api/payment_approval_api.php?action=getPendingPayments')
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            displayAgentPendingPayments(data.data);
            updateAgentPendingStats(data.data);
          } else {
            console.error('Error loading pending payments:', data.error);
            document.getElementById('agentPendingPaymentsTable').innerHTML = 
              '<tr><td colspan="8" class="text-center">Error loading payments</td></tr>';
          }
        })
        .catch(error => {
          console.error('Error:', error);
          document.getElementById('agentPendingPaymentsTable').innerHTML = 
            '<tr><td colspan="8" class="text-center">Error loading payments</td></tr>';
        });
    };

    function displayAgentPendingPayments(payments) {
      const tbody = document.getElementById('agentPendingPaymentsTable');
      
      if (payments.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center">No pending payments</td></tr>';
        return;
      }

      tbody.innerHTML = payments.map(payment => `
        <tr>
          <td>${payment.payment_number || 'PAY-' + payment.id}</td>
          <td>${payment.customer_name}<br><small>${payment.Email || ''}</small></td>
          <td>${payment.vehicle_display || 'N/A'}</td>
          <td>₱${parseFloat(payment.amount_paid).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
          <td>${payment.payment_method}</td>
          <td>${payment.reference_number || 'N/A'}</td>
          <td>${new Date(payment.payment_date).toLocaleDateString()}</td>
          <td class="table-actions">
            <button class="btn btn-small btn-success" onclick="approveAgentPayment(${payment.id})" title="Approve Payment">
              <i class="fas fa-check"></i>
            </button>
            <button class="btn btn-small btn-danger" onclick="rejectAgentPayment(${payment.id})" title="Reject Payment">
              <i class="fas fa-times"></i>
            </button>
            <button class="btn btn-small btn-info" onclick="viewAgentPaymentDetails(${payment.id})" title="View Details">
              <i class="fas fa-eye"></i>
            </button>
          </td>
        </tr>
      `).join('');
    }

    function updateAgentPendingStats(payments) {
      const count = payments.length;
      const totalAmount = payments.reduce((sum, payment) => sum + parseFloat(payment.amount_paid), 0);
      
      document.getElementById('agentPendingCount').textContent = count;
      document.getElementById('agentPendingAmount').textContent = '₱' + totalAmount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    window.loadAgentVerifiedPayments = function() {
      fetch('../../includes/api/payment_approval_api.php?action=getVerifiedPayments')
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            displayAgentVerifiedPayments(data.data);
            updateAgentVerifiedStats(data.data);
          } else {
            console.error('Error loading verified payments:', data.error);
            document.getElementById('agentVerifiedPaymentsTable').innerHTML = 
              '<tr><td colspan="7" class="text-center">Error loading payments</td></tr>';
          }
        })
        .catch(error => {
          console.error('Error:', error);
          document.getElementById('agentVerifiedPaymentsTable').innerHTML = 
            '<tr><td colspan="7" class="text-center">Error loading payments</td></tr>';
        });
    };

    function displayAgentVerifiedPayments(payments) {
      const tbody = document.getElementById('agentVerifiedPaymentsTable');
      
      if (payments.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center">No verified payments found</td></tr>';
        return;
      }

      tbody.innerHTML = payments.map(payment => `
        <tr>
          <td>${payment.payment_number || 'PAY-' + payment.id}</td>
          <td>${payment.customer_name}<br><small>${payment.Email || ''}</small></td>
          <td>${payment.vehicle_display || 'N/A'}</td>
          <td>₱${parseFloat(payment.amount_paid).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
          <td>${new Date(payment.payment_date).toLocaleDateString()}</td>
          <td>${payment.processor_name || 'N/A'}<br><small>${payment.updated_at ? new Date(payment.updated_at).toLocaleDateString() : ''}</small></td>
          <td class="table-actions">
            <button class="btn btn-small btn-info" onclick="viewAgentPaymentDetails(${payment.id})" title="View Details">
              <i class="fas fa-eye"></i> Details
            </button>
          </td>
        </tr>
      `).join('');
    }

    function updateAgentVerifiedStats(payments) {
      const count = payments.length;
      const totalAmount = payments.reduce((sum, payment) => sum + parseFloat(payment.amount_paid), 0);
      
      document.getElementById('agentVerifiedCount').textContent = count;
      document.getElementById('agentVerifiedAmount').textContent = '₱' + totalAmount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    window.loadAgentLoanCustomers = function() {
      fetch('../../includes/api/payment_approval_api.php?action=getAllLoanCustomers')
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            displayAgentLoanCustomers(data.data);
            updateAgentLoanCustomerStats(data.data);
          } else {
            console.error('Error loading loan customers:', data.error);
            document.getElementById('agentLoanCustomersTable').innerHTML =
              '<tr><td colspan="7" class="text-center">Error loading loan customers</td></tr>';
          }
        })
        .catch(error => {
          console.error('Error:', error);
          document.getElementById('agentLoanCustomersTable').innerHTML =
            '<tr><td colspan="7" class="text-center">Error loading loan customers</td></tr>';
        });
    };

    function displayAgentLoanCustomers(customers) {
      const tbody = document.getElementById('agentLoanCustomersTable');

      if (customers.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center">No loan customers found</td></tr>';
        return;
      }

      tbody.innerHTML = customers.map(customer => {
        const statusClass = customer.payment_status === 'overdue' ? 'status rejected' : 
                           customer.payment_status === 'completed' ? 'status approved' : 
                           customer.payment_status === 'in_progress' ? 'status pending' : 
                           'status';
        
        const progressColor = customer.payment_status === 'overdue' ? '#dc3545' : 
                             customer.payment_status === 'completed' ? '#28a745' : 
                             '#ffc107';
        
        return `
          <tr>
            <td>${customer.order_number}</td>
            <td>${customer.customer_name}<br><small>${customer.Email || ''}</small></td>
            <td>${customer.vehicle_display}</td>
            <td>₱${parseFloat(customer.monthly_payment || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
            <td>
              <div style="display: flex; align-items: center; gap: 0.5rem;">
                <div style="flex: 1; background: #e0e0e0; border-radius: 0.5rem; height: 1.25rem; overflow: hidden;">
                  <div style="width: ${customer.payment_progress}%; background: ${progressColor}; height: 100%; transition: width 0.3s;"></div>
                </div>
                <span style="font-size: 0.875rem; font-weight: 600;">${customer.payment_progress}%</span>
              </div>
              <small>${customer.payments_made || 0} of ${customer.total_payments_due || 0} payments</small>
            </td>
            <td>${customer.next_due_date ? new Date(customer.next_due_date).toLocaleDateString() : 'N/A'}</td>
            <td><span class="${statusClass}">${customer.payment_status_label}</span></td>
          </tr>
        `;
      }).join('');
    }

    function updateAgentLoanCustomerStats(customers) {
      const totalCount = customers.length;
      const activeCount = customers.filter(c => c.payment_status === 'in_progress' || c.payment_status === 'pending').length;
      const overdueCount = customers.filter(c => c.payment_status === 'overdue').length;
      
      document.getElementById('agentLoanCustomersCount').textContent = totalCount;
      document.getElementById('agentActiveLoansCount').textContent = activeCount;
      document.getElementById('agentOverdueCount').textContent = overdueCount;
    }

    // Shared payment action functions for sales agents
    window.approveAgentPayment = function(paymentId) {
      Swal.fire({
        title: 'Approve Payment?',
        text: 'Are you sure you want to approve this payment?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#dc143c',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, approve it!'
      }).then((result) => {
        if (result.isConfirmed) {
          const formData = new FormData();
          formData.append('action', 'approvePayment');
          formData.append('payment_id', paymentId);
          
          fetch('../../includes/api/payment_approval_api.php', {
            method: 'POST',
            body: formData
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              Swal.fire({
                title: 'Approved!',
                text: 'Payment has been approved successfully.',
                icon: 'success',
                confirmButtonColor: '#dc143c'
              });
              loadAgentPendingPayments();
              loadAgentVerifiedPayments();
              loadAgentLoanCustomers();
            } else {
              Swal.fire({
                title: 'Error!',
                text: data.error || 'Failed to approve payment',
                icon: 'error',
                confirmButtonColor: '#dc143c'
              });
            }
          })
          .catch(error => {
            console.error('Error:', error);
            Swal.fire({
              title: 'Error!',
              text: 'Network error occurred',
              icon: 'error',
              confirmButtonColor: '#dc143c'
            });
          });
        }
      });
    };

    window.rejectAgentPayment = function(paymentId) {
      Swal.fire({
        title: 'Reject Payment?',
        text: 'Please provide a reason for rejection:',
        input: 'textarea',
        inputPlaceholder: 'Enter rejection reason...',
        inputValidator: (value) => {
          if (!value) {
            return 'You need to provide a reason for rejection!';
          }
        },
        showCancelButton: true,
        confirmButtonColor: '#dc143c',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Reject Payment'
      }).then((result) => {
        if (result.isConfirmed) {
          const formData = new FormData();
          formData.append('action', 'rejectPayment');
          formData.append('payment_id', paymentId);
          formData.append('rejection_reason', result.value);
          
          fetch('../../includes/api/payment_approval_api.php', {
            method: 'POST',
            body: formData
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              Swal.fire({
                title: 'Rejected!',
                text: 'Payment has been rejected.',
                icon: 'success',
                confirmButtonColor: '#dc143c'
              });
              loadAgentPendingPayments();
            } else {
              Swal.fire({
                title: 'Error!',
                text: data.error || 'Failed to reject payment',
                icon: 'error',
                confirmButtonColor: '#dc143c'
              });
            }
          })
          .catch(error => {
            console.error('Error:', error);
            Swal.fire({
              title: 'Error!',
              text: 'Network error occurred',
              icon: 'error',
              confirmButtonColor: '#dc143c'
            });
          });
        }
      });
    };

    window.viewAgentPaymentDetails = function(paymentId) {
      fetch(`../../includes/api/payment_approval_api.php?action=getPaymentDetails&payment_id=${paymentId}`)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            const payment = data.data;
            
            let receiptSection = '';
            if (payment.has_receipt && payment.receipt_filename) {
              const receiptUrl = payment.receipt_url;
              const isImage = /\.(jpg|jpeg|png|gif)$/i.test(payment.receipt_filename);
              
              if (isImage) {
                receiptSection = `
                  <p><strong>Receipt:</strong></p>
                  <div style="margin: 0.625rem 0; text-align: center;">
                    <img src="${receiptUrl}" alt="Payment Receipt" style="max-width: 100%; max-height: 12.5rem; border: 0.0625rem solid #ddd; border-radius: 0.25rem; cursor: pointer;" onclick="window.open('${receiptUrl}', '_blank')" />
                    <br><small style="color: #666;">Click to view full size</small>
                  </div>
                `;
              } else {
                receiptSection = `
                  <p><strong>Receipt:</strong> 
                    <a href="${receiptUrl}" target="_blank" style="color: #dc143c; text-decoration: none;">
                      📄 ${payment.receipt_filename}
                    </a>
                  </p>
                `;
              }
            } else {
              receiptSection = '<p><strong>Receipt:</strong> <span style="color: #999;">No receipt uploaded</span></p>';
            }
            
            Swal.fire({
              title: 'Payment Details',
              html: `
                <div style="text-align: left; max-height: 31.25rem; overflow-y: auto;">
                  <p><strong>Payment Number:</strong> ${payment.payment_number}</p>
                  <p><strong>Customer:</strong> ${payment.customer_name}</p>
                  <p><strong>Mobile:</strong> ${payment.customer_mobile}</p>
                  <p><strong>Email:</strong> ${payment.customer_email}</p>
                  <p><strong>Vehicle:</strong> ${payment.vehicle_model} ${payment.vehicle_variant}</p>
                  <p><strong>Amount:</strong> ₱${parseFloat(payment.amount_paid).toLocaleString()}</p>
                  <p><strong>Payment Method:</strong> ${payment.payment_method}</p>
                  <p><strong>Payment Type:</strong> ${payment.payment_type}</p>
                  <p><strong>Reference Number:</strong> ${payment.reference_number || 'N/A'}</p>
                  <p><strong>Bank:</strong> ${payment.bank_name || 'N/A'}</p>
                  ${receiptSection}
                  <p><strong>Date Submitted:</strong> ${new Date(payment.payment_date).toLocaleString()}</p>
                  <p><strong>Status:</strong> <span style="padding: 0.125rem 0.5rem; border-radius: 0.25rem; background: ${payment.status === 'Pending' ? '#ffc107' : payment.status === 'Confirmed' ? '#28a745' : '#dc3545'}; color: white; font-size: 0.75rem;">${payment.status}</span></p>
                  <p><strong>Notes:</strong> ${payment.notes || 'None'}</p>
                  ${payment.processor_name ? `<p><strong>Processed By:</strong> ${payment.processor_name}</p>` : ''}
                </div>
              `,
              width: '43.75rem',
              confirmButtonColor: '#dc143c'
            });
          } else {
            Swal.fire({
              title: 'Error!',
              text: 'Failed to load payment details',
              icon: 'error',
              confirmButtonColor: '#dc143c'
            });
          }
        })
        .catch(error => {
          console.error('Error:', error);
          Swal.fire({
            title: 'Error!',
            text: 'Network error occurred',
            icon: 'error',
            confirmButtonColor: '#dc143c'
          });
        });
    };

    window.viewAgentLoanDetails = function(orderId) {
      Swal.fire({
        title: 'Loan Details',
        text: 'Loading loan details...',
        icon: 'info',
        confirmButtonColor: '#dc143c'
      });
      // TODO: Implement full loan details view with payment schedule
    };

    // Amortization Management Functions
    window.loadUpcomingPayments = function() {
      fetch('../../includes/api/amortization_api.php?action=getUpcomingPayments')
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            displayUpcomingPayments(data.data);
            updateUpcomingStats(data.stats);
          } else {
            console.error('Error loading upcoming payments:', data.error);
            document.getElementById('amortUpcomingTable').innerHTML = 
              '<tr><td colspan="8" class="text-center">Error loading payments</td></tr>';
          }
        })
        .catch(error => {
          console.error('Error:', error);
          document.getElementById('amortUpcomingTable').innerHTML = 
            '<tr><td colspan="8" class="text-center">Error loading payments</td></tr>';
        });
    };

    function displayUpcomingPayments(payments) {
      const tbody = document.getElementById('amortUpcomingTable');
      
      if (payments.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center">No upcoming payments in the next 30 days</td></tr>';
        return;
      }

      tbody.innerHTML = payments.map(payment => {
        const daysUntilDue = parseInt(payment.days_until_due);
        let statusClass = 'status approved';
        let statusText = 'Upcoming';
        
        if (daysUntilDue <= 3) {
          statusClass = 'status urgent';
          statusText = 'Due Soon';
        } else if (payment.status === 'Partial') {
          statusClass = 'status pending';
          statusText = 'Partial';
        }
        
        return `
          <tr>
            <td>${payment.order_number}</td>
            <td>${payment.customer_name}<br><small>${payment.customer_email || ''}</small></td>
            <td>${payment.vehicle_model} ${payment.vehicle_variant || ''}</td>
            <td>${payment.payment_number} of ${payment.total_payments}</td>
            <td>₱${parseFloat(payment.amount_due).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
            <td>${new Date(payment.due_date).toLocaleDateString('en-US', {month: 'short', day: 'numeric', year: 'numeric'})}</td>
            <td><span class="${statusClass}">${statusText}</span></td>
            <td class="table-actions">
              <button class="btn btn-small btn-success" onclick="confirmAmortPayment(${payment.schedule_id}, '${payment.order_number}', '${payment.customer_name}', ${payment.amount_due}, '${payment.due_date}', ${payment.payment_number}, ${payment.total_payments})" title="Confirm Payment">
                <i class="fas fa-check"></i> Confirm
              </button>
            </td>
          </tr>
        `;
      }).join('');
    }

    function updateUpcomingStats(stats) {
      document.getElementById('amortUpcomingCount').textContent = stats.total_upcoming;
      document.getElementById('amortUpcomingAmount').textContent = '₱' + parseFloat(stats.total_amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
      document.getElementById('amortDueThisWeek').textContent = stats.due_this_week;
    }

    window.loadOverduePayments = function() {
      fetch('../../includes/api/amortization_api.php?action=getOverduePayments')
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            displayOverduePayments(data.data);
            updateOverdueStats(data.stats);
          } else {
            console.error('Error loading overdue payments:', data.error);
            document.getElementById('amortOverdueTable').innerHTML = 
              '<tr><td colspan="8" class="text-center">Error loading payments</td></tr>';
          }
        })
        .catch(error => {
          console.error('Error:', error);
          document.getElementById('amortOverdueTable').innerHTML = 
            '<tr><td colspan="8" class="text-center">Error loading payments</td></tr>';
        });
    };

    function displayOverduePayments(payments) {
      const tbody = document.getElementById('amortOverdueTable');
      
      if (payments.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center">No overdue payments</td></tr>';
        return;
      }

      tbody.innerHTML = payments.map(payment => {
        const daysOverdue = parseInt(payment.days_overdue);
        const statusClass = daysOverdue > 30 ? 'status rejected' : 'status overdue';
        
        return `
          <tr>
            <td>${payment.order_number}</td>
            <td>${payment.customer_name}<br><small>${payment.customer_email || ''}</small></td>
            <td>${payment.vehicle_model} ${payment.vehicle_variant || ''}</td>
            <td>${payment.payment_number} of ${payment.total_payments}</td>
            <td>₱${parseFloat(payment.amount_due).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
            <td>${new Date(payment.due_date).toLocaleDateString('en-US', {month: 'short', day: 'numeric', year: 'numeric'})}</td>
            <td><span class="${statusClass}">${daysOverdue} days</span></td>
            <td class="table-actions">
              <button class="btn btn-small btn-success" onclick="confirmAmortPayment(${payment.schedule_id}, '${payment.order_number}', '${payment.customer_name}', ${payment.amount_due}, '${payment.due_date}', ${payment.payment_number}, ${payment.total_payments})" title="Confirm Payment">
                <i class="fas fa-check"></i> Confirm
              </button>
            </td>
          </tr>
        `;
      }).join('');
    }

    function updateOverdueStats(stats) {
      document.getElementById('amortOverdueCount').textContent = stats.total_overdue;
      document.getElementById('amortOverdueAmount').textContent = '₱' + parseFloat(stats.total_amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
      document.getElementById('amortCriticalCount').textContent = stats.critical;
    }

    window.loadRecentConfirmations = function() {
      fetch('../../includes/api/amortization_api.php?action=getRecentConfirmations')
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            displayRecentConfirmations(data.data);
            updateRecentStats(data.stats);
          } else {
            console.error('Error loading recent confirmations:', data.error);
            document.getElementById('amortRecentTable').innerHTML = 
              '<tr><td colspan="7" class="text-center">Error loading confirmations</td></tr>';
          }
        })
        .catch(error => {
          console.error('Error:', error);
          document.getElementById('amortRecentTable').innerHTML = 
            '<tr><td colspan="7" class="text-center">Error loading confirmations</td></tr>';
        });
    };

    function displayRecentConfirmations(confirmations) {
      const tbody = document.getElementById('amortRecentTable');
      
      if (confirmations.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center">No recent confirmations in the last 30 days</td></tr>';
        return;
      }

      tbody.innerHTML = confirmations.map(conf => `
        <tr>
          <td>${conf.payment_ref}</td>
          <td>${conf.customer_name}<br><small>${conf.customer_email || ''}</small></td>
          <td>${conf.vehicle_model} ${conf.vehicle_variant || ''}</td>
          <td>₱${parseFloat(conf.amount_paid).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
          <td>${new Date(conf.payment_date).toLocaleDateString('en-US', {month: 'short', day: 'numeric', year: 'numeric'})}</td>
          <td>${conf.processed_by_name || 'N/A'}</td>
          <td><span class="status approved">${conf.status}</span></td>
        </tr>
      `).join('');
    }

    function updateRecentStats(stats) {
      document.getElementById('amortTodayCount').textContent = stats.today_confirmations;
      document.getElementById('amortWeekCount').textContent = stats.week_confirmations;
      document.getElementById('amortRecentAmount').textContent = '₱' + parseFloat(stats.total_amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    window.confirmAmortPayment = function(scheduleId, orderNumber, customerName, amount, dueDate, paymentNum, totalPayments) {
      Swal.fire({
        title: 'Confirm Payment',
        html: `
          <div style="text-align: left; padding: 0 1.25rem;">
            <p style="margin-bottom: 0.75rem;"><strong>Order:</strong> ${orderNumber}</p>
            <p style="margin-bottom: 0.75rem;"><strong>Customer:</strong> ${customerName}</p>
            <p style="margin-bottom: 0.75rem;"><strong>Payment:</strong> ${paymentNum} of ${totalPayments}</p>
            <p style="margin-bottom: 0.75rem;"><strong>Amount:</strong> ₱${parseFloat(amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</p>
            <p style="margin-bottom: 0.75rem;"><strong>Due Date:</strong> ${new Date(dueDate).toLocaleDateString('en-US', {month: 'long', day: 'numeric', year: 'numeric'})}</p>
            <div style="background: #fff3cd; padding: 0.75rem; border-radius: 0.25rem; margin-top: 1rem; border-left: 0.25rem solid #ffc107;">
              <p style="margin: 0; font-size: 0.875rem; color: #856404;">
                <i class="fas fa-info-circle"></i> This will mark the payment as received and record it in the payment history.
              </p>
            </div>
          </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-check"></i> Confirm Payment',
        cancelButtonText: 'Cancel'
      }).then((result) => {
        if (result.isConfirmed) {
          const formData = new FormData();
          formData.append('action', 'confirmPayment');
          formData.append('schedule_id', scheduleId);
          
          fetch('../../includes/api/amortization_api.php', {
            method: 'POST',
            body: formData
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              Swal.fire({
                title: 'Success!',
                text: 'Payment confirmed successfully',
                icon: 'success',
                confirmButtonColor: '#28a745'
              }).then(() => {
                // Reload all payment data
                loadUpcomingPayments();
                loadOverduePayments();
                loadRecentConfirmations();
              });
            } else {
              Swal.fire({
                title: 'Error!',
                text: data.error || 'Failed to confirm payment',
                icon: 'error',
                confirmButtonColor: '#dc3545'
              });
            }
          })
          .catch(error => {
            console.error('Error:', error);
            Swal.fire({
              title: 'Error!',
              text: 'Network error occurred',
              icon: 'error',
              confirmButtonColor: '#dc3545'
            });
          });
        }
      });
    };

</script>

<style>
  .detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
  }

  .detail-item {
    display: flex;
    flex-direction: column;
  }

  .detail-item.full-width {
    grid-column: 1 / -1;
  }

  .detail-item label {
    font-weight: 600;
    color: #555;
    margin-bottom: 5px;
  }

  .detail-item span {
    color: #333;
    padding: 8px 0;
  }

  .table-container {
    overflow-x: auto;
  }

  .status.urgent {
    background-color: #ff6b6b;
    color: white;
  }

  .status.high {
    background-color: #ffa726;
    color: white;
  }

  .status.overdue {
    background-color: #d32f2f;
    color: white;
  }

  .status-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    margin-top: 0.25rem;
  }

  .status-badge.no-show {
    background-color: #ffc107;
    color: #000;
  }

  .status-badge.cancelled {
    background-color: #dc3545;
    color: white;
  }

  .status.normal {
    background-color: #66bb6a;
    color: white;
  }

  .text-center {
    text-align: center;
  }

  /* Remove modal styles since we're using SweetAlert */
  /* Modal styles */
  /* .modal {
  position: fixed;
  z-index: 1000;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0,0,0,0.5);
}

.modal-content {
  background-color: #fefefe;
  margin: 5% auto;
  padding: 0;
  border: none;
  border-radius: 8px;
  width: 80%;
  max-width: 600px;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
} */

  /* Custom SweetAlert styles for test drive modals */
  .test-drive-modal-container .swal2-popup {
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
  }

  .test-drive-modal-title {
    font-size: 1.4em;
    font-weight: 600;
    margin-bottom: 20px;
    color: #333;
  }

  .test-drive-modal-content {
    background: #23272f;
    color: #fff;
    border-radius: 14px;
    padding: 24px 28px;
    font-size: 1.05em;
    box-shadow: 0 10px 40px rgba(0,0,0,0.18);
    margin-bottom: 0;
  }
  .test-drive-modal-section {
    color: #ffd700;
    font-size: 1.15em;
    font-weight: 600;
    margin: 18px 0 10px 0;
    border-bottom: 1.5px solid #444;
    padding-bottom: 6px;
    letter-spacing: 0.5px;
  }
  .test-drive-modal-row {
    display: flex;
    align-items: center;
    margin-bottom: 7px;
    gap: 10px;
  }
  .test-drive-modal-label {
    min-width: 120px;
    color: #ffd700;
    font-weight: 500;
    font-size: 1em;
  }
  .test-drive-modal-notes {
    background: #2d313a;
    border-left: 4px solid #ffd700;
    padding: 10px 14px;
    border-radius: 6px;
    margin-bottom: 10px;
    color: #fff;
    font-size: 0.98em;
  }

  .license-modal {
    display: none;
    position: fixed;
    z-index: 100000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.8);
    animation: fadeIn 0.3s;
  }

  .license-modal.show {
    display: flex;
    justify-content: center;
    align-items: center;
  }

  .license-modal-content {
    background-color: #fff;
    border-radius: 8px;
    max-width: 90%;
    max-height: 90vh;
    overflow: auto;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
    position: relative;
  }

  .license-modal-close {
    position: absolute;
    top: 10px;
    right: 15px;
    background: rgba(255, 255, 255, 0.9);
    border: none;
    color: #333;
    font-size: 1.5em;
    cursor: pointer;
    width: 35px;
    height: 35px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.3s;
    z-index: 1;
  }

  .license-modal-close:hover {
    background: #f0f0f0;
  }

  .license-image {
    display: block;
    max-width: 100%;
    height: auto;
  }

  @keyframes fadeIn {
    from {
      opacity: 0;
    }

    to {
      opacity: 1;
    }
  }

  /* Inquiry-specific styles */
  .badge {
    display: inline-block;
    padding: 3px 8px;
    font-size: 11px;
    font-weight: bold;
    border-radius: 3px;
    text-transform: uppercase;
  }

  .badge-info {
    background: #17a2b8;
    color: white;
  }

  .badge-warning {
    background: #ffc107;
    color: #333;
  }

  .badge-success {
    background: #28a745;
    color: white;
  }

  .text-muted {
    color: #6c757d;
    font-style: italic;
  }

  /* Custom styles for inquiry response modal */
  .inquiry-response-modal {
    border-radius: 12px !important;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
  }

  .inquiry-modal-html-container {
    overflow-x: hidden !important;
    overflow-y: auto !important;
    max-height: 70vh !important;
    padding: 0 !important;
    margin: 0 !important;
  }

  /* Custom styles for inquiry details modal */
  .inquiry-details-modal {
    border-radius: 12px !important;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
  }

  .inquiry-details-html-container {
    overflow-x: hidden !important;
    overflow-y: auto !important;
    max-height: 70vh !important;
    padding: 0 !important;
    margin: 0 !important;
  }
</style>