<?php
declare(strict_types=1);

/**
 * Pretty URLs (no .php in the browser). Works on XAMPP (Apache + .htaccess) and Railway (router.php).
 */

function app_base_path(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    if (function_exists('app_env')) {
        $fromEnv = app_env('APP_BASE_PATH');
        if ($fromEnv !== null && $fromEnv !== '') {
            $cached = rtrim(str_replace('\\', '/', $fromEnv), '/');
            return $cached;
        }
    }

    $docRoot = str_replace('\\', '/', (string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $appRoot = str_replace('\\', '/', (string)(realpath(__DIR__ . '/..') ?: ''));
    if ($docRoot !== '' && $appRoot !== '' && str_starts_with($appRoot, $docRoot)) {
        $cached = rtrim(substr($appRoot, strlen($docRoot)), '/');
        return $cached;
    }

    $cached = '';
    return $cached;
}

/** Current route path relative to app base, without leading slash (no .php). */
function app_request_path(): string
{
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $uri = rawurldecode((string)$uri);
    $base = app_base_path();
    if ($base !== '' && str_starts_with($uri, $base)) {
        $uri = substr($uri, strlen($base)) ?: '/';
    }
    $uri = trim($uri, '/');
    if ($uri === 'index' || str_ends_with($uri, '/index')) {
        $uri = preg_replace('#/index$#', '', $uri) ?? $uri;
        $uri = trim((string)$uri, '/');
    }
    return preg_replace('/\.php$/i', '', $uri) ?? $uri;
}

function app_route_is(string $route): bool
{
    $route = trim(preg_replace('/\.php$/i', '', $route), '/');
    $current = app_request_path();
    if ($route === '' || $route === 'index') {
        return $current === '' || $current === 'index';
    }
    return $current === $route;
}

/**
 * @param array<string, scalar|null> $query
 */
function app_url(string $route = '', array $query = []): string
{
    $hash = '';
    if (str_contains($route, '#')) {
        [$route, $hashPart] = explode('#', $route, 2);
        $hash = '#' . $hashPart;
    }

    $route = trim(str_replace('\\', '/', $route), '/');
    $route = preg_replace('/\.php$/i', '', $route) ?? $route;

    if ($route === '' || strtolower($route) === 'index') {
        $url = app_base_path() . '/';
    } else {
        $url = app_base_path() . '/' . $route;
    }

    if ($query !== []) {
        $url .= '?' . http_build_query($query);
    }

    return $url . $hash;
}

function app_url_embed(string $route, bool $embed, array $query = []): string
{
    if ($embed) {
        $query['embed'] = 1;
    }
    return app_url($route, $query);
}

function app_asset(string $path): string
{
    return app_base_path() . '/' . ltrim(str_replace('\\', '/', $path), '/');
}

/**
 * @param array<string, scalar|null> $query
 */
function app_redirect(string $route, array $query = [], int $status = 302): never
{
    header('Location: ' . app_url($route, $query), true, $status);
    exit;
}
