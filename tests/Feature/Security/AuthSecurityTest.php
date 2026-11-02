<?php

namespace Tests\Feature\Security;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test 1: Brute-force rate limiting on Login endpoint (throttle:5,1).
     * After 5 failed or rapid requests, subsequent requests should receive 429 Too Many Requests.
     */
    public function test_login_endpoint_throttles_brute_force_attempts(): void
    {
        $payload = [
            'email'    => 'victim@serein.test',
            'password' => 'WrongPassword',
        ];

        // 5 allowed attempts
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/auth/login', $payload);
            $response->assertStatus(401);
        }

        // 6th attempt must be throttled with HTTP 429
        $throttledResponse = $this->postJson('/api/auth/login', $payload);
        $throttledResponse->assertStatus(429);
    }

    /**
     * Test 1 (Cont.): Registration endpoint throttles rapid account creations (throttle:3,1).
     */
    public function test_register_endpoint_throttles_rapid_registration_attempts(): void
    {
        $makePayload = fn ($idx) => [
            'first_name'            => "User{$idx}",
            'last_name'             => "Test",
            'email'                 => "user{$idx}@serein.test",
            'phone'                 => "0100000000{$idx}",
            'password'              => 'Secret1234',
            'password_confirmation' => 'Secret1234',
            'governorate'           => 'Cairo',
            'address'               => 'Test Address',
        ];

        // 3 registrations allowed in 1 minute
        for ($i = 1; $i <= 3; $i++) {
            $res = $this->postJson('/api/auth/register', $makePayload($i));
            $res->assertStatus(201);
        }

        // 4th attempt must be blocked with HTTP 429
        $blocked = $this->postJson('/api/auth/register', $makePayload(4));
        $blocked->assertStatus(429);
    }

    /**
     * Test 2: Clamp per_page query parameter to prevent Memory Exhaustion DoS attacks.
     */
    public function test_orders_endpoint_clamps_per_page_to_safe_boundaries(): void
    {
        $user = User::create([
            'first_name'  => 'Load',
            'last_name'   => 'Tester',
            'email'       => 'load@serein.test',
            'phone'       => '01012341234',
            'password'    => bcrypt('password'),
            'role'        => 'user',
            'governorate' => 'Cairo',
        ]);

        Sanctum::actingAs($user);

        // Requesting an absurdly high per_page must be clamped to 50
        $highResponse = $this->getJson('/api/auth/orders?per_page=9999999');
        $highResponse->assertStatus(200);
        $this->assertEquals(50, $highResponse->json('per_page'));

        // Requesting negative or zero per_page must be clamped to minimum 1
        $lowResponse = $this->getJson('/api/auth/orders?per_page=-5');
        $lowResponse->assertStatus(200);
        $this->assertEquals(1, $lowResponse->json('per_page'));
    }

    /**
     * Test 3: Business logic leak fix: Customer orders are fetched STRICTLY by user_id.
     * Prevents viewing past guest orders created with the same email prior to account creation.
     */
    public function test_customer_orders_are_queried_strictly_by_user_id(): void
    {
        $email = 'guest_turned_user@serein.test';

        // Order 1: Created as a Guest with no user_id (unverified email match)
        Order::create([
            'order_number'    => 'ORD-GUEST-001',
            'user_id'         => null,
            'customer_name'   => 'Unverified Guest',
            'customer_email'  => $email,
            'subtotal'        => 100,
            'shipping_cost'   => 50,
            'total'           => 150,
            'status'          => 'Pending',
        ]);

        // User registers with this email
        $user = User::create([
            'first_name'  => 'New',
            'last_name'   => 'User',
            'email'       => $email,
            'phone'       => '01098765432',
            'password'    => bcrypt('password'),
            'role'        => 'user',
            'governorate' => 'Cairo',
        ]);

        // Order 2: Legitimately created by this user
        Order::create([
            'order_number'    => 'ORD-USER-002',
            'user_id'         => $user->id,
            'customer_name'   => 'New User',
            'customer_email'  => $email,
            'subtotal'        => 200,
            'shipping_cost'   => 50,
            'total'           => 250,
            'status'          => 'Pending',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/auth/orders');

        $response->assertStatus(200);

        // Must ONLY return Order 2, never Order 1 (Guest order)
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('ORD-USER-002', $response->json('data.0.order_number'));
    }

    /**
     * Test 4: Token bloat prevention.
     * Logging in revokes previous tokens so old tokens do not accumulate indefinitely.
     */
    public function test_login_revokes_previous_tokens_to_prevent_token_bloat(): void
    {
        $user = User::create([
            'first_name'  => 'Single',
            'last_name'   => 'Session',
            'email'       => 'session@serein.test',
            'phone'       => '01055555555',
            'password'    => Hash::make('SecretPass123'),
            'role'        => 'user',
            'governorate' => 'Cairo',
        ]);

        // Simulate 3 prior logins
        $user->createToken('token_1');
        $user->createToken('token_2');
        $user->createToken('token_3');

        $this->assertEquals(3, $user->tokens()->count());

        // Performing login
        $response = $this->postJson('/api/auth/login', [
            'email'    => 'session@serein.test',
            'password' => 'SecretPass123',
        ]);

        $response->assertStatus(200);

        // Previous tokens must be wiped; exactly 1 active token should exist now
        $this->assertEquals(1, $user->fresh()->tokens()->count());
    }

    /**
     * Test 5: Standardized UserResource format across responses.
     */
    public function test_user_resource_standardized_format(): void
    {
        $user = User::create([
            'first_name'  => 'Standard',
            'last_name'   => 'Format',
            'email'       => 'standard@serein.test',
            'phone'       => '01066666666',
            'password'    => Hash::make('SecretPass123'),
            'role'        => 'user',
            'country'     => 'Egypt',
            'governorate' => 'Cairo',
            'address'     => '10 Nile St',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'first_name',
                    'last_name',
                    'email',
                    'phone',
                    'role',
                    'country',
                    'governorate',
                    'address',
                ]
            ]);
    }
}
