<?php

namespace App\Policies;

use App\Models\Report;
use App\Models\UserAccount;
use Illuminate\Auth\Access\Response;

/**
 * Same rules as admin/reports.php: the page is for Admins and Instructors
 * (require_roles), and generating or deleting needs can_manage_records(),
 * which is the same two roles.
 */
class ReportPolicy
{
    /**
     * Reports page, snapshot trends and the saved reports list.
     */
    public function viewAny(UserAccount $user): Response
    {
        return $this->staff($user);
    }

    /**
     * Viewing one saved report or exporting it as CSV.
     */
    public function view(UserAccount $user, Report $report): Response
    {
        return $this->staff($user);
    }

    public function create(UserAccount $user): Response
    {
        return $this->manage($user);
    }

    public function delete(UserAccount $user, Report $report): Response
    {
        return $this->manage($user);
    }

    private function staff(UserAccount $user): Response
    {
        return $user->isStaff() ? Response::allow() : Response::deny('Forbidden');
    }

    private function manage(UserAccount $user): Response
    {
        return $user->isStaff()
            ? Response::allow()
            : Response::deny('View-only accounts cannot generate or delete reports.');
    }
}
