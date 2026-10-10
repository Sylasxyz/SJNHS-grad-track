<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/landing_shell.php';
$loggedIn = is_logged_in();

$pageTitle = 'About';
$schoolName = (string)($SCHOOL_NAME ?? 'School');
$logoUrl = (string)($SCHOOL_LOGO_URL ?? '');
$aboutImageUrl = (string)($SCHOOL_ABOUT_IMAGE_URL ?? '');
$locationLabel = (string)($SCHOOL_LOCATION_LABEL ?? '');
$tagline = (string)($SCHOOL_TAGLINE ?? '');
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
<body class="landing-page about-page app-body">

<nav class="navbar navbar-expand-lg landing-nav sticky-top">
  <div class="container landing-container">
    <a class="navbar-brand d-flex align-items-center gap-2 fw-bold" href="index.php">
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
        <li class="nav-item"><a class="nav-link active" href="about.php">About</a></li>
        <li class="nav-item"><a class="nav-link" href="index.php#features">Features</a></li>
        <li class="nav-item"><a class="nav-link" href="index.php#access">Get access</a></li>
        <?php if ($loggedIn): ?>
          <li class="nav-item ms-lg-2">
            <a class="btn btn-sm btn-outline-secondary landing-cta app-btn" href="dashboard.php">Dashboard</a>
          </li>
          <li class="nav-item">
            <a class="btn btn-sm btn-primary landing-cta app-btn" href="logout.php">Logout</a>
          </li>
        <?php else: ?>
          <li class="nav-item ms-lg-2">
            <a class="btn btn-sm btn-primary landing-cta app-btn js-open-auth" data-auth-mode="login" href="login.php">Login</a>
          </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<header class="about-hero">
  <div class="container landing-container">
    <div class="row align-items-center g-4 about-hero-row">
      <div class="col-12 col-lg-6 about-hero-copy about-reveal">
        <div class="about-eyebrow">
          <i class="bi bi-info-circle"></i>
          <span>About</span>
        </div>
        <h1 class="about-hero-title">
          <?= htmlspecialchars($schoolName) ?> Tracking System
        </h1>
        <p class="about-hero-lead">
          San Josef National High School is committed to supporting students beyond Senior High School.
          Through our Graduate Tracking System, we stay connected with graduates<?= $locationLabel ? ' in ' . htmlspecialchars($locationLabel) : '' ?> —
          helping them share their employment and education journey while giving the school reliable data for guidance, reporting, and planning.
        </p>
        <?php if ($tagline): ?>
          <p class="about-hero-tagline"><?= htmlspecialchars($tagline) ?></p>
        <?php endif; ?>
      </div>
      <div class="col-12 col-lg-6 about-hero-media about-reveal about-reveal-delay">
        <figure class="about-hero-photo<?= $aboutImageUrl ? '' : ' is-empty' ?>">
          <?php if ($aboutImageUrl): ?>
            <div class="about-hero-photo-frame">
              <img
                src="<?= htmlspecialchars(landing_asset_or_url($aboutImageUrl)) ?>"
                alt="<?= htmlspecialchars($schoolName) ?> Annex building entrance"
                width="1200"
                height="900"
                loading="eager"
                decoding="async"
              >
              <div class="about-hero-fade" aria-hidden="true"></div>
            </div>
            <figcaption class="about-hero-photo-caption">
              San Josef National High School (Annex)<?= $locationLabel ? ' · ' . htmlspecialchars($locationLabel) : '' ?>
            </figcaption>
          <?php else: ?>
            <div class="about-hero-photo-empty">
              <i class="bi bi-building"></i>
              <span>Add school photo in <code>$SCHOOL_ABOUT_IMAGE_URL</code></span>
            </div>
          <?php endif; ?>
        </figure>
      </div>
    </div>
  </div>
</header>

<section class="about-section about-mv">
  <div class="container landing-container">
    <div class="text-center mb-4 about-reveal">
      <h2 class="landing-section-title">Mission and Vision of our system</h2>
    </div>
    <div class="row g-4">
      <div class="col-12 col-md-6 about-reveal">
        <article class="about-mv-card">
          <div class="about-mv-icon"><i class="bi bi-bullseye"></i></div>
          <h3>Our Mission</h3>
          <p>
            To provide a secure and reliable digital platform that helps San Josef NHS graduates update their
            employment and further-studies information, while enabling instructors and administrators to verify
            records, communicate updates, and support continuous graduate monitoring.
          </p>
        </article>
      </div>
      <div class="col-12 col-md-6 about-reveal about-reveal-delay">
        <article class="about-mv-card">
          <div class="about-mv-icon"><i class="bi bi-eye"></i></div>
          <h3>Our Vision</h3>
          <p>
            A connected school community where every graduate’s journey is documented, verified, and used to
            guide better decisions — building stronger pathways to employment, entrepreneurship, and higher education.
          </p>
        </article>
      </div>
    </div>
  </div>
</section>

<section class="about-section about-why">
  <div class="container landing-container">
    <div class="text-center mb-5 about-reveal">
      <div class="about-why-eyebrow">Why it matters</div>
      <h2 class="landing-section-title mb-2">More Than Tracking. A Connection That Continues.</h2>
      <p class="about-why-sub text-muted mb-0">
        From registration to reports, our system supports the full graduate journey — not just a one-time form.
      </p>
    </div>

    <div class="about-process about-reveal">
      <div class="about-process-line" aria-hidden="true"></div>
      <div class="row g-4 about-process-row">
        <div class="col-12 col-md-6 col-lg-3">
          <article class="about-process-step">
            <div class="about-process-icon"><i class="bi bi-people-fill"></i></div>
            <div class="about-process-kicker">Connect</div>
            <h3>Remember Every Journey</h3>
            <p>Graduates register and stay linked to San Josef NHS through a secure personal portal.</p>
          </article>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
          <article class="about-process-step">
            <div class="about-process-icon"><i class="bi bi-pencil-square"></i></div>
            <div class="about-process-kicker">Update</div>
            <h3>Keep Information Current</h3>
            <p>Profiles track employment, self-employment, unemployment, and further studies over time.</p>
          </article>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
          <article class="about-process-step">
            <div class="about-process-icon"><i class="bi bi-shield-check"></i></div>
            <div class="about-process-kicker">Verify</div>
            <h3>Build Trust Through Verification</h3>
            <p>Instructors verify records so the school can rely on accurate, checked graduate data.</p>
          </article>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
          <article class="about-process-step">
            <div class="about-process-icon"><i class="bi bi-bar-chart-fill"></i></div>
            <div class="about-process-kicker">Learn</div>
            <h3>Turn Journeys Into Insights</h3>
            <p>Analytics and reports help the school monitor outcomes and support better planning.</p>
          </article>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="about-section about-cta-band">
  <div class="container landing-container text-center about-reveal">
    <h2 class="landing-section-title mb-2">Ready to get started?</h2>
    <p class="text-muted mb-4">Register as a graduate or log in to open your dashboard.</p>
    <div class="d-flex flex-wrap justify-content-center gap-2">
      <?php if ($loggedIn): ?>
        <a class="btn btn-primary landing-cta app-btn" href="dashboard.php"><i class="bi bi-grid me-1"></i> Open Dashboard</a>
      <?php else: ?>
        <a class="btn btn-primary landing-cta app-btn js-open-auth" data-auth-mode="register" href="register.php"><i class="bi bi-person-plus me-1"></i> Register</a>
        <a class="btn btn-outline-secondary landing-cta app-btn js-open-auth" data-auth-mode="login" href="login.php"><i class="bi bi-box-arrow-in-right me-1"></i> Login</a>
      <?php endif; ?>
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
<script>
  (function () {
    const nodes = document.querySelectorAll('.about-reveal');
    if (!nodes.length) return;
    if (!('IntersectionObserver' in window)) {
      nodes.forEach((el) => el.classList.add('is-visible'));
      return;
    }
    const io = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.14, rootMargin: '0px 0px -40px 0px' });
    nodes.forEach((el) => io.observe(el));
  })();
</script>
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
        const src = mode === 'register' ? 'register.php?embed=1' : 'login.php?embed=1';
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
