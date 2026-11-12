<?php

namespace App\Support;

use App\Models\Hospital;
use App\Models\Slideshow;
use App\Models\Ward;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * The slideshow the ward dashboards open from the hospital logo: the files the
 * hospital uploads on its Slideshow page, then the built-in pictures kept in
 * base_path('picture') on the server. The Slideshow page switches the logo link
 * on or off, sets the seconds per slide and picks which slides show.
 */
final class WardSlideshow
{
    /** Built-in charts too detailed to read at screen size, shown zoomed to 250%. */
    public const ZOOMED_PICTURES = ['Hosp Org Chart.jpeg', 'Clinical Governance Chart.jpeg'];

    private const EXTENSIONS = ['jpg', 'jpeg', 'png', 'pdf'];

    /** @return list<string> the built-in pictures' file names, by name */
    public static function pictures(): array
    {
        $directory = base_path('picture');

        if (! File::isDirectory($directory)) {
            return [];
        }

        return collect(File::files($directory))
            ->filter(fn ($file) => in_array(strtolower($file->getExtension()), self::EXTENSIONS, true))
            ->map(fn ($file) => $file->getFilename())
            ->values()
            ->all();
    }

    public static function isPdf(string $fileName): bool
    {
        return strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) === 'pdf';
    }

    /**
     * What the ward's slideshow plays, in order: the hospital's switched-on uploads
     * for every ward or this one (by their order, newest first within one), then
     * the built-in pictures the hospital hasn't hidden.
     *
     * @return list<array{url: string, name: string, type: string, scale250: bool}>
     */
    public static function slides(Ward $ward, Hospital $hospital): array
    {
        $slides = Slideshow::where('hospital_id', $hospital->id)
            ->where(fn ($query) => $query->whereNull('ward_id')->orWhere('ward_id', $ward->id))
            ->where('is_active', true)
            ->orderBy('order')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Slideshow $slide) => [
                'url' => Storage::url($slide->file_path),
                'name' => $slide->displayName(),
                'type' => str_contains($slide->file_type, 'pdf') ? 'pdf' : 'image',
                'scale250' => false,
            ])
            ->all();

        $hidden = $hospital->hiddenSlideshowPictures();

        foreach (self::pictures() as $fileName) {
            if (in_array($fileName, $hidden, true)) {
                continue;
            }

            $slides[] = [
                'url' => route('picture.serve', ['filename' => $fileName]),
                'name' => pathinfo($fileName, PATHINFO_FILENAME),
                'type' => self::isPdf($fileName) ? 'pdf' : 'image',
                'scale250' => in_array($fileName, self::ZOOMED_PICTURES, true),
            ];
        }

        return $slides;
    }
}
