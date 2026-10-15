# San Josef National High School Graduate Tracking System

A Laravel 12 app (in `laravel/`) on top of the existing `graduate_tracking` MySQL database. The schema is managed with the SQL files in `database/`; there are no Laravel migrations.

## Local setup (XAMPP)

1. Start **MySQL** in XAMPP and import `database/schema.sql`, then the `database/migrate_*.sql` files as needed (phpMyAdmin or `mysql.exe`).
2. Install dependencies and configure the app:

   ```powershell
   cd laravel
   C:\xampp\php\php.exe C:\xampp\php\composer.phar install
   copy .env.example .env
   C:\xampp\php\php.exe artisan key:generate
   ```

   `.env.example` already points at XAMPP's MySQL (`graduate_tracking`, user `root`, no password).
3. Run it:

   ```powershell
   C:\xampp\php\php.exe artisan serve --port=8011
   ```

   Open `http://127.0.0.1:8011`.

Email goes through the Brevo HTTPS API. Leave `BREVO_API_KEY` empty locally (sends fail and are logged to `laravel/storage/logs/mail.log`), or set an API key (`xkeysib-…`) to send for real.

Uploaded announcement images and profile photos live in `uploads/` next to `laravel/`.

**Railway:** see [DEPLOY-RAILWAY.md](DEPLOY-RAILWAY.md).

## Pages

| URL | Who | What |
|-----|-----|------|
| `/`, `/about`, `/privacy`, `/terms` | Everyone | Public homepage, about page, privacy policy, terms |
| `/login`, `/register`, `/forgot-password`, `/reset-password` | Guests | Sign in, student/instructor registration, 6-digit email code password reset |
| `/dashboard` | Signed in | Role dashboard (student or staff view) |
| `/announcements` | Signed in | Announcements; staff can post (`/announcements/create`), archive, restore, delete |
| `/messages` | Signed in | Messages between students and staff |
| `/student/profile` | Student | Graduate profile and photo |
| `/graduates` | Instructor, Admin | Search, filter, export, verify and edit graduate records |
| `/instructor/users` | Instructor, Admin | Approve and manage student accounts |
| `/admin/users` | Admin | Manage all users and security alerts |
| `/admin/reports` | Instructor, Admin | Generate, view, export (CSV) and delete saved reports |
| `/analytics` | Instructor, Admin | KPIs and charts, save snapshots |
| `/admin/mail_test` | Admin | Email connection check and test send |
