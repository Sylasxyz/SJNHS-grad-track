<?php
declare(strict_types=1);

/**
 * Read environment variables (Railway, Docker, or local .env via docker-compose).
 */
function app_env(string $key, ?string $default = null): ?string
{
    $val = getenv($key);
    if ($val === false || $val === '') {
        $val = $_ENV[$key] ?? $_SERVER[$key] ?? false;
    }
    if ($val === false || $val === '') {
        return $default;
    }
    return (string)$val;
}

function app_env_bool(string $key, bool $default = false): bool
{
    $v = app_env($key);
    if ($v === null) {
        return $default;
    }
    return in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true);
}

function app_env_int(string $key, int $default): int
{
    $v = app_env($key);
    if ($v === null || !is_numeric($v)) {
        return $default;
    }
    return (int)$v;
}

/**
 * @return array{host:string,port:int,name:string,user:string,pass:string,charset:string}
 */
function app_db_settings(): array
{
    $charset = app_env('DB_CHARSET', 'utf8mb4') ?? 'utf8mb4';

    $host = app_env('MYSQLHOST') ?? app_env('DB_HOST', '127.0.0.1') ?? '127.0.0.1';
    $port = app_env_int('MYSQLPORT', app_env_int('DB_PORT', 3306));
    $name = app_env('MYSQLDATABASE') ?? app_env('DB_NAME', 'graduate_tracking') ?? 'graduate_tracking';
    $user = app_env('MYSQLUSER') ?? app_env('DB_USER', 'root') ?? 'root';
    $pass = app_env('MYSQLPASSWORD') ?? app_env('DB_PASS', '') ?? '';

    $url = app_env('DATABASE_URL') ?? app_env('MYSQL_URL');
    if ($url !== null && str_starts_with($url, 'mysql://')) {
        $parts = parse_url($url);
        if (is_array($parts)) {
            if (!empty($parts['host'])) {
                $host = (string)$parts['host'];
            }
            if (!empty($parts['port'])) {
                $port = (int)$parts['port'];
            }
            if (!empty($parts['path'])) {
                $name = ltrim((string)$parts['path'], '/');
            }
            if (isset($parts['user'])) {
                $user = rawurldecode((string)$parts['user']);
            }
            if (isset($parts['pass'])) {
                $pass = rawurldecode((string)$parts['pass']);
            }
        }
    }

    return [
        'host' => $host,
        'port' => $port,
        'name' => $name,
        'user' => $user,
        'pass' => $pass,
        'charset' => $charset,
    ];
}

function app_db_dsn(array $settings): string
{
    $host = $settings['host'];
    $port = (int)$settings['port'];
    $name = $settings['name'];
    $charset = $settings['charset'];

    if ($port > 0 && $port !== 3306) {
        return "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";
    }

    return "mysql:host={$host};dbname={$name};charset={$charset}";
}
