<?php

namespace App\Providers;

use App\Models\UserAccount;
use Illuminate\Auth\Access\Response;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // analytics.php: require_roles(['Admin', 'Instructor']).
        Gate::define('viewAnalytics', fn (UserAccount $user) => $user->isStaff()
            ? Response::allow()
            : Response::deny('Forbidden'));

        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        // Also limits guessing of the 6-digit reset code.
        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
    }
}
