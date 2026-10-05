<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\Hospital;
use App\Models\Nurse;
use App\Models\NurseLeave;
use App\Models\NurseRosterEntry;
use App\Models\NurseRosterRequest;
use App\Models\Patient;
use App\Models\PublicHoliday;
use App\Models\ShiftSetting;
use App\Models\User;
use App\Models\Ward;
use App\Models\WardScheduleAssignment;
use App\Models\WardSpecialDuty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Nurse app and the AI Nurse Schedule: my roster, acknowledging it, leave and
 * shift-swap requests approved by the nurse manager, the ward team on shift,
 * and each bed's workload.
 */
class NurseAppRosterTest extends TestCase
{
    use RefreshDatabase;

    private Ward $ward;
    private array $beds = [];
    private Nurse $aisyah;
    private Nurse $meiLing;
    private Nurse $farah;
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-05 10:00:00')); // Monday, AM shift

        $hospital = Hospital::create(['name' => 'Test Hospital']);
        $this->ward = Ward::create(['hospital_id' => $hospital->id, 'ward_code' => 'W6', 'ward_name' => 'Ward 6', 'is_active' => true]);
        foreach (ShiftSetting::getDefaults() as $shift) {
            ShiftSetting::create($shift + ['ward_id' => $this->ward->id, 'is_active' => true]);
        }
        foreach (['B1', 'B2', 'B3'] as $number) {
            $this->beds[$number] = Bed::create(['ward_id' => $this->ward->id, 'bed_number' => $number, 'bed_id' => 'W6-' . $number, 'bed_display_name' => $number, 'status' => 'occupied', 'is_active' => true]);
        }

        $this->aisyah = $this->nurse('Aisyah Rahman', 'aisyah');
        $this->meiLing = $this->nurse('Mei Ling', 'meiling');
        $this->farah = $this->nurse('Farah Idris', 'farah');
        $this->manager = User::factory()->create(['name' => 'Sister Wong']);

        // Patients: B1 heavy (level 3, isolation), B2 plain, B3 ADT fall risk alert
        $this->patient('B1', ['nursing_level' => 'level_3', 'isolation_type' => 'contact']);
        $this->patient('B2');
        $this->patient('B3', ['fall_risk' => '1']);

        // The week: Aisyah AM Mon (team leader), PM Tue, off Wed, ON Thu; Mei Ling PM Mon, AM Tue, AM Thu
        $this->roster($this->aisyah, ['2026-10-05' => 'AM', '2026-10-06' => 'PM', '2026-10-07' => 'OFF', '2026-10-08' => 'ON']);
        $this->roster($this->meiLing, ['2026-10-05' => 'PM', '2026-10-06' => 'AM', '2026-10-08' => 'AM']);
        $this->roster($this->farah, ['2026-10-05' => 'AM']);
        $this->beds('2026-10-05', 'AM', ['B1' => $this->aisyah, 'B2' => $this->aisyah, 'B3' => $this->farah]);
        $this->beds('2026-10-05', 'PM', ['B1' => $this->meiLing, 'B2' => $this->meiLing, 'B3' => $this->meiLing]);
        $this->beds('2026-10-06', 'PM', ['B1' => $this->aisyah]);
        $this->beds('2026-10-06', 'AM', ['B1' => $this->meiLing]);
        WardSpecialDuty::create(['ward_id' => $this->ward->id, 'nurse_id' => $this->aisyah->id, 'date' => '2026-10-05', 'shift' => 'AM', 'duty_type' => 'team_leader']);
        NurseLeave::create(['nurse_id' => $this->farah->id, 'type' => 'annual', 'start_date' => '2026-10-07', 'end_date' => '2026-10-07']);
        PublicHoliday::create(['holiday_date' => '2026-10-09', 'name' => 'Founders Day']);
    }

    private function nurse(string $name, string $username): Nurse
    {
        return Nurse::create([
            'name' => $name, 'registration_number' => 'LJM-' . crc32($name), 'designation' => 'STAFF NURSE I',
            'ward_id' => $this->ward->id, 'app_username' => $username, 'app_password' => 'secret123', 'is_active' => true,
        ]);
    }

    private function patient(string $bed, array $fields = []): Patient
    {
        return Patient::create($fields + [
            'name' => 'Patient ' . $bed, 'mrn' => 'MRN-' . $bed, 'rn' => 'RN-' . $bed, 'ic_passport' => 'IC-' . $bed,
            'age' => 50, 'gender' => 'Female', 'phone' => '0', 'ward_id' => $this->ward->id, 'bed_number' => $bed,
            'status' => 'admitted', 'admitted_at' => now()->subDays(3), 'is_active' => true,
        ]);
    }

    private function roster(Nurse $nurse, array $days): void
    {
        foreach ($days as $date => $shift) {
            NurseRosterEntry::create(['ward_id' => $this->ward->id, 'nurse_id' => $nurse->id, 'roster_date' => $date, 'shift' => $shift, 'source' => NurseRosterEntry::SOURCE_AUTO]);
        }
    }

    private function beds(string $date, string $shift, array $map): void
    {
        foreach ($map as $bed => $nurse) {
            WardScheduleAssignment::create(['ward_id' => $this->ward->id, 'bed_id' => $this->beds[$bed]->id, 'nurse_id' => $nurse->id, 'scheduled_date' => $date, 'shift' => $shift]);
        }
    }

    private function as(Nurse $nurse)
    {
        return $this->withToken($nurse->generateAppToken());
    }

    private function aiSchedule(string $tab = 'leave')
    {
        return $this->actingAs($this->manager)->get(route('ward.ai-schedule', ['ward_id' => $this->ward->id, 'week' => '2026-10-05', 'tab' => $tab]));
    }

    public function test_my_roster_shows_shifts_beds_leave_holidays_and_duties(): void
    {
        $roster = $this->as($this->aisyah)->getJson('/api/nurse/roster')->assertOk()->json('roster');

        $this->assertCount(2, $roster['weeks']);
        $week = $roster['weeks'][0];
        $days = collect($week['days'])->keyBy('date');
        $this->assertSame('5 Oct – 11 Oct', $week['label']);
        $this->assertSame(['AM', '07:00 - 14:00', ['B1', 'B2'], ['Team Leader'], true],
            [$days['2026-10-05']['shift'], $days['2026-10-05']['time'], $days['2026-10-05']['beds'], $days['2026-10-05']['duties'], $days['2026-10-05']['is_today']]);
        $this->assertFalse($days['2026-10-05']['swappable'], 'the AM shift has started');
        $this->assertSame(['PM', ['B1'], true], [$days['2026-10-06']['shift'], $days['2026-10-06']['beds'], $days['2026-10-06']['swappable']]);
        $this->assertSame(['OFF', 'Day off'], [$days['2026-10-07']['shift'], $days['2026-10-07']['shift_name']]);
        $this->assertSame('ON', $days['2026-10-08']['shift']);
        $this->assertSame('Founders Day', $days['2026-10-09']['holiday']);
        $this->assertEquals(['shifts' => 3, 'hours' => 24, 'nights' => 1, 'leave_days' => 0], $week['totals']);
        $this->assertSame('new', $week['acknowledgement']['state']);
        $this->assertArrayNotHasKey('fingerprint', $week);
        $this->assertContains('annual', array_column($roster['options']['leave_types'], 'key'));

        $this->withoutToken()->getJson('/api/nurse/roster')->assertUnauthorized();
    }

    public function test_acknowledging_a_week_holds_until_the_roster_changes(): void
    {
        $this->as($this->aisyah)->postJson('/api/nurse/roster/acknowledge', ['week' => '2026-10-07'])
            ->assertOk()
            ->assertJsonPath('message', 'Roster for 5 Oct – 11 Oct acknowledged.')
            ->assertJsonPath('roster.weeks.0.acknowledgement.state', 'seen');

        $this->aiSchedule('roster')->assertOk()->assertSee('Seen 05 Oct 10:00')->assertSee('Not seen');

        // The manager moves her day off: she is asked to look again
        NurseRosterEntry::where('nurse_id', $this->aisyah->id)->whereDate('roster_date', '2026-10-07')->update(['shift' => 'AM']);
        $this->as($this->aisyah)->getJson('/api/nurse/roster')->assertJsonPath('roster.weeks.0.acknowledgement.state', 'changed');
        $this->aiSchedule('roster')->assertSee('Changed since seen');

        // A change to the beds alone does not count
        $this->as($this->aisyah)->postJson('/api/nurse/roster/acknowledge', ['week' => '2026-10-05']);
        WardScheduleAssignment::where('nurse_id', $this->aisyah->id)->whereDate('scheduled_date', '2026-10-06')->update(['nurse_id' => $this->farah->id]);
        $this->as($this->aisyah)->getJson('/api/nurse/roster')->assertJsonPath('roster.weeks.0.acknowledgement.state', 'seen');
    }

    public function test_leave_request_is_approved_on_the_ai_schedule(): void
    {
        $this->roster($this->aisyah, ['2026-10-12' => 'AM']);

        $this->as($this->aisyah)->postJson('/api/nurse/requests', ['type' => 'leave', 'leave_type' => 'annual', 'start_date' => '2026-10-01', 'end_date' => '2026-10-02'])
            ->assertStatus(422)->assertJsonPath('message', 'Leave cannot start in the past. Ask the nurse manager to record it.');

        $this->as($this->aisyah)->postJson('/api/nurse/requests', [
            'type' => 'leave', 'leave_type' => 'annual', 'start_date' => '2026-10-12', 'end_date' => '2026-10-13', 'note' => 'Family wedding',
        ])->assertOk()
            ->assertJsonPath('message', 'Leave request sent to the nurse manager.')
            ->assertJsonPath('roster.requests.mine.0.summary', 'Annual leave, Mon 12 Oct to Tue 13 Oct')
            ->assertJsonPath('roster.requests.mine.0.status', 'pending')
            ->assertJsonPath('roster.requests.mine.0.can_cancel', true);

        $this->as($this->aisyah)->postJson('/api/nurse/requests', ['type' => 'leave', 'leave_type' => 'medical', 'start_date' => '2026-10-13', 'end_date' => '2026-10-13'])
            ->assertStatus(422)->assertJsonPath('message', 'You already asked for leave on some of those days.');

        $this->aiSchedule()->assertOk()
            ->assertSee('Requests from the nurse app')
            ->assertSeeInOrder(['Aisyah Rahman', 'Leave', 'Waiting for approval', 'Annual leave, Mon 12 Oct to Tue 13 Oct', 'Family wedding', 'Approve'])
            ->assertSee('request(s) from the nurse app to decide', false);

        $request = NurseRosterRequest::sole();
        $this->actingAs($this->manager)->post(route('ward.ai-schedule.requests.approve', $request), ['week' => '2026-10-05', 'decision_note' => 'Enjoy'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Approved. Annual leave approved for Aisyah Rahman, 12 Oct to 13 Oct. 1 AI-planned shifts on those days were removed; generate the roster again to fill the gaps.');

        $this->assertSame(['approved', 'Sister Wong', 'Enjoy'], [$request->fresh()->status, $request->fresh()->decided_by_name, $request->fresh()->decision_note]);
        $leave = NurseLeave::where('nurse_id', $this->aisyah->id)->sole();
        $this->assertSame(['annual', '2026-10-12', '2026-10-13'], [$leave->type, $leave->start_date->toDateString(), $leave->end_date->toDateString()]);
        $this->assertFalse(NurseRosterEntry::where('nurse_id', $this->aisyah->id)->whereDate('roster_date', '2026-10-12')->exists());

        // Only once, and nurses cannot decide
        $this->actingAs($this->manager)->post(route('ward.ai-schedule.requests.approve', $request))->assertSessionHas('error');
        $nurseUser = User::factory()->create(['role' => User::ROLE_NURSE]);
        $this->actingAs($nurseUser)->post(route('ward.ai-schedule.requests.decline', $request))->assertForbidden();
    }

    public function test_the_managers_leave_form_still_books_leave_and_clears_ai_shifts(): void
    {
        $this->actingAs($this->manager)->post(route('ward.ai-schedule.leaves.store'), [
            'ward_id' => $this->ward->id, 'week' => '2026-10-05', 'nurse_id' => $this->meiLing->id,
            'type' => 'medical', 'start_date' => '2026-10-06', 'end_date' => '2026-10-06',
        ])->assertSessionHas('success', 'Medical leave added for Mei Ling, 6 Oct. 1 AI-planned shifts on those days were removed; generate the roster again to fill the gaps.');

        $this->assertSame('medical', NurseLeave::where('nurse_id', $this->meiLing->id)->value('type'));
        $this->assertFalse(NurseRosterEntry::where('nurse_id', $this->meiLing->id)->whereDate('roster_date', '2026-10-06')->exists());
    }

    public function test_a_swap_goes_to_the_colleague_then_the_manager_and_moves_roster_and_beds(): void
    {
        $swap = ['type' => 'swap', 'shift_date' => '2026-10-06', 'colleague_id' => $this->meiLing->id];

        $this->as($this->aisyah)->postJson('/api/nurse/requests', $swap + ['colleague_shift' => 'PM'])
            ->assertStatus(422)->assertJsonPath('message', 'Swap for a different shift, or give the shift away.');
        $this->as($this->aisyah)->postJson('/api/nurse/requests', $swap)
            ->assertStatus(422)->assertJsonPath('message', 'Mei Ling is working AM that day: ask to swap shifts instead.');

        $this->as($this->aisyah)->postJson('/api/nurse/requests', $swap + ['colleague_shift' => 'AM', 'note' => 'Clinic appointment'])
            ->assertOk()
            ->assertJsonPath('roster.requests.mine.0.summary', 'Swap PM on Tue 6 Oct with Mei Ling (for their AM)')
            ->assertJsonPath('roster.requests.mine.0.status', 'awaiting_colleague');
        $request = NurseRosterRequest::sole();

        // Not with the manager yet
        $this->actingAs($this->manager)->post(route('ward.ai-schedule.requests.approve', $request))
            ->assertSessionHas('error', 'The colleague has not accepted this swap yet.');

        // Only Mei Ling can answer it
        $this->as($this->farah)->postJson('/api/nurse/requests/' . $request->id . '/respond', ['accept' => true])->assertStatus(409);
        $this->as($this->meiLing)->getJson('/api/nurse/roster')
            ->assertJsonPath('roster.requests.incoming.0.from', 'Aisyah Rahman')
            ->assertJsonPath('roster.requests.incoming.0.summary', 'You work PM on Tue 6 Oct; Aisyah Rahman works your AM')
            ->assertJsonPath('roster.requests.incoming.0.can_respond', true);
        $this->as($this->meiLing)->postJson('/api/nurse/requests/' . $request->id . '/respond', ['accept' => true])
            ->assertOk()
            ->assertJsonPath('roster.requests.incoming', []);
        $this->assertSame('pending', $request->fresh()->status);

        $this->actingAs($this->manager)->post(route('ward.ai-schedule.requests.approve', $request), ['week' => '2026-10-05'])
            ->assertSessionHas('success', 'Approved. Swapped on Tue 6 Oct: Mei Ling works PM, Aisyah Rahman works AM. 2 bed assignments moved with the shifts.');

        $entry = fn (Nurse $nurse) => NurseRosterEntry::where('nurse_id', $nurse->id)->whereDate('roster_date', '2026-10-06')->first();
        $this->assertSame(['AM', 'manual'], [$entry($this->aisyah)->shift, $entry($this->aisyah)->source]);
        $this->assertSame(['PM', 'manual'], [$entry($this->meiLing)->shift, $entry($this->meiLing)->source]);
        $bedNurse = fn (string $shift) => WardScheduleAssignment::where('bed_id', $this->beds['B1']->id)->whereDate('scheduled_date', '2026-10-06')->where('shift', $shift)->value('nurse_id');
        $this->assertSame([$this->aisyah->id, $this->meiLing->id], [$bedNurse('AM'), $bedNurse('PM')]);
    }

    public function test_give_away_checks_and_declines_and_cancels(): void
    {
        // Already started
        $this->as($this->aisyah)->postJson('/api/nurse/requests', ['type' => 'swap', 'shift_date' => '2026-10-05', 'colleague_id' => $this->meiLing->id])
            ->assertStatus(422)->assertJsonPath('message', 'That shift has already started.');
        // Farah is free on Thursday, so she can take the night
        $this->as($this->aisyah)->postJson('/api/nurse/requests', ['type' => 'swap', 'shift_date' => '2026-10-08', 'colleague_id' => $this->farah->id])
            ->assertOk()
            ->assertJsonPath('roster.requests.mine.0.summary', 'Give away ON on Thu 8 Oct with Farah Idris');
        $request = NurseRosterRequest::sole();

        $this->as($this->farah)->postJson('/api/nurse/requests/' . $request->id . '/respond', ['accept' => false, 'note' => 'Cannot do nights this week'])
            ->assertOk();
        $this->assertSame(['declined', 'Farah Idris', 'Cannot do nights this week'], [$request->fresh()->status, $request->fresh()->decided_by_name, $request->fresh()->decision_note]);
        $this->as($this->aisyah)->getJson('/api/nurse/roster')
            ->assertJsonPath('roster.requests.mine.0.status_label', 'Declined')
            ->assertJsonPath('roster.requests.mine.0.decision_note', 'Cannot do nights this week')
            ->assertJsonPath('roster.requests.mine.0.can_cancel', false);

        // On leave that day: refused
        $this->as($this->aisyah)->postJson('/api/nurse/requests', ['type' => 'swap', 'shift_date' => '2026-10-06', 'colleague_id' => $this->farah->id])->assertOk();
        NurseRosterRequest::where('status', 'awaiting_colleague')->update(['status' => 'cancelled']);
        NurseRosterEntry::where('nurse_id', $this->aisyah->id)->whereDate('roster_date', '2026-10-07')->update(['shift' => 'AM']);
        $this->as($this->aisyah)->postJson('/api/nurse/requests', ['type' => 'swap', 'shift_date' => '2026-10-07', 'colleague_id' => $this->farah->id])
            ->assertStatus(422)->assertJsonPath('message', 'Farah Idris is on annual leave that day.');

        // The requester withdraws a request
        $this->as($this->aisyah)->postJson('/api/nurse/requests', ['type' => 'leave', 'leave_type' => 'training', 'start_date' => '2026-10-20', 'end_date' => '2026-10-20'])->assertOk();
        $leaveRequest = NurseRosterRequest::where('type', 'leave')->sole();
        $this->as($this->meiLing)->postJson('/api/nurse/requests/' . $leaveRequest->id . '/cancel')->assertStatus(409);
        $this->as($this->aisyah)->postJson('/api/nurse/requests/' . $leaveRequest->id . '/cancel')->assertOk()->assertJsonPath('message', 'Request cancelled.');
        $this->assertSame('cancelled', $leaveRequest->fresh()->status);
    }

    public function test_colleagues_for_a_swap_show_their_shift_and_leave(): void
    {
        $colleagues = collect($this->as($this->aisyah)->getJson('/api/nurse/roster/colleagues?date=2026-10-08')->assertOk()->json('colleagues'))->keyBy('name');

        $this->assertSame('AM', $colleagues['Mei Ling']['shift']);
        $this->assertNull($colleagues['Farah Idris']['shift']);
        $this->assertArrayNotHasKey('Aisyah Rahman', $colleagues->all());

        $this->as($this->aisyah)->getJson('/api/nurse/roster/colleagues?date=2026-10-07')->assertStatus(422);
    }

    public function test_team_shows_who_is_on_with_their_beds_and_workload(): void
    {
        $team = $this->as($this->farah)->getJson('/api/nurse/team')->assertOk()->json('team');

        $this->assertSame('Ward 6', $team['ward']['name']);
        $this->assertSame(['2026-10-05', 'AM'], [$team['current']['date'], $team['current']['code']]);
        $nurses = collect($team['current']['nurses']);
        // The team leader first
        $this->assertSame(['Aisyah Rahman', 'Farah Idris'], $nurses->pluck('name')->all());
        $this->assertSame([true, ['Team Leader'], ['B1', 'B2'], 2], [$nurses[0]['is_team_leader'], $nurses[0]['duties'], $nurses[0]['beds'], $nurses[0]['patients']]);
        $this->assertTrue($nurses[1]['is_me']);
        // B1: patient 1 + level 3 (1) + isolation 0.5 = 2.5; B2: 1 -> 3.5. B3: 1 + fall risk alert 0.5 = 1.5
        $this->assertSame([3.5, 1.5], [$nurses[0]['score'], $nurses[1]['score']]);
        $this->assertSame(['heavy', 'light'], [$nurses[0]['level'], $nurses[1]['level']]);

        $this->assertSame(['PM', ['Mei Ling']], [$team['next']['code'], array_column($team['next']['nurses'], 'name')]);
    }

    public function test_the_dashboard_carries_bed_workload_my_load_and_the_roster_at_a_glance(): void
    {
        $this->as($this->meiLing)->postJson('/api/nurse/requests', ['type' => 'swap', 'shift_date' => '2026-10-06', 'colleague_id' => $this->aisyah->id, 'colleague_shift' => 'PM'])->assertOk();

        $dashboard = $this->as($this->aisyah)->getJson('/api/nurse/dashboard')->assertOk()->json();

        $beds = collect($dashboard['beds'])->keyBy('number');
        $this->assertSame(2.5, $beds['B1']['workload']['score']);
        $this->assertSame(['Patient', 'Nursing level 3', 'Isolation'], array_column($beds['B1']['workload']['factors'], 'label'));
        $this->assertSame(['score' => 3.5, 'beds' => 2, 'patients' => 2, 'level' => 'heavy', 'team_average' => 2.5, 'team_size' => 2], $dashboard['summary']['my_load']);

        $schedule = $dashboard['schedule'];
        $this->assertSame(['AM', 'PM'], [$schedule['today']['shift'], $schedule['tomorrow']['shift']]);
        $this->assertSame(1, $schedule['swaps_to_answer']);
        $this->assertSame([['start' => '2026-10-05', 'label' => '5 Oct – 11 Oct', 'state' => 'new']], $schedule['to_acknowledge']);
    }
}
