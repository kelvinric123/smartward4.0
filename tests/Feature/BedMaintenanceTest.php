<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use App\Models\Ward;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BedMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Ward $ward;
    private Bed $emptyBed;
    private Bed $occupiedBed;
    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $hospital = Hospital::create(['name' => 'Test Hospital']);
        $this->ward = Ward::create(['hospital_id' => $hospital->id, 'ward_code' => 'MW1', 'ward_name' => 'Medical Ward 1', 'is_active' => true]);

        $this->occupiedBed = $this->makeBed('B01');
        $this->emptyBed = $this->makeBed('B02');

        $this->patient = $this->makePatient('Tan Mei Ling', 'B01', Patient::STATUS_ADMITTED);
    }

    private function makeBed(string $number): Bed
    {
        return Bed::create([
            'ward_id' => $this->ward->id, 'bed_number' => $number, 'bed_id' => 'MW1-' . $number,
            'bed_display_name' => $number, 'status' => 'available', 'is_active' => true,
        ]);
    }

    private function makePatient(string $name, ?string $bed, string $status): Patient
    {
        static $n = 0;
        $n++;

        return Patient::create([
            'name' => $name, 'mrn' => 'MRN70000' . $n, 'rn' => 'RN70000' . $n, 'ic_passport' => '800101-10-00' . $n,
            'age' => 50, 'gender' => 'Female', 'phone' => '012-0000000',
            'ward_id' => $this->ward->id, 'bed_number' => $bed, 'status' => $status, 'is_active' => true,
            'admitted_at' => $status === Patient::STATUS_ADMITTED ? now()->subDay() : null,
        ]);
    }

    private function toggleMaintenance(Bed $bed)
    {
        return $this->actingAs($this->user)->from(route('beds.index'))->post(route('beds.maintenance', $bed));
    }

    public function test_beds_page_has_a_manage_menu_with_deactivate_and_maintenance(): void
    {
        $this->actingAs($this->user)->get(route('beds.index'))
            ->assertOk()
            ->assertSee('Manage')
            ->assertSee(route('beds.deactivate', $this->emptyBed), false)
            ->assertSee(route('beds.maintenance', $this->emptyBed), false)
            ->assertSee('Take this bed out of service')
            // The occupied bed warns before anyone clicks
            ->assertSee('A patient is in or booked into this bed');
    }

    public function test_an_empty_bed_goes_under_maintenance_and_back(): void
    {
        $this->toggleMaintenance($this->emptyBed)
            ->assertRedirect(route('beds.index'))
            ->assertSessionHas('success', 'Bed B02 is now under maintenance. Nobody can be admitted, prebooked or transferred to it until maintenance ends.');
        $this->assertSame(Bed::STATUS_MAINTENANCE, $this->emptyBed->fresh()->status);

        // The page's bed sync leaves an empty maintenance bed alone, and offers to end it
        $this->actingAs($this->user)->get(route('beds.index'))
            ->assertOk()
            ->assertSee('End maintenance');
        $this->assertSame(Bed::STATUS_MAINTENANCE, $this->emptyBed->fresh()->status);

        $this->toggleMaintenance($this->emptyBed)
            ->assertSessionHas('success', 'Bed B02 is back in service.');
        $this->assertSame('available', $this->emptyBed->fresh()->status);
    }

    public function test_a_bed_with_a_patient_admitted_cannot_go_under_maintenance(): void
    {
        $this->occupiedBed->update(['status' => 'occupied', 'patient_id' => $this->patient->id]);

        $this->toggleMaintenance($this->occupiedBed)
            ->assertSessionHas('error', 'Bed B01 cannot go under maintenance: Tan Mei Ling is admitted to it. Discharge or transfer the patient first.');
        $this->assertSame('occupied', $this->occupiedBed->fresh()->status);

        // The user sees why on the page
        $this->actingAs($this->user)->followingRedirects()->from(route('beds.index'))
            ->post(route('beds.maintenance', $this->occupiedBed))
            ->assertOk()
            ->assertSee('cannot go under maintenance: Tan Mei Ling is admitted to it');
    }

    public function test_a_prebooked_bed_or_one_with_a_pending_discharge_cannot_go_under_maintenance(): void
    {
        $this->makePatient('Lim Ah Kow', 'B02', Patient::STATUS_PREBOOK);
        $this->toggleMaintenance($this->emptyBed)
            ->assertSessionHas('error', 'Bed B02 cannot go under maintenance: it is prebooked for Lim Ah Kow. Cancel or move the prebooking first.');

        $this->patient->update(['status' => Patient::STATUS_PENDING_DISCHARGE]);
        $this->toggleMaintenance($this->occupiedBed)
            ->assertSessionHas('error', 'Bed B01 cannot go under maintenance: Tan Mei Ling is admitted to it. Discharge or transfer the patient first.');

        $this->assertNotSame(Bed::STATUS_MAINTENANCE, $this->emptyBed->fresh()->status);
        $this->assertNotSame(Bed::STATUS_MAINTENANCE, $this->occupiedBed->fresh()->status);
    }

    public function test_nobody_can_be_admitted_prebooked_or_transferred_to_a_bed_under_maintenance(): void
    {
        $this->toggleMaintenance($this->emptyBed)->assertSessionHas('success');
        $waiting = $this->makePatient('Siti Aminah', null, 'waiting');

        $this->actingAs($this->user)->post(route('ward.admit-patient'), [
            'patient_id' => $waiting->id, 'ward_id' => $this->ward->id, 'bed_number' => 'B02',
        ])->assertSessionHas('error', 'Bed B02 is under maintenance. End maintenance on the Beds page before admitting a patient to it.');
        $this->assertNull($waiting->fresh()->bed_number);

        $this->actingAs($this->user)->post(route('ward.prebook-patient'), [
            'patient_id' => $waiting->id, 'ward_id' => $this->ward->id, 'bed_number' => 'B02',
        ])->assertSessionHas('error', 'Bed B02 is under maintenance. End maintenance on the Beds page before prebooking it.');
        $this->assertSame('waiting', $waiting->fresh()->status);

        $this->actingAs($this->user)->post(route('ward.transfer-bed'), [
            'patient_id' => $this->patient->id, 'ward_id' => $this->ward->id, 'bed_number' => 'B02',
        ])->assertSessionHas('error', 'Bed B02 is under maintenance, so nobody can be transferred to it.');
        $this->assertSame('B01', $this->patient->fresh()->bed_number);

        // Once maintenance ends, the bed takes patients again
        $this->toggleMaintenance($this->emptyBed);
        $this->actingAs($this->user)->post(route('ward.transfer-bed'), [
            'patient_id' => $this->patient->id, 'ward_id' => $this->ward->id, 'bed_number' => 'B02',
        ])->assertSessionHas('success');
        $this->assertSame('B02', $this->patient->fresh()->bed_number);
    }

    public function test_ward_dashboard_shows_a_maintenance_bed_without_admit_or_prebook(): void
    {
        $dashboard = route('ward.dashboard', ['ward_id' => $this->ward->id]);
        $admitB02 = "bedNumber: '" . 'B02' . "'";

        $this->actingAs($this->user)->get($dashboard)
            ->assertOk()
            ->assertSee($admitB02, false)
            ->assertDontSee('Under maintenance');

        $this->toggleMaintenance($this->emptyBed);

        $this->actingAs($this->user)->get($dashboard)
            ->assertOk()
            ->assertSee('Under maintenance')
            ->assertSee('Not available for admission')
            ->assertDontSee($admitB02, false);
    }
}
