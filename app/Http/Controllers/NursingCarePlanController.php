<?php

namespace App\Http\Controllers;

use App\Models\NursingCarePlanItem;
use App\Models\Patient;
use App\Services\NursingPlan\NursingCarePlan;
use App\Services\NursingPlan\NursingCarePlanRules;
use App\Support\NursingCarePlanLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * The ward dashboard's Nursing Plan tab: the patient's nursing care plan
 * (diagnoses, goals, interventions, per-shift evaluation). The nurse app
 * works the same plan through NurseAppPatientController, with the same
 * service and rules. Every outcome comes back to the Nursing Plan tab.
 */
class NursingCarePlanController extends Controller
{
    private const TAB = 'nursing_plan';

    public function store(Request $request, Patient $patient): RedirectResponse
    {
        $validated = $this->validateForTab($request, $patient->id, NursingCarePlanRules::item());

        if (!empty($validated['template_key']) && NursingCarePlanItem::where('patient_id', $patient->id)
            ->where('status', NursingCarePlanItem::STATUS_ACTIVE)
            ->where('template_key', $validated['template_key'])
            ->exists()) {
            return $this->backToTab($patient->id)->with('error',
                NursingCarePlanLibrary::TEMPLATES[$validated['template_key']]['diagnosis'] . ' is already in the plan.');
        }

        // Interventions come from one textarea, a line each (sent empty: none)
        if (array_key_exists('interventions_text', $validated)) {
            $validated['interventions'] = preg_split('/\r\n|\r|\n/', (string) $validated['interventions_text']);
            unset($validated['interventions_text']);
        }

        $item = NursingCarePlan::add($patient, $validated, Auth::id());

        Log::info('Care plan item added', ['nursing_care_plan_item_id' => $item->id, 'patient_id' => $patient->id, 'user_id' => Auth::id()]);

        return $this->backToTab($patient->id)->with('success', '"' . $item->diagnosis . '" added to the care plan.');
    }

    public function update(Request $request, NursingCarePlanItem $item): RedirectResponse
    {
        $validated = $this->validateForTab($request, $item->patient_id, NursingCarePlanRules::update() + [
            'interventions_text' => 'nullable|string|max:5000',
        ]);

        if (!$item->isActive()) {
            return $this->backToTab($item->patient_id)->with('error', 'That diagnosis is closed.');
        }

        if (array_key_exists('interventions_text', $validated)) {
            $validated['interventions'] = preg_split('/\r\n|\r|\n/', (string) $validated['interventions_text']);
            unset($validated['interventions_text']);
        }

        NursingCarePlan::update($item, $validated);

        return $this->backToTab($item->patient_id)->with('success', '"' . $item->diagnosis . '" updated.');
    }

    public function evaluate(Request $request, NursingCarePlanItem $item): RedirectResponse
    {
        $validated = $this->validateForTab($request, $item->patient_id, NursingCarePlanRules::evaluation($request));

        if (!$item->isActive()) {
            return $this->backToTab($item->patient_id)->with('error', 'That diagnosis is closed.');
        }

        $evaluation = NursingCarePlan::evaluate($item, $validated['outcome'], $validated['note'] ?? null, Auth::id());

        return $this->backToTab($item->patient_id)->with('success',
            '"' . $item->diagnosis . '" evaluated: ' . $evaluation->outcomeLabel() . '.');
    }

    public function close(Request $request, NursingCarePlanItem $item): RedirectResponse
    {
        $validated = $this->validateForTab($request, $item->patient_id, NursingCarePlanRules::close($request));

        if (!$item->isActive()) {
            return $this->backToTab($item->patient_id)->with('error', 'That diagnosis is already closed.');
        }

        NursingCarePlan::close($item, $validated['status'], $validated['note'] ?? null, Auth::id());

        return $this->backToTab($item->patient_id)->with('success', '"' . $item->diagnosis . '" ' . $validated['status'] . '.');
    }

    // -------------------------------------------------------------- helpers

    /** Patient Details on this tab, named in the URL so a save never lands elsewhere. */
    private function tabUrl(int $patientId): string
    {
        return route('ward.patient-details', ['patient_id' => $patientId, 'active_tab' => self::TAB]);
    }

    private function backToTab(int $patientId): RedirectResponse
    {
        return redirect()->to($this->tabUrl($patientId));
    }

    private function validateForTab(Request $request, int $patientId, array $rules): array
    {
        $rules += ['interventions_text' => 'nullable|string|max:5000'];
        $validator = Validator::make($request->all(), $rules, NursingCarePlanRules::messages());

        if ($validator->fails()) {
            // Own error bag: the tab shows only its own errors, not another tab's
            throw (new ValidationException($validator))
                ->errorBag('nursingPlan')
                ->redirectTo($this->tabUrl($patientId));
        }

        return $validator->validated();
    }
}
