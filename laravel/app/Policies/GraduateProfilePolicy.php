<?php

namespace App\Policies;

use App\Models\GraduateProfile;
use App\Models\UserAccount;
use Illuminate\Auth\Access\Response;

/**
 * Students keep their own profile (profile.php); staff see every graduate's
 * profile in Graduate Records (graduates.php, graduate_edit.php), where
 * Instructors and Admins edit and verify and only Admins delete.
 */
class GraduateProfilePolicy
{
    public function view(UserAccount $user, GraduateProfile $profile): bool
    {
        return $user->isStaff() || (int) $profile->User_ID === (int) $user->User_ID;
    }

    /**
     * A student creates or edits only their own profile; the controller always loads it by the signed-in User_ID.
     */
    public function manageOwn(UserAccount $user): bool
    {
        return $user->isStudent();
    }

    /**
     * Graduate Records list, search and CSV export.
     */
    public function viewAny(UserAccount $user): Response
    {
        return $this->staff($user);
    }

    public function update(UserAccount $user, GraduateProfile $profile): Response
    {
        return $this->staff($user);
    }

    public function verify(UserAccount $user): Response
    {
        return $user->isStaff()
            ? Response::allow()
            : Response::deny('View-only accounts cannot verify profiles.');
    }

    public function delete(UserAccount $user, GraduateProfile $profile): Response
    {
        return $user->isAdmin() ? Response::allow() : Response::deny('Forbidden');
    }

    private function staff(UserAccount $user): Response
    {
        return $user->isStaff() ? Response::allow() : Response::deny('Forbidden');
    }
}
