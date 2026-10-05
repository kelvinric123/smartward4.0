<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\ClinicalIndicator;
use App\Models\ClinicalIndicatorScore;
use App\Models\Hospital;
use App\Models\LabInvestigation;
use App\Models\Nurse;
use App\Models\OxygenTherapyChange;
use App\Models\Patient;
use App\Models\ShiftSetting;
use App\Models\VitalSign;
use App\Models\Ward;
use App\Models\WardScheduleAssignment;
use App\Models\WardType;
use App\Services\LabInvestigations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Nurse app: the Patient Details features added since v1.2 - lab results to
 * review, oxygen therapy, the ward's assessment scales, and the patient's
 * allergies with severity, VIP, payor, COE and expected discharge.
 */
class NurseAppClinicalFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private Ward $ward;
    private Nurse $nurse;
    private Patient $patient;
    private string $token;
    private ClinicalIndicator $morse;
    private ClinicalIndicator $pain;
    private ClinicalIndicator $cssrs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-05 10:00:00')); // AM shift

        $this->morse = ClinicalIndicator::where('code', 'MORSE')->firstOrFail();
        $this->pain = ClinicalIndicator::where('code', 'PAIN')->firstOrFail();
        $this->cssrs = ClinicalIndicator::where('code', 'CSSRS')->firstOrFail();
        // Morse is due every 4 h, overdue after 8 h
        $this->morse->update(['monitoring_enabled' => true, 'monitoring_suggested_minutes' => 240, 'monitoring_warning_minutes' => 480]);

        $medical = WardType::create(['code' => 'MED', 'name' => 'Medical', 'is_active' => true]);
        $medical->clinicalIndicators()->sync([$this->morse->id, $this->pain->id, $this->cssrs->id]);

        $hospital = Hospital::create(['name' => 'Test Hospital']);
        $this->ward = Ward::create(['hospital_id' => $hospital->id, 'ward_code' => 'W6', 'ward_name' => 'Ward 6', 'ward_type_id' => $medical->id, 'is_active' => true]);
        $bed = Bed::create(['ward_id' => $this->ward->id, 'bed_number' => 'D610', 'bed_id' => 'W6-D610', 'bed_display_name' => 'D610', 'status' => 'occupied', 'is_active' => true]);
        foreach (ShiftSetting::getDefaults() as $shift) {
            ShiftSetting::create($shift + ['ward_id' => $this->ward->id, 'is_active' => true]);
        }

        $this->nurse = Nurse::create([
            'name' => 'Aisyah Rahman', 'registration_number' => 'LJM-1001', 'designation' => 'STAFF NURSE I',
            'ward_id' => $this->ward->id, 'app_username' => 'aisyah', 'app_password' => 'secret123', 'is_active' => true,
        ]);
        $this->token = $this->nurse->generateAppToken();
        WardScheduleAssignment::create(['ward_id' => $this->ward->id, 'bed_id' => $bed->id, 'nurse_id' => $this->nurse->id, 'scheduled_date' => '2026-10-05', 'shift' => 'AM']);

        $this->patient = Patient::create([
            'name' => 'Siti Aminah', 'mrn' => 'MRN900001', 'rn' => 'RN900001', 'ic_passport' => '800202-14-6666',
            'age' => 46, 'gender' => 'Female', 'phone' => '013-2224444',
            'ward_id' => $this->ward->id, 'bed_number' => 'D610', 'status' => 'admitted', 'is_active' => true,
            // Admitted 9 h ago, never scored: Morse is overdue
            'admitted_at' => Carbon::parse('2026-10-05 01:00:00'),
        ]);
    }

    private function api(string $method, string $uri, array $data = [])
    {
        return $this->withToken($this->token)->json($method, '/api/nurse' . $uri, $data);
    }

    private function chart(): array
    {
        return $this->api('GET', '/patients/' . $this->patient->id)->assertOk()->json('patient');
    }

    private function lab(array $attributes = []): LabInvestigation
    {
        return LabInvestigation::create($attributes + [
            'patient_id' => $this->patient->id,
            'source' => LabInvestigation::SOURCE_HIS,
            'order_no' => 'LAB0001',
            'test_name' => 'Renal Profile (BUSE)',
            'category' => 'biochemistry',
            'priority' => 'stat',
            'ordered_by' => 'Dr. Lim',
            'ordered_at' => now()->subHours(3),
            'status' => 'resulted',
            'resulted_at' => now()->subHours(2),
            'results' => [['name' => 'Potassium', 'value' => '6.2', 'unit' => 'mmol/L', 'range' => '3.5-5.1', 'flag' => 'HH']],
        ]);
    }

    public function test_the_chart_carries_allergy_severity_vip_payor_coe_and_expected_discharge(): void
    {
        $this->patient->update([
            'allergies' => [
                ['allergen' => 'PEN^Penicillin', 'severity_code' => 'SV', 'status' => 'Active'],
                ['allergen' => 'Latex', 'status' => 'Resolved'],
                'Peanuts',
            ],
            'fall_risk' => '1', // as the ADT feed sends it
            'vip_status' => 'vvip',
            'payor_type' => 'insurance',
            'payor_name' => 'Acme Assurance',
            'payor_status' => 'gl_requested',
            'coe_indicators' => ['CCPC Breast'],
            'estimated_length_of_stay' => 3,
        ]);

        $patient = $this->chart()['patient'];

        $this->assertSame([
            ['name' => 'Penicillin', 'resolved' => false, 'severity' => 'Severe'],
            ['name' => 'Peanuts', 'resolved' => false, 'severity' => null],
            ['name' => 'Latex', 'resolved' => true, 'severity' => null],
        ], $patient['allergies']);
        $this->assertSame('FR Alert Active', $patient['fall_risk']);
        $this->assertSame('VVIP', $patient['vip']);
        $this->assertSame(['status' => 'gl_requested', 'status_label' => 'GL Requested', 'detail' => 'Acme Assurance'], $patient['payor']);
        $this->assertSame(['CCPC Breast'], $patient['coe']);
        $this->assertSame('RN900001', $patient['rn']);
        // Projected from the 3-day estimated stay: a date only
        $this->assertSame(['label' => '08 Oct 2026', 'projected' => true, 'relative' => 'In 3 days', 'tone' => 'info'], $patient['expected_discharge']);
        $this->assertSame('08 Oct 2026', $patient['expected_discharge_label']);
    }

    public function test_lab_results_show_with_when_to_review_and_can_be_marked_reviewed(): void
    {
        $lab = $this->lab();

        $chart = $this->chart();
        $item = $chart['labs']['items'][0];
        $this->assertTrue($chart['labs']['enabled']);
        $this->assertSame(['awaiting_review' => 1, 'overdue' => 1, 'critical' => 1, 'pending' => 0], $chart['labs']['counts']);
        $this->assertSame(['overdue', 'Review overdue since 05 Oct 09:00', true, 'critical', 'STAT'],
            [$item['review_state'], $item['review_label'], $item['can_review'], $item['flag'], $item['priority_label']]);
        $this->assertSame('critical', $item['results'][0]['level']);
        $this->assertSame([1, 1, 1], [$chart['badges']['labs_review'], $chart['badges']['labs_overdue'], $chart['badges']['labs_critical']]);

        $this->api('POST', '/patients/' . $this->patient->id . '/labs/' . $lab->id . '/review')
            ->assertOk()
            ->assertJsonPath('message', 'Renal Profile (BUSE) marked as reviewed.')
            ->assertJsonPath('patient.labs.items.0.review_label', 'Reviewed 05 Oct 10:00 by Aisyah Rahman')
            ->assertJsonPath('patient.badges.labs_review', 0);

        $lab->refresh();
        $this->assertSame('Aisyah Rahman', $lab->reviewed_by_name);
        $this->assertNotNull($lab->reviewed_by, 'recorded as the nurse\'s user');

        // Once only, and only for this patient's results
        $this->api('POST', '/patients/' . $this->patient->id . '/labs/' . $lab->id . '/review')->assertStatus(409);
        $other = Patient::create(['name' => 'Other', 'mrn' => 'MRN900002', 'rn' => 'RN900002', 'ic_passport' => 'X', 'age' => 30, 'gender' => 'Male', 'phone' => '0', 'ward_id' => $this->ward->id, 'bed_number' => 'D611', 'status' => 'admitted', 'is_active' => true]);
        $this->api('POST', '/patients/' . $other->id . '/labs/' . $lab->id . '/review')->assertNotFound();
    }

    public function test_labs_follow_the_ward_switches(): void
    {
        LabInvestigations::save(true, true);
        $chart = $this->chart();
        $this->assertTrue($chart['labs']['sample']);
        $this->assertGreaterThan(0, collect($chart['labs']['items'])->where('sample', true)->count());

        LabInvestigations::save(false, false);
        $lab = $this->lab();
        $chart = $this->chart();
        $this->assertSame(['enabled' => false, 'sample' => false, 'items' => []], array_intersect_key($chart['labs'], array_flip(['enabled', 'sample', 'items'])));
        $this->assertSame(0, $chart['badges']['labs_review']);
        $this->api('POST', '/patients/' . $this->patient->id . '/labs/' . $lab->id . '/review')->assertStatus(409);
    }

    public function test_oxygen_can_be_changed_struck_out_and_reads_against_the_target(): void
    {
        $uri = '/patients/' . $this->patient->id . '/oxygen';

        $this->api('POST', $uri, ['oxygen_delivery' => 'nasal_cannula'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Enter the flow rate or the FiO₂ for Nasal Cannula / Prongs.');
        $this->api('POST', $uri, ['oxygen_delivery' => 'nasal_cannula', 'oxygen_flow_rate' => 2, 'target_spo2_min' => 94])
            ->assertStatus(422)
            ->assertJsonValidationErrors('target_spo2_max');

        $this->api('POST', $uri, [
            'oxygen_delivery' => 'nasal_cannula', 'oxygen_flow_rate' => 2,
            'target_spo2_min' => 94, 'target_spo2_max' => 98, 'minutes_ago' => 30,
        ])->assertOk()->assertJsonPath('message', 'Oxygen changed to Nasal Cannula / Prongs 2 L/min at 09:30.');

        $change = OxygenTherapyChange::sole();
        $this->assertSame(['nasal_cannula', '2.0', 94, 98, '2026-10-05 09:30:00'],
            [$change->oxygen_delivery, (string) $change->oxygen_flow_rate, $change->target_spo2_min, $change->target_spo2_max, $change->started_at->toDateTimeString()]);
        $this->assertNotNull($change->recorded_by);

        // SpO2 below the target since the change is the one to act on
        VitalSign::create(['patient_id' => $this->patient->id, 'reading_type' => 'single', 'spo2' => 90, 'recorded_at' => now()->subMinutes(5)]);
        $chart = $this->chart();
        $this->assertSame(['94–98%', 'critical', 'critical'], [$chart['oxygen']['target']['label'], $chart['oxygen']['alert'], $chart['badges']['oxygen_level']]);
        $this->assertSame($change->id, $chart['oxygen']['history'][0]['change_id']);
        $this->assertNotEmpty($chart['oxygen']['options']['devices']);

        // The same again, from now, changes nothing
        $this->api('POST', $uri, ['oxygen_delivery' => 'nasal_cannula', 'oxygen_flow_rate' => 2, 'target_spo2_min' => 94, 'target_spo2_max' => 98])
            ->assertOk()
            ->assertJsonPath('message', 'Oxygen unchanged: still Nasal Cannula / Prongs 2 L/min.');
        $this->assertSame(1, OxygenTherapyChange::count());

        $this->api('POST', $uri . '/' . $change->id . '/void', [])->assertStatus(422);
        $this->api('POST', $uri . '/' . $change->id . '/void', ['void_reason' => 'Wrong patient'])
            ->assertOk()
            ->assertJsonPath('message', 'Struck out: Nasal Cannula / Prongs 2 L/min from 09:30.')
            ->assertJsonPath('patient.oxygen.history.0.voided', true);
        $this->api('POST', $uri . '/' . $change->id . '/void', ['void_reason' => 'Again'])->assertStatus(409);

        $this->api('POST', $uri, ['oxygen_delivery' => 'room_air'])
            ->assertOk()
            ->assertJsonPath('patient.oxygen.current.on_oxygen', false);
    }

    public function test_assessment_scales_show_when_due_and_can_be_scored(): void
    {
        $chart = $this->chart();
        $scales = collect($chart['assessments']['scales'])->keyBy('code');

        $this->assertSame(['scored', true, 'overdue'], [$scales['MORSE']['kind'], $scales['MORSE']['can_score'], $scales['MORSE']['monitoring']['state']]);
        $this->assertCount(6, $scales['MORSE']['items']);
        $this->assertSame(['score', true], [$scales['PAIN']['kind'], $scales['PAIN']['can_score']]);
        $this->assertSame(['screen', false], [$scales['CSSRS']['kind'], $scales['CSSRS']['can_score']]);
        $this->assertSame(1, $chart['badges']['assess_overdue']);

        // The overdue reassessment on this shift's list opens the Assess tab
        $task = collect($chart['nursing_plan']['shift']['timed'])
            ->concat($chart['nursing_plan']['shift']['standing'])
            ->first(fn (array $task) => str_contains($task['title'], 'Morse'));
        $this->assertNotNull($task, 'the overdue Morse reassessment is on the shift list');
        $this->assertSame('assess', $task['tab']);

        $uri = '/patients/' . $this->patient->id . '/assessments/';

        // History of falling: Yes (25), IV therapy: Yes (20), everything else 0 = 45, high risk
        $this->api('POST', $uri . $this->morse->id, ['item_scores' => [25, 0, 0]])->assertStatus(422)
            ->assertJsonPath('message', 'Score every item before saving.');
        $this->api('POST', $uri . $this->morse->id, ['item_scores' => [25, 0, 0, 20, 0, 7]])->assertStatus(422)
            ->assertJsonPath('message', 'That is not a valid option for Mental status.');

        $this->api('POST', $uri . $this->morse->id, ['item_scores' => [25, 0, 0, 20, 0, 0], 'notes' => 'Unsteady on mobilising'])
            ->assertOk()
            ->assertJsonPath('message', 'Morse Fall Scale scored 45 - High risk.')
            ->assertJsonPath('patient.badges.assess_overdue', 0);

        $score = ClinicalIndicatorScore::sole();
        $this->assertSame([45, 'High risk', 'Unsteady on mobilising'], [$score->score, $score->band_label, $score->notes]);
        $this->assertNotNull($score->recorded_by);

        $this->api('POST', $uri . $this->pain->id, [])->assertStatus(422)->assertJsonPath('message', 'Enter a score before saving.');
        $this->api('POST', $uri . $this->pain->id, ['score' => 11])->assertStatus(422);
        $this->api('POST', $uri . $this->pain->id, ['score' => 4])->assertOk();

        $this->api('POST', $uri . $this->cssrs->id, ['item_scores' => [0]])->assertStatus(409);

        // A scale the ward is not set up for
        $braden = ClinicalIndicator::where('code', 'BRADEN')->firstOrFail();
        $this->api('POST', $uri . $braden->id, ['score' => 15])->assertNotFound();
    }
}
