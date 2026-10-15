@php
    $embed = $embed ?? false;
    $schoolName = config('school.name');
@endphp
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'Graduate Tracking System')</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
</head>
<body class="@yield('body-class', 'app-body'){{ $embed ? ' auth-embed-body' : '' }}">
  @section('body')
  @unless ($embed)
  <header class="app-topbar app-topbar-compact">
    <div class="container app-topbar-inner landing-container">
      <div class="app-topbar-row">
        <a class="text-decoration-none app-topbar-brand" href="{{ route('dashboard') }}">
          <div class="d-flex align-items-center gap-3">
            @if (config('school.logo'))
              <img class="app-brand-img" src="{{ asset(config('school.logo')) }}" alt="{{ $schoolName }} logo">
            @endif
            <div class="lh-sm">
              <div class="fw-bold app-topbar-brand-title">{{ $schoolName }}</div>
              <div class="small app-topbar-brand-sub">Graduate Tracking System</div>
            </div>
          </div>
        </a>

        <div class="app-topbar-actions">
          <a class="app-topbar-link" href="{{ route('home') }}">Back to Homepage</a>
          @auth
            <a class="btn btn-sm btn-dark app-btn app-topbar-dash-btn" href="{{ route('dashboard') }}"><i class="bi bi-grid-1x2 me-1"></i>Dashboard</a>
            <span class="app-topbar-meta">Role: {{ auth()->user()->Role }}</span>
            <form method="post" action="{{ route('logout') }}" class="d-inline">
              @csrf
              <button class="btn btn-sm btn-outline-secondary app-btn" type="submit">Logout</button>
            </form>
          @else
            <a class="btn btn-sm btn-outline-secondary app-btn" href="{{ route('login') }}">Login</a>
            <a class="btn btn-sm btn-primary app-btn" href="{{ route('register') }}">Register</a>
          @endauth
        </div>
      </div>
    </div>
  </header>
  @endunless

  <main class="{{ $embed ? 'container-fluid auth-embed-main py-3' : 'container-fluid app-main py-4' }}">
    @yield('content')
  </main>
  @show
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  @stack('scripts')
</body>
</html>
