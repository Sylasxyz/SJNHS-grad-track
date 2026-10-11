<?php
declare(strict_types=1);

require_once __DIR__ . '/session.php';
app_session_start();
if (!function_exists('app_url')) {
    require_once __DIR__ . '/url_helpers.php';
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_token'];
}

function csrf_validate(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], $token)) {
        $_SESSION['flash_error'] = 'Your session expired or the page was outdated. Please refresh and try again.';
        http_response_code(403);

        $back = (string)($_SERVER['HTTP_REFERER'] ?? '');
        if ($back !== '') {
            $refHost = parse_url($back, PHP_URL_HOST);
            $hereHost = (string)($_SERVER['HTTP_HOST'] ?? '');
            if ($refHost !== null && $refHost !== '' && strcasecmp($refHost, $hereHost) === 0) {
                header('Location: ' . $back);
                exit;
            }
        }

        $fallback = app_url('login');
        if (str_contains((string)($_SERVER['REQUEST_URI'] ?? ''), 'register')) {
            $fallback = app_url('register');
        }
        header('Location: ' . $fallback);
        exit;
    }
}

