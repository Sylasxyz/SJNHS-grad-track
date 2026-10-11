<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/app_config.php';
require_once __DIR__ . '/includes/db_config.php';
require_once __DIR__ . '/includes/dashboard_analytics.php';

require_login();
$user = current_user();
$role = (string)($user['Role'] ?? '');
$userId = (int)($user['User_ID'] ?? 0);
$firstName = trim((string)($user['First_Name'] ?? ''));
$displayName = trim($firstName . ' ' . (string)($user['Last_Name'] ?? ''));
if ($displayName === '') {
    $displayName = (string)($user['Username'] ?? 'User');
}
if ($firstName === '') {
    $firstName = $displayName;
}

$pageTitle = 'Dashboard';

function compute_student_profile_completion(?array $profile): int
{
    return score_graduate_profile_completion($profile);
}

function format_dashboard_date(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return '—';
    }
    $ts = strtotime($value);
    if ($ts === false) {
        return htmlspecialchars($value);
    }
    return date('M j, Y', $ts);
}

$analytics = null;
if ($role === 'Admin' || $role === 'Instructor' || $role === 'Principal') {
    try {
        $analytics = compute_dashboard_analytics($pdo, 0, 0);
    } catch (Throwable $e) {
        $analytics = null;
    }
}

$statusOrder = $analytics['status_order'] ?? ['Employed', 'Unemployed', 'Self-Employed', 'Further Studies'];
$countsByStatus = $analytics['counts_by_status'] ?? array_fill_keys($statusOrder, 0);

$instUnverified = [];
$instSchemaErrors = [];
$adminPendingUsers = [];
$directoryRows = [];
$securityAlerts = [];
$securityAlertCount = 0;

$studentProfile = null;
$profileCompletion = 0;
$studentAnnouncements = [];
$studentActivity = [];

if ($role === 'Instructor' || $role === 'Admin') {
    try {
        $pdo->query("SELECT Course, Is_Verified FROM Graduate_Profiles LIMIT 1");
    } catch (PDOException $e) {
        $instSchemaErrors[] = msg_staff_schema_pending();
    }

    if (!$instSchemaErrors) {
        $instUnverified = $pdo->query(
            "SELECT gp.Profile_ID, gp.Full_Name, gp.Course, gp.Batch_Year, gp.Employment_Status, gp.Last_Updated, ua.Username
             FROM Graduate_Profiles gp
             INNER JOIN User_Accounts ua ON ua.User_ID = gp.User_ID
             WHERE gp.Is_Verified = 0 AND ua.Status = 'Active'
             ORDER BY gp.Last_Updated DESC
             LIMIT 8"
        )->fetchAll();
    }
}

if ($role === 'Student') {
    $accountCreatedAt = '';
    try {
        $createdStmt = $pdo->prepare('SELECT Created_At FROM User_Accounts WHERE User_ID = ? LIMIT 1');
        $createdStmt->execute([$userId]);
        $accountCreatedAt = (string)($createdStmt->fetchColumn() ?: '');
    } catch (Throwable $e) {
        $accountCreatedAt = '';
    }

    try {
        $profileStmt = $pdo->prepare(
            'SELECT Profile_ID, Full_Name, Course, Batch_Year, Employment_Status, Company_School_Name,
                    Further_Studies_Program, Contact_Info, Profile_Picture, Is_Verified, Verified_At, Last_Updated
             FROM Graduate_Profiles WHERE User_ID = ? LIMIT 1'
        );
        $profileStmt->execute([$userId]);
        $studentProfile = $profileStmt->fetch() ?: null;
    } catch (Throwable $e) {
        $studentProfile = null;
    }
    $profileCompletion = compute_student_profile_completion($studentProfile);

    try {
        $studentAnnouncements = $pdo->query(
            "SELECT a.Announcement_ID, a.Title, a.Date_Posted
             FROM Announcements a
             WHERE COALESCE(a.Audience, 'Everyone') = 'Everyone'
               AND COALESCE(a.Is_Archived, 0) = 0
             ORDER BY a.Date_Posted DESC
             LIMIT 5"
        )->fetchAll();
    } catch (Throwable $e) {
        try {
            $studentAnnouncements = $pdo->query(
                "SELECT a.Announcement_ID, a.Title, a.Date_Posted
                 FROM Announcements a
                 WHERE COALESCE(a.Audience, 'Everyone') = 'Everyone'
                 ORDER BY a.Date_Posted DESC
                 LIMIT 5"
            )->fetchAll();
        } catch (Throwable $e2) {
            try {
                $studentAnnouncements = $pdo->query(
                    "SELECT Announcement_ID, Title, Date_Posted
                     FROM Announcements
                     ORDER BY Date_Posted DESC
                     LIMIT 5"
                )->fetchAll();
            } catch (Throwable $e3) {
                $studentAnnouncements = [];
            }
        }
    }

    // Build accurate activity from real account/profile events.
    if ($studentProfile) {
        $studentActivity[] = [
            'icon' => 'bi-pencil-square',
            'tone' => 'green',
            'text' => 'You updated your graduate profile.',
            'date' => (string)($studentProfile['Last_Updated'] ?? ''),
        ];
        if ((int)($studentProfile['Is_Verified'] ?? 0) === 1 && !empty($studentProfile['Verified_At'])) {
            $studentActivity[] = [
                'icon' => 'bi-shield-check',
                'tone' => 'blue',
                'text' => 'Your profile was verified by the school.',
                'date' => (string)$studentProfile['Verified_At'],
            ];
        } else {
            $studentActivity[] = [
                'icon' => 'bi-hourglass-split',
                'tone' => 'orange',
                'text' => 'Your profile is pending school verification.',
                'date' => (string)($studentProfile['Last_Updated'] ?? ''),
            ];
        }
    } else {
        $studentActivity[] = [
            'icon' => 'bi-person-plus',
            'tone' => 'orange',
            'text' => 'Graduate profile not submitted yet.',
            'date' => '',
        ];
    }
    $studentActivity[] = [
        'icon' => 'bi-person-check',
        'tone' => 'blue',
        'text' => 'Your student account was activated.',
        'date' => $accountCreatedAt,
    ];
    // Sort by date desc when available
    usort($studentActivity, static function (array $a, array $b): int {
        return strcmp((string)($b['date'] ?? ''), (string)($a['date'] ?? ''));
    });
    $studentActivity = array_slice($studentActivity, 0, 4);
}

if ($role === 'Admin') {
    try {
        $adminPendingUsers = $pdo->query(
            "SELECT User_ID, CONCAT(First_Name, ' ', Last_Name) AS Full_Name, Role, Created_At
             FROM User_Accounts
             WHERE Status = 'Pending'
             ORDER BY Created_At ASC
             LIMIT 6"
        )->fetchAll();
    } catch (Throwable $e) {
        $adminPendingUsers = [];
    }

    $securityAlerts = fetch_login_security_alerts($pdo, 10);
    $securityAlertCount = count($securityAlerts);
}

if ($role === 'Instructor' || $role === 'Admin' || $role === 'Principal') {
    try {
        $directoryRows = $pdo->query(
            "SELECT gp.Full_Name, gp.Course, gp.Batch_Year, gp.Employment_Status, gp.Is_Verified
             FROM Graduate_Profiles gp
             INNER JOIN User_Accounts ua ON ua.User_ID = gp.User_ID
             WHERE ua.Status = 'Active'
             ORDER BY gp.Last_Updated DESC
             LIMIT 8"
        )->fetchAll();
    } catch (Throwable $e) {
        $directoryRows = [];
    }
}

$profileName = $studentProfile
    ? (string)$studentProfile['Full_Name']
    : $displayName;
$profileCourse = $studentProfile ? trim((string)($studentProfile['Course'] ?? '')) : '';
$profileBatch = $studentProfile && !empty($studentProfile['Batch_Year']) ? (string)$studentProfile['Batch_Year'] : '—';
$profilePhone = $studentProfile ? trim((string)($studentProfile['Contact_Info'] ?? '')) : '';
$profilePhoto = $studentProfile ? trim((string)($studentProfile['Profile_Picture'] ?? '')) : '';
$isVerified = $studentProfile && (int)($studentProfile['Is_Verified'] ?? 0) === 1;
$profileEmail = (string)($user['Email'] ?? '');
$profileUpdated = $studentProfile ? (string)($studentProfile['Last_Updated'] ?? '') : '';

require_once __DIR__ . '/includes/header.php';
?>

<div class="dashboard-shell">
  <?php require_once __DIR__ . '/includes/dashboard_rail.php'; ?>

  <div class="dashboard-content">
  <?php if ($role === 'Student'): ?>

    <section class="student-dash">
      <div class="student-dash-hero">
        <div>
          <h1 class="student-dash-greeting">Welcome back, <?= htmlspecialchars($firstName) ?>!</h1>
          <p class="student-dash-sub">Keep your graduate information up to date.</p>
        </div>
        <div class="student-dash-hero-art" aria-hidden="true">
          <div class="student-dash-hero-blob">
            <i class="bi bi-mortarboard-fill"></i>
            <i class="bi bi-laptop"></i>
            <i class="bi bi-file-earmark-check"></i>
          </div>
        </div>
      </div>

      <div class="student-dash-top">
        <div class="app-card student-status-card">
          <h2 class="student-card-title">My Graduate Status</h2>
          <div class="student-status-grid">
            <div class="student-status-item">
              <div class="student-status-icon is-purple"><i class="bi bi-person-fill"></i></div>
              <div class="student-status-label">Profile Completion</div>
              <div class="student-status-value is-purple"><?= (int)$profileCompletion ?>%</div>
              <div class="student-progress">
                <span style="width: <?= max(0, min(100, (int)$profileCompletion)) ?>%;"></span>
              </div>
              <a class="student-status-link" href="<?= htmlspecialchars(app_url('profile')) ?>">Complete your profile →</a>
            </div>

            <div class="student-status-item">
              <div class="student-status-icon is-orange"><i class="bi bi-shield-fill-check"></i></div>
              <div class="student-status-label">Verification Status</div>
              <?php if (!$studentProfile): ?>
                <div class="student-status-badge is-muted">No profile yet</div>
                <div class="student-status-note">Submit your graduate profile to start verification.</div>
              <?php elseif ($isVerified): ?>
                <div class="student-status-badge is-success">Verified</div>
                <div class="student-status-note">Your profile has been reviewed and verified.</div>
              <?php else: ?>
                <div class="student-status-badge is-pending">Pending verification</div>
                <div class="student-status-note">Your profile is being reviewed by the school.</div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <aside class="app-card student-profile-card">
          <div class="student-profile-head">
            <h2 class="student-card-title mb-0">My Profile</h2>
            <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars(app_url('profile')) ?>" aria-label="Edit profile"><i class="bi bi-pencil"></i></a>
          </div>
          <div class="student-profile-identity">
            <div class="student-profile-avatar">
              <?php if ($profilePhoto !== ''): ?>
                <img src="<?= htmlspecialchars($profilePhoto) ?>" alt="Profile photo">
              <?php else: ?>
                <i class="bi bi-person-fill"></i>
              <?php endif; ?>
            </div>
            <div>
              <div class="student-profile-name"><?= htmlspecialchars($profileName) ?></div>
              <div class="student-profile-course"><?= htmlspecialchars($profileCourse !== '' ? $profileCourse : 'Course not set') ?></div>
            </div>
          </div>
          <ul class="student-profile-meta">
            <li>
              <i class="bi bi-calendar3"></i>
              <div>
                <span>Batch Year</span>
                <strong><?= htmlspecialchars($profileBatch) ?></strong>
              </div>
            </li>
            <li>
              <i class="bi bi-envelope"></i>
              <div>
                <span>Email</span>
                <strong><?= htmlspecialchars($profileEmail !== '' ? $profileEmail : '—') ?></strong>
              </div>
            </li>
            <li>
              <i class="bi bi-telephone"></i>
              <div>
                <span>Phone</span>
                <strong><?= htmlspecialchars($profilePhone !== '' ? $profilePhone : '—') ?></strong>
              </div>
            </li>
            <li>
              <i class="bi bi-clock-history"></i>
              <div>
                <span>Last Updated</span>
                <strong><?= format_dashboard_date($profileUpdated !== '' ? $profileUpdated : null) ?></strong>
              </div>
            </li>
          </ul>
        </aside>
      </div>

      <div class="student-info-banner">
        <i class="bi bi-info-circle-fill"></i>
        <p>Please keep your information updated so the school can maintain accurate records and provide better support for graduates.</p>
      </div>

      <div class="student-dash-bottom">
        <div class="app-card student-list-card">
          <div class="student-list-head">
            <h2 class="student-card-title mb-0">Announcements</h2>
            <a href="<?= htmlspecialchars(app_url('announcements')) ?>">View all</a>
          </div>
          <?php if (!$studentAnnouncements): ?>
            <div class="text-muted small py-2">No announcements yet.</div>
          <?php else: ?>
            <ul class="student-list">
              <?php foreach ($studentAnnouncements as $ann): ?>
                <li>
                  <span class="student-list-icon is-blue"><i class="bi bi-megaphone-fill"></i></span>
                  <div class="student-list-body">
                    <strong><?= htmlspecialchars((string)$ann['Title']) ?></strong>
                  </div>
                  <time><?= format_dashboard_date((string)($ann['Date_Posted'] ?? '')) ?></time>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
          <a class="student-list-footer" href="<?= htmlspecialchars(app_url('announcements')) ?>">See all announcements →</a>
        </div>

        <div class="app-card student-list-card">
          <div class="student-list-head">
            <h2 class="student-card-title mb-0">Messages</h2>
            <a href="<?= htmlspecialchars(app_url('messages')) ?>">Open inbox</a>
          </div>
          <div class="text-muted small py-2">Message instructors or admins inside the system for support and follow-ups.</div>
          <a class="student-list-footer" href="<?= htmlspecialchars(app_url('messages', ['compose' => 1])) ?>">Send a message →</a>
        </div>

        <div class="app-card student-list-card">
          <div class="student-list-head">
            <h2 class="student-card-title mb-0">Recent Activity</h2>
          </div>
          <ul class="student-list">
            <?php foreach ($studentActivity as $act): ?>
              <li>
                <span class="student-list-icon is-<?= htmlspecialchars((string)$act['tone']) ?>"><i class="bi <?= htmlspecialchars((string)$act['icon']) ?>"></i></span>
                <div class="student-list-body">
                  <strong><?= htmlspecialchars((string)$act['text']) ?></strong>
                </div>
                <time><?= format_dashboard_date(($act['date'] ?? '') !== '' ? (string)$act['date'] : null) ?></time>
              </li>
            <?php endforeach; ?>
          </ul>
          <a class="student-list-footer" href="<?= htmlspecialchars(app_url('profile')) ?>">View profile details →</a>
        </div>
      </div>
    </section>

  <?php else: ?>

  <?php if ($role === 'Admin' && $securityAlertCount > 0): ?>
  <div>
    <div class="alert alert-danger d-flex align-items-center justify-content-between gap-3 mb-0" role="alert">
      <div class="d-flex align-items-center gap-2">
        <i class="bi bi-shield-exclamation fs-4 flex-shrink-0"></i>
        <div>
          <strong>Security Alert:</strong>
          <?= $securityAlertCount ?> account(s) had <strong>5+ failed login attempts</strong> in the last 30 minutes.
        </div>
      </div>
      <a href="<?= htmlspecialchars(app_url('admin/users#security-alerts')) ?>" class="btn btn-danger btn-sm flex-shrink-0">
        <i class="bi bi-eye me-1"></i>View Alerts
      </a>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($instSchemaErrors): ?>
    <div>
      <?php foreach ($instSchemaErrors as $schemaErr): ?>
        <div class="alert alert-warning"><?= htmlspecialchars($schemaErr) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="dashboard-main-grid">
    <div class="app-card p-3 dashboard-center-top">
      <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
        <h2 class="h5 mb-0">Employment Overview</h2>
        <?php if ($analytics): ?>
          <span class="badge text-bg-primary"><?= (float)$analytics['employment_rate'] ?>% employed</span>
        <?php else: ?>
          <span class="badge text-bg-light border">Overview</span>
        <?php endif; ?>
      </div>
      <div class="dashboard-chart-wrap">
        <canvas id="instProgressChart"></canvas>
      </div>
    </div>

    <div class="app-card p-3 dashboard-right-top">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <h2 class="h5 mb-0">
          <?php if ($role === 'Instructor'): ?>
            Awaiting Verification
          <?php elseif ($role === 'Principal'): ?>
            Overview
          <?php else: ?>
            Pending Users
          <?php endif; ?>
        </h2>
        <?php if ($role === 'Instructor'): ?>
          <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars(app_url('graduates')) ?>"><i class="bi bi-search"></i></a>
        <?php elseif ($role === 'Principal'): ?>
          <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars(app_url('graduates')) ?>"><i class="bi bi-search"></i></a>
        <?php else: ?>
          <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars(app_url('admin/users')) ?>"><i class="bi bi-plus"></i></a>
        <?php endif; ?>
      </div>
      <?php if ($role === 'Instructor'): ?>
        <?php if (!$instUnverified): ?>
          <div class="text-muted small">All profiles are verified.</div>
        <?php else: ?>
          <div class="list-group dashboard-message-list">
            <?php foreach (array_slice($instUnverified, 0, 5) as $uv): ?>
              <a class="list-group-item list-group-item-action" href="<?= htmlspecialchars(app_url('graduate_edit', ['profile_id' => (int)$uv['Profile_ID']])) ?>">
                <div class="fw-semibold"><?= htmlspecialchars((string)$uv['Full_Name']) ?></div>
                <div class="text-muted small"><?= htmlspecialchars((string)($uv['Course'] ?? '')) ?> · Batch <?= (int)$uv['Batch_Year'] ?></div>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      <?php elseif ($role === 'Principal'): ?>
        <div class="text-muted small mb-2">View-only access. You can review records and analytics.</div>
        <?php if ($analytics): ?>
          <div class="small">
            <div class="d-flex justify-content-between py-1 border-bottom"><span>Profiles</span><strong><?= (int)$analytics['total_profiles'] ?></strong></div>
            <div class="d-flex justify-content-between py-1 border-bottom"><span>Employment rate</span><strong><?= (float)$analytics['employment_rate'] ?>%</strong></div>
            <div class="d-flex justify-content-between py-1"><span>Verified</span><strong><?= (float)$analytics['verification_rate'] ?>%</strong></div>
          </div>
        <?php else: ?>
          <div class="text-muted small">No analytics available yet.</div>
        <?php endif; ?>
      <?php else: ?>
        <?php if (!$adminPendingUsers): ?>
          <div class="text-muted small">No pending users.</div>
        <?php else: ?>
          <div class="list-group dashboard-message-list">
            <?php foreach ($adminPendingUsers as $pu): ?>
              <a class="list-group-item list-group-item-action" href="<?= htmlspecialchars(app_url('admin/users')) ?>">
                <div class="fw-semibold"><?= htmlspecialchars((string)$pu['Full_Name']) ?></div>
                <div class="text-muted small"><?= htmlspecialchars((string)$pu['Role']) ?></div>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <div class="app-card p-3 dashboard-bottom-left">
      <h3 class="h6 mb-2">Features</h3>
      <div class="row g-2">
        <?php if ($role === 'Principal'): ?>
          <div class="col-12"><a class="dashboard-action-card" href="<?= htmlspecialchars(app_url('graduates')) ?>"><i class="bi bi-search"></i><span>Graduate Records</span></a></div>
          <div class="col-12"><a class="dashboard-action-card" href="<?= htmlspecialchars(app_url('announcements')) ?>"><i class="bi bi-megaphone"></i><span>Announcements</span></a></div>
          <div class="col-12"><a class="dashboard-action-card" href="<?= htmlspecialchars(app_url('analytics')) ?>"><i class="bi bi-graph-up-arrow"></i><span>Analytics</span></a></div>
          <div class="col-12"><a class="dashboard-action-card" href="<?= htmlspecialchars(app_url('admin/reports')) ?>"><i class="bi bi-file-earmark-bar-graph"></i><span>View Reports</span></a></div>
        <?php elseif ($role === 'Instructor'): ?>
          <div class="col-12"><a class="dashboard-action-card" href="<?= htmlspecialchars(app_url('messages')) ?>"><i class="bi bi-chat-dots"></i><span>Messages</span></a></div>
          <div class="col-12"><a class="dashboard-action-card" href="<?= htmlspecialchars(app_url('graduates')) ?>"><i class="bi bi-search"></i><span>Search / Filter</span></a></div>
          <div class="col-12"><a class="dashboard-action-card" href="<?= htmlspecialchars(app_url('announcement_create')) ?>"><i class="bi bi-pencil-square"></i><span>Post Announcement</span></a></div>
          <div class="col-12"><a class="dashboard-action-card" href="<?= htmlspecialchars(app_url('instructor/users')) ?>"><i class="bi bi-person-gear"></i><span>Manage Students</span></a></div>
          <div class="col-12"><a class="dashboard-action-card" href="<?= htmlspecialchars(app_url('analytics')) ?>"><i class="bi bi-graph-up-arrow"></i><span>Analytics</span></a></div>
          <div class="col-12"><a class="dashboard-action-card" href="<?= htmlspecialchars(app_url('admin/reports')) ?>"><i class="bi bi-file-earmark-bar-graph"></i><span>Generate Reports</span></a></div>
        <?php else: ?>
          <div class="col-12"><a class="dashboard-action-card" href="<?= htmlspecialchars(app_url('messages')) ?>"><i class="bi bi-chat-dots"></i><span>Messages</span></a></div>
          <div class="col-12"><a class="dashboard-action-card" href="<?= htmlspecialchars(app_url('admin/users')) ?>"><i class="bi bi-people"></i><span>Verify / Manage Users</span></a></div>
          <div class="col-12"><a class="dashboard-action-card" href="<?= htmlspecialchars(app_url('analytics')) ?>"><i class="bi bi-graph-up-arrow"></i><span>Analytics & Reports</span></a></div>
          <div class="col-12"><a class="dashboard-action-card" href="<?= htmlspecialchars(app_url('admin/reports')) ?>"><i class="bi bi-file-earmark-bar-graph"></i><span>Generate Reports</span></a></div>
          <div class="col-12"><a class="dashboard-action-card" href="<?= htmlspecialchars(app_url('graduates')) ?>"><i class="bi bi-search"></i><span>Graduate Records</span></a></div>
          <div class="col-12">
            <a class="dashboard-action-card" href="<?= htmlspecialchars(app_url('admin/users#security-alerts')) ?>" style="position:relative;">
              <i class="bi bi-shield-exclamation"></i>
              <span>Security Alerts</span>
              <?php if ($securityAlertCount > 0): ?>
                <span class="badge text-bg-danger ms-auto" style="font-size:0.7rem;"><?= $securityAlertCount ?></span>
              <?php endif; ?>
            </a>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="app-card p-3 dashboard-bottom-center">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <h3 class="h6 mb-0">Recent Graduate Records</h3>
        <a class="small" href="<?= htmlspecialchars(app_url('graduates')) ?>">View all</a>
      </div>
      <?php if (!$directoryRows): ?>
        <div class="text-muted small">No records yet.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead>
              <tr><th>Name</th><th>Course</th><th>Batch</th><th>Status</th><th>Verified</th></tr>
            </thead>
            <tbody>
              <?php foreach ($directoryRows as $dr): ?>
                <tr>
                  <td><?= htmlspecialchars((string)$dr['Full_Name']) ?></td>
                  <td><?= htmlspecialchars((string)($dr['Course'] ?? '')) ?></td>
                  <td><?= (int)$dr['Batch_Year'] ?></td>
                  <td><?= htmlspecialchars((string)$dr['Employment_Status']) ?></td>
                  <td><?= !empty($dr['Is_Verified']) ? '<span class="badge text-bg-success">Yes</span>' : '<span class="badge text-bg-warning">No</span>' ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php if ($role !== 'Student'): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
  const labels = <?= json_encode(array_values($statusOrder)) ?>;
  const data = <?= json_encode(array_values($countsByStatus)) ?>;
  const barEl = document.getElementById('instProgressChart');
  if (barEl) {
    new Chart(barEl, {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: 'Graduates',
          data,
          backgroundColor: ['#5B9A8B', '#D4A574', '#6B9BB8', '#A3B07A'],
          borderRadius: 8,
          maxBarThickness: 48
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: { ticks: { maxRotation: 45, minRotation: 0, autoSkip: true, color: '#64748b' }, grid: { display: false } },
          y: { beginAtZero: true, ticks: { precision: 0, color: '#64748b' }, grid: { color: 'rgba(148, 163, 184, 0.25)' } }
        }
      }
    });
  }
</script>
<?php endif; ?>
