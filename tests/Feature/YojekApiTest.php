<?php

namespace Tests\Feature;

use App\Models\FinanceEntry;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class YojekApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_courier_and_admin_share_the_mysql_order_state(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $courier = User::factory()->create(['role' => 'courier', 'vehicle' => 'Motor']);

        $created = $this->actingAs($customer)->postJson('/api/yojek/orders', [
            'item' => 'Nasi goreng',
            'category' => 'Makanan',
            'qty' => 2,
            'pickup' => 'Warung A',
            'dropoff' => 'Jl. B',
            'note' => 'Tanpa pedas',
        ])->assertCreated()->json('order');

        $orderId = $created['id'];

        $this->actingAs($courier)
            ->getJson('/api/yojek/state')
            ->assertOk()
            ->assertJsonPath('orders.0.id', $orderId);

        $this->actingAs($courier)
            ->postJson("/api/yojek/orders/{$orderId}/accept")
            ->assertOk()
            ->assertJsonPath('order.status', 'accepted');

        $this->actingAs($courier)
            ->postJson("/api/yojek/orders/{$orderId}/complete", ['amount' => 25000])
            ->assertOk()
            ->assertJsonPath('order.status', 'awaiting_confirmation');

        $this->actingAs($customer)
            ->postJson("/api/yojek/orders/{$orderId}/confirm")
            ->assertOk()
            ->assertJsonPath('order.status', 'completed');

        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'completed', 'courier_user_id' => $courier->id]);
        $this->assertDatabaseCount('finance_entries', 1);

        $this->actingAs($customer)
            ->postJson("/api/yojek/orders/{$orderId}/confirm")
            ->assertStatus(422);
        $this->assertDatabaseCount('finance_entries', 1);

        $this->withSession(['yojek_admin' => true])
            ->getJson('/api/yojek/state')
            ->assertOk()
            ->assertJsonPath('orders.0.id', $orderId)
            ->assertJsonCount(1, 'finance');
    }

    public function test_users_cannot_mutate_another_customers_order(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $otherCustomer = User::factory()->create(['role' => 'customer']);
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

        $this->actingAs($otherCustomer)
            ->postJson("/api/yojek/orders/{$order->id}/confirm")
            ->assertForbidden();

        $this->assertDatabaseCount('finance_entries', 0);
    }

    public function test_disabled_courier_cannot_accept_an_order_until_reactivated(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $courier = User::factory()->create(['role' => 'courier']);
        $order = $this->actingAs($customer)->postJson('/api/yojek/orders', [
            'item' => 'Mie ayam',
            'category' => 'Makanan',
            'qty' => 1,
            'pickup' => 'A',
            'dropoff' => 'B',
        ])->assertCreated()->json('order');

        $this->withSession(['yojek_admin' => true])
            ->postJson("/api/yojek/couriers/{$courier->id}/disabled", ['disabled' => true])
            ->assertOk();

        $this->actingAs($courier)
            ->postJson('/api/yojek/orders/'.$order['id'].'/accept')
            ->assertStatus(422)
            ->assertJsonValidationErrors('courier');
        $this->assertDatabaseHas('orders', ['id' => $order['id'], 'status' => 'new', 'courier_user_id' => null]);
    }

    public function test_state_uses_the_authenticated_identity_and_hides_other_courier_attendance_from_customers(): void
    {
        $customer = User::factory()->create(['role' => 'customer', 'phone' => '0812345678']);
        $courier = User::factory()->create([
            'role' => 'courier',
            'attendance_photo' => 'private-attendance-photo',
            'attendance_location' => ['latitude' => -0.5, 'longitude' => 117.1],
        ]);

        $this->actingAs($customer)
            ->getJson('/api/yojek/state')
            ->assertOk()
            ->assertJsonPath('actor.id', $customer->id)
            ->assertJsonPath('actor.role', 'customer')
            ->assertJsonCount(0, 'couriers');

        $this->actingAs($courier)
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

        $this->actingAs($customer)
            ->postJson("/api/yojek/orders/{$order->id}/accept")
            ->assertStatus(403)
            ->assertJsonPath('message', 'Sesi aktif adalah akun pelanggan. Silakan logout lalu login sebagai kurir.');
    }
}
