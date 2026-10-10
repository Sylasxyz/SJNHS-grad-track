<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db_config.php';
require_once __DIR__ . '/includes/csrf.php';

require_roles(['Instructor', 'Admin']);
$user = current_user();

$pageTitle = 'Post Announcement';
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();

    $title = trim((string)($_POST['title'] ?? ''));
    $message = trim((string)($_POST['message'] ?? ''));
    $imagePath = null;

    if ($title === '' || strlen($title) < 3) {
        $errors[] = 'Title must be at least 3 characters.';
    }
    if (strlen($title) > 150) {
        $errors[] = 'Title is too long. Maximum 150 characters only.';
    }
    if ($message === '' || strlen($message) < 5) {
        $errors[] = 'Message must be at least 5 characters.';
    }
    if (strlen($message) > 2000) {
        $errors[] = 'Message is too long. Maximum 2000 characters only.';
    }
    if (preg_match('/https?:\/\//i', $title) || preg_match('/https?:\/\//i', $message)) {
        $errors[] = 'Announcements cannot contain external links (http:// or https://). Please remove any URLs.';
    }
    if (preg_match('/bit\.ly|tinyurl|t\.co|goo\.gl/i', $message)) {
        $errors[] = 'Announcements cannot contain shortened URLs or suspicious links.';
    }

    if (isset($_FILES['announcement_image']) && is_array($_FILES['announcement_image'])
        && (int)($_FILES['announcement_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $uploadErr = (int)($_FILES['announcement_image']['error'] ?? UPLOAD_ERR_OK);
        if ($uploadErr !== UPLOAD_ERR_OK) {
            $errors[] = 'Image upload failed.';
        } else {
            $tmp = (string)($_FILES['announcement_image']['tmp_name'] ?? '');
            $size = (int)($_FILES['announcement_image']['size'] ?? 0);
            if ($size > 3 * 1024 * 1024) {
                $errors[] = 'Image must be 3MB or less.';
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = (string)$finfo->file($tmp);
                $extMap = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                ];
                if (!isset($extMap[$mime])) {
                    $errors[] = 'Image must be JPG, PNG, or WEBP.';
                } else {
                    $uploadDir = __DIR__ . '/uploads/announcements';
                    if (!is_dir($uploadDir)) {
                        @mkdir($uploadDir, 0775, true);
                    }
                    $filename = 'a' . (int)$user['User_ID'] . '_' . time() . '.' . $extMap[$mime];
                    $destAbs = $uploadDir . '/' . $filename;
                    if (!move_uploaded_file($tmp, $destAbs)) {
                        $errors[] = 'Unable to save image.';
                    } else {
                        $imagePath = 'uploads/announcements/' . $filename;
                    }
                }
            }
        }
    }

    if (!$errors) {
        try {
            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO Announcements (Author_ID, Title, Message, Image_Path, Audience)
                     VALUES (?, ?, ?, ?, 'Everyone')"
                );
                $stmt->execute([(int)$user['User_ID'], $title, $message, $imagePath]);
            } catch (Throwable $e) {
                $stmt = $pdo->prepare(
                    'INSERT INTO Announcements (Author_ID, Title, Message, Image_Path) VALUES (?, ?, ?, ?)'
                );
                $stmt->execute([(int)$user['User_ID'], $title, $message, $imagePath]);
            }
            $success = 'Announcement posted successfully.';
            $_POST = [];
        } catch (Throwable $e) {
            if ($imagePath) {
                $abs = __DIR__ . '/' . $imagePath;
                if (is_file($abs)) {
                    @unlink($abs);
                }
            }
            $errors[] = 'Could not post announcement. Please try again.';
        }
    } elseif ($imagePath) {
        $abs = __DIR__ . '/' . $imagePath;
        if (is_file($abs)) {
            @unlink($abs);
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="dashboard-shell">
  <?php require_once __DIR__ . '/includes/dashboard_rail.php'; ?>
  <div class="dashboard-content">
    <div class="app-card p-3 mb-3">
      <div class="d-flex align-items-center justify-content-between gap-2">
        <h1 class="h4 mb-0">Post Announcement</h1>
        <a class="btn btn-sm btn-outline-secondary" href="dashboard.php">Back</a>
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

    <div class="app-card p-3">
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

        <div class="mb-3">
          <label class="form-label" for="title">Title</label>
          <input class="form-control" id="title" name="title" required
            maxlength="150"
            value="<?= htmlspecialchars((string)($_POST['title'] ?? '')) ?>">
          <div class="form-text text-muted">Maximum 150 characters.</div>
        </div>

        <div class="mb-3">
          <label class="form-label" for="message">Message</label>
          <textarea class="form-control" id="message" name="message" rows="6" required
            maxlength="2000"><?= htmlspecialchars((string)($_POST['message'] ?? '')) ?></textarea>
          <div class="form-text text-muted">
            Maximum 2000 characters. External links (http://, https://) are not allowed.
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label" for="announcement_image">Image (optional)</label>
          <input class="form-control" type="file" id="announcement_image" name="announcement_image"
            accept="image/jpeg,image/png,image/webp">
          <div class="form-text text-muted">JPG, PNG, or WEBP. Maximum 3MB.</div>
          <div class="mt-2" id="announcementImagePreview" hidden>
            <img src="" alt="Preview" class="announcement-image-preview">
          </div>
        </div>

        <button class="btn btn-primary" type="submit">Post</button>
        <a class="btn btn-link" href="announcements.php">View announcements</a>
      </form>
    </div>
  </div>
</div>

<script>
(() => {
  const input = document.getElementById('announcement_image');
  const previewWrap = document.getElementById('announcementImagePreview');
  const previewImg = previewWrap?.querySelector('img');
  if (!input || !previewWrap || !previewImg) return;

  input.addEventListener('change', () => {
    const file = input.files && input.files[0];
    if (!file) {
      previewWrap.hidden = true;
      previewImg.removeAttribute('src');
      return;
    }
    const url = URL.createObjectURL(file);
    previewImg.onload = () => URL.revokeObjectURL(url);
    previewImg.src = url;
    previewWrap.hidden = false;
  });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
