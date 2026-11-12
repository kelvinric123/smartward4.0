<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\Hospital;
use App\Models\Nurse;
use App\Models\NurseHandover;
use App\Models\Patient;
use App\Models\ShiftSetting;
use App\Models\VitalSign;
use App\Models\Ward;
use App\Models\WardScheduleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class NurseHandoverApiTest extends TestCase
{
    use RefreshDatabase;

    private Ward $ward;
    private Bed $bed;
    private Bed $otherBed;
    private Patient $patient;
    private Patient $otherPatient;
    private Nurse $amNurse;
    private Nurse $pmNurse;
    private Nurse $otherNurse;

    protected function setUp(): void
    {
        parent::setUp();

        // 13:30 — late in the AM shift, the PM shift starts at 14:00
        $this->travelTo(Carbon::parse('2026-10-06 13:30:00'));

        $hospital = Hospital::create(['name' => 'Test Hospital']);
        $this->ward = Ward::create([
            'hospital_id' => $hospital->id,
            'ward_code' => 'W3A',
            'ward_name' => 'Ward 3A',
        ]);
        foreach (ShiftSetting::getDefaults() as $shift) {
            ShiftSetting::create($shift + ['ward_id' => $this->ward->id, 'is_active' => true]);
        }

        $this->bed = $this->makeBed('12A');
        $this->otherBed = $this->makeBed('12B');
        $this->patient = $this->makePatient('Tan Wei Ming', '12A');
        $this->otherPatient = $this->makePatient('Siti Nurhaliza', '12B');

        $this->amNurse = $this->makeNurse('Sr. Maria Lim');
        $this->pmNurse = $this->makeNurse('Sr. Hannah Tan');
        $this->otherNurse = $this->makeNurse('Sr. Priya Devi');

        // AM: Maria has 12A. PM: Hannah has 12A, Priya has 12B.
        $this->assign($this->amNurse, $this->bed, 'AM');
        $this->assign($this->pmNurse, $this->bed, 'PM');
        $this->assign($this->otherNurse, $this->otherBed, 'PM');

        VitalSign::create([
            'patient_id' => $this->patient->id,
            'pulse_rate' => 112,
            'systolic_bp' => 152,
            'diastolic_bp' => 94,
            'spo2' => 93,
            'respiratory_rate' => 22,
            'temperature' => 38.4,
            'recorded_at' => now()->subHours(2),
        ]);
        VitalSign::create([
            'patient_id' => $this->patient->id,
            'pulse_rate' => 118,
            'systolic_bp' => 150,
            'diastolic_bp' => 92,
            'spo2' => 92,
            'respiratory_rate' => 24,
            'temperature' => 38.6,
            'recorded_at' => now()->subHour(),
        ]);
    }

    public function test_next_shift_follows_current_shift()
    {
        $next = ShiftSetting::getNextShift($this->ward->id);
        $this->assertSame('PM', $next['shift']->shift_code);
        $this->assertSame('2026-10-06 14:00', $next['starts_at']->format('Y-m-d H:i'));

        $overnight = ShiftSetting::getNextShift($this->ward->id, Carbon::parse('2026-10-06 23:30:00'));
        $this->assertSame('AM', $overnight['shift']->shift_code);
        $this->assertSame('2026-10-07 07:00', $overnight['starts_at']->format('Y-m-d H:i'));

        // Past midnight the night shift started the day before
        $night = ShiftSetting::getCurrentPeriod($this->ward->id, Carbon::parse('2026-10-07 03:00:00'));
        $this->assertSame('ON', $night['shift']->shift_code);
        $this->assertSame('2026-10-06 23:00', $night['starts_at']->format('Y-m-d H:i'));
        $this->assertSame('2026-10-07 07:00', $night['ends_at']->format('Y-m-d H:i'));

        $previous = ShiftSetting::getPreviousShift($this->ward->id, Carbon::parse('2026-10-06 14:20:00'));
        $this->assertSame('AM', $previous['shift']->shift_code);
        $this->assertSame('2026-10-06 14:00', $previous['ends_at']->format('Y-m-d H:i'));
    }

    public function test_dashboard_includes_vitals_history_oldest_first()
    {
        $response = $this->withToken($this->amNurse->generateAppToken())
            ->getJson('/api/nurse/dashboard')
            ->assertOk();

        $bed = $response->json('beds.0');
        $this->assertSame($this->patient->id, $bed['patient_id']);
        $this->assertCount(2, $bed['vitals_history']);
        $this->assertSame(112, $bed['vitals_history'][0]['pulse_rate']);
        $this->assertSame(118, $bed['vitals_history'][1]['pulse_rate']);
        $this->assertSame(118, $bed['vitals']['pulse_rate']);
        $this->assertArrayHasKey('ews', $bed['vitals_history'][0]);
    }

    public function test_handover_goes_to_next_shift_nurse_who_can_receive_it()
    {
        $amToken = $this->amNurse->generateAppToken();
        $pmToken = $this->pmNurse->generateAppToken();

        $state = $this->withToken($amToken)->getJson('/api/nurse/handovers')->assertOk();
        $state->assertJsonPath('from_shift.shift_code', 'AM');
        $state->assertJsonPath('to_shift.shift_code', 'PM');
        $state->assertJsonPath('suggested_receivers.0.patient_id', $this->patient->id);
        $state->assertJsonPath('suggested_receivers.0.nurse.id', $this->pmNurse->id);

        $this->withToken($amToken)->postJson('/api/nurse/handovers', [
            'items' => [[
                'patient_id' => $this->patient->id,
                'condition_status' => 'deteriorating',
                'patient_condition' => 'Febrile, tachycardic.',
                'nursing_plan' => 'Hourly vitals.',
            ]],
        ])->assertOk()
            ->assertJsonPath('handed_over', 1)
            ->assertJsonPath('outgoing.0.to_nurse.id', $this->pmNurse->id)
            ->assertJsonPath('outgoing.0.status', 'pending');

        $handover = NurseHandover::sole();
        $this->assertSame('AM', $handover->from_shift);
        $this->assertSame('PM', $handover->to_shift);
        $this->assertSame('2026-10-06', $handover->to_shift_date->toDateString());
        $this->assertNotNull($handover->ews);

        // Incoming nurse sees it before their shift starts...
        $this->withToken($pmToken)->getJson('/api/nurse/handovers')
            ->assertOk()
            ->assertJsonCount(1, 'incoming')
            ->assertJsonPath('incoming.0.patient_condition', 'Febrile, tachycardic.')
            ->assertJsonPath('incoming.0.from_nurse.id', $this->amNurse->id);

        // ...but a nurse it was not addressed to does not
        $this->withToken($this->otherNurse->generateAppToken())->getJson('/api/nurse/handovers')
            ->assertOk()
            ->assertJsonCount(0, 'incoming');

        $this->withToken($pmToken)->postJson('/api/nurse/handovers/receive', ['ids' => [$handover->id]])
            ->assertOk()
            ->assertJsonPath('received', 1)
            ->assertJsonPath('incoming.0.status', 'received')
            ->assertJsonPath('incoming.0.received_by.id', $this->pmNurse->id);

        $this->withToken($amToken)->getJson('/api/nurse/handovers')
            ->assertOk()
            ->assertJsonPath('outgoing.0.status', 'received');
    }

    public function test_handover_state_resets_every_shift()
    {
        $this->withToken($this->amNurse->generateAppToken())->postJson('/api/nurse/handovers', [
            'items' => [['patient_id' => $this->patient->id, 'patient_condition' => 'Day 1 handover.']],
        ])->assertOk();

        // Next day, same roster: Maria on AM, Hannah on PM
        $this->travelTo(Carbon::parse('2026-10-07 10:00:00'));
        $this->assign($this->amNurse, $this->bed, 'AM');
        $this->assign($this->pmNurse, $this->bed, 'PM');

        $this->withToken($this->amNurse->generateAppToken())->getJson('/api/nurse/handovers')
            ->assertOk()
            ->assertJsonPath('from_shift.shift_code', 'AM')
            ->assertJsonPath('to_shift.shift_code', 'PM')
            ->assertJsonPath('patients.0.patient_id', $this->patient->id)
            ->assertJsonCount(0, 'outgoing');

        // Yesterday's pending handover is not today's: a new one is created
        $this->withToken($this->amNurse->generateAppToken())->postJson('/api/nurse/handovers', [
            'items' => [['patient_id' => $this->patient->id, 'patient_condition' => 'Day 2 handover.']],
        ])->assertOk()->assertJsonCount(1, 'outgoing');
        $this->assertSame(2, NurseHandover::count());

        $this->withToken($this->pmNurse->generateAppToken())->getJson('/api/nurse/handovers')
            ->assertOk()
            ->assertJsonCount(1, 'incoming')
            ->assertJsonPath('incoming.0.patient_condition', 'Day 2 handover.');
    }

    public function test_nurse_can_still_hand_over_shortly_after_shift_ends()
    {
        // 14:20 — the AM shift ended 20 minutes ago, PM is running
        $this->travelTo(Carbon::parse('2026-10-06 14:20:00'));
        $amToken = $this->amNurse->generateAppToken();

        $this->withToken($amToken)->getJson('/api/nurse/handovers')
            ->assertOk()
            ->assertJsonPath('from_shift.shift_code', 'AM')
            ->assertJsonPath('to_shift.shift_code', 'PM')
            ->assertJsonPath('to_shift.started', true)
            ->assertJsonPath('patients.0.patient_id', $this->patient->id)
            ->assertJsonPath('patients.0.from_previous_shift', true)
            ->assertJsonPath('suggested_receivers.0.nurse.id', $this->pmNurse->id);

        $this->withToken($amToken)->postJson('/api/nurse/handovers', [
            'items' => [['patient_id' => $this->patient->id, 'patient_condition' => 'Late handover.']],
        ])->assertOk()->assertJsonPath('outgoing.0.to_nurse.id', $this->pmNurse->id);

        $this->withToken($this->pmNurse->generateAppToken())->getJson('/api/nurse/handovers')
            ->assertOk()
            ->assertJsonCount(1, 'incoming');

        // After the grace period the patient can no longer be handed over
        $this->travelTo(Carbon::parse('2026-10-06 15:10:00'));
        $this->withToken($amToken)->getJson('/api/nurse/handovers')
            ->assertOk()
            ->assertJsonCount(0, 'patients');
        $this->withToken($amToken)->postJson('/api/nurse/handovers', [
            'items' => [['patient_id' => $this->patient->id, 'patient_condition' => 'Too late.']],
        ])->assertForbidden();
    }

    public function test_resubmitting_a_pending_handover_updates_it()
    {
        $token = $this->amNurse->generateAppToken();
        $payload = fn($plan) => ['items' => [[
            'patient_id' => $this->patient->id,
            'nursing_plan' => $plan,
        ]]];

        $this->withToken($token)->postJson('/api/nurse/handovers', $payload('First plan'))->assertOk();
        $this->withToken($token)->postJson('/api/nurse/handovers', $payload('Revised plan'))->assertOk();

        $this->assertSame(1, NurseHandover::count());
        $this->assertSame('Revised plan', NurseHandover::sole()->nursing_plan);
    }

    public function test_explicit_receiver_overrides_roster()
    {
        $this->withToken($this->amNurse->generateAppToken())->postJson('/api/nurse/handovers', [
            'to_nurse_id' => $this->otherNurse->id,
            'items' => [['patient_id' => $this->patient->id, 'patient_condition' => 'Stable.']],
        ])->assertOk();

        $this->assertSame($this->otherNurse->id, NurseHandover::sole()->to_nurse_id);

        // The rostered PM nurse cannot receive a handover addressed to someone else
        $this->withToken($this->pmNurse->generateAppToken())
            ->postJson('/api/nurse/handovers/receive', ['ids' => [NurseHandover::sole()->id]])
            ->assertOk()
            ->assertJsonPath('received', 0);
        $this->assertTrue(NurseHandover::sole()->isPending());
    }

    public function test_unrostered_bed_handover_is_open_to_ward_nurses()
    {
        WardScheduleAssignment::where('nurse_id', $this->pmNurse->id)->delete();
        $wardNurse = $this->makeNurse('Sr. Daniel Wong');
        $wardNurse->update(['ward_id' => $this->ward->id]);

        $this->withToken($this->amNurse->generateAppToken())->postJson('/api/nurse/handovers', [
            'items' => [['patient_id' => $this->patient->id, 'nursing_plan' => 'Turn 2-hourly.']],
        ])->assertOk()->assertJsonPath('outgoing.0.to_nurse', null);

        $token = $wardNurse->generateAppToken();
        $this->withToken($token)->getJson('/api/nurse/handovers')
            ->assertOk()
            ->assertJsonCount(1, 'incoming');
        $this->withToken($token)
            ->postJson('/api/nurse/handovers/receive', ['ids' => [NurseHandover::sole()->id]])
            ->assertJsonPath('received', 1);

        // Once received, it no longer shows for other ward nurses
        $this->withToken($this->pmNurse->generateAppToken())->getJson('/api/nurse/handovers')
            ->assertJsonCount(0, 'incoming');
    }

    public function test_cannot_hand_over_unassigned_patient_or_empty_note()
    {
        $token = $this->amNurse->generateAppToken();

        $this->withToken($token)->postJson('/api/nurse/handovers', [
            'items' => [['patient_id' => $this->otherPatient->id, 'patient_condition' => 'Stable.']],
        ])->assertForbidden();

        $this->withToken($token)->postJson('/api/nurse/handovers', [
            'items' => [['patient_id' => $this->patient->id, 'patient_condition' => '  ', 'nursing_plan' => '']],
        ])->assertStatus(422);

        $this->withToken($token)->postJson('/api/nurse/handovers', [
            'to_nurse_id' => $this->amNurse->id,
            'items' => [['patient_id' => $this->patient->id, 'patient_condition' => 'Stable.']],
        ])->assertStatus(422);

        $this->assertSame(0, NurseHandover::count());
    }

    public function test_handover_endpoints_require_a_token()
    {
        $this->getJson('/api/nurse/handovers')->assertUnauthorized();
        $this->postJson('/api/nurse/handovers', [])->assertUnauthorized();
        $this->postJson('/api/nurse/handovers/receive', [])->assertUnauthorized();
    }

    private function makeBed(string $number): Bed
    {
        return Bed::create([
            'ward_id' => $this->ward->id,
            'bed_number' => $number,
            'bed_id' => 'W3A-' . $number,
            'bed_display_name' => 'Bed ' . $number,
            'status' => 'occupied',
            'is_active' => true,
        ]);
    }

    private function makePatient(string $name, string $bedNumber): Patient
    {
        return Patient::create([
            'name' => $name,
            'mrn' => 'MRN-' . $bedNumber,
            'rn' => 'RN-' . $bedNumber,
            'ic_passport' => 'IC-' . $bedNumber,
            'phone' => '0123456789',
            'is_active' => true,
            'ward_id' => $this->ward->id,
            'bed_number' => $bedNumber,
            'status' => Patient::STATUS_ADMITTED,
            'admitted_at' => now()->subDays(2),
        ]);
    }

    private function makeNurse(string $name): Nurse
    {
        return Nurse::create([
            'name' => $name,
            'registration_number' => 'RN-' . md5($name),
            'is_active' => true,
            'ward_id' => null,
        ]);
    }

    private function assign(Nurse $nurse, Bed $bed, string $shift): void
    {
        WardScheduleAssignment::create([
            'ward_id' => $this->ward->id,
            'bed_id' => $bed->id,
            'nurse_id' => $nurse->id,
            'scheduled_date' => now()->toDateString(),
            'shift' => $shift,
        ]);
    }
}
