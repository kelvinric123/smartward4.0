<?php

namespace Tests\Feature;

use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use App\Models\Ward;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * The ECG PDF viewer route (/ecg/pdf/view, ecg.pdf.public) sits outside the
 * auth group for the viewer iframes, so it needs a logged-in session or a
 * signed URL from the ECG pages.
 */
class EcgPdfAccessTest extends TestCase
{
    use RefreshDatabase;

    private string $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = storage_path('framework/testing/ecg-' . uniqid());
        File::ensureDirectoryExists($this->store);
        File::put($this->store . '/ecg_0001.pdf', '%PDF-1.4 test ecg');
        config(['services.ecg.store_path' => $this->store]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->store);

        parent::tearDown();
    }

    private function signedUrl(string $file, int $minutes = 30): string
    {
        return url(URL::temporarySignedRoute('ecg.pdf.public', now()->addMinutes($minutes), ['file' => $file], false));
    }

    public function test_a_guest_without_a_signed_url_cannot_fetch_an_ecg(): void
    {
        $this->get('/ecg/pdf/view?file=ecg_0001.pdf')->assertForbidden();
        $this->get('/ecg/pdf/view?file=ecg_0001.pdf&download=1')->assertForbidden();
    }

    public function test_a_logged_in_user_can_open_an_ecg_without_a_signature(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/ecg/pdf/view?file=ecg_0001.pdf')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_a_signed_url_opens_and_downloads_the_ecg_without_a_session(): void
    {
        $url = $this->signedUrl('ecg_0001.pdf');

        $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');

        // The export button appends download options to the signed URL
        $download = $this->get($url . '&download=1&dl_name=' . rawurlencode('ECG_MRN1_07 Oct 2026.pdf'))->assertOk();
        $this->assertStringContainsString('attachment', $download->headers->get('Content-Disposition'));
    }

    public function test_a_signed_url_is_only_good_for_its_file_and_its_lifetime(): void
    {
        File::put($this->store . '/ecg_0002.pdf', '%PDF-1.4 another patient');
        $url = $this->signedUrl('ecg_0001.pdf');

        $this->get(str_replace('ecg_0001.pdf', 'ecg_0002.pdf', $url))->assertForbidden();
        $this->get(preg_replace('/signature=[0-9a-f]+/', 'signature=' . str_repeat('0', 64), $url))->assertForbidden();

        $this->travel(31)->minutes();
        $this->get($url)->assertForbidden();
    }

    public function test_the_patient_ecg_page_hands_the_viewer_a_signed_url(): void
    {
        $hospital = Hospital::create(['name' => 'Test Hospital']);
        $ward = Ward::create(['hospital_id' => $hospital->id, 'ward_code' => 'W6', 'ward_name' => 'Ward 6', 'is_active' => true]);
        $patient = Patient::create([
            'name' => 'Siti Aminah', 'mrn' => 'MRN900001', 'rn' => 'RN900001', 'ic_passport' => '800202-14-6666',
            'age' => 46, 'gender' => 'Female', 'phone' => '013-2224444', 'ward_id' => $ward->id,
            'bed_number' => 'D610', 'status' => 'admitted', 'is_active' => true,
        ]);
        $file = "manual_pid{$patient->id}_20261007_100000.pdf";
        File::put($this->store . '/' . $file, '%PDF-1.4 uploaded');

        $page = $this->withoutVite()
            ->actingAs(User::factory()->create())
            ->get(route('ecg.patient', ['patient_id' => $patient->id]))
            ->assertOk();

        $this->assertMatchesRegularExpression('/<iframe id="pdfFrame" src="([^"]+)"/', $page->getContent());
        preg_match('/<iframe id="pdfFrame" src="([^"]+)"/', $page->getContent(), $m);
        $src = html_entity_decode($m[1]);
        $this->assertStringContainsString('signature=', $src);
        $this->assertStringContainsString('file=' . $file, $src);

        // The iframe URL works on its own, e.g. in a viewer without the session
        auth()->logout();
        $this->get($src)->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }
}
