<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db_config.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/mailer.php';

require_roles(['Admin']);

$pageTitle = 'Test email';
$msg = '';
$err = '';
$detail = '';

$me = current_user();
$defaultTo = (string)($me['Email'] ?? '');
$diagnostics = mail_admin_diagnostics();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $to = trim((string)($_POST['to_email'] ?? ''));
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $err = 'Enter a valid recipient email.';
    } else {
        $result = send_app_email(
            $to,
            'SMTP test — San Josef NHS Graduate Tracking',
            "This is a test message from the Graduate Tracking System.\n\n"
            . 'If you received this, outbound email is working on ' . date('Y-m-d H:i:s T') . ".\n"
        );
        if (!empty($result['ok'])) {
            $msg = 'Test email sent via ' . htmlspecialchars((string)$result['via']) . '. Check the inbox (and spam).';
        } else {
            $err = 'Test email failed.';
            $detail = (string)($result['error'] ?? '');
            if (!mail_outbound_ready()) {
                $detail = 'Mail is not configured. ' . mail_operator_setup_hint();
            }
        }
        $diagnostics = mail_admin_diagnostics();
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-shell">
  <?php require_once __DIR__ . '/../includes/dashboard_rail.php'; ?>
  <div class="dashboard-content">
    <div class="app-card p-3 p-md-4 mb-3">
      <h1 class="h4 mb-2">Test outbound email</h1>
      <p class="text-muted small mb-0">Admin only. On Railway set <code>BREVO_API_KEY</code> + <code>MAIL_FROM_EMAIL</code> (recommended), then redeploy.</p>
    </div>

    <div class="app-card p-3 p-md-4 mb-3">
      <h2 class="h6 mb-2">Connection check</h2>
      <ul class="small mb-0 font-monospace">
        <?php foreach ($diagnostics as $line): ?>
          <li><?= htmlspecialchars($line) ?></li>
        <?php endforeach; ?>
      </ul>
      <?php if (!mail_outbound_ready()): ?>
        <div class="alert alert-warning small mt-3 mb-0">
          <strong>Mail not configured.</strong> Railway → web service → Variables → add
          <code>BREVO_API_KEY</code> (Brevo → SMTP &amp; API → API Keys), <code>MAIL_FROM_EMAIL</code> (verified sender),
          <code>SMTP_ENABLED=1</code>, then redeploy.
        </div>
      <?php elseif (mail_on_railway() && !mail_brevo_api_ready()): ?>
        <div class="alert alert-warning small mt-3 mb-0">
          <strong>SMTP ports often time out on Railway.</strong> Add <code>BREVO_API_KEY</code> to send via HTTPS (port 443).
        </div>
      <?php endif; ?>
    </div>

    <div class="app-card p-3 p-md-4">
      <?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
      <?php if ($err): ?>
        <div class="alert alert-danger">
          <?= htmlspecialchars($err) ?>
          <?php if ($detail !== ''): ?>
            <div class="small mt-2 mb-0"><strong>Details:</strong> <?= htmlspecialchars($detail) ?></div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <form method="post" class="row g-3" style="max-width: 28rem;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <div class="col-12">
          <label class="form-label" for="to_email">Send test to</label>
          <input class="form-control" type="email" id="to_email" name="to_email" required
                 value="<?= htmlspecialchars((string)($_POST['to_email'] ?? $defaultTo)) ?>">
        </div>
        <div class="col-12">
          <button class="btn btn-primary" type="submit">Send test email</button>
          <a class="btn btn-outline-secondary ms-2" href="<?= htmlspecialchars(app_url('dashboard')) ?>">Back</a>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
