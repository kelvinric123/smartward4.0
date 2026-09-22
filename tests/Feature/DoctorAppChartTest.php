<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\Consultant;
use App\Models\ConsultantOrder;
use App\Models\FluidBalanceEntry;
use App\Models\FluidBalancePlan;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\PatientMedication;
use App\Models\User;
use App\Models\Ward;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DoctorAppChartTest extends TestCase
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

    private function makePatient(string $name, string $bed, Consultant $consultant): Patient
    {
        static $n = 0;
        $n++;

        return Patient::create([
            'name' => $name, 'mrn' => 'MRN92000' . $n, 'rn' => 'RN92000' . $n, 'ic_passport' => '600101-10-60' . $n,
            'age' => 67, 'gender' => 'Female', 'phone' => '012-9876543', 'consultant_id' => $consultant->id,
            'ward_id' => $this->ward->id, 'bed_number' => $bed, 'status' => 'admitted', 'is_active' => true,
            'admitted_at' => Carbon::parse('2026-09-22 07:30:00'),
        ]);
    }

    private function api(string $method, string $uri, array $data = [])
    {
        return $this->withToken($this->token)->json($method, $uri, $data);
    }

    private function order(array $overrides = []): ConsultantOrder
    {
        return ConsultantOrder::create($overrides + [
            'patient_id' => $this->patient->id, 'ward_id' => $this->ward->id, 'consultant_id' => $this->consultant->id,
            'consultant_name' => $this->consultant->name, 'instruction' => 'Repeat FBC at 06:00', 'urgency' => 'routine',
            'ordered_at' => now(), 'status' => ConsultantOrder::STATUS_OPEN,
        ]);
    }

    private function seedChart(): void
    {
        FluidBalanceEntry::create(['patient_id' => $this->patient->id, 'ward_id' => $this->ward->id, 'direction' => 'intake', 'category' => 'oral', 'volume_ml' => 400, 'description' => 'Water', 'recorded_at' => now()->subHour(), 'recorded_by' => $this->nurseUser->id]);
        FluidBalanceEntry::create(['patient_id' => $this->patient->id, 'ward_id' => $this->ward->id, 'direction' => 'output', 'category' => 'urine', 'volume_ml' => 250, 'recorded_at' => now()->subMinutes(30), 'recorded_by' => $this->nurseUser->id]);
        FluidBalancePlan::create(['patient_id' => $this->patient->id, 'ward_id' => $this->ward->id, 'intake_limit_ml' => 1500, 'set_by' => $this->nurseUser->id]);

        $medication = PatientMedication::create([
            'patient_id' => $this->patient->id, 'ward_id' => $this->ward->id, 'medication_name' => 'Ceftriaxone',
            'dose_amount' => 1, 'dose_unit' => 'g', 'route' => 'IV', 'infusion_volume_ml' => 100, 'frequency' => 'od',
            'interval_minutes' => 1440, 'status' => 'active', 'start_at' => now()->subHours(2), 'next_due_at' => now()->subHours(2),
        ]);
        $medication->record('given', now()->subHour(), null, $this->nurseUser->id);

        $this->order();
        $this->order(['consultant_id' => $this->colleague->id, 'consultant_name' => $this->colleague->name, 'instruction' => 'Chest X-ray', 'urgency' => 'urgent']);
        $this->order(['instruction' => 'ECG', 'status' => ConsultantOrder::STATUS_DONE, 'closed_at' => now(), 'closed_by' => $this->nurseUser->id, 'outcome_note' => 'Sinus rhythm']);
    }

    public function test_the_chart_needs_a_valid_token_and_a_patient_under_care(): void
    {
        $this->getJson("/api/doctor/patients/{$this->patient->id}/chart")->assertStatus(401);
        $this->withToken('not-a-token')->getJson("/api/doctor/patients/{$this->patient->id}/chart")->assertStatus(401);

        $elsewhere = $this->makePatient('Lim Ah Kow', 'B02', $this->colleague);
        $this->api('GET', "/api/doctor/patients/{$elsewhere->id}/chart")
            ->assertStatus(403)
            ->assertJsonPath('message', 'This patient is not under your care.');
        $this->api('POST', "/api/doctor/patients/{$elsewhere->id}/orders", ['instruction' => 'Anything', 'urgency' => 'stat'])->assertStatus(403);
        $this->assertSame(0, ConsultantOrder::count());
    }

    public function test_the_chart_has_the_io_the_medications_and_the_orders(): void
    {
        $this->seedChart();

        $this->api('GET', "/api/doctor/patients/{$this->patient->id}/chart")
            ->assertOk()
            ->assertJsonPath('chart.patient.name', 'Tan Mei Ling')
            // 400 mL water and the 100 mL the IV dose was given in
            ->assertJsonPath('chart.io.totals.intake', 500)
            ->assertJsonPath('chart.io.totals.output', 250)
            ->assertJsonPath('chart.io.totals.balance', 250)
            ->assertJsonPath('chart.io.day.label', 'Today, 07:00 to 07:00')
            ->assertJsonPath('chart.io.plan.intake_limit_ml', 1500)
            ->assertJsonPath('chart.io.plan.from_order', false)
            ->assertJsonPath('chart.io.limit.remaining', 1000)
            ->assertJsonPath('chart.io.entries.0.type_label', 'Urine')
            ->assertJsonPath('chart.io.entries.1.auto', 'medication dose')
            ->assertJsonPath('chart.io.entries.1.description', 'Ceftriaxone 1 g IV in 100 mL')
            ->assertJsonPath('chart.medications.active.0.name', 'Ceftriaxone')
            ->assertJsonPath('chart.medications.active.0.io_volume_ml', 100)
            ->assertJsonPath('chart.medications.active.0.doses.0.status_label', 'Given')
            ->assertJsonPath('chart.medications.active.0.doses.0.by', 'Nurse Aina')
            ->assertJsonCount(2, 'chart.orders.open')
            ->assertJsonPath('chart.orders.closed.0.outcome_note', 'Sinus rhythm')
            ->assertJsonPath('chart.badges.orders_open', 2);

        $orders = collect($this->api('GET', "/api/doctor/patients/{$this->patient->id}/chart")->json('chart.orders.open'))->keyBy('instruction');
        $this->assertTrue($orders['Repeat FBC at 06:00']['can_cancel']);
        $this->assertFalse($orders['Chest X-ray']['is_mine']);
        $this->assertFalse($orders['Chest X-ray']['can_cancel']);
    }

    public function test_an_earlier_io_day_can_be_opened(): void
    {
        $this->travelTo(Carbon::parse('2026-09-23 10:00:00'));
        $this->api('GET', "/api/doctor/patients/{$this->patient->id}/chart?io_day=2026-09-22")
            ->assertOk()
            ->assertJsonPath('chart.io.day.key', '2026-09-22')
            ->assertJsonPath('chart.io.day.is_current', false)
            ->assertJsonPath('chart.io.day.next', '2026-09-23');
    }

    public function test_writing_an_order_with_a_fluid_restriction_updates_the_io_plan(): void
    {
        $this->api('POST', "/api/doctor/patients/{$this->patient->id}/orders", ['instruction' => '', 'urgency' => 'stat'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['instruction' => 'Write the order.']);
        $this->api('POST', "/api/doctor/patients/{$this->patient->id}/orders", ['instruction' => 'Restrict', 'urgency' => 'routine', 'fluid_limit_ml' => 50])
            ->assertStatus(422)
            ->assertJsonValidationErrors('fluid_limit_ml');

        $this->api('POST', "/api/doctor/patients/{$this->patient->id}/orders", [
            'instruction' => 'Fluid restrict 1.2 L/day, strict I/O', 'urgency' => 'urgent',
            'fluid_limit_ml' => 1200, 'urine_min_ml_per_hour' => 30,
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Order sent. No nurse is rostered to this bed for the shift on now, so it waits unassigned. The fluid plan on the I/O chart now follows it.')
            ->assertJsonPath('chart.orders.open.0.instruction', 'Fluid restrict 1.2 L/day, strict I/O')
            ->assertJsonPath('chart.orders.open.0.fluid_restriction', 'Fluid restriction 1,200 mL per day, urine at least 30 mL/h')
            ->assertJsonPath('chart.io.plan.intake_limit_ml', 1200)
            ->assertJsonPath('chart.io.plan.urine_min_ml_per_hour', 30)
            ->assertJsonPath('chart.io.plan.from_order', true);

        $order = ConsultantOrder::sole();
        $this->assertSame($this->consultant->id, $order->consultant_id);
        $this->assertSame('Dr. Tan Wei Liang', $order->consultant_name);
        $this->assertSame('urgent', $order->urgency);
        $this->assertNull($order->created_by);
        $this->assertSame(ConsultantOrder::STATUS_OPEN, $order->status);
    }

    public function test_only_the_consultants_own_open_orders_can_be_cancelled(): void
    {
        $mine = $this->order(['fluid_limit_ml' => 1000]);
        $theirs = $this->order(['consultant_id' => $this->colleague->id, 'consultant_name' => $this->colleague->name]);
        $this->assertSame(1000, FluidBalancePlan::currentFor($this->patient)->intake_limit_ml);

        $this->api('POST', "/api/doctor/patients/{$this->patient->id}/orders/{$theirs->id}/cancel", ['reason' => 'Not needed'])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Only the consultant who wrote an order can cancel it from the app.');
        $this->api('POST', "/api/doctor/patients/{$this->patient->id}/orders/{$mine->id}/cancel", ['reason' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason' => 'Give a reason for cancelling the order.']);

        $this->api('POST', "/api/doctor/patients/{$this->patient->id}/orders/{$mine->id}/cancel", ['reason' => 'Oedema settled'])
            ->assertOk()
            ->assertJsonPath('message', 'Order cancelled.')
            ->assertJsonPath('chart.orders.closed.0.status_label', 'Cancelled')
            // The restriction is lifted; the plan says why
            ->assertJsonPath('chart.io.plan.summary', 'No limits')
            ->assertJsonPath('chart.io.plan.from_order', false);

        $mine->refresh();
        $this->assertSame(ConsultantOrder::STATUS_CANCELLED, $mine->status);
        $this->assertSame('Cancelled by Dr. Tan Wei Liang in the doctor app: Oedema settled', $mine->outcome_note);
        $this->assertNull(FluidBalancePlan::currentFor($this->patient)->intake_limit_ml);

        $this->api('POST', "/api/doctor/patients/{$this->patient->id}/orders/{$mine->id}/cancel", ['reason' => 'Again'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'That order is already closed.');

        // An order is only reachable under its own patient
        $other = $this->makePatient('Siti Aminah', 'B03', $this->consultant);
        $this->api('POST', "/api/doctor/patients/{$other->id}/orders/{$theirs->id}/cancel", ['reason' => 'x'])->assertStatus(404);
    }

    public function test_the_dashboard_shows_real_medications_orders_and_io(): void
    {
        $this->seedChart();

        $response = $this->api('GET', '/api/doctor/dashboard')->assertOk();
        $bed = collect($response->json('beds'))->firstWhere('patient_id', $this->patient->id);

        $this->assertSame(2, $bed['pending_orders']);
        $this->assertSame(2, $response->json('summary.pending_orders'));
        $this->assertStringStartsWith('Ceftriaxone 1 g IV OD · Due in', $bed['active_medications'][0]);
        $this->assertSame(['active' => 1, 'overdue' => 0, 'due_soon' => 0], $bed['medication_counts']);
        $this->assertSame(500, $bed['io']['intake']);
        $this->assertSame(250, $bed['io']['output']);
        $this->assertSame(1500, $bed['io']['limit']['limit']);
    }
}
