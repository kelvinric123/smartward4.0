<?php

namespace Tests\Feature;

use App\Models\Nurse;
use App\Models\NurseCredential;
use App\Models\NursePrivilege;
use App\Models\User;
use App\Models\UserActivity;
use App\Support\NurseCredentialing;
use App\Support\NursePrivilegeCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class NurseCredentialingTest extends TestCase
{
    use RefreshDatabase;

    private const PASSPHRASE = 'askdrtai';

    private User $user;
    private Nurse $nurse;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-22 10:00:00');

        $this->user = User::factory()->create(['name' => 'Matron Aishah']);
        $this->nurse = Nurse::create([
            'name' => 'Nurul Huda', 'registration_number' => 'RN-80001', 'qualification' => 'Diploma',
            'designation' => Nurse::DEFAULT_DESIGNATION, 'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function editUrl(array $query = []): string
    {
        return route('nurses.edit', ['nurse' => $this->nurse] + $query);
    }

    private function tabUrl(): string
    {
        return $this->editUrl(['tab' => NurseCredentialing::TAB]);
    }

    private function credential(array $overrides = []): NurseCredential
    {
        return $this->nurse->credentials()->create($overrides + [
            'type' => 'apc', 'title' => 'Annual Practising Certificate 2026', 'reference_number' => 'APC-123',
            'issuing_body' => NurseCredential::NURSING_BOARD, 'issued_on' => '2026-01-02', 'expires_on' => '2026-12-31',
        ]);
    }

    private function privilege(array $overrides = []): NursePrivilege
    {
        return $this->nurse->privileges()->create($overrides + [
            'name' => 'Peripheral IV cannulation', 'category' => 'core', 'status' => 'granted',
            'granted_on' => '2026-01-10', 'review_on' => '2029-01-10',
        ]);
    }

    /**
     * A save of the privilege tick boxes: $items is code => held, status and
     * was (how the page showed it), as the page posts them.
     */
    private function checklist(array $items, array $overrides = []): array
    {
        return ['active_tab' => 'credentialing', '_form' => 'privilege-checklist', 'checklist' => $overrides + [
            'items' => $items,
            'granted_on' => '2026-09-22',
            'review' => 'level',
            'approved_by' => NursePrivilege::DEFAULT_APPROVER,
        ]];
    }

    private function saveChecklist(array $items, array $overrides = [])
    {
        return $this->actingAs($this->user)->from($this->tabUrl())
            ->post(route('nurses.privileges.checklist', $this->nurse), $this->checklist($items, $overrides));
    }

    public function test_the_edit_page_has_the_credentialing_tab_with_empty_states(): void
    {
        $this->actingAs($this->user)->get($this->editUrl())
            ->assertOk()
            ->assertSee('Credentialing and Privileging')
            ->assertSee("tab: 'profile'", false)
            ->assertSee('No credentials recorded yet')
            ->assertSee('No privileges recorded yet')
            ->assertSee('Not recorded')
            ->assertSee(route('nurses.credentials.store', $this->nurse), false)
            ->assertSee(route('nurses.privileges.store', $this->nurse), false)
            ->assertSee(route('nurses.privileges.checklist', $this->nurse), false)
            // The nurse form itself still saves as before
            ->assertSee(route('nurses.update', $this->nurse), false);

        // Linking straight to the tab opens it
        $this->actingAs($this->user)->get($this->tabUrl())
            ->assertOk()
            ->assertSee("tab: 'credentialing'", false);
    }

    public function test_adding_a_verified_apc(): void
    {
        $this->actingAs($this->user)->from($this->tabUrl())->post(route('nurses.credentials.store', $this->nurse), [
            'active_tab' => 'credentialing', '_form' => 'credential-new',
            'credential' => [
                'type' => 'apc', 'title' => 'Annual Practising Certificate 2026', 'reference_number' => 'APC-123',
                'issuing_body' => NurseCredential::NURSING_BOARD, 'issued_on' => '2026-01-02',
                'expires_on' => '2026-12-31', 'verified' => '1', 'notes' => '',
            ],
        ])
            ->assertRedirect($this->tabUrl())
            ->assertSessionHas('success', 'Annual Practising Certificate 2026 added.');

        $credential = $this->nurse->credentials()->sole();
        $this->assertSame('APC-123', $credential->reference_number);
        $this->assertSame('2026-12-31', $credential->expires_on->format('Y-m-d'));
        $this->assertSame($this->user->id, $credential->verified_by);
        $this->assertNotNull($credential->verified_at);
        $this->assertSame($this->user->id, $credential->recorded_by);

        $this->actingAs($this->user)->get($this->tabUrl())
            ->assertOk()
            ->assertSee('Valid until 31 Dec 2026')
            ->assertSee('Verified by Matron Aishah on 22 Sep 2026')
            ->assertSee('All current and verified');

        $this->assertTrue(UserActivity::where('activity_type', 'nurse_credentialing')
            ->where('description', 'Added credential "Annual Practising Certificate 2026" for nurse Nurul Huda')->exists());
    }

    public function test_a_credential_that_fails_validation_reopens_its_form_with_the_values_kept(): void
    {
        $this->actingAs($this->user)->from($this->tabUrl())->post(route('nurses.credentials.store', $this->nurse), [
            'active_tab' => 'credentialing', '_form' => 'credential-new',
            'credential' => [
                'type' => 'life_support', 'title' => 'Basic Life Support (BLS)',
                'issued_on' => '2026-03-01', 'expires_on' => '2026-02-01',
            ],
        ])
            ->assertRedirect($this->tabUrl())
            ->assertSessionHasErrors(['credential.expires_on' => 'The expiry date cannot be before the issue date.']);
        $this->assertSame(0, $this->nurse->credentials()->count());

        // APCs and life support cards must have an expiry date
        $this->actingAs($this->user)->from($this->tabUrl())->post(route('nurses.credentials.store', $this->nurse), [
            'active_tab' => 'credentialing', '_form' => 'credential-new',
            'credential' => ['type' => 'apc', 'title' => 'Annual Practising Certificate 2026', 'expires_on' => ''],
        ])->assertSessionHasErrors(['credential.expires_on' => 'Enter the expiry date. APCs and life support certificates always have one.']);

        // Other types may leave it blank
        $this->actingAs($this->user)->post(route('nurses.credentials.store', $this->nurse), [
            'credential' => ['type' => 'qualification', 'title' => 'Diploma in Nursing', 'issued_on' => '2015-06-01'],
        ])->assertSessionHasNoErrors();

        // Coming back, the page opens the tab and the form, with what was typed
        $this->actingAs($this->user)
            ->withSession([
                '_old_input' => [
                    'active_tab' => 'credentialing', '_form' => 'credential-new',
                    'credential' => ['type' => 'life_support', 'title' => 'Basic Life Support (BLS)', 'issued_on' => '2026-03-01', 'expires_on' => '2026-02-01'],
                ],
                'errors' => (new ViewErrorBag)->put('default', new MessageBag([
                    'credential.expires_on' => ['The expiry date cannot be before the issue date.'],
                ])),
            ])
            ->get($this->editUrl())
            ->assertOk()
            ->assertSee("tab: 'credentialing'", false)
            ->assertSee("open: 'credential-new'", false)
            ->assertSee('value="Basic Life Support (BLS)"', false)
            ->assertSee('The expiry date cannot be before the issue date.')
            // ...and leaves the nurse's own fields alone
            ->assertSee('value="Nurul Huda"', false);
    }

    public function test_editing_a_credential_keeps_the_first_verifier_and_unticking_clears_it(): void
    {
        $other = User::factory()->create(['name' => 'Sister Tan']);
        $credential = $this->credential(['verified_at' => now()->subMonth(), 'verified_by' => $other->id]);

        $payload = fn (array $changes) => [
            'active_tab' => 'credentialing', '_form' => 'credential-' . $credential->id,
            'credential' => $changes + [
                'type' => 'apc', 'title' => 'Annual Practising Certificate 2026', 'reference_number' => 'APC-999',
                'issuing_body' => NurseCredential::NURSING_BOARD, 'issued_on' => '2026-01-02', 'expires_on' => '2026-12-31',
            ],
        ];

        $this->actingAs($this->user)->put(route('nurses.credentials.update', [$this->nurse, $credential]), $payload(['verified' => '1']))
            ->assertRedirect($this->tabUrl())
            ->assertSessionHas('success', 'Annual Practising Certificate 2026 saved.');
        $credential->refresh();
        $this->assertSame('APC-999', $credential->reference_number);
        $this->assertSame($other->id, $credential->verified_by);
        $this->assertSame($this->user->id, $credential->updated_by);

        $this->actingAs($this->user)->put(route('nurses.credentials.update', [$this->nurse, $credential]), $payload([]));
        $this->assertNull($credential->fresh()->verified_at);
        $this->assertNull($credential->fresh()->verified_by);

        $log = UserActivity::where('description', 'Updated credential "Annual Practising Certificate 2026" for nurse Nurul Huda')->first();
        // (MySQL's JSON column reorders the keys, so compare without order)
        $this->assertEquals(['old' => 'APC-123', 'new' => 'APC-999'], $log->properties['reference_number']);
    }

    public function test_removing_a_credential_needs_the_passphrase(): void
    {
        $credential = $this->credential();

        $this->actingAs($this->user)->from($this->tabUrl())
            ->delete(route('nurses.credentials.destroy', [$this->nurse, $credential]), ['active_tab' => 'credentialing'])
            ->assertSessionHas('error');
        $this->assertNotNull($credential->fresh());

        $this->actingAs($this->user)
            ->delete(route('nurses.credentials.destroy', [$this->nurse, $credential]), ['delete_passphrase' => self::PASSPHRASE])
            ->assertRedirect($this->tabUrl())
            ->assertSessionHas('success', 'Annual Practising Certificate 2026 removed.');
        $this->assertNull($credential->fresh());
    }

    public function test_a_renewed_apc_supersedes_the_old_one_and_expiry_is_flagged(): void
    {
        $this->credential(['title' => 'Annual Practising Certificate 2025', 'issued_on' => '2025-01-02', 'expires_on' => '2025-12-31']);

        // Only an expired APC on file: flagged red, on the tab too
        $this->actingAs($this->user)->get($this->editUrl())
            ->assertSee('Expired 31 Dec 2025')
            ->assertSee('1 expired')
            ->assertSee('title="1 expired"', false);

        // The renewal supersedes it, so nothing is expired any more
        $this->credential(['verified_at' => now(), 'verified_by' => $this->user->id]);
        $this->credential(['type' => 'life_support', 'title' => 'Basic Life Support (BLS)', 'reference_number' => null,
            'issuing_body' => null, 'issued_on' => '2024-11-01', 'expires_on' => '2026-10-31']);

        $data = NurseCredentialing::forNurse($this->nurse);
        $this->assertSame('Annual Practising Certificate 2026', $data['apc']['credential']->title);
        $this->assertSame(0, $data['counts']['expired']);
        $this->assertSame(1, $data['counts']['expiring']);
        $this->assertSame(['level' => 'amber', 'text' => '1 expiring soon'], $data['attention']);
        $this->assertSame(
            ['Annual Practising Certificate 2026', 'Annual Practising Certificate 2025', 'Basic Life Support (BLS)'],
            $data['credentials']->map(fn ($row) => $row['credential']->title)->all()
        );
        $this->assertSame([false, true, false], $data['credentials']->pluck('superseded')->all());

        // The next APC suggested is the one after the latest on file
        $this->assertSame('Annual Practising Certificate 2027', $data['presets']['apc']['title']);
        $this->assertSame('2027-12-31', $data['presets']['apc']['expires_on']);

        $this->actingAs($this->user)->get($this->editUrl())
            ->assertSee('Superseded')
            ->assertSee('Expires in 39 days')
            ->assertSee('title="1 expiring soon"', false);
    }

    public function test_granting_a_privilege_and_blocking_a_duplicate(): void
    {
        $this->actingAs($this->user)->from($this->tabUrl())->post(route('nurses.privileges.store', $this->nurse), [
            'active_tab' => 'credentialing', '_form' => 'privilege-new',
            'privilege' => [
                'name' => '  peripheral iv   cannulation ', 'category' => 'advanced', 'status' => 'supervised',
                'granted_on' => '2026-09-01', 'review_on' => '2027-09-01',
                'approved_by' => NursePrivilege::DEFAULT_APPROVER, 'notes' => 'Under SN Mary until 5 supervised insertions',
            ],
        ])
            ->assertRedirect($this->tabUrl())
            ->assertSessionHas('success', 'Peripheral IV cannulation recorded as Supervised.');

        // Typed in, it is still the built-in list's entry, at the list's level
        $privilege = $this->nurse->privileges()->sole();
        $this->assertSame('Peripheral IV cannulation', $privilege->name);
        $this->assertSame('piv', $privilege->code);
        $this->assertSame('core', $privilege->category);
        $this->assertSame('supervised', $privilege->status);
        $this->assertNotNull($privilege->status_changed_at);

        // The same privilege again, whatever the spelling, is refused
        $this->actingAs($this->user)->from($this->tabUrl())->post(route('nurses.privileges.store', $this->nurse), [
            'privilege' => ['name' => 'PERIPHERAL IV CANNULATION', 'category' => 'advanced', 'status' => 'granted'],
        ])->assertSessionHasErrors(['privilege.name' => 'This nurse already has Peripheral IV cannulation on record. Edit that entry instead.']);
        $this->assertSame(1, $this->nurse->privileges()->count());

        // Another nurse may hold it
        $colleague = Nurse::create(['name' => 'Siti Mariam', 'registration_number' => 'RN-80002', 'qualification' => 'Degree', 'is_active' => true]);
        $this->actingAs($this->user)->post(route('nurses.privileges.store', $colleague), [
            'privilege' => ['name' => 'Peripheral IV cannulation', 'category' => 'core', 'status' => 'granted'],
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->user)->get($this->tabUrl())
            ->assertOk()
            ->assertSee('Clinical procedures')
            ->assertSee('1 of 20 held')
            ->assertSee('Supervised')
            ->assertSee('Conditions:')
            ->assertSee('Under SN Mary until 5 supervised insertions')
            ->assertSee('1 supervised');
    }

    public function test_suspending_a_privilege_needs_a_reason_and_is_logged(): void
    {
        $privilege = $this->privilege();
        $url = route('nurses.privileges.update', [$this->nurse, $privilege]);
        $payload = fn (array $changes) => ['active_tab' => 'credentialing', '_form' => 'privilege-' . $privilege->id,
            'privilege' => $changes + [
                'name' => 'Peripheral IV cannulation', 'category' => 'core', 'status' => 'suspended',
                'granted_on' => '2026-01-10', 'review_on' => '2029-01-10', 'approved_by' => '', 'notes' => '',
            ]];

        $this->actingAs($this->user)->from($this->tabUrl())->put($url, $payload([]))
            ->assertSessionHasErrors(['privilege.notes' => 'Give the reason for suspending or withdrawing this privilege.']);
        $this->assertSame('granted', $privilege->fresh()->status);

        $this->actingAs($this->user)->put($url, $payload(['notes' => 'Incident review pending']))
            ->assertRedirect($this->tabUrl())
            ->assertSessionHas('success', 'Peripheral IV cannulation is now Suspended.');
        $this->assertSame('suspended', $privilege->fresh()->status);
        $this->assertTrue(UserActivity::where('description',
            'Changed privilege "Peripheral IV cannulation" from Granted to Suspended for nurse Nurul Huda')->exists());

        $this->actingAs($this->user)->get($this->tabUrl())
            ->assertSee('Reason:')
            ->assertSee('Incident review pending')
            ->assertSee('Suspended 22 Sep 2026')
            ->assertSee('1 suspended');
    }

    public function test_an_overdue_review_is_flagged_but_not_for_a_withdrawn_privilege(): void
    {
        $this->privilege(['review_on' => '2026-09-01']);
        $this->privilege(['name' => 'Blood and blood product transfusion', 'category' => 'advanced', 'review_on' => '2026-10-22']);
        $this->privilege(['name' => 'Chemotherapy administration', 'category' => 'advanced', 'status' => 'withdrawn', 'review_on' => '2025-01-01', 'notes' => 'Moved ward']);

        $data = NurseCredentialing::forNurse($this->nurse);
        $this->assertSame(1, $data['counts']['review_overdue']);
        $this->assertSame(1, $data['counts']['review_due']);
        $this->assertSame(['level' => 'red', 'text' => '1 review overdue, 1 review due soon'], $data['attention']);
        // Held privileges first, withdrawn last
        $this->assertSame(
            ['Blood and blood product transfusion', 'Peripheral IV cannulation', 'Chemotherapy administration'],
            $data['privileges']->map(fn ($row) => $row['privilege']->name)->all()
        );
        // A withdrawn privilege is not at risk for want of its certificate
        $this->assertSame(0, $data['counts']['at_risk']);

        $this->actingAs($this->user)->get($this->tabUrl())
            ->assertSee('Review overdue')
            ->assertSee('Review due in 30 days')
            ->assertSee('title="1 review overdue, 1 review due soon"', false);
    }

    public function test_a_record_only_resolves_under_its_own_nurse(): void
    {
        $credential = $this->credential();
        $privilege = $this->privilege();
        $colleague = Nurse::create(['name' => 'Siti Mariam', 'registration_number' => 'RN-80002', 'qualification' => 'Degree', 'is_active' => true]);

        $this->actingAs($this->user)->put(route('nurses.credentials.update', [$colleague, $credential]), [
            'credential' => ['type' => 'apc', 'title' => 'Hijacked', 'expires_on' => '2026-12-31'],
        ])->assertNotFound();
        $this->actingAs($this->user)->delete(route('nurses.privileges.destroy', [$colleague, $privilege]), [
            'delete_passphrase' => self::PASSPHRASE,
        ])->assertNotFound();

        $this->assertSame('Annual Practising Certificate 2026', $credential->fresh()->title);
        $this->assertNotNull($privilege->fresh());
    }

    public function test_removing_a_privilege_with_the_passphrase(): void
    {
        $privilege = $this->privilege();

        $this->actingAs($this->user)
            ->delete(route('nurses.privileges.destroy', [$this->nurse, $privilege]), ['delete_passphrase' => self::PASSPHRASE])
            ->assertRedirect($this->tabUrl())
            ->assertSessionHas('success', 'Peripheral IV cannulation removed.');
        $this->assertSame(0, $this->nurse->privileges()->count());
    }

    public function test_the_nurse_form_still_saves_and_a_nurse_delete_takes_the_records_with_it(): void
    {
        $this->credential();
        $this->privilege();

        $this->actingAs($this->user)->put(route('nurses.update', $this->nurse), [
            'name' => 'Nurul Huda binti Ahmad', 'registration_number' => 'RN-80001', 'qualification' => 'Degree',
            'is_tagging' => '0',
        ])->assertRedirect(route('nurses.index'));
        $this->assertSame('Nurul Huda binti Ahmad', $this->nurse->fresh()->name);
        $this->assertSame(1, $this->nurse->credentials()->count());

        $this->nurse->delete();
        $this->assertSame(0, NurseCredential::count());
        $this->assertSame(0, NursePrivilege::count());
    }

    /**
     * $json as @js() writes it into an attribute, quotes as ".
     */
    private function inAttribute(string $json): string
    {
        return str_replace('"', chr(92) . 'u0022', $json);
    }

    public function test_the_built_in_list_is_shown_by_field_with_the_certificates_it_needs(): void
    {
        $items = NursePrivilegeCatalogue::items();
        $this->assertCount(57, $items);
        $this->assertCount(57, array_unique(array_map('strtolower', array_column($items, 'name'))));
        foreach ($items as $item) {
            $this->assertArrayHasKey($item['level'], NursePrivilege::CATEGORIES, $item['name']);
            $this->assertTrue($item['requires'] === null || isset(NursePrivilegeCatalogue::CERTIFICATES[$item['requires']]), $item['name']);
        }
        // A specialty's certificate covers the whole section
        $this->assertSame('midwifery', $items['normal_delivery']['requires']);

        $this->credential(['type' => 'life_support', 'title' => 'Basic Life Support (BLS)', 'issued_on' => '2025-03-01', 'expires_on' => '2027-03-01']);

        $this->actingAs($this->user)->get($this->tabUrl())
            ->assertOk()
            ->assertSeeInOrder(['Resuscitation', 'Medication administration', 'Clinical procedures',
                'Perioperative (scrub and circulating)', 'Anaesthesia (nurse anaesthetist)', 'Midwifery and obstetrics'])
            ->assertSee('Adult basic life support (CPR and AED)')
            ->assertSee('BLS valid until 1 Mar 2027')
            ->assertSee('Needs ACLS')
            ->assertSee('Needs Registered Midwife or Post Basic Midwifery')
            ->assertSee('Only where local law allows it')
            ->assertSee('Edit privileges')
            ->assertSee('name="checklist[items][piv][held]"', false)
            ->assertSee('form="privilege-checklist"', false);
    }

    public function test_ticking_privileges_grants_them_with_the_review_date_by_level(): void
    {
        $this->saveChecklist([
            'piv' => ['held' => '1', 'status' => 'granted', 'was' => ''],
            'transfusion' => ['held' => '1', 'status' => 'supervised', 'was' => ''],
            'ecg' => ['status' => 'granted', 'was' => ''],
        ])
            ->assertRedirect($this->tabUrl())
            ->assertSessionHas('success', 'Privileges saved: 2 granted.')
            ->assertSessionMissing('warning');

        $piv = $this->nurse->privileges()->where('code', 'piv')->sole();
        $this->assertSame('Peripheral IV cannulation', $piv->name);
        $this->assertSame('core', $piv->category);
        $this->assertSame('granted', $piv->status);
        $this->assertSame('2026-09-22', $piv->granted_on->toDateString());
        $this->assertSame('2029-09-22', $piv->review_on->toDateString());
        $this->assertSame(NursePrivilege::DEFAULT_APPROVER, $piv->approved_by);
        $this->assertSame($this->user->id, $piv->recorded_by);

        $transfusion = $this->nurse->privileges()->where('code', 'transfusion')->sole();
        $this->assertSame('advanced', $transfusion->category);
        $this->assertSame('supervised', $transfusion->status);
        $this->assertSame('2027-09-22', $transfusion->review_on->toDateString());

        $this->assertSame(2, $this->nurse->privileges()->count());
        $this->assertTrue(UserActivity::where('activity_type', 'nurse_credentialing')
            ->where('description', 'Recorded privilege "Peripheral IV cannulation" (Granted) for nurse Nurul Huda')->exists());

        $this->actingAs($this->user)->get($this->tabUrl())
            ->assertSee('1 of 20 held')
            ->assertSee('1 independent')
            ->assertSee('1 supervised')
            ->assertSee('Review by 22 Sep 2029');
    }

    public function test_unticking_withdraws_a_privilege_with_a_reason_and_keeps_the_record(): void
    {
        $privilege = $this->privilege();

        $this->saveChecklist(['piv' => ['status' => 'granted', 'was' => 'granted']])
            ->assertSessionHasErrors(['checklist.reason' => 'Give the reason for withdrawing Peripheral IV cannulation.']);
        $this->assertSame('granted', $privilege->fresh()->status);

        // The failed save reopens the tab with the ticks as they were left
        $this->actingAs($this->user)->get($this->tabUrl())
            ->assertSee("tab: 'credentialing'", false)
            ->assertSee($this->inAttribute('"editing":true'), false)
            ->assertSee($this->inAttribute('"piv":{"held":false,"status":"granted"}'), false)
            ->assertSee('Give the reason for withdrawing Peripheral IV cannulation.');

        $this->saveChecklist(['piv' => ['status' => 'granted', 'was' => 'granted']], ['reason' => 'Left the IV team'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Privileges saved: 1 withdrawn.');

        $privilege->refresh();
        $this->assertSame('withdrawn', $privilege->status);
        $this->assertSame('Left the IV team', $privilege->notes);
        $this->assertSame('piv', $privilege->code);
        $this->assertSame('2026-01-10', $privilege->granted_on->toDateString());
        $this->assertTrue(UserActivity::where('description',
            'Changed privilege "Peripheral IV cannulation" from Granted to Withdrawn for nurse Nurul Huda')->exists());

        $this->actingAs($this->user)->get($this->tabUrl())
            ->assertSee('Withdrawn 22 Sep 2026')
            ->assertSee('Left the IV team');
    }

    public function test_changing_to_supervised_and_granting_a_withdrawn_privilege_again(): void
    {
        $piv = $this->privilege();
        $chemotherapy = $this->privilege([
            'name' => 'Chemotherapy administration', 'category' => 'advanced', 'status' => 'withdrawn',
            'notes' => 'Moved ward', 'granted_on' => '2024-01-01', 'review_on' => '2025-01-01',
        ]);

        $this->saveChecklist([
            'piv' => ['held' => '1', 'status' => 'supervised', 'was' => 'granted'],
            'chemotherapy' => ['held' => '1', 'status' => 'granted', 'was' => ''],
        ])
            ->assertSessionHas('success', 'Privileges saved: 1 granted, 1 changed.')
            // Chemotherapy needs an oncology certificate this nurse does not have: allowed, but flagged
            ->assertSessionHas('warning', 'Granted without a valid certificate: Chemotherapy administration (needs Post Basic Oncology). '
                . 'Add the certificate under Credentials, or the privilege stays at risk.');

        // Changing how it is held keeps its dates
        $piv->refresh();
        $this->assertSame('supervised', $piv->status);
        $this->assertSame('2026-01-10', $piv->granted_on->toDateString());
        $this->assertSame('2029-01-10', $piv->review_on->toDateString());

        // Held again after being withdrawn is a new grant
        $chemotherapy->refresh();
        $this->assertSame('granted', $chemotherapy->status);
        $this->assertSame('chemotherapy', $chemotherapy->code);
        $this->assertSame('2026-09-22', $chemotherapy->granted_on->toDateString());
        $this->assertSame('2027-09-22', $chemotherapy->review_on->toDateString());
        $this->assertNull($chemotherapy->notes);
        $this->assertTrue(UserActivity::where('description',
            'Changed privilege "Chemotherapy administration" from Withdrawn to Granted for nurse Nurul Huda')->exists());

        $data = NurseCredentialing::forNurse($this->nurse);
        $this->assertSame(1, $data['counts']['at_risk']);
        $this->assertSame(['level' => 'amber', 'text' => '1 privilege at risk'], $data['attention']);
    }

    public function test_only_what_was_changed_on_the_page_is_saved(): void
    {
        // Granted by someone else after this page was drawn: the page never touched it
        $privilege = $this->privilege();
        $this->saveChecklist(['piv' => ['status' => 'granted', 'was' => '']])
            ->assertSessionHas('success', 'No changes to the privileges.');
        $this->assertSame('granted', $privilege->fresh()->status);

        // Ticked on the page but already held: nothing to add
        $this->saveChecklist(['piv' => ['held' => '1', 'status' => 'granted', 'was' => '']])
            ->assertSessionHas('success', 'No changes to the privileges.');
        $this->assertSame(1, $this->nurse->privileges()->count());
    }

    public function test_the_review_date_can_be_a_given_date_or_none(): void
    {
        $this->saveChecklist(['ecg' => ['held' => '1', 'status' => 'granted', 'was' => '']], ['review' => 'date'])
            ->assertSessionHasErrors(['checklist.review_on' => 'Pick the review date.']);

        $this->saveChecklist(['ecg' => ['held' => '1', 'status' => 'granted', 'was' => '']], ['review' => 'date', 'review_on' => '2027-01-15'])
            ->assertSessionHasNoErrors();
        $this->assertSame('2027-01-15', $this->nurse->privileges()->where('code', 'ecg')->sole()->review_on->toDateString());

        $this->saveChecklist(['oxygen' => ['held' => '1', 'status' => 'granted', 'was' => '']], ['review' => 'none'])
            ->assertSessionHasNoErrors();
        $this->assertNull($this->nurse->privileges()->where('code', 'oxygen')->sole()->review_on);

        $this->saveChecklist(['glucose' => ['held' => '1', 'status' => 'granted', 'was' => '']], ['granted_on' => '2026-09-23'])
            ->assertSessionHasErrors(['checklist.granted_on' => 'The date granted cannot be in the future.']);
    }

    public function test_certificates_on_file_decide_which_privileges_are_at_risk(): void
    {
        // Expired ACLS; a valid PALS is not an ACLS; a midwife's registration never runs out
        $this->credential(['type' => 'life_support', 'title' => 'Advanced Cardiac Life Support (ACLS)', 'issued_on' => '2024-03-03', 'expires_on' => '2026-03-03']);
        $this->credential(['type' => 'life_support', 'title' => 'Paediatric Advanced Life Support (PALS)', 'issued_on' => '2025-01-01', 'expires_on' => '2027-01-01']);
        $this->credential(['type' => 'registration', 'title' => 'Registered Midwife', 'issued_on' => '2015-01-01', 'expires_on' => null]);
        $this->privilege(['name' => 'Adult advanced life support', 'category' => 'advanced']);
        $this->privilege(['name' => 'Neonatal resuscitation', 'category' => 'advanced']);
        $this->privilege(['name' => 'Paediatric advanced life support', 'category' => 'advanced']);

        $data = NurseCredentialing::forNurse($this->nurse);
        $this->assertSame(['key' => 'expired', 'chip' => 'ACLS expired 3 Mar 2026', 'title' => 'Advanced Cardiac Life Support (ACLS)'], $data['certificates']['acls']);
        $this->assertSame('valid', $data['certificates']['pals']['key']);
        $this->assertSame('missing', $data['certificates']['nrp']['key']);
        $this->assertSame(['key' => 'valid', 'chip' => 'Midwifery on file', 'title' => 'Registered Midwife'], $data['certificates']['midwifery']);

        // ACLS ran out (red), NRP was never recorded; PALS is fine
        $this->assertSame(2, $data['counts']['at_risk']);
        $this->assertSame(1, $data['counts']['at_risk_lapsed']);
        $this->assertSame(['level' => 'red', 'text' => '1 expired, 2 privileges at risk'], $data['attention']);

        // A specialty section opens for a nurse who works in it
        $this->assertTrue($data['checklist']['resuscitation']['open']);
        $this->assertTrue($data['checklist']['midwifery']['open']);
        $this->assertFalse($data['checklist']['perioperative']['open']);
        $this->assertSame(3, $data['checklist']['resuscitation']['held']);

        $this->actingAs($this->user)->get($this->tabUrl())
            ->assertSee('At risk')
            ->assertSee('2 at risk')
            ->assertSee('ACLS expired 3 Mar 2026')
            ->assertSee('Needs NRP')
            ->assertSee('Midwifery on file')
            ->assertSee('title="1 expired, 2 privileges at risk"', false);
    }

    public function test_a_privilege_of_the_hospitals_own_is_kept_apart_and_a_listed_one_keeps_its_name(): void
    {
        $this->actingAs($this->user)->post(route('nurses.privileges.store', $this->nurse), [
            'privilege' => ['name' => 'Plaster of Paris application', 'category' => 'advanced', 'status' => 'granted'],
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->user)->post(route('nurses.privileges.store', $this->nurse), [
            'privilege' => ['name' => 'ecg recording', 'category' => 'advanced', 'status' => 'granted'],
        ])->assertSessionHasNoErrors();

        $own = $this->nurse->privileges()->where('name', 'Plaster of Paris application')->sole();
        $this->assertNull($own->code);
        $this->assertSame('advanced', $own->category);
        $ecg = $this->nurse->privileges()->where('code', 'ecg')->sole();
        $this->assertSame('ECG recording', $ecg->name);
        $this->assertSame('core', $ecg->category);

        // Editing a listed one cannot rename it or change its level
        $this->actingAs($this->user)->put(route('nurses.privileges.update', [$this->nurse, $ecg]), [
            'privilege' => ['name' => 'Something else', 'category' => 'advanced', 'status' => 'supervised'],
        ])->assertSessionHasNoErrors();
        $ecg->refresh();
        $this->assertSame('ECG recording', $ecg->name);
        $this->assertSame('core', $ecg->category);
        $this->assertSame('supervised', $ecg->status);

        $data = NurseCredentialing::forNurse($this->nurse);
        $this->assertSame(['Plaster of Paris application'], $data['other']->map(fn ($row) => $row['privilege']->name)->all());

        $this->actingAs($this->user)->get($this->tabUrl())
            ->assertSee('Other privileges')
            ->assertSee('Plaster of Paris application')
            ->assertSee('Named by the built-in list.');
    }
}
