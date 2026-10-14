@extends('layouts.dashboard')

@section('title', 'Manage Users')

@php
    $me = auth()->user();
    $alertCount = count($securityAlerts);
    // Old input belongs to whichever form failed validation, so only that form re-fills from it.
    $createFailed = $errors->hasBag('create');
    $editFailedId = $errors->hasBag('edit') ? (int) old('user_id') : null;
    $createRole = $createFailed ? (string) old('role', '') : '';
@endphp

@section('dashboard-content')
  <div class="app-card p-3 p-md-4 mb-3">
    <div class="d-flex align-items-center justify-content-between gap-2">
      <div>
        <h1 class="h4 mb-1">Verify / Manage Users</h1>
        <div class="text-muted small">Approve accounts, change roles/status, deactivate, or permanently delete to free an email for registration.</div>
      </div>
      <div class="d-flex gap-2">
        <a class="btn btn-sm btn-outline-secondary app-btn" href="{{ route('dashboard') }}">Back</a>
      </div>
    </div>
  </div>

  @include('partials.flash')
  @if ($schemaWarning)<div class="alert alert-warning">{{ $schemaWarning }}</div>@endif

  <div class="app-card p-3 p-md-4 mb-3 border" id="security-alerts"
       style="border-color: {{ $alertCount > 0 ? '#dc3545' : '#dee2e6' }} !important;">
    <div class="d-flex align-items-center justify-content-between mb-3 gap-2">
      <div class="d-flex align-items-center gap-2">
        <i class="bi bi-shield-exclamation text-danger fs-5"></i>
        <h2 class="h6 mb-0">Security Alerts — Failed Login Attempts (Last 30 min)</h2>
      </div>
      @if ($alertCount > 0)
        <span class="badge text-bg-danger">{{ $alertCount }} alert(s)</span>
      @else
        <span class="badge text-bg-success">All clear</span>
      @endif
    </div>
    @if (! $securityAlerts)
      <div class="alert alert-success mb-0 d-flex align-items-center gap-2">
        <i class="bi bi-check-circle-fill"></i>
        No suspicious login activity detected in the last 30 minutes.
      </div>
    @else
      <div class="alert alert-danger mb-3 d-flex align-items-start gap-2">
        <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
        <div>
          <strong>Warning:</strong> The following accounts received 5 or more failed login
          attempts in the last 30 minutes. This may indicate a brute-force or credential-stuffing
          attack. Consider reviewing or temporarily locking these accounts.
          Use <strong>Clear account alert</strong> to remove alerts for one login ID (all IPs),
          or <strong>Dismiss IP</strong> for a single row.
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
            @foreach ($securityAlerts as $alert)
              @php $target = $alertAccounts[$alert->Username] ?? null; @endphp
              <tr class="table-danger">
                <td class="fw-semibold">{{ $alert->Username }}</td>
                <td><code>{{ $alert->IP_Address }}</code></td>
                <td><span class="badge text-bg-danger fs-6">{{ (int) $alert->Attempt_Count }}</span></td>
                <td class="text-muted small">{{ $alert->Last_Attempt }}</td>
                <td>
                  <div class="d-flex flex-wrap gap-1">
                    @if ($target && $target->Status === 'Active')
                      <form method="post" action="{{ route('admin.users.access', $target) }}" class="d-inline">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="role" value="{{ $target->Role }}">
                        <input type="hidden" name="status" value="Inactive">
                        <button class="btn btn-sm btn-danger" type="submit"
                                onclick="return confirm('Temporarily lock this account?');">
                          <i class="bi bi-lock-fill me-1"></i>Lock Account
                        </button>
                      </form>
                    @elseif ($target)
                      <span class="badge text-bg-secondary align-self-center">Already {{ $target->Status }}</span>
                    @else
                      <span class="text-muted small align-self-center">Unknown user</span>
                    @endif
                    <form method="post" action="{{ route('admin.security-alerts.clear-account') }}" class="d-inline">
                      @csrf
                      @method('DELETE')
                      <input type="hidden" name="alert_username" value="{{ $alert->Username }}">
                      <button class="btn btn-sm btn-outline-primary" type="submit"
                              onclick="return confirm('Remove all security alerts for this account (every IP)?');"
                              title="Clear every alert row for this Student/Employee ID">
                        Clear account alert
                      </button>
                    </form>
                    <form method="post" action="{{ route('admin.security-alerts.dismiss-ip') }}" class="d-inline">
                      @csrf
                      @method('DELETE')
                      <input type="hidden" name="alert_username" value="{{ $alert->Username }}">
                      <input type="hidden" name="alert_ip" value="{{ $alert->IP_Address }}">
                      <button class="btn btn-sm btn-outline-secondary" type="submit"
                              title="Clear only this username + IP row">
                        Dismiss IP
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>

  <div class="app-card p-3 p-md-4 mb-3">
    <div class="d-flex align-items-center justify-content-between mb-3 gap-2">
      <h2 class="h6 mb-0">Pending Registrations</h2>
      <span class="badge text-bg-warning">{{ $pendingUsers->count() }} pending</span>
    </div>
    @if ($pendingUsers->isEmpty())
      <div class="alert alert-secondary mb-0">No pending registrations.</div>
    @else
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
            @foreach ($pendingUsers as $u)
              @php $pendingStatus = trim((string) $u->Status); @endphp
              <tr>
                <td>{{ $u->User_ID }}</td>
                <td>{{ $u->First_Name }} {{ $u->Last_Name }}</td>
                <td>{{ $u->Username }}</td>
                <td>{{ $u->Email }}</td>
                <td>{{ $u->Role }}</td>
                <td>
                  @if ($pendingStatus === 'Pending' || $pendingStatus === '')
                    <span class="badge text-bg-warning">{{ $pendingStatus === '' ? 'Pending (legacy/blank)' : 'Pending' }}</span>
                  @else
                    <span class="badge text-bg-secondary">{{ $pendingStatus }}</span>
                  @endif
                </td>
                <td class="text-muted small">{{ $u->Created_At?->format('Y-m-d H:i:s') }}</td>
                <td>
                  <form method="post" action="{{ route('admin.users.approve', $u) }}">
                    @csrf
                    @method('PATCH')
                    <button class="btn btn-sm btn-success admin-action-btn" type="submit">
                      <i class="bi bi-check-circle me-1"></i>Approve
                    </button>
                  </form>

                  <form method="post" action="{{ route('admin.users.decline', $u) }}" class="mt-1" onsubmit="return confirm('Decline and archive this registration? The account will be kept on record.');">
                    @csrf
                    @method('PATCH')
                    <button class="btn btn-sm btn-outline-secondary admin-action-btn" type="submit">
                      <i class="bi bi-archive me-1"></i>Decline
                    </button>
                  </form>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>

  <div class="app-card p-3 p-md-4 mb-3">
    <h2 class="h6 mb-3">Add User</h2>
    <form class="row g-2 align-items-end" method="post" action="{{ route('admin.users.store') }}" id="adminCreateUserForm">
      @csrf
      <div class="col-12 col-md-2">
        <input class="form-control" name="first_name" placeholder="First name" required value="{{ $createFailed ? old('first_name') : '' }}">
      </div>
      <div class="col-12 col-md-2">
        <input class="form-control" name="last_name" placeholder="Last name" required value="{{ $createFailed ? old('last_name') : '' }}">
      </div>
      <div class="col-12 col-md-2">
        <input class="form-control" name="username" id="createUserId" value="{{ $createFailed ? old('username') : '' }}" required>
      </div>
      <div class="col-12 col-md-2">
        <select class="form-select" name="role" id="createUserRole" required>
          <option value="" @selected($createRole === '')>Select Role</option>
          @foreach ($roles as $r)
            <option value="{{ $r }}" @selected($createRole === $r)>{{ $r }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-12 col-md-3">
        <input class="form-control" name="email" type="email" placeholder="Email" required value="{{ $createFailed ? old('email') : '' }}">
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
        @php $createStatus = $createFailed ? (string) old('status', 'Active') : 'Active'; @endphp
        <select class="form-select" name="status" required>
          @foreach ($statuses as $s)
            <option @selected($createStatus === $s)>{{ $s }}</option>
          @endforeach
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
      <span class="text-muted small">{{ $users->count() }} shown</span>
    </div>
    <form class="row g-2 mb-3" method="get" action="{{ route('admin.users') }}">
      <div class="col-12 col-md-8">
        <input class="form-control form-control-sm" name="q" placeholder="Search name, Student ID / Employee ID, email, role, or status" value="{{ $search }}">
      </div>
      <div class="col-12 col-md-2 d-grid">
        <button class="btn btn-sm btn-primary" type="submit">Search</button>
      </div>
      @if ($search !== '')
        <div class="col-12 col-md-2 d-grid">
          <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.users') }}">Clear</a>
        </div>
      @endif
    </form>
    @if ($users->isEmpty())
      <div class="alert alert-secondary mb-0">No users found.</div>
    @else
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
            @foreach ($users as $u)
              @php
                  $isSelf = $u->is($me);
                  $editFailed = $editFailedId === $u->User_ID;
              @endphp
              <tr>
                <td>{{ $u->User_ID }}</td>
                <td>{{ $u->First_Name }} {{ $u->Last_Name }}</td>
                <td>{{ $u->Username }}</td>
                <td>{{ $u->Email }}</td>
                <td>{{ $u->Role }}</td>
                <td>{{ $u->Status }}</td>
                <td class="text-muted small">{{ $u->Created_At?->format('Y-m-d H:i:s') }}</td>
                <td>
                  <details class="mb-1" @if ($editFailed) open @endif>
                    <summary class="small text-primary" style="cursor:pointer;">Edit details</summary>
                    <form class="row g-1 mt-2" method="post" action="{{ route('admin.users.update', $u) }}">
                      @csrf
                      @method('PATCH')
                      <input type="hidden" name="user_id" value="{{ $u->User_ID }}">
                      <div class="col-6"><input class="form-control form-control-sm" name="first_name" value="{{ $editFailed ? old('first_name') : $u->First_Name }}" required></div>
                      <div class="col-6"><input class="form-control form-control-sm" name="last_name" value="{{ $editFailed ? old('last_name') : $u->Last_Name }}" required></div>
                      <div class="col-12"><input class="form-control form-control-sm" name="email" type="email" value="{{ $editFailed ? old('email') : $u->Email }}" required></div>
                      <div class="col-12"><input class="form-control form-control-sm" name="new_password" type="password" placeholder="New password (optional)" autocomplete="new-password"></div>
                      <div class="col-12"><button class="btn btn-sm btn-outline-success admin-action-btn" type="submit"><i class="bi bi-pencil me-1"></i>Save details</button></div>
                    </form>
                  </details>
                  <form class="d-flex flex-wrap gap-1" method="post" action="{{ route('admin.users.access', $u) }}">
                    @csrf
                    @method('PATCH')
                    <select class="form-select form-select-sm" name="role">
                      @foreach ($roles as $r)
                        <option @selected($u->Role === $r)>{{ $r }}</option>
                      @endforeach
                    </select>
                    <select class="form-select form-select-sm" name="status">
                      @foreach ($statuses as $s)
                        <option @selected($u->Status === $s)>{{ $s }}</option>
                      @endforeach
                    </select>
                    <button class="btn btn-sm btn-outline-primary admin-action-btn" type="submit">
                      <i class="bi bi-save me-1"></i>Save
                    </button>
                  </form>

                  @if (! $isSelf && $u->Status === 'Active')
                    <form class="mt-1 d-inline" method="post" action="{{ route('admin.users.deactivate', $u) }}" onsubmit="return confirm('Set this account to Inactive? The user cannot log in but records are kept.');">
                      @csrf
                      @method('PATCH')
                      <input type="hidden" name="status" value="Inactive">
                      <button class="btn btn-sm btn-outline-warning admin-action-btn" type="submit">
                        <i class="bi bi-pause-circle me-1"></i>Deactivate
                      </button>
                    </form>
                  @endif

                  @unless ($isSelf)
                    <form class="mt-1 d-inline" method="post" action="{{ route('admin.users.destroy', $u) }}" onsubmit="return confirm('Permanently delete this user? Email and Student/Employee ID can be registered again. This cannot be undone.');">
                      @csrf
                      @method('DELETE')
                      <button class="btn btn-sm btn-outline-danger admin-action-btn" type="submit">
                        <i class="bi bi-trash me-1"></i>Delete permanently
                      </button>
                    </form>
                  @endunless
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <div class="text-muted small mb-0">Showing up to 200 users.</div>
    @endif
  </div>
@endsection

@push('scripts')
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
      } else if (role === 'Instructor') {
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
@endpush
