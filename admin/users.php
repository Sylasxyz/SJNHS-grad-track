<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db_config.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/mailer.php';

require_roles(['Admin']);

$pageTitle = 'Manage Users';
$msg = '';
$err = '';
$schemaWarn = '';

// Approve pending registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'approve') {
    csrf_validate();
    $userId = (int)($_POST['user_id'] ?? 0);
    if ($userId <= 0) {
        $err = 'Invalid user.';
    } else {
        $upd = $pdo->prepare("UPDATE User_Accounts SET Status = 'Active' WHERE User_ID = ? AND Status = 'Pending'");
        $upd->execute([$userId]);

        if ($upd->rowCount() < 1) {
            $err = 'User was not pending, or could not be approved.';
        } else {
            $fetch = $pdo->prepare('SELECT First_Name, Last_Name, Email FROM User_Accounts WHERE User_ID = ? LIMIT 1');
            $fetch->execute([$userId]);
            $u = $fetch->fetch();

            if ($u && !empty($u['Email']) && filter_var((string)$u['Email'], FILTER_VALIDATE_EMAIL)) {
                $to = (string)$u['Email'];
                $name = trim((string)($u['First_Name'] ?? '') . ' ' . (string)($u['Last_Name'] ?? '')) ?: 'User';
                $loginUrl = app_absolute_url('login.php');
                $subject = 'Account approved — San Josef NHS';
                $body =
                    "Hello {$name},\n\n" .
                    "Good news! Your Graduate Tracking System account has been approved by the Administrator.\n\n" .
                    "You can now log in and use the system.\n\n" .
                    "Login here:\n{$loginUrl}\n\n" .
                    "If you did not register for an account, please ignore this email or contact the school.\n\n" .
                    "— San Josef National High School\n";
                $mailResult = send_app_email($to, $subject, $body);
                $msg = !empty($mailResult['ok'])
                    ? 'User approved and notification email sent.'
                    : 'User approved. Email could not be sent (check SMTP in includes/mail_config.php or logs/mail.log).';
            } else {
                $msg = 'User approved, but no valid email was found for notification.';
            }
        }
    }
}

// Create user (admin tool)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    csrf_validate();

    $firstName = trim((string)($_POST['first_name'] ?? ''));
    $lastName = trim((string)($_POST['last_name'] ?? ''));
    $username = trim((string)($_POST['username'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $role = (string)($_POST['role'] ?? '');
    $status = (string)($_POST['status'] ?? 'Active');

    $allowedRoles = ['Admin', 'Instructor', 'Student', 'Principal'];
    $allowedStatus = ['Pending', 'Active', 'Inactive', 'Archived'];
    $idLabel = $role === 'Student' ? 'Student ID' : (in_array($role, ['Instructor', 'Principal'], true) ? 'Employee ID' : 'Username');

    if ($firstName === '' || strlen($firstName) < 2) {
        $err = 'First name must be at least 2 characters.';
    } elseif ($lastName === '' || strlen($lastName) < 2) {
        $err = 'Last name must be at least 2 characters.';
    } elseif (!in_array($role, $allowedRoles, true)) {
        $err = 'Please select a role.';
    } elseif ($username === '' || strlen($username) < 4) {
        $err = $idLabel . ' must be at least 4 characters.';
    } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'Email address is invalid.';
    } elseif ($password === '' || strlen($password) < 8) {
        $err = 'Password must be at least 8 characters.';
    } elseif (!in_array($status, $allowedStatus, true)) {
        $err = 'Invalid status.';
    } else {
        $studentNo = $role === 'Student' ? $username : null;
        $employeeId = in_array($role, ['Instructor', 'Principal'], true) ? $username : null;
        try {
            $check = $pdo->prepare('SELECT User_ID FROM User_Accounts WHERE Username = ? OR Email = ? OR Student_No = ? OR Employee_ID = ? LIMIT 1');
            $check->execute([$username, $email, $studentNo, $employeeId]);
        } catch (Throwable $e) {
            $check = $pdo->prepare('SELECT User_ID FROM User_Accounts WHERE Username = ? OR Email = ? LIMIT 1');
            $check->execute([$username, $email]);
        }
        if ($check->fetch()) {
            $err = $idLabel . ' or email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            try {
                $ins = $pdo->prepare('INSERT INTO User_Accounts (First_Name, Last_Name, Username, Email, Student_No, Employee_ID, Password, Role, Status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $ins->execute([$firstName, $lastName, $username, $email, $studentNo, $employeeId, $hash, $role, $status]);
            } catch (Throwable $e) {
                $ins = $pdo->prepare('INSERT INTO User_Accounts (First_Name, Last_Name, Username, Email, Password, Role, Status) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $ins->execute([$firstName, $lastName, $username, $email, $hash, $role, $status]);
            }
            $msg = 'User created.';
            $_POST = [];
        }
    }
}

// Edit user profile fields and optional password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    csrf_validate();
    $userId = (int)($_POST['user_id'] ?? 0);
    $firstName = trim((string)($_POST['first_name'] ?? ''));
    $lastName = trim((string)($_POST['last_name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $newPassword = (string)($_POST['new_password'] ?? '');

    if ($userId <= 0) {
        $err = 'Invalid user.';
    } elseif ($firstName === '' || strlen($firstName) < 2) {
        $err = 'First name must be at least 2 characters.';
    } elseif ($lastName === '' || strlen($lastName) < 2) {
        $err = 'Last name must be at least 2 characters.';
    } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'Email address is invalid.';
    } else {
        $dup = $pdo->prepare('SELECT User_ID FROM User_Accounts WHERE Email = ? AND User_ID <> ? LIMIT 1');
        $dup->execute([$email, $userId]);
        if ($dup->fetch()) {
            $err = 'Email already in use by another account.';
        } elseif ($newPassword !== '' && strlen($newPassword) < 8) {
            $err = 'New password must be at least 8 characters.';
        } else {
            if ($newPassword !== '') {
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                $upd = $pdo->prepare('UPDATE User_Accounts SET First_Name = ?, Last_Name = ?, Email = ?, Password = ? WHERE User_ID = ?');
                $upd->execute([$firstName, $lastName, $email, $hash, $userId]);
            } else {
                $upd = $pdo->prepare('UPDATE User_Accounts SET First_Name = ?, Last_Name = ?, Email = ? WHERE User_ID = ?');
                $upd->execute([$firstName, $lastName, $email, $userId]);
            }
            $msg = $newPassword !== '' ? 'User updated and password reset.' : 'User details updated.';
        }
    }
}

// Update status/role
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    csrf_validate();
    $userId = (int)($_POST['user_id'] ?? 0);
    $role = (string)($_POST['role'] ?? '');
    $status = (string)($_POST['status'] ?? '');
    $allowedRoles = ['Admin', 'Instructor', 'Student', 'Principal'];
    $allowedStatus = ['Pending', 'Active', 'Inactive', 'Archived'];

    if ($userId <= 0) {
        $err = 'Invalid user.';
    } elseif (!in_array($role, $allowedRoles, true) || !in_array($status, $allowedStatus, true)) {
        $err = 'Invalid role/status.';
    } else {
        // Prevent locking yourself out accidentally
        if ($userId === (int)current_user()['User_ID'] && $status !== 'Active') {
            $err = 'You cannot deactivate your own account.';
        } else {
            $prevStmt = $pdo->prepare('SELECT Status FROM User_Accounts WHERE User_ID = ? LIMIT 1');
            $prevStmt->execute([$userId]);
            $prevStatus = (string)($prevStmt->fetchColumn() ?: 'unknown');
            $upd = $pdo->prepare('UPDATE User_Accounts SET Role = ?, Status = ? WHERE User_ID = ?');
            $upd->execute([$role, $status, $userId]);
            log_admin_status_change($pdo, $userId, $prevStatus, $status, 'manual update');
            $msg = 'User updated.';
        }
    }
}
// Decline a pending registration by archiving (no account deletion)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reject') {
    csrf_validate();
    $userId = (int)($_POST['user_id'] ?? 0);
    if ($userId <= 0) {
        $err = 'Invalid user.';
    } else {
        $upd = $pdo->prepare("UPDATE User_Accounts SET Status = 'Archived' WHERE User_ID = ? AND Status = 'Pending'");
        $upd->execute([$userId]);
        $msg = 'Registration declined. Account archived (not deleted).';
    }
}

function log_admin_status_change(PDO $pdo, int $userId, string $fromStatus, string $toStatus, string $note = ''): void
{
    $fetch = $pdo->prepare('SELECT Username, Role FROM User_Accounts WHERE User_ID = ? LIMIT 1');
    $fetch->execute([$userId]);
    $target = $fetch->fetch();
    if (!$target) {
        return;
    }
    $logFile = __DIR__ . '/../logs/admin_actions.log';
    $logLine = date('Y-m-d H:i:s')
        . ' | ACTION: STATUS CHANGE'
        . ' | By Admin ID: ' . (int)current_user()['User_ID']
        . ' | User ID: ' . $userId
        . ' | Username: ' . ($target['Username'] ?? 'unknown')
        . ' | Role: ' . ($target['Role'] ?? 'unknown')
        . ' | From: ' . $fromStatus
        . ' | To: ' . $toStatus
        . ($note !== '' ? ' | Note: ' . $note : '')
        . ' | IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown')
        . "\n";
    file_put_contents($logFile, $logLine, FILE_APPEND);
}

// Permanent delete (Admin only) — frees email / Student ID for new registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_user') {
    csrf_validate();
    $userId = (int)($_POST['user_id'] ?? 0);
    if ($userId <= 0) {
        $err = 'Invalid user.';
    } elseif ($userId === (int)current_user()['User_ID']) {
        $err = 'You cannot delete your own account.';
    } else {
        $targetStmt = $pdo->prepare('SELECT User_ID, Username, Email, Role, Status FROM User_Accounts WHERE User_ID = ? LIMIT 1');
        $targetStmt->execute([$userId]);
        $target = $targetStmt->fetch();
        if (!$target) {
            $err = 'User not found.';
        } else {
            try {
                $del = $pdo->prepare('DELETE FROM User_Accounts WHERE User_ID = ?');
                $del->execute([$userId]);
                $msg = 'User permanently deleted. Email and login ID can be used for registration again.';
                $logFile = __DIR__ . '/../logs/admin_actions.log';
                $logLine = date('Y-m-d H:i:s')
                    . ' | ACTION: DELETE USER'
                    . ' | By Admin ID: ' . (int)current_user()['User_ID']
                    . ' | User ID: ' . $userId
                    . ' | Email: ' . ($target['Email'] ?? '')
                    . ' | Username: ' . ($target['Username'] ?? '')
                    . "\n";
                file_put_contents($logFile, $logLine, FILE_APPEND);
            } catch (PDOException $e) {
                $err = 'Could not delete user. Remove linked records first or delete related graduate profile, then try again.';
            }
        }
    }
}

// Quick deactivate (Inactive or Archived) — row remains (email still reserved until permanent delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'deactivate') {
    csrf_validate();
    $userId = (int)($_POST['user_id'] ?? 0);
    $newStatus = (string)($_POST['status'] ?? 'Inactive');
    $allowedStatus = ['Inactive', 'Archived'];
    if ($userId <= 0) {
        $err = 'Invalid user.';
    } elseif ($userId === (int)current_user()['User_ID']) {
        $err = 'You cannot deactivate your own account.';
    } elseif (!in_array($newStatus, $allowedStatus, true)) {
        $err = 'Invalid status.';
    } else {
        $prevStmt = $pdo->prepare("SELECT Status FROM User_Accounts WHERE User_ID = ? LIMIT 1");
        $prevStmt->execute([$userId]);
        $prevStatus = (string)($prevStmt->fetchColumn() ?: 'unknown');
        $upd = $pdo->prepare('UPDATE User_Accounts SET Status = ? WHERE User_ID = ?');
        $upd->execute([$newStatus, $userId]);
        log_admin_status_change($pdo, $userId, $prevStatus, $newStatus, 'quick deactivate');
        $msg = $newStatus === 'Archived' ? 'User archived.' : 'User set to inactive.';
    }
}

$search = trim((string)($_GET['q'] ?? ''));
$userSql = "SELECT User_ID, First_Name, Last_Name, CONCAT(First_Name, ' ', Last_Name) AS Full_Name, Username, Email, Role, Status, Created_At FROM User_Accounts";
$userParams = [];
if ($search !== '') {
    $userSql .= " WHERE First_Name LIKE ? OR Last_Name LIKE ? OR Username LIKE ? OR Email LIKE ? OR Role LIKE ? OR Status LIKE ?";
    $like = '%' . $search . '%';
    $userParams = [$like, $like, $like, $like, $like, $like];
}
$userSql .= ' ORDER BY User_ID DESC LIMIT 200';
$stmt = $pdo->prepare($userSql);
$stmt->execute($userParams);
$users = $stmt->fetchAll();

// Detect legacy schema/data (e.g. old enum without Pending causing blank status).
$invalidStatusCount = 0;
foreach ($users as $u) {
    $s = trim((string)($u['Status'] ?? ''));
    if (!in_array($s, ['Pending', 'Active', 'Inactive', 'Archived'], true)) {
        $invalidStatusCount++;
    }
}
if ($invalidStatusCount > 0) {
    $schemaWarn = 'Some user records have invalid/blank status. Please run the status migration (Status enum must include Pending).';
}

// Direct query for pending panel (includes blank legacy status so it becomes visible to admin).
$pendingStmt = $pdo->query(
    "SELECT User_ID, CONCAT(First_Name, ' ', Last_Name) AS Full_Name, Username, Email, Role, Status, Created_At
     FROM User_Accounts
     WHERE TRIM(COALESCE(Status, '')) = 'Pending' OR TRIM(COALESCE(Status, '')) = ''
     ORDER BY Created_At ASC
     LIMIT 200"
);
$pendingUsers = $pendingStmt->fetchAll();

// SECURITY FIX (A09): Fetch login security alerts
$securityAlerts    = [];
$securityAlertCount = 0;
try {
    $secAlerts = $pdo->query(
        "SELECT Username,
                IP_Address,
                COUNT(*) AS Attempt_Count,
                MAX(Attempted_At) AS Last_Attempt
         FROM Login_Attempts
         WHERE Was_Successful = 0
           AND Attempted_At >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
         GROUP BY Username, IP_Address
         HAVING Attempt_Count >= 5
         ORDER BY Last_Attempt DESC
         LIMIT 50"
    );
    $securityAlerts    = $secAlerts->fetchAll();
    $securityAlertCount = count($securityAlerts);
} catch (Throwable $e) {
    $securityAlerts    = [];
    $securityAlertCount = 0;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-shell">
  <?php require_once __DIR__ . '/../includes/dashboard_rail.php'; ?>
  <div>
    <div class="app-card p-3 p-md-4 mb-3">
      <div class="d-flex align-items-center justify-content-between gap-2">
        <div>
          <h1 class="h4 mb-1">Verify / Manage Users</h1>
          <div class="text-muted small">Approve accounts, change roles/status, deactivate, or permanently delete to free an email for registration.</div>
        </div>
        <div class="d-flex gap-2">
          <a class="btn btn-sm btn-outline-secondary app-btn" href="../dashboard.php">Back</a>
        </div>
      </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>
    <?php if ($schemaWarn): ?><div class="alert alert-warning"><?= htmlspecialchars($schemaWarn) ?></div><?php endif; ?>
      <div class="app-card p-3 p-md-4 mb-3 border" id="security-alerts"
         style="border-color: <?= $securityAlertCount > 0 ? '#dc3545' : '#dee2e6' ?> !important;">
      <div class="d-flex align-items-center justify-content-between mb-3 gap-2">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-shield-exclamation text-danger fs-5"></i>
          <h2 class="h6 mb-0">Security Alerts — Failed Login Attempts (Last 30 min)</h2>
        </div>
        <?php if ($securityAlertCount > 0): ?>
          <span class="badge text-bg-danger"><?= $securityAlertCount ?> alert(s)</span>
        <?php else: ?>
          <span class="badge text-bg-success">All clear</span>
        <?php endif; ?>
      </div>
      <?php if (!$securityAlerts): ?>
        <div class="alert alert-success mb-0 d-flex align-items-center gap-2">
          <i class="bi bi-check-circle-fill"></i>
          No suspicious login activity detected in the last 30 minutes.
        </div>
      <?php else: ?>
        <div class="alert alert-danger mb-3 d-flex align-items-start gap-2">
          <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
          <div>
            <strong>Warning:</strong> The following accounts received 5 or more failed login
            attempts in the last 30 minutes. This may indicate a brute-force or credential-stuffing
            attack. Consider reviewing or temporarily locking these accounts.
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-sm align-middle admin-table table-danger">
            <thead>
              <tr>
                <th>Username Targeted</th>
                <th>IP Address</th>
                <th>Failed Attempts</th>
                <th>Last Attempt</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($securityAlerts as $sa): ?>
                <tr class="table-danger">
                  <td class="fw-semibold"><?= htmlspecialchars((string)$sa['Username']) ?></td>
                  <td><code><?= htmlspecialchars((string)$sa['IP_Address']) ?></code></td>
                  <td><span class="badge text-bg-danger fs-6"><?= (int)$sa['Attempt_Count'] ?></span></td>
                  <td class="text-muted small"><?= htmlspecialchars((string)$sa['Last_Attempt']) ?></td>
                  <td>
                    <?php
                      $tgtStmt = $pdo->prepare("SELECT User_ID, Status FROM User_Accounts WHERE Username = ? LIMIT 1");
                      $tgtStmt->execute([(string)$sa['Username']]);
                      $tgtUser = $tgtStmt->fetch();
                    ?>
                    <?php if ($tgtUser && (string)$tgtUser['Status'] === 'Active'): ?>
                      <form method="post" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="user_id" value="<?= (int)$tgtUser['User_ID'] ?>">
                        <input type="hidden" name="role" value="Student">
                        <input type="hidden" name="status" value="Inactive">
                        <button class="btn btn-sm btn-danger" type="submit"
                                onclick="return confirm('Temporarily lock this account?');">
                          <i class="bi bi-lock-fill me-1"></i>Lock Account
                        </button>
                      </form>
                    <?php elseif ($tgtUser): ?>
                      <span class="badge text-bg-secondary">Already <?= htmlspecialchars((string)$tgtUser['Status']) ?></span>
                    <?php else: ?>
                      <span class="text-muted small">Unknown user</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <div class="app-card p-3 p-md-4 mb-3">
      <div class="d-flex align-items-center justify-content-between mb-3 gap-2">
        <h2 class="h6 mb-0">Pending Registrations</h2>
        <span class="badge text-bg-warning"><?= count($pendingUsers) ?> pending</span>
      </div>
      <?php if (!$pendingUsers): ?>
        <div class="alert alert-secondary mb-0">No pending registrations.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle admin-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Full Name</th>
                <th>Student ID / Employee ID</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Submitted</th>
                <th style="width: 140px;">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($pendingUsers as $u): ?>
                <tr>
                  <td><?= (int)$u['User_ID'] ?></td>
                  <td><?= htmlspecialchars((string)$u['Full_Name']) ?></td>
                  <td><?= htmlspecialchars((string)$u['Username']) ?></td>
                  <td><?= htmlspecialchars((string)$u['Email']) ?></td>
                  <td><?= htmlspecialchars((string)$u['Role']) ?></td>
                  <td>
                    <?php $ps = trim((string)($u['Status'] ?? '')); ?>
                    <?php if ($ps === 'Pending' || $ps === ''): ?>
                      <span class="badge text-bg-warning"><?= $ps === '' ? 'Pending (legacy/blank)' : 'Pending' ?></span>
                    <?php else: ?>
                      <span class="badge text-bg-secondary"><?= htmlspecialchars($ps) ?></span>
                    <?php endif; ?>
                  </td>
                  <td class="text-muted small"><?= htmlspecialchars((string)$u['Created_At']) ?></td>
                  <td>
                    <form method="post">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                      <input type="hidden" name="action" value="approve">
                      <input type="hidden" name="user_id" value="<?= (int)$u['User_ID'] ?>">
                      <button class="btn btn-sm btn-success admin-action-btn" type="submit">
                        <i class="bi bi-check-circle me-1"></i>Approve
                      </button>
                    </form> 

                    <form method="post" class="mt-1" onsubmit="return confirm('Decline and archive this registration? The account will be kept on record.');">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                      <input type="hidden" name="action" value="reject">
                      <input type="hidden" name="user_id" value="<?= (int)$u['User_ID'] ?>">
                      <button class="btn btn-sm btn-outline-secondary admin-action-btn" type="submit">
                        <i class="bi bi-archive me-1"></i>Decline
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <div class="app-card p-3 p-md-4 mb-3">
      <h2 class="h6 mb-3">Add User</h2>
      <?php $createRole = (string)($_POST['role'] ?? ''); ?>
      <form class="row g-2 align-items-end" method="post" id="adminCreateUserForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="action" value="create">
        <div class="col-12 col-md-2">
          <input class="form-control" name="first_name" placeholder="First name" required value="<?= htmlspecialchars((string)($_POST['first_name'] ?? '')) ?>">
        </div>
        <div class="col-12 col-md-2">
          <input class="form-control" name="last_name" placeholder="Last name" required value="<?= htmlspecialchars((string)($_POST['last_name'] ?? '')) ?>">
        </div>
        <div class="col-12 col-md-2">
          <input class="form-control" name="username" id="createUserId" value="<?= htmlspecialchars((string)($_POST['username'] ?? '')) ?>" required>
        </div>
        <div class="col-12 col-md-2">
          <select class="form-select" name="role" id="createUserRole" required>
            <option value="" <?= $createRole === '' ? 'selected' : '' ?>>Select Role</option>
            <option value="Student" <?= $createRole === 'Student' ? 'selected' : '' ?>>Student</option>
            <option value="Instructor" <?= $createRole === 'Instructor' ? 'selected' : '' ?>>Instructor</option>
            <option value="Principal" <?= $createRole === 'Principal' ? 'selected' : '' ?>>Principal</option>
            <option value="Admin" <?= $createRole === 'Admin' ? 'selected' : '' ?>>Admin</option>
          </select>
        </div>
        <div class="col-12 col-md-3">
          <input class="form-control" name="email" type="email" placeholder="Email" required value="<?= htmlspecialchars((string)($_POST['email'] ?? '')) ?>">
        </div>
        <div class="col-12 col-md-3">
          <div class="input-group">
            <input class="form-control" type="password" name="password" id="createUserPassword" placeholder="Password" required>
            <button class="btn btn-outline-secondary" type="button" id="toggleCreateUserPassword" aria-label="Show password">
              <i class="bi bi-eye"></i>
            </button>
          </div>
        </div>
        <div class="col-6 col-md-2">
          <select class="form-select" name="status" required>
            <option>Pending</option>
            <option selected>Active</option>
            <option>Inactive</option>
            <option>Archived</option>
          </select>
        </div>
        <div class="col-12 col-md-2 d-grid">
          <button class="btn btn-primary admin-action-btn" type="submit"><i class="bi bi-person-plus me-1"></i>Create</button>
        </div>
      </form>
    </div>

    <div class="app-card p-3 p-md-4">
      <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
        <h2 class="h6 mb-0">Users</h2>
        <span class="text-muted small"><?= count($users) ?> shown</span>
      </div>
      <form class="row g-2 mb-3" method="get">
        <div class="col-12 col-md-8">
          <input class="form-control form-control-sm" name="q" placeholder="Search name, Student ID / Employee ID, email, role, or status" value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-12 col-md-2 d-grid">
          <button class="btn btn-sm btn-primary" type="submit">Search</button>
        </div>
        <?php if ($search !== ''): ?>
          <div class="col-12 col-md-2 d-grid">
            <a class="btn btn-sm btn-outline-secondary" href="users.php">Clear</a>
          </div>
        <?php endif; ?>
      </form>
      <?php if (!$users): ?>
        <div class="alert alert-secondary mb-0">No users found.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle admin-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Full Name</th>
                <th>Student ID / Employee ID</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Created</th>
                <th style="width: 260px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($users as $u): ?>
                <tr>
                  <td><?= (int)$u['User_ID'] ?></td>
                  <td><?= htmlspecialchars((string)$u['Full_Name']) ?></td>
                  <td><?= htmlspecialchars((string)$u['Username']) ?></td>
                  <td><?= htmlspecialchars((string)$u['Email']) ?></td>
                  <td><?= htmlspecialchars((string)$u['Role']) ?></td>
                  <td><?= htmlspecialchars((string)$u['Status']) ?></td>
                  <td class="text-muted small"><?= htmlspecialchars((string)$u['Created_At']) ?></td>
                  <td>
                    <details class="mb-1">
                      <summary class="small text-primary" style="cursor:pointer;">Edit details</summary>
                      <form class="row g-1 mt-2" method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="user_id" value="<?= (int)$u['User_ID'] ?>">
                        <input type="hidden" name="username" value="<?= htmlspecialchars((string)$u['Username']) ?>">
                        <div class="col-6"><input class="form-control form-control-sm" name="first_name" value="<?= htmlspecialchars((string)$u['First_Name']) ?>" required></div>
                        <div class="col-6"><input class="form-control form-control-sm" name="last_name" value="<?= htmlspecialchars((string)$u['Last_Name']) ?>" required></div>
                        <div class="col-12"><input class="form-control form-control-sm" name="email" type="email" value="<?= htmlspecialchars((string)$u['Email']) ?>" required></div>
                        <div class="col-12"><input class="form-control form-control-sm" name="new_password" type="password" placeholder="New password (optional)"></div>
                        <div class="col-12"><button class="btn btn-sm btn-outline-success admin-action-btn" type="submit"><i class="bi bi-pencil me-1"></i>Save details</button></div>
                      </form>
                    </details>
                    <form class="d-flex flex-wrap gap-1" method="post">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                      <input type="hidden" name="user_id" value="<?= (int)$u['User_ID'] ?>">
                      <input type="hidden" name="action" value="update">
                      <select class="form-select form-select-sm" name="role">
                        <?php foreach (['Student','Instructor','Principal','Admin'] as $r): ?>
                          <option <?= ($u['Role'] === $r) ? 'selected' : '' ?>><?= htmlspecialchars($r) ?></option>
                        <?php endforeach; ?>
                      </select>
                      <select class="form-select form-select-sm" name="status">
                        <?php foreach (['Pending','Active','Inactive','Archived'] as $s): ?>
                          <option <?= ($u['Status'] === $s) ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                        <?php endforeach; ?>
                      </select>
                      <button class="btn btn-sm btn-outline-primary admin-action-btn" type="submit">
                        <i class="bi bi-save me-1"></i>Save
                      </button>
                    </form>

                    <?php if ((int)$u['User_ID'] !== (int)current_user()['User_ID'] && (string)$u['Status'] === 'Active'): ?>
                      <form class="mt-1 d-inline" method="post" onsubmit="return confirm('Set this account to Inactive? The user cannot log in but records are kept.');">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                        <input type="hidden" name="user_id" value="<?= (int)$u['User_ID'] ?>">
                        <input type="hidden" name="action" value="deactivate">
                        <input type="hidden" name="status" value="Inactive">
                        <button class="btn btn-sm btn-outline-warning admin-action-btn" type="submit">
                          <i class="bi bi-pause-circle me-1"></i>Deactivate
                        </button>
                      </form>
                    <?php endif; ?>

                    <?php if ((int)$u['User_ID'] !== (int)current_user()['User_ID']): ?>
                      <form class="mt-1 d-inline" method="post" onsubmit="return confirm('Permanently delete this user? Email and Student/Employee ID can be registered again. This cannot be undone.');">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                        <input type="hidden" name="user_id" value="<?= (int)$u['User_ID'] ?>">
                        <input type="hidden" name="action" value="delete_user">
                        <button class="btn btn-sm btn-outline-danger admin-action-btn" type="submit">
                          <i class="bi bi-trash me-1"></i>Delete permanently
                        </button>
                      </form>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="text-muted small mb-0">Showing up to 200 users.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
  (function () {
    const roleEl = document.getElementById('createUserRole');
    const idEl = document.getElementById('createUserId');
    const toggleBtn = document.getElementById('toggleCreateUserPassword');
    const passwordEl = document.getElementById('createUserPassword');
    if (!roleEl || !idEl) return;

    function updateIdField() {
      const role = roleEl.value;
      if (role === 'Student') {
        idEl.placeholder = 'Student ID';
        idEl.disabled = false;
      } else if (role === 'Instructor' || role === 'Principal') {
        idEl.placeholder = 'Employee ID';
        idEl.disabled = false;
      } else if (role === 'Admin') {
        idEl.placeholder = 'Username';
        idEl.disabled = false;
      } else {
        idEl.placeholder = '';
        idEl.value = '';
        idEl.disabled = true;
      }
    }

    roleEl.addEventListener('change', updateIdField);
    updateIdField();

    if (toggleBtn && passwordEl) {
      toggleBtn.addEventListener('click', function () {
        const hidden = passwordEl.type === 'password';
        passwordEl.type = hidden ? 'text' : 'password';
        const icon = toggleBtn.querySelector('i');
        if (icon) icon.className = hidden ? 'bi bi-eye-slash' : 'bi bi-eye';
        toggleBtn.setAttribute('aria-label', hidden ? 'Hide password' : 'Show password');
      });
    }
  })();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

