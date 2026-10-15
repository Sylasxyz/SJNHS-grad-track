@extends('layouts.app')

@section('title', 'Welcome · '.config('school.name'))

@section('body-class', 'landing-page app-body')

@section('body')
@php
    $schoolName = config('school.name');
    $heroUrl = (string) config('school.hero_image');
    $locationLabel = (string) config('school.location_label');
@endphp

@include('partials.landing.nav', ['active' => 'home'])

<header class="landing-hero-header py-4 pt-lg-5 pb-lg-0">
  <div class="container landing-container">
    <div class="row landing-hero-row g-4">
      <div class="col-12 col-lg-6 landing-hero-copy">
        <div class="landing-pill mb-3">
          <i class="bi bi-mortarboard"></i>
          <span>Graduate Tracking System</span>
          @if ($locationLabel)
            <span class="text-muted">· {{ $locationLabel }}</span>
          @endif
        </div>
        <h1 class="landing-hero-title mb-3">
          <span class="landing-hero-title-line">Connecting Graduates.</span>
          <span class="landing-hero-title-line landing-hero-title-accent">Building Futures.</span>
        </h1>
        <p class="landing-hero-sub mb-4">
          A secure and reliable portal for San Josef graduates to share their journey, while the school tracks progress and creates a better future for all.
        </p>
        <div class="d-flex flex-wrap gap-2">
          @auth
            <a class="btn btn-primary landing-cta app-btn" href="{{ route('dashboard') }}"><i class="bi bi-grid me-1"></i> Open Dashboard</a>
            <a class="btn btn-outline-secondary landing-cta app-btn" href="{{ route('register') }}"><i class="bi bi-person-plus me-1"></i> Register Another Account</a>
          @else
            <a class="btn btn-primary landing-cta app-btn js-open-auth" data-auth-mode="register" href="{{ route('register') }}"><i class="bi bi-person-plus me-1"></i> Register</a>
            <a class="btn btn-outline-secondary landing-cta app-btn js-open-auth" data-auth-mode="login" href="{{ route('login') }}"><i class="bi bi-box-arrow-in-right me-1"></i> Login</a>
          @endauth
        </div>
      </div>

      <div class="col-12 col-lg-6 landing-hero-visual-col">
        <div class="landing-hero-visual">
          @if ($heroUrl)
            <img src="{{ \App\Support\AppLink::assetOrUrl($heroUrl) }}" alt="{{ $schoolName }} students" class="landing-hero-photo">
          @else
            <div class="p-4 text-muted">Add a cover image in <code>config/school.php</code> → <code>hero_image</code>.</div>
          @endif
        </div>
      </div>
    </div>
  </div>
</header>

<section id="overview" class="py-5 landing-section-alt">
  <div class="container landing-container">
    <div class="text-center mb-4">
      <h2 class="landing-section-title">Built for Graduate Tracking</h2>
      <p class="text-muted mb-2">Simple for students. Powerful for the school.</p>
      <a class="fw-semibold text-decoration-none" href="{{ route('about') }}" style="color:#111827;font-size:.85rem;">Learn more about us →</a>
    </div>
    <div class="row g-3">
      <div class="col-12 col-md-4">
        <div class="landing-feature-card">
          <div class="d-flex gap-2">
            <i class="bi bi-person-check fs-4"></i>
            <div>
              <div class="fw-bold">Verified access</div>
              <div class="text-muted small">Students register, then Admin approves before they can log in.</div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-12 col-md-4">
        <div class="landing-feature-card">
          <div class="d-flex gap-2">
            <i class="bi bi-briefcase fs-4"></i>
            <div>
              <div class="fw-bold">Outcome tracking</div>
              <div class="text-muted small">Employment, self-employment, unemployment, and further studies.</div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-12 col-md-4">
        <div class="landing-feature-card">
          <div class="d-flex gap-2">
            <i class="bi bi-bar-chart-line fs-4"></i>
            <div>
              <div class="fw-bold">Reports & insights</div>
              <div class="text-muted small">Admin dashboards summarize employment statistics with charts.</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section id="features" class="py-5">
  <div class="container landing-container">
    <div class="row align-items-end mb-4 g-2">
      <div class="col">
        <h2 class="landing-section-title mb-1">Key Features</h2>
        <div class="text-muted">Everything needed for accurate graduate monitoring.</div>
      </div>
    </div>

    <div class="row g-3">
      <div class="col-12 col-md-6 col-lg-4">
        <div class="landing-feature-card">
          <div class="fw-bold mb-1"><i class="bi bi-ui-checks-grid me-2"></i>Graduate Profiles</div>
          <div class="text-muted small">Students update their profile and submitted information anytime.</div>
        </div>
      </div>
      <div class="col-12 col-md-6 col-lg-4">
        <div class="landing-feature-card">
          <div class="fw-bold mb-1"><i class="bi bi-funnel me-2"></i>Search & Filter</div>
          <div class="text-muted small">Instructors/Admin can filter by batch year and employment status.</div>
        </div>
      </div>
      <div class="col-12 col-md-6 col-lg-4">
        <div class="landing-feature-card">
          <div class="fw-bold mb-1"><i class="bi bi-megaphone me-2"></i>Announcements</div>
          <div class="text-muted small">Post school updates and reminders for graduates.</div>
        </div>
      </div>
      <div class="col-12 col-md-6 col-lg-4">
        <div class="landing-feature-card">
          <div class="fw-bold mb-1"><i class="bi bi-shield-check me-2"></i>RBAC Security</div>
          <div class="text-muted small">Role-based access control keeps admin pages protected.</div>
        </div>
      </div>
      <div class="col-12 col-md-6 col-lg-4">
        <div class="landing-feature-card">
          <div class="fw-bold mb-1"><i class="bi bi-file-earmark-text me-2"></i>Report Snapshots</div>
          <div class="text-muted small">Save summary snapshots into the Reports table.</div>
        </div>
      </div>
      <div class="col-12 col-md-6 col-lg-4">
        <div class="landing-feature-card">
          <div class="fw-bold mb-1"><i class="bi bi-phone me-2"></i>Mobile Responsive</div>
          <div class="text-muted small">Fast UI built with Bootstrap 5 and responsive layouts.</div>
        </div>
      </div>
    </div>
  </div>
</section>

<section id="access" class="py-5 landing-section-alt">
  <div class="container landing-container">
    <div class="text-center mb-4">
      <h2 class="landing-section-title">Get access</h2>
      <p class="text-muted mb-0">Choose the right portal for your role.</p>
    </div>
    <div class="row g-3">
      <div class="col-12 col-md-4">
        <div class="landing-feature-card">
          <div class="fw-bold mb-1"><i class="bi bi-person me-2"></i>Graduate</div>
          <div class="text-muted small mb-3">Register, then wait for verification.</div>
          <a class="link-primary fw-semibold text-decoration-none js-open-auth" data-auth-mode="register" href="{{ route('register') }}">Student registration →</a>
        </div>
      </div>
      <div class="col-12 col-md-4">
        <div class="landing-feature-card">
          <div class="fw-bold mb-1"><i class="bi bi-box-arrow-in-right me-2"></i>Login</div>
          <div class="text-muted small mb-3">Access your dashboard after approval.</div>
          <a class="link-primary fw-semibold text-decoration-none js-open-auth" data-auth-mode="login" href="{{ route('login') }}">Login →</a>
        </div>
      </div>
      <div class="col-12 col-md-4">
        <div class="landing-feature-card">
          <div class="fw-bold mb-1"><i class="bi bi-shield-lock me-2"></i>Admin Portal</div>
          <div class="text-muted small mb-3">Verify registrations, manage users, and view reports.</div>
          <a class="link-primary fw-semibold text-decoration-none js-open-auth" data-auth-mode="login" href="{{ route('login') }}">Login →</a>
        </div>
      </div>
    </div>
  </div>
</section>

@include('partials.landing.footer')

@include('partials.landing.auth-modal')
@endsection
