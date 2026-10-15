@extends('layouts.dashboard')

@section('title', 'Manage Student Accounts')

@section('dashboard-content')
  <div class="app-card p-3 mb-3">
    <div class="d-flex align-items-center justify-content-between gap-2">
      <h1 class="h4 mb-0">Manage Student Accounts</h1>
      <a class="btn btn-sm btn-outline-secondary" href="{{ route('dashboard') }}">Back</a>
    </div>
    <div class="text-muted small mt-1">
      Instructors can approve and update <strong>Student</strong> accounts. (Admins still have full user management.)
    </div>
  </div>

  @include('partials.flash')

  <div class="app-card p-3 mb-3">
    <h2 class="h6 mb-3">Pending Student Registrations</h2>
    @if ($pending->isEmpty())
      <div class="alert alert-secondary mb-0">No pending registrations.</div>
    @else
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
            @foreach ($pending as $u)
              <tr>
                <td>{{ $u->User_ID }}</td>
                <td>{{ $u->First_Name }} {{ $u->Last_Name }}</td>
                <td>{{ $u->Username }}</td>
                <td>{{ $u->Email }}</td>
                <td class="text-muted small">{{ $u->Created_At?->format('Y-m-d H:i:s') }}</td>
                <td class="text-end">
                  <form method="post" action="{{ route('instructor.users.approve', $u) }}">
                    @csrf
                    @method('PATCH')
                    <button class="btn btn-sm btn-success" type="submit">Approve</button>
                  </form>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>

  <div class="app-card p-3">
    <h2 class="h6 mb-3">Students</h2>
    @if ($students->isEmpty())
      <div class="alert alert-secondary mb-0">No student accounts.</div>
    @else
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
            @foreach ($students as $u)
              <tr>
                <td>{{ $u->User_ID }}</td>
                <td>{{ $u->First_Name }} {{ $u->Last_Name }}</td>
                <td>{{ $u->Username }}</td>
                <td>{{ $u->Email }}</td>
                <td>{{ $u->Status }}</td>
                <td class="text-end">
                  <form class="d-flex justify-content-end gap-1" method="post" action="{{ route('instructor.users.status', $u) }}">
                    @csrf
                    @method('PATCH')
                    <select class="form-select form-select-sm" name="status">
                      @foreach ($statuses as $s)
                        <option @selected($u->Status === $s)>{{ $s }}</option>
                      @endforeach
                    </select>
                    <button class="btn btn-sm btn-outline-primary" type="submit">Save</button>
                  </form>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>
@endsection
