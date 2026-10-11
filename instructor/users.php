<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/app_config.php';
require_once __DIR__ . '/../includes/db_config.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/mailer.php';

// Instructor can manage Student accounts only (Principal can use Instructor role)
require_roles(['Instructor', 'Admin']);
$me = current_user();
$isAdmin = (($me['Role'] ?? '') === 'Admin');

$pageTitle = 'Manage Student Accounts';
$msg = '';
$err = '';

// Approve pending student registration (Instructor/Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'approve_student') {
    csrf_validate();
    $userId = (int)($_POST['user_id'] ?? 0);
    if ($userId <= 0) {
        $err = 'Invalid user.';
    } else {
        // Only approve Student accounts
        $upd = $pdo->prepare("UPDATE User_Accounts SET Status = 'Active' WHERE User_ID = ? AND Role = 'Student' AND Status = 'Pending'");
        $upd->execute([$userId]);

        if ($upd->rowCount() < 1) {
            $err = 'Student was not pending, or could not be approved.';
        } else {
            $fetch = $pdo->prepare("SELECT First_Name, Last_Name, Email FROM User_Accounts WHERE User_ID = ? AND Role = 'Student' LIMIT 1");
            $fetch->execute([$userId]);
            $u = $fetch->fetch();

            if ($u && !empty($u['Email']) && filter_var((string)$u['Email'], FILTER_VALIDATE_EMAIL)) {
                $to = (string)$u['Email'];
                $name = trim((string)($u['First_Name'] ?? '') . ' ' . (string)($u['Last_Name'] ?? '')) ?: 'Student';
                $loginUrl = app_absolute_url('login.php');
                $subject = 'Account approved — San Josef NHS';
                $body =
                    "Hello {$name},\n\n" .
                    "Good news! Your Graduate Tracking System account has been approved.\n\n" .
                    "You can now log in and use the system.\n\n" .
                    "Login here:\n{$loginUrl}\n\n" .
                    "If you did not register for an account, please ignore this email or contact the school.\n\n" .
                    "— San Josef National High School\n";
                $mailResult = send_app_email($to, $subject, $body);
                $msg = !empty($mailResult['ok'])
                    ? 'Student approved and notification email sent.'
                    : 'Student approved. ' . msg_staff_email_not_sent();
            } else {
                $msg = 'Student approved, but no valid email was found for notification.';
            }
        }
    }
}

// Update student status (Active/Inactive/Archived) - Instructor/Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_student_status') {
    csrf_validate();
    $userId = (int)($_POST['user_id'] ?? 0);
    $status = (string)($_POST['status'] ?? '');
    $allowedStatus = ['Pending', 'Active', 'Inactive', 'Archived'];
    if ($userId <= 0) {
        $err = 'Invalid user.';
    } elseif (!in_array($status, $allowedStatus, true)) {
        $err = 'Invalid status.';
    } else {
        $upd = $pdo->prepare("UPDATE User_Accounts SET Status = ? WHERE User_ID = ? AND Role = 'Student'");
        $upd->execute([$status, $userId]);
        $msg = 'Student status updated.';
    }
}

$pending = $pdo->query(
    "SELECT User_ID, CONCAT(First_Name, ' ', Last_Name) AS Full_Name, Username, Email, Status, Created_At
     FROM User_Accounts
     WHERE Role = 'Student' AND Status = 'Pending'
     ORDER BY Created_At ASC
     LIMIT 200"
)->fetchAll();

$students = $pdo->query(
    "SELECT User_ID, CONCAT(First_Name, ' ', Last_Name) AS Full_Name, Username, Email, Status, Created_At
     FROM User_Accounts
     WHERE Role = 'Student'
     ORDER BY User_ID DESC
     LIMIT 200"
)->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-shell">
  <?php require_once __DIR__ . '/../includes/dashboard_rail.php'; ?>
  <div>
    <div class="app-card p-3 mb-3">
      <div class="d-flex align-items-center justify-content-between gap-2">
        <h1 class="h4 mb-0">Manage Student Accounts</h1>
        <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(app_url('dashboard')) ?>">Back</a>
      </div>
      <div class="text-muted small mt-1">
        Instructors can approve and update <strong>Student</strong> accounts. (Admins still have full user management.)
      </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <div class="app-card p-3 mb-3">
      <h2 class="h6 mb-3">Pending Student Registrations</h2>
      <?php if (!$pending): ?>
        <div class="alert alert-secondary mb-0">No pending registrations.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead>
              <tr>
                <th>ID</th>
                <th>Full Name</th>
                <th>Student ID / Employee ID</th>
                <th>Email</th>
                <th>Submitted</th>
                <th style="width:140px;"></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($pending as $u): ?>
                <tr>
                  <td><?= (int)$u['User_ID'] ?></td>
                  <td><?= htmlspecialchars((string)$u['Full_Name']) ?></td>
                  <td><?= htmlspecialchars((string)$u['Username']) ?></td>
                  <td><?= htmlspecialchars((string)$u['Email']) ?></td>
                  <td class="text-muted small"><?= htmlspecialchars((string)$u['Created_At']) ?></td>
                  <td class="text-end">
                    <form method="post">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                      <input type="hidden" name="action" value="approve_student">
                      <input type="hidden" name="user_id" value="<?= (int)$u['User_ID'] ?>">
                      <button class="btn btn-sm btn-success" type="submit">Approve</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <div class="app-card p-3">
      <h2 class="h6 mb-3">Students</h2>
      <?php if (!$students): ?>
        <div class="alert alert-secondary mb-0">No student accounts.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead>
              <tr>
                <th>ID</th>
                <th>Full Name</th>
                <th>Student ID / Employee ID</th>
                <th>Email</th>
                <th>Status</th>
                <th style="width:260px;"></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($students as $u): ?>
                <tr>
                  <td><?= (int)$u['User_ID'] ?></td>
                  <td><?= htmlspecialchars((string)$u['Full_Name']) ?></td>
                  <td><?= htmlspecialchars((string)$u['Username']) ?></td>
                  <td><?= htmlspecialchars((string)$u['Email']) ?></td>
                  <td><?= htmlspecialchars((string)$u['Status']) ?></td>
                  <td class="text-end">
                    <form class="d-flex justify-content-end gap-1" method="post">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                      <input type="hidden" name="action" value="update_student_status">
                      <input type="hidden" name="user_id" value="<?= (int)$u['User_ID'] ?>">
                      <select class="form-select form-select-sm" name="status">
                        <?php foreach (['Pending','Active','Inactive','Archived'] as $s): ?>
                          <option <?= ($u['Status'] === $s) ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                        <?php endforeach; ?>
                      </select>
                      <button class="btn btn-sm btn-outline-primary" type="submit">Save</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

