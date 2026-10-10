-- Optional image attachment for announcements
ALTER TABLE Announcements
  ADD COLUMN Image_Path VARCHAR(255) NULL AFTER Message;
