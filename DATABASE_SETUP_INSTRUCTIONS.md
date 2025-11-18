# PMS System - Database Setup Instructions

## Issue
You received a white screen when submitting a PMS request. This is because the database tables for the PMS inquiry system have not been created yet.

## Solution
Execute the following SQL queries in phpMyAdmin to set up the required database tables.

## Step-by-Step Instructions

### 1. Open phpMyAdmin
- Go to `http://localhost/phpmyadmin`
- Select the `mitsubishi` database

### 2. Execute the SQL Queries
Click on the "SQL" tab and paste the following queries:

```sql
-- Add customer_needs column to car_pms_records if it doesn't exist
ALTER TABLE car_pms_records 
ADD COLUMN IF NOT EXISTS customer_needs LONGTEXT COMMENT 'Customer description of vehicle issues or maintenance needs';

-- Create pms_inquiries table to track PMS inquiries separately
CREATE TABLE IF NOT EXISTS pms_inquiries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pms_id INT NOT NULL,
    customer_id INT NOT NULL,
    inquiry_type VARCHAR(50) NOT NULL DEFAULT 'PMS' COMMENT 'Type of inquiry (PMS, Service, etc.)',
    status ENUM('Open', 'In Progress', 'Resolved', 'Closed') DEFAULT 'Open',
    assigned_agent_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    
    INDEX idx_pms_id (pms_id),
    INDEX idx_customer_id (customer_id),
    INDEX idx_assigned_agent_id (assigned_agent_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- Create pms_messages table for customer-agent communication
CREATE TABLE IF NOT EXISTS pms_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inquiry_id INT NOT NULL,
    sender_id INT NOT NULL,
    sender_type ENUM('Customer', 'Agent') NOT NULL,
    message_text LONGTEXT NOT NULL,
    message_type VARCHAR(50) DEFAULT 'text' COMMENT 'text, quote, service_offer, etc.',
    is_read TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_inquiry_id (inquiry_id),
    INDEX idx_sender_id (sender_id),
    INDEX idx_sender_type (sender_type),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at)
);

-- Create pms_inquiry_attachments table for file uploads in messages
CREATE TABLE IF NOT EXISTS pms_inquiry_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_type VARCHAR(100) NOT NULL,
    file_data LONGBLOB NOT NULL,
    file_size INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_message_id (message_id)
);
```

### 3. Click "Go" to Execute
After pasting the queries, click the "Go" button to execute them.

### 4. Verify Success
You should see a success message. The tables are now created.

## Testing the System

After executing the SQL queries:

1. **Submit a PMS Request**
   - Go to Customer Dashboard
   - Click "Submit PMS Request"
   - Fill in the form with vehicle details and describe your needs
   - Click Submit

2. **View Your Inquiries**
   - You should be redirected to "My PMS Inquiries"
   - Your submitted inquiry should appear in the list

3. **Send Messages**
   - Click "View Messages" on any inquiry
   - Type a message and send it
   - Messages should appear in the chat

4. **Agent Interface**
   - Sales agents can access: `/pages/agent_pms_inquiries.php`
   - View all PMS inquiries
   - Assign inquiries to themselves
   - Respond to customer messages

## Troubleshooting

**Still seeing white screen?**
- Clear your browser cache (Ctrl+Shift+Delete)
- Refresh the page (Ctrl+F5)
- Check that all SQL queries executed successfully

**Tables not created?**
- Verify you're in the correct database (mitsubishi)
- Check for SQL syntax errors in the error message
- Try executing each query separately

**Need help?**
- Contact your system administrator
- Check the error logs in Apache/PHP

