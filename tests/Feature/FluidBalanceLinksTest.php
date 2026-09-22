<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\BloodTransfusion;
use App\Models\Consultant;
use App\Models\ConsultantOrder;
use App\Models\FluidBalanceEntry;
use App\Models\FluidBalancePlan;
use App\Models\Hospital;
use App\Models\Infusion;
use App\Models\Patient;
use App\Models\PatientMedication;
use App\Models\User;
use App\Models\Ward;
use App\Models\WardDashboardSetting;
use App\Services\FluidBalanceLinks;
use App\Support\FluidBalanceChart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FluidBalanceLinksTest extends TestCase
{
    use RefreshDatabase;

    private const PASSPHRASE = 'askdrtai';

    private User $user;
    private Ward $ward;
    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        // No shift settings, so the chart day runs 07:00 to 07:00
        $this->travelTo(Carbon::parse('2026-09-22 08:00:00'));

        $this->user = User::factory()->create(['name' => 'Nurse Aina']);
        $hospital = Hospital::create(['name' => 'Test Hospital']);
        $this->ward = Ward::create(['hospital_id' => $hospital->id, 'ward_code' => 'MW1', 'ward_name' => 'Medical Ward 1', 'is_active' => true]);
        Bed::create(['ward_id' => $this->ward->id, 'bed_number' => 'B01', 'bed_id' => 'MW1-B01', 'bed_display_name' => 'B01', 'status' => 'available', 'is_active' => true]);

        $this->patient = Patient::create([
            'name' => 'Tan Mei Ling', 'mrn' => 'MRN910001', 'rn' => 'RN910001', 'ic_passport' => '600101-10-5555',
            'age' => 67, 'gender' => 'Female', 'phone' => '012-9876543',
            'ward_id' => $this->ward->id, 'bed_number' => 'B01', 'status' => 'admitted', 'is_active' => true,
            'admitted_at' => Carbon::parse('2026-09-22 07:30:00'),
        ]);
    }

    private function entries(?string $source = null)
    {
        return FluidBalanceEntry::where('patient_id', $this->patient->id)
            ->when($source, fn ($query) => $query->where('source', $source))
            ->orderBy('id')
            ->get();
    }

    private function unit(array $overrides = []): BloodTransfusion
    {
        return BloodTransfusion::create($overrides + [
            'patient_id' => $this->patient->id, 'ward_id' => $this->ward->id,
            'unit_number' => 'U123456', 'product_type' => 'Packed Red Cells', 'volume_ml' => 300, 'prescribed_minutes' => 120,
            'status' => BloodTransfusion::STATUS_IN_PROGRESS, 'started_at' => now(), 'checked_by' => $this->user->id,
        ]);
    }

    private function medication(array $overrides = []): PatientMedication
    {
        return PatientMedication::create($overrides + [
            'patient_id' => $this->patient->id, 'ward_id' => $this->ward->id, 'medication_name' => 'Ceftriaxone',
            'dose_amount' => 1, 'dose_unit' => 'g', 'route' => 'IV', 'infusion_volume_ml' => 100,
            'frequency' => 'tds', 'interval_minutes' => 480, 'status' => PatientMedication::STATUS_ACTIVE,
            'start_at' => now(), 'next_due_at' => now(),
        ]);
    }

    private function consultant(): Consultant
    {
        return Consultant::create(['name' => 'Dr. Tan Wei Liang', 'personnel_code' => 'C100', 'registration_number' => 'MMC-C100', 'is_active' => true]);
    }

    public function test_a_finished_blood_unit_is_charted_once(): void
    {
        $unit = $this->unit();
        $this->assertCount(0, $this->entries());

        $this->travel(2)->hours();
        $unit->update(['status' => BloodTransfusion::STATUS_COMPLETED, 'completed_at' => now()]);

        $entry = $this->entries(FluidBalanceLinks::SOURCE_TRANSFUSION)->sole();
        $this->assertSame('intake', $entry->direction);
        $this->assertSame('blood', $entry->category);
        $this->assertSame(300, $entry->volume_ml);
        $this->assertSame('Packed Red Cells, unit U123456', $entry->description);
        $this->assertSame($unit->id, $entry->source_id);
        $this->assertSame($this->user->id, $entry->recorded_by);
        $this->assertSame('2026-09-22 10:00', $entry->recorded_at->format('Y-m-d H:i'));
        $this->assertTrue($entry->isAuto());
        $this->assertSame('blood unit', $entry->sourceLabel());

        // Saving the finished unit again changes nothing, and nor does striking its entry out
        $unit->update(['notes' => 'Observations stable']);
        $entry->update(['voided_at' => now(), 'voided_by' => $this->user->id, 'void_reason' => 'Already charted by hand']);
        $unit->update(['notes' => 'Observations stable after 1 h']);
        $this->assertCount(1, $this->entries());
    }

    public function test_a_unit_stopped_early_charts_an_estimate_and_says_so(): void
    {
        $unit = $this->unit();
        $this->travel(45)->minutes();
        $unit->update(['status' => BloodTransfusion::STATUS_STOPPED, 'completed_at' => now(), 'stop_reason' => 'Rigors']);

        $entry = $this->entries(FluidBalanceLinks::SOURCE_TRANSFUSION)->sole();
        // 45 of 120 minutes of a 300 mL unit
        $this->assertSame(113, $entry->volume_ml);
        $this->assertStringContainsString('stopped early: about 113 mL, estimated from 45 of 120 min', $entry->description);
    }

    public function test_an_iv_dose_with_a_volume_is_charted_and_struck_out_when_undone(): void
    {
        $order = $this->medication();
        $dose = $order->record('given', now(), null, $this->user->id);

        $entry = $this->entries(FluidBalanceLinks::SOURCE_DOSE)->sole();
        $this->assertSame('iv_med', $entry->category);
        $this->assertSame(100, $entry->volume_ml);
        $this->assertSame('Ceftriaxone 1 g IV in 100 mL', $entry->description);
        $this->assertSame($dose->id, $entry->source_id);
        $this->assertSame($this->user->id, $entry->recorded_by);

        // Undoing the dose on the Medications tab strikes its entry out
        $this->actingAs($this->user)->post(route('ward.medications.undo', $dose), ['delete_passphrase' => self::PASSPHRASE]);
        $entry->refresh();
        $this->assertTrue($entry->isVoided());
        $this->assertSame('The dose record was undone', $entry->void_reason);
        $this->assertSame(0, FluidBalanceChart::forPatient($this->patient)['totals']['intake']);
    }

    public function test_only_doses_given_by_vein_with_a_known_volume_are_charted(): void
    {
        // Held: nothing went in
        $this->medication()->record('held', now(), 'Patient NBM', $this->user->id);
        // Oral, even in mL
        $this->medication(['medication_name' => 'Lactulose', 'dose_amount' => 15, 'dose_unit' => 'mL', 'route' => 'PO', 'infusion_volume_ml' => null])
            ->record('given', now(), null, $this->user->id);
        // IV in mg with no volume set
        $this->medication(['medication_name' => 'Pantoprazole', 'dose_amount' => 40, 'dose_unit' => 'mg', 'infusion_volume_ml' => null])
            ->record('given', now(), null, $this->user->id);
        $this->assertCount(0, $this->entries());

        // IV written in mL counts as its own volume
        $this->medication(['medication_name' => 'Paracetamol', 'dose_amount' => 100, 'dose_unit' => 'mL', 'infusion_volume_ml' => null])
            ->record('given', now(), null, $this->user->id);
        $this->assertSame([100], $this->entries(FluidBalanceLinks::SOURCE_DOSE)->pluck('volume_ml')->all());
    }

    public function test_pump_infusions_are_charted_hourly_and_when_they_end(): void
    {
        $infusion = Infusion::create([
            'patient_id' => $this->patient->id, 'medication_name' => '0.9% NaCl', 'total_volume' => 1000,
            'infused_volume' => 0, 'flow_rate' => 125, 'status' => Infusion::STATUS_RUNNING, 'started_at' => now(),
        ]);
        $this->assertCount(0, $this->entries());

        $infusion->update(['infused_volume' => 125]);
        $this->travel(30)->minutes();
        $infusion->update(['infused_volume' => 190]); // not an hour since the last entry yet
        $this->travel(31)->minutes();
        $infusion->update(['infused_volume' => 250]);
        $infusion->update(['infused_volume' => 270, 'status' => Infusion::STATUS_COMPLETED, 'completed_at' => now()]);

        $entries = $this->entries(FluidBalanceLinks::SOURCE_INFUSION);
        $this->assertSame([125, 125, 20], $entries->pluck('volume_ml')->all());
        $this->assertSame('iv', $entries->first()->category);
        $this->assertSame('Pump: 0.9% NaCl', $entries->first()->description);
        $this->assertSame('Pump: 0.9% NaCl, infusion ended', $entries->last()->description);
        $this->assertSame(270, FluidBalanceChart::forPatient($this->patient)['totals']['intake']);

        // A counter that goes backwards (cleared on the pump) counts again from zero
        $second = Infusion::create([
            'patient_id' => $this->patient->id, 'medication_name' => 'Dextrose 5%', 'infused_volume' => 200,
            'status' => Infusion::STATUS_RUNNING, 'started_at' => now(),
        ]);
        $this->travel(61)->minutes();
        $second->update(['infused_volume' => 50]);
        $this->assertSame([200, 50], FluidBalanceEntry::where('source', 'infusion')->where('source_id', $second->id)->orderBy('id')->pluck('volume_ml')->all());
    }

    public function test_a_consultant_order_with_a_fluid_restriction_sets_the_plan_and_cancelling_lifts_it(): void
    {
        $consultant = $this->consultant();
        FluidBalancePlan::create(['patient_id' => $this->patient->id, 'ward_id' => $this->ward->id, 'urine_min_ml_per_hour' => 30, 'set_by' => $this->user->id]);

        $this->actingAs($this->user)->post(route('ward.consultant-orders.store'), [
            'patient_id' => $this->patient->id, 'active_tab' => 'orders', 'consultant_id' => $consultant->id,
            'instruction' => 'Fluid restrict to 1.5 L a day. Daily weights.', 'urgency' => 'routine', 'fluid_limit_ml' => 1500,
        ])->assertSessionHasNoErrors();

        $order = ConsultantOrder::sole();
        $this->assertSame(1500, $order->fluid_limit_ml);
        $plan = FluidBalancePlan::currentFor($this->patient);
        $this->assertSame(1500, $plan->intake_limit_ml);
        $this->assertSame(30, $plan->urine_min_ml_per_hour); // kept from the plan before
        $this->assertSame($order->id, $plan->consultant_order_id);
        $this->assertSame($this->user->id, $plan->set_by);
        $this->assertSame('Consultant order by Dr. Tan Wei Liang: Fluid restrict to 1.5 L a day. Daily weights.', $plan->notes);

        $this->actingAs($this->user)->get(route('ward.patient-details', ['patient_id' => $this->patient->id, 'active_tab' => 'io']))
            ->assertOk()
            ->assertSee('Set by a consultant order (Orders tab)')
            ->assertSee('Fluid restriction 1,500 mL per day');

        $this->actingAs($this->user)->post(route('ward.consultant-orders.cancel', $order), [
            'active_tab' => 'orders', 'outcome_note' => 'Oedema settled',
        ]);
        $plan = FluidBalancePlan::currentFor($this->patient);
        $this->assertNull($plan->intake_limit_ml);
        $this->assertSame(30, $plan->urine_min_ml_per_hour);
        $this->assertNull($plan->consultant_order_id);
        $this->assertSame('Fluid restriction lifted: the consultant order was cancelled (Oedema settled)', $plan->notes);
        $this->assertSame(3, FluidBalancePlan::count());
    }

    public function test_cancelling_leaves_a_plan_someone_set_since_alone(): void
    {
        $order = ConsultantOrder::create([
            'patient_id' => $this->patient->id, 'ward_id' => $this->ward->id, 'consultant_id' => $this->consultant()->id,
            'consultant_name' => 'Dr. Tan Wei Liang', 'instruction' => 'Fluid restrict 1 L', 'fluid_limit_ml' => 1000,
            'urgency' => 'routine', 'ordered_at' => now(), 'status' => 'open',
        ]);
        $this->assertSame(1000, FluidBalancePlan::currentFor($this->patient)->intake_limit_ml);

        // The nurse changes the plan afterwards
        FluidBalancePlan::create(['patient_id' => $this->patient->id, 'ward_id' => $this->ward->id, 'intake_limit_ml' => 1200, 'set_by' => $this->user->id]);

        $order->update(['status' => 'cancelled', 'closed_at' => now(), 'outcome_note' => 'Written in error']);
        $this->assertSame(1200, FluidBalancePlan::currentFor($this->patient)->intake_limit_ml);
        $this->assertSame(2, FluidBalancePlan::count());
    }

    public function test_the_ward_screens_show_where_entries_and_restrictions_come_from(): void
    {
        $this->unit()->update(['status' => BloodTransfusion::STATUS_COMPLETED, 'completed_at' => now()]);
        $this->medication(['medication_name' => 'Vancomycin', 'infusion_volume_ml' => 250]);
        // Medication monitoring is off until a user switches it on
        WardDashboardSetting::updateOrCreate(['user_id' => $this->user->id], ['patient_details_tabs' => ['medications' => true]]);

        $this->actingAs($this->user)->get(route('ward.patient-details', ['patient_id' => $this->patient->id, 'active_tab' => 'io']))
            ->assertOk()
            ->assertSee('Auto &middot; blood unit', false)
            ->assertSee('Finished blood units, IV doses with a volume and pump infusions are added automatically')
            // The Medications tab says what each dose adds; the order form offers the IV volume and the fluid restriction
            ->assertSee('Each dose given adds 250 mL to the I/O chart')
            ->assertSee('IV volume per dose (mL)')
            ->assertSee('Includes a fluid restriction');
    }

    public function test_an_iv_volume_is_only_kept_for_iv_orders(): void
    {
        $this->actingAs($this->user)->post(route('ward.medications.store'), [
            'patient_id' => $this->patient->id, 'active_tab' => 'medications', 'medication_name' => 'Metronidazole',
            'dose_amount' => 500, 'dose_unit' => 'mg', 'route' => 'IV', 'infusion_volume_ml' => 100,
            'frequency' => 'tds', 'first_dose' => 'due_now',
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->user)->post(route('ward.medications.store'), [
            'patient_id' => $this->patient->id, 'active_tab' => 'medications', 'medication_name' => 'Metronidazole',
            'dose_amount' => 400, 'dose_unit' => 'mg', 'route' => 'PO', 'infusion_volume_ml' => 100,
            'frequency' => 'tds', 'first_dose' => 'due_now',
        ])->assertSessionHasNoErrors();

        $this->assertSame([100, null], PatientMedication::orderBy('id')->pluck('infusion_volume_ml')->all());
    }
}
