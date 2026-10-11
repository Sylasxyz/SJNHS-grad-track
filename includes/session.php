<?php
declare(strict_types=1);

/** Start PHP session with cookies suited for HTTPS behind Railway/proxies. */
function app_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        session_set_cookie_params(0, '/; samesite=Lax', '', $secure, true);
    }

    session_start();
}

function pull_flash_error(): string
{
    if (empty($_SESSION['flash_error'])) {
        return '';
    }
    $msg = (string)$_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
    return $msg;
}
