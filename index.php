<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/landing_shell.php';
$loggedIn = is_logged_in();

$pageTitle = 'Welcome';
$schoolName = (string)($SCHOOL_NAME ?? 'School');
$logoUrl = (string)($SCHOOL_LOGO_URL ?? '');
$heroUrl = (string)($SCHOOL_HERO_IMAGE_URL ?? '');
$locationLabel = (string)($SCHOOL_LOCATION_LABEL ?? '');
$carousel = $LANDING_CAROUSEL_IMAGES ?? [];
if (!is_array($carousel)) {
    $carousel = [];
}
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle) ?> · <?= htmlspecialchars($schoolName) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="assets/app.css">
</head>
<body class="landing-page app-body">

<nav class="navbar navbar-expand-lg landing-nav sticky-top">
  <div class="container landing-container">
    <a class="navbar-brand d-flex align-items-center gap-2 fw-bold" href="<?= htmlspecialchars(app_url('index')) ?>">
      <?php if ($logoUrl): ?>
        <img src="<?= htmlspecialchars(landing_asset_or_url($logoUrl)) ?>" alt="<?= htmlspecialchars($schoolName) ?> logo" style="width:32px;height:32px;border-radius:10px;object-fit:cover;">
      <?php endif; ?>
      <span><?= htmlspecialchars($schoolName) ?></span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#landingNav" aria-controls="landingNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="landingNav">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
        <li class="nav-item"><a class="nav-link" href="<?= htmlspecialchars(app_url('about')) ?>">About</a></li>
        <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
        <li class="nav-item"><a class="nav-link" href="#access">Get access</a></li>
        <?php if ($loggedIn): ?>
          <li class="nav-item ms-lg-2">
            <a class="btn btn-sm btn-outline-secondary landing-cta app-btn" href="<?= htmlspecialchars(app_url('dashboard')) ?>">Dashboard</a>
          </li>
          <li class="nav-item">
            <a class="btn btn-sm btn-primary landing-cta app-btn" href="<?= htmlspecialchars(app_url('logout')) ?>">Logout</a>
          </li>
        <?php else: ?>
          <li class="nav-item ms-lg-2">
            <a class="btn btn-sm btn-primary landing-cta app-btn js-open-auth" data-auth-mode="login" href="<?= htmlspecialchars(app_url('login')) ?>">Login</a>
          </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<header class="landing-hero-header py-4 pt-lg-5 pb-lg-0">
  <div class="container landing-container">
    <div class="row landing-hero-row g-4">
      <div class="col-12 col-lg-6 landing-hero-copy">
        <div class="landing-pill mb-3">
          <i class="bi bi-mortarboard"></i>
          <span>Graduate Tracking System</span>
          <?php if ($locationLabel): ?>
            <span class="text-muted">· <?= htmlspecialchars($locationLabel) ?></span>
          <?php endif; ?>
        </div>
        <h1 class="landing-hero-title mb-3">
          <span class="landing-hero-title-line">Connecting Graduates.</span>
          <span class="landing-hero-title-line landing-hero-title-accent">Building Futures.</span>
        </h1>
        <p class="landing-hero-sub mb-4">
          A secure and reliable portal for San Josef graduates to share their journey, while the school tracks progress and creates a better future for all.
        </p>
        <div class="d-flex flex-wrap gap-2">
          <?php if ($loggedIn): ?>
            <a class="btn btn-primary landing-cta app-btn" href="<?= htmlspecialchars(app_url('dashboard')) ?>"><i class="bi bi-grid me-1"></i> Open Dashboard</a>
            <a class="btn btn-outline-secondary landing-cta app-btn" href="<?= htmlspecialchars(app_url('register')) ?>"><i class="bi bi-person-plus me-1"></i> Register Another Account</a>
          <?php else: ?>
            <a class="btn btn-primary landing-cta app-btn js-open-auth" data-auth-mode="register" href="<?= htmlspecialchars(app_url('register')) ?>"><i class="bi bi-person-plus me-1"></i> Register</a>
            <a class="btn btn-outline-secondary landing-cta app-btn js-open-auth" data-auth-mode="login" href="<?= htmlspecialchars(app_url('login')) ?>"><i class="bi bi-box-arrow-in-right me-1"></i> Login</a>
          <?php endif; ?>
        </div>
      </div>

      <div class="col-12 col-lg-6 landing-hero-visual-col">
        <div class="landing-hero-visual">
          <?php if ($heroUrl): ?>
            <img src="<?= htmlspecialchars(landing_asset_or_url($heroUrl)) ?>" alt="<?= htmlspecialchars($schoolName) ?> students" class="landing-hero-photo">
          <?php else: ?>
            <div class="p-4 text-muted">Add a cover image in <code>includes/app_config.php</code> → <code>$SCHOOL_HERO_IMAGE_URL</code>.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</header>

<section id="overview" class="py-5 landing-section-alt">
  <div class="container landing-container">
    <div class="text-center mb-4">
      <h2 class="landing-section-title">Built for Graduate Tracking</h2>
      <p class="text-muted mb-2">Simple for students. Powerful for the school.</p>
      <a class="fw-semibold text-decoration-none" href="<?= htmlspecialchars(app_url('about')) ?>" style="color:#111827;font-size:.85rem;">Learn more about us →</a>
    </div>
    <div class="row g-3">
      <div class="col-12 col-md-4">
        <div class="landing-feature-card">
          <div class="d-flex gap-2">
            <i class="bi bi-person-check fs-4"></i>
            <div>
              <div class="fw-bold">Verified access</div>
              <div class="text-muted small">Students register, then Admin approves before they can log in.</div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-12 col-md-4">
        <div class="landing-feature-card">
          <div class="d-flex gap-2">
            <i class="bi bi-briefcase fs-4"></i>
            <div>
              <div class="fw-bold">Outcome tracking</div>
              <div class="text-muted small">Employment, self-employment, unemployment, and further studies.</div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-12 col-md-4">
        <div class="landing-feature-card">
          <div class="d-flex gap-2">
            <i class="bi bi-bar-chart-line fs-4"></i>
            <div>
              <div class="fw-bold">Reports & insights</div>
              <div class="text-muted small">Admin dashboards summarize employment statistics with charts.</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section id="features" class="py-5">
  <div class="container landing-container">
    <div class="row align-items-end mb-4 g-2">
      <div class="col">
        <h2 class="landing-section-title mb-1">Key Features</h2>
        <div class="text-muted">Everything needed for accurate graduate monitoring.</div>
      </div>
    </div>

    <div class="row g-3">
      <div class="col-12 col-md-6 col-lg-4">
        <div class="landing-feature-card">
          <div class="fw-bold mb-1"><i class="bi bi-ui-checks-grid me-2"></i>Graduate Profiles</div>
          <div class="text-muted small">Students update their profile and submitted information anytime.</div>
        </div>
      </div>
      <div class="col-12 col-md-6 col-lg-4">
        <div class="landing-feature-card">
          <div class="fw-bold mb-1"><i class="bi bi-funnel me-2"></i>Search & Filter</div>
          <div class="text-muted small">Instructors/Admin can filter by batch year and employment status.</div>
        </div>
      </div>
      <div class="col-12 col-md-6 col-lg-4">
        <div class="landing-feature-card">
          <div class="fw-bold mb-1"><i class="bi bi-megaphone me-2"></i>Announcements</div>
          <div class="text-muted small">Post school updates and reminders for graduates.</div>
        </div>
      </div>
      <div class="col-12 col-md-6 col-lg-4">
        <div class="landing-feature-card">
          <div class="fw-bold mb-1"><i class="bi bi-shield-check me-2"></i>RBAC Security</div>
          <div class="text-muted small">Role-based access control keeps admin pages protected.</div>
        </div>
      </div>
      <div class="col-12 col-md-6 col-lg-4">
        <div class="landing-feature-card">
          <div class="fw-bold mb-1"><i class="bi bi-file-earmark-text me-2"></i>Report Snapshots</div>
          <div class="text-muted small">Save summary snapshots into the Reports table.</div>
        </div>
      </div>
      <div class="col-12 col-md-6 col-lg-4">
        <div class="landing-feature-card">
          <div class="fw-bold mb-1"><i class="bi bi-phone me-2"></i>Mobile Responsive</div>
          <div class="text-muted small">Fast UI built with Bootstrap 5 and responsive layouts.</div>
        </div>
      </div>
    </div>
  </div>
</section>

<section id="access" class="py-5 landing-section-alt">
  <div class="container landing-container">
    <div class="text-center mb-4">
      <h2 class="landing-section-title">Get access</h2>
      <p class="text-muted mb-0">Choose the right portal for your role.</p>
    </div>
    <div class="row g-3">
      <div class="col-12 col-md-4">
        <div class="landing-feature-card">
          <div class="fw-bold mb-1"><i class="bi bi-person me-2"></i>Graduate</div>
          <div class="text-muted small mb-3">Register, then wait for verification.</div>
          <a class="link-primary fw-semibold text-decoration-none js-open-auth" data-auth-mode="register" href="<?= htmlspecialchars(app_url('register')) ?>">Student registration →</a>
        </div>
      </div>
      <div class="col-12 col-md-4">
        <div class="landing-feature-card">
          <div class="fw-bold mb-1"><i class="bi bi-box-arrow-in-right me-2"></i>Login</div>
          <div class="text-muted small mb-3">Access your dashboard after approval.</div>
          <a class="link-primary fw-semibold text-decoration-none js-open-auth" data-auth-mode="login" href="<?= htmlspecialchars(app_url('login')) ?>">Login →</a>
        </div>
      </div>
      <div class="col-12 col-md-4">
        <div class="landing-feature-card">
          <div class="fw-bold mb-1"><i class="bi bi-shield-lock me-2"></i>Admin Portal</div>
          <div class="text-muted small mb-3">Verify registrations, manage users, and view reports.</div>
          <a class="link-primary fw-semibold text-decoration-none js-open-auth" data-auth-mode="login" href="<?= htmlspecialchars(app_url('login')) ?>">Login →</a>
        </div>
      </div>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/landing_footer.php'; ?>

<?php if (!$loggedIn): ?>
<div class="modal fade auth-modal" id="authModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered auth-modal-dialog">
    <div class="modal-content auth-modal-content">
      <div class="auth-modal-head">
        <div class="visually-hidden" id="authModalTitle">Log in</div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="auth-modal-body">
        <iframe id="authFrame" class="auth-frame" src="about:blank" title="Authentication"></iframe>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php if (!$loggedIn): ?>
<script>
  (function () {
    const modalEl = document.getElementById('authModal');
    const frame = document.getElementById('authFrame');
    const title = document.getElementById('authModalTitle');
    if (!modalEl || !frame || !title) return;
    const modal = new bootstrap.Modal(modalEl);
    document.querySelectorAll('.js-open-auth').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        const mode = btn.getAttribute('data-auth-mode') || 'login';
        const src = mode === 'register'
          ? <?= json_encode(app_url('register', ['embed' => 1])) ?>
          : <?= json_encode(app_url('login', ['embed' => 1])) ?>;
        title.textContent = mode === 'register' ? 'Create your account' : 'Log in';
        frame.src = src;
        modal.show();
      });
    });
    modalEl.addEventListener('hidden.bs.modal', () => {
      frame.src = 'about:blank';
    });
  })();
</script>
<?php endif; ?>
</body>
</html>

