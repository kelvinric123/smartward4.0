<?php

namespace Tests\Feature;

use App\Models\AdmissionLog;
use App\Models\ClinicalIndicator;
use App\Models\ClinicalIndicatorScore;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use App\Models\Ward;
use App\Models\WardType;
use App\Support\ClinicalIndicatorLibrary;
use App\Support\ClinicalIndicatorReadings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Advanced Hemodynamics (Numerics): readings typed in from the monitor, each
 * flagged against its range, the worst one setting the status.
 */
class HemodynamicReadingsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Ward $ward;
    private Patient $patient;
    private ClinicalIndicator $hemo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['name' => 'ICU Nurse']);
        $hospital = Hospital::create(['name' => 'Test Hospital']);

        // The library sync migrations put the indicators in; bind HEMO and GCS to an ICU type
        $this->hemo = ClinicalIndicator::where('code', 'HEMO')->firstOrFail();
        $icu = WardType::create(['code' => 'ICU', 'name' => 'ICU', 'is_critical_care' => true, 'is_active' => true]);
        $icu->clinicalIndicators()->sync([$this->hemo->id, ClinicalIndicator::where('code', 'GCS')->firstOrFail()->id]);

        $this->ward = Ward::create([
            'hospital_id' => $hospital->id, 'ward_code' => 'ICU1', 'ward_name' => 'Intensive Care Unit',
            'ward_type_id' => $icu->id, 'is_active' => true,
        ]);

        $this->patient = Patient::create([
            'name' => 'Lim Ah Kow', 'mrn' => 'MRN81001', 'rn' => 'RN81001', 'ic_passport' => '600101-10-5001',
            'age' => 64, 'gender' => 'Male', 'phone' => '012-0000000',
            'ward_id' => $this->ward->id, 'bed_number' => 'ICU01', 'status' => Patient::STATUS_ADMITTED, 'is_active' => true,
            'admitted_at' => now()->subDay(),
        ]);
    }

    /** Post readings in item order: SBP, DBP, MAP, CVP, CO, CI. */
    private function record(array $readings, ?string $notes = null)
    {
        return $this->actingAs($this->user)
            ->from(route('ward.patient-details', ['patient_id' => $this->patient->id]))
            ->post(route('ward.clinical-indicator-score.store'), [
                'patient_id' => $this->patient->id,
                'clinical_indicator_id' => $this->hemo->id,
                'readings' => $readings,
                'notes' => $notes,
            ]);
    }

    public function test_the_library_entry_is_internally_consistent(): void
    {
        $definition = ClinicalIndicatorLibrary::find('HEMO');

        $this->assertTrue(ClinicalIndicatorLibrary::takesReadings($definition));
        $this->assertFalse(ClinicalIndicatorLibrary::isScorable($definition));
        $this->assertArrayHasKey($definition['category'], ClinicalIndicatorLibrary::CATEGORIES);
        $this->assertSame(['SBP', 'DBP', 'MAP', 'CVP', 'CO', 'CI'], array_column($definition['items'], 'abbr'));

        foreach ($definition['items'] as $item) {
            [$low, $high] = $item['normal'];
            [$min, $max] = $item['limits'];
            $this->assertLessThan($high, $low, $item['abbr'] . ' normal range');
            $this->assertTrue($min < $low && $high < $max, $item['abbr'] . ' limits must contain the normal range');
            if (isset($item['escalate_below'])) {
                $this->assertTrue($item['escalate_below'] <= $low && $item['escalate_below'] > $min, $item['abbr'] . ' escalate_below');
            }
            if (isset($item['escalate_above'])) {
                $this->assertTrue($item['escalate_above'] >= $high && $item['escalate_above'] < $max, $item['abbr'] . ' escalate_above');
            }
        }

        $abbrs = array_column($definition['items'], 'abbr');
        foreach ($definition['checks'] as [$left, , $right]) {
            $this->assertContains($left, $abbrs);
            $this->assertContains($right, $abbrs);
        }
        foreach (array_keys(ClinicalIndicatorReadings::groupMembers($definition)) as $group) {
            $this->assertArrayHasKey($group, $definition['groups']);
        }

        // Every possible status (worst flag 0, 1 or 2) falls in exactly one band
        foreach ([0, 1, 2] as $grade) {
            $this->assertNotNull(ClinicalIndicatorLibrary::bandFor('HEMO', $grade), "band for $grade");
        }
    }

    public function test_readings_are_flagged_and_the_worst_one_sets_the_status(): void
    {
        $this->record([85, 42, 56, 14, 3.4, 1.9], 'On noradrenaline 0.1')
            ->assertRedirect()
            ->assertSessionHas('success', 'Advanced Hemodynamics (Numerics) recorded: ABP 85/42 (56) mmHg ↓↓ · CVP 14 mmHg ↑ · CO 3.4 L/min ↓ · CI 1.9 L/min/m² ↓↓ - Escalate.');

        $record = ClinicalIndicatorScore::sole();
        $this->assertSame(2, $record->score);
        $this->assertSame('Escalate', $record->band_label);
        $this->assertSame('high', $record->band_tone);
        $this->assertSame($this->ward->id, $record->ward_id);
        $this->assertTrue($record->isReadings());
        $this->assertEquals(
            ['SBP' => 'escalate_low', 'DBP' => 'low', 'MAP' => 'escalate_low', 'CVP' => 'high', 'CO' => 'low', 'CI' => 'escalate_low'],
            collect($record->item_scores)->pluck('flag', 'abbr')->all()
        );
        $this->assertEquals(85, $record->item_scores[0]['value']);
        $this->assertSame('L/min/m²', $record->item_scores[5]['unit']);
    }

    public function test_status_follows_the_worst_reading_and_only_monitored_values_are_needed(): void
    {
        $this->record([118, 64, 82, 6, 5.1, 2.8])->assertSessionHas('success');
        $this->record(['', '', '', 9, '', ''])->assertSessionHas('success');
        $this->record([118, 64, 68, '', '', ''])->assertSessionHas('success');

        $records = ClinicalIndicatorScore::orderBy('id')->get();
        $this->assertSame([0, 1, 1], $records->pluck('score')->all());
        $this->assertSame(['Within normal range', 'Outside normal range', 'Outside normal range'], $records->pluck('band_label')->all());
        $this->assertSame(['CVP'], collect($records[1]->item_scores)->pluck('abbr')->all());
        $this->assertSame('ABP 118/64 (68) mmHg ↓', $records[2]->breakdown());
    }

    public function test_implausible_or_inconsistent_readings_are_refused(): void
    {
        $cases = [
            'Enter at least one reading before saving.' => ['', '', '', '', '', ''],
            'Enter SBP, DBP and MAP together for invasive arterial pressure.' => [120, '', '', 5, '', ''],
            'Diastolic pressure must be lower than systolic.' => [80, 90, 85, '', '', ''],
            'MAP must lie between the diastolic and systolic pressures.' => [120, 60, 125, '', '', ''],
            'Arterial systolic pressure of 1200 mmHg is outside what a monitor shows (20 to 300). Check the reading.' => [1200, 60, 80, '', '', ''],
        ];

        foreach ($cases as $message => $readings) {
            $this->record($readings)->assertSessionHas('error', $message);
        }

        $this->record(['abc', 60, 80])->assertSessionHasErrors('readings.0');
        $this->assertSame(0, ClinicalIndicatorScore::count());
    }

    public function test_option_scored_scales_still_score_as_before(): void
    {
        $gcs = ClinicalIndicator::where('code', 'GCS')->firstOrFail();

        $this->actingAs($this->user)
            ->from(route('ward.patient-details', ['patient_id' => $this->patient->id]))
            ->post(route('ward.clinical-indicator-score.store'), [
                'patient_id' => $this->patient->id,
                'clinical_indicator_id' => $gcs->id,
                'item_scores' => [3, 4, 6],
            ])
            ->assertSessionHas('success', 'Glasgow Coma Scale scored 13 (E3 V4 M6) - Mild impairment.');

        $this->assertFalse(ClinicalIndicatorScore::sole()->isReadings());
    }

    public function test_patient_details_offers_the_readings_form_and_recent_readings(): void
    {
        $this->record([85, 42, 56, 14, 3.4, 1.9], 'On noradrenaline 0.1');

        $page = $this->actingAs($this->user)
            ->get(route('ward.patient-details', ['patient_id' => $this->patient->id]))
            ->assertOk()
            ->assertSee('Advanced Hemodynamics (Numerics)')
            ->assertSee('name="readings[0]"', false)
            ->assertSee('name="readings[5]"', false)
            ->assertSee('Normal 90 to 140 mmHg. Escalate below 90 or above 180')
            ->assertSee('Normal 2.5 to 4.0 L/min/m². Escalate below 2.2')
            ->assertSee('Invasive arterial pressure')
            ->assertSee('Save readings')
            ->assertSee('Readings this admission')
            ->assertSee('On noradrenaline 0.1')
            ->assertSee('What is recorded');

        // The option-scored GCS on the same ward keeps its dropdown form
        $page->assertSee('name="item_scores[0]"', false)->assertSee('Save score');
    }

    public function test_the_tab_shows_the_latest_values_and_charts_this_admission(): void
    {
        // A reading from before this admission stays out of the tiles, the trend and the table
        $definition = ClinicalIndicatorLibrary::find('HEMO');
        $old = ClinicalIndicatorReadings::evaluate($definition, [70, 35, 45, 20, 2.0, 1.1]);
        ClinicalIndicatorScore::create([
            'patient_id' => $this->patient->id, 'clinical_indicator_id' => $this->hemo->id, 'ward_id' => $this->ward->id,
            'score' => $old['score'], 'item_scores' => $old['items'], 'notes' => 'Before this admission',
            'recorded_by' => $this->user->id, 'recorded_at' => $this->patient->admitted_at->copy()->subHours(2),
        ]);

        $details = route('ward.patient-details', ['patient_id' => $this->patient->id]);

        $this->record([90, 50, 62, 12, 3.9, 2.1]);
        $this->actingAs($this->user)->get($details)
            ->assertOk()
            ->assertSee('Trend this admission')
            ->assertSee('The trend appears once two readings are recorded')
            ->assertDontSee('x-ref="chart_pressure"', false)
            ->assertDontSee('Before this admission');

        $this->record([104, 58, 72, 9, 4.6, 2.6]);
        $this->actingAs($this->user)->get($details)
            ->assertOk()
            ->assertSee('x-ref="chart_pressure"', false)
            ->assertSee('x-ref="chart_flow"', false)
            ->assertSee('clinicalReadingsPanel(', false)
            // Tiles: the latest set, its normal ranges in the monitor format, and the one before
            ->assertSee('Normal 90–140/60–90 (70–105)')
            ->assertSee('prev 90/50 (62)')
            ->assertSee('prev 3.9')
            ->assertDontSee('Before this admission')
            ->assertDontSee('The trend appears once two readings are recorded');
    }

    public function test_the_ward_types_page_lists_the_parameters_and_their_ranges(): void
    {
        $this->actingAs($this->user)->get(route('ward-types.index', ['tab' => 'clinical_indicators']))
            ->assertOk()
            ->assertSee('Hemodynamics')
            ->assertSee('Advanced Hemodynamics (Numerics)')
            ->assertSee('Readings: SBP, DBP, MAP, CVP, CO, CI')
            ->assertSee('Arterial systolic pressure')
            ->assertSee('Normal 70 to 105 mmHg. Escalate below 65')
            ->assertSee('set by the worst reading');
    }

    public function test_discharge_summary_shows_the_readings_rather_than_the_score(): void
    {
        $admit = AdmissionLog::create([
            'patient_id' => $this->patient->id, 'ward_id' => $this->ward->id, 'user_id' => $this->user->id,
            'bed_number' => 'ICU01', 'action' => 'admit', 'patient_name' => $this->patient->name,
            'mrn' => $this->patient->mrn, 'source' => 'manual', 'admitted_at' => now()->subDay(),
        ]);
        $admit->forceFill(['created_at' => now()->subDay()])->saveQuietly();

        $this->record([85, 42, 56, 14, 3.4, 1.9]);

        $this->actingAs($this->user)->get(route('ward.discharge-summary.panel', ['patient' => $this->patient->id]))
            ->assertOk()
            ->assertSee('Escalate · ABP 85/42 (56) mmHg ↓↓ · CVP 14 mmHg ↑ · CO 3.4 L/min ↓ · CI 1.9 L/min/m² ↓↓')
            ->assertDontSee('Score 2')
            ->assertDontSee('2 · Escalate');
    }
}
