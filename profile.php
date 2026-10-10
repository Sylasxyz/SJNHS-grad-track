<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db_config.php';
require_once __DIR__ . '/includes/csrf.php';

require_roles(['Student']);
$user = current_user();
$userId = (int)$user['User_ID'];

$pageTitle = 'Graduate Profile';
$errors = [];
$success = '';

// Fetch existing profile (if any)
$stmt = $pdo->prepare('SELECT * FROM Graduate_Profiles WHERE User_ID = ? LIMIT 1');
$stmt->execute([$userId]);
$profile = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();

    $postAction = (string)($_POST['action'] ?? 'save');

    // Remove profile picture only (does not require re-saving other fields)
    if ($postAction === 'remove_picture') {
        if ($profile && !empty($profile['Profile_Picture'])) {
            $oldPath = (string)$profile['Profile_Picture'];
            $oldAbs = __DIR__ . '/' . ltrim(str_replace('\\', '/', $oldPath), '/');
            $profilesDir = realpath(__DIR__ . '/uploads/profiles');
            $oldReal = is_file($oldAbs) ? realpath($oldAbs) : false;
            if ($profilesDir && $oldReal && str_starts_with($oldReal, $profilesDir)) {
                @unlink($oldReal);
            }
            $upd = $pdo->prepare('UPDATE Graduate_Profiles SET Profile_Picture = NULL WHERE User_ID = ?');
            $upd->execute([$userId]);
            $stmt->execute([$userId]);
            $profile = $stmt->fetch();
            $success = 'Profile picture removed.';
        } else {
            $errors[] = 'No profile picture to remove.';
        }
    } else {
    $fullName = trim((string)($_POST['full_name'] ?? ''));
    $course = trim((string)($_POST['course'] ?? ''));
    $batchYear = (int)($_POST['batch_year'] ?? 0);
    $employmentStatus = (string)($_POST['employment_status'] ?? '');
    $companySchool = trim((string)($_POST['company_school_name'] ?? ''));
    $contactInfo = trim((string)($_POST['contact_info'] ?? ''));
    $profilePicturePath = (string)($profile['Profile_Picture'] ?? '');
    $removePicture = isset($_POST['remove_profile_picture']);
    $oldPicturePath = $profilePicturePath;

    $allowedStatuses = ['Employed', 'Unemployed', 'Self-Employed', 'Further Studies'];

    if ($fullName === '' || strlen($fullName) < 3) {
        $errors[] = 'Full name must be at least 3 characters.';
    }
    // ── SECURITY FIX A06: Lock full name if already verified ──
    if ($profile && (int)($profile['Is_Verified'] ?? 0) === 1) {
        if ($fullName !== (string)($profile['Full_Name'] ?? '')) {
            $errors[] = 'Your profile is verified. You cannot change your Full Name. Contact your Instructor.';
        }
    }
    if ($batchYear < 1990 || $batchYear > ((int)date('Y') + 1)) {
        $errors[] = 'Batch year is invalid.';
    }
    $allowedStrands = ['HUMMS', 'STEMS', 'ABM'];
    if ($course === '' || !in_array($course, $allowedStrands, true)) {
        $errors[] = 'Please select a strand (HUMMS, STEMS, or ABM).';
    }
    if (!in_array($employmentStatus, $allowedStatuses, true)) {
        $errors[] = 'Employment status is invalid.';
    }
    // ── SECURITY FIX A03: Philippine phone number format only ──
    if ($contactInfo !== '') {
        // Accept formats: 09XXXXXXXXX or +639XXXXXXXXX
        if (!preg_match('/^(09\d{9}|\+639\d{9})$/', $contactInfo)) {
            $errors[] = 'Contact info must be a valid Philippine phone number. Use format: 09XXXXXXXXX or +639XXXXXXXXX.';
        }
    }
    // ── SECURITY FIX A03: Validate contact info length and format ──
    if ($contactInfo !== '' && strlen($contactInfo) > 20) {
        $errors[] = 'Contact info is too long. Maximum 20 characters only.';
    }
    if ($contactInfo !== '' && !preg_match('/^[0-9\+\-\s\(\)]{7,20}$/', $contactInfo)) {
        $errors[] = 'Contact info must be a valid phone number (digits, +, -, spaces only).';
    }
    // ── SECURITY FIX A03: Validate contact info format ──
    if ($contactInfo !== '' && strlen($contactInfo) > 20) {
        $errors[] = 'Contact info is too long. Maximum 20 characters only.';
    }
    if ($contactInfo !== '' && !preg_match('/^[0-9\+\-\s\(\)]{7,20}$/', $contactInfo)) {
        $errors[] = 'Contact info must be a valid phone number (digits, +, -, spaces only).';
    }

    if ($removePicture) {
        $profilePicturePath = '';
    }

    // Optional profile picture upload (replace existing)
    $uploadedNewPicture = false;
    if (isset($_FILES['profile_picture']) && is_array($_FILES['profile_picture']) && (int)($_FILES['profile_picture']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $uploadErr = (int)($_FILES['profile_picture']['error'] ?? UPLOAD_ERR_OK);
        if ($uploadErr !== UPLOAD_ERR_OK) {
            $errors[] = 'Profile picture upload failed.';
        } else {
            $tmp = (string)($_FILES['profile_picture']['tmp_name'] ?? '');
            $size = (int)($_FILES['profile_picture']['size'] ?? 0);
            if ($size > 2 * 1024 * 1024) {
                $errors[] = 'Profile picture must be 2MB or less.';
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = (string)$finfo->file($tmp);
                $extMap = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                ];
                if (!isset($extMap[$mime])) {
                    $errors[] = 'Profile picture must be JPG, PNG, or WEBP.';
                } else {
                    $uploadDir = __DIR__ . '/uploads/profiles';
                    if (!is_dir($uploadDir)) {
                        @mkdir($uploadDir, 0775, true);
                    }
                    $filename = 'u' . $userId . '_' . time() . '.' . $extMap[$mime];
                    $destAbs = $uploadDir . '/' . $filename;
                    if (!move_uploaded_file($tmp, $destAbs)) {
                        $errors[] = 'Unable to save profile picture.';
                    } else {
                        $profilePicturePath = 'uploads/profiles/' . $filename;
                        $uploadedNewPicture = true;
                        $removePicture = false;
                    }
                }
            }
        }
    }

    if (!$errors) {
        if ($profile) {
            $upd = $pdo->prepare(
                'UPDATE Graduate_Profiles
                 SET Full_Name = ?, Course = ?, Batch_Year = ?, Employment_Status = ?, Company_School_Name = ?, Contact_Info = ?, Profile_Picture = ?
                 WHERE User_ID = ?'
            );
            $upd->execute([$fullName, $course ?: null, $batchYear, $employmentStatus, $companySchool ?: null, $contactInfo ?: null, $profilePicturePath !== '' ? $profilePicturePath : null, $userId]);
        } else {
            $ins = $pdo->prepare(
                'INSERT INTO Graduate_Profiles (User_ID, Full_Name, Course, Batch_Year, Employment_Status, Company_School_Name, Contact_Info, Profile_Picture)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $ins->execute([$userId, $fullName, $course ?: null, $batchYear, $employmentStatus, $companySchool ?: null, $contactInfo ?: null, $profilePicturePath !== '' ? $profilePicturePath : null]);
        }

        // Delete old file when replaced or removed
        if ($oldPicturePath !== '' && ($removePicture || $uploadedNewPicture) && $oldPicturePath !== $profilePicturePath) {
            $oldAbs = __DIR__ . '/' . ltrim(str_replace('\\', '/', $oldPicturePath), '/');
            $profilesDir = realpath(__DIR__ . '/uploads/profiles');
            $oldReal = realpath($oldAbs);
            if ($profilesDir && $oldReal && str_starts_with($oldReal, $profilesDir) && is_file($oldReal)) {
                @unlink($oldReal);
            }
        }

        if ($uploadedNewPicture) {
            $success = 'Profile saved. Profile picture updated.';
        } elseif ($removePicture) {
            $success = 'Profile saved. Profile picture removed.';
        } else {
            $success = 'Profile saved successfully.';
        }

        $stmt = $pdo->prepare('SELECT * FROM Graduate_Profiles WHERE User_ID = ? LIMIT 1');
        $stmt->execute([$userId]);
        $profile = $stmt->fetch();
    }
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

<div class="dashboard-shell">
  <?php require_once __DIR__ . '/includes/dashboard_rail.php'; ?>
  <div class="dashboard-content">
    <div class="app-card p-3 mb-3">
      <div class="d-flex align-items-center justify-content-between gap-2">
        <h1 class="h4 mb-0">Graduate Profile</h1>
        <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(app_url('dashboard')) ?>">Back</a>
      </div>
    </div>

    <div class="app-card p-3 mb-3">
      <h2 class="h6 mb-3">Submitted Information</h2>
      <?php if (!$profile): ?>
        <div class="alert alert-secondary mb-0">
          No submission yet. Please fill out the form below and click <strong>Save Profile</strong>.
        </div>
      <?php else: ?>
        <div class="row g-2">
          <div class="col-12">
            <?php if (((int)($profile['Is_Verified'] ?? 0)) === 1): ?>
              <span class="badge text-bg-success">Verified</span>
            <?php else: ?>
              <span class="badge text-bg-warning text-dark">Not yet verified</span>
            <?php endif; ?>
          </div>
          <div class="col-12 col-md-6">
            <div class="text-muted small">Full Name</div>
            <div class="fw-semibold"><?= htmlspecialchars((string)$profile['Full_Name']) ?></div>
          </div>
          <div class="col-12 col-md-6">
            <div class="text-muted small">Strand</div>
            <div class="fw-semibold"><?= htmlspecialchars((string)($profile['Course'] ?? '')) ?></div>
          </div>
          <div class="col-12 col-md-3">
            <div class="text-muted small">Batch Year</div>
            <div class="fw-semibold"><?= htmlspecialchars((string)$profile['Batch_Year']) ?></div>
          </div>
          <div class="col-12 col-md-3">
            <div class="text-muted small">Employment Status</div>
            <div class="fw-semibold"><?= htmlspecialchars((string)$profile['Employment_Status']) ?></div>
          </div>
          <div class="col-12 col-md-6">
            <div class="text-muted small">Company / School Name</div>
            <div class="fw-semibold"><?= htmlspecialchars((string)($profile['Company_School_Name'] ?? '')) ?></div>
          </div>
          <div class="col-12 col-md-6">
            <div class="text-muted small">Contact Info</div>
            <div class="fw-semibold"><?= htmlspecialchars((string)($profile['Contact_Info'] ?? '')) ?></div>
          </div>
          <?php if (!empty($profile['Profile_Picture'])): ?>
            <div class="col-12">
              <div class="text-muted small">Profile Picture</div>
              <img
                src="<?= htmlspecialchars((string)$profile['Profile_Picture']) ?>"
                alt="Profile picture"
                class="mt-1"
                style="width:88px;height:88px;object-fit:cover;border-radius:10px;border:1px solid #ddd;">
            </div>
          <?php endif; ?>
          <div class="col-12">
            <div class="text-muted small">Last Updated</div>
            <div class="fw-semibold"><?= htmlspecialchars((string)$profile['Last_Updated']) ?></div>
          </div>
        </div>
      <?php endif; ?>
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

    <div class="app-card p-3">
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

        <div class="mb-3">
          <label class="form-label" for="full_name">Full Name</label>
          <input class="form-control" id="full_name" name="full_name" required
            value="<?= htmlspecialchars($values['full_name']) ?>"
            <?= ($profile && (int)($profile['Is_Verified'] ?? 0) === 1) ? 'readonly style="background:#f0fff0;cursor:not-allowed;"' : '' ?>>
          <?php if ($profile && (int)($profile['Is_Verified'] ?? 0) === 1): ?>
            <div class="form-text text-success">
              <i class="bi bi-lock-fill"></i> Your name is locked because your profile is verified. Contact your Instructor to change it.
            </div>
          <?php endif; ?>
        </div>

        <div class="mb-3">
          <label class="form-label" for="course">Strand</label>
          <select class="form-select" id="course" name="course" required>
            <option value="" <?= $values['course'] === '' ? 'selected' : '' ?>>Choose Strand</option>
            <?php foreach (['HUMMS', 'STEMS', 'ABM'] as $strand): ?>
              <option value="<?= htmlspecialchars($strand) ?>" <?= $values['course'] === $strand ? 'selected' : '' ?>>
                <?= htmlspecialchars($strand) ?>
              </option>
            <?php endforeach; ?>
          </select>
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
          <div class="form-text">Fill this if Employed / Self-Employed / Further Studies.</div>
        </div>

        <div class="mb-3">
          <label class="form-label" for="contact_info">Contact Info</label>
          <input class="form-control" id="contact_info" name="contact_info"
            value="<?= htmlspecialchars($values['contact_info']) ?>"
            maxlength="13"
            placeholder="09XXXXXXXXX or +639XXXXXXXXX">
          <div class="form-text text-muted">
            Philippine mobile number only. Accepted formats:
            <strong>09XXXXXXXXX</strong> (11 digits) or <strong>+639XXXXXXXXX</strong> (13 characters).
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label" for="profile_picture">
            <?= !empty($profile['Profile_Picture']) ? 'Change Profile Picture' : 'Profile Picture (optional)' ?>
          </label>
          <?php if (!empty($profile['Profile_Picture'])): ?>
            <div class="d-flex flex-wrap align-items-center gap-3 mb-2">
              <img
                src="<?= htmlspecialchars((string)$profile['Profile_Picture']) ?>"
                alt="Current profile picture"
                style="width:96px;height:96px;object-fit:cover;border-radius:12px;border:1px solid #ddd;">
              <div>
                <div class="small text-muted mb-2">Current photo</div>
                <button
                  class="btn btn-sm btn-outline-danger"
                  type="submit"
                  name="action"
                  value="remove_picture"
                  formnovalidate
                  onclick="return confirm('Remove your profile picture?');">
                  <i class="bi bi-trash me-1"></i>Remove photo
                </button>
              </div>
            </div>
          <?php endif; ?>
          <input class="form-control" id="profile_picture" name="profile_picture" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
          <div class="form-text">
            <?= !empty($profile['Profile_Picture'])
              ? 'Choose a new image to replace your current photo. Max 2MB. JPG, PNG, or WEBP.'
              : 'Max 2MB. JPG, PNG, or WEBP.' ?>
          </div>
          <?php if (!empty($profile['Profile_Picture'])): ?>
            <div class="form-check mt-2">
              <input class="form-check-input" type="checkbox" value="1" id="remove_profile_picture" name="remove_profile_picture">
              <label class="form-check-label" for="remove_profile_picture">
                Remove current photo when I save
              </label>
            </div>
          <?php endif; ?>
        </div>

        <button class="btn btn-primary" type="submit" name="action" value="save">Save Profile</button>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

