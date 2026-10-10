<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/db_config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/app_config.php';

$isEmbed = (($_GET['embed'] ?? '') === '1');

if ($isEmbed && is_logged_in()) {
    echo '<script>window.top.location.href=' . json_encode(app_url('dashboard')) . ';</script>';
    exit;
}
if (!$isEmbed) {
    redirect_logged_in_user();
}

$pageTitle = 'Login';
$error     = '';
$username  = '';

// ─── SECURITY FIX (A07 / A09): Login attempt tracking ─────────────────────

function get_client_ip(): string
{
    foreach (['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
        $val = (string)($_SERVER[$key] ?? '');
        if ($val !== '') {
            $ip = trim(explode(',', $val)[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}

function ensure_login_attempts_table(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS Login_Attempts (
                Attempt_ID INT UNSIGNED NOT NULL AUTO_INCREMENT,
                Username VARCHAR(50) NOT NULL,
                IP_Address VARCHAR(45) NOT NULL,
                Was_Successful TINYINT(1) NOT NULL DEFAULT 0,
                Attempted_At TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (Attempt_ID),
                KEY idx_login_attempts_user_time (Username, Attempted_At),
                KEY idx_login_attempts_ip_time (IP_Address, Attempted_At),
                KEY idx_login_attempts_failed_time (Was_Successful, Attempted_At)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $ready = true;
    } catch (Throwable $e) {
        // Table may already exist or DB user lacks CREATE privilege.
    }
}

function count_recent_failures(PDO $pdo, string $username, string $ip, int $windowMinutes = 30): int
{
    // Count by username only. Do NOT OR with IP — shared IPs (::1 / school LAN)
    // would lock every account after a few wrong tries on any one username.
    try {
        ensure_login_attempts_table($pdo);
        if ($username === '') {
            return 0;
        }
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM Login_Attempts
             WHERE Username = ?
               AND Was_Successful = 0
               AND Attempted_At >= DATE_SUB(NOW(), INTERVAL ? MINUTE)"
        );
        $stmt->execute([$username, $windowMinutes]);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function record_login_attempt(PDO $pdo, string $username, string $ip, bool $success): void
{
    try {
        ensure_login_attempts_table($pdo);
        $stmt = $pdo->prepare(
            'INSERT INTO Login_Attempts (Username, IP_Address, Was_Successful) VALUES (?, ?, ?)'
        );
        $stmt->execute([substr($username, 0, 50), substr($ip, 0, 45), $success ? 1 : 0]);
    } catch (Throwable $e) {
        // Silently ignore if table not ready yet
    }
}

const MAX_FAILURES    = 5;
const LOCKOUT_MINUTES = 5;

// ─── Handle POST ───────────────────────────────────────────────────────────

$MAX_ATTEMPTS  = MAX_FAILURES;
$LOCKOUT_SECS  = LOCKOUT_MINUTES * 60;
$attempts      = (int)($_SESSION['login_attempts']     ?? 0);
$lockoutUntil  = (int)($_SESSION['login_lockout_until'] ?? 0);
$isLockedOut   = ($lockoutUntil > time());
$clientIp      = get_client_ip();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();

    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    // Also respect recent DB failures (works across browsers / devices).
    $dbFailures = ($username !== '') ? count_recent_failures($pdo, $username, $clientIp, 30) : 0;
    if (!$isLockedOut && $dbFailures >= $MAX_ATTEMPTS) {
        $_SESSION['login_lockout_until'] = time() + $LOCKOUT_SECS;
        $isLockedOut = true;
        $lockoutUntil = (int)$_SESSION['login_lockout_until'];
    } elseif ($isLockedOut && $dbFailures < $MAX_ATTEMPTS && $attempts < $MAX_ATTEMPTS) {
        // Allow retry once the failure window has cooled (e.g. admin cleared attempts).
        unset($_SESSION['login_lockout_until'], $_SESSION['login_attempts']);
        $isLockedOut = false;
        $lockoutUntil = 0;
        $attempts = 0;
    }

    if ($isLockedOut) {
        $remaining = max(1, $lockoutUntil - time());
        $error = "Too many failed attempts. Please wait {$remaining} second(s) before trying again.";
        // Do not insert another failure while locked — that extends the DB lock window forever.
    } else {
        $stmt = $pdo->prepare(
            "SELECT User_ID, First_Name, Last_Name, Middle_Initial, Name_Suffix, Username, Email, Password, Role, Status
             FROM User_Accounts
             WHERE Username = ? OR Student_No = ? OR Employee_ID = ?
             LIMIT 1"
        );
        try {
            $stmt->execute([$username, $username, $username]);
            $user = $stmt->fetch();
        } catch (Throwable $e) {
            $stmt = $pdo->prepare(
                'SELECT User_ID, First_Name, Last_Name, Middle_Initial, Name_Suffix, Username, Email, Password, Role, Status
                 FROM User_Accounts WHERE Username = ? LIMIT 1'
            );
            $stmt->execute([$username]);
            $user = $stmt->fetch();
        }

        if (!$user || !password_verify($password, (string)$user['Password'])) {
            record_login_attempt($pdo, $username !== '' ? $username : '(blank)', $clientIp, false);
            $_SESSION['login_attempts'] = $attempts + 1;
            $totalFails = count_recent_failures($pdo, $username !== '' ? $username : '(blank)', $clientIp, 30);

            if ($_SESSION['login_attempts'] >= $MAX_ATTEMPTS || $totalFails >= $MAX_ATTEMPTS) {
                $_SESSION['login_lockout_until'] = time() + $LOCKOUT_SECS;
                $_SESSION['login_attempts'] = 0;
                $error = 'Too many failed attempts. Login is locked for ' . LOCKOUT_MINUTES . ' minutes.';
            } else {
                $left = max(1, $MAX_ATTEMPTS - max($_SESSION['login_attempts'], $totalFails));
                $error = "Invalid Student ID / Employee ID or password. {$left} attempt(s) left before lockout.";
            }
        } elseif (($user['Status'] ?? '') === 'Pending') {
            record_login_attempt($pdo, $username, $clientIp, false);
            $error = 'Your registration is pending verification. Please wait for an Admin to approve your account.';
        } elseif (($user['Status'] ?? '') !== 'Active') {
            record_login_attempt($pdo, $username, $clientIp, false);
            $error = 'Account is not active. Please contact the administrator.';
        } else {
            record_login_attempt($pdo, $username, $clientIp, true);
            unset($_SESSION['login_attempts'], $_SESSION['login_lockout_until']);
            login_user($user);
            if ($isEmbed) {
                echo '<script>window.top.location.href=' . json_encode(app_url('dashboard')) . ';</script>';
                exit;
            }
            app_redirect('dashboard');
        }
    }
}

$EMBED_AUTH = $isEmbed;
require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-12 col-xl-11">
    <div class="app-card auth-popup-card auth-split">
      <div class="auth-side-panel">
        <div class="auth-side-center">
          <?php if (!empty($SCHOOL_LOGO_URL)): ?>
            <img
              class="auth-side-logo"
              src="<?= htmlspecialchars(app_asset_or_url((string)$SCHOOL_LOGO_URL)) ?>"
              alt="<?= htmlspecialchars((string)($SCHOOL_NAME ?? 'School')) ?> logo">
          <?php else: ?>
            <div class="auth-side-mark">WELCOME.</div>
          <?php endif; ?>
        </div>
        <div class="auth-side-bottom">
          Not a member yet?
          <a class="fw-semibold text-decoration-none" href="<?= htmlspecialchars(app_url_embed('register', $isEmbed)) ?>">Register now</a>
        </div>
      </div>

      <div class="auth-form-panel">
        <div class="d-flex align-items-start justify-content-between mb-3">
          <h1 class="auth-title-sm mb-0">Log in</h1>
          <div class="auth-logo-wrap auth-logo-right">
            <?php if (!empty($SCHOOL_LOGO_URL)): ?>
              <img class="auth-logo-img" src="<?= htmlspecialchars(app_asset_or_url((string)$SCHOOL_LOGO_URL)) ?>" alt="<?= htmlspecialchars((string)($SCHOOL_NAME ?? 'School')) ?> logo">
            <?php else: ?>
              <span class="auth-logo-fallback"><i class="bi bi-mortarboard"></i></span>
            <?php endif; ?>
          </div>
        </div>

        <?php if ($error): ?>
          <div class="alert alert-danger d-flex align-items-start gap-2">
            <i class="bi bi-shield-exclamation mt-1 flex-shrink-0"></i>
            <div><?= htmlspecialchars($error) ?></div>
          </div>
        <?php endif; ?>

        <?php
          // Show countdown warning on 3rd and 4th failed attempt
          if ($error && $username !== '') {
              $clientIp  = get_client_ip();
              $remaining = MAX_FAILURES - count_recent_failures($pdo, $username, $clientIp, LOCKOUT_MINUTES);
              if ($remaining > 0 && $remaining <= 2) {
                  echo '<div class="alert alert-warning d-flex align-items-center gap-2">'
                     . '<i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>'
                     . '<div><strong>Warning:</strong> ' . (int)$remaining
                     . ' attempt(s) remaining before your account is temporarily locked.</div>'
                     . '</div>';
              }
          }
        ?>

        <form method="post" autocomplete="off">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

          <div class="mb-3">
            <label class="form-label auth-label" for="username">Student ID or Employee ID</label>
            <input class="form-control auth-input-minimal" id="username" name="username" required
                   value="<?= htmlspecialchars($username) ?>"
                   placeholder="Student ID or Employee ID">
          </div>

          <div class="mb-3">
            <label class="form-label auth-label" for="password">Password</label>
            <div class="input-group">
              <input class="form-control auth-input-minimal" type="password" id="password"
                     name="password" required placeholder="Password">
              <button class="btn btn-outline-secondary" type="button"
                      id="toggleLoginPassword" aria-label="Show password">
                <i class="bi bi-eye"></i>
              </button>
            </div>
          </div>

          <button class="btn btn-dark w-100 auth-submit-btn" type="submit">Log in now</button>
          <div class="text-end mt-2">
            <a class="small text-decoration-none"
               href="<?= htmlspecialchars(app_url_embed('forgot_password', $isEmbed)) ?>">Forgot password?</a>
          </div>
          <div class="text-center mt-3 small">
            Need an account?
            <a class="fw-semibold text-decoration-none"
               href="<?= htmlspecialchars(app_url_embed('register', $isEmbed)) ?>">Register</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
  (function () {
    const btn   = document.getElementById('toggleLoginPassword');
    const input = document.getElementById('password');
    if (!btn || !input) return;
    btn.addEventListener('click', () => {
      const hidden = input.type === 'password';
      input.type   = hidden ? 'text' : 'password';
      const icon   = btn.querySelector('i');
      if (icon) icon.className = hidden ? 'bi bi-eye-slash' : 'bi bi-eye';
    });
  })();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>