<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * SmartWard is deployed on hospital networks with no internet, so every page
 * has to work from what the server itself serves.
 */
class OfflineAssetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_page_loads_scripts_styles_or_fonts_from_the_internet(): void
    {
        $external = [];

        foreach (Finder::create()->files()->in(resource_path('views'))->name('*.blade.php') as $file) {
            foreach (explode("\n", $file->getContents()) as $index => $line) {
                // A script or stylesheet tag, or a script added from JavaScript, pointing off the server
                $loadsRemote = preg_match('/<script[^>]+src=["\']https?:\/\//i', $line)
                    || preg_match('/<link[^>]+href=["\']https?:\/\//i', $line)
                    || preg_match('/\.src\s*=\s*["\']https?:\/\//i', $line)
                    || preg_match('/(cdn\.jsdelivr\.net|unpkg\.com|cdnjs\.cloudflare\.com|fonts\.bunny\.net|fonts\.googleapis\.com)/i', $line);

                if ($loadsRemote) {
                    $external[] = $file->getRelativePathname() . ':' . ($index + 1) . '  ' . trim($line);
                }
            }
        }

        $this->assertSame([], $external, "Bundle these with Vite instead (see resources/js/chart.js):\n" . implode("\n", $external));
    }

    public function test_allergies_cannot_break_the_patient_details_markup(): void
    {
        // Names with quotes in them too, as an ADT feed can send
        $patient = Patient::create([
            'name' => 'Siti Aminah', 'mrn' => 'MRN96001', 'rn' => 'RN96001', 'ic_passport' => '800101-14-5566',
            'age' => 45, 'gender' => 'Female', 'phone' => '012-3456789', 'is_active' => true,
            'allergies' => [
                ['allergen' => 'NSAIDS^NSAIDs', 'status' => 'Active'],
                ['allergen' => 'PCM^Paracetamol "500mg" O\'Brien', 'status' => 'Active'],
            ],
        ]);

        $html = $this->actingAs(User::factory()->create())
            ->get(route('ward.patient-details', ['patient_id' => $patient->id]))
            ->assertOk()
            ->getContent();

        // The list sits inside a double-quoted x-data attribute: raw JSON would end it at its first "
        $this->assertStringNotContainsString('allergies: [{', $html);
        $this->assertStringContainsString("allergies: JSON.parse('", $html);
        $this->assertMatchesRegularExpression('/allergies: JSON\.parse\(\'[^"]*NSAIDs[^"]*\'\)/', $html);
    }
}
