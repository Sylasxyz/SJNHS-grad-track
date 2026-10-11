<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app_config.php';
require_once __DIR__ . '/includes/db_config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/mailer.php';

$isEmbed = (($_GET['embed'] ?? '') === '1') || (($_POST['embed'] ?? '') === '1');
$EMBED_AUTH = $isEmbed;

$pageTitle = 'Forgot Password';
$error = '';
$success = '';
$emailState = ''; // '', 'success', 'error'
$codeState = '';
$debugCode = '';
$emailValue = strtolower(trim((string)($_POST['email'] ?? $_SESSION['pw_reset_email'] ?? '')));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $action = (string)($_POST['action'] ?? 'send_code');
    $emailValue = strtolower(trim((string)($_POST['email'] ?? '')));

    if ($action === 'send_code') {
        if ($emailValue === '' || !filter_var($emailValue, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
            $emailState = 'error';
        } else {
            try {
                $stmt = $pdo->prepare(
                    "SELECT User_ID, First_Name, Last_Name, Email, Status
                     FROM User_Accounts
                     WHERE LOWER(Email) = ?
                     LIMIT 1"
                );
                $stmt->execute([$emailValue]);
                $user = $stmt->fetch();

                if (!$user) {
                    $error = 'This email is not registered in the system.';
                    $emailState = 'error';
                } elseif ((string)($user['Status'] ?? '') !== 'Active') {
                    $error = 'This account is not active yet. Please wait for Admin approval or contact the school.';
                    $emailState = 'error';
                } else {
                    $code = (string)random_int(100000, 999999);
                    $tokenHash = hash('sha256', $code);

                    $pdo->beginTransaction();
                    $pdo->prepare('DELETE FROM Password_Resets WHERE User_ID = ?')->execute([(int)$user['User_ID']]);
                    $ins = $pdo->prepare(
                        "INSERT INTO Password_Resets (User_ID, Token_Hash, Expires_At)
                         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))"
                    );
                    $ins->execute([(int)$user['User_ID'], $tokenHash]);
                    $pdo->commit();

                    $_SESSION['pw_reset_email'] = $emailValue;
                    $_SESSION['pw_reset_user_id'] = (int)$user['User_ID'];
                    unset($_SESSION['pw_reset_verified'], $_SESSION['pw_reset_id']);

                    $name = trim((string)($user['First_Name'] ?? '') . ' ' . (string)($user['Last_Name'] ?? '')) ?: 'User';
                    $toEmail = (string)$user['Email'];
                    $subject = 'Password Reset Code — San Josef NHS';
                    $body =
                        "Hello {$name},\n\n" .
                        "We received a request to reset the password for your Graduate Tracking System account.\n" .
                        "Your verification code is:\n\n" .
                        "{$code}\n\n" .
                        "Enter this 6-digit code on the password reset page. It expires in 15 minutes.\n\n" .
                        "If you did not request this, you can ignore this email. Your password will stay the same.\n\n" .
                        "— San Josef National High School\n";

                    $mailResult = send_app_email($toEmail, $subject, $body);

                    global $MAIL_SHOW_RESET_LINK_ON_FAILURE;
                    if (!$mailResult['ok'] && !empty($MAIL_SHOW_RESET_LINK_ON_FAILURE)) {
                        $debugCode = $code;
                        $success = msg_password_reset_show_code_success();
                        $emailState = 'success';
                    } elseif ($mailResult['ok']) {
                        $success = 'A 6-digit verification code has been sent to ' . $emailValue . '. Please check your inbox (and spam folder).';
                        $emailState = 'success';
                    } else {
                        $error = 'Could not send the verification code email. Please try again later or contact the school.';
                        $emailState = 'error';
                    }
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Unable to process password reset right now. Please try again later.';
                $emailState = 'error';
            }
        }
    }

    if ($action === 'verify_code') {
        $code = preg_replace('/\D+/', '', (string)($_POST['verification_code'] ?? '')) ?? '';
        $sessionEmail = strtolower(trim((string)($_SESSION['pw_reset_email'] ?? '')));
        if ($emailValue === '') {
            $emailValue = $sessionEmail;
        }

        if ($emailValue === '' || !filter_var($emailValue, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter your registered email address first.';
            $emailState = 'error';
        } elseif ($sessionEmail === '' || $sessionEmail !== $emailValue) {
            $error = 'Please send a verification code to this email first.';
            $emailState = 'error';
            $codeState = 'error';
        } elseif (!preg_match('/^\d{6}$/', $code)) {
            $error = 'Please enter the 6-digit verification code from your email.';
            $emailState = 'success';
            $codeState = 'error';
        } else {
            try {
                $tokenHash = hash('sha256', $code);
                $stmt = $pdo->prepare(
                    "SELECT pr.Reset_ID, pr.User_ID, ua.Email, ua.Status
                     FROM Password_Resets pr
                     INNER JOIN User_Accounts ua ON ua.User_ID = pr.User_ID
                     WHERE pr.Token_Hash = ?
                       AND LOWER(ua.Email) = ?
                       AND pr.Used_At IS NULL
                       AND pr.Expires_At > NOW()
                     LIMIT 1"
                );
                $stmt->execute([$tokenHash, $emailValue]);
                $resetRow = $stmt->fetch() ?: null;

                if (!$resetRow || (string)($resetRow['Status'] ?? '') !== 'Active') {
                    $error = 'Invalid or expired verification code. Please request a new code.';
                    $emailState = 'success';
                    $codeState = 'error';
                } else {
                    $_SESSION['pw_reset_verified'] = true;
                    $_SESSION['pw_reset_user_id'] = (int)$resetRow['User_ID'];
                    $_SESSION['pw_reset_id'] = (int)$resetRow['Reset_ID'];
                    $_SESSION['pw_reset_email'] = $emailValue;

                    app_redirect('reset_password', $isEmbed ? ['embed' => 1] : []);
                }
            } catch (Throwable $e) {
                $error = 'Unable to verify the code right now. Please try again later.';
                $codeState = 'error';
            }
        }
    }
}

if ($emailState === '' && $emailValue !== '' && !empty($_SESSION['pw_reset_email']) && $emailValue === $_SESSION['pw_reset_email']) {
    $emailState = 'success';
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-12 col-md-7 col-lg-5">
    <div class="app-card p-3 p-md-4 pw-reset-card">
      <h1 class="h4 mb-2">Forgot Password</h1>
      <p class="text-muted small mb-3">Enter your registered email, then request a 6-digit verification code to reset your password.</p>

      <?php if ($error): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <?php if ($success): ?><div class="alert alert-success py-2"><?= htmlspecialchars($success) ?></div><?php endif; ?>

      <?php if ($debugCode !== ''): ?>
        <div class="alert alert-warning py-2">
          <div class="fw-semibold mb-1">Verification code</div>
          <div class="fs-4 fw-bold letter-spacing"><?= htmlspecialchars($debugCode) ?></div>
          <div class="small mt-2 mb-0"><?= htmlspecialchars(msg_password_reset_code_fallback()) ?></div>
        </div>
      <?php endif; ?>

      <form method="post" autocomplete="off" class="pw-reset-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <?php if ($isEmbed): ?><input type="hidden" name="embed" value="1"><?php endif; ?>

        <div class="mb-3">
          <label class="form-label" for="email">Email address</label>
          <input
            class="form-control pw-reset-email<?= $emailState === 'success' ? ' is-valid-email' : ($emailState === 'error' ? ' is-invalid-email' : '') ?>"
            type="email"
            id="email"
            name="email"
            required
            placeholder="Please enter your email address"
            value="<?= htmlspecialchars($emailValue) ?>"
          >
          <div class="form-text">Please enter a valid email address to receive our message.</div>
        </div>

        <div class="mb-3">
          <label class="form-label" for="verification_code">Email verification code</label>
          <div class="pw-code-row">
            <input
              class="form-control pw-code-input<?= $codeState === 'error' ? ' is-invalid-email' : '' ?>"
              type="text"
              inputmode="numeric"
              pattern="[0-9]{6}"
              maxlength="6"
              id="verification_code"
              name="verification_code"
              placeholder="Email Verification Code"
              autocomplete="one-time-code"
            >
            <button class="btn btn-dark pw-send-code-btn" type="submit" name="action" value="send_code">Send Code</button>
          </div>
          <div class="form-text">Tap “Send Code” and we will email a 6-digit verification code. Enter it here, then continue.</div>
        </div>

        <button class="btn btn-primary w-100" type="submit" name="action" value="verify_code">
          Verify code &amp; continue
        </button>
      </form>

      <div class="text-center mt-3">
        <a href="<?= htmlspecialchars(app_url_embed('login', $isEmbed)) ?>">Back to Login</a>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
