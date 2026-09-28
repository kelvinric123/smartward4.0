<?php

namespace App\Http\Controllers;

use App\Models\Hospital;
use App\Models\User;
use App\Models\WardDashboardSetting;
use App\Services\CommandCenterSummary;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Command Center V2: the executive summary of every ward for the management
 * screen in the command centre. The page draws the first figures and asks
 * data() for fresh ones every CommandCenterSummary::REFRESH_SECONDS.
 */
class CommandCenterV2Controller extends Controller
{
    public function index()
    {
        $this->authorizeViewer();

        $hospital = Hospital::first();
        $logo = $hospital?->navbar_logo_path ?: $hospital?->logo_path;

        return view('command-center-v2.index', [
            'hospital' => $hospital,
            'logoUrl' => $logo ? Storage::url($logo) : asset('phkl_new.png'),
            'snapshot' => CommandCenterSummary::build($this->ewsSystem()),
        ]);
    }

    public function data()
    {
        $this->authorizeViewer();

        return response()
            ->json(CommandCenterSummary::build($this->ewsSystem()))
            ->header('Cache-Control', 'no-store');
    }

    /** The Command Center's viewers: superadmins, hospital admins and IT admins. */
    private function authorizeViewer(): void
    {
        $user = Auth::user();
        if (!$user->isSuperadmin()
            && !$user->hasRole(User::ROLE_HOSPITAL_ADMIN)
            && !$user->hasRole(User::ROLE_IT_ADMIN)) {
            abort(403);
        }
    }

    /** The EWS the viewer's ward dashboard scores with, as the Command Center uses, so the two agree. */
    private function ewsSystem(): string
    {
        $settings = WardDashboardSetting::where('user_id', Auth::id())->first();
        $clinical = $settings && is_array($settings->clinical_settings) ? $settings->clinical_settings : [];

        return $clinical['ews_system'] ?? 'ews_ihh';
    }
}
