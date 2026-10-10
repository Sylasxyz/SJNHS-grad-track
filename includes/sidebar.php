<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$user = current_user();
$role = (string)($user['Role'] ?? '');
$currentPath = trim((string)($_SERVER['REQUEST_URI'] ?? ''), '/');

// When the current page is inside a subfolder (e.g. /admin or /instructor),
// build links relative to that folder.
$prefix = (strpos($currentPath, 'admin/') !== false || strpos($currentPath, 'instructor/') !== false) ? '../' : '';

function sidebar_href(string $href): string {
    // Build a correct relative path from the current page (root vs /admin).
    global $prefix;
    return $prefix . $href;
}

function sidebar_is_active(string $href): bool {
    // $currentPath typically looks like "Capstone/admin/reports.php" or "Capstone/dashboard.php"
    global $currentPath;
    return str_ends_with($currentPath, ltrim($href, '/'));
}
?>

<div class="app-sidebar">
  <div class="app-sidebar-title">
    Menu (<?= htmlspecialchars($role) ?>)
  </div>

  <div class="p-2">
    <a class="app-sidebar-link <?= (sidebar_is_active('dashboard.php') ? 'active' : '') ?>"
       href="<?= htmlspecialchars(sidebar_href('dashboard.php')) ?>">
      <i class="bi bi-house"></i>
      <span>Dashboard</span>
    </a>

    <a class="app-sidebar-link <?= (sidebar_is_active('announcements.php') ? 'active' : '') ?>"
       href="<?= htmlspecialchars(sidebar_href('announcements.php')) ?>">
      <i class="bi bi-megaphone"></i>
      <span>Announcements</span>
    </a>

    <?php if ($role !== 'Principal'): ?>
      <a class="app-sidebar-link <?= (sidebar_is_active('messages.php') ? 'active' : '') ?>"
         href="<?= htmlspecialchars(sidebar_href('messages.php')) ?>">
        <i class="bi bi-chat-dots"></i>
        <span>Messages</span>
      </a>
    <?php endif; ?>

    <?php if ($role === 'Student'): ?>
      <a class="app-sidebar-link <?= (sidebar_is_active('profile.php') ? 'active' : '') ?>"
         href="<?= htmlspecialchars(sidebar_href('profile.php')) ?>">
        <i class="bi bi-person-workspace"></i>
        <span>My Graduate Profile</span>
      </a>
    <?php endif; ?>

    <?php if ($role === 'Principal'): ?>
      <a class="app-sidebar-link <?= (sidebar_is_active('graduates.php') ? 'active' : '') ?>"
         href="<?= htmlspecialchars(sidebar_href('graduates.php')) ?>">
        <i class="bi bi-search"></i>
        <span>Graduate Records</span>
      </a>
      <a class="app-sidebar-link <?= (sidebar_is_active('analytics.php') ? 'active' : '') ?>"
         href="<?= htmlspecialchars(sidebar_href('analytics.php')) ?>">
        <i class="bi bi-graph-up-arrow"></i>
        <span>Analytics</span>
      </a>
      <a class="app-sidebar-link <?= (sidebar_is_active('admin/reports.php') ? 'active' : '') ?>"
         href="<?= htmlspecialchars(sidebar_href('admin/reports.php')) ?>">
        <i class="bi bi-file-earmark-bar-graph"></i>
        <span>Reports</span>
      </a>
    <?php endif; ?>

    <?php if ($role === 'Instructor' || $role === 'Admin'): ?>
      <a class="app-sidebar-link <?= (sidebar_is_active('graduates.php') ? 'active' : '') ?>"
         href="<?= htmlspecialchars(sidebar_href('graduates.php')) ?>">
        <i class="bi bi-search"></i>
        <span>Graduate Records</span>
      </a>
      <a class="app-sidebar-link <?= (sidebar_is_active('announcement_create.php') ? 'active' : '') ?>"
         href="<?= htmlspecialchars(sidebar_href('announcement_create.php')) ?>">
        <i class="bi bi-pencil-square"></i>
        <span>Post Announcement</span>
      </a>
    <?php endif; ?>

    <?php if ($role === 'Instructor'): ?>
      <a class="app-sidebar-link <?= (sidebar_is_active('instructor/users.php') ? 'active' : '') ?>"
         href="<?= htmlspecialchars(sidebar_href('instructor/users.php')) ?>">
        <i class="bi bi-person-gear"></i>
        <span>Manage Student Accounts</span>
      </a>
      <a class="app-sidebar-link <?= (sidebar_is_active('admin/reports.php') ? 'active' : '') ?>"
         href="<?= htmlspecialchars(sidebar_href('admin/reports.php')) ?>">
        <i class="bi bi-file-earmark-bar-graph"></i>
        <span>Reports</span>
      </a>
      <a class="app-sidebar-link <?= (sidebar_is_active('analytics.php') ? 'active' : '') ?>"
         href="<?= htmlspecialchars(sidebar_href('analytics.php')) ?>">
        <i class="bi bi-graph-up-arrow"></i>
        <span>Analytics</span>
      </a>
    <?php endif; ?>

    <?php if ($role === 'Admin'): ?>
      <a class="app-sidebar-link <?= (sidebar_is_active('admin/users.php') ? 'active' : '') ?>"
         href="<?= htmlspecialchars(sidebar_href('admin/users.php')) ?>">
        <i class="bi bi-people"></i>
        <span>Verify / Manage Users</span>
      </a>
      <a class="app-sidebar-link <?= (sidebar_is_active('admin/reports.php') ? 'active' : '') ?>"
         href="<?= htmlspecialchars(sidebar_href('admin/reports.php')) ?>">
        <i class="bi bi-file-earmark-bar-graph"></i>
        <span>Reports</span>
      </a>
      <a class="app-sidebar-link <?= (sidebar_is_active('analytics.php') ? 'active' : '') ?>"
         href="<?= htmlspecialchars(sidebar_href('analytics.php')) ?>">
        <i class="bi bi-graph-up-arrow"></i>
        <span>Analytics</span>
      </a>
    <?php endif; ?>
  </div>
</div>
