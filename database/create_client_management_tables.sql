-- Create tables for client management functionality

-- Table for tracking client reassignments
CREATE TABLE IF NOT EXISTS `client_reassignments` (
    `reassignment_id` int NOT NULL AUTO_INCREMENT,
    `client_id` int NOT NULL,
    `old_agent_id` int NULL,
    `new_agent_id` int NOT NULL,
    `reason` varchar(255) NOT NULL,
    `notes` text NULL,
    `reassigned_by` int NOT NULL,
    `reassigned_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`reassignment_id`),
    KEY `idx_client` (`client_id`),
    KEY `idx_old_agent` (`old_agent_id`),
    KEY `idx_new_agent` (`new_agent_id`),
    KEY `idx_reassigned_by` (`reassigned_by`),
    KEY `idx_reassigned_at` (`reassigned_at`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- Table for tracking client escalations
CREATE TABLE IF NOT EXISTS `client_escalations` (
    `escalation_id` int NOT NULL AUTO_INCREMENT,
    `client_id` int NOT NULL,
    `escalation_reason` varchar(255) NOT NULL,
    `priority` enum('Low', 'Medium', 'High', 'Critical') DEFAULT 'High',
    `notes` text NULL,
    `escalated_by` int NOT NULL,
    `escalated_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `resolved_by` int NULL,
    `resolved_at` timestamp NULL,
    `status` enum('Pending', 'In Progress', 'Resolved', 'Closed') DEFAULT 'Pending',
    PRIMARY KEY (`escalation_id`),
    KEY `idx_client` (`client_id`),
    KEY `idx_escalated_by` (`escalated_by`),
    KEY `idx_resolved_by` (`resolved_by`),
    KEY `idx_status` (`status`),
    KEY `idx_priority` (`priority`),
    KEY `idx_escalated_at` (`escalated_at`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- Table for tracking client archives
CREATE TABLE IF NOT EXISTS `client_archives` (
    `archive_id` int NOT NULL AUTO_INCREMENT,
    `client_id` int NOT NULL,
    `archive_reason` varchar(255) NOT NULL,
    `archived_by` int NOT NULL,
    `archived_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `restored_by` int NULL,
    `restored_at` timestamp NULL,
    `status` enum('Archived', 'Restored') DEFAULT 'Archived',
    PRIMARY KEY (`archive_id`),
    KEY `idx_client` (`client_id`),
    KEY `idx_archived_by` (`archived_by`),
    KEY `idx_restored_by` (`restored_by`),
    KEY `idx_status` (`status`),
    KEY `idx_archived_at` (`archived_at`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- Add status column to customer_information if it doesn't exist
ALTER TABLE `customer_information` 
ADD COLUMN IF NOT EXISTS `status` enum('Active', 'Pending', 'Completed', 'Escalated', 'Archived') DEFAULT 'Active' AFTER `agent_id`;

-- Add index for status column
ALTER TABLE `customer_information` 
ADD INDEX IF NOT EXISTS `idx_status` (`status`);

-- Add index for agent_id if it doesn't exist
ALTER TABLE `customer_information` 
ADD INDEX IF NOT EXISTS `idx_agent_id` (`agent_id`);
