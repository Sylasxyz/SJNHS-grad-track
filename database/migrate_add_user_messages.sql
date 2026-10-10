USE graduate_tracking;

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
