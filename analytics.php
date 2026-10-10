<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db_config.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/dashboard_analytics.php';
require_once __DIR__ . '/includes/report_helpers.php';

require_login();
require_roles(['Admin', 'Instructor', 'Principal']);

$user = current_user();
$role = (string)($user['Role'] ?? '');
$canManageAnalytics = can_manage_records();

$pageTitle = 'Analytics & Decision Support';
$batchFrom = (int)($_POST['batch_from'] ?? $_GET['batch_from'] ?? 0);
$batchTo = (int)($_POST['batch_to'] ?? $_GET['batch_to'] ?? 0);
$savedSnapshot = '';
$analyticsError = '';

if ($canManageAnalytics && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_snapshot') {
    csrf_validate();
    $analyticsForSave = compute_dashboard_analytics($pdo, $batchFrom, $batchTo);
    $summary = build_report_summary($analyticsForSave);
    $ins = $pdo->prepare('INSERT INTO Reports (Report_Name, Summary_JSON) VALUES (?, ?)');
    $ins->execute(['Dashboard Analytics Snapshot', json_encode($summary, JSON_UNESCAPED_UNICODE)]);
    $savedSnapshot = 'Analytics snapshot saved.';
}

$analytics = null;
try {
    $analytics = compute_dashboard_analytics($pdo, $batchFrom, $batchTo);
} catch (Throwable $e) {
    $analytics = null;
    $analyticsError = 'Unable to load analytics right now. Please try again after confirming the database is up to date.';
}

$statusOrder = ($analytics ?? [])['status_order'] ?? ['Employed', 'Unemployed', 'Self-Employed', 'Further Studies'];
$countsByStatus = ($analytics ?? [])['counts_by_status'] ?? array_fill_keys($statusOrder, 0);

$instUnverified = [];
$snapshotTrends = [];

try {
    $uvSql = "SELECT gp.Profile_ID, gp.Full_Name, gp.Course, gp.Batch_Year, gp.Employment_Status, gp.Last_Updated, ua.Username
         FROM Graduate_Profiles gp
         INNER JOIN User_Accounts ua ON ua.User_ID = gp.User_ID
         WHERE gp.Is_Verified = 0 AND ua.Status = 'Active'";
    $uvParams = [];
    if ($batchFrom > 0) {
        $uvSql .= ' AND gp.Batch_Year >= ?';
        $uvParams[] = $batchFrom;
    }
    if ($batchTo > 0) {
        $uvSql .= ' AND gp.Batch_Year <= ?';
        $uvParams[] = $batchTo;
    }
    $uvSql .= ' ORDER BY gp.Last_Updated DESC LIMIT 8';
    $uvStmt = $pdo->prepare($uvSql);
    $uvStmt->execute($uvParams);
    $instUnverified = $uvStmt->fetchAll();
} catch (Throwable $e) {
    $instUnverified = [];
}

try {
    $snapshotTrends = fetch_snapshot_trends($pdo, 12);
} catch (Throwable $e) {
    $snapshotTrends = [];
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="dashboard-shell">
  <?php require_once __DIR__ . '/includes/dashboard_rail.php'; ?>

  <div class="dashboard-content analytics-page">
    <?php if ($savedSnapshot): ?>
      <div class="alert alert-success analytics-alert"><?= htmlspecialchars($savedSnapshot) ?></div>
    <?php endif; ?>

    <?php if ($analyticsError): ?>
      <div class="alert alert-warning analytics-alert"><?= htmlspecialchars($analyticsError) ?></div>
    <?php endif; ?>

    <header class="analytics-page-header">
      <div>
        <p class="analytics-eyebrow">Decision support</p>
        <h1 class="analytics-title">Analytics</h1>
        <p class="analytics-subtitle">Active accounts only · batch filters apply to every KPI and chart</p>
      </div>
      <?php if ($canManageAnalytics): ?>
        <div class="analytics-header-actions">
          <form method="post" class="m-0">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_snapshot">
            <input type="hidden" name="batch_from" value="<?= (int)$batchFrom ?>">
            <input type="hidden" name="batch_to" value="<?= (int)$batchTo ?>">
            <button class="btn btn-outline-secondary" type="submit">Save snapshot</button>
          </form>
          <a class="btn btn-dark" href="admin/reports.php">Generate reports</a>
        </div>
      <?php else: ?>
        <div class="analytics-header-actions">
          <a class="btn btn-dark" href="admin/reports.php">View reports</a>
        </div>
      <?php endif; ?>
    </header>

    <form class="analytics-filter-bar" method="get">
      <div class="analytics-filter-fields">
        <div class="analytics-filter-field">
          <label for="batch_from">Batch from</label>
          <input id="batch_from" name="batch_from" type="number" placeholder="2018" value="<?= htmlspecialchars((string)($batchFrom ?: '')) ?>">
        </div>
        <div class="analytics-filter-field">
          <label for="batch_to">Batch to</label>
          <input id="batch_to" name="batch_to" type="number" placeholder="2024" value="<?= htmlspecialchars((string)($batchTo ?: '')) ?>">
        </div>
      </div>
      <button class="btn btn-dark" type="submit">Apply</button>
      <?php if ($batchFrom > 0 || $batchTo > 0): ?>
        <a class="btn btn-outline-secondary" href="analytics.php">Clear</a>
      <?php endif; ?>
    </form>

    <?php if ($analytics): ?>
    <section class="analytics-stack">
      <div class="analytics-kpi-grid">
        <article class="analytics-kpi">
          <span class="analytics-kpi-label">Graduate profiles</span>
          <strong class="analytics-kpi-value"><?= (int)$analytics['total_profiles'] ?></strong>
        </article>
        <article class="analytics-kpi">
          <span class="analytics-kpi-label">Employment rate</span>
          <strong class="analytics-kpi-value"><?= (float)$analytics['employment_rate'] ?>%</strong>
        </article>
        <article class="analytics-kpi">
          <span class="analytics-kpi-label">Verified profiles</span>
          <strong class="analytics-kpi-value"><?= (float)$analytics['verification_rate'] ?>%</strong>
        </article>
        <article class="analytics-kpi">
          <span class="analytics-kpi-label">Profile completion</span>
          <strong class="analytics-kpi-value"><?= (float)$analytics['profile_completion_rate'] ?>%</strong>
          <span class="analytics-kpi-hint">
            <?= (int)($analytics['complete_profiles'] ?? 0) ?> complete
            · <?= (int)($analytics['incomplete_profiles'] ?? 0) ?> incomplete
            <?php if ((int)($analytics['students_without_profile'] ?? 0) > 0): ?>
              · <?= (int)$analytics['students_without_profile'] ?> no profile
            <?php endif; ?>
          </span>
        </article>
      </div>

      <div class="analytics-panel-grid analytics-panel-grid-2">
        <article class="analytics-panel">
          <h2 class="analytics-panel-title">Employment rate by batch</h2>
          <?php if (empty($analytics['batch_trends'])): ?>
            <p class="analytics-empty">No batch trend data for the selected filters.</p>
          <?php else: ?>
            <div class="analytics-chart">
              <canvas id="batchTrendChart"></canvas>
            </div>
          <?php endif; ?>
        </article>
        <article class="analytics-panel">
          <h2 class="analytics-panel-title">Snapshot trends</h2>
          <?php if (!$snapshotTrends): ?>
            <p class="analytics-empty">Save snapshots to track employment and verification over time.</p>
          <?php else: ?>
            <div class="analytics-chart">
              <canvas id="snapshotTrendChart"></canvas>
            </div>
          <?php endif; ?>
        </article>
      </div>

      <div class="analytics-panel-grid analytics-panel-grid-dist">
        <article class="analytics-panel">
          <h2 class="analytics-panel-title">Employment distribution</h2>
          <div class="analytics-chart analytics-chart-doughnut">
            <canvas id="employmentDoughnutChart"></canvas>
          </div>
        </article>
        <article class="analytics-panel">
          <h2 class="analytics-panel-title">Outcomes by course / strand</h2>
          <?php if (empty($analytics['course_breakdown'])): ?>
            <p class="analytics-empty">No course data available.</p>
          <?php else: ?>
            <div class="table-responsive">
              <table class="analytics-table">
                <thead>
                  <tr><th>Course</th><th>Profiles</th><th>Employed</th><th>Rate</th></tr>
                </thead>
                <tbody>
                  <?php foreach ($analytics['course_breakdown'] as $course): ?>
                    <tr>
                      <td><?= htmlspecialchars((string)$course['course']) ?></td>
                      <td><?= (int)$course['total'] ?></td>
                      <td><?= (int)$course['employed'] ?></td>
                      <td><?= (float)$course['rate'] ?>%</td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </article>
      </div>

      <article class="analytics-panel">
        <div class="analytics-panel-head">
          <h2 class="analytics-panel-title">Batch year breakdown</h2>
          <span class="analytics-panel-meta">Stacked employment counts</span>
        </div>
        <?php if (!$analytics['batch_years']): ?>
          <p class="analytics-empty">No batch data for the selected filter.</p>
        <?php else: ?>
          <div class="analytics-panel-grid analytics-panel-grid-dist analytics-panel-nested">
            <div class="analytics-chart analytics-chart-tall">
              <canvas id="batchChart"></canvas>
            </div>
            <div class="table-responsive">
              <table class="analytics-table">
                <thead>
                  <tr>
                    <th>Batch</th>
                    <?php foreach ($statusOrder as $s): ?>
                      <th><?= htmlspecialchars($s) ?></th>
                    <?php endforeach; ?>
                    <th>Rate</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($analytics['batch_years'] as $year): ?>
                    <?php
                      $rowTotal = array_sum($analytics['batch_matrix'][$year]);
                      $rowEmployed = ($analytics['batch_matrix'][$year]['Employed'] ?? 0) + ($analytics['batch_matrix'][$year]['Self-Employed'] ?? 0);
                      $rowRate = $rowTotal > 0 ? round(($rowEmployed / $rowTotal) * 100, 1) : 0;
                    ?>
                    <tr>
                      <td class="fw-semibold"><?= (int)$year ?></td>
                      <?php foreach ($statusOrder as $s): ?>
                        <td><?= (int)($analytics['batch_matrix'][$year][$s] ?? 0) ?></td>
                      <?php endforeach; ?>
                      <td><?= $rowRate ?>%</td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endif; ?>
      </article>

      <?php if ($instUnverified): ?>
      <article class="analytics-panel">
        <div class="analytics-panel-head">
          <h2 class="analytics-panel-title">Awaiting verification</h2>
          <a class="analytics-panel-link" href="graduates.php">Open graduate records</a>
        </div>
        <div class="table-responsive">
          <table class="analytics-table">
            <thead>
              <tr><th>Name</th><th>Course</th><th>Batch</th><th>Status</th><th>Updated</th></tr>
            </thead>
            <tbody>
              <?php foreach ($instUnverified as $uv): ?>
                <tr>
                  <td><?= htmlspecialchars((string)$uv['Full_Name']) ?></td>
                  <td><?= htmlspecialchars((string)($uv['Course'] ?? '')) ?></td>
                  <td><?= (int)$uv['Batch_Year'] ?></td>
                  <td><?= htmlspecialchars((string)$uv['Employment_Status']) ?></td>
                  <td class="text-muted"><?= htmlspecialchars((string)$uv['Last_Updated']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </article>
      <?php endif; ?>
    </section>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php if ($analytics): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
  const labels = <?= json_encode(array_values($statusOrder)) ?>;
  const data = <?= json_encode(array_values($countsByStatus)) ?>;
  const statusColors = ['#5B9A8B', '#D4A574', '#6B9BB8', '#A3B07A'];
  const chartGrid = 'rgba(148, 163, 184, 0.25)';
  const chartTick = '#64748b';

  Chart.defaults.color = chartTick;
  Chart.defaults.borderColor = chartGrid;

  const doughnutEl = document.getElementById('employmentDoughnutChart');
  if (doughnutEl) {
    new Chart(doughnutEl, {
      type: 'doughnut',
      data: {
        labels,
        datasets: [{
          data,
          backgroundColor: statusColors,
          borderColor: '#ffffff',
          borderWidth: 2,
          hoverOffset: 4
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '58%',
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 14, usePointStyle: true } } }
      }
    });
  }

  const batchYears = <?= json_encode($analytics['batch_years']) ?>;
  const batchDatasets = <?= json_encode($analytics['batch_datasets']) ?>;
  const batchEl = document.getElementById('batchChart');
  if (batchEl && batchYears.length) {
    const colors = {
      'Employed': '#5B9A8B',
      'Unemployed': '#D4A574',
      'Self-Employed': '#6B9BB8',
      'Further Studies': '#A3B07A'
    };
    for (const ds of batchDatasets) {
      ds.backgroundColor = colors[ds.label] || '#94a3b8';
      ds.borderRadius = 6;
      ds.stack = 'stack1';
    }
    new Chart(batchEl, {
      type: 'bar',
      data: { labels: batchYears, datasets: batchDatasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          x: { stacked: true, grid: { display: false } },
          y: { stacked: true, beginAtZero: true, ticks: { precision: 0 }, grid: { color: chartGrid } }
        },
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 14, usePointStyle: true } } }
      }
    });
  }

  const batchTrendData = <?= json_encode($analytics['batch_trends']) ?>;
  const batchTrendEl = document.getElementById('batchTrendChart');
  if (batchTrendEl && batchTrendData.length) {
    new Chart(batchTrendEl, {
      type: 'line',
      data: {
        labels: batchTrendData.map((row) => String(row.year)),
        datasets: [{
          label: 'Employment rate (%)',
          data: batchTrendData.map((row) => row.employment_rate),
          borderColor: '#5B9A8B',
          backgroundColor: 'rgba(91, 154, 139, 0.16)',
          pointBackgroundColor: '#5B9A8B',
          pointBorderColor: '#ffffff',
          pointBorderWidth: 2,
          pointRadius: 4,
          fill: true,
          tension: 0.35
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: { beginAtZero: true, max: 100, grid: { color: chartGrid } },
          x: { grid: { display: false } }
        },
        plugins: { legend: { display: false } }
      }
    });
  }

  const snapshotTrendData = <?= json_encode($snapshotTrends) ?>;
  const snapshotTrendEl = document.getElementById('snapshotTrendChart');
  if (snapshotTrendEl && snapshotTrendData.length) {
    new Chart(snapshotTrendEl, {
      type: 'line',
      data: {
        labels: snapshotTrendData.map((row) => row.label),
        datasets: [
          {
            label: 'Employment rate',
            data: snapshotTrendData.map((row) => row.employment_rate),
            borderColor: '#5B9A8B',
            pointBackgroundColor: '#5B9A8B',
            pointBorderColor: '#ffffff',
            pointBorderWidth: 2,
            tension: 0.35
          },
          {
            label: 'Verification rate',
            data: snapshotTrendData.map((row) => row.verification_rate),
            borderColor: '#6B9BB8',
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
          y: { beginAtZero: true, max: 100, grid: { color: chartGrid } },
          x: { grid: { display: false } }
        },
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 14, usePointStyle: true } } }
      }
    });
  }
</script>
<?php endif; ?>
