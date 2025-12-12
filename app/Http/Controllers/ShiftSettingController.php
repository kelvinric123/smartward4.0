<?php

namespace App\Http\Controllers;

use App\Models\ShiftSetting;
use App\Models\Ward;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ShiftSettingController extends Controller
{
    /**
     * Display shift settings iframe for a ward.
     */
    public function index(Request $request): View
    {
        $wardId = $request->input('ward_id');
        $ward = $wardId ? Ward::find($wardId) : null;

        if (!$ward) {
            $ward = Ward::where('is_active', true)->first();
        }

        $shifts = [];
        if ($ward) {
            $shifts = ShiftSetting::where('ward_id', $ward->id)
                ->orderBy('display_order')
                ->get();

            // If no shifts exist, create defaults
            if ($shifts->isEmpty()) {
                $defaults = ShiftSetting::getDefaults();
                foreach ($defaults as $default) {
                    ShiftSetting::create(array_merge($default, [
                        'ward_id' => $ward->id,
                        'is_active' => true,
                    ]));
                }
                $shifts = ShiftSetting::where('ward_id', $ward->id)
                    ->orderBy('display_order')
                    ->get();
            }
        }

        $wards = Ward::where('is_active', true)->orderBy('ward_name')->get();

        return view('wards.shift-settings', [
            'ward' => $ward,
            'wards' => $wards,
            'shifts' => $shifts,
        ]);
    }

    /**
     * Update shift settings for a ward.
     */
    public function update(Request $request)
    {
        $request->validate([
            'ward_id' => 'required|exists:wards,id',
            'shifts' => 'required|array',
            'shifts.*.id' => 'nullable|exists:shift_settings,id',
            'shifts.*.shift_code' => 'required|string|max:10',
            'shifts.*.shift_name' => 'required|string|max:50',
            'shifts.*.start_time' => 'required|date_format:H:i',
            'shifts.*.end_time' => 'required|date_format:H:i',
            'shifts.*.is_active' => 'boolean',
        ]);

        $wardId = $request->input('ward_id');
        $shiftsData = $request->input('shifts');

        foreach ($shiftsData as $index => $shiftData) {
            if (!empty($shiftData['id'])) {
                // Update existing shift
                $shift = ShiftSetting::find($shiftData['id']);
                if ($shift && $shift->ward_id == $wardId) {
                    $shift->update([
                        'shift_code' => $shiftData['shift_code'],
                        'shift_name' => $shiftData['shift_name'],
                        'start_time' => $shiftData['start_time'],
                        'end_time' => $shiftData['end_time'],
                        'is_active' => $shiftData['is_active'] ?? true,
                        'display_order' => $index + 1,
                    ]);
                }
            } else {
                // Create new shift
                ShiftSetting::create([
                    'ward_id' => $wardId,
                    'shift_code' => $shiftData['shift_code'],
                    'shift_name' => $shiftData['shift_name'],
                    'start_time' => $shiftData['start_time'],
                    'end_time' => $shiftData['end_time'],
                    'is_active' => $shiftData['is_active'] ?? true,
                    'display_order' => $index + 1,
                ]);
            }
        }

        Log::info('Shift settings updated', [
            'ward_id' => $wardId,
            'shifts_count' => count($shiftsData),
        ]);

        return redirect()
            ->route('ward.shift-settings', ['ward_id' => $wardId])
            ->with('success', 'Shift settings updated successfully.');
    }

    /**
     * Reset shift settings to defaults.
     */
    public function reset(Request $request)
    {
        $request->validate([
            'ward_id' => 'required|exists:wards,id',
        ]);

        $wardId = $request->input('ward_id');

        // Delete existing shifts
        ShiftSetting::where('ward_id', $wardId)->delete();

        // Create defaults
        $defaults = ShiftSetting::getDefaults();
        foreach ($defaults as $default) {
            ShiftSetting::create(array_merge($default, [
                'ward_id' => $wardId,
                'is_active' => true,
            ]));
        }

        Log::info('Shift settings reset to defaults', [
            'ward_id' => $wardId,
        ]);

        return redirect()
            ->route('ward.shift-settings', ['ward_id' => $wardId])
            ->with('success', 'Shift settings reset to defaults.');
    }
}


