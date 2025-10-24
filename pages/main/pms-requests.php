<?php
include_once(dirname(dirname(__DIR__)) . '/includes/init.php');

// Check if user is Sales Agent
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'SalesAgent') {
  header("Location: ../../pages/login.php");
  exit();
}

// Get sales agent ID
$sales_agent_id = $_SESSION['user_id'];

// Use the database connection from init.php
$pdo = $GLOBALS['pdo'] ?? null;

if (!$pdo) {
  die("Database connection not available. Please check your database configuration.");
}

// Include notification API
require_once(dirname(dirname(__DIR__)) . '/includes/api/notification_api.php');

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'approve':
                $pms_id = (int)$_POST['pms_id'];

                // Update PMS request status
                $stmt = $pdo->prepare("
                    UPDATE car_pms_records p
                    INNER JOIN customer_information ci ON p.customer_id = ci.account_id
                    SET p.request_status = 'Approved', p.approved_by = ?, p.approved_at = NOW()
                    WHERE p.pms_id = ? AND ci.agent_id = ?
                ");
                $stmt->execute([$_SESSION['user_id'], $pms_id, $sales_agent_id]);

                // Get customer_id and request details for notification
                $stmt = $pdo->prepare("
                    SELECT p.customer_id, CONCAT('PMS-', YEAR(p.created_at), '-', LPAD(p.pms_id, 3, '0')) as request_id
                    FROM car_pms_records p
                    WHERE p.pms_id = ?
                ");
                $stmt->execute([$pms_id]);
                $pms_data = $stmt->fetch(PDO::FETCH_ASSOC);

                // Create notification for customer
                if ($pms_data && $pms_data['customer_id']) {
                    try {
                        createNotification(
                            $pms_data['customer_id'],
                            null,
                            'PMS Request Approved',
                            'Your PMS request ' . $pms_data['request_id'] . ' has been approved.',
                            'pms',
                            $pms_id
                        );
                    } catch (Exception $e) {
                        // Log error but don't fail the approval
                        error_log("Failed to create PMS approval notification: " . $e->getMessage());
                    }
                }

                echo json_encode(['success' => true, 'message' => 'PMS request approved successfully']);
                break;
                
            case 'reject':
                $pms_id = (int)$_POST['pms_id'];
                $reason = $_POST['rejection_reason'] ?? '';

                // Update PMS request status
                $stmt = $pdo->prepare("
                    UPDATE car_pms_records p
                    INNER JOIN customer_information ci ON p.customer_id = ci.account_id
                    SET p.request_status = 'Rejected', p.approved_by = ?, p.approved_at = NOW(), p.rejection_reason = ?
                    WHERE p.pms_id = ? AND ci.agent_id = ?
                ");
                $stmt->execute([$_SESSION['user_id'], $reason, $pms_id, $sales_agent_id]);

                // Get customer_id and request details for notification
                $stmt = $pdo->prepare("
                    SELECT p.customer_id, CONCAT('PMS-', YEAR(p.created_at), '-', LPAD(p.pms_id, 3, '0')) as request_id
                    FROM car_pms_records p
                    WHERE p.pms_id = ?
                ");
                $stmt->execute([$pms_id]);
                $pms_data = $stmt->fetch(PDO::FETCH_ASSOC);

                // Create notification for customer
                if ($pms_data && $pms_data['customer_id']) {
                    try {
                        $notification_message = 'Your PMS request ' . $pms_data['request_id'] . ' has been rejected.';
                        if (!empty($reason)) {
                            $notification_message .= ' Reason: ' . $reason;
                        }

                        createNotification(
                            $pms_data['customer_id'],
                            null,
                            'PMS Request Rejected',
                            $notification_message,
                            'pms',
                            $pms_id
                        );
                    } catch (Exception $e) {
                        // Log error but don't fail the rejection
                        error_log("Failed to create PMS rejection notification: " . $e->getMessage());
                    }
                }

                echo json_encode(['success' => true, 'message' => 'PMS request rejected']);
                break;
                
            case 'reschedule':
                $pms_id = (int)$_POST['pms_id'];
                $new_date = $_POST['new_date'];
                $new_time = $_POST['new_time'];
                $reason = $_POST['reschedule_reason'] ?? '';

                $scheduled_datetime = $new_date . ' ' . $new_time . ':00';

                // Update PMS request status
                $stmt = $pdo->prepare("
                    UPDATE car_pms_records p
                    INNER JOIN customer_information ci ON p.customer_id = ci.account_id
                    SET p.request_status = 'Scheduled', p.scheduled_date = ?, p.approved_by = ?, p.approved_at = NOW(),
                        p.service_notes_findings = CONCAT(COALESCE(p.service_notes_findings, ''), '\nRescheduled: ', ?)
                    WHERE p.pms_id = ? AND ci.agent_id = ?
                ");
                $stmt->execute([$scheduled_datetime, $_SESSION['user_id'], $reason, $pms_id, $sales_agent_id]);

                // Get customer_id and request details for notification
                $stmt = $pdo->prepare("
                    SELECT p.customer_id, CONCAT('PMS-', YEAR(p.created_at), '-', LPAD(p.pms_id, 3, '0')) as request_id
                    FROM car_pms_records p
                    WHERE p.pms_id = ?
                ");
                $stmt->execute([$pms_id]);
                $pms_data = $stmt->fetch(PDO::FETCH_ASSOC);

                // Create notification for customer
                if ($pms_data && $pms_data['customer_id']) {
                    try {
                        // Format the date and time for display
                        $formatted_date = date('F j, Y', strtotime($new_date));
                        $formatted_time = date('g:i A', strtotime($new_time));

                        $notification_message = 'Your PMS request ' . $pms_data['request_id'] . ' has been rescheduled to ' . $formatted_date . ' at ' . $formatted_time . '.';
                        if (!empty($reason)) {
                            $notification_message .= ' Reason: ' . $reason;
                        }

                        createNotification(
                            $pms_data['customer_id'],
                            null,
                            'PMS Request Rescheduled',
                            $notification_message,
                            'pms',
                            $pms_id
                        );
                    } catch (Exception $e) {
                        // Log error but don't fail the reschedule
                        error_log("Failed to create PMS reschedule notification: " . $e->getMessage());
                    }
                }

                echo json_encode(['success' => true, 'message' => 'PMS request rescheduled successfully']);
                break;

            case 'mark_completed':
                $pms_id = (int)$_POST['pms_id'];
                $completion_date = $_POST['completion_date'] ?? '';
                $service_notes = $_POST['service_notes'] ?? '';
                $next_pms_due = $_POST['next_pms_due'] ?? '';

                if (!$completion_date || !$service_notes) {
                    echo json_encode(['success' => false, 'message' => 'Completion date and service notes are required']);
                    break;
                }

                // Update PMS request status to Completed
                $updateFields = [
                    'p.request_status = ?',
                    'p.pms_date = ?',
                    'p.service_notes_findings = ?',
                    'p.approved_by = ?',
                    'p.approved_at = NOW()'
                ];
                $params = ['Completed', $completion_date, $service_notes, $_SESSION['user_id']];

                if (!empty($next_pms_due)) {
                    $updateFields[] = 'p.next_pms_due = ?';
                    $params[] = $next_pms_due;
                }

                $updateFields[] = 'p.pms_id = ?';
                $params[] = $pms_id;
                $updateFields[] = 'ci.agent_id = ?';
                $params[] = $sales_agent_id;

                $stmt = $pdo->prepare("
                    UPDATE car_pms_records p
                    INNER JOIN customer_information ci ON p.customer_id = ci.account_id
                    SET " . implode(', ', array_slice($updateFields, 0, -2)) . "
                    WHERE " . implode(' AND ', array_slice($updateFields, -2))
                );
                $stmt->execute($params);

                // Get customer_id and request details for notification
                $stmt = $pdo->prepare("
                    SELECT p.customer_id, CONCAT('PMS-', YEAR(p.created_at), '-', LPAD(p.pms_id, 3, '0')) as request_id
                    FROM car_pms_records p
                    WHERE p.pms_id = ?
                ");
                $stmt->execute([$pms_id]);
                $pms_data = $stmt->fetch(PDO::FETCH_ASSOC);

                // Create notification for customer
                if ($pms_data && $pms_data['customer_id']) {
                    try {
                        $formatted_date = date('F j, Y', strtotime($completion_date));
                        $notification_message = 'Your PMS request ' . $pms_data['request_id'] . ' has been completed on ' . $formatted_date . '.';

                        if (!empty($next_pms_due)) {
                            $notification_message .= ' Next PMS due: ' . $next_pms_due;
                        }

                        createNotification(
                            $pms_data['customer_id'],
                            null,
                            'PMS Request Completed',
                            $notification_message,
                            'pms',
                            $pms_id
                        );
                    } catch (Exception $e) {
                        // Log error but don't fail the completion
                        error_log("Failed to create PMS completion notification: " . $e->getMessage());
                    }
                }

                echo json_encode(['success' => true, 'message' => 'PMS request marked as completed successfully']);
                break;

            case 'view_details':
                $pms_id = (int)$_POST['pms_id'];
                $stmt = $pdo->prepare("
                    SELECT 
                        p.*,
                        a.FirstName,
                        a.LastName,
                        a.Email,
                        ci.mobile_number,
                        ci.firstname as ci_firstname,
                        ci.lastname as ci_lastname,
                        ci.middlename,
                        ci.birthday,
                        ci.age,
                        ci.gender,
                        ci.civil_status,
                        ci.employment_status,
                        ci.company_name,
                        ci.position,
                        ci.monthly_income,
                        CONCAT('PMS-', YEAR(p.created_at), '-', LPAD(p.pms_id, 3, '0')) as request_id
                    FROM car_pms_records p
                    LEFT JOIN accounts a ON p.customer_id = a.Id
                    LEFT JOIN customer_information ci ON p.customer_id = ci.account_id
                    WHERE p.pms_id = ? AND ci.agent_id = ?
                ");
                $stmt->execute([$pms_id, $sales_agent_id]);
                $details = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($details) {
                    // Format the data for display
                    $services = [];
                    if ($details['service_oil_change']) $services[] = 'Oil Change';
                    if ($details['service_oil_filter_replacement']) $services[] = 'Oil Filter Replacement';
                    if ($details['service_air_filter_replacement']) $services[] = 'Air Filter Replacement';
                    if ($details['service_tire_rotation']) $services[] = 'Tire Rotation';
                    if ($details['service_fluid_top_up']) $services[] = 'Fluid Top-Up';
                    if ($details['service_spark_plug_check']) $services[] = 'Spark Plug Check';
                    if (!empty($details['service_others'])) $services[] = $details['service_others'];
                    
                    $details['services_performed'] = $services;
                    $details['has_receipt'] = !empty($details['uploaded_receipt']);
                    
                    // Remove the actual blob data to avoid large response
                    unset($details['uploaded_receipt']);
                    
                    echo json_encode(['success' => true, 'data' => $details]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'PMS request not found']);
                }
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// Handle receipt download
if (isset($_GET['download_receipt']) && isset($_GET['pms_id'])) {
    $pms_id = (int)$_GET['pms_id'];
    $stmt = $pdo->prepare("
        SELECT p.uploaded_receipt 
        FROM car_pms_records p
        LEFT JOIN customer_information ci ON p.customer_id = ci.account_id
        WHERE p.pms_id = ? AND ci.agent_id = ?
    ");
    $stmt->execute([$pms_id, $sales_agent_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result && !empty($result['uploaded_receipt'])) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime_type = $finfo->buffer($result['uploaded_receipt']);
        
        header('Content-Type: ' . $mime_type);
        header('Content-Disposition: inline; filename="receipt_' . $pms_id . '"');
        echo $result['uploaded_receipt'];
        exit();
    } else {
        header('HTTP/1.0 404 Not Found');
        echo 'Receipt not found';
        exit();
    }
}

// Auto-update No Show status for scheduled PMS that have passed their scheduled date
$auto_noshow_query = "
    UPDATE car_pms_records p
    INNER JOIN customer_information ci ON p.customer_id = ci.account_id
    SET p.request_status = 'No Show'
    WHERE p.request_status = 'Scheduled'
    AND p.scheduled_date < NOW()
    AND ci.agent_id = :sales_agent_id
";
$stmt = $pdo->prepare($auto_noshow_query);
$stmt->bindParam(':sales_agent_id', $sales_agent_id, PDO::PARAM_INT);
$stmt->execute();

// Fetch statistics
$stats_query = "
    SELECT
        COUNT(CASE WHEN p.request_status = 'Pending' THEN 1 END) as pending,
        COUNT(CASE WHEN p.request_status = 'Approved' AND DATE(p.approved_at) = CURDATE() THEN 1 END) as approved_today,
        COUNT(CASE WHEN p.request_status = 'Rejected' THEN 1 END) as rejected,
        COUNT(CASE WHEN p.request_status = 'Scheduled' THEN 1 END) as scheduled,
        COUNT(CASE WHEN p.request_status = 'No Show' THEN 1 END) as no_show
    FROM car_pms_records p
    LEFT JOIN customer_information ci ON p.customer_id = ci.account_id
    WHERE ci.agent_id = :sales_agent_id
";
$stmt = $pdo->prepare($stats_query);
$stmt->bindParam(':sales_agent_id', $sales_agent_id, PDO::PARAM_INT);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch PMS requests with customer information
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? 'all';
$service_filter = $_GET['service'] ?? 'all';
$date_filter = $_GET['date'] ?? 'all';
$odometer_filter = $_GET['odometer'] ?? '';

$where_conditions = ["ci.agent_id = ?"];
$params = [$sales_agent_id];

if (!empty($search)) {
    $where_conditions[] = "(p.plate_number LIKE ? OR a.FirstName LIKE ? OR a.LastName LIKE ? OR CONCAT('PMS-', YEAR(p.created_at), '-', LPAD(p.pms_id, 3, '0')) LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if ($status_filter !== 'all') {
    $where_conditions[] = "p.request_status = ?";
    $params[] = ucfirst($status_filter);
}

if ($service_filter !== 'all') {
    $where_conditions[] = "p.pms_info LIKE ?";
    $params[] = "%$service_filter%";
}

// Odometer range filter (e.g., 20000 filters 20000-25000)
if (!empty($odometer_filter)) {
    $odometer_value = preg_replace('/\D/', '', $odometer_filter);
    $odometer_value = intval($odometer_value);
    if ($odometer_value > 0) {
        $where_conditions[] = "p.current_odometer >= ? AND p.current_odometer <= ?";
        $params[] = $odometer_value;
        $params[] = $odometer_value + 5000;
    }
}

switch ($date_filter) {
    case 'today':
        $where_conditions[] = "DATE(p.created_at) = CURDATE()";
        break;
    case 'week':
        $where_conditions[] = "WEEK(p.created_at) = WEEK(NOW()) AND YEAR(p.created_at) = YEAR(NOW())";
        break;
    case 'month':
        $where_conditions[] = "MONTH(p.created_at) = MONTH(NOW()) AND YEAR(p.created_at) = YEAR(NOW())";
        break;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$query = "
    SELECT 
        p.*,
        a.FirstName,
        a.LastName,
        ci.mobile_number,
        CONCAT('PMS-', YEAR(p.created_at), '-', LPAD(p.pms_id, 3, '0')) as request_id
    FROM car_pms_records p
    LEFT JOIN accounts a ON p.customer_id = a.Id
    LEFT JOIN customer_information ci ON p.customer_id = ci.account_id
    $where_clause
    ORDER BY p.created_at DESC
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$pms_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>PMS Request Approval - Sales Agent</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link href="../../includes/css/common-styles.css" rel="stylesheet">
  <link href="../../includes/css/orders-styles.css" rel="stylesheet">
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
      <div class="page-header">
        <h1 class="page-title">
          <i class="fas fa-tools icon-gradient"></i>
          PMS Request Approval
        </h1>
      </div>

      <!-- Sales Agent Statistics -->
      <div class="sales-agent-stats">
        <div class="stat-card">
          <div class="stat-icon orange">
            <i class="fas fa-clock"></i>
          </div>
          <div class="stat-info">
            <h3><?php echo $stats['pending']; ?></h3>
            <p>Pending Requests</p>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon green">
            <i class="fas fa-check-circle"></i>
          </div>
          <div class="stat-info">
            <h3><?php echo $stats['approved_today']; ?></h3>
            <p>Approved Today</p>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon red">
            <i class="fas fa-times-circle"></i>
          </div>
          <div class="stat-info">
            <h3><?php echo $stats['rejected']; ?></h3>
            <p>Rejected</p>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon blue">
            <i class="fas fa-calendar-check"></i>
          </div>
          <div class="stat-info">
            <h3><?php echo $stats['scheduled']; ?></h3>
            <p>Scheduled</p>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon purple">
            <i class="fas fa-user-slash"></i>
          </div>
          <div class="stat-info">
            <h3><?php echo $stats['no_show']; ?></h3>
            <p>No Show</p>
          </div>
        </div>
      </div>

      <div class="filters-section">
        <form method="GET" class="filter-row" id="filterForm">
          <div class="filter-group">
            <label for="search">Search Requests</label>
            <input type="text" id="search" name="search" class="filter-input"
                   value="<?php echo htmlspecialchars($search); ?>"
                   placeholder="Request ID or Customer name">
          </div>
          <div class="filter-group" style="position: relative;">
            <label for="odometer">Odometer (KM)</label>
            <input type="text" id="odometer" name="odometer" class="filter-input"
                   value="<?php echo htmlspecialchars($odometer_filter); ?>"
                   placeholder="e.g., 20000" pattern="[0-9]*" inputmode="numeric"
                   title="Enter value to filter range (e.g., 20000 shows 20000-25000 km)">
          </div>
          <div class="filter-group">
            <label for="status">Request Status</label>
            <select id="status" name="status" class="filter-select">
              <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
              <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
              <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
              <option value="scheduled" <?php echo $status_filter === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
              <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
              <option value="no show" <?php echo $status_filter === 'no show' ? 'selected' : ''; ?>>No Show</option>
              <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
            </select>
          </div>
          <div class="filter-group">
            <label for="service">Service Type</label>
            <select id="service" name="service" class="filter-select">
              <option value="all" <?php echo $service_filter === 'all' ? 'selected' : ''; ?>>All Services</option>
              <option value="5K" <?php echo $service_filter === '5K' ? 'selected' : ''; ?>>5,000 KM PMS</option>
              <option value="10K" <?php echo $service_filter === '10K' ? 'selected' : ''; ?>>10,000 KM PMS</option>
              <option value="20K" <?php echo $service_filter === '20K' ? 'selected' : ''; ?>>20,000 KM PMS</option>
              <option value="general" <?php echo $service_filter === 'general' ? 'selected' : ''; ?>>General Service</option>
            </select>
          </div>
          <div class="filter-group">
            <label for="date">Date Range</label>
            <select id="date" name="date" class="filter-select">
              <option value="all" <?php echo $date_filter === 'all' ? 'selected' : ''; ?>>All Time</option>
              <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Today</option>
              <option value="week" <?php echo $date_filter === 'week' ? 'selected' : ''; ?>>This Week</option>
              <option value="month" <?php echo $date_filter === 'month' ? 'selected' : ''; ?>>This Month</option>
            </select>
          </div>
        </form>
      </div>

      <!-- PMS Requests Table -->
      <div class="client-orders-section">
        <div class="section-header">
          <h2 class="section-title">
            <i class="fas fa-list"></i>
            <span>PMS Request Management</span>
          </h2>
        </div>
        <div class="table-responsive">
          <table class="table">
            <thead>
              <tr>
                <th>Request Information</th>
                <th>Customer</th>
                <th>Vehicle</th>
                <th>Service Type</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($pms_requests as $request): ?>
              <tr>
                <td>
                  <div class="order-info">
                    <span class="order-id"><?php echo htmlspecialchars($request['request_id']); ?></span>
                    <span class="order-date"><?php echo date('M d, Y', strtotime($request['created_at'])); ?></span>
                    <?php if ($request['scheduled_date']): ?>
                    <small style="color: #ffd700;">Scheduled: <?php echo date('M d, Y g:i A', strtotime($request['scheduled_date'])); ?></small>
                    <?php endif; ?>
                  </div>
                </td>
                <td>
                  <div class="customer-info">
                    <span class="customer-name"><?php echo htmlspecialchars($request['FirstName'] . ' ' . $request['LastName']); ?></span>
                    <span class="customer-contact"><?php echo htmlspecialchars($request['mobile_number'] ?? 'N/A'); ?></span>
                  </div>
                </td>
                <td>
                  <div class="vehicle-info">
                    <span class="vehicle-model"><?php echo htmlspecialchars($request['model']); ?></span>
                    <span class="vehicle-details"><?php echo htmlspecialchars($request['plate_number']); ?></span>
                    <small><?php echo htmlspecialchars($request['current_odometer']); ?> KM</small>
                  </div>
                </td>
                <td><?php echo htmlspecialchars($request['pms_info']); ?></td>
                <td>
                  <span class="status-badge <?php echo strtolower($request['request_status']); ?>">
                    <?php echo htmlspecialchars($request['request_status']); ?>
                  </span>
                </td>
                <td>
                  <div class="order-actions-enhanced">
                    <?php if ($request['request_status'] === 'Pending'): ?>
                    <button class="btn-small btn-view" title="Approve" onclick="approveRequest(<?php echo $request['pms_id']; ?>, '<?php echo $request['request_id']; ?>')">
                      <i class="fas fa-check"></i>
                    </button>
                    <button class="btn-small btn-edit" title="Reschedule" onclick="rescheduleRequest(<?php echo $request['pms_id']; ?>, '<?php echo $request['request_id']; ?>')">
                      <i class="fas fa-calendar-alt"></i>
                    </button>
                    <button class="btn-small btn-delete" title="Reject" onclick="rejectRequest(<?php echo $request['pms_id']; ?>, '<?php echo $request['request_id']; ?>')" style="background: #e74c3c;">
                      <i class="fas fa-times"></i>
                    </button>
                    <button class="btn-small btn-info" title="View Details" onclick="viewDetails(<?php echo $request['pms_id']; ?>)" style="background: #3498db;">
                      <i class="fas fa-eye"></i>
                    </button>
                    <?php elseif ($request['request_status'] === 'Approved' || $request['request_status'] === 'Scheduled'): ?>
                    <button class="btn-small btn-view" title="View Details" onclick="viewDetails(<?php echo $request['pms_id']; ?>)">
                      <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn-small btn-edit" title="Reschedule" onclick="rescheduleRequest(<?php echo $request['pms_id']; ?>, '<?php echo $request['request_id']; ?>')">
                      <i class="fas fa-calendar-alt"></i>
                    </button>
                    <button class="btn-small btn-success" title="Mark as Completed" onclick="markCompleted(<?php echo $request['pms_id']; ?>, '<?php echo $request['request_id']; ?>')" style="background: #27ae60;">
                      <i class="fas fa-check-circle"></i>
                    </button>
                    <?php elseif ($request['request_status'] === 'No Show'): ?>
                    <button class="btn-small btn-view" title="View Details" onclick="viewDetails(<?php echo $request['pms_id']; ?>)">
                      <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn-small btn-edit" title="Reschedule" onclick="rescheduleRequest(<?php echo $request['pms_id']; ?>, '<?php echo $request['request_id']; ?>')">
                      <i class="fas fa-calendar-alt"></i>
                    </button>
                    <button class="btn-small btn-success" title="Mark as Completed" onclick="markCompleted(<?php echo $request['pms_id']; ?>, '<?php echo $request['request_id']; ?>')" style="background: #27ae60;">
                      <i class="fas fa-check-circle"></i>
                    </button>
                    <?php else: ?>
                    <button class="btn-small btn-view" title="View Details" onclick="viewDetails(<?php echo $request['pms_id']; ?>)">
                      <i class="fas fa-eye"></i>
                    </button>
                    <?php if ($request['request_status'] !== 'Completed' && $request['request_status'] !== 'Rejected'): ?>
                    <button class="btn-small btn-edit" title="Reschedule" onclick="rescheduleRequest(<?php echo $request['pms_id']; ?>, '<?php echo $request['request_id']; ?>')">
                      <i class="fas fa-calendar-alt"></i>
                    </button>
                    <?php endif; ?>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
              
              <?php if (empty($pms_requests)): ?>
              <tr>
                <td colspan="6" style="text-align: center; padding: 40px; color: #999;">
                  <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 10px;"></i><br>
                  No PMS requests found
                </td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Approve Modal -->
  <div class="modal-overlay" id="approveModal">
    <div class="modal">
      <div class="modal-header">
        <div style="display: flex; align-items: center; gap: 10px;">
          <div style="width: 40px; height: 40px; background: #27ae60; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
            <i class="fas fa-question" style="color: white; font-size: 18px;"></i>
          </div>
          <h3>Approve PMS Request</h3>
        </div>
        <button class="modal-close" onclick="closeApproveModal()">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <form id="approveForm">
        <div class="modal-body">
          <input type="hidden" id="approveRequestId" name="requestId">
          <input type="hidden" id="approvePmsId" name="pmsId">
          <p id="approveMessage">Approve PMS request <span id="approveRequestDisplay"></span> for service?</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" onclick="closeApproveModal()">Cancel</button>
          <button type="submit" class="btn btn-success">Yes, Approve</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Reject Modal -->
  <div class="modal-overlay" id="rejectModal">
    <div class="modal">
      <div class="modal-header">
        <h3>Reject PMS Request</h3>
        <button class="modal-close" onclick="closeRejectModal()">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <form id="rejectForm">
        <div class="modal-body">
          <input type="hidden" id="rejectRequestId" name="requestId">
          <input type="hidden" id="rejectPmsId" name="pmsId">
          <div class="form-group">
            <label class="form-label">Reason for Rejection <span style="color: red;">*</span></label>
            <textarea class="form-control" id="rejectionReason" name="rejectionReason" rows="4" 
                      placeholder="Enter reason for rejecting this request..." required></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" onclick="closeRejectModal()">Cancel</button>
          <button type="submit" class="btn btn-danger">Reject Request</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Reschedule Modal -->
  <div class="modal-overlay" id="rescheduleModal">
    <div class="modal">
      <div class="modal-header">
        <h3>Reschedule PMS Request</h3>
        <button class="modal-close" onclick="closeRescheduleModal()">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <form id="rescheduleForm">
        <div class="modal-body">
          <input type="hidden" id="rescheduleRequestId" name="requestId">
          <input type="hidden" id="reschedulePmsId" name="pmsId">
          <div class="form-group">
            <label class="form-label">New Preferred Date</label>
            <input type="date" class="form-control" id="newDate" name="newDate" required>
          </div>
          <div class="form-group">
            <label class="form-label">Available Time Slots</label>
            <select class="form-control" id="newTime" name="newTime" required>
              <option value="">Select time slot</option>
              <option value="08:00">8:00 AM</option>
              <option value="09:00">9:00 AM</option>
              <option value="10:00">10:00 AM</option>
              <option value="14:00">2:00 PM</option>
              <option value="15:00">3:00 PM</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Reason for Rescheduling</label>
            <textarea class="form-control" id="rescheduleReason" name="rescheduleReason" rows="3" placeholder="Optional reason..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" onclick="closeRescheduleModal()">Cancel</button>
          <button type="submit" class="btn btn-primary">Confirm Reschedule</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Mark as Completed Modal -->
  <div class="modal-overlay" id="markCompletedModal">
    <div class="modal">
      <div class="modal-header">
        <div style="display: flex; align-items: center; gap: 10px;">
          <div style="width: 40px; height: 40px; background: #27ae60; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
            <i class="fas fa-check-circle" style="color: white; font-size: 18px;"></i>
          </div>
          <h3>Mark PMS as Completed</h3>
        </div>
        <button class="modal-close" onclick="closeMarkCompletedModal()">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <form id="markCompletedForm">
        <div class="modal-body">
          <input type="hidden" id="completedRequestId" name="requestId">
          <input type="hidden" id="completedPmsId" name="pmsId">
          <p style="margin-bottom: 20px; color: #666;">Complete PMS request <strong id="completedRequestDisplay"></strong></p>

          <div class="form-group">
            <label class="form-label">Completion Date <span style="color: red;">*</span></label>
            <input type="date" class="form-control" id="completionDate" name="completionDate" required>
          </div>

          <div class="form-group">
            <label class="form-label">Service Notes/Findings <span style="color: red;">*</span></label>
            <textarea class="form-control" id="serviceNotes" name="serviceNotes" rows="4"
                      placeholder="Enter service notes, findings, or work performed..." required></textarea>
          </div>

          <div class="form-group">
            <label class="form-label">Next PMS Due (Optional)</label>
            <input type="text" class="form-control" id="nextPmsDue" name="nextPmsDue"
                   placeholder="e.g., 10,000 KM or 6 months">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" onclick="closeMarkCompletedModal()">Cancel</button>
          <button type="submit" class="btn btn-success">Mark as Completed</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Success/Error Message Modal -->
  <div class="modal-overlay" id="messageModal">
    <div class="modal">
      <div class="modal-header">
        <div style="display: flex; align-items: center; gap: 10px;">
          <div id="messageIcon" style="width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
            <i id="messageIconClass" style="color: white; font-size: 18px;"></i>
          </div>
          <h3 id="messageTitle">Success!</h3>
        </div>
        <button class="modal-close" onclick="closeMessageModal()">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div class="modal-body">
        <p id="messageText"></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" onclick="closeMessageModal()">OK</button>
      </div>
    </div>
  </div>

  <!-- View Details Modal -->
  <div class="modal-overlay" id="viewDetailsModal">
    <div class="modal" style="max-width: 800px;">
      <div class="modal-header">
        <h3>PMS Request Details</h3>
        <button class="modal-close" onclick="closeViewDetailsModal()">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
        <div id="detailsContent">
          <!-- Content will be loaded here -->
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeViewDetailsModal()">Close</button>
      </div>
    </div>
  </div>

  <!-- Remove SweetAlert CDN -->
  <script src="../../includes/js/common-scripts.js"></script>
  
  <script>
    let currentRequestId = null;
    let currentPmsId = null;

    function approveRequest(pmsId, requestId) {
      currentPmsId = pmsId;
      currentRequestId = requestId;
      document.getElementById('approvePmsId').value = pmsId;
      document.getElementById('approveRequestId').value = requestId;
      document.getElementById('approveRequestDisplay').textContent = requestId;
      document.getElementById('approveModal').classList.add('active');
    }

    function rescheduleRequest(pmsId, requestId) {
      currentPmsId = pmsId;
      currentRequestId = requestId;
      document.getElementById('reschedulePmsId').value = pmsId;
      document.getElementById('rescheduleRequestId').value = requestId;
      document.getElementById('rescheduleModal').classList.add('active');
    }

    function rejectRequest(pmsId, requestId) {
      currentPmsId = pmsId;
      currentRequestId = requestId;
      document.getElementById('rejectPmsId').value = pmsId;
      document.getElementById('rejectRequestId').value = requestId;
      document.getElementById('rejectModal').classList.add('active');
    }

    function closeApproveModal() {
      document.getElementById('approveModal').classList.remove('active');
      clearModalData();
    }

    function closeRejectModal() {
      document.getElementById('rejectModal').classList.remove('active');
      document.getElementById('rejectionReason').value = '';
      clearModalData();
    }

    function closeRescheduleModal() {
      document.getElementById('rescheduleModal').classList.remove('active');
      document.getElementById('rescheduleForm').reset();
      clearModalData();
    }

    function markCompleted(pmsId, requestId) {
      currentPmsId = pmsId;
      currentRequestId = requestId;
      document.getElementById('completedPmsId').value = pmsId;
      document.getElementById('completedRequestId').value = requestId;
      document.getElementById('completedRequestDisplay').textContent = requestId;

      // Set default completion date to today
      const today = new Date().toISOString().split('T')[0];
      document.getElementById('completionDate').value = today;

      document.getElementById('markCompletedModal').classList.add('active');
    }

    function closeMarkCompletedModal() {
      document.getElementById('markCompletedModal').classList.remove('active');
      document.getElementById('markCompletedForm').reset();
      clearModalData();
    }

    function closeMessageModal() {
      document.getElementById('messageModal').classList.remove('active');
      location.reload();
    }

    function closeViewDetailsModal() {
      document.getElementById('viewDetailsModal').classList.remove('active');
      document.getElementById('detailsContent').innerHTML = '';
    }

    function clearModalData() {
      currentRequestId = null;
      currentPmsId = null;
    }

    function showMessage(title, message, type = 'success') {
      const modal = document.getElementById('messageModal');
      const titleEl = document.getElementById('messageTitle');
      const textEl = document.getElementById('messageText');
      const iconEl = document.getElementById('messageIcon');
      const iconClassEl = document.getElementById('messageIconClass');
      
      titleEl.textContent = title;
      textEl.textContent = message;
      
      if (type === 'success') {
        iconEl.style.background = '#27ae60';
        iconClassEl.className = 'fas fa-check';
      } else {
        iconEl.style.background = '#e74c3c';
        iconClassEl.className = 'fas fa-times';
      }
      
      modal.classList.add('active');
    }

    function performAction(action, pmsId, data) {
      // Show loading state
      const activeModal = document.querySelector('.modal-overlay.active');
      if (activeModal) {
        const submitBtn = activeModal.querySelector('button[type="submit"]');
        if (submitBtn) {
          submitBtn.disabled = true;
          submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        }
      }

      fetch(window.location.href, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
          action: action,
          ...data
        })
      })
      .then(response => response.json())
      .then(data => {
        // Close all modals first
        document.querySelectorAll('.modal-overlay').forEach(modal => {
          modal.classList.remove('active');
        });
        
        if (data.success) {
          showMessage('Success!', data.message, 'success');
        } else {
          throw new Error(data.message);
        }
      })
      .catch(error => {
        // Close all modals first
        document.querySelectorAll('.modal-overlay').forEach(modal => {
          modal.classList.remove('active');
        });
        showMessage('Error!', error.message, 'error');
      });
    }

    function viewDetails(pmsId) {
      // Show loading
      document.getElementById('detailsContent').innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
      document.getElementById('viewDetailsModal').classList.add('active');
      
      fetch(window.location.href, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
          action: 'view_details',
          pms_id: pmsId
        })
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          displayPMSDetails(data.data);
        } else {
          throw new Error(data.message);
        }
      })
      .catch(error => {
        document.getElementById('detailsContent').innerHTML = '<div style="text-align: center; padding: 20px; color: #e74c3c;"><i class="fas fa-exclamation-triangle"></i> Error loading details: ' + error.message + '</div>';
      });
    }

    function displayPMSDetails(data) {
      const content = `
        <div class="details-grid">
          <!-- Request Information -->
          <div class="detail-section">
            <h4><i class="fas fa-info-circle"></i> Request Information</h4>
            <div class="detail-row">
              <span class="label">Request ID:</span>
              <span class="value">${data.request_id}</span>
            </div>
            <div class="detail-row">
              <span class="label">Status:</span>
              <span class="value status-badge ${data.request_status.toLowerCase()}">${data.request_status}</span>
            </div>
            <div class="detail-row">
              <span class="label">Date Submitted:</span>
              <span class="value">${new Date(data.created_at).toLocaleDateString('en-US', {year: 'numeric', month: 'long', day: 'numeric'})}</span>
            </div>
            ${data.scheduled_date ? `
            <div class="detail-row">
              <span class="label">Scheduled Date:</span>
              <span class="value">${new Date(data.scheduled_date).toLocaleDateString('en-US', {year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit'})}</span>
            </div>
            ` : ''}
            ${data.rejection_reason ? `
            <div class="detail-row">
              <span class="label">Rejection Reason:</span>
              <span class="value" style="color: #e74c3c;">${data.rejection_reason}</span>
            </div>
            ` : ''}
          </div>

          <!-- Customer Information -->
          <div class="detail-section">
            <h4><i class="fas fa-user"></i> Customer Information</h4>
            <div class="detail-row">
              <span class="label">Name:</span>
              <span class="value">${data.FirstName} ${data.LastName}</span>
            </div>
            <div class="detail-row">
              <span class="label">Email:</span>
              <span class="value">${data.Email || 'N/A'}</span>
            </div>
            <div class="detail-row">
              <span class="label">Mobile:</span>
              <span class="value">${data.mobile_number || 'N/A'}</span>
            </div>
            ${data.age ? `
            <div class="detail-row">
              <span class="label">Age:</span>
              <span class="value">${data.age} years old</span>
            </div>
            ` : ''}
            ${data.gender ? `
            <div class="detail-row">
              <span class="label">Gender:</span>
              <span class="value">${data.gender}</span>
            </div>
            ` : ''}
            ${data.employment_status ? `
            <div class="detail-row">
              <span class="label">Employment:</span>
              <span class="value">${data.employment_status}</span>
            </div>
            ` : ''}
            ${data.company_name ? `
            <div class="detail-row">
              <span class="label">Company:</span>
              <span class="value">${data.company_name}</span>
            </div>
            ` : ''}
          </div>

          <!-- Vehicle Information -->
          <div class="detail-section">
            <h4><i class="fas fa-car"></i> Vehicle Information</h4>
            <div class="detail-row">
              <span class="label">Model:</span>
              <span class="value">${data.model}</span>
            </div>
            <div class="detail-row">
              <span class="label">Plate Number:</span>
              <span class="value">${data.plate_number}</span>
            </div>
            <div class="detail-row">
              <span class="label">Current Odometer:</span>
              <span class="value">${data.current_odometer} KM</span>
            </div>
            ${data.transmission ? `
            <div class="detail-row">
              <span class="label">Transmission:</span>
              <span class="value">${data.transmission}</span>
            </div>
            ` : ''}
            ${data.engine_type ? `
            <div class="detail-row">
              <span class="label">Engine Type:</span>
              <span class="value">${data.engine_type}</span>
            </div>
            ` : ''}
            ${data.color ? `
            <div class="detail-row">
              <span class="label">Color:</span>
              <span class="value">${data.color}</span>
            </div>
            ` : ''}
          </div>

          <!-- Service Information -->
          <div class="detail-section">
            <h4><i class="fas fa-tools"></i> Service Information</h4>
            <div class="detail-row">
              <span class="label">PMS Type:</span>
              <span class="value">${data.pms_info}</span>
            </div>
            <div class="detail-row">
              <span class="label">Service Date:</span>
              <span class="value">${data.pms_date ? new Date(data.pms_date).toLocaleDateString() : 'N/A'}</span>
            </div>
            ${data.next_pms_due ? `
            <div class="detail-row">
              <span class="label">Next PMS Due:</span>
              <span class="value">${data.next_pms_due}</span>
            </div>
            ` : ''}
            <div class="detail-row">
              <span class="label">Services Performed:</span>
              <div class="value">
                ${data.services_performed.length > 0 ? 
                  data.services_performed.map(service => `<span class="service-tag">${service}</span>`).join(' ') : 
                  '<span style="color: #999;">No services selected</span>'
                }
              </div>
            </div>
            ${data.service_notes_findings ? `
            <div class="detail-row">
              <span class="label">Notes/Findings:</span>
              <div class="value notes-content">${data.service_notes_findings}</div>
            </div>
            ` : ''}
          </div>

          <!-- Receipt Information -->
          <div class="detail-section">
            <h4><i class="fas fa-receipt"></i> Uploaded Receipt</h4>
            <div class="detail-row">
              <span class="label">Receipt:</span>
              <div class="value">
                ${data.has_receipt ? 
                  `<button class="btn btn-primary btn-sm" onclick="viewReceipt(${data.pms_id})">
                    <i class="fas fa-eye"></i> View Receipt
                  </button>` : 
                  '<span style="color: #999;">No receipt uploaded</span>'
                }
              </div>
            </div>
          </div>
        </div>
      `;
      
      document.getElementById('detailsContent').innerHTML = content;
    }

    function viewReceipt(pmsId) {
      window.open(`?download_receipt=1&pms_id=${pmsId}`, '_blank');
    }

    // Add form event handlers
    document.addEventListener('DOMContentLoaded', function() {
      // Approve form handler
      document.getElementById('approveForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const pmsId = document.getElementById('approvePmsId').value;
        performAction('approve', pmsId, { pms_id: pmsId });
      });

      // Reject form handler
      document.getElementById('rejectForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const pmsId = document.getElementById('rejectPmsId').value;
        const reason = document.getElementById('rejectionReason').value;
        
        if (!reason.trim()) {
          alert('Please enter a reason for rejection');
          return;
        }
        
        performAction('reject', pmsId, { 
          pms_id: pmsId, 
          rejection_reason: reason 
        });
      });

      // Reschedule form handler
      document.getElementById('rescheduleForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const pmsId = document.getElementById('reschedulePmsId').value;
        const newDate = document.getElementById('newDate').value;
        const newTime = document.getElementById('newTime').value;
        const reason = document.getElementById('rescheduleReason').value;

        if (!newDate || !newTime) {
          alert('Please select both date and time');
          return;
        }

        // Check if date is not in the past
        const selectedDate = new Date(newDate + ' ' + newTime);
        const now = new Date();
        if (selectedDate <= now) {
          alert('Please select a future date and time');
          return;
        }

        performAction('reschedule', pmsId, {
          pms_id: pmsId,
          new_date: newDate,
          new_time: newTime,
          reschedule_reason: reason
        });
      });

      // Mark as Completed form handler
      document.getElementById('markCompletedForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const pmsId = document.getElementById('completedPmsId').value;
        const completionDate = document.getElementById('completionDate').value;
        const serviceNotes = document.getElementById('serviceNotes').value;
        const nextPmsDue = document.getElementById('nextPmsDue').value;

        if (!completionDate || !serviceNotes.trim()) {
          alert('Please fill in all required fields');
          return;
        }

        // Check if completion date is not in the future
        const selectedDate = new Date(completionDate);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        if (selectedDate > today) {
          alert('Completion date cannot be in the future');
          return;
        }

        performAction('mark_completed', pmsId, {
          pms_id: pmsId,
          completion_date: completionDate,
          service_notes: serviceNotes,
          next_pms_due: nextPmsDue
        });
      });

      // Set minimum date for reschedule to tomorrow
      const tomorrow = new Date();
      tomorrow.setDate(tomorrow.getDate() + 1);
      const tomorrowStr = tomorrow.toISOString().split('T')[0];
      document.getElementById('newDate').min = tomorrowStr;

      // Set maximum date for completion to today
      const today = new Date().toISOString().split('T')[0];
      document.getElementById('completionDate').max = today;
    });

    // Odometer input validation - only allow numbers
    const odometerInput = document.getElementById('odometer');
    if (odometerInput) {
      odometerInput.addEventListener('input', function(e) {
        // Remove all non-numeric characters
        this.value = this.value.replace(/\D/g, '');
      });

      odometerInput.addEventListener('paste', function(e) {
        setTimeout(() => {
          this.value = this.value.replace(/\D/g, '');
        }, 0);
      });
    }

    // Real-time filtering functionality
    let filterTimeout;
    const filterForm = document.getElementById('filterForm');
    const searchInput = document.getElementById('search');
    const odometerFilterInput = document.getElementById('odometer');
    const statusSelect = document.getElementById('status');
    const serviceSelect = document.getElementById('service');
    const dateSelect = document.getElementById('date');

    // Function to apply filters
    function applyFilters() {
      // Clear any existing timeout
      if (filterTimeout) {
        clearTimeout(filterTimeout);
      }

      // Set a small delay for text inputs to avoid too many requests while typing
      const delay = (event && event.target && (event.target.id === 'search' || event.target.id === 'odometer')) ? 500 : 0;

      filterTimeout = setTimeout(() => {
        // Build query string from form inputs
        const formData = new FormData(filterForm);
        const params = new URLSearchParams(formData);

        // Reload page with new parameters
        window.location.href = '?' + params.toString();
      }, delay);
    }

    // Add event listeners for real-time filtering
    if (searchInput) {
      searchInput.addEventListener('input', applyFilters);
    }

    if (odometerFilterInput) {
      odometerFilterInput.addEventListener('input', applyFilters);
    }

    if (statusSelect) {
      statusSelect.addEventListener('change', applyFilters);
    }

    if (serviceSelect) {
      serviceSelect.addEventListener('change', applyFilters);
    }

    if (dateSelect) {
      dateSelect.addEventListener('change', applyFilters);
    }
  </script>

  <style>
    .modal-overlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
      z-index: 1000;
      align-items: center;
      justify-content: center;
    }

    .modal-overlay.active {
      display: flex;
    }

    .modal {
      background: white;
      border-radius: 8px;
      width: 90%;
      max-width: 500px;
      max-height: 90vh;
      overflow-y: auto;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    }

    .modal-header {
      padding: 20px;
      border-bottom: 1px solid #eee;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .modal-header h3 {
      margin: 0;
      color: #333;
    }

    .modal-close {
      background: none;
      border: none;
      font-size: 18px;
      cursor: pointer;
      color: #999;
      padding: 5px;
    }

    .modal-close:hover {
      color: #333;
    }

    .modal-body {
      padding: 20px;
    }

    .modal-footer {
      padding: 20px;
      border-top: 1px solid #eee;
      display: flex;
      gap: 10px;
      justify-content: flex-end;
    }

    .form-group {
      margin-bottom: 15px;
    }

    .form-label {
      display: block;
      margin-bottom: 5px;
      font-weight: 500;
      color: #333;
    }

    .form-control {
      width: 100%;
      padding: 10px;
      border: 1px solid #ddd;
      border-radius: 4px;
      font-size: 14px;
    }

    .form-control:focus {
      outline: none;
      border-color: #007bff;
      box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
    }

    .btn {
      padding: 10px 20px;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      font-size: 14px;
      transition: all 0.2s;
    }

    .btn-primary {
      background: #007bff;
      color: white;
    }

    .btn-primary:hover {
      background: #0056b3;
    }

    .btn-success {
      background: #27ae60;
      color: white;
    }

    .btn-success:hover {
      background: #219a52;
    }

    .btn-danger {
      background: #e74c3c;
      color: white;
    }

    .btn-danger:hover {
      background: #c0392b;
    }

    .btn-secondary {
      background: #6c757d;
      color: white;
    }

    .btn-secondary:hover {
      background: #545b62;
    }

    .btn:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }

    .details-grid {
      display: grid;
      gap: 20px;
    }

    .detail-section {
      background: #f8f9fa;
      border-radius: 8px;
      padding: 15px;
      border: 1px solid #e9ecef;
    }

    .detail-section h4 {
      margin: 0 0 15px 0;
      color: #495057;
      font-size: 16px;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .detail-row {
      display: flex;
      margin-bottom: 10px;
      align-items: flex-start;
    }

    .detail-row .label {
      font-weight: 500;
      color: #6c757d;
      min-width: 140px;
      flex-shrink: 0;
    }

    .detail-row .value {
      color: #333;
      flex: 1;
    }

    .service-tag {
      display: inline-block;
      background: #e3f2fd;
      color: #1976d2;
      padding: 2px 8px;
      border-radius: 12px;
      font-size: 12px;
      margin: 2px;
      border: 1px solid #bbdefb;
    }

    .notes-content {
      background: white;
      padding: 10px;
      border-radius: 4px;
      border: 1px solid #dee2e6;
      white-space: pre-wrap;
      font-family: 'Courier New', monospace;
      font-size: 13px;
    }

    .btn-sm {
      padding: 6px 12px;
      font-size: 12px;
    }

    .btn-info {
      background: #3498db;
      color: white;
    }

    .btn-info:hover {
      background: #2980b9;
    }

    @media (max-width: 768px) {
      .detail-row {
        flex-direction: column;
      }
      
      .detail-row .label {
        min-width: auto;
        margin-bottom: 5px;
      }
    }

    /* Status Badge Styling */
    .status-badge {
      display: inline-block;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      border: 1px solid transparent;
    }

    .status-badge.pending {
      background-color: #fff3cd;
      color: #856404;
      border-color: #ffeaa7;
    }

    .status-badge.approved {
      background-color: #d4edda;
      color: #155724;
      border-color: #c3e6cb;
    }

    .status-badge.rejected {
      background-color: #f8d7da;
      color: #721c24;
      border-color: #f5c6cb;
    }

    .status-badge.scheduled {
      background-color: #d1ecf1;
      color: #0c5460;
      border-color: #bee5eb;
    }

    .status-badge.completed {
      background-color: #e2e3e5;
      color: #383d41;
      border-color: #d6d8db;
    }

    .status-badge.no.show {
      background-color: #f5d0fe;
      color: #86198f;
      border-color: #f0abfc;
    }

    /* Also style status badges in detail view */
    .detail-section .status-badge {
      font-size: 11px;
      padding: 3px 8px;
    }
  </style>
</body>
</html>