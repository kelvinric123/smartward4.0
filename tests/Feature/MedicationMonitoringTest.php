<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\Hospital;
use App\Models\Medication;
use App\Models\MedicationAdministration;
use App\Models\Patient;
use App\Models\PatientMedication;
use App\Models\User;
use App\Models\Ward;
use App\Models\WardDashboardSetting;
use Database\Seeders\MedicationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MedicationMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private const PASSPHRASE = 'askdrtai';

    private User $user;
    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-09-21 08:00:00'));
        $this->seed(MedicationSeeder::class);

        $this->user = User::factory()->create();
        $this->patient = Patient::create([
            'name' => 'Lim Wei Ming',
            'mrn' => 'MRN800001',
            'rn' => 'RN800001',
            'ic_passport' => '700101-10-5555',
            'age' => 56,
            'gender' => 'Male',
            'phone' => '012-3456789',
        ]);
    }

    private function enableMonitoring(?User $user = null): void
    {
        WardDashboardSetting::updateOrCreate(
            ['user_id' => ($user ?? $this->user)->id],
            ['patient_details_tabs' => ['medications' => true]]
        );
    }

    private function addMedication(array $fields = [])
    {
        return $this->actingAs($this->user)->post(route('ward.medications.store'), array_merge([
            'patient_id' => $this->patient->id,
            'active_tab' => 'medications',
            '_form' => 'add',
            'medication_id' => Medication::where('name', 'Ceftriaxone')->value('id'),
            'dose_amount' => 1,
            'dose_unit' => 'g',
            'route' => 'IV',
            'frequency' => 'tds',
            'first_dose' => 'due_now',
        ], $fields));
    }

    private function latestOrder(): PatientMedication
    {
        return PatientMedication::latest('id')->firstOrFail();
    }

    public function test_seeder_adds_thirty_common_inpatient_medications(): void
    {
        $this->assertSame(30, Medication::active()->count());

        $paracetamol = Medication::where('name', 'Paracetamol')->first();
        $this->assertSame('1 g PO QID', $paracetamol->defaultsLabel());

        $morphine = Medication::where('name', 'Morphine')->first();
        $this->assertTrue($morphine->is_high_alert);
        $this->assertSame('prn', $morphine->default_frequency);

        // Re-running it updates rather than duplicates
        $this->seed(MedicationSeeder::class);
        $this->assertSame(30, Medication::count());
    }

    public function test_monitoring_is_off_by_default_and_switched_on_per_user_in_settings(): void
    {
        $details = route('ward.patient-details', ['patient_id' => $this->patient->id]);

        $this->actingAs($this->user)->get($details)
            ->assertOk()
            ->assertDontSee('Medication Monitoring');

        $this->actingAs($this->user)->get(route('ward.settings'))
            ->assertOk()
            ->assertSee('Medication Monitoring');
        $this->assertFalse(WardDashboardSetting::where('user_id', $this->user->id)->first()->patient_details_tabs['medications']);

        $this->actingAs($this->user)->post(route('ward.settings.update'), [
            'setting_type' => 'patient_details',
            'tabs' => ['info' => 1, 'additional' => 1, 'vitals' => 1, 'medications' => 1],
        ])->assertSessionHas('settings_tab', 'patient-details');

        $this->assertTrue(WardDashboardSetting::where('user_id', $this->user->id)->first()->patient_details_tabs['medications']);

        $this->actingAs($this->user)->get($details)
            ->assertOk()
            ->assertSee('Medication Monitoring')
            ->assertSee('Add medication')
            ->assertSee('Ceftriaxone');

        // Settings are per user
        $this->actingAs(User::factory()->create())->get($details)
            ->assertOk()
            ->assertDontSee('Medication Monitoring');
    }

    public function test_added_medication_is_due_now_by_default(): void
    {
        $this->addMedication(['frequency' => 'od'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $order = $this->latestOrder();
        $this->assertSame('Ceftriaxone', $order->medication_name);
        $this->assertSame('1 g IV OD', $order->summary());
        $this->assertSame(1440, $order->interval_minutes);
        $this->assertTrue($order->next_due_at->equalTo(now()));
        $this->assertSame($this->user->id, $order->created_by);
        $this->assertSame(PatientMedication::STATUS_ACTIVE, $order->status);
    }

    public function test_first_dose_given_now_schedules_the_next_one_interval_later(): void
    {
        $this->addMedication(['first_dose' => 'given_now'])->assertSessionHasNoErrors();

        $order = $this->latestOrder();
        $this->assertCount(1, $order->administrations);
        $this->assertSame(MedicationAdministration::STATUS_GIVEN, $order->administrations->first()->status);
        $this->assertTrue($order->last_given_at->equalTo(now()));
        $this->assertTrue($order->next_due_at->equalTo(now()->addHours(8)));
        $this->assertSame('scheduled', $order->dueState());
    }

    public function test_a_set_first_dose_time_is_used(): void
    {
        $this->addMedication(['first_dose' => 'due_at', 'start_at' => '2026-09-21T14:00'])->assertSessionHasNoErrors();
        $this->assertSame('2026-09-21 14:00', $this->latestOrder()->next_due_at->format('Y-m-d H:i'));

        $this->addMedication(['first_dose' => 'due_at', 'start_at' => null])->assertSessionHasErrors('start_at');
        $this->addMedication(['first_dose' => 'due_at', 'start_at' => '2026-09-19T08:00'])->assertSessionHasErrors('start_at');
    }

    public function test_dose_is_due_soon_then_overdue_once_the_interval_has_passed(): void
    {
        $this->addMedication(['first_dose' => 'given_now']);
        $order = $this->latestOrder();

        $this->travelTo(now()->addHours(7)->addMinutes(40));
        $this->assertSame('due_soon', $order->dueState());
        $this->assertSame('Due in 20m', $order->dueLabel());

        $this->travelTo(Carbon::parse('2026-09-21 17:05:00'));
        $this->assertSame('overdue', $order->dueState());
        $this->assertSame('Overdue 1h 05m', $order->dueLabel());

        $alerts = PatientMedication::alertsForPatients([$this->patient->id]);
        $this->assertSame(1, $alerts[$this->patient->id]['overdue']);
        $this->assertSame('Ceftriaxone', $alerts[$this->patient->id]['items'][0]['name']);
    }

    public function test_recording_a_dose_clears_the_warning_and_restarts_the_interval(): void
    {
        $this->addMedication();
        $order = $this->latestOrder();

        $this->travel(90)->minutes();
        $this->assertSame('overdue', $order->fresh()->dueState());

        $this->actingAs($this->user)->post(route('ward.medications.administer', $order), [
            'active_tab' => 'medications',
            'status' => 'given',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $order->refresh();
        $dose = $order->administrations()->first();
        $this->assertSame(90, $dose->minutesLate());
        $this->assertSame($this->user->id, $dose->recorded_by);
        $this->assertTrue($order->next_due_at->equalTo(now()->addHours(8)));
        $this->assertSame('scheduled', $order->dueState());
        $this->assertSame(0, PatientMedication::alertsForPatients([$this->patient->id])[$this->patient->id]['overdue']);
    }

    public function test_held_or_refused_dose_needs_a_reason_and_answers_the_due_dose(): void
    {
        $this->addMedication();
        $order = $this->latestOrder();

        $this->actingAs($this->user)->post(route('ward.medications.administer', $order), ['status' => 'held'])
            ->assertSessionHasErrors('notes');
        $this->assertSame(0, $order->administrations()->count());

        $this->actingAs($this->user)->post(route('ward.medications.administer', $order), [
            'status' => 'held',
            'notes' => 'NBM for OT',
        ])->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertNull($order->last_given_at, 'a held dose is not a given dose');
        $this->assertTrue($order->next_due_at->equalTo(now()->addHours(8)));
    }

    public function test_an_earlier_time_can_be_recorded_but_not_a_future_one(): void
    {
        $this->addMedication();
        $order = $this->latestOrder();

        $this->actingAs($this->user)->post(route('ward.medications.administer', $order), [
            'status' => 'given',
            'administered_at' => now()->addHour()->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors('administered_at');

        $this->actingAs($this->user)->post(route('ward.medications.administer', $order), [
            'status' => 'given',
            'administered_at' => now()->subHours(2)->format('Y-m-d\TH:i'),
        ])->assertSessionHasNoErrors();

        $this->assertTrue($order->fresh()->next_due_at->equalTo(now()->subHours(2)->addHours(8)));
    }

    public function test_prn_is_never_overdue_and_keeps_its_minimum_gap(): void
    {
        $this->addMedication([
            'medication_id' => Medication::where('name', 'Morphine')->value('id'),
            'dose_amount' => 2.5,
            'dose_unit' => 'mg',
            'frequency' => 'prn',
            'interval_hours' => 4,
            'first_dose' => 'given_now',
        ])->assertSessionHasNoErrors();

        $order = $this->latestOrder();
        $this->assertTrue($order->is_high_alert);
        $this->assertNull($order->next_due_at);
        $this->assertSame('PRN, at least 4 h apart', $order->frequencyLabel());
        $this->assertSame('PRN - not before 12:00', $order->dueLabel());

        $this->travelTo(Carbon::parse('2026-09-22 08:00:00'));
        $this->assertSame('prn', $order->dueState());
        $this->assertSame('PRN - when required', $order->dueLabel());
        $this->assertSame(0, PatientMedication::alertsForPatients([$this->patient->id])[$this->patient->id]['overdue']);
    }

    public function test_stat_dose_is_urgent_until_given_then_complete(): void
    {
        $this->addMedication(['frequency' => 'stat']);
        $order = $this->latestOrder();

        $this->travel(1)->minutes();
        $this->assertSame('overdue', $order->dueState());
        $this->assertSame('STAT - give now', $order->dueLabel());

        $this->actingAs($this->user)->post(route('ward.medications.administer', $order), ['status' => 'given'])
            ->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame(PatientMedication::STATUS_COMPLETED, $order->status);
        $this->assertNull($order->next_due_at);
        $this->assertSame([], PatientMedication::alertsForPatients([$this->patient->id]));
    }

    public function test_custom_interval_and_a_medication_typed_in(): void
    {
        $this->addMedication([
            'medication_id' => null,
            'medication_name' => 'Vitamin B Complex',
            'dose_amount' => 1,
            'dose_unit' => 'tab',
            'route' => 'PO',
            'frequency' => 'custom',
            'interval_hours' => 36,
        ])->assertSessionHasNoErrors();

        $order = $this->latestOrder();
        $this->assertNull($order->medication_id);
        $this->assertSame('Vitamin B Complex', $order->medication_name);
        $this->assertSame(2160, $order->interval_minutes);
        $this->assertSame('1 tab PO Q36H', $order->summary());

        $this->addMedication(['frequency' => 'custom'])->assertSessionHasErrors('interval_hours');
        $this->addMedication(['medication_id' => null])->assertSessionHasErrors('medication_name');
        $this->addMedication(['route' => 'EAR'])->assertSessionHasErrors('route');
        $this->addMedication(['dose_amount' => 0])->assertSessionHasErrors('dose_amount');
    }

    public function test_stopped_medication_needs_a_reason_and_is_no_longer_monitored(): void
    {
        $this->addMedication();
        $order = $this->latestOrder();

        $this->actingAs($this->user)->post(route('ward.medications.stop', $order), [])
            ->assertSessionHasErrors('stop_reason');

        $this->actingAs($this->user)->post(route('ward.medications.stop', $order), ['stop_reason' => 'Course completed'])
            ->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame(PatientMedication::STATUS_STOPPED, $order->status);
        $this->assertNull($order->next_due_at);
        $this->assertSame($this->user->id, $order->stopped_by);

        $this->actingAs($this->user)->post(route('ward.medications.administer', $order), ['status' => 'given'])
            ->assertSessionHas('error');
        $this->assertSame(0, $order->administrations()->count());
        $this->assertSame([], PatientMedication::alertsForPatients([$this->patient->id]));
    }

    public function test_undo_needs_the_passphrase_and_puts_the_schedule_back(): void
    {
        $this->addMedication(['first_dose' => 'given_now']); // 08:00, next due 16:00
        $order = $this->latestOrder();
        $first = $order->administrations()->first();

        $this->travelTo(Carbon::parse('2026-09-21 09:00:00'));
        $this->actingAs($this->user)->post(route('ward.medications.administer', $order), ['status' => 'given']);
        $mistake = $order->administrations()->latest('id')->first();
        $this->assertSame('2026-09-21 17:00', $order->fresh()->next_due_at->format('Y-m-d H:i'));

        $this->actingAs($this->user)->post(route('ward.medications.undo', $mistake), [])
            ->assertSessionHas('error');
        $this->assertSame(2, $order->administrations()->count());

        // Only the latest record can be undone
        $this->actingAs($this->user)->post(route('ward.medications.undo', $first), ['delete_passphrase' => self::PASSPHRASE])
            ->assertSessionHas('error');

        $this->actingAs($this->user)->post(route('ward.medications.undo', $mistake), ['delete_passphrase' => self::PASSPHRASE])
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertSame(1, $order->administrations()->count());
        $this->assertSame('2026-09-21 16:00', $order->next_due_at->format('Y-m-d H:i'));
    }

    public function test_patient_details_shows_the_warning_and_opens_on_the_requested_tab(): void
    {
        $this->enableMonitoring();
        $this->addMedication();
        $this->travel(2)->hours();

        $this->actingAs($this->user)
            ->get(route('ward.patient-details', ['patient_id' => $this->patient->id, 'open_tab' => 'medications']))
            ->assertOk()
            ->assertSee('"activeTab":"medications"', false)
            ->assertSee('Overdue 2h 00m')
            ->assertSee('Record dose');

        // A form just posted from another tab still returns to that tab
        $this->actingAs($this->user)
            ->withSession(['_old_input' => ['active_tab' => 'additional']])
            ->get(route('ward.patient-details', ['patient_id' => $this->patient->id, 'open_tab' => 'medications']))
            ->assertSee('"activeTab":"additional"', false);

        // ...even when the page URL still names the tab an earlier save returned to
        $this->actingAs($this->user)
            ->withSession(['_old_input' => ['active_tab' => 'transfusion']])
            ->get(route('ward.patient-details', ['patient_id' => $this->patient->id, 'active_tab' => 'additional']))
            ->assertSee('"activeTab":"transfusion"', false);
    }

    public function test_every_medication_action_comes_back_to_the_medications_tab(): void
    {
        $this->enableMonitoring();
        $this->addMedication(['first_dose' => 'given_now']);
        $order = $this->latestOrder();

        // The page was last loaded by another tab's save, so its URL still names that tab
        $page = route('ward.patient-details', ['patient_id' => $this->patient->id, 'active_tab' => 'additional']);
        $submit = fn (string $route, $target, array $fields) => $this->actingAs($this->user)
            ->from($page)
            ->followingRedirects()
            ->post(route($route, $target), $fields + ['active_tab' => 'medications'])
            ->assertOk()
            ->assertSee('"activeTab":"medications"', false);

        // Add: saved, and not saved (the form opens again with the reason)
        $submit('ward.medications.store', [], [
            'patient_id' => $this->patient->id,
            '_form' => 'add',
            'medication_id' => Medication::where('name', 'Paracetamol')->value('id'),
            'dose_amount' => 1, 'dose_unit' => 'g', 'route' => 'PO', 'frequency' => 'qid', 'first_dose' => 'due_now',
        ])->assertSee('Paracetamol 1 g PO QID added');
        $submit('ward.medications.store', [], ['patient_id' => $this->patient->id, '_form' => 'add', 'first_dose' => 'due_now'])
            ->assertSee('Not saved');

        // Record: saved, and not saved
        $submit('ward.medications.administer', $order, ['_form' => 'record-' . $order->id, 'status' => 'given'])
            ->assertSee('Ceftriaxone: given at 08:00');
        $submit('ward.medications.administer', $order, ['_form' => 'record-' . $order->id, 'status' => 'refused'])
            ->assertSee('Give a reason when a dose is held or refused.');

        // Undo with a wrong passphrase, then stop
        $submit('ward.medications.undo', $order->administrations()->latest('id')->first(), ['delete_passphrase' => 'wrong'])
            ->assertSee('Undo failed');
        $submit('ward.medications.stop', $order, ['_form' => 'stop-' . $order->id, 'stop_reason' => 'Course completed'])
            ->assertSee('Ceftriaxone stopped.');
    }

    public function test_dashboard_flags_overdue_doses_only_for_users_who_switched_monitoring_on(): void
    {
        $hospital = Hospital::create(['name' => 'Test Hospital']);
        $ward = Ward::create(['hospital_id' => $hospital->id, 'ward_code' => 'MW1', 'ward_name' => 'Medical Ward 1', 'is_active' => true]);
        Bed::create(['ward_id' => $ward->id, 'bed_number' => 'B01', 'bed_id' => 'MW1-B01', 'bed_display_name' => 'B01', 'status' => 'available', 'is_active' => true]);
        $this->patient->update([
            'ward_id' => $ward->id,
            'bed_number' => 'B01',
            'status' => 'admitted',
            'is_active' => true,
            'admitted_at' => now()->subDay(),
        ]);

        $this->addMedication();
        $this->travel(45)->minutes();

        $dashboard = route('ward.dashboard', ['ward_id' => $ward->id]);
        $chip = 'data-status-popover="med_' . $this->patient->id . '"';

        $this->actingAs($this->user)->get($dashboard)
            ->assertOk()
            ->assertDontSee($chip, false)
            ->assertDontSee('dose overdue');

        $this->enableMonitoring();

        $this->actingAs($this->user)->get($dashboard)
            ->assertOk()
            ->assertSee($chip, false)
            ->assertSee('1 dose overdue')
            ->assertSee('Overdue 45m')
            ->assertSee('Open Medications');
    }
}
