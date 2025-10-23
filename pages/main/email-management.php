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

// Check if user has permission to access email management (Admin or Sales Agent only)
if ($user_role !== 'Admin' && $user_role !== 'SalesAgent') {
  header("Location: dashboard.php");
  exit();
}

// Handle AJAX requests for email sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  header('Content-Type: application/json');
  
  if ($_POST['action'] === 'send_email') {
    try {
      $recipient = $_POST['recipient'] ?? '';
      $subject = $_POST['subject'] ?? '';
      $message = $_POST['message'] ?? '';
      $email_type = $_POST['email_type'] ?? 'general';
      
      // Basic validation
      if (empty($recipient) || empty($subject) || empty($message)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit();
      }
      
      if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email address']);
        exit();
      }
      
      // Here you would integrate with your email service
      // For now, we'll simulate success
      
      // Log the email attempt
      $log_sql = "INSERT INTO email_logs (sender_id, recipient, subject, message, email_type, sent_at, status) 
                  VALUES (?, ?, ?, ?, ?, NOW(), 'sent')";
      
      // Note: You'll need to create the email_logs table
      // For now, we'll just return success
      
      echo json_encode([
        'success' => true, 
        'message' => 'Email sent successfully to ' . $recipient
      ]);
      exit();
      
    } catch (Exception $e) {
      error_log("Email sending error: " . $e->getMessage());
      echo json_encode(['success' => false, 'message' => 'Failed to send email']);
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
  <title>Email Management - Mitsubishi Dashboard</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link href="../../includes/css/common-styles.css" rel="stylesheet">
  <link rel="stylesheet" href="../../includes/css/dashboard-styles.css">
  <link href="../../includes/css/admin-styles.css" rel="stylesheet">
  <link href="../../includes/css/notification-styles.css" rel="stylesheet">
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
        <h1>Email Management</h1>
        <p>Send emails to customers and manage communication</p>
      </div>

      <!-- Email Dashboard Cards -->
      <div class="dashboard-grid">
        <div class="dashboard-card" onclick="showEmailInterface('compose')">
          <div class="card-header">
            <div class="card-icon red">
              <i class="fas fa-envelope"></i>
            </div>
            <div class="card-title">Compose Email</div>
          </div>
          <p>Send new emails to customers and prospects.</p>
        </div>

        <div class="dashboard-card" onclick="showEmailInterface('templates')">
          <div class="card-header">
            <div class="card-icon blue">
              <i class="fas fa-file-alt"></i>
            </div>
            <div class="card-title">Email Templates</div>
          </div>
          <p>Use pre-designed templates for common communications.</p>
        </div>

        <div class="dashboard-card" onclick="showEmailInterface('history')">
          <div class="card-header">
            <div class="card-icon green">
              <i class="fas fa-history"></i>
            </div>
            <div class="card-title">Email History</div>
          </div>
          <p>View sent emails and delivery status.</p>
        </div>
      </div>

      <!-- Email Compose Interface -->
      <div id="composeInterface" class="interface-container">
        <div class="interface-header">
          <h2 class="interface-title">
            <i class="fas fa-envelope"></i>
            Compose Email
          </h2>
          <button class="interface-close" onclick="hideEmailInterface()">
            <i class="fas fa-times"></i>
          </button>
        </div>

        <form id="emailForm" class="email-form">
          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="emailType">
                Email Type <span class="required">*</span>
              </label>
              <select id="emailType" name="email_type" class="form-select" required>
                <option value="">Select email type</option>
                <option value="inquiry_response">Inquiry Response</option>
                <option value="quote_follow_up">Quote Follow-up</option>
                <option value="test_drive_confirmation">Test Drive Confirmation</option>
                <option value="payment_reminder">Payment Reminder</option>
                <option value="promotional">Promotional</option>
                <option value="general">General Communication</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label" for="priority">
                Priority
              </label>
              <select id="priority" name="priority" class="form-select">
                <option value="normal">Normal</option>
                <option value="high">High</option>
                <option value="urgent">Urgent</option>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="recipient">
              Recipient Email <span class="required">*</span>
            </label>
            <div class="searchable-dropdown-container">
              <input type="email" id="recipient" name="recipient" class="form-input searchable-input" 
                     placeholder="Type to search customers or enter email..." required autocomplete="off">
              <div id="customerDropdown" class="customer-dropdown" style="display: none;">
                <div class="dropdown-loading">Loading customers...</div>
              </div>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="subject">
              Subject <span class="required">*</span>
            </label>
            <input type="text" id="subject" name="subject" class="form-input" 
                   placeholder="Enter email subject" required>
          </div>

          <div class="form-group">
            <label class="form-label" for="message">
              Message <span class="required">*</span>
            </label>
            <textarea id="message" name="message" class="form-textarea" rows="8" 
                      placeholder="Enter your message here..." required></textarea>
          </div>

          <div class="form-group">
            <label class="form-label">
              <input type="checkbox" id="saveTemplate" name="save_template">
              Save as template for future use
            </label>
          </div>

          <div class="action-area">
            <button type="button" class="btn btn-secondary" onclick="hideEmailInterface()">
              <i class="fas fa-times"></i> Cancel
            </button>
            <button type="button" class="btn btn-outline" onclick="previewEmail()">
              <i class="fas fa-eye"></i> Preview
            </button>
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-paper-plane"></i> Send Email
            </button>
          </div>
        </form>
      </div>

      <!-- Email Templates Interface -->
      <div id="templatesInterface" class="interface-container">
        <div class="interface-header">
          <h2 class="interface-title">
            <i class="fas fa-file-alt"></i>
            Email Templates
          </h2>
          <button class="interface-close" onclick="hideEmailInterface()">
            <i class="fas fa-times"></i>
          </button>
        </div>

        <div class="templates-grid">
          <div class="template-card" onclick="useTemplate('inquiry_response')">
            <div class="template-icon">
              <i class="fas fa-question-circle"></i>
            </div>
            <h3>Inquiry Response</h3>
            <p>Standard response to customer inquiries</p>
          </div>

          <div class="template-card" onclick="useTemplate('quote_follow_up')">
            <div class="template-icon">
              <i class="fas fa-calculator"></i>
            </div>
            <h3>Quote Follow-up</h3>
            <p>Follow up on vehicle quotes and pricing</p>
          </div>

          <div class="template-card" onclick="useTemplate('test_drive_confirmation')">
            <div class="template-icon">
              <i class="fas fa-car"></i>
            </div>
            <h3>Test Drive Confirmation</h3>
            <p>Confirm test drive appointments</p>
          </div>

          <div class="template-card" onclick="useTemplate('payment_reminder')">
            <div class="template-icon">
              <i class="fas fa-credit-card"></i>
            </div>
            <h3>Payment Reminder</h3>
            <p>Remind customers about pending payments</p>
          </div>
        </div>
      </div>

      <!-- Email History Interface -->
      <div id="historyInterface" class="interface-container">
        <div class="interface-header">
          <h2 class="interface-title">
            <i class="fas fa-history"></i>
            Email History
          </h2>
          <button class="interface-close" onclick="hideEmailInterface()">
            <i class="fas fa-times"></i>
          </button>
        </div>

        <div class="filter-bar">
          <div class="search-input">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Search emails..." id="emailSearch">
          </div>
          <select class="form-select" id="statusFilter">
            <option value="">All Status</option>
            <option value="sent">Sent</option>
            <option value="delivered">Delivered</option>
            <option value="failed">Failed</option>
          </select>
        </div>

        <div class="table-container">
          <table class="data-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Recipient</th>
                <th>Subject</th>
                <th>Type</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="emailHistoryTable">
              <!-- Email history will be loaded dynamically -->
            </tbody>
          </table>
        </div>
      </div>

      <!-- Email Preview Modal -->
      <div id="emailPreviewModal" class="modal" style="display: none;">
        <div class="modal-content">
          <div class="modal-header">
            <h3>Email Preview</h3>
            <button class="modal-close" onclick="closePreview()">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <div class="modal-body">
            <div class="preview-field">
              <strong>To:</strong> <span id="previewRecipient"></span>
            </div>
            <div class="preview-field">
              <strong>Subject:</strong> <span id="previewSubject"></span>
            </div>
            <div class="preview-field">
              <strong>Message:</strong>
              <div id="previewMessage" class="preview-message"></div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closePreview()">Close</button>
            <button class="btn btn-primary" onclick="sendEmailFromPreview()">Send Email</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="../../includes/js/common-scripts.js"></script>
  <script>
    // Email Management JavaScript
    function showEmailInterface(type) {
      // Hide all interfaces first
      document.querySelectorAll('.interface-container').forEach(container => {
        container.style.display = 'none';
      });
      
      // Show the selected interface
      const interfaceId = type + 'Interface';
      const interface = document.getElementById(interfaceId);
      if (interface) {
        interface.style.display = 'block';
        interface.scrollIntoView({ behavior: 'smooth' });
      }
    }

    function hideEmailInterface() {
      document.querySelectorAll('.interface-container').forEach(container => {
        container.style.display = 'none';
      });
    }

    // Subject templates for each email type
    const subjectTemplates = {
      'inquiry_response': 'Thank you for your inquiry - Mitsubishi Motors',
      'quote_follow_up': 'Follow-up on Your Vehicle Quote - Mitsubishi Motors',
      'test_drive_confirmation': 'Test Drive Appointment Confirmation - Mitsubishi Motors',
      'payment_reminder': 'Payment Reminder - Mitsubishi Motors',
      'promotional': 'Special Offer from Mitsubishi Motors',
      'general': 'Message from Mitsubishi Motors'
    };

    // Function to prefill subject based on email type
    function prefillSubject(emailType) {
      const subjectField = document.getElementById('subject');
      if (emailType && subjectTemplates[emailType]) {
        subjectField.value = subjectTemplates[emailType];
      } else {
        subjectField.value = '';
      }
    }

    function useTemplate(templateType) {
      const templates = {
        'inquiry_response': {
          subject: 'Thank you for your inquiry - Mitsubishi Motors',
          message: 'Dear Valued Customer,\n\nThank you for your interest in Mitsubishi Motors. We have received your inquiry and one of our sales representatives will contact you within 24 hours.\n\nBest regards,\nMitsubishi Motors Team'
        },
        'quote_follow_up': {
          subject: 'Follow-up on Your Vehicle Quote - Mitsubishi Motors',
          message: 'Dear Customer,\n\nWe wanted to follow up on the vehicle quote we provided. If you have any questions or would like to proceed with your purchase, please don\'t hesitate to contact us.\n\nBest regards,\nMitsubishi Motors Team'
        },
        'test_drive_confirmation': {
          subject: 'Test Drive Appointment Confirmation - Mitsubishi Motors',
          message: 'Dear Customer,\n\nYour test drive appointment has been confirmed. Please bring a valid driver\'s license and arrive 15 minutes early.\n\nBest regards,\nMitsubishi Motors Team'
        },
        'payment_reminder': {
          subject: 'Payment Reminder - Mitsubishi Motors',
          message: 'Dear Customer,\n\nThis is a friendly reminder about your upcoming payment. Please contact us if you have any questions.\n\nBest regards,\nMitsubishi Motors Team'
        }
      };

      const template = templates[templateType];
      if (template) {
        document.getElementById('subject').value = template.subject;
        document.getElementById('message').value = template.message;
        document.getElementById('emailType').value = templateType;
        showEmailInterface('compose');
      }
    }

    function previewEmail() {
      const recipient = document.getElementById('recipient').value;
      const subject = document.getElementById('subject').value;
      const message = document.getElementById('message').value;

      if (!recipient || !subject || !message) {
        showWarning('Please fill in all required fields before previewing.');
        return;
      }

      document.getElementById('previewRecipient').textContent = recipient;
      document.getElementById('previewSubject').textContent = subject;
      document.getElementById('previewMessage').textContent = message;
      document.getElementById('emailPreviewModal').style.display = 'flex';
    }

    function closePreview() {
      document.getElementById('emailPreviewModal').style.display = 'none';
    }

    function sendEmailFromPreview() {
      closePreview();
      document.getElementById('emailForm').dispatchEvent(new Event('submit'));
    }

    // Handle email form submission
    document.getElementById('emailForm').addEventListener('submit', function(e) {
      e.preventDefault();
      
      const formData = new FormData();
      formData.append('action', 'send_email');
      formData.append('recipient', document.getElementById('recipient').value);
      formData.append('subject', document.getElementById('subject').value);
      formData.append('message', document.getElementById('message').value);
      formData.append('email_type', document.getElementById('emailType').value);
      formData.append('priority', document.getElementById('priority').value);
      formData.append('save_template', document.getElementById('saveTemplate').checked ? '1' : '0');
      
      // Show loading state
      const submitBtn = this.querySelector('button[type="submit"]');
      const originalText = submitBtn.innerHTML;
      submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
      submitBtn.disabled = true;
      
      fetch('../../api/send_email_api.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          showNotification('Email sent successfully!', 'success');
          this.reset();
          hideEmailInterface();
          loadEmailHistory(); // Refresh history
        } else {
          showNotification('Error: ' + data.message, 'error');
        }
      })
      .catch(error => {
        console.error('Error:', error);
        showNotification('An error occurred while sending the email.', 'error');
      })
      .finally(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
      });
    });

    // Load email history when history interface is shown
    function loadEmailHistory() {
      fetch('../../api/email_history_api.php?action=get_history')
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            updateEmailHistoryTable(data.data);
          } else {
            console.error('Error loading email history:', data.message);
          }
        })
        .catch(error => {
          console.error('Error:', error);
        });
    }

    // Update email history table
    function updateEmailHistoryTable(emails) {
      const tbody = document.getElementById('emailHistoryTable');
      tbody.innerHTML = '';
      
      if (emails.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: #666;">No emails found</td></tr>';
        return;
      }
      
      emails.forEach(email => {
        const row = document.createElement('tr');
        row.innerHTML = `
          <td>${email.formatted_date}</td>
          <td>${email.recipient}</td>
          <td>${email.subject}</td>
          <td>${email.email_type.replace('_', ' ').toUpperCase()}</td>
          <td><span class="status ${email.status}">${email.status.toUpperCase()}</span></td>
          <td>
            <button class="btn btn-small btn-outline" onclick="viewEmailDetails(${email.id})" title="View Details">
              <i class="fas fa-eye"></i>
            </button>
            <button class="btn btn-small btn-outline" onclick="deleteEmail(${email.id})" title="Delete">
              <i class="fas fa-trash"></i>
            </button>
          </td>
        `;
        tbody.appendChild(row);
      });
    }

    // Show notification
    function showNotification(message, type = 'info') {
      // Create notification element
      const notification = document.createElement('div');
      notification.className = `notification notification-${type}`;
      notification.innerHTML = `
        <span>${message}</span>
        <button onclick="this.parentElement.remove()" class="notification-close">
          <i class="fas fa-times"></i>
        </button>
      `;
      
      // Add to page
      document.body.appendChild(notification);
      
      // Auto remove after 5 seconds
      setTimeout(() => {
        if (notification.parentElement) {
          notification.remove();
        }
      }, 5000);
    }

    function viewEmailDetails(emailId) {
      fetch(`../../api/email_history_api.php?action=get_email_details&email_id=${emailId}`)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            showEmailDetailsModal(data.data);
          } else {
            showNotification('Error: ' + data.message, 'error');
          }
        })
        .catch(error => {
          console.error('Error:', error);
          showNotification('Error loading email details', 'error');
        });
    }

    function showEmailDetailsModal(email) {
      const modal = document.createElement('div');
      modal.className = 'modal';
      modal.innerHTML = `
        <div class="modal-content">
          <div class="modal-header">
            <h3>Email Details</h3>
            <button class="modal-close" onclick="this.closest('.modal').remove()">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <div class="modal-body">
            <div class="email-details">
              <div class="detail-row">
                <strong>From:</strong> ${email.sender_name}
              </div>
              <div class="detail-row">
                <strong>To:</strong> ${email.recipient}
              </div>
              <div class="detail-row">
                <strong>Subject:</strong> ${email.subject}
              </div>
              <div class="detail-row">
                <strong>Type:</strong> ${email.email_type.replace('_', ' ').toUpperCase()}
              </div>
              <div class="detail-row">
                <strong>Priority:</strong> ${email.priority.toUpperCase()}
              </div>
              <div class="detail-row">
                <strong>Sent:</strong> ${email.formatted_date}
              </div>
              <div class="detail-row">
                <strong>Status:</strong> <span class="status ${email.status}">${email.status.toUpperCase()}</span>
              </div>
              ${email.error_message ? `<div class="detail-row"><strong>Error:</strong> <span style="color: #dc143c;">${email.error_message}</span></div>` : ''}
              <div class="detail-row">
                <strong>Message:</strong>
                <div class="email-message-content">${email.message.replace(/\n/g, '<br>')}</div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" onclick="this.closest('.modal').remove()">Close</button>
          </div>
        </div>
      `;
      
      document.body.appendChild(modal);
      modal.style.display = 'flex';
    }

    function deleteEmail(emailId) {
      if (!confirm('Are you sure you want to delete this email?')) {
        return;
      }
      
      const formData = new FormData();
      formData.append('action', 'delete_email');
      formData.append('email_id', emailId);
      
      fetch('../../api/email_history_api.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          showNotification('Email deleted successfully', 'success');
          loadEmailHistory(); // Refresh the table
        } else {
          showNotification('Error: ' + data.message, 'error');
        }
      })
      .catch(error => {
        console.error('Error:', error);
        showNotification('Error deleting email', 'error');
      });
    }

    // Load email history when the history interface is shown
    const originalShowEmailInterface = showEmailInterface;
    showEmailInterface = function(type) {
      originalShowEmailInterface(type);
      if (type === 'history') {
        loadEmailHistory();
      }
    };

    // Add event listener for email type dropdown to prefill subject
    document.addEventListener('DOMContentLoaded', function() {
      const emailTypeSelect = document.getElementById('emailType');
      if (emailTypeSelect) {
        emailTypeSelect.addEventListener('change', function() {
          prefillSubject(this.value);
        });
      }
      
      // Load customer emails when page loads
      loadCustomerEmails();
      
      // Add searchable dropdown functionality
      const recipientInput = document.getElementById('recipient');
      const customerDropdown = document.getElementById('customerDropdown');
      let customers = [];
      let selectedIndex = -1;
      
      if (recipientInput) {
        // Show dropdown on focus
        recipientInput.addEventListener('focus', function() {
          if (customers.length > 0) {
            // Show all customers when focusing on empty field
            filterAndShowCustomers(this.value.toLowerCase());
          }
        });
        
        // Filter customers as user types
        recipientInput.addEventListener('input', function() {
          const searchTerm = this.value.toLowerCase();
          filterAndShowCustomers(searchTerm);
          selectedIndex = -1;
        });
        
        // Handle keyboard navigation
        recipientInput.addEventListener('keydown', function(e) {
          const items = customerDropdown.querySelectorAll('.dropdown-item:not(.dropdown-loading):not(.dropdown-no-results)');
          
          if (e.key === 'ArrowDown') {
            e.preventDefault();
            selectedIndex = Math.min(selectedIndex + 1, items.length - 1);
            updateHighlight(items);
          } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            selectedIndex = Math.max(selectedIndex - 1, -1);
            updateHighlight(items);
          } else if (e.key === 'Enter') {
            e.preventDefault();
            if (selectedIndex >= 0 && items[selectedIndex]) {
              selectCustomer(items[selectedIndex]);
            }
          } else if (e.key === 'Escape') {
            hideDropdown();
          }
        });
        
        // Hide dropdown when clicking outside
        document.addEventListener('click', function(e) {
          if (!recipientInput.contains(e.target) && !customerDropdown.contains(e.target)) {
            hideDropdown();
          }
        });
      }
    });

    // Function to load customer emails from API
    function loadCustomerEmails() {
      fetch('../../api/get_customer_emails.php')
        .then(response => response.json())
        .then(data => {
          if (data.success && data.data) {
            customers = data.data;
            const customerDropdown = document.getElementById('customerDropdown');
            customerDropdown.innerHTML = '<div class="dropdown-loading">Customers loaded. Start typing to search...</div>';
          } else {
            customers = [];
            const customerDropdown = document.getElementById('customerDropdown');
            customerDropdown.innerHTML = '<div class="dropdown-no-results">No customers found</div>';
          }
        })
        .catch(error => {
          console.error('Error loading customer emails:', error);
          customers = [];
          const customerDropdown = document.getElementById('customerDropdown');
          customerDropdown.innerHTML = '<div class="dropdown-no-results">Error loading customers</div>';
        });
    }

    // Function to filter and show customers based on search term
    function filterAndShowCustomers(searchTerm) {
      const customerDropdown = document.getElementById('customerDropdown');
      
      if (!searchTerm) {
        // Show all customers when search term is empty
        if (customers.length > 0) {
          customerDropdown.innerHTML = customers.map(customer => 
            `<div class="dropdown-item" data-email="${customer.email}" data-name="${customer.full_name}">
              <div class="customer-name">${customer.full_name}</div>
              <div class="customer-email">${customer.email}</div>
            </div>`
          ).join('');
          
          // Add click handlers to dropdown items
          customerDropdown.querySelectorAll('.dropdown-item').forEach(item => {
            item.addEventListener('click', function() {
              selectCustomer(this);
            });
          });
          
          showDropdown();
        } else {
          customerDropdown.innerHTML = '<div class="dropdown-no-results">No customers available</div>';
          showDropdown();
        }
        return;
      }
      
      const filteredCustomers = customers.filter(customer => 
        customer.full_name.toLowerCase().includes(searchTerm) || 
        customer.email.toLowerCase().includes(searchTerm)
      );
      
      if (filteredCustomers.length > 0) {
        customerDropdown.innerHTML = filteredCustomers.map(customer => 
          `<div class="dropdown-item" data-email="${customer.email}" data-name="${customer.full_name}">
            <div class="customer-name">${customer.full_name}</div>
            <div class="customer-email">${customer.email}</div>
          </div>`
        ).join('');
        
        // Add click handlers to dropdown items
        customerDropdown.querySelectorAll('.dropdown-item').forEach(item => {
          item.addEventListener('click', function() {
            selectCustomer(this);
          });
        });
        
        showDropdown();
      } else {
        customerDropdown.innerHTML = '<div class="dropdown-no-results">No customers found</div>';
        showDropdown();
      }
    }
    
    // Function to select a customer
    function selectCustomer(item) {
      const email = item.dataset.email;
      const name = item.dataset.name;
      
      document.getElementById('recipient').value = email;
      hideDropdown();
    }
    
    // Function to show dropdown
    function showDropdown() {
      document.getElementById('customerDropdown').style.display = 'block';
    }
    
    // Function to hide dropdown
    function hideDropdown() {
      document.getElementById('customerDropdown').style.display = 'none';
      selectedIndex = -1;
    }
    
    // Function to update keyboard navigation highlight
    function updateHighlight(items) {
      items.forEach((item, index) => {
        if (index === selectedIndex) {
          item.classList.add('highlighted');
        } else {
          item.classList.remove('highlighted');
        }
      });
    }
  </script>

  <style>
    /* Email Management Specific Styles */
    .email-form {
      max-width: 800px;
    }
    
    .searchable-dropdown-container {
      position: relative;
    }
    
    .searchable-input {
      width: 100%;
      font-size: 14px;
      padding: 8px 12px;
      border: 1px solid #ddd;
      border-radius: 4px;
      background-color: white;
    }
    
    .searchable-input:focus {
      outline: none;
      border-color: #007bff;
      box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
    }
    
    .customer-dropdown {
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      background: white;
      border: 1px solid #ddd;
      border-top: none;
      border-radius: 0 0 4px 4px;
      max-height: 200px;
      overflow-y: auto;
      z-index: 1000;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }
    
    .dropdown-item {
      padding: 10px 12px;
      cursor: pointer;
      border-bottom: 1px solid #f0f0f0;
      transition: background-color 0.2s;
    }
    
    .dropdown-item:hover,
    .dropdown-item.highlighted {
      background-color: #f8f9fa;
    }
    
    .dropdown-item:last-child {
      border-bottom: none;
    }
    
    .dropdown-loading {
      padding: 10px 12px;
      color: #666;
      font-style: italic;
    }
    
    .dropdown-no-results {
      padding: 10px 12px;
      color: #999;
      font-style: italic;
    }
    
    .customer-name {
      font-weight: 500;
      color: #333;
    }
    
    .customer-email {
      font-size: 12px;
      color: #666;
      margin-top: 2px;
    }
    
    .templates-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 20px;
      margin-top: 20px;
    }
    
    .template-card {
      background: white;
      border: 1px solid var(--border-light);
      border-radius: 10px;
      padding: 20px;
      text-align: center;
      cursor: pointer;
      transition: var(--transition);
      box-shadow: var(--shadow-light);
    }
    
    .template-card:hover {
      transform: translateY(-3px);
      box-shadow: var(--shadow-medium);
      border-color: var(--primary-red);
    }
    
    .template-icon {
      width: 50px;
      height: 50px;
      background: linear-gradient(135deg, var(--primary-red), #b91c3c);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 15px;
      color: white;
      font-size: 20px;
    }
    
    .template-card h3 {
      color: var(--text-dark);
      margin-bottom: 10px;
      font-size: 16px;
    }
    
    .template-card p {
      color: var(--text-light);
      font-size: 14px;
      margin: 0;
    }
    
    .modal {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 2000;
    }
    
    .modal-content {
      background: white;
      border-radius: 10px;
      max-width: 600px;
      width: 90%;
      max-height: 80vh;
      overflow-y: auto;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    }
    
    .modal-header {
      padding: 20px;
      border-bottom: 1px solid var(--border-light);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .modal-header h3 {
      margin: 0;
      color: var(--text-dark);
    }
    
    .modal-close {
      background: none;
      border: none;
      font-size: 18px;
      cursor: pointer;
      color: var(--text-light);
      padding: 5px;
      border-radius: 4px;
      transition: all 0.2s ease;
    }
    
    .modal-close:hover {
      color: var(--text-dark);
      background: #f5f5f5;
    }
    
    .modal-body {
      padding: 20px;
    }
    
    .email-details {
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }
    
    .detail-row {
      display: flex;
      flex-direction: column;
      gap: 0.25rem;
    }
    
    .detail-row strong {
      color: var(--text-dark);
      font-weight: 600;
    }
    
    .email-message-content {
      background: #f8f9fa;
      padding: 1rem;
      border-radius: 4px;
      border-left: 4px solid var(--primary-red);
      margin-top: 0.5rem;
      line-height: 1.6;
    }
    
    /* Notification Styles */
    .notification {
      position: fixed;
      top: 20px;
      right: 20px;
      background: white;
      border-radius: 8px;
      padding: 1rem 1.5rem;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
      display: flex;
      align-items: center;
      gap: 0.75rem;
      z-index: 2001;
      min-width: 300px;
      max-width: 500px;
      animation: slideIn 0.3s ease-out;
    }
    
    @keyframes slideIn {
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
      border-left: 4px solid #28a745;
      color: #155724;
    }
    
    .notification-success i {
      color: #28a745;
    }
    
    .notification-error {
      border-left: 4px solid #dc3545;
      color: #721c24;
    }
    
    .notification-error i {
      color: #dc3545;
    }
    
    .notification-info {
      border-left: 4px solid #007bff;
      color: #004085;
    }
    
    .notification-info i {
      color: #007bff;
    }
    
    .notification-close {
      background: none;
      border: none;
      cursor: pointer;
      color: #666;
      margin-left: auto;
      padding: 0.25rem;
      border-radius: 4px;
      transition: all 0.2s ease;
    }
    
    .notification-close:hover {
      background: #f5f5f5;
      color: #333;
    }
    
    .preview-field {
      margin-bottom: 15px;
    }
    
    .preview-field strong {
      color: var(--text-dark);
    }
    
    .preview-message {
      background: #f8f9fa;
      padding: 15px;
      border-radius: 5px;
      margin-top: 10px;
      white-space: pre-wrap;
      border-left: 4px solid var(--primary-red);
    }
    
    .modal-footer {
      padding: 20px;
      border-top: 1px solid var(--border-light);
      display: flex;
      justify-content: flex-end;
      gap: 10px;
    }
    
    .table-container {
      overflow-x: auto;
    }
    
    @media (max-width: 768px) {
      .templates-grid {
        grid-template-columns: 1fr;
      }
      
      .modal-content {
        width: 95%;
        margin: 10px;
      }
    }
  </style>
  
  <!-- Notification System -->
  <script src="../../includes/js/notification-system.js"></script>
</body>

</html>