<?php

namespace App\Policies;

use App\Models\GraduateProfile;
use App\Models\UserAccount;

/**
 * Students keep their own profile (profile.php); staff see every graduate's
 * profile in Graduate Records.
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
}
