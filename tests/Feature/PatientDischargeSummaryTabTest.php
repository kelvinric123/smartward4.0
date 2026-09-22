<?php

namespace Tests\Feature;

use App\Models\AdmissionLog;
use App\Models\Bed;
use App\Models\BloodTransfusion;
use App\Models\ClinicalIndicator;
use App\Models\ClinicalIndicatorScore;
use App\Models\Consultant;
use App\Models\ConsultantNote;
use App\Models\ConsultantOrder;
use App\Models\FluidBalanceEntry;
use App\Models\Hospital;
use App\Models\Nurse;
use App\Models\Patient;
use App\Models\PatientCareProvider;
use App\Models\PatientMedication;
use App\Models\PatientMovement;
use App\Models\ShiftSetting;
use App\Models\SugarReading;
use App\Models\User;
use App\Models\VitalSign;
use App\Models\Ward;
use App\Models\WardDashboardSetting;
use App\Models\WardNotification;
use App\Models\WardScheduleAssignment;
use App\Services\DischargeSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PatientDischargeSummaryTabTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Ward $ward;
    private Bed $bed;
    private Bed $otherBed;
    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-09-21 10:00:00'));

        $this->user = User::factory()->create(['name' => 'Nurse Manager']);
        $hospital = Hospital::create(['name' => 'Test Hospital']);
        $this->ward = Ward::create(['hospital_id' => $hospital->id, 'ward_code' => 'W6', 'ward_name' => 'Ward 6', 'is_active' => true]);
        $this->bed = Bed::create(['ward_id' => $this->ward->id, 'bed_number' => 'D610', 'bed_id' => 'W6-D610', 'bed_display_name' => 'D610', 'status' => 'occupied', 'is_active' => true]);
        $this->otherBed = Bed::create(['ward_id' => $this->ward->id, 'bed_number' => 'D611', 'bed_id' => 'W6-D611', 'bed_display_name' => 'D611', 'status' => 'available', 'is_active' => true]);
        foreach (ShiftSetting::getDefaults() as $shift) {
            ShiftSetting::create($shift + ['ward_id' => $this->ward->id, 'is_active' => true]);
        }

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
            'admitted_at' => Carbon::parse('2026-09-19 08:30:00'),
            'allergies' => ['Penicillin', ['allergen' => 'LATEX^Latex', 'status' => 'Resolved']],
            'fall_risk' => 'high',
            'is_active' => true,
        ]);
    }

    /** A log row filed at $at, as the ward dashboard would have filed it then. */
    private function log(string $action, string $at, array $attributes = []): AdmissionLog
    {
        $log = AdmissionLog::create($attributes + [
            'patient_id' => $this->patient->id,
            'ward_id' => $this->ward->id,
            'user_id' => $this->user->id,
            'bed_number' => 'D610',
            'action' => $action,
            'patient_name' => $this->patient->name,
            'mrn' => $this->patient->mrn,
            'source' => 'manual',
        ]);
        $log->forceFill(['created_at' => Carbon::parse($at), 'updated_at' => Carbon::parse($at)])->saveQuietly();

        return $log;
    }

    private function admit(string $at = '2026-09-19 08:30:00'): AdmissionLog
    {
        return $this->log('admit', $at, ['admitted_at' => Carbon::parse($at), 'consultant_name' => 'Dr. Tan Wei Liang']);
    }

    private function nurse(string $name): Nurse
    {
        return Nurse::create(['name' => $name, 'registration_number' => 'LJM-' . crc32($name), 'designation' => 'STAFF NURSE I', 'is_active' => true]);
    }

    private function panel(array $query = [])
    {
        return $this->actingAs($this->user)
            ->get(route('ward.discharge-summary.panel', ['patient' => $this->patient->id] + $query));
    }

    private function summary(): array
    {
        $this->actingAs($this->user);
        $service = app(DischargeSummaryService::class);

        return $service->summaryForEpisode($service->admissionsFor($this->patient)->first()->episode);
    }

    /** Every timeline event, flattened, as "category: title". */
    private function timelineTitles(array $summary): array
    {
        return collect($summary['timeline']['days'])
            ->reject(fn(array $block) => $block['empty'])
            ->flatMap(fn(array $block) => $block['events'])
            ->map(fn(array $event) => $event['category'] . ': ' . $event['title'])
            ->values()
            ->all();
    }

    public function test_the_tab_sits_after_discharge_and_builds_the_summary_only_when_opened(): void
    {
        $this->admit();

        $this->actingAs($this->user)
            ->get(route('ward.patient-details', ['patient_id' => $this->patient->id]))
            ->assertOk()
            ->assertSeeInOrder(['Transfer Bed', 'Discharge', 'Discharge Summary'])
            ->assertSee('dischargeSummaryTab(', false)
            ->assertSee('\/ward-dashboard\/patients\/' . $this->patient->id . '\/discharge-summary', false)
            // The summary itself is fetched when the tab opens, not built with the page
            ->assertDontSee('Clinical timeline');
    }

    public function test_the_tab_can_be_switched_off_per_user_in_settings(): void
    {
        $this->admit();

        $this->actingAs($this->user)->get(route('ward.settings'))
            ->assertOk()
            ->assertSee('Discharge Summary')
            ->assertSee('toggle-discharge_summary', false);

        $keep = ['info', 'additional', 'vitals', 'io', 'movement', 'careprovider', 'anaesthetist', 'nurses', 'infusion', 'transfer', 'discharge'];
        $this->actingAs($this->user)
            ->post(route('ward.settings.update'), [
                'setting_type' => 'patient_details',
                'tabs' => array_fill_keys($keep, '1'),
            ])
            ->assertRedirect();

        $tabs = WardDashboardSetting::where('user_id', $this->user->id)->value('patient_details_tabs');
        $this->assertFalse($tabs['discharge_summary']);
        $this->assertTrue($tabs['discharge']);

        $this->actingAs($this->user)
            ->get(route('ward.patient-details', ['patient_id' => $this->patient->id]))
            ->assertOk()
            ->assertDontSee('Discharge Summary')
            ->assertDontSee('dischargeSummaryTab(', false);
    }

    public function test_an_open_admission_lists_every_kind_of_event_in_order(): void
    {
        $admission = $this->admit();
        $consultant = Consultant::create(['name' => 'Dr. Tan Wei Liang', 'personnel_code' => 'C100', 'registration_number' => 'MMC-C100', 'is_active' => true]);
        $this->patient->update(['consultant_id' => $consultant->id]);

        // Before the admission: never part of it
        VitalSign::create(['patient_id' => $this->patient->id, 'systolic_bp' => 200, 'diastolic_bp' => 100, 'pulse_rate' => 130, 'recorded_at' => '2026-09-18 22:00:00']);

        // Day 1 (19 Sep)
        PatientCareProvider::create(['patient_id' => $this->patient->id, 'role' => 'attending', 'doctor_code' => 'C100', 'doctor_name' => 'TAN', 'consultant_id' => $consultant->id, 'source' => 'manual', 'assigned_at' => '2026-09-19 09:00:00', 'is_active' => true]);
        $paracetamol = PatientMedication::create([
            'patient_id' => $this->patient->id, 'ward_id' => $this->ward->id, 'medication_name' => 'Paracetamol',
            'dose_amount' => 1, 'dose_unit' => 'g', 'route' => 'PO', 'frequency' => 'qid', 'interval_minutes' => 360,
            'status' => 'active', 'start_at' => '2026-09-19 09:30:00', 'next_due_at' => '2026-09-19 09:30:00', 'created_by' => $this->user->id,
        ]);
        $paracetamol->forceFill(['created_at' => '2026-09-19 09:30:00'])->saveQuietly();
        VitalSign::create(['patient_id' => $this->patient->id, 'systolic_bp' => 88, 'diastolic_bp' => 50, 'pulse_rate' => 125, 'temperature' => 38.4, 'spo2' => 93, 'respiratory_rate' => 24, 'recorded_at' => '2026-09-19 10:00:00', 'recorded_by' => $this->user->id]);
        $paracetamol->record('given', Carbon::parse('2026-09-19 10:15:00'), null, $this->user->id);
        FluidBalanceEntry::create(['patient_id' => $this->patient->id, 'ward_id' => $this->ward->id, 'direction' => 'intake', 'category' => 'oral', 'volume_ml' => 500, 'description' => 'Water', 'recorded_at' => '2026-09-19 12:00:00', 'recorded_by' => $this->user->id]);
        FluidBalanceEntry::create(['patient_id' => $this->patient->id, 'ward_id' => $this->ward->id, 'direction' => 'intake', 'category' => 'iv', 'volume_ml' => 1000, 'recorded_at' => '2026-09-19 13:00:00', 'recorded_by' => $this->user->id, 'voided_at' => '2026-09-19 13:05:00', 'void_reason' => 'Wrong patient']);
        FluidBalanceEntry::create(['patient_id' => $this->patient->id, 'ward_id' => $this->ward->id, 'direction' => 'output', 'category' => 'urine', 'volume_ml' => 300, 'recorded_at' => '2026-09-19 14:00:00', 'recorded_by' => $this->user->id]);

        // Day 2 (20 Sep)
        SugarReading::create(['patient_id' => $this->patient->id, 'value' => 3.2, 'recorded_at' => '2026-09-20 06:00:00', 'recorded_by' => $this->user->id]);
        // The indicator library is synced into the table by a migration
        $gcs = ClinicalIndicator::firstOrCreate(['code' => 'GCS'], ['name' => 'Glasgow Coma Scale', 'is_active' => true]);
        ClinicalIndicatorScore::create(['patient_id' => $this->patient->id, 'clinical_indicator_id' => $gcs->id, 'ward_id' => $this->ward->id, 'score' => 15, 'band_label' => 'Normal', 'band_tone' => 'low', 'recorded_at' => '2026-09-20 07:00:00', 'recorded_by' => $this->user->id]);
        ConsultantOrder::create(['patient_id' => $this->patient->id, 'ward_id' => $this->ward->id, 'consultant_id' => $consultant->id, 'consultant_name' => 'Dr. Tan Wei Liang', 'instruction' => 'Repeat FBC', 'urgency' => 'stat', 'ordered_at' => '2026-09-20 08:00:00', 'status' => 'done', 'closed_at' => '2026-09-20 11:00:00', 'closed_by' => $this->user->id, 'outcome_note' => 'Hb 11.2', 'created_by' => $this->user->id]);
        $paracetamol->record('held', Carbon::parse('2026-09-20 08:30:00'), 'Patient asleep', $this->user->id);
        BloodTransfusion::create(['patient_id' => $this->patient->id, 'ward_id' => $this->ward->id, 'unit_number' => 'U123', 'product_type' => 'Packed Red Cells', 'unit_blood_group' => 'O+', 'patient_blood_group' => 'O+', 'volume_ml' => 280, 'status' => 'completed', 'started_at' => '2026-09-20 09:30:00', 'completed_at' => '2026-09-20 12:30:00', 'created_by' => $this->user->id])
            ->forceFill(['created_at' => '2026-09-20 09:00:00'])->saveQuietly();
        PatientMovement::create(['patient_id' => $this->patient->id, 'ward_id' => $this->ward->id, 'bed_number' => 'D610', 'location' => 'Radiology', 'location_type' => 'radiology', 'scheduled_at' => '2026-09-20 14:00:00', 'sent_at' => '2026-09-20 14:00:00', 'returned_at' => '2026-09-20 15:00:00', 'status' => 'returned'])
            ->forceFill(['created_at' => '2026-09-20 07:30:00'])->saveQuietly();
        WardNotification::create(['ward_id' => $this->ward->id, 'patient_id' => $this->patient->id, 'bed_number' => 'D610', 'type' => 'patient_request', 'severity' => 'normal', 'message' => 'Patient needs help to the toilet', 'status' => 'responded', 'responded_at' => '2026-09-20 16:05:00', 'responded_by' => $this->user->id])
            ->forceFill(['created_at' => '2026-09-20 16:00:00'])->saveQuietly();
        $this->log('transfer', '2026-09-20 18:00:00', ['bed_number' => 'D611', 'notes' => 'Transfer from D610 to D611. Reason: closer to station']);

        // Day 3 (21 Sep)
        ConsultantNote::create(['consultant_id' => $consultant->id, 'patient_id' => $this->patient->id, 'note' => 'Improving, plan discharge tomorrow'])
            ->forceFill(['created_at' => '2026-09-21 08:00:00'])->saveQuietly();

        // Roster: Aisyah had this bed on day 1; Mei Ling had the other bed that day, and this
        // patient's new bed on day 3
        $aisyah = $this->nurse('Aisyah Rahman');
        $meiLing = $this->nurse('Mei Ling');
        WardScheduleAssignment::create(['ward_id' => $this->ward->id, 'bed_id' => $this->bed->id, 'nurse_id' => $aisyah->id, 'scheduled_date' => '2026-09-19', 'shift' => 'AM']);
        WardScheduleAssignment::create(['ward_id' => $this->ward->id, 'bed_id' => $this->otherBed->id, 'nurse_id' => $meiLing->id, 'scheduled_date' => '2026-09-19', 'shift' => 'AM']);
        WardScheduleAssignment::create(['ward_id' => $this->ward->id, 'bed_id' => $this->otherBed->id, 'nurse_id' => $meiLing->id, 'scheduled_date' => '2026-09-21', 'shift' => 'AM']);

        $summary = $this->summary();

        $this->assertSame([
            'admission: Admitted',
            'care: Attending Doctor assigned',
            'medication: Medication charted',
            'vitals: Vital signs',
            'medication: Dose given',
            'fluid: Intake',
            'fluid: Output',
            'assessment: Blood glucose (HGT)',
            'assessment: ' . $gcs->name,
            'movement: Movement booked',
            'order: Order written',
            'medication: Dose held',
            'transfusion: Blood unit registered',
            'transfusion: Transfusion started',
            'order: Order done',
            'transfusion: Transfusion completed',
            'movement: Left the ward',
            'movement: Back on the ward',
            'alert: Patient call',
            'admission: Transferred',
            'care: Consultant note',
        ], $this->timelineTitles($summary));

        $days = collect($summary['timeline']['days'])->reject(fn($block) => $block['empty'])->values();
        $this->assertSame([1, 2, 3], $days->pluck('number')->all());
        // Each day names only the nurses who had the patient's bed that day
        $this->assertSame([['shift' => 'Morning', 'nurses' => 'Aisyah Rahman']], $days[0]['roster']);
        $this->assertSame([['shift' => 'Morning', 'nurses' => 'Mei Ling']], $days[2]['roster']);

        // Tone and flags carried on the events
        $events = $days->flatMap(fn($block) => $block['events']);
        $vital = $events->firstWhere('category', 'vitals');
        $this->assertSame('critical', $vital['tone']);
        $this->assertStringStartsWith('EWS ', $vital['badge']);
        $this->assertSame('critical', $events->firstWhere('title', 'Blood glucose (HGT)')['tone']);
        $this->assertSame('STAT', $events->firstWhere('title', 'Order written')['badge']);
        $this->assertSame('Patient asleep', $events->firstWhere('title', 'Dose held')['note']);
        $this->assertSame('Answered by Nurse Manager after 5 min', $events->firstWhere('title', 'Patient call')['note']);

        // Struck-out I/O entries count nowhere
        $this->assertSame(500, $summary['fluidBalance']['totals']['intake']);
        $this->assertSame(300, $summary['fluidBalance']['totals']['output']);
        $this->assertSame(1, $summary['fluidBalance']['voidedCount']);
        $this->assertSame(1, $summary['highlights']['transfers']);
        $this->assertSame(1, $summary['highlights']['dosesGiven']);
        $this->assertSame(1, $summary['highlights']['dosesNotGiven']);

        $this->panel()
            ->assertOk()
            ->assertSee('Provisional')
            ->assertSee('Discharge Summary')
            ->assertSee('Ref ' . $summary['episode']->reference())
            ->assertSeeInOrder(['Stay at a glance', 'Clinical alerts', 'Doctor &amp; care team', 'Clinical timeline', 'Vital signs', 'Intake / output', 'Medications', 'Blood transfusion', 'Consultant orders &amp; notes', 'Assessments &amp; blood glucose', 'Transfers &amp; movements', 'Sign-off'], false)
            ->assertSee('Penicillin')
            ->assertSee('Latex')
            ->assertSee('Day 3')
            ->assertSee('Repeat FBC')
            ->assertSee('+200 mL')
            ->assertSee('Improving, plan discharge tomorrow')
            ->assertSee('Transferred to');
    }

    public function test_a_discharged_stay_is_final_and_earlier_admissions_can_be_picked(): void
    {
        $first = $this->admit('2026-09-01 09:00:00');
        VitalSign::create(['patient_id' => $this->patient->id, 'systolic_bp' => 120, 'diastolic_bp' => 80, 'recorded_at' => '2026-09-02 08:00:00']);
        $order = PatientMedication::create([
            'patient_id' => $this->patient->id, 'ward_id' => $this->ward->id, 'medication_name' => 'Omeprazole',
            'dose_amount' => 20, 'dose_unit' => 'mg', 'route' => 'PO', 'frequency' => 'od', 'interval_minutes' => 1440,
            'status' => 'stopped', 'start_at' => '2026-09-01 10:00:00', 'next_due_at' => '2026-09-02 10:00:00',
            // stopped a week after this stay ended: still running at discharge
            'stopped_at' => '2026-09-10 10:00:00', 'stop_reason' => 'Course finished',
        ]);
        $order->forceFill(['created_at' => '2026-09-01 10:00:00'])->saveQuietly();
        $this->log('discharge', '2026-09-03 11:00:00', ['discharged_at' => Carbon::parse('2026-09-03 11:00:00'), 'notes' => 'Reason: Discharged home']);

        // Readmitted: the latest admission is the one the tab opens on
        VitalSign::create(['patient_id' => $this->patient->id, 'systolic_bp' => 130, 'diastolic_bp' => 85, 'recorded_at' => '2026-09-19 12:00:00']);
        $this->admit('2026-09-19 08:30:00');

        $this->panel()
            ->assertOk()
            ->assertSee('Provisional')
            ->assertSee('discharge-summary-admission', false)
            ->assertSee('01 Sep 2026')
            ->assertDontSee('Omeprazole');

        $response = $this->panel(['admission' => $first->id])
            ->assertOk()
            ->assertSee('Final')
            ->assertDontSee('Provisional &mdash; patient not yet discharged', false)
            ->assertSee('Discharged home')
            ->assertSee('Active at discharge')
            ->assertSee('Omeprazole')
            ->assertSee('Length of stay');

        // Only the first stay's reading
        $this->assertStringContainsString('1 reading during this admission', $response->getContent());
    }

    public function test_an_admission_of_another_patient_or_a_guest_is_refused(): void
    {
        $this->admit();
        $other = Patient::create(['name' => 'Other', 'mrn' => 'MRN2', 'rn' => 'RN2', 'ic_passport' => '700101-01-0001', 'age' => 56, 'gender' => 'Male', 'phone' => '012-0000000', 'status' => 'admitted', 'ward_id' => $this->ward->id, 'bed_number' => 'D611', 'is_active' => true]);
        $otherAdmission = AdmissionLog::create(['patient_id' => $other->id, 'ward_id' => $this->ward->id, 'bed_number' => 'D611', 'action' => 'admit', 'patient_name' => 'Other', 'mrn' => 'MRN2', 'admitted_at' => now()]);

        $this->get(route('ward.discharge-summary.panel', ['patient' => $this->patient->id]))
            ->assertRedirect(route('login'));

        $this->panel(['admission' => $otherAdmission->id])->assertNotFound();
    }

    public function test_a_patient_admitted_before_admission_logs_still_gets_a_summary(): void
    {
        VitalSign::create(['patient_id' => $this->patient->id, 'systolic_bp' => 118, 'diastolic_bp' => 76, 'recorded_at' => '2026-09-20 08:00:00']);

        $this->panel()
            ->assertOk()
            ->assertSee('Provisional')
            ->assertSee('19 Sep 2026, 08:30')
            ->assertSee('1 reading during this admission');

        $this->actingAs($this->user)
            ->get(route('ward.discharge-summary.print', ['patient' => $this->patient->id]))
            ->assertOk()
            ->assertSee('Siti Aminah');
    }

    public function test_a_prebooked_patient_has_no_summary_yet(): void
    {
        $this->patient->update(['status' => 'prebook', 'admitted_at' => null]);

        $this->panel()
            ->assertOk()
            ->assertSee('No admission to summarise yet');

        $this->actingAs($this->user)
            ->get(route('ward.discharge-summary.print', ['patient' => $this->patient->id]))
            ->assertNotFound();
    }

    public function test_the_print_page_offers_every_section_and_the_admission_list_pages_still_work(): void
    {
        $admission = $this->admit();

        $print = $this->actingAs($this->user)
            ->get(route('ward.discharge-summary.print', ['patient' => $this->patient->id, 'admission' => $admission->id]))
            ->assertOk()
            ->assertSee('Provisional');

        foreach (array_keys(DischargeSummaryService::SECTIONS) as $key) {
            $print->assertSee('data-print-section="' . $key . '"', false);
        }

        $this->actingAs($this->user)->get(route('discharge-summaries.show', $admission))
            ->assertOk()
            ->assertSee('Timeline')
            ->assertSee('Clinical timeline');

        $this->actingAs($this->user)->get(route('discharge-summaries.print', $admission))->assertOk();
        $this->actingAs($this->user)->get(route('discharge-summaries.index'))->assertOk()->assertSee('Siti Aminah');
    }
}
