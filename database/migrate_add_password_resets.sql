USE graduate_tracking;

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

