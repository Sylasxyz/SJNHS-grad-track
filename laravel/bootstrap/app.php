<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'active' => EnsureAccountIsActive::class,
        ]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));

        // Railway terminates HTTPS at its edge proxy; trust it so URLs, redirects and cookies use https.
        if (filled(env('RAILWAY_ENVIRONMENT')) || filled(env('RAILWAY_PROJECT_ID')) || filled(env('RAILWAY_SERVICE_NAME'))) {
            $middleware->trustProxies(at: '*');
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['new_password']);

        // An expired form (CSRF token mismatch, already mapped to HTTP 419) sends the user back with a readable message.
        $exceptions->render(function (HttpException $e, Request $request) {
            if (! $e->getPrevious() instanceof TokenMismatchException) {
                return null;
            }

            // Only go back to a same-host Referer (no open redirect); otherwise to register or login.
            $referer = (string) $request->headers->get('referer', '');
            $target = $referer !== '' && strcasecmp((string) parse_url($referer, PHP_URL_HOST), $request->getHost()) === 0
                ? $referer
                : route(str_contains($request->path(), 'register') ? 'register' : 'login');

            return redirect()->to($target)
                ->withInput($request->except(['password', 'confirm_password', '_token']))
                ->with('error', 'Your session expired or the page was outdated. Please refresh and try again.');
        });
    })->create();
