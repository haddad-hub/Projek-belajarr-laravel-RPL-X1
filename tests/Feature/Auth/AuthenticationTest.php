<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\YojekSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_login_and_registration_screens_remain_available_with_another_tab_session(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        $this->actingAs($user)
            ->get('/login')
            ->assertOk()
            ->assertSee('Masuk pelanggan atau kurir');

        $this->actingAs($user)
            ->get('/register')
            ->assertOk()
            ->assertSee('Buat akun operasional');
    }

    public function test_default_dashboard_redirects_to_yojek(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('yojek.launch'));
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'role' => 'customer',
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('yojek.launch', absolute: false));
    }

    public function test_customer_login_clears_an_existing_admin_session_flag(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        $this->withSession(['yojek_admin' => true])
            ->post('/login', [
                'role' => 'customer',
                'email' => $user->email,
                'password' => 'password',
            ])
            ->assertRedirect(route('yojek.launch', absolute: false))
            ->assertSessionMissing('yojek_admin');
    }

    public function test_admin_can_authenticate_using_static_credentials(): void
    {
        $response = $this->post('/admin/login', [
            'email' => 'adminyojek',
            'password' => 'admin123',
        ]);

        $response->assertRedirect('/Yojek/dashboard%20yojek.html');
    }

    public function test_admin_login_screen_is_separate_from_customer_login(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Pelanggan')
            ->assertSee('Kurir')
            ->assertDontSee('Admin Dashboard');

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Sign in admin')
            ->assertDontSee('Buat akun')
            ->assertDontSee('Pelanggan')
            ->assertDontSee('Kurir');
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'role' => 'customer',
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();
        YojekSession::create([
            'token_hash' => hash('sha256', 'user-session-token'),
            'user_id' => $user->id,
            'role' => $user->role,
            'expires_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $this->assertDatabaseMissing('yojek_sessions', ['user_id' => $user->id]);
        $response->assertRedirect('/');
    }

    public function test_api_logout_revokes_only_this_tab_token(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $token = 'api-session-token';
        YojekSession::create([
            'token_hash' => hash('sha256', $token),
            'user_id' => $user->id,
            'role' => $user->role,
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($user)
            ->withHeader('X-Yojek-Token', $token)
            ->postJson('/api/yojek/logout')
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseMissing('yojek_sessions', ['token_hash' => hash('sha256', $token)]);
        $this->withHeader('X-Yojek-Token', $token)
            ->getJson('/api/yojek/state')
            ->assertUnauthorized();
    }

    public function test_customer_and_courier_data_api_routes_are_not_registered(): void
    {
        $viewer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($viewer)
            ->getJson('/api/customers')
            ->assertNotFound();

        $this->actingAs($viewer)
            ->getJson('/api/couriers')
            ->assertNotFound();
    }
}
