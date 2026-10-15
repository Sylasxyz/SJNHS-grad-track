@php
    $schoolName = config('school.name');
    $address = (string) (config('school.address') ?: config('school.location_label'));
@endphp
<footer class="landing-footer">
  <div class="landing-footer-main">
    <div class="container landing-container">
      <div class="row landing-footer-grid g-4 g-lg-0">
        <div class="col-12 col-lg-3 landing-footer-brand">
          <div class="d-flex align-items-start gap-2 mb-2">
            @if (config('school.logo'))
              <img src="{{ \App\Support\AppLink::assetOrUrl(config('school.logo')) }}" alt="{{ $schoolName }} logo" class="landing-footer-logo">
            @endif
            <div>
              <div class="landing-footer-school-name">{{ $schoolName }}</div>
              @if (config('school.tagline'))
                <p class="landing-footer-tagline mb-0">{{ config('school.tagline') }}</p>
              @endif
            </div>
          </div>
          @if ($address)
            <div class="landing-footer-meta"><i class="bi bi-geo-alt"></i> {{ $address }}</div>
          @endif
        </div>

        <div class="col-6 col-lg-3 landing-footer-col">
          <h3 class="landing-footer-heading">Quick Links</h3>
          <ul class="landing-footer-links list-unstyled mb-0">
            <li><a href="{{ route('about') }}">About Us</a></li>
            <li><a href="{{ route('home') }}#features">Features</a></li>
            <li><a href="{{ route('home') }}#access">Get Access</a></li>
            <li><a href="{{ route('privacy') }}">Privacy Policy</a></li>
          </ul>
        </div>

        <div class="col-6 col-lg-3 landing-footer-col">
          <h3 class="landing-footer-heading">Contact Us</h3>
          <ul class="landing-footer-contact list-unstyled mb-0">
            @if (config('school.email'))
              <li><i class="bi bi-envelope"></i> <a href="mailto:{{ config('school.email') }}">{{ config('school.email') }}</a></li>
            @endif
            @if (config('school.phone'))
              <li><i class="bi bi-telephone"></i> <span>{{ config('school.phone') }}</span></li>
            @endif
          </ul>
        </div>

        <div class="col-12 col-lg-3 landing-footer-col">
          <h3 class="landing-footer-heading">Follow Us</h3>
          <div class="landing-footer-social">
            <a href="{{ config('school.social.facebook', '#') }}" class="landing-footer-social-btn" aria-label="Facebook" target="_blank" rel="noopener noreferrer"><i class="bi bi-facebook"></i></a>
            <a href="{{ config('school.social.instagram', '#') }}" class="landing-footer-social-btn" aria-label="Instagram" target="_blank" rel="noopener noreferrer"><i class="bi bi-instagram"></i></a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="landing-footer-bar">
    <div class="container landing-container landing-footer-bar-inner">
      <div class="landing-footer-copy">© {{ now()->format('Y') }} {{ $schoolName }}. All rights reserved.</div>
      <div class="landing-footer-legal">
        <a href="{{ route('terms') }}">Terms of Use</a>
      </div>
    </div>
    <div class="landing-footer-decor" aria-hidden="true"></div>
  </div>
</footer>
