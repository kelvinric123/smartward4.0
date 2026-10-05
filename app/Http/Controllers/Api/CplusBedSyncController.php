<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hospital;
use App\Models\Ward;
use App\Services\Cplus\CplusBedImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Where the rpa_cplus_smartward RPA pushes C+ Bed Management: every ward, bed
 * and patient C+ lists. Authenticated by an Integration User's API token
 * (AuthenticateIntegrationUser), never by a user session.
 */
class CplusBedSyncController extends Controller
{
    /** Connection check for the RPA's --check: token accepted, hospital resolved. */
    public function ping(Request $request): JsonResponse
    {
        $hospital = $this->hospital();

        return response()->json([
            'ok' => true,
            'app' => config('app.name'),
            'integration_user' => $request->attributes->get('integration_user')?->name,
            'hospital' => $hospital?->only(['id', 'name']),
            'linked_wards' => Ward::whereNotNull('cplus_location_id')->count(),
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function sync(Request $request, CplusBedImporter $importer): JsonResponse
    {
        $data = $request->validate([
            'facility.id' => 'required|string|max:16',
            'facility.name' => 'nullable|string|max:255',
            'wards' => 'required|array|min:1',
            'wards.*.id' => 'required|string|max:32',
            'wards.*.name' => 'required|string|max:255',
            // C+ unit group: 2 Ward (IP), 6 Day Care, 3 ER (whose zones are tagged Emergency)
            'wards.*.unit_group' => 'nullable|string|max:8',
            'wards.*.beds' => 'present|array',
            'wards.*.beds.*.id' => 'required|string|max:32',
            'wards.*.beds.*.name' => 'required|string|max:255',
            'wards.*.beds.*.room_no' => 'nullable|string|max:32',
            'wards.*.beds.*.status' => 'required|string|in:occupied,available,out_of_service,unknown',
            'wards.*.beds.*.patient' => 'nullable|array',
            'wards.*.beds.*.patient.mrn' => 'required_with:wards.*.beds.*.patient|string|max:64',
            'wards.*.beds.*.patient.rn' => 'nullable|string|max:64',
            'wards.*.beds.*.patient.name' => 'nullable|string|max:255',
            'wards.*.beds.*.patient.gender' => 'nullable|string|max:16',
            'wards.*.beds.*.patient.physician' => 'nullable|string|max:255',
            'wards.*.beds.*.patient.admitted_at' => 'nullable|date',
            'wards.*.beds.*.patient.admitted_at_source' => 'nullable|string|in:census,request',
            // The Isolation box on the C+ admission card; left out when the RPA has not read it
            'wards.*.beds.*.patient.isolation' => 'nullable|boolean',
        ]);

        $hospital = $this->hospital();
        if (! $hospital) {
            return response()->json([
                'message' => 'Set CPLUS_SYNC_HOSPITAL_ID: there is not exactly one active hospital to put the C+ wards in.',
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'hospital' => $hospital->only(['id', 'name']),
            ...$importer->import($hospital, $data),
        ]);
    }

    /** The configured hospital, or the only active one. */
    private function hospital(): ?Hospital
    {
        if ($id = config('cplus.hospital_id')) {
            return Hospital::find($id);
        }

        $active = Hospital::where('is_active', true)->limit(2)->get();

        return $active->count() === 1 ? $active->first() : null;
    }
}
