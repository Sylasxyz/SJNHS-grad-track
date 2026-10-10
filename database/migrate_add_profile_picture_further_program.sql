USE graduate_tracking;

ALTER TABLE Graduate_Profiles
  ADD COLUMN Further_Studies_Program VARCHAR(200) NULL AFTER Company_School_Name,
  ADD COLUMN Profile_Picture VARCHAR(255) NULL AFTER Contact_Info;

