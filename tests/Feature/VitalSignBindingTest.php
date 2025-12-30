<?php

namespace Tests\Feature;

use App\Models\ApiUser;
use App\Models\GatewayNurseBinding;
use App\Models\Nurse;
use App\Models\Patient;
use App\Models\User;
use App\Models\VitalSign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class VitalSignBindingTest extends TestCase
{
    use RefreshDatabase;

    public function test_gateway_binding_attributes_vital_signs_to_nurse()
    {
        $this->withoutExceptionHandling();

        // 1. Setup Data
        $apiUser = ApiUser::create([
            'name' => 'Test Gateway',
            'username' => 'gateway01',
            'password' => 'password',
            'is_active' => true,
        ]);

        $token = $apiUser->generateToken();

        $nurse = Nurse::create([
            'name' => 'Test Nurse',
            'registration_number' => 'RN12345',
            'qualification' => 'Degree',
            'is_active' => true,
        ]);

        $patient = Patient::create([
            'name' => 'John Doe',
            'mrn' => 'MRN001',
            'gender' => 'Male',
            'date_of_birth' => '1980-01-01',
            'is_active' => true,
        ]);

        // Simulate admission
        $patient->update(['bed_id' => 1]); // Mock bed ID

        // 2. Create Binding using the Controller logic (simulating the POST request)
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)
            ->post(route('vital-sign-integration.bind'), [
                'api_user_id' => $apiUser->id,
                'nurse_id' => $nurse->id,
                'duration_hours' => 8,
            ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('gateway_nurse_bindings', [
            'api_user_id' => $apiUser->id,
            'nurse_id' => $nurse->id,
        ]);

        // 3. Send Vital Sign via API
        $vitalResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/vital-sign/reading', [
                    'patient_mrn' => 'MRN001',
                    'systolic_bp' => 120,
                    'diastolic_bp' => 80,
                    'pulse_rate' => 75,
                ]);

        $vitalResponse->assertStatus(200);

        // 4. Verify Operator Attribution
        $vitalSign = VitalSign::latest()->first();
        $this->assertEquals($nurse->id, $vitalSign->operator_id);
    }
}
