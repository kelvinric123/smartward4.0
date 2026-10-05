<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\EkadBedMapping;
use App\Models\EkadConfiguration;
use App\Models\Hospital;
use App\Models\IsolationType;
use App\Models\Patient;
use App\Models\User;
use App\Models\Ward;
use App\Models\WardType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The EKad screen shows the patient's isolation precaution (EKad field
 * isolation_type), from Patient Details > Isolation Precautions on the ward
 * and critical care dashboards, or from the ADT feed.
 */
class EkadIsolationTypeTest extends TestCase
{
    use RefreshDatabase;

    private const MAC = 'D43D39000001';

    private User $user;
    private Ward $icu;
    private Bed $bed;

    protected function setUp(): void
    {
        parent::setUp();

        // Never paint a real screen from a test
        Http::preventStrayRequests();
        Http::fake(function (HttpRequest $request) {
            if (str_ends_with($request->url(), '/api/v1/template/batchPaintingByJson')) {
                return Http::response(['code' => 200, 'msg' => 'ok']);
            }

            return null;
        });

        $this->user = User::factory()->create();

        EkadConfiguration::create([
            'base_url' => 'http://seekink.test/cloud/prod-api',
            'username' => 'moe',
            'password' => 'secret',
            'template_id' => '2000477842757914624',
            'bearer_token' => 'token',
            'token_expires_at' => now()->addHour(),
            'auto_push_enabled' => true,
            'is_active' => true,
        ]);

        foreach (['CI' => 'Contact Isolation', 'DI' => 'Droplet Isolation', 'AI' => 'Airborne Isolation'] as $code => $name) {
            IsolationType::create(['code' => $code, 'name' => $name, 'is_active' => true]);
        }

        $type = WardType::create(['code' => 'ICU', 'name' => 'ICU type', 'is_critical_care' => true, 'is_active' => true]);
        $this->icu = Ward::create([
            'hospital_id' => Hospital::create(['name' => 'Test Hospital'])->id,
            'ward_code' => 'ICU', 'ward_name' => 'Intensive Care Unit', 'ward_type_id' => $type->id, 'is_active' => true,
        ]);
        $this->bed = Bed::create([
            'ward_id' => $this->icu->id, 'bed_number' => 'ICU-01', 'bed_id' => 'ICU-ICU-01',
            'bed_display_name' => 'ICU-01', 'status' => 'available', 'is_active' => true,
        ]);
        EkadBedMapping::create(['bed_id' => $this->bed->id, 'mac_address' => self::MAC]);
    }

    private function makePatient(string $isolationType, bool $admitted = false): Patient
    {
        // Set up without observers, so only what the test does is painted
        return Model::withoutEvents(function () use ($isolationType, $admitted) {
            $patient = Patient::create([
                'name' => 'Tan Mei Ling', 'mrn' => 'MRN70001', 'rn' => 'RN-70001', 'ic_passport' => '800101-10-7001',
                'age' => 46, 'gender' => 'Female', 'phone' => '012-0000000', 'is_active' => true,
                'isolation_type' => $isolationType,
                'status' => $admitted ? Patient::STATUS_ADMITTED : 'active',
                'ward_id' => $admitted ? $this->icu->id : null,
                'bed_number' => $admitted ? 'ICU-01' : null,
                'admitted_at' => $admitted ? now()->subDay() : null,
            ]);

            if ($admitted) {
                $this->bed->update(['status' => 'occupied', 'patient_id' => $patient->id]);
            }

            return $patient;
        });
    }

    /** The isolation_type shown by each paint of the screen, in order */
    private function paintedIsolation(): array
    {
        return Http::recorded(fn (HttpRequest $request) => str_ends_with($request->url(), '/batchPaintingByJson'))
            ->map(function (array $sent) {
                $this->assertSame([self::MAC], $sent[0]['macList']);

                return $sent[0]['data'][0]['isolation_type'];
            })
            ->values()->all();
    }

    public function test_admission_from_the_critical_care_dashboard_shows_the_isolation_precaution_and_discharge_clears_it(): void
    {
        $patient = $this->makePatient('CI');
        $dashboard = route('critical-care.dashboard', ['ward_id' => $this->icu->id]);

        $this->actingAs($this->user)->from($dashboard)->post(route('ward.admit-patient'), [
            'patient_id' => $patient->id, 'ward_id' => $this->icu->id, 'bed_number' => 'ICU-01',
        ])->assertRedirect($dashboard)->assertSessionHas('success');

        $this->actingAs($this->user)->post(route('ward.discharge-patient'), ['patient_id' => $patient->id])
            ->assertSessionHas('success');

        $this->assertSame(['CONTACT ISOLATION', '-'], $this->paintedIsolation());
    }

    public function test_changing_isolation_precautions_in_patient_details_repaints_the_screen(): void
    {
        $patient = $this->makePatient('none', admitted: true);

        $this->actingAs($this->user)->post(route('ward.update-patient-clinical'), [
            'patient_id' => $patient->id, 'isolation_type' => 'AI',
        ])->assertSessionHas('success');

        $this->assertSame('AI', $patient->fresh()->isolation_type);
        $painted = $this->paintedIsolation();
        $this->assertNotEmpty($painted);
        $this->assertSame(['AIRBORNE ISOLATION'], array_values(array_unique($painted)));

        // Back to no isolation precaution
        $this->actingAs($this->user)->post(route('ward.update-patient-clinical'), [
            'patient_id' => $patient->id, 'isolation_type' => 'none',
        ])->assertSessionHas('success');

        $this->assertSame('-', last($this->paintedIsolation()));
    }

    public function test_an_isolation_change_from_another_source_repaints_the_screen(): void
    {
        $patient = $this->makePatient('none', admitted: true);

        // As the ADT feed saves it: a code from Isolation Types, then one it only knows by its own name
        $patient->update(['isolation_type' => 'DI']);
        $patient->update(['isolation_type' => 'tb']);
        // Other fields alone do not repaint the screen
        $patient->update(['phone' => '013-1111111']);

        $this->assertSame(['DROPLET ISOLATION', 'TB'], $this->paintedIsolation());
    }

    public function test_sync_preview_and_manual_push_carry_the_isolation_type(): void
    {
        $this->makePatient('CI', admitted: true);

        $preview = $this->actingAs($this->user)->getJson(route('ekad.sync-preview'))->assertOk()->json('data.0.payload');
        $this->assertSame('CONTACT ISOLATION', $preview['isolation_type']);

        $this->actingAs($this->user)->postJson(route('ekad.push'), [
            'token' => 'token', 'template_id' => '2000477842757914624', 'mac_list' => [self::MAC],
            'patient_name' => 'TEST PATIENT', 'isolation_type' => 'DROPLET ISOLATION',
        ])->assertOk()->assertJsonPath('payload.data.0.isolation_type', 'DROPLET ISOLATION');

        $this->actingAs($this->user)->postJson(route('ekad.push'), [
            'token' => 'token', 'template_id' => '2000477842757914624', 'mac_list' => [self::MAC],
            'patient_name' => 'TEST PATIENT',
        ])->assertOk()->assertJsonPath('payload.data.0.isolation_type', '-');
    }
}
