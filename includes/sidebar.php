<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/app_config.php';

$user = current_user();
$role = (string)($user['Role'] ?? '');
?>

<div class="app-sidebar">
  <div class="app-sidebar-title">
    Menu (<?= htmlspecialchars($role) ?>)
  </div>

  <div class="p-2">
    <a class="app-sidebar-link <?= app_route_is('dashboard') ? 'active' : '' ?>"
       href="<?= htmlspecialchars(app_url('dashboard')) ?>">
      <i class="bi bi-house"></i>
      <span>Dashboard</span>
    </a>

    <a class="app-sidebar-link <?= app_route_is('announcements') ? 'active' : '' ?>"
       href="<?= htmlspecialchars(app_url('announcements')) ?>">
      <i class="bi bi-megaphone"></i>
      <span>Announcements</span>
    </a>

    <a class="app-sidebar-link <?= app_route_is('messages') ? 'active' : '' ?>"
       href="<?= htmlspecialchars(app_url('messages')) ?>">
      <i class="bi bi-chat-dots"></i>
      <span>Messages</span>
    </a>

    <?php if ($role === 'Student'): ?>
      <a class="app-sidebar-link <?= app_route_is('profile') ? 'active' : '' ?>"
         href="<?= htmlspecialchars(app_url('profile')) ?>">
        <i class="bi bi-person-workspace"></i>
        <span>My Graduate Profile</span>
      </a>
    <?php endif; ?>

    <?php if ($role === 'Instructor' || $role === 'Admin'): ?>
      <a class="app-sidebar-link <?= app_route_is('graduates') ? 'active' : '' ?>"
         href="<?= htmlspecialchars(app_url('graduates')) ?>">
        <i class="bi bi-search"></i>
        <span>Graduate Records</span>
      </a>
      <a class="app-sidebar-link <?= app_route_is('announcement_create') ? 'active' : '' ?>"
         href="<?= htmlspecialchars(app_url('announcement_create')) ?>">
        <i class="bi bi-pencil-square"></i>
        <span>Post Announcement</span>
      </a>
    <?php endif; ?>

    <?php if ($role === 'Instructor'): ?>
      <a class="app-sidebar-link <?= app_route_is('instructor/users') ? 'active' : '' ?>"
         href="<?= htmlspecialchars(app_url('instructor/users')) ?>">
        <i class="bi bi-person-gear"></i>
        <span>Manage Student Accounts</span>
      </a>
      <a class="app-sidebar-link <?= app_route_is('admin/reports') ? 'active' : '' ?>"
         href="<?= htmlspecialchars(app_url('admin/reports')) ?>">
        <i class="bi bi-file-earmark-bar-graph"></i>
        <span>Reports</span>
      </a>
      <a class="app-sidebar-link <?= app_route_is('analytics') ? 'active' : '' ?>"
         href="<?= htmlspecialchars(app_url('analytics')) ?>">
        <i class="bi bi-graph-up-arrow"></i>
        <span>Analytics</span>
      </a>
    <?php endif; ?>

    <?php if ($role === 'Admin'): ?>
      <a class="app-sidebar-link <?= app_route_is('admin/users') ? 'active' : '' ?>"
         href="<?= htmlspecialchars(app_url('admin/users')) ?>">
        <i class="bi bi-people"></i>
        <span>Verify / Manage Users</span>
      </a>
      <a class="app-sidebar-link <?= app_route_is('admin/reports') ? 'active' : '' ?>"
         href="<?= htmlspecialchars(app_url('admin/reports')) ?>">
        <i class="bi bi-file-earmark-bar-graph"></i>
        <span>Reports</span>
      </a>
      <a class="app-sidebar-link <?= app_route_is('analytics') ? 'active' : '' ?>"
         href="<?= htmlspecialchars(app_url('analytics')) ?>">
        <i class="bi bi-graph-up-arrow"></i>
        <span>Analytics</span>
      </a>
    <?php endif; ?>
  </div>
</div>
