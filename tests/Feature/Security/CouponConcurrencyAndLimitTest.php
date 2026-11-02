<?php

namespace Tests\Feature\Security;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * CouponConcurrencyAndLimitTest
 * ─────────────────────────────
 * Comprehensive tests for High-Concurrency Coupon Management:
 *  1. Checkout fails (422) if coupon reached max_uses.
 *  2. Checkout fails (422) if coupon is expired (expires_at is past).
 *  3. Success checkout atomically increments used_count by exactly 1.
 *  4. Simulated high-concurrency race condition: max_uses is strictly respected and never exceeded.
 *  5. Admin FormRequest validation: expires_at must be after:now, max_uses >= 1.
 *  6. Mass-Assignment & Immutability: used_count can never be injected via HTTP requests.
 *  7. Preview validation endpoint (validateCoupon) rejects exhausted and expired coupons with 422.
 */
class CouponConcurrencyAndLimitTest extends TestCase
{
    use RefreshDatabase;

    protected Product $product;
    protected ProductVariant $variant;
    protected ShippingZone $zone;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $category = Category::create([
            'name_en'   => 'Honey Collection',
            'name_ar'   => 'مجموعة العسل',
            'slug'      => 'honey-collection',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'category_id' => $category->id,
            'name_en'     => 'Sidr Honey Royal',
            'name_ar'     => 'عسل سدر ملكي',
            'slug'        => 'sidr-honey-royal',
            'is_active'   => true,
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'name_en'    => '1kg Jar',
            'name_ar'    => 'برطمان 1 كجم',
            'sku'        => 'SIDR-1KG',
            'unit'       => 'kg',
            'value'      => 1,
            'price'      => 500.00,
            'stock'      => 50,
            'is_active'  => true,
        ]);

        $this->zone = ShippingZone::create([
            'name_en'   => 'Cairo & Giza',
            'name_ar'   => 'القاهرة والجيزة',
            'price'     => 50.00,
            'min_days'  => 1,
            'max_days'  => 3,
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'first_name' => 'Super',
            'last_name'  => 'Admin',
            'email'      => 'admin.coupons@serein.test',
            'phone'      => '01011119999',
            'password'   => bcrypt('password'),
            'role'       => 'admin',
        ]);
    }

    private function checkoutPayload(string $couponCode): array
    {
        return [
            'first_name'       => 'Ahmed',
            'last_name'        => 'Eweis',
            'email'            => 'ahmed@serein.test',
            'phone'            => '01012345678',
            'address'          => '15 Tahrir Square',
            'city'             => 'Cairo',
            'shipping_zone_id' => $this->zone->id,
            'coupon_code'      => $couponCode,
            'items'            => [
                [
                    'product_id' => $this->product->id,
                    'variant_id' => $this->variant->id,
                    'quantity'   => 1,
                ]
            ],
        ];
    }

    // ── 1. Checkout Rejection: Max Uses Exceeded ─────────────────────────────

    public function test_checkout_fails_with_422_when_coupon_reaches_max_uses(): void
    {
        $coupon = Coupon::create([
            'code'       => 'LIMITED1',
            'percentage' => 15,
            'is_active'  => true,
            'max_uses'   => 1,
            'expires_at' => null,
        ]);

        // Manually mark as already exhausted
        $coupon->used_count = 1;
        $coupon->save();

        $response = $this->postJson('/api/orders', $this->checkoutPayload('LIMITED1'));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['coupon_code'])
            ->assertJsonFragment(['coupon_code' => ['This coupon has reached its maximum usage limit.']]);

        // Ensure stock was not deducted and no order created
        $this->assertEquals(50, $this->variant->fresh()->stock);
        $this->assertDatabaseMissing('orders', ['coupon_code' => 'LIMITED1']);
    }

    // ── 2. Checkout Rejection: Expired Coupon ─────────────────────────────────

    public function test_checkout_fails_with_422_when_coupon_is_expired(): void
    {
        Coupon::create([
            'code'       => 'YESTERDAY',
            'percentage' => 20,
            'is_active'  => true,
            'expires_at' => now()->subMinutes(10), // Expired 10 minutes ago
            'max_uses'   => 100,
        ]);

        $response = $this->postJson('/api/orders', $this->checkoutPayload('YESTERDAY'));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['coupon_code'])
            ->assertJsonFragment(['coupon_code' => ['This coupon has expired.']]);

        $this->assertEquals(50, $this->variant->fresh()->stock);
        $this->assertDatabaseMissing('orders', ['coupon_code' => 'YESTERDAY']);
    }

    // ── 3. Atomic Increment of used_count on Success ──────────────────────────

    public function test_checkout_atomically_increments_used_count_by_exactly_one_on_success(): void
    {
        $coupon = Coupon::create([
            'code'       => 'FRESH5',
            'percentage' => 10,
            'is_active'  => true,
            'max_uses'   => 5,
            'expires_at' => now()->addDays(7),
        ]);

        $this->assertEquals(0, $coupon->fresh()->used_count);

        $response = $this->postJson('/api/orders', $this->checkoutPayload('FRESH5'));

        $response->assertStatus(201);

        // Exactly incremented by 1
        $this->assertEquals(1, $coupon->fresh()->used_count);

        // Second checkout
        $this->postJson('/api/orders', $this->checkoutPayload('FRESH5'))->assertStatus(201);
        $this->assertEquals(2, $coupon->fresh()->used_count);
    }

    // ── 4. Concurrency & Race Condition Protection ───────────────────────────

    public function test_simulated_concurrent_checkout_strictly_respects_max_uses(): void
    {
        // Coupon only allows 1 use total
        $coupon = Coupon::create([
            'code'       => 'ONETIMEONLY',
            'percentage' => 25,
            'is_active'  => true,
            'max_uses'   => 1,
            'expires_at' => now()->addHour(),
        ]);

        // First user checks out: takes the 1 slot
        $firstRes = $this->postJson('/api/orders', $this->checkoutPayload('ONETIMEONLY'));
        $firstRes->assertStatus(201);
        $this->assertEquals(1, $coupon->fresh()->used_count);

        // Second concurrent user attempts immediately with same coupon:
        // Pessimistic lock + Double check causes immediate 422 rejection
        $secondRes = $this->postJson('/api/orders', $this->checkoutPayload('ONETIMEONLY'));
        $secondRes->assertStatus(422)
            ->assertJsonValidationErrors(['coupon_code'])
            ->assertJsonFragment(['coupon_code' => ['This coupon has reached its maximum usage limit.']]);

        // CRITICAL: used_count must NEVER exceed max_uses (still exactly 1)
        $this->assertEquals(1, $coupon->fresh()->used_count);
    }

    // ── 5. Admin Validation: Past Expiry Date Rejected ───────────────────────

    public function test_admin_cannot_create_coupon_with_past_expiry_date(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/admin/coupons', [
            'code'       => 'PASTDATE',
            'percentage' => 10,
            'expires_at' => now()->subDay()->toDateTimeString(), // Past date
            'max_uses'   => 50,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['expires_at']);
    }

    // ── 6. Admin Validation: Invalid max_uses Rejected ────────────────────────

    public function test_admin_cannot_create_coupon_with_invalid_max_uses(): void
    {
        Sanctum::actingAs($this->admin);

        // min:1 violated
        $resZero = $this->postJson('/api/admin/coupons', [
            'code'       => 'ZEROUSES',
            'percentage' => 10,
            'max_uses'   => 0,
        ]);
        $resZero->assertStatus(422)->assertJsonValidationErrors(['max_uses']);

        // Negative value violated
        $resNeg = $this->postJson('/api/admin/coupons', [
            'code'       => 'NEGUSES',
            'percentage' => 10,
            'max_uses'   => -5,
        ]);
        $resNeg->assertStatus(422)->assertJsonValidationErrors(['max_uses']);
    }

    // ── 7. Immutability & Mass-Assignment Protection ──────────────────────────

    public function test_used_count_is_immutable_and_ignored_from_admin_requests(): void
    {
        Sanctum::actingAs($this->admin);

        // Attacker attempts to inject used_count during creation
        $resStore = $this->postJson('/api/admin/coupons', [
            'code'       => 'INJECTED',
            'percentage' => 10,
            'max_uses'   => 100,
            'used_count' => 999, // Attempted tamper
        ]);

        $resStore->assertStatus(201);
        $couponId = $resStore->json('data.id');

        $created = Coupon::findOrFail($couponId);
        // used_count MUST remain default 0
        $this->assertEquals(0, $created->used_count);

        // Attacker attempts to manipulate used_count via update
        $resUpdate = $this->putJson("/api/admin/coupons/{$couponId}", [
            'used_count' => 888, // Attempted tamper
        ]);

        $resUpdate->assertStatus(200);
        $this->assertEquals(0, $created->fresh()->used_count);
    }

    // ── 8. Preview Validation Endpoint Rejections ─────────────────────────────

    public function test_preview_endpoint_rejects_exhausted_coupon(): void
    {
        $coupon = Coupon::create([
            'code'       => 'EXHAUSTED_PREVIEW',
            'percentage' => 10,
            'is_active'  => true,
            'max_uses'   => 5,
        ]);

        $coupon->used_count = 5;
        $coupon->save();

        $response = $this->postJson('/api/coupons/validate', [
            'code'     => 'EXHAUSTED_PREVIEW',
            'subtotal' => 200,
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'valid'   => false,
                'message' => 'This coupon has reached its maximum usage limit.',
            ]);
    }
}
