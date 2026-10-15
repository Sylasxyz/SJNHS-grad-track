@extends('layouts.app')

@section('title', 'Forgot Password')

@php
    $emailClass = match ($emailState) {
        'success' => ' is-valid-email',
        'error' => ' is-invalid-email',
        default => '',
    };
@endphp

@section('content')
  <div class="row justify-content-center">
    <div class="col-12 col-md-7 col-lg-5">
      <div class="app-card p-3 p-md-4 pw-reset-card">
        <h1 class="h4 mb-2">Forgot Password</h1>
        <p class="text-muted small mb-3">Enter your registered email, then request a 6-digit verification code to reset your password.</p>

        @if (session('error'))<div class="alert alert-danger py-2">{{ session('error') }}</div>@endif
        @if (session('success'))<div class="alert alert-success py-2">{{ session('success') }}</div>@endif

        @if (session('debug_code'))
          <div class="alert alert-warning py-2">
            <div class="fw-semibold mb-1">Verification code</div>
            <div class="fs-4 fw-bold letter-spacing">{{ session('debug_code') }}</div>
            <div class="small mt-2 mb-0">Email delivery is unavailable. Use the verification code below to continue resetting your password.</div>
          </div>
        @endif

        <form method="post" action="{{ route('password.email') }}" autocomplete="off" class="pw-reset-form">
          @csrf
          @if ($embed)<input type="hidden" name="embed" value="1">@endif

          <div class="mb-3">
            <label class="form-label" for="email">Email address</label>
            <input class="form-control pw-reset-email{{ $emailClass }}" type="email" id="email" name="email" required
                   placeholder="Please enter your email address" value="{{ $email }}">
            <div class="form-text">Please enter a valid email address to receive our message.</div>
          </div>

          <div class="mb-3">
            <label class="form-label" for="verification_code">Email verification code</label>
            <div class="pw-code-row">
              <input class="form-control pw-code-input{{ $codeState === 'error' ? ' is-invalid-email' : '' }}" type="text"
                     inputmode="numeric" pattern="[0-9]{6}" maxlength="6" id="verification_code" name="verification_code"
                     placeholder="Email Verification Code" autocomplete="one-time-code">
              <button class="btn btn-dark pw-send-code-btn" type="submit" name="action" value="send_code">Send Code</button>
            </div>
            <div class="form-text">Tap “Send Code” and we will email a 6-digit verification code. Enter it here, then continue.</div>
          </div>

          <button class="btn btn-primary w-100" type="submit" name="action" value="verify_code">
            Verify code &amp; continue
          </button>
        </form>

        <div class="text-center mt-3">
          <a href="{{ route('login', $embed ? ['embed' => 1] : []) }}">Back to Login</a>
        </div>
      </div>
    </div>
  </div>
@endsection
