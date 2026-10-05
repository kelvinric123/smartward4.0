<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\ClinicalIndicator;
use App\Models\ClinicalIndicatorScore;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use App\Models\Ward;
use App\Models\WardType;
use App\Services\DemoClinicalData;
use App\Support\ClinicalIndicatorLibrary;
use App\Support\ClinicalIndicatorScreen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mental State Assessment (C-SSRS): the Columbia suicide risk screen, asked
 * question by question on the ward dashboard, with the most serious answer
 * setting the risk and the response it calls for.
 */
class MentalStateAssessmentTest extends TestCase
{
    use RefreshDatabase;

    /** Item indexes, in the order the screen asks them */
    private const Q1 = 0, Q2 = 1, Q3 = 2, Q4 = 3, Q5 = 4, Q6 = 5;

    private User $user;
    private Ward $ward;
    private Patient $patient;
    private ClinicalIndicator $cssrs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['name' => 'Ward Nurse']);
        $hospital = Hospital::create(['name' => 'Test Hospital']);

        // The library sync migrations put the indicator in; bind it to a medical ward type
        $this->cssrs = ClinicalIndicator::where('code', 'CSSRS')->firstOrFail();
        $medical = WardType::create(['code' => 'MED', 'name' => 'Medical', 'is_active' => true]);
        $medical->clinicalIndicators()->sync([$this->cssrs->id]);

        $this->ward = Ward::create([
            'hospital_id' => $hospital->id, 'ward_code' => 'W7', 'ward_name' => 'Ward 7',
            'ward_type_id' => $medical->id, 'is_active' => true,
        ]);
        Bed::create([
            'ward_id' => $this->ward->id, 'bed_number' => 'B701', 'bed_id' => 'W7-B701',
            'bed_display_name' => 'B701', 'status' => 'occupied', 'is_active' => true,
        ]);

        $this->patient = Patient::create([
            'name' => 'Lim Ah Kow', 'mrn' => 'MRN93001', 'rn' => 'RN93001', 'ic_passport' => '700101-10-5003',
            'age' => 55, 'gender' => 'Male', 'phone' => '012-0000000',
            'ward_id' => $this->ward->id, 'bed_number' => 'B701', 'status' => Patient::STATUS_ADMITTED, 'is_active' => true,
            'admitted_at' => now()->subDay(),
        ]);
    }

    private function definition(): array
    {
        return ClinicalIndicatorLibrary::find('CSSRS');
    }

    private function screen(array $answers): array
    {
        return ClinicalIndicatorScreen::evaluate($this->definition(), $answers);
    }

    private function record(array $answers, ?string $notes = null)
    {
        return $this->actingAs($this->user)
            ->from(route('ward.patient-details', ['patient_id' => $this->patient->id]))
            ->post(route('ward.clinical-indicator-score.store'), [
                'patient_id' => $this->patient->id,
                'clinical_indicator_id' => $this->cssrs->id,
                'item_scores' => $answers,
                'notes' => $notes,
            ]);
    }

    public function test_the_library_entry_is_internally_consistent(): void
    {
        $definition = $this->definition();

        $this->assertSame('Mental State Assessment (C-SSRS)', $definition['name']);
        $this->assertSame('Mental state', $definition['category']);
        $this->assertArrayHasKey($definition['category'], ClinicalIndicatorLibrary::CATEGORIES);
        $this->assertTrue(ClinicalIndicatorLibrary::isScreen($definition));
        $this->assertFalse(ClinicalIndicatorLibrary::isScorable($definition), 'a screen is not totalled');
        $this->assertFalse(ClinicalIndicatorLibrary::takesReadings($definition));
        $this->assertSame(['Q1', 'Q2', 'Q3', 'Q4', 'Q5', 'Q6'], array_column($definition['items'], 'abbr'));

        $seen = [];
        $highest = 0;
        foreach ($definition['items'] as $item) {
            $this->assertNotEmpty($item['question'], $item['abbr']);
            $values = array_column($item['options'], 'value');
            $this->assertSame($values, array_unique($values), $item['abbr'] . ' answers are told apart by value');
            $this->assertContains(0, $values, $item['abbr'] . ' can be answered no');
            $highest = max($highest, ...$values);

            // A question waits only on an answer to one asked before it
            if (isset($item['asked_when'])) {
                [$abbr, $label] = $item['asked_when'];
                $this->assertArrayHasKey($abbr, $seen, $item['abbr']);
                $this->assertContains($label, $seen[$abbr], $item['abbr']);
            }
            $seen[$item['abbr']] = array_column($item['options'], 'label');
        }

        // Every level an answer can reach has a band, and every band says what to do
        $this->assertSame($definition['score_max'], $highest);
        foreach (range($definition['score_min'], $definition['score_max']) as $level) {
            $band = ClinicalIndicatorLibrary::bandFor('CSSRS', $level);
            $this->assertNotNull($band, "band for $level");
            $this->assertNotEmpty($band['action'], "action for $level");
        }
    }

    public function test_the_most_serious_answer_sets_the_risk(): void
    {
        $cases = [
            'every answer no' => [[self::Q1 => 0, self::Q2 => 0, self::Q6 => 0], 0, 'No risk identified'],
            'wish to be dead only' => [[self::Q1 => 1, self::Q2 => 0, self::Q6 => 0], 1, 'Low risk'],
            'thoughts, no method' => [[self::Q1 => 1, self::Q2 => 1, self::Q3 => 0, self::Q4 => 0, self::Q5 => 0, self::Q6 => 0], 1, 'Low risk'],
            'thoughts with a method' => [[self::Q1 => 1, self::Q2 => 1, self::Q3 => 2, self::Q4 => 0, self::Q5 => 0, self::Q6 => 0], 2, 'Moderate risk'],
            'behaviour over 3 months ago' => [[self::Q1 => 0, self::Q2 => 0, self::Q6 => 2], 2, 'Moderate risk'],
            'intent to act' => [[self::Q1 => 0, self::Q2 => 1, self::Q3 => 0, self::Q4 => 3, self::Q5 => 0, self::Q6 => 0], 3, 'High risk'],
            'plan and intent' => [[self::Q1 => 1, self::Q2 => 1, self::Q3 => 2, self::Q4 => 0, self::Q5 => 3, self::Q6 => 0], 3, 'High risk'],
            'behaviour within 3 months' => [[self::Q1 => 0, self::Q2 => 0, self::Q6 => 3], 3, 'High risk'],
        ];

        foreach ($cases as $case => [$answers, $level, $band]) {
            $screen = $this->screen($answers);

            $this->assertNull($screen['error'], $case);
            $this->assertSame($level, $screen['score'], $case);
            $this->assertSame($band, ClinicalIndicatorLibrary::bandFor('CSSRS', $screen['score'])['label'], $case);
        }

        $this->assertSame('Psychiatric consultation and patient safety precautions.', ClinicalIndicatorLibrary::bandFor('CSSRS', 3)['action']);
        $this->assertSame('Behavioural health referral at discharge.', ClinicalIndicatorLibrary::bandFor('CSSRS', 2)['action']);
    }

    public function test_questions_3_to_5_are_asked_only_after_a_yes_to_question_2(): void
    {
        // A no to question 2 skips to 6: answers left on 3 to 5 do not count
        $screen = $this->screen([self::Q1 => 0, self::Q2 => 0, self::Q3 => 2, self::Q4 => 3, self::Q5 => 3, self::Q6 => 0]);

        $this->assertSame(0, $screen['score']);
        foreach ([self::Q3, self::Q4, self::Q5] as $index) {
            $this->assertFalse($screen['items'][$index]['asked']);
            $this->assertSame(ClinicalIndicatorScreen::NOT_ASKED, $screen['items'][$index]['label']);
            $this->assertNull($screen['items'][$index]['value']);
        }
        $this->assertTrue($screen['items'][self::Q6]['asked']);

        // After a yes to question 2 they have to be answered
        $screen = $this->screen([self::Q1 => 0, self::Q2 => 1, self::Q6 => 0]);
        $this->assertSame('Answer Q3 (Suicidal thoughts with a method) before saving.', $screen['error']);

        // And only with the answers the question has
        $screen = $this->screen([self::Q1 => 5, self::Q2 => 0, self::Q6 => 0]);
        $this->assertSame('That is not a valid answer for Wish to be dead.', $screen['error']);
    }

    public function test_saving_a_screen_records_the_risk_and_the_answers(): void
    {
        $this->record([self::Q1 => 0, self::Q2 => 1, self::Q3 => 0, self::Q4 => 0, self::Q5 => 3, self::Q6 => 0], 'Psychiatry informed, 1:1 observation')
            ->assertRedirect()
            ->assertSessionHas('success', 'Mental State Assessment (C-SSRS) recorded: Yes to Q2, Q5 - High risk.');

        $record = ClinicalIndicatorScore::where('clinical_indicator_id', $this->cssrs->id)->sole();
        $this->assertSame(3, $record->score);
        $this->assertSame('High risk', $record->band_label);
        $this->assertSame('high', $record->band_tone);
        $this->assertSame('Psychiatry informed, 1:1 observation', $record->notes);
        $this->assertTrue($record->isScreen());
        $this->assertFalse($record->isReadings());
        $this->assertFalse($record->hasTotal(), 'the score is only the most serious answer');
        $this->assertSame('Yes to Q2, Q5', $record->breakdown());
        $this->assertSame(['No', 'Yes', 'No', 'No', 'Yes', 'No'], array_column($record->item_scores, 'label'));

        // Question 6 says when, and a no to everything has nothing to list
        $this->record([self::Q1 => 0, self::Q2 => 0, self::Q6 => 3])
            ->assertSessionHas('success', 'Mental State Assessment (C-SSRS) recorded: Yes to Q6 (within the past 3 months) - High risk.');
        $this->record([self::Q1 => 0, self::Q2 => 0, self::Q6 => 0])
            ->assertSessionHas('success', 'Mental State Assessment (C-SSRS) recorded - No risk identified.');
    }

    public function test_an_unfinished_screen_is_refused(): void
    {
        $this->record([self::Q1 => 1, self::Q2 => 1, self::Q6 => 0])
            ->assertRedirect()
            ->assertSessionHas('error', 'Answer Q3 (Suicidal thoughts with a method) before saving.');

        $this->assertSame(0, ClinicalIndicatorScore::count());
    }

    public function test_patient_details_shows_the_screen_and_what_the_last_one_calls_for(): void
    {
        $details = fn () => $this->actingAs($this->user)->get(route('ward.patient-details', ['patient_id' => $this->patient->id]));

        $details()
            ->assertOk()
            ->assertSee('Mental State Assessment (C-SSRS)')
            ->assertSeeInOrder([
                'Ask questions 1 and 2.',
                'Have you wished you were dead or wished you could go to sleep and not wake up?',
                'Have you actually had any thoughts of killing yourself?',
                'If YES to 2, ask questions 3, 4, 5, and 6. If NO to 2, go directly to question 6.',
                'Have you been thinking about how you might do this?',
                'Have you ever done anything, started to do anything, or prepared to do anything to end your life?',
                'If YES, ask: Was this within the past 3 months?',
                'Save screen',
            ])
            ->assertSee('No screens recorded yet.')
            ->assertDontSee('on the last screen');

        $this->record([self::Q1 => 0, self::Q2 => 1, self::Q3 => 0, self::Q4 => 3, self::Q5 => 0, self::Q6 => 0]);

        $details()
            ->assertOk()
            ->assertSee('High risk on the last screen')
            ->assertSee('Yes to Q2, Q4')
            ->assertSee('Psychiatric consultation and patient safety precautions.')
            ->assertDontSee('No screens recorded yet.');
    }

    public function test_the_ward_types_page_lists_it_under_mental_state(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);

        $this->actingAs($admin)
            ->get(route('ward-types.index', ['tab' => 'clinical_indicators']))
            ->assertOk()
            ->assertSeeInOrder(['Mental state', 'CSSRS', 'Mental State Assessment (C-SSRS)', 'Screen: 6 questions, risk from the most serious answer'])
            ->assertSee('Psychiatric consultation and patient safety precautions.');
    }

    public function test_demo_seeding_records_screens_the_way_a_nurse_would(): void
    {
        $this->assertSame(DemoClinicalData::KIND_SCREEN, DemoClinicalData::kind($this->definition()));

        $saved = DemoClinicalData::seedIndicator($this->patient, $this->cssrs, now()->subDays(2), now(), 3, 'deteriorating', $this->user->id);
        $this->assertGreaterThan(0, $saved);

        $records = ClinicalIndicatorScore::where('clinical_indicator_id', $this->cssrs->id)->orderBy('recorded_at')->get();
        $this->assertSame('low', $records->first()->band_tone, 'starts well');
        $this->assertSame('high', $records->last()->band_tone, 'ends at high risk');

        // Each one is a screen as the form would have put it: the same answers give the same risk
        foreach ($records as $record) {
            $answers = array_map(fn (array $entry) => $entry['value'], $record->item_scores);
            $this->assertSame($record->score, $this->screen($answers)['score']);
            $this->assertTrue($record->isScreen());
        }
    }
}
