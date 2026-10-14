<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

/**
 * Links to a page that may or may not be converted yet: uses the Laravel
 * route when it exists, otherwise falls back to the legacy PHP page.
 */
class AppLink
{
    public static function to(string $routeName, string $legacyPath): string
    {
        if (Route::has($routeName)) {
            return route($routeName);
        }

        return self::legacy($legacyPath);
    }

    public static function legacy(string $path = ''): string
    {
        return config('school.legacy_url').'/'.ltrim($path, '/');
    }

    public static function isActive(string $routeName): bool
    {
        return request()->routeIs($routeName);
    }
}
