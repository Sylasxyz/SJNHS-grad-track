USE graduate_tracking;

SET @sql := IF(
  EXISTS(
    SELECT 1
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'User_Accounts'
      AND COLUMN_NAME = 'Middle_Initial'
  ),
  'SELECT 1',
  'ALTER TABLE User_Accounts ADD COLUMN Middle_Initial VARCHAR(1) NULL AFTER Last_Name'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
