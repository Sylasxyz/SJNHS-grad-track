<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db_config.php';
require_once __DIR__ . '/includes/csrf.php';

require_roles(['Instructor', 'Admin']);
$me = current_user();
$isAdmin = (($me['Role'] ?? '') === 'Admin');

$profileId = (int)($_GET['profile_id'] ?? 0);
if ($profileId <= 0) {
    http_response_code(400);
    echo 'Invalid profile.';
    exit;
}

$pageTitle = 'Edit Graduate Profile';
$errors = [];
$success = '';

// Fetch profile + username
$stmt = $pdo->prepare(
    "SELECT gp.*, ua.Username
     FROM Graduate_Profiles gp
     INNER JOIN User_Accounts ua ON ua.User_ID = gp.User_ID
     WHERE gp.Profile_ID = ?
     LIMIT 1"
);
$stmt->execute([$profileId]);
$profile = $stmt->fetch();
if (!$profile) {
    http_response_code(404);
    echo 'Profile not found.';
    exit;
}

// Delete (Admin-only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    csrf_validate();
    if (!$isAdmin) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
    $del = $pdo->prepare('DELETE FROM Graduate_Profiles WHERE Profile_ID = ?');
    $del->execute([$profileId]);
    app_redirect('graduates');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    csrf_validate();

    $fullName = trim((string)($_POST['full_name'] ?? ''));
    $course = trim((string)($_POST['course'] ?? ''));
    $batchYear = (int)($_POST['batch_year'] ?? 0);
    $employmentStatus = (string)($_POST['employment_status'] ?? '');
    $companySchool = trim((string)($_POST['company_school_name'] ?? ''));
    $contactInfo = trim((string)($_POST['contact_info'] ?? ''));
    $markVerified = isset($_POST['mark_verified']) ? 1 : 0;

    $allowedStatuses = ['Employed', 'Unemployed', 'Self-Employed', 'Further Studies'];

    if ($fullName === '' || strlen($fullName) < 3) {
        $errors[] = 'Full name must be at least 3 characters.';
    }
    if ($batchYear < 1990 || $batchYear > ((int)date('Y') + 1)) {
        $errors[] = 'Batch year is invalid.';
    }
    if (!in_array($employmentStatus, $allowedStatuses, true)) {
        $errors[] = 'Employment status is invalid.';
    }

    if (!$errors) {
        if ($markVerified) {
            $upd = $pdo->prepare(
                "UPDATE Graduate_Profiles
                 SET Full_Name = ?, Course = ?, Batch_Year = ?, Employment_Status = ?, Company_School_Name = ?, Contact_Info = ?,
                     Is_Verified = 1, Verified_By = ?, Verified_At = CURRENT_TIMESTAMP
                 WHERE Profile_ID = ?"
            );
            $upd->execute([
                $fullName,
                $course ?: null,
                $batchYear,
                $employmentStatus,
                $companySchool ?: null,
                $contactInfo ?: null,
                (int)$me['User_ID'],
                $profileId
            ]);
        } else {
            $upd = $pdo->prepare(
                "UPDATE Graduate_Profiles
                 SET Full_Name = ?, Course = ?, Batch_Year = ?, Employment_Status = ?, Company_School_Name = ?, Contact_Info = ?
                 WHERE Profile_ID = ?"
            );
            $upd->execute([
                $fullName,
                $course ?: null,
                $batchYear,
                $employmentStatus,
                $companySchool ?: null,
                $contactInfo ?: null,
                $profileId
            ]);
        }

        $success = 'Graduate profile updated.';

        $stmt->execute([$profileId]);
        $profile = $stmt->fetch();
    }
}

$values = [
    'full_name' => (string)($profile['Full_Name'] ?? ''),
    'course' => (string)($profile['Course'] ?? ''),
    'batch_year' => (string)($profile['Batch_Year'] ?? ''),
    'employment_status' => (string)($profile['Employment_Status'] ?? 'Unemployed'),
    'company_school_name' => (string)($profile['Company_School_Name'] ?? ''),
    'contact_info' => (string)($profile['Contact_Info'] ?? ''),
];

require_once __DIR__ . '/includes/header.php';
?>

<div class="row g-3">
  <div class="col-12 col-lg-3">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
  </div>

  <div class="col-12 col-lg-9">
    <div class="app-card p-3 mb-3">
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
          <h1 class="h4 mb-1">Edit Graduate Profile</h1>
          <div class="text-muted small">User: <strong><?= htmlspecialchars((string)$profile['Username']) ?></strong> · Profile ID: <?= (int)$profileId ?></div>
        </div>
        <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(app_url('graduates')) ?>">Back</a>
      </div>
    </div>

    <?php if ($success): ?>
      <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($errors): ?>
      <div class="alert alert-danger">
        <ul class="mb-0">
          <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <div class="app-card p-3 mb-3">
      <h2 class="h6 mb-3">Verification</h2>
      <div class="row g-2">
        <div class="col-12 col-md-4">
          <div class="text-muted small">Verified</div>
          <div class="fw-semibold">
            <?= ((int)($profile['Is_Verified'] ?? 0) === 1) ? 'Yes' : 'No' ?>
          </div>
        </div>
        <div class="col-12 col-md-4">
          <div class="text-muted small">Verified At</div>
          <div class="fw-semibold"><?= htmlspecialchars((string)($profile['Verified_At'] ?? '')) ?></div>
        </div>
        <div class="col-12 col-md-4">
          <div class="text-muted small">Last Updated</div>
          <div class="fw-semibold"><?= htmlspecialchars((string)($profile['Last_Updated'] ?? '')) ?></div>
        </div>
      </div>
    </div>

    <div class="app-card p-3">
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="action" value="save">

        <div class="mb-3">
          <label class="form-label" for="full_name">Full Name</label>
          <input class="form-control" id="full_name" name="full_name" required value="<?= htmlspecialchars($values['full_name']) ?>">
        </div>

        <div class="mb-3">
          <label class="form-label" for="course">Course / Strand</label>
          <input class="form-control" id="course" name="course" value="<?= htmlspecialchars($values['course']) ?>">
        </div>

        <div class="mb-3">
          <label class="form-label" for="batch_year">Batch Year</label>
          <input class="form-control" id="batch_year" name="batch_year" type="number" min="1990" max="<?= (int)date('Y') + 1 ?>" required value="<?= htmlspecialchars($values['batch_year']) ?>">
        </div>

        <div class="mb-3">
          <label class="form-label" for="employment_status">Employment Status</label>
          <select class="form-select" id="employment_status" name="employment_status" required>
            <?php
              $options = ['Employed', 'Unemployed', 'Self-Employed', 'Further Studies'];
              foreach ($options as $opt) {
                  $sel = ($values['employment_status'] === $opt) ? 'selected' : '';
                  echo '<option value="' . htmlspecialchars($opt) . '" ' . $sel . '>' . htmlspecialchars($opt) . '</option>';
              }
            ?>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label" for="company_school_name">Company / School Name</label>
          <input class="form-control" id="company_school_name" name="company_school_name" value="<?= htmlspecialchars($values['company_school_name']) ?>">
        </div>

        <div class="mb-3">
          <label class="form-label" for="contact_info">Contact Info</label>
          <input class="form-control" id="contact_info" name="contact_info" value="<?= htmlspecialchars($values['contact_info']) ?>">
        </div>

        <div class="form-check mb-3">
          <?php if ((int)($profile['Is_Verified'] ?? 0) !== 1): ?>
          <input class="form-check-input" type="checkbox" value="1" id="mark_verified" name="mark_verified">
          <label class="form-check-label" for="mark_verified">
            Mark as verified (sets Verified By/At)
          </label>
          <?php else: ?>
          <span class="badge text-bg-success"><i class="bi bi-check-circle me-1"></i>Profile verified</span>
          <?php endif; ?>
        </div>

        <div class="d-flex flex-wrap gap-2">
          <button class="btn btn-primary" type="submit">Save Changes</button>
        </div>
      </form>
    </div>

    <?php if ($isAdmin): ?>
      <form method="post" class="mt-3" onsubmit="return confirm('Delete this graduate profile? This cannot be undone.');">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="action" value="delete">
        <button class="btn btn-outline-danger btn-sm" type="submit">Delete Profile</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

