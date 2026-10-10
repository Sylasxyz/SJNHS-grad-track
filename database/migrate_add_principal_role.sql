-- Add Principal role (view-only school head account)
ALTER TABLE User_Accounts
  MODIFY Role ENUM('Admin','Instructor','Student','Principal') NOT NULL DEFAULT 'Student';
