<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrationUserTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
    }

    private function integrationUser(): User
    {
        return User::factory()->create(['name' => 'C+ RPA', 'email' => 'cplus-rpa@integration.local', 'role' => User::ROLE_INTEGRATION]);
    }

    public function test_an_integration_user_is_created_without_a_password_and_lands_on_its_token_page(): void
    {
        $response = $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'C+ RPA', 'email' => 'cplus-rpa@integration.local', 'role' => User::ROLE_INTEGRATION,
        ]);

        $user = User::where('email', 'cplus-rpa@integration.local')->firstOrFail();
        $response->assertRedirect(route('users.edit', $user));
        $this->assertTrue($user->isIntegration());
        $this->assertFalse($user->hasApiToken());

        $this->actingAs($this->admin)->get(route('users.edit', $user))
            ->assertOk()
            ->assertSee('API token')
            ->assertSee('Generate token')
            ->assertDontSee('Change Password (Optional)');
    }

    public function test_other_roles_still_need_a_password(): void
    {
        $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'Nurse Aida', 'email' => 'aida@example.com', 'role' => User::ROLE_NURSE,
        ])->assertSessionHasErrors('password');
    }

    public function test_the_token_is_shown_once_and_only_its_hash_is_kept(): void
    {
        $user = $this->integrationUser();

        $response = $this->actingAs($this->admin)->post(route('users.api-token.generate', $user));

        $response->assertRedirect(route('users.edit', $user));
        $token = session('api_token');
        $this->assertIsString($token);
        $this->assertSame(hash('sha256', $token), $user->fresh()->api_token);
        $this->assertTrue(User::findByApiToken($token)->is($user));

        $this->actingAs($this->admin)->withSession(['api_token' => $token])->get(route('users.edit', $user))
            ->assertSee($token)->assertSee('It will not be shown again.');
        $this->actingAs($this->admin)->get(route('users.edit', $user))
            ->assertDontSee($token)->assertSee('Regenerate token');
    }

    public function test_regenerating_retires_the_old_token_and_revoking_retires_it_too(): void
    {
        $user = $this->integrationUser();
        $old = $user->generateApiToken();

        $this->actingAs($this->admin)->post(route('users.api-token.generate', $user));
        $this->assertNull(User::findByApiToken($old));
        $this->assertNotNull(User::findByApiToken(session('api_token')));

        $this->actingAs($this->admin)->post(route('users.api-token.revoke', $user))->assertRedirect(route('users.edit', $user));
        $this->assertFalse($user->fresh()->hasApiToken());
    }

    public function test_only_integration_users_get_a_token_and_changing_the_role_drops_it(): void
    {
        $nurse = User::factory()->create(['role' => User::ROLE_NURSE]);
        $this->actingAs($this->admin)->post(route('users.api-token.generate', $nurse))->assertSessionHas('error');
        $this->assertFalse($nurse->fresh()->hasApiToken());

        $user = $this->integrationUser();
        $token = $user->generateApiToken();
        $this->actingAs($this->admin)->postJson(route('users.update-role', $user), ['role' => User::ROLE_USER])->assertOk();

        $this->assertFalse($user->fresh()->hasApiToken());
        $this->assertNull(User::findByApiToken($token));
    }

    public function test_an_integration_user_cannot_sign_in_to_the_web_ui(): void
    {
        $user = User::factory()->create(['email' => 'rpa@integration.local', 'role' => User::ROLE_INTEGRATION, 'password' => 'secret-password']);

        $this->post('/login', ['email' => $user->email, 'password' => 'secret-password'])
            ->assertSessionHasErrors(['email' => 'Integration users connect with an API token and cannot sign in here.']);
        $this->assertGuest();
    }

    public function test_the_users_list_links_to_the_token(): void
    {
        $user = $this->integrationUser();

        $this->actingAs($this->admin)->get(route('users.index'))
            ->assertOk()
            ->assertSee(route('users.edit', $user) . '#api-token', false)
            ->assertSee('Integration User');
    }
}
