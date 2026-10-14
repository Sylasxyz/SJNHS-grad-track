@extends('layouts.dashboard')

@section('title', 'Dashboard')

@section('dashboard-content')
  @if ($isAdmin && $securityAlertCount > 0)
  <div>
    <div class="alert alert-danger d-flex align-items-center justify-content-between gap-3 mb-0" role="alert">
      <div class="d-flex align-items-center gap-2">
        <i class="bi bi-shield-exclamation fs-4 flex-shrink-0"></i>
        <div>
          <strong>Security Alert:</strong>
          {{ $securityAlertCount }} account(s) had <strong>5+ failed login attempts</strong> in the last 30 minutes.
        </div>
      </div>
      <a href="{{ route('admin.users') }}#security-alerts" class="btn btn-danger btn-sm flex-shrink-0">
        <i class="bi bi-eye me-1"></i>View Alerts
      </a>
    </div>
  </div>
  @endif

  @if ($schemaErrors)
    <div>
      @foreach ($schemaErrors as $schemaErr)
        <div class="alert alert-warning">{{ $schemaErr }}</div>
      @endforeach
    </div>
  @endif

  <div class="dashboard-main-grid">
    <div class="app-card p-3 dashboard-center-top">
      <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
        <h2 class="h5 mb-0">Employment Overview</h2>
        @if ($analytics)
          <span class="badge text-bg-primary">{{ (float) $analytics['employment_rate'] }}% employed</span>
        @else
          <span class="badge text-bg-light border">Overview</span>
        @endif
      </div>
      <div class="dashboard-chart-wrap">
        <canvas id="instProgressChart"></canvas>
      </div>
    </div>

    <div class="app-card p-3 dashboard-right-top">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <h2 class="h5 mb-0">
          @if (! $isAdmin)
            Awaiting Verification
          @else
            Pending Users
          @endif
        </h2>
        @if (! $isAdmin)
          <a class="btn btn-sm btn-outline-primary" href="{{ route('graduates') }}"><i class="bi bi-search"></i></a>
        @else
          <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.users') }}"><i class="bi bi-plus"></i></a>
        @endif
      </div>
      @if (! $isAdmin)
        @if (! $unverified)
          <div class="text-muted small">All profiles are verified.</div>
        @else
          <div class="list-group dashboard-message-list">
            @foreach ($unverified as $uv)
              <a class="list-group-item list-group-item-action" href="{{ route('graduates.edit', (int) $uv->Profile_ID) }}">
                <div class="fw-semibold">{{ $uv->Full_Name }}</div>
                <div class="text-muted small">{{ (string) ($uv->Course ?? '') }} · Batch {{ (int) $uv->Batch_Year }}</div>
              </a>
            @endforeach
          </div>
        @endif
      @else
        @if (! $pendingUsers)
          <div class="text-muted small">No pending users.</div>
        @else
          <div class="list-group dashboard-message-list">
            @foreach ($pendingUsers as $pu)
              <a class="list-group-item list-group-item-action" href="{{ route('admin.users') }}">
                <div class="fw-semibold">{{ (string) $pu->Full_Name }}</div>
                <div class="text-muted small">{{ $pu->Role }}</div>
              </a>
            @endforeach
          </div>
        @endif
      @endif
    </div>

    <div class="app-card p-3 dashboard-bottom-left">
      <h3 class="h6 mb-2">Features</h3>
      <div class="row g-2">
        @if (! $isAdmin)
          <div class="col-12"><a class="dashboard-action-card" href="{{ route('messages') }}"><i class="bi bi-chat-dots"></i><span>Messages</span></a></div>
          <div class="col-12"><a class="dashboard-action-card" href="{{ route('graduates') }}"><i class="bi bi-search"></i><span>Search / Filter</span></a></div>
          <div class="col-12"><a class="dashboard-action-card" href="{{ route('announcements.create') }}"><i class="bi bi-pencil-square"></i><span>Post Announcement</span></a></div>
          <div class="col-12"><a class="dashboard-action-card" href="{{ route('instructor.users') }}"><i class="bi bi-person-gear"></i><span>Manage Students</span></a></div>
          <div class="col-12"><a class="dashboard-action-card" href="{{ route('analytics') }}"><i class="bi bi-graph-up-arrow"></i><span>Analytics</span></a></div>
          <div class="col-12"><a class="dashboard-action-card" href="{{ route('reports') }}"><i class="bi bi-file-earmark-bar-graph"></i><span>Generate Reports</span></a></div>
        @else
          <div class="col-12"><a class="dashboard-action-card" href="{{ route('messages') }}"><i class="bi bi-chat-dots"></i><span>Messages</span></a></div>
          <div class="col-12"><a class="dashboard-action-card" href="{{ route('admin.users') }}"><i class="bi bi-people"></i><span>Verify / Manage Users</span></a></div>
          <div class="col-12"><a class="dashboard-action-card" href="{{ route('analytics') }}"><i class="bi bi-graph-up-arrow"></i><span>Analytics & Reports</span></a></div>
          <div class="col-12"><a class="dashboard-action-card" href="{{ route('reports') }}"><i class="bi bi-file-earmark-bar-graph"></i><span>Generate Reports</span></a></div>
          <div class="col-12"><a class="dashboard-action-card" href="{{ route('graduates') }}"><i class="bi bi-search"></i><span>Graduate Records</span></a></div>
          <div class="col-12">
            <a class="dashboard-action-card" href="{{ route('admin.users') }}#security-alerts" style="position:relative;">
              <i class="bi bi-shield-exclamation"></i>
              <span>Security Alerts</span>
              @if ($securityAlertCount > 0)
                <span class="badge text-bg-danger ms-auto" style="font-size:0.7rem;">{{ $securityAlertCount }}</span>
              @endif
            </a>
          </div>
        @endif
      </div>
    </div>

    <div class="app-card p-3 dashboard-bottom-center">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <h3 class="h6 mb-0">Recent Graduate Records</h3>
        <a class="small" href="{{ route('graduates') }}">View all</a>
      </div>
      @if (! $directoryRows)
        <div class="text-muted small">No records yet.</div>
      @else
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead>
              <tr><th>Name</th><th>Course</th><th>Batch</th><th>Status</th><th>Verified</th></tr>
            </thead>
            <tbody>
              @foreach ($directoryRows as $dr)
                <tr>
                  <td>{{ $dr->Full_Name }}</td>
                  <td>{{ (string) ($dr->Course ?? '') }}</td>
                  <td>{{ (int) $dr->Batch_Year }}</td>
                  <td>{{ $dr->Employment_Status }}</td>
                  <td>@if (! empty($dr->Is_Verified))<span class="badge text-bg-success">Yes</span>@else<span class="badge text-bg-warning">No</span>@endif</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </div>
  </div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
  const labels = @json(array_values($statusOrder));
  const data = @json(array_values($countsByStatus));
  const barEl = document.getElementById('instProgressChart');
  if (barEl) {
    new Chart(barEl, {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: 'Graduates',
          data,
          backgroundColor: ['#5B9A8B', '#D4A574', '#6B9BB8', '#A3B07A'],
          borderRadius: 8,
          maxBarThickness: 48
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: { ticks: { maxRotation: 45, minRotation: 0, autoSkip: true, color: '#64748b' }, grid: { display: false } },
          y: { beginAtZero: true, ticks: { precision: 0, color: '#64748b' }, grid: { color: 'rgba(148, 163, 184, 0.25)' } }
        }
      }
    });
  }
</script>
@endpush
