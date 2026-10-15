# Deploy on Railway

The app is the **Laravel 12** project in `laravel/`, built by the root `Dockerfile` (PHP 8.2 CLI) and served with PHP's built-in server from `laravel/public`. It uses the existing **MySQL** database as is; there are no Laravel migrations.

## 1. Railway project

1. [railway.app](https://railway.app) → **New Project** → **Deploy from GitHub repo**.
2. **Settings → Root Directory**: the folder that contains the `Dockerfile` (`.` if it is the repo root).
3. **+ New → Database → MySQL** in the same project.
4. Web service → **Variables** → **Add references** from the MySQL service (`MYSQLHOST`, `MYSQLPORT`, `MYSQLUSER`, `MYSQLPASSWORD`, `MYSQLDATABASE`). Laravel reads these directly; `DB_HOST`, `DB_DATABASE`, etc. override them if set.

## 2. Variables (web service)

| Variable | Value |
|----------|-------|
| `APP_KEY` | **Required.** Generate once with `php artisan key:generate --show` (run inside `laravel/`) and keep it; changing it logs everyone out. The container refuses to start without it. |
| `APP_URL` | `https://your-app.up.railway.app` (used for links generated outside a request) |
| `BREVO_API_KEY` | Brevo → SMTP & API → **API Keys** (starts with `xkeysib-`, not the SMTP key `xsmtpsib-…`) |
| `MAIL_FROM_EMAIL` | Brevo **verified sender** (e.g. `gradtrack.sjnhs@gmail.com`) |
| `MAIL_FROM_NAME` | `San Josef NHS Graduate Tracking` |
| `MAIL_SHOW_RESET_LINK_ON_FAILURE` | `false` |

Set by the Dockerfile (override only if needed): `APP_ENV=production`, `APP_DEBUG=false`, `LOG_CHANNEL=stderr` (logs appear in Railway's log view), `SESSION_SECURE_COOKIE=true`.

Sessions and cache are stored in files inside the container, so a redeploy signs everyone out.

The old `SMTP_*` variables are no longer used: email goes only through the Brevo HTTPS API (Railway blocks SMTP ports).

## 3. Email check

After deploying, log in as **Admin** and open `/admin/mail_test`. The connection check should show `Hosting: Railway` and `Outbound ready (Brevo API): yes`; sending a test should report `Test email sent via brevo-api`. Failed sends are also written to `laravel/storage/logs/mail.log` inside the container.

## 4. Database setup (new database only)

Connect to Railway MySQL (Railway CLI or a client using the public URL):

1. Run `database/schema.sql`.
2. Run the `database/migrate_*.sql` files as needed (same order as XAMPP/phpMyAdmin).
3. Create an **Admin** user (register locally and import, or insert via SQL with a bcrypt password).

## 5. Public URL

**Networking → Generate domain** on the web service, then open `https://your-app.up.railway.app/`. Railway's proxy terminates HTTPS; the app trusts it automatically when Railway's variables are present, so links, redirects and cookies use `https`.

## 6. Uploads (announcement images, profile photos)

Railway disk is ephemeral. Keep a **Volume** on the web service mounted at `/var/www/html/uploads` (same path as before). Laravel reads and writes `uploads/` next to `laravel/`, so existing files keep working.

## 7. Local development (XAMPP)

```powershell
cd laravel
copy .env.example .env          # first time only, then set DB_* and APP_KEY
C:\xampp\php\php.exe artisan key:generate   # first time only
C:\xampp\php\php.exe artisan serve --port=8011
```

Open `http://127.0.0.1:8011`. MySQL comes from XAMPP (`graduate_tracking`, user `root`, no password by default).

## 8. Security

- Never commit `laravel/.env`.
- Keep `APP_DEBUG=false` in production.
- Failed logins are stored in `Login_Attempts`. Admins see **Security Alerts** on the dashboard and under **Manage Users** (5+ failures per username/IP in 30 minutes).
