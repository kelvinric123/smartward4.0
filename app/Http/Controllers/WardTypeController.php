<?php

namespace App\Http\Controllers;

use App\Models\ClinicalIndicator;
use App\Models\Hospital;
use App\Models\WardType;
use App\Support\ClinicalIndicatorLibrary;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WardTypeController extends Controller
{
    public function index()
    {
        $wardTypes = WardType::with(['hospital', 'clinicalIndicators'])
            ->orderBy('hospital_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20, ['*'], 'ward_type_page');

        // Not paginated: a page break would split a category down the middle,
        // and the list is a few dozen at most.
        $clinicalIndicators = ClinicalIndicator::withCount('wardTypes')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $indicatorGroups = ClinicalIndicatorLibrary::groupByCategory($clinicalIndicators);

        return view('admin.ward-types.index', compact('wardTypes', 'clinicalIndicators', 'indicatorGroups'));
    }

    public function create()
    {
        return view('admin.ward-types.create', $this->formOptions());
    }

    public function store(Request $request)
    {
        $validated = $this->validateWardType($request);

        $indicatorIds = $validated['clinical_indicator_ids'] ?? [];
        unset($validated['clinical_indicator_ids']);

        $validated['code'] = strtoupper(trim($validated['code']));
        $validated['is_critical_care'] = $request->boolean('is_critical_care');
        $validated['sort_order'] = $validated['sort_order'] ?? ((int) WardType::max('sort_order') + 1);
        $validated['is_active'] = true;

        $wardType = WardType::create($validated);
        $wardType->clinicalIndicators()->sync($indicatorIds);

        return redirect()->route('ward-types.index')->with('success', 'Ward type created successfully.');
    }

    public function edit(WardType $wardType)
    {
        $selectedIndicatorIds = $wardType->clinicalIndicators()->pluck('clinical_indicators.id')->all();

        return view('admin.ward-types.edit', array_merge(
            $this->formOptions(),
            compact('wardType', 'selectedIndicatorIds')
        ));
    }

    public function update(Request $request, WardType $wardType)
    {
        $validated = $this->validateWardType($request, $wardType);

        $indicatorIds = $validated['clinical_indicator_ids'] ?? [];
        unset($validated['clinical_indicator_ids']);

        $validated['code'] = strtoupper(trim($validated['code']));
        $validated['is_critical_care'] = $request->boolean('is_critical_care', $wardType->is_critical_care);
        $validated['sort_order'] = $validated['sort_order'] ?? $wardType->sort_order;

        $wardType->update($validated);
        $wardType->clinicalIndicators()->sync($indicatorIds);

        return redirect()->route('ward-types.index')->with('success', 'Ward type updated successfully.');
    }

    public function toggleActive(WardType $wardType)
    {
        $wardType->update(['is_active' => !$wardType->is_active]);
        $status = $wardType->is_active ? 'activated' : 'deactivated';
        return redirect()->route('ward-types.index')->with('success', "Ward type {$status} successfully.");
    }

    public function destroy(WardType $wardType)
    {
        $wardType->delete();
        return redirect()->route('ward-types.index')->with('success', 'Ward type deleted successfully.');
    }

    private function formOptions(): array
    {
        return [
            'hospitals' => Hospital::where('is_active', true)->orderBy('name')->get(),
            'indicatorGroups' => ClinicalIndicatorLibrary::groupByCategory(
                ClinicalIndicator::where('is_active', true)
                    ->orderBy('sort_order')->orderBy('name')->get()
            ),
        ];
    }

    /**
     * Codes only need to be unique inside their own scope, so that two
     * hospitals can each keep, say, a "CCU" of their own without clashing.
     */
    private function validateWardType(Request $request, ?WardType $wardType = null): array
    {
        $hospitalId = $request->input('hospital_id') ?: null;

        return $request->validate([
            'hospital_id' => 'nullable|exists:hospitals,id',
            'code' => [
                'required', 'string', 'max:20',
                Rule::unique('ward_types', 'code')
                    ->where(fn ($query) => $query->where('hospital_id', $hospitalId))
                    ->ignore($wardType?->id),
            ],
            'name' => 'required|string|max:255',
            'clinical_indicator_ids' => 'nullable|array',
            'clinical_indicator_ids.*' => 'exists:clinical_indicators,id',
            'description' => 'nullable|string|max:255',
            'is_critical_care' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ], [
            'code.unique' => $hospitalId
                ? 'This hospital already has a ward type with that code.'
                : 'A system ward type with that code already exists.',
        ]);
    }
}
