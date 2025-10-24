-- Notification Logs Table
-- Tracks all email and SMS notifications sent by the system
-- This table provides a centralized log for monitoring notification delivery

CREATE TABLE IF NOT EXISTS notification_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    notification_type VARCHAR(50) NOT NULL COMMENT 'Type: loan_approval, loan_rejection, test_drive_approval, etc.',
    channel ENUM('email', 'sms', 'both') NOT NULL DEFAULT 'both',
    
    -- Email tracking
    email_status ENUM('sent', 'failed', 'skipped') DEFAULT 'skipped',
    email_recipient VARCHAR(255) DEFAULT NULL,
    email_subject VARCHAR(500) DEFAULT NULL,
    email_error TEXT DEFAULT NULL,
    
    -- SMS tracking
    sms_status ENUM('sent', 'failed', 'skipped') DEFAULT 'skipped',
    sms_recipient VARCHAR(20) DEFAULT NULL,
    sms_message_preview VARCHAR(160) DEFAULT NULL,
    sms_error TEXT DEFAULT NULL,
    
    -- Related record tracking
    related_id INT DEFAULT NULL COMMENT 'ID of related record (loan_id, payment_id, etc.)',
    related_table VARCHAR(50) DEFAULT NULL COMMENT 'Table name of related record',
    
    -- Metadata
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes for performance
    INDEX idx_customer_id (customer_id),
    INDEX idx_notification_type (notification_type),
    INDEX idx_sent_at (sent_at),
    INDEX idx_email_status (email_status),
    INDEX idx_sms_status (sms_status),
    INDEX idx_related (related_table, related_id),
    
    -- Foreign key
    FOREIGN KEY (customer_id) REFERENCES accounts(Id) ON DELETE CASCADE
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
COMMENT='Centralized log for all email and SMS notifications sent by the system';

