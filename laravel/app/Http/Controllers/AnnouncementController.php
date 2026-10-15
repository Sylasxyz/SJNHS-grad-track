<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Services\AnnouncementImageStore;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Announcements, ported from announcements.php (list, archive, restore, delete)
 * and announcement_create.php (posting). Permissions live in AnnouncementPolicy.
 */
class AnnouncementController extends Controller
{
    public function index(Request $request): View
    {
        $archived = $request->query('view') === 'archived'
            && $request->user()->can('viewArchived', Announcement::class);

        $announcements = Announcement::query()
            ->with('author:User_ID,Username')
            ->where('Audience', Announcement::AUDIENCE_EVERYONE)
            ->where('Is_Archived', $archived ? 1 : 0)
            ->orderByDesc('Date_Posted')
            ->get();

        return view('announcements.index', [
            'announcements' => $announcements,
            'archived' => $archived,
        ]);
    }

    public function create(): View
    {
        return view('announcements.create');
    }

    public function store(Request $request, AnnouncementImageStore $images): RedirectResponse
    {
        $noLinks = function (string $attribute, mixed $value, Closure $fail) {
            if (preg_match('/https?:\/\//i', (string) $value)) {
                $fail('Announcements cannot contain external links (http:// or https://). Please remove any URLs.');
            }
        };

        $data = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:150', $noLinks],
            'message' => ['required', 'string', 'min:5', 'max:2000', $noLinks, function (string $attribute, mixed $value, Closure $fail) {
                if (preg_match('/bit\.ly|tinyurl|t\.co|goo\.gl/i', (string) $value)) {
                    $fail('Announcements cannot contain shortened URLs or suspicious links.');
                }
            }],
            'announcement_image' => ['bail', 'nullable', 'file', 'max:3072', 'mimetypes:'.implode(',', array_keys(AnnouncementImageStore::EXTENSIONS))],
        ], [
            'title.required' => 'Title must be at least 3 characters.',
            'title.string' => 'Title must be at least 3 characters.',
            'title.min' => 'Title must be at least 3 characters.',
            'title.max' => 'Title is too long. Maximum 150 characters only.',
            'message.required' => 'Message must be at least 5 characters.',
            'message.string' => 'Message must be at least 5 characters.',
            'message.min' => 'Message must be at least 5 characters.',
            'message.max' => 'Message is too long. Maximum 2000 characters only.',
            'announcement_image.uploaded' => 'Image upload failed.',
            'announcement_image.file' => 'Image upload failed.',
            'announcement_image.max' => 'Image must be 3MB or less.',
            'announcement_image.mimetypes' => 'Image must be JPG, PNG, or WEBP.',
        ]);

        $imagePath = null;
        if ($request->hasFile('announcement_image')) {
            $imagePath = $images->store($request->file('announcement_image'), $request->user()->User_ID);
            if ($imagePath === null) {
                return back()->withInput()->withErrors(['announcement_image' => 'Unable to save image.']);
            }
        }

        $announcement = new Announcement([
            'Author_ID' => $request->user()->User_ID,
            'Title' => $data['title'],
            'Message' => $data['message'],
            'Image_Path' => $imagePath,
            'Audience' => Announcement::AUDIENCE_EVERYONE,
        ]);
        // Date_Posted keeps the database default, as in the legacy insert.
        $announcement->timestamps = false;

        try {
            $announcement->save();
        } catch (Throwable $e) {
            report($e);
            $images->delete($imagePath);

            return back()->withInput()->with('error', 'Could not post announcement. Please try again.');
        }

        return redirect()->route('announcements.create')->with('success', 'Announcement posted successfully.');
    }

    public function archive(Announcement $announcement): RedirectResponse
    {
        $announcement->update(['Is_Archived' => true]);

        return redirect()->route('announcements')->with('success', 'Announcement archived.');
    }

    public function restore(Announcement $announcement): RedirectResponse
    {
        $announcement->update(['Is_Archived' => false]);

        return redirect()->route('announcements', ['view' => 'archived'])
            ->with('success', 'Announcement restored to active list.');
    }

    public function destroy(Announcement $announcement, AnnouncementImageStore $images): RedirectResponse
    {
        $imagePath = $announcement->Image_Path;
        $announcement->delete();
        $images->delete($imagePath);

        return back(fallback: route('announcements'))->with('success', 'Announcement permanently deleted.');
    }

    public function image(Announcement $announcement, AnnouncementImageStore $images): BinaryFileResponse
    {
        $path = $images->path($announcement->Image_Path);
        abort_if($path === null, 404);

        return response()->file($path, ['Cache-Control' => 'private, max-age=86400']);
    }
}
