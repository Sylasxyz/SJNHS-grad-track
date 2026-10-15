<?php

namespace App\Support;

/**
 * Small link helpers shared by the layouts.
 */
class AppLink
{
    /** School image settings may be a full URL or a path under public/. */
    public static function assetOrUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '' || preg_match('#^https?://#i', $value)) {
            return $value;
        }

        return asset(ltrim($value, '/'));
    }

    public static function isActive(string $routeName): bool
    {
        return request()->routeIs($routeName);
    }
}
