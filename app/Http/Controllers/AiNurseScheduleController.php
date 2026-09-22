<?php

namespace App\Http\Controllers;

use App\Models\Nurse;
use App\Models\NurseLeave;
use App\Models\NurseRosterEntry;
use App\Models\PublicHoliday;
use App\Models\User;
use App\Models\Ward;
use App\Models\WardRosterSetting;
use App\Models\WardScheduleAssignment;
use App\Services\NurseScheduling\BedAssigner;
use App\Services\NurseScheduling\RosterBoard;
use App\Services\NurseScheduling\RosterGenerator;
use App\Services\NurseScheduling\RosterRules;
use App\Services\NurseScheduling\WardShifts;
use App\Services\NurseScheduling\WardTeam;
use App\Services\NurseScheduling\WorkloadCalculator;
use App\Services\NurseScheduling\WorkloadWeights;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Schedule > AI Nurse Schedule: the staff roster (who works which shift),
 * smart bed assignment by workload, workload and fairness, and leave and
 * public holidays.
 *
 * Bed assignments are saved to ward_schedule_assignments, the table the Ward
 * Schedule page, the ward dashboard and the bedside screens already use, so
 * what is planned here shows up there. The Ward Schedule page is untouched.
 */
class AiNurseScheduleController extends Controller
{
    private const TABS = ['roster', 'assign', 'workload', 'leave'];

    private const MAX_LEAVE_DAYS = 366;

    public function index(Request $request)
    {
        $wards = Ward::where('is_active', true)->orderBy('ward_name')->get();
        $ward = $wards->firstWhere('id', (int) $request->input('ward_id')) ?? $wards->first();

        if (!$ward) {
            return view('wards.ai-schedule.index', ['wards' => $wards, 'ward' => null]);
        }

        $weekStart = $this->weekStart($request->input('week'));
        $rules = RosterRules::forWard($ward);
        $board = RosterBoard::build($ward, $weekStart, $rules);
        $shifts = $board['shifts'];
        $today = now()->toDateString();
        $currentCode = WardShifts::currentCode($shifts);

        // Bed assignment: one shift of the week, the shift on now by default
        $days = $board['dates']->map->toDateString()->all();
        $day = in_array($request->input('day'), $days, true)
            ? $request->input('day')
            : (in_array($today, $days, true) ? $today : $days[0]);
        $shift = in_array($request->input('shift'), WardShifts::CODES, true)
            ? $request->input('shift')
            : ($day === $today && $currentCode ? $currentCode : 'AM');

        $weights = WorkloadWeights::forWard($ward);
        $beds = WorkloadCalculator::forWard($ward, $weights);
        $rostered = RosterBoard::onShift($board, $day, $shift);
        $saved = BedAssigner::current($ward, $day, $shift);
        $suggesting = $request->boolean('suggest') && $rostered->isNotEmpty();
        $mapping = $suggesting
            ? BedAssigner::propose($beds, $rostered->pluck('id')->all(), BedAssigner::previous($ward, $day, $shift), $weights)
            : $saved;

        // Anyone holding beds on that shift without being rostered still shows, so it can be fixed
        $assignNurses = Nurse::whereIn('id', $rostered->pluck('id')->merge(array_values($mapping))->merge(array_values($saved))->unique())
            ->orderBy('name')
            ->get()
            ->keyBy('id');

        // Workload now: the shift on duty, as the dashboard shows it
        $liveCode = $currentCode ?? 'AM';
        $liveLoads = WorkloadCalculator::loads($beds, BedAssigner::current($ward, $today, $liveCode), [], $weights->band());

        return view('wards.ai-schedule.index', [
            'wards' => $wards,
            'ward' => $ward,
            'weekStart' => $weekStart,
            'tab' => in_array($request->input('tab'), self::TABS, true) ? $request->input('tab') : 'roster',
            'rules' => $rules,
            'board' => $board,
            'shifts' => $shifts,
            'today' => $today,
            'currentCode' => $currentCode,
            'canEdit' => !Auth::user()?->hasRole(User::ROLE_NURSE),
            'day' => $day,
            'shift' => $shift,
            'beds' => $beds,
            'rostered' => $rostered,
            'saved' => $saved,
            'mapping' => $mapping,
            'suggesting' => $suggesting,
            'assignNurses' => $assignNurses,
            'weights' => $weights,
            'liveCode' => $liveCode,
            'liveLoads' => $liveLoads,
            'liveNurses' => Nurse::whereIn('id', array_keys($liveLoads))->get()->keyBy('id'),
            'leaves' => NurseLeave::with('nurse:id,name')
                ->whereIn('nurse_id', $board['team']->pluck('id'))
                ->where('end_date', '>=', now()->subDays(7)->toDateString())
                ->orderBy('start_date')
                ->get(),
            'holidays' => PublicHoliday::where('holiday_date', '>=', now()->startOfYear()->toDateString())
                ->orderBy('holiday_date')
                ->get(),
        ]);
    }

    /** Plan the roster for the week shown, or the next two or four weeks. */
    public function generate(Request $request)
    {
        $this->authorizeEdit();
        $validated = $request->validate([
            'ward_id' => 'required|exists:wards,id',
            'week' => 'required|date',
            'weeks' => 'required|integer|in:1,2,4',
        ]);

        $ward = Ward::findOrFail($validated['ward_id']);
        $from = $this->weekStart($validated['week']);
        $to = $from->copy()->addWeeks((int) $validated['weeks'])->subDay();
        $team = WardTeam::for($ward)['nurses'];

        if ($team->isEmpty()) {
            return $this->backTo($ward, $from, 'roster')
                ->with('error', 'There are no active nurses to roster. Add nurses under Admin Management first.');
        }

        $result = (new RosterGenerator($ward, $team, RosterRules::forWard($ward)))->generate($from, $to);

        return $this->backTo($ward, $from, 'roster')
            ->with('success', 'Roster planned for ' . $from->format('j M') . ' to ' . $to->format('j M Y') . ': '
                . $result['created'] . ' shifts. Shifts set by hand were kept.')
            ->with('roster_shortfalls', $result['shortfalls']);
    }

    /** Set one nurse's day by hand: a shift, a day off, or cleared. */
    public function updateCell(Request $request)
    {
        $this->authorizeEdit();
        $validated = $request->validate([
            'ward_id' => 'required|exists:wards,id',
            'week' => 'required|date',
            'nurse_id' => 'required|exists:nurses,id',
            'date' => 'required|date',
            'shift' => ['required', Rule::in([...WardShifts::CODES, NurseRosterEntry::OFF, 'clear'])],
        ]);

        $ward = Ward::findOrFail($validated['ward_id']);
        $nurse = Nurse::findOrFail($validated['nurse_id']);
        $date = Carbon::parse($validated['date'])->startOfDay();
        $back = $this->backTo($ward, $this->weekStart($validated['week']), 'roster');
        $entry = NurseRosterEntry::where('nurse_id', $nurse->id)->where('roster_date', $date->toDateString())->first();

        if ($entry && $entry->ward_id !== $ward->id) {
            return $back->with('error', $nurse->name . ' is already rostered on ' . ($entry->ward->ward_name ?? 'another ward') . ' that day.');
        }

        if ($validated['shift'] === 'clear') {
            $entry?->delete();

            return $back->with('success', $nurse->name . ' cleared for ' . $date->format('D j M') . '.');
        }

        if (in_array($validated['shift'], WardShifts::CODES, true)) {
            $leave = NurseLeave::where('nurse_id', $nurse->id)->overlapping($date, $date)->first();
            if ($leave) {
                return $back->with('error', $nurse->name . ' is on ' . strtolower($leave->label()) . ' that day.');
            }
        }

        NurseRosterEntry::updateOrCreate(
            ['nurse_id' => $nurse->id, 'roster_date' => $date->toDateString()],
            ['ward_id' => $ward->id, 'shift' => $validated['shift'], 'source' => NurseRosterEntry::SOURCE_MANUAL]
        );

        return $back->with('success', $nurse->name . ': ' . $validated['shift'] . ' on ' . $date->format('D j M') . '.');
    }

    /** Remove the AI-planned shifts of the week; shifts set by hand stay. */
    public function clear(Request $request)
    {
        $this->authorizeEdit();
        $validated = $request->validate([
            'ward_id' => 'required|exists:wards,id',
            'week' => 'required|date',
        ]);

        $ward = Ward::findOrFail($validated['ward_id']);
        $from = $this->weekStart($validated['week']);

        $removed = NurseRosterEntry::where('ward_id', $ward->id)
            ->where('source', NurseRosterEntry::SOURCE_AUTO)
            ->whereBetween('roster_date', [$from->toDateString(), $from->copy()->addDays(6)->toDateString()])
            ->delete();

        return $this->backTo($ward, $from, 'roster')
            ->with('success', 'Removed ' . $removed . ' AI-planned shifts from the week. Shifts set by hand were kept.');
    }

    /**
     * Turn the week's Ward Schedule bed assignments into roster entries, set
     * by hand, so the generator keeps them and plans around them.
     */
    public function importSchedule(Request $request)
    {
        $this->authorizeEdit();
        $validated = $request->validate([
            'ward_id' => 'required|exists:wards,id',
            'week' => 'required|date',
        ]);

        $ward = Ward::findOrFail($validated['ward_id']);
        $from = $this->weekStart($validated['week']);

        $byNurseDay = WardScheduleAssignment::where('ward_id', $ward->id)
            ->whereBetween('scheduled_date', [$from->toDateString(), $from->copy()->addDays(6)->toDateString()])
            ->get()
            ->groupBy(fn (WardScheduleAssignment $row) => $row->nurse_id . '|' . $row->scheduled_date->toDateString());

        $created = 0;
        foreach ($byNurseDay as $key => $rows) {
            [$nurseId, $day] = explode('|', $key);

            if (NurseRosterEntry::where('nurse_id', $nurseId)->where('roster_date', $day)->exists()) {
                continue;
            }

            NurseRosterEntry::create([
                'ward_id' => $ward->id,
                'nurse_id' => (int) $nurseId,
                'roster_date' => $day,
                'shift' => collect(WardShifts::CODES)->first(fn (string $code) => $rows->contains('shift', $code)),
                'source' => NurseRosterEntry::SOURCE_MANUAL,
            ]);
            $created++;
        }

        return $this->backTo($ward, $from, 'roster')->with('success', $created
            ? 'Brought in ' . $created . ' shifts from the Ward Schedule. They are kept when the roster is generated.'
            : 'Nothing to bring in: every Ward Schedule shift this week is already on the roster.');
    }

    /** Save one shift's bed assignments to the ward dashboard. */
    public function applyAssignments(Request $request)
    {
        $this->authorizeEdit();
        $validated = $request->validate([
            'ward_id' => 'required|exists:wards,id',
            'week' => 'required|date',
            'date' => 'required|date',
            'shift' => ['required', Rule::in(WardShifts::CODES)],
            'beds' => 'nullable|array',
            'beds.*' => 'nullable|integer|exists:nurses,id',
        ]);

        $ward = Ward::findOrFail($validated['ward_id']);
        $day = Carbon::parse($validated['date'])->toDateString();
        $result = BedAssigner::apply($ward, $day, $validated['shift'], $validated['beds'] ?? []);

        return $this->backTo($ward, $this->weekStart($validated['week']), 'assign', ['day' => $day, 'shift' => $validated['shift']])
            ->with('success', 'Saved to the ward dashboard for ' . $validated['shift'] . ' on ' . Carbon::parse($day)->format('D j M')
                . ': ' . $result['saved'] . ' beds updated, ' . $result['removed'] . ' cleared.');
    }

    /**
     * Share the beds out by AI for every shift left this week that has nurses
     * on the roster, and save them to the ward dashboard. Each day follows on
     * from the day before, so nurses keep their beds where the load allows.
     */
    public function applyWeek(Request $request)
    {
        $this->authorizeEdit();
        $validated = $request->validate([
            'ward_id' => 'required|exists:wards,id',
            'week' => 'required|date',
        ]);

        $ward = Ward::findOrFail($validated['ward_id']);
        $from = $this->weekStart($validated['week']);
        $board = RosterBoard::build($ward, $from, RosterRules::forWard($ward));
        $weights = WorkloadWeights::forWard($ward);
        $beds = WorkloadCalculator::forWard($ward, $weights);
        $today = now()->toDateString();

        $applied = 0;
        $empty = 0;
        $carried = [];

        foreach ($board['dates'] as $date) {
            $day = $date->toDateString();

            foreach (WardShifts::CODES as $code) {
                if ($day < $today || WardShifts::hasEnded($day, $board['shifts'][$code])) {
                    continue;
                }

                $nurseIds = RosterBoard::onShift($board, $day, $code)->pluck('id')->all();
                if (!$nurseIds) {
                    $empty++;
                    continue;
                }

                $mapping = BedAssigner::propose($beds, $nurseIds, $carried[$code] ?? BedAssigner::previous($ward, $day, $code), $weights);
                BedAssigner::apply($ward, $day, $code, $mapping);
                $carried[$code] = $mapping;
                $applied++;
            }
        }

        $message = $applied
            ? 'Beds shared out by workload for ' . $applied . ' shifts and saved to the ward dashboard.'
            : 'No shifts to assign: nobody is rostered for the rest of this week.';
        if ($applied && $empty) {
            $message .= ' ' . $empty . ' shifts have nobody rostered yet.';
        }

        return $this->backTo($ward, $from, 'assign')->with($applied ? 'success' : 'error', $message);
    }

    public function storeLeave(Request $request)
    {
        $this->authorizeEdit();
        $validated = $request->validate([
            'ward_id' => 'required|exists:wards,id',
            'week' => 'required|date',
            'nurse_id' => 'required|exists:nurses,id',
            'type' => ['required', Rule::in(array_keys(NurseLeave::TYPES))],
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'note' => 'nullable|string|max:255',
        ]);

        $ward = Ward::findOrFail($validated['ward_id']);
        $back = $this->backTo($ward, $this->weekStart($validated['week']), 'leave');
        $start = Carbon::parse($validated['start_date'])->startOfDay();
        $end = Carbon::parse($validated['end_date'])->startOfDay();

        if ($start->diffInDays($end) + 1 > self::MAX_LEAVE_DAYS) {
            return $back->withInput()->with('error', 'Leave can be at most ' . self::MAX_LEAVE_DAYS . ' days at a time.');
        }

        $leave = NurseLeave::create([
            'nurse_id' => $validated['nurse_id'],
            'type' => $validated['type'],
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'note' => $validated['note'] ?? null,
            'created_by' => Auth::id(),
        ]);

        // AI-planned shifts on those days no longer stand
        $removed = NurseRosterEntry::where('nurse_id', $leave->nurse_id)
            ->where('source', NurseRosterEntry::SOURCE_AUTO)
            ->whereBetween('roster_date', [$start->toDateString(), $end->toDateString()])
            ->delete();

        $message = $leave->label() . ' added for ' . $leave->nurse->name . ', ' . $start->format('j M')
            . ($start->equalTo($end) ? '' : ' to ' . $end->format('j M')) . '.';
        if ($removed) {
            $message .= ' ' . $removed . ' AI-planned shifts on those days were removed; generate the roster again to fill the gaps.';
        }

        return $back->with('success', $message);
    }

    public function destroyLeave(Request $request, NurseLeave $leave)
    {
        $this->authorizeEdit();
        $leave->delete();

        return $this->backTo($this->wardFrom($request), $this->weekStart($request->input('week')), 'leave')
            ->with('success', 'Leave removed.');
    }

    public function storeHoliday(Request $request)
    {
        $this->authorizeEdit();
        $validated = $request->validate([
            'ward_id' => 'required|exists:wards,id',
            'week' => 'required|date',
            'holiday_date' => 'required|date|unique:public_holidays,holiday_date',
            'name' => 'required|string|max:100',
        ], [
            'holiday_date.unique' => 'There is already a public holiday on that date.',
        ]);

        PublicHoliday::create(['holiday_date' => $validated['holiday_date'], 'name' => trim($validated['name'])]);

        return $this->backTo(Ward::findOrFail($validated['ward_id']), $this->weekStart($validated['week']), 'leave')
            ->with('success', 'Public holiday added: ' . trim($validated['name']) . ', ' . Carbon::parse($validated['holiday_date'])->format('j M Y') . '.');
    }

    public function destroyHoliday(Request $request, PublicHoliday $holiday)
    {
        $this->authorizeEdit();
        $holiday->delete();

        return $this->backTo($this->wardFrom($request), $this->weekStart($request->input('week')), 'leave')
            ->with('success', 'Public holiday removed.');
    }

    public function updateRules(Request $request)
    {
        $this->authorizeEdit();
        $validated = $request->validate([
            'ward_id' => 'required|exists:wards,id',
            'week' => 'required|date',
            'min_staff.AM' => 'required|integer|min:0|max:50',
            'min_staff.PM' => 'required|integer|min:0|max:50',
            'min_staff.ON' => 'required|integer|min:0|max:50',
            'target_shifts_per_week' => 'required|integer|min:1|max:7',
            'max_consecutive_days' => 'required|integer|min:1|max:14',
            'max_consecutive_nights' => 'required|integer|min:1|max:7',
        ]);

        $ward = Ward::findOrFail($validated['ward_id']);

        $this->saveSettings($ward, fn (array $rules) => array_merge($rules, [
            'min_staff' => array_map('intval', $validated['min_staff']),
            'target_shifts_per_week' => (int) $validated['target_shifts_per_week'],
            'max_consecutive_days' => (int) $validated['max_consecutive_days'],
            'max_consecutive_nights' => (int) $validated['max_consecutive_nights'],
            'avoid_quick_return' => $request->boolean('avoid_quick_return'),
        ]));

        return $this->backTo($ward, $this->weekStart($validated['week']), 'roster')
            ->with('success', 'Staffing rules saved. Generate the roster again to apply them.');
    }

    /** Save the ward's workload weights, then show the same shift again so the effect is visible. */
    public function updateWeights(Request $request)
    {
        $this->authorizeEdit();
        $validated = $request->validate([
            'ward_id' => 'required|exists:wards,id',
            'week' => 'required|date',
            'day' => 'nullable|date',
            'shift' => ['nullable', Rule::in(WardShifts::CODES)],
        ] + WorkloadWeights::validationRules());

        $ward = Ward::findOrFail($validated['ward_id']);
        $weights = array_map(fn ($value) => round((float) $value, 2), $validated['weights']);

        $this->saveSettings($ward, fn (array $rules) => array_merge($rules, ['workload' => $weights]));

        return $this->backToShift($ward, $request, $validated)
            ->with('success', 'Workload weights saved. Scores and AI suggestions now use them.');
    }

    public function resetWeights(Request $request)
    {
        $this->authorizeEdit();
        $validated = $request->validate([
            'ward_id' => 'required|exists:wards,id',
            'week' => 'required|date',
            'day' => 'nullable|date',
            'shift' => ['nullable', Rule::in(WardShifts::CODES)],
        ]);

        $ward = Ward::findOrFail($validated['ward_id']);
        $this->saveSettings($ward, function (array $rules) {
            unset($rules['workload']);

            return $rules;
        });

        return $this->backToShift($ward, $request, $validated)
            ->with('success', 'Workload weights reset to the defaults.');
    }

    /** Change a ward's stored settings without losing the parts not being changed. */
    private function saveSettings(Ward $ward, callable $change): void
    {
        $setting = WardRosterSetting::firstOrNew(['ward_id' => $ward->id]);
        $setting->rules = $change(is_array($setting->rules) ? $setting->rules : []);
        $setting->save();
    }

    /** Back to the bed assignment tab on the shift the form was posted from. */
    private function backToShift(Ward $ward, Request $request, array $validated): RedirectResponse
    {
        return $this->backTo($ward, $this->weekStart($validated['week']), 'assign', array_filter([
            'day' => $validated['day'] ?? null,
            'shift' => $validated['shift'] ?? null,
            'suggest' => $request->boolean('suggest') ? 1 : null,
            'weights' => 1,
        ]));
    }

    private function authorizeEdit(): void
    {
        abort_if(Auth::user()?->hasRole(User::ROLE_NURSE), 403, 'Nurses can view the AI Nurse Schedule but not change it.');
    }

    private function weekStart(?string $week): Carbon
    {
        try {
            $date = $week ? Carbon::parse($week) : now();
        } catch (\Throwable) {
            $date = now();
        }

        return $date->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
    }

    private function wardFrom(Request $request): Ward
    {
        return Ward::find((int) $request->input('ward_id')) ?? Ward::where('is_active', true)->orderBy('ward_name')->firstOrFail();
    }

    private function backTo(Ward $ward, Carbon $weekStart, string $tab, array $extra = []): RedirectResponse
    {
        return redirect()->route('ward.ai-schedule', array_merge([
            'ward_id' => $ward->id,
            'week' => $weekStart->toDateString(),
            'tab' => $tab,
        ], $extra));
    }
}
