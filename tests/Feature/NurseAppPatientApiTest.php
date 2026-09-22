<?php

namespace Tests\Feature;

use App\Models\AdmissionLog;
use App\Models\Bed;
use App\Models\Consultant;
use App\Models\ConsultantOrder;
use App\Models\FluidBalanceEntry;
use App\Models\FluidBalancePlan;
use App\Models\FluidOverloadAssessment;
use App\Models\Hospital;
use App\Models\MedicationAdministration;
use App\Models\Nurse;
use App\Models\Patient;
use App\Models\PatientMedication;
use App\Models\ShiftSetting;
use App\Models\User;
use App\Models\VitalSign;
use App\Models\Ward;
use App\Models\WardNotification;
use App\Models\WardScheduleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class NurseAppPatientApiTest extends TestCase
{
    use RefreshDatabase;

    private Ward $ward;
    private Bed $bed;
    private Nurse $nurse;
    private Nurse $nextNurse;
    private Patient $patient;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-09-22 10:00:00')); // AM shift

        $hospital = Hospital::create(['name' => 'Test Hospital']);
        $this->ward = Ward::create(['hospital_id' => $hospital->id, 'ward_code' => 'W6', 'ward_name' => 'Ward 6', 'is_active' => true]);
        $this->bed = Bed::create(['ward_id' => $this->ward->id, 'bed_number' => 'D610', 'bed_id' => 'W6-D610', 'bed_display_name' => 'D610', 'status' => 'occupied', 'is_active' => true]);
        foreach (ShiftSetting::getDefaults() as $shift) {
            ShiftSetting::create($shift + ['ward_id' => $this->ward->id, 'is_active' => true]);
        }

        $this->nurse = $this->nurse('Aisyah Rahman', 'aisyah');
        $this->nextNurse = $this->nurse('Mei Ling', 'meiling');
        $this->token = $this->nurse->generateAppToken();

        // Aisyah has the bed this morning, Mei Ling this afternoon
        WardScheduleAssignment::create(['ward_id' => $this->ward->id, 'bed_id' => $this->bed->id, 'nurse_id' => $this->nurse->id, 'scheduled_date' => '2026-09-22', 'shift' => 'AM']);
        WardScheduleAssignment::create(['ward_id' => $this->ward->id, 'bed_id' => $this->bed->id, 'nurse_id' => $this->nextNurse->id, 'scheduled_date' => '2026-09-22', 'shift' => 'PM']);

        $this->patient = Patient::create([
            'name' => 'Siti Aminah',
            'mrn' => 'MRN900001',
            'rn' => 'RN900001',
            'ic_passport' => '800202-14-6666',
            'age' => 46,
            'gender' => 'Female',
            'phone' => '013-2224444',
            'ward_id' => $this->ward->id,
            'bed_number' => 'D610',
            'status' => 'admitted',
            'admitted_at' => Carbon::parse('2026-09-21 08:30:00'),
            'allergies' => ['Penicillin'],
            'is_active' => true,
        ]);
    }

    private function nurse(string $name, string $username): Nurse
    {
        return Nurse::create([
            'name' => $name,
            'registration_number' => 'LJM-' . crc32($name),
            'designation' => 'STAFF NURSE I',
            'ward_id' => $this->ward->id,
            'app_username' => $username,
            'app_password' => 'secret123',
            'is_active' => true,
        ]);
    }

    private function api(string $method, string $uri, array $data = [])
    {
        return $this->withToken($this->token)->json($method, '/api/nurse' . $uri, $data);
    }

    private function patientUri(string $suffix = ''): string
    {
        return '/patients/' . $this->patient->id . $suffix;
    }

    public function test_a_patient_needs_a_valid_token_and_the_nurses_ward(): void
    {
        $this->getJson('/api/nurse' . $this->patientUri())->assertUnauthorized();

        $otherWard = Ward::create(['hospital_id' => $this->ward->hospital_id, 'ward_code' => 'W7', 'ward_name' => 'Ward 7', 'is_active' => true]);
        $elsewhere = Patient::create(['name' => 'Elsewhere', 'mrn' => 'MRN2', 'rn' => 'RN2', 'ic_passport' => '700101-01-0001', 'age' => 60, 'gender' => 'Male', 'phone' => '0', 'ward_id' => $otherWard->id, 'bed_number' => 'A1', 'status' => 'admitted', 'is_active' => true]);
        $this->api('GET', '/patients/' . $elsewhere->id)->assertForbidden();

        $this->api('GET', $this->patientUri())
            ->assertOk()
            ->assertJsonPath('patient.patient.name', 'Siti Aminah')
            ->assertJsonPath('patient.patient.bed', 'D610')
            ->assertJsonPath('patient.patient.allergies.0.name', 'Penicillin')
            ->assertJsonPath('patient.orders.slots.current.nurse', 'Aisyah Rahman')
            ->assertJsonPath('patient.orders.slots.next.nurse', 'Mei Ling')
            ->assertJsonPath('patient.io.day.is_current', true)
            ->assertJsonPath('patient.badges.orders_open', 0)
            ->assertJsonStructure(['patient' => ['patient', 'vitals', 'orders', 'io', 'medications', 'infusions', 'alerts', 'badges']]);

        // A discharged patient is no longer on the nurse's list
        $this->patient->update(['status' => 'discharged']);
        $this->api('GET', $this->patientUri())->assertForbidden();
    }

    public function test_consultant_orders_can_be_added_closed_and_handed_over(): void
    {
        $consultant = Consultant::create(['name' => 'Dr. Tan Wei Liang', 'personnel_code' => 'C100', 'registration_number' => 'MMC-C100', 'is_active' => true]);

        $this->api('POST', $this->patientUri('/orders'), ['instruction' => '', 'urgency' => 'stat', 'consultant_id' => $consultant->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('instruction');

        $this->api('POST', $this->patientUri('/orders'), ['instruction' => 'Repeat FBC', 'urgency' => 'stat', 'consultant_id' => $consultant->id])
            ->assertOk()
            ->assertJsonPath('message', 'Order added for Aisyah Rahman.')
            ->assertJsonPath('patient.badges.orders_open', 1)
            ->assertJsonPath('patient.badges.orders_stat', 1)
            ->assertJsonPath('patient.orders.open.0.instruction', 'Repeat FBC')
            ->assertJsonPath('patient.orders.open.0.is_mine', true);

        $order = ConsultantOrder::firstOrFail();
        $this->assertSame($this->nurse->id, $order->assigned_nurse_id);
        $this->assertSame('AM', $order->shift_code);

        // Handed to this afternoon's nurse, and logged
        $this->api('POST', $this->patientUri('/orders/handover'), ['to' => 'next', 'note' => 'Bloods due at 15:00'])
            ->assertOk()
            ->assertJsonPath('message', '1 order passed to Mei Ling (PM).');
        $this->assertSame($this->nextNurse->id, $order->fresh()->assigned_nurse_id);
        $this->assertSame('Bloods due at 15:00', $order->handovers()->first()->note);

        $this->api('POST', $this->patientUri('/orders/' . $order->id . '/cancel'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('outcome_note');

        $this->api('POST', $this->patientUri('/orders/' . $order->id . '/complete'), ['outcome_note' => 'Hb 11.2'])
            ->assertOk()
            ->assertJsonPath('patient.badges.orders_open', 0)
            ->assertJsonPath('patient.orders.closed.0.status_label', 'Done')
            ->assertJsonPath('patient.orders.closed.0.closed_by', 'Aisyah Rahman')
            ->assertJsonPath('patient.orders.closed.0.outcome_note', 'Hb 11.2');

        $this->api('POST', $this->patientUri('/orders/' . $order->id . '/complete'))
            ->assertStatus(422)
            ->assertJsonPath('message', 'That order is already closed.');
    }

    public function test_app_actions_are_recorded_as_the_nurses_linked_user_or_an_app_only_account(): void
    {
        $this->assertNull($this->nurse->user_id);

        $this->api('POST', $this->patientUri('/io/entries'), ['direction' => 'intake', 'category' => 'oral', 'volume_ml' => 200])->assertOk();
        $this->api('POST', $this->patientUri('/io/entries'), ['direction' => 'output', 'category' => 'urine', 'volume_ml' => 350])->assertOk();

        $account = User::where('email', 'nurse-app-' . $this->nurse->id . '@smartward.invalid')->firstOrFail();
        $this->assertSame('Aisyah Rahman', $account->name);
        $this->assertSame(User::ROLE_NURSE, $account->role);
        $this->assertTrue($account->isDeactivated());
        $this->assertFalse((bool) $account->is_ldap_user);
        $this->assertSame($account->id, $this->nurse->fresh()->user_id);
        // One account, reused
        $this->assertSame(1, User::where('email', 'like', 'nurse-app-%')->count());
        $this->assertSame([$account->id], FluidBalanceEntry::pluck('recorded_by')->unique()->values()->all());

        // A nurse bound to a real user records as that user
        $real = User::factory()->create(['name' => 'Mei Ling (LDAP)', 'role' => User::ROLE_NURSE]);
        $this->nextNurse->update(['user_id' => $real->id]);
        $this->token = $this->nextNurse->generateAppToken();
        $this->api('POST', $this->patientUri('/io/entries'), ['direction' => 'intake', 'category' => 'iv', 'volume_ml' => 500])->assertOk();
        $this->assertSame($real->id, FluidBalanceEntry::latest('id')->first()->recorded_by);
        $this->assertSame(1, User::where('email', 'like', 'nurse-app-%')->count());
    }

    public function test_the_io_chart_follows_the_ward_dashboard_rules(): void
    {
        $this->api('POST', $this->patientUri('/io/entries'), ['direction' => 'intake', 'category' => 'urine', 'volume_ml' => 100])
            ->assertStatus(422)->assertJsonValidationErrors('category');
        $this->api('POST', $this->patientUri('/io/entries'), ['direction' => 'intake', 'category' => 'oral', 'volume_ml' => FluidBalanceEntry::VOLUME_MAX + 1])
            ->assertStatus(422)->assertJsonValidationErrors('volume_ml');
        $this->api('POST', $this->patientUri('/io/entries'), ['direction' => 'intake', 'category' => 'oral', 'volume_ml' => 100, 'minutes_ago' => 24 * 60 + 1])
            ->assertStatus(422)->assertJsonValidationErrors('minutes_ago');

        $this->api('POST', $this->patientUri('/io/entries'), ['direction' => 'intake', 'category' => 'oral', 'volume_ml' => 250, 'description' => 'Water', 'minutes_ago' => 30])
            ->assertOk()
            ->assertJsonPath('message', 'Intake recorded: Oral - Water, 250 mL at 09:30.')
            ->assertJsonPath('patient.io.totals.intake', 250)
            ->assertJsonPath('patient.io.totals.balance_label', '+250 mL');
        $this->assertSame('2026-09-22 09:30:00', FluidBalanceEntry::firstOrFail()->recorded_at->toDateTimeString());

        $entry = FluidBalanceEntry::firstOrFail();
        $this->api('POST', $this->patientUri('/io/entries/' . $entry->id . '/void'), [])
            ->assertStatus(422)->assertJsonValidationErrors('void_reason');
        $this->api('POST', $this->patientUri('/io/entries/' . $entry->id . '/void'), ['void_reason' => 'Wrong patient'])
            ->assertOk()
            ->assertJsonPath('patient.io.totals.intake', 0)
            ->assertJsonPath('patient.io.shifts.0.entries.0.voided', true)
            ->assertJsonPath('patient.io.shifts.0.entries.0.void_reason', 'Wrong patient');
        $this->api('POST', $this->patientUri('/io/entries/' . $entry->id . '/void'), ['void_reason' => 'Again'])
            ->assertStatus(422)->assertJsonPath('message', 'That entry is already struck out.');

        // Another patient's entry is not reachable through this patient
        $other = Patient::create(['name' => 'Other', 'mrn' => 'MRN3', 'rn' => 'RN3', 'ic_passport' => '700101-01-0002', 'age' => 50, 'gender' => 'Male', 'phone' => '0', 'ward_id' => $this->ward->id, 'bed_number' => 'D611', 'status' => 'admitted', 'is_active' => true]);
        $foreign = FluidBalanceEntry::create(['patient_id' => $other->id, 'ward_id' => $this->ward->id, 'direction' => 'intake', 'category' => 'oral', 'volume_ml' => 100, 'recorded_at' => now()]);
        $this->api('POST', $this->patientUri('/io/entries/' . $foreign->id . '/void'), ['void_reason' => 'x'])->assertNotFound();

        $this->api('POST', $this->patientUri('/io/plan'), ['intake_limit_ml' => 1500, 'urine_min_ml_per_hour' => 30])
            ->assertOk()
            ->assertJsonPath('message', 'Fluid plan set: Intake up to 1,500 mL per day, urine at least 30 mL/h.')
            ->assertJsonPath('patient.io.plan.intake_limit_ml', 1500);
        $this->api('POST', $this->patientUri('/io/plan'), ['intake_limit_ml' => 1500, 'urine_min_ml_per_hour' => 30])
            ->assertOk()->assertJsonPath('message', 'Fluid plan unchanged.');
        $this->assertSame(1, FluidBalancePlan::count());

        $this->api('POST', $this->patientUri('/io/assessments'), ['edema_grade' => 2, 'edema_sites' => ['feet_ankles'], 'signs' => ['breathless'], 'weight_kg' => 71.26])
            ->assertOk()
            ->assertJsonPath('patient.io.latest_assessment.edema', 'Edema 2+ (feet / ankles)')
            ->assertJsonPath('patient.io.latest_assessment.weight_kg', 71.3);
        $this->assertSame(['feet_ankles'], FluidOverloadAssessment::firstOrFail()->edema_sites);
    }

    public function test_doses_are_recorded_with_a_reason_when_not_given(): void
    {
        $order = PatientMedication::create([
            'patient_id' => $this->patient->id, 'ward_id' => $this->ward->id, 'medication_name' => 'Paracetamol',
            'dose_amount' => 1, 'dose_unit' => 'g', 'route' => 'PO', 'frequency' => 'qid', 'interval_minutes' => 360,
            'status' => 'active', 'start_at' => '2026-09-22 08:00:00', 'next_due_at' => '2026-09-22 08:00:00',
        ]);

        $this->api('GET', $this->patientUri())
            ->assertJsonPath('patient.badges.meds_overdue', 1)
            ->assertJsonPath('patient.medications.active.0.due_state', 'overdue');

        $this->api('POST', $this->patientUri('/medications/' . $order->id . '/doses'), ['status' => 'held'])
            ->assertStatus(422)->assertJsonValidationErrors('notes');

        $this->api('POST', $this->patientUri('/medications/' . $order->id . '/doses'), ['status' => 'given', 'minutes_ago' => 10])
            ->assertOk()
            ->assertJsonPath('message', 'Paracetamol: dose given at 09:50.')
            ->assertJsonPath('patient.badges.meds_overdue', 0)
            ->assertJsonPath('patient.medications.active.0.recent_doses.0.status', 'given')
            ->assertJsonPath('patient.medications.active.0.recent_doses.0.by', 'Aisyah Rahman');

        $this->assertSame(1, MedicationAdministration::count());
        $this->assertSame('2026-09-22 15:50:00', $order->fresh()->next_due_at->toDateTimeString());

        $order->update(['status' => 'stopped', 'stopped_at' => now()]);
        $this->api('POST', $this->patientUri('/medications/' . $order->id . '/doses'), ['status' => 'given'])
            ->assertStatus(422)->assertJsonPath('message', 'Paracetamol is no longer active.');
    }

    public function test_alerts_are_answered_once(): void
    {
        $alert = WardNotification::create(['ward_id' => $this->ward->id, 'patient_id' => $this->patient->id, 'bed_number' => 'D610', 'type' => 'patient_request', 'severity' => 'normal', 'message' => 'Needs help to the toilet', 'status' => 'pending']);

        $this->api('GET', $this->patientUri())->assertJsonPath('patient.badges.alerts_pending', 1);

        $this->api('POST', '/notifications/' . $alert->id . '/respond')
            ->assertOk()
            ->assertJsonPath('patient.badges.alerts_pending', 0)
            ->assertJsonPath('patient.alerts.recent.0.responded_by', 'Aisyah Rahman');

        $this->assertSame(WardNotification::STATUS_RESPONDED, $alert->fresh()->status);
        $this->api('POST', '/notifications/' . $alert->id . '/respond')->assertStatus(409);
    }

    public function test_the_dashboard_beds_carry_what_needs_doing(): void
    {
        $consultant = Consultant::create(['name' => 'Dr. Tan', 'personnel_code' => 'C1', 'registration_number' => 'R1', 'is_active' => true]);
        ConsultantOrder::create(['patient_id' => $this->patient->id, 'ward_id' => $this->ward->id, 'consultant_id' => $consultant->id, 'consultant_name' => 'Dr. Tan', 'instruction' => 'ECG now', 'urgency' => 'stat', 'ordered_at' => now(), 'status' => 'open']);
        WardNotification::create(['ward_id' => $this->ward->id, 'patient_id' => $this->patient->id, 'bed_number' => 'D610', 'type' => 'patient_request', 'severity' => 'normal', 'message' => 'Call', 'status' => 'pending']);

        $this->withToken($this->token)->getJson('/api/nurse/dashboard')
            ->assertOk()
            ->assertJsonPath('beds.0.patient_id', $this->patient->id)
            ->assertJsonPath('beds.0.badges.orders_open', 1)
            ->assertJsonPath('beds.0.badges.orders_stat', 1)
            ->assertJsonPath('beds.0.badges.alerts_pending', 1)
            ->assertJsonPath('summary.open_orders', 1)
            ->assertJsonPath('summary.pending_alerts', 1);
    }

    public function test_a_blood_unit_goes_through_registration_checks_start_and_finish(): void
    {
        $this->api('POST', $this->patientUri('/transfusions'), ['product_type' => 'Packed Red Cells'])
            ->assertStatus(422)->assertJsonValidationErrors('unit_number');

        $this->api('POST', $this->patientUri('/transfusions'), [
            'unit_number' => ' PRC-1 ', 'product_type' => 'Packed Red Cells', 'unit_blood_group' => 'O+', 'patient_blood_group' => 'O+',
            'crossmatch_reference' => 'XM-77', 'unit_expires_at' => '2026-09-23 23:59', 'volume_ml' => 300, 'prescribed_minutes' => 120,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Unit PRC-1 registered. Next: the bedside checks.')
            ->assertJsonPath('patient.transfusions.pending.0.unit_number', 'PRC-1')
            ->assertJsonPath('patient.transfusions.pending.0.rate', 150)
            ->assertJsonPath('patient.transfusions.pending.0.compatibility', 'compatible')
            ->assertJsonPath('patient.transfusions.pending.0.steps.0.is_next', true)
            ->assertJsonPath('patient.transfusions.pending.0.can_start', false)
            ->assertJsonPath('patient.transfusions.pending.0.registered_by', 'Aisyah Rahman')
            ->assertJsonPath('patient.badges.transfusions_pending', 1);

        $unit = \App\Models\BloodTransfusion::firstOrFail();
        $this->assertSame('2026-09-23 23:59:00', $unit->unit_expires_at->toDateTimeString());
        $uri = fn(string $what) => $this->patientUri('/transfusions/' . $unit->id . '/' . $what);
        $check = fn(string $step, string $action = 'confirm') => $this->api('POST', $uri('checklist'), ['step' => $step, 'action' => $action]);

        // In order only, and nothing starts before all four
        $check('check_product')->assertStatus(422)->assertJsonPath('message', 'Confirm the earlier checks first.');
        $this->api('POST', $uri('start'))->assertStatus(422)->assertJsonPath('message', 'Complete every pre-start check before starting this unit.');

        $check('check_crossmatch')->assertOk()->assertJsonPath('message', 'Check 1 of 4 done: Crossmatch confirmed.');
        $check('check_product')->assertOk();
        // Only the last confirmed step can be undone
        $check('check_crossmatch', 'undo')->assertStatus(422)->assertJsonPath('message', 'Only the last confirmed check can be undone.');
        $check('check_product', 'undo')->assertOk()->assertJsonPath('message', 'Check 2 undone: Product type verified. 1 of 4 done.');
        $check('check_product')->assertOk();
        $check('check_expiry')->assertOk();
        $check('check_identity')
            ->assertOk()
            ->assertJsonPath('message', 'Check 4 of 4 done: Patient and unit match.')
            ->assertJsonPath('patient.transfusions.pending.0.can_start', true)
            ->assertJsonPath('patient.transfusions.pending.0.start_note', 'All four steps confirmed. Ready to start.')
            ->assertJsonPath('patient.transfusions.pending.0.last_action_label', '10:00 by Aisyah Rahman');

        $this->api('POST', $uri('start'))
            ->assertOk()
            ->assertJsonPath('message', 'Unit PRC-1 started.')
            ->assertJsonPath('patient.transfusions.running.0.end_label', '22 Sep 12:00')
            ->assertJsonPath('patient.transfusions.running.0.limit_label', '14:00')
            ->assertJsonPath('patient.badges.transfusions_running', 1);

        $this->withToken($this->token)->getJson('/api/nurse/dashboard')
            ->assertJsonPath('beds.0.badges.transfusions_running', 1);

        // Checks are locked once running; stopping needs a reason
        $check('check_identity', 'undo')->assertStatus(422)->assertJsonPath('message', 'The checks are locked once the unit has started.');
        $this->api('POST', $uri('finish'), ['outcome' => 'stopped'])
            ->assertStatus(422)->assertJsonPath('message', 'Give a reason when stopping a unit early.');

        $this->travel(40)->minutes();
        $this->api('POST', $uri('finish'), ['outcome' => 'stopped', 'stop_reason' => 'Suspected reaction: rigors'])
            ->assertOk()
            ->assertJsonPath('message', 'Unit PRC-1 stopped.')
            ->assertJsonPath('patient.transfusions.finished.0.status', 'stopped')
            ->assertJsonPath('patient.transfusions.finished.0.stop_reason', 'Suspected reaction: rigors')
            ->assertJsonPath('patient.transfusions.finished.0.took_minutes', 40)
            ->assertJsonPath('patient.badges.transfusions_running', 0);

        $this->api('POST', $uri('finish'), ['outcome' => 'completed'])
            ->assertStatus(422)->assertJsonPath('message', 'That unit is not running.');
    }

    public function test_a_mismatched_or_expired_unit_cannot_be_started(): void
    {
        foreach ([
            ['unit' => 'MISMATCH', 'group' => 'A+', 'expires' => '2026-09-25 23:59', 'problem' => 'Blood group mismatch'],
            ['unit' => 'EXPIRED', 'group' => 'O+', 'expires' => '2026-09-21 23:59', 'problem' => 'Unit past expiry'],
        ] as $case) {
            $this->api('POST', $this->patientUri('/transfusions'), [
                'unit_number' => $case['unit'], 'product_type' => 'Packed Red Cells', 'unit_blood_group' => $case['group'],
                'patient_blood_group' => 'O+', 'crossmatch_reference' => 'XM', 'unit_expires_at' => $case['expires'],
                'volume_ml' => 300, 'prescribed_minutes' => 120,
            ])->assertOk();

            $unit = \App\Models\BloodTransfusion::where('unit_number', $case['unit'])->firstOrFail();
            foreach (['check_crossmatch', 'check_product', 'check_expiry', 'check_identity'] as $step) {
                $this->api('POST', $this->patientUri('/transfusions/' . $unit->id . '/checklist'), ['step' => $step, 'action' => 'confirm'])->assertOk();
            }

            $response = $this->api('POST', $this->patientUri('/transfusions/' . $unit->id . '/start'))
                ->assertStatus(422)
                ->assertJsonPath('message', 'Resolve the flagged problems before starting this unit.');

            $bundle = $this->api('GET', $this->patientUri())->json('patient.transfusions');
            $this->assertContains($case['problem'], collect($bundle['exceptions'])->where('unit', $case['unit'])->pluck('title')->all());
            $this->assertFalse(collect($bundle['pending'])->firstWhere('unit_number', $case['unit'])['can_start']);
            $this->assertSame('pending', $unit->fresh()->status);
        }
    }

    public function test_finished_units_are_listed_latest_finished_first(): void
    {
        $unit = fn(string $number, string $started, string $finished, string $status) => \App\Models\BloodTransfusion::create([
            'patient_id' => $this->patient->id, 'ward_id' => $this->ward->id, 'unit_number' => $number, 'product_type' => 'Platelets',
            'volume_ml' => 250, 'prescribed_minutes' => 30, 'status' => $status,
            'check_crossmatch' => true, 'check_product' => true, 'check_expiry' => true, 'check_identity' => true,
            'started_at' => Carbon::parse($started), 'completed_at' => Carbon::parse($finished),
        ]);
        // Registered first, finished last
        $unit('PLT-1', '2026-09-22 09:00', '2026-09-22 09:40', 'completed');
        $unit('PLT-2', '2026-09-21 20:00', '2026-09-21 20:30', 'stopped');

        $this->api('GET', $this->patientUri())
            ->assertOk()
            ->assertJsonPath('patient.transfusions.finished.0.unit_number', 'PLT-1')
            ->assertJsonPath('patient.transfusions.finished.1.unit_number', 'PLT-2');
    }

    public function test_the_stay_timeline_lists_events_newest_day_first(): void
    {
        $log = AdmissionLog::create(['patient_id' => $this->patient->id, 'ward_id' => $this->ward->id, 'bed_number' => 'D610', 'action' => 'admit', 'patient_name' => 'Siti Aminah', 'mrn' => 'MRN900001', 'admitted_at' => $this->patient->admitted_at, 'source' => 'manual']);
        $log->forceFill(['created_at' => $this->patient->admitted_at])->saveQuietly();
        VitalSign::create(['patient_id' => $this->patient->id, 'systolic_bp' => 120, 'diastolic_bp' => 80, 'pulse_rate' => 80, 'recorded_at' => '2026-09-22 08:00:00']);

        $this->api('GET', $this->patientUri('/timeline'))
            ->assertOk()
            ->assertJsonPath('timeline.total', 2)
            ->assertJsonPath('timeline.days.0.label', 'Day 2')
            ->assertJsonPath('timeline.days.0.events.0.title', 'Vital signs')
            ->assertJsonPath('timeline.days.1.label', 'Day 1')
            ->assertJsonPath('timeline.days.1.events.0.title', 'Admitted');
    }
}
