<?php

namespace App\Http\Controllers;

use App\Models\LabInvestigation;
use App\Services\LabInvestigations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Patient Details > Lab Investigations: marking results reviewed, and the system-wide
 * switches in Settings > Patient Additional Info.
 */
class LabInvestigationController extends Controller
{
    public function review(LabInvestigation $labInvestigation): RedirectResponse
    {
        $redirect = redirect()->route('ward.patient-details', [
            'patient_id' => $labInvestigation->patient_id,
            'active_tab' => 'lab',
        ]);

        if (! $labInvestigation->awaitingReview()) {
            return $redirect->with('error', $labInvestigation->test_name . ' has no result waiting for review.');
        }

        $labInvestigation->update([
            'reviewed_at' => now(),
            'reviewed_by' => Auth::id(),
            'reviewed_by_name' => Auth::user()?->name,
        ]);

        Log::info('Lab investigation reviewed', [
            'lab_investigation_id' => $labInvestigation->id,
            'patient_id' => $labInvestigation->patient_id,
            'order_no' => $labInvestigation->order_no,
            'user_id' => Auth::id(),
        ]);

        return $redirect->with('success', $labInvestigation->test_name . ' marked as reviewed.');
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        LabInvestigations::save($request->boolean('enabled'), $request->boolean('sample'));

        Log::info('Lab investigation settings updated', LabInvestigations::settings() + ['user_id' => Auth::id()]);

        return back()
            ->with('success', 'Lab Investigations settings updated. This applies to all users.')
            ->with('settings_tab', 'patient-additional-info');
    }
}
