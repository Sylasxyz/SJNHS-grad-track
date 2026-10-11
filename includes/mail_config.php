<?php
declare(strict_types=1);

/**
 * Mail / SMTP settings (XAMPP + production).
 *
 * Local: put your Gmail App Password in mail_config.local.php (gitignored).
 * Gmail: 2-Step Verification ON → App password (16 characters).
 */

$MAIL_FROM_EMAIL = 'gradtrack.sjnhs@gmail.com';
$MAIL_FROM_NAME  = 'San Josef NHS Graduate Tracking';

$SMTP_ENABLED = true;
$SMTP_HOST    = 'smtp.gmail.com';
$SMTP_PORT    = 587;
$SMTP_SECURE  = 'tls';   // 'tls' (port 587) or 'ssl' (port 465)
$SMTP_USER    = 'gradtrack.sjnhs@gmail.com';
$SMTP_PASS    = '';
$SMTP_TIMEOUT = 25;

$MAIL_ALWAYS_LOG = true;
$MAIL_SHOW_RESET_LINK_ON_FAILURE = false;

$localMail = __DIR__ . '/mail_config.local.php';
if (is_file($localMail)) {
    require $localMail;
}

// Optional hosting env vars (only when non-empty — does not wipe local.php values)
if (!function_exists('app_env')) {
    require_once __DIR__ . '/env.php';
}

$envMailMap = [
    'MAIL_FROM_EMAIL' => 'string',
    'MAIL_FROM_NAME' => 'string',
    'SMTP_HOST' => 'string',
    'SMTP_SECURE' => 'string',
    'SMTP_USER' => 'string',
    'SMTP_PASS' => 'string',
];
foreach ($envMailMap as $key => $_type) {
    $v = app_env($key);
    if ($v !== null && $v !== '') {
        $$key = trim((string)$v);
    }
}
if (($p = app_env('SMTP_PORT')) !== null && $p !== '' && is_numeric($p)) {
    $SMTP_PORT = (int)$p;
}
if (($t = app_env('SMTP_TIMEOUT')) !== null && $t !== '' && is_numeric($t)) {
    $SMTP_TIMEOUT = (int)$t;
}
$envEnabled = app_env('SMTP_ENABLED');
if ($envEnabled !== null && $envEnabled !== '') {
    $SMTP_ENABLED = in_array(strtolower($envEnabled), ['1', 'true', 'yes', 'on'], true);
}
$envShowReset = app_env('MAIL_SHOW_RESET_LINK_ON_FAILURE');
if ($envShowReset !== null && $envShowReset !== '') {
    $MAIL_SHOW_RESET_LINK_ON_FAILURE = in_array(strtolower($envShowReset), ['1', 'true', 'yes', 'on'], true);
}
