@extends('layouts.app')

@section('title', 'Login')

@php
    $withEmbed = fn (string $name) => route($name, $embed ? ['embed' => 1] : []);
@endphp

@section('content')
  <x-auth-split-card title="Log in" side-prompt="Not a member yet?" side-link-label="Register now" :side-link-url="$withEmbed('register')">
    @if (session('error'))
      <div class="alert alert-danger d-flex align-items-start gap-2">
        <i class="bi bi-shield-exclamation mt-1 flex-shrink-0"></i>
        <div>{{ session('error') }}</div>
      </div>
    @endif

    @if (session('attempts_remaining'))
      <div class="alert alert-warning d-flex align-items-center gap-2">
        <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
        <div><strong>Warning:</strong> {{ session('attempts_remaining') }} attempt(s) remaining before your account is temporarily locked.</div>
      </div>
    @endif

    <form method="post" action="{{ route('login.attempt') }}" autocomplete="off">
      @csrf
      @if ($embed)<input type="hidden" name="embed" value="1">@endif

      <div class="mb-3">
        <label class="form-label auth-label" for="username">Student ID or Employee ID</label>
        <input class="form-control auth-input-minimal" id="username" name="username" required
               value="{{ old('username') }}" placeholder="Student ID or Employee ID">
      </div>

      <div class="mb-3">
        <label class="form-label auth-label" for="password">Password</label>
        <div class="input-group">
          <input class="form-control auth-input-minimal" type="password" id="password"
                 name="password" required placeholder="Password">
          <button class="btn btn-outline-secondary" type="button" data-toggle-password="password" aria-label="Show password">
            <i class="bi bi-eye"></i>
          </button>
        </div>
      </div>

      <button class="btn btn-dark w-100 auth-submit-btn" type="submit">Log in now</button>
      <div class="text-end mt-2">
        <a class="small text-decoration-none" href="{{ $withEmbed('password.request') }}">Forgot password?</a>
      </div>
      <div class="text-center mt-3 small">
        Need an account?
        <a class="fw-semibold text-decoration-none" href="{{ $withEmbed('register') }}">Register</a>
      </div>
    </form>
  </x-auth-split-card>
@endsection

@push('scripts')
  @include('auth.partials.password-toggle')
@endpush
