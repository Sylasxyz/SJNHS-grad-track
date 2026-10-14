<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

/**
 * Images kept in a folder under the legacy uploads/ directory, so that pages from
 * both apps resolve the same stored paths ("uploads/<folder>/<prefix><id>_<time>.<ext>").
 */
abstract class LegacyUploadStore
{
    public const DIRECTORY = '';

    public const PREFIX = '';

    public const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * Saves a validated upload and returns its stored path, or null if it could not be saved.
     */
    public function store(UploadedFile $file, int $ownerId): ?string
    {
        $extension = self::EXTENSIONS[$file->getMimeType()] ?? null;
        if ($extension === null) {
            return null;
        }

        $directory = config('school.uploads_root').'/'.static::DIRECTORY;
        if (! is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $filename = static::PREFIX.$ownerId.'_'.time().'.'.$extension;

        try {
            $file->move($directory, $filename);
        } catch (FileException) {
            return null;
        }

        return static::DIRECTORY.'/'.$filename;
    }

    /**
     * Absolute path of an existing file inside this store's folder, or null.
     */
    public function path(?string $storedPath): ?string
    {
        $storedPath = trim(str_replace('\\', '/', (string) $storedPath), '/');
        $filename = substr($storedPath, strlen(static::DIRECTORY) + 1);

        if (! str_starts_with($storedPath, static::DIRECTORY.'/') || $filename === '' || $filename !== basename($filename)) {
            return null;
        }

        $absolute = config('school.uploads_root').'/'.static::DIRECTORY.'/'.$filename;

        return is_file($absolute) ? $absolute : null;
    }

    public function delete(?string $storedPath): void
    {
        if ($absolute = $this->path($storedPath)) {
            @unlink($absolute);
        }
    }
}
