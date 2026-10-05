<?php

namespace App\Services\Cplus;

use App\Models\AdmissionLog;
use App\Models\Bed;
use App\Models\Consultant;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\PatientCareProvider;
use App\Models\Ward;
use App\Models\WardType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Brings C+ (Cerebral HIS) Bed Management into SmartWard, as pushed by the
 * rpa_cplus_smartward RPA to POST /api/cplus/bed-sync. Every C+ location
 * becomes a ward, every C+ bed a bed, and each patient goes into the bed C+
 * has them in.
 *
 * C+ owns occupancy in the wards linked to it:
 * - Wards and beds are found again by their C+ ids (wards.cplus_location_id,
 *   beds.cplus_bed_id), so renaming them here keeps the link. Names and bed
 *   numbers are only set when they are created.
 * - A patient (by MRN) listed in a bed is admitted to it, or moved to it.
 * - The admission date is C+'s (the census Entry Date, else the Request
 *   Date; the RPA picks). A patient already in a bed, and their C+ admit log
 *   entry, get it corrected when it differs. Without one, a new admission is
 *   dated when the sync first sees it.
 * - An admitted patient in a linked ward whom C+ no longer lists anywhere is
 *   discharged, but only when the push listed any patients at all.
 * - A stay ends where C+ starts a new one: a new RN, or leaving an Emergency
 *   ward (an ED zone) for any other ward. The old stay gets a discharge entry
 *   naming the ward the patient went on to (admission_logs.to_ward_id), and the
 *   new one an admission, which Command Center V2 (ED) counts as ED admitted.
 * - The ED's zones (ER Management, unit group 3) are tagged with the Emergency
 *   ward type when they are made, or while they have no type; a type set by
 *   hand is kept.
 * - The physician C+ lists is the patient's attending doctor (a care provider
 *   with source cplus), which the bed card shows.
 * - C+ is the main source of isolation: the Isolation box on the admission
 *   card, when the RPA has read it (see syncIsolation). Staff can still set
 *   one in Patient Details for a patient C+ has not ticked.
 * - Prebooked patients are SmartWard's own plans and are left alone.
 * - Nothing is deleted. A ward C+ stops listing keeps its beds and patients.
 */
class CplusBedImporter
{
    public const SOURCE = 'cplus';

    /** The C+ unit group of ER Management's locations, the ED's zones. */
    public const ER_UNIT_GROUP = '3';

    /** SmartWard bed status for an empty bed, by C+ status. */
    private const EMPTY_BED_STATUS = [
        'available' => 'available',
        'out_of_service' => Bed::STATUS_MAINTENANCE,
    ];

    private const IN_BED = [Patient::STATUS_ADMITTED, Patient::STATUS_PENDING_DISCHARGE];

    private array $summary = [];

    /** Consultant id by upper-cased name, null when none matches. */
    private array $consultants = [];

    /** The Emergency ward type ED zones get; false once looked up and none found. */
    private int|false|null $emergencyType = null;

    public function import(Hospital $hospital, array $data): array
    {
        $this->summary = [
            'wards_received' => count($data['wards']),
            'wards_created' => 0,
            'beds_received' => 0,
            'beds_created' => 0,
            'beds_updated' => 0,
            'patients_received' => 0,
            'admitted' => 0,
            'transferred' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'discharged' => 0,
            'ed_admitted_to_ward' => 0,
            'admission_dates_corrected' => 0,
            'unmatched_physicians' => [],
            'skipped' => [],
        ];
        $this->consultants = [];
        $this->emergencyType = null;

        $facilityId = trim((string) $data['facility']['id']);
        $now = now();

        DB::transaction(function () use ($hospital, $data, $facilityId, $now) {
            $wardIds = [];
            $beds = [];
            $placements = [];

            foreach ($data['wards'] as $wardData) {
                $ward = $this->syncWard($hospital, $facilityId, $wardData, $now);
                $wardIds[] = $ward->id;

                foreach ($wardData['beds'] as $bedData) {
                    $this->summary['beds_received']++;
                    $bed = $this->syncBed($ward, $facilityId, $bedData, $now);
                    if (! $bed) {
                        continue;
                    }
                    $beds[] = $bed;

                    if (empty($bedData['patient'])) {
                        continue;
                    }
                    $mrn = trim((string) $bedData['patient']['mrn']);
                    if (isset($placements[$mrn])) {
                        $this->skip("MRN {$mrn} is listed in two beds; kept {$placements[$mrn]['bed']->bed_number}, left {$bed->bed_number} empty.");
                        continue;
                    }
                    $placements[$mrn] = ['ward' => $ward, 'bed' => $bed, 'patient' => $bedData['patient']];
                }
            }

            $this->summary['patients_received'] = count($placements);

            // Discharges first, so the bed a patient leaves is free for whoever C+ has in it now.
            if ($placements) {
                $this->dischargeMissing($wardIds, $placements, $now);
            } else {
                $this->skip('C+ listed no patients at all, so nobody was discharged.');
            }

            foreach ($placements as $mrn => $placement) {
                $this->place((string) $mrn, $placement['ward'], $placement['bed'], $placement['patient'], $now);
            }

            foreach ($beds as $bed) {
                $this->syncBedOccupancy($bed);
            }
        });

        $this->summary['unmatched_physicians'] = array_values(array_unique($this->summary['unmatched_physicians']));

        return $this->summary;
    }

    // -- wards and beds ----------------------------------------------------

    private function syncWard(Hospital $hospital, string $facilityId, array $wardData, Carbon $now): Ward
    {
        $locationId = trim((string) $wardData['id']);
        $capacity = max(1, count($wardData['beds']));
        $isEd = (string) ($wardData['unit_group'] ?? '') === self::ER_UNIT_GROUP;

        $ward = Ward::where('cplus_facility_id', $facilityId)
            ->where('cplus_location_id', $locationId)
            ->first();

        if (! $ward) {
            $this->summary['wards_created']++;

            return Ward::create([
                'hospital_id' => $hospital->id,
                'ward_code' => $this->uniqueWardCode($facilityId, $locationId),
                'ward_name' => trim((string) $wardData['name']),
                'ward_type_id' => $isEd ? $this->emergencyTypeId($hospital) : null,
                'capacity' => $capacity,
                'description' => $isEd
                    ? "From C+ ER Management (location {$locationId})"
                    : "From C+ Bed Management (location {$locationId})",
                'is_active' => true,
                'cplus_facility_id' => $facilityId,
                'cplus_location_id' => $locationId,
                'cplus_synced_at' => $now,
            ]);
        }

        $changes = ['capacity' => $capacity, 'cplus_synced_at' => $now];
        // An ED zone made before its type existed gets it now; a type set by hand stays
        if ($isEd && ! $ward->ward_type_id && ($typeId = $this->emergencyTypeId($hospital))) {
            $changes['ward_type_id'] = $typeId;
        }
        $ward->update($changes);

        return $ward;
    }

    private function syncBed(Ward $ward, string $facilityId, array $bedData, Carbon $now): ?Bed
    {
        $cplusBedId = trim((string) $bedData['id']);
        $name = trim((string) $bedData['name']);
        $link = [
            'cplus_bed_id' => $cplusBedId,
            'cplus_room_no' => trim((string) ($bedData['room_no'] ?? '')) ?: null,
            'cplus_status' => $bedData['status'],
            'cplus_synced_at' => $now,
        ];

        // By C+ id anywhere in this facility (a bed C+ moved to another location
        // moves with it), else a bed made here by hand with the same number.
        $bed = Bed::where('cplus_bed_id', $cplusBedId)
            ->whereHas('ward', fn ($q) => $q->where('cplus_facility_id', $facilityId))
            ->first()
            ?? Bed::where('ward_id', $ward->id)->whereNull('cplus_bed_id')->where('bed_number', $name)->first();

        if (! $bed) {
            if (Bed::where('ward_id', $ward->id)->where('bed_number', $name)->exists()) {
                $this->skip("{$ward->ward_name}: two C+ beds are called {$name}; bed {$cplusBedId} was left out.");

                return null;
            }

            $this->summary['beds_created']++;

            return Bed::create([
                'ward_id' => $ward->id,
                'bed_number' => $name,
                'bed_id' => $this->uniqueBedId($facilityId, $cplusBedId),
                'bed_display_name' => $name,
                'status' => 'available',
                'is_active' => true,
                ...$link,
            ]);
        }

        $bed->fill(['ward_id' => $ward->id, ...$link]);
        if ($bed->isDirty(['ward_id', 'cplus_bed_id', 'cplus_room_no', 'cplus_status'])) {
            $this->summary['beds_updated']++;
        }
        $bed->save();

        return $bed;
    }

    /** The bed box follows the patients table; the bed row is brought in line with it. */
    private function syncBedOccupancy(Bed $bed): void
    {
        $occupant = $bed->occupant();

        if ($occupant && in_array($occupant->status, self::IN_BED, true)) {
            $bed->fill(['patient_id' => $occupant->id, 'status' => 'occupied']);
        } elseif ($occupant) {
            $bed->fill(['patient_id' => $occupant->id, 'status' => 'reserved']);
        } else {
            $bed->fill([
                'patient_id' => null,
                'status' => self::EMPTY_BED_STATUS[$bed->cplus_status] ?? 'available',
            ]);
        }

        if ($bed->isDirty()) {
            $bed->save();
        }
    }

    /** The hospital's own active Emergency ward type, else a system one (ED Observation Bay). */
    private function emergencyTypeId(Hospital $hospital): ?int
    {
        if ($this->emergencyType === null) {
            $this->emergencyType = WardType::active()->emergency()->availableTo($hospital->id)
                ->orderByRaw('hospital_id IS NULL')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->value('id') ?? false;
        }

        return $this->emergencyType ?: null;
    }

    private function uniqueWardCode(string $facilityId, string $locationId): string
    {
        $base = "CP{$facilityId}-{$locationId}";
        $code = $base;
        for ($n = 2; Ward::where('ward_code', $code)->exists(); $n++) {
            $code = "{$base}-{$n}";
        }

        return $code;
    }

    private function uniqueBedId(string $facilityId, string $cplusBedId): string
    {
        $base = "CP{$facilityId}-{$cplusBedId}";
        $id = $base;
        for ($n = 2; Bed::where('bed_id', $id)->exists(); $n++) {
            $id = "{$base}-{$n}";
        }

        return $id;
    }

    // -- patients ------------------------------------------------------------

    /** Admit, move or update the patient C+ lists in this bed. */
    private function place(string $mrn, Ward $ward, Bed $bed, array $data, Carbon $now): void
    {
        $patient = Patient::where('mrn', $mrn)->first();
        $isNew = ! $patient;
        if ($isNew) {
            // What C+ Bed Management does not show; ADT fills these the same way.
            $patient = new Patient(['mrn' => $mrn, 'ic_passport' => 'IC-' . $mrn, 'phone' => '']);
        }

        $inBed = ! $isNew && $patient->is_active && in_array($patient->status, self::IN_BED, true);
        $fromWard = $inBed && $patient->ward_id ? Ward::with('wardType')->find($patient->ward_id) : null;
        $fromBed = $patient->bed_number;
        $from = $inBed ? $this->where($patient) : null;
        $admittedAt = $this->admittedAt($data['admitted_at'] ?? null, $now);
        $rn = trim((string) ($data['rn'] ?? ''));
        $oldVisit = $patient->visit_number;
        // A new RN for someone still admitted here means C+ discharged and
        // readmitted them between two syncs.
        $newVisit = $inBed && $rn !== '' && $oldVisit && $oldVisit !== $rn;
        // Leaving an ED zone for a ward is where the ED visit ends and the stay on the ward begins
        $leavesEd = $fromWard && $fromWard->id !== $ward->id && $fromWard->isEmergency() && ! $ward->isEmergency();
        $newStay = $fromWard && ($newVisit || $leavesEd);
        $physician = $this->physicianName($data['physician'] ?? null);

        $patient->name = trim((string) ($data['name'] ?? '')) ?: ($patient->name ?: $mrn);
        if (in_array($data['gender'] ?? null, ['Male', 'Female'], true)) {
            $patient->gender = $data['gender'];
        }
        if ($rn !== '' && ! Patient::where('rn', $rn)->where('mrn', '!=', $mrn)->exists()) {
            $patient->rn = $rn;
            $patient->visit_number = $rn;
        } elseif (! $patient->rn) {
            $patient->rn = 'RN-' . $mrn;
        }
        if ($consultantId = $this->consultantId($physician)) {
            $patient->consultant_id = $consultantId;
        }
        $patient->ward_id = $ward->id;
        $patient->bed_number = $bed->bed_number;
        $patient->is_active = true;
        $this->syncIsolation($patient, $data['isolation'] ?? null);

        $dateCorrected = false;
        if (! $inBed || $newStay) {
            $action = 'admitted';
            $patient->status = Patient::STATUS_ADMITTED;
            $patient->admitted_at = $admittedAt ?? $now;
            $patient->discharged_at = null;
            $patient->pending_discharge_at = null;
            $patient->target_bed_number = null;
        } else {
            if ($admittedAt && (! $patient->admitted_at || ! $admittedAt->equalTo($patient->admitted_at))) {
                $patient->admitted_at = $admittedAt;
                $dateCorrected = true;
            }
            if ($from !== $this->where($patient)) {
                $action = 'transferred';
            } else {
                $action = $patient->isDirty() ? 'updated' : 'unchanged';
            }
        }

        if ($patient->isDirty() || ! $patient->exists) {
            $patient->save();
        }
        $this->summary[$action]++;
        $this->syncAttending($patient, $physician, $consultantId);

        if ($newStay) {
            // The stay that ended: out of the ward they were in, onward to this one when it is another
            $this->log($patient, $fromWard->id, $fromBed ?: '-', 'discharge', [
                'to_ward_id' => $fromWard->id === $ward->id ? null : $ward->id,
                'discharged_at' => $patient->admitted_at,
                'consultant_name' => $patient->consultant?->name ?? $physician,
                'notes' => $leavesEd
                    ? "C+: left the ED for {$ward->ward_name}/{$bed->bed_number}"
                    : "C+: visit {$oldVisit} ended; new visit {$rn}",
            ]);
            if ($leavesEd) {
                $this->summary['ed_admitted_to_ward']++;
            }
        }

        if ($dateCorrected) {
            $this->summary['admission_dates_corrected']++;
            // The Command Center counts admissions by this entry's admitted_at.
            AdmissionLog::where('patient_id', $patient->id)
                ->where('action', 'admit')
                ->where('source', self::SOURCE)
                ->latest('id')
                ->first()
                ?->update(['admitted_at' => $patient->admitted_at]);
        }

        if ($action === 'admitted' || $action === 'transferred') {
            $this->log($patient, $ward->id, $bed->bed_number, $action === 'admitted' ? 'admit' : 'transfer', [
                'admitted_at' => $action === 'admitted' ? $patient->admitted_at : null,
                'consultant_name' => $patient->consultant?->name ?? $physician,
                'notes' => match (true) {
                    $leavesEd => "C+: admitted from the ED ({$from})",
                    $action === 'admitted' => 'C+ Bed Management lists this patient in ' . $ward->ward_name . '/' . $bed->bed_number,
                    default => 'C+ Bed Management: moved from ' . $from,
                },
            ]);
        }
    }

    /**
     * The Isolation box on the C+ admission card ($ticked; null when the RPA
     * has not read it, which changes nothing). C+ records only that the
     * patient is isolated, not the kind:
     * - Ticked: a patient without an isolation precaution gets the C+ one
     *   (config cplus.isolation_type), again if it is taken off here. A kind
     *   picked in Patient Details is kept.
     * - Unticked after being ticked: the C+ one comes off. A kind picked in
     *   Patient Details stays until staff change it, as does one staff set for
     *   a patient C+ never ticked.
     */
    private function syncIsolation(Patient $patient, mixed $ticked): void
    {
        if ($ticked === null) {
            return;
        }
        $ticked = (bool) $ticked;
        $cplusType = (string) config('cplus.isolation_type');

        if ($ticked && in_array(strtolower((string) $patient->isolation_type), ['', 'none'], true)) {
            $patient->isolation_type = $cplusType;
        } elseif (! $ticked && $patient->cplus_isolation && $patient->isolation_type === $cplusType) {
            $patient->isolation_type = 'none';
        }

        $patient->cplus_isolation = $ticked;
    }

    /**
     * The physician C+ lists, as the patient's attending doctor: one care
     * provider with source cplus, kept up to date. The bed card shows it.
     */
    private function syncAttending(Patient $patient, ?string $physician, ?int $consultantId): void
    {
        if (! $physician) {
            return;
        }

        $provider = PatientCareProvider::firstOrNew([
            'patient_id' => $patient->id,
            'role' => PatientCareProvider::ROLE_ATTENDING,
            'source' => PatientCareProvider::SOURCE_CPLUS,
        ]);
        $provider->fill([
            'doctor_code' => mb_substr(mb_strtoupper($physician), 0, 255),
            'doctor_name' => $physician,
            'consultant_id' => $consultantId,
            'visit_number' => $patient->visit_number,
            'is_active' => true,
        ]);
        if (! $provider->exists || $provider->isDirty('doctor_code')) {
            $provider->assigned_at = now();
        }
        if ($provider->isDirty()) {
            $provider->save();
        }
    }

    private function physicianName(?string $physician): ?string
    {
        $name = trim((string) preg_replace('/\s+/', ' ', (string) $physician));

        return $name === '' || $name === '-' ? null : $name;
    }

    /**
     * Discharge whoever is admitted in the linked wards but no longer listed
     * by C+, and hand their bed to a prebook waiting for it (as an ADT
     * discharge does) unless C+ has already put someone else in it.
     */
    private function dischargeMissing(array $wardIds, array $placements, Carbon $now): void
    {
        $taken = [];
        foreach ($placements as $placement) {
            $taken[$placement['ward']->id . '|' . $placement['bed']->bed_number] = true;
        }

        $leaving = Patient::whereIn('ward_id', $wardIds)
            ->where('is_active', true)
            ->whereIn('status', self::IN_BED)
            ->whereNotIn('mrn', array_map('strval', array_keys($placements)))
            ->get();

        foreach ($leaving as $patient) {
            $wardId = $patient->ward_id;
            $bedNumber = $patient->bed_number;

            $patient->update([
                'status' => Patient::STATUS_DISCHARGED,
                'ward_id' => null,
                'bed_number' => null,
                'is_active' => false,
                'discharged_at' => $now,
                'pending_discharge_at' => null,
            ]);
            $this->summary['discharged']++;

            $this->log($patient, $wardId, $bedNumber ?? '-', 'discharge', [
                'discharged_at' => $now,
                'notes' => 'C+ Bed Management no longer lists this patient',
            ]);
            // Their C+ doctor goes with the stay (a query, so no care-provider events fire)
            PatientCareProvider::where('patient_id', $patient->id)
                ->where('source', PatientCareProvider::SOURCE_CPLUS)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            if ($bedNumber && ! isset($taken[$wardId . '|' . $bedNumber])) {
                $this->activatePendingPrebook($wardId, $bedNumber, $patient);
            }
        }
    }

    private function activatePendingPrebook(int $wardId, string $bedNumber, Patient $discharged): void
    {
        $prebook = Patient::where('ward_id', $wardId)
            ->where('target_bed_number', $bedNumber)
            ->where('is_active', true)
            ->where('status', Patient::STATUS_PREBOOK_PENDING)
            ->first();

        if (! $prebook) {
            return;
        }

        $prebook->update([
            'bed_number' => $bedNumber,
            'target_bed_number' => null,
            'status' => Patient::STATUS_PREBOOK,
        ]);

        $this->log($prebook, $wardId, $bedNumber, 'prebook-activated', [
            'notes' => 'C+ Bed Management: prebook activated after discharge of ' . $discharged->name,
        ]);
    }

    /** C+'s admission time in the app's timezone; null when missing, unreadable or in the future. */
    private function admittedAt(?string $value, Carbon $now): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            $at = Carbon::parse($value)->setTimezone(config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }

        return $at->greaterThan($now->copy()->addHour()) ? null : $at;
    }

    private function consultantId(?string $name): ?int
    {
        if (! $name) {
            return null;
        }

        $key = mb_strtoupper($name);
        if (! array_key_exists($key, $this->consultants)) {
            $this->consultants[$key] = Consultant::whereRaw('UPPER(TRIM(name)) = ?', [$key])->value('id');
            if (! $this->consultants[$key]) {
                $this->summary['unmatched_physicians'][] = $name;
            }
        }

        return $this->consultants[$key];
    }

    private function where(Patient $patient): string
    {
        $ward = $patient->ward_id ? Ward::find($patient->ward_id) : null;

        return ($ward?->ward_name ?? 'no ward') . '/' . ($patient->bed_number ?? '-');
    }

    private function log(Patient $patient, int $wardId, string $bedNumber, string $action, array $extra = []): void
    {
        AdmissionLog::create([
            'patient_id' => $patient->id,
            'ward_id' => $wardId,
            'user_id' => null,
            'bed_number' => $bedNumber,
            'action' => $action,
            'patient_name' => $patient->name,
            'mrn' => $patient->mrn,
            'consultant_name' => $patient->consultant?->name,
            'gender' => $patient->gender,
            'age' => $patient->age,
            'source' => self::SOURCE,
            ...$extra,
        ]);
    }

    private function skip(string $reason): void
    {
        $this->summary['skipped'][] = $reason;
    }
}
