<?php

namespace App\Policies;

use App\Models\UserAccount;

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
}
