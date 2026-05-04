<?php

namespace App\Http\Controllers;

use App\Models\Hospital;
use App\Models\Slideshow;
use App\Models\Ward;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SlideshowController extends Controller
{
    public function index(Hospital $hospital)
    {
        $hospital->load(['slideshows.ward', 'wards']);
        return view('admin.hospitals.slideshows.index', compact('hospital'));
    }

    public function store(Request $request, Hospital $hospital)
    {
        $request->validate([
            'ward_id' => 'nullable|exists:wards,id',
            'file' => 'required|file|mimes:jpeg,jpg,png,pdf|max:51200', // max 50MB
            'order' => 'nullable|integer|min:0',
        ]);

        $file = $request->file('file');
        $path = $file->store('slideshows', 'public');

        $hospital->slideshows()->create([
            'ward_id' => $request->ward_id,
            'file_path' => $path,
            'file_type' => $file->getClientMimeType(),
            'order' => $request->order ?? 0,
            'is_active' => true,
        ]);

        return redirect()->back()->with('success', 'File uploaded successfully.');
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
