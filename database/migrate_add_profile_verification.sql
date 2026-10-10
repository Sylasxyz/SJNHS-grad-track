USE graduate_tracking;

ALTER TABLE Graduate_Profiles
  ADD COLUMN Is_Verified TINYINT(1) NOT NULL DEFAULT 0 AFTER Contact_Info,
  ADD COLUMN Verified_By INT UNSIGNED NULL AFTER Is_Verified,
  ADD COLUMN Verified_At TIMESTAMP NULL DEFAULT NULL AFTER Verified_By,
  ADD KEY idx_graduate_profiles_is_verified (Is_Verified),
  ADD CONSTRAINT fk_graduate_profiles_verified_by
    FOREIGN KEY (Verified_By) REFERENCES User_Accounts(User_ID)
    ON DELETE SET NULL
    ON UPDATE CASCADE;

