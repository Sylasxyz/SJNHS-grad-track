@extends('layouts.app')

@section('content')
  <div class="dashboard-shell">
    @include('partials.sidebar')

    <div class="{{ trim('dashboard-content '.trim($__env->yieldContent('dashboard-content-class'))) }}">
      @yield('dashboard-content')
    </div>
  </div>
@endsection
