<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use App\Models\VitalSign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VitalSignOxygenTest extends TestCase
{
    use RefreshDatabase;

    private const PASSPHRASE = 'askdrtai';

    private function makePatient(): Patient
    {
        return Patient::create([
            'name' => 'Lim Wei Jie',
            'mrn' => 'MRN800001',
            'rn' => 'RN800001',
            'ic_passport' => '760101-07-1122',
            'age' => 49,
            'gender' => 'Male',
            'phone' => '012-8887777',
            'status' => Patient::STATUS_ADMITTED,
            'admitted_at' => now()->subDay(),
        ]);
    }

    private function reading(Patient $patient, array $overrides = []): VitalSign
    {
        return VitalSign::create(array_merge([
            'patient_id' => $patient->id,
            'recorded_by' => User::factory()->create()->id,
            'pulse_rate' => 80,
            'spo2' => 97,
            'reading_type' => 'single',
            'recorded_at' => now()->subHour(),
        ], $overrides));
    }

    public function test_a_reading_can_be_recorded_with_oxygen_delivery(): void
    {
        $patient = $this->makePatient();

        $this->actingAs(User::factory()->create())
            ->post(route('vital-signs.store'), [
                'patient_id' => $patient->id,
                'temperature' => 37.2,
                'systolic_bp' => 128,
                'diastolic_bp' => 82,
                'pulse_rate' => 88,
                'respiratory_rate' => 18,
                'spo2' => 94,
                'oxygen_delivery' => 'high_flow_mask',
                'oxygen_flow_rate' => '10.5',
                'fio2_percent' => 60,
            ])
            ->assertSessionHasNoErrors();

        $vital = VitalSign::where('patient_id', $patient->id)->firstOrFail();
        $this->assertSame('high_flow_mask', $vital->oxygen_delivery);
        $this->assertSame('10.5', $vital->oxygen_flow_rate);
        $this->assertSame(60, $vital->fio2_percent);
        $this->assertTrue($vital->isOnOxygen());
        $this->assertSame('High Flow Mask', $vital->oxygenDeliveryLabel());
        $this->assertSame('HFM 10.5L', $vital->oxygenShortLabel());
        // a full set of values is still classified as a full reading
        $this->assertSame('full', $vital->reading_type);
    }

    public function test_room_air_clears_flow_rate_and_fio2(): void
    {
        $patient = $this->makePatient();

        $this->actingAs(User::factory()->create())
            ->post(route('vital-signs.store'), [
                'patient_id' => $patient->id,
                'pulse_rate' => 70,
                'oxygen_delivery' => 'room_air',
                'oxygen_flow_rate' => '4',
                'fio2_percent' => 40,
            ])
            ->assertSessionHasNoErrors();

        $vital = VitalSign::where('patient_id', $patient->id)->firstOrFail();
        $this->assertSame('room_air', $vital->oxygen_delivery);
        $this->assertNull($vital->oxygen_flow_rate);
        $this->assertNull($vital->fio2_percent);
        $this->assertFalse($vital->isOnOxygen());
    }

    public function test_unknown_oxygen_delivery_is_rejected(): void
    {
        $patient = $this->makePatient();

        $this->actingAs(User::factory()->create())
            ->post(route('vital-signs.store'), [
                'patient_id' => $patient->id,
                'pulse_rate' => 70,
                'oxygen_delivery' => 'space_helmet',
            ])
            ->assertSessionHasErrors('oxygen_delivery');

        $this->assertSame(0, VitalSign::where('patient_id', $patient->id)->count());
    }

    public function test_editing_a_reading_requires_the_passphrase(): void
    {
        $patient = $this->makePatient();
        $vital = $this->reading($patient);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('vital-signs.update', $vital), [
                'pulse_rate' => 55,
                'oxygen_delivery' => 'nasal_cannula',
                'oxygen_flow_rate' => 2,
            ]);

        $this->assertSame(80, $vital->fresh()->pulse_rate, 'the reading must not change without the passphrase');

        $this->actingAs($user)
            ->put(route('vital-signs.update', $vital), [
                'pulse_rate' => 55,
                'spo2' => 93,
                'oxygen_delivery' => 'nasal_cannula',
                'oxygen_flow_rate' => 2,
                'delete_passphrase' => self::PASSPHRASE,
            ])
            ->assertSessionHasNoErrors();

        $vital->refresh();
        $this->assertSame(55, $vital->pulse_rate);
        $this->assertSame('nasal_cannula', $vital->oxygen_delivery);
        $this->assertSame('2.0', $vital->oxygen_flow_rate);
        $this->assertSame('NP 2L', $vital->oxygenShortLabel());
    }

    public function test_readings_from_a_monitor_cannot_be_edited(): void
    {
        $patient = $this->makePatient();
        $deviceReading = $this->reading($patient, ['gateway_id' => 'CART-01', 'recorded_by' => null]);

        $this->assertFalse($deviceReading->isManualEntry());

        $this->actingAs(User::factory()->create())
            ->put(route('vital-signs.update', $deviceReading), [
                'pulse_rate' => 120,
                'delete_passphrase' => self::PASSPHRASE,
            ]);

        $this->assertSame(80, $deviceReading->fresh()->pulse_rate);
    }

    public function test_removing_a_reading_requires_the_passphrase(): void
    {
        $patient = $this->makePatient();
        $vital = $this->reading($patient);
        $user = User::factory()->create();

        $this->actingAs($user)->delete(route('vital-signs.destroy', $vital));
        $this->assertNotSoftDeleted($vital);

        $this->actingAs($user)->delete(route('vital-signs.destroy', $vital), [
            'delete_passphrase' => self::PASSPHRASE,
        ]);
        $this->assertSoftDeleted($vital);
    }

    public function test_the_vitals_page_is_read_only_unless_edit_is_requested(): void
    {
        $patient = $this->makePatient();
        $this->reading($patient, ['oxygen_delivery' => 'venturi_mask', 'fio2_percent' => 35]);
        $user = User::factory()->create();

        // Dashboard modal: same figures, no way to change them
        $this->actingAs($user)
            ->get(route('vital-signs.patient', ['patient_id' => $patient->id]))
            ->assertOk()
            ->assertSee('Venturi Mask')
            ->assertSee('VM 35%')
            ->assertDontSee('Record Reading');

        // Patient details tab: same page with recording enabled
        $this->actingAs($user)
            ->get(route('vital-signs.patient', ['patient_id' => $patient->id, 'edit' => 1]))
            ->assertOk()
            ->assertSee('Venturi Mask')
            ->assertSee('Record Reading')
            ->assertSee('Oxygen Status');
    }

    public function test_patient_details_vitals_tab_embeds_the_same_panel(): void
    {
        $patient = $this->makePatient();

        $this->actingAs(User::factory()->create())
            ->get(route('ward.patient-details', ['patient_id' => $patient->id, 'active_tab' => 'vitals']))
            ->assertOk()
            // escaped by assertSee, so this matches the &amp; in the rendered iframe src
            ->assertSee('/vital-signs/patient?patient_id=' . $patient->id . '&edit=1');
    }
}
