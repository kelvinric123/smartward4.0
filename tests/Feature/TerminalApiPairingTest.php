<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\Hospital;
use App\Models\Nurse;
use App\Models\Patient;
use App\Models\TerminalDevice;
use App\Models\Ward;
use App\Models\WardNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Bedside terminal API (/api/terminal/*): terminals pair with one bed through
 * a nurse-app login and send that token; unpaired terminals only get the
 * patient's initials (or nothing, with TERMINAL_REQUIRE_DEVICE_TOKEN).
 */
class TerminalApiPairingTest extends TestCase
{
    use RefreshDatabase;

    private Ward $ward;
    private Bed $bed;
    private Bed $otherBed;
    private Nurse $nurse;
    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-07 10:00:00'));
        config(['terminal.require_device_token' => false]);

        $hospital = Hospital::create(['name' => 'Test Hospital']);
        $this->ward = Ward::create(['hospital_id' => $hospital->id, 'ward_code' => 'W6', 'ward_name' => 'Ward 6', 'is_active' => true]);
        $this->bed = Bed::create(['ward_id' => $this->ward->id, 'bed_number' => 'D610', 'bed_id' => 'W6-D610', 'bed_display_name' => 'D610', 'status' => 'occupied', 'is_active' => true]);
        $this->otherBed = Bed::create(['ward_id' => $this->ward->id, 'bed_number' => 'D611', 'bed_id' => 'W6-D611', 'bed_display_name' => 'D611', 'status' => 'available', 'is_active' => true]);

        $this->nurse = Nurse::create([
            'name' => 'Aisyah Rahman',
            'registration_number' => 'LJM-1001',
            'designation' => 'STAFF NURSE I',
            'ward_id' => $this->ward->id,
            'app_username' => 'aisyah',
            'app_password' => 'secret123',
            'is_active' => true,
        ]);

        $this->patient = Patient::create([
            'name' => 'Siti Aminah binti Ahmad',
            'mrn' => 'MRN900001',
            'rn' => 'RN900001',
            'ic_passport' => '800202-14-6666',
            'age' => 46,
            'gender' => 'Female',
            'phone' => '013-2224444',
            'ward_id' => $this->ward->id,
            'bed_number' => 'D610',
            'status' => 'admitted',
            'admitted_at' => Carbon::parse('2026-10-06 08:30:00'),
            'is_active' => true,
        ]);
    }

    private function pair(?Bed $bed = null, ?string $currentToken = null): string
    {
        $request = $currentToken ? $this->withToken($currentToken) : $this;

        $response = $request->postJson('/api/terminal/pair', [
            'username' => 'aisyah',
            'password' => 'secret123',
            'bed_id' => ($bed ?? $this->bed)->id,
            'device_name' => 'Bedside terminal',
        ])->assertCreated();

        // withToken() sticks to later requests; each test sets its own
        $this->flushHeaders();

        return $response->json('token');
    }

    private function snapshotUri(?Bed $bed = null): string
    {
        return '/api/terminal/beds/' . ($bed ?? $this->bed)->id . '/snapshot';
    }

    public function test_the_bed_picker_shows_initials_not_names(): void
    {
        $response = $this->getJson('/api/terminal/beds?ward_id=' . $this->ward->id)
            ->assertOk()
            ->assertJsonPath('beds.0.bed_number', 'D610')
            ->assertJsonPath('beds.0.patient_name', 'S. A. B. A.')
            ->assertJsonPath('beds.1.patient_name', null);

        $this->assertStringNotContainsString('Siti', $response->getContent());
    }

    public function test_an_unpaired_terminal_gets_initials_and_no_mrn(): void
    {
        $response = $this->getJson($this->snapshotUri())
            ->assertOk()
            ->assertJsonPath('paired', false)
            ->assertJsonPath('occupied', true)
            ->assertJsonPath('patient.name', 'S. A. B. A.')
            ->assertJsonPath('patient.mrn', null)
            ->assertJsonPath('patient.age', 46);

        $this->assertStringNotContainsString('Siti', $response->getContent());
        $this->assertStringNotContainsString('MRN900001', $response->getContent());
    }

    public function test_a_nurse_pairs_the_terminal_and_it_gets_its_own_bed_in_full(): void
    {
        $token = $this->pair();

        $device = TerminalDevice::sole();
        $this->assertSame($this->bed->id, $device->bed_id);
        $this->assertSame($this->nurse->id, $device->paired_by_nurse_id);
        $this->assertSame(hash('sha256', $token), $device->token);

        $this->withToken($token)->getJson($this->snapshotUri())
            ->assertOk()
            ->assertJsonPath('paired', true)
            ->assertJsonPath('patient.name', 'Siti Aminah binti Ahmad')
            ->assertJsonPath('patient.mrn', 'MRN900001');

        $this->assertNotNull($device->fresh()->last_seen_at);

        // Another bed is off limits to this terminal
        $this->withToken($token)->getJson($this->snapshotUri($this->otherBed))
            ->assertForbidden()
            ->assertJsonPath('code', 'terminal_wrong_bed');
        $this->withToken($token)->getJson('/api/terminal/beds/' . $this->otherBed->id . '/requests')->assertForbidden();
    }

    public function test_pairing_needs_a_valid_nurse_login(): void
    {
        $this->postJson('/api/terminal/pair', ['username' => 'aisyah', 'password' => 'wrong', 'bed_id' => $this->bed->id])
            ->assertUnauthorized();
        $this->postJson('/api/terminal/pair', ['username' => 'nobody', 'password' => 'secret123', 'bed_id' => $this->bed->id])
            ->assertUnauthorized();

        $this->nurse->update(['is_active' => false]);
        $this->postJson('/api/terminal/pair', ['username' => 'aisyah', 'password' => 'secret123', 'bed_id' => $this->bed->id])
            ->assertUnauthorized();

        $this->postJson('/api/terminal/pair', ['username' => 'aisyah', 'password' => 'secret123', 'bed_id' => 999999])
            ->assertUnprocessable();

        $this->assertSame(0, TerminalDevice::count());
    }

    public function test_a_nurse_can_pair_with_their_registration_number_like_the_nurse_app_login(): void
    {
        $this->postJson('/api/terminal/pair', ['username' => 'LJM-1001', 'password' => 'secret123', 'bed_id' => $this->bed->id])
            ->assertCreated()
            ->assertJsonPath('device.paired_by', 'Aisyah Rahman');

        // The nurse app's own login shares the lookup (Nurse::findByAppLogin)
        $this->postJson('/api/nurse/login', ['username' => 'LJM-1001', 'password' => 'secret123'])->assertOk();
        $this->postJson('/api/nurse/login', ['username' => 'aisyah', 'password' => 'secret123'])->assertOk();
        $this->postJson('/api/nurse/login', ['username' => 'aisyah', 'password' => 'wrong'])->assertUnauthorized();
    }

    public function test_an_unknown_or_revoked_token_is_refused(): void
    {
        $this->withToken('not-a-real-token')->getJson($this->snapshotUri())
            ->assertUnauthorized()
            ->assertJsonPath('code', 'terminal_not_paired');
        $this->flushHeaders();

        $token = $this->pair();
        $this->withToken($token)->postJson('/api/terminal/unpair')->assertOk();
        $this->assertNotNull(TerminalDevice::sole()->revoked_at);

        $this->withToken($token)->getJson($this->snapshotUri())->assertUnauthorized();
    }

    public function test_pairing_again_moves_the_terminal_and_retires_its_old_token(): void
    {
        $first = $this->pair();
        $second = $this->pair($this->otherBed, $first);

        $this->withToken($first)->getJson($this->snapshotUri())->assertUnauthorized();
        $this->flushHeaders();
        $this->withToken($second)->getJson($this->snapshotUri($this->otherBed))
            ->assertOk()
            ->assertJsonPath('paired', true)
            ->assertJsonPath('occupied', false);

        $this->assertSame(1, TerminalDevice::whereNull('revoked_at')->count());
    }

    public function test_requiring_a_device_token_turns_unpaired_terminals_away(): void
    {
        config(['terminal.require_device_token' => true]);

        $this->getJson($this->snapshotUri())->assertUnauthorized()->assertJsonPath('code', 'terminal_not_paired');
        $this->getJson('/api/terminal/beds/' . $this->bed->id . '/requests')->assertUnauthorized();
        $this->postJson('/api/terminal/beds/' . $this->bed->id . '/requests', ['category' => 'water', 'label' => 'Water'])
            ->assertUnauthorized();
        $this->assertSame(0, WardNotification::count());

        // Setup still works, so the terminal can be paired
        $this->getJson('/api/terminal/ping')->assertOk();
        $this->getJson('/api/terminal/wards')->assertOk();
        $this->getJson('/api/terminal/beds?ward_id=' . $this->ward->id)->assertOk();

        $token = $this->pair();
        $this->withToken($token)->getJson($this->snapshotUri())->assertOk()->assertJsonPath('patient.name', 'Siti Aminah binti Ahmad');
        $this->withToken($token)->postJson('/api/terminal/beds/' . $this->bed->id . '/requests', ['category' => 'water', 'label' => 'Water'])
            ->assertCreated();
    }

    public function test_smart_calls_from_an_unpaired_terminal_show_initials_but_reach_the_ward_in_full(): void
    {
        $response = $this->postJson('/api/terminal/beds/' . $this->bed->id . '/requests', ['category' => 'water', 'label' => 'Water'])
            ->assertCreated();

        $this->assertStringContainsString('S. A. B. A.', $response->json('request.message'));
        $this->assertStringNotContainsString('Siti', $response->getContent());

        // The ward dashboard's notification keeps the full name
        $this->assertStringContainsString('Siti Aminah binti Ahmad', WardNotification::sole()->message);

        $list = $this->getJson('/api/terminal/beds/' . $this->bed->id . '/requests')->assertOk();
        $this->assertStringNotContainsString('Siti', $list->getContent());

        $token = $this->pair();
        $this->withToken($token)->getJson('/api/terminal/beds/' . $this->bed->id . '/requests')
            ->assertOk()
            ->assertJsonPath('requests.0.message', WardNotification::sole()->message);
    }

    public function test_the_farewell_screen_of_an_unpaired_terminal_has_no_name(): void
    {
        $this->patient->update(['status' => Patient::STATUS_DISCHARGED, 'discharged_at' => now()->subHour()]);

        $response = $this->getJson($this->snapshotUri())
            ->assertOk()
            ->assertJsonPath('occupied', false)
            ->assertJsonPath('vacancy_reason', 'discharged')
            ->assertJsonPath('discharged_patient.name', 'S. A. B. A.')
            ->assertJsonPath('discharged_patient.first_name', null);
        $this->assertStringNotContainsString('Siti', $response->getContent());

        $token = $this->pair();
        $this->withToken($token)->getJson($this->snapshotUri())
            ->assertOk()
            ->assertJsonPath('discharged_patient.first_name', 'Siti');
    }

    public function test_the_artisan_command_lists_and_revokes_pairings(): void
    {
        $token = $this->pair();
        $device = TerminalDevice::sole();

        $this->artisan('terminal:devices')->expectsOutputToContain('Aisyah Rahman')->assertSuccessful();
        $this->artisan('terminal:devices', ['--revoke' => $device->id])->assertSuccessful();

        $this->withToken($token)->getJson($this->snapshotUri())->assertUnauthorized();

        $this->flushHeaders();
        $again = $this->pair();
        $this->artisan('terminal:devices', ['--revoke-bed' => $this->bed->id])->assertSuccessful();
        $this->withToken($again)->getJson($this->snapshotUri())->assertUnauthorized();
    }
}
