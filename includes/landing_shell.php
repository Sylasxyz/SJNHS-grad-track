<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/app_config.php';

function landing_asset_or_url(string $value): string
{
    $v = trim($value);
    if ($v === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $v)) {
        return $v;
    }
    return ltrim($v, '/');
}

