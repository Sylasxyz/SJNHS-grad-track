<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/app_config.php';
$phpSelf = $_SERVER['PHP_SELF'] ?? '';
$prefix = (strpos($phpSelf, '/admin/') !== false || strpos($phpSelf, '/instructor/') !== false) ? '../' : '';
$isEmbedAuth = (bool)($EMBED_AUTH ?? false);

function app_asset_or_url(string $value, string $prefix): string
{
    $v = trim($value);
    if ($v === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $v)) {
        return $v;
    }
    return $prefix . ltrim($v, '/');
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= isset($pageTitle) ? htmlspecialchars((string)$pageTitle) : 'Graduate Tracking System' ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= htmlspecialchars($prefix . 'assets/app.css') ?>">
</head>
<body class="app-body<?= $isEmbedAuth ? ' auth-embed-body' : '' ?>"> 
  <?php if (!$isEmbedAuth): ?>
  <header class="app-topbar app-topbar-compact">
    <div class="container app-topbar-inner landing-container">
      <div class="app-topbar-row">
        <a class="text-decoration-none app-topbar-brand" href="<?= htmlspecialchars($prefix . 'dashboard.php') ?>">
          <div class="d-flex align-items-center gap-3">
            <?php if (!empty($SCHOOL_LOGO_URL)): ?>
              <img
                class="app-brand-img"
                src="<?= htmlspecialchars(app_asset_or_url((string)$SCHOOL_LOGO_URL, $prefix)) ?>"
                alt="<?= htmlspecialchars((string)($SCHOOL_NAME ?? 'School')) ?> logo">
            <?php endif; ?>
            <div class="lh-sm">
              <div class="fw-bold app-topbar-brand-title"><?= htmlspecialchars((string)($SCHOOL_NAME ?? 'School')) ?></div>
              <div class="small app-topbar-brand-sub">Graduate Tracking System</div>
            </div>
          </div>
        </a>

        <div class="app-topbar-actions">
          <a class="app-topbar-link" href="<?= htmlspecialchars($prefix . 'index.php') ?>">Back to Homepage</a>
          <?php if (is_logged_in()): ?>
            <a class="btn btn-sm btn-dark app-btn app-topbar-dash-btn" href="<?= htmlspecialchars($prefix . 'dashboard.php') ?>"><i class="bi bi-grid-1x2 me-1"></i>Dashboard</a>
            <span class="app-topbar-meta">Role: <?= htmlspecialchars(current_user()['Role'] ?? '') ?></span>
            <a class="btn btn-sm btn-outline-secondary app-btn" href="<?= htmlspecialchars($prefix . 'logout.php') ?>">Logout</a>
          <?php else: ?>
            <a class="btn btn-sm btn-outline-secondary app-btn" href="<?= htmlspecialchars($prefix . 'login.php') ?>">Login</a>
            <a class="btn btn-sm btn-primary app-btn" href="<?= htmlspecialchars($prefix . 'register.php') ?>">Register</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </header>
  <?php endif; ?>

  <main class="<?= $isEmbedAuth ? 'container-fluid auth-embed-main py-3' : 'container-fluid app-main py-4' ?>">

