@extends('layouts.dashboard')

@section('title', 'Analytics & Decision Support')

@section('dashboard-content-class', 'analytics-page')

@section('dashboard-content')
  @include('partials.flash', ['alertClass' => 'analytics-alert'])

  @if ($analyticsError)
    <div class="alert alert-warning analytics-alert">{{ $analyticsError }}</div>
  @endif

  <header class="analytics-page-header">
    <div>
      <p class="analytics-eyebrow">Decision support</p>
      <h1 class="analytics-title">Analytics</h1>
      <p class="analytics-subtitle">Active accounts only · batch filters apply to every KPI and chart</p>
    </div>
    @can('create', \App\Models\Report::class)
      <div class="analytics-header-actions">
        <form method="post" action="{{ route('analytics.snapshot') }}" class="m-0">
          @csrf
          <input type="hidden" name="batch_from" value="{{ $batchFrom }}">
          <input type="hidden" name="batch_to" value="{{ $batchTo }}">
          <button class="btn btn-outline-secondary" type="submit">Save snapshot</button>
        </form>
        <a class="btn btn-dark" href="{{ route('reports') }}">Generate reports</a>
      </div>
    @else
      <div class="analytics-header-actions">
        <a class="btn btn-dark" href="{{ route('reports') }}">View reports</a>
      </div>
    @endcan
  </header>

  <form class="analytics-filter-bar" method="get" action="{{ route('analytics') }}">
    <div class="analytics-filter-fields">
      <div class="analytics-filter-field">
        <label for="batch_from">Batch from</label>
        <input id="batch_from" name="batch_from" type="number" placeholder="2018" value="{{ $batchFrom ?: '' }}">
      </div>
      <div class="analytics-filter-field">
        <label for="batch_to">Batch to</label>
        <input id="batch_to" name="batch_to" type="number" placeholder="2024" value="{{ $batchTo ?: '' }}">
      </div>
    </div>
    <button class="btn btn-dark" type="submit">Apply</button>
    @if ($batchFrom > 0 || $batchTo > 0)
      <a class="btn btn-outline-secondary" href="{{ route('analytics') }}">Clear</a>
    @endif
  </form>

  @if ($analytics)
  <section class="analytics-stack">
    <div class="analytics-kpi-grid">
      <article class="analytics-kpi">
        <span class="analytics-kpi-label">Graduate profiles</span>
        <strong class="analytics-kpi-value">{{ (int) $analytics['total_profiles'] }}</strong>
      </article>
      <article class="analytics-kpi">
        <span class="analytics-kpi-label">Employment rate</span>
        <strong class="analytics-kpi-value">{{ (float) $analytics['employment_rate'] }}%</strong>
      </article>
      <article class="analytics-kpi">
        <span class="analytics-kpi-label">Verified profiles</span>
        <strong class="analytics-kpi-value">{{ (float) $analytics['verification_rate'] }}%</strong>
      </article>
      <article class="analytics-kpi">
        <span class="analytics-kpi-label">Profile completion</span>
        <strong class="analytics-kpi-value">{{ (float) $analytics['profile_completion_rate'] }}%</strong>
        <span class="analytics-kpi-hint">
          {{ (int) ($analytics['complete_profiles'] ?? 0) }} complete
          · {{ (int) ($analytics['incomplete_profiles'] ?? 0) }} incomplete
          @if ((int) ($analytics['students_without_profile'] ?? 0) > 0)
            · {{ (int) $analytics['students_without_profile'] }} no profile
          @endif
        </span>
      </article>
    </div>

    <div class="analytics-panel-grid analytics-panel-grid-2">
      <article class="analytics-panel">
        <h2 class="analytics-panel-title">Employment rate by batch</h2>
        @if (empty($analytics['batch_trends']))
          <p class="analytics-empty">No batch trend data for the selected filters.</p>
        @else
          <div class="analytics-chart">
            <canvas id="batchTrendChart"></canvas>
          </div>
        @endif
      </article>
      <article class="analytics-panel">
        <h2 class="analytics-panel-title">Snapshot trends</h2>
        @if (! $snapshotTrends)
          <p class="analytics-empty">Save snapshots to track employment and verification over time.</p>
        @else
          <div class="analytics-chart">
            <canvas id="snapshotTrendChart"></canvas>
          </div>
        @endif
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
        @if (empty($analytics['course_breakdown']))
          <p class="analytics-empty">No course data available.</p>
        @else
          <div class="table-responsive">
            <table class="analytics-table">
              <thead>
                <tr><th>Course</th><th>Profiles</th><th>Employed</th><th>Rate</th></tr>
              </thead>
              <tbody>
                @foreach ($analytics['course_breakdown'] as $course)
                  <tr>
                    <td>{{ (string) $course['course'] }}</td>
                    <td>{{ (int) $course['total'] }}</td>
                    <td>{{ (int) $course['employed'] }}</td>
                    <td>{{ (float) $course['rate'] }}%</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </article>
    </div>

    <article class="analytics-panel">
      <div class="analytics-panel-head">
        <h2 class="analytics-panel-title">Batch year breakdown</h2>
        <span class="analytics-panel-meta">Stacked employment counts</span>
      </div>
      @if (! $analytics['batch_years'])
        <p class="analytics-empty">No batch data for the selected filter.</p>
      @else
        <div class="analytics-panel-grid analytics-panel-grid-dist analytics-panel-nested">
          <div class="analytics-chart analytics-chart-tall">
            <canvas id="batchChart"></canvas>
          </div>
          <div class="table-responsive">
            <table class="analytics-table">
              <thead>
                <tr>
                  <th>Batch</th>
                  @foreach ($statusOrder as $s)
                    <th>{{ $s }}</th>
                  @endforeach
                  <th>Rate</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($analytics['batch_years'] as $year)
                  @php
                    $rowTotal = array_sum($analytics['batch_matrix'][$year]);
                    $rowEmployed = ($analytics['batch_matrix'][$year]['Employed'] ?? 0) + ($analytics['batch_matrix'][$year]['Self-Employed'] ?? 0);
                    $rowRate = $rowTotal > 0 ? round(($rowEmployed / $rowTotal) * 100, 1) : 0;
                  @endphp
                  <tr>
                    <td class="fw-semibold">{{ (int) $year }}</td>
                    @foreach ($statusOrder as $s)
                      <td>{{ (int) ($analytics['batch_matrix'][$year][$s] ?? 0) }}</td>
                    @endforeach
                    <td>{{ $rowRate }}%</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      @endif
    </article>

    @if ($unverified)
    <article class="analytics-panel">
      <div class="analytics-panel-head">
        <h2 class="analytics-panel-title">Awaiting verification</h2>
        <a class="analytics-panel-link" href="{{ route('graduates') }}">Open graduate records</a>
      </div>
      <div class="table-responsive">
        <table class="analytics-table">
          <thead>
            <tr><th>Name</th><th>Course</th><th>Batch</th><th>Status</th><th>Updated</th></tr>
          </thead>
          <tbody>
            @foreach ($unverified as $uv)
              <tr>
                <td>{{ (string) $uv->Full_Name }}</td>
                <td>{{ (string) ($uv->Course ?? '') }}</td>
                <td>{{ (int) $uv->Batch_Year }}</td>
                <td>{{ (string) $uv->Employment_Status }}</td>
                <td class="text-muted">{{ (string) $uv->Last_Updated }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </article>
    @endif
  </section>
  @endif
@endsection

@push('scripts')
@if ($analytics)
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
  const labels = @json(array_values($statusOrder));
  const data = @json(array_values($countsByStatus));
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

  const batchYears = @json($analytics['batch_years']);
  const batchDatasets = @json($analytics['batch_datasets']);
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

  const batchTrendData = @json($analytics['batch_trends']);
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
          x: {
            grid: { display: false },
            ticks: {
              maxRotation: window.matchMedia('(max-width: 768px)').matches ? 45 : 0,
              font: { size: window.matchMedia('(max-width: 768px)').matches ? 10 : 12 }
            }
          }
        },
        plugins: { legend: { display: false } }
      }
    });
  }

  const snapshotTrendData = @json($snapshotTrends);
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
@endif
@endpush
