-- Email Verification Table for OTP System
-- This table stores OTP codes for email verification during account registration
-- Created: 2025-10-21

-- Create email_verifications table
-- Note: Removed foreign key constraint to avoid compatibility issues
-- The relationship is maintained through application logic
CREATE TABLE IF NOT EXISTS email_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    email VARCHAR(255) NOT NULL,
    otp_code VARCHAR(6) NOT NULL,
    otp_hash VARCHAR(255) NOT NULL,  -- Hashed version for security
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    verified_at TIMESTAMP NULL,
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 5,
    is_used TINYINT DEFAULT 0,
    resend_count INT DEFAULT 0,
    last_resend_at TIMESTAMP NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    INDEX idx_account_id (account_id),
    INDEX idx_email (email),
    INDEX idx_otp_hash (otp_hash),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Alter accounts table to add email verification columns
-- Check if columns exist before adding them
SET @dbname = DATABASE();
SET @tablename = 'accounts';
SET @columnname1 = 'email_verified';
SET @columnname2 = 'email_verified_at';

-- Add email_verified column if it doesn't exist
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname1)
  ) > 0,
  'SELECT 1',
  'ALTER TABLE accounts ADD COLUMN email_verified TINYINT DEFAULT 0 AFTER Email'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add email_verified_at column if it doesn't exist
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname2)
  ) > 0,
  'SELECT 1',
  'ALTER TABLE accounts ADD COLUMN email_verified_at TIMESTAMP NULL AFTER email_verified'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Mark existing accounts as verified (grandfather clause)
-- This ensures existing users are not affected by the new verification requirement
UPDATE accounts 
SET email_verified = 1, 
    email_verified_at = COALESCE(CreatedAt, NOW()) 
WHERE email_verified IS NULL OR email_verified = 0;

-- Success message
SELECT 'Email verification tables created successfully!' AS message;

