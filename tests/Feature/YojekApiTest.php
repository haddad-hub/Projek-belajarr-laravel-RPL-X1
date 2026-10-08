<?php

namespace Tests\Feature;

use App\Models\FinanceEntry;
use App\Models\Order;
use App\Models\User;
use App\Models\YojekSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class YojekApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_courier_and_admin_share_the_mysql_order_state(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $courier = User::factory()->create(['role' => 'courier', 'vehicle' => 'Motor']);
        $customerToken = $this->tokenFor($customer);
        $courierToken = $this->tokenFor($courier);

        $created = $this->withHeader('X-Yojek-Token', $customerToken)->postJson('/api/yojek/orders', [
            'item' => 'Nasi goreng',
            'category' => 'Makanan',
            'qty' => 2,
            'pickup' => 'Warung A',
            'dropoff' => 'Jl. B',
            'note' => 'Tanpa pedas',
        ])->assertCreated()->json('order');

        $orderId = $created['id'];

        $this->withHeader('X-Yojek-Token', $courierToken)
            ->getJson('/api/yojek/state')
            ->assertOk()
            ->assertJsonPath('orders.0.id', $orderId);

        $this->withHeader('X-Yojek-Token', $courierToken)
            ->postJson("/api/yojek/orders/{$orderId}/accept")
            ->assertOk()
            ->assertJsonPath('order.status', 'accepted');

        $this->withHeader('X-Yojek-Token', $courierToken)
            ->postJson("/api/yojek/orders/{$orderId}/complete", ['amount' => 25000])
            ->assertOk()
            ->assertJsonPath('order.status', 'awaiting_confirmation');

        $this->withHeader('X-Yojek-Token', $customerToken)
            ->postJson("/api/yojek/orders/{$orderId}/confirm")
            ->assertOk()
            ->assertJsonPath('order.status', 'completed');

        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'completed', 'courier_user_id' => $courier->id]);
        $this->assertDatabaseCount('finance_entries', 1);

        $this->withHeader('X-Yojek-Token', $customerToken)
            ->postJson("/api/yojek/orders/{$orderId}/confirm")
            ->assertStatus(422);
        $this->assertDatabaseCount('finance_entries', 1);

        $this->withHeader('X-Yojek-Token', $this->adminToken())
            ->getJson('/api/yojek/state')
            ->assertOk()
            ->assertJsonPath('orders.0.id', $orderId)
            ->assertJsonCount(1, 'finance');
    }

    public function test_users_cannot_mutate_another_customers_order(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $otherCustomer = User::factory()->create(['role' => 'customer']);
        $otherCustomerToken = $this->tokenFor($otherCustomer);
        $order = Order::create([
            'customer_user_id' => $owner->id,
            'customer_name' => $owner->name,
            'item' => 'Minuman',
            'category' => 'Minuman',
            'qty' => 1,
            'pickup' => 'A',
            'dropoff' => 'B',
            'status' => 'awaiting_confirmation',
        ]);

        $this->withHeader('X-Yojek-Token', $otherCustomerToken)
            ->postJson("/api/yojek/orders/{$order->id}/confirm")
            ->assertForbidden();

        $this->assertDatabaseCount('finance_entries', 0);
    }

    public function test_disabled_courier_cannot_accept_an_order_until_reactivated(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $courier = User::factory()->create(['role' => 'courier']);
        $customerToken = $this->tokenFor($customer);
        $courierToken = $this->tokenFor($courier);
        $order = $this->withHeader('X-Yojek-Token', $customerToken)->postJson('/api/yojek/orders', [
            'item' => 'Mie ayam',
            'category' => 'Makanan',
            'qty' => 1,
            'pickup' => 'A',
            'dropoff' => 'B',
        ])->assertCreated()->json('order');

        $this->withHeader('X-Yojek-Token', $this->adminToken())
            ->postJson("/api/yojek/couriers/{$courier->id}/disabled", ['disabled' => true])
            ->assertOk();

        $this->withHeader('X-Yojek-Token', $courierToken)
            ->postJson('/api/yojek/orders/'.$order['id'].'/accept')
            ->assertStatus(422)
            ->assertJsonValidationErrors('courier');
        $this->assertDatabaseHas('orders', ['id' => $order['id'], 'status' => 'new', 'courier_user_id' => null]);
    }

    public function test_state_uses_the_authenticated_identity_and_hides_other_courier_attendance_from_customers(): void
    {
        $customer = User::factory()->create(['role' => 'customer', 'phone' => '0812345678']);
        $customerToken = $this->tokenFor($customer);
        $courier = User::factory()->create([
            'role' => 'courier',
            'attendance_photo' => 'private-attendance-photo',
            'attendance_location' => ['latitude' => -0.5, 'longitude' => 117.1],
        ]);
        $courierToken = $this->tokenFor($courier);

        $this->withHeader('X-Yojek-Token', $customerToken)
            ->getJson('/api/yojek/state')
            ->assertOk()
            ->assertJsonPath('actor.id', $customer->id)
            ->assertJsonPath('actor.role', 'customer')
            ->assertJsonCount(0, 'couriers');

        $this->withHeader('X-Yojek-Token', $courierToken)
            ->postJson('/api/yojek/profile', ['phone' => '0899999999', 'vehicle' => 'Motor'])
            ->assertOk()
            ->assertJsonPath('actor.phone', '0899999999');

        $this->assertDatabaseHas('users', ['id' => $courier->id, 'phone' => '0899999999', 'vehicle' => 'Motor']);
    }

    public function test_operational_reset_preserves_admin_accounts_only(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);
        $courier = User::factory()->create(['role' => 'courier']);
        $order = Order::create([
            'customer_user_id' => $customer->id,
            'courier_user_id' => $courier->id,
            'customer_name' => $customer->name,
            'item' => 'Nasi goreng',
            'category' => 'Makanan',
            'qty' => 1,
            'pickup' => 'A',
            'dropoff' => 'B',
            'status' => 'completed',
            'revenue' => 25000,
        ]);
        FinanceEntry::create([
            'order_id' => $order->id,
            'category' => 'Pembayaran Order',
            'type' => 'income',
            'amount' => 25000,
        ]);

        $this->artisan('yojek:reset-operational-data --force')
            ->assertSuccessful();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'role' => 'admin']);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('finance_entries', 0);
    }

    public function test_customer_receives_a_clear_error_when_trying_to_accept_an_order_as_a_courier(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $customerToken = $this->tokenFor($customer);
        $order = Order::create([
            'customer_user_id' => $customer->id,
            'customer_name' => $customer->name,
            'item' => 'Minuman',
            'category' => 'Minuman',
            'qty' => 1,
            'pickup' => 'A',
            'dropoff' => 'B',
            'status' => 'new',
        ]);

        $this->withHeader('X-Yojek-Token', $customerToken)
            ->postJson("/api/yojek/orders/{$order->id}/accept")
            ->assertStatus(403)
            ->assertJsonPath('message', 'Sesi aktif adalah akun pelanggan. Silakan logout lalu login sebagai kurir.');
    }

    public function test_customer_and_courier_logins_issue_independent_tab_tokens(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $courier = User::factory()->create(['role' => 'courier']);

        $this->actingAs($customer);
        $customerToken = $this->postJson('/api/yojek/login', [
            'role' => 'customer',
            'email' => $customer->email,
            'password' => 'password',
        ])->assertOk()->json('token');

        $courierToken = $this->postJson('/api/yojek/login', [
            'role' => 'courier',
            'email' => $courier->email,
            'password' => 'password',
        ])->assertOk()->json('token');

        $this->assertNotSame($customerToken, $courierToken);
        $this->assertAuthenticatedAs($customer);

        $order = $this->withHeader('X-Yojek-Token', $customerToken)
            ->postJson('/api/yojek/orders', [
                'item' => 'Pesanan per tab',
                'category' => 'Makanan',
                'qty' => 1,
                'pickup' => 'Lokasi A',
                'dropoff' => 'Lokasi B',
            ])->assertCreated()->json('order');

        $this->withHeader('X-Yojek-Token', $courierToken)
            ->getJson('/api/yojek/state')
            ->assertOk()
            ->assertJsonPath('actor.id', $courier->id)
            ->assertJsonPath('orders.0.id', $order['id']);

        $this->withHeader('X-Yojek-Token', $customerToken)
            ->getJson('/api/yojek/state')
            ->assertOk()
            ->assertJsonPath('actor.id', $customer->id)
            ->assertJsonPath('orders.0.id', $order['id']);
    }

    public function test_registration_creates_a_tab_token_without_logging_into_the_shared_web_session(): void
    {
        $response = $this->postJson('/api/yojek/register', [
            'name' => 'Tab Courier',
            'email' => 'tab-courier@example.test',
            'role' => 'courier',
            'phone' => '081234567890',
            'address' => 'Alamat uji',
            'vehicle' => 'Motor',
            'password' => 'TestPassword!2026',
            'password_confirmation' => 'TestPassword!2026',
        ])->assertCreated()
            ->assertJsonPath('role', 'courier');

        $this->assertGuest();
        $this->assertDatabaseHas('users', [
            'email' => 'tab-courier@example.test',
            'role' => 'courier',
        ]);
        $this->assertDatabaseHas('yojek_sessions', [
            'token_hash' => hash('sha256', $response->json('token')),
            'role' => 'courier',
        ]);
    }

    public function test_web_cookie_without_a_tab_token_does_not_authenticate_yojek_api(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer)
            ->getJson('/api/yojek/state')
            ->assertUnauthorized();
    }

    private function tokenFor(User $user): string
    {
        $token = Str::random(80);
        YojekSession::create([
            'token_hash' => hash('sha256', $token),
            'user_id' => $user->id,
            'role' => $user->role,
            'expires_at' => now()->addDay(),
        ]);

        return $token;
    }

    private function adminToken(): string
    {
        $token = Str::random(80);
        YojekSession::create([
            'token_hash' => hash('sha256', $token),
            'user_id' => null,
            'role' => 'admin',
            'expires_at' => now()->addDay(),
        ]);

        return $token;
    }
}
