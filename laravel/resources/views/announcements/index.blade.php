@extends('layouts.dashboard')

@section('title', 'Announcements')

@section('dashboard-content')
  @php
    $canManage = auth()->user()->can('viewArchived', \App\Models\Announcement::class);
  @endphp

  <div class="app-card p-3 mb-3">
    <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
      <div>
        <h1 class="h4 mb-0">Announcements</h1>
        @if ($archived)
          <div class="text-muted small">Showing archived announcements</div>
        @endif
      </div>
      <div class="d-flex gap-2 flex-wrap">
        @if ($canManage)
          @if ($archived)
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('announcements') }}">Active</a>
          @else
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('announcements', ['view' => 'archived']) }}">Archived</a>
          @endif
        @endif
        @can('create', \App\Models\Announcement::class)
          <a class="btn btn-sm btn-primary" href="{{ route('announcements.create') }}">Post announcement</a>
        @endcan
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('dashboard') }}">Back</a>
      </div>
    </div>
  </div>

  @include('partials.flash')

  @if ($announcements->isEmpty())
    <div class="alert alert-secondary">
      {{ $archived ? 'No archived announcements.' : 'No announcements yet.' }}
    </div>
  @else
    <div class="vstack gap-3">
      @foreach ($announcements as $a)
        <div class="app-card p-3">
          <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="d-flex flex-wrap align-items-center gap-2">
              <h2 class="h5 mb-0">{{ $a->Title }}</h2>
              @if ($a->Is_Archived)
                <span class="badge text-bg-secondary">Archived</span>
              @endif
            </div>
            <small class="text-muted">
              {{ $a->Date_Posted?->format('Y-m-d H:i:s') }}
              @if ($a->author?->Username)
                · by {{ $a->author->Username }}
              @endif
            </small>
          </div>
          <hr>
          <p class="mb-0" style="white-space: pre-wrap;">{{ $a->Message }}</p>
          @if (trim((string) $a->Image_Path) !== '')
            <div class="announcement-image-wrap mt-3">
              <img src="{{ route('announcements.image', $a) }}" alt="Announcement image" class="announcement-image">
            </div>
          @endif

          @if ($canManage)
            <div class="d-flex flex-wrap gap-2 mt-3">
              @if ($a->Is_Archived)
                @can('restore', $a)
                  <form method="post" action="{{ route('announcements.restore', $a) }}" class="d-inline">
                    @csrf
                    @method('PATCH')
                    <button class="btn btn-sm btn-outline-primary" type="submit">Restore</button>
                  </form>
                @endcan
              @else
                @can('archive', $a)
                  <form method="post" action="{{ route('announcements.archive', $a) }}" class="d-inline">
                    @csrf
                    @method('PATCH')
                    <button class="btn btn-sm btn-outline-secondary" type="submit">Archive</button>
                  </form>
                @endcan
              @endif
              @can('delete', $a)
                <form method="post" action="{{ route('announcements.destroy', $a) }}" class="d-inline" onsubmit="return confirm('Permanently delete this announcement? This cannot be undone.');">
                  @csrf
                  @method('DELETE')
                  <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                </form>
              @endcan
            </div>
          @endif
        </div>
      @endforeach
    </div>
  @endif
@endsection
