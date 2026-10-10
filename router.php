<?php
declare(strict_types=1);

/**
 * PHP built-in server router (Railway). Apache uses .htaccess instead.
 */
require_once __DIR__ . '/includes/url_helpers.php';

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri = rawurldecode((string)$uri);

$base = app_base_path();
if ($base !== '' && str_starts_with($uri, $base)) {
    $uri = substr($uri, strlen($base)) ?: '/';
}

$query = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY) ?? '');
$qs = $query !== '' ? '?' . $query : '';

// Redirect direct *.php URLs to extensionless (hide script names).
if (preg_match('#^(.+?)\.php$#', $uri, $m)) {
    $target = $m[1] === '/index' || $m[1] === 'index' ? '/' : $m[1];
    header('Location: ' . app_base_path() . $target . $qs, true, 301);
    exit;
}

if ($uri === '/' || $uri === '') {
    require __DIR__ . '/index.php';
    return true;
}

$relative = ltrim($uri, '/');
$docRoot = __DIR__;

$candidate = $docRoot . '/' . $relative;
if ($relative !== '' && is_file($candidate) && !str_ends_with(strtolower($relative), '.php')) {
    return false;
}

$phpFile = $docRoot . '/' . $relative . '.php';
if (is_file($phpFile)) {
    require $phpFile;
    return true;
}

$indexInDir = $docRoot . '/' . $relative . '/index.php';
if (is_dir($docRoot . '/' . $relative) && is_file($indexInDir)) {
    require $indexInDir;
    return true;
}

http_response_code(404);
echo '404 Not Found';
return true;
