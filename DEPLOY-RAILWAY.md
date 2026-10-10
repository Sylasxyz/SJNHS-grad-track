# Deploy on Railway

This app runs as **PHP 8.2 + Apache** in Docker with **MySQL** and environment-based config.

## 1. GitHub

Push the folder that contains this `Dockerfile` (this directory: `Capstoneafter/Capstoneafter` if your repo root is `Capstoneafter`).

## 2. Railway project

1. [railway.app](https://railway.app) → **New Project** → **Deploy from GitHub repo**.
2. **Settings → Root Directory**: set to this folder (e.g. `Capstoneafter` or `.` if this folder is the repo root).
3. **+ New → Database → MySQL** in the same project.
4. Open the **web service** → **Variables** → **Add references** from the MySQL service (or copy `MYSQLHOST`, `MYSQLPORT`, `MYSQLUSER`, `MYSQLPASSWORD`, `MYSQLDATABASE`).

## 3. Required variables (web service)

| Variable | Production value |
|----------|------------------|
| `APP_DEBUG` | `0` |
| `SMTP_PASS` | Gmail App Password (if using email) |
| `MAIL_SHOW_RESET_LINK_ON_FAILURE` | `0` |

MySQL variables are usually injected automatically when the database is linked.

## 4. Database setup

Connect to Railway MySQL (Railway CLI or a client using public URL if enabled):

1. Run `database/schema.sql`.
2. Run migrations in `database/migrate_*.sql` as needed (same order as XAMPP/phpMyAdmin).
3. Create an **Admin** user (register locally and import, or insert via SQL with a bcrypt password).

## 5. Public URL

**Networking → Generate domain** on the web service. Open `https://your-app.up.railway.app/`.

## 6. Uploads (announcements, profile photos)

Railway disk is ephemeral. For a persistent demo:

- Add a **Volume** on the web service mounted at `/var/www/html/uploads`.

## 7. Local XAMPP after env changes

- DB: unchanged if you don’t set env vars (defaults `127.0.0.1`, `root`, `graduate_tracking`).
- Mail: copy `includes/mail_config.local.example.php` → `includes/mail_config.local.php` and set `SMTP_PASS` (file is gitignored).

## 8. Pretty URLs (no `.php` in the browser)

The app uses extensionless paths (`/login`, `/dashboard`). Railway uses `router.php` with the PHP built-in server; XAMPP uses root `.htaccess` (enable **mod_rewrite** and **AllowOverride All** for the app folder).

Visiting `*.php` directly redirects to the clean URL.

## 9. Security

- Do not commit `mail_config.local.php` or `.env` with secrets.
- Set `APP_DEBUG=0` in production.
- Remove or protect `test_smtp.php` on production.
