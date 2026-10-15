@extends('layouts.dashboard')

@section('title', 'Edit Graduate Profile')

@section('dashboard-content')
  @php
    $values = [
        'full_name' => old('full_name', (string) $profile->Full_Name),
        'course' => old('course', (string) $profile->Course),
        'batch_year' => old('batch_year', (string) $profile->Batch_Year),
        'employment_status' => old('employment_status', (string) ($profile->Employment_Status ?? 'Unemployed')),
        'company_school_name' => old('company_school_name', (string) $profile->Company_School_Name),
        'contact_info' => old('contact_info', (string) $profile->Contact_Info),
    ];
  @endphp

  <div class="app-card p-3 mb-3">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
      <div>
        <h1 class="h4 mb-1">Edit Graduate Profile</h1>
        <div class="text-muted small">User: <strong>{{ $profile->user?->Username }}</strong> · Profile ID: {{ $profile->Profile_ID }}</div>
      </div>
      <a class="btn btn-sm btn-outline-secondary" href="{{ route('graduates') }}">Back</a>
    </div>
  </div>

  @include('partials.flash')

  <div class="app-card p-3 mb-3">
    <h2 class="h6 mb-3">Verification</h2>
    <div class="row g-2">
      <div class="col-12 col-md-4">
        <div class="text-muted small">Verified</div>
        <div class="fw-semibold">{{ $profile->Is_Verified ? 'Yes' : 'No' }}</div>
      </div>
      <div class="col-12 col-md-4">
        <div class="text-muted small">Verified At</div>
        <div class="fw-semibold">{{ $profile->getRawOriginal('Verified_At') }}</div>
      </div>
      <div class="col-12 col-md-4">
        <div class="text-muted small">Last Updated</div>
        <div class="fw-semibold">{{ $profile->getRawOriginal('Last_Updated') }}</div>
      </div>
    </div>
  </div>

  <div class="app-card p-3">
    <form method="post" action="{{ route('graduates.update', $profile) }}">
      @csrf
      @method('PUT')

      <div class="mb-3">
        <label class="form-label" for="full_name">Full Name</label>
        <input class="form-control" id="full_name" name="full_name" required value="{{ $values['full_name'] }}">
      </div>

      <div class="mb-3">
        <label class="form-label" for="course">Course / Strand</label>
        <input class="form-control" id="course" name="course" value="{{ $values['course'] }}">
      </div>

      <div class="mb-3">
        <label class="form-label" for="batch_year">Batch Year</label>
        <input class="form-control" id="batch_year" name="batch_year" type="number" min="1990" max="{{ now()->year + 1 }}" required value="{{ $values['batch_year'] }}">
      </div>

      <div class="mb-3">
        <label class="form-label" for="employment_status">Employment Status</label>
        <select class="form-select" id="employment_status" name="employment_status" required>
          @foreach (\App\Models\GraduateProfile::EMPLOYMENT_STATUSES as $opt)
            <option value="{{ $opt }}" @selected($values['employment_status'] === $opt)>{{ $opt }}</option>
          @endforeach
        </select>
      </div>

      <div class="mb-3">
        <label class="form-label" for="company_school_name">Company / School Name</label>
        <input class="form-control" id="company_school_name" name="company_school_name" value="{{ $values['company_school_name'] }}">
      </div>

      <div class="mb-3">
        <label class="form-label" for="contact_info">Contact Info</label>
        <input class="form-control" id="contact_info" name="contact_info" value="{{ $values['contact_info'] }}">
      </div>

      <div class="form-check mb-3">
        @if (! $profile->Is_Verified)
          <input class="form-check-input" type="checkbox" value="1" id="mark_verified" name="mark_verified" @checked(old('mark_verified'))>
          <label class="form-check-label" for="mark_verified">
            Mark as verified (sets Verified By/At)
          </label>
        @else
          <span class="badge text-bg-success"><i class="bi bi-check-circle me-1"></i>Profile verified</span>
        @endif
      </div>

      <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-primary" type="submit">Save Changes</button>
      </div>
    </form>
  </div>

  @can('delete', $profile)
    <form method="post" action="{{ route('graduates.destroy', $profile) }}" class="mt-3" onsubmit="return confirm('Delete this graduate profile? This cannot be undone.');">
      @csrf
      @method('DELETE')
      <button class="btn btn-outline-danger btn-sm" type="submit">Delete Profile</button>
    </form>
  @endcan
@endsection
