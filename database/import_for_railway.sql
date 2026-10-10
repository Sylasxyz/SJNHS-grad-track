-- San Josef NHS Graduate Tracking — import on Railway MySQL
-- In HeidiSQL: select your Railway database (MYSQLDATABASE), then run this whole file.
-- Do NOT run CREATE DATABASE; Railway already gave you a database.

-- San Josef National High School Graduate Tracking System
-- MySQL 8+ / MariaDB 10.4+

CREATE DATABASE IF NOT EXISTS graduate_tracking
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;


-- Drop in dependency-safe order (optional for re-runs)
DROP TABLE IF EXISTS Reports;
DROP TABLE IF EXISTS Announcement_Recipients;
DROP TABLE IF EXISTS Announcements;
DROP TABLE IF EXISTS Graduate_Profiles;
DROP TABLE IF EXISTS User_Accounts;

CREATE TABLE User_Accounts (
  User_ID INT UNSIGNED NOT NULL AUTO_INCREMENT,
  First_Name VARCHAR(80) NOT NULL,
  Last_Name VARCHAR(80) NOT NULL,
  Middle_Initial VARCHAR(2) NULL,
  Name_Suffix VARCHAR(10) NULL,
  Username VARCHAR(50) NOT NULL,
  Email VARCHAR(150) NOT NULL,
  Password VARCHAR(255) NOT NULL,
  Role ENUM('Admin','Instructor','Student','Principal') NOT NULL DEFAULT 'Student',
  Status ENUM('Pending','Active','Inactive','Archived') NOT NULL DEFAULT 'Pending',
  Created_At TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (User_ID),
  UNIQUE KEY uq_user_accounts_username (Username),
  UNIQUE KEY uq_user_accounts_email (Email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE Graduate_Profiles (
  Profile_ID INT UNSIGNED NOT NULL AUTO_INCREMENT,
  User_ID INT UNSIGNED NOT NULL,
  Full_Name VARCHAR(150) NOT NULL,
  Course VARCHAR(120) NULL,
  Batch_Year INT NOT NULL,
  Employment_Status ENUM('Employed','Unemployed','Self-Employed','Further Studies') NOT NULL DEFAULT 'Unemployed',
  Company_School_Name VARCHAR(200) NULL,
  Further_Studies_Program VARCHAR(200) NULL,
  Contact_Info VARCHAR(200) NULL,
  Profile_Picture VARCHAR(255) NULL,
  Is_Verified TINYINT(1) NOT NULL DEFAULT 0,
  Verified_By INT UNSIGNED NULL,
  Verified_At TIMESTAMP NULL DEFAULT NULL,
  Last_Updated TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (Profile_ID),
  UNIQUE KEY uq_graduate_profiles_user_id (User_ID),
  KEY idx_graduate_profiles_batch_year (Batch_Year),
  KEY idx_graduate_profiles_employment_status (Employment_Status),
  KEY idx_graduate_profiles_is_verified (Is_Verified),
  CONSTRAINT fk_graduate_profiles_user
    FOREIGN KEY (User_ID) REFERENCES User_Accounts(User_ID)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_graduate_profiles_verified_by
    FOREIGN KEY (Verified_By) REFERENCES User_Accounts(User_ID)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE Announcements (
  Announcement_ID INT UNSIGNED NOT NULL AUTO_INCREMENT,
  Author_ID INT UNSIGNED NULL,
  Title VARCHAR(150) NOT NULL,
  Message TEXT NOT NULL,
  Image_Path VARCHAR(255) NULL,
  Audience ENUM('Everyone','Specific') NOT NULL DEFAULT 'Everyone',
  Date_Posted TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  Is_Archived TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (Announcement_ID),
  KEY idx_announcements_date_posted (Date_Posted),
  KEY idx_announcements_audience (Audience),
  KEY idx_announcements_archived (Is_Archived),
  CONSTRAINT fk_announcements_author
    FOREIGN KEY (Author_ID) REFERENCES User_Accounts(User_ID)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE Announcement_Recipients (
  Announcement_ID INT UNSIGNED NOT NULL,
  User_ID INT UNSIGNED NOT NULL,
  PRIMARY KEY (Announcement_ID, User_ID),
  KEY idx_announcement_recipients_user (User_ID),
  CONSTRAINT fk_announcement_recipients_announcement
    FOREIGN KEY (Announcement_ID) REFERENCES Announcements(Announcement_ID)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_announcement_recipients_user
    FOREIGN KEY (User_ID) REFERENCES User_Accounts(User_ID)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE Reports (
  Report_ID INT UNSIGNED NOT NULL AUTO_INCREMENT,
  Report_Name VARCHAR(150) NOT NULL,
  Generated_Date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  Summary_JSON JSON NOT NULL,
  PRIMARY KEY (Report_ID),
  KEY idx_reports_generated_date (Generated_Date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE User_Messages (
  Message_ID INT UNSIGNED NOT NULL AUTO_INCREMENT,
  Sender_ID INT UNSIGNED NOT NULL,
  Recipient_ID INT UNSIGNED NOT NULL,
  Message_Text TEXT NOT NULL,
  Sent_At TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  Is_Read TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (Message_ID),
  KEY idx_user_messages_sender (Sender_ID),
  KEY idx_user_messages_recipient (Recipient_ID),
  KEY idx_user_messages_sent_at (Sent_At),
  CONSTRAINT fk_user_messages_sender
    FOREIGN KEY (Sender_ID) REFERENCES User_Accounts(User_ID)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_user_messages_recipient
    FOREIGN KEY (Recipient_ID) REFERENCES User_Accounts(User_ID)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE Password_Resets (
  Reset_ID INT UNSIGNED NOT NULL AUTO_INCREMENT,
  User_ID INT UNSIGNED NOT NULL,
  Token_Hash CHAR(64) NOT NULL,
  Expires_At DATETIME NOT NULL,
  Used_At TIMESTAMP NULL DEFAULT NULL,
  Created_At TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (Reset_ID),
  UNIQUE KEY uq_password_resets_token_hash (Token_Hash),
  KEY idx_password_resets_user_id (User_ID),
  KEY idx_password_resets_expires_at (Expires_At),
  CONSTRAINT fk_password_resets_user
    FOREIGN KEY (User_ID) REFERENCES User_Accounts(User_ID)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS Login_Attempts (
  Attempt_ID INT UNSIGNED NOT NULL AUTO_INCREMENT,
  Username VARCHAR(50) NOT NULL,
  IP_Address VARCHAR(45) NOT NULL,
  Was_Successful TINYINT(1) NOT NULL DEFAULT 0,
  Attempted_At TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (Attempt_ID),
  KEY idx_login_attempts_user_time (Username, Attempted_At),
  KEY idx_login_attempts_ip_time (IP_Address, Attempted_At),
  KEY idx_login_attempts_failed_time (Was_Successful, Attempted_At)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- ---- migrate_split_user_fullname_add_names.sql ----


-- If you previously added Full_Name, keep it; weâ€™ll derive first/last then drop it (optional).
ALTER TABLE User_Accounts
  ADD COLUMN First_Name VARCHAR(80) NULL AFTER User_ID,
  ADD COLUMN Last_Name VARCHAR(80) NULL AFTER First_Name;

-- Best-effort split from existing Full_Name (if present); safe if NULL/empty.
UPDATE User_Accounts
SET
  First_Name = COALESCE(NULLIF(First_Name, ''), SUBSTRING_INDEX(COALESCE(Full_Name, Username), ' ', 1)),
  Last_Name  = COALESCE(NULLIF(Last_Name, ''), NULLIF(SUBSTRING(COALESCE(Full_Name, ''), LENGTH(SUBSTRING_INDEX(COALESCE(Full_Name, ''), ' ', 1)) + 2), ''))
WHERE First_Name IS NULL OR Last_Name IS NULL;

ALTER TABLE User_Accounts
  MODIFY First_Name VARCHAR(80) NOT NULL,
  MODIFY Last_Name VARCHAR(80) NOT NULL;

-- Optional: drop Full_Name column if it exists (run manually if you want)
-- ALTER TABLE User_Accounts DROP COLUMN Full_Name;



-- ---- migrate_add_user_email_fullname.sql ----


ALTER TABLE User_Accounts
  ADD COLUMN Full_Name VARCHAR(150) NOT NULL AFTER User_ID,
  ADD COLUMN Email VARCHAR(150) NOT NULL AFTER Username,
  ADD UNIQUE KEY uq_user_accounts_email (Email);



-- ---- migrate_add_user_ids.sql ----


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


-- ---- migrate_add_middle_initial.sql ----


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


-- ---- migrate_name_suffix_and_mi2.sql ----


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


-- ---- migrate_add_user_messages.sql ----


CREATE TABLE IF NOT EXISTS User_Messages (
  Message_ID INT UNSIGNED NOT NULL AUTO_INCREMENT,
  Sender_ID INT UNSIGNED NOT NULL,
  Recipient_ID INT UNSIGNED NOT NULL,
  Message_Text TEXT NOT NULL,
  Sent_At TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  Is_Read TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (Message_ID),
  KEY idx_user_messages_sender (Sender_ID),
  KEY idx_user_messages_recipient (Recipient_ID),
  KEY idx_user_messages_sent_at (Sent_At),
  CONSTRAINT fk_user_messages_sender
    FOREIGN KEY (Sender_ID) REFERENCES User_Accounts(User_ID)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_user_messages_recipient
    FOREIGN KEY (Recipient_ID) REFERENCES User_Accounts(User_ID)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---- migrate_add_profile_course.sql ----


ALTER TABLE Graduate_Profiles
  ADD COLUMN Course VARCHAR(120) NULL AFTER Full_Name,
  ADD KEY idx_graduate_profiles_course (Course);



-- ---- migrate_add_profile_verification.sql ----


ALTER TABLE Graduate_Profiles
  ADD COLUMN Is_Verified TINYINT(1) NOT NULL DEFAULT 0 AFTER Contact_Info,
  ADD COLUMN Verified_By INT UNSIGNED NULL AFTER Is_Verified,
  ADD COLUMN Verified_At TIMESTAMP NULL DEFAULT NULL AFTER Verified_By,
  ADD KEY idx_graduate_profiles_is_verified (Is_Verified),
  ADD CONSTRAINT fk_graduate_profiles_verified_by
    FOREIGN KEY (Verified_By) REFERENCES User_Accounts(User_ID)
    ON DELETE SET NULL
    ON UPDATE CASCADE;



-- ---- migrate_add_profile_picture_further_program.sql ----


ALTER TABLE Graduate_Profiles
  ADD COLUMN Further_Studies_Program VARCHAR(200) NULL AFTER Company_School_Name,
  ADD COLUMN Profile_Picture VARCHAR(255) NULL AFTER Contact_Info;



-- ---- migrate_add_announcement_image.sql ----

-- Optional image attachment for announcements
ALTER TABLE Announcements
  ADD COLUMN Image_Path VARCHAR(255) NULL AFTER Message;


-- ---- migrate_add_announcement_archive.sql ----

-- Archive support for announcements
ALTER TABLE Announcements
  ADD COLUMN Is_Archived TINYINT(1) NOT NULL DEFAULT 0 AFTER Date_Posted;

ALTER TABLE Announcements
  ADD KEY idx_announcements_archived (Is_Archived);


-- ---- migrate_add_principal_role.sql ----

-- Add Principal role (view-only school head account)
ALTER TABLE User_Accounts
  MODIFY Role ENUM('Admin','Instructor','Student','Principal') NOT NULL DEFAULT 'Student';


-- ---- migrate_add_password_resets.sql ----


CREATE TABLE IF NOT EXISTS Password_Resets (
  Reset_ID INT UNSIGNED NOT NULL AUTO_INCREMENT,
  User_ID INT UNSIGNED NOT NULL,
  Token_Hash CHAR(64) NOT NULL,
  Expires_At TIMESTAMP NOT NULL,
  Used_At TIMESTAMP NULL DEFAULT NULL,
  Created_At TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (Reset_ID),
  UNIQUE KEY uq_password_resets_token_hash (Token_Hash),
  KEY idx_password_resets_user_id (User_ID),
  KEY idx_password_resets_expires_at (Expires_At),
  CONSTRAINT fk_password_resets_user
    FOREIGN KEY (User_ID) REFERENCES User_Accounts(User_ID)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- ---- migrate_add_login_attempts.sql ----

-- Add Login_Attempts for Security Alerts (failed login tracking)
-- Run in phpMyAdmin on database: graduate_tracking
-- (Optional if the app auto-creates the table on first login.)


CREATE TABLE IF NOT EXISTS Login_Attempts (
  Attempt_ID INT UNSIGNED NOT NULL AUTO_INCREMENT,
  Username VARCHAR(50) NOT NULL,
  IP_Address VARCHAR(45) NOT NULL,
  Was_Successful TINYINT(1) NOT NULL DEFAULT 0,
  Attempted_At TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (Attempt_ID),
  KEY idx_login_attempts_user_time (Username, Attempted_At),
  KEY idx_login_attempts_ip_time (IP_Address, Attempted_At),
  KEY idx_login_attempts_failed_time (Was_Successful, Attempted_At)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

