<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use App\Models\Ward;
use App\Models\WardDashboardSetting;
use App\Models\WardNotification;
use App\Models\WardType;
use App\Support\PatientPrivacy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WardDashboardPrivacyRefreshTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Ward $ward;
    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $hospital = Hospital::create(['name' => 'Test Hospital']);
        $this->ward = Ward::create(['hospital_id' => $hospital->id, 'ward_code' => 'MW1', 'ward_name' => 'Medical Ward 1', 'is_active' => true]);
        Bed::create(['ward_id' => $this->ward->id, 'bed_number' => 'B01', 'bed_id' => 'MW1-B01', 'bed_display_name' => 'B01', 'status' => 'available', 'is_active' => true]);

        $this->patient = Patient::create([
            'name' => 'Lim Wei Ming',
            'mrn' => 'MRN800001',
            'rn' => 'RN800001',
            'ic_passport' => '700101-10-5555',
            'age' => 56,
            'gender' => 'Male',
            'phone' => '012-3456789',
            'ward_id' => $this->ward->id,
            'bed_number' => 'B01',
            'status' => 'admitted',
            'is_active' => true,
            'admitted_at' => now()->subDay(),
        ]);
    }

    private function saveDisplay(array $display)
    {
        return $this->actingAs($this->user)->post(route('ward.settings.update'), [
            'setting_type' => 'dashboard_display',
            'dashboard_display_config' => json_encode($display),
        ]);
    }

    private function savedDisplay(): array
    {
        return WardDashboardSetting::where('user_id', $this->user->id)->first()->dashboard_display;
    }

    public function test_names_are_masked_per_mode(): void
    {
        $name = 'John Michael Smith';

        $this->assertSame('John Michael Smith', PatientPrivacy::name($name, 'full'));
        $this->assertSame('John M****** S****', PatientPrivacy::name($name, 'first_only'));
        $this->assertSame('J*** M****** Smith', PatientPrivacy::name($name, 'last_only'));
        $this->assertSame('J.M.S.', PatientPrivacy::name($name, 'initials'));
        $this->assertSame('John S.', PatientPrivacy::name($name, 'first_last_initial'));
        $this->assertSame('**** ******* *****', PatientPrivacy::name($name, 'all_asterisk'));

        // Counts characters, not bytes
        $this->assertSame('Zoë Á.', PatientPrivacy::name('Zoë Ánh', 'first_last_initial'));
        $this->assertSame('*** ***', PatientPrivacy::name('Zoë Ánh', 'all_asterisk'));
        $this->assertSame('', PatientPrivacy::name(null, 'initials'));
    }

    public function test_mrns_are_masked_per_mode(): void
    {
        $this->assertSame('MRN800001', PatientPrivacy::mrn('MRN800001', 'full'));
        $this->assertSame('*****0001', PatientPrivacy::mrn('MRN800001', 'last4'));
        $this->assertSame('****', PatientPrivacy::mrn('1234', 'last4'));
        $this->assertNull(PatientPrivacy::mrn('MRN800001', 'hidden'));
    }

    public function test_saved_refresh_interval_and_privacy_modes_are_held_to_known_values(): void
    {
        $this->saveDisplay(['refresh_interval' => 300, 'patient_name_mask' => 'initials', 'patient_mrn_mask' => 'last4'])
            ->assertSessionHas('success');
        $this->assertSame(300, $this->savedDisplay()['refresh_interval']);
        $this->assertSame('initials', $this->savedDisplay()['patient_name_mask']);
        $this->assertSame('last4', $this->savedDisplay()['patient_mrn_mask']);

        $this->saveDisplay(['refresh_interval' => 0]);
        $this->assertSame(0, $this->savedDisplay()['refresh_interval']);

        $this->saveDisplay(['refresh_interval' => 3]);
        $this->assertSame(10, $this->savedDisplay()['refresh_interval']);

        $this->saveDisplay(['refresh_interval' => 99999]);
        $this->assertSame(3600, $this->savedDisplay()['refresh_interval']);

        $this->saveDisplay(['refresh_interval' => 'soon', 'patient_name_mask' => 'bogus', 'patient_mrn_mask' => 'bogus']);
        $this->assertSame(60, $this->savedDisplay()['refresh_interval']);
        $this->assertSame('full', $this->savedDisplay()['patient_name_mask']);
        $this->assertSame('full', $this->savedDisplay()['patient_mrn_mask']);
    }

    public function test_dashboard_reloads_at_the_saved_interval(): void
    {
        $dashboard = route('ward.dashboard', ['ward_id' => $this->ward->id]);

        // Unchanged default
        $this->actingAs($this->user)->get($dashboard)->assertOk()->assertSee('refreshInterval: 60,', false);

        $this->saveDisplay(['refresh_interval' => 300]);
        $this->actingAs($this->user)->get($dashboard)->assertSee('refreshInterval: 300,', false);

        $this->saveDisplay(['refresh_interval' => 0]);
        $this->actingAs($this->user)->get($dashboard)->assertSee('refreshInterval: 0,', false);
    }

    public function test_privacy_mode_masks_patient_identity_on_the_dashboard(): void
    {
        $dashboard = route('ward.dashboard', ['ward_id' => $this->ward->id]);

        $this->actingAs($this->user)->get($dashboard)
            ->assertSee('Lim Wei Ming')
            ->assertSee('MRN800001')
            ->assertDontSee('Privacy Mode: patient names');

        $this->saveDisplay(['patient_name_mask' => 'initials', 'patient_mrn_mask' => 'last4']);

        // Not even in hover titles or data attributes
        $this->actingAs($this->user)->get($dashboard)
            ->assertOk()
            ->assertSee('L.W.M.')
            ->assertSee('*****0001')
            ->assertDontSee('Lim Wei Ming')
            ->assertDontSee('MRN800001')
            ->assertSee('Privacy Mode: patient names');

        $this->saveDisplay(['patient_name_mask' => 'initials', 'patient_mrn_mask' => 'hidden']);

        $this->actingAs($this->user)->get($dashboard)
            ->assertDontSee('MRN800001')
            ->assertDontSee('*****0001');
    }

    public function test_critical_care_dashboard_follows_the_same_settings(): void
    {
        $type = WardType::create(['code' => 'ICU', 'name' => 'ICU type', 'is_critical_care' => true, 'is_active' => true]);
        $this->ward->update(['ward_type_id' => $type->id]);
        $this->saveDisplay(['patient_name_mask' => 'initials', 'patient_mrn_mask' => 'last4', 'refresh_interval' => 120]);

        $this->actingAs($this->user)->get(route('critical-care.dashboard', ['ward_id' => $this->ward->id]))
            ->assertOk()
            ->assertSee('refreshInterval: 120,', false)
            ->assertSee('L.W.M.')
            ->assertDontSee('Lim Wei Ming')
            ->assertDontSee('MRN800001');
    }

    public function test_notifications_follow_privacy_mode(): void
    {
        WardNotification::createPatientRequest($this->ward->id, $this->patient->id, 'B01', 'pain', 'Pain relief', $this->patient->name);
        $notifications = route('ward.notifications', ['ward_id' => $this->ward->id]);

        $this->actingAs($this->user)->getJson($notifications)
            ->assertJsonPath('pending.0.patient_name', 'Lim Wei Ming')
            ->assertJsonPath('pending.0.patient_mrn', 'MRN800001');

        $this->saveDisplay(['patient_name_mask' => 'first_last_initial', 'patient_mrn_mask' => 'hidden']);

        $response = $this->actingAs($this->user)->getJson($notifications)
            ->assertJsonPath('pending.0.patient_name', 'Lim M.')
            ->assertJsonPath('pending.0.patient_mrn', null);

        $this->assertStringNotContainsString('Lim Wei Ming', $response->getContent());
        $this->assertStringContainsString('Lim M. (Bed B01)', $response->json('pending.0.message'));
    }
}
