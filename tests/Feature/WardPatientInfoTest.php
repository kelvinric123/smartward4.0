<?php

namespace Tests\Feature;

use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use App\Models\Ward;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Ward Dashboard -> Patient Details -> Patient Info: VIP status, payor status,
 * COE indicators and expected discharge date.
 */
class WardPatientInfoTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Ward $ward;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-05 10:00:00'));

        $this->user = User::factory()->create();
        $hospital = Hospital::create(['name' => 'Test Hospital']);
        $this->ward = Ward::create(['hospital_id' => $hospital->id, 'ward_code' => 'W6', 'ward_name' => 'Ward 6', 'is_active' => true]);
    }

    private function patient(array $overrides = []): Patient
    {
        return Patient::create(array_merge([
            'name' => 'Siti Aminah',
            'mrn' => 'MRN910001',
            'rn' => 'RN910001',
            'ic_passport' => '800303-14-7777',
            'age' => 46,
            'gender' => 'Female',
            'phone' => '013-2224444',
            'ward_id' => $this->ward->id,
            'bed_number' => 'D610',
            'status' => Patient::STATUS_ADMITTED,
            'admitted_at' => now()->subDays(2),
            'is_active' => true,
        ], $overrides));
    }

    private function info(Patient $patient)
    {
        return $this->actingAs($this->user)
            ->get(route('ward.patient-details', ['patient_id' => $patient->id]));
    }

    public function test_patient_info_shows_vip_payor_coe_and_expected_discharge(): void
    {
        $patient = $this->patient([
            'vip_status' => 'vvip',
            'payor_type' => 'insurance',
            'payor_name' => 'Acme Assurance',
            'payor_status' => 'gl_requested',
            'coe_indicators' => ['CCPC Breast', 'Chronic Kidney Disease'],
            'expected_discharge_at' => Carbon::parse('2026-10-07 11:00'),
        ]);

        $this->info($patient)
            ->assertOk()
            ->assertSeeInOrder([
                'VIP Status', 'VVIP',
                'Payor Status', 'GL Requested', 'Acme Assurance',
                'COE Indicators', 'CCPC Breast', 'Chronic Kidney Disease',
                'Expected Discharge', '2026-10-07 11:00', 'In 2 days',
            ])
            ->assertSee(route('patients.show', $patient), false);
    }

    public function test_payor_type_stands_in_when_there_is_no_payor_name(): void
    {
        $this->info($this->patient(['payor_type' => 'self_pay']))
            ->assertOk()
            ->assertSeeInOrder(['Payor Status', 'Self Pay', 'COE Indicators']);
    }

    public function test_expected_discharge_is_projected_from_estimated_stay_or_flagged_overdue(): void
    {
        $projected = $this->patient([
            'admitted_at' => Carbon::parse('2026-10-01 09:00'),
            'estimated_length_of_stay' => 7,
        ]);

        $this->info($projected)
            ->assertOk()
            ->assertSeeInOrder(['Expected Discharge', '2026-10-08', 'In 3 days', 'Projected from est. length of stay']);

        $overdue = $this->patient([
            'mrn' => 'MRN910002', 'rn' => 'RN910002', 'ic_passport' => '800404-14-8888',
            'expected_discharge_at' => Carbon::parse('2026-10-03 12:00'),
        ]);

        $this->info($overdue)
            ->assertOk()
            ->assertSee('Overdue by 2 days');
    }

    public function test_patient_without_these_fields_still_renders(): void
    {
        $this->info($this->patient())
            ->assertOk()
            ->assertSee('Admission &amp; Payor', false)
            ->assertDontSee('Projected from est. length of stay')
            ->assertDontSee('Overdue by');
    }
}
