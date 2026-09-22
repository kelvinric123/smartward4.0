<?php

namespace Tests\Feature;

use App\Models\Anaesthetist;
use App\Models\Bed;
use App\Models\Consultant;
use App\Models\Hospital;
use App\Models\Nurse;
use App\Models\Patient;
use App\Models\PatientCareProvider;
use App\Models\ShiftSetting;
use App\Models\User;
use App\Models\Ward;
use App\Models\WardDashboardSetting;
use App\Models\WardScheduleAssignment;
use App\Models\WardSpecialDuty;
use App\Services\PatientRoster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PatientCareTeamTabsTest extends TestCase
{
    use RefreshDatabase;

    private const PASSPHRASE = 'askdrtai';

    private User $user;
    private Ward $ward;
    private Bed $bed;
    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-09-21 10:00:00')); // AM shift

        $this->user = User::factory()->create();
        $hospital = Hospital::create(['name' => 'Test Hospital']);
        $this->ward = Ward::create(['hospital_id' => $hospital->id, 'ward_code' => 'W6', 'ward_name' => 'Ward 6', 'is_active' => true]);
        $this->bed = Bed::create(['ward_id' => $this->ward->id, 'bed_number' => 'D610', 'bed_id' => 'W6-D610', 'bed_display_name' => 'D610', 'status' => 'occupied', 'is_active' => true]);
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
            'is_active' => true,
        ]);
    }

    private function details(array $query = [])
    {
        return $this->actingAs($this->user)
            ->get(route('ward.patient-details', ['patient_id' => $this->patient->id] + $query));
    }

    private function anaesthetist(string $name, string $code): Anaesthetist
    {
        return Anaesthetist::create(['name' => $name, 'personnel_code' => $code, 'registration_number' => 'MMC-' . $code, 'phone' => '012-000' . substr($code, -4), 'is_active' => true]);
    }

    private function nurse(string $name, string $designation = 'STAFF NURSE I'): Nurse
    {
        return Nurse::create(['name' => $name, 'registration_number' => 'LJM-' . crc32($name), 'designation' => $designation, 'is_active' => true]);
    }

    public function test_care_provider_tab_is_now_consultant_and_anaesthetists_have_their_own_tab(): void
    {
        $consultant = Consultant::create(['name' => 'Dr. Tan Wei Liang', 'personnel_code' => 'C100', 'registration_number' => 'MMC-C100', 'is_active' => true]);
        $anaesthetist = $this->anaesthetist('Dr. Farah Idris', 'A200');

        PatientCareProvider::create(['patient_id' => $this->patient->id, 'role' => 'attending', 'doctor_code' => 'C100', 'doctor_name' => 'TAN', 'consultant_id' => $consultant->id, 'source' => 'adt', 'is_active' => true]);
        PatientCareProvider::create(['patient_id' => $this->patient->id, 'role' => 'consulting', 'doctor_code' => 'A200', 'doctor_name' => 'FARAH', 'anaesthetist_id' => $anaesthetist->id, 'source' => 'adt', 'is_active' => true]);

        $this->details()
            ->assertOk()
            ->assertSeeInOrder(['Vital Signs', 'Consultant', 'Anaesthetist', 'Nurses', 'Infusion Management'])
            ->assertDontSee('Care Provider')
            ->assertSee('Dr. Tan Wei Liang')
            // The anaesthetist is no longer listed as a consulting doctor...
            ->assertSee('No consulting doctor assigned from ADT')
            // ...but on the Anaesthetist tab, with where ADT put them
            ->assertSee('Dr. Farah Idris')
            ->assertSee('Consulting Doctor (PV1-9)');
    }

    public function test_an_anaesthetist_can_be_added_and_removed_with_the_passphrase(): void
    {
        $anaesthetist = $this->anaesthetist('Dr. Farah Idris', 'A200');

        $this->actingAs($this->user)
            ->post(route('ward.care-providers.store-anaesthetist'), ['patient_id' => $this->patient->id, 'anaesthetist_id' => $anaesthetist->id])
            ->assertRedirect(route('ward.patient-details', ['patient_id' => $this->patient->id, 'active_tab' => 'anaesthetist']))
            ->assertSessionHas('success');

        $provider = PatientCareProvider::where('anaesthetist_id', $anaesthetist->id)->firstOrFail();
        $this->assertSame(PatientCareProvider::SOURCE_MANUAL, $provider->source);
        $this->assertSame(PatientCareProvider::ROLE_CONSULTING, $provider->role);
        $this->assertSame('A200', $provider->doctor_code);

        $this->details(['active_tab' => 'anaesthetist'])
            ->assertSee('Dr. Farah Idris')
            ->assertSee('Added manually')
            ->assertSee('Phone: 012-000A200');

        // Adding the same one again is refused
        $this->actingAs($this->user)
            ->post(route('ward.care-providers.store-anaesthetist'), ['patient_id' => $this->patient->id, 'anaesthetist_id' => $anaesthetist->id])
            ->assertSessionHas('error');
        $this->assertSame(1, PatientCareProvider::where('anaesthetist_id', $anaesthetist->id)->count());

        // A wrong passphrase keeps them, and comes back to the Anaesthetist tab
        $page = route('ward.patient-details', ['patient_id' => $this->patient->id]);
        $this->actingAs($this->user)->from($page)->followingRedirects()
            ->delete(route('ward.care-providers.destroy', $provider), ['active_tab' => 'anaesthetist', 'delete_passphrase' => 'wrong'])
            ->assertSee('Delete failed')
            ->assertSee('"activeTab":"anaesthetist"', false);
        $this->assertTrue($provider->fresh()->is_active);

        $this->actingAs($this->user)
            ->delete(route('ward.care-providers.destroy', $provider), ['active_tab' => 'anaesthetist', 'delete_passphrase' => self::PASSPHRASE])
            ->assertRedirect(route('ward.patient-details', ['patient_id' => $this->patient->id, 'active_tab' => 'anaesthetist']))
            ->assertSessionHas('success');
        $this->assertFalse($provider->fresh()->is_active);
    }

    public function test_manually_added_consultant_can_now_be_removed(): void
    {
        $consultant = Consultant::create(['name' => 'Dr. Wong Mei Ling', 'personnel_code' => 'C300', 'registration_number' => 'MMC-C300', 'is_active' => true]);

        $this->actingAs($this->user)
            ->post(route('ward.care-providers.store'), ['patient_id' => $this->patient->id, 'consultant_id' => $consultant->id, 'role' => 'consulting'])
            ->assertSessionHas('success');
        $provider = PatientCareProvider::where('consultant_id', $consultant->id)->firstOrFail();

        // The Remove control now asks for the passphrase instead of sending a delete that always failed
        $this->details(['active_tab' => 'careprovider'])
            ->assertSee(route('ward.care-providers.destroy', $provider->id), false)
            ->assertSee('name="delete_passphrase"', false);

        $this->actingAs($this->user)
            ->delete(route('ward.care-providers.destroy', $provider), ['active_tab' => 'careprovider', 'delete_passphrase' => self::PASSPHRASE])
            ->assertRedirect(route('ward.patient-details', ['patient_id' => $this->patient->id, 'active_tab' => 'careprovider']));
        $this->assertFalse($provider->fresh()->is_active);
    }

    public function test_anaesthetist_on_the_patient_record_is_listed_and_not_duplicated(): void
    {
        $anaesthetist = $this->anaesthetist('Dr. Kumar Selvam', 'A400');
        $this->patient->update(['anaesthetist_id' => $anaesthetist->id]);

        $this->details(['active_tab' => 'anaesthetist'])
            ->assertSee('Dr. Kumar Selvam')
            ->assertSee('Patient record');

        $this->actingAs($this->user)
            ->post(route('ward.care-providers.store-anaesthetist'), ['patient_id' => $this->patient->id, 'anaesthetist_id' => $anaesthetist->id])
            ->assertSessionHas('error');
        $this->assertSame(0, PatientCareProvider::count());
    }

    public function test_nurses_tab_shows_the_roster_for_the_patients_bed(): void
    {
        $aisyah = $this->nurse('Nur Aisyah', 'SENIOR STAFF NURSE II');
        $student = $this->nurse('Lee Jia Hui', 'Student nurse');
        $aisyah->taggingNurses()->attach($student->id);
        $ravi = $this->nurse('Ravi Chandran');
        $mei = $this->nurse('Mei Ling');
        $leader = $this->nurse('Kak Ros', 'Nurse Clinician');

        $today = now()->toDateString();
        $tomorrow = now()->addDay()->toDateString();
        WardScheduleAssignment::create(['ward_id' => $this->ward->id, 'bed_id' => $this->bed->id, 'nurse_id' => $aisyah->id, 'scheduled_date' => $today, 'shift' => 'AM']);
        WardScheduleAssignment::create(['ward_id' => $this->ward->id, 'bed_id' => $this->bed->id, 'nurse_id' => $ravi->id, 'scheduled_date' => $today, 'shift' => 'PM']);
        WardScheduleAssignment::create(['ward_id' => $this->ward->id, 'bed_id' => $this->bed->id, 'nurse_id' => $mei->id, 'scheduled_date' => $tomorrow, 'shift' => 'ON']);
        WardSpecialDuty::create(['ward_id' => $this->ward->id, 'nurse_id' => $leader->id, 'date' => $today, 'shift' => 'AM', 'duty_type' => 'team_leader']);

        $roster = PatientRoster::forPatient($this->patient);
        $this->assertSame('AM', $roster['current']['code']);
        $this->assertSame($aisyah->id, $roster['current']['nurse']->id);
        $this->assertSame($leader->id, $roster['current']['team_leader']->id);
        $this->assertSame($mei->id, $roster['days'][1]['shifts'][2]['nurse']->id);
        $this->assertNull($roster['days'][0]['shifts'][2]['nurse'], 'tonight is not rostered yet');

        $this->details(['open_tab' => 'nurses'])
            ->assertOk()
            ->assertSee('"activeTab":"nurses"', false)
            ->assertSee('From the ward roster for bed')
            ->assertSee('On duty now')
            ->assertSee('Nur Aisyah')
            ->assertSee('Tagging: Lee Jia Hui')
            ->assertSee('Kak Ros')
            ->assertSee('Ravi Chandran')
            ->assertSee('Mei Ling')
            ->assertSee('Not rostered')
            ->assertSee('Open roster');
    }

    public function test_nurses_tab_without_a_bed_or_a_rostered_nurse(): void
    {
        $this->details(['open_tab' => 'nurses'])
            ->assertSee('No nurse rostered to this bed');

        $this->patient->update(['bed_number' => null]);
        $this->details(['open_tab' => 'nurses'])
            ->assertSee('not in a bed');
    }

    public function test_the_tabs_can_be_switched_off_per_user_in_settings(): void
    {
        $this->actingAs($this->user)->get(route('ward.settings'))
            ->assertOk()
            ->assertSeeInOrder(['Consultant', 'Anaesthetist', 'Nurses']);

        $this->actingAs($this->user)->post(route('ward.settings.update'), [
            'setting_type' => 'patient_details',
            'tabs' => ['info' => 1, 'careprovider' => 1, 'anaesthetist' => 1],
        ]);

        $tabs = WardDashboardSetting::where('user_id', $this->user->id)->first()->patient_details_tabs;
        $this->assertTrue($tabs['anaesthetist']);
        $this->assertFalse($tabs['nurses']);

        $this->details()->assertDontSee('On duty now')->assertDontSee('Open roster');
    }
}
