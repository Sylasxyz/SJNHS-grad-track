USE graduate_tracking;

-- If you previously added Full_Name, keep it; we’ll derive first/last then drop it (optional).
ALTER TABLE User_Accounts
  ADD COLUMN First_Name VARCHAR(80) NULL AFTER User_ID,
  ADD COLUMN Last_Name VARCHAR(80) NULL AFTER First_Name;

-- Best-effort split from existing Full_Name (if present); safe if NULL/empty.
UPDATE User_Accounts
SET
  First_Name = COALESCE(NULLIF(First_Name, ''), SUBSTRING_INDEX(COALESCE(Full_Name, Username), ' ', 1)),
  Last_Name  = COALESCE(NULLIF(Last_Name, ''), NULLIF(SUBSTRING(COALESCE(Full_Name, ''), LENGTH(SUBSTRING_INDEX(COALESCE(Full_Name, ''), ' ', 1)) + 2), ''))
WHERE First_Name IS NULL OR Last_Name IS NULL;

ALTER TABLE User_Accounts
  MODIFY First_Name VARCHAR(80) NOT NULL,
  MODIFY Last_Name VARCHAR(80) NOT NULL;

-- Optional: drop Full_Name column if it exists (run manually if you want)
-- ALTER TABLE User_Accounts DROP COLUMN Full_Name;

