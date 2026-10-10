<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/db_config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

$isEmbed = (($_GET['embed'] ?? '') === '1') || (($_POST['embed'] ?? '') === '1');
$EMBED_AUTH = $isEmbed;

$pageTitle = 'Reset Password';
$error = '';
$success = '';

$verified = !empty($_SESSION['pw_reset_verified']);
$userId = (int)($_SESSION['pw_reset_user_id'] ?? 0);
$resetId = (int)($_SESSION['pw_reset_id'] ?? 0);
$email = (string)($_SESSION['pw_reset_email'] ?? '');
$canReset = false;

if ($verified && $userId > 0 && $resetId > 0) {
    try {
        $stmt = $pdo->prepare(
            "SELECT pr.Reset_ID, pr.User_ID, ua.Email, ua.Status
             FROM Password_Resets pr
             INNER JOIN User_Accounts ua ON ua.User_ID = pr.User_ID
             WHERE pr.Reset_ID = ?
               AND pr.User_ID = ?
               AND pr.Used_At IS NULL
               AND pr.Expires_At > NOW()
             LIMIT 1"
        );
        $stmt->execute([$resetId, $userId]);
        $resetRow = $stmt->fetch() ?: null;
        if ($resetRow && (string)($resetRow['Status'] ?? '') === 'Active') {
            $canReset = true;
            $email = (string)$resetRow['Email'];
        }
    } catch (Throwable $e) {
        $canReset = false;
        $error = 'Password reset is unavailable until the database migration is applied.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if (!$canReset) {
        $error = 'Your verification session is invalid or expired. Please request a new code.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $error = 'Password must include at least one letter and one number.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $pdo->beginTransaction();
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE User_Accounts SET Password = ? WHERE User_ID = ?')
                ->execute([$hash, $userId]);
            $pdo->prepare('UPDATE Password_Resets SET Used_At = NOW() WHERE Reset_ID = ?')
                ->execute([$resetId]);
            $pdo->prepare('UPDATE Password_Resets SET Used_At = NOW() WHERE User_ID = ? AND Used_At IS NULL')
                ->execute([$userId]);
            $pdo->commit();

            unset(
                $_SESSION['pw_reset_verified'],
                $_SESSION['pw_reset_user_id'],
                $_SESSION['pw_reset_id'],
                $_SESSION['pw_reset_email']
            );

            $success = 'Password has been reset. You can now log in with your new password.';
            $canReset = false;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Could not reset password. Please try again.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-12 col-md-7 col-lg-5">
    <div class="app-card p-3 p-md-4">
      <h1 class="h4 mb-3">Create New Password</h1>
      <?php if ($error): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <?php if ($success): ?>
        <div class="alert alert-success py-2"><?= htmlspecialchars($success) ?></div>
        <a class="btn btn-primary w-100" href="<?= htmlspecialchars(app_url_embed('login', $isEmbed)) ?>">Go to Login</a>
      <?php elseif (!$canReset): ?>
        <div class="alert alert-warning py-2">Please verify your email with a 6-digit code first.</div>
        <a class="btn btn-outline-primary w-100" href="<?= htmlspecialchars(app_url_embed('forgot_password', $isEmbed)) ?>">Request a verification code</a>
      <?php else: ?>
        <p class="text-muted small mb-3">
          Resetting password for <strong><?= htmlspecialchars($email) ?></strong>
        </p>
        <form method="post" autocomplete="off">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
          <?php if ($isEmbed): ?><input type="hidden" name="embed" value="1"><?php endif; ?>
          <div class="mb-3">
            <label class="form-label" for="password">New Password</label>
            <input class="form-control" type="password" id="password" name="password" required minlength="8">
            <div class="form-text">At least 8 characters, with letters and numbers.</div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="confirm_password">Confirm Password</label>
            <input class="form-control" type="password" id="confirm_password" name="confirm_password" required minlength="8">
          </div>
          <button class="btn btn-primary w-100" type="submit">Reset Password</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
