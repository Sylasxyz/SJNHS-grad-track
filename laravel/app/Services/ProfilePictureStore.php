<?php

namespace App\Services;

/**
 * Profile pictures: uploads/profiles/u<user>_<time>.<ext> (Graduate_Profiles.Profile_Picture).
 */
class ProfilePictureStore extends LegacyUploadStore
{
    public const DIRECTORY = 'uploads/profiles';

    public const PREFIX = 'u';
}
