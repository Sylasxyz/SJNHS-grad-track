<?php
declare(strict_types=1);

require_once __DIR__ . '/session.php';
app_session_start();

require_once __DIR__ . '/url_helpers.php';

function is_logged_in(): bool
{
    return isset($_SESSION['user']) && is_array($_SESSION['user']);
}

function current_user(): ?array
{
    return is_logged_in() ? $_SESSION['user'] : null;
}

function require_login(): void
{
    if (!is_logged_in()) {
        app_redirect('login');
    }
}

function require_roles(array $roles): void
{
    require_login();
    $user = current_user();
    $role = $user['Role'] ?? '';
    if (!in_array($role, $roles, true)) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}

/** Current user's role string. */
function current_role(): string
{
    $user = current_user();
    return (string)($user['Role'] ?? '');
}

function is_principal(): bool
{
    return current_role() === 'Principal';
}

/** Admin / Instructor — can change data. */
function can_manage_records(): bool
{
    return in_array(current_role(), ['Admin', 'Instructor'], true);
}

/** Admin / Instructor / Principal — can view oversight pages. */
function can_view_oversight(): bool
{
    return in_array(current_role(), ['Admin', 'Instructor', 'Principal'], true);
}

function redirect_logged_in_user(): void
{
    if (is_logged_in()) {
        app_redirect('dashboard');
    }
}

function login_user(array $userRow): void
{
    session_regenerate_id(true);
    $first = trim((string)($userRow['First_Name'] ?? ''));
    $last = trim((string)($userRow['Last_Name'] ?? ''));
    $mi = strtoupper(trim((string)($userRow['Middle_Initial'] ?? '')));
    $suffix = strtoupper(trim((string)($userRow['Name_Suffix'] ?? '')));
    $parts = array_filter([$first, $mi !== '' ? $mi . '.' : '', $last, $suffix], static fn($p) => $p !== '');
    $fullName = trim(implode(' ', $parts));
    $_SESSION['user'] = [
        'User_ID' => (int)$userRow['User_ID'],
        'First_Name' => $first,
        'Last_Name' => $last,
        'Middle_Initial' => $mi,
        'Name_Suffix' => $suffix,
        'Full_Name' => $fullName !== '' ? $fullName : trim($first . ' ' . $last),
        'Username' => (string)$userRow['Username'],
        'Email' => (string)($userRow['Email'] ?? ''),
        'Role' => (string)$userRow['Role'],
        'Status' => (string)$userRow['Status'],
    ];
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
    }
    session_destroy();
}

