<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\Consultant;
use App\Models\Hospital;
use App\Models\LabInvestigation;
use App\Models\OxygenTherapyChange;
use App\Models\Patient;
use App\Models\User;
use App\Models\VitalSign;
use App\Models\Ward;
use App\Services\LabInvestigations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The doctor app gets what Patient Details added: oxygen therapy (read together with the
 * oxygen on vital signs), lab investigations with reviewing from the app, allergy
 * severity and the VIP status.
 */
class DoctorAppClinicalFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private Consultant $consultant;
    private Consultant $colleague;
    private string $token;
    private Ward $ward;
    private Patient $patient;
    private User $nurseUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-09-22 09:00:00'));

        $hospital = Hospital::create(['name' => 'Test Hospital']);
        $this->ward = Ward::create(['hospital_id' => $hospital->id, 'ward_code' => 'MW1', 'ward_name' => 'Medical Ward 1', 'is_active' => true]);
        Bed::create(['ward_id' => $this->ward->id, 'bed_number' => 'B01', 'bed_id' => 'MW1-B01', 'bed_display_name' => 'B01', 'status' => 'occupied', 'is_active' => true]);

        $this->consultant = Consultant::create([
            'name' => 'Dr. Tan Wei Liang', 'personnel_code' => 'C100', 'registration_number' => 'MMC-C100', 'is_active' => true,
            'app_username' => 'drtan', 'app_password' => 'secret123',
        ]);
        $this->colleague = Consultant::create(['name' => 'Dr. Wong Mei Ling', 'personnel_code' => 'C200', 'registration_number' => 'MMC-C200', 'is_active' => true]);
        $this->token = $this->consultant->generateAppToken();
        $this->nurseUser = User::factory()->create(['name' => 'Nurse Aina']);

        $this->patient = $this->makePatient('Tan Mei Ling', 'B01', $this->consultant);
    }

    private function makePatient(string $name, string $bed, Consultant $consultant, array $extra = []): Patient
    {
        static $n = 0;
        $n++;

        return Patient::create($extra + [
            'name' => $name, 'mrn' => 'MRN93000' . $n, 'rn' => 'RN93000' . $n, 'ic_passport' => '600101-10-70' . $n,
            'age' => 67, 'gender' => 'Female', 'phone' => '012-9876543', 'consultant_id' => $consultant->id,
            'ward_id' => $this->ward->id, 'bed_number' => $bed, 'status' => 'admitted', 'is_active' => true,
            'admitted_at' => Carbon::parse('2026-09-22 07:30:00'),
        ]);
    }

    private function api(string $method, string $uri, array $data = [])
    {
        return $this->withToken($this->token)->json($method, $uri, $data);
    }

    private function chartUrl(?Patient $patient = null): string
    {
        return '/api/doctor/patients/' . ($patient ?? $this->patient)->id . '/chart';
    }

    private function reviewUrl(LabInvestigation $lab, ?Patient $patient = null): string
    {
        return '/api/doctor/patients/' . ($patient ?? $this->patient)->id . '/labs/' . $lab->id . '/review';
    }

    /** NP 2 L/min set on the ward at 08:00, turned up to 3 L/min with the vitals at 08:30. */
    private function seedOxygen(): void
    {
        OxygenTherapyChange::create([
            'patient_id' => $this->patient->id, 'ward_id' => $this->ward->id, 'oxygen_delivery' => 'nasal_cannula',
            'oxygen_flow_rate' => 2, 'target_spo2_min' => 94, 'target_spo2_max' => 98, 'notes' => 'Desaturated on mobilising',
            'started_at' => Carbon::parse('2026-09-22 08:00:00'), 'recorded_by' => $this->nurseUser->id,
        ]);
        VitalSign::create([
            'patient_id' => $this->patient->id, 'recorded_by' => $this->nurseUser->id, 'reading_type' => 'single',
            'spo2' => 91, 'oxygen_delivery' => 'nasal_cannula', 'oxygen_flow_rate' => 3,
            'recorded_at' => Carbon::parse('2026-09-22 08:30:00'),
        ]);
    }

    private function lab(array $fields, ?Patient $patient = null): LabInvestigation
    {
        return LabInvestigation::create($fields + [
            'patient_id' => ($patient ?? $this->patient)->id, 'source' => LabInvestigation::SOURCE_HIS,
            'order_no' => 'LAB0001', 'category' => 'biochemistry', 'priority' => 'routine', 'ordered_by' => 'Dr. Tan Wei Liang',
            'ordered_at' => now()->subHours(6),
        ]);
    }

    /** An overdue renal profile with a critical potassium, a full blood count due later, and a CRP still in the lab. */
    private function seedLabs(): array
    {
        return [
            'renal' => $this->lab([
                'test_name' => 'Renal Profile (BUSE)', 'priority' => 'urgent', 'status' => 'resulted',
                'resulted_at' => now()->subHours(5),
                'results' => [
                    ['name' => 'Potassium', 'value' => '6.2', 'unit' => 'mmol/L', 'range' => '3.5-5.1', 'flag' => 'HH'],
                    ['name' => 'Sodium', 'value' => '138', 'unit' => 'mmol/L', 'range' => '135-145', 'flag' => 'N'],
                ],
                'comment' => 'Critical potassium phoned to ward by lab.',
            ]),
            'fbc' => $this->lab([
                'test_name' => 'Full Blood Count', 'category' => 'haematology', 'status' => 'resulted',
                'resulted_at' => now()->subHours(2),
                'results' => [['name' => 'Haemoglobin', 'value' => '9.8', 'unit' => 'g/dL', 'range' => '12.0-15.0', 'flag' => 'L']],
            ]),
            'crp' => $this->lab(['test_name' => 'C-Reactive Protein', 'priority' => 'stat', 'status' => 'ordered', 'ordered_at' => now()->subMinutes(20)]),
            // Demo results stay out while sample data is off
            'sample' => $this->lab(['test_name' => 'HbA1c', 'source' => LabInvestigation::SOURCE_SAMPLE, 'status' => 'resulted', 'resulted_at' => now()->subDay()]),
        ];
    }

    public function test_the_chart_carries_oxygen_therapy_read_together_with_vital_signs(): void
    {
        $this->seedOxygen();

        $chart = $this->api('GET', $this->chartUrl())->assertOk()->json('chart');
        $oxygen = $chart['oxygen'];

        // Turned up at the bedside and recorded with the vitals: that is the oxygen now
        $this->assertSame('NP 3L', $oxygen['current']['short']);
        $this->assertSame('3 L/min', $oxygen['current']['settings']);
        $this->assertSame('vitals', $oxygen['current']['source']);
        $this->assertSame('22 Sep 08:30', $oxygen['current']['since_label']);
        $this->assertSame('30 min', $oxygen['current']['duration_label']);
        $this->assertSame(['min' => 94, 'max' => 98, 'label' => '94–98%', 'note' => 'Most patients'], $oxygen['target']);

        $this->assertSame(91, $oxygen['latest_spo2']['value']);
        $this->assertSame('below', $oxygen['latest_spo2']['state']);
        $this->assertSame('critical', $oxygen['alert']);
        $this->assertSame('1 h', $oxygen['on_oxygen_duration_label']);

        $this->assertCount(2, $oxygen['history']);
        $this->assertTrue($oxygen['history'][0]['current']);
        $this->assertSame('30 min so far', $oxygen['history'][0]['duration_label']);
        $this->assertSame('Desaturated on mobilising', $oxygen['history'][1]['notes']);
        $this->assertSame('Nurse Aina', $oxygen['history'][1]['by']);
        $this->assertCount(2, $oxygen['chart']['steps']);
        $this->assertSame([91], array_column($oxygen['chart']['spo2'], 'y'));

        $this->assertSame('NP 3L', $chart['badges']['oxygen_short']);
        $this->assertSame('critical', $chart['badges']['oxygen_level']);
    }

    public function test_a_patient_without_oxygen_recorded_has_an_empty_oxygen_section(): void
    {
        $chart = $this->api('GET', $this->chartUrl())->assertOk()->json('chart');

        $this->assertNull($chart['oxygen']['current']);
        $this->assertNull($chart['oxygen']['target']);
        $this->assertNull($chart['oxygen']['alert']);
        $this->assertSame([], $chart['oxygen']['history']);
        $this->assertNull($chart['badges']['oxygen_short']);
    }

    public function test_the_chart_lists_lab_results_overdue_ones_first(): void
    {
        $this->seedLabs();

        $chart = $this->api('GET', $this->chartUrl())->assertOk()->json('chart');
        $labs = $chart['labs'];

        $this->assertTrue($labs['enabled']);
        $this->assertSame(['Renal Profile (BUSE)', 'Full Blood Count', 'C-Reactive Protein'], array_column($labs['items'], 'test_name'));
        $this->assertSame(['awaiting_review' => 2, 'overdue' => 1, 'critical' => 1, 'pending' => 1], $labs['counts']);

        $renal = $labs['items'][0];
        $this->assertSame('overdue', $renal['review_state']);
        $this->assertSame('Review overdue since 22 Sep 08:00', $renal['review_label']);
        $this->assertSame('critical', $renal['flag']);
        $this->assertSame('critical', $renal['results'][0]['level']);
        // "N" is the lab's way of saying normal
        $this->assertSame('', $renal['results'][1]['flag']);
        $this->assertNull($renal['results'][1]['level']);
        $this->assertTrue($renal['can_review']);
        $this->assertSame('Urgent', $renal['priority_label']);

        $this->assertSame('Review by 23 Sep 07:00', $labs['items'][1]['review_label']);
        $this->assertSame('Awaiting result', $labs['items'][2]['review_label']);
        $this->assertFalse($labs['items'][2]['can_review']);

        $this->assertSame(2, $chart['badges']['labs_review']);
        $this->assertSame(1, $chart['badges']['labs_overdue']);
    }

    public function test_a_consultant_marks_a_lab_result_reviewed_from_the_app(): void
    {
        $labs = $this->seedLabs();

        $this->api('POST', $this->reviewUrl($labs['renal']))
            ->assertOk()
            ->assertJsonPath('message', 'Renal Profile (BUSE) marked as reviewed.')
            ->assertJsonPath('chart.labs.counts.awaiting_review', 1)
            ->assertJsonPath('chart.labs.counts.overdue', 0);

        $labs['renal']->refresh();
        $this->assertTrue($labs['renal']->reviewed_at->equalTo(now()));
        $this->assertSame('Dr. Tan Wei Liang', $labs['renal']->reviewed_by_name);
        $this->assertNull($labs['renal']->reviewed_by);

        $chart = $this->api('GET', $this->chartUrl())->json('chart');
        $renal = collect($chart['labs']['items'])->firstWhere('id', $labs['renal']->id);
        $this->assertSame('Reviewed 22 Sep 09:00 by Dr. Tan Wei Liang', $renal['review_label']);

        // Once is enough, and a result still in the lab cannot be reviewed
        $this->api('POST', $this->reviewUrl($labs['renal']))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Renal Profile (BUSE) has no result waiting for review.');
        $this->api('POST', $this->reviewUrl($labs['crp']))->assertStatus(422);
    }

    public function test_reviews_stay_with_the_consultants_own_patients(): void
    {
        $labs = $this->seedLabs();
        $elsewhere = $this->makePatient('Lim Ah Kow', 'B02', $this->colleague);
        $theirs = $this->lab(['test_name' => 'Lipid Profile', 'status' => 'resulted', 'resulted_at' => now()->subHour()], $elsewhere);

        // No token (asked first: the token stays on every request after it is set)
        $this->postJson($this->reviewUrl($labs['fbc']))->assertStatus(401);
        // Not under this consultant's care
        $this->api('POST', $this->reviewUrl($theirs, $elsewhere))->assertStatus(403);
        // Under care, but the result belongs to someone else
        $this->api('POST', $this->reviewUrl($theirs))
            ->assertStatus(404)
            ->assertJsonPath('message', 'That result is not on this patient.');

        $this->assertNull($theirs->fresh()->reviewed_at);
        $this->assertNull($labs['fbc']->fresh()->reviewed_at);
    }

    public function test_labs_stay_out_of_the_app_while_switched_off_on_the_ward_system(): void
    {
        $this->seedLabs();
        LabInvestigations::save(false, false);

        $this->api('GET', $this->chartUrl())
            ->assertOk()
            ->assertJsonPath('chart.labs.enabled', false)
            ->assertJsonPath('chart.labs.items', [])
            ->assertJsonPath('chart.badges.labs_review', 0);

        $dashboard = $this->api('GET', '/api/doctor/dashboard')->assertOk();
        $bed = collect($dashboard->json('beds'))->firstWhere('patient_id', $this->patient->id);
        $this->assertNull($bed['labs']);
        $this->assertFalse($bed['pending_review']);
        $this->assertSame(0, $dashboard->json('summary.pending_reviews'));
    }

    public function test_allergies_carry_their_severity_and_the_vip_status_shows(): void
    {
        $this->patient->update([
            'vip_status' => 'vvip',
            'allergies' => [
                'Latex',
                ['allergen' => 'Aspirin', 'severity' => 'Mild', 'status' => 'Resolved'],
                ['allergen_code' => 'DA001', 'allergen' => 'PEN^Penicillin', 'severity_code' => 'SV', 'status' => 'Active'],
            ],
        ]);

        $patient = $this->api('GET', $this->chartUrl())->assertOk()->json('chart.patient');

        $this->assertSame('VVIP', $patient['vip']);
        // Active ones as text, most severe first; the resolved one is left out
        $this->assertSame(['Penicillin (Severe)', 'Latex'], $patient['allergies']);
        $this->assertSame([
            ['name' => 'Penicillin', 'severity' => 'Severe', 'resolved' => false, 'label' => 'Penicillin (Severe)'],
            ['name' => 'Latex', 'severity' => null, 'resolved' => false, 'label' => 'Latex'],
            ['name' => 'Aspirin', 'severity' => 'Mild', 'resolved' => true, 'label' => 'Aspirin (Mild)'],
        ], $patient['allergy_list']);
    }

    public function test_the_dashboard_shows_oxygen_results_to_review_allergies_and_vip(): void
    {
        $this->seedOxygen();
        $this->seedLabs();
        $this->patient->update(['vip_status' => 'vip', 'allergies' => [['allergen' => 'Penicillin', 'severity_code' => 'MO']]]);

        $response = $this->api('GET', '/api/doctor/dashboard')->assertOk();
        $bed = collect($response->json('beds'))->firstWhere('patient_id', $this->patient->id);

        $this->assertSame('VIP', $bed['vip_status']);
        $this->assertSame('Penicillin (Moderate)', $bed['allergy_list'][0]['label']);

        $this->assertTrue($bed['oxygen']['on_oxygen']);
        $this->assertSame('NP 3L', $bed['oxygen']['short']);
        $this->assertSame('94–98%', $bed['oxygen']['target_label']);
        $this->assertSame('below', $bed['oxygen']['spo2_state']);

        $this->assertSame(['awaiting_review' => 2, 'overdue' => 1, 'critical' => 1], $bed['labs']);
        $this->assertTrue($bed['pending_review']);
        $this->assertSame(2, $response->json('summary.pending_reviews'));
    }
}
