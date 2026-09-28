<?php

namespace Tests\Feature;

use App\Models\Bed;
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
 * Ventilator & Airway Parameters: readings typed in from the ventilator and
 * capnograph, flagged like the hemodynamic numerics, and shown on the
 * Critical Care Ward Dashboard bed cards while they are being charted.
 */
class VentilatorReadingsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private WardType $icu;
    private Ward $ward;
    private Patient $patient;
    private ClinicalIndicator $vent;

    protected function setUp(): void
    {
        parent::setUp();

        // Whole seconds, as the database keeps them, so recorded times compare exactly
        $this->freezeSecond();

        $this->user = User::factory()->create(['name' => 'ICU Nurse']);
        $hospital = Hospital::create(['name' => 'Test Hospital']);

        // The library sync migrations put the indicators in; bind VENT to a critical care ICU type
        $this->vent = ClinicalIndicator::where('code', 'VENT')->firstOrFail();
        $this->icu = WardType::create(['code' => 'ICU', 'name' => 'ICU', 'is_critical_care' => true, 'is_active' => true]);
        $this->icu->clinicalIndicators()->sync([$this->vent->id]);

        $this->ward = Ward::create([
            'hospital_id' => $hospital->id, 'ward_code' => 'ICU1', 'ward_name' => 'Intensive Care Unit',
            'ward_type_id' => $this->icu->id, 'is_active' => true,
        ]);

        Bed::create([
            'ward_id' => $this->ward->id, 'bed_number' => 'ICU01', 'bed_id' => 'ICU1-ICU01',
            'bed_display_name' => 'ICU01', 'status' => 'occupied', 'is_active' => true,
        ]);

        $this->patient = Patient::create([
            'name' => 'Tan Mei Ling', 'mrn' => 'MRN82001', 'rn' => 'RN82001', 'ic_passport' => '580101-10-5002',
            'age' => 67, 'gender' => 'Female', 'phone' => '012-0000000',
            'ward_id' => $this->ward->id, 'bed_number' => 'ICU01', 'status' => Patient::STATUS_ADMITTED, 'is_active' => true,
            'admitted_at' => now()->subDay(),
        ]);
    }

    /** Post readings in item order: EtCO2, FiO2, PEEP, PIP, Vt. */
    private function record(array $readings, ?string $notes = null)
    {
        return $this->actingAs($this->user)
            ->from(route('ward.patient-details', ['patient_id' => $this->patient->id]))
            ->post(route('ward.clinical-indicator-score.store'), [
                'patient_id' => $this->patient->id,
                'clinical_indicator_id' => $this->vent->id,
                'readings' => $readings,
                'notes' => $notes,
            ]);
    }

    /** A set of readings recorded at a given time, as the store would have saved it. */
    private function recordedAt(array $readings, $at, ?ClinicalIndicator $indicator = null): ClinicalIndicatorScore
    {
        $indicator ??= $this->vent;
        $evaluated = ClinicalIndicatorReadings::evaluate($indicator->definition(), $readings);

        $record = new ClinicalIndicatorScore([
            'patient_id' => $this->patient->id, 'clinical_indicator_id' => $indicator->id, 'ward_id' => $this->ward->id,
            'score' => $evaluated['score'], 'item_scores' => $evaluated['items'],
            'recorded_by' => $this->user->id, 'recorded_at' => $at,
        ]);
        $record->applyBand($indicator->code)->save();

        return $record;
    }

    public function test_the_library_entry_is_internally_consistent(): void
    {
        $definition = ClinicalIndicatorLibrary::find('VENT');

        $this->assertSame('Ventilator & Airway Parameters', $definition['name']);
        $this->assertTrue(ClinicalIndicatorLibrary::takesReadings($definition));
        $this->assertFalse(ClinicalIndicatorLibrary::isScorable($definition));
        $this->assertArrayHasKey($definition['category'], ClinicalIndicatorLibrary::CATEGORIES);
        $this->assertSame(['EtCO2', 'FiO2', 'PEEP', 'PIP', 'Vt'], array_column($definition['items'], 'abbr'));

        foreach ($definition['items'] as $item) {
            [$low, $high] = $item['normal'];
            [$min, $max] = $item['limits'];
            $this->assertLessThan($high, $low, $item['abbr'] . ' normal range');
            // FiO2 cannot go below room air, so its normal range may start at the limit
            $this->assertTrue($min <= $low && $high < $max, $item['abbr'] . ' limits must contain the normal range');
            if (isset($item['escalate_below'])) {
                $this->assertTrue($item['escalate_below'] <= $low && $item['escalate_below'] > $min, $item['abbr'] . ' escalate_below');
            }
            if (isset($item['escalate_above'])) {
                $this->assertTrue($item['escalate_above'] >= $high && $item['escalate_above'] < $max, $item['abbr'] . ' escalate_above');
            }
            $this->assertArrayHasKey($item['chart'], $definition['charts'], $item['abbr'] . ' chart');
        }

        $abbrs = array_column($definition['items'], 'abbr');
        foreach ($definition['checks'] as [$left, , $right]) {
            $this->assertContains($left, $abbrs);
            $this->assertContains($right, $abbrs);
        }
        foreach ($definition['dashboard'] as $abbr) {
            $this->assertContains($abbr, $abbrs);
        }

        foreach ([0, 1, 2] as $grade) {
            $this->assertNotNull(ClinicalIndicatorLibrary::bandFor('VENT', $grade), "band for $grade");
        }

        // Room air is normal, and FiO2 is never flagged low
        $fio2 = $definition['items'][1];
        $this->assertSame(ClinicalIndicatorReadings::FLAG_NORMAL, ClinicalIndicatorReadings::flag($fio2, 21));
    }

    public function test_readings_are_flagged_and_the_worst_one_sets_the_status(): void
    {
        $this->record([28, 65, 10, 38, 450], 'Desaturating, suctioned')
            ->assertRedirect()
            ->assertSessionHas('success', 'Ventilator & Airway Parameters recorded: EtCO2 28 mmHg ↓↓ · FiO2 65 % ↑↑ · PEEP 10 cmH₂O ↑ · PIP 38 cmH₂O ↑↑ · Vt 450 mL - Escalate.');

        $record = ClinicalIndicatorScore::sole();
        $this->assertSame(2, $record->score);
        $this->assertSame('Escalate', $record->band_label);
        $this->assertSame('high', $record->band_tone);
        $this->assertTrue($record->isReadings());
        $this->assertEquals(
            ['EtCO2' => 'escalate_low', 'FiO2' => 'escalate_high', 'PEEP' => 'high', 'PIP' => 'escalate_high', 'Vt' => 'normal'],
            collect($record->item_scores)->pluck('flag', 'abbr')->all()
        );
    }

    public function test_only_what_the_patient_is_on_is_needed_and_the_readings_must_hang_together(): void
    {
        $this->record(['', 40, 5, '', ''])->assertSessionHas('success');
        $this->record([38, '', '', '', ''])->assertSessionHas('success');
        $this->record(['', '', '', '', 560])->assertSessionHas('success');

        $records = ClinicalIndicatorScore::orderBy('id')->get();
        $this->assertSame([0, 0, 1], $records->pluck('score')->all());
        $this->assertSame(['Within normal range', 'Within normal range', 'Outside normal range'], $records->pluck('band_label')->all());

        $cases = [
            'Enter at least one reading before saving.' => ['', '', '', '', ''],
            'Peak inspiratory pressure must be higher than PEEP.' => ['', '', 10, 8, ''],
            'Fraction of inspired oxygen of 15 % is outside what a monitor shows (21 to 100). Check the reading.' => ['', 15, '', '', ''],
            'Tidal volume of 4500 mL is outside what a monitor shows (0 to 2000). Check the reading.' => ['', '', '', '', 4500],
        ];

        foreach ($cases as $message => $readings) {
            $this->record($readings)->assertSessionHas('error', $message);
        }

        $this->assertSame(3, ClinicalIndicatorScore::count());
    }

    public function test_patient_details_charts_the_ventilator_on_its_own_charts(): void
    {
        $details = route('ward.patient-details', ['patient_id' => $this->patient->id]);

        $this->recordedAt([38, 40, 5, 22, 450], now()->subHour());
        $this->record([36, 45, 8, 26, 430]);

        $this->actingAs($this->user)->get($details)
            ->assertOk()
            ->assertSee('Ventilator & Airway Parameters')
            ->assertSee('name="readings[0]"', false)
            ->assertSee('name="readings[4]"', false)
            ->assertSee('Normal 35–45 · Escalate <30 or >50')
            ->assertSee('placeholder="e.g. SIMV-PC rate 14, plateau 26, ETT 7.5 at 22 cm, suctioned"', false)
            // Gas exchange on one chart, airway pressures and volume on the other
            ->assertSee('End-tidal CO2 and inspired oxygen')
            ->assertSee('Airway pressures and tidal volume')
            ->assertSee('x-ref="chart_gas"', false)
            ->assertSee('x-ref="chart_airway"', false)
            ->assertDontSee('x-ref="chart_pressure"', false)
            ->assertSee('prev 38');
    }

    public function test_the_ward_types_page_lists_the_parameters_and_their_ranges(): void
    {
        $this->actingAs($this->user)->get(route('ward-types.index', ['tab' => 'clinical_indicators']))
            ->assertOk()
            ->assertSee('Ventilation')
            ->assertSee('Ventilator & Airway Parameters')
            ->assertSee('Readings: EtCO2, FiO2, PEEP, PIP, Vt')
            ->assertSee('End-tidal carbon dioxide')
            ->assertSee('Normal 35 to 45 mmHg. Escalate below 30 or above 50')
            ->assertSee('Normal 21 to 40 %. Escalate above 60')
            ->assertSee('Normal 10 to 30 cmH₂O. Escalate above 35');
    }

    public function test_the_critical_care_bed_card_shows_the_ventilator_being_charted(): void
    {
        // A full set three hours ago, then only EtCO2 an hour ago: the latest of each is shown
        $this->recordedAt([38, 60, 12, 28, 420], now()->subHours(3));
        $this->recordedAt([27, '', '', '', ''], now()->subHour());

        $response = $this->actingAs($this->user)->get(route('critical-care.dashboard'))->assertOk();

        $set = $response->viewData('bedsideReadings')[$this->patient->id]['VENT'];
        $this->assertSame(['EtCO2', 'FiO2', 'PEEP', 'PIP', 'Vt'], array_column($set['readings'], 'abbr'));
        $this->assertSame(['27', '60', '12', '28', '420'], array_column($set['readings'], 'text'));
        $this->assertSame(['FiO2', 'PEEP'], array_column($set['headline'], 'abbr'));
        $this->assertSame(ClinicalIndicatorReadings::FLAG_ESCALATE_LOW, $set['flag']);
        $this->assertSame('Escalate', $set['status']);
        $this->assertTrue($set['at']->equalTo(now()->subHour()));

        $response->assertSeeInOrder(['>FiO2</span> 60', '>PEEP</span> 12'], false)
            ->assertSee('Ventilator & Airway Parameters: EtCO2 27 mmHg ↓↓ · FiO2 60 % ↑ · PEEP 12 cmH₂O ↑ · PIP 28 cmH₂O · Vt 420 mL')
            ->assertSee('tabular-nums bg-red-600 animate-pulse', false)
            ->assertSee('Latest of each in the last 12 hours')
            ->assertSee("tab: 'indicator-" . $this->vent->id . "'", false)
            ->assertSee('Open readings');

        // The general ward dashboard is left as it was
        $this->actingAs($this->user)->get(route('ward.dashboard', ['ward_id' => $this->ward->id]))
            ->assertOk()
            ->assertDontSee('>FiO2</span> 60', false)
            ->assertDontSee('Open readings');
    }

    public function test_readings_no_longer_current_or_from_before_admission_stay_off_the_card(): void
    {
        $dashboard = fn () => $this->actingAs($this->user)->get(route('critical-care.dashboard'))->assertOk();

        // Last charted 13 hours ago: the patient may since have been extubated
        $this->recordedAt([38, 40, 5, 22, 450], now()->subHours(13));
        $this->assertSame([], $dashboard()->viewData('bedsideReadings'));

        // Inside the window, but before this admission
        $this->patient->update(['admitted_at' => now()->subHours(2)]);
        $this->recordedAt([38, 40, 5, 22, 450], now()->subHours(3));
        $dashboard()->assertDontSee('Open readings');

        // Charted this admission: shown, in the normal colour while every reading is in range
        $this->recordedAt([38, 40, 5, 22, 450], now()->subMinutes(10));
        $response = $dashboard();
        $this->assertSame('Within normal range', $response->viewData('bedsideReadings')[$this->patient->id]['VENT']['status']);
        $response->assertSeeInOrder(['>FiO2</span> 40', '>PEEP</span> 5'], false)
            ->assertSee('tabular-nums bg-cyan-600"', false);
    }

    public function test_only_scales_bound_to_the_ward_type_with_dashboard_readings_are_shown(): void
    {
        // HEMO readings are charted in patient details but not on the bed card
        $hemo = ClinicalIndicator::where('code', 'HEMO')->firstOrFail();
        $this->icu->clinicalIndicators()->sync([$this->vent->id, $hemo->id]);
        $this->recordedAt([118, 64, 82, 6, 5.1, 2.8], now()->subMinutes(30), $hemo);
        $this->recordedAt([38, 40, 5, 22, 450], now()->subMinutes(30));

        $readings = ClinicalIndicatorReadings::recentForPatients([$this->patient->fresh()]);
        $this->assertSame(['VENT'], array_keys($readings[$this->patient->id]));

        // Unbound from the ward type: off the card, the readings themselves kept
        $this->icu->clinicalIndicators()->sync([$hemo->id]);
        $this->assertSame([], ClinicalIndicatorReadings::recentForPatients([$this->patient->fresh()]));
        $this->assertSame(2, ClinicalIndicatorScore::count());

        // A headline value not charted recently gives way to what was
        $this->icu->clinicalIndicators()->sync([$this->vent->id]);
        ClinicalIndicatorScore::query()->delete();
        $this->recordedAt([41, '', '', 24, ''], now()->subMinutes(5));
        $set = ClinicalIndicatorReadings::recentForPatients([$this->patient->fresh()])[$this->patient->id]['VENT'];
        $this->assertSame(['EtCO2', 'PIP'], array_column($set['headline'], 'abbr'));
    }
}
