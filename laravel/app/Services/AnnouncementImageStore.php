<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

/**
 * Announcement images live in the legacy uploads/announcements folder so that
 * pages from both apps resolve the same Image_Path values.
 */
class AnnouncementImageStore
{
    public const DIRECTORY = 'uploads/announcements';

    public const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * Saves a validated upload and returns its Image_Path, or null if it could not be saved.
     */
    public function store(UploadedFile $file, int $authorId): ?string
    {
        $extension = self::EXTENSIONS[$file->getMimeType()] ?? null;
        if ($extension === null) {
            return null;
        }

        $directory = config('school.uploads_root').'/'.self::DIRECTORY;
        if (! is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $filename = 'a'.$authorId.'_'.time().'.'.$extension;

        try {
            $file->move($directory, $filename);
        } catch (FileException) {
            return null;
        }

        return self::DIRECTORY.'/'.$filename;
    }

    /**
     * Absolute path of an existing image inside the announcements folder, or null.
     */
    public function path(?string $imagePath): ?string
    {
        $imagePath = trim((string) $imagePath);
        $filename = substr($imagePath, strlen(self::DIRECTORY) + 1);

        if (! str_starts_with($imagePath, self::DIRECTORY.'/') || $filename === '' || $filename !== basename($filename)) {
            return null;
        }

        $absolute = config('school.uploads_root').'/'.self::DIRECTORY.'/'.$filename;

        return is_file($absolute) ? $absolute : null;
    }

    public function delete(?string $imagePath): void
    {
        if ($absolute = $this->path($imagePath)) {
            @unlink($absolute);
        }
    }
}
