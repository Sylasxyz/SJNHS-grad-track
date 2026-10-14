<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\Concerns\HandlesEmbed;
use App\Http\Controllers\Controller;
use App\Models\PasswordReset;
use App\Models\UserAccount;
use App\Services\BrevoMailer;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Throwable;

/**
 * Two-step reset using a 6-digit emailed code stored as a SHA-256 hash in
 * Password_Resets (ported from forgot_password.php and reset_password.php).
 */
class PasswordResetController extends Controller
{
    use HandlesEmbed;

    private const SESSION_KEYS = ['pw_reset_email', 'pw_reset_user_id', 'pw_reset_verified', 'pw_reset_id'];

    public function showForgot(Request $request): View
    {
        $email = (string) old('email', $request->session()->get('pw_reset_email', ''));
        $emailState = session('email_state', '');
        if ($emailState === '' && $email !== '' && $email === $request->session()->get('pw_reset_email')) {
            $emailState = 'success';
        }

        return view('auth.forgot-password', [
            'embed' => $this->isEmbed($request),
            'email' => $email,
            'emailState' => $emailState,
            'codeState' => session('code_state', ''),
        ]);
    }

    public function handleForgot(Request $request, BrevoMailer $mailer): RedirectResponse
    {
        return $request->input('action') === 'verify_code'
            ? $this->verifyCode($request)
            : $this->sendCode($request, $mailer);
    }

    public function showReset(Request $request): View
    {
        return view('auth.reset-password', [
            'embed' => $this->isEmbed($request),
            'resetRow' => $this->verifiedReset($request),
        ]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $reset = $this->verifiedReset($request);
        $password = (string) $request->input('password', '');

        $error = match (true) {
            $reset === null => 'Your verification session is invalid or expired. Please request a new code.',
            strlen($password) < 8 => 'Password must be at least 8 characters.',
            ! preg_match('/[A-Za-z]/', $password) || ! preg_match('/[0-9]/', $password) => 'Password must include at least one letter and one number.',
            $password !== (string) $request->input('confirm_password', '') => 'Passwords do not match.',
            default => null,
        };

        if ($error !== null) {
            return redirect($this->embedRoute($request, 'password.reset'))->with('error', $error);
        }

        try {
            DB::transaction(function () use ($reset, $password) {
                UserAccount::query()->whereKey($reset->User_ID)->update(['Password' => Hash::make($password)]);
                PasswordReset::query()
                    ->where('User_ID', $reset->User_ID)
                    ->whereNull('Used_At')
                    ->update(['Used_At' => DB::raw('NOW()')]);
            });
        } catch (Throwable) {
            return redirect($this->embedRoute($request, 'password.reset'))->with('error', 'Could not reset password. Please try again.');
        }

        $request->session()->forget(self::SESSION_KEYS);

        return redirect($this->embedRoute($request, 'password.reset'))
            ->with('success', 'Password has been reset. You can now log in with your new password.');
    }

    private function sendCode(Request $request, BrevoMailer $mailer): RedirectResponse
    {
        $email = strtolower(trim((string) $request->input('email', '')));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->backToForgot($request, $email, 'Please enter a valid email address.', 'error');
        }

        $user = UserAccount::query()->whereRaw('LOWER(Email) = ?', [$email])->first();
        if (! $user) {
            return $this->backToForgot($request, $email, 'This email is not registered in the system.', 'error');
        }
        if (! $user->isActive()) {
            return $this->backToForgot($request, $email, 'This account is not active yet. Please wait for Admin approval or contact the school.', 'error');
        }

        try {
            $code = $this->storeNewCode($user->User_ID);
        } catch (Throwable) {
            return $this->backToForgot($request, $email, 'Unable to process password reset right now. Please try again later.', 'error');
        }

        $request->session()->put(['pw_reset_email' => $email, 'pw_reset_user_id' => $user->User_ID]);
        $request->session()->forget(['pw_reset_verified', 'pw_reset_id']);

        $mail = $mailer->send((string) $user->Email, 'Password Reset Code — San Josef NHS', $this->codeEmail($user, $code));

        if ($mail['ok']) {
            return $this->backToForgot($request, $email,
                "A 6-digit verification code has been sent to {$email}. Please check your inbox (and spam folder).", 'success', isError: false);
        }

        if (config('services.brevo.show_code_on_failure')) {
            return $this->backToForgot($request, $email,
                'We could not send the verification email. Use the code below to continue. It expires in 15 minutes.', 'success', isError: false)
                ->with('debug_code', $code);
        }

        return $this->backToForgot($request, $email, 'Could not send the verification code email. Please try again later or contact the school.', 'error');
    }

    private function verifyCode(Request $request): RedirectResponse
    {
        $code = preg_replace('/\D+/', '', (string) $request->input('verification_code', '')) ?? '';
        $sessionEmail = strtolower(trim((string) $request->session()->get('pw_reset_email', '')));
        $email = strtolower(trim((string) $request->input('email', ''))) ?: $sessionEmail;

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->backToForgot($request, $email, 'Please enter your registered email address first.', 'error');
        }
        if ($sessionEmail === '' || $sessionEmail !== $email) {
            return $this->backToForgot($request, $email, 'Please send a verification code to this email first.', 'error', 'error');
        }
        if (! preg_match('/^\d{6}$/', $code)) {
            return $this->backToForgot($request, $email, 'Please enter the 6-digit verification code from your email.', 'success', 'error');
        }

        $reset = PasswordReset::query()
            ->join('User_Accounts', 'User_Accounts.User_ID', '=', 'Password_Resets.User_ID')
            ->where('Password_Resets.Token_Hash', hash('sha256', $code))
            ->whereRaw('LOWER(User_Accounts.Email) = ?', [$email])
            ->whereNull('Password_Resets.Used_At')
            ->whereRaw('Password_Resets.Expires_At > NOW()')
            ->where('User_Accounts.Status', UserAccount::STATUS_ACTIVE)
            ->first(['Password_Resets.Reset_ID', 'Password_Resets.User_ID']);

        if (! $reset) {
            return $this->backToForgot($request, $email, 'Invalid or expired verification code. Please request a new code.', 'success', 'error');
        }

        $request->session()->put([
            'pw_reset_verified' => true,
            'pw_reset_user_id' => $reset->User_ID,
            'pw_reset_id' => $reset->Reset_ID,
            'pw_reset_email' => $email,
        ]);

        return redirect($this->embedRoute($request, 'password.reset'));
    }

    /**
     * Replaces any previous code for the user. Token_Hash is UNIQUE across all
     * users, so retry on the rare collision with another account's code.
     */
    private function storeNewCode(int $userId): string
    {
        for ($try = 1; ; $try++) {
            $code = (string) random_int(100000, 999999);
            try {
                DB::transaction(function () use ($userId, $code) {
                    PasswordReset::query()->where('User_ID', $userId)->delete();
                    PasswordReset::query()->insert([
                        'User_ID' => $userId,
                        'Token_Hash' => hash('sha256', $code),
                        'Expires_At' => DB::raw('DATE_ADD(NOW(), INTERVAL '.(int) config('school.password_reset.code_ttl_minutes').' MINUTE)'),
                    ]);
                });

                return $code;
            } catch (QueryException $e) {
                if ((string) $e->getCode() !== '23000' || $try >= 3) {
                    throw $e;
                }
            }
        }
    }

    /**
     * The verified reset row for this session, or null if it expired or was used.
     */
    private function verifiedReset(Request $request): ?PasswordReset
    {
        $session = $request->session();
        if (! $session->get('pw_reset_verified') || ! $session->get('pw_reset_id') || ! $session->get('pw_reset_user_id')) {
            return null;
        }

        return PasswordReset::query()
            ->join('User_Accounts', 'User_Accounts.User_ID', '=', 'Password_Resets.User_ID')
            ->where('Password_Resets.Reset_ID', (int) $session->get('pw_reset_id'))
            ->where('Password_Resets.User_ID', (int) $session->get('pw_reset_user_id'))
            ->whereNull('Password_Resets.Used_At')
            ->whereRaw('Password_Resets.Expires_At > NOW()')
            ->where('User_Accounts.Status', UserAccount::STATUS_ACTIVE)
            ->first(['Password_Resets.Reset_ID', 'Password_Resets.User_ID', 'User_Accounts.Email']);
    }

    private function backToForgot(Request $request, string $email, string $message, string $emailState, string $codeState = '', bool $isError = true): RedirectResponse
    {
        return redirect($this->embedRoute($request, 'password.request'))
            ->withInput(['email' => $email])
            ->with($isError ? 'error' : 'success', $message)
            ->with('email_state', $emailState)
            ->with('code_state', $codeState);
    }

    private function codeEmail(UserAccount $user, string $code): string
    {
        $name = trim("{$user->First_Name} {$user->Last_Name}") ?: 'User';
        $minutes = (int) config('school.password_reset.code_ttl_minutes');

        return "Hello {$name},\n\n"
            ."We received a request to reset the password for your Graduate Tracking System account.\n"
            ."Your verification code is:\n\n"
            ."{$code}\n\n"
            ."Enter this 6-digit code on the password reset page. It expires in {$minutes} minutes.\n\n"
            ."If you did not request this, you can ignore this email. Your password will stay the same.\n\n"
            ."— San Josef National High School\n";
    }
}
