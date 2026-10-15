<?php

namespace App\Services;

/**
 * Announcement images: uploads/announcements/a<author>_<time>.<ext> (Announcements.Image_Path).
 */
class AnnouncementImageStore extends LegacyUploadStore
{
    public const DIRECTORY = 'uploads/announcements';

    public const PREFIX = 'a';
}
