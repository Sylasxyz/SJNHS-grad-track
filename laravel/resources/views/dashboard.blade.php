@extends('layouts.dashboard')

@section('title', 'Dashboard')

@section('dashboard-content')
  <div class="app-card p-4">
    <h1 class="h4 mb-1">Welcome back, {{ $user->First_Name }}!</h1>
    <p class="text-muted mb-3">Signed in as {{ $user->fullName() }} ({{ $user->Role }}).</p>
    <p class="mb-0 small text-muted">
      The full dashboard has not been converted to Laravel yet. Use the menu to open the existing pages.
    </p>
  </div>
@endsection
