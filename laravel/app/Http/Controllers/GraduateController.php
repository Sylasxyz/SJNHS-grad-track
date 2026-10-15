<?php

namespace App\Http\Controllers;

use App\Models\GraduateProfile;
use App\Services\ProfilePictureStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Graduate Records, ported from graduates.php (search, card/table view, CSV export,
 * bulk verify) and graduate_edit.php (edit, mark verified, admin delete).
 * Permissions live in GraduateProfilePolicy.
 */
class GraduateController extends Controller
{
    public function index(Request $request, ProfilePictureStore $pictures): View|RedirectResponse
    {
        // Old-style export links (graduates?export=csv) go to the export route.
        if ($request->query('export') === 'csv') {
            return redirect()->route('graduates.export', $request->except('export'));
        }

        $filters = $this->filters($request);
        $rows = $this->search($filters);
        $viewMode = in_array($request->query('view'), ['cards', 'table'], true) ? $request->query('view') : 'cards';

        $photos = [];
        foreach ($rows as $row) {
            $photos[$row->Profile_ID] = $pictures->path($row->Profile_Picture) !== null
                ? route('profiles.picture', [$row->Profile_ID, 'v' => substr(md5($row->Profile_Picture), 0, 8)])
                : null;
        }

        return view('graduates.index', [
            'rows' => $rows,
            'photos' => $photos,
            'filters' => $filters,
            'viewMode' => $viewMode,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->search($this->filters($request));

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Full Name', 'Username', 'Batch', 'Course', 'Employment Status', 'Verified', 'Company/School', 'Contact', 'Last Updated']);
            foreach ($rows as $r) {
                fputcsv($out, [
                    (string) $r->Full_Name,
                    (string) $r->Username,
                    (int) $r->Batch_Year,
                    (string) $r->Course,
                    (string) $r->Employment_Status,
                    $r->Is_Verified ? 'Yes' : 'No',
                    (string) $r->Company_School_Name,
                    (string) $r->Contact_Info,
                    (string) $r->getRawOriginal('Last_Updated'),
                ]);
            }
            fclose($out);
        }, 'graduate-records-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=utf-8']);
    }

    /**
     * "Verify selected" in the table view.
     */
    public function verifyBulk(Request $request): RedirectResponse
    {
        $ids = collect((array) $request->input('profile_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();

        if ($ids->isEmpty()) {
            return back()->with('error', 'Select at least one profile to verify.');
        }

        GraduateProfile::query()
            ->whereIn('Profile_ID', $ids)
            ->where('Is_Verified', 0)
            ->toBase()
            ->update([
                'Is_Verified' => 1,
                'Verified_By' => $request->user()->User_ID,
                'Verified_At' => DB::raw('NOW()'),
            ]);

        return back()->with('success', 'Selected profiles marked as verified.');
    }

    public function edit(GraduateProfile $profile): View
    {
        return view('graduates.edit', [
            'profile' => $profile->load('user:User_ID,Username'),
        ]);
    }

    public function update(Request $request, GraduateProfile $profile): RedirectResponse
    {
        $maxYear = now()->year + 1;

        $data = $request->validate([
            'full_name' => ['required', 'string', 'min:3', 'max:150'],
            'course' => ['nullable', 'string', 'max:120'],
            'batch_year' => ['required', 'integer', 'between:1990,'.$maxYear],
            'employment_status' => ['required', 'string', 'in:'.implode(',', GraduateProfile::EMPLOYMENT_STATUSES)],
            'company_school_name' => ['nullable', 'string', 'max:200'],
            'contact_info' => ['nullable', 'string', 'max:200'],
        ], [
            'full_name.required' => 'Full name must be at least 3 characters.',
            'full_name.string' => 'Full name must be at least 3 characters.',
            'full_name.min' => 'Full name must be at least 3 characters.',
            'full_name.max' => 'Full name is too long. Maximum 150 characters only.',
            'course.*' => 'Course / Strand is too long. Maximum 120 characters only.',
            'batch_year.*' => 'Batch year is invalid.',
            'employment_status.*' => 'Employment status is invalid.',
            'company_school_name.*' => 'Company / School name is too long. Maximum 200 characters only.',
            'contact_info.*' => 'Contact info is too long. Maximum 200 characters only.',
        ]);

        $attributes = [
            'Full_Name' => $data['full_name'],
            'Course' => $data['course'] ?? null,
            'Batch_Year' => (int) $data['batch_year'],
            'Employment_Status' => $data['employment_status'],
            'Company_School_Name' => $data['company_school_name'] ?? null,
            'Contact_Info' => $data['contact_info'] ?? null,
        ];

        // The checkbox is only offered for unverified profiles; an existing verification keeps its original verifier.
        if ($request->boolean('mark_verified') && ! $profile->Is_Verified) {
            $attributes += [
                'Is_Verified' => 1,
                'Verified_By' => $request->user()->User_ID,
                'Verified_At' => DB::raw('CURRENT_TIMESTAMP'),
            ];
        }

        // Query-builder update so Last_Updated stays with the database (ON UPDATE CURRENT_TIMESTAMP).
        GraduateProfile::query()->whereKey($profile->getKey())->toBase()->update($attributes);

        return redirect()->route('graduates.edit', $profile)->with('success', 'Graduate profile updated.');
    }

    public function destroy(GraduateProfile $profile, ProfilePictureStore $pictures): RedirectResponse
    {
        $picture = $profile->Profile_Picture;
        $profile->delete();
        $pictures->delete($picture);

        return redirect()->route('graduates');
    }

    /**
     * @return array{batch_year: string, course: string, employment_status: string, q: string}
     */
    private function filters(Request $request): array
    {
        $batchYear = trim((string) $request->query('batch_year', ''));
        if ($batchYear !== '') {
            $batchInt = (int) $batchYear;
            $batchYear = $batchInt >= 2000 && $batchInt <= 2050 ? (string) $batchInt : '';
        }

        $course = trim((string) $request->query('course', ''));
        if (! in_array($course, GraduateProfile::STRANDS, true)) {
            $course = '';
        }

        return [
            'batch_year' => $batchYear,
            'course' => $course,
            'employment_status' => trim((string) $request->query('employment_status', '')),
            'q' => trim((string) $request->query('q', '')),
        ];
    }

    /**
     * @param  array{batch_year: string, course: string, employment_status: string, q: string}  $filters
     * @return Collection<int, GraduateProfile>
     */
    private function search(array $filters): Collection
    {
        return GraduateProfile::query()
            ->join('User_Accounts', 'User_Accounts.User_ID', '=', 'Graduate_Profiles.User_ID')
            ->select([
                'Graduate_Profiles.Profile_ID', 'Graduate_Profiles.User_ID', 'Graduate_Profiles.Full_Name',
                'Graduate_Profiles.Batch_Year', 'Graduate_Profiles.Employment_Status', 'Graduate_Profiles.Company_School_Name',
                'Graduate_Profiles.Contact_Info', 'Graduate_Profiles.Last_Updated', 'Graduate_Profiles.Is_Verified',
                'Graduate_Profiles.Course', 'Graduate_Profiles.Profile_Picture',
                'User_Accounts.Username', 'User_Accounts.Student_No',
            ])
            ->when($filters['batch_year'] !== '', fn (Builder $q) => $q->where('Graduate_Profiles.Batch_Year', (int) $filters['batch_year']))
            ->when($filters['course'] !== '', fn (Builder $q) => $q->where('Graduate_Profiles.Course', $filters['course']))
            ->when($filters['employment_status'] !== '', fn (Builder $q) => $q->where('Graduate_Profiles.Employment_Status', $filters['employment_status']))
            ->when($filters['q'] !== '', function (Builder $q) use ($filters) {
                $like = '%'.$filters['q'].'%';
                $q->where(fn (Builder $w) => $w->where('Graduate_Profiles.Full_Name', 'like', $like)
                    ->orWhere('User_Accounts.Student_No', 'like', $like)
                    ->orWhere('User_Accounts.Username', 'like', $like));
            })
            ->orderByDesc('Graduate_Profiles.Batch_Year')
            ->orderBy('Graduate_Profiles.Full_Name')
            ->limit(200)
            ->get();
    }
}
