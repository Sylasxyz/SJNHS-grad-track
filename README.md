# San Josef National High School Graduate Tracking System

## Setup (XAMPP)

- Import the database schema: `database/schema.sql`
- Run additional migrations in phpMyAdmin when needed:
  - `database/migrate_add_user_ids.sql` (Student No / Employee ID fields for role-based registration)
  - `database/migrate_add_middle_initial.sql` (adds Middle Initial column)
  - `database/migrate_add_user_messages.sql` (Student/Instructor messaging)
- DB: defaults in `includes/db_config.php` (XAMPP). Optional: env vars — see `.env.example`.
- Mail (local): copy `includes/mail_config.local.example.php` → `mail_config.local.php` and set Gmail App Password.
- **Railway:** see [DEPLOY-RAILWAY.md](DEPLOY-RAILWAY.md).
- Visit your local URL (e.g. `http://localhost/Capstoneafter/Capstoneafter/`).

## Pages

- `register.php`: Student registration
- `login.php` / `logout.php`: Authentication
- `forgot_password.php` / `reset_password.php`: Password reset flow
- `dashboard.php`: Role-based dashboard
- `profile.php`: Student graduate profile update form
- `announcements.php`: View announcements (all logged-in roles)
- `announcement_create.php`: Post announcement (Instructor/Admin)
- `graduates.php`: Search/filter graduates (Instructor/Admin)
- `admin/users.php`: User management (Admin)
- `admin/reports.php`: Employment statistics + chart (Admin)
- `messages.php`: Direct messages between Students and Instructors

