# Railway MySQL — fix “Database update required” on Register

Railway starts with an **empty** database (often named `railway`). Your app needs the same tables/columns as local **`graduate_tracking`**.

## Option A — Use `railway_from_xampp.sql` (recommended)

Built from your XAMPP export (`Downloads/graduate_tracking.sql`):

1. File: **`database/railway_from_xampp.sql`** (drops old tables, imports users/data, renames tables for Linux).
2. Connect to Railway MySQL (CLI tunnel + HeidiSQL, or public TCP):
   - `railway connect MySQL --tunnel-only`
   - HeidiSQL → `127.0.0.1` + tunnel port + `MYSQLUSER` / `MYSQLPASSWORD` / `MYSQLDATABASE`
3. **File → Run SQL file** → choose **`database/railway_from_xampp.sql`**.
4. Refresh your live **Register** page and log in (e.g. **Admin1**, same password as XAMPP).

## Option B — Manual XAMPP export

Export from phpMyAdmin, remove `CREATE DATABASE` / `USE graduate_tracking`, import manually, then run the `RENAME TABLE` block from `railway_from_xampp.sql`.

## Option C — Fresh schema on Railway (no local data)

1. In HeidiSQL, select the Railway database (`MYSQLDATABASE`, usually `railway`).
2. Run **`import_for_railway.sql`** in this folder (full schema + migrations).
3. Create an Admin user (register locally and export that row, or insert via SQL).
4. Refresh Register.

## Option C — Only fix Register columns (already have some tables)

Run these files **in order** on the Railway database:

1. `migrate_split_user_fullname_add_names.sql`
2. `migrate_add_user_email_fullname.sql`
3. `migrate_add_user_ids.sql`
4. `migrate_add_middle_initial.sql`
5. `migrate_name_suffix_and_mi2.sql`

(Remove or skip `USE graduate_tracking;` at the top of each file — you are already connected to `railway`.)

## Verify

In HeidiSQL, run:

```sql
SHOW COLUMNS FROM User_Accounts;
```

You should see: `Email`, `First_Name`, `Last_Name`, `Middle_Initial`, `Name_Suffix`, `Student_No`, `Employee_ID`.
