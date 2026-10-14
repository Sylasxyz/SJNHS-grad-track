@extends('layouts.app')

@section('content')
  <div class="dashboard-shell">
    @include('partials.sidebar')

    <div class="dashboard-content">
      @yield('dashboard-content')
    </div>
  </div>
@endsection
