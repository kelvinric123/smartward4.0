<?php

namespace Tests\Feature;

use App\Models\IntegrationSetting;
use App\Models\Patient;
use App\Models\User;
use App\Services\PatientInfoSources;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientAdditionalInfoSourcesTest extends TestCase
{
    use RefreshDatabase;

    private array $adtAllergy = ['allergen' => 'PEN^Penicillin', 'status' => 'Resolved'];

    private function makePatient(): Patient
    {
        // Looks like a patient the ADT feed has filled in
        return Patient::create([
            'name' => 'Nurul Huda',
            'mrn' => 'MRN700001',
            'rn' => 'RN700001',
            'ic_passport' => '850505-10-2233',
            'age' => 41,
            'gender' => 'Female',
            'phone' => '019-2223344',
            'nursing_level' => 'level_1',
            'isolation_type' => 'none',
            'diet_types' => ['DMD'],
            'fall_risk' => '1',
            'allergies' => [$this->adtAllergy, 'Peanuts'],
        ]);
    }

    private function saveAdditionalInfo(Patient $patient, array $fields = [])
    {
        return $this->actingAs(User::factory()->create())
            ->post(route('ward.update-patient-clinical'), array_merge([
                'patient_id' => $patient->id,
                'active_tab' => 'additional',
                'nursing_level' => 'level_3',
                'isolation_type' => 'contact',
                'hgt_frequency' => 'tds',
            ], $fields));
    }

    public function test_defaults_keep_the_existing_behaviour(): void
    {
        $this->assertSame([
            'nursing_level' => 'manual',
            'diet' => 'adt',
            'fall_risk' => 'adt',
            'isolation' => 'manual',
            'allergies' => 'adt',
        ], PatientInfoSources::all());
    }

    public function test_saving_the_tab_no_longer_clears_adt_diet_fall_risk_or_allergies(): void
    {
        $patient = $this->makePatient();

        // The default page posts only nursing level, isolation and HGT
        $this->saveAdditionalInfo($patient)->assertSessionHasNoErrors();

        $patient->refresh();
        $this->assertSame('level_3', $patient->nursing_level);
        $this->assertSame('contact', $patient->isolation_type);
        $this->assertSame(['DMD'], $patient->diet_types);
        $this->assertSame('1', $patient->fall_risk);
        $this->assertEquals([$this->adtAllergy, 'Peanuts'], $patient->allergies);
    }

    public function test_fields_left_to_adt_ignore_submitted_values(): void
    {
        $patient = $this->makePatient();

        $this->saveAdditionalInfo($patient, [
            'diet_managed' => 1,
            'diet_types' => ['LFD'],
            'nbm' => 1,
            'fall_risk' => 'none',
            'allergies_managed' => 1,
            'new_allergy' => 'Latex',
        ])->assertSessionHasNoErrors();

        $patient->refresh();
        $this->assertSame(['DMD'], $patient->diet_types);
        $this->assertSame('1', $patient->fall_risk);
        $this->assertEquals([$this->adtAllergy, 'Peanuts'], $patient->allergies);
    }

    public function test_directly_managed_diet_saves_nbm_feeding_diet_types_and_orders(): void
    {
        PatientInfoSources::save(['diet' => 'manual']);
        $patient = $this->makePatient();

        $this->saveAdditionalInfo($patient, [
            'diet_managed' => 1,
            'nbm' => 1,
            'feeding_routes' => ['ngt', 'tpn'],
            'diet_types' => ['dmd', 'LFD', 'NBM'],
            'diet_orders' => 'NBM from midnight for OT',
        ])->assertSessionHasNoErrors();

        $patient->refresh();
        // NBM is stored as a diet code so the bed box and EKad show it
        $this->assertSame(['NBM', 'DMD', 'LFD'], $patient->diet_types);
        $this->assertSame(['ngt', 'tpn'], $patient->feeding_routes);
        $this->assertSame('NBM from midnight for OT', $patient->diet_orders);

        // Unticking NBM and every diet clears them
        $this->saveAdditionalInfo($patient, ['diet_managed' => 1])->assertSessionHasNoErrors();

        $patient->refresh();
        $this->assertNull($patient->diet_types);
        $this->assertNull($patient->feeding_routes);
        $this->assertNull($patient->diet_orders);
    }

    public function test_unknown_feeding_route_is_rejected(): void
    {
        PatientInfoSources::save(['diet' => 'manual']);
        $patient = $this->makePatient();

        $this->saveAdditionalInfo($patient, ['diet_managed' => 1, 'feeding_routes' => ['jetpack']])
            ->assertSessionHasErrors('feeding_routes.0');

        $this->assertNull($patient->fresh()->feeding_routes);
    }

    public function test_directly_managed_allergies_keep_adt_entries_intact(): void
    {
        PatientInfoSources::save(['allergies' => 'manual']);
        $patient = $this->makePatient();

        // Keep the ADT entry exactly as stored, drop "Peanuts", add "Latex"
        $this->saveAdditionalInfo($patient, [
            'allergies_managed' => 1,
            'allergies_kept' => [json_encode($this->adtAllergy)],
            'new_allergy' => ' Latex ',
        ])->assertSessionHasNoErrors();

        $this->assertEquals([$this->adtAllergy, 'Latex'], $patient->fresh()->allergies);
    }

    public function test_new_allergy_can_carry_an_optional_severity(): void
    {
        PatientInfoSources::save(['allergies' => 'manual']);
        $patient = $this->makePatient();

        // Severity is stored the same way the ADT feed sends it (AL1-4)
        $this->saveAdditionalInfo($patient, [
            'allergies_managed' => 1,
            'allergies_kept' => [json_encode('Peanuts')],
            'new_allergy' => 'Latex',
            'new_allergy_severity' => 'SV',
        ])->assertSessionHasNoErrors();

        $this->assertEquals([
            'Peanuts',
            ['allergen' => 'Latex', 'severity_code' => 'SV', 'severity' => 'Severe', 'status' => 'Active'],
        ], $patient->fresh()->allergies);

        // An unknown severity code is rejected and nothing is saved
        $this->saveAdditionalInfo($patient, [
            'allergies_managed' => 1,
            'new_allergy' => 'Dust',
            'new_allergy_severity' => 'XX',
        ])->assertSessionHasErrors('new_allergy_severity');

        $this->assertCount(2, $patient->fresh()->allergies);
    }

    public function test_patient_details_show_allergy_severity_when_present(): void
    {
        $patient = $this->makePatient();
        // ADT entry with a severity code only, and one with no severity at all
        $patient->update(['allergies' => [
            ['allergen' => 'PEN^Penicillin', 'severity_code' => 'MO', 'status' => 'Active'],
            'Peanuts',
        ]]);

        $html = $this->actingAs(User::factory()->create())
            ->get(route('ward.patient-details', ['patient_id' => $patient->id, 'active_tab' => 'additional']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('"name":"Penicillin","status":"Active","severity":"Moderate"', $html);
        $this->assertStringContainsString('"name":"Peanuts","status":"Active","severity":null', $html);

        // The editor offers severity as optional
        PatientInfoSources::save(['allergies' => 'manual']);
        $this->actingAs(User::factory()->create())
            ->get(route('ward.patient-details', ['patient_id' => $patient->id, 'active_tab' => 'additional']))
            ->assertSee('name="new_allergy_severity"', false)
            ->assertSee('Not specified');
    }

    public function test_directly_managed_fall_risk_can_be_set(): void
    {
        PatientInfoSources::save(['fall_risk' => 'manual']);
        $patient = $this->makePatient();

        $this->saveAdditionalInfo($patient, ['fall_risk' => 'moderate'])->assertSessionHasNoErrors();

        $this->assertSame('moderate', $patient->fresh()->fall_risk);
    }

    public function test_nursing_level_switched_to_adt_becomes_read_only(): void
    {
        PatientInfoSources::save(['nursing_level' => 'adt']);
        $patient = $this->makePatient();

        $this->saveAdditionalInfo($patient)->assertSessionHasNoErrors();

        $this->assertSame('level_1', $patient->fresh()->nursing_level);
    }

    public function test_sources_are_saved_system_wide_from_settings(): void
    {
        $patient = $this->makePatient();

        $this->actingAs(User::factory()->create())
            ->post(route('ward.settings.update'), [
                'setting_type' => 'patient_info_sources',
                'sources' => [
                    'nursing_level' => 'manual',
                    'diet' => 'manual',
                    'fall_risk' => 'adt',
                    'isolation' => 'adt',
                    'allergies' => 'manual',
                ],
            ])
            ->assertSessionHas('settings_tab', 'patient-additional-info')
            ->assertSessionHasNoErrors();

        $this->assertSame('manual', IntegrationSetting::get(PatientInfoSources::KEY)['diet']);
        $this->assertTrue(PatientInfoSources::isManual('diet'));
        $this->assertFalse(PatientInfoSources::isManual('isolation'));

        // Another user now sees the diet editor
        $this->actingAs(User::factory()->create())
            ->get(route('ward.patient-details', ['patient_id' => $patient->id, 'active_tab' => 'additional']))
            ->assertOk()
            ->assertSee('Nil By Mouth (NBM)')
            ->assertSee('Tube / Parenteral Feeding')
            ->assertSee('Diet Orders');
    }

    public function test_settings_reject_unknown_sources(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('ward.settings.update'), [
                'setting_type' => 'patient_info_sources',
                'sources' => ['diet' => 'carrier_pigeon'],
            ])
            ->assertSessionHasErrors('sources.diet');

        $this->assertSame('adt', PatientInfoSources::all()['diet']);
    }
}
