<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\Concerns\HandlesEmbed;
use App\Http\Controllers\Controller;
use App\Models\UserAccount;
use App\Services\LoginThrottle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    use HandlesEmbed;

    public function show(Request $request): View|RedirectResponse
    {
        if (Auth::check()) {
            return $this->isEmbed($request)
                ? $this->topWindowRedirect(route('dashboard'))
                : redirect()->route('dashboard');
        }

        return view('auth.login', ['embed' => $this->isEmbed($request)]);
    }

    public function store(Request $request, LoginThrottle $throttle): View|RedirectResponse
    {
        if (Auth::check()) {
            return $this->isEmbed($request)
                ? $this->topWindowRedirect(route('dashboard'))
                : redirect()->route('dashboard');
        }

        $username = trim((string) $request->input('username', ''));
        $password = (string) $request->input('password', '');
        $failKey = $username !== '' ? $username : '(blank)';
        $max = $throttle->maxFailures();

        // DB failures make the lockout work across browsers and devices.
        $dbFailures = $throttle->recentFailures($username);
        if (! $throttle->isLockedOut() && $dbFailures >= $max) {
            $throttle->lock();
        } elseif ($throttle->isLockedOut() && $dbFailures < $max && $throttle->sessionAttempts() < $max) {
            // Failure window cooled down (or an admin cleared attempts), so allow a retry.
            $throttle->clear();
        }

        if ($throttle->isLockedOut()) {
            // Recording another failure here would extend the DB lock window forever.
            return $this->failed($request, $throttle, $username,
                "Too many failed attempts. Please wait {$throttle->secondsRemaining()} second(s) before trying again.");
        }

        $user = UserAccount::query()
            ->where('Username', $username)
            ->orWhere('Student_No', $username)
            ->orWhere('Employee_ID', $username)
            ->first();

        if ($username === '' || ! $user || ! password_verify($password, (string) $user->Password)) {
            $throttle->record($failKey, false);
            $sessionAttempts = $throttle->incrementSessionAttempts();
            $totalFailures = $throttle->recentFailures($failKey);

            if ($sessionAttempts >= $max || $totalFailures >= $max) {
                $throttle->lock();
                $throttle->resetSessionAttempts();

                return $this->failed($request, $throttle, $username,
                    "Too many failed attempts. Login is locked for {$throttle->lockoutMinutes()} minutes.");
            }

            $left = max(1, $max - max($sessionAttempts, $totalFailures));

            return $this->failed($request, $throttle, $username,
                "Invalid Student ID / Employee ID or password. {$left} attempt(s) left before lockout.");
        }

        $statusError = match (true) {
            $user->Role === 'Principal' => 'This account type is no longer supported. Contact the administrator.',
            $user->Status === UserAccount::STATUS_PENDING => 'Your registration is pending verification. Please wait for an Admin to approve your account.',
            ! $user->isActive() => 'Account is not active. Please contact the administrator.',
            default => null,
        };

        if ($statusError !== null) {
            $throttle->record($username, false);

            return $this->failed($request, $throttle, $username, $statusError);
        }

        $throttle->record($username, true);
        $throttle->clear();

        Auth::login($user);
        $request->session()->regenerate();

        return $this->isEmbed($request)
            ? $this->topWindowRedirect(route('dashboard'))
            : redirect()->route('dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    private function failed(Request $request, LoginThrottle $throttle, string $username, string $message): RedirectResponse
    {
        // Warn on the last two attempts before the account is locked.
        $remaining = $username !== '' ? $throttle->maxFailures() - $throttle->recentFailures($username) : 0;

        return redirect($this->embedRoute($request, 'login'))
            ->withInput($request->only('username'))
            ->with('error', $message)
            ->with('attempts_remaining', $remaining > 0 && $remaining <= 2 ? $remaining : null);
    }
}
