@extends('layouts.dashboard')

@section('title', 'Test email')

@section('dashboard-content')
  <div class="app-card p-3 p-md-4 mb-3">
    <h1 class="h4 mb-2">Test outbound email</h1>
    <p class="text-muted small mb-0">Admin only. On Railway set <code>BREVO_API_KEY</code> + <code>MAIL_FROM_EMAIL</code> (recommended), then redeploy.</p>
  </div>

  <div class="app-card p-3 p-md-4 mb-3">
    <h2 class="h6 mb-2">Connection check</h2>
    <ul class="small mb-0 font-monospace">
      @foreach ($diagnostics as $line)
        <li>{{ $line }}</li>
      @endforeach
    </ul>
    @if (! $outboundReady)
      <div class="alert alert-warning small mt-3 mb-0">
        <strong>Mail not configured.</strong> Railway → web service → Variables → add
        <code>BREVO_API_KEY</code> (Brevo → SMTP &amp; API → API Keys), <code>MAIL_FROM_EMAIL</code> (verified sender),
        then redeploy.
      </div>
    @endif
  </div>

  <div class="app-card p-3 p-md-4">
    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if (session('error') || $errors->has('to_email'))
      <div class="alert alert-danger">
        {{ $errors->first('to_email') ?: session('error') }}
        @if (filled(session('error_detail')))
          <div class="small mt-2 mb-0"><strong>Details:</strong> {{ session('error_detail') }}</div>
        @endif
      </div>
    @endif

    <form method="post" action="{{ route('admin.mail-test.send') }}" class="row g-3" style="max-width: 28rem;">
      @csrf
      <div class="col-12">
        <label class="form-label" for="to_email">Send test to</label>
        <input class="form-control" type="email" id="to_email" name="to_email" required
               value="{{ old('to_email', $defaultTo) }}">
      </div>
      <div class="col-12">
        <button class="btn btn-primary" type="submit">Send test email</button>
        <a class="btn btn-outline-secondary ms-2" href="{{ route('dashboard') }}">Back</a>
      </div>
    </form>
  </div>
@endsection
