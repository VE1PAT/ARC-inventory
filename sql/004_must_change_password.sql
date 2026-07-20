-- Force password change on first login / after admin reset
-- Select your database in phpMyAdmin first, then import.

SET @db := DATABASE();

SET @col_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'must_change_password'
);

SET @sql := IF(
  @col_exists = 0,
  'ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER locked_at',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
