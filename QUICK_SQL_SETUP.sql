-- PMS System Database Setup
-- Copy and paste this entire script into phpMyAdmin SQL tab
-- Make sure you're in the 'mitsubishi' database before executing

-- Add customer_needs column to car_pms_records
ALTER TABLE car_pms_records 
ADD COLUMN IF NOT EXISTS customer_needs LONGTEXT COMMENT 'Customer description of vehicle issues or maintenance needs';

-- Create pms_inquiries table
CREATE TABLE IF NOT EXISTS pms_inquiries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pms_id INT NOT NULL,
    customer_id INT NOT NULL,
    inquiry_type VARCHAR(50) NOT NULL DEFAULT 'PMS',
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

-- Create pms_messages table
CREATE TABLE IF NOT EXISTS pms_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inquiry_id INT NOT NULL,
    sender_id INT NOT NULL,
    sender_type ENUM('Customer', 'Agent') NOT NULL,
    message_text LONGTEXT NOT NULL,
    message_type VARCHAR(50) DEFAULT 'text',
    is_read TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_inquiry_id (inquiry_id),
    INDEX idx_sender_id (sender_id),
    INDEX idx_sender_type (sender_type),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at)
);

-- Create pms_inquiry_attachments table
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

