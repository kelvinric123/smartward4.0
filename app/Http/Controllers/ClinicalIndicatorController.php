<?php

namespace App\Http\Controllers;

use App\Models\ClinicalIndicator;
use App\Support\ClinicalIndicatorMonitoring;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClinicalIndicatorController extends Controller
{
    /** The monitoring form inputs, which are saved as minutes rather than as posted. */
    private const MONITORING_INPUTS = [
        'monitoring_enabled',
        'monitoring_suggested_value',
        'monitoring_suggested_unit',
        'monitoring_warning_value',
        'monitoring_warning_unit',
    ];

    public function create()
    {
        return view('admin.clinical-indicators.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:clinical_indicators,code',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
        ] + $this->monitoringRules($request));

        $monitoring = $this->monitoringAttributes($request, $validated);
        $validated = Arr::except($validated, self::MONITORING_INPUTS);

        $validated['code'] = strtoupper(trim($validated['code']));
        $validated['sort_order'] = $validated['sort_order'] ?? ((int) ClinicalIndicator::max('sort_order') + 1);
        $validated['is_active'] = true;
        ClinicalIndicator::create($validated + $monitoring);

        return redirect()->route('ward-types.index', ['tab' => 'clinical_indicators'])
            ->with('success', 'Clinical indicator created successfully.');
    }

    public function edit(ClinicalIndicator $clinicalIndicator)
    {
        return view('admin.clinical-indicators.edit', compact('clinicalIndicator'));
    }

    public function update(Request $request, ClinicalIndicator $clinicalIndicator)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:clinical_indicators,code,' . $clinicalIndicator->id,
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
        ] + $this->monitoringRules($request));

        $monitoring = $this->monitoringAttributes($request, $validated);
        $validated = Arr::except($validated, self::MONITORING_INPUTS);

        $validated['code'] = strtoupper(trim($validated['code']));
        $validated['sort_order'] = $validated['sort_order'] ?? $clinicalIndicator->sort_order;
        $clinicalIndicator->update($validated + $monitoring);

        return redirect()->route('ward-types.index', ['tab' => 'clinical_indicators'])
            ->with('success', 'Clinical indicator updated successfully.');
    }

    public function toggleActive(ClinicalIndicator $clinicalIndicator)
    {
        $clinicalIndicator->update(['is_active' => !$clinicalIndicator->is_active]);
        $status = $clinicalIndicator->is_active ? 'activated' : 'deactivated';

        return redirect()->route('ward-types.index', ['tab' => 'clinical_indicators'])
            ->with('success', "Clinical indicator {$status} successfully.");
    }

    public function destroy(ClinicalIndicator $clinicalIndicator)
    {
        $clinicalIndicator->delete();

        return redirect()->route('ward-types.index', ['tab' => 'clinical_indicators'])
            ->with('success', 'Clinical indicator deleted successfully.');
    }

    /**
     * The levels are entered as a number and a unit, and only needed while
     * monitoring is switched on.
     */
    private function monitoringRules(Request $request): array
    {
        $required = $request->boolean('monitoring_enabled') ? 'required' : 'nullable';
        $unit = [$required, Rule::in(array_keys(ClinicalIndicatorMonitoring::UNITS))];

        return [
            'monitoring_enabled' => 'nullable|boolean',
            'monitoring_suggested_value' => "$required|integer|min:1|max:" . ClinicalIndicatorMonitoring::MAX_MINUTES,
            'monitoring_suggested_unit' => $unit,
            'monitoring_warning_value' => "$required|integer|min:1|max:" . ClinicalIndicatorMonitoring::MAX_MINUTES,
            'monitoring_warning_unit' => $unit,
        ];
    }

    /**
     * The monitoring columns to save, in minutes. Switched off, only the switch
     * changes, so the levels last used come back when it is switched on again.
     */
    private function monitoringAttributes(Request $request, array $validated): array
    {
        if (!$request->boolean('monitoring_enabled')) {
            return ['monitoring_enabled' => false];
        }

        $suggested = ClinicalIndicatorMonitoring::toMinutes(
            (int) $validated['monitoring_suggested_value'],
            $validated['monitoring_suggested_unit']
        );
        $warning = ClinicalIndicatorMonitoring::toMinutes(
            (int) $validated['monitoring_warning_value'],
            $validated['monitoring_warning_unit']
        );

        $errors = [];
        if ($suggested > ClinicalIndicatorMonitoring::MAX_MINUTES) {
            $errors['monitoring_suggested_value'] = 'The suggested interval cannot be longer than 30 days.';
        }
        if ($warning > ClinicalIndicatorMonitoring::MAX_MINUTES) {
            $errors['monitoring_warning_value'] = 'The warning level cannot be longer than 30 days.';
        } elseif ($warning <= $suggested) {
            $errors['monitoring_warning_value'] = 'The warning level has to be longer than the suggested interval.';
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'monitoring_enabled' => true,
            'monitoring_suggested_minutes' => $suggested,
            'monitoring_warning_minutes' => $warning,
        ];
    }
}
