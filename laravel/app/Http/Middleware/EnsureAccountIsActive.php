<?php

namespace App\Http\Middleware;

use App\Models\UserAccount;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs out users whose account was deactivated, archived, or moved to the
 * retired Principal role after they logged in.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof UserAccount && (! $user->isActive() || $user->Role === 'Principal')) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('error', 'Account is not active. Please contact the administrator.');
        }

        return $next($request);
    }
}
