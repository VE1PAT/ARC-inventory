-- Borrower/returner subject on remote witness requests (for existing installs)
-- Select your database in phpMyAdmin first, then import.

SET @db := DATABASE();

SET @col_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'witness_requests'
    AND COLUMN_NAME = 'subject_user_id'
);

SET @sql := IF(
  @col_exists = 0,
  'ALTER TABLE witness_requests ADD COLUMN subject_user_id INT UNSIGNED NULL DEFAULT NULL AFTER actor_user_id',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE witness_requests
SET subject_user_id = actor_user_id
WHERE subject_user_id IS NULL;
