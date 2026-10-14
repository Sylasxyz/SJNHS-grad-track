@use('App\Support\AppLink')
@php
    $role = (string) auth()->user()?->Role;
    $isStaff = in_array($role, ['Instructor', 'Admin'], true);

    // [route name once converted, legacy page, icon, label, optional active-route pattern]
    $links = [['dashboard', 'dashboard', 'bi-house-door-fill', 'Dashboard'],
              ['announcements', 'announcements', 'bi-megaphone', 'Announcements'],
              ['messages', 'messages', 'bi-chat-dots', 'Messages', 'messages*']];

    if ($role === 'Student') {
        $links[] = ['student.profile', 'profile', 'bi-person-badge', 'My Graduate Profile'];
    }
    if ($isStaff) {
        $links[] = ['graduates', 'graduates', 'bi-search', 'Graduate Records'];
        $links[] = ['announcements.create', 'announcement_create', 'bi-pencil-square', 'Post Announcement'];
    }
    if ($role === 'Instructor') {
        $links[] = ['instructor.users', 'instructor/users', 'bi-person-gear', 'Manage Students'];
    }
    if ($role === 'Admin') {
        $links[] = ['admin.users', 'admin/users', 'bi-people', 'Manage Users'];
    }
    if ($isStaff) {
        $links[] = ['reports', 'admin/reports', 'bi-file-earmark-bar-graph', 'Reports'];
        $links[] = ['analytics', 'analytics', 'bi-graph-up-arrow', 'Analytics'];
    }
@endphp

<aside class="dashboard-left-rail">
  <button type="button" class="dashboard-rail-logo dashboard-rail-logo-btn" data-dashboard-menu-toggle aria-label="Toggle navigation menu">
    <i class="bi bi-mortarboard-fill"></i>
  </button>
  <div class="dashboard-rail-icons">
    @foreach ($links as $link)
      @php([$routeName, $legacyPath, $icon, $label] = $link)
      <a href="{{ AppLink::to($routeName, $legacyPath) }}" class="dashboard-rail-icon {{ AppLink::isActive($link[4] ?? $routeName) ? 'is-active' : '' }}">
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
