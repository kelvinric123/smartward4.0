<?php

namespace Tests\Feature;

use App\Models\AdmissionLog;
use App\Models\AmbulanceCall;
use App\Models\Bed;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\PatientCareProvider;
use App\Models\User;
use App\Models\Ward;
use App\Models\WardType;
use App\Services\CommandCenterEd;
use App\Services\CommandCenterSummary;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Command Center V2 (ED): the emergency department's board, over the wards of
 * an Emergency ward type (the ED's zones), which the hospital-wide V2 leaves out.
 */
class CommandCenterEdTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Hospital $hospital;
    private WardType $edType;
    private Ward $red;
    private Ward $yellow;
    private Ward $waiting;
    private Ward $medical;
    private int $n = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-09-29 14:00:00'));

        $this->admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $this->hospital = Hospital::create(['name' => 'Test Hospital']);

        $this->edType = WardType::create(['code' => 'ED', 'name' => 'Emergency', 'is_emergency' => true, 'is_active' => true]);
        $general = WardType::create(['code' => 'GEN', 'name' => 'General', 'is_active' => true]);

        $this->red = $this->ward('EDR', 'ED - RED ZONE', $this->edType, ['RED01', 'RED02']);
        $this->yellow = $this->ward('EDY', 'ED - YELLOW ZONE', $this->edType, ['YELLOW01', 'YELLOW02', 'YELLOW03']);
        $this->waiting = $this->ward('EDW', 'ED - WAITING AREA', $this->edType, ['SEAT01', 'SEAT02', 'SEAT03', 'SEAT04']);
        $this->medical = $this->ward('MW1', 'Medical Ward 1', $general, ['MW01', 'MW02']);
    }

    private function ward(string $code, string $name, WardType $type, array $beds): Ward
    {
        $ward = Ward::create(['hospital_id' => $this->hospital->id, 'ward_code' => $code, 'ward_name' => $name, 'ward_type_id' => $type->id, 'is_active' => true]);
        foreach ($beds as $bed) {
            Bed::create(['ward_id' => $ward->id, 'bed_number' => $bed, 'bed_id' => "{$code}-{$bed}", 'bed_display_name' => $bed, 'status' => 'available', 'is_active' => true]);
        }

        return $ward;
    }

    private function arrive(Ward $ward, string $bed, int $minutesAgo, ?string $doctor = null, string $name = 'Siti Binti Ali'): Patient
    {
        $this->n++;
        $patient = Patient::create([
            'name' => $name, 'mrn' => 'MRN9000' . $this->n, 'rn' => 'RN9000' . $this->n, 'ic_passport' => 'IC9000' . $this->n,
            'gender' => 'Female', 'phone' => '', 'ward_id' => $ward->id, 'bed_number' => $bed,
            'status' => Patient::STATUS_ADMITTED, 'is_active' => true, 'admitted_at' => now()->subMinutes($minutesAgo),
        ]);
        AdmissionLog::create([
            'patient_id' => $patient->id, 'ward_id' => $ward->id, 'bed_number' => $bed, 'action' => 'admit',
            'patient_name' => $name, 'mrn' => $patient->mrn, 'admitted_at' => $patient->admitted_at, 'source' => 'cplus',
        ]);
        if ($doctor) {
            PatientCareProvider::create([
                'patient_id' => $patient->id, 'role' => PatientCareProvider::ROLE_ATTENDING, 'doctor_code' => strtoupper($doctor),
                'doctor_name' => $doctor, 'source' => PatientCareProvider::SOURCE_CPLUS, 'assigned_at' => now(), 'is_active' => true,
            ]);
        }

        return $patient;
    }

    /** A patient who arrived and has since left the ED: home, or on to a ward. */
    private function left(Ward $zone, Carbon $arrived, Carbon $left, ?Ward $toWard = null): void
    {
        $this->n++;
        $patient = Patient::create([
            'name' => 'Tan Ah Kow', 'mrn' => 'MRN9100' . $this->n, 'rn' => 'RN9100' . $this->n, 'ic_passport' => 'IC9100' . $this->n,
            'gender' => 'Male', 'phone' => '', 'status' => Patient::STATUS_DISCHARGED, 'is_active' => false, 'discharged_at' => $left,
        ]);
        $log = ['patient_id' => $patient->id, 'ward_id' => $zone->id, 'bed_number' => 'SEAT01', 'patient_name' => 'Tan Ah Kow', 'mrn' => $patient->mrn, 'source' => 'cplus'];
        AdmissionLog::create($log + ['action' => 'admit', 'admitted_at' => $arrived]);
        AdmissionLog::create($log + ['action' => 'discharge', 'discharged_at' => $left, 'to_ward_id' => $toWard?->id]);
    }

    private function board(): array
    {
        return CommandCenterEd::build($this->hospital->fresh());
    }

    public function test_only_command_center_viewers_see_it(): void
    {
        $nurse = User::factory()->create(['role' => User::ROLE_NURSE]);
        $this->actingAs($nurse)->get(route('command-center-ed.index'))->assertForbidden();
        $this->actingAs($nurse)->getJson(route('command-center-ed.data'))->assertForbidden();
        $this->actingAs($nurse)->postJson(route('command-center-ed.settings'), ['target_minutes' => 180])->assertForbidden();

        $this->actingAs($this->admin)->get(route('command-center-ed.index'))
            ->assertOk()
            ->assertSee('Command Center V2 (ED)')
            ->assertSee('data-url="' . route('command-center-ed.data') . '"', false)
            ->assertSee('id="cced-snapshot"', false)
            ->assertSee("SETTINGS_KEY = 'ccedDisplay'", false);
        $this->actingAs($this->admin)->getJson(route('command-center-ed.data'))->assertOk()->assertJsonPath('targets.minutes', 120);
    }

    public function test_a_ward_type_is_marked_emergency_on_the_ward_types_page(): void
    {
        $this->actingAs($this->admin)->post(route('ward-types.store'), [
            'code' => 'edz', 'name' => 'ED Zone', 'is_emergency' => '1',
        ])->assertRedirect(route('ward-types.index'));
        $type = WardType::where('code', 'EDZ')->firstOrFail();
        $this->assertTrue($type->is_emergency);

        $this->actingAs($this->admin)->put(route('ward-types.update', $type), ['code' => 'EDZ', 'name' => 'ED Zone', 'is_emergency' => '0']);
        $this->assertFalse($type->fresh()->is_emergency);

        // A form without the field keeps the saved choice; a new type defaults to No
        $type->refresh()->update(['is_emergency' => true]);
        $this->actingAs($this->admin)->put(route('ward-types.update', $type), ['code' => 'EDZ', 'name' => 'ED Zone']);
        $this->assertTrue($type->fresh()->is_emergency);

        $edit = $this->actingAs($this->admin)->get(route('ward-types.edit', $type))->assertOk()->assertSee('Emergency Ward');
        $this->assertMatchesRegularExpression('/name="is_emergency" value="1"\s+checked/', $edit->getContent());
        $create = $this->actingAs($this->admin)->get(route('ward-types.create'))->assertOk()->assertSee('Emergency Ward');
        $this->assertMatchesRegularExpression('/name="is_emergency" value="0"\s+checked/', $create->getContent());
        $this->actingAs($this->admin)->get(route('ward-types.index'))->assertOk()->assertSee('Wards of this type are on Command Center V2 (ED)');
        $this->actingAs($this->admin)->get(route('wards.index'))->assertOk()->assertSee('Counted on Command Center V2 (ED)');
    }

    public function test_the_hospital_wide_v2_leaves_the_ed_zones_out_and_the_ed_board_has_only_them(): void
    {
        $v2 = collect(CommandCenterSummary::build()['wards'])->pluck('id')->all();
        $this->assertSame([$this->medical->id], $v2);

        $ed = collect($this->board()['zones'])->pluck('id')->sort()->values()->all();
        $this->assertSame(collect([$this->red->id, $this->yellow->id, $this->waiting->id])->sort()->values()->all(), $ed);
    }

    public function test_zones_count_beds_and_patients_and_time_in_the_ed_against_the_target(): void
    {
        $this->arrive($this->red, 'RED01', 30);
        $this->arrive($this->yellow, 'YELLOW01', 100);
        $this->arrive($this->yellow, 'YELLOW02', 150);
        $this->arrive($this->waiting, 'SEAT01', 300);
        Bed::where('bed_number', 'SEAT04')->update(['status' => Bed::STATUS_MAINTENANCE]);

        $board = $this->board();
        $now = $board['now'];
        $this->assertSame(4, $now['patients']);
        $this->assertSame(9, $now['beds']);
        $this->assertSame(1, $now['waiting']);
        $this->assertSame(2, $now['within_target']);
        $this->assertEquals(50, $now['within_pct']);
        $this->assertSame(2, $now['over_target']);
        $this->assertSame(1, $now['over_double']);
        $this->assertSame(125, $now['median']);
        $this->assertSame(300, $now['longest']);
        $this->assertSame('Waiting Area', $now['longest_zone']);
        $this->assertSame('serious', $now['time_status']);

        $yellow = collect($board['zones'])->firstWhere('id', $this->yellow->id);
        $this->assertSame('Yellow Zone', $yellow['short']);
        $this->assertSame('yellow', $yellow['colour']);
        $this->assertSame([2, 3, 1, 1], [$yellow['occupied'], $yellow['beds'], $yellow['free'], $yellow['over_target']]);
        $this->assertSame(125, $yellow['median']);
        $this->assertSame('warning', $yellow['status']);
        $waiting = collect($board['zones'])->firstWhere('id', $this->waiting->id);
        $this->assertSame([1, 1, 2], [$waiting['occupied'], $waiting['closed'], $waiting['free']]);

        $this->assertSame([1, 1, 1, 1, 0], collect($board['buckets'])->pluck('count')->all());
        $this->assertSame([false, false, true, true, true], collect($board['buckets'])->pluck('over_target')->all());
    }

    public function test_flow_splits_those_who_left_into_admitted_and_home_with_their_time_in_the_ed(): void
    {
        $this->arrive($this->red, 'RED01', 30);
        $this->left($this->yellow, now()->subHours(5), now()->subHours(2), $this->medical);    // 3 h, then admitted
        $this->left($this->waiting, now()->subHours(3), now()->subHours(2)->subMinutes(30));   // 30 min, home
        $this->left($this->waiting, now()->subDay()->subHours(3), now()->subDay()->subHour()); // yesterday, by this time

        $flow = $this->board()['flow'];
        $this->assertSame(3, $flow['arrivals_today']);
        $this->assertSame(1, $flow['arrivals_same_time_yesterday']);
        $this->assertSame(2, $flow['departures_today']);
        $this->assertSame(1, $flow['departures_same_time_yesterday']);
        $this->assertSame([1, 1], [$flow['admitted'], $flow['home']]);
        $this->assertEquals(50, $flow['admission_rate']);
        $this->assertSame(105, $flow['stay_median']);
        $this->assertSame(1, $flow['left_within_target']);

        $trend = $this->board()['trend'];
        $this->assertSame(14, $trend['hour_now']);
        $this->assertSame(1, $trend['hourly_today'][13]);
        $this->assertSame([1, 1, 3], [end($trend['admitted']), end($trend['home']), end($trend['arrivals'])]);
    }

    public function test_doctors_are_counted_by_each_patients_attending_doctor(): void
    {
        $this->arrive($this->red, 'RED01', 30, 'DR LEE');
        $this->arrive($this->yellow, 'YELLOW01', 200, 'DR LEE');
        $this->arrive($this->yellow, 'YELLOW02', 20, 'DR TAN');
        $this->arrive($this->waiting, 'SEAT01', 10);

        $doctors = $this->board()['doctors'];
        $this->assertSame(2, $doctors['count']);
        $this->assertEquals(1.5, $doctors['per_doctor']);
        $this->assertSame(1, $doctors['unassigned']);
        $this->assertSame(['name' => 'DR LEE', 'patients' => 2, 'over_target' => 1], $doctors['rows'][0]);
    }

    public function test_attention_lists_those_over_the_target_longest_first_and_full_zones_without_names(): void
    {
        $this->arrive($this->red, 'RED01', 400, null, 'Nurul Huda');
        $this->arrive($this->red, 'RED02', 30, null, 'Nurul Huda');
        $this->arrive($this->yellow, 'YELLOW01', 150, 'DR LEE', 'Nurul Huda');

        $board = $this->board();
        $this->assertSame(['patient', 'zone', 'patient'], collect($board['attention'])->map(fn ($item) => explode('.', $item['key'])[0])->all());
        [$longest, $full, $over] = $board['attention'];
        $this->assertSame('critical', $longest['severity']);
        $this->assertSame('Red Zone · RED01', $longest['title']);
        $this->assertSame('Red Zone is full', $full['title']);
        $this->assertSame('serious', $full['severity']);
        $this->assertSame('warning', $over['severity']);
        $this->assertStringContainsString('DR LEE', $over['detail']);

        $page = $this->actingAs($this->admin)->get(route('command-center-ed.index'))->assertOk();
        $this->assertStringNotContainsString('Nurul Huda', $page->getContent());
        $this->assertStringNotContainsString('Nurul Huda', json_encode($board));

        // The arrival time in the detail, and the instant, for the board's own time format
        $this->assertSame('07:20', $longest['since_label']);
        $this->assertStringContainsString('since 07:20', $longest['detail']);
        $this->assertTrue(Carbon::parse($longest['since'])->equalTo(now()->subMinutes(400)));
        $this->assertNull($full['since']);
    }

    public function test_proceed_for_admission_counts_todays_decisions_and_those_waiting_in_the_ed_for_a_bed(): void
    {
        $this->arrive($this->red, 'RED01', 200)->update(['proceed_admission_at' => now()->subMinutes(60)]);
        $this->arrive($this->yellow, 'YELLOW01', 100)->update(['proceed_admission_at' => now()->subMinutes(30)]);
        // Decided during an earlier stay: neither today's nor waiting now
        $this->arrive($this->yellow, 'YELLOW02', 40)->update(['proceed_admission_at' => now()->subDays(3)]);
        $this->arrive($this->waiting, 'SEAT01', 20);
        // Decided this morning and gone on to the ward since
        $this->arrive($this->medical, 'MW01', 120)->update(['proceed_admission_at' => now()->subMinutes(180)]);
        // Yesterday, by this time and after it
        $this->arrive($this->medical, 'MW02', 2000)->update(['proceed_admission_at' => now()->subDay()->subHours(4)]);
        $this->left($this->waiting, now()->subDay()->subHours(2), now()->subDay()->addHour());
        Patient::latest('id')->first()->update(['proceed_admission_at' => now()->subDay()->addMinutes(30)]);

        $admission = $this->board()['admission'];
        $this->assertFalse($admission['demo']);
        $this->assertSame(3, $admission['today']);
        $this->assertSame(1, $admission['same_time_yesterday']);
        $this->assertSame(2, $admission['waiting']);
        $this->assertSame(45, $admission['waiting_median']);
        $this->assertSame(60, $admission['waiting_longest']);

        $demo = $this->board()['admission_demo'];
        $this->assertTrue($demo['demo']);
        $this->assertSame($demo, CommandCenterEd::proceedForAdmissionDemo(now()));
        $this->assertSame($demo['today'], CommandCenterEd::proceedForAdmissionDemo(now()->addDay())['same_time_yesterday']);
        $this->assertGreaterThanOrEqual($demo['today'], CommandCenterEd::proceedForAdmissionDemo(now()->endOfDay())['today']);
    }

    public function test_ambulance_cases_today_count_the_calls_the_cases_attended_and_the_response_times(): void
    {
        $call = fn (string $called, ?string $arrived = null, ?string $cancelled = null) => AmbulanceCall::create([
            'called_at' => Carbon::parse($called), 'arrived_at' => $arrived ? Carbon::parse($arrived) : null,
            'cancelled_at' => $cancelled ? Carbon::parse($cancelled) : null,
        ]);
        $call('2026-09-29 08:00', '2026-09-29 08:06');          // 6 min
        $call('2026-09-29 10:00', '2026-09-29 10:20');          // 20 min
        $call('2026-09-29 13:00', '2026-09-29 13:10');          // 10 min
        $call('2026-09-29 12:00', null, '2026-09-29 12:05');    // called off
        $call('2026-09-29 13:50');                              // the crew on the way
        $call('2026-09-29 13:55', '2026-09-29 14:05');          // not there yet at 14:00
        $call('2026-09-29 15:00', '2026-09-29 15:10');          // later than now
        $call('2026-09-28 11:00', '2026-09-28 11:12');          // yesterday, by this time
        $call('2026-09-28 16:00', '2026-09-28 16:12');          // yesterday, after it

        $ambulance = $this->board()['ambulance'];
        $this->assertFalse($ambulance['demo']);
        $this->assertSame(6, $ambulance['calls']);
        $this->assertSame(1, $ambulance['calls_same_time_yesterday']);
        $this->assertSame(3, $ambulance['attended']);
        $this->assertEquals(50, $ambulance['attended_pct']);
        $this->assertSame([2, 1], [$ambulance['open'], $ambulance['cancelled']]);
        $this->assertSame(10, $ambulance['response_median']);
        $this->assertEquals(12, $ambulance['response_average']);
        $this->assertSame(20, $ambulance['response_longest']);

        // The Demo data mode is the board's own choice: its made-up figures leave the calls alone
        $this->assertTrue($this->board()['ambulance_demo']['demo']);
        $this->assertSame(9, AmbulanceCall::count());
    }

    public function test_demo_ambulance_figures_are_steady_and_add_up_through_the_day(): void
    {
        $afternoon = CommandCenterEd::ambulanceDemo(Carbon::parse('2026-09-29 14:00'));
        $this->assertSame($afternoon, CommandCenterEd::ambulanceDemo(Carbon::parse('2026-09-29 14:00')));
        $this->assertTrue($afternoon['demo']);
        $this->assertSame($afternoon['calls'], $afternoon['attended'] + $afternoon['open'] + $afternoon['cancelled']);
        $this->assertGreaterThan(0, $afternoon['calls']);
        $this->assertGreaterThanOrEqual(7, $afternoon['response_median']);

        $night = CommandCenterEd::ambulanceDemo(Carbon::parse('2026-09-29 23:59:59'));
        $this->assertGreaterThanOrEqual($afternoon['calls'], $night['calls']);
        $this->assertSame(0, CommandCenterEd::ambulanceDemo(Carbon::parse('2026-09-29 00:00:00'))['calls']);
        $this->assertSame($afternoon['calls'], CommandCenterEd::ambulanceDemo(Carbon::parse('2026-09-30 14:00'))['calls_same_time_yesterday']);

        // Without ambulance calls the live card counts none
        $this->assertSame(0, $this->board()['ambulance']['calls']);
        $this->assertNull($this->board()['ambulance']['response_median']);
    }

    public function test_the_settings_offer_the_cards_the_time_format_and_the_data_mode(): void
    {
        $this->actingAs($this->admin)->get(route('command-center-ed.index'))
            ->assertOk()
            ->assertSee('Ambulance cases today')
            ->assertSee('Time format')
            ->assertSee('12-hour (AM/PM)')
            ->assertSee('Data mode')
            ->assertSee('Proceed for admission')
            ->assertSee("{ key: 'doctors', label: 'Doctors' }", false);

        $this->actingAs($this->admin)->get(route('command-center-v2.index'))
            ->assertOk()
            ->assertSee('Time format')
            ->assertSee('12-hour (AM/PM)')
            ->assertDontSee('Data mode');
    }

    public function test_the_target_is_set_in_settings_for_every_screen(): void
    {
        $this->arrive($this->yellow, 'YELLOW01', 150);
        $this->assertSame(1, $this->board()['now']['over_target']);

        $this->actingAs($this->admin)->postJson(route('command-center-ed.settings'), ['target_minutes' => 180])
            ->assertOk()
            ->assertJsonPath('targets.minutes', 180)
            ->assertJsonPath('now.over_target', 0);
        $this->assertSame(180, $this->hospital->fresh()->ed_target_minutes);

        $this->actingAs($this->admin)->postJson(route('command-center-ed.settings'), ['target_minutes' => 7])->assertStatus(422);
        $this->assertSame(180, $this->hospital->fresh()->ed_target_minutes);
    }

    public function test_without_emergency_wards_the_board_says_how_to_add_them(): void
    {
        Ward::whereIn('id', [$this->red->id, $this->yellow->id, $this->waiting->id])->update(['ward_type_id' => null]);

        $board = $this->board();
        $this->assertSame([], $board['zones']);
        $this->assertSame(0, $board['now']['patients']);
        $this->assertNull($board['now']['within_pct']);
        $this->actingAs($this->admin)->get(route('command-center-ed.index'))->assertOk()->assertSee('No Emergency wards yet');
    }

    public function test_the_sidebar_links_it_and_a_hospital_can_hide_it(): void
    {
        $link = 'href="' . route('command-center-ed.index') . '"';
        $this->actingAs($this->admin)->get(route('command-center.index'))->assertSee($link, false)->assertSee('Command Center V2 (ED)');

        $this->hospital->update(['hidden_nav_items' => ['command-center-ed']]);
        $this->actingAs($this->admin)->get(route('command-center.index'))
            ->assertDontSee($link, false)
            ->assertSee('href="' . route('command-center-v2.index') . '"', false);
    }
}
