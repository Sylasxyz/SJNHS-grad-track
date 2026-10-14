<?php

namespace App\Policies;

use App\Models\UserAccount;
use App\Services\Messaging;
use Illuminate\Auth\Access\Response;

class UserAccountPolicy
{
    /**
     * Verify / Manage Users (admin/users.php): Admins manage every account.
     */
    public function manage(UserAccount $actor, UserAccount $target): bool
    {
        return $actor->isAdmin();
    }

    /**
     * Manage Student Accounts (instructor/users.php): staff may act on Student accounts only.
     */
    public function manageStudent(UserAccount $actor, UserAccount $target): bool
    {
        return $actor->isStaff() && $target->isStudent();
    }

    /**
     * Send a message to $target (messages.php): Active recipient, not yourself,
     * and the role pairing allowed by Messaging::canRolesMessage().
     */
    public function message(UserAccount $actor, UserAccount $target): Response
    {
        if (! $target->isActive()) {
            return Response::deny('Recipient not found or inactive.');
        }
        if ((int) $target->User_ID === (int) $actor->User_ID) {
            return Response::deny('You cannot message yourself.');
        }
        if (! Messaging::canRolesMessage((string) $actor->Role, (string) $target->Role)) {
            return Response::deny($actor->isStudent()
                ? 'Students can only message instructors or admins.'
                : 'Staff can only message students and alumni.');
        }

        return Response::allow();
    }

    /**
     * Open the conversation with $target (messages.php?with=ID).
     */
    public function openConversation(UserAccount $actor, UserAccount $target): Response
    {
        return $target->isActive() && Messaging::canRolesMessage((string) $actor->Role, (string) $target->Role)
            ? Response::allow()
            : Response::deny('You cannot open that conversation.');
    }
}
