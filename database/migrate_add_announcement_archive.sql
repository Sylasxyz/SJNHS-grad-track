-- Archive support for announcements
ALTER TABLE Announcements
  ADD COLUMN Is_Archived TINYINT(1) NOT NULL DEFAULT 0 AFTER Date_Posted;

ALTER TABLE Announcements
  ADD KEY idx_announcements_archived (Is_Archived);
