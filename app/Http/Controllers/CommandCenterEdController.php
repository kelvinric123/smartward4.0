<?php

namespace App\Http\Controllers;

use App\Models\Hospital;
use App\Models\User;
use App\Services\CommandCenterEd;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Command Center V2 (ED): the emergency department's board for the command
 * centre, over the wards of an Emergency ward type. The page draws the first
 * figures and asks data() for fresh ones every CommandCenterEd::REFRESH_SECONDS.
 * The time-in-ED target is the hospital's (settings()), so every screen agrees.
 */
class CommandCenterEdController extends Controller
{
    public function index()
    {
        $this->authorizeViewer();

        $hospital = Hospital::first();
        $logo = $hospital?->navbar_logo_path ?: $hospital?->logo_path;

        return view('command-center-ed.index', [
            'hospital' => $hospital,
            'logoUrl' => $logo ? Storage::url($logo) : asset('phkl_new.png'),
            'snapshot' => CommandCenterEd::build($hospital),
        ]);
    }

    public function data(): JsonResponse
    {
        $this->authorizeViewer();

        return response()
            ->json(CommandCenterEd::build(Hospital::first()))
            ->header('Cache-Control', 'no-store');
    }

    /** The time-in-ED target, from the board's Settings. */
    public function settings(Request $request): JsonResponse
    {
        $this->authorizeViewer();

        $validated = $request->validate([
            'target_minutes' => ['required', 'integer', Rule::in(CommandCenterEd::TARGET_CHOICES)],
        ]);

        $hospital = Hospital::first();
        abort_unless($hospital, 422, 'Add a hospital first.');
        $hospital->update(['ed_target_minutes' => $validated['target_minutes']]);

        return response()->json(CommandCenterEd::build($hospital))->header('Cache-Control', 'no-store');
    }

    /** The same viewers as Command Center V2: superadmins, hospital admins and IT admins. */
    private function authorizeViewer(): void
    {
        $user = Auth::user();
        if (!$user->isSuperadmin()
            && !$user->hasRole(User::ROLE_HOSPITAL_ADMIN)
            && !$user->hasRole(User::ROLE_IT_ADMIN)) {
            abort(403);
        }
    }
}
