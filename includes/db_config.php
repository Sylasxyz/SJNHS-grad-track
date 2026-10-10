<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';

$db = app_db_settings();
$DB_HOST = $db['host'];
$DB_NAME = $db['name'];
$DB_USER = $db['user'];
$DB_PASS = $db['pass'];
$DB_CHARSET = $db['charset'];

// Local XAMPP default: APP_DEBUG=1. Production (Railway): set APP_DEBUG=0
$DB_DEBUG = app_env_bool('APP_DEBUG', app_env_bool('DB_DEBUG', true));

$dsn = app_db_dsn($db);

try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    if ($DB_DEBUG) {
        echo 'Database connection failed: ' . htmlspecialchars($e->getMessage());
    } else {
        echo 'Database connection failed.';
    }
    exit;
}
