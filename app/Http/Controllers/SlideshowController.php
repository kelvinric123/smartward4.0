<?php

namespace App\Http\Controllers;

use App\Models\Hospital;
use App\Models\Slideshow;
use App\Models\User;
use App\Support\WardSlideshow;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class SlideshowController extends Controller implements HasMiddleware
{
    /**
     * The roles that manage the hospital (see HospitalController): the slideshow
     * and its settings show on every ward dashboard.
     */
    public static function middleware(): array
    {
        return [
            function (Request $request, Closure $next) {
                $user = $request->user();
                abort_unless(
                    $user->isSuperadmin() || $user->hasRole(User::ROLE_HOSPITAL_ADMIN) || $user->hasRole(User::ROLE_IT_ADMIN),
                    403
                );

                return $next($request);
            },
        ];
    }

    public function index(Hospital $hospital)
    {
        $hospital->load(['wards']);
        $slides = $hospital->slideshows()->with('ward')->orderBy('order')->orderByDesc('created_at')->get();
        $hidden = $hospital->hiddenSlideshowPictures();
        $pictures = collect(WardSlideshow::pictures())->map(fn ($fileName) => [
            'file' => $fileName,
            'name' => pathinfo($fileName, PATHINFO_FILENAME),
            'pdf' => WardSlideshow::isPdf($fileName),
            'shown' => ! in_array($fileName, $hidden, true),
        ]);

        return view('admin.hospitals.slideshows.index', compact('hospital', 'slides', 'pictures'));
    }

    public function store(Request $request, Hospital $hospital)
    {
        $request->validate([
            'ward_id' => 'nullable|exists:wards,id',
            'file' => 'required|file|mimes:jpeg,jpg,png,pdf|max:51200', // max 50MB
            'title' => 'nullable|string|max:100',
            'order' => 'nullable|integer|min:0',
        ]);

        $file = $request->file('file');
        $path = $file->store('slideshows', 'public');

        $slide = [
            'ward_id' => $request->ward_id,
            'file_path' => $path,
            'file_type' => $file->getClientMimeType(),
            'order' => $request->order ?? 0,
            'is_active' => true,
        ];
        // Named after the file unless given a name; a server without the title column yet uploads as before
        if (Schema::hasColumn('slideshows', 'title')) {
            $slide['title'] = $request->filled('title')
                ? trim($request->title)
                : mb_substr(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), 0, 100);
        }
        $hospital->slideshows()->create($slide);

        return redirect()->back()->with('success', 'File uploaded successfully.');
    }

    /**
     * The Slideshow page's settings: whether the ward dashboard logo opens the
     * slideshow, the seconds per slide, and each slide's name, order and whether
     * it shows, uploaded or built in.
     */
    public function updateSettings(Request $request, Hospital $hospital)
    {
        // A server that hasn't run the migration keeps the old slideshow until it does
        if (! Hospital::hasDisplaySettings()) {
            return redirect()
                ->route('hospitals.slideshows.index', $hospital)
                ->withErrors(['slideshow_enabled' => 'The slideshow settings can be saved once the database migrations have run (php artisan migrate).']);
        }

        $validated = $request->validate([
            'slideshow_enabled' => 'required|boolean',
            'slideshow_seconds' => 'required|integer|min:' . Hospital::MIN_SLIDESHOW_SECONDS . '|max:' . Hospital::MAX_SLIDESHOW_SECONDS,
            'slides' => 'sometimes|array',
            'slides.*.active' => 'sometimes|boolean',
            'slides.*.order' => 'sometimes|nullable|integer|min:0|max:9999',
            'slides.*.title' => 'sometimes|nullable|string|max:100',
            'pictures' => 'sometimes|array',
            'pictures.*.file' => 'required|string',
            'pictures.*.shown' => 'required|boolean',
        ], [
            'slideshow_seconds.min' => 'Show each slide for at least :min seconds.',
            'slideshow_seconds.max' => 'Show each slide for at most :max seconds.',
            'slides.*.order.integer' => 'Each slide\'s order is a whole number.',
            'slides.*.title.max' => 'Keep each slide\'s name to :max characters.',
        ]);

        // Built-in pictures that are switched off; files no longer on the server drop out
        $hidden = collect($validated['pictures'] ?? [])
            ->reject(fn ($picture) => (bool) $picture['shown'])
            ->pluck('file')
            ->intersect(WardSlideshow::pictures())
            ->values()
            ->all();

        $hospital->update([
            'slideshow_enabled' => (bool) $validated['slideshow_enabled'],
            'slideshow_seconds' => (int) $validated['slideshow_seconds'],
            'slideshow_hidden_pictures' => $hidden ?: null,
        ]);

        $fields = $validated['slides'] ?? [];
        $hasTitles = Schema::hasColumn('slideshows', 'title');
        foreach ($hospital->slideshows()->whereIn('id', array_keys($fields))->get() as $slide) {
            $changes = $fields[$slide->id];
            $update = [];
            if (array_key_exists('active', $changes)) {
                $update['is_active'] = (bool) $changes['active'];
            }
            if (array_key_exists('order', $changes)) {
                $update['order'] = (int) ($changes['order'] ?? 0);
            }
            if (array_key_exists('title', $changes) && $hasTitles) {
                $title = trim((string) $changes['title']);
                $update['title'] = $title === '' ? null : $title;
            }
            $slide->update($update);
        }

        return redirect()
            ->route('hospitals.slideshows.index', $hospital)
            ->with('success', 'Slideshow settings saved.');
    }

    public function destroy(Hospital $hospital, Slideshow $slideshow)
    {
        if ($slideshow->hospital_id !== $hospital->id) {
            abort(403);
        }

        if (Storage::disk('public')->exists($slideshow->file_path)) {
            Storage::disk('public')->delete($slideshow->file_path);
        }

        $slideshow->delete();

        return redirect()->back()->with('success', 'File deleted successfully.');
    }
}
