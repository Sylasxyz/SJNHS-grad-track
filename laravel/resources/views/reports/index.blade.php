@extends('layouts.dashboard')

@section('title', 'Reports')

@section('dashboard-content')
  @php
    $canManageReports = auth()->user()->can('create', \App\Models\Report::class);
  @endphp

  <div class="app-card p-3 p-md-4 mb-3">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
      <div>
        <h1 class="h4 mb-1">Generate Reports</h1>
        <div class="text-muted small">
          @if ($canManageReports)
            Create analytics reports, export CSV, and review saved snapshots.
          @else
            View and export saved analytics reports (view only).
          @endif
        </div>
      </div>
      <a class="btn btn-sm btn-outline-secondary" href="{{ route('analytics') }}">View analytics</a>
    </div>
  </div>

  @include('partials.flash')

  <div class="row g-3 mb-3">
    @if ($canManageReports)
    <div class="col-12 col-lg-5">
      <div class="app-card p-3 p-md-4 h-100">
        <h2 class="h6 mb-3">Generate new report</h2>
        <form method="post" action="{{ route('reports.store') }}" class="vstack gap-2" id="generateReportForm">
          @csrf
          <div>
            <label class="form-label small text-muted" for="report_name">Report name</label>
            <input class="form-control form-control-sm" id="report_name" name="report_name" placeholder="Analytics Report" maxlength="130" required value="{{ old('report_name') }}">
          </div>
          <div class="row g-2">
            <div class="col-6">
              <label class="form-label small text-muted" for="batch_from">Batch from</label>
              <input class="form-control form-control-sm" id="batch_from" name="batch_from" type="number" placeholder="e.g. 2018" value="{{ $batchFrom ?: '' }}">
            </div>
            <div class="col-6">
              <label class="form-label small text-muted" for="batch_to">Batch to</label>
              <input class="form-control form-control-sm" id="batch_to" name="batch_to" type="number" placeholder="e.g. 2024" value="{{ $batchTo ?: '' }}">
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
    @else
    <div class="col-12">
    @endif
      <div class="app-card p-3 p-md-4 h-100 dashboard-trend-card">
        <h2 class="h6 mb-2">Report snapshot trends</h2>
        @if (! $snapshotTrends)
          <div class="text-muted small">Generate reports to build trend history over time.</div>
        @else
          <div class="dashboard-chart-wrap">
            <canvas id="reportsTrendChart"></canvas>
          </div>
        @endif
      </div>
    </div>
  </div>

  @if ($viewReport)
    @php
      $summary = $viewReport->summary();
      $graduates = is_array($summary['graduates'] ?? null) ? $summary['graduates'] : [];
    @endphp
    <div class="app-card p-3 p-md-4 mb-3">
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
          <h2 class="h6 mb-1">{{ $viewReport->Report_Name }}</h2>
          <div class="text-muted small">
            Generated {{ $viewReport->generatedDate() }}
            · Status: {{ (string) ($summary['filters']['employment_status'] ?? 'All Status') }}
          </div>
        </div>
        <div class="d-flex gap-2">
          <a class="btn btn-sm btn-outline-primary" href="{{ route('reports.export', $viewReport) }}">
            <i class="bi bi-download me-1"></i>Export CSV
          </a>
          <a class="btn btn-sm btn-outline-secondary" href="{{ route('reports') }}">Close</a>
        </div>
      </div>
      <div class="row g-3 mb-3">
        <div class="col-6 col-md-3"><div class="dashboard-kpi-card"><div class="dashboard-kpi-label">Profiles</div><div class="dashboard-kpi-value">{{ (int) ($summary['total_profiles'] ?? 0) }}</div></div></div>
        <div class="col-6 col-md-3"><div class="dashboard-kpi-card"><div class="dashboard-kpi-label">Employment rate</div><div class="dashboard-kpi-value">{{ (float) ($summary['employment_rate'] ?? 0) }}%</div></div></div>
        <div class="col-6 col-md-3"><div class="dashboard-kpi-card"><div class="dashboard-kpi-label">Verified</div><div class="dashboard-kpi-value">{{ (float) ($summary['verification_rate'] ?? 0) }}%</div></div></div>
        <div class="col-6 col-md-3"><div class="dashboard-kpi-card"><div class="dashboard-kpi-label">Completion</div><div class="dashboard-kpi-value">{{ (float) ($summary['profile_completion_rate'] ?? 0) }}%</div></div></div>
      </div>
      @if (! empty($summary['employment_counts']) && is_array($summary['employment_counts']))
        <h3 class="h6">Employment counts</h3>
        <div class="d-flex flex-wrap gap-2 mb-3">
          @foreach ($summary['employment_counts'] as $statusLabel => $count)
            <span class="badge text-bg-light border">{{ (string) $statusLabel }}: {{ (int) $count }}</span>
          @endforeach
        </div>
      @endif
      @if (! empty($summary['decision_insights']))
        <h3 class="h6">Insights</h3>
        <ul class="dashboard-insight-list">
          @foreach ($summary['decision_insights'] as $insight)
            <li>{{ (string) $insight }}</li>
          @endforeach
        </ul>
      @endif
      @if ($graduates)
        <h3 class="h6 mt-3">Graduate roster ({{ count($graduates) }})</h3>
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
              @foreach ($graduates as $g)
                <tr>
                  <td>{{ (string) ($g['full_name'] ?? '') }}</td>
                  <td>{{ (string) ($g['student_id'] ?? '') }}</td>
                  <td>{{ (string) ($g['batch_year'] ?? '') }}</td>
                  <td>{{ (string) ($g['strand'] ?? '') }}</td>
                  <td>{{ (string) ($g['employment_status'] ?? '') }}</td>
                  <td>{{ (string) ($g['verified'] ?? '') }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </div>
  @endif

  <div class="app-card p-3 p-md-4">
    <div class="d-flex align-items-center justify-content-between mb-3 gap-2">
      <h2 class="h6 mb-0">Saved reports</h2>
      <span class="text-muted small">{{ count($reports) }} report(s)</span>
    </div>
    @if ($reports->isEmpty())
      <div class="alert alert-secondary mb-0">No reports generated yet.</div>
    @else
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
            @foreach ($reports as $report)
              @php($s = $report->summary())
              <tr>
                <td>{{ (int) $report->Report_ID }}</td>
                <td>{{ $report->Report_Name }}</td>
                <td class="text-muted small">{{ $report->generatedDate() }}</td>
                <td>{{ (float) ($s['employment_rate'] ?? 0) }}%</td>
                <td>{{ (float) ($s['verification_rate'] ?? 0) }}%</td>
                <td>
                  <div class="d-flex flex-wrap gap-1">
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('reports.show', $report) }}">View</a>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('reports.export', $report) }}">CSV</a>
                    @can('delete', $report)
                    <form method="post" action="{{ route('reports.destroy', $report) }}" class="d-inline" onsubmit="return confirm('Delete this report?');">
                      @csrf
                      @method('DELETE')
                      <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                    </form>
                    @endcan
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>
@endsection

@push('scripts')
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

@if ($snapshotTrends)
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
  const snapshotTrendData = @json($snapshotTrends);
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
@endif
@endpush
