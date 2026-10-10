USE graduate_tracking;

SET @sql := IF(
  EXISTS(
    SELECT 1
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'User_Accounts'
      AND COLUMN_NAME = 'Student_No'
  ),
  'SELECT 1',
  'ALTER TABLE User_Accounts ADD COLUMN Student_No VARCHAR(60) NULL AFTER Email'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  EXISTS(
    SELECT 1
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'User_Accounts'
      AND COLUMN_NAME = 'Employee_ID'
  ),
  'SELECT 1',
  'ALTER TABLE User_Accounts ADD COLUMN Employee_ID VARCHAR(60) NULL AFTER Student_No'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  EXISTS(
    SELECT 1
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'User_Accounts'
      AND INDEX_NAME = 'uq_user_accounts_student_no'
  ),
  'SELECT 1',
  'ALTER TABLE User_Accounts ADD UNIQUE KEY uq_user_accounts_student_no (Student_No)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  EXISTS(
    SELECT 1
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'User_Accounts'
      AND INDEX_NAME = 'uq_user_accounts_employee_id'
  ),
  'SELECT 1',
  'ALTER TABLE User_Accounts ADD UNIQUE KEY uq_user_accounts_employee_id (Employee_ID)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
