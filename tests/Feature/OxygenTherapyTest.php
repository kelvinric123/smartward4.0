<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\Hospital;
use App\Models\OxygenTherapyChange;
use App\Models\Patient;
use App\Models\User;
use App\Models\VitalSign;
use App\Models\Ward;
use App\Support\OxygenTherapyChart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class OxygenTherapyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Ward $ward;
    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-05 08:00:00'));

        $this->user = User::factory()->create(['name' => 'Nurse Aina']);
        $hospital = Hospital::create(['name' => 'Test Hospital']);
        $this->ward = Ward::create(['hospital_id' => $hospital->id, 'ward_code' => 'MW1', 'ward_name' => 'Medical Ward 1', 'is_active' => true]);
        Bed::create(['ward_id' => $this->ward->id, 'bed_number' => 'B01', 'bed_id' => 'MW1-B01', 'bed_display_name' => 'B01', 'status' => 'available', 'is_active' => true]);

        $this->patient = Patient::create([
            'name' => 'Tan Mei Ling',
            'mrn' => 'MRN900101',
            'rn' => 'RN900101',
            'ic_passport' => '600101-10-4444',
            'age' => 67,
            'gender' => 'Female',
            'phone' => '012-9876543',
            'ward_id' => $this->ward->id,
            'bed_number' => 'B01',
            'status' => 'admitted',
            'is_active' => true,
            'admitted_at' => Carbon::parse('2026-10-05 07:00:00'),
        ]);
    }

    private function change(array $fields = [])
    {
        return $this->actingAs($this->user)->post(route('ward.oxygen-therapy.store'), array_merge([
            'patient_id' => $this->patient->id,
            'active_tab' => 'oxygen',
            '_form' => 'change',
            'oxygen_delivery' => 'nasal_cannula',
            'oxygen_flow_rate' => 2,
        ], $fields));
    }

    private function reading(string $at, array $fields): VitalSign
    {
        return VitalSign::create(array_merge([
            'patient_id' => $this->patient->id,
            'recorded_by' => $this->user->id,
            'reading_type' => 'single',
            'recorded_at' => Carbon::parse($at),
        ], $fields));
    }

    private function chart(): array
    {
        return OxygenTherapyChart::forPatient($this->patient->fresh());
    }

    private function tabUrl(): string
    {
        return route('ward.patient-details', ['patient_id' => $this->patient->id, 'active_tab' => 'oxygen']);
    }

    public function test_oxygen_tab_follows_vital_signs_and_can_be_switched_off_per_user(): void
    {
        $this->actingAs($this->user)->get($this->tabUrl())
            ->assertOk()
            ->assertSeeInOrder(['Vital Signs', 'Oxygen Therapy', 'I/O Chart'])
            ->assertSee('Change to Room Air')
            ->assertSee('Not recorded this admission');

        $this->actingAs($this->user)->get(route('ward.settings'))
            ->assertOk()
            ->assertSee('Oxygen Therapy')
            ->assertSee('Includes the oxygen recorded with vital signs');

        $this->actingAs($this->user)->post(route('ward.settings.update'), [
            'setting_type' => 'patient_details',
            'tabs' => ['info' => 1, 'additional' => 1, 'vitals' => 1],
        ])->assertRedirect();

        $this->actingAs($this->user)->get($this->tabUrl())
            ->assertOk()
            ->assertDontSee('Change to Room Air');
    }

    public function test_changing_oxygen_saves_the_device_its_settings_and_the_spo2_target(): void
    {
        $this->change(['target_spo2_min' => 94, 'target_spo2_max' => 98, 'notes' => 'Desaturated on mobilising'])
            ->assertRedirect($this->tabUrl())
            ->assertSessionHas('success', 'Oxygen changed to Nasal Cannula / Prongs 2 L/min at 08:00.');

        $change = OxygenTherapyChange::sole();
        $this->assertSame('nasal_cannula', $change->oxygen_delivery);
        $this->assertSame('2.0', $change->oxygen_flow_rate);
        $this->assertNull($change->fio2_percent);
        $this->assertSame([94, 98], $change->target());
        $this->assertSame($this->ward->id, $change->ward_id);
        $this->assertSame($this->user->id, $change->recorded_by);

        $chart = $this->chart();
        $this->assertSame('NP 2L', $chart['current']['short']);
        $this->assertSame('therapy', $chart['current']['source']);
        $this->assertSame([94, 98], $chart['current']['target']);
        $this->assertTrue($chart['on_oxygen_since']->equalTo(Carbon::parse('2026-10-05 08:00:00')));

        $this->actingAs($this->user)->get($this->tabUrl())
            ->assertOk()
            ->assertSee('Nasal Cannula / Prongs')
            ->assertSee('2 L/min')
            ->assertSee('94–98%')
            ->assertSee('Desaturated on mobilising');
    }

    public function test_room_air_carries_no_flow_rate_or_fio2(): void
    {
        $this->change(['started_at' => '2026-10-05 07:30']);

        $this->change(['oxygen_delivery' => 'room_air', 'oxygen_flow_rate' => 3, 'fio2_percent' => 40])
            ->assertRedirect($this->tabUrl())
            ->assertSessionHas('success', 'Changed to room air at 08:00.');

        $roomAir = OxygenTherapyChange::latest('id')->first();
        $this->assertSame('room_air', $roomAir->oxygen_delivery);
        $this->assertNull($roomAir->oxygen_flow_rate);
        $this->assertNull($roomAir->fio2_percent);

        $chart = $this->chart();
        $this->assertFalse($chart['current']['on_oxygen']);
        $this->assertSame('No supplemental O₂', $chart['current']['settings']);
        $this->assertNull($chart['on_oxygen_since']);
        // The step chart drops to no added flow at 21%
        $this->assertSame([2.0, 0], array_column($chart['chart']['steps'], 'flow'));
        $this->assertSame([null, 21], array_column($chart['chart']['steps'], 'fio2'));
    }

    public function test_an_unworkable_change_is_not_saved_and_says_why(): void
    {
        $this->change(['oxygen_delivery' => 'venturi_mask', 'oxygen_flow_rate' => null])
            ->assertRedirect($this->tabUrl())
            ->assertSessionHasErrors(['oxygen_flow_rate' => 'Enter the flow rate or the FiO₂ for Venturi Mask.']);

        $this->change(['oxygen_delivery' => 'cpap', 'oxygen_flow_rate' => null, 'fio2_percent' => 15])
            ->assertSessionHasErrors('fio2_percent');

        $this->change(['target_spo2_min' => 94])
            ->assertSessionHasErrors('target_spo2_max');

        $this->change(['target_spo2_min' => 96, 'target_spo2_max' => 92])
            ->assertSessionHasErrors(['target_spo2_max' => 'The top of the SpO₂ target must be above the bottom.']);

        $this->change(['started_at' => '2026-10-04 07:00'])
            ->assertSessionHasErrors(['started_at' => 'A change cannot be timed more than 24 hours back.']);

        $this->change(['oxygen_delivery' => 'oxygen_tent'])
            ->assertSessionHasErrors('oxygen_delivery');

        $this->assertSame(0, OxygenTherapyChange::count());

        // The tab opens again with the reasons listed
        $this->change(['oxygen_delivery' => 'venturi_mask', 'oxygen_flow_rate' => null]);
        $this->actingAs($this->user)->get($this->tabUrl())
            ->assertSee('Not saved')
            ->assertSee('Enter the flow rate or the FiO₂ for Venturi Mask.');
    }

    public function test_saving_the_oxygen_already_in_force_does_not_repeat_it(): void
    {
        $this->change(['target_spo2_min' => 94, 'target_spo2_max' => 98]);

        $this->change(['target_spo2_min' => 94, 'target_spo2_max' => 98])
            ->assertSessionHas('success', 'Oxygen unchanged: still Nasal Cannula / Prongs 2 L/min.');
        $this->assertSame(1, OxygenTherapyChange::count());

        // A new target, or a note, is a change worth keeping
        $this->change(['target_spo2_min' => 88, 'target_spo2_max' => 92]);
        $this->change(['target_spo2_min' => 88, 'target_spo2_max' => 92, 'notes' => 'Reviewed by Dr Lim']);
        $this->assertSame(3, OxygenTherapyChange::count());
    }

    public function test_oxygen_recorded_with_vital_signs_joins_the_timeline_only_when_it_differs(): void
    {
        $this->change(['started_at' => '2026-10-05 07:40', 'target_spo2_min' => 94, 'target_spo2_max' => 98]);

        // The same oxygen: a reading, not a change
        $this->reading('2026-10-05 07:50', ['oxygen_delivery' => 'nasal_cannula', 'oxygen_flow_rate' => 2, 'spo2' => 95]);
        // Turned up at the bedside and recorded with the vitals
        $turnedUp = $this->reading('2026-10-05 08:30', ['oxygen_delivery' => 'nasal_cannula', 'oxygen_flow_rate' => 4, 'spo2' => 91]);
        // No oxygen recorded with this one: SpO2 only
        $this->reading('2026-10-05 09:00', ['spo2' => 93]);

        $this->travelTo(Carbon::parse('2026-10-05 09:05:00'));
        $chart = $this->chart();

        $this->assertCount(2, $chart['timeline']);
        $this->assertSame('reading-' . $turnedUp->id, $chart['current']['key']);
        $this->assertSame('vitals', $chart['current']['source']);
        $this->assertSame('NP 4L', $chart['current']['short']);
        // The target set on the tab stays in force
        $this->assertSame([94, 98], $chart['current']['target']);
        $this->assertSame(50, $chart['timeline'][0]['minutes']);
        $this->assertSame(35, $chart['current']['minutes']);

        $this->assertSame([95, 91, 93], array_column($chart['chart']['spo2'], 'y'));
        $this->assertSame([null, 'below', 'below'], array_column($chart['chart']['spo2'], 'state'));
        $this->assertSame(['NP 2L', 'NP 4L', 'NP 4L'], array_column($chart['chart']['spo2'], 'on'));
        $this->assertSame(93, $chart['latest_spo2']['spo2']);
        $this->assertFalse($chart['latest_spo2']['stale']);

        $this->actingAs($this->user)->get($this->tabUrl())
            ->assertOk()
            ->assertSee('Vital signs (SpO₂ 91%)')
            ->assertSee('SpO₂ below target');
    }

    public function test_spo2_above_target_on_oxygen_is_flagged_until_the_oxygen_changes(): void
    {
        $this->change(['oxygen_flow_rate' => 4, 'started_at' => '2026-10-05 07:30', 'target_spo2_min' => 88, 'target_spo2_max' => 92]);
        $this->reading('2026-10-05 07:45', ['spo2' => 97]);

        $chart = $this->chart();
        $this->assertSame('above', $chart['latest_spo2']['state']);
        $this->assertFalse($chart['latest_spo2']['stale']);

        // Weaned since: the reading says little about the oxygen now
        $this->change(['oxygen_flow_rate' => 1, 'target_spo2_min' => 88, 'target_spo2_max' => 92]);
        $chart = $this->chart();
        $this->assertTrue($chart['latest_spo2']['stale']);

        $this->actingAs($this->user)->get($this->tabUrl())
            ->assertOk()
            ->assertDontSee('SpO₂ above target')
            ->assertSee('Taken before the change at 08:00');
    }

    public function test_striking_out_a_change_puts_back_the_oxygen_before_it(): void
    {
        $this->change(['started_at' => '2026-10-05 07:40']);
        $this->change(['oxygen_delivery' => 'non_rebreather', 'oxygen_flow_rate' => 15]);
        $wrong = OxygenTherapyChange::latest('id')->first();

        $this->actingAs($this->user)->post(route('ward.oxygen-therapy.void', $wrong), [
            'active_tab' => 'oxygen',
            '_form' => 'void-' . $wrong->id,
        ])->assertRedirect($this->tabUrl())->assertSessionHasErrors('void_reason');

        $this->actingAs($this->user)->post(route('ward.oxygen-therapy.void', $wrong), [
            'active_tab' => 'oxygen',
            '_form' => 'void-' . $wrong->id,
            'void_reason' => 'Entered for the wrong patient',
        ])->assertRedirect($this->tabUrl())
            ->assertSessionHas('success', 'Struck out: Non-Rebreather Mask 15 L/min from 08:00.');

        $wrong->refresh();
        $this->assertTrue($wrong->isVoided());
        $this->assertSame($this->user->id, $wrong->voided_by);

        $chart = $this->chart();
        $this->assertSame('NP 2L', $chart['current']['short']);
        $this->assertCount(1, $chart['timeline']);
        $this->assertCount(2, $chart['history']);
        $this->assertTrue($chart['history'][0]['voided']);

        $this->actingAs($this->user)->get($this->tabUrl())
            ->assertOk()
            ->assertSee('Entered for the wrong patient');

        $this->actingAs($this->user)->post(route('ward.oxygen-therapy.void', $wrong), [
            'void_reason' => 'Again',
        ])->assertSessionHas('error', 'That change is already struck out.');
    }

    public function test_oxygen_from_an_earlier_admission_is_left_out(): void
    {
        OxygenTherapyChange::create([
            'patient_id' => $this->patient->id,
            'oxygen_delivery' => 'hfnc',
            'oxygen_flow_rate' => 50,
            'fio2_percent' => 60,
            'started_at' => Carbon::parse('2026-09-20 10:00:00'),
        ]);
        $this->reading('2026-09-21 10:00', ['oxygen_delivery' => 'cpap', 'fio2_percent' => 40, 'spo2' => 92]);

        $chart = $this->chart();
        $this->assertNull($chart['current']);
        $this->assertSame([], $chart['history']);
        $this->assertNull($chart['latest_spo2']);
    }

    public function test_vital_signs_start_new_readings_from_the_oxygen_now(): void
    {
        $this->reading('2026-10-05 07:30', ['oxygen_delivery' => 'room_air', 'spo2' => 98]);
        $this->change(['oxygen_flow_rate' => 3]);

        $this->actingAs($this->user)
            ->get(route('vital-signs.patient', ['patient_id' => $this->patient->id, 'edit' => 1]))
            ->assertOk()
            ->assertSee('oxygenPrefill: {"oxygen_delivery":"nasal_cannula","oxygen_flow_rate":"3","fio2_percent":""}', false)
            ->assertSee('Filled in from the oxygen the patient is on now (NP 3L)')
            // The Oxygen card shows the oxygen now, not just what the last reading recorded
            ->assertSee('3 L/min');
    }

    public function test_vital_sign_oxygen_labels_are_unchanged(): void
    {
        $reading = $this->reading('2026-10-05 07:30', ['oxygen_delivery' => 'venturi_mask', 'fio2_percent' => 28]);

        $this->assertSame('Venturi Mask', $reading->oxygenDeliveryLabel());
        $this->assertSame('VM 28%', $reading->oxygenShortLabel());
        $this->assertSame('FiO₂ 28%', $reading->oxygenSettingsLabel());
        $this->assertTrue($reading->isOnOxygen());
    }
}
