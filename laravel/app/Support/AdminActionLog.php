<?php

namespace App\Support;

use App\Models\UserAccount;

/**
 * Audit trail for admin user-management actions, in the same line format as
 * the legacy logs/admin_actions.log.
 */
class AdminActionLog
{
    public static function statusChange(UserAccount $admin, UserAccount $target, string $from, string $to, string $note, ?string $ip): void
    {
        self::write(' | ACTION: STATUS CHANGE'
            .' | By Admin ID: '.$admin->User_ID
            .' | User ID: '.$target->User_ID
            .' | Username: '.($target->Username ?? 'unknown')
            .' | Role: '.($target->Role ?? 'unknown')
            .' | From: '.$from
            .' | To: '.$to
            .($note !== '' ? ' | Note: '.$note : '')
            .' | IP: '.($ip ?? 'unknown'));
    }

    public static function deleted(UserAccount $admin, UserAccount $target): void
    {
        self::write(' | ACTION: DELETE USER'
            .' | By Admin ID: '.$admin->User_ID
            .' | User ID: '.$target->User_ID
            .' | Email: '.($target->Email ?? '')
            .' | Username: '.($target->Username ?? ''));
    }

    private static function write(string $line): void
    {
        @file_put_contents(storage_path('logs/admin_actions.log'), now()->format('Y-m-d H:i:s').$line."\n", FILE_APPEND);
    }
}
