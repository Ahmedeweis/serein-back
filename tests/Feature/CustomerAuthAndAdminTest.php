<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerAuthAndAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_register_successfully(): void
    {
        $payload = [
            'first_name'            => 'Ahmed',
            'last_name'             => 'Eweis',
            'email'                 => 'ahmed.customer@example.com',
            'phone'                 => '01012345678',
            'password'              => 'Secret1234',
            'password_confirmation' => 'Secret1234',
            'country'               => 'Egypt',
            'governorate'           => 'Cairo',
            'address'               => '15 El-Tahrir Street',
        ];

        $response = $this->postJson('/api/auth/register', $payload);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'message',
                     'user' => ['id', 'name', 'first_name', 'last_name', 'email', 'phone', 'governorate', 'address'],
                     'token',
                 ]);

        $this->assertDatabaseHas('users', [
            'email'       => 'ahmed.customer@example.com',
            'phone'       => '01012345678',
            'governorate' => 'Cairo',
            'role'        => 'user',
        ]);
    }

    public function test_customer_can_login_with_valid_credentials(): void
    {
        $user = User::create([
            'first_name'  => 'Sara',
            'last_name'   => 'Ali',
            'email'       => 'sara@example.com',
            'phone'       => '01123456789',
            'password'    => Hash::make('Password123'),
            'role'        => 'user',
            'country'     => 'Egypt',
            'governorate' => 'Giza',
            'address'     => '10 Dokki St',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'sara@example.com',
            'password' => 'Password123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'message',
                     'user' => ['id', 'name', 'email', 'phone'],
                     'token',
                 ]);
    }

    public function test_customer_cannot_login_with_invalid_credentials(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email'    => 'nonexistent@example.com',
            'password' => 'WrongPass',
        ]);

        $response->assertStatus(401);
    }

    public function test_authenticated_customer_can_fetch_and_update_profile(): void
    {
        $user = User::create([
            'first_name'  => 'Omar',
            'last_name'   => 'Farouk',
            'email'       => 'omar@example.com',
            'phone'       => '01234567890',
            'password'    => Hash::make('OldPassword123'),
            'role'        => 'user',
            'country'     => 'Egypt',
            'governorate' => 'Alexandria',
            'address'     => '20 Corniche St',
        ]);

        $token = $user->createToken('test_token')->plainTextToken;

        // Fetch profile
        $meResponse = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/auth/me');

        $meResponse->assertStatus(200)
                   ->assertJsonPath('user.email', 'omar@example.com')
                   ->assertJsonPath('user.first_name', 'Omar');

        // Update profile (including password)
        $updateResponse = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->putJson('/api/auth/profile', [
                'first_name'            => 'Omar Updated',
                'last_name'             => 'Farouk',
                'phone'                 => '01234567890',
                'governorate'           => 'Cairo',
                'address'               => 'New Cairo, 5th Settlement',
                'current_password'      => 'OldPassword123',
                'password'              => 'NewPassword123',
                'password_confirmation' => 'NewPassword123',
            ]);

        $updateResponse->assertStatus(200)
                       ->assertJsonPath('user.first_name', 'Omar Updated')
                       ->assertJsonPath('user.governorate', 'Cairo');

        $user->refresh();
        $this->assertEquals('Omar Updated', $user->first_name);
        $this->assertEquals('Cairo', $user->governorate);
        $this->assertTrue(Hash::check('NewPassword123', $user->password));
    }

    public function test_customer_can_view_order_history(): void
    {
        $user = User::create([
            'first_name'  => 'Youssef',
            'last_name'   => 'Mansour',
            'email'       => 'youssef@example.com',
            'phone'       => '01099887766',
            'password'    => Hash::make('Password123'),
            'role'        => 'user',
            'governorate' => 'Cairo',
        ]);

        $token = $user->createToken('test_token')->plainTextToken;

        Order::create([
            'order_number'    => 'ORD-TEST-1',
            'user_id'         => $user->id,
            'customer_name'   => 'Youssef Mansour',
            'customer_email'  => 'youssef@example.com',
            'subtotal'        => 300,
            'shipping_cost'   => 50,
            'total'           => 350,
            'status'          => 'Delivered',
        ]);

        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/customer/orders');

        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data')
                 ->assertJsonPath('data.0.order_number', 'ORD-TEST-1');
    }

    public function test_admin_can_manage_customers_crud(): void
    {
        $admin = User::create([
            'first_name'  => 'Admin',
            'last_name'   => 'Boss',
            'email'       => 'admin@serein.com',
            'phone'       => '01000000001',
            'password'    => Hash::make('AdminPass123'),
            'role'        => 'admin',
        ]);

        $adminToken = $admin->createToken('admin_token')->plainTextToken;

        $customer = User::create([
            'first_name'  => 'Kareem',
            'last_name'   => 'Nabil',
            'email'       => 'kareem@example.com',
            'phone'       => '01511223344',
            'password'    => Hash::make('Secret123'),
            'role'        => 'user',
            'governorate' => 'Alexandria',
        ]);

        Order::create([
            'order_number'    => 'ORD-KAREEM-1',
            'user_id'         => $customer->id,
            'customer_name'   => 'Kareem Nabil',
            'customer_email'  => 'kareem@example.com',
            'total'           => 500,
            'status'          => 'Confirmed',
        ]);

        // 1. List customers
        $listRes = $this->withHeaders(['Authorization' => "Bearer {$adminToken}"])
            ->getJson('/api/admin/customers?search=Kareem');

        $listRes->assertStatus(200)
                ->assertJsonPath('data.0.email', 'kareem@example.com')
                ->assertJsonPath('data.0.orders_count', 1)
                ->assertJsonPath('data.0.total_spent', 500);

        // 2. Show customer
        $showRes = $this->withHeaders(['Authorization' => "Bearer {$adminToken}"])
            ->getJson("/api/admin/customers/{$customer->id}");

        $showRes->assertStatus(200)
                ->assertJsonPath('data.customer.email', 'kareem@example.com')
                ->assertJsonCount(1, 'data.orders');

        // 3. Update customer
        $updateRes = $this->withHeaders(['Authorization' => "Bearer {$adminToken}"])
            ->putJson("/api/admin/customers/{$customer->id}", [
                'first_name'  => 'Kareem Modified',
                'governorate' => 'Cairo',
            ]);

        $updateRes->assertStatus(200)
                  ->assertJsonPath('data.first_name', 'Kareem Modified')
                  ->assertJsonPath('data.governorate', 'Cairo');

        // 4. Delete customer
        $delRes = $this->withHeaders(['Authorization' => "Bearer {$adminToken}"])
            ->deleteJson("/api/admin/customers/{$customer->id}");

        $delRes->assertStatus(200);
        $this->assertDatabaseMissing('users', ['id' => $customer->id]);

        // 5. Protected: Cannot delete admin
        $delAdminRes = $this->withHeaders(['Authorization' => "Bearer {$adminToken}"])
            ->deleteJson("/api/admin/customers/{$admin->id}");

        $delAdminRes->assertStatus(403);
    }
}
