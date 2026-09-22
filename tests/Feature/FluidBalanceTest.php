<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\FluidBalanceEntry;
use App\Models\FluidBalancePlan;
use App\Models\FluidOverloadAssessment;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use App\Models\Ward;
use App\Models\WardDashboardSetting;
use App\Support\FluidBalanceChart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FluidBalanceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Ward $ward;
    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        // The ward has no shift settings, so the default shifts apply: AM 07-14, PM 14-23, ON 23-07
        $this->travelTo(Carbon::parse('2026-09-21 08:00:00'));

        $this->user = User::factory()->create(['name' => 'Nurse Aina']);
        $hospital = Hospital::create(['name' => 'Test Hospital']);
        $this->ward = Ward::create(['hospital_id' => $hospital->id, 'ward_code' => 'MW1', 'ward_name' => 'Medical Ward 1', 'is_active' => true]);
        Bed::create(['ward_id' => $this->ward->id, 'bed_number' => 'B01', 'bed_id' => 'MW1-B01', 'bed_display_name' => 'B01', 'status' => 'available', 'is_active' => true]);

        $this->patient = Patient::create([
            'name' => 'Tan Mei Ling',
            'mrn' => 'MRN900001',
            'rn' => 'RN900001',
            'ic_passport' => '600101-10-4444',
            'age' => 67,
            'gender' => 'Female',
            'phone' => '012-9876543',
            'ward_id' => $this->ward->id,
            'bed_number' => 'B01',
            'status' => 'admitted',
            'is_active' => true,
            'admitted_at' => Carbon::parse('2026-09-21 07:30:00'),
        ]);
    }

    private function record(array $fields = [])
    {
        return $this->actingAs($this->user)->post(route('ward.fluid-balance.store'), array_merge([
            'patient_id' => $this->patient->id,
            'active_tab' => 'io',
            '_form' => 'record',
            'direction' => 'intake',
            'category' => 'oral',
            'volume_ml' => 200,
        ], $fields));
    }

    private function setPlan(array $fields)
    {
        return $this->actingAs($this->user)->post(route('ward.fluid-balance.plan'), array_merge([
            'patient_id' => $this->patient->id,
            'active_tab' => 'io',
            '_form' => 'plan',
        ], $fields));
    }

    private function assess(array $fields)
    {
        return $this->actingAs($this->user)->post(route('ward.fluid-balance.assessments.store'), array_merge([
            'patient_id' => $this->patient->id,
            'active_tab' => 'io',
            '_form' => 'assess',
            'edema_grade' => 0,
        ], $fields));
    }

    private function chart(?string $day = null): array
    {
        return FluidBalanceChart::forPatient($this->patient->fresh(), $day);
    }

    private function alertTitles(array $chart): array
    {
        return array_column($chart['status']['alerts'], 'title');
    }

    private function detailsUrl(array $query = []): string
    {
        return route('ward.patient-details', ['patient_id' => $this->patient->id] + $query);
    }

    public function test_io_chart_tab_follows_vital_signs_and_can_be_switched_off_per_user(): void
    {
        $this->actingAs($this->user)->get($this->detailsUrl())
            ->assertOk()
            ->assertSeeInOrder(['Vital Signs', 'I/O Chart', 'Patient Movement'])
            ->assertSee('Save intake');

        $this->actingAs($this->user)->get(route('ward.settings'))
            ->assertOk()
            ->assertSee('I/O Chart')
            ->assertSee('signs of fluid overload such as edema');

        $this->actingAs($this->user)->post(route('ward.settings.update'), [
            'setting_type' => 'patient_details',
            'tabs' => ['info' => 1, 'additional' => 1, 'vitals' => 1],
        ])->assertSessionHas('settings_tab', 'patient-details');

        $this->assertFalse(WardDashboardSetting::where('user_id', $this->user->id)->first()->patient_details_tabs['io']);
        $this->actingAs($this->user)->get($this->detailsUrl())
            ->assertOk()
            ->assertDontSee('I/O Chart');

        // Settings are per user
        $this->actingAs(User::factory()->create())->get($this->detailsUrl())
            ->assertOk()
            ->assertSee('I/O Chart');
    }

    public function test_intake_and_output_are_totalled_by_shift_over_a_chart_day_from_07_00(): void
    {
        $this->record(['category' => 'oral', 'volume_ml' => 200, 'description' => 'Tea / coffee'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Intake recorded: Oral - Tea / coffee, 200 mL at 08:00.');
        $this->record(['category' => 'iv', 'volume_ml' => 500, 'description' => '0.9% NaCl']);
        $this->record(['direction' => 'output', 'category' => 'urine', 'volume_ml' => 300]);

        $entry = FluidBalanceEntry::first();
        $this->assertSame($this->user->id, $entry->recorded_by);
        $this->assertSame($this->ward->id, $entry->ward_id);
        $this->assertTrue($entry->recorded_at->equalTo(now()));

        $this->travelTo(Carbon::parse('2026-09-21 15:00:00'));
        $this->record(['direction' => 'output', 'category' => 'urine', 'volume_ml' => 250]);

        // 06:30 next morning still belongs to the day that started at 07:00 yesterday
        $this->travelTo(Carbon::parse('2026-09-22 06:30:00'));
        $this->record(['category' => 'oral', 'volume_ml' => 100]);

        $chart = $this->chart();
        $this->assertSame('2026-09-21', $chart['day']['key']);
        $this->assertSame(800, $chart['totals']['intake']);
        $this->assertSame(550, $chart['totals']['output']);
        $this->assertSame(250, $chart['totals']['balance']);
        $this->assertSame(['oral' => 300, 'iv' => 500], $chart['totals']['by_type']['intake']);

        $shifts = collect($chart['shifts'])->keyBy('code');
        $this->assertSame([700, 300, 3], [$shifts['AM']['intake'], $shifts['AM']['output'], count($shifts['AM']['rows'])]);
        $this->assertSame([0, 250], [$shifts['PM']['intake'], $shifts['PM']['output']]);
        $this->assertSame([100, 0], [$shifts['ON']['intake'], $shifts['ON']['output']]);
        $this->assertTrue($shifts['ON']['current']);
        // Running balance through the day
        $this->assertSame([200, 700, 400], array_column($shifts['AM']['rows'], 'running'));

        // At 07:00 a new chart day starts empty, and yesterday moves to the day summary
        $this->travelTo(Carbon::parse('2026-09-22 07:10:00'));
        $chart = $this->chart();
        $this->assertSame('2026-09-22', $chart['day']['key']);
        $this->assertSame(0, $chart['totals']['intake']);
        $this->assertSame('2026-09-21', $chart['day']['previous']);
        $this->assertSame(
            ['2026-09-22' => 0, '2026-09-21' => 250],
            collect($chart['days']['rows'])->pluck('balance', 'key')->all()
        );
        $this->assertSame(250, $chart['days']['stay_balance']);

        $this->actingAs($this->user)->get($this->detailsUrl(['active_tab' => 'io', 'io_day' => '2026-09-21']))
            ->assertOk()
            ->assertSee('"activeTab":"io"', false)
            ->assertSee('Viewing the chart for Mon 21 Sep')
            ->assertSee('Oral - Tea / coffee')
            ->assertSee('IV fluid - 0.9% NaCl')
            ->assertDontSee('Save intake');
    }

    public function test_recent_days_keep_gaps_in_charting_but_not_the_days_before_it_started(): void
    {
        $this->patient->update(['admitted_at' => Carbon::parse('2026-09-14 10:00:00')]);

        $this->travelTo(Carbon::parse('2026-09-18 09:00:00'));
        $this->record(['volume_ml' => 300]);
        $this->travelTo(Carbon::parse('2026-09-21 08:00:00'));
        $this->record(['volume_ml' => 100]);

        $days = $this->chart()['days'];
        $this->assertSame(
            ['2026-09-21' => 100, '2026-09-20' => 0, '2026-09-19' => 0, '2026-09-18' => 300],
            collect($days['rows'])->pluck('intake', 'key')->all()
        );
        $this->assertSame('2026-09-14', $days['stay_since']->toDateString());
        $this->assertSame(400, $days['stay_balance']);
    }

    public function test_an_entry_can_be_timed_up_to_a_day_back_but_not_ahead(): void
    {
        $this->travelTo(Carbon::parse('2026-09-21 07:40:00'));

        // 06:50 (say, fluids given in the emergency department before the 07:30 admission) falls in
        // the chart day that started at 07:00 the day before, so that is the day that opens
        $this->record(['recorded_at' => '2026-09-21T06:50', 'volume_ml' => 150])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('ward.patient-details', [
                'patient_id' => $this->patient->id, 'active_tab' => 'io', 'io_day' => '2026-09-20',
            ]));
        $this->assertSame('2026-09-21 06:50', FluidBalanceEntry::first()->recorded_at->format('Y-m-d H:i'));

        $this->record(['recorded_at' => '2026-09-21T09:00'])
            ->assertSessionHasErrors(['recorded_at' => 'An entry cannot be timed in the future.']);
        $this->record(['recorded_at' => '2026-09-20T07:00'])
            ->assertSessionHasErrors(['recorded_at' => 'An entry cannot be timed more than 24 hours back.']);

        // The type has to fit the direction, and the volume has to be sensible
        $this->record(['direction' => 'output', 'category' => 'oral'])->assertSessionHasErrors('category');
        $this->record(['volume_ml' => 0])->assertSessionHasErrors('volume_ml');
        $this->record(['volume_ml' => 6000])->assertSessionHasErrors('volume_ml');
        $this->assertSame(1, FluidBalanceEntry::count());
    }

    public function test_a_struck_out_entry_stays_on_the_chart_but_no_longer_counts(): void
    {
        $this->record(['volume_ml' => 200]);
        $this->record(['volume_ml' => 2000]);
        $mistake = FluidBalanceEntry::latest('id')->first();

        $this->actingAs($this->user)->post(route('ward.fluid-balance.void', $mistake), ['active_tab' => 'io'])
            ->assertSessionHasErrors(['void_reason' => 'Give a reason for striking out this entry.']);

        $this->actingAs($this->user)
            ->post(route('ward.fluid-balance.void', $mistake), ['active_tab' => 'io', 'void_reason' => 'Wrong volume'])
            ->assertSessionHas('success', 'Struck out: Oral, 2,000 mL at 08:00.');

        $mistake->refresh();
        $this->assertTrue($mistake->isVoided());
        $this->assertSame($this->user->id, $mistake->voided_by);
        $this->assertSame('Wrong volume', $mistake->void_reason);
        $this->assertSame('2026-09-21 08:00', $mistake->recorded_at->format('Y-m-d H:i'));

        $chart = $this->chart();
        $this->assertSame(200, $chart['totals']['intake']);
        $this->assertCount(2, $chart['entries']);

        $this->actingAs($this->user)
            ->post(route('ward.fluid-balance.void', $mistake), ['active_tab' => 'io', 'void_reason' => 'Again'])
            ->assertSessionHas('error', 'That entry is already struck out.');

        $this->actingAs($this->user)->get($this->detailsUrl(['active_tab' => 'io']))
            ->assertSee('Struck out by Nurse Aina: Wrong volume');
    }

    public function test_intake_limit_warns_when_near_and_flags_when_over(): void
    {
        $this->setPlan(['intake_limit_ml' => 1000, 'notes' => 'Fluid restrict 1 L/day'])
            ->assertSessionHas('success', 'Fluid plan set: Intake up to 1,000 mL per day.');

        $this->record(['volume_ml' => 750]);
        $limit = $this->chart()['status']['limit'];
        $this->assertSame(['ok', 75, 250], [$limit['state'], $limit['percent'], $limit['remaining']]);

        $this->record(['volume_ml' => 100]);
        $chart = $this->chart();
        $this->assertSame('near', $chart['status']['limit']['state']);
        $this->assertSame(['Near the intake limit'], $this->alertTitles($chart));
        $this->assertStringContainsString('150 mL left until 07:00', $chart['status']['alerts'][0]['detail']);

        $this->record(['category' => 'iv', 'volume_ml' => 250]);
        $chart = $this->chart();
        $this->assertSame(['over', 100], [$chart['status']['limit']['state'], $chart['status']['limit']['over_by']]);
        $this->assertSame('critical', $chart['status']['alerts'][0]['level']);
        $this->assertSame(
            '1,100 mL taken in against a limit of 1,000 mL, 100 mL over.',
            $chart['status']['alerts'][0]['detail']
        );

        // Output does not count against the intake limit
        $this->record(['direction' => 'output', 'category' => 'urine', 'volume_ml' => 900]);
        $this->assertSame('over', $this->chart()['status']['limit']['state']);

        $this->actingAs($this->user)->get($this->detailsUrl(['active_tab' => 'io']))
            ->assertSee('Over the intake limit')
            ->assertSee('Fluid restrict 1 L/day');
    }

    public function test_urine_target_is_judged_on_the_average_since_admission_after_four_hours(): void
    {
        $this->setPlan(['urine_min_ml_per_hour' => 30]);
        $this->record(['direction' => 'output', 'category' => 'urine', 'volume_ml' => 20]);
        $this->assertSame('pending', $this->chart()['status']['urine']['state']);

        // Admitted 07:30, so by 12:30 the patient has been on the ward 5 hours (not the 5.5 since 07:00)
        $this->travelTo(Carbon::parse('2026-09-21 12:30:00'));
        $this->record(['direction' => 'output', 'category' => 'urine', 'volume_ml' => 80]);
        $chart = $this->chart();
        $this->assertSame(['low', 20], [$chart['status']['urine']['state'], $chart['status']['urine']['average']]);
        $this->assertSame(['Urine output below target'], $this->alertTitles($chart));
        $this->assertStringContainsString('since 07:30', $chart['status']['alerts'][0]['detail']);

        $this->record(['direction' => 'output', 'category' => 'urine', 'volume_ml' => 100]);
        $chart = $this->chart();
        $this->assertSame(['ok', 40], [$chart['status']['urine']['state'], $chart['status']['urine']['average']]);
        $this->assertSame([], $chart['status']['alerts']);
    }

    public function test_plan_changes_keep_their_history_and_a_blank_plan_lifts_the_limits(): void
    {
        $this->setPlan(['intake_limit_ml' => 1500, 'urine_min_ml_per_hour' => 30])
            ->assertSessionHas('success', 'Fluid plan set: Intake up to 1,500 mL per day, urine at least 30 mL/h.');
        $this->setPlan(['intake_limit_ml' => 1500, 'urine_min_ml_per_hour' => 30])
            ->assertSessionHas('success', 'Fluid plan unchanged.');
        $this->assertSame(1, FluidBalancePlan::count());

        $this->travel(2)->hours();
        $this->setPlan(['intake_limit_ml' => 1200, 'urine_min_ml_per_hour' => 30]);
        $this->assertSame(1200, FluidBalancePlan::currentFor($this->patient->fresh())->intake_limit_ml);

        $this->setPlan(['intake_limit_ml' => '', 'urine_min_ml_per_hour' => ''])
            ->assertSessionHas('success', 'Fluid limits lifted.');
        $this->assertFalse(FluidBalancePlan::currentFor($this->patient->fresh())->hasLimits());
        $this->assertCount(3, $this->chart()['plan_history']);
        $this->assertNull($this->chart()['status']['limit']);

        $this->setPlan(['intake_limit_ml' => 50])->assertSessionHasErrors('intake_limit_ml');
        $this->setPlan(['urine_min_ml_per_hour' => 900])->assertSessionHasErrors('urine_min_ml_per_hour');
    }

    public function test_a_plan_from_an_earlier_stay_is_ignored(): void
    {
        $old = FluidBalancePlan::create(['patient_id' => $this->patient->id, 'intake_limit_ml' => 800]);
        $old->forceFill(['created_at' => Carbon::parse('2026-08-01 10:00:00')])->save();

        $this->assertNull(FluidBalancePlan::currentFor($this->patient->fresh()));
        $this->assertNull($this->chart()['status']['limit']);
        $this->assertSame([], FluidBalanceChart::alertsForPatients([$this->patient->fresh()]));
    }

    public function test_overload_check_records_edema_and_signs_and_flags_them(): void
    {
        $this->assess([
            'edema_grade' => 2,
            'edema_sites' => ['legs', 'feet_ankles'],
            'signs' => ['crackles'],
            'weight_kg' => 71.25,
            'notes' => 'Needs 3 pillows',
        ])->assertSessionHas('success', 'Assessment recorded: Edema 2+ (feet / ankles, lower legs), Crackles on the chest, weight 71.3 kg.');

        $assessment = FluidOverloadAssessment::first();
        $this->assertSame(['legs', 'feet_ankles'], $assessment->edema_sites);
        $this->assertSame('71.3', $assessment->weight_kg);
        $this->assertSame($this->user->id, $assessment->recorded_by);

        $chart = $this->chart();
        $this->assertSame(['Signs of fluid overload'], $this->alertTitles($chart));
        $this->assertStringStartsWith(
            'Edema 2+ (feet / ankles, lower legs), Crackles on the chest. Recorded 21 Sep 08:00 by Nurse Aina.',
            $chart['status']['alerts'][0]['detail']
        );

        // Sites only count with edema, and a clear check replaces the flag
        $this->travel(1)->hours();
        $this->assess(['edema_grade' => 0, 'edema_sites' => ['legs']])
            ->assertSessionHas('success', 'Assessment recorded: no signs of fluid overload.');
        $this->assertSame([], FluidOverloadAssessment::latest('id')->first()->edema_sites);
        $this->assertSame([], $this->chart()['status']['alerts']);

        // Pink frothy sputum is escalated at once
        $this->assess(['signs' => ['breathless', 'frothy_sputum']]);
        $chart = $this->chart();
        $this->assertSame(['critical', 'Pink frothy sputum'], [$chart['status']['alerts'][0]['level'], $chart['status']['alerts'][0]['title']]);

        $this->assess(['edema_grade' => 5])->assertSessionHasErrors('edema_grade');
        $this->assess(['signs' => ['hiccups']])->assertSessionHasErrors('signs.0');
    }

    public function test_weight_gain_of_two_kg_within_three_days_is_flagged(): void
    {
        $this->assess(['weight_kg' => 70.0]);

        $this->travelTo(Carbon::parse('2026-09-22 08:00:00'));
        $this->assess(['weight_kg' => 71.2]);
        $weight = $this->chart()['weight'];
        $this->assertSame([71.2, 1.2, null], [$weight['kg'], $weight['change'], $weight['gain']]);

        $this->travelTo(Carbon::parse('2026-09-23 08:00:00'));
        $this->assess(['weight_kg' => 72.4]);
        $chart = $this->chart();
        $this->assertSame([1.2, 2.4], [$chart['weight']['change'], $chart['weight']['gain']]);
        $this->assertSame(['Weight up 2.4 kg'], $this->alertTitles($chart));
        $this->assertStringStartsWith('70.0 kg on 21 Sep to 72.4 kg on 23 Sep.', $chart['status']['alerts'][0]['detail']);

        // Four days on, the first weight is outside the window
        $this->travelTo(Carbon::parse('2026-09-25 09:00:00'));
        $this->assess(['weight_kg' => 72.0]);
        $this->assertNull($this->chart()['weight']['gain']);
    }

    public function test_every_io_action_comes_back_to_the_io_tab(): void
    {
        $this->record();
        $entry = FluidBalanceEntry::first();

        // The page was last loaded by another tab's save, so its URL still names that tab
        $page = $this->detailsUrl(['active_tab' => 'additional']);
        $submit = fn (string $route, $target, array $fields) => $this->actingAs($this->user)
            ->from($page)
            ->followingRedirects()
            ->post(route($route, $target), $fields + ['active_tab' => 'io'])
            ->assertOk()
            ->assertSee('"activeTab":"io"', false);

        $submit('ward.fluid-balance.store', [], [
            'patient_id' => $this->patient->id, '_form' => 'record', 'direction' => 'output', 'category' => 'drain',
            'volume_ml' => 120, 'description' => 'Chest drain',
        ])->assertSee('Output recorded: Drain - Chest drain, 120 mL at 08:00.');
        $submit('ward.fluid-balance.store', [], ['patient_id' => $this->patient->id, '_form' => 'record', 'direction' => 'intake'])
            ->assertSee('Not saved')
            ->assertSee('Enter the volume in mL.');

        $submit('ward.fluid-balance.void', $entry, ['_form' => 'void-' . $entry->id, 'void_reason' => 'Recorded twice'])
            ->assertSee('Struck out: Oral, 200 mL at 08:00.');
        $submit('ward.fluid-balance.plan', [], ['patient_id' => $this->patient->id, '_form' => 'plan', 'intake_limit_ml' => 1500])
            ->assertSee('Fluid plan set: Intake up to 1,500 mL per day.');
        $submit('ward.fluid-balance.assessments.store', [], [
            'patient_id' => $this->patient->id, '_form' => 'assess', 'edema_grade' => 1, 'edema_sites' => ['sacral'],
        ])->assertSee('Assessment recorded: Edema 1+ (sacrum).');
    }

    public function test_dashboard_bed_box_opens_the_io_chart_and_flags_patients_over_their_limit(): void
    {
        $dashboard = route('ward.dashboard', ['ward_id' => $this->ward->id]);
        $button = "tab: 'io'";

        $this->actingAs($this->user)->get($dashboard)
            ->assertOk()
            ->assertSee($button, false)
            ->assertDontSee('Over the intake limit');

        $this->setPlan(['intake_limit_ml' => 1000]);
        $this->record(['volume_ml' => 1200]);
        $this->assess(['edema_grade' => 3, 'edema_sites' => ['feet_ankles']]);

        $alerts = FluidBalanceChart::alertsForPatients([$this->patient->fresh()]);
        $this->assertSame('critical', $alerts[$this->patient->id]['level']);
        $this->assertSame(1200, $alerts[$this->patient->id]['intake']);

        $this->actingAs($this->user)->get($dashboard)
            ->assertOk()
            ->assertSee('I/O Chart - in 1,200 of 1,000 mL, out 0 mL today - Over the intake limit - Signs of fluid overload', false)
            ->assertSee('bg-red-500 animate-pulse', false);

        // Switched off in Settings: no button
        WardDashboardSetting::updateOrCreate(['user_id' => $this->user->id], ['patient_details_tabs' => ['io' => false]]);
        $this->actingAs($this->user)->get($dashboard)
            ->assertOk()
            ->assertDontSee($button, false);
    }
}
