@php
    $validationMessages = collect($errors->getBags())->flatMap(fn ($bag) => $bag->all())->unique();
@endphp
@if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
@if ($validationMessages->count() === 1)
  <div class="alert alert-danger">{{ $validationMessages->first() }}</div>
@elseif ($validationMessages->isNotEmpty())
  <div class="alert alert-danger"><ul class="mb-0">@foreach ($validationMessages as $message)<li>{{ $message }}</li>@endforeach</ul></div>
@endif
