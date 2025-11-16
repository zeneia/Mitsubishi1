-- Add approved_by column to test_drive_requests table
-- This column will store the ID of the user (sales agent) who approved the test drive request

-- Check if the column already exists before adding it
SET @dbname = DATABASE();
SET @tablename = 'test_drive_requests';
SET @columnname = 'approved_by';

-- Add approved_by column if it doesn't exist
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  'ALTER TABLE test_drive_requests ADD COLUMN approved_by INT DEFAULT NULL AFTER approved_at'
));

PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add foreign key constraint if the column was just added
SET @preparedStatement2 = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
      AND (constraint_name LIKE 'fk_%')
  ) > 0,
  'SELECT 1',
  'ALTER TABLE test_drive_requests ADD CONSTRAINT fk_test_drive_approved_by FOREIGN KEY (approved_by) REFERENCES accounts(Id) ON DELETE SET NULL'
));

PREPARE alterIfNotExists2 FROM @preparedStatement2;
EXECUTE alterIfNotExists2;
DEALLOCATE PREPARE alterIfNotExists2;

-- Add index for better query performance
SET @preparedStatement3 = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (index_name = 'idx_approved_by')
  ) > 0,
  'SELECT 1',
  'CREATE INDEX idx_approved_by ON test_drive_requests(approved_by)'
));

PREPARE alterIfNotExists3 FROM @preparedStatement3;
EXECUTE alterIfNotExists3;
DEALLOCATE PREPARE alterIfNotExists3;

