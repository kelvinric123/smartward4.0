<?php

namespace App\Services\NursingPlan;

use App\Models\NursingCarePlanEvaluation;
use App\Models\NursingCarePlanItem;
use App\Support\NursingCarePlanLibrary;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Validation for the nursing care plan, shared by the ward dashboard form
 * and the nurse app so the two can never accept different things.
 */
class NursingCarePlanRules
{
    /** Adding a diagnosis: a library template, or one written from scratch. */
    public static function item(): array
    {
        return [
            'template_key' => ['nullable', Rule::in(array_keys(NursingCarePlanLibrary::TEMPLATES))],
            'diagnosis' => 'required_without:template_key|nullable|string|max:255',
            'related_to' => 'nullable|string|max:1000',
            'goal' => 'required_without:template_key|nullable|string|max:1000',
            'interventions' => 'nullable|array|max:20',
            'interventions.*' => 'nullable|string|max:255',
        ];
    }

    public static function update(): array
    {
        return [
            'diagnosis' => 'sometimes|required|string|max:255',
            'related_to' => 'nullable|string|max:1000',
            'goal' => 'sometimes|required|string|max:1000',
            'interventions' => 'nullable|array|max:20',
            'interventions.*' => 'nullable|string|max:255',
        ];
    }

    /** A goal not met needs a word on why - that is what the next shift acts on. */
    public static function evaluation(Request $request): array
    {
        return [
            'outcome' => ['required', Rule::in(array_keys(NursingCarePlanEvaluation::OUTCOMES))],
            'note' => [
                Rule::requiredIf(fn() => $request->input('outcome') === NursingCarePlanEvaluation::OUTCOME_NOT_MET),
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    public static function close(Request $request): array
    {
        return [
            'status' => ['required', Rule::in([NursingCarePlanItem::STATUS_RESOLVED, NursingCarePlanItem::STATUS_DISCONTINUED])],
            'note' => [
                Rule::requiredIf(fn() => $request->input('status') === NursingCarePlanItem::STATUS_DISCONTINUED),
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    public static function messages(): array
    {
        return [
            'diagnosis.required_without' => 'Name the nursing diagnosis, or pick one from the list.',
            'goal.required_without' => 'Write the goal for this diagnosis.',
            'goal.required' => 'Write the goal for this diagnosis.',
            'outcome.required' => 'Choose whether the goal was met.',
            'note.required' => 'Say briefly why - the next shift works from it.',
            'status.required' => 'Choose resolved or discontinued.',
        ];
    }
}
