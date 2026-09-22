<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\BloodTransfusion;
use App\Models\Consultant;
use App\Models\ConsultantOrder;
use App\Models\Hospital;
use App\Models\Nurse;
use App\Models\NursingCarePlanItem;
use App\Models\Patient;
use App\Models\PatientMedication;
use App\Models\PatientMovement;
use App\Models\ShiftSetting;
use App\Models\SugarReading;
use App\Models\User;
use App\Models\VitalSign;
use App\Models\Ward;
use App\Models\WardDashboardSetting;
use App\Models\WardScheduleAssignment;
use App\Services\NursingPlan\NursingCarePlan;
use App\Services\NursingPlan\ShiftTasks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The Nursing Plan: the ward dashboard tab (Patient Details) and the nurse
 * app's twin, over the same care plan (NursingCarePlan) and the same shift
 * tasks (ShiftTasks).
 */
class NursingPlanTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Ward $ward;
    private Nurse $nurse;
    private Patient $patient;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-09-22 10:00:00')); // AM shift, 07:00 - 14:00

        $this->user = User::factory()->create(['name' => 'Sister Farah']);
        $hospital = Hospital::create(['name' => 'Test Hospital']);
        $this->ward = Ward::create(['hospital_id' => $hospital->id, 'ward_code' => 'W6', 'ward_name' => 'Ward 6', 'is_active' => true]);
        $bed = Bed::create(['ward_id' => $this->ward->id, 'bed_number' => 'D610', 'bed_id' => 'W6-D610', 'bed_display_name' => 'D610', 'status' => 'occupied', 'is_active' => true]);
        foreach (ShiftSetting::getDefaults() as $shift) {
            ShiftSetting::create($shift + ['ward_id' => $this->ward->id, 'is_active' => true]);
        }

        $this->nurse = Nurse::create([
            'name' => 'Aisyah Rahman',
            'registration_number' => 'LJM-1001',
            'designation' => 'STAFF NURSE I',
            'ward_id' => $this->ward->id,
            'app_username' => 'aisyah',
            'app_password' => 'secret123',
            'is_active' => true,
        ]);
        $this->token = $this->nurse->generateAppToken();
        WardScheduleAssignment::create(['ward_id' => $this->ward->id, 'bed_id' => $bed->id, 'nurse_id' => $this->nurse->id, 'scheduled_date' => '2026-09-22', 'shift' => 'AM']);

        $this->patient = $this->admit('Siti Aminah', 'MRN900001', 'D610', ['fall_risk' => 'high']);
    }

    private function admit(string $name, string $mrn, string $bed, array $extra = []): Patient
    {
        return Patient::create($extra + [
            'name' => $name,
            'mrn' => $mrn,
            'rn' => 'RN' . $mrn,
            'ic_passport' => '800202-14-' . substr($mrn, -4),
            'age' => 46,
            'gender' => 'Female',
            'phone' => '013-2224444',
            'ward_id' => $this->ward->id,
            'bed_number' => $bed,
            'status' => 'admitted',
            'admitted_at' => Carbon::parse('2026-09-21 08:30:00'),
            'is_active' => true,
        ]);
    }

    private function tabUrl(): string
    {
        return route('ward.patient-details', ['patient_id' => $this->patient->id, 'active_tab' => 'nursing_plan']);
    }

    private function api(string $method, string $suffix, array $data = [])
    {
        return $this->withToken($this->token)->json($method, '/api/nurse/patients/' . $this->patient->id . $suffix, $data);
    }

    private function medication(string $name, string $frequency, int $intervalMinutes, string $nextDue): PatientMedication
    {
        return PatientMedication::create([
            'patient_id' => $this->patient->id,
            'ward_id' => $this->ward->id,
            'medication_name' => $name,
            'dose_amount' => 1,
            'dose_unit' => 'g',
            'route' => 'PO',
            'frequency' => $frequency,
            'interval_minutes' => $intervalMinutes,
            'status' => PatientMedication::STATUS_ACTIVE,
            'start_at' => Carbon::parse('2026-09-22 06:00:00'),
            'next_due_at' => Carbon::parse($nextDue),
        ]);
    }

    // ------------------------------------------------------ ward dashboard

    public function test_the_tab_sits_after_consultant_orders_with_this_shift_and_the_care_plan(): void
    {
        $this->medication('Paracetamol', 'qid', 360, '2026-09-22 09:30:00');
        $consultant = Consultant::create(['name' => 'Dr. Tan Wei Liang', 'personnel_code' => 'C100', 'registration_number' => 'MMC-C100', 'is_active' => true]);
        ConsultantOrder::create([
            'patient_id' => $this->patient->id, 'ward_id' => $this->ward->id, 'consultant_id' => $consultant->id,
            'consultant_name' => $consultant->name, 'instruction' => 'Repeat FBC', 'urgency' => 'stat',
            'ordered_at' => Carbon::parse('2026-09-22 09:00:00'), 'status' => 'open', 'created_by' => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->get(route('ward.patient-details', ['patient_id' => $this->patient->id]))
            ->assertOk()
            ->assertSeeInOrder(['Consultant Orders', 'Nursing Plan', 'Transfer Bed'])
            // The badge counts what is overdue this shift
            ->assertSee('1 overdue this shift')
            ->assertSee('Morning shift 07:00 - 14:00')
            ->assertSeeInOrder(['This shift', '09:30', 'Paracetamol', 'Overdue', 'During the shift', 'Repeat FBC'])
            ->assertSeeInOrder(['Nursing care plan', 'Suggested from the record', 'Risk for falls', 'Fall risk on record: High'])
            ->assertSee('No nursing diagnosis in the plan yet');
    }

    public function test_a_diagnosis_is_added_evaluated_edited_and_closed_from_the_tab(): void
    {
        $web = $this->actingAs($this->user);

        $web->post(route('ward.nursing-plan.store', $this->patient), ['template_key' => 'falls'])
            ->assertRedirect($this->tabUrl())
            ->assertSessionHas('success', '"Risk for falls" added to the care plan.');

        $item = NursingCarePlanItem::firstOrFail();
        $this->assertSame('safety', $item->category);
        $this->assertSame('Impaired mobility, medication or confusion', $item->related_to);
        $this->assertCount(6, $item->interventions);
        $this->assertSame($this->user->id, $item->created_by);
        $this->assertSame($this->ward->id, $item->ward_id);

        // The same diagnosis twice is refused
        $web->post(route('ward.nursing-plan.store', $this->patient), ['template_key' => 'falls'])
            ->assertRedirect($this->tabUrl())
            ->assertSessionHas('error', 'Risk for falls is already in the plan.');

        // From a template with fields cleared on purpose: they stay cleared
        $web->post(route('ward.nursing-plan.store', $this->patient), [
            'template_key' => 'pain', 'diagnosis' => 'Acute pain', 'related_to' => '', 'goal' => 'Pain 3/10 or less', 'interventions_text' => '',
        ])->assertRedirect($this->tabUrl());
        $pain = NursingCarePlanItem::where('template_key', 'pain')->firstOrFail();
        $this->assertNull($pain->related_to);
        $this->assertSame([], $pain->interventions);
        $this->assertSame('Pain 3/10 or less', $pain->goal);

        // In the nurse's own words a goal is needed; the error shows on this tab
        $web->post(route('ward.nursing-plan.store', $this->patient), ['diagnosis' => 'Impaired physical mobility'])
            ->assertRedirect($this->tabUrl())
            ->assertSessionHasErrors(['goal'], null, 'nursingPlan');
        $web->from($this->tabUrl())->followingRedirects()
            ->post(route('ward.nursing-plan.store', $this->patient), ['diagnosis' => 'Impaired physical mobility'])
            ->assertSee('Write the goal for this diagnosis.');

        // Evaluating: "not met" needs a note, and a shift is evaluated once
        $evaluate = fn(array $data) => $web->post(route('ward.nursing-plan.evaluate', $item), $data);
        $evaluate(['outcome' => 'not_met'])->assertSessionHasErrors(['note'], null, 'nursingPlan');
        $evaluate(['outcome' => 'partly_met', 'note' => 'Needed reminding'])
            ->assertSessionHas('success', '"Risk for falls" evaluated: Partly met.');
        $evaluate(['outcome' => 'met'])->assertRedirect($this->tabUrl());

        $this->assertSame(1, $item->evaluations()->count());
        $evaluation = $item->evaluations()->first();
        $this->assertSame('met', $evaluation->outcome);
        $this->assertNull($evaluation->note);
        $this->assertSame('AM', $evaluation->shift_code);
        $this->assertSame('2026-09-22', $evaluation->shift_date->toDateString());
        $this->assertSame($this->user->id, $evaluation->evaluated_by);

        // The afternoon shift evaluates again
        $this->travelTo(Carbon::parse('2026-09-22 15:00:00'));
        $evaluate(['outcome' => 'not_met', 'note' => 'Got up alone once'])->assertRedirect($this->tabUrl());
        $this->assertSame(['PM', 'AM'], $item->evaluations()->pluck('shift_code')->all());

        // Editing the goal and interventions (a line each, blanks dropped)
        $web->post(route('ward.nursing-plan.update', $item), [
            'related_to' => 'Post-op right hip',
            'goal' => 'No fall',
            'interventions_text' => "Hourly rounding\r\n\r\n  Call bell within reach  ",
        ])->assertSessionHas('success', '"Risk for falls" updated.');
        $item->refresh();
        $this->assertSame('No fall', $item->goal);
        $this->assertSame('Post-op right hip', $item->related_to);
        $this->assertSame(['Hourly rounding', 'Call bell within reach'], $item->interventions);

        // Closing: discontinuing needs a reason
        $close = fn(array $data) => $web->post(route('ward.nursing-plan.close', $item), $data);
        $close(['status' => 'discontinued'])->assertSessionHasErrors(['note'], null, 'nursingPlan');
        $close(['status' => 'resolved', 'note' => 'Mobilising safely'])->assertSessionHas('success', '"Risk for falls" resolved.');
        $item->refresh();
        $this->assertSame(NursingCarePlanItem::STATUS_RESOLVED, $item->status);
        $this->assertSame($this->user->id, $item->resolved_by);
        $this->assertSame('Mobilising safely', $item->resolve_note);
        $evaluate(['outcome' => 'met'])->assertSessionHas('error', 'That diagnosis is closed.');

        // It stays on record, and the diagnosis can be added again
        $web->get($this->tabUrl())
            ->assertOk()
            ->assertSee('Closed this stay (1)')
            ->assertSee('Mobilising safely');
        $web->post(route('ward.nursing-plan.store', $this->patient), ['template_key' => 'falls'])
            ->assertSessionHas('success');
    }

    public function test_the_tab_can_be_switched_off_per_user_in_settings(): void
    {
        $this->actingAs($this->user)->get(route('ward.settings'))
            ->assertOk()
            ->assertSee('Nursing Plan')
            ->assertSee('toggle-nursing_plan', false);

        $keep = ['info', 'additional', 'vitals', 'io', 'movement', 'careprovider', 'anaesthetist', 'nurses', 'infusion', 'transfer', 'discharge', 'discharge_summary'];
        $this->actingAs($this->user)
            ->post(route('ward.settings.update'), ['setting_type' => 'patient_details', 'tabs' => array_fill_keys($keep, '1')])
            ->assertRedirect();

        $tabs = WardDashboardSetting::where('user_id', $this->user->id)->value('patient_details_tabs');
        $this->assertFalse($tabs['nursing_plan']);
        $this->assertTrue($tabs['discharge_summary']);

        $this->actingAs($this->user)
            ->get(route('ward.patient-details', ['patient_id' => $this->patient->id]))
            ->assertOk()
            ->assertDontSee('Nursing Plan')
            ->assertSee('Discharge Summary');
    }

    // ----------------------------------------------------------- nurse app

    public function test_the_app_works_the_same_plan_and_counts_what_is_due(): void
    {
        $this->api('GET', '')
            ->assertOk()
            ->assertJsonPath('patient.nursing_plan.shift.shift.code', 'AM')
            ->assertJsonPath('patient.nursing_plan.shift.shift.time', '07:00 - 14:00')
            ->assertJsonPath('patient.nursing_plan.shift.shift.nurse', 'Aisyah Rahman')
            ->assertJsonPath('patient.nursing_plan.care_plan.suggestions.0.key', 'falls')
            ->assertJsonPath('patient.nursing_plan.care_plan.active', [])
            ->assertJsonPath('patient.badges.care_plan_due', 0);

        $added = $this->api('POST', '/care-plan', ['template_key' => 'falls'])
            ->assertOk()
            ->assertJsonPath('message', '"Risk for falls" added to the care plan.')
            ->assertJsonPath('patient.nursing_plan.care_plan.active.0.diagnosis', 'Risk for falls')
            ->assertJsonPath('patient.nursing_plan.care_plan.active.0.evaluated_this_shift', false)
            ->assertJsonPath('patient.nursing_plan.care_plan.active.0.created_by', 'Aisyah Rahman')
            ->assertJsonPath('patient.nursing_plan.care_plan.due_evaluations', 1)
            ->assertJsonPath('patient.badges.care_plan_due', 1);
        $this->assertNotContains('falls', array_column($added->json('patient.nursing_plan.care_plan.suggestions'), 'key'));
        $evaluateTask = collect($added->json('patient.nursing_plan.shift.standing'))->firstWhere('title', 'Evaluate: Risk for falls');
        $this->assertSame('plan', $evaluateTask['tab']);

        $this->api('POST', '/care-plan', ['template_key' => 'falls'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Risk for falls is already in the plan.');
        $this->api('POST', '/care-plan', ['diagnosis' => 'Impaired physical mobility'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('goal');

        $item = NursingCarePlanItem::firstOrFail();
        $uri = fn(string $what) => '/care-plan/' . $item->id . '/' . $what;

        $this->api('POST', $uri('evaluate'), ['outcome' => 'not_met'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('note');
        $this->api('POST', $uri('evaluate'), ['outcome' => 'met', 'note' => 'Called for help each time'])
            ->assertOk()
            ->assertJsonPath('message', '"Risk for falls" evaluated: Met.')
            ->assertJsonPath('patient.nursing_plan.care_plan.active.0.latest.outcome', 'met')
            ->assertJsonPath('patient.nursing_plan.care_plan.active.0.latest.by', 'Aisyah Rahman')
            ->assertJsonPath('patient.nursing_plan.care_plan.active.0.latest.shift_label', 'AM')
            ->assertJsonPath('patient.nursing_plan.care_plan.active.0.evaluated_this_shift', true)
            ->assertJsonPath('patient.badges.care_plan_due', 0);
        // Recorded as the nurse's app account, like every other app action
        $this->assertSame($item->evaluations()->first()->evaluated_by, $item->fresh()->created_by);

        $this->api('POST', $uri('update'), ['goal' => 'No fall', 'related_to' => null, 'interventions' => ['Hourly rounding', ' ', 'Hourly rounding', 'Bed at its lowest']])
            ->assertOk()
            ->assertJsonPath('patient.nursing_plan.care_plan.active.0.goal', 'No fall')
            ->assertJsonPath('patient.nursing_plan.care_plan.active.0.related_to', null)
            ->assertJsonPath('patient.nursing_plan.care_plan.active.0.interventions', ['Hourly rounding', 'Bed at its lowest']);

        $this->api('POST', $uri('close'), ['status' => 'discontinued'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('note');
        $this->api('POST', $uri('close'), ['status' => 'discontinued', 'note' => 'Moved to a low bed'])
            ->assertOk()
            ->assertJsonPath('message', '"Risk for falls" discontinued.')
            ->assertJsonPath('patient.nursing_plan.care_plan.active', [])
            ->assertJsonPath('patient.nursing_plan.care_plan.closed.0.status', 'discontinued')
            ->assertJsonPath('patient.nursing_plan.care_plan.closed.0.resolve_note', 'Moved to a low bed');
        $this->api('POST', $uri('evaluate'), ['outcome' => 'met'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'That diagnosis is closed.');

        // Another patient's diagnosis cannot be reached through this patient
        $other = $this->admit('Mei Hua', 'MRN900002', 'D611');
        $otherItem = NursingCarePlan::add($other, ['template_key' => 'pain'], null);
        $this->api('POST', '/care-plan/' . $otherItem->id . '/evaluate', ['outcome' => 'met'])->assertNotFound();
        $this->assertSame(0, $otherItem->evaluations()->count());
    }

    public function test_this_shifts_tasks_come_from_the_record(): void
    {
        $this->patient->update([
            'hgt_enabled' => true,
            'hgt_frequency' => SugarReading::FREQUENCY_TDS,
            'expected_discharge_at' => Carbon::parse('2026-09-22 13:30:00'),
        ]);
        $this->medication('Paracetamol', 'qid', 360, '2026-09-22 09:30:00'); // overdue; next at 15:30, after the shift
        $this->medication('Amoxicillin', 'tds', 480, '2026-09-22 11:00:00'); // due; next at 19:00
        ConsultantOrder::create([
            'patient_id' => $this->patient->id, 'ward_id' => $this->ward->id, 'consultant_name' => 'Dr. Tan',
            'instruction' => 'Chest X-ray today', 'urgency' => 'urgent', 'ordered_at' => Carbon::parse('2026-09-22 08:00:00'),
            'status' => 'open', 'created_by' => $this->user->id,
        ]);
        SugarReading::create(['patient_id' => $this->patient->id, 'value' => 7.8, 'frequency' => 'tds', 'recorded_by' => $this->user->id, 'recorded_at' => Carbon::parse('2026-09-22 05:00:00')]);
        PatientMovement::create([
            'patient_id' => $this->patient->id, 'ward_id' => $this->ward->id, 'bed_number' => 'D610',
            'location' => 'Radiology', 'location_type' => 'Imaging', 'scheduled_at' => Carbon::parse('2026-09-22 12:30:00'), 'status' => 'scheduled',
        ]);
        BloodTransfusion::create([
            'patient_id' => $this->patient->id, 'ward_id' => $this->ward->id, 'unit_number' => 'PRC-9', 'product_type' => 'Packed Red Cells',
            'unit_blood_group' => 'O+', 'patient_blood_group' => 'O+', 'crossmatch_reference' => 'XM-9',
            'unit_expires_at' => Carbon::parse('2026-09-21 23:59:00'), 'volume_ml' => 300, 'prescribed_minutes' => 120,
            'check_crossmatch' => true, 'check_product' => true, 'check_expiry' => true, 'check_identity' => true,
            'status' => BloodTransfusion::STATUS_PENDING, 'created_by' => $this->user->id,
        ]);
        VitalSign::create(['patient_id' => $this->patient->id, 'systolic_bp' => 124, 'diastolic_bp' => 78, 'pulse_rate' => 82, 'temperature' => 36.9, 'spo2' => 97, 'respiratory_rate' => 16, 'recorded_at' => Carbon::parse('2026-09-22 04:00:00')]);
        NursingCarePlan::add($this->patient, ['template_key' => 'falls'], $this->user->id);

        $shift = ShiftTasks::forPatient($this->patient->fresh());

        $this->assertSame('Morning', $shift['shift']['name']);
        $this->assertSame('07:00 - 14:00', $shift['shift']['time']);

        // Timed, in time order: the overdue dose first, nothing after the shift ends
        $timed = collect($shift['timed']);
        $this->assertSame(['09:30', '11:00', '12:30', '13:00', '13:30'], $timed->pluck('time_label')->all());
        $this->assertSame(['medication', 'medication', 'movement', 'hgt', 'discharge'], $timed->pluck('category')->all());
        $this->assertSame([true, false, false, false, false], $timed->pluck('overdue')->all());
        $this->assertSame('Overdue', $timed[0]['badge']);
        $this->assertStringStartsWith('Paracetamol', $timed[0]['title']);
        $this->assertSame('To Radiology (Imaging)', $timed[2]['title']);
        $this->assertStringContainsString('last 7.8 mmol/L', $timed[3]['detail']);

        // For the whole shift, worst first
        $standing = collect($shift['standing'])->keyBy('title');
        $this->assertSame('critical', $shift['standing'][0]['tone']);
        $this->assertSame('Unit PRC-9 on hold: Unit past expiry', $shift['standing'][0]['title']);
        $this->assertSame('warning', $standing['Chest X-ray today']['tone']);
        $this->assertSame('orders', $standing['Chest X-ray today']['tab']);
        $this->assertSame('plan', $standing['Evaluate: Risk for falls']['tab']);
        $this->assertStringContainsString('6 h ago', $standing['Vital signs']['detail']);
        $this->assertSame('warning', $standing['Vital signs']['tone']);

        $this->assertSame(1, $shift['counts']['overdue']);
        $this->assertSame($timed->count() + $standing->count(), $shift['counts']['total']);
    }
}
