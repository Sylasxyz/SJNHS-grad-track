<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db_config.php';
require_once __DIR__ . '/includes/csrf.php';

require_roles(['Instructor', 'Admin']);
$me = current_user();
$isAdmin = (($me['Role'] ?? '') === 'Admin');
$canManage = can_manage_records();
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'verify_bulk') {
    csrf_validate();
    if (!$canManage) {
        $err = 'View-only accounts cannot verify profiles.';
    } else {
    $ids = array_filter(array_map('intval', (array)($_POST['profile_ids'] ?? [])));
    if (!$ids) {
        $err = 'Select at least one profile to verify.';
    } else {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([(int)$me['User_ID']], $ids);
        $upd = $pdo->prepare(
            "UPDATE Graduate_Profiles
             SET Is_Verified = 1, Verified_By = ?, Verified_At = NOW()
             WHERE Profile_ID IN ($placeholders) AND Is_Verified = 0"
        );
        $upd->execute($params);
        $msg = 'Selected profiles marked as verified.';
    }
    }
}

$pageTitle = 'Graduate Search';

$batchYear = trim((string)($_GET['batch_year'] ?? ''));
$course = trim((string)($_GET['course'] ?? ''));
$status = trim((string)($_GET['employment_status'] ?? ''));
$q = trim((string)($_GET['q'] ?? ''));

$where = [];
$params = [];

if ($batchYear !== '') {
    $batchInt = (int)$batchYear;
    if ($batchInt >= 2000 && $batchInt <= 2050) {
        $where[] = 'gp.Batch_Year = ?';
        $params[] = $batchInt;
        $batchYear = (string)$batchInt;
    } else {
        $batchYear = '';
    }
}
$allowedStrands = ['HUMMS', 'STEMS', 'ABM'];
if ($course !== '' && in_array($course, $allowedStrands, true)) {
    $where[] = 'gp.Course = ?';
    $params[] = $course;
} else {
    $course = '';
}
if ($status !== '') {
    $where[] = 'gp.Employment_Status = ?';
    $params[] = $status;
}
if ($q !== '') {
    $where[] = '(gp.Full_Name LIKE ? OR ua.Student_No LIKE ? OR ua.Username LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql =
    "SELECT gp.Profile_ID, gp.User_ID, gp.Full_Name, gp.Batch_Year, gp.Employment_Status, gp.Company_School_Name, gp.Contact_Info, gp.Last_Updated,
            gp.Is_Verified,
            gp.Course,
            gp.Profile_Picture,
            ua.Username,
            ua.Student_No
     FROM Graduate_Profiles gp
     INNER JOIN User_Accounts ua ON ua.User_ID = gp.User_ID";

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' ORDER BY gp.Batch_Year DESC, gp.Full_Name ASC LIMIT 200';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="graduate-records-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    if ($out !== false) {
        fputcsv($out, ['Full Name', 'Username', 'Batch', 'Course', 'Employment Status', 'Verified', 'Company/School', 'Contact', 'Last Updated']);
        foreach ($rows as $r) {
            fputcsv($out, [
                (string)$r['Full_Name'],
                (string)$r['Username'],
                (int)$r['Batch_Year'],
                (string)($r['Course'] ?? ''),
                (string)$r['Employment_Status'],
                ((int)($r['Is_Verified'] ?? 0) === 1) ? 'Yes' : 'No',
                (string)($r['Company_School_Name'] ?? ''),
                (string)($r['Contact_Info'] ?? ''),
                (string)$r['Last_Updated'],
            ]);
        }
        fclose($out);
    }
    exit;
}

$viewMode = (string)($_GET['view'] ?? 'cards');
if (!in_array($viewMode, ['cards', 'table'], true)) {
    $viewMode = 'cards';
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="dashboard-shell">
  <?php require_once __DIR__ . '/includes/dashboard_rail.php'; ?>
  <div>
    <div class="app-card p-3 mb-3">
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
        <h1 class="h4 mb-0">Search / Verify Graduates</h1>
        <div class="d-flex gap-2">
          <a class="btn btn-sm btn-outline-secondary" href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['view' => $viewMode === 'cards' ? 'table' : 'cards']))) ?>">
            <?= $viewMode === 'cards' ? 'Table view' : 'Card view' ?>
          </a>
          <a class="btn btn-sm btn-outline-primary" href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['export' => 'csv']))) ?>">
            <i class="bi bi-download me-1"></i>Export CSV
          </a>
          <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(app_url('dashboard')) ?>">Back</a>
        </div>
      </div>
      <?php if ($msg): ?><div class="alert alert-success mb-0"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
      <?php if ($err): ?><div class="alert alert-danger mb-0 mt-2"><?= htmlspecialchars($err) ?></div><?php endif; ?>
    </div>

    <div class="app-card p-3 mb-3">
      <form class="row g-2" method="get">
        <div class="col-12 col-md-3">
          <select class="form-select" name="batch_year">
            <option value="">All Batch Years</option>
            <?php for ($y = 2050; $y >= 2000; $y--): ?>
              <option value="<?= $y ?>" <?= $batchYear === (string)$y ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="col-12 col-md-3">
          <select class="form-select" name="course">
            <option value="">All Strands</option>
            <?php foreach ($allowedStrands as $strand): ?>
              <option value="<?= htmlspecialchars($strand) ?>" <?= $course === $strand ? 'selected' : '' ?>>
                <?= htmlspecialchars($strand) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 col-md-3">
          <select class="form-select" name="employment_status">
            <option value="">All Status</option>
            <?php
              $options = ['Employed', 'Unemployed', 'Self-Employed', 'Further Studies'];
              foreach ($options as $opt) {
                  $sel = ($status === $opt) ? 'selected' : '';
                  echo '<option value="' . htmlspecialchars($opt) . '" ' . $sel . '>' . htmlspecialchars($opt) . '</option>';
              }
            ?>
          </select>
        </div>
        <div class="col-12 col-md-3">
          <input class="form-control" name="q" placeholder="Search name or Student ID" value="<?= htmlspecialchars($q) ?>">
        </div>
        <div class="col-12 col-md-2 d-grid">
          <button class="btn btn-primary" type="submit">Filter</button>
        </div>
      </form>
    </div>

    <div class="app-card p-3">
      <?php if (!$rows): ?>
        <div class="alert alert-secondary mb-0">No graduate profiles found.</div>
      <?php elseif ($viewMode === 'table'): ?>
        <?php if ($canManage): ?>
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
          <input type="hidden" name="action" value="verify_bulk">
          <div class="d-flex justify-content-between align-items-center mb-2 gap-2">
            <span class="text-muted small"><?= count($rows) ?> result(s)</span>
            <button class="btn btn-sm btn-success" type="submit"><i class="bi bi-check2-all me-1"></i>Verify selected</button>
          </div>
        <?php else: ?>
          <div class="d-flex justify-content-between align-items-center mb-2 gap-2">
            <span class="text-muted small"><?= count($rows) ?> result(s) · View only</span>
          </div>
        <?php endif; ?>
          <div class="table-responsive">
            <table class="table table-sm align-middle admin-table">
              <thead>
                <tr>
                  <?php if ($canManage): ?><th><input type="checkbox" id="selectAllProfiles"></th><?php endif; ?>
                  <th>Name</th>
                  <th>Batch</th>
                  <th>Course</th>
                  <th>Status</th>
                  <th>Verified</th>
                  <th>Updated</th>
                  <?php if ($canManage): ?><th>Actions</th><?php endif; ?>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($rows as $r): ?>
                  <tr>
                    <?php if ($canManage): ?>
                    <td>
                      <?php if ((int)($r['Is_Verified'] ?? 0) !== 1): ?>
                        <input type="checkbox" name="profile_ids[]" value="<?= (int)$r['Profile_ID'] ?>" class="profile-select">
                      <?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <td><?= htmlspecialchars((string)$r['Full_Name']) ?></td>
                    <td><?= (int)$r['Batch_Year'] ?></td>
                    <td><?= htmlspecialchars((string)($r['Course'] ?? '')) ?></td>
                    <td><?= htmlspecialchars((string)$r['Employment_Status']) ?></td>
                    <td><?= ((int)($r['Is_Verified'] ?? 0) === 1) ? '<span class="badge text-bg-success">Yes</span>' : '<span class="badge text-bg-warning">No</span>' ?></td>
                    <td class="text-muted small"><?= htmlspecialchars((string)$r['Last_Updated']) ?></td>
                    <?php if ($canManage): ?>
                    <td><a class="btn btn-sm btn-primary" href="<?= htmlspecialchars(app_url('graduate_edit', ['profile_id' => (int)$r['Profile_ID']])) ?>">Edit</a></td>
                    <?php endif; ?>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php if ($canManage): ?></form><?php endif; ?>
        <div class="text-muted small mb-0">Showing up to 200 results.</div>
      <?php else: ?>
        <div class="vstack gap-3">
          <?php foreach ($rows as $r): ?>
            <div class="border rounded-3 p-3">
              <div class="row g-3 align-items-center">
                <div class="col-12 col-md-2">
                  <div class="border rounded-3 d-flex align-items-center justify-content-center bg-light text-muted" style="height:110px;">
                    <?php if (!empty($r['Profile_Picture'])): ?>
                      <img src="<?= htmlspecialchars((string)$r['Profile_Picture']) ?>" alt="Graduate photo" style="width:100%;height:110px;object-fit:cover;border-radius:8px;">
                    <?php else: ?>
                      <div class="text-center">
                        <i class="bi bi-person-badge fs-2 d-block"></i>
                        <small>No Photo</small>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="col-12 col-md-7">
                  <div class="fw-bold"><?= htmlspecialchars((string)$r['Full_Name']) ?></div>
                  <div class="text-muted small"><?= htmlspecialchars((string)$r['Username']) ?></div>
                  <div class="mt-2 d-flex flex-wrap gap-2">
                    <span class="badge text-bg-light border">Batch <?= htmlspecialchars((string)$r['Batch_Year']) ?></span>
                    <span class="badge text-bg-light border"><?= htmlspecialchars((string)($r['Course'] ?? 'No course')) ?></span>
                    <span class="badge text-bg-light border"><?= htmlspecialchars((string)$r['Employment_Status']) ?></span>
                    <?php if (((int)($r['Is_Verified'] ?? 0)) === 1): ?>
                      <span class="badge text-bg-success">Verified</span>
                    <?php else: ?>
                      <span class="badge text-bg-secondary">Unverified</span>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="col-12 col-md-3 text-md-end">
                  <button
                    type="button"
                    class="btn btn-sm btn-outline-primary mb-2"
                    data-bs-toggle="modal"
                    data-bs-target="#graduateInfoModal"
                    data-fullname="<?= htmlspecialchars((string)$r['Full_Name']) ?>"
                    data-username="<?= htmlspecialchars((string)$r['Username']) ?>"
                    data-batch="<?= htmlspecialchars((string)$r['Batch_Year']) ?>"
                    data-course="<?= htmlspecialchars((string)($r['Course'] ?? '')) ?>"
                    data-status="<?= htmlspecialchars((string)$r['Employment_Status']) ?>"
                    data-verified="<?= ((int)($r['Is_Verified'] ?? 0) === 1) ? 'Yes' : 'No' ?>"
                    data-company="<?= htmlspecialchars((string)($r['Company_School_Name'] ?? '')) ?>"
                    data-contact="<?= htmlspecialchars((string)($r['Contact_Info'] ?? '')) ?>"
                    data-updated="<?= htmlspecialchars((string)$r['Last_Updated']) ?>"
                    data-photo="<?= htmlspecialchars((string)($r['Profile_Picture'] ?? '')) ?>">
                    View Information
                  </button>
                  <?php if ($canManage): ?>
                  <div>
                    <a class="btn btn-sm btn-primary" href="<?= htmlspecialchars(app_url('graduate_edit', ['profile_id' => (int)$r['Profile_ID']])) ?>">Edit</a>
                  </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="text-muted small mb-0">Showing up to 200 results.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
  const selectAll = document.getElementById('selectAllProfiles');
  if (selectAll) {
    selectAll.addEventListener('change', () => {
      document.querySelectorAll('.profile-select').forEach((cb) => {
        cb.checked = selectAll.checked;
      });
    });
  }
</script>

<div class="modal fade" id="graduateInfoModal" tabindex="-1" aria-labelledby="graduateInfoModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title h5 mb-0" id="graduateInfoModalLabel">Student Information</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-12 col-md-4">
            <div class="border rounded-3 d-flex align-items-center justify-content-center bg-light text-muted" style="height:180px;">
              <img id="m_photo" src="" alt="Student photo" style="display:none;width:100%;height:180px;object-fit:cover;border-radius:8px;">
              <div id="m_photo_placeholder" class="text-center">
                <i class="bi bi-person-badge fs-2 d-block"></i>
                <small>No Photo</small>
              </div>
            </div>
          </div>
          <div class="col-12 col-md-8">
            <div class="row g-3">
          <div class="col-12 col-md-6"><small class="text-muted d-block">Full Name</small><div id="m_fullname" class="fw-semibold"></div></div>
          <div class="col-12 col-md-6"><small class="text-muted d-block">Username</small><div id="m_username" class="fw-semibold"></div></div>
          <div class="col-12 col-md-4"><small class="text-muted d-block">Batch</small><div id="m_batch" class="fw-semibold"></div></div>
          <div class="col-12 col-md-4"><small class="text-muted d-block">Course</small><div id="m_course" class="fw-semibold"></div></div>
          <div class="col-12 col-md-4"><small class="text-muted d-block">Verified</small><div id="m_verified" class="fw-semibold"></div></div>
          <div class="col-12 col-md-6"><small class="text-muted d-block">Employment Status</small><div id="m_status" class="fw-semibold"></div></div>
          <div class="col-12 col-md-6"><small class="text-muted d-block">Company / School</small><div id="m_company" class="fw-semibold"></div></div>
          <div class="col-12 col-md-6"><small class="text-muted d-block">Contact Info</small><div id="m_contact" class="fw-semibold"></div></div>
          <div class="col-12 col-md-6"><small class="text-muted d-block">Last Updated</small><div id="m_updated" class="fw-semibold"></div></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  const modal = document.getElementById('graduateInfoModal');
  if (modal) {
    modal.addEventListener('show.bs.modal', (event) => {
      const button = event.relatedTarget;
      const get = (k) => button?.getAttribute('data-' + k) || '';
      document.getElementById('m_fullname').textContent = get('fullname');
      document.getElementById('m_username').textContent = get('username');
      document.getElementById('m_batch').textContent = get('batch');
      document.getElementById('m_course').textContent = get('course');
      document.getElementById('m_verified').textContent = get('verified');
      document.getElementById('m_status').textContent = get('status');
      document.getElementById('m_company').textContent = get('company');
      document.getElementById('m_contact').textContent = get('contact');
      document.getElementById('m_updated').textContent = get('updated');
      const photo = get('photo');
      const img = document.getElementById('m_photo');
      const ph = document.getElementById('m_photo_placeholder');
      if (photo) {
        img.src = photo;
        img.style.display = 'block';
        ph.style.display = 'none';
      } else {
        img.src = '';
        img.style.display = 'none';
        ph.style.display = 'block';
      }
    });
  }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

