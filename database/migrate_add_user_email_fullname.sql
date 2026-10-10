USE graduate_tracking;

ALTER TABLE User_Accounts
  ADD COLUMN Full_Name VARCHAR(150) NOT NULL AFTER User_ID,
  ADD COLUMN Email VARCHAR(150) NOT NULL AFTER Username,
  ADD UNIQUE KEY uq_user_accounts_email (Email);

