<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\GraduateProfile;
use App\Models\UserAccount;
use App\Services\GraduateAnalytics;
use App\Services\LoginThrottle;
use App\Services\ProfilePictureStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

/**
 * Role dashboards, ported from dashboard.php: students see their graduate status,
 * profile, announcements and activity; Instructors and Admins see the employment
 * overview, their work queue, feature shortcuts and recent graduate records.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, GraduateAnalytics $analytics, LoginThrottle $throttle, ProfilePictureStore $pictures): View
    {
        $user = $request->user();

        return $user->isStudent()
            ? $this->student($user, $pictures)
            : $this->staff($user, $analytics, $throttle);
    }

    private function student(UserAccount $user, ProfilePictureStore $pictures): View
    {
        $profile = GraduateProfile::query()->where('User_ID', $user->User_ID)->first();
        $profileUpdated = (string) $profile?->getRawOriginal('Last_Updated');

        $announcements = Announcement::query()
            ->whereRaw("COALESCE(Audience, 'Everyone') = 'Everyone'")
            ->whereRaw('COALESCE(Is_Archived, 0) = 0')
            ->orderByDesc('Date_Posted')
            ->limit(5)
            ->get(['Announcement_ID', 'Title', 'Date_Posted']);

        // Built from real account/profile events, newest first.
        $activity = [];
        if ($profile) {
            $activity[] = ['icon' => 'bi-pencil-square', 'tone' => 'green', 'text' => 'You updated your graduate profile.', 'date' => $profileUpdated];
            $verifiedAt = (string) $profile->getRawOriginal('Verified_At');
            if ($profile->Is_Verified && $verifiedAt !== '') {
                $activity[] = ['icon' => 'bi-shield-check', 'tone' => 'blue', 'text' => 'Your profile was verified by the school.', 'date' => $verifiedAt];
            } else {
                $activity[] = ['icon' => 'bi-hourglass-split', 'tone' => 'orange', 'text' => 'Your profile is pending school verification.', 'date' => $profileUpdated];
            }
        } else {
            $activity[] = ['icon' => 'bi-person-plus', 'tone' => 'orange', 'text' => 'Graduate profile not submitted yet.', 'date' => ''];
        }
        $activity[] = ['icon' => 'bi-person-check', 'tone' => 'blue', 'text' => 'Your student account was activated.', 'date' => (string) $user->getRawOriginal('Created_At')];
        usort($activity, static fn (array $a, array $b): int => strcmp($b['date'], $a['date']));

        $firstName = trim((string) $user->First_Name);
        $displayName = trim($firstName.' '.$user->Last_Name);
        if ($displayName === '') {
            $displayName = (string) $user->Username;
        }

        return view('dashboard.student', [
            'firstName' => $firstName !== '' ? $firstName : $displayName,
            'profile' => $profile,
            'profileName' => $profile ? (string) $profile->Full_Name : $displayName,
            'profileCourse' => trim((string) $profile?->Course),
            'profileBatch' => $profile && $profile->Batch_Year ? (string) $profile->Batch_Year : '—',
            'profilePhone' => trim((string) $profile?->Contact_Info),
            'profileEmail' => (string) $user->Email,
            'profileUpdated' => $profileUpdated,
            'photoUrl' => $profile && $pictures->path($profile->Profile_Picture) !== null
                ? route('profiles.picture', [$profile->Profile_ID, 'v' => substr(md5($profile->Profile_Picture), 0, 8)])
                : null,
            'completion' => GraduateAnalytics::profileCompletion($profile),
            'announcements' => $announcements,
            'activity' => array_slice($activity, 0, 4),
        ]);
    }

    private function staff(UserAccount $user, GraduateAnalytics $analytics, LoginThrottle $throttle): View
    {
        try {
            $data = $analytics->compute();
        } catch (Throwable $e) {
            report($e);
            $data = null;
        }
        $statusOrder = $data['status_order'] ?? GraduateAnalytics::STATUS_ORDER;

        $schemaErrors = [];
        try {
            DB::select('SELECT Course, Is_Verified FROM Graduate_Profiles LIMIT 1');
        } catch (Throwable $e) {
            report($e);
            $schemaErrors[] = config('school.messages.staff_schema_pending');
        }
        $unverified = $schemaErrors ? [] : $analytics->awaitingVerification();

        $pendingUsers = [];
        $securityAlertCount = 0;
        if ($user->isAdmin()) {
            $pendingUsers = DB::select(
                "SELECT User_ID, CONCAT(First_Name, ' ', Last_Name) AS Full_Name, Role, Created_At
                 FROM User_Accounts
                 WHERE Status = 'Pending'
                 ORDER BY Created_At ASC
                 LIMIT 6"
            );
            $securityAlertCount = count($throttle->securityAlerts(10));
        }

        $directoryRows = DB::select(
            "SELECT gp.Full_Name, gp.Course, gp.Batch_Year, gp.Employment_Status, gp.Is_Verified
             FROM Graduate_Profiles gp
             INNER JOIN User_Accounts ua ON ua.User_ID = gp.User_ID
             WHERE ua.Status = 'Active'
             ORDER BY gp.Last_Updated DESC
             LIMIT 8"
        );

        return view('dashboard.staff', [
            'isAdmin' => $user->isAdmin(),
            'analytics' => $data,
            'statusOrder' => $statusOrder,
            'countsByStatus' => $data['counts_by_status'] ?? array_fill_keys($statusOrder, 0),
            'schemaErrors' => $schemaErrors,
            'unverified' => array_slice($unverified, 0, 5),
            'pendingUsers' => $pendingUsers,
            'securityAlertCount' => $securityAlertCount,
            'directoryRows' => $directoryRows,
        ]);
    }
}
