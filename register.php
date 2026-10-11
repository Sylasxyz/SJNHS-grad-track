<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/db_config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/app_config.php';
require_once __DIR__ . '/includes/mailer.php';

$isEmbed = (($_GET['embed'] ?? '') === '1');

if ($isEmbed && is_logged_in()) {
    echo '<script>window.top.location.href=' . json_encode(app_url('dashboard')) . ';</script>';
    exit;
}

$pageTitle = 'Register';
$errors = [];
$warnings = [];
$success = '';
$flashErr = pull_flash_error();
if ($flashErr !== '') {
    $errors[] = $flashErr;
}

function ensure_registration_name_columns(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }
    try {
        $pdo->exec('ALTER TABLE User_Accounts MODIFY COLUMN Middle_Initial VARCHAR(2) NULL');
    } catch (Throwable $e) {
        try {
            $pdo->exec('ALTER TABLE User_Accounts ADD COLUMN Middle_Initial VARCHAR(2) NULL AFTER Last_Name');
        } catch (Throwable $e2) {
            // Column may already exist with different privileges.
        }
    }
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM User_Accounts LIKE 'Name_Suffix'")->fetch();
        if (!$cols) {
            $pdo->exec('ALTER TABLE User_Accounts ADD COLUMN Name_Suffix VARCHAR(10) NULL AFTER Middle_Initial');
        }
    } catch (Throwable $e) {
        // Ignore if CREATE/ALTER not allowed.
    }
    $ready = true;
}

try {
    ensure_registration_name_columns($pdo);
    $pdo->query("SELECT Email, First_Name, Last_Name, Middle_Initial, Name_Suffix, Student_No, Employee_ID FROM User_Accounts LIMIT 1");
} catch (PDOException $e) {
    $errors[] = msg_registration_unavailable();
}

$allowedSuffixes = ['JR', 'SR', 'II', 'III', 'IV'];

/**
 * Detect common / easy-to-guess passwords even if they meet character rules.
 */
function is_easy_guess_password(string $password): bool
{
    $raw = $password;
    $lower = strtolower($raw);
    $compact = preg_replace('/[^a-z0-9]/', '', $lower) ?? $lower;

    $common = [
        'password', 'password1', 'password123', 'password123!', 'passw0rd',
        'admin', 'admin123', 'admin123!', 'administrator',
        'welcome', 'welcome1', 'welcome123', 'welcome123!',
        'qwerty', 'qwerty1', 'qwerty123', 'qwerty123!', 'qwertyuiop',
        'asdfgh', 'asdfghjkl', 'zxcvbn', 'zxcvbnm',
        'abc123', 'abcd1234', 'abcdef', 'abcdefg',
        '12345678', '123456789', '1234567890', '11111111', '00000000',
        'iloveyou', 'letmein', 'monkey', 'dragon', 'master', 'login',
        'changeme', 'changeme1', 'test1234', 'testing1',
        'sjnhs', 'sanjosef', 'cabanatuan', 'philippines',
        'student', 'student1', 'student123', 'student123!',
        'instructor', 'teacher', 'teacher1', 'graduate',
        'p@ssw0rd', 'p@ssword', 'p@ssword1', 'pass@word1',
        'summer2024', 'summer2025', 'summer2026',
        'winter2024', 'winter2025', 'winter2026',
    ];

    foreach ($common as $word) {
        if ($lower === $word || $compact === preg_replace('/[^a-z0-9]/', '', $word)) {
            return true;
        }
        if (strlen($word) >= 5 && str_contains($compact, preg_replace('/[^a-z0-9]/', '', $word) ?? $word)) {
            // Only flag short-ish passwords that clearly embed a common base word.
            if (strlen($compact) <= strlen(preg_replace('/[^a-z0-9]/', '', $word) ?? $word) + 4) {
                return true;
            }
        }
    }

    // Repeated same character: Aaaaaaaa1!
    if (preg_match('/^(.)\1{4,}/u', $raw) || preg_match('/(.)\1{5,}/u', $raw)) {
        return true;
    }

    // Simple sequences: 123456, abcdef, qwerty fragments
    $sequences = [
        '0123456789', '9876543210',
        'abcdefghijklmnopqrstuvwxyz',
        'zyxwvutsrqponmlkjihgfedcba',
        'qwertyuiop', 'asdfghjkl', 'zxcvbnm',
    ];
    foreach ($sequences as $seq) {
        for ($i = 0; $i <= strlen($seq) - 5; $i++) {
            $chunk = substr($seq, $i, 5);
            if ($chunk !== false && str_contains($compact, $chunk)) {
                return true;
            }
        }
    }

    // Mostly digits / letters with only one symbol at end (very common pattern)
    if (preg_match('/^[A-Za-z]+[0-9]{1,4}[!@#$%^&*._-]?$/', $raw) && strlen($raw) <= 12) {
        $letters = strtolower(preg_replace('/[^A-Za-z]/', '', $raw) ?? '');
        $commonWords = ['password', 'pass', 'admin', 'user', 'login', 'welcome', 'qwerty', 'abc', 'test', 'school', 'student'];
        foreach ($commonWords as $w) {
            if ($letters === $w || str_starts_with($letters, $w)) {
                return true;
            }
        }
    }

    return false;
}

function normalize_registration_email(string $raw): string
{
    return strtolower(trim($raw));
}

function registration_email_exists(PDO $pdo, string $email): bool
{
    if ($email === '') {
        return false;
    }
    $stmt = $pdo->prepare('SELECT User_ID FROM User_Accounts WHERE LOWER(TRIM(Email)) = ? LIMIT 1');
    $stmt->execute([$email]);
    return $stmt->fetchColumn() !== false;
}

function registration_login_id_exists(PDO $pdo, string $loginId): bool
{
    $loginId = trim($loginId);
    if ($loginId === '') {
        return false;
    }
    $stmt = $pdo->prepare('SELECT User_ID FROM User_Accounts WHERE Username = ? LIMIT 1');
    $stmt->execute([$loginId]);
    if ($stmt->fetchColumn() !== false) {
        return true;
    }
    try {
        $stmt2 = $pdo->prepare(
            'SELECT User_ID FROM User_Accounts WHERE Student_No = ? OR Employee_ID = ? LIMIT 1'
        );
        $stmt2->execute([$loginId, $loginId]);
        return $stmt2->fetchColumn() !== false;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Remove non-active accounts so email / Student ID / Employee ID can be used again.
 * (Archived, Inactive, and Pending rows still block UNIQUE keys until removed.)
 */
function registration_reclaim_inactive_accounts(PDO $pdo, string $email, string $loginId): void
{
    $reclaimable = ['Archived', 'Inactive', 'Pending'];
    $ids = [];

    if ($email !== '') {
        $stmt = $pdo->prepare(
            'SELECT User_ID, Status FROM User_Accounts WHERE LOWER(TRIM(Email)) = ?'
        );
        $stmt->execute([$email]);
        foreach ($stmt->fetchAll() as $row) {
            $status = trim((string)($row['Status'] ?? ''));
            if ($status === '' || in_array($status, $reclaimable, true)) {
                $ids[(int)$row['User_ID']] = true;
            }
        }
    }

    $loginId = trim($loginId);
    if ($loginId !== '') {
        $stmt = $pdo->prepare(
            'SELECT User_ID, Status FROM User_Accounts
             WHERE Username = ? OR Student_No = ? OR Employee_ID = ?'
        );
        try {
            $stmt->execute([$loginId, $loginId, $loginId]);
        } catch (Throwable $e) {
            $stmt = $pdo->prepare('SELECT User_ID, Status FROM User_Accounts WHERE Username = ?');
            $stmt->execute([$loginId]);
        }
        foreach ($stmt->fetchAll() as $row) {
            $status = trim((string)($row['Status'] ?? ''));
            if ($status === '' || in_array($status, $reclaimable, true)) {
                $ids[(int)$row['User_ID']] = true;
            }
        }
    }

    if (!$ids) {
        return;
    }

    $delStrict = $pdo->prepare(
        "DELETE FROM User_Accounts
         WHERE User_ID = ?
           AND (Status IN ('Archived','Inactive','Pending') OR TRIM(COALESCE(Status,'')) = '')"
    );
    foreach (array_keys($ids) as $userId) {
        try {
            $delStrict->execute([$userId]);
        } catch (Throwable $e) {
            // FK or permissions — ignore single row
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();

    $firstName = trim((string)($_POST['first_name'] ?? ''));
    $lastName = trim((string)($_POST['last_name'] ?? ''));
    $middleInitial = strtoupper(preg_replace('/[^A-Za-z]/', '', (string)($_POST['middle_initial'] ?? '')) ?? '');
    $nameSuffix = strtoupper(trim((string)($_POST['name_suffix'] ?? '')));
    $roleChoice = (string)($_POST['role'] ?? 'Student');
    if ($roleChoice === 'Instructor') {
        $email = normalize_registration_email((string)($_POST['instructor_email'] ?? $_POST['email'] ?? ''));
    } else {
        $email = normalize_registration_email((string)($_POST['student_email'] ?? $_POST['email'] ?? ''));
    }
    $studentNo = trim((string)($_POST['student_no'] ?? ''));
    $employeeId = trim((string)($_POST['employee_id'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');
    $agreeTerms = isset($_POST['agree_terms']);

    if ($firstName === '' || strlen($firstName) < 2) {
        $errors[] = 'First name must be at least 2 characters.';
    }
    if ($lastName === '' || strlen($lastName) < 2) {
        $errors[] = 'Last name must be at least 2 characters.';
    }
    if ($middleInitial !== '' && !preg_match('/^[A-Z]{1,2}$/', $middleInitial)) {
        $errors[] = 'Middle initial must be 1–2 letters only (optional).';
    }
    if ($nameSuffix !== '' && !in_array($nameSuffix, $allowedSuffixes, true)) {
        $errors[] = 'Suffix selection is invalid.';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email address is invalid.';
    }
    $allowedRoles = ['Student', 'Instructor'];
    if (!in_array($roleChoice, $allowedRoles, true)) {
        $errors[] = 'Role selection is invalid.';
    }
    // ── SECURITY FIX: Strong password policy ──
    if ($password === '' || strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter.';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number.';
    } elseif (!preg_match('/[\W_]/', $password)) {
        $errors[] = 'Password must contain at least one special character (e.g. @, #, !, $).';
    } elseif (is_easy_guess_password($password)) {
        $errors[] = 'That password is too easy to guess. Please choose a stronger, less common password.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }
    if (!$agreeTerms) {
        $errors[] = 'You must agree to the Terms and Conditions and Privacy Policy before registering.';
    }
    if ($roleChoice === 'Student') {
        if ($studentNo === '' || strlen($studentNo) < 4) {
            $errors[] = 'Student No. must be at least 4 characters.';
        }
        if ($employeeId !== '') {
            $errors[] = 'Employee ID is not valid for Student registration.';
        }
    } elseif ($roleChoice === 'Instructor') {
        if ($employeeId === '' || strlen($employeeId) < 4) {
            $errors[] = 'Employee ID must be at least 4 characters.';
        }
        if ($studentNo !== '') {
            $errors[] = 'Student No. is not valid for Instructor registration.';
        }
    }

    if (!$errors) {
        ensure_registration_name_columns($pdo);
        $username = ($roleChoice === 'Student') ? $studentNo : $employeeId;

        registration_reclaim_inactive_accounts($pdo, $email, $username);

        if (registration_email_exists($pdo, $email)) {
            $errors[] = 'This email address is already registered. Contact the school office if you need assistance.';
        }

        if ($roleChoice === 'Student' && $studentNo !== '') {
            if (registration_login_id_exists($pdo, $studentNo)) {
                $errors[] = 'This Student ID is already registered.';
            }
        }

        if ($roleChoice === 'Instructor' && $employeeId !== '') {
            if (registration_login_id_exists($pdo, $employeeId)) {
                $errors[] = 'This Employee ID is already registered.';
            }
        }

        if (!$errors) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $insert = $pdo->prepare(
                'INSERT INTO User_Accounts (First_Name, Last_Name, Middle_Initial, Name_Suffix, Username, Email, Student_No, Employee_ID, Password, Role, Status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            try {
                $insert->execute([
                    $firstName,
                    $lastName,
                    $middleInitial !== '' ? $middleInitial : null,
                    $nameSuffix !== '' ? $nameSuffix : null,
                    $username,
                    $email,
                    $studentNo !== '' ? $studentNo : null,
                    $employeeId !== '' ? $employeeId : null,
                    $hash,
                    $roleChoice,
                    'Pending',
                ]);
            } catch (PDOException $e) {
                $sqlState = (string)$e->getCode();
                $msg = $e->getMessage();
                if ($sqlState === '23000' || str_contains($msg, 'Duplicate')) {
                    if (str_contains($msg, 'uq_user_accounts_email') || str_contains(strtolower($msg), 'email')) {
                        $errors[] = 'This email address is already registered. Contact the school office if you need assistance.';
                    } elseif (str_contains($msg, 'uq_user_accounts_username') || str_contains(strtolower($msg), 'username')) {
                        $errors[] = $roleChoice === 'Student'
                            ? 'This Student ID is already registered.'
                            : 'This Employee ID is already registered.';
                    } elseif (str_contains($msg, 'student_no')) {
                        $errors[] = 'This Student ID is already registered.';
                    } elseif (str_contains($msg, 'employee_id')) {
                        $errors[] = 'This Employee ID is already registered.';
                    } else {
                        $errors[] = 'This account could not be created because a unique field is already in use.';
                    }
                } else {
                    throw $e;
                }
            }

            if ($errors) {
                // Skip success/mail when insert failed
            } else {

            $name = trim($firstName . ' ' . $lastName) ?: 'User';
            $loginIdLabel = $roleChoice === 'Student' ? 'Student ID' : 'Employee ID';
            $subject = 'Registration received — pending approval | San Josef NHS';
            $body =
                "Hello {$name},\n\n" .
                "Thank you for registering in the San Josef National High School Graduate Tracking System.\n\n" .
                "Your account has been submitted and is pending Admin approval.\n" .
                "Please wait while an administrator reviews your registration. You will receive another email once your account is approved.\n\n" .
                "Registration details:\n" .
                "- Role: {$roleChoice}\n" .
                "- {$loginIdLabel}: {$username}\n" .
                "- Email: {$email}\n\n" .
                "You cannot log in until your account is approved.\n\n" .
                "If you did not register for an account, please ignore this email or contact the school.\n\n" .
                "— San Josef National High School\n";

                $mailResult = send_app_email($email, $subject, $body, '', true);
                if (!empty($mailResult['ok'])) {
                    $success = 'Registration submitted. A confirmation email was sent to ' . $email . '. Please wait for Admin approval before logging in.';
                } else {
                    $success = 'Registration submitted successfully. Please wait for Admin approval before logging in.';
                    $warnings[] = mail_public_delivery_notice();
                }
                $_POST = [];
            }
        }
    }
}

$EMBED_AUTH = $isEmbed;
require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-12 col-xl-11">
    <div class="app-card auth-popup-card auth-split">
      <div class="auth-side-panel">
        <div class="auth-side-center">
          <?php if (!empty($SCHOOL_LOGO_URL)): ?>
            <img
              class="auth-side-logo"
              src="<?= htmlspecialchars(app_asset_or_url((string)$SCHOOL_LOGO_URL)) ?>"
              alt="<?= htmlspecialchars((string)($SCHOOL_NAME ?? 'School')) ?> logo">
          <?php else: ?>
            <div class="auth-side-mark">WELCOME.</div>
          <?php endif; ?>
        </div>
        <div class="auth-side-bottom">
          Already a member?
          <a class="fw-semibold text-decoration-none" href="<?= htmlspecialchars(app_url_embed('login', $isEmbed)) ?>">Log in</a>
        </div>
      </div>

      <div class="auth-form-panel">
        <div class="d-flex align-items-start justify-content-between mb-3">
          <h1 class="auth-title-sm mb-0">Register</h1>
          <div class="auth-logo-wrap auth-logo-right">
            <?php if (!empty($SCHOOL_LOGO_URL)): ?>
              <img class="auth-logo-img" src="<?= htmlspecialchars(app_asset_or_url((string)$SCHOOL_LOGO_URL)) ?>" alt="<?= htmlspecialchars((string)($SCHOOL_NAME ?? 'School')) ?> logo">
            <?php else: ?>
              <span class="auth-logo-fallback"><i class="bi bi-mortarboard"></i></span>
            <?php endif; ?>
          </div>
        </div>

        <?php if ($success): ?>
          <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($warnings): ?>
          <div class="alert alert-warning">
            <ul class="mb-0">
              <?php foreach ($warnings as $w): ?>
                <li><?= htmlspecialchars($w) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
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

        <form method="post" autocomplete="off">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
          <input type="hidden" name="role" id="role" value="<?= htmlspecialchars((string)($_POST['role'] ?? 'Student')) ?>">

          <div class="mb-3">
            <div class="fw-semibold mb-2">Choose your role</div>
            <?php $roleSel = (string)($_POST['role'] ?? 'Student'); ?>
            <div class="role-cards">
              <div class="role-card <?= ($roleSel === 'Student') ? 'is-selected' : '' ?>" data-role="Student" tabindex="0" role="radio" aria-checked="<?= ($roleSel === 'Student') ? 'true' : 'false' ?>">
                <div class="role-card-check" aria-hidden="true"><i class="bi bi-check-lg"></i></div>
                <div class="d-flex gap-3 align-items-start">
                  <div class="role-card-icon"><i class="bi bi-mortarboard fs-4"></i></div>
                  <div>
                    <div class="fw-bold">Student / Graduate</div>
                    <div class="text-muted small">Register to update your employment / further studies status.</div>
                  </div>
                </div>
              </div>
              <div class="role-card <?= ($roleSel === 'Instructor') ? 'is-selected' : '' ?>" data-role="Instructor" tabindex="0" role="radio" aria-checked="<?= ($roleSel === 'Instructor') ? 'true' : 'false' ?>">
                <div class="role-card-check" aria-hidden="true"><i class="bi bi-check-lg"></i></div>
                <div class="d-flex gap-3 align-items-start">
                  <div class="role-card-icon"><i class="bi bi-person-video3 fs-4"></i></div>
                  <div>
                    <div class="fw-bold">Teacher / Instructor</div>
                    <div class="text-muted small">Register to monitor graduates and verify/update records.</div>
                  </div>
                </div>
              </div>
            </div>
            <div class="small mt-2">
              Selected role:
              <span class="fw-bold text-success" id="selectedRoleLabel">
                <?= htmlspecialchars((string)($_POST['role'] ?? 'Student')) ?>
              </span>
            </div>
            <div class="form-text">Accounts require Admin verification before login.</div>
          </div>

          <div id="studentForm" class="d-none register-form-panel">
            <h2 class="h6 mb-3">Student Registration Form</h2>
            <div class="row g-2 mb-3 align-items-end">
              <div class="col-12 col-md-3">
                <label class="form-label" for="student_first_name">First Name</label>
                <input class="form-control" id="student_first_name" name="first_name" value="<?= htmlspecialchars((string)($_POST['first_name'] ?? '')) ?>">
              </div>
              <div class="col-12 col-md-3">
                <label class="form-label" for="student_last_name">Last Name</label>
                <input class="form-control" id="student_last_name" name="last_name" value="<?= htmlspecialchars((string)($_POST['last_name'] ?? '')) ?>">
              </div>
              <div class="col-12 col-md-3">
                <label class="form-label" for="student_middle_initial">M.I. <span class="text-muted fw-normal">(if you have)</span></label>
                <input class="form-control text-uppercase" maxlength="2" id="student_middle_initial" name="middle_initial" value="<?= htmlspecialchars((string)($_POST['middle_initial'] ?? '')) ?>" autocomplete="off">
              </div>
              <div class="col-12 col-md-3">
                <label class="form-label" for="student_name_suffix">Suffix <span class="text-muted fw-normal">(if you have)</span></label>
                <select class="form-select" id="student_name_suffix" name="name_suffix">
                  <option value="">None</option>
                  <?php foreach ($allowedSuffixes as $sfx): ?>
                    <option value="<?= htmlspecialchars($sfx) ?>" <?= ((string)($_POST['name_suffix'] ?? '') === $sfx) ? 'selected' : '' ?>><?= htmlspecialchars($sfx) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label" for="student_email">Email Address</label>
              <input class="form-control" type="email" id="student_email" name="student_email" value="<?= htmlspecialchars((string)($_POST['student_email'] ?? $_POST['email'] ?? '')) ?>">
            </div>
            <div class="mb-3">
              <label class="form-label" for="student_no">Student No.</label>
              <input class="form-control" id="student_no" name="student_no" value="<?= htmlspecialchars((string)($_POST['student_no'] ?? '')) ?>">
            </div>
            <div class="mb-3">
              <label class="form-label" for="student_password">Password</label>
              <div class="input-group">
                <input class="form-control" type="password" id="student_password" name="password" autocomplete="new-password">
                <button class="btn btn-outline-secondary" type="button" id="toggleStudentPassword" aria-label="Show password">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
              <div class="pw-strength mt-2" data-pw-for="student_password">
                <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                  <span class="small text-muted">Password strength</span>
                  <span class="small fw-semibold pw-strength-label">—</span>
                </div>
                <div class="pw-strength-bar" aria-hidden="true"><span class="pw-strength-fill"></span></div>
                <ul class="pw-strength-rules list-unstyled mb-0 mt-2 small">
                  <li data-rule="length"><i class="bi bi-circle"></i> At least 8 characters</li>
                  <li data-rule="upper"><i class="bi bi-circle"></i> One uppercase letter (A–Z)</li>
                  <li data-rule="lower"><i class="bi bi-circle"></i> One lowercase letter (a–z)</li>
                  <li data-rule="number"><i class="bi bi-circle"></i> One number (0–9)</li>
                  <li data-rule="symbol"><i class="bi bi-circle"></i> One symbol (e.g. @ # ! $)</li>
                  <li data-rule="safe"><i class="bi bi-circle"></i> Not an easy-to-guess password</li>
                </ul>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label" for="student_confirm_password">Confirm Password</label>
              <div class="input-group">
                <input class="form-control" type="password" id="student_confirm_password" name="confirm_password">
                <button class="btn btn-outline-secondary" type="button" id="toggleStudentConfirmPassword" aria-label="Show password">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
            </div>
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" value="1" id="student_agree_terms" name="agree_terms" <?= !empty($_POST['agree_terms']) ? 'checked' : '' ?>>
              <label class="form-check-label" for="student_agree_terms">
                I agree to the
                <a href="<?= htmlspecialchars(app_url_embed('terms', $isEmbed)) ?>" target="_blank" rel="noopener">Terms and Conditions</a>
                and
                <a href="<?= htmlspecialchars(app_url_embed('privacy', $isEmbed)) ?>" target="_blank" rel="noopener">Privacy Policy</a>
              </label>
            </div>
            <button class="btn btn-primary w-100 register-submit-btn" type="submit" id="studentRegisterBtn" disabled>Register as Student</button>
          </div>

          <div id="instructorForm" class="d-none register-form-panel">
            <h2 class="h6 mb-3">Instructor Registration Form</h2>
            <div class="row g-2 mb-3 align-items-end">
              <div class="col-12 col-md-3">
                <label class="form-label" for="instructor_first_name">First Name</label>
                <input class="form-control" id="instructor_first_name" name="first_name" value="<?= htmlspecialchars((string)($_POST['first_name'] ?? '')) ?>">
              </div>
              <div class="col-12 col-md-3">
                <label class="form-label" for="instructor_last_name">Last Name</label>
                <input class="form-control" id="instructor_last_name" name="last_name" value="<?= htmlspecialchars((string)($_POST['last_name'] ?? '')) ?>">
              </div>
              <div class="col-12 col-md-3">
                <label class="form-label" for="instructor_middle_initial">M.I. <span class="text-muted fw-normal">(if you have)</span></label>
                <input class="form-control text-uppercase" maxlength="2" id="instructor_middle_initial" name="middle_initial" value="<?= htmlspecialchars((string)($_POST['middle_initial'] ?? '')) ?>" autocomplete="off">
              </div>
              <div class="col-12 col-md-3">
                <label class="form-label" for="instructor_name_suffix">Suffix <span class="text-muted fw-normal">(if you have)</span></label>
                <select class="form-select" id="instructor_name_suffix" name="name_suffix">
                  <option value="">None</option>
                  <?php foreach ($allowedSuffixes as $sfx): ?>
                    <option value="<?= htmlspecialchars($sfx) ?>" <?= ((string)($_POST['name_suffix'] ?? '') === $sfx) ? 'selected' : '' ?>><?= htmlspecialchars($sfx) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label" for="instructor_email">Email Address</label>
              <input class="form-control" type="email" id="instructor_email" name="instructor_email" value="<?= htmlspecialchars((string)($_POST['instructor_email'] ?? $_POST['email'] ?? '')) ?>">
            </div>
            <div class="mb-3">
              <label class="form-label" for="employee_id">Employee ID</label>
              <input class="form-control" id="employee_id" name="employee_id" value="<?= htmlspecialchars((string)($_POST['employee_id'] ?? '')) ?>">
            </div>
            <div class="mb-3">
              <label class="form-label" for="instructor_password">Password</label>
              <div class="input-group">
                <input class="form-control" type="password" id="instructor_password" name="password" autocomplete="new-password">
                <button class="btn btn-outline-secondary" type="button" id="toggleInstructorPassword" aria-label="Show password">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
              <div class="pw-strength mt-2" data-pw-for="instructor_password">
                <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                  <span class="small text-muted">Password strength</span>
                  <span class="small fw-semibold pw-strength-label">—</span>
                </div>
                <div class="pw-strength-bar" aria-hidden="true"><span class="pw-strength-fill"></span></div>
                <ul class="pw-strength-rules list-unstyled mb-0 mt-2 small">
                  <li data-rule="length"><i class="bi bi-circle"></i> At least 8 characters</li>
                  <li data-rule="upper"><i class="bi bi-circle"></i> One uppercase letter (A–Z)</li>
                  <li data-rule="lower"><i class="bi bi-circle"></i> One lowercase letter (a–z)</li>
                  <li data-rule="number"><i class="bi bi-circle"></i> One number (0–9)</li>
                  <li data-rule="symbol"><i class="bi bi-circle"></i> One symbol (e.g. @ # ! $)</li>
                  <li data-rule="safe"><i class="bi bi-circle"></i> Not an easy-to-guess password</li>
                </ul>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label" for="instructor_confirm_password">Confirm Password</label>
              <div class="input-group">
                <input class="form-control" type="password" id="instructor_confirm_password" name="confirm_password">
                <button class="btn btn-outline-secondary" type="button" id="toggleInstructorConfirmPassword" aria-label="Show password">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
            </div>
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" value="1" id="instructor_agree_terms" name="agree_terms" <?= !empty($_POST['agree_terms']) ? 'checked' : '' ?>>
              <label class="form-check-label" for="instructor_agree_terms">
                I agree to the
                <a href="<?= htmlspecialchars(app_url_embed('terms', $isEmbed)) ?>" target="_blank" rel="noopener">Terms and Conditions</a>
                and
                <a href="<?= htmlspecialchars(app_url_embed('privacy', $isEmbed)) ?>" target="_blank" rel="noopener">Privacy Policy</a>
              </label>
            </div>
            <button class="btn btn-primary w-100 register-submit-btn" type="submit" id="instructorRegisterBtn" disabled>Register as Instructor</button>
          </div>

          <div class="text-center mt-3 small">
            Already have an account?
            <a class="fw-semibold text-decoration-none" href="<?= htmlspecialchars(app_url_embed('login', $isEmbed)) ?>">Login</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
  // Role card selection (Student vs Instructor)
  (function () {
    const roleInput = document.getElementById('role');
    const roleLabel = document.getElementById('selectedRoleLabel');
    const studentForm = document.getElementById('studentForm');
    const instructorForm = document.getElementById('instructorForm');
    const cards = document.querySelectorAll('.role-card[data-role]');
    if (!roleInput || !cards.length) return;

    function setRequiredFields(role) {
      const studentRequired = ['student_first_name', 'student_last_name', 'student_email', 'student_no', 'student_password', 'student_confirm_password'];
      const instructorRequired = ['instructor_first_name', 'instructor_last_name', 'instructor_email', 'employee_id', 'instructor_password', 'instructor_confirm_password'];
      const studentOptional = ['student_middle_initial', 'student_name_suffix'];
      const instructorOptional = ['instructor_middle_initial', 'instructor_name_suffix'];
      const studentTerms = document.getElementById('student_agree_terms');
      const instructorTerms = document.getElementById('instructor_agree_terms');

      studentRequired.forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        const active = (role === 'Student');
        el.required = active;
        el.disabled = !active;
      });
      studentOptional.forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        const active = (role === 'Student');
        el.required = false;
        el.disabled = !active;
      });
      if (studentTerms) {
        studentTerms.required = (role === 'Student');
        studentTerms.disabled = (role !== 'Student');
      }
      instructorRequired.forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        const active = (role === 'Instructor');
        el.required = active;
        el.disabled = !active;
      });
      instructorOptional.forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        const active = (role === 'Instructor');
        el.required = false;
        el.disabled = !active;
      });
      if (instructorTerms) {
        instructorTerms.required = (role === 'Instructor');
        instructorTerms.disabled = (role !== 'Instructor');
      }
      updateRegisterButtons(role);
    }

    function updateRegisterButtons(role) {
      const studentBtn = document.getElementById('studentRegisterBtn');
      const instructorBtn = document.getElementById('instructorRegisterBtn');
      const studentTerms = document.getElementById('student_agree_terms');
      const instructorTerms = document.getElementById('instructor_agree_terms');
      if (studentBtn && studentTerms) {
        studentBtn.disabled = !(role === 'Student' && studentTerms.checked);
      }
      if (instructorBtn && instructorTerms) {
        instructorBtn.disabled = !(role === 'Instructor' && instructorTerms.checked);
      }
    }

    function selectRole(role) {
      roleInput.value = role;
      if (roleLabel) roleLabel.textContent = role;
      if (studentForm) studentForm.classList.toggle('d-none', role !== 'Student');
      if (instructorForm) instructorForm.classList.toggle('d-none', role !== 'Instructor');
      setRequiredFields(role);
      cards.forEach(c => {
        const isSel = c.getAttribute('data-role') === role;
        c.classList.toggle('is-selected', isSel);
        c.setAttribute('aria-checked', isSel ? 'true' : 'false');
      });
    }

    cards.forEach(card => {
      card.addEventListener('click', () => selectRole(card.getAttribute('data-role')));
      card.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          selectRole(card.getAttribute('data-role'));
        }
      });
    });

    const studentTermsEl = document.getElementById('student_agree_terms');
    const instructorTermsEl = document.getElementById('instructor_agree_terms');
    if (studentTermsEl) {
      studentTermsEl.addEventListener('change', () => updateRegisterButtons(roleInput.value || 'Student'));
    }
    if (instructorTermsEl) {
      instructorTermsEl.addEventListener('change', () => updateRegisterButtons(roleInput.value || 'Student'));
    }

    selectRole(roleInput.value || 'Student');
  })();

  function setupPasswordToggle(btnId, inputId) {
    const btn = document.getElementById(btnId);
    const input = document.getElementById(inputId);
    if (!btn || !input) return;
    btn.addEventListener('click', () => {
      const hidden = input.type === 'password';
      input.type = hidden ? 'text' : 'password';
      const icon = btn.querySelector('i');
      if (icon) icon.className = hidden ? 'bi bi-eye-slash' : 'bi bi-eye';
    });
  }

  setupPasswordToggle('toggleStudentPassword', 'student_password');
  setupPasswordToggle('toggleStudentConfirmPassword', 'student_confirm_password');
  setupPasswordToggle('toggleInstructorPassword', 'instructor_password');
  setupPasswordToggle('toggleInstructorConfirmPassword', 'instructor_confirm_password');

  (function () {
    const COMMON_PASSWORDS = [
      'password', 'password1', 'password123', 'password123!', 'passw0rd',
      'admin', 'admin123', 'admin123!', 'administrator',
      'welcome', 'welcome1', 'welcome123', 'welcome123!',
      'qwerty', 'qwerty1', 'qwerty123', 'qwerty123!', 'qwertyuiop',
      'asdfgh', 'asdfghjkl', 'zxcvbn', 'zxcvbnm',
      'abc123', 'abcd1234', 'abcdef', 'abcdefg',
      '12345678', '123456789', '1234567890', '11111111', '00000000',
      'iloveyou', 'letmein', 'monkey', 'dragon', 'master', 'login',
      'changeme', 'changeme1', 'test1234', 'testing1',
      'sjnhs', 'sanjosef', 'cabanatuan', 'philippines',
      'student', 'student1', 'student123', 'student123!',
      'instructor', 'teacher', 'teacher1', 'graduate',
      'p@ssw0rd', 'p@ssword', 'p@ssword1', 'pass@word1',
      'summer2024', 'summer2025', 'summer2026',
      'winter2024', 'winter2025', 'winter2026'
    ];

    function compactPassword(value) {
      return String(value || '').toLowerCase().replace(/[^a-z0-9]/g, '');
    }

    function isEasyGuessPassword(value) {
      if (!value) return false;
      const lower = value.toLowerCase();
      const compact = compactPassword(value);

      for (const word of COMMON_PASSWORDS) {
        const wordCompact = compactPassword(word);
        if (lower === word || compact === wordCompact) return true;
        if (wordCompact.length >= 5 && compact.includes(wordCompact) && compact.length <= wordCompact.length + 4) {
          return true;
        }
      }

      if (/(.)\1{5,}/.test(value) || /^(.)\1{4,}/.test(value)) return true;

      const sequences = [
        '0123456789', '9876543210',
        'abcdefghijklmnopqrstuvwxyz',
        'zyxwvutsrqponmlkjihgfedcba',
        'qwertyuiop', 'asdfghjkl', 'zxcvbnm'
      ];
      for (const seq of sequences) {
        for (let i = 0; i <= seq.length - 5; i++) {
          if (compact.includes(seq.slice(i, i + 5))) return true;
        }
      }

      if (/^[A-Za-z]+[0-9]{1,4}[!@#$%^&*._-]?$/.test(value) && value.length <= 12) {
        const letters = value.toLowerCase().replace(/[^a-z]/g, '');
        const commonWords = ['password', 'pass', 'admin', 'user', 'login', 'welcome', 'qwerty', 'abc', 'test', 'school', 'student'];
        for (const w of commonWords) {
          if (letters === w || letters.startsWith(w)) return true;
        }
      }

      return false;
    }

    function evaluatePassword(value) {
      const policyOk = {
        length: value.length >= 8,
        upper: /[A-Z]/.test(value),
        lower: /[a-z]/.test(value),
        number: /[0-9]/.test(value),
        symbol: /[\W_]/.test(value),
      };
      const easy = value !== '' && isEasyGuessPassword(value);
      return {
        ...policyOk,
        safe: value !== '' && !easy,
        easy,
        policyComplete: Object.values(policyOk).every(Boolean),
      };
    }

    function bindStrengthMeter(inputId) {
      const input = document.getElementById(inputId);
      const wrap = document.querySelector('[data-pw-for="' + inputId + '"]');
      if (!input || !wrap) return;

      const fill = wrap.querySelector('.pw-strength-fill');
      const label = wrap.querySelector('.pw-strength-label');
      const rules = wrap.querySelectorAll('.pw-strength-rules [data-rule]');
      let hint = wrap.querySelector('.pw-strength-hint');
      if (!hint) {
        hint = document.createElement('div');
        hint.className = 'pw-strength-hint small mt-1';
        wrap.appendChild(hint);
      }

      function render() {
        const value = input.value || '';
        const checks = evaluatePassword(value);
        let score = 0;
        ['length', 'upper', 'lower', 'number', 'symbol', 'safe'].forEach((key) => {
          if (checks[key]) score += 1;
        });

        rules.forEach((li) => {
          const key = li.getAttribute('data-rule');
          const ok = !!(key && checks[key]);
          li.classList.toggle('is-met', ok);
          const icon = li.querySelector('i');
          if (icon) {
            icon.className = ok ? 'bi bi-check-circle-fill' : 'bi bi-circle';
          }
        });

        wrap.classList.remove('is-weak', 'is-fair', 'is-good', 'is-strong', 'is-empty');
        if (value === '') {
          wrap.classList.add('is-empty');
          if (label) label.textContent = '—';
          if (fill) fill.style.width = '0%';
          hint.textContent = '';
          return;
        }

        // Even if all character rules pass, force Weak when easy to guess.
        let level = 'weak';
        let text = 'Weak';
        let width = '25%';
        if (checks.easy || score <= 2) {
          level = 'weak';
          text = checks.easy ? 'Weak (easy to guess)' : 'Weak';
          width = '25%';
        } else if (score <= 4) {
          level = 'fair';
          text = 'Fair';
          width = '50%';
        } else if (score === 5) {
          level = 'good';
          text = 'Good';
          width = '75%';
        } else {
          level = 'strong';
          text = 'Strong';
          width = '100%';
        }

        wrap.classList.add('is-' + level);
        if (label) label.textContent = text;
        if (fill) fill.style.width = width;
        hint.textContent = checks.easy
          ? 'This password is common or easy to guess. Try a longer, less predictable mix.'
          : '';
      }

      input.addEventListener('input', render);
      input.addEventListener('change', render);
      render();
    }

    bindStrengthMeter('student_password');
    bindStrengthMeter('instructor_password');
  })();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
