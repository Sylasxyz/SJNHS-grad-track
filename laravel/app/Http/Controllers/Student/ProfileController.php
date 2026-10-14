<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\GraduateProfile;
use App\Services\ProfilePictureStore;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * My Graduate Profile, ported from profile.php. The profile is always the
 * signed-in student's own row (Graduate_Profiles.User_ID).
 */
class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        return view('student.profile', [
            'profile' => $this->ownProfile($request),
        ]);
    }

    public function update(Request $request, ProfilePictureStore $pictures): RedirectResponse
    {
        $profile = $this->ownProfile($request);
        $isVerified = (bool) $profile?->Is_Verified;
        $maxYear = now()->year + 1;

        $data = $request->validate([
            'full_name' => ['required', 'string', 'min:3', 'max:150', function (string $attribute, mixed $value, Closure $fail) use ($profile, $isVerified) {
                if ($isVerified && (string) $value !== (string) $profile->Full_Name) {
                    $fail('Your profile is verified. You cannot change your Full Name. Contact your Instructor.');
                }
            }],
            'course' => ['required', 'string', 'in:'.implode(',', GraduateProfile::STRANDS)],
            'batch_year' => ['required', 'integer', 'between:1990,'.$maxYear],
            'employment_status' => ['required', 'string', 'in:'.implode(',', GraduateProfile::EMPLOYMENT_STATUSES)],
            'company_school_name' => ['nullable', 'string', 'max:200'],
            'contact_info' => ['nullable', 'string',
                function (string $attribute, mixed $value, Closure $fail) {
                    if (! preg_match('/^(09\d{9}|\+639\d{9})$/', (string) $value)) {
                        $fail('Contact info must be a valid Philippine phone number. Use format: 09XXXXXXXXX or +639XXXXXXXXX.');
                    }
                },
                'max:20',
                function (string $attribute, mixed $value, Closure $fail) {
                    if (! preg_match('/^[0-9\+\-\s\(\)]{7,20}$/', (string) $value)) {
                        $fail('Contact info must be a valid phone number (digits, +, -, spaces only).');
                    }
                },
            ],
            'profile_picture' => ['bail', 'nullable', 'file', 'max:2048', 'mimetypes:'.implode(',', array_keys(ProfilePictureStore::EXTENSIONS))],
        ], [
            'full_name.required' => 'Full name must be at least 3 characters.',
            'full_name.string' => 'Full name must be at least 3 characters.',
            'full_name.min' => 'Full name must be at least 3 characters.',
            'full_name.max' => 'Full name is too long. Maximum 150 characters only.',
            'course.*' => 'Please select a strand (HUMMS, STEMS, or ABM).',
            'batch_year.*' => 'Batch year is invalid.',
            'employment_status.*' => 'Employment status is invalid.',
            'company_school_name.*' => 'Company / School name is too long. Maximum 200 characters only.',
            'contact_info.string' => 'Contact info must be a valid phone number (digits, +, -, spaces only).',
            'contact_info.max' => 'Contact info is too long. Maximum 20 characters only.',
            'profile_picture.uploaded' => 'Profile picture upload failed.',
            'profile_picture.file' => 'Profile picture upload failed.',
            'profile_picture.max' => 'Profile picture must be 2MB or less.',
            'profile_picture.mimetypes' => 'Profile picture must be JPG, PNG, or WEBP.',
        ]);

        $oldPicture = (string) $profile?->Profile_Picture;
        $newPicture = $oldPicture;
        $removed = $oldPicture !== '' && $request->boolean('remove_profile_picture');
        if ($removed) {
            $newPicture = '';
        }

        $uploaded = false;
        if ($request->hasFile('profile_picture')) {
            $stored = $pictures->store($request->file('profile_picture'), (int) $request->user()->User_ID);
            if ($stored === null) {
                return back()->withInput()->withErrors(['profile_picture' => 'Unable to save profile picture.']);
            }
            $newPicture = $stored;
            $uploaded = true;
            $removed = false;
        }

        $profile ??= new GraduateProfile(['User_ID' => $request->user()->User_ID]);
        $profile->fill([
            'Full_Name' => $data['full_name'],
            'Course' => $data['course'],
            'Batch_Year' => (int) $data['batch_year'],
            'Employment_Status' => $data['employment_status'],
            'Company_School_Name' => $data['company_school_name'] ?? null,
            'Contact_Info' => $data['contact_info'] ?? null,
            'Profile_Picture' => $newPicture !== '' ? $newPicture : null,
        ]);
        // Last_Updated is maintained by the database (DEFAULT / ON UPDATE CURRENT_TIMESTAMP).
        $profile->timestamps = false;

        try {
            $profile->save();
        } catch (Throwable $e) {
            report($e);
            if ($uploaded) {
                $pictures->delete($newPicture);
            }

            return back()->withInput()->with('error', 'Could not save profile. Please try again.');
        }

        if ($oldPicture !== '' && ($removed || $uploaded) && $oldPicture !== $newPicture) {
            $pictures->delete($oldPicture);
        }

        $message = match (true) {
            $uploaded => 'Profile saved. Profile picture updated.',
            $removed => 'Profile saved. Profile picture removed.',
            default => 'Profile saved successfully.',
        };

        return redirect()->route('student.profile')->with('success', $message);
    }

    /**
     * "Remove photo" button: clears the picture without re-saving the other fields.
     */
    public function destroyPicture(Request $request, ProfilePictureStore $pictures): RedirectResponse
    {
        $profile = $this->ownProfile($request);
        $picture = (string) $profile?->Profile_Picture;

        if ($picture === '') {
            return redirect()->route('student.profile')->with('error', 'No profile picture to remove.');
        }

        $profile->timestamps = false;
        $profile->update(['Profile_Picture' => null]);
        $pictures->delete($picture);

        return redirect()->route('student.profile')->with('success', 'Profile picture removed.');
    }

    private function ownProfile(Request $request): ?GraduateProfile
    {
        return GraduateProfile::query()->where('User_ID', $request->user()->User_ID)->first();
    }
}
