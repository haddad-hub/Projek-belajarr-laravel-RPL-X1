<?php

namespace Tests\Feature\Auth;

use App\Models\User;
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

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
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
