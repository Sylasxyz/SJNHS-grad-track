@extends('layouts.app')

@section('title', 'Reset Password')

@php
    $embedQuery = $embed ? ['embed' => 1] : [];
@endphp

@section('content')
  <div class="row justify-content-center">
    <div class="col-12 col-md-7 col-lg-5">
      <div class="app-card p-3 p-md-4">
        <h1 class="h4 mb-3">Create New Password</h1>
        @if (session('error'))<div class="alert alert-danger py-2">{{ session('error') }}</div>@endif

        @if (session('success'))
          <div class="alert alert-success py-2">{{ session('success') }}</div>
          <a class="btn btn-primary w-100" href="{{ route('login', $embedQuery) }}">Go to Login</a>
        @elseif (! $resetRow)
          <div class="alert alert-warning py-2">Please verify your email with a 6-digit code first.</div>
          <a class="btn btn-outline-primary w-100" href="{{ route('password.request', $embedQuery) }}">Request a verification code</a>
        @else
          <p class="text-muted small mb-3">
            Resetting password for <strong>{{ $resetRow->Email }}</strong>
          </p>
          <form method="post" action="{{ route('password.update') }}" autocomplete="off">
            @csrf
            @if ($embed)<input type="hidden" name="embed" value="1">@endif
            <div class="mb-3">
              <label class="form-label" for="password">New Password</label>
              <input class="form-control" type="password" id="password" name="password" required minlength="8">
              <div class="form-text">At least 8 characters, with letters and numbers.</div>
            </div>
            <div class="mb-3">
              <label class="form-label" for="confirm_password">Confirm Password</label>
              <input class="form-control" type="password" id="confirm_password" name="confirm_password" required minlength="8">
            </div>
            <button class="btn btn-primary w-100" type="submit">Reset Password</button>
          </form>
        @endif
      </div>
    </div>
  </div>
@endsection
