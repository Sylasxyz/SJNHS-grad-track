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
$error     = pull_flash_error();
$username  = '';

// ─── Handle POST ───────────────────────────────────────────────────────────

$MAX_ATTEMPTS  = LOGIN_MAX_FAILURES;
$LOCKOUT_SECS  = LOGIN_LOCKOUT_MINUTES * 60;
$attempts      = (int)($_SESSION['login_attempts']     ?? 0);
$lockoutUntil  = (int)($_SESSION['login_lockout_until'] ?? 0);
$isLockedOut   = ($lockoutUntil > time());
$clientIp      = login_client_ip();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();

    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    // Also respect recent DB failures (works across browsers / devices).
    $dbFailures = ($username !== '') ? count_recent_login_failures($pdo, $username) : 0;
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
            $totalFails = count_recent_login_failures($pdo, $username !== '' ? $username : '(blank)');

            if ($_SESSION['login_attempts'] >= $MAX_ATTEMPTS || $totalFails >= $MAX_ATTEMPTS) {
                $_SESSION['login_lockout_until'] = time() + $LOCKOUT_SECS;
                $_SESSION['login_attempts'] = 0;
                $error = 'Too many failed attempts. Login is locked for ' . LOGIN_LOCKOUT_MINUTES . ' minutes.';
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
              $remaining = LOGIN_MAX_FAILURES - count_recent_login_failures($pdo, $username);
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
