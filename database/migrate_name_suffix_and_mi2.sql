USE graduate_tracking;

-- Allow up to 2 letters for middle initials (e.g. AB)
SET @sql := IF(
  EXISTS(
    SELECT 1
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'User_Accounts'
      AND COLUMN_NAME = 'Middle_Initial'
  ),
  'ALTER TABLE User_Accounts MODIFY COLUMN Middle_Initial VARCHAR(2) NULL',
  'ALTER TABLE User_Accounts ADD COLUMN Middle_Initial VARCHAR(2) NULL AFTER Last_Name'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Optional name suffix (JR, SR, II, III, IV)
SET @sql := IF(
  EXISTS(
    SELECT 1
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'User_Accounts'
      AND COLUMN_NAME = 'Name_Suffix'
  ),
  'SELECT 1',
  'ALTER TABLE User_Accounts ADD COLUMN Name_Suffix VARCHAR(10) NULL AFTER Middle_Initial'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
