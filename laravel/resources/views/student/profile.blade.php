@extends('layouts.dashboard')

@section('title', 'Graduate Profile')

@section('dashboard-content')
  @php
    $isVerified = (bool) $profile?->Is_Verified;
    $hasPicture = trim((string) $profile?->Profile_Picture) !== '';
    $pictureUrl = $hasPicture ? route('profiles.picture', [$profile, 'v' => substr(md5($profile->Profile_Picture), 0, 8)]) : null;
    $values = [
        'full_name' => old('full_name', (string) $profile?->Full_Name),
        'course' => old('course', (string) $profile?->Course),
        'batch_year' => old('batch_year', (string) $profile?->Batch_Year),
        'employment_status' => old('employment_status', (string) ($profile?->Employment_Status ?? 'Unemployed')),
        'company_school_name' => old('company_school_name', (string) $profile?->Company_School_Name),
        'contact_info' => old('contact_info', (string) $profile?->Contact_Info),
    ];
  @endphp

  <div class="app-card p-3 mb-3">
    <div class="d-flex align-items-center justify-content-between gap-2">
      <h1 class="h4 mb-0">Graduate Profile</h1>
      <a class="btn btn-sm btn-outline-secondary" href="{{ route('dashboard') }}">Back</a>
    </div>
  </div>

  <div class="app-card p-3 mb-3">
    <h2 class="h6 mb-3">Submitted Information</h2>
    @if (! $profile)
      <div class="alert alert-secondary mb-0">
        No submission yet. Please fill out the form below and click <strong>Save Profile</strong>.
      </div>
    @else
      <div class="row g-2">
        <div class="col-12">
          @if ($isVerified)
            <span class="badge text-bg-success">Verified</span>
          @else
            <span class="badge text-bg-warning text-dark">Not yet verified</span>
          @endif
        </div>
        <div class="col-12 col-md-6">
          <div class="text-muted small">Full Name</div>
          <div class="fw-semibold">{{ $profile->Full_Name }}</div>
        </div>
        <div class="col-12 col-md-6">
          <div class="text-muted small">Strand</div>
          <div class="fw-semibold">{{ $profile->Course }}</div>
        </div>
        <div class="col-12 col-md-3">
          <div class="text-muted small">Batch Year</div>
          <div class="fw-semibold">{{ $profile->Batch_Year }}</div>
        </div>
        <div class="col-12 col-md-3">
          <div class="text-muted small">Employment Status</div>
          <div class="fw-semibold">{{ $profile->Employment_Status }}</div>
        </div>
        <div class="col-12 col-md-6">
          <div class="text-muted small">Company / School Name</div>
          <div class="fw-semibold">{{ $profile->Company_School_Name }}</div>
        </div>
        <div class="col-12 col-md-6">
          <div class="text-muted small">Contact Info</div>
          <div class="fw-semibold">{{ $profile->Contact_Info }}</div>
        </div>
        @if ($hasPicture)
          <div class="col-12">
            <div class="text-muted small">Profile Picture</div>
            <img
              src="{{ $pictureUrl }}"
              alt="Profile picture"
              class="mt-1"
              style="width:88px;height:88px;object-fit:cover;border-radius:10px;border:1px solid #ddd;">
          </div>
        @endif
        <div class="col-12">
          <div class="text-muted small">Last Updated</div>
          <div class="fw-semibold">{{ $profile->getRawOriginal('Last_Updated') }}</div>
        </div>
      </div>
    @endif
  </div>

  @include('partials.flash')

  <div class="app-card p-3">
    <form method="post" action="{{ route('student.profile.update') }}" enctype="multipart/form-data">
      @csrf
      @method('PUT')

      <div class="mb-3">
        <label class="form-label" for="full_name">Full Name</label>
        <input class="form-control" id="full_name" name="full_name" required
          value="{{ $values['full_name'] }}"
          @if ($isVerified) readonly style="background:#f0fff0;cursor:not-allowed;" @endif>
        @if ($isVerified)
          <div class="form-text text-success">
            <i class="bi bi-lock-fill"></i> Your name is locked because your profile is verified. Contact your Instructor to change it.
          </div>
        @endif
      </div>

      <div class="mb-3">
        <label class="form-label" for="course">Strand</label>
        <select class="form-select" id="course" name="course" required>
          <option value="" @selected($values['course'] === '')>Choose Strand</option>
          @foreach (\App\Models\GraduateProfile::STRANDS as $strand)
            <option value="{{ $strand }}" @selected($values['course'] === $strand)>
              {{ $strand }}
            </option>
          @endforeach
        </select>
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
        <div class="form-text">Fill this if Employed / Self-Employed / Further Studies.</div>
      </div>

      <div class="mb-3">
        <label class="form-label" for="contact_info">Contact Info</label>
        <input class="form-control" id="contact_info" name="contact_info"
          value="{{ $values['contact_info'] }}"
          maxlength="13"
          placeholder="09XXXXXXXXX or +639XXXXXXXXX">
        <div class="form-text text-muted">
          Philippine mobile number only. Accepted formats:
          <strong>09XXXXXXXXX</strong> (11 digits) or <strong>+639XXXXXXXXX</strong> (13 characters).
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label" for="profile_picture">
          {{ $hasPicture ? 'Change Profile Picture' : 'Profile Picture (optional)' }}
        </label>
        @if ($hasPicture)
          <div class="d-flex flex-wrap align-items-center gap-3 mb-2">
            <img
              src="{{ $pictureUrl }}"
              alt="Current profile picture"
              style="width:96px;height:96px;object-fit:cover;border-radius:12px;border:1px solid #ddd;">
            <div>
              <div class="small text-muted mb-2">Current photo</div>
              <button
                class="btn btn-sm btn-outline-danger"
                type="submit"
                form="removePictureForm"
                onclick="return confirm('Remove your profile picture?');">
                <i class="bi bi-trash me-1"></i>Remove photo
              </button>
            </div>
          </div>
        @endif
        <input class="form-control" id="profile_picture" name="profile_picture" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
        <div class="form-text">
          {{ $hasPicture
            ? 'Choose a new image to replace your current photo. Max 2MB. JPG, PNG, or WEBP.'
            : 'Max 2MB. JPG, PNG, or WEBP.' }}
        </div>
        @if ($hasPicture)
          <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" value="1" id="remove_profile_picture" name="remove_profile_picture" @checked(old('remove_profile_picture'))>
            <label class="form-check-label" for="remove_profile_picture">
              Remove current photo when I save
            </label>
          </div>
        @endif
      </div>

      <button class="btn btn-primary" type="submit">Save Profile</button>
    </form>

    @if ($hasPicture)
      <form method="post" action="{{ route('student.profile.picture.destroy') }}" id="removePictureForm" class="d-none">
        @csrf
        @method('DELETE')
      </form>
    @endif
  </div>
@endsection
