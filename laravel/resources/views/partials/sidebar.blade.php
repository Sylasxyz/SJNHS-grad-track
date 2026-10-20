@use('App\Support\AppLink')
@php
    $role = (string) auth()->user()?->Role;
    $isStaff = in_array($role, ['Instructor', 'Admin'], true);

    // [route name, icon, label, optional active-route pattern]
    $links = [['dashboard', 'bi-house-door-fill', 'Dashboard'],
              ['announcements', 'bi-megaphone', 'Announcements'],
              ['messages', 'bi-chat-dots', 'Messages', 'messages*']];

    if ($role === 'Student') {
        $links[] = ['student.profile', 'bi-person-badge', 'My Graduate Profile'];
    }
    if ($isStaff) {
        $links[] = ['graduates', 'bi-search', 'Graduate Records', 'graduates*'];
        $links[] = ['announcements.create', 'bi-pencil-square', 'Post Announcement'];
    }
    if ($role === 'Instructor') {
        $links[] = ['instructor.users', 'bi-person-gear', 'Manage Students'];
    }
    if ($role === 'Admin') {
        $links[] = ['admin.users', 'bi-people', 'Manage Users'];
    }
    if ($isStaff) {
        $links[] = ['reports', 'bi-file-earmark-bar-graph', 'Reports', 'reports*'];
        $links[] = ['analytics', 'bi-graph-up-arrow', 'Analytics'];
    }
@endphp

<aside class="dashboard-left-rail" aria-label="Main navigation">
  <button type="button" class="dashboard-rail-logo dashboard-rail-logo-btn dashboard-rail-desktop-toggle" data-dashboard-menu-toggle aria-label="Toggle navigation menu" title="Expand menu">
    <i class="bi bi-mortarboard-fill"></i>
  </button>
  <div class="dashboard-rail-icons">
    @foreach ($links as $link)
      @php([$routeName, $icon, $label] = $link)
      <a href="{{ route($routeName) }}" class="dashboard-rail-icon {{ AppLink::isActive($link[3] ?? $routeName) ? 'is-active' : '' }}" title="{{ $label }}">
        <i class="bi {{ $icon }}"></i><span class="dashboard-rail-label">{{ $label }}</span>
      </a>
    @endforeach
  </div>
</aside>

<script>
  (function () {
    const shell = document.currentScript?.closest('.dashboard-shell');
    const btn = shell?.querySelector('[data-dashboard-menu-toggle]');
    if (!shell || !btn) return;
    btn.addEventListener('click', () => {
      shell.classList.toggle('is-nav-open');
    });
    shell.querySelectorAll('.dashboard-rail-icon').forEach((a) => {
      a.addEventListener('click', () => shell.classList.remove('is-nav-open'));
    });
  })();
</script>
