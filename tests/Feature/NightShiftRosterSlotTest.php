<?php

namespace Tests\Feature;

use App\Models\AdmissionLog;
use App\Models\Bed;
use App\Models\Hospital;
use App\Models\Nurse;
use App\Models\NurseRosterEntry;
use App\Models\Patient;
use App\Models\ShiftSetting;
use App\Models\User;
use App\Models\Ward;
use App\Models\WardScheduleAssignment;
use App\Services\DischargeSummaryService;
use App\Services\NurseScheduling\RosterSlot;
use App\Services\NursingPlan\ShiftTasks;
use App\Services\PatientRoster;
use App\Services\ShiftHandover;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * ON on a date is the night that starts that evening, so after midnight the
 * night on duty is the ON dated yesterday (RosterSlot). Every screen that shows
 * who is on now reads that entry, not tonight's.
 *
 * Bed B1's roster: Mon 5 Oct AM Aisyah, PM Ravi, ON Nadia (on until 07:00 on
 * the 6th); Tue 6 Oct AM Mei Ling, ON Farah (tonight).
 */
class NightShiftRosterSlotTest extends TestCase
{
    use RefreshDatabase;

    private const SMALL_HOURS = '2026-10-06 02:00:00';
    private const LATE_EVENING = '2026-10-05 23:30:00';

    private Ward $ward;
    private Bed $bed;
    private Patient $patient;
    private User $manager;
    /** @var array<string, Nurse> */
    private array $nurses = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse(self::SMALL_HOURS));

        $hospital = Hospital::create(['name' => 'Test Hospital']);
        $this->ward = Ward::create(['hospital_id' => $hospital->id, 'ward_code' => 'W6', 'ward_name' => 'Ward 6', 'is_active' => true]);
        foreach (ShiftSetting::getDefaults() as $shift) {
            ShiftSetting::create($shift + ['ward_id' => $this->ward->id, 'is_active' => true]);
        }
        $this->bed = Bed::create(['ward_id' => $this->ward->id, 'bed_number' => 'B1', 'bed_id' => 'W6-B1', 'bed_display_name' => 'B1', 'status' => 'occupied', 'is_active' => true]);
        Bed::create(['ward_id' => $this->ward->id, 'bed_number' => 'B2', 'bed_id' => 'W6-B2', 'bed_display_name' => 'B2', 'status' => 'available', 'is_active' => true]);

        foreach (['aisyah' => 'Aisyah Rahman', 'ravi' => 'Ravi Kumar', 'nadia' => 'Nadia Hassan', 'meiling' => 'Mei Ling', 'farah' => 'Farah Idris'] as $username => $name) {
            $this->nurses[$username] = Nurse::create([
                'name' => $name, 'registration_number' => 'LJM-' . crc32($name), 'designation' => 'STAFF NURSE I',
                'ward_id' => $this->ward->id, 'app_username' => $username, 'app_password' => 'secret123', 'is_active' => true,
            ]);
        }
        $this->manager = User::factory()->create(['name' => 'Sister Wong']);

        $this->patient = Patient::create([
            'name' => 'Patient B1', 'mrn' => 'MRN-B1', 'rn' => 'RN-B1', 'ic_passport' => 'IC-B1', 'age' => 60, 'gender' => 'Female',
            'phone' => '0', 'ward_id' => $this->ward->id, 'bed_number' => 'B1', 'status' => 'admitted',
            'admitted_at' => Carbon::parse('2026-10-03 10:00:00'), 'is_active' => true,
        ]);

        $this->roster('2026-10-05', ['AM' => 'aisyah', 'PM' => 'ravi', 'ON' => 'nadia']);
        $this->roster('2026-10-06', ['AM' => 'meiling', 'ON' => 'farah']);
    }

    /** Bed B1's nurse for each shift of a day. */
    private function roster(string $date, array $shifts): void
    {
        foreach ($shifts as $shift => $nurse) {
            WardScheduleAssignment::create(['ward_id' => $this->ward->id, 'bed_id' => $this->bed->id, 'nurse_id' => $this->nurses[$nurse]->id, 'scheduled_date' => $date, 'shift' => $shift]);
        }
    }

    private function slot(?array $slot): ?array
    {
        return $slot ? [$slot['date'], $slot['code'], $slot['label']] : null;
    }

    private function asNurse(string $nurse)
    {
        return $this->withToken($this->nurses[$nurse]->generateAppToken());
    }

    public function test_after_midnight_the_slot_on_duty_is_last_nights_on(): void
    {
        $current = RosterSlot::current($this->ward->id);
        $this->assertSame(['2026-10-05', 'ON', 'ON'], $this->slot($current));
        $this->assertSame(['2026-10-05 23:00:00', '2026-10-06 07:00:00'], [$current['starts_at']->toDateTimeString(), $current['ends_at']->toDateTimeString()]);
        $this->assertSame(['2026-10-06', 'AM', 'AM'], $this->slot(RosterSlot::next($this->ward->id)), 'this morning');

        // Before midnight it is the same night; the morning after it is tomorrow's
        $late = Carbon::parse(self::LATE_EVENING);
        $this->assertSame(['2026-10-05', 'ON', 'ON'], $this->slot(RosterSlot::current($this->ward->id, $late)));
        $this->assertSame(['2026-10-06', 'AM', 'AM tomorrow'], $this->slot(RosterSlot::next($this->ward->id, $late)));

        // The night ends at 07:00; the default shift times read the same
        $this->assertSame(['2026-10-05', 'ON', 'ON'], $this->slot(RosterSlot::current($this->ward->id, Carbon::parse('2026-10-06 06:59:59'))));
        $this->assertSame(['2026-10-06', 'AM', 'AM'], $this->slot(RosterSlot::current($this->ward->id, Carbon::parse('2026-10-06 07:00:00'))));
        $this->assertSame(['2026-10-05', 'ON', 'ON'], $this->slot(RosterSlot::current(null)));

        // Labels: the night on duty is plain "ON", tonight's "ON tomorrow" until it ends
        $this->assertSame(['ON', 'ON tomorrow', 'ON yesterday', 'PM yesterday', 'AM'], [
            ShiftHandover::label('ON', '2026-10-05'),
            ShiftHandover::label('ON', '2026-10-06'),
            ShiftHandover::label('ON', '2026-10-04'),
            ShiftHandover::label('PM', '2026-10-05'),
            ShiftHandover::label('AM', '2026-10-06'),
        ]);
        $morning = Carbon::parse('2026-10-06 10:00:00');
        $this->assertSame(['ON yesterday', 'ON'], [ShiftHandover::label('ON', '2026-10-05', $morning), ShiftHandover::label('ON', '2026-10-06', $morning)]);
    }

    public function test_the_wards_own_times_and_gaps_between_shifts(): void
    {
        $ward = Ward::create(['hospital_id' => $this->ward->hospital_id, 'ward_code' => 'W7', 'ward_name' => 'Ward 7', 'is_active' => true]);
        foreach ([['AM', 'Morning', '07:00', '14:00'], ['PM', 'Afternoon', '15:00', '21:00'], ['ON', 'Night', '21:00', '07:00']] as $i => [$code, $name, $start, $end]) {
            ShiftSetting::create(['ward_id' => $ward->id, 'shift_code' => $code, 'shift_name' => $name, 'start_time' => $start, 'end_time' => $end, 'is_active' => true, 'display_order' => $i + 1]);
        }

        $this->assertSame(['2026-10-05', 'ON', 'ON'], $this->slot(RosterSlot::current($ward->id, Carbon::parse('2026-10-05 21:30:00'))));
        $this->assertSame(['2026-10-05', 'ON', 'ON'], $this->slot(RosterSlot::current($ward->id, Carbon::parse('2026-10-06 06:00:00'))));

        // 14:00 - 15:00 is nobody's: nothing on, and the afternoon is next
        $this->assertNull(RosterSlot::current($ward->id, Carbon::parse('2026-10-06 14:30:00')));
        $this->assertSame(['2026-10-06', 'PM', 'PM'], $this->slot(RosterSlot::next($ward->id, Carbon::parse('2026-10-06 14:30:00'))));
        $this->assertSame(['2026-10-06', 'PM', 'PM'], $this->slot(RosterSlot::next($ward->id, Carbon::parse('2026-10-06 13:00:00'))));
    }

    public function test_ward_dashboard_bed_boxes_nurse_list_and_count_show_the_night_on_duty(): void
    {
        $dashboard = fn () => $this->actingAs($this->manager)->get(route('ward.dashboard', ['ward_id' => $this->ward->id]))->assertOk();

        $response = $dashboard();
        $bed = collect($response->viewData('beds'))->firstWhere('number', 'B1');
        $this->assertSame(['Nadia Hassan', 'ON'], [$bed['nurse_on_duty'], $bed['current_shift']]);
        $this->assertSame(['Nadia Hassan'], array_column($response->viewData('nursePatients'), 'name'));
        $this->assertSame(1, $response->viewData('statistics')['nurses']);

        $this->travelTo(Carbon::parse(self::LATE_EVENING));
        $this->assertSame('Nadia Hassan', collect($dashboard()->viewData('beds'))->firstWhere('number', 'B1')['nurse_on_duty']);

        $this->travelTo(Carbon::parse('2026-10-06 10:00:00'));
        $bed = collect($dashboard()->viewData('beds'))->firstWhere('number', 'B1');
        $this->assertSame(['Mei Ling', 'AM'], [$bed['nurse_on_duty'], $bed['current_shift']]);
    }

    public function test_web_nurse_dashboard_lists_the_beds_of_the_night_on_duty(): void
    {
        $nadia = $this->actingAs($this->manager)->get(route('nurses.dashboard', $this->nurses['nadia']))->assertOk();
        $this->assertSame(['B1'], $nadia->viewData('assignedBeds')->pluck('number')->all());
        $this->assertSame('ON', $nadia->viewData('currentShift')->shift_code);

        // Tonight's nurse is not on yet
        $farah = $this->actingAs($this->manager)->get(route('nurses.dashboard', $this->nurses['farah']))->assertOk();
        $this->assertSame([], $farah->viewData('assignedBeds')->pluck('number')->all());
    }

    public function test_nurse_app_dashboard_and_team_follow_the_night_on_duty(): void
    {
        $dashboard = $this->asNurse('nadia')->getJson('/api/nurse/dashboard')->assertOk()->json();
        $this->assertSame(['B1'], array_column($dashboard['beds'], 'number'));
        $this->assertSame('ON', $dashboard['current_shift']['shift_code']);
        $this->assertSame([], array_column($this->asNurse('farah')->getJson('/api/nurse/dashboard')->assertOk()->json('beds'), 'number'), 'tonight is not on yet');

        $team = $this->asNurse('nadia')->getJson('/api/nurse/team')->assertOk()->json('team');
        $this->assertSame(['2026-10-05', 'ON', 'ON'], [$team['current']['date'], $team['current']['code'], $team['current']['label']]);
        $this->assertSame([['Nadia Hassan', ['B1'], true]], array_map(fn (array $nurse) => [$nurse['name'], $nurse['beds'], $nurse['is_me']], $team['current']['nurses']));
        $this->assertSame(['2026-10-06', 'AM', 'AM', ['Mei Ling']], [$team['next']['date'], $team['next']['code'], $team['next']['label'], array_column($team['next']['nurses'], 'name')]);

        // At 23:30 the same night is on, and tomorrow's morning comes next
        $this->travelTo(Carbon::parse(self::LATE_EVENING));
        $team = $this->asNurse('nadia')->getJson('/api/nurse/team')->assertOk()->json('team');
        $this->assertSame(['2026-10-05', 'ON', 'ON'], [$team['current']['date'], $team['current']['code'], $team['current']['label']]);
        $this->assertSame(['2026-10-06', 'AM', 'AM tomorrow'], [$team['next']['date'], $team['next']['code'], $team['next']['label']]);
        $this->assertSame(['B1'], array_column($this->asNurse('nadia')->getJson('/api/nurse/dashboard')->json('beds'), 'number'));
    }

    public function test_shift_handover_slots_and_the_patient_roster_put_the_night_nurse_on_now(): void
    {
        $slots = ShiftHandover::slotsFor($this->patient);
        $this->assertSame(['2026-10-05', 'ON', 'ON', '23:00 - 07:00', 'Nadia Hassan'],
            [$slots['current']['date'], $slots['current']['code'], $slots['current']['label'], $slots['current']['time'], $slots['current']['nurse']->name]);
        $this->assertSame(['2026-10-06', 'AM', 'AM', 'Mei Ling'],
            [$slots['next']['date'], $slots['next']['code'], $slots['next']['label'], $slots['next']['nurse']->name]);

        $roster = PatientRoster::forPatient($this->patient);
        $this->assertSame(['ON', 'Nadia Hassan'], [$roster['current']['code'], $roster['current']['nurse']->name]);
        $this->assertSame([['2026-10-05', 'Yesterday'], ['2026-10-06', 'Today']],
            array_map(fn (array $day) => [$day['date']->toDateString(), $day['label']], $roster['days']));
        $this->assertSame(['2026-10-05 ON'], collect($roster['days'])
            ->flatMap(fn (array $day) => collect($day['shifts'])->where('is_current', true)->map(fn (array $shift) => $day['date']->toDateString() . ' ' . $shift['code']))
            ->values()->all());
        $this->assertSame('Farah Idris', $roster['days'][1]['shifts'][2]['nurse']->name, 'tonight');

        // Before midnight: the same night, and tomorrow's morning next
        $this->travelTo(Carbon::parse(self::LATE_EVENING));
        $slots = ShiftHandover::slotsFor($this->patient);
        $this->assertSame(['2026-10-05', 'ON', 'ON', 'Nadia Hassan'], [$slots['current']['date'], $slots['current']['code'], $slots['current']['label'], $slots['current']['nurse']->name]);
        $this->assertSame(['2026-10-06', 'AM', 'AM tomorrow', 'Mei Ling'], [$slots['next']['date'], $slots['next']['code'], $slots['next']['label'], $slots['next']['nurse']->name]);
        $this->assertSame([['2026-10-05', 'Today'], ['2026-10-06', 'Tomorrow']],
            array_map(fn (array $day) => [$day['date']->toDateString(), $day['label']], PatientRoster::forPatient($this->patient)['days']));
    }

    public function test_this_shifts_tasks_name_the_night_nurse(): void
    {
        $shift = ShiftTasks::forPatient($this->patient)['shift'];

        $this->assertSame(['ON', 'ON', '23:00 - 07:00', 'Nadia Hassan'], [$shift['code'], $shift['label'], $shift['time'], $shift['nurse']]);
    }

    public function test_the_ai_schedule_opens_on_and_assigns_the_night_still_on(): void
    {
        $assign = $this->actingAs($this->manager)
            ->get(route('ward.ai-schedule', ['ward_id' => $this->ward->id, 'week' => '2026-10-05', 'tab' => 'assign']))
            ->assertOk()
            ->assertSee('ON &middot; 23:00 - 07:00 (now)', false);
        $this->assertSame(['2026-10-05', 'ON', 'ON'], [$assign->viewData('day'), $assign->viewData('shift'), $assign->viewData('liveCode')]);

        // Sharing the week's beds out still covers last night's ON: it runs until 07:00
        WardScheduleAssignment::where('nurse_id', $this->nurses['nadia']->id)->delete();
        NurseRosterEntry::create(['ward_id' => $this->ward->id, 'nurse_id' => $this->nurses['nadia']->id, 'roster_date' => '2026-10-05', 'shift' => 'ON', 'source' => NurseRosterEntry::SOURCE_MANUAL]);

        $this->actingAs($this->manager)
            ->post(route('ward.ai-schedule.assign-week'), ['ward_id' => $this->ward->id, 'week' => '2026-10-05'])
            ->assertSessionHas('success');

        $nurseOn = fn (string $date, string $shift) => WardScheduleAssignment::where('bed_id', $this->bed->id)->whereDate('scheduled_date', $date)->where('shift', $shift)->value('nurse_id');
        $this->assertSame($this->nurses['nadia']->id, $nurseOn('2026-10-05', 'ON'));
        // The day's shifts that are over are left as they were
        $this->assertSame([$this->nurses['aisyah']->id, $this->nurses['ravi']->id], [$nurseOn('2026-10-05', 'AM'), $nurseOn('2026-10-05', 'PM')]);
    }

    public function test_the_bedside_display_shift_change_targets_last_nights_on(): void
    {
        $this->artisan('ekad:update-shift-nurses', ['--shift' => 'ON', '--ward' => $this->ward->id])
            ->expectsOutputToContain('Target Shift: ON for Date: 2026-10-05')
            ->assertExitCode(0);

        // As scheduled, 15 minutes before the night starts
        $this->travelTo(Carbon::parse('2026-10-05 22:45:00'));
        $this->artisan('ekad:update-shift-nurses', ['--shift' => 'ON', '--ward' => $this->ward->id])
            ->expectsOutputToContain('Target Shift: ON for Date: 2026-10-05')
            ->assertExitCode(0);

        // No shift given: the one starting within 20 minutes
        $this->travelTo(Carbon::parse('2026-10-06 06:45:00'));
        $this->artisan('ekad:update-shift-nurses', ['--ward' => $this->ward->id])
            ->expectsOutputToContain('Target Shift: AM for Date: 2026-10-06')
            ->assertExitCode(0);
    }

    public function test_the_discharge_summary_counts_the_night_a_patient_was_admitted_in(): void
    {
        $this->travelTo(Carbon::parse('2026-10-06 10:00:00'));
        $this->patient->update(['admitted_at' => Carbon::parse('2026-10-06 02:00:00')]);
        AdmissionLog::create([
            'patient_id' => $this->patient->id, 'ward_id' => $this->ward->id, 'user_id' => $this->manager->id, 'bed_number' => 'B1',
            'action' => 'admit', 'patient_name' => $this->patient->name, 'mrn' => $this->patient->mrn, 'source' => 'manual',
            'admitted_at' => Carbon::parse('2026-10-06 02:00:00'),
        ])->forceFill(['created_at' => Carbon::parse('2026-10-06 02:05:00')])->saveQuietly();

        $this->actingAs($this->manager);
        $service = app(DischargeSummaryService::class);
        $summary = $service->summaryForEpisode($service->admissionsFor($this->patient)->first()->episode);

        // Nadia's night (ON on the 5th) admitted her; the 5th's day shifts were before she came
        $this->assertSame(['Farah Idris', 'Mei Ling', 'Nadia Hassan'],
            $summary['nursingRoster']['nurses']->map(fn (array $row) => $row['nurse']->name)->sort()->values()->all());
    }
}
