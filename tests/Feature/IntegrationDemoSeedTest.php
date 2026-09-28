<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\ClinicalIndicator;
use App\Models\ClinicalIndicatorScore;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use App\Models\VitalSign;
use App\Models\Ward;
use App\Models\WardType;
use App\Services\DemoClinicalData;
use App\Support\ClinicalIndicatorLibrary;
use App\Support\ClinicalIndicatorReadings;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Integration Demo page's vital signs form: vital signs as before, and
 * entries for the clinical indicators the patient's ward records, all
 * following one clinical course.
 */
class IntegrationDemoSeedTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Patient $patient;
    private Ward $ward;

    protected function setUp(): void
    {
        parent::setUp();

        // 62 hours from the start of the day two days ago: 16 sets at 6 a day, 6 assessments at 2 a day
        $this->travelTo(Carbon::parse('2026-09-26 14:00:00'));

        $this->user = User::factory()->create();
        $hospital = Hospital::create(['name' => 'Test Hospital']);

        $icu = WardType::create(['code' => 'ICU', 'name' => 'ICU', 'is_critical_care' => true, 'is_active' => true]);
        $icu->clinicalIndicators()->sync($this->ids(['HEMO', 'VENT', 'GCS', 'MORSE', 'PAIN', 'EWS']));

        $this->ward = Ward::create([
            'hospital_id' => $hospital->id, 'ward_code' => 'ICU1', 'ward_name' => 'Intensive Care Unit',
            'ward_type_id' => $icu->id, 'is_active' => true,
        ]);
        $this->patient = $this->admit($this->ward, 'ICU01', 'Lim Ah Kow');
    }

    private function ids(array $codes): array
    {
        return ClinicalIndicator::whereIn('code', $codes)->pluck('id', 'code')->all();
    }

    private function admit(Ward $ward, string $bed, string $name): Patient
    {
        Bed::create([
            'ward_id' => $ward->id, 'bed_number' => $bed, 'bed_id' => $ward->ward_code . '-' . $bed,
            'bed_display_name' => $bed, 'status' => 'occupied', 'is_active' => true,
        ]);

        return Patient::create([
            'name' => $name, 'mrn' => 'MRN-' . $bed, 'rn' => 'RN-' . $bed, 'ic_passport' => sprintf('600101-10-%04d', Patient::count() + 1),
            'age' => 64, 'gender' => 'Male', 'phone' => '012-0000000',
            'ward_id' => $ward->id, 'bed_number' => $bed, 'status' => Patient::STATUS_ADMITTED, 'is_active' => true,
            'admitted_at' => now()->subDays(5),
        ]);
    }

    private function seedDemo(array $fields)
    {
        return $this->actingAs($this->user)
            ->from(route('integration.demo.index'))
            ->post(route('integration.demo.seed-vital-signs'), $fields + [
                'patient_id' => $this->patient->id, 'readings_per_day' => 4, 'past_days' => 2,
            ]);
    }

    /** The patient's entries for one scale, oldest first. */
    private function entries(string $code)
    {
        return ClinicalIndicatorScore::where('patient_id', $this->patient->id)
            ->where('clinical_indicator_id', $this->ids([$code])[$code])
            ->orderBy('recorded_at')
            ->get();
    }

    public function test_the_vital_signs_form_offers_what_each_patients_ward_records(): void
    {
        $untyped = $this->admit(
            Ward::create(['hospital_id' => $this->ward->hospital_id, 'ward_code' => 'NW1', 'ward_name' => 'New Ward', 'is_active' => true]),
            'NW01', 'Siti Aminah'
        );

        $response = $this->actingAs($this->user)->get(route('integration.demo.index'))
            ->assertOk()
            ->assertSee('Clinical course')
            ->assertSee('Deteriorating: drifts to escalation levels')
            ->assertSee('Seed vital signs')
            ->assertSee("activeTab: 'seed-patients'", false);

        $offered = $response->viewData('patientIndicators');
        $icu = collect($offered[$this->patient->id]['indicators'])->keyBy('code');

        $this->assertSame('ICU', $offered[$this->patient->id]['wardType']);
        $this->assertEquals(
            ['MORSE' => 'scored', 'PAIN' => 'score', 'GCS' => 'scored', 'EWS' => null, 'HEMO' => 'readings', 'VENT' => 'readings'],
            $icu->map(fn ($indicator) => $indicator['kind'])->all()
        );
        $this->assertSame('Monitor readings: EtCO2, FiO2, PEEP, PIP, Vt', $icu['VENT']['detail']);
        $this->assertSame('Details still to confirm: nothing to seed yet', $icu['EWS']['detail']);
        $this->assertEquals(['wardType' => null, 'indicators' => []], $offered[$untyped->id]);
    }

    public function test_vital_signs_alone_seed_as_before(): void
    {
        // The form as it was: no course, no clinical indicators
        $this->seedDemo([])
            ->assertRedirect(route('integration.demo.index'))
            ->assertSessionHas('success', 'Successfully seeded 12 vital sign readings for Lim Ah Kow.')
            ->assertSessionHas('demo_tab', 'seed-vitals');

        $vitals = VitalSign::where('patient_id', $this->patient->id)->get();
        $this->assertCount(12, $vitals);
        foreach ($vitals as $vital) {
            $this->assertTrue($vital->systolic_bp >= 100 && $vital->systolic_bp <= 140);
            $this->assertTrue($vital->pulse_rate >= 60 && $vital->pulse_rate <= 100);
            $this->assertTrue($vital->spo2 >= 95);
            $this->assertNull($vital->oxygen_delivery);
            $this->assertSame(DemoClinicalData::NOTE, $vital->notes);
        }
        $this->assertSame(0, ClinicalIndicatorScore::count());

        // Back on the vital signs tab
        $this->actingAs($this->user)->get(route('integration.demo.index'))->assertSee("activeTab: 'seed-vitals'", false);
    }

    public function test_a_deteriorating_course_runs_through_vitals_hemodynamics_and_the_ventilator(): void
    {
        $ids = $this->ids(['HEMO', 'VENT']);

        $this->seedDemo(['pattern' => 'deteriorating', 'seed_vitals' => '1', 'indicator_ids' => array_values($ids), 'monitor_per_day' => 6])
            ->assertSessionHas('success', 'Successfully seeded 12 vital sign readings, 16 HEMO readings and 16 VENT readings for Lim Ah Kow (deteriorating course).');

        foreach (['HEMO', 'VENT'] as $code) {
            $entries = $this->entries($code);
            $definition = ClinicalIndicatorLibrary::find($code);

            $this->assertCount(16, $entries, $code);
            $this->assertLessThanOrEqual(1, $entries->first()->score, "$code starts in or near range");
            $this->assertSame(2, $entries->last()->score, "$code ends at an escalation level");
            $this->assertSame('Escalate', $entries->last()->band_label);
            $this->assertTrue($entries->last()->recorded_at->gt(now()->subMinutes(15)), "$code latest is current");

            foreach ($entries as $entry) {
                $this->assertTrue($entry->isReadings());
                $this->assertSame(DemoClinicalData::NOTE, $entry->notes);
                $this->assertSame($this->ward->id, $entry->ward_id);
                $values = collect($entry->item_scores)->pluck('value', 'abbr');
                // What a nurse could have saved: the same readings evaluate cleanly to the same status
                $posted = array_map(fn ($item) => $values[$item['abbr']] ?? '', $definition['items']);
                $this->assertSame($entry->score, ClinicalIndicatorReadings::evaluate($definition, $posted)['score']);
            }
        }

        $hemo = $this->entries('HEMO');
        foreach ($hemo as $entry) {
            $values = collect($entry->item_scores)->pluck('value', 'abbr');
            $this->assertTrue($values['DBP'] < $values['MAP'] && $values['MAP'] < $values['SBP']);
        }
        // Cardiac output every third set, with its index
        $this->assertSame(6, $hemo->filter(fn ($entry) => collect($entry->item_scores)->contains('abbr', 'CI'))->count());
        $this->assertSame(6, $hemo->filter(fn ($entry) => collect($entry->item_scores)->contains('abbr', 'CO'))->count());

        foreach ($this->entries('VENT') as $entry) {
            $values = collect($entry->item_scores)->pluck('value', 'abbr');
            $this->assertGreaterThan($values['PEEP'], $values['PIP']);
        }

        // The critical care bed card shows the ventilator at escalation
        $bedside = ClinicalIndicatorReadings::recentForPatients([$this->patient->fresh()]);
        $this->assertSame(2, ClinicalIndicatorReadings::FLAGS[$bedside[$this->patient->id]['VENT']['flag']]['grade']);

        // The vital signs worsen with them, on the ventilator's FiO2
        $vitals = VitalSign::where('patient_id', $this->patient->id)->orderBy('recorded_at')->get();
        $firstDay = $vitals->filter(fn ($vital) => $vital->recorded_at->isSameDay(now()->subDays(2)));
        $today = $vitals->filter(fn ($vital) => $vital->recorded_at->isToday());
        $this->assertGreaterThan($firstDay->avg('pulse_rate') + 15, $today->avg('pulse_rate'));
        $this->assertLessThan($firstDay->avg('systolic_bp') - 15, $today->avg('systolic_bp'));
        $this->assertTrue($vitals->every(fn ($vital) => $vital->oxygen_delivery === 'ventilator'));
        $this->assertGreaterThanOrEqual(60, $today->min('fio2_percent'));
    }

    public function test_an_improving_course_can_seed_the_ventilator_alone(): void
    {
        $this->seedDemo(['pattern' => 'improving', 'seed_vitals' => '0', 'indicator_ids' => [$this->ids(['VENT'])['VENT']]])
            ->assertSessionHas('success', 'Successfully seeded 16 VENT readings for Lim Ah Kow (improving course).');

        $entries = $this->entries('VENT');
        $this->assertSame(2, $entries->first()->score);
        $this->assertSame(0, $entries->last()->score);
        $this->assertSame(0, VitalSign::count());
    }

    public function test_scored_scales_land_in_the_bands_the_course_calls_for(): void
    {
        $this->seedDemo(['pattern' => 'deteriorating', 'seed_vitals' => '0', 'indicator_ids' => array_values($this->ids(['GCS', 'MORSE', 'PAIN'])), 'assessments_per_day' => 2])
            ->assertSessionHas('success', 'Successfully seeded 6 MORSE scores, 6 PAIN scores and 6 GCS scores for Lim Ah Kow (deteriorating course).');

        foreach (['GCS', 'MORSE', 'PAIN'] as $code) {
            $entries = $this->entries($code);
            $definition = ClinicalIndicatorLibrary::find($code);

            $this->assertCount(6, $entries, $code);
            $this->assertSame('low', $entries->first()->band_tone, "$code starts well");
            $this->assertSame('high', $entries->last()->band_tone, "$code ends at high risk");

            foreach ($entries as $entry) {
                $this->assertSame(ClinicalIndicatorLibrary::bandFor($code, $entry->score)['label'], $entry->band_label);

                if ($code === 'PAIN') {
                    // A total alone, as the form records Pain Score
                    $this->assertNull($entry->item_scores);
                    $this->assertTrue($entry->score >= 0 && $entry->score <= 10);
                    continue;
                }

                // Every item scored with one of its own options, adding up to the total
                $this->assertSame(array_column($definition['items'], 'name'), array_column($entry->item_scores, 'name'));
                foreach ($entry->item_scores as $i => $scored) {
                    $this->assertContains(['label' => $scored['label'], 'value' => $scored['value']], $definition['items'][$i]['options']);
                }
                $this->assertSame($entry->score, array_sum(array_column($entry->item_scores, 'value')));
            }
        }

        // GCS keeps its breakdown, as E3 V4 M6
        $this->assertMatchesRegularExpression('/^E\d V\d M\d$/', $this->entries('GCS')->last()->breakdown());
    }

    public function test_only_what_the_patients_ward_records_can_be_seeded(): void
    {
        // Braden is not bound to the ICU type; EWS is, but has no detail to seed from yet
        $this->seedDemo(['seed_vitals' => '0', 'indicator_ids' => array_values($this->ids(['BRADEN', 'EWS']))])
            ->assertSessionHas('error', "Choose vital signs or a clinical indicator recorded on Lim Ah Kow's ward to seed.");
        $this->assertSame(0, ClinicalIndicatorScore::count());

        $this->seedDemo(['seed_vitals' => '0', 'indicator_ids' => array_values($this->ids(['BRADEN', 'EWS', 'VENT']))])
            ->assertSessionHas('success', 'Successfully seeded 16 VENT readings for Lim Ah Kow.');
        $this->assertSame([$this->ids(['VENT'])['VENT']], ClinicalIndicatorScore::distinct()->pluck('clinical_indicator_id')->all());

        $this->seedDemo(['pattern' => 'worse'])->assertSessionHasErrors('pattern');
    }
}
