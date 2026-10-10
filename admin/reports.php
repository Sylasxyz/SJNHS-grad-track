<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db_config.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/dashboard_analytics.php';
require_once __DIR__ . '/../includes/report_helpers.php';

require_roles(['Admin', 'Instructor', 'Principal']);

$pageTitle = 'Reports';
$msg = '';
$err = '';
$batchFrom = (int)($_GET['batch_from'] ?? 0);
$batchTo = (int)($_GET['batch_to'] ?? 0);
$viewReport = null;
$canManageReports = can_manage_records();

if (isset($_GET['export']) && (int)$_GET['export'] > 0) {
    $report = fetch_report_by_id($pdo, (int)$_GET['export']);
    if ($report) {
        export_report_csv($report);
        exit;
    }
    $err = 'Report not found.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    if (!$canManageReports) {
        $err = 'View-only accounts cannot generate or delete reports.';
    } else {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'generate') {
        $reportName = trim((string)($_POST['report_name'] ?? 'Analytics Report'));
        if ($reportName === '') {
            $reportName = 'Analytics Report';
        }
        $batchFrom = (int)($_POST['batch_from'] ?? 0);
        $batchTo = (int)($_POST['batch_to'] ?? 0);
        $employmentFilter = trim((string)($_POST['employment_status'] ?? 'all'));
        $allowedFilters = report_employment_filter_options();
        if (!in_array($employmentFilter, $allowedFilters, true)) {
            $employmentFilter = 'all';
        }
        $separateByStatus = isset($_POST['separate_by_status']) && (string)$_POST['separate_by_status'] === '1';

        try {
            if ($separateByStatus && ($employmentFilter === 'all' || $employmentFilter === '')) {
                $created = 0;
                foreach (['Employed', 'Unemployed', 'Self-Employed', 'Further Studies'] as $status) {
                    $analytics = compute_dashboard_analytics($pdo, $batchFrom, $batchTo, $status);
                    $graduates = fetch_report_graduates($pdo, $batchFrom, $batchTo, $status);
                    $statusName = $reportName . ' — ' . $status;
                    save_generated_report($pdo, $statusName, $analytics, $graduates);
                    $created++;
                }
                $msg = "Generated {$created} separate reports (one per employment status).";
            } else {
                $filterValue = $employmentFilter === 'all' ? '' : $employmentFilter;
                $analytics = compute_dashboard_analytics($pdo, $batchFrom, $batchTo, $filterValue);
                $graduates = fetch_report_graduates($pdo, $batchFrom, $batchTo, $filterValue);
                $label = employment_filter_label($filterValue);
                if ($label !== 'All Status' && stripos($reportName, $label) === false) {
                    $reportName .= ' — ' . $label;
                }
                save_generated_report($pdo, $reportName, $analytics, $graduates);
                $msg = 'Report generated successfully (' . $label . ').';
            }
        } catch (Throwable $e) {
            $err = 'Could not generate report.';
        }
    }

    if ($action === 'delete') {
        $reportId = (int)($_POST['report_id'] ?? 0);
        if ($reportId > 0) {
            $del = $pdo->prepare('DELETE FROM Reports WHERE Report_ID = ?');
            $del->execute([$reportId]);
            $msg = 'Report deleted.';
        }
    }
    }
}

if (isset($_GET['view']) && (int)$_GET['view'] > 0) {
    $viewReport = fetch_report_by_id($pdo, (int)$_GET['view']);
    if (!$viewReport) {
        $err = 'Report not found.';
    }
}

$reports = fetch_saved_reports($pdo, 50);
$snapshotTrends = fetch_snapshot_trends($pdo, 12);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-shell">
  <?php require_once __DIR__ . '/../includes/dashboard_rail.php'; ?>
  <div class="dashboard-content">
    <div class="app-card p-3 p-md-4 mb-3">
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
          <h1 class="h4 mb-1">Generate Reports</h1>
          <div class="text-muted small">
            <?php if ($canManageReports): ?>
              Create analytics reports, export CSV, and review saved snapshots.
            <?php else: ?>
              View and export saved analytics reports (view only).
            <?php endif; ?>
          </div>
        </div>
        <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(app_url('analytics')) ?>">View analytics</a>
      </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <div class="row g-3 mb-3">
      <?php if ($canManageReports): ?>
      <div class="col-12 col-lg-5">
        <div class="app-card p-3 p-md-4 h-100">
          <h2 class="h6 mb-3">Generate new report</h2>
          <form method="post" class="vstack gap-2" id="generateReportForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="generate">
            <div>
              <label class="form-label small text-muted" for="report_name">Report name</label>
              <input class="form-control form-control-sm" id="report_name" name="report_name" placeholder="Analytics Report" required>
            </div>
            <div class="row g-2">
              <div class="col-6">
                <label class="form-label small text-muted" for="batch_from">Batch from</label>
                <input class="form-control form-control-sm" id="batch_from" name="batch_from" type="number" placeholder="e.g. 2018" value="<?= htmlspecialchars((string)($batchFrom ?: '')) ?>">
              </div>
              <div class="col-6">
                <label class="form-label small text-muted" for="batch_to">Batch to</label>
                <input class="form-control form-control-sm" id="batch_to" name="batch_to" type="number" placeholder="e.g. 2024" value="<?= htmlspecialchars((string)($batchTo ?: '')) ?>">
              </div>
            </div>
            <div>
              <label class="form-label small text-muted" for="employment_status">Employment status</label>
              <select class="form-select form-select-sm" id="employment_status" name="employment_status">
                <option value="all">All Status</option>
                <option value="All Employed">All Employed</option>
                <option value="Employed">Employed</option>
                <option value="Unemployed">Unemployed</option>
                <option value="Self-Employed">Self-Employed</option>
                <option value="Further Studies">Further Studies</option>
              </select>
              <div class="form-text">All Employed includes Employed and Self-Employed.</div>
            </div>
            <div class="form-check" id="separateByStatusWrap">
              <input class="form-check-input" type="checkbox" value="1" id="separate_by_status" name="separate_by_status">
              <label class="form-check-label small" for="separate_by_status">
                Generate separate report for each status
              </label>
              <div class="form-text">Available when Employment status is All Status. Creates 4 reports.</div>
            </div>
            <button class="btn btn-primary btn-sm mt-1" type="submit">
              <i class="bi bi-file-earmark-plus me-1"></i>Generate report
            </button>
          </form>
        </div>
      </div>
      <div class="col-12 col-lg-7">
      <?php else: ?>
      <div class="col-12">
      <?php endif; ?>
        <div class="app-card p-3 p-md-4 h-100 dashboard-trend-card">
          <h2 class="h6 mb-2">Report snapshot trends</h2>
          <?php if (!$snapshotTrends): ?>
            <div class="text-muted small">Generate reports to build trend history over time.</div>
          <?php else: ?>
            <div class="dashboard-chart-wrap">
              <canvas id="reportsTrendChart"></canvas>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php if ($viewReport): ?>
      <?php $summary = $viewReport['summary']; ?>
      <div class="app-card p-3 p-md-4 mb-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
          <div>
            <h2 class="h6 mb-1"><?= htmlspecialchars($viewReport['report_name']) ?></h2>
            <div class="text-muted small">
              Generated <?= htmlspecialchars($viewReport['generated_date']) ?>
              · Status: <?= htmlspecialchars((string)(($summary['filters']['employment_status'] ?? 'All Status'))) ?>
            </div>
          </div>
          <div class="d-flex gap-2">
            <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars(app_url('admin/reports', ['export' => (int)$viewReport['report_id']])) ?>">
              <i class="bi bi-download me-1"></i>Export CSV
            </a>
            <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(app_url('reports')) ?>">Close</a>
          </div>
        </div>
        <div class="row g-3 mb-3">
          <div class="col-6 col-md-3"><div class="dashboard-kpi-card"><div class="dashboard-kpi-label">Profiles</div><div class="dashboard-kpi-value"><?= (int)($summary['total_profiles'] ?? 0) ?></div></div></div>
          <div class="col-6 col-md-3"><div class="dashboard-kpi-card"><div class="dashboard-kpi-label">Employment rate</div><div class="dashboard-kpi-value"><?= (float)($summary['employment_rate'] ?? 0) ?>%</div></div></div>
          <div class="col-6 col-md-3"><div class="dashboard-kpi-card"><div class="dashboard-kpi-label">Verified</div><div class="dashboard-kpi-value"><?= (float)($summary['verification_rate'] ?? 0) ?>%</div></div></div>
          <div class="col-6 col-md-3"><div class="dashboard-kpi-card"><div class="dashboard-kpi-label">Completion</div><div class="dashboard-kpi-value"><?= (float)($summary['profile_completion_rate'] ?? 0) ?>%</div></div></div>
        </div>
        <?php if (!empty($summary['employment_counts']) && is_array($summary['employment_counts'])): ?>
          <h3 class="h6">Employment counts</h3>
          <div class="d-flex flex-wrap gap-2 mb-3">
            <?php foreach ($summary['employment_counts'] as $statusLabel => $count): ?>
              <span class="badge text-bg-light border"><?= htmlspecialchars((string)$statusLabel) ?>: <?= (int)$count ?></span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <?php if (!empty($summary['decision_insights'])): ?>
          <h3 class="h6">Insights</h3>
          <ul class="dashboard-insight-list">
            <?php foreach ($summary['decision_insights'] as $insight): ?>
              <li><?= htmlspecialchars((string)$insight) ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
        <?php
          $graduates = is_array($summary['graduates'] ?? null) ? $summary['graduates'] : [];
        ?>
        <?php if ($graduates): ?>
          <h3 class="h6 mt-3">Graduate roster (<?= count($graduates) ?>)</h3>
          <div class="table-responsive">
            <table class="table table-sm align-middle admin-table mb-0">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Student ID</th>
                  <th>Batch</th>
                  <th>Strand</th>
                  <th>Status</th>
                  <th>Verified</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($graduates as $g): ?>
                  <tr>
                    <td><?= htmlspecialchars((string)($g['full_name'] ?? '')) ?></td>
                    <td><?= htmlspecialchars((string)($g['student_id'] ?? '')) ?></td>
                    <td><?= htmlspecialchars((string)($g['batch_year'] ?? '')) ?></td>
                    <td><?= htmlspecialchars((string)($g['strand'] ?? '')) ?></td>
                    <td><?= htmlspecialchars((string)($g['employment_status'] ?? '')) ?></td>
                    <td><?= htmlspecialchars((string)($g['verified'] ?? '')) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="app-card p-3 p-md-4">
      <div class="d-flex align-items-center justify-content-between mb-3 gap-2">
        <h2 class="h6 mb-0">Saved reports</h2>
        <span class="text-muted small"><?= count($reports) ?> report(s)</span>
      </div>
      <?php if (!$reports): ?>
        <div class="alert alert-secondary mb-0">No reports generated yet.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle admin-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Generated</th>
                <th>Employment</th>
                <th>Verified</th>
                <th style="width:220px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($reports as $report): ?>
                <?php $s = $report['summary']; ?>
                <tr>
                  <td><?= (int)$report['report_id'] ?></td>
                  <td><?= htmlspecialchars($report['report_name']) ?></td>
                  <td class="text-muted small"><?= htmlspecialchars($report['generated_date']) ?></td>
                  <td><?= (float)($s['employment_rate'] ?? 0) ?>%</td>
                  <td><?= (float)($s['verification_rate'] ?? 0) ?>%</td>
                  <td>
                    <div class="d-flex flex-wrap gap-1">
                      <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars(app_url('admin/reports', ['view' => (int)$report['report_id']])) ?>">View</a>
                      <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(app_url('admin/reports', ['export' => (int)$report['report_id']])) ?>">CSV</a>
                      <?php if ($canManageReports): ?>
                      <form method="post" class="d-inline" onsubmit="return confirm('Delete this report?');">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="report_id" value="<?= (int)$report['report_id'] ?>">
                        <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                      </form>
                      <?php endif; ?>
                    </div>
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

<script>
(() => {
  const statusSelect = document.getElementById('employment_status');
  const separateWrap = document.getElementById('separateByStatusWrap');
  const separateCheck = document.getElementById('separate_by_status');
  if (!statusSelect || !separateWrap || !separateCheck) return;

  function syncSeparateOption() {
    const isAll = statusSelect.value === 'all';
    separateWrap.style.opacity = isAll ? '1' : '.55';
    separateCheck.disabled = !isAll;
    if (!isAll) separateCheck.checked = false;
  }

  statusSelect.addEventListener('change', syncSeparateOption);
  syncSeparateOption();
})();
</script>

<?php if ($snapshotTrends): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
  const snapshotTrendData = <?= json_encode($snapshotTrends) ?>;
  const reportsTrendEl = document.getElementById('reportsTrendChart');
  if (reportsTrendEl && snapshotTrendData.length) {
    new Chart(reportsTrendEl, {
      type: 'line',
      data: {
        labels: snapshotTrendData.map((row) => row.label),
        datasets: [
          {
            label: 'Employment rate',
            data: snapshotTrendData.map((row) => row.employment_rate),
            borderColor: '#5B9A8B',
            backgroundColor: 'rgba(91, 154, 139, 0.12)',
            pointBackgroundColor: '#5B9A8B',
            pointBorderColor: '#ffffff',
            pointBorderWidth: 2,
            tension: 0.35
          },
          {
            label: 'Profile completion',
            data: snapshotTrendData.map((row) => row.profile_completion_rate),
            borderColor: '#6B9BB8',
            backgroundColor: 'rgba(107, 155, 184, 0.12)',
            pointBackgroundColor: '#6B9BB8',
            pointBorderColor: '#ffffff',
            pointBorderWidth: 2,
            tension: 0.35
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: { beginAtZero: true, max: 100, grid: { color: 'rgba(148, 163, 184, 0.25)' }, ticks: { color: '#64748b' } },
          x: { grid: { display: false }, ticks: { color: '#64748b' } }
        },
        plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 10 } } }
      }
    });
  }
</script>
<?php endif; ?>
