<?php
include_once(dirname(dirname(__DIR__)) . '/includes/init.php');

// Check if user is Admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: ../../pages/login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Handled Records - Mitsubishi</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
    }
    /* Admin Handled Records Specific Styles */
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

    .tab-button:hover:not(.active) {
      color: var(--text-dark);
      background-color: var(--border-light);
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

    .info-card:hover {
      box-shadow: var(--shadow-light);
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

    /* Align table cells consistently and keep actions inline */
    .data-table td { 
      vertical-align: middle; 
    }

    /* Reserve consistent space for Actions column and prevent wrapping */
    .data-table th:last-child,
    .data-table td:last-child {
      width: 220px;
      white-space: nowrap;
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

    .status.approved {
      background-color: #e6f7ed;
      color: #0e7c42;
    }

    .status.pending {
      background-color: #fff8e6;
      color: #b78105;
    }

    .status.completed {
      background-color: #e6eefb;
      color: #1e62cd;
    }

    .status.overdue {
      background-color: #fce8e8;
      color: #b91c1c;
    }

    .table-actions {
      display: flex;
      gap: 8px;
      align-items: center;
      justify-content: flex-start;
      flex-wrap: nowrap;
    }

    .table-actions .btn {
      white-space: nowrap;
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

    /* Workload Status Cards */
    .workload-stats {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 15px;
      margin-bottom: 30px;
    }

    .workload-card {
      background-color: white;
      border-radius: 10px;
      padding: 20px;
      border-left: 5px solid;
      box-shadow: var(--shadow-light);
    }

    .workload-card.high {
      border-left-color: #ef4444;
    }

    .workload-card.medium {
      border-left-color: #f59e0b;
    }

    .workload-card.low {
      border-left-color: #10b981;
    }

    .workload-card.normal {
      border-left-color: #3b82f6;
    }

    .workload-value {
      font-size: 28px;
      font-weight: 700;
      margin-bottom: 5px;
    }

    .workload-label {
      color: var(--text-light);
      font-size: 14px;
    }

    /* Assignment form */
    .assignment-form {
      background: white;
      border-radius: 10px;
      padding: 25px;
      margin-bottom: 30px;
      border: 1px solid var(--border-light);
      box-shadow: var(--shadow-light);
    }

    .form-group {
      margin-bottom: 20px;
    }

    .form-label {
      display: block;
      margin-bottom: 8px;
      font-weight: 600;
      color: var(--text-dark);
    }

    .form-input, .form-select, .form-textarea {
      width: 100%;
      padding: 12px 15px;
      border: 1px solid var(--border-light);
      border-radius: 8px;
      font-size: 14px;
      transition: var(--transition);
      background: white;
    }

    .form-row {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 20px;
    }

    .section-heading {
      margin-bottom: 20px;
      padding-bottom: 10px;
      border-bottom: 1px solid var(--border-light);
      font-size: 18px;
      color: var(--text-dark);
      font-weight: 600;
    }

    .required {
      color: var(--primary-red);
    }

    /* Responsive Design */
    @media (max-width: 575px) {
      .info-cards, .workload-stats {
        grid-template-columns: 1fr;
      }
      .form-row {
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
      .form-row {
        grid-template-columns: 1fr;
      }
    }

    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    /* Modal Enhancements */
    .modal-dialog-centered {
      display: flex;
      align-items: center;
      min-height: calc(100% - 3.5rem);
    }

    .modal-content {
      border-radius: 12px;
      border: none;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
    }

    .modal-header {
      background: linear-gradient(135deg, var(--primary-red), #b91c3c);
      color: white;
      border-bottom: none;
      border-radius: 12px 12px 0 0;
      padding: 20px 30px;
    }

    .modal-header .modal-title {
      font-size: 20px;
      font-weight: 700;
    }

    .modal-header .btn-close {
      filter: brightness(0) invert(1);
      opacity: 0.8;
    }

    .modal-header .btn-close:hover {
      opacity: 1;
    }

    .modal-body {
      padding: 30px;
      background-color: #f8f9fa;
    }

    .modal-body h6 {
      font-size: 16px;
      font-weight: 700;
      color: var(--primary-red);
      margin-bottom: 15px;
      padding-bottom: 10px;
      border-bottom: 2px solid var(--primary-red);
    }

    .modal-body p {
      margin-bottom: 12px;
      font-size: 14px;
      line-height: 1.6;
    }

    .modal-body strong {
      color: var(--text-dark);
      font-weight: 600;
      display: inline-block;
      min-width: 140px;
    }

    .modal-footer {
      border-top: 1px solid #dee2e6;
      padding: 15px 30px;
      background-color: white;
      border-radius: 0 0 12px 12px;
    }

    .modal-backdrop.show {
      opacity: 0.7;
    }

    /* Info Cards in Modal */
    .info-section {
      background: white;
      padding: 20px;
      border-radius: 10px;
      margin-bottom: 20px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .info-section:last-child {
      margin-bottom: 0;
    }

    /* Table in Modal */
    .modal-body .table {
      background: white;
      margin-bottom: 0;
    }

    .modal-body .table thead {
      background-color: #f8f9fa;
    }

    .modal-body .table thead th {
      font-weight: 600;
      color: var(--text-dark);
      border-bottom: 2px solid #dee2e6;
      padding: 12px;
    }

    .modal-body .table tbody td {
      padding: 12px;
      vertical-align: middle;
    }

    .modal-body .table tbody tr:hover {
      background-color: #f8f9fa;
    }

    /* Status Badge in Modal */
    .modal-body .status {
      font-size: 11px;
      padding: 4px 10px;
    }

    /* Responsive Modal */
    @media (max-width: 768px) {
      .modal-dialog {
        margin: 10px;
      }

      .modal-body {
        padding: 20px;
      }

      .modal-body strong {
        min-width: auto;
        display: block;
        margin-bottom: 3px;
      }

      /* Restore spacing on small screens while keeping centering */
      .cm-modal {
        width: calc(100% - 20px);
      }
    }

    /* Custom centered modal (conflict-free, modeled after inquiries.php) */
    .cm-modal-overlay {
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.6);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 1055;
    }
    .cm-modal-overlay.active { display: flex; }

    .cm-modal {
      background: #fff;
      border-radius: 12px;
      width: 900px;
      max-width: 95vw;
      max-height: 85vh;
      display: flex;
      flex-direction: column;
      box-shadow: 0 10px 40px rgba(0,0,0,0.25);
      overflow: hidden;
    }
    .cm-modal-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      background: linear-gradient(135deg, var(--primary-red), #b91c3c);
      color: #fff;
      padding: 16px 22px;
    }
    .cm-modal-title { font-size: 18px; font-weight: 700; margin: 0; }
    .cm-modal-close {
      background: transparent;
      border: none;
      color: #fff;
      font-size: 20px;
      cursor: pointer;
      opacity: 0.9;
    }
    .cm-modal-close:hover { opacity: 1; }
    .cm-modal-body {
      padding: 22px;
      overflow-y: auto;
      background: #f8f9fa;
      flex: 1;
    }
    .cm-modal-footer {
      display: flex;
      justify-content: flex-end;
      gap: 10px;
      padding: 14px 22px;
      background: #fff;
      border-top: 1px solid #e5e7eb;
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
          <i class="fas fa-user-friends"></i>
          Admin Handled Records
        </h1>
      </div>

      <div class="tab-navigation">
        <button class="tab-button active" data-tab="handled-clients">Client-Agent Assignments</button>
        <button class="tab-button" data-tab="handled-workload">Agent Workload</button>
        <button class="tab-button" data-tab="handled-reassign">Reassign Clients</button>
        <button class="tab-button" data-tab="handled-performance">Performance Review</button>
      </div>

      <!-- Client-Agent Assignments Tab -->
      <div class="tab-content active" id="handled-clients">
        <div class="info-cards">
          <div class="info-card">
            <div class="info-card-title">Total Client Assignments</div>
            <div class="info-card-value">1,247</div>
          </div>
          <div class="info-card">
            <div class="info-card-title">Active Clients</div>
            <div class="info-card-value">1,198</div>
          </div>
          <div class="info-card">
            <div class="info-card-title">Successful Conversions</div>
            <div class="info-card-value">89%</div>
          </div>
          <div class="info-card">
            <div class="info-card-title">Avg. Handle Time</div>
            <div class="info-card-value">14 days</div>
          </div>
        </div>

        <div class="filter-bar">
          <div class="search-input">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Search clients or agents...">
          </div>
          <select class="filter-select">
            <option value="">All Agents</option>
            <option value="carlos">Carlos Mendoza</option>
            <option value="ana">Ana Santos</option>
            <option value="juan">Juan Reyes</option>
          </select>
          <select class="filter-select">
            <option value="">All Status</option>
            <option value="active">Active</option>
            <option value="completed">Completed</option>
            <option value="pending">Pending</option>
          </select>
        </div>

        <table class="data-table">
          <thead>
            <tr>
              <th>Client Name</th>
              <th>Contact Info</th>
              <th>Vehicle Interest</th>
              <th>Assigned Agent</th>
              <th>Assignment Date</th>
              <th>Status</th>
              <th>Last Activity</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>John Doe<br><small>CL-2024-001</small></td>
              <td>john.doe@email.com<br><small>+63 917 123 4567</small></td>
              <td>Montero Sport GLS<br><small>Interested in financing</small></td>
              <td>Carlos Mendoza<br><small>Senior Agent</small></td>
              <td>Mar 15, 2024</td>
              <td><span class="status approved">Active</span></td>
              <td>Mar 23, 2024</td>
              <td class="table-actions">
                <button class="btn btn-small btn-outline">View Details</button>
                <button class="btn btn-small btn-primary">Reassign</button>
              </td>
            </tr>
            <tr>
              <td>Maria Santos<br><small>CL-2024-002</small></td>
              <td>maria@email.com<br><small>+63 917 234 5678</small></td>
              <td>Xpander GLS AT<br><small>Test drive completed</small></td>
              <td>Ana Santos<br><small>Sales Agent</small></td>
              <td>Mar 10, 2024</td>
              <td><span class="status completed">Sale Completed</span></td>
              <td>Mar 22, 2024</td>
              <td class="table-actions">
                <button class="btn btn-small btn-outline">View Details</button>
                <button class="btn btn-small btn-secondary">Archive</button>
              </td>
            </tr>
            <tr>
              <td>Robert Cruz<br><small>CL-2024-003</small></td>
              <td>robert@email.com<br><small>+63 917 345 6789</small></td>
              <td>Mirage G4 GLS<br><small>Price negotiation</small></td>
              <td>Juan Reyes<br><small>Junior Agent</small></td>
              <td>Mar 20, 2024</td>
              <td><span class="status pending">Follow-up Required</span></td>
              <td>Mar 21, 2024</td>
              <td class="table-actions">
                <button class="btn btn-small btn-outline">View Details</button>
                <button class="btn btn-small btn-primary">Escalate</button>
              </td>
            </tr>
          </tbody>
        </table>
        
        <div class="action-area">
          <button class="btn btn-primary">Export Client List</button>
          <button class="btn btn-secondary">Generate Assignment Report</button>
        </div>
      </div>

      <!-- Agent Workload Tab -->
      <div class="tab-content" id="handled-workload">
        <div class="workload-stats">
          <div class="workload-card high">
            <div class="workload-value">23</div>
            <div class="workload-label">Carlos - Active Clients</div>
          </div>
          <div class="workload-card medium">
            <div class="workload-value">19</div>
            <div class="workload-label">Ana - Active Clients</div>
          </div>
          <div class="workload-card normal">
            <div class="workload-value">15</div>
            <div class="workload-label">Juan - Active Clients</div>
          </div>
          <div class="workload-card low">
            <div class="workload-value">12</div>
            <div class="workload-label">Maria - Active Clients</div>
          </div>
        </div>

        <table class="data-table">
          <thead>
            <tr>
              <th>Sales Agent</th>
              <th>Active Clients</th>
              <th>Completed This Month</th>
              <th>Average Handle Time</th>
              <th>Success Rate</th>
              <th>Workload Status</th>
              <th>Next Available</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>Carlos Mendoza</td>
              <td>23</td>
              <td>18</td>
              <td>12 days</td>
              <td><span class="status approved">92%</span></td>
              <td><span class="status overdue">High</span></td>
              <td>Mar 28, 2024</td>
              <td class="table-actions">
                <button class="btn btn-small btn-outline">View Clients</button>
                <button class="btn btn-small btn-secondary">Reduce Load</button>
              </td>
            </tr>
            <tr>
              <td>Ana Santos</td>
              <td>19</td>
              <td>15</td>
              <td>14 days</td>
              <td><span class="status approved">88%</span></td>
              <td><span class="status pending">Medium</span></td>
              <td>Mar 25, 2024</td>
              <td class="table-actions">
                <button class="btn btn-small btn-outline">View Clients</button>
                <button class="btn btn-small btn-primary">Assign More</button>
              </td>
            </tr>
            <tr>
              <td>Juan Reyes</td>
              <td>15</td>
              <td>10</td>
              <td>18 days</td>
              <td><span class="status pending">75%</span></td>
              <td><span class="status approved">Normal</span></td>
              <td>Available</td>
              <td class="table-actions">
                <button class="btn btn-small btn-outline">View Clients</button>
                <button class="btn btn-small btn-primary">Assign More</button>
              </td>
            </tr>
            <tr>
              <td>Maria Gonzales</td>
              <td>12</td>
              <td>8</td>
              <td>16 days</td>
              <td><span class="status pending">70%</span></td>
              <td><span class="status approved">Low</span></td>
              <td>Available</td>
              <td class="table-actions">
                <button class="btn btn-small btn-outline">View Clients</button>
                <button class="btn btn-small btn-primary">Assign More</button>
              </td>
            </tr>
          </tbody>
        </table>
        
        <div class="action-area">
          <button class="btn btn-primary">Balance Workload</button>
          <button class="btn btn-secondary">Export Workload Report</button>
        </div>
      </div>

      <!-- Reassign Clients Tab -->
      <div class="tab-content" id="handled-reassign">
        <div class="assignment-form">
          <h3 class="section-heading">Reassign Client to Agent</h3>
          <form id="reassignForm">
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Select Client <span class="required">*</span></label>
                <select class="form-select" name="client_id" id="reassign_client_select" required>
                  <option value="">Choose client to reassign</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Current Agent</label>
                <input type="text" class="form-input" id="current_agent_display" placeholder="Current assigned agent" readonly>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">New Agent <span class="required">*</span></label>
                <select class="form-select" name="new_agent_id" required>
                  <option value="">Select new agent</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Reassignment Reason <span class="required">*</span></label>
                <select class="form-select" name="reason" required>
                  <option value="">Select reason</option>
                  <option value="workload">Workload Balancing</option>
                  <option value="expertise">Agent Expertise Match</option>
                  <option value="availability">Agent Availability</option>
                  <option value="performance">Performance Issues</option>
                  <option value="request">Client Request</option>
                </select>
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Additional Notes</label>
              <textarea class="form-textarea" name="notes" rows="3" placeholder="Enter any additional notes for the reassignment..."></textarea>
            </div>
            <div class="action-area">
              <button type="submit" class="btn btn-primary">Reassign Client</button>
              <button type="button" class="btn btn-secondary">Cancel</button>
            </div>
          </form>
        </div>

        <h3 class="section-heading">Recent Reassignments</h3>
        <table class="data-table">
          <thead>
            <tr>
              <th>Client</th>
              <th>From Agent</th>
              <th>To Agent</th>
              <th>Reassignment Date</th>
              <th>Reason</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>Michael Torres</td>
              <td>Maria Gonzales</td>
              <td>Carlos Mendoza</td>
              <td>Mar 22, 2024</td>
              <td>Agent Expertise Match</td>
              <td><span class="status completed">Completed</span></td>
              <td class="table-actions">
                <button class="btn btn-small btn-outline">View Details</button>
              </td>
            </tr>
            <tr>
              <td>Sarah Johnson</td>
              <td>Juan Reyes</td>
              <td>Ana Santos</td>
              <td>Mar 21, 2024</td>
              <td>Workload Balancing</td>
              <td><span class="status approved">Active</span></td>
              <td class="table-actions">
                <button class="btn btn-small btn-outline">View Details</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Performance Review Tab -->
      <div class="tab-content" id="handled-performance">
        <div class="info-cards">
          <div class="info-card">
            <div class="info-card-title">Overall Success Rate</div>
            <div class="info-card-value">84%</div>
          </div>
          <div class="info-card">
            <div class="info-card-title">Average Handle Time</div>
            <div class="info-card-value">15 days</div>
          </div>
          <div class="info-card">
            <div class="info-card-title">Client Satisfaction</div>
            <div class="info-card-value">4.2/5</div>
          </div>
          <div class="info-card">
            <div class="info-card-title">Reassignment Rate</div>
            <div class="info-card-value">8%</div>
          </div>
        </div>

        <h3 class="section-heading">Agent Performance Analysis</h3>
        <table class="data-table">
          <thead>
            <tr>
              <th>Agent</th>
              <th>Clients Handled</th>
              <th>Success Rate</th>
              <th>Avg. Handle Time</th>
              <th>Client Rating</th>
              <th>Reassignments</th>
              <th>Performance Grade</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>Carlos Mendoza</td>
              <td>156</td>
              <td><span class="status approved">92%</span></td>
              <td>12 days</td>
              <td>4.8/5</td>
              <td>2</td>
              <td><span class="status approved">A+</span></td>
              <td class="table-actions">
                <button class="btn btn-small btn-outline">Detailed Review</button>
                <button class="btn btn-small btn-primary">Commend</button>
              </td>
            </tr>
            <tr>
              <td>Ana Santos</td>
              <td>134</td>
              <td><span class="status approved">88%</span></td>
              <td>14 days</td>
              <td>4.5/5</td>
              <td>5</td>
              <td><span class="status approved">A</span></td>
              <td class="table-actions">
                <button class="btn btn-small btn-outline">Detailed Review</button>
                <button class="btn btn-small btn-primary">Commend</button>
              </td>
            </tr>
            <tr>
              <td>Juan Reyes</td>
              <td>89</td>
              <td><span class="status pending">75%</span></td>
              <td>18 days</td>
              <td>3.9/5</td>
              <td>12</td>
              <td><span class="status pending">B</span></td>
              <td class="table-actions">
                <button class="btn btn-small btn-outline">Detailed Review</button>
                <button class="btn btn-small btn-secondary">Training Plan</button>
              </td>
            </tr>
            <tr>
              <td>Maria Gonzales</td>
              <td>76</td>
              <td><span class="status pending">70%</span></td>
              <td>20 days</td>
              <td>3.7/5</td>
              <td>18</td>
              <td><span class="status overdue">C+</span></td>
              <td class="table-actions">
                <button class="btn btn-small btn-outline">Detailed Review</button>
                <button class="btn btn-small btn-secondary">Performance Plan</button>
              </td>
            </tr>
          </tbody>
        </table>
        
        <div class="action-area">
          <button class="btn btn-primary">Generate Performance Report</button>
          <button class="btn btn-secondary">Schedule Reviews</button>
          <button class="btn btn-outline">Export Analysis</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../../includes/js/common-scripts.js"></script>
  <script>
    // Global variables
    let clientsData = [];
    let agentsData = [];

    document.addEventListener('DOMContentLoaded', function() {
      // Initialize the page
      initializePage();
      
      // Tab navigation functionality
      document.querySelectorAll('.tab-button').forEach(function(button) {
        button.addEventListener('click', function() {
          // Remove active class from all buttons
          document.querySelectorAll('.tab-button').forEach(function(btn) {
            btn.classList.remove('active');
          });
          // Add active class to clicked button
          this.classList.add('active');
          
          // Get the target tab content id
          const tabId = this.getAttribute('data-tab');
          // Hide all tab contents
          document.querySelectorAll('.tab-content').forEach(function(tab) {
            tab.classList.remove('active');
          });
          // Show the target tab content
          document.getElementById(tabId).classList.add('active');
          
          // Load data for specific tabs
          if (tabId === 'handled-clients') {
            loadClientAssignments();
          } else if (tabId === 'handled-workload') {
            loadWorkloadStats();
          }
        });
      });

      // Form submission for reassignment
      document.getElementById('reassignForm').addEventListener('submit', function(e) {
        e.preventDefault();
        handleReassignment();
      });

      // Search and filter functionality
      const searchInput = document.querySelector('.search-input input');
      if (searchInput) {
        searchInput.addEventListener('input', debounce(loadClientAssignments, 300));
      }

      const filterSelects = document.querySelectorAll('.filter-select');
      filterSelects.forEach(select => {
        select.addEventListener('change', loadClientAssignments);
      });
    });

    function initializePage() {
      loadClientAssignments();
      loadAvailableAgents();
      loadWorkloadStats();
    }

    async function loadClientAssignments() {
      try {
        const search = document.querySelector('.search-input input')?.value || '';
        const agentFilter = document.querySelector('.filter-select')?.value || '';
        const statusFilter = document.querySelectorAll('.filter-select')[1]?.value || '';

        const params = new URLSearchParams({
          action: 'get_clients',
          search: search,
          agent: agentFilter,
          status: statusFilter
        });

        const response = await fetch(`../../api/client_management.php?${params}`);
        const result = await response.json();

        if (result.success) {
          clientsData = result.data;
          renderClientTable(result.data);
          updateClientSelect(result.data);
        } else {
          showError('Failed to load client assignments: ' + result.message);
        }
      } catch (error) {
        console.error('Error loading client assignments:', error);
        showError('Error loading client assignments');
      }
    }

    async function loadAvailableAgents() {
      try {
        const response = await fetch('../../api/client_management.php?action=get_agents');
        const result = await response.json();

        if (result.success) {
          agentsData = result.data;
          updateAgentSelects(result.data);
        }
      } catch (error) {
        console.error('Error loading agents:', error);
      }
    }

    async function loadWorkloadStats() {
      try {
        const response = await fetch('../../api/client_management.php?action=get_workload_stats');
        const result = await response.json();

        if (result.success) {
          renderWorkloadStats(result.data);
        }
      } catch (error) {
        console.error('Error loading workload stats:', error);
      }
    }

    function renderClientTable(clients) {
      const tbody = document.querySelector('#handled-clients .data-table tbody');
      if (!tbody) return;

      tbody.innerHTML = '';

      clients.forEach(client => {
        const row = document.createElement('tr');
        row.innerHTML = `
          <td>${client.client_name}<br><small>${client.client_code}</small></td>
          <td>${client.email}<br><small>${client.phone}</small></td>
          <td>Vehicle Interest<br><small>Status: ${client.status}</small></td>
          <td>${client.agent_name}<br><small>${client.agent_position}</small></td>
          <td>${formatDate(client.assignment_date)}</td>
          <td><span class="status ${getStatusClass(client.status)}">${client.status}</span></td>
          <td>${formatDate(client.last_activity)}</td>
          <td class="table-actions">
            <button class="btn btn-small btn-outline" onclick="viewClientDetails(${client.client_id})">View Details</button>
            ${getActionButton(client.status, client.client_id)}
          </td>
        `;
        tbody.appendChild(row);
      });
    }

    function getActionButton(status, clientId) {
      switch (status) {
        case 'Active':
          return `<button class="btn btn-small btn-primary" onclick="reassignClient(${clientId})">Reassign</button>`;
        case 'Completed':
          return `<button class="btn btn-small btn-secondary" onclick="archiveClient(${clientId})">Archive</button>`;
        case 'Pending':
          return `<button class="btn btn-small btn-primary" onclick="escalateClient(${clientId})">Escalate</button>`;
        default:
          return `<button class="btn btn-small btn-outline" onclick="viewClientDetails(${clientId})">View Details</button>`;
      }
    }

    function getStatusClass(status) {
      switch (status) {
        case 'Active': return 'approved';
        case 'Completed': return 'completed';
        case 'Pending': return 'pending';
        case 'Escalated': return 'overdue';
        case 'Archived': return 'secondary';
        default: return 'pending';
      }
    }

    function renderWorkloadStats(stats) {
      const workloadStats = document.querySelector('.workload-stats');
      if (!workloadStats) return;

      workloadStats.innerHTML = '';

      stats.forEach(stat => {
        const workloadClass = getWorkloadClass(stat.active_clients);
        const card = document.createElement('div');
        card.className = `workload-card ${workloadClass}`;
        card.innerHTML = `
          <div class="workload-value">${stat.active_clients}</div>
          <div class="workload-label">${stat.first_name} ${stat.last_name} - Active Clients</div>
        `;
        workloadStats.appendChild(card);
      });
    }

    function getWorkloadClass(activeClients) {
      if (activeClients >= 20) return 'high';
      if (activeClients >= 15) return 'medium';
      if (activeClients >= 10) return 'normal';
      return 'low';
    }

    function updateAgentSelects(agents) {
      const agentSelects = document.querySelectorAll('select[name="new_agent_id"]');
      agentSelects.forEach(select => {
        const currentValue = select.value;
        select.innerHTML = '<option value="">Select new agent</option>';
        
        agents.forEach(agent => {
          const option = document.createElement('option');
          option.value = agent.account_id;
          option.textContent = `${agent.first_name} ${agent.last_name} (${agent.active_clients} active clients)`;
          select.appendChild(option);
        });
        
        select.value = currentValue;
      });
    }

    function updateClientSelect(clients) {
      const clientSelect = document.getElementById('reassign_client_select');
      if (!clientSelect) return;

      const currentValue = clientSelect.value;
      clientSelect.innerHTML = '<option value="">Choose client to reassign</option>';
      
      clients.forEach(client => {
        const option = document.createElement('option');
        option.value = client.client_id;
        option.textContent = `${client.client_name} - ${client.agent_name} (${client.status})`;
        clientSelect.appendChild(option);
      });
      
      clientSelect.value = currentValue;
    }

    // Add event listener for client selection change
    document.addEventListener('DOMContentLoaded', function() {
      const clientSelect = document.getElementById('reassign_client_select');
      if (clientSelect) {
        clientSelect.addEventListener('change', function() {
          const selectedClientId = this.value;
          const selectedClient = clientsData.find(c => c.client_id == selectedClientId);
          
          const currentAgentDisplay = document.getElementById('current_agent_display');
          if (currentAgentDisplay && selectedClient) {
            currentAgentDisplay.value = selectedClient.agent_name;
          } else if (currentAgentDisplay) {
            currentAgentDisplay.value = '';
          }
        });
      }
    });

    async function viewClientDetails(clientId) {
      try {
        const response = await fetch(`../../api/client_management.php?action=get_client_details&client_id=${clientId}`);
        const result = await response.json();

        if (result.success) {
          showClientDetailsModal(result.data);
        } else {
          showError('Failed to load client details: ' + result.message);
        }
      } catch (error) {
        console.error('Error loading client details:', error);
        showError('Error loading client details');
      }
    }

    async function reassignClient(clientId) {
      const client = clientsData.find(c => c.client_id == clientId);
      if (!client) return;

      const newAgentId = prompt(`Reassign ${client.client_name} to which agent?`, '');
      if (!newAgentId) return;

      const reason = prompt('Reason for reassignment:', '');
      if (!reason) return;

      const notes = prompt('Additional notes (optional):', '');

      try {
        const response = await fetch('../../api/client_management.php?action=reassign_client', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            client_id: clientId,
            new_agent_id: newAgentId,
            reason: reason,
            notes: notes
          })
        });

        const result = await response.json();

        if (result.success) {
          showSuccess('Client successfully reassigned');
          loadClientAssignments();
        } else {
          showError('Failed to reassign client: ' + result.message);
        }
      } catch (error) {
        console.error('Error reassigning client:', error);
        showError('Error reassigning client');
      }
    }

    async function escalateClient(clientId) {
      const client = clientsData.find(c => c.client_id == clientId);
      if (!client) return;

      const reason = prompt('Reason for escalation:', '');
      if (!reason) return;

      const priority = prompt('Priority (Low/Medium/High/Critical):', 'High');
      const notes = prompt('Additional notes (optional):', '');

      try {
        const response = await fetch('../../api/client_management.php?action=escalate_client', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            client_id: clientId,
            escalation_reason: reason,
            priority: priority,
            notes: notes
          })
        });

        const result = await response.json();

        if (result.success) {
          showSuccess('Client successfully escalated');
          loadClientAssignments();
        } else {
          showError('Failed to escalate client: ' + result.message);
        }
      } catch (error) {
        console.error('Error escalating client:', error);
        showError('Error escalating client');
      }
    }

    async function archiveClient(clientId) {
      const client = clientsData.find(c => c.client_id == clientId);
      if (!client) return;

      if (!confirm(`Are you sure you want to archive ${client.client_name}?`)) return;

      const reason = prompt('Reason for archiving:', '');
      if (!reason) return;

      try {
        const response = await fetch('../../api/client_management.php?action=archive_client', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            client_id: clientId,
            archive_reason: reason
          })
        });

        const result = await response.json();

        if (result.success) {
          showSuccess('Client successfully archived');
          loadClientAssignments();
        } else {
          showError('Failed to archive client: ' + result.message);
        }
      } catch (error) {
        console.error('Error archiving client:', error);
        showError('Error archiving client');
      }
    }

    async function handleReassignment() {
      const form = document.getElementById('reassignForm');
      const formData = new FormData(form);
      
      const data = {
        client_id: formData.get('client_id'),
        new_agent_id: formData.get('new_agent_id'),
        reason: formData.get('reason'),
        notes: formData.get('notes')
      };

      try {
        const response = await fetch('../../api/client_management.php?action=reassign_client', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
          showSuccess('Client successfully reassigned');
          form.reset();
          loadClientAssignments();
        } else {
          showError('Failed to reassign client: ' + result.message);
        }
      } catch (error) {
        console.error('Error reassigning client:', error);
        showError('Error reassigning client');
      }
    }

    function showClientDetailsModal(client) {
      // Remove existing modal if any
      const existing = document.getElementById('cmClientDetailsModal');
      if (existing) existing.remove();

      const modalHtml = `
        <div class="cm-modal-overlay active" id="cmClientDetailsModal" role="dialog" aria-modal="true">
          <div class="cm-modal" role="document">
            <div class="cm-modal-header">
              <h3 class="cm-modal-title">Client Details - ${client.firstname} ${client.lastname}</h3>
              <button class="cm-modal-close" aria-label="Close" onclick="closeClientDetailsModal()">&times;</button>
            </div>
            <div class="cm-modal-body">
              <div class="row">
                <div class="col-md-6">
                  <div class="info-section">
                    <h6><i class="fas fa-user"></i> Personal Information</h6>
                    <p><strong>Full Name:</strong> ${client.firstname} ${client.lastname}</p>
                    <p><strong>Email:</strong> ${client.email || 'N/A'}</p>
                    <p><strong>Phone:</strong> ${client.mobile_number || 'N/A'}</p>
                    <p><strong>Status:</strong> <span class="status ${getStatusClass(client.status)}">${client.status}</span></p>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="info-section">
                    <h6><i class="fas fa-user-tie"></i> Assignment Information</h6>
                    <p><strong>Assigned Agent:</strong> ${client.agent_first_name} ${client.agent_last_name}</p>
                    <p><strong>Agent Position:</strong> ${client.agent_position}</p>
                    <p><strong>Assignment Date:</strong> ${formatDate(client.created_at)}</p>
                    <p><strong>Last Activity:</strong> ${formatDate(client.updated_at)}</p>
                  </div>
                </div>
              </div>
              <div class="row mt-3">
                <div class="col-12">
                  <div class="info-section">
                    <h6><i class="fas fa-comments"></i> Conversation History</h6>
                    ${client.conversations && client.conversations.length > 0 ? `
                      <div class="table-responsive">
                        <table class="table table-sm table-hover">
                          <thead>
                            <tr>
                              <th>Conversation ID</th>
                              <th>Status</th>
                              <th>Messages</th>
                              <th>Last Message</th>
                            </tr>
                          </thead>
                          <tbody>
                            ${client.conversations.map(conv => `
                              <tr>
                                <td>#${conv.conversation_id}</td>
                                <td><span class="status ${getStatusClass(conv.conversation_status)}">${conv.conversation_status}</span></td>
                                <td><i class="fas fa-envelope"></i> ${conv.message_count}</td>
                                <td>${formatDate(conv.last_message_at)}</td>
                              </tr>
                            `).join('')}
                          </tbody>
                        </table>
                      </div>
                    ` : `
                      <p style="text-align: center; color: var(--text-light); padding: 20px;">
                        <i class="fas fa-inbox" style="font-size: 48px; opacity: 0.3;"></i><br>
                        No conversation history available
                      </p>
                    `}
                  </div>
                </div>
              </div>
            </div>
            <div class="cm-modal-footer">
              <button type="button" class="btn btn-secondary" onclick="closeClientDetailsModal()">Close</button>
            </div>
          </div>
        </div>
      `;

      document.body.insertAdjacentHTML('beforeend', modalHtml);

      // Close on overlay click
      const overlay = document.getElementById('cmClientDetailsModal');
      overlay.addEventListener('click', function(e) {
        if (e.target === overlay) closeClientDetailsModal();
      });

      // Close on Escape
      document.addEventListener('keydown', escCloseHandler);
    }

    function escCloseHandler(e) {
      if (e.key === 'Escape') {
        closeClientDetailsModal();
      }
    }

    function closeClientDetailsModal() {
      const overlay = document.getElementById('cmClientDetailsModal');
      if (overlay) overlay.remove();
      document.removeEventListener('keydown', escCloseHandler);
    }

    function formatDate(dateString) {
      if (!dateString) return 'N/A';
      const date = new Date(dateString);
      return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
    }

    function showSuccess(message) {
      // You can replace this with your preferred notification system
      alert('Success: ' + message);
    }

    function showError(message) {
      // You can replace this with your preferred notification system
      alert('Error: ' + message);
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
  </script>
</body>
</html>
