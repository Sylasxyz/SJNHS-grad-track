@extends('layouts.dashboard')

@section('title', 'Graduate Search')

@section('dashboard-content')
  @php
    $canVerify = auth()->user()->can('verify', \App\Models\GraduateProfile::class);
    $query = request()->query();
  @endphp

  <div class="app-card p-3 mb-3">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
      <h1 class="h4 mb-0">Search / Verify Graduates</h1>
      <div class="d-flex gap-2">
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('graduates', array_merge($query, ['view' => $viewMode === 'cards' ? 'table' : 'cards'])) }}">
          {{ $viewMode === 'cards' ? 'Table view' : 'Card view' }}
        </a>
        <a class="btn btn-sm btn-outline-primary" href="{{ route('graduates.export', $query) }}">
          <i class="bi bi-download me-1"></i>Export CSV
        </a>
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('dashboard') }}">Back</a>
      </div>
    </div>
  </div>

  @include('partials.flash')

  <div class="app-card p-3 mb-3">
    <form class="row g-2" method="get" action="{{ route('graduates') }}">
      @if ($viewMode === 'table')
        <input type="hidden" name="view" value="table">
      @endif
      <div class="col-12 col-md-3">
        <select class="form-select" name="batch_year">
          <option value="">All Batch Years</option>
          @for ($y = 2050; $y >= 2000; $y--)
            <option value="{{ $y }}" @selected($filters['batch_year'] === (string) $y)>{{ $y }}</option>
          @endfor
        </select>
      </div>
      <div class="col-12 col-md-3">
        <select class="form-select" name="course">
          <option value="">All Strands</option>
          @foreach (\App\Models\GraduateProfile::STRANDS as $strand)
            <option value="{{ $strand }}" @selected($filters['course'] === $strand)>
              {{ $strand }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="col-12 col-md-3">
        <select class="form-select" name="employment_status">
          <option value="">All Status</option>
          @foreach (\App\Models\GraduateProfile::EMPLOYMENT_STATUSES as $opt)
            <option value="{{ $opt }}" @selected($filters['employment_status'] === $opt)>{{ $opt }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-12 col-md-3">
        <input class="form-control" name="q" placeholder="Search name or Student ID" value="{{ $filters['q'] }}">
      </div>
      <div class="col-12 col-md-2 d-grid">
        <button class="btn btn-primary" type="submit">Filter</button>
      </div>
    </form>
  </div>

  <div class="app-card p-3">
    @if ($rows->isEmpty())
      <div class="alert alert-secondary mb-0">No graduate profiles found.</div>
    @elseif ($viewMode === 'table')
      @if ($canVerify)
        <form method="post" action="{{ route('graduates.verify') }}">
          @csrf
          @method('PATCH')
          <div class="d-flex justify-content-between align-items-center mb-2 gap-2">
            <span class="text-muted small">{{ $rows->count() }} result(s)</span>
            <button class="btn btn-sm btn-success" type="submit"><i class="bi bi-check2-all me-1"></i>Verify selected</button>
          </div>
      @else
        <div class="d-flex justify-content-between align-items-center mb-2 gap-2">
          <span class="text-muted small">{{ $rows->count() }} result(s) · View only</span>
        </div>
      @endif
        <div class="table-responsive">
          <table class="table table-sm align-middle admin-table">
            <thead>
              <tr>
                @if ($canVerify)<th><input type="checkbox" id="selectAllProfiles"></th>@endif
                <th>Name</th>
                <th>Batch</th>
                <th>Course</th>
                <th>Status</th>
                <th>Verified</th>
                <th>Updated</th>
                @if ($canVerify)<th>Actions</th>@endif
              </tr>
            </thead>
            <tbody>
              @foreach ($rows as $r)
                <tr>
                  @if ($canVerify)
                    <td>
                      @if (! $r->Is_Verified)
                        <input type="checkbox" name="profile_ids[]" value="{{ $r->Profile_ID }}" class="profile-select">
                      @endif
                    </td>
                  @endif
                  <td>{{ $r->Full_Name }}</td>
                  <td>{{ $r->Batch_Year }}</td>
                  <td>{{ $r->Course }}</td>
                  <td>{{ $r->Employment_Status }}</td>
                  <td>
                    @if ($r->Is_Verified)
                      <span class="badge text-bg-success">Yes</span>
                    @else
                      <span class="badge text-bg-warning">No</span>
                    @endif
                  </td>
                  <td class="text-muted small">{{ $r->getRawOriginal('Last_Updated') }}</td>
                  @if ($canVerify)
                    <td>
                      @can('update', $r)
                        <a class="btn btn-sm btn-primary" href="{{ route('graduates.edit', $r->Profile_ID) }}">Edit</a>
                      @endcan
                    </td>
                  @endif
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @if ($canVerify)</form>@endif
      <div class="text-muted small mb-0">Showing up to 200 results.</div>
    @else
      <div class="vstack gap-3">
        @foreach ($rows as $r)
          <div class="border rounded-3 p-3">
            <div class="row g-3 align-items-center">
              <div class="col-12 col-md-2">
                <div class="border rounded-3 d-flex align-items-center justify-content-center bg-light text-muted" style="height:110px;">
                  @if ($photos[$r->Profile_ID])
                    <img src="{{ $photos[$r->Profile_ID] }}" alt="Graduate photo" style="width:100%;height:110px;object-fit:cover;border-radius:8px;">
                  @else
                    <div class="text-center">
                      <i class="bi bi-person-badge fs-2 d-block"></i>
                      <small>No Photo</small>
                    </div>
                  @endif
                </div>
              </div>
              <div class="col-12 col-md-7">
                <div class="fw-bold">{{ $r->Full_Name }}</div>
                <div class="text-muted small">{{ $r->Username }}</div>
                <div class="mt-2 d-flex flex-wrap gap-2">
                  <span class="badge text-bg-light border">Batch {{ $r->Batch_Year }}</span>
                  <span class="badge text-bg-light border">{{ $r->Course ?? 'No course' }}</span>
                  <span class="badge text-bg-light border">{{ $r->Employment_Status }}</span>
                  @if ($r->Is_Verified)
                    <span class="badge text-bg-success">Verified</span>
                  @else
                    <span class="badge text-bg-secondary">Unverified</span>
                  @endif
                </div>
              </div>
              <div class="col-12 col-md-3 text-md-end">
                <button
                  type="button"
                  class="btn btn-sm btn-outline-primary mb-2"
                  data-bs-toggle="modal"
                  data-bs-target="#graduateInfoModal"
                  data-fullname="{{ $r->Full_Name }}"
                  data-username="{{ $r->Username }}"
                  data-batch="{{ $r->Batch_Year }}"
                  data-course="{{ $r->Course }}"
                  data-status="{{ $r->Employment_Status }}"
                  data-verified="{{ $r->Is_Verified ? 'Yes' : 'No' }}"
                  data-company="{{ $r->Company_School_Name }}"
                  data-contact="{{ $r->Contact_Info }}"
                  data-updated="{{ $r->getRawOriginal('Last_Updated') }}"
                  data-photo="{{ $photos[$r->Profile_ID] }}">
                  View Information
                </button>
                @can('update', $r)
                  <div>
                    <a class="btn btn-sm btn-primary" href="{{ route('graduates.edit', $r->Profile_ID) }}">Edit</a>
                  </div>
                @endcan
              </div>
            </div>
          </div>
        @endforeach
      </div>
      <div class="text-muted small mb-0">Showing up to 200 results.</div>
    @endif
  </div>

  <div class="modal fade" id="graduateInfoModal" tabindex="-1" aria-labelledby="graduateInfoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h2 class="modal-title h5 mb-0" id="graduateInfoModalLabel">Student Information</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12 col-md-4">
              <div class="border rounded-3 d-flex align-items-center justify-content-center bg-light text-muted" style="height:180px;">
                <img id="m_photo" src="" alt="Student photo" style="display:none;width:100%;height:180px;object-fit:cover;border-radius:8px;">
                <div id="m_photo_placeholder" class="text-center">
                  <i class="bi bi-person-badge fs-2 d-block"></i>
                  <small>No Photo</small>
                </div>
              </div>
            </div>
            <div class="col-12 col-md-8">
              <div class="row g-3">
                <div class="col-12 col-md-6"><small class="text-muted d-block">Full Name</small><div id="m_fullname" class="fw-semibold"></div></div>
                <div class="col-12 col-md-6"><small class="text-muted d-block">Username</small><div id="m_username" class="fw-semibold"></div></div>
                <div class="col-12 col-md-4"><small class="text-muted d-block">Batch</small><div id="m_batch" class="fw-semibold"></div></div>
                <div class="col-12 col-md-4"><small class="text-muted d-block">Course</small><div id="m_course" class="fw-semibold"></div></div>
                <div class="col-12 col-md-4"><small class="text-muted d-block">Verified</small><div id="m_verified" class="fw-semibold"></div></div>
                <div class="col-12 col-md-6"><small class="text-muted d-block">Employment Status</small><div id="m_status" class="fw-semibold"></div></div>
                <div class="col-12 col-md-6"><small class="text-muted d-block">Company / School</small><div id="m_company" class="fw-semibold"></div></div>
                <div class="col-12 col-md-6"><small class="text-muted d-block">Contact Info</small><div id="m_contact" class="fw-semibold"></div></div>
                <div class="col-12 col-md-6"><small class="text-muted d-block">Last Updated</small><div id="m_updated" class="fw-semibold"></div></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    const selectAll = document.getElementById('selectAllProfiles');
    if (selectAll) {
      selectAll.addEventListener('change', () => {
        document.querySelectorAll('.profile-select').forEach((cb) => {
          cb.checked = selectAll.checked;
        });
      });
    }

    const modal = document.getElementById('graduateInfoModal');
    if (modal) {
      modal.addEventListener('show.bs.modal', (event) => {
        const button = event.relatedTarget;
        const get = (k) => button?.getAttribute('data-' + k) || '';
        document.getElementById('m_fullname').textContent = get('fullname');
        document.getElementById('m_username').textContent = get('username');
        document.getElementById('m_batch').textContent = get('batch');
        document.getElementById('m_course').textContent = get('course');
        document.getElementById('m_verified').textContent = get('verified');
        document.getElementById('m_status').textContent = get('status');
        document.getElementById('m_company').textContent = get('company');
        document.getElementById('m_contact').textContent = get('contact');
        document.getElementById('m_updated').textContent = get('updated');
        const photo = get('photo');
        const img = document.getElementById('m_photo');
        const ph = document.getElementById('m_photo_placeholder');
        if (photo) {
          img.src = photo;
          img.style.display = 'block';
          ph.style.display = 'none';
        } else {
          img.src = '';
          img.style.display = 'none';
          ph.style.display = 'block';
        }
      });
    }
  </script>
@endpush
