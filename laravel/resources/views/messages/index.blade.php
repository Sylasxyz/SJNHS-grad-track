@use('App\Services\Messaging')
@extends('layouts.dashboard')

@section('title', 'Messages')

@section('dashboard-content')
  @php
    $isStudent = $me->isStudent();
    $composeLabel = $isStudent ? 'Message instructor or admin' : 'Message student or alumni';
  @endphp

  <div class="app-card p-3 mb-3">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
      <div>
        <h1 class="h4 mb-1">Messages</h1>
        <div class="text-muted small">
          @if ($isStudent)
            Contact instructors or admins inside the system.
          @else
            Message students and alumni inside the system.
          @endif
        </div>
      </div>
      <div class="d-flex gap-2">
        <a class="btn btn-sm btn-primary" href="{{ route('messages', ['compose' => 1]) }}">
          <i class="bi bi-pencil-square me-1"></i>New message
        </a>
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('dashboard') }}">Back</a>
      </div>
    </div>
  </div>

  @include('partials.flash')
  @if ($loadError)
    <div class="alert alert-danger">{{ $loadError }}</div>
  @endif

  <div class="messages-layout">
    <aside class="app-card messages-sidebar">
      <div class="messages-sidebar-head">
        <strong>Inbox</strong>
        @if ($unreadTotal > 0)
          <span class="badge text-bg-danger">{{ $unreadTotal }}</span>
        @endif
      </div>
      @if (! $conversations)
        <div class="text-muted small p-3">No conversations yet.</div>
      @else
        <div class="messages-conv-list">
          @foreach ($conversations as $c)
            <a class="messages-conv-item {{ $partnerId === $c['partner_id'] ? 'is-active' : '' }}"
               href="{{ route('messages.show', $c['partner_id']) }}">
              <div class="d-flex justify-content-between gap-2">
                <div class="messages-conv-name">{{ $c['display_name'] }}</div>
                @if ($c['unread_count'] > 0)
                  <span class="badge text-bg-danger">{{ $c['unread_count'] }}</span>
                @endif
              </div>
              <div class="messages-conv-sub">{{ $c['subtitle'] }}</div>
              <div class="messages-conv-preview">{{ mb_strlen($c['last_message']) > 80 ? mb_substr($c['last_message'], 0, 77).'…' : $c['last_message'] }}</div>
              <div class="messages-conv-time">{{ $c['last_sent_at'] }}</div>
            </a>
          @endforeach
        </div>
      @endif
    </aside>

    <section class="app-card messages-main">
      @if ($composeMode && ! $partner)
        <div class="messages-main-head">
          <h2 class="h6 mb-0">{{ $composeLabel }}</h2>
        </div>
        <div class="p-3">
          <form method="get" action="{{ route('messages') }}" class="mb-3">
            <input type="hidden" name="compose" value="1">
            <div class="input-group input-group-sm">
              <input class="form-control" type="search" name="q"
                     placeholder="{{ $isStudent ? 'Search by Instructor or Admin name' : 'Search by name or Student ID' }}"
                     value="{{ $search }}">
              <button class="btn btn-outline-secondary" type="submit">Search</button>
            </div>
          </form>

          @if ($messageable->isEmpty())
            <div class="alert alert-secondary mb-0">No matching recipients found.</div>
          @else
            <form method="post" action="{{ route('messages.store') }}" class="vstack gap-3">
              @csrf
              <div>
                <label class="form-label" for="recipient_id">Recipient</label>
                <select class="form-select" id="recipient_id" name="recipient_id" required>
                  <option value="">Select recipient</option>
                  @foreach ($messageable as $r)
                    <option value="{{ $r->User_ID }}" @selected((string) old('recipient_id') === (string) $r->User_ID)>
                      {{ Messaging::displayName($r).' — '.Messaging::subtitle($r) }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div>
                <label class="form-label" for="message_text">Message</label>
                <textarea class="form-control" id="message_text" name="message_text" rows="5" required maxlength="2000"
                  placeholder="Write your message…">{{ old('message_text') }}</textarea>
                <div class="form-text">Maximum 2000 characters.</div>
              </div>
              <div>
                <button class="btn btn-primary" type="submit">Send message</button>
                <a class="btn btn-link" href="{{ route('messages') }}">Cancel</a>
              </div>
            </form>
          @endif
        </div>

      @elseif ($partner)
        <div class="messages-main-head">
          <div>
            <h2 class="h6 mb-0">{{ Messaging::displayName($partner) }}</h2>
            <div class="text-muted small">{{ Messaging::subtitle($partner) }}</div>
          </div>
          <a class="btn btn-sm btn-outline-secondary" href="{{ route('messages', ['compose' => 1]) }}">New</a>
        </div>

        <div class="messages-thread" id="messagesThread">
          @forelse ($thread as $m)
            <div class="messages-bubble {{ (int) $m->Sender_ID === (int) $me->User_ID ? 'is-mine' : 'is-theirs' }}">
              <div class="messages-bubble-text">{!! nl2br(e($m->Message_Text)) !!}</div>
              <div class="messages-bubble-meta">{{ $m->getRawOriginal('Sent_At') }}</div>
            </div>
          @empty
            <div class="text-muted small text-center py-4">No messages yet. Say hello below.</div>
          @endforelse
        </div>

        <form method="post" action="{{ route('messages.reply', $partner) }}" class="messages-compose">
          @csrf
          <textarea class="form-control" name="message_text" rows="2" required maxlength="2000"
            placeholder="Type a reply…">{{ old('message_text') }}</textarea>
          <button class="btn btn-primary" type="submit">Send</button>
        </form>

      @else
        <div class="messages-empty">
          <i class="bi bi-chat-dots"></i>
          <h2 class="h6 mb-1">Select a conversation</h2>
          <p class="text-muted small mb-3">Or start a new message to {{ $isStudent ? 'an instructor/admin' : 'a student or alumni' }}.</p>
          <a class="btn btn-primary btn-sm" href="{{ route('messages', ['compose' => 1]) }}">New message</a>
        </div>
      @endif
    </section>
  </div>
@endsection

@push('scripts')
  <script>
    const thread = document.getElementById('messagesThread');
    if (thread) thread.scrollTop = thread.scrollHeight;
  </script>
@endpush
