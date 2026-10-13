<?php

namespace Tests\Feature;

use App\Models\Consultant;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\PatientFlowCommandCentre;
use App\Models\Ward;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The public Patient Flow Command Centre display: its settings decide what
 * the server sends, not only what the browser shows.
 */
class CommandCentreFlowPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private PatientFlowCommandCentre $centre;

    protected function setUp(): void
    {
        parent::setUp();

        $hospital = Hospital::create(['name' => 'Test Hospital']);
        $ward = Ward::create(['hospital_id' => $hospital->id, 'ward_code' => 'W6', 'ward_name' => 'Ward 6', 'is_active' => true]);
        $consultant = Consultant::create(['name' => 'Dr Tan Ah Kow', 'registration_number' => 'MMC1001', 'is_active' => true]);

        Patient::create([
            'name' => 'Siti Aminah', 'mrn' => 'MRN900001', 'rn' => 'RN900001', 'ic_passport' => '800202-14-6666',
            'age' => 46, 'gender' => 'Female', 'phone' => '013-2224444', 'ward_id' => $ward->id,
            'bed_number' => 'D610', 'status' => 'admitted', 'admitted_at' => now(), 'consultant_id' => $consultant->id,
            'is_active' => true,
        ]);

        $this->centre = PatientFlowCommandCentre::create([
            'name' => 'Bed Management',
            'login_username' => 'bedmgmt',
            'login_password' => Hash::make('secret123'),
            'settings' => PatientFlowCommandCentre::defaultSettings(),
            'is_active' => true,
        ]);
        $this->centre->wards()->attach($ward->id);
    }

    private function settings(array $overrides): void
    {
        $this->centre->update(['settings' => array_merge(PatientFlowCommandCentre::defaultSettings(), $overrides)]);
    }

    private function wallData()
    {
        $this->post('/command-centre/login', ['username' => 'bedmgmt', 'password' => 'secret123'])
            ->assertRedirect(route('command-centre.view', $this->centre->id));

        return $this->getJson('/command-centre/' . $this->centre->id . '/data')->assertOk();
    }

    public function test_names_stay_on_the_server_when_the_display_hides_them(): void
    {
        $this->settings(['show_patient_name' => false]);

        $response = $this->wallData()
            ->assertJsonPath('wardData.0.bed_details.0.bed_number', 'D610')
            ->assertJsonPath('wardData.0.bed_details.0.patient_name', null)
            ->assertJsonPath('wardData.0.bed_details.0.mrn', 'MRN900001')
            ->assertJsonPath('wardData.0.bed_details.0.consultant_name', 'Dr Tan Ah Kow');

        $this->assertStringNotContainsString('Siti', $response->getContent());

        // The first page render embeds the same data
        $page = $this->withoutVite()->get('/command-centre/' . $this->centre->id . '/view')->assertOk();
        $this->assertStringNotContainsString('Siti', $page->getContent());
    }

    public function test_names_are_sent_when_the_display_shows_them(): void
    {
        $this->settings(['show_patient_name' => true]);

        $this->wallData()->assertJsonPath('wardData.0.bed_details.0.patient_name', 'Siti Aminah');
    }

    public function test_hidden_consultants_and_bed_details_are_not_sent(): void
    {
        $this->settings(['show_consultant' => false]);
        $response = $this->wallData()->assertJsonPath('wardData.0.bed_details.0.consultant_name', null);
        $this->assertStringNotContainsString('Dr Tan', $response->getContent());

        $this->settings(['show_bed_details' => false, 'show_patient_name' => true]);
        $response = $this->getJson('/command-centre/' . $this->centre->id . '/data')
            ->assertOk()
            ->assertJsonPath('wardData.0.bed_details', [])
            ->assertJsonPath('wardData.0.occupied_beds', 1);
        $this->assertStringNotContainsString('MRN900001', $response->getContent());
    }
}
