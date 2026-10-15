<a class="navbar-brand d-flex align-items-center gap-2 fw-bold" href="{{ route('home') }}">
  @if (config('school.logo'))
    <img src="{{ \App\Support\AppLink::assetOrUrl(config('school.logo')) }}" alt="{{ config('school.name') }} logo" style="width:32px;height:32px;border-radius:10px;object-fit:cover;">
  @endif
  <span>{{ config('school.name') }}</span>
</a>
