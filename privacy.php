<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/landing_shell.php';

$pageTitle = 'Privacy Policy';
$schoolName = (string)($SCHOOL_NAME ?? 'School');
$logoUrl = (string)($SCHOOL_LOGO_URL ?? '');
$isEmbed = (($_GET['embed'] ?? '') === '1');
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

<?php if (!$isEmbed): ?>
<nav class="navbar navbar-expand-lg landing-nav sticky-top">
  <div class="container landing-container">
    <a class="navbar-brand d-flex align-items-center gap-2 fw-bold" href="<?= htmlspecialchars(app_url('index')) ?>">
      <?php if ($logoUrl): ?>
        <img src="<?= htmlspecialchars(landing_asset_or_url($logoUrl)) ?>" alt="<?= htmlspecialchars($schoolName) ?> logo" style="width:32px;height:32px;border-radius:10px;object-fit:cover;">
      <?php endif; ?>
      <span><?= htmlspecialchars($schoolName) ?></span>
    </a>
    <div class="ms-auto d-flex gap-2">
      <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(app_url('terms')) ?>">Terms and Conditions</a>
      <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(app_url('register')) ?>">Back to Register</a>
    </div>
  </div>
</nav>
<?php endif; ?>

<main class="py-5">
  <div class="container landing-container" style="max-width:860px;">
    <h1 class="h3 mb-2">Privacy Policy</h1>
    <p class="text-muted mb-4">
      How San Josef National High School handles information in the Graduate Tracking System.
    </p>

    <div class="app-card p-3 p-md-4 legal-doc">
      <p>
        San Josef National High School respects the privacy of its graduates, students, teachers,
        administrators, and other authorized users of the Graduate Tracking System.
      </p>
      <p>
        This Privacy Policy explains what information may be collected through the System, why it is used,
        how it is protected, and who may access it.
      </p>
      <p>
        The System is designed to centralize graduate information and improve the efficiency, accuracy,
        and security of graduate monitoring.
      </p>

      <h2 class="h6">1. Information We Collect</h2>
      <p>
        Depending on the user's role and the features used, the System may collect and maintain information
        related to graduate records, including:
      </p>
      <ul>
        <li>Name and basic personal information;</li>
        <li>Contact information;</li>
        <li>Academic information;</li>
        <li>Course, program, or academic year;</li>
        <li>Batch year;</li>
        <li>Employment status;</li>
        <li>Other post-graduation outcomes;</li>
        <li>Account and login information;</li>
        <li>Profile update information;</li>
        <li>Verification status; and</li>
        <li>Information necessary for maintaining and managing the graduate record.</li>
      </ul>
      <p>
        The System's purpose includes maintaining graduate information and tracking employment and other
        post-graduation outcomes.
      </p>

      <h2 class="h6">2. How We Use Your Information</h2>
      <p>Information collected through the System may be used to:</p>
      <ul>
        <li>Maintain accurate graduate records;</li>
        <li>Monitor graduate outcomes;</li>
        <li>Track employment status;</li>
        <li>Monitor graduates by batch, course, program, or academic year;</li>
        <li>Verify submitted information;</li>
        <li>Generate summary reports and statistical information;</li>
        <li>Support institutional evaluation;</li>
        <li>Improve graduate monitoring processes;</li>
        <li>Send account-related notifications; and</li>
        <li>Communicate important school announcements.</li>
      </ul>
      <p>
        The System is intended for graduate monitoring, documentation, reporting, and institutional evaluation
        rather than job placement or job matching.
      </p>

      <h2 class="h6">3. Employment Information</h2>
      <p>
        Employment information is provided for graduate tracking and institutional monitoring purposes.
      </p>
      <p>
        The System does not automatically obtain employment information from government employment databases,
        employers, or other external employment systems.
      </p>
      <p>
        Employment information depends on available school records, follow-ups, and information voluntarily
        provided or updated by graduates.
      </p>

      <h2 class="h6">4. Who Can Access Your Information?</h2>
      <p>
        Access to graduate information is limited according to user roles and System permissions.
      </p>
      <p>
        Authorized school administrators, instructors, and designated personnel may access graduate records
        when necessary for their responsibilities.
      </p>
      <p>
        Graduate users may access information associated with their own account.
      </p>
      <p>
        Graduate information should not be publicly disclosed without appropriate authorization. Personal
        information and responses should not be publicly disclosed without permission.
      </p>

      <h2 class="h6">5. Reports and Statistical Information</h2>
      <p>
        The System may process graduate information to create summary reports and statistical summaries.
      </p>
      <p>
        Where possible, reports intended for institutional monitoring should focus on aggregated information
        rather than unnecessarily exposing individual personal information.
      </p>
      <p>Reports are intended to support:</p>
      <ul>
        <li>Graduate monitoring;</li>
        <li>Documentation;</li>
        <li>Institutional evaluation; and</li>
        <li>School decision-making.</li>
      </ul>

      <h2 class="h6">6. Data Security</h2>
      <p>
        The school and System administrators will take reasonable measures to protect graduate records against
        unauthorized access, disclosure, alteration, or misuse.
      </p>
      <p>Security measures may include:</p>
      <ul>
        <li>Authenticated user accounts;</li>
        <li>Role-based access;</li>
        <li>Restricted access to graduate records;</li>
        <li>Secure storage of System information;</li>
        <li>Regular data backups; and</li>
        <li>Administrative controls for authorized users.</li>
      </ul>
      <p>
        Secure storage, authorized access, secure login authentication, and regular backups are part of the
        System's security and maintenance approach.
      </p>

      <h2 class="h6">7. Data Accuracy</h2>
      <p>
        The accuracy of information in the System depends partly on the information provided by school records
        and graduate updates.
      </p>
      <p>
        Users are encouraged to keep their information accurate and updated. When incorrect information is
        identified, authorized school personnel may review or correct records in accordance with their responsibilities.
      </p>
      <p>
        System accuracy depends on school records and graduate-provided updates.
      </p>

      <h2 class="h6">8. Data Confidentiality</h2>
      <p>
        Graduate records and institutional information will be treated as confidential.
      </p>
      <p>
        Information should only be accessed by individuals who have appropriate authorization and a legitimate
        reason to use it.
      </p>
      <p>
        Graduate and institutional information should be securely stored and accessed only by authorized persons.
      </p>

      <h2 class="h6">9. Data Retention</h2>
      <p>
        Graduate information may be retained by the school for as long as reasonably necessary to fulfill
        legitimate graduate tracking, documentation, reporting, and institutional monitoring purposes.
      </p>
      <p>
        When information is no longer required, its handling or disposal should follow the school's applicable
        records-management and data-protection procedures.
      </p>

      <h2 class="h6">10. Your Responsibilities</h2>
      <p>Users are expected to:</p>
      <ul>
        <li>Provide accurate information;</li>
        <li>Keep their account credentials secure;</li>
        <li>Update information when necessary;</li>
        <li>Avoid accessing information belonging to other users;</li>
        <li>Respect the confidentiality of graduate information; and</li>
        <li>Report suspected security or privacy incidents to the appropriate school personnel.</li>
      </ul>

      <h2 class="h6">11. Changes to This Privacy Policy</h2>
      <p>
        This Privacy Policy may be updated when necessary because of changes in the System, school procedures,
        or applicable privacy requirements.
      </p>
      <p>
        The latest version will be made available through the Graduate Tracking System.
      </p>

      <h2 class="h6">12. Contact Us</h2>
      <p>
        If you have questions, concerns, or requests regarding this Privacy Policy or your information, please contact:
      </p>
      <p class="mb-0">
        <strong>San Josef National High School</strong><br>
        Graduate Tracking System<br>
        San Josef, Cabanatuan City, Philippines
      </p>
    </div>

    <?php if ($isEmbed): ?>
      <div class="mt-3">
        <button class="btn btn-sm btn-dark" type="button" onclick="window.close();">Close</button>
      </div>
    <?php else: ?>
      <div class="mt-3 d-flex flex-wrap gap-2">
        <a class="btn btn-sm btn-dark" href="<?= htmlspecialchars(app_url('register')) ?>">Return to Register</a>
        <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(app_url('terms')) ?>">View Terms and Conditions</a>
      </div>
    <?php endif; ?>
  </div>
</main>

<?php if (!$isEmbed): ?>
  <?php require_once __DIR__ . '/includes/landing_footer.php'; ?>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
