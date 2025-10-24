<?php
include_once(dirname(dirname(__DIR__)) . '/includes/init.php');
require_once (dirname(dirname(__DIR__)) . '/includes/handlers/pms_handler.php');

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

// Initialize PMS Handler
$pmsHandler = new PMSHandler($pdo);

// Get statistics and data for the page
$stats = $pmsHandler->getPMSStatistics();
$pmsRecords = $pmsHandler->getAllPMSRecords();
$customers = $pmsHandler->getCustomers();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>PMS Tracking - Admin Dashboard</title>
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
          <i class="fas fa-calendar-check icon-gradient"></i>
          PMS Tracking & Management
        </h1>
        <p class="page-subtitle">Track and manage preventive maintenance schedules and completed PMS sessions by each client</p>
      </div>

      <!-- PMS Tracking Statistics -->
      <div class="sales-agent-stats">
        <div class="stat-card">
          <div class="stat-icon green">
            <i class="fas fa-check-circle"></i>
          </div>
          <div class="stat-info">
            <h3 id="totalCompleted"><?php echo $stats['total_completed']; ?></h3>
            <p>Total PMS Sessions Completed</p>
            <small>All time record</small>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon blue">
            <i class="fas fa-calendar-alt"></i>
          </div>
          <div class="stat-info">
            <h3 id="thisMonth"><?php echo $stats['this_month']; ?></h3>
            <p>PMS Sessions This Month</p>
            <small>Current month completed</small>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon orange">
            <i class="fas fa-users"></i>
          </div>
          <div class="stat-info">
            <h3 id="activeClients"><?php echo $stats['active_clients']; ?></h3>
            <p>Active Clients with PMS</p>
            <small>Regular maintenance clients</small>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon purple">
            <i class="fas fa-clock"></i>
          </div>
          <div class="stat-info">
            <h3 id="avgDuration"><?php echo number_format($stats['avg_duration'], 1); ?></h3>
            <p>Avg Hours per Session</p>
            <small>Service completion time</small>
          </div>
        </div>
      </div>

      <div class="filters-section">
        <div class="filter-row">
          <div class="filter-group">
            <label for="client-search">Search Client/Vehicle</label>
            <input type="text" id="client-search" class="filter-input" placeholder="Search by client name, vehicle model, or plate number">
          </div>
          <div class="filter-group" style="position: relative;">
            <label for="odometer-filter">Odometer (KM)</label>
            <input type="text" id="odometer-filter" class="filter-input" placeholder="e.g., 20000" pattern="[0-9]*" inputmode="numeric" title="Enter value to filter range (e.g., 20000 shows 20000-25000 km)">
          </div>
          <div class="filter-group">
            <label for="completion-period">Completion Period</label>
            <select id="completion-period" class="filter-select">
              <option value="all">All Time</option>
              <option value="last7days">Last 7 Days</option>
              <option value="last30days">Last 30 Days</option>
              <option value="last3months">Last 3 Months</option>
              <option value="last6months">Last 6 Months</option>
              <option value="thisyear">This Year</option>
            </select>
          </div>
          <div class="filter-group">
            <label for="status-filter">Status</label>
            <select id="status-filter" class="filter-select">
              <option value="all">All Statuses</option>
              <option value="Completed">Completed</option>
              <option value="Scheduled">Scheduled</option>
              <option value="Approved">Approved</option>
              <option value="Pending">Pending</option>
            </select>
          </div>
          <button class="filter-btn" onclick="applyFilters()">Apply Filters</button>
        </div>
      </div>

      <!-- PMS Client Tracking Table -->
      <div class="client-orders-section">
        <div class="section-header">
          <h2 class="section-title">
            <i class="fas fa-list-check"></i>
            <span id="sectionTitle">Client PMS Session Tracking</span>
          </h2>
        </div>
        <div class="table-responsive">
          <table class="table">
            <thead>
              <tr>
                <th>Client Information</th>
                <th>Vehicle Details</th>
                <th>PMS Information</th>
                <th>Service Date</th>
                <th>Status</th>
                <th>Approved By</th>
              </tr>
            </thead>
            <tbody id="pmsTableBody">
              <?php if (!empty($pmsRecords)): ?>
                <?php foreach ($pmsRecords as $record): ?>
                  <tr data-customer-id="<?php echo $record['customer_id'] ?? 0; ?>">
                    <td>
                      <div class="customer-info">
                        <span class="customer-name"><?php echo htmlspecialchars($record['full_name'] ?? 'N/A'); ?></span>
                        <span class="customer-contact"><?php echo htmlspecialchars($record['mobile_number'] ?? 'N/A'); ?></span>
                        <div class="agent-note">Customer ID: <?php echo $record['customer_id'] ?? 'N/A'; ?></div>
                      </div>
                    </td>
                    <td>
                      <div class="vehicle-info">
                        <span class="vehicle-model"><?php echo htmlspecialchars($record['model'] ?? 'N/A'); ?></span>
                        <span class="vehicle-details">
                          <?php echo htmlspecialchars($record['plate_number'] ?? 'N/A'); ?> | 
                          <?php echo htmlspecialchars($record['color'] ?? 'N/A'); ?> |
                          <?php echo number_format($record['current_odometer'] ?? 0); ?> KM
                        </span>
                      </div>
                    </td>
                    <td>
                      <div class="pms-sessions">
                        <span class="session-count"><?php echo htmlspecialchars($record['pms_info'] ?? 'N/A'); ?></span>
                        <div class="session-breakdown">
                          <small>Transmission: <?php echo htmlspecialchars($record['transmission'] ?? 'N/A'); ?></small>
                        </div>
                      </div>
                    </td>
                    <td>
                      <div class="order-info">
                        <span class="order-date">
                          <?php echo !empty($record['pms_date']) ? date('M d, Y', strtotime($record['pms_date'])) : 'N/A'; ?>
                        </span>
                        <span class="order-id">Next Due: <?php echo htmlspecialchars($record['next_pms_due'] ?? 'N/A'); ?></span>
                      </div>
                    </td>
                    <td>
                      <span class="status-badge <?php echo strtolower($record['request_status'] ?? 'pending'); ?>">
                        <?php echo htmlspecialchars($record['request_status'] ?? 'Pending'); ?>
                      </span>
                    </td>
                    <td>
                      <div class="service-duration">
                        <span class="duration-time"><?php echo htmlspecialchars($record['approved_by_name'] ?? 'N/A'); ?></span>
                        <small>
                          <?php echo !empty($record['approved_at']) ? date('M d, Y', strtotime($record['approved_at'])) : ''; ?>
                        </small>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="6" style="text-align: center; padding: 20px;">No PMS records found.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- PMS History Modal -->
  <div class="modal-overlay" id="pmsHistoryModal">
    <div class="modal">
      <div class="modal-header">
        <h3>Complete PMS Session History</h3>
        <button class="modal-close" onclick="closePMSHistoryModal()">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div class="modal-body">
        <div class="pms-history-stats" id="historyStats">
          <!-- Stats will be loaded here -->
        </div>
        <div class="pms-timeline" id="historyTimeline">
          <!-- Timeline will be loaded here -->
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closePMSHistoryModal()">Close</button>
        <button type="button" class="btn btn-primary" onclick="exportHistory()">Export History</button>
      </div>
    </div>
  </div>

  <!-- Status Update Confirmation Modal -->
  <div class="modal-overlay" id="statusUpdateModal">
    <div class="modal">
      <div class="modal-header">
        <h3>Update PMS Status</h3>
        <button class="modal-close" onclick="closeStatusUpdateModal()">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div class="modal-body">
        <p id="statusUpdateMessage">Are you sure you want to update this PMS status?</p>
        <input type="hidden" id="statusUpdatePmsId" value="">
        <input type="hidden" id="statusUpdateNewStatus" value="">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeStatusUpdateModal()">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="confirmStatusUpdate()">Confirm Update</button>
      </div>
    </div>
  </div>

  <!-- Edit PMS Record Modal -->
  <div class="modal-overlay" id="editPMSModal">
    <div class="modal modal-large">
      <div class="modal-header">
        <h3>Edit PMS Record</h3>
        <button class="modal-close" onclick="closeEditPMSModal()">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div class="modal-body">
        <form id="editPMSForm">
          <input type="hidden" id="edit_pms_id" name="pms_id">
          
          <div class="form-row">
            <div class="form-group">
              <label for="edit_model">Vehicle Model</label>
              <input type="text" id="edit_model" name="model" class="form-input" required>
            </div>
            <div class="form-group">
              <label for="edit_plate_number">Plate Number</label>
              <input type="text" id="edit_plate_number" name="plate_number" class="form-input" required>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="edit_color">Color</label>
              <input type="text" id="edit_color" name="color" class="form-input">
            </div>
            <div class="form-group">
              <label for="edit_transmission">Transmission</label>
              <select id="edit_transmission" name="transmission" class="form-select">
                <option value="Automatic">Automatic</option>
                <option value="Manual">Manual</option>
              </select>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="edit_pms_info">PMS Type</label>
              <select id="edit_pms_info" name="pms_info" class="form-select" required>
                <option value="First 1K KM">First 1K KM</option>
                <option value="5K KM">5K KM</option>
                <option value="10K KM">10K KM</option>
                <option value="15K KM">15K KM</option>
                <option value="20K KM">20K KM</option>
                <option value="40K KM">40K KM</option>
                <option value="60K KM">60K KM</option>
                <option value="General PMS">General PMS</option>
              </select>
            </div>
            <div class="form-group">
              <label for="edit_current_odometer">Current Odometer (KM)</label>
              <input type="text" id="edit_current_odometer" name="current_odometer" class="form-input" pattern="[0-9]*" inputmode="numeric" placeholder="e.g., 20000" required>
              <small style="color: #666; font-size: 0.85em;">Enter numbers only (e.g., 20000)</small>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="edit_pms_date">Service Date</label>
              <input type="date" id="edit_pms_date" name="pms_date" class="form-input" required>
            </div>
            <div class="form-group">
              <label for="edit_next_pms_due">Next PMS Due</label>
              <input type="text" id="edit_next_pms_due" name="next_pms_due" class="form-input" placeholder="e.g., 20K KM or Date">
            </div>
          </div>

          <div class="form-group">
            <label for="edit_service_notes">Service Notes / Findings</label>
            <textarea id="edit_service_notes" name="service_notes_findings" class="form-textarea" rows="4" placeholder="Enter service notes and findings..."></textarea>
          </div>

          <div class="edit-loading" id="editLoading" style="display: none; text-align: center; padding: 20px;">
            <i class="fas fa-spinner fa-spin fa-2x"></i>
            <p style="margin-top: 10px;">Loading record...</p>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeEditPMSModal()">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="saveEditPMSRecord()">
          <i class="fas fa-save"></i> Save Changes
        </button>
      </div>
    </div>
  </div>

  <!-- Export History Modal -->
  <div class="modal-overlay" id="exportHistoryModal">
    <div class="modal">
      <div class="modal-header">
        <h3>Export PMS History</h3>
        <button class="modal-close" onclick="closeExportHistoryModal()">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label for="exportFormat">Export Format:</label>
          <select id="exportFormat" class="form-select">
            <option value="pdf">PDF Report</option>
            <option value="excel">Excel Spreadsheet</option>
            <option value="csv">CSV File</option>
          </select>
        </div>
        <div class="form-group">
          <label for="exportDateRange">Date Range:</label>
          <select id="exportDateRange" class="form-select">
            <option value="all">All Time</option>
            <option value="last30days">Last 30 Days</option>
            <option value="last6months">Last 6 Months</option>
            <option value="thisyear">This Year</option>
          </select>
        </div>
        <p><i class="fas fa-info-circle"></i> Exporting complete PMS session history...</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeExportHistoryModal()">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="processExport()">Generate Export</button>
      </div>
    </div>
  </div>

  <!-- Receipt Viewer Modal -->
  <div class="modal-overlay" id="receiptViewerModal">
    <div class="modal modal-large">
      <div class="modal-header">
        <h3>Service Receipt</h3>
        <button class="modal-close" onclick="closeReceiptModal()">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div class="modal-body">
        <div class="receipt-container">
          <img id="receiptImage" src="" alt="Service Receipt" style="max-width: 100%; height: auto; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
          <div class="receipt-loading" id="receiptLoading" style="text-align: center; padding: 40px;">
            <i class="fas fa-spinner fa-spin fa-2x"></i>
            <p style="margin-top: 15px;">Loading receipt...</p>
          </div>
          <div class="receipt-error" id="receiptError" style="text-align: center; padding: 40px; color: #dc3545; display: none;">
            <i class="fas fa-exclamation-triangle fa-2x"></i>
            <p style="margin-top: 15px;">Failed to load receipt</p>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeReceiptModal()">Close</button>
        <button type="button" class="btn btn-primary" onclick="downloadReceipt()">
          <i class="fas fa-download"></i> Download
        </button>
      </div>
    </div>
  </div>

  <!-- Reschedule Modal -->
  <div class="modal-overlay" id="rescheduleModal">
    <div class="modal">
      <div class="modal-header">
        <h3>Reschedule PMS Appointment</h3>
        <button class="modal-close" onclick="closeRescheduleModal()">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <form id="rescheduleForm">
        <div class="modal-body">
          <input type="hidden" id="reschedulePmsId" name="pmsId">
          <div class="form-group">
            <label class="form-label">New Scheduled Date</label>
            <input type="date" class="form-input" id="newDate" name="newDate" required>
          </div>
          <div class="form-group">
            <label class="form-label">Available Time Slots</label>
            <select class="form-input" id="newTime" name="newTime" required>
              <option value="">Select a time slot</option>
              <option value="08:00">8:00 AM - 9:00 AM</option>
              <option value="09:00">9:00 AM - 10:00 AM</option>
              <option value="10:00">10:00 AM - 11:00 AM</option>
              <option value="11:00">11:00 AM - 12:00 PM</option>
              <option value="13:00">1:00 PM - 2:00 PM</option>
              <option value="14:00">2:00 PM - 3:00 PM</option>
              <option value="15:00">3:00 PM - 4:00 PM</option>
              <option value="16:00">4:00 PM - 5:00 PM</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Reason for Rescheduling (Optional)</label>
            <textarea class="form-textarea" id="rescheduleReason" name="rescheduleReason" rows="3" placeholder="Enter reason for rescheduling..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" onclick="closeRescheduleModal()">Cancel</button>
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-calendar-check"></i> Confirm Reschedule
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Mark as Completed Modal -->
  <div class="modal-overlay" id="completedModal">
    <div class="modal">
      <div class="modal-header">
        <h3>Mark PMS as Completed</h3>
        <button class="modal-close" onclick="closeCompletedModal()">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <form id="completedForm">
        <div class="modal-body">
          <input type="hidden" id="completedPmsId" name="pmsId">
          <div class="form-group">
            <label class="form-label">Completion Date</label>
            <input type="date" class="form-input" id="completionDate" name="completionDate" required>
          </div>
          <div class="form-group">
            <label class="form-label">Service Notes / Findings</label>
            <textarea class="form-textarea" id="completionNotes" name="completionNotes" rows="4" placeholder="Enter service notes and findings..." required></textarea>
          </div>
          <div class="form-group">
            <label class="form-label">Next PMS Due</label>
            <input type="text" class="form-input" id="nextPmsDue" name="nextPmsDue" placeholder="e.g., 20K KM or Date">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" onclick="closeCompletedModal()">Cancel</button>
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-check-circle"></i> Mark as Completed
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Remove SweetAlert CDN -->
  <script src="../../includes/js/common-scripts.js"></script>
  
  <script>
    // Store customers data for reference
    const customers = <?php echo json_encode($customers); ?>;
    const userRole = '<?php echo $_SESSION['user_role'] ?? ''; ?>';
    
    // Function to view PMS history
    function viewPMSHistory(customerId) {
      if (!customerId || customerId === 0) {
        showNotification('Error: Invalid customer ID', 'error');
        return;
      }

      // Show loading
      document.getElementById('historyStats').innerHTML = '<div style="text-align: center; padding: 20px;">Loading...</div>';
      document.getElementById('historyTimeline').innerHTML = '';
      document.getElementById('pmsHistoryModal').classList.add('active');

      // Fetch history data
      fetch(`../../api/pms_api.php?action=get_customer_history&customer_id=${customerId}`)
        .then(response => {
          if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
          }
          return response.json();
        })
        .then(data => {
          console.log('API Response:', data); // Debug log
          if (data.success) {
            displayCustomerHistory(data.data);
          } else {
            throw new Error(data.message || 'Failed to load history');
          }
        })
        .catch(error => {
          console.error('Error loading history:', error);
          document.getElementById('historyStats').innerHTML = `<div style="text-align: center; padding: 20px; color: red;">Error loading history: ${error.message}</div>`;
          document.getElementById('historyTimeline').innerHTML = '';
        });
    }
    
    function displayCustomerHistory(data) {
      console.log('Displaying history data:', data); // Debug log

      if (!data || !data.stats || !data.history) {
        document.getElementById('historyStats').innerHTML = '<div style="text-align: center; padding: 20px; color: red;">Invalid data format</div>';
        document.getElementById('historyTimeline').innerHTML = '';
        return;
      }

      const stats = data.stats;
      const history = data.history;

      // Display stats
      document.getElementById('historyStats').innerHTML = `
        <div class="history-stat">
          <span class="stat-label">Total Sessions:</span>
          <span class="stat-value">${stats.total_sessions || 0}</span>
        </div>
        <div class="history-stat">
          <span class="stat-label">Total Service Time:</span>
          <span class="stat-value">${(stats.total_time || 0).toFixed(1)} hours</span>
        </div>
        <div class="history-stat">
          <span class="stat-label">Average Session Time:</span>
          <span class="stat-value">${(stats.avg_time || 0).toFixed(1)} hours</span>
        </div>
      `;

      // Display timeline
      let timelineHTML = '';
      if (history && history.length > 0) {
        history.forEach((record, index) => {
          const sessionNumber = history.length - index;
          const pmsDate = record.pms_date ? new Date(record.pms_date).toLocaleDateString() : 'N/A';

          timelineHTML += `
            <div class="timeline-item completed">
              <div class="timeline-date">${pmsDate}</div>
              <div class="timeline-content">
                <h4>${record.pms_info || 'PMS Service'} (Session #${sessionNumber})</h4>
                <p>${record.service_notes_findings || 'Standard maintenance service'}</p>
                <div class="session-details">
                  <span class="service-tech">Approved by: ${record.approved_by_name || 'N/A'}</span>
                  <span class="service-duration">Status: ${record.request_status || 'N/A'}</span>
                </div>
              </div>
            </div>
          `;
        });
      } else {
        timelineHTML = '<p style="text-align: center; color: #666;">No PMS history found for this customer.</p>';
      }

      document.getElementById('historyTimeline').innerHTML = timelineHTML;
    }

    function updateStatus(pmsId, status) {
      document.getElementById('statusUpdatePmsId').value = pmsId;
      document.getElementById('statusUpdateNewStatus').value = status;
      document.getElementById('statusUpdateMessage').textContent = `Are you sure you want to update status to "${status}"?`;
      document.getElementById('statusUpdateModal').classList.add('active');
    }

    function confirmStatusUpdate() {
      const pmsId = document.getElementById('statusUpdatePmsId').value;
      const status = document.getElementById('statusUpdateNewStatus').value;
      
      const formData = new FormData();
      formData.append('pms_id', pmsId);
      formData.append('status', status);
      
      fetch('../../api/pms_api.php?action=update_status', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          showNotification('Status updated successfully!', 'success');
          closeStatusUpdateModal();
          setTimeout(() => location.reload(), 1500);
        } else {
          throw new Error(data.message);
        }
      })
      .catch(error => {
        showNotification('Failed to update status: ' + error.message, 'error');
      });
    }

    function applyFilters() {
      // Trim and clean search input to handle pasted text with hidden characters
      const searchValue = document.getElementById('client-search').value;
      const cleanedSearch = searchValue.trim().replace(/\s+/g, ' ');

      const filters = {
        customer_search: cleanedSearch,
        odometer_filter: document.getElementById('odometer-filter').value,
        completion_period: document.getElementById('completion-period').value,
        status: document.getElementById('status-filter').value
      };

      const params = new URLSearchParams(filters);

      fetch(`../../api/pms_api.php?action=get_pms_records&${params}`)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            updateTable(data.data);
          } else {
            throw new Error(data.message);
          }
        })
        .catch(error => {
          showNotification('Failed to apply filters: ' + error.message, 'error');
        });
    }
    
    function updateTable(records) {
      const tbody = document.getElementById('pmsTableBody');
      let html = '';

      if (records.length === 0) {
        html = '<tr><td colspan="6" style="text-align: center; padding: 20px;">No records found matching the filters.</td></tr>';
      } else {
        records.forEach(record => {
          const isRejected = (record.request_status || '') === 'Rejected';
          html += `
            <tr data-customer-id="${record.customer_id || 0}">
              <td>
                <div class="customer-info">
                  <span class="customer-name">${record.full_name || 'N/A'}</span>
                  <span class="customer-contact">${record.mobile_number || 'N/A'}</span>
                  <div class="agent-note">Customer ID: ${record.customer_id || 'N/A'}</div>
                </div>
              </td>
              <td>
                <div class="vehicle-info">
                  <span class="vehicle-model">${record.model || 'N/A'}</span>
                  <span class="vehicle-details">
                    ${record.plate_number || 'N/A'} | 
                    ${record.color || 'N/A'} |
                    ${(record.current_odometer || 0).toLocaleString()} KM
                  </span>
                </div>
              </td>
              <td>
                <div class="pms-sessions">
                  <span class="session-count">${record.pms_info || 'N/A'}</span>
                  <div class="session-breakdown">
                    <small>Transmission: ${record.transmission || 'N/A'}</small>
                  </div>
                </div>
              </td>
              <td>
                <div class="order-info">
                  <span class="order-date">${record.pms_date ? new Date(record.pms_date).toLocaleDateString() : 'N/A'}</span>
                  <span class="order-id">Next Due: ${record.next_pms_due || 'N/A'}</span>
                </div>
              </td>
              <td>
                <span class="status-badge ${(record.request_status || 'pending').toLowerCase()}">
                  ${record.request_status || 'Pending'}
                </span>
              </td>
              <td>
                <div class="service-duration">
                  <span class="duration-time">${record.approved_by_name || 'N/A'}</span>
                  <small>${record.approved_at ? new Date(record.approved_at).toLocaleDateString() : ''}</small>
                </div>
              </td>
            </tr>
          `;
        });
      }
      
      tbody.innerHTML = html;
    }

    // Function to view receipt (temporary functionality)
    function viewReceipt(pmsId) {
      if (!pmsId) {
        showNotification('Error: Invalid PMS ID', 'error');
        return;
      }
      
      // Show modal with temporary content
      document.getElementById('receiptViewerModal').classList.add('active');
      document.getElementById('receiptLoading').style.display = 'block';
      document.getElementById('receiptImage').style.display = 'none';
      document.getElementById('receiptError').style.display = 'none';
      
      // Simulate loading delay and show temporary message
      setTimeout(() => {
        document.getElementById('receiptLoading').style.display = 'none';
        document.getElementById('receiptError').style.display = 'block';
        document.getElementById('receiptError').innerHTML = `
          <div style="text-align: center; padding: 40px;">
            <i class="fas fa-image fa-3x" style="color: #17a2b8; margin-bottom: 15px;"></i>
            <h4>Receipt Viewer (Temporary)</h4>
            <p style="color: #6c757d; margin: 15px 0;">This is a temporary placeholder for receipt viewing functionality.</p>
            <p style="color: #6c757d;">Backend integration for receipt #${pmsId} will be implemented in future updates.</p>
            
            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 20px 0; text-align: left;">
              <h5 style="margin-bottom: 10px; color: #495057;">Planned Receipt Features:</h5>
              <ul style="color: #6c757d; margin: 0; padding-left: 20px;">
                <li>View uploaded service receipts</li>
                <li>Download receipt images</li>
                <li>Zoom and pan functionality</li>
                <li>Receipt validation status</li>
                <li>Print receipt option</li>
              </ul>
            </div>
            
            <button type="button" class="btn btn-primary" onclick="showNotification('Receipt viewer coming soon!', 'info'); closeReceiptModal();">
              <i class="fas fa-info-circle"></i> Got it
            </button>
          </div>
        `;
      }, 1500);
    }

    // Function to close receipt modal
    function closeReceiptModal() {
      document.getElementById('receiptViewerModal').classList.remove('active');
      // Reset modal content
      document.getElementById('receiptLoading').style.display = 'block';
      document.getElementById('receiptImage').style.display = 'none';
      document.getElementById('receiptError').style.display = 'none';
      document.getElementById('receiptError').innerHTML = `
        <i class="fas fa-exclamation-triangle fa-2x"></i>
        <p style="margin-top: 15px;">Failed to load receipt</p>
      `;
    }

    // Function to download receipt (temporary functionality)
    function downloadReceipt() {
      showNotification('Download functionality will be available once backend is implemented', 'info');
    }

    function exportHistory() {
      document.getElementById('exportHistoryModal').classList.add('active');
    }

    function processExport() {
      const format = document.getElementById('exportFormat').value;
      const dateRange = document.getElementById('exportDateRange').value;
      
      showNotification(`Export functionality for ${format.toUpperCase()} format is temporarily disabled`, 'info');
      closeExportHistoryModal();
      
      // Simulate export process with temporary message
      setTimeout(() => {
        showNotification('Export feature will be implemented in future updates!', 'warning');
      }, 1000);
    }

    // Modal control functions
    function closePMSHistoryModal() {
      document.getElementById('pmsHistoryModal').classList.remove('active');
    }

    function closeStatusUpdateModal() {
      document.getElementById('statusUpdateModal').classList.remove('active');
    }

    function editPMSRecord(pmsId) {
      if (!pmsId) {
        showNotification('Error: Invalid PMS ID', 'error');
        return;
      }
      
      // Show modal and loading state
      document.getElementById('editPMSModal').classList.add('active');
      document.getElementById('editPMSForm').style.display = 'none';
      document.getElementById('editLoading').style.display = 'block';
      
      // Fetch record data
      fetch(`../../api/pms_api.php?action=get_record&pms_id=${pmsId}`)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            populateEditForm(data.data);
            document.getElementById('editLoading').style.display = 'none';
            document.getElementById('editPMSForm').style.display = 'block';
          } else {
            throw new Error(data.message || 'Failed to load record');
          }
        })
        .catch(error => {
          console.error('Error loading record:', error);
          showNotification('Failed to load record: ' + error.message, 'error');
          closeEditPMSModal();
        });
    }

    function populateEditForm(record) {
      document.getElementById('edit_pms_id').value = record.pms_id || '';
      document.getElementById('edit_model').value = record.model || '';
      document.getElementById('edit_plate_number').value = record.plate_number || '';
      document.getElementById('edit_color').value = record.color || '';
      document.getElementById('edit_transmission').value = record.transmission || 'Automatic';
      document.getElementById('edit_pms_info').value = record.pms_info || '';
      document.getElementById('edit_current_odometer').value = record.current_odometer || 0;
      document.getElementById('edit_pms_date').value = record.pms_date || '';
      document.getElementById('edit_next_pms_due').value = record.next_pms_due || '';
      document.getElementById('edit_service_notes').value = record.service_notes_findings || '';
    }

    function saveEditPMSRecord() {
      const form = document.getElementById('editPMSForm');
      
      // Basic validation
      if (!form.checkValidity()) {
        form.reportValidity();
        return;
      }
      
      const formData = new FormData(form);
      
      // Disable save button to prevent double submission
      const saveBtn = event.target;
      const originalText = saveBtn.innerHTML;
      saveBtn.disabled = true;
      saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
      
      fetch('../../api/pms_api.php?action=update_record', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          showNotification('PMS record updated successfully!', 'success');
          closeEditPMSModal();
          // Reload the table to show updated data
          setTimeout(() => location.reload(), 1500);
        } else {
          throw new Error(data.message);
        }
      })
      .catch(error => {
        showNotification('Failed to update record: ' + error.message, 'error');
      })
      .finally(() => {
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalText;
      });
    }

    function closeEditPMSModal() {
      document.getElementById('editPMSModal').classList.remove('active');
      document.getElementById('editPMSForm').reset();
    }

    function closeExportHistoryModal() {
      document.getElementById('exportHistoryModal').classList.remove('active');
    }

    // Reschedule functions
    function rescheduleRequest(pmsId) {
      if (!pmsId) {
        showNotification('Error: Invalid PMS ID', 'error');
        return;
      }

      document.getElementById('reschedulePmsId').value = pmsId;
      document.getElementById('rescheduleModal').classList.add('active');

      // Set minimum date to tomorrow
      const tomorrow = new Date();
      tomorrow.setDate(tomorrow.getDate() + 1);
      const tomorrowStr = tomorrow.toISOString().split('T')[0];
      document.getElementById('newDate').min = tomorrowStr;
    }

    function closeRescheduleModal() {
      document.getElementById('rescheduleModal').classList.remove('active');
      document.getElementById('rescheduleForm').reset();
    }

    // Mark as Completed functions
    function markAsCompleted(pmsId) {
      if (!pmsId) {
        showNotification('Error: Invalid PMS ID', 'error');
        return;
      }

      document.getElementById('completedPmsId').value = pmsId;
      document.getElementById('completedModal').classList.add('active');

      // Set default completion date to today
      const today = new Date().toISOString().split('T')[0];
      document.getElementById('completionDate').value = today;
      document.getElementById('completionDate').max = today;
    }

    function closeCompletedModal() {
      document.getElementById('completedModal').classList.remove('active');
      document.getElementById('completedForm').reset();
    }

    // Notification system to replace SweetAlert
    function showNotification(message, type = 'info') {
      // Create notification element
      const notification = document.createElement('div');
      notification.className = `notification notification-${type}`;
      notification.innerHTML = `
        <div class="notification-content">
          <i class="fas fa-${getNotificationIcon(type)}"></i>
          <span>${message}</span>
        </div>
        <button class="notification-close" onclick="this.parentElement.remove()">
          <i class="fas fa-times"></i>
        </button>
      `;
      
      // Add to page
      document.body.appendChild(notification);
      
      // Auto remove after 3 seconds
      setTimeout(() => {
        if (notification.parentElement) {
          notification.remove();
        }
      }, 3000);
    }

    function getNotificationIcon(type) {
      switch(type) {
        case 'success': return 'check-circle';
        case 'error': return 'exclamation-circle';
        case 'warning': return 'exclamation-triangle';
        case 'info': return 'info-circle';
        default: return 'info-circle';
      }
    }

    // Debounce function to limit API calls
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

    // Initialize page
    document.addEventListener('DOMContentLoaded', function() {
      // Add click outside modal to close functionality
      document.querySelectorAll('.modal-overlay').forEach(modal => {
        modal.addEventListener('click', function(e) {
          if (e.target === this) {
            this.classList.remove('active');
          }
        });
      });

      // Real-time search functionality with debounce
      const searchInput = document.getElementById('client-search');
      const debouncedSearch = debounce(applyFilters, 500);

      searchInput.addEventListener('input', function() {
        debouncedSearch();
      });

      // Clean pasted text to remove hidden characters
      searchInput.addEventListener('paste', function(e) {
        setTimeout(() => {
          // Trim and remove extra whitespace from pasted content
          this.value = this.value.trim().replace(/\s+/g, ' ');
          debouncedSearch();
        }, 0);
      });

      // Also trigger filter when other dropdowns change
      document.getElementById('completion-period').addEventListener('change', applyFilters);
      document.getElementById('status-filter').addEventListener('change', applyFilters);

      // Add input event for odometer filter with debounce
      const odometerInput = document.getElementById('odometer-filter');
      let odometerTimeout;
      odometerInput.addEventListener('input', function(e) {
        // Only allow numbers
        this.value = this.value.replace(/\D/g, '');

        clearTimeout(odometerTimeout);
        odometerTimeout = setTimeout(() => {
          applyFilters();
        }, 500);
      });

      // Odometer input validation for edit form
      const editOdometerInput = document.getElementById('edit_current_odometer');
      if (editOdometerInput) {
        editOdometerInput.addEventListener('input', function(e) {
          this.value = this.value.replace(/\D/g, '');
        });

        editOdometerInput.addEventListener('paste', function(e) {
          setTimeout(() => {
            this.value = this.value.replace(/\D/g, '');
          }, 0);
        });
      }

      // Reschedule form handler
      document.getElementById('rescheduleForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const pmsId = document.getElementById('reschedulePmsId').value;
        const newDate = document.getElementById('newDate').value;
        const newTime = document.getElementById('newTime').value;
        const reason = document.getElementById('rescheduleReason').value;

        if (!newDate || !newTime) {
          showNotification('Please select both date and time', 'error');
          return;
        }

        // Check if date is not in the past
        const selectedDate = new Date(newDate + ' ' + newTime);
        const now = new Date();
        if (selectedDate <= now) {
          showNotification('Please select a future date and time', 'error');
          return;
        }

        const formData = new FormData();
        formData.append('pms_id', pmsId);
        formData.append('new_date', newDate);
        formData.append('new_time', newTime);
        formData.append('reschedule_reason', reason);

        fetch('../../api/pms_api.php?action=reschedule', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            showNotification('PMS appointment rescheduled successfully!', 'success');
            closeRescheduleModal();
            setTimeout(() => location.reload(), 1500);
          } else {
            throw new Error(data.message || 'Failed to reschedule');
          }
        })
        .catch(error => {
          showNotification('Failed to reschedule: ' + error.message, 'error');
        });
      });

      // Mark as Completed form handler
      document.getElementById('completedForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const pmsId = document.getElementById('completedPmsId').value;
        const completionDate = document.getElementById('completionDate').value;
        const notes = document.getElementById('completionNotes').value;
        const nextPmsDue = document.getElementById('nextPmsDue').value;

        if (!completionDate || !notes) {
          showNotification('Please fill in all required fields', 'error');
          return;
        }

        const formData = new FormData();
        formData.append('pms_id', pmsId);
        formData.append('completion_date', completionDate);
        formData.append('service_notes', notes);
        formData.append('next_pms_due', nextPmsDue);

        fetch('../../api/pms_api.php?action=mark_completed', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            showNotification('PMS marked as completed successfully!', 'success');
            closeCompletedModal();
            setTimeout(() => location.reload(), 1500);
          } else {
            throw new Error(data.message || 'Failed to mark as completed');
          }
        })
        .catch(error => {
          showNotification('Failed to mark as completed: ' + error.message, 'error');
        });
      });
    });
  </script>

  <style>
    .page-subtitle {
      color: #7f8c8d;
      font-size: 1rem;
      margin: 5px 0 0 0;
      font-weight: normal;
    }

    .pms-sessions {
      text-align: center;
    }

    .session-count {
      font-weight: bold;
      font-size: 1.1rem;
      color: #2c3e50;
      display: block;
      margin-bottom: 5px;
    }

    .session-breakdown {
      font-size: 0.8rem;
      color: #7f8c8d;
      line-height: 1.2;
    }

    .service-duration {
      text-align: center;
    }

    .duration-time {
      font-weight: bold;
      color: #8e44ad;
      display: block;
    }

    .service-duration small {
      color: #95a5a6;
      font-size: 0.8rem;
    }

    .pms-history-stats {
      display: flex;
      justify-content: space-around;
      background: #f8f9fa;
      padding: 1rem;
      border-radius: 8px;
      margin-bottom: 1.5rem;
    }

    .history-stat {
      text-align: center;
    }

    .stat-label {
      display: block;
      font-size: 0.9rem;
      color: #7f8c8d;
      margin-bottom: 5px;
    }

    .stat-value {
      font-size: 1.2rem;
      font-weight: bold;
      color: #2c3e50;
    }

    .pms-timeline {
      padding: 1rem 0;
    }

    .timeline-item {
      display: flex;
      margin-bottom: 1.5rem;
      padding: 1rem;
      border-radius: 8px;
      border-left: 4px solid #27ae60;
      background: #f8fff8;
    }

    .timeline-date {
      min-width: 120px;
      font-weight: bold;
      color: #2c3e50;
    }

    .timeline-content {
      flex: 1;
      margin-left: 1rem;
    }

    .timeline-content h4 {
      margin: 0 0 0.5rem 0;
      color: #2c3e50;
    }

    .timeline-content p {
      margin: 0 0 0.5rem 0;
      color: #666;
    }

    .session-details {
      display: flex;
      gap: 15px;
      flex-wrap: wrap;
    }

    .service-tech, .service-duration {
      font-size: 0.8rem;
      color: #95a5a6;
      font-style: italic;
    }

    .sales-agent-stats .stat-info small {
      font-size: 0.8rem;
      color: #95a5a6;
      margin-top: 5px;
      display: block;
    }

    .status-badge.completed { background: #d4edda; color: #155724; }
    .status-badge.scheduled { background: #d1ecf1; color: #0c5460; }
    .status-badge.approved { background: #fff3cd; color: #856404; }
    .status-badge.pending { background: #f8d7da; color: #721c24; }
    .status-badge.rejected { background: #f5c6cb; color: #721c24; }

    /* Modal Styles */
    .modal-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.5);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 1000;
    }

    .modal-overlay.active {
      display: flex;
    }

    .modal {
      background: white;
      border-radius: 12px;
      width: 90%;
      max-width: 600px;
      max-height: 90vh;
      overflow-y: auto;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
      animation: modalSlideIn 0.3s ease;
    }

    @keyframes modalSlideIn {
      from {
        opacity: 0;
        transform: translateY(-50px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .modal-header {
      padding: 20px 25px;
      border-bottom: 1px solid #eee;
      display: flex;
      justify-content: space-between;
      align-items: center;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      border-radius: 12px 12px 0 0;
    }

    .modal-header h3 {
      margin: 0;
      font-size: 1.4rem;
      font-weight: 600;
    }

    .modal-close {
      background: none;
      border: none;
      color: white;
      font-size: 24px;
      cursor: pointer;
      padding: 5px;
      border-radius: 50%;
      width: 35px;
      height: 35px;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: background-color 0.3s ease;
    }

    .modal-close:hover {
      background-color: rgba(255, 255, 255, 0.2);
    }

    .modal-body {
      padding: 25px;
      line-height: 1.6;
    }

    .modal-footer {
      padding: 20px 25px;
      border-top: 1px solid #eee;
      display: flex;
      justify-content: flex-end;
      gap: 12px;
      background-color: #f8f9fa;
      border-radius: 0 0 12px 12px;
    }

    .form-group {
      margin-bottom: 20px;
    }

    .form-group label {
      display: block;
      margin-bottom: 8px;
      font-weight: 600;
      color: #2c3e50;
    }

    .form-row {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 1rem;
      margin-bottom: 1rem;
    }

    @media (max-width: 768px) {
      .form-row {
        grid-template-columns: 1fr;
      }
    }

    .form-select,
    .form-input,
    .form-textarea {
      width: 100%;
      padding: 0.625rem 0.9375rem;
      border: 2px solid #e1e8ed;
      border-radius: 0.5rem;
      font-size: 1rem;
      transition: border-color 0.3s ease;
      font-family: inherit;
    }

    .form-select:focus,
    .form-input:focus,
    .form-textarea:focus {
      outline: none;
      border-color: #667eea;
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .form-textarea {
      resize: vertical;
      min-height: 6.25rem;
    }

    .form-input[type="number"]::-webkit-inner-spin-button,
    .form-input[type="number"]::-webkit-outer-spin-button {
      opacity: 1;
    }

    /* Notification Styles */
    .notification {
      position: fixed;
      top: 20px;
      right: 20px;
      padding: 15px 20px;
      border-radius: 8px;
      color: white;
      font-weight: 500;
      z-index: 1001;
      min-width: 300px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      animation: slideInRight 0.3s ease;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    @keyframes slideInRight {
      from {
        transform: translateX(100%);
        opacity: 0;
      }
      to {
        transform: translateX(0);
        opacity: 1;
      }
    }

    .notification-success {
      background: linear-gradient(135deg, #28a745, #20c997);
    }

    .notification-error {
      background: linear-gradient(135deg, #dc3545, #e74c3c);
    }

    .notification-warning {
      background: linear-gradient(135deg, #ffc107, #fd7e14);
    }

    .notification-info {
      background: linear-gradient(135deg, #17a2b8, #007bff);
    }

    .notification-content {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .notification-close {
      background: none;
      border: none;
      color: white;
      cursor: pointer;
      padding: 5px;
      border-radius: 4px;
      transition: background-color 0.3s ease;
    }

    .notification-close:hover {
      background-color: rgba(255, 255, 255, 0.2);
    }

    /* Button Styles */
    .btn {
      padding: 10px 20px;
      border: none;
      border-radius: 6px;
      font-size: 1rem;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.3s ease;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }

    .btn-primary {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }

    .btn-secondary {
      background: #6c757d;
      color: white;
    }

    .btn-secondary:hover {
      background: #5a6268;
      transform: translateY(-2px);
    }

    .modal-large {
      max-width: 800px;
    }

    .receipt-container {
      text-align: center;
      min-height: 200px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .receipt-loading {
      color: #6c757d;
    }

    .receipt-loading i {
      color: #007bff;
    }

    .btn-info {
      background: #17a2b8;
      color: white;
      border: none;
      padding: 4px 8px;
      border-radius: 4px;
      font-size: 0.8rem;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 4px;
      transition: background-color 0.3s ease;
    }

    .btn-info:hover {
      background: #138496;
    }

    .order-actions-enhanced {
      display: flex;
      gap: 5px;
      flex-wrap: wrap;
      align-items: center;
      justify-content: center;
      min-height: 35px;
    }
    
    /* Receipt indicator in table */
    .receipt-indicator {
      background: #17a2b8;
      color: white;
      padding: 2px 6px;
      border-radius: 3px;
      font-size: 0.7rem;
      margin-left: 5px;
    }

    /* Responsive receipt viewer */
    @media (max-width: 768px) {
      .modal-large {
        width: 95%;
        max-width: none;
      }
      
      .receipt-container img {
        max-width: 100%;
        height: auto;
      }
    }

    .status-note {
      font-size: 0.8rem;
      font-style: italic;
      color: #6c757d;
      text-align: center;
      padding: 5px;
    }
  </style>
</body>
</html>