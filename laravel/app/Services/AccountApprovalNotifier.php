<?php

namespace App\Services;

use App\Models\UserAccount;

/**
 * "Account approved" email sent from the admin and instructor user pages.
 */
class AccountApprovalNotifier
{
    public function __construct(private BrevoMailer $mailer) {}

    /**
     * Returns null when the account has no valid email, otherwise whether delivery succeeded.
     */
    public function send(UserAccount $user, bool $byAdministrator, string $fallbackName): ?bool
    {
        $email = (string) $user->Email;
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $name = trim($user->First_Name.' '.$user->Last_Name) ?: $fallbackName;
        $approvedBy = $byAdministrator ? ' by the Administrator' : '';

        $body = "Hello {$name},\n\n"
            ."Good news! Your Graduate Tracking System account has been approved{$approvedBy}.\n\n"
            ."You can now log in and use the system.\n\n"
            ."Login here:\n".route('login')."\n\n"
            ."If you did not register for an account, please ignore this email or contact the school.\n\n"
            ."— San Josef National High School\n";

        return $this->mailer->send($email, 'Account approved — San Josef NHS', $body)['ok'];
    }
}
