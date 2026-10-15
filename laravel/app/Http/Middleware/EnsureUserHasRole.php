<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('role:admin') or ->middleware('role:admin,instructor').
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $userRole = strtolower((string) $request->user()?->Role);
        $allowed = array_map('strtolower', $roles);

        if (! in_array($userRole, $allowed, true)) {
            abort(403);
        }

        return $next($request);
    }
}
