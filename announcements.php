<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db_config.php';
require_once __DIR__ . '/includes/csrf.php';

require_login();
$user = current_user();
$role = (string)($user['Role'] ?? '');
$canManage = in_array($role, ['Admin', 'Instructor'], true);
$isAdmin = ($role === 'Admin');

$pageTitle = 'Announcements';
$announcements = [];
$listError = '';
$msg = '';
$err = '';
$hasArchiveColumn = false;

function ensure_announcement_archive_column(PDO $pdo): bool
{
    try {
        $col = $pdo->query("SHOW COLUMNS FROM Announcements LIKE 'Is_Archived'")->fetch();
        if ($col) {
            return true;
        }
        $pdo->exec(
            'ALTER TABLE Announcements
             ADD COLUMN Is_Archived TINYINT(1) NOT NULL DEFAULT 0 AFTER Date_Posted'
        );
        try {
            $pdo->exec('ALTER TABLE Announcements ADD KEY idx_announcements_archived (Is_Archived)');
        } catch (Throwable $e) {
            // Index may already exist.
        }
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

$hasArchiveColumn = ensure_announcement_archive_column($pdo);
$view = (string)($_GET['view'] ?? 'active');
if (!$canManage || !in_array($view, ['active', 'archived'], true)) {
    $view = 'active';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $action = (string)($_POST['action'] ?? '');
    $announcementId = (int)($_POST['announcement_id'] ?? 0);

    if (!$canManage) {
        $err = 'You do not have permission to manage announcements.';
    } elseif ($announcementId <= 0) {
        $err = 'Invalid announcement.';
    } elseif ($action === 'archive' && $hasArchiveColumn) {
        $upd = $pdo->prepare('UPDATE Announcements SET Is_Archived = 1 WHERE Announcement_ID = ?');
        $upd->execute([$announcementId]);
        $msg = 'Announcement archived.';
        $view = 'active';
    } elseif ($action === 'unarchive' && $hasArchiveColumn) {
        $upd = $pdo->prepare('UPDATE Announcements SET Is_Archived = 0 WHERE Announcement_ID = ?');
        $upd->execute([$announcementId]);
        $msg = 'Announcement restored to active list.';
        $view = 'archived';
    } elseif ($action === 'delete') {
        if (!$isAdmin) {
            $err = 'Only Admin can permanently delete announcements.';
        } else {
            $imgStmt = $pdo->prepare('SELECT Image_Path FROM Announcements WHERE Announcement_ID = ? LIMIT 1');
            $imgStmt->execute([$announcementId]);
            $imagePath = trim((string)($imgStmt->fetchColumn() ?: ''));

            $del = $pdo->prepare('DELETE FROM Announcements WHERE Announcement_ID = ?');
            $del->execute([$announcementId]);

            if ($imagePath !== '' && str_starts_with($imagePath, 'uploads/announcements/')) {
                $abs = __DIR__ . '/' . $imagePath;
                if (is_file($abs)) {
                    @unlink($abs);
                }
            }
            $msg = 'Announcement permanently deleted.';
        }
    } else {
        $err = 'Unsupported action.';
    }
}

try {
    $select =
        "SELECT a.Announcement_ID, a.Title, a.Message, a.Image_Path, a.Date_Posted,
                " . ($hasArchiveColumn ? 'a.Is_Archived,' : '0 AS Is_Archived,') . "
                u.Username AS Author
         FROM Announcements a
         LEFT JOIN User_Accounts u ON u.User_ID = a.Author_ID
         WHERE COALESCE(a.Audience, 'Everyone') = 'Everyone'";

    if ($hasArchiveColumn) {
        if ($canManage && $view === 'archived') {
            $select .= ' AND a.Is_Archived = 1';
        } else {
            $select .= ' AND a.Is_Archived = 0';
        }
    }

    $select .= ' ORDER BY a.Date_Posted DESC';
    $announcements = $pdo->query($select)->fetchAll();
} catch (Throwable $e) {
    try {
        $stmt = $pdo->query(
            "SELECT a.Announcement_ID, a.Title, a.Message, NULL AS Image_Path, a.Date_Posted,
                    0 AS Is_Archived, u.Username AS Author
             FROM Announcements a
             LEFT JOIN User_Accounts u ON u.User_ID = a.Author_ID
             ORDER BY a.Date_Posted DESC"
        );
        $announcements = $stmt->fetchAll();
    } catch (Throwable $e2) {
        $announcements = [];
        $listError = 'Unable to load announcements.';
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="dashboard-shell">
  <?php require_once __DIR__ . '/includes/dashboard_rail.php'; ?>
  <div class="dashboard-content">
    <div class="app-card p-3 mb-3">
      <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
        <div>
          <h1 class="h4 mb-0">Announcements</h1>
          <?php if ($canManage && $view === 'archived'): ?>
            <div class="text-muted small">Showing archived announcements</div>
          <?php endif; ?>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <?php if ($canManage && $hasArchiveColumn): ?>
            <?php if ($view === 'archived'): ?>
              <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(app_url('announcements')) ?>">Active</a>
            <?php else: ?>
              <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(app_url('announcements', ['view' => 'archived'])) ?>">Archived</a>
            <?php endif; ?>
          <?php endif; ?>
          <?php if ($canManage): ?>
            <a class="btn btn-sm btn-primary" href="<?= htmlspecialchars(app_url('announcement_create')) ?>">Post announcement</a>
          <?php endif; ?>
          <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(app_url('dashboard')) ?>">Back</a>
        </div>
      </div>
    </div>

    <?php if ($msg): ?>
      <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>
    <?php if ($err): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($err) ?></div>
    <?php endif; ?>
    <?php if ($listError): ?>
      <div class="alert alert-warning"><?= htmlspecialchars($listError) ?></div>
    <?php endif; ?>

    <?php if (!$announcements): ?>
      <div class="alert alert-secondary">
        <?= $view === 'archived' ? 'No archived announcements.' : 'No announcements yet.' ?>
      </div>
    <?php else: ?>
      <div class="vstack gap-3">
        <?php foreach ($announcements as $a): ?>
          <?php
            $imagePath = trim((string)($a['Image_Path'] ?? ''));
            $isArchived = ((int)($a['Is_Archived'] ?? 0) === 1);
          ?>
          <div class="app-card p-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
              <div class="d-flex flex-wrap align-items-center gap-2">
                <h2 class="h5 mb-0"><?= htmlspecialchars((string)$a['Title']) ?></h2>
                <?php if ($isArchived): ?>
                  <span class="badge text-bg-secondary">Archived</span>
                <?php endif; ?>
              </div>
              <small class="text-muted">
                <?= htmlspecialchars((string)$a['Date_Posted']) ?>
                <?php if (!empty($a['Author'])): ?>
                  · by <?= htmlspecialchars((string)$a['Author']) ?>
                <?php endif; ?>
              </small>
            </div>
            <hr>
            <p class="mb-0" style="white-space: pre-wrap;"><?= htmlspecialchars((string)$a['Message']) ?></p>
            <?php if ($imagePath !== ''): ?>
              <div class="announcement-image-wrap mt-3">
                <img src="<?= htmlspecialchars($imagePath) ?>" alt="Announcement image" class="announcement-image">
              </div>
            <?php endif; ?>

            <?php if ($canManage): ?>
              <div class="d-flex flex-wrap gap-2 mt-3">
                <?php if ($hasArchiveColumn && !$isArchived): ?>
                  <form method="post" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="action" value="archive">
                    <input type="hidden" name="announcement_id" value="<?= (int)$a['Announcement_ID'] ?>">
                    <button class="btn btn-sm btn-outline-secondary" type="submit">Archive</button>
                  </form>
                <?php endif; ?>
                <?php if ($hasArchiveColumn && $isArchived): ?>
                  <form method="post" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="action" value="unarchive">
                    <input type="hidden" name="announcement_id" value="<?= (int)$a['Announcement_ID'] ?>">
                    <button class="btn btn-sm btn-outline-primary" type="submit">Restore</button>
                  </form>
                <?php endif; ?>
                <?php if ($isAdmin): ?>
                  <form method="post" class="d-inline" onsubmit="return confirm('Permanently delete this announcement? This cannot be undone.');">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="announcement_id" value="<?= (int)$a['Announcement_ID'] ?>">
                    <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                  </form>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
