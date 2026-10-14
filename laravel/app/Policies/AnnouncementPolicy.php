<?php

namespace App\Policies;

use App\Models\Announcement;
use App\Models\UserAccount;
use Illuminate\Auth\Access\Response;

/**
 * Same rules as announcements.php / announcement_create.php: every signed-in user reads
 * active "Everyone" announcements, Instructors and Admins post, archive and restore,
 * and only Admins delete permanently.
 */
class AnnouncementPolicy
{
    public function view(UserAccount $user, Announcement $announcement): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        return $announcement->Audience === Announcement::AUDIENCE_EVERYONE && ! $announcement->Is_Archived;
    }

    public function viewArchived(UserAccount $user): bool
    {
        return $user->isStaff();
    }

    public function create(UserAccount $user): bool
    {
        return $user->isStaff();
    }

    public function archive(UserAccount $user, Announcement $announcement): Response
    {
        return $this->manage($user);
    }

    public function restore(UserAccount $user, Announcement $announcement): Response
    {
        return $this->manage($user);
    }

    public function delete(UserAccount $user, Announcement $announcement): Response
    {
        if (! $user->isStaff()) {
            return $this->manage($user);
        }

        return $user->isAdmin()
            ? Response::allow()
            : Response::deny('Only Admin can permanently delete announcements.');
    }

    private function manage(UserAccount $user): Response
    {
        return $user->isStaff()
            ? Response::allow()
            : Response::deny('You do not have permission to manage announcements.');
    }
}
