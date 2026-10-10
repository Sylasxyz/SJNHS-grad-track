<?php
declare(strict_types=1);

$schoolName = (string)($SCHOOL_NAME ?? 'School');
$logoUrl = (string)($SCHOOL_LOGO_URL ?? '');
$tagline = (string)($SCHOOL_TAGLINE ?? '');
$address = (string)($SCHOOL_ADDRESS ?? ($SCHOOL_LOCATION_LABEL ?? ''));
$email = (string)($SCHOOL_EMAIL ?? '');
$phone = (string)($SCHOOL_PHONE ?? '');
$privacyUrl = (string)($SCHOOL_PRIVACY_URL ?? '#');
$termsUrl = (string)($SCHOOL_TERMS_URL ?? '#');
$facebook = (string)($SCHOOL_SOCIAL_FACEBOOK ?? '#');
$instagram = (string)($SCHOOL_SOCIAL_INSTAGRAM ?? '#');
$year = date('Y');
?>

<footer class="landing-footer">
  <div class="landing-footer-main">
    <div class="container landing-container">
      <div class="row landing-footer-grid g-4 g-lg-0">
        <div class="col-12 col-lg-3 landing-footer-brand">
          <div class="d-flex align-items-start gap-2 mb-2">
            <?php if ($logoUrl): ?>
              <img src="<?= htmlspecialchars(landing_asset_or_url($logoUrl)) ?>" alt="<?= htmlspecialchars($schoolName) ?> logo" class="landing-footer-logo">
            <?php endif; ?>
            <div>
              <div class="landing-footer-school-name"><?= htmlspecialchars($schoolName) ?></div>
              <?php if ($tagline): ?>
                <p class="landing-footer-tagline mb-0"><?= htmlspecialchars($tagline) ?></p>
              <?php endif; ?>
            </div>
          </div>
          <?php if ($address): ?>
            <div class="landing-footer-meta"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($address) ?></div>
          <?php endif; ?>
        </div>

        <div class="col-6 col-lg-3 landing-footer-col">
          <h3 class="landing-footer-heading">Quick Links</h3>
          <ul class="landing-footer-links list-unstyled mb-0">
            <li><a href="about.php">About Us</a></li>
            <li><a href="index.php#features">Features</a></li>
            <li><a href="index.php#access">Get Access</a></li>
            <li><a href="<?= htmlspecialchars($privacyUrl) ?>">Privacy Policy</a></li>
          </ul>
        </div>

        <div class="col-6 col-lg-3 landing-footer-col">
          <h3 class="landing-footer-heading">Contact Us</h3>
          <ul class="landing-footer-contact list-unstyled mb-0">
            <?php if ($email): ?>
              <li><i class="bi bi-envelope"></i> <a href="mailto:<?= htmlspecialchars($email) ?>"><?= htmlspecialchars($email) ?></a></li>
            <?php endif; ?>
            <?php if ($phone): ?>
              <li><i class="bi bi-telephone"></i> <span><?= htmlspecialchars($phone) ?></span></li>
            <?php endif; ?>
          </ul>
        </div>

        <div class="col-12 col-lg-3 landing-footer-col">
          <h3 class="landing-footer-heading">Follow Us</h3>
          <div class="landing-footer-social">
            <a href="<?= htmlspecialchars($facebook) ?>" class="landing-footer-social-btn" aria-label="Facebook" target="_blank" rel="noopener noreferrer"><i class="bi bi-facebook"></i></a>
            <a href="<?= htmlspecialchars($instagram) ?>" class="landing-footer-social-btn" aria-label="Instagram" target="_blank" rel="noopener noreferrer"><i class="bi bi-instagram"></i></a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="landing-footer-bar">
    <div class="container landing-container landing-footer-bar-inner">
      <div class="landing-footer-copy">© <?= htmlspecialchars($year) ?> <?= htmlspecialchars($schoolName) ?>. All rights reserved.</div>
      <div class="landing-footer-legal">
        <a href="<?= htmlspecialchars($termsUrl) ?>">Terms of Use</a>
      </div>
    </div>
    <div class="landing-footer-decor" aria-hidden="true"></div>
  </div>
</footer>
