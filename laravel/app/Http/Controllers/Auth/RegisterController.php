<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\Concerns\HandlesEmbed;
use App\Http\Controllers\Controller;
use App\Models\UserAccount;
use App\Services\BrevoMailer;
use App\Support\PasswordPolicy;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Throwable;

class RegisterController extends Controller
{
    use HandlesEmbed;

    private const RECLAIMABLE_STATUSES = [
        UserAccount::STATUS_ARCHIVED,
        UserAccount::STATUS_INACTIVE,
        UserAccount::STATUS_PENDING,
    ];

    public function show(Request $request): View
    {
        if ($this->isEmbed($request) && Auth::check()) {
            return $this->topWindowRedirect(route('dashboard'));
        }

        return view('auth.register', [
            'embed' => $this->isEmbed($request),
            'suffixes' => config('school.name_suffixes'),
        ]);
    }

    public function store(Request $request, BrevoMailer $mailer): RedirectResponse
    {
        $role = (string) $request->input('role', UserAccount::ROLE_STUDENT);
        $isStudent = $role === UserAccount::ROLE_STUDENT;

        $firstName = trim((string) $request->input('first_name', ''));
        $lastName = trim((string) $request->input('last_name', ''));
        $middleInitial = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $request->input('middle_initial', '')) ?? '');
        $nameSuffix = strtoupper(trim((string) $request->input('name_suffix', '')));
        $email = strtolower(trim((string) $request->input($isStudent ? 'student_email' : 'instructor_email', '')));
        $studentNo = trim((string) $request->input('student_no', ''));
        $employeeId = trim((string) $request->input('employee_id', ''));
        $password = (string) $request->input('password', '');

        $errors = [];
        if (strlen($firstName) < 2) {
            $errors[] = 'First name must be at least 2 characters.';
        }
        if (strlen($lastName) < 2) {
            $errors[] = 'Last name must be at least 2 characters.';
        }
        if ($middleInitial !== '' && ! preg_match('/^[A-Z]{1,2}$/', $middleInitial)) {
            $errors[] = 'Middle initial must be 1–2 letters only (optional).';
        }
        if ($nameSuffix !== '' && ! in_array($nameSuffix, config('school.name_suffixes'), true)) {
            $errors[] = 'Suffix selection is invalid.';
        }
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email address is invalid.';
        }
        if (! in_array($role, [UserAccount::ROLE_STUDENT, UserAccount::ROLE_INSTRUCTOR], true)) {
            $errors[] = 'Role selection is invalid.';
        }
        if ($passwordError = PasswordPolicy::registrationError($password)) {
            $errors[] = $passwordError;
        }
        if ($password !== (string) $request->input('confirm_password', '')) {
            $errors[] = 'Passwords do not match.';
        }
        if (! $request->boolean('agree_terms')) {
            $errors[] = 'You must agree to the Terms and Conditions and Privacy Policy before registering.';
        }
        if ($role === UserAccount::ROLE_STUDENT) {
            if (strlen($studentNo) < 4) {
                $errors[] = 'Student No. must be at least 4 characters.';
            }
            if ($employeeId !== '') {
                $errors[] = 'Employee ID is not valid for Student registration.';
            }
        } elseif ($role === UserAccount::ROLE_INSTRUCTOR) {
            if (strlen($employeeId) < 4) {
                $errors[] = 'Employee ID must be at least 4 characters.';
            }
            if ($studentNo !== '') {
                $errors[] = 'Student No. is not valid for Instructor registration.';
            }
        }

        if ($errors) {
            return $this->back($request, $errors);
        }

        $loginId = $isStudent ? $studentNo : $employeeId;
        $loginIdTaken = $isStudent ? 'This Student ID is already registered.' : 'This Employee ID is already registered.';
        $emailTaken = 'This email address is already registered. Contact the school office if you need assistance.';

        $this->reclaimInactiveAccounts($email, $loginId);

        if (UserAccount::query()->whereRaw('LOWER(TRIM(Email)) = ?', [$email])->exists()) {
            $errors[] = $emailTaken;
        }
        if ($this->loginIdExists($loginId)) {
            $errors[] = $loginIdTaken;
        }
        if ($errors) {
            return $this->back($request, $errors);
        }

        try {
            UserAccount::query()->create([
                'First_Name' => $firstName,
                'Last_Name' => $lastName,
                'Middle_Initial' => $middleInitial !== '' ? $middleInitial : null,
                'Name_Suffix' => $nameSuffix !== '' ? $nameSuffix : null,
                'Username' => $loginId,
                'Email' => $email,
                'Student_No' => $studentNo !== '' ? $studentNo : null,
                'Employee_ID' => $employeeId !== '' ? $employeeId : null,
                'Password' => Hash::make($password),
                'Role' => $role,
                'Status' => UserAccount::STATUS_PENDING,
            ]);
        } catch (QueryException $e) {
            if ((string) $e->getCode() !== '23000') {
                throw $e;
            }
            $message = strtolower($e->getMessage());

            return $this->back($request, [match (true) {
                str_contains($message, 'email') => $emailTaken,
                str_contains($message, 'username'),
                str_contains($message, 'student_no'),
                str_contains($message, 'employee_id') => $loginIdTaken,
                default => 'This account could not be created because a unique field is already in use.',
            }]);
        }

        $mail = $mailer->send($email, 'Registration received — pending approval | San Josef NHS',
            $this->confirmationEmail($firstName, $lastName, $role, $loginId, $email));

        $redirect = redirect($this->embedRoute($request, 'register'));
        if ($mail['ok']) {
            return $redirect->with('success', "Registration submitted. A confirmation email was sent to {$email}. Please wait for Admin approval before logging in.");
        }

        return $redirect
            ->with('success', 'Registration submitted successfully. Please wait for Admin approval before logging in.')
            ->with('warning', 'We could not send a confirmation email right now. Your registration was received—please wait for administrator approval. You do not need to register again. Contact the school office if you need assistance.');
    }

    /**
     * @param  list<string>  $errors
     */
    private function back(Request $request, array $errors): RedirectResponse
    {
        return redirect($this->embedRoute($request, 'register'))
            ->withInput($request->except(['password', 'confirm_password']))
            ->with('registration_errors', $errors);
    }

    private function loginIdExists(string $loginId): bool
    {
        return $loginId !== '' && UserAccount::query()
            ->where('Username', $loginId)
            ->orWhere('Student_No', $loginId)
            ->orWhere('Employee_ID', $loginId)
            ->exists();
    }

    /**
     * Non-active rows still hold the UNIQUE email / ID, so remove them to let
     * the person register again (same behaviour as the legacy register.php).
     */
    private function reclaimInactiveAccounts(string $email, string $loginId): void
    {
        $candidates = UserAccount::query()
            ->where(function ($query) use ($email, $loginId) {
                $query->whereRaw('LOWER(TRIM(Email)) = ?', [$email]);
                if ($loginId !== '') {
                    $query->orWhere('Username', $loginId)
                        ->orWhere('Student_No', $loginId)
                        ->orWhere('Employee_ID', $loginId);
                }
            })
            ->get(['User_ID', 'Status']);

        foreach ($candidates as $account) {
            $status = trim((string) $account->Status);
            if ($status !== '' && ! in_array($status, self::RECLAIMABLE_STATUSES, true)) {
                continue;
            }
            try {
                UserAccount::query()
                    ->whereKey($account->User_ID)
                    ->where(fn ($q) => $q->whereIn('Status', self::RECLAIMABLE_STATUSES)
                        ->orWhereRaw("TRIM(COALESCE(Status, '')) = ''"))
                    ->delete();
            } catch (Throwable) {
                // A foreign key may still reference this row; leave it in place.
            }
        }
    }

    private function confirmationEmail(string $firstName, string $lastName, string $role, string $loginId, string $email): string
    {
        $name = trim("{$firstName} {$lastName}") ?: 'User';
        $loginIdLabel = $role === UserAccount::ROLE_STUDENT ? 'Student ID' : 'Employee ID';

        return "Hello {$name},\n\n"
            ."Thank you for registering in the San Josef National High School Graduate Tracking System.\n\n"
            ."Your account has been submitted and is pending Admin approval.\n"
            ."Please wait while an administrator reviews your registration. You will receive another email once your account is approved.\n\n"
            ."Registration details:\n"
            ."- Role: {$role}\n"
            ."- {$loginIdLabel}: {$loginId}\n"
            ."- Email: {$email}\n\n"
            ."You cannot log in until your account is approved.\n\n"
            ."If you did not register for an account, please ignore this email or contact the school.\n\n"
            ."— San Josef National High School\n";
    }
}
