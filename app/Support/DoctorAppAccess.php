<?php

namespace App\Support;

use App\Models\Consultant;
use App\Models\Patient;
use App\Models\PatientCareProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Who is using the doctor app and which patients they may open. Shared by
 * DoctorAppApiController and DoctorAppPatientController, so the dashboard
 * and the patient chart always agree on "under your care".
 */
class DoctorAppAccess
{
    /** The consultant the request's bearer token belongs to, if it is valid. */
    public static function consultant(Request $request): ?Consultant
    {
        $token = $request->bearerToken() ?: $request->input('token');

        return $token ? Consultant::findByAppToken((string) $token) : null;
    }

    /**
     * Under this consultant's care: the patient's primary consultant, an
     * active ADT care provider, or assigned to the patient's bed.
     */
    public static function isUnderCare(Consultant $consultant, Patient $patient): bool
    {
        if ((int) $patient->consultant_id === (int) $consultant->id) {
            return true;
        }

        $viaCareProvider = PatientCareProvider::where('consultant_id', $consultant->id)
            ->where('patient_id', $patient->id)
            ->where('is_active', true)
            ->exists();
        if ($viaCareProvider) {
            return true;
        }

        return DB::table('bed_consultant')
            ->join('beds', 'beds.id', '=', 'bed_consultant.bed_id')
            ->where('bed_consultant.consultant_id', $consultant->id)
            ->where('beds.patient_id', $patient->id)
            ->exists();
    }
}
