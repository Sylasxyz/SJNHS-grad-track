<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\LoginThrottle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Clears failed-login records behind the Security Alerts panel on Manage Users.
 */
class SecurityAlertController extends Controller
{
    /**
     * "Clear account alert": every IP for one login ID.
     */
    public function clearAccount(Request $request, LoginThrottle $throttle): RedirectResponse
    {
        $username = trim((string) $request->input('alert_username', ''));
        if ($username === '') {
            return back()->with('error', 'Invalid account.');
        }

        return back()->with('success', $throttle->clearFailures($username) > 0
            ? "Removed security alerts for account «{$username}» (all IPs in the last 30 minutes)."
            : 'No recent failed attempts for that account (they may have expired).');
    }

    /**
     * "Dismiss IP": one login ID + IP row.
     */
    public function dismissIp(Request $request, LoginThrottle $throttle): RedirectResponse
    {
        $username = trim((string) $request->input('alert_username', ''));
        $ip = trim((string) $request->input('alert_ip', ''));
        if ($username === '' || $ip === '') {
            return back()->with('error', 'Invalid security alert row.');
        }

        return back()->with('success', $throttle->clearFailures($username, $ip) > 0
            ? 'Cleared failed login records for that account from this IP.'
            : 'No matching failed attempts to clear (they may have expired).');
    }
}
