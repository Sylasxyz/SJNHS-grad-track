<?php

namespace App\Http\Controllers;

use App\Models\GraduateProfile;
use App\Services\ProfilePictureStore;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves a graduate's profile picture from the shared uploads/profiles folder
 * after GraduateProfilePolicy::view (the owner, or staff).
 */
class ProfilePictureController extends Controller
{
    public function __invoke(GraduateProfile $profile, ProfilePictureStore $pictures): BinaryFileResponse
    {
        $path = $pictures->path($profile->Profile_Picture);
        abort_if($path === null, 404);

        return response()->file($path, ['Cache-Control' => 'private, max-age=86400']);
    }
}
