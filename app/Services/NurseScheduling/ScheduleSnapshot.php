<?php

namespace App\Services\NurseScheduling;

use App\Models\Bed;
use App\Models\Hospital;
use App\Models\Nurse;
use App\Models\NurseLeave;
use App\Models\Patient;
use App\Models\Ward;
use App\Support\PatientPrivacy;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * A read-only copy of a ward's AI Nurse Schedule from a week on: the roster
 * weeks with staffing, patient acuity and acuity-based staffing, one shift's
 * bed assignment, and each nurse's totals for the workload table. The
 * printouts read it, and so does the view-only link that needs no sign-in.
 *
 * Everything comes back as plain arrays, with privacy already applied, so a
 * view cannot show more than it was given:
 *   - patient names are full, initials or left out (the bed only); the MRN is
 *     there only with full names, and never on a public copy;
 *   - leave is the type and its code, or just "Leave" (a public copy always:
 *     an MC code is staff health information);
 *   - a public copy has no indicator reasons, patient notes or rater names,
 *     and never any leave notes, requests or acknowledgements (none of those
 *     are in here at all).
 *
 * Options (all optional):
 *   weeks         1, 2 or 4 roster weeks from $weekStart (default 1)
 *   day, shift    the bed assignment's shift; a date in the weeks shown and a
 *                 code in WardShifts::CODES, else the shift on now, or AM of
 *                 today (or of the first Monday), as the schedule page picks
 *   names         full | initials | hidden (default initials; full becomes
 *                 initials on a public copy)
 *   public        true for a copy anyone with the link can see (default false)
 *   leave_detail  show each leave type (default true; always false when public)
 *   acuity        build patient acuity and acuity staffing (default true)
 *   assign        build the bed assignment (default true)
 *   ews_system    early warning score system for the acuity indicators;
 *                 null is the ward's (AcuitySettings::ewsSystem), whoever views it
 */
final class ScheduleSnapshot
{
    public const WEEKS = [1, 2, 4];

    public const NAME_MODES = ['full', 'initials', 'hidden'];

    public static function build(Ward $ward, CarbonInterface $weekStart, array $options = []): array
    {
        $options = self::options($options);
        $from = Carbon::instance($weekStart)->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $rules = RosterRules::forWard($ward);

        $boards = [];
        for ($week = 0; $week < $options['weeks']; $week++) {
            $boards[] = RosterBoard::build($ward, $from->copy()->addWeeks($week), $rules);
        }
        $shifts = $boards[0]['shifts'];

        $nowSlot = RosterSlot::current($ward->id);
        $nowSlot = $nowSlot && in_array($nowSlot['code'], WardShifts::CODES, true) ? $nowSlot : null;

        // The bed sheet needs each bed's workload; its rows carry the beds and
        // patients, so acuity is worked out from them rather than loaded again
        $weights = $options['assign'] ? WorkloadWeights::forWard($ward) : null;
        $workload = $weights ? WorkloadCalculator::forWard($ward, $weights) : null;
        $acuityRows = null;
        $acuitySettings = null;
        $staffing = null;
        if ($options['acuity'] || $options['assign']) {
            // The ward's early warning score system unless one is asked for, as on the schedule page
            $acuitySettings = AcuitySettings::forWard($ward);
            $ewsSystem = $options['ews_system'] ?? $acuitySettings->ewsSystem();
            $acuityRows = $workload
                ? PatientAcuity::fromBeds($ward, $workload, $ewsSystem)
                : PatientAcuity::forWard($ward, $ewsSystem);
            $staffing = AcuityStaffing::forShifts($acuityRows, $shifts, $acuitySettings);
        }

        $weeks = [];
        foreach ($boards as $board) {
            $weeks[] = self::week($board, $options['acuity'] ? $staffing : null, $options['leave_detail']);
        }

        $hospital = $ward->hospital ?? Hospital::first();

        return [
            'public' => $options['public'],
            'names' => $options['names'],
            'leave_detail' => $options['leave_detail'],
            'generated_at' => now(),
            'today' => now()->toDateString(),
            'hospital' => [
                'name' => $hospital?->name ?? config('app.name'),
                // The printed logo only: the navbar logo is white and does not show on paper
                'logo_url' => $hospital?->logo_path ? Storage::url($hospital->logo_path) : null,
            ],
            'ward' => [
                'id' => $ward->id,
                'name' => $ward->ward_name,
                'code' => $ward->ward_code,
                'critical_care' => (bool) $ward->wardType?->is_critical_care,
            ],
            'shifts' => $shifts,
            'now' => $nowSlot ? ['date' => $nowSlot['date'], 'code' => $nowSlot['code']] : null,
            'from' => $from,
            'to' => $from->copy()->addWeeks($options['weeks'])->subDay(),
            'rules' => [
                'min_staff' => $rules->minStaff,
                'target_shifts_per_week' => $rules->targetShiftsPerWeek,
            ],
            'weeks' => $weeks,
            'acuity' => $options['acuity'] ? self::acuity($acuityRows, $staffing, $acuitySettings, $weeks[0], $options) : null,
            'assign' => $options['assign'] ? self::assignment($ward, $boards, $workload, $acuityRows, $staffing, $weights, $nowSlot, $options) : null,
        ];
    }

    /** The options with every value checked, and the public copy's limits applied. */
    public static function options(array $options): array
    {
        $public = (bool) ($options['public'] ?? false);
        $names = in_array($options['names'] ?? null, self::NAME_MODES, true) ? $options['names'] : 'initials';
        if ($public && $names === 'full') {
            $names = 'initials';
        }

        $weeks = is_numeric($options['weeks'] ?? null) ? (int) $options['weeks'] : 1;

        return [
            'weeks' => in_array($weeks, self::WEEKS, true) ? $weeks : 1,
            'day' => is_string($options['day'] ?? null) ? $options['day'] : null,
            'shift' => in_array($options['shift'] ?? null, WardShifts::CODES, true) ? $options['shift'] : null,
            'names' => $names,
            'public' => $public,
            'leave_detail' => !$public && (bool) ($options['leave_detail'] ?? true),
            'acuity' => (bool) ($options['acuity'] ?? true),
            'assign' => (bool) ($options['assign'] ?? true),
            'ews_system' => is_string($options['ews_system'] ?? null) ? $options['ews_system'] : null,
        ];
    }

    /**
     * One roster week: each nurse's days and totals, and the staffing of each
     * shift against its minimum and, with acuity, the registered nurses
     * against the recommendation.
     */
    private static function week(array $board, ?array $staffing, bool $leaveDetail): array
    {
        $acuityWeek = $staffing ? AcuityStaffing::week($board, $staffing) : [];

        $nurses = $board['team']->map(function (Nurse $nurse) use ($board, $leaveDetail) {
            $days = [];
            foreach ($board['cells'][$nurse->id] ?? [] as $day => $cell) {
                $working = in_array($cell['shift'], WardShifts::CODES, true);
                $leave = self::leave($cell['leave'], $leaveDetail);

                $days[$day] = [
                    'shift' => $cell['shift'],
                    'leave' => $leave,
                    'other_ward' => $cell['other_ward'],
                    'manual' => $cell['source'] === 'manual',
                    'schedule' => $cell['source'] === 'schedule',
                    // A clash with leave names the leave only as far as it may be shown
                    'conflict' => $cell['leave'] && $working ? 'Rostered during ' . mb_strtolower($leave['label']) : $cell['conflict'],
                ];
            }

            return [
                'id' => $nurse->id,
                'name' => $nurse->name,
                'designation' => $nurse->designation,
                'registered' => AcuityStaffing::isRegisteredNurse($nurse),
                'days' => $days,
                'stats' => $board['stats'][$nurse->id],
            ];
        })->values()->all();

        $shiftStaffing = [];
        foreach ($board['staffing'] as $day => $codes) {
            foreach ($codes as $code => $staff) {
                $acuity = $acuityWeek[$day][$code] ?? null;
                $shiftStaffing[$day][$code] = [
                    'count' => $staff['count'],
                    'min' => $staff['min'],
                    'registered' => $acuity['registered'] ?? null,
                    'recommended' => $acuity['recommended'] ?? null,
                    'status' => $acuity['status'] ?? null,
                ];
            }
        }

        $start = $board['dates']->first()->copy();

        return [
            'start' => $start,
            'end' => $board['dates']->last()->copy(),
            'number' => $start->isoWeek(),
            'dates' => $board['dates']->map(fn (Carbon $date) => $date->copy())->all(),
            'holidays' => $board['holidays'],
            'team_source' => $board['teamSource'],
            'nurses' => $nurses,
            'staffing' => $shiftStaffing,
            'short_shifts' => $staffing ? AcuityStaffing::shortShifts($acuityWeek) : null,
        ];
    }

    /** Patients by care level and what they mean for staffing, with names as allowed. */
    private static function acuity(Collection $rows, array $staffing, AcuitySettings $settings, array $firstWeek, array $options): array
    {
        $public = $options['public'];

        return [
            'rows' => $rows->map(fn (array $row) => [
                'bed' => self::bedLabel($row['bed']),
                'occupied' => (bool) $row['patient'],
                'patient' => self::patientLabel($row['patient'], $options['names']),
                'mrn' => self::mrn($row['patient'], $options),
                'suggested' => $row['suggested'],
                'classified' => $row['classified'],
                'level' => $row['level'],
                'status' => $row['status'],
                'reasons' => $public ? [] : $row['reasons'],
                'notes' => $public ? [] : $row['notes'],
                'rated_at' => $public ? null : $row['last_rating']?->rated_at,
                'rated_by' => $public ? null : $row['last_rating']?->rated_by_name,
            ])->all(),
            'summary' => PatientAcuity::summary($rows),
            'staffing' => $staffing,
            'settings' => $settings,
            'week' => collect($firstWeek['staffing'])
                ->map(fn (array $codes) => collect($codes)->map(fn (array $staff) => [
                    'rostered' => $staff['count'],
                    'registered' => $staff['registered'],
                    'min' => $staff['min'],
                    'recommended' => $staff['recommended'],
                    'status' => $staff['status'],
                ])->all())
                ->all(),
            'short_shifts' => $firstWeek['short_shifts'] ?? 0,
        ];
    }

    /**
     * The bed assignment saved for one shift: each bed with its patient (as
     * allowed), care level, workload and nurse, and each nurse's share.
     */
    private static function assignment(Ward $ward, array $boards, Collection $workload, Collection $acuityRows, array $staffing, WorkloadWeights $weights, ?array $nowSlot, array $options): array
    {
        $days = collect($boards)->flatMap(fn (array $board) => $board['dates']->map->toDateString())->all();
        $today = now()->toDateString();
        $onShown = $nowSlot && in_array($nowSlot['date'], $days, true);

        $day = in_array($options['day'], $days, true)
            ? $options['day']
            : ($onShown ? $nowSlot['date'] : (in_array($today, $days, true) ? $today : $days[0]));
        $shift = $options['shift'] ?? ($onShown && $day === $nowSlot['date'] ? $nowSlot['code'] : 'AM');

        $board = collect($boards)->first(fn (array $board) => $board['dates']->contains(fn (Carbon $date) => $date->toDateString() === $day));
        $rostered = RosterBoard::onShift($board, $day, $shift);
        $mapping = BedAssigner::current($ward, $day, $shift);
        $nurses = Nurse::whereIn('id', $rostered->pluck('id')->merge(array_values($mapping))->unique())
            ->get(['id', 'name', 'designation'])
            ->keyBy('id');
        $loads = WorkloadCalculator::loads($workload, $mapping, $rostered->pluck('id'), $weights->band());
        $rosteredIds = $rostered->pluck('id')->all();

        $rows = [];
        $bedsOf = [];
        foreach ($workload as $bedId => $row) {
            $nurseId = isset($mapping[$bedId]) && $nurses->has($mapping[$bedId]) ? $mapping[$bedId] : null;
            $acuity = $acuityRows->get($bedId);
            $label = self::bedLabel($row['bed']);
            if ($nurseId) {
                $bedsOf[$nurseId][] = $label;
            }

            $rows[] = [
                'bed' => $label,
                'occupied' => (bool) $row['patient'],
                'patient' => self::patientLabel($row['patient'], $options['names']),
                'mrn' => self::mrn($row['patient'], $options),
                'level' => $acuity['level'] ?? null,
                'classified' => $acuity['classified'] ?? null,
                'score' => $row['score'],
                // What makes the patient heavier to look after: the acuity indicators, and a discharge planned
                'needs' => $options['public'] || !$acuity ? [] : [...array_map(fn (array $reason) => $reason['label'], $acuity['reasons']), ...$acuity['notes']],
                'nurse_id' => $nurseId,
                'nurse' => $nurseId ? $nurses[$nurseId]->name : null,
            ];
        }

        $shares = [];
        foreach ($loads as $nurseId => $load) {
            if (!$nurses->has($nurseId)) {
                continue;
            }
            $shares[] = [
                'id' => $nurseId,
                'name' => $nurses[$nurseId]->name,
                'designation' => $nurses[$nurseId]->designation,
                'rostered' => in_array($nurseId, $rosteredIds, true),
                'beds' => $bedsOf[$nurseId] ?? [],
                'patients' => $load['patients'],
                'score' => $load['score'],
                'level' => $load['level'],
            ];
        }

        return [
            'day' => $day,
            'date' => Carbon::parse($day),
            'shift' => $shift,
            'time' => $boards[0]['shifts'][$shift]['time'],
            'is_now' => $nowSlot && $nowSlot['date'] === $day && $nowSlot['code'] === $shift,
            'rows' => $rows,
            'loads' => $shares,
            'rostered' => count($rosteredIds),
            'min' => (int) ($board['staffing'][$day][$shift]['min'] ?? 0),
            'recommended' => $staffing['shifts'][$shift]['recommended'] ?? null,
            'patients' => $workload->filter(fn (array $row) => $row['patient'])->count(),
            'beds' => $workload->count(),
            'unassigned' => count(array_filter($rows, fn (array $row) => !$row['nurse_id'])),
            'band' => (float) $weights->get('balance_band'),
        ];
    }

    private static function leave(?NurseLeave $leave, bool $detail): ?array
    {
        if (!$leave) {
            return null;
        }

        return $detail
            ? ['code' => $leave->code(), 'label' => $leave->label(), 'type' => $leave->type]
            : ['code' => 'Leave', 'label' => 'Leave', 'type' => null];
    }

    private static function bedLabel(Bed $bed): string
    {
        return $bed->bed_display_name ?: $bed->bed_number;
    }

    /** The patient as the names option allows; null for an empty bed or when names are hidden. */
    private static function patientLabel(?Patient $patient, string $names): ?string
    {
        if (!$patient || $names === 'hidden') {
            return null;
        }

        return $names === 'full' ? $patient->name : PatientPrivacy::name($patient->name, 'initials');
    }

    /** The MRN goes with a full name only, and never on a public copy. */
    private static function mrn(?Patient $patient, array $options): ?string
    {
        return $patient && $options['names'] === 'full' && !$options['public'] ? $patient->mrn : null;
    }
}
