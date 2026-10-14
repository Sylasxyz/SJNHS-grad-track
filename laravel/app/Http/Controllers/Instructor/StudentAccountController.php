<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\UserAccount;
use App\Services\AccountApprovalNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Manage Student Accounts, ported from instructor/users.php. Open to Instructors
 * and Admins; every action is limited to Student accounts by UserAccountPolicy.
 */
class StudentAccountController extends Controller
{
    private const STATUSES = [
        UserAccount::STATUS_PENDING,
        UserAccount::STATUS_ACTIVE,
        UserAccount::STATUS_INACTIVE,
        UserAccount::STATUS_ARCHIVED,
    ];

    public function index(): View
    {
        $students = UserAccount::query()->where('Role', UserAccount::ROLE_STUDENT);

        return view('instructor.users.index', [
            'pending' => (clone $students)
                ->where('Status', UserAccount::STATUS_PENDING)
                ->orderBy('Created_At')
                ->limit(200)
                ->get(),
            'students' => $students->orderByDesc('User_ID')->limit(200)->get(),
            'statuses' => self::STATUSES,
        ]);
    }

    public function approve(UserAccount $student, AccountApprovalNotifier $notifier): RedirectResponse
    {
        $approved = UserAccount::query()
            ->whereKey($student->User_ID)
            ->where('Role', UserAccount::ROLE_STUDENT)
            ->where('Status', UserAccount::STATUS_PENDING)
            ->update(['Status' => UserAccount::STATUS_ACTIVE]);

        if ($approved < 1) {
            return back()->with('error', 'Student was not pending, or could not be approved.');
        }

        return back()->with('success', match ($notifier->send($student, false, 'Student')) {
            true => 'Student approved and notification email sent.',
            false => 'Student approved. '.config('school.messages.staff_email_not_sent'),
            null => 'Student approved, but no valid email was found for notification.',
        });
    }

    public function updateStatus(Request $request, UserAccount $student): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
        ], [
            'status.*' => 'Invalid status.',
        ]);

        $student->Status = $data['status'];
        $student->save();

        return back()->with('success', 'Student status updated.');
    }
}
