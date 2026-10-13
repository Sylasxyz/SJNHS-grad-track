<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/app_config.php';

$user = current_user();
$role = (string)($user['Role'] ?? '');

function rail_href(string $route): string
{
    return app_url($route);
}

function rail_active(string $route): bool
{
    return app_route_is($route);
}
?>

<aside class="dashboard-left-rail">
  <button type="button" class="dashboard-rail-logo dashboard-rail-logo-btn" data-dashboard-menu-toggle aria-label="Toggle navigation menu">
    <i class="bi bi-mortarboard-fill"></i>
  </button>
  <div class="dashboard-rail-icons">
    <a href="<?= htmlspecialchars(rail_href('dashboard.php')) ?>" class="dashboard-rail-icon <?= rail_active('dashboard.php') ? 'is-active' : '' ?>">
      <i class="bi bi-house-door-fill"></i><span class="dashboard-rail-label">Dashboard</span>
    </a>
    <a href="<?= htmlspecialchars(rail_href('announcements.php')) ?>" class="dashboard-rail-icon <?= rail_active('announcements.php') ? 'is-active' : '' ?>">
      <i class="bi bi-megaphone"></i><span class="dashboard-rail-label">Announcements</span>
    </a>

    <?php if ($role === 'Student'): ?>
      <a href="<?= htmlspecialchars(rail_href('messages.php')) ?>" class="dashboard-rail-icon <?= rail_active('messages.php') ? 'is-active' : '' ?>">
        <i class="bi bi-chat-dots"></i><span class="dashboard-rail-label">Messages</span>
      </a>
      <a href="<?= htmlspecialchars(rail_href('profile.php')) ?>" class="dashboard-rail-icon <?= rail_active('profile.php') ? 'is-active' : '' ?>">
        <i class="bi bi-person-badge"></i><span class="dashboard-rail-label">My Graduate Profile</span>
      </a>
    <?php endif; ?>

    <?php if ($role === 'Instructor' || $role === 'Admin'): ?>
      <a href="<?= htmlspecialchars(rail_href('messages.php')) ?>" class="dashboard-rail-icon <?= rail_active('messages.php') ? 'is-active' : '' ?>">
        <i class="bi bi-chat-dots"></i><span class="dashboard-rail-label">Messages</span>
      </a>
      <a href="<?= htmlspecialchars(rail_href('graduates.php')) ?>" class="dashboard-rail-icon <?= rail_active('graduates.php') ? 'is-active' : '' ?>">
        <i class="bi bi-search"></i><span class="dashboard-rail-label">Graduate Records</span>
      </a>
      <a href="<?= htmlspecialchars(rail_href('announcement_create.php')) ?>" class="dashboard-rail-icon <?= rail_active('announcement_create.php') ? 'is-active' : '' ?>">
        <i class="bi bi-pencil-square"></i><span class="dashboard-rail-label">Post Announcement</span>
      </a>
    <?php endif; ?>

    <?php if ($role === 'Instructor'): ?>
      <a href="<?= htmlspecialchars(rail_href('instructor/users.php')) ?>" class="dashboard-rail-icon <?= rail_active('instructor/users.php') ? 'is-active' : '' ?>">
        <i class="bi bi-person-gear"></i><span class="dashboard-rail-label">Manage Students</span>
      </a>
      <a href="<?= htmlspecialchars(rail_href('admin/reports.php')) ?>" class="dashboard-rail-icon <?= rail_active('admin/reports.php') ? 'is-active' : '' ?>">
        <i class="bi bi-file-earmark-bar-graph"></i><span class="dashboard-rail-label">Reports</span>
      </a>
      <a href="<?= htmlspecialchars(rail_href('analytics.php')) ?>" class="dashboard-rail-icon <?= rail_active('analytics.php') ? 'is-active' : '' ?>">
        <i class="bi bi-graph-up-arrow"></i><span class="dashboard-rail-label">Analytics</span>
      </a>
    <?php endif; ?>

    <?php if ($role === 'Admin'): ?>
      <a href="<?= htmlspecialchars(rail_href('admin/users.php')) ?>" class="dashboard-rail-icon <?= rail_active('admin/users.php') ? 'is-active' : '' ?>">
        <i class="bi bi-people"></i><span class="dashboard-rail-label">Manage Users</span>
      </a>
      <a href="<?= htmlspecialchars(rail_href('admin/reports.php')) ?>" class="dashboard-rail-icon <?= (rail_active('admin/reports.php')) ? 'is-active' : '' ?>">
        <i class="bi bi-file-earmark-bar-graph"></i><span class="dashboard-rail-label">Reports</span>
      </a>
      <a href="<?= htmlspecialchars(rail_href('analytics.php')) ?>" class="dashboard-rail-icon <?= rail_active('analytics.php') ? 'is-active' : '' ?>">
        <i class="bi bi-graph-up-arrow"></i><span class="dashboard-rail-label">Analytics</span>
      </a>
    <?php endif; ?>
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
