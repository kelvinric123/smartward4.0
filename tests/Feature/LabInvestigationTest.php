<?php

namespace Tests\Feature;

use App\Models\Hospital;
use App\Models\LabInvestigation;
use App\Models\Patient;
use App\Models\User;
use App\Models\Ward;
use App\Services\LabInvestigations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Patient Details > Lab Investigations: HIS lab orders and results, when to review them,
 * the HIS feed, and the on/off and sample switches in Settings > Patient Additional Info.
 */
class LabInvestigationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-05 10:00:00'));

        $this->user = User::factory()->create(['name' => 'Nurse Aisyah']);
        $hospital = Hospital::create(['name' => 'Test Hospital']);
        $ward = Ward::create(['hospital_id' => $hospital->id, 'ward_code' => 'W7', 'ward_name' => 'Ward 7', 'is_active' => true]);

        $this->patient = Patient::create([
            'name' => 'Rajesh Kumar', 'mrn' => 'MRN920001', 'rn' => 'RN920001', 'ic_passport' => '790404-10-1234',
            'age' => 47, 'gender' => 'Male', 'phone' => '012-3334444',
            'ward_id' => $ward->id, 'bed_number' => 'B701', 'status' => Patient::STATUS_ADMITTED,
            'admitted_at' => now()->subDays(2), 'is_active' => true,
        ]);
    }

    private function details(array $query = [])
    {
        return $this->actingAs($this->user)
            ->get(route('ward.patient-details', ['patient_id' => $this->patient->id, 'active_tab' => 'lab'] + $query));
    }

    private function lab(array $attributes = []): LabInvestigation
    {
        return LabInvestigation::create($attributes + [
            'patient_id' => $this->patient->id,
            'source' => LabInvestigation::SOURCE_HIS,
            'order_no' => 'LAB0001',
            'test_name' => 'Full Blood Count',
            'category' => 'haematology',
            'priority' => 'routine',
            'ordered_at' => now()->subHours(3),
            'status' => 'ordered',
        ]);
    }

    private function hisToken(): string
    {
        return User::factory()->create(['name' => 'HIS', 'role' => User::ROLE_INTEGRATION])->generateApiToken();
    }

    private function hisOrder(array $investigation = [], array $order = []): array
    {
        return [
            'patient' => ['mrn' => 'MRN920001'],
            'order' => $order + [
                'order_no' => 'LAB2610050001',
                'ordered_at' => '2026-10-05T07:00:00+08:00',
                'ordered_by' => 'Dr. Lim Wei Ming',
                'priority' => 'urgent',
                'investigations' => [$investigation + [
                    'code' => 'RP',
                    'name' => 'Renal Profile (BUSE)',
                    'category' => 'biochemistry',
                    'specimen' => 'Blood (Plain)',
                    'status' => 'ordered',
                ]],
            ],
        ];
    }

    public function test_tab_follows_the_settings_switch_and_sits_after_patient_movement(): void
    {
        $this->details()
            ->assertOk()
            ->assertSeeInOrder(['Patient Movement', 'Lab Investigations', 'Consultant'])
            ->assertSee('No lab investigations received from the HIS for this patient.');

        LabInvestigations::save(false, false);

        $this->details()->assertOk()->assertDontSee('Lab Investigations');

        // An embed that restricts tabs leaves it out too
        LabInvestigations::save(true, false);
        $this->details(['tabs' => 'info,movement'])->assertOk()->assertDontSee('Lab Investigations');
    }

    public function test_shows_ordering_results_and_when_to_review(): void
    {
        $this->lab([
            'order_no' => 'LAB0002', 'test_code' => 'RP', 'test_name' => 'Renal Profile (BUSE)', 'category' => 'biochemistry',
            'priority' => 'stat', 'ordered_by' => 'Dr. Lim Wei Ming', 'ordered_at' => now()->subHours(3),
            'status' => 'resulted', 'resulted_at' => now()->subHours(2),
            'results' => [
                ['name' => 'Potassium', 'value' => '6.2', 'unit' => 'mmol/L', 'range' => '3.5-5.1', 'flag' => 'HH'],
                ['name' => 'Sodium', 'value' => '139', 'unit' => 'mmol/L', 'range' => '135-145', 'flag' => ''],
            ],
        ]);
        $this->lab(['order_no' => 'LAB0003', 'test_name' => 'HbA1c', 'priority' => 'routine', 'ordered_at' => now()->subHour()]);

        $this->details()
            ->assertOk()
            ->assertSeeInOrder([
                'Renal Profile (BUSE)', 'Biochemistry', 'STAT', '2026-10-05 07:00', 'Dr. Lim Wei Ming', 'Order LAB0002',
                'Resulted', 'CRITICAL', 'Potassium 6.2 mmol/L', '(HH)',
                // STAT: due 1 h after the result
                'Review overdue', '05/10 09:00', 'Mark reviewed',
                'HbA1c', 'Routine', 'Ordered', 'No result yet', 'Awaiting result', '06/10 09:00',
            ])
            ->assertSee('awaiting review · 1 overdue')
            ->assertSee('critical result unreviewed')
            ->assertSee('title="1 result(s) overdue for review"', false);
    }

    public function test_review_state_follows_priority_and_his_review_time(): void
    {
        $resulted = fn (string $priority, int $minutesAgo) => $this->lab([
            'order_no' => 'LAB-' . $priority . $minutesAgo, 'priority' => $priority,
            'status' => 'resulted', 'resulted_at' => now()->subMinutes($minutesAgo),
        ]);

        $this->assertSame('overdue', $resulted('stat', 61)->reviewState());
        $this->assertSame('due_soon', $resulted('stat', 10)->reviewState());
        $this->assertSame('due_soon', $resulted('urgent', 200)->reviewState());
        $this->assertSame('due', $resulted('routine', 60)->reviewState());

        $culture = $this->lab(['order_no' => 'LAB-BC', 'status' => 'in_progress', 'review_due_at' => now()->addDays(4)]);
        $this->assertSame('awaiting_result', $culture->reviewState());
        $this->assertTrue($culture->reviewDueAt()->equalTo(now()->addDays(4)));

        $late = $this->lab(['order_no' => 'LAB-LATE', 'priority' => 'stat', 'ordered_at' => now()->subHours(2)]);
        $this->assertSame('result_late', $late->reviewState());

        $this->assertSame('none', $this->lab(['order_no' => 'LAB-X', 'status' => 'cancelled'])->reviewState());
    }

    public function test_result_can_be_marked_reviewed_once(): void
    {
        $lab = $this->lab(['status' => 'resulted', 'resulted_at' => now()->subHour(), 'results' => [['name' => 'Hb', 'value' => '9.8', 'flag' => 'L']]]);

        $this->actingAs($this->user)
            ->post(route('ward.lab-investigations.review', $lab))
            ->assertRedirect(route('ward.patient-details', ['patient_id' => $this->patient->id, 'active_tab' => 'lab']))
            ->assertSessionHas('success');

        $lab->refresh();
        $this->assertSame('reviewed', $lab->reviewState());
        $this->assertSame($this->user->id, $lab->reviewed_by);
        $this->assertSame('Nurse Aisyah', $lab->reviewed_by_name);

        $this->actingAs($this->user)->post(route('ward.lab-investigations.review', $lab))->assertSessionHas('error');

        // Nothing to review before a result arrives
        $pending = $this->lab(['order_no' => 'LAB0009']);
        $this->actingAs($this->user)->post(route('ward.lab-investigations.review', $pending))->assertSessionHas('error');
        $this->assertNull($pending->fresh()->reviewed_at);
    }

    public function test_sample_data_demonstrates_the_integration_and_is_removed_when_switched_off(): void
    {
        $this->lab(['test_name' => 'Real HIS order']);
        LabInvestigations::save(true, true);

        $this->details()
            ->assertOk()
            ->assertSee('Sample data is on.')
            ->assertSee('SAMPLE')
            ->assertSee('Real HIS order')
            ->assertSee('Full Blood Count')
            ->assertSee('Arterial Blood Gas')
            ->assertSee('Review overdue')
            ->assertSee('Review due soon')
            ->assertSee('Awaiting result')
            ->assertSee('Reviewed');

        // Opening the patient again does not add a second set
        $count = LabInvestigation::where('source', 'sample')->count();
        $this->details()->assertOk();
        $this->assertSame($count, LabInvestigation::where('source', 'sample')->count());

        $this->actingAs($this->user)
            ->post(route('ward.lab-investigations.settings'), ['enabled' => '1', 'sample' => '0'])
            ->assertSessionHas('settings_tab', 'patient-additional-info');

        $this->assertSame(0, LabInvestigation::where('source', 'sample')->count());
        $this->details()
            ->assertSee('Real HIS order')
            ->assertDontSee('Sample data is on.')
            ->assertDontSee('Arterial Blood Gas');
    }

    public function test_settings_page_offers_the_switches_and_the_his_endpoint(): void
    {
        $this->actingAs($this->user)
            ->get(route('ward.settings'))
            ->assertOk()
            ->assertSeeInOrder(['Patient Additional Info', 'Save Sources', 'Lab Investigations', 'Show Lab Investigations', 'Sample data'])
            ->assertSee(route('api.his.lab-investigations'))
            ->assertSee('LAB2610050001');

        $this->actingAs($this->user)
            ->post(route('ward.lab-investigations.settings'), ['enabled' => '0', 'sample' => '1']);
        $this->assertSame(['enabled' => false, 'sample' => true], LabInvestigations::settings());
    }

    public function test_his_feed_needs_an_integration_token(): void
    {
        $this->postJson(route('api.his.lab-investigations'), $this->hisOrder())->assertStatus(401);
        $this->withToken('not-a-token')->postJson(route('api.his.lab-investigations'), $this->hisOrder())->assertStatus(401);

        $this->assertSame(0, LabInvestigation::count());
    }

    public function test_his_feed_creates_then_updates_an_order_as_it_is_resulted(): void
    {
        $token = $this->hisToken();

        $this->withToken($token)->postJson(route('api.his.lab-investigations'), $this->hisOrder())
            ->assertOk()
            ->assertJson(['ok' => true, 'patient_id' => $this->patient->id, 'created' => 1, 'updated' => 0]);

        $lab = LabInvestigation::sole();
        $this->assertSame(['his', 'LAB2610050001', 'RP', 'urgent', 'Dr. Lim Wei Ming', 'ordered'],
            [$lab->source, $lab->order_no, $lab->test_code, $lab->priority, $lab->ordered_by, $lab->status]);

        $results = [['name' => 'Potassium', 'value' => '6.2', 'unit' => 'mmol/L', 'range' => '3.5-5.1', 'flag' => 'HH']];
        $this->withToken($token)->postJson(route('api.his.lab-investigations'), $this->hisOrder([
            'status' => 'resulted',
            'resulted_at' => '2026-10-05T09:00:00+08:00',
            'results' => $results,
        ]))->assertOk()->assertJson(['created' => 0, 'updated' => 1]);

        $lab = LabInvestigation::sole();
        $this->assertSame('resulted', $lab->status);
        $this->assertSame('critical', $lab->resultFlag());
        // Urgent: review due 4 h after the result
        $this->assertTrue($lab->reviewDueAt()->equalTo(Carbon::parse('2026-10-05T13:00:00+08:00')));

        // Reviewed on the ward, then the lab amends the result: it has to be reviewed again
        $lab->update(['reviewed_at' => now(), 'reviewed_by_name' => 'Nurse Aisyah']);
        $results[0]['value'] = '5.9';
        $results[0]['flag'] = 'H';
        $this->withToken($token)->postJson(route('api.his.lab-investigations'), $this->hisOrder(['status' => 'resulted', 'results' => $results]))
            ->assertOk();

        $lab->refresh();
        $this->assertNull($lab->reviewed_at);
        $this->assertSame('5.9', $lab->results[0]['value']);
    }

    public function test_his_feed_rejects_unknown_patients_and_bad_values(): void
    {
        $token = $this->hisToken();

        $order = $this->hisOrder();
        $order['patient']['mrn'] = 'MRN-NOBODY';
        $this->withToken($token)->postJson(route('api.his.lab-investigations'), $order)->assertNotFound();

        $order = $this->hisOrder();
        $order['patient']['rn'] = 'RN-OTHER-EPISODE';
        $this->withToken($token)->postJson(route('api.his.lab-investigations'), $order)->assertNotFound();

        $this->withToken($token)->postJson(route('api.his.lab-investigations'), $this->hisOrder([], ['priority' => 'whenever']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('order.priority');

        $this->assertSame(0, LabInvestigation::count());
    }
}
