{{-- Privacy Policy / Terms page; ?embed=1 (opened from the register form) hides the nav and footer. --}}
@extends('layouts.app')

@section('title', $heading.' · '.config('school.name'))

@section('body-class', 'landing-page about-page app-body')

@section('body')
@php
    $isEmbed = request()->query('embed') === '1';
@endphp

@unless ($isEmbed)
<nav class="navbar navbar-expand-lg landing-nav sticky-top">
  <div class="container landing-container">
    @include('partials.landing.brand')
    <div class="ms-auto d-flex gap-2">
      <a class="btn btn-sm btn-outline-secondary" href="{{ route($other) }}">{{ $otherLabel }}</a>
      <a class="btn btn-sm btn-outline-secondary" href="{{ route('register') }}">Back to Register</a>
    </div>
  </div>
</nav>
@endunless

<main class="py-5">
  <div class="container landing-container" style="max-width:860px;">
    <h1 class="h3 mb-2">{{ $heading }}</h1>
    <p class="text-muted mb-4">
      @yield('legal-intro')
    </p>

    <div class="app-card p-3 p-md-4 legal-doc">
      @yield('legal-content')
    </div>

    @if ($isEmbed)
      <div class="mt-3">
        <button class="btn btn-sm btn-dark" type="button" onclick="window.close();">Close</button>
      </div>
    @else
      <div class="mt-3 d-flex flex-wrap gap-2">
        <a class="btn btn-sm btn-dark" href="{{ route('register') }}">Return to Register</a>
        <a class="btn btn-sm btn-outline-secondary" href="{{ route($other) }}">View {{ $otherLabel }}</a>
      </div>
    @endif
  </div>
</main>

@unless ($isEmbed)
  @include('partials.landing.footer')
@endunless
@endsection
