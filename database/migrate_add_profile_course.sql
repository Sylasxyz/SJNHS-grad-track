USE graduate_tracking;

ALTER TABLE Graduate_Profiles
  ADD COLUMN Course VARCHAR(120) NULL AFTER Full_Name,
  ADD KEY idx_graduate_profiles_course (Course);

