@php
    $onHome = ($active ?? '') === 'home';
@endphp
<nav class="navbar navbar-expand-lg landing-nav sticky-top">
  <div class="container landing-container">
    @include('partials.landing.brand')
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#landingNav" aria-controls="landingNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="landingNav">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
        <li class="nav-item"><a class="nav-link{{ ($active ?? '') === 'about' ? ' active' : '' }}" href="{{ route('about') }}">About</a></li>
        <li class="nav-item"><a class="nav-link" href="{{ $onHome ? '' : route('home') }}#features">Features</a></li>
        <li class="nav-item"><a class="nav-link" href="{{ $onHome ? '' : route('home') }}#access">Get access</a></li>
        @auth
          <li class="nav-item ms-lg-2">
            <a class="btn btn-sm btn-outline-secondary landing-cta app-btn" href="{{ route('dashboard') }}">Dashboard</a>
          </li>
          <li class="nav-item">
            <form method="post" action="{{ route('logout') }}" class="d-inline">
              @csrf
              <button class="btn btn-sm btn-primary landing-cta app-btn" type="submit">Logout</button>
            </form>
          </li>
        @else
          <li class="nav-item ms-lg-2">
            <a class="btn btn-sm btn-primary landing-cta app-btn js-open-auth" data-auth-mode="login" href="{{ route('login') }}">Login</a>
          </li>
        @endauth
      </ul>
    </div>
  </div>
</nav>
