@extends('layouts.dashboard')

@section('title', 'Post Announcement')

@section('dashboard-content')
  <div class="app-card p-3 mb-3">
    <div class="d-flex align-items-center justify-content-between gap-2">
      <h1 class="h4 mb-0">Post Announcement</h1>
      <a class="btn btn-sm btn-outline-secondary" href="{{ route('dashboard') }}">Back</a>
    </div>
  </div>

  @include('partials.flash')

  <div class="app-card p-3">
    <form method="post" action="{{ route('announcements.store') }}" enctype="multipart/form-data">
      @csrf

      <div class="mb-3">
        <label class="form-label" for="title">Title</label>
        <input class="form-control" id="title" name="title" required
          maxlength="150"
          value="{{ old('title') }}">
        <div class="form-text text-muted">Maximum 150 characters.</div>
      </div>

      <div class="mb-3">
        <label class="form-label" for="message">Message</label>
        <textarea class="form-control" id="message" name="message" rows="6" required
          maxlength="2000">{{ old('message') }}</textarea>
        <div class="form-text text-muted">
          Maximum 2000 characters. External links (http://, https://) are not allowed.
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label" for="announcement_image">Image (optional)</label>
        <input class="form-control" type="file" id="announcement_image" name="announcement_image"
          accept="image/jpeg,image/png,image/webp">
        <div class="form-text text-muted">JPG, PNG, or WEBP. Maximum 3MB.</div>
        <div class="mt-2" id="announcementImagePreview" hidden>
          <img src="" alt="Preview" class="announcement-image-preview">
        </div>
      </div>

      <button class="btn btn-primary" type="submit">Post</button>
      <a class="btn btn-link" href="{{ route('announcements') }}">View announcements</a>
    </form>
  </div>
@endsection

@push('scripts')
<script>
(() => {
  const input = document.getElementById('announcement_image');
  const previewWrap = document.getElementById('announcementImagePreview');
  const previewImg = previewWrap?.querySelector('img');
  if (!input || !previewWrap || !previewImg) return;

  input.addEventListener('change', () => {
    const file = input.files && input.files[0];
    if (!file) {
      previewWrap.hidden = true;
      previewImg.removeAttribute('src');
      return;
    }
    const url = URL.createObjectURL(file);
    previewImg.onload = () => URL.revokeObjectURL(url);
    previewImg.src = url;
    previewWrap.hidden = false;
  });
})();
</script>
@endpush
