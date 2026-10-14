@props(['title', 'sidePrompt', 'sideLinkLabel', 'sideLinkUrl'])
@php
    $schoolName = config('school.name');
    $logo = config('school.logo') ? asset(config('school.logo')) : null;
@endphp
<div class="row justify-content-center">
  <div class="col-12 col-xl-11">
    <div class="app-card auth-popup-card auth-split">
      <div class="auth-side-panel">
        <div class="auth-side-center">
          @if ($logo)
            <img class="auth-side-logo" src="{{ $logo }}" alt="{{ $schoolName }} logo">
          @else
            <div class="auth-side-mark">WELCOME.</div>
          @endif
        </div>
        <div class="auth-side-bottom">
          {{ $sidePrompt }}
          <a class="fw-semibold text-decoration-none" href="{{ $sideLinkUrl }}">{{ $sideLinkLabel }}</a>
        </div>
      </div>

      <div class="auth-form-panel">
        <div class="d-flex align-items-start justify-content-between mb-3">
          <h1 class="auth-title-sm mb-0">{{ $title }}</h1>
          <div class="auth-logo-wrap auth-logo-right">
            @if ($logo)
              <img class="auth-logo-img" src="{{ $logo }}" alt="{{ $schoolName }} logo">
            @else
              <span class="auth-logo-fallback"><i class="bi bi-mortarboard"></i></span>
            @endif
          </div>
        </div>

        {{ $slot }}
      </div>
    </div>
  </div>
</div>
