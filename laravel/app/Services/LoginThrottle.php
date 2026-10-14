<?php

namespace App\Services;

use App\Models\LoginAttempt;
use Illuminate\Http\Request;
use Throwable;

/**
 * Login lockout backed by the Login_Attempts table plus the session,
 * ported from includes/login_security.php. Time comparisons use MySQL NOW()
 * so they match rows written by the legacy app.
 */
class LoginThrottle
{
    private const SESSION_ATTEMPTS = 'login_attempts';

    private const SESSION_LOCKOUT_UNTIL = 'login_lockout_until';

    public function __construct(private Request $request) {}

    public function maxFailures(): int
    {
        return (int) config('school.login.max_failures');
    }

    public function lockoutMinutes(): int
    {
        return (int) config('school.login.lockout_minutes');
    }

    public function recentFailures(string $username): int
    {
        if ($username === '') {
            return 0;
        }

        try {
            return LoginAttempt::query()
                ->where('Username', mb_substr($username, 0, 50))
                ->where('Was_Successful', 0)
                ->whereRaw('Attempted_At >= DATE_SUB(NOW(), INTERVAL ? MINUTE)', [
                    (int) config('school.login.failure_window_minutes'),
                ])
                ->count();
        } catch (Throwable) {
            return 0;
        }
    }

    public function record(string $username, bool $success): void
    {
        try {
            // insert() skips Eloquent timestamps so Attempted_At uses the DB default (same clock as NOW()).
            LoginAttempt::query()->insert([
                'Username' => mb_substr($username, 0, 50),
                'IP_Address' => mb_substr($this->clientIp(), 0, 45),
                'Was_Successful' => $success ? 1 : 0,
            ]);
        } catch (Throwable) {
            // A missing table must never block login.
        }
    }

    public function sessionAttempts(): int
    {
        return (int) $this->request->session()->get(self::SESSION_ATTEMPTS, 0);
    }

    public function lockedUntil(): int
    {
        return (int) $this->request->session()->get(self::SESSION_LOCKOUT_UNTIL, 0);
    }

    public function isLockedOut(): bool
    {
        return $this->lockedUntil() > time();
    }

    public function secondsRemaining(): int
    {
        return max(1, $this->lockedUntil() - time());
    }

    public function lock(): void
    {
        $this->request->session()->put(self::SESSION_LOCKOUT_UNTIL, time() + $this->lockoutMinutes() * 60);
    }

    public function incrementSessionAttempts(): int
    {
        $attempts = $this->sessionAttempts() + 1;
        $this->request->session()->put(self::SESSION_ATTEMPTS, $attempts);

        return $attempts;
    }

    public function resetSessionAttempts(): void
    {
        $this->request->session()->put(self::SESSION_ATTEMPTS, 0);
    }

    public function clear(): void
    {
        $this->request->session()->forget([self::SESSION_ATTEMPTS, self::SESSION_LOCKOUT_UNTIL]);
    }

    /**
     * Mirrors login_client_ip(): honours proxy headers used on Railway / Cloudflare.
     */
    public function clientIp(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'] as $key) {
            $value = (string) $this->request->server($key, '');
            if ($value === '') {
                continue;
            }
            $ip = trim(explode(',', $value)[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return '0.0.0.0';
    }
}
