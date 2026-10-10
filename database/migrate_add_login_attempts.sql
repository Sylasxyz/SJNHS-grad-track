-- Add Login_Attempts for Security Alerts (failed login tracking)
-- Run in phpMyAdmin on database: graduate_tracking
-- (Optional if the app auto-creates the table on first login.)

USE graduate_tracking;

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
