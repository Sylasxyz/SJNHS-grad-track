<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserAccount;
use App\Services\AccountApprovalNotifier;
use App\Services\LoginThrottle;
use App\Support\AdminActionLog;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Verify / Manage Users, ported from admin/users.php.
 */
class UserController extends Controller
{
    private const ROLES = [UserAccount::ROLE_STUDENT, UserAccount::ROLE_INSTRUCTOR, UserAccount::ROLE_ADMIN];

    private const STATUSES = [
        UserAccount::STATUS_PENDING,
        UserAccount::STATUS_ACTIVE,
        UserAccount::STATUS_INACTIVE,
        UserAccount::STATUS_ARCHIVED,
    ];

    public function index(Request $request, LoginThrottle $throttle): View
    {
        $search = trim((string) $request->query('q', ''));

        $users = UserAccount::query()
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.$search.'%';
                $query->where(fn ($q) => $q->where('First_Name', 'like', $like)
                    ->orWhere('Last_Name', 'like', $like)
                    ->orWhere('Username', 'like', $like)
                    ->orWhere('Email', 'like', $like)
                    ->orWhere('Role', 'like', $like)
                    ->orWhere('Status', 'like', $like));
            })
            ->orderByDesc('User_ID')
            ->limit(200)
            ->get();

        // Blank legacy statuses are listed as pending so the admin can see them.
        $pendingUsers = UserAccount::query()
            ->whereRaw("TRIM(COALESCE(Status, '')) IN ('Pending', '')")
            ->orderBy('Created_At')
            ->limit(200)
            ->get();

        $hasInvalidStatus = $users->contains(fn ($u) => ! in_array(trim((string) $u->Status), self::STATUSES, true));

        $securityAlerts = $throttle->securityAlerts(50);

        return view('admin.users.index', [
            'users' => $users,
            'pendingUsers' => $pendingUsers,
            'search' => $search,
            'schemaWarning' => $hasInvalidStatus ? config('school.messages.staff_schema_pending') : null,
            'securityAlerts' => $securityAlerts,
            'alertAccounts' => $this->accountsForAlerts($securityAlerts),
            'roles' => self::ROLES,
            'statuses' => self::STATUSES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $role = (string) $request->input('role', '');
        $idLabel = match ($role) {
            UserAccount::ROLE_STUDENT => 'Student ID',
            UserAccount::ROLE_INSTRUCTOR => 'Employee ID',
            default => 'Username',
        };

        $data = $request->validateWithBag('create', [
            'first_name' => ['required', 'string', 'min:2', 'max:80'],
            'last_name' => ['required', 'string', 'min:2', 'max:80'],
            'role' => ['required', Rule::in(self::ROLES)],
            'username' => ['required', 'string', 'min:4', 'max:50'],
            'email' => ['required', 'email:filter', 'max:150'],
            'password' => ['required', 'string', 'min:8'],
            'status' => ['required', Rule::in(self::STATUSES)],
        ], [
            'first_name.required' => 'First name must be at least 2 characters.',
            'first_name.min' => 'First name must be at least 2 characters.',
            'last_name.required' => 'Last name must be at least 2 characters.',
            'last_name.min' => 'Last name must be at least 2 characters.',
            'role.*' => 'Please select a role.',
            'username.required' => "{$idLabel} must be at least 4 characters.",
            'username.min' => "{$idLabel} must be at least 4 characters.",
            'email.required' => 'Email address is invalid.',
            'email.email' => 'Email address is invalid.',
            'password.required' => 'Password must be at least 8 characters.',
            'password.min' => 'Password must be at least 8 characters.',
            'status.*' => 'Invalid status.',
        ], [
            'first_name' => 'first name',
            'last_name' => 'last name',
            'username' => $idLabel,
        ]);

        $loginId = $data['username'];
        $taken = ValidationException::withMessages(['username' => "{$idLabel} or email already exists."])
            ->errorBag('create');

        $exists = UserAccount::query()
            ->where('Username', $loginId)
            ->orWhere('Student_No', $loginId)
            ->orWhere('Employee_ID', $loginId)
            ->orWhere('Email', $data['email'])
            ->exists();
        if ($exists) {
            throw $taken;
        }

        try {
            UserAccount::query()->create([
                'First_Name' => $data['first_name'],
                'Last_Name' => $data['last_name'],
                'Username' => $loginId,
                'Email' => $data['email'],
                'Student_No' => $role === UserAccount::ROLE_STUDENT ? $loginId : null,
                'Employee_ID' => $role === UserAccount::ROLE_INSTRUCTOR ? $loginId : null,
                'Password' => Hash::make($data['password']),
                'Role' => $role,
                'Status' => $data['status'],
            ]);
        } catch (QueryException $e) {
            if ((string) $e->getCode() === '23000') {
                throw $taken;
            }
            throw $e;
        }

        return back()->with('success', 'User created.');
    }

    public function approve(UserAccount $user, AccountApprovalNotifier $notifier): RedirectResponse
    {
        $approved = UserAccount::query()
            ->whereKey($user->User_ID)
            ->where('Status', UserAccount::STATUS_PENDING)
            ->update(['Status' => UserAccount::STATUS_ACTIVE]);

        if ($approved < 1) {
            return back()->with('error', 'User was not pending, or could not be approved.');
        }

        return back()->with('success', match ($notifier->send($user, true, 'User')) {
            true => 'User approved and notification email sent.',
            false => 'User approved. '.config('school.messages.staff_email_not_sent'),
            null => 'User approved, but no valid email was found for notification.',
        });
    }

    /**
     * Declines a pending registration by archiving it; the account is kept on record.
     */
    public function decline(UserAccount $user): RedirectResponse
    {
        UserAccount::query()
            ->whereKey($user->User_ID)
            ->where('Status', UserAccount::STATUS_PENDING)
            ->update(['Status' => UserAccount::STATUS_ARCHIVED]);

        return back()->with('success', 'Registration declined. Account archived (not deleted).');
    }

    /**
     * Edits name and email, optionally resetting the password.
     */
    public function update(Request $request, UserAccount $user): RedirectResponse
    {
        $data = $request->validateWithBag('edit', [
            'first_name' => ['required', 'string', 'min:2', 'max:80'],
            'last_name' => ['required', 'string', 'min:2', 'max:80'],
            'email' => ['required', 'email:filter', 'max:150',
                Rule::unique(UserAccount::class, 'Email')->ignore($user->User_ID, 'User_ID')],
            'new_password' => ['nullable', 'string', 'min:8'],
        ], [
            'first_name.required' => 'First name must be at least 2 characters.',
            'first_name.min' => 'First name must be at least 2 characters.',
            'last_name.required' => 'Last name must be at least 2 characters.',
            'last_name.min' => 'Last name must be at least 2 characters.',
            'email.required' => 'Email address is invalid.',
            'email.email' => 'Email address is invalid.',
            'email.unique' => 'Email already in use by another account.',
            'new_password.min' => 'New password must be at least 8 characters.',
        ], [
            'first_name' => 'first name',
            'last_name' => 'last name',
        ]);

        $user->First_Name = $data['first_name'];
        $user->Last_Name = $data['last_name'];
        $user->Email = $data['email'];
        $resetPassword = ($data['new_password'] ?? '') !== '';
        if ($resetPassword) {
            $user->Password = Hash::make($data['new_password']);
        }
        $user->save();

        return back()->with('success', $resetPassword ? 'User updated and password reset.' : 'User details updated.');
    }

    /**
     * Sets role and status together (row "Save" button and the Security Alerts "Lock Account" button).
     */
    public function updateAccess(Request $request, UserAccount $user): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(self::ROLES)],
            'status' => ['required', Rule::in(self::STATUSES)],
        ], [
            'role.*' => 'Invalid role/status.',
            'status.*' => 'Invalid role/status.',
        ]);

        if ($user->is($request->user()) && $data['status'] !== UserAccount::STATUS_ACTIVE) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        $previousStatus = (string) ($user->Status ?: 'unknown');
        $user->Role = $data['role'];
        $user->Status = $data['status'];
        $user->save();

        AdminActionLog::statusChange($request->user(), $user, $previousStatus, $data['status'], 'manual update', $request->ip());

        return back()->with('success', 'User updated.');
    }

    /**
     * Quick deactivate (Inactive or Archived). The row stays, so the email remains reserved.
     */
    public function deactivate(Request $request, UserAccount $user): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([UserAccount::STATUS_INACTIVE, UserAccount::STATUS_ARCHIVED])],
        ], [
            'status.*' => 'Invalid status.',
        ]);

        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        $previousStatus = (string) ($user->Status ?: 'unknown');
        $user->Status = $data['status'];
        $user->save();

        AdminActionLog::statusChange($request->user(), $user, $previousStatus, $data['status'], 'quick deactivate', $request->ip());

        return back()->with('success', $data['status'] === UserAccount::STATUS_ARCHIVED ? 'User archived.' : 'User set to inactive.');
    }

    /**
     * Permanent delete, which frees the email and login ID for a new registration.
     */
    public function destroy(Request $request, UserAccount $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        try {
            $user->delete();
        } catch (QueryException) {
            return back()->with('error', 'Could not delete user. Remove linked records first or delete related graduate profile, then try again.');
        }

        AdminActionLog::deleted($request->user(), $user);

        return back()->with('success', 'User permanently deleted. Email and login ID can be used for registration again.');
    }

    /**
     * Maps each alerted login ID to its account (by Username, Student_No, or Employee_ID).
     *
     * @param  list<\stdClass>  $alerts
     * @return array<string, UserAccount>
     */
    private function accountsForAlerts(array $alerts): array
    {
        $loginIds = array_values(array_unique(array_map(fn ($alert) => (string) $alert->Username, $alerts)));
        if (! $loginIds) {
            return [];
        }

        $accounts = UserAccount::query()
            ->whereIn('Username', $loginIds)
            ->orWhereIn('Student_No', $loginIds)
            ->orWhereIn('Employee_ID', $loginIds)
            ->get(['User_ID', 'Username', 'Student_No', 'Employee_ID', 'Role', 'Status']);

        $map = [];
        foreach ($loginIds as $loginId) {
            $match = $accounts->firstWhere('Username', $loginId)
                ?? $accounts->firstWhere('Student_No', $loginId)
                ?? $accounts->firstWhere('Employee_ID', $loginId);
            if ($match) {
                $map[$loginId] = $match;
            }
        }

        return $map;
    }
}
