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
      zoom:85%;
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

    /* Agent Autocomplete Styles */
    .agent-autocomplete-wrapper {
      position: relative;
      min-width: 250px;
    }

    .agent-input-container {
      position: relative;
      display: flex;
      align-items: center;
      background-color: white;
      border: 1px solid var(--border-light);
      border-radius: 8px;
      padding: 10px 15px;
      transition: border-color 0.3s ease;
    }

    .agent-input-container:focus-within {
      border-color: var(--primary-red);
      box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
    }

    .agent-input-container i.fa-user-tie {
      color: var(--text-light);
      margin-right: 10px;
      font-size: 14px;
    }

    .agent-filter-input {
      flex: 1;
      border: none;
      outline: none;
      font-size: 14px;
      color: var(--text-dark);
      background: transparent;
    }

    .agent-filter-input::placeholder {
      color: var(--text-light);
    }

    .clear-agent-btn {
      background: none;
      border: none;
      color: var(--text-light);
      cursor: pointer;
      padding: 0 5px;
      margin-left: 5px;
      font-size: 14px;
      transition: color 0.2s ease;
    }

    .clear-agent-btn:hover {
      color: var(--primary-red);
    }

    .agent-suggestions {
      position: absolute;
      top: calc(100% + 5px);
      left: 0;
      right: 0;
      background: white;
      border: 1px solid var(--border-light);
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      max-height: 300px;
      overflow-y: auto;
      z-index: 1000;
      display: none;
    }

    .agent-suggestions.show {
      display: block;
    }

    .agent-suggestion-item {
      padding: 12px 15px;
      cursor: pointer;
      transition: background-color 0.2s ease;
      border-bottom: 1px solid var(--border-light);
    }

    .agent-suggestion-item:last-child {
      border-bottom: none;
    }

    .agent-suggestion-item:hover {
      background-color: #f9fafb;
    }

    .agent-suggestion-item.all-agents {
      font-weight: 600;
      color: var(--primary-red);
    }

    .agent-name {
      font-weight: 500;
      color: var(--text-dark);
      display: block;
    }

    .agent-clients {
      font-size: 12px;
      color: var(--text-light);
      margin-top: 2px;
    }

    .no-suggestions {
      padding: 12px 15px;
      color: var(--text-light);
      text-align: center;
      font-size: 14px;
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

    /* Align table cells consistently */
    .data-table td {
      vertical-align: middle;
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



      <!-- Client-Agent Assignments -->
      <div id="handled-clients">
        <div class="info-cards">
          <div class="info-card">
            <div class="info-card-title">Total Client Assignments</div>
            <div class="info-card-value" id="total-assignments">-</div>
          </div>
          <div class="info-card">
            <div class="info-card-title">Active Clients</div>
            <div class="info-card-value" id="active-clients">-</div>
          </div>
          <div class="info-card">
            <div class="info-card-title">Avg. Handle Time</div>
            <div class="info-card-value" id="avg-handle-time">-</div>
          </div>
        </div>

        <div class="filter-bar">
          <div class="search-input">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Search Email">
          </div>
          <div class="agent-autocomplete-wrapper">
            <div class="agent-input-container">
              <i class="fas fa-user-tie"></i>
              <input type="text" id="agent-filter-input" class="agent-filter-input" placeholder="Search agents..." autocomplete="off">
              <button type="button" id="clear-agent-filter" class="clear-agent-btn" style="display: none;">
                <i class="fas fa-times"></i>
              </button>
            </div>
            <div id="agent-suggestions" class="agent-suggestions"></div>
            <input type="hidden" id="agent-filter" value="">
          </div>
          <select id="status-filter" class="filter-select">
            <option value="">All Status</option>
            <option value="Active">Active</option>
            <option value="Completed">Completed</option>
            <option value="Pending">Pending</option>
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
            </tr>
            <tr>
              <td>Maria Santos<br><small>CL-2024-002</small></td>
              <td>maria@email.com<br><small>+63 917 234 5678</small></td>
              <td>Xpander GLS AT<br><small>Test drive completed</small></td>
              <td>Ana Santos<br><small>Sales Agent</small></td>
              <td>Mar 10, 2024</td>
              <td><span class="status completed">Sale Completed</span></td>
              <td>Mar 22, 2024</td>
            </tr>
            <tr>
              <td>Robert Cruz<br><small>CL-2024-003</small></td>
              <td>robert@email.com<br><small>+63 917 345 6789</small></td>
              <td>Mirage G4 GLS<br><small>Price negotiation</small></td>
              <td>Juan Reyes<br><small>Junior Agent</small></td>
              <td>Mar 20, 2024</td>
              <td><span class="status pending">Follow-up Required</span></td>
              <td>Mar 21, 2024</td>
            </tr>
          </tbody>
        </table>
        
        <div class="action-area">
          <button class="btn btn-primary">Export Client List</button>
          <button class="btn btn-secondary">Generate Assignment Report</button>
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
    let selectedAgentId = '';

    document.addEventListener('DOMContentLoaded', function() {
      // Initialize the page
      initializePage();

      // Search and filter functionality
      const searchInput = document.querySelector('.search-input input');
      if (searchInput) {
        searchInput.addEventListener('input', debounce(loadClientAssignments, 300));
      }

      const filterSelects = document.querySelectorAll('.filter-select');
      filterSelects.forEach(select => {
        select.addEventListener('change', loadClientAssignments);
      });

      // Agent autocomplete functionality
      const agentInput = document.getElementById('agent-filter-input');
      const agentSuggestions = document.getElementById('agent-suggestions');
      const clearAgentBtn = document.getElementById('clear-agent-filter');

      // Show suggestions on focus
      agentInput.addEventListener('focus', function() {
        if (agentsData.length > 0) {
          showAgentSuggestions('');
        }
      });

      // Filter suggestions as user types (with debounce)
      agentInput.addEventListener('input', debounce(function() {
        const query = agentInput.value.trim();
        showAgentSuggestions(query);

        // Show/hide clear button
        clearAgentBtn.style.display = query ? 'block' : 'none';
      }, 300));

      // Clear agent filter
      clearAgentBtn.addEventListener('click', function() {
        agentInput.value = '';
        selectedAgentId = '';
        document.getElementById('agent-filter').value = '';
        clearAgentBtn.style.display = 'none';
        agentSuggestions.classList.remove('show');
        loadClientAssignments();
      });

      // Close suggestions when clicking outside
      document.addEventListener('click', function(e) {
        if (!e.target.closest('.agent-autocomplete-wrapper')) {
          agentSuggestions.classList.remove('show');
        }
      });
    });

    function initializePage() {
      loadSummaryStats();
      loadAgents();
      loadClientAssignments();
    }

    async function loadSummaryStats() {
      try {
        const response = await fetch('../../api/client_management.php?action=get_summary_stats');
        const result = await response.json();

        if (result.success) {
          const stats = result.data;

          // Update the info cards with real data
          document.getElementById('total-assignments').textContent = stats.total_assignments.toLocaleString();
          document.getElementById('active-clients').textContent = stats.active_clients.toLocaleString();
          document.getElementById('avg-handle-time').textContent = stats.avg_handle_time + ' days';
        }
      } catch (error) {
        console.error('Error loading summary stats:', error);
        // Keep the default "-" values on error
      }
    }

    async function loadAgents() {
      try {
        const response = await fetch('../../api/client_management.php?action=get_agents');
        const result = await response.json();

        if (result.success) {
          // Store agents data globally for autocomplete
          agentsData = result.data;
        }
      } catch (error) {
        console.error('Error loading agents:', error);
      }
    }

    function showAgentSuggestions(query) {
      const agentSuggestions = document.getElementById('agent-suggestions');
      const lowerQuery = query.toLowerCase().trim();

      // Filter agents based on query
      let filteredAgents = agentsData;
      if (query) {
        filteredAgents = agentsData.filter(agent => {
          const fullName = `${agent.first_name} ${agent.last_name}`.toLowerCase();
          const firstName = agent.first_name.toLowerCase();
          const lastName = agent.last_name.toLowerCase();

          // Match full name, first name, last name, or any part
          return fullName.includes(lowerQuery) ||
                 firstName.includes(lowerQuery) ||
                 lastName.includes(lowerQuery) ||
                 fullName.replace(/\s+/g, '').includes(lowerQuery.replace(/\s+/g, ''));
        });
      }

      // Build suggestions HTML
      let html = '';

      // Always show "All Agents" option if no query or if "all" matches
      if (!query || 'all agents'.includes(lowerQuery)) {
        html += `
          <div class="agent-suggestion-item all-agents" data-agent-id="" data-agent-name="All Agents">
            <span class="agent-name">All Agents</span>
          </div>
        `;
      }

      // Show filtered agents
      if (filteredAgents.length > 0) {
        filteredAgents.forEach(agent => {
          const fullName = `${agent.first_name} ${agent.last_name}`;
          const clientInfo = agent.active_clients > 0 ? `${agent.active_clients} active client${agent.active_clients !== 1 ? 's' : ''}` : 'No active clients';

          html += `
            <div class="agent-suggestion-item" data-agent-id="${agent.account_id}" data-agent-name="${fullName}">
              <span class="agent-name">${fullName}</span>
              <span class="agent-clients">${clientInfo}</span>
            </div>
          `;
        });
      } else if (query && !html) {
        html += `<div class="no-suggestions">No agents found matching "${query}"</div>`;
      }

      // If no results at all, show message
      if (!html) {
        html = `<div class="no-suggestions">No agents available</div>`;
      }

      agentSuggestions.innerHTML = html;
      agentSuggestions.classList.add('show');

      // Add click handlers to suggestion items
      const suggestionItems = agentSuggestions.querySelectorAll('.agent-suggestion-item');
      suggestionItems.forEach(item => {
        item.addEventListener('click', function() {
          const agentId = this.getAttribute('data-agent-id');
          const agentName = this.getAttribute('data-agent-name');

          // Update input and hidden field
          document.getElementById('agent-filter-input').value = agentName;
          document.getElementById('agent-filter').value = agentId;
          selectedAgentId = agentId;

          // Show/hide clear button
          document.getElementById('clear-agent-filter').style.display = agentName !== 'All Agents' ? 'block' : 'none';

          // Hide suggestions
          agentSuggestions.classList.remove('show');

          // Load filtered clients
          loadClientAssignments();
        });
      });
    }

    async function loadClientAssignments() {
      try {
        const search = document.querySelector('.search-input input')?.value || '';
        const agentFilter = document.getElementById('agent-filter')?.value || '';
        const statusFilter = document.getElementById('status-filter')?.value || '';

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
        } else {
          showError('Failed to load client assignments: ' + result.message);
        }
      } catch (error) {
        console.error('Error loading client assignments:', error);
        showError('Error loading client assignments');
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
        `;
        tbody.appendChild(row);
      });
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
