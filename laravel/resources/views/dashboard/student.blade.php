@extends('layouts.dashboard')

@section('title', 'Dashboard')

@section('dashboard-content')
  @use('App\Support\DisplayDate')

  <section class="student-dash">
    <div class="student-dash-hero">
      <div>
        <h1 class="student-dash-greeting">Welcome back, {{ $firstName }}!</h1>
        <p class="student-dash-sub">Keep your graduate information up to date.</p>
      </div>
      <div class="student-dash-hero-art" aria-hidden="true">
        <div class="student-dash-hero-blob">
          <i class="bi bi-mortarboard-fill"></i>
          <i class="bi bi-laptop"></i>
          <i class="bi bi-file-earmark-check"></i>
        </div>
      </div>
    </div>

    <div class="student-dash-top">
      <div class="app-card student-status-card">
        <h2 class="student-card-title">My Graduate Status</h2>
        <div class="student-status-grid">
          <div class="student-status-item">
            <div class="student-status-icon is-purple"><i class="bi bi-person-fill"></i></div>
            <div class="student-status-label">Profile Completion</div>
            <div class="student-status-value is-purple">{{ (int) $completion }}%</div>
            <div class="student-progress">
              <span style="width: {{ max(0, min(100, (int) $completion)) }}%;"></span>
            </div>
            <a class="student-status-link" href="{{ route('student.profile') }}">Complete your profile →</a>
          </div>

          <div class="student-status-item">
            <div class="student-status-icon is-orange"><i class="bi bi-shield-fill-check"></i></div>
            <div class="student-status-label">Verification Status</div>
            @if (! $profile)
              <div class="student-status-badge is-muted">No profile yet</div>
              <div class="student-status-note">Submit your graduate profile to start verification.</div>
            @elseif ($profile->Is_Verified)
              <div class="student-status-badge is-success">Verified</div>
              <div class="student-status-note">Your profile has been reviewed and verified.</div>
            @else
              <div class="student-status-badge is-pending">Pending verification</div>
              <div class="student-status-note">Your profile is being reviewed by the school.</div>
            @endif
          </div>
        </div>
      </div>

      <aside class="app-card student-profile-card">
        <div class="student-profile-head">
          <h2 class="student-card-title mb-0">My Profile</h2>
          <a class="btn btn-sm btn-outline-primary" href="{{ route('student.profile') }}" aria-label="Edit profile"><i class="bi bi-pencil"></i></a>
        </div>
        <div class="student-profile-identity">
          <div class="student-profile-avatar">
            @if ($photoUrl)
              <img src="{{ $photoUrl }}" alt="Profile photo">
            @else
              <i class="bi bi-person-fill"></i>
            @endif
          </div>
          <div>
            <div class="student-profile-name">{{ $profileName }}</div>
            <div class="student-profile-course">{{ $profileCourse !== '' ? $profileCourse : 'Course not set' }}</div>
          </div>
        </div>
        <ul class="student-profile-meta">
          <li>
            <i class="bi bi-calendar3"></i>
            <div>
              <span>Batch Year</span>
              <strong>{{ $profileBatch }}</strong>
            </div>
          </li>
          <li>
            <i class="bi bi-envelope"></i>
            <div>
              <span>Email</span>
              <strong>{{ $profileEmail !== '' ? $profileEmail : '—' }}</strong>
            </div>
          </li>
          <li>
            <i class="bi bi-telephone"></i>
            <div>
              <span>Phone</span>
              <strong>{{ $profilePhone !== '' ? $profilePhone : '—' }}</strong>
            </div>
          </li>
          <li>
            <i class="bi bi-clock-history"></i>
            <div>
              <span>Last Updated</span>
              <strong>{{ DisplayDate::short($profileUpdated) }}</strong>
            </div>
          </li>
        </ul>
      </aside>
    </div>

    <div class="student-info-banner">
      <i class="bi bi-info-circle-fill"></i>
      <p>Please keep your information updated so the school can maintain accurate records and provide better support for graduates.</p>
    </div>

    <div class="student-dash-bottom">
      <div class="app-card student-list-card">
        <div class="student-list-head">
          <h2 class="student-card-title mb-0">Announcements</h2>
          <a href="{{ route('announcements') }}">View all</a>
        </div>
        @if ($announcements->isEmpty())
          <div class="text-muted small py-2">No announcements yet.</div>
        @else
          <ul class="student-list">
            @foreach ($announcements as $ann)
              <li>
                <span class="student-list-icon is-blue"><i class="bi bi-megaphone-fill"></i></span>
                <div class="student-list-body">
                  <strong>{{ $ann->Title }}</strong>
                </div>
                <time>{{ DisplayDate::short((string) $ann->getRawOriginal('Date_Posted')) }}</time>
              </li>
            @endforeach
          </ul>
        @endif
        <a class="student-list-footer" href="{{ route('announcements') }}">See all announcements →</a>
      </div>

      <div class="app-card student-list-card">
        <div class="student-list-head">
          <h2 class="student-card-title mb-0">Messages</h2>
          <a href="{{ route('messages') }}">Open inbox</a>
        </div>
        <div class="text-muted small py-2">Message instructors or admins inside the system for support and follow-ups.</div>
        <a class="student-list-footer" href="{{ route('messages', ['compose' => 1]) }}">Send a message →</a>
      </div>

      <div class="app-card student-list-card">
        <div class="student-list-head">
          <h2 class="student-card-title mb-0">Recent Activity</h2>
        </div>
        <ul class="student-list">
          @foreach ($activity as $act)
            <li>
              <span class="student-list-icon is-{{ $act['tone'] }}"><i class="bi {{ $act['icon'] }}"></i></span>
              <div class="student-list-body">
                <strong>{{ $act['text'] }}</strong>
              </div>
              <time>{{ DisplayDate::short($act['date']) }}</time>
            </li>
          @endforeach
        </ul>
        <a class="student-list-footer" href="{{ route('student.profile') }}">View profile details →</a>
      </div>
    </div>
  </section>
@endsection
