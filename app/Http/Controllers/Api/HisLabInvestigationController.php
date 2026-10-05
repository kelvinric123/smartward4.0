<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LabInvestigation;
use App\Models\Patient;
use App\Services\LabInvestigations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Where the HIS (or its interface engine, e.g. from HL7 ORM/ORU) pushes lab orders and
 * results. One request is one order; resend it as it moves from ordered to resulted.
 * Authenticated by an Integration User's API token (AuthenticateIntegrationUser).
 */
class HisLabInvestigationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'patient.mrn' => 'required|string|max:64',
            'patient.rn' => 'nullable|string|max:64',
            'order.order_no' => 'required|string|max:64',
            'order.ordered_at' => 'nullable|date',
            'order.ordered_by' => 'nullable|string|max:255',
            'order.priority' => ['nullable', Rule::in(array_keys(LabInvestigation::PRIORITIES))],
            'order.review_due_at' => 'nullable|date',
            'order.investigations' => 'required|array|min:1',
            'order.investigations.*.name' => 'required|string|max:255',
            'order.investigations.*.code' => 'nullable|string|max:32',
            'order.investigations.*.category' => ['nullable', Rule::in(array_keys(LabInvestigation::CATEGORIES))],
            'order.investigations.*.specimen' => 'nullable|string|max:255',
            'order.investigations.*.status' => ['nullable', Rule::in(array_keys(LabInvestigation::STATUSES))],
            'order.investigations.*.collected_at' => 'nullable|date',
            'order.investigations.*.resulted_at' => 'nullable|date',
            'order.investigations.*.review_due_at' => 'nullable|date',
            'order.investigations.*.reviewed_at' => 'nullable|date',
            'order.investigations.*.reviewed_by' => 'nullable|string|max:255',
            'order.investigations.*.comment' => 'nullable|string|max:2000',
            'order.investigations.*.results' => 'nullable|array',
            'order.investigations.*.results.*.name' => 'required|string|max:255',
            'order.investigations.*.results.*.value' => 'nullable|string|max:255',
            'order.investigations.*.results.*.unit' => 'nullable|string|max:32',
            'order.investigations.*.results.*.range' => 'nullable|string|max:64',
            'order.investigations.*.results.*.flag' => 'nullable|string|max:4',
        ]);

        // The admitted patient this order belongs to; the RN pins the episode when sent
        $patient = Patient::where('is_active', true)
            ->where('mrn', $data['patient']['mrn'])
            ->when($data['patient']['rn'] ?? null, fn ($query, $rn) => $query->where('rn', $rn))
            ->latest('id')
            ->first();

        if (! $patient) {
            return response()->json([
                'message' => 'No active patient with this MRN' . (empty($data['patient']['rn']) ? '' : ' and RN') . '.',
            ], 404);
        }

        $counts = LabInvestigations::ingest($patient, $data['order']);

        Log::info('HIS lab order received', [
            'patient_id' => $patient->id,
            'order_no' => $data['order']['order_no'],
            'integration_user' => $request->attributes->get('integration_user')?->id,
        ] + $counts);

        return response()->json(['ok' => true, 'patient_id' => $patient->id] + $counts);
    }
}
