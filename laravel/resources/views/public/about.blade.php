@extends('layouts.app')

@section('title', 'About · '.config('school.name'))

@section('body-class', 'landing-page about-page app-body')

@section('body')
@php
    $schoolName = config('school.name');
    $aboutImageUrl = (string) config('school.about_image');
    $locationLabel = (string) config('school.location_label');
    $tagline = (string) config('school.tagline');
@endphp

@include('partials.landing.nav', ['active' => 'about'])

<header class="about-hero">
  <div class="container landing-container">
    <div class="row align-items-center g-4 about-hero-row">
      <div class="col-12 col-lg-6 about-hero-copy about-reveal">
        <div class="about-eyebrow">
          <i class="bi bi-info-circle"></i>
          <span>About</span>
        </div>
        <h1 class="about-hero-title">
          {{ $schoolName }} Tracking System
        </h1>
        <p class="about-hero-lead">
          San Josef National High School is committed to supporting students beyond Senior High School.
          Through our Graduate Tracking System, we stay connected with graduates{{ $locationLabel ? ' in '.$locationLabel : '' }} —
          helping them share their employment and education journey while giving the school reliable data for guidance, reporting, and planning.
        </p>
        @if ($tagline)
          <p class="about-hero-tagline">{{ $tagline }}</p>
        @endif
      </div>
      <div class="col-12 col-lg-6 about-hero-media about-reveal about-reveal-delay">
        <figure class="about-hero-photo{{ $aboutImageUrl ? '' : ' is-empty' }}">
          @if ($aboutImageUrl)
            <div class="about-hero-photo-frame">
              <img
                src="{{ \App\Support\AppLink::assetOrUrl($aboutImageUrl) }}"
                alt="{{ $schoolName }} Annex building entrance"
                width="1200"
                height="900"
                loading="eager"
                decoding="async"
              >
              <div class="about-hero-fade" aria-hidden="true"></div>
            </div>
            <figcaption class="about-hero-photo-caption">
              San Josef National High School (Annex){{ $locationLabel ? ' · '.$locationLabel : '' }}
            </figcaption>
          @else
            <div class="about-hero-photo-empty">
              <i class="bi bi-building"></i>
              <span>Add school photo in <code>config/school.php</code> → <code>about_image</code></span>
            </div>
          @endif
        </figure>
      </div>
    </div>
  </div>
</header>

<section class="about-section about-mv">
  <div class="container landing-container">
    <div class="text-center mb-4 about-reveal">
      <h2 class="landing-section-title">Mission and Vision of our system</h2>
    </div>
    <div class="row g-4">
      <div class="col-12 col-md-6 about-reveal">
        <article class="about-mv-card">
          <div class="about-mv-icon"><i class="bi bi-bullseye"></i></div>
          <h3>Our Mission</h3>
          <p>
            To provide a secure and reliable digital platform that helps San Josef NHS graduates update their
            employment and further-studies information, while enabling instructors and administrators to verify
            records, communicate updates, and support continuous graduate monitoring.
          </p>
        </article>
      </div>
      <div class="col-12 col-md-6 about-reveal about-reveal-delay">
        <article class="about-mv-card">
          <div class="about-mv-icon"><i class="bi bi-eye"></i></div>
          <h3>Our Vision</h3>
          <p>
            A connected school community where every graduate’s journey is documented, verified, and used to
            guide better decisions — building stronger pathways to employment, entrepreneurship, and higher education.
          </p>
        </article>
      </div>
    </div>
  </div>
</section>

<section class="about-section about-why">
  <div class="container landing-container">
    <div class="text-center mb-5 about-reveal">
      <div class="about-why-eyebrow">Why it matters</div>
      <h2 class="landing-section-title mb-2">More Than Tracking. A Connection That Continues.</h2>
      <p class="about-why-sub text-muted mb-0">
        From registration to reports, our system supports the full graduate journey — not just a one-time form.
      </p>
    </div>

    <div class="about-process about-reveal">
      <div class="about-process-line" aria-hidden="true"></div>
      <div class="row g-4 about-process-row">
        <div class="col-12 col-md-6 col-lg-3">
          <article class="about-process-step">
            <div class="about-process-icon"><i class="bi bi-people-fill"></i></div>
            <div class="about-process-kicker">Connect</div>
            <h3>Remember Every Journey</h3>
            <p>Graduates register and stay linked to San Josef NHS through a secure personal portal.</p>
          </article>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
          <article class="about-process-step">
            <div class="about-process-icon"><i class="bi bi-pencil-square"></i></div>
            <div class="about-process-kicker">Update</div>
            <h3>Keep Information Current</h3>
            <p>Profiles track employment, self-employment, unemployment, and further studies over time.</p>
          </article>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
          <article class="about-process-step">
            <div class="about-process-icon"><i class="bi bi-shield-check"></i></div>
            <div class="about-process-kicker">Verify</div>
            <h3>Build Trust Through Verification</h3>
            <p>Instructors verify records so the school can rely on accurate, checked graduate data.</p>
          </article>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
          <article class="about-process-step">
            <div class="about-process-icon"><i class="bi bi-bar-chart-fill"></i></div>
            <div class="about-process-kicker">Learn</div>
            <h3>Turn Journeys Into Insights</h3>
            <p>Analytics and reports help the school monitor outcomes and support better planning.</p>
          </article>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="about-section about-cta-band">
  <div class="container landing-container text-center about-reveal">
    <h2 class="landing-section-title mb-2">Ready to get started?</h2>
    <p class="text-muted mb-4">Register as a graduate or log in to open your dashboard.</p>
    <div class="d-flex flex-wrap justify-content-center gap-2">
      @auth
        <a class="btn btn-primary landing-cta app-btn" href="{{ route('dashboard') }}"><i class="bi bi-grid me-1"></i> Open Dashboard</a>
      @else
        <a class="btn btn-primary landing-cta app-btn js-open-auth" data-auth-mode="register" href="{{ route('register') }}"><i class="bi bi-person-plus me-1"></i> Register</a>
        <a class="btn btn-outline-secondary landing-cta app-btn js-open-auth" data-auth-mode="login" href="{{ route('login') }}"><i class="bi bi-box-arrow-in-right me-1"></i> Login</a>
      @endauth
    </div>
  </div>
</section>

@include('partials.landing.footer')

@push('scripts')
<script>
  (function () {
    const nodes = document.querySelectorAll('.about-reveal');
    if (!nodes.length) return;
    if (!('IntersectionObserver' in window)) {
      nodes.forEach((el) => el.classList.add('is-visible'));
      return;
    }
    const io = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.14, rootMargin: '0px 0px -40px 0px' });
    nodes.forEach((el) => io.observe(el));
  })();
</script>
@endpush

@include('partials.landing.auth-modal')
@endsection
