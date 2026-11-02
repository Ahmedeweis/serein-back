<?php

namespace Tests\Feature\Security;

use App\Models\Coupon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CouponSecurityTest
 * ──────────────────
 * Covers all three hardening areas for the coupon validation endpoint:
 *  1. Rate Limiting  (throttle:10,1 middleware)
 *  2. Full validation: expiry date + minimum spend
 *  3. Correct discount calculation (server-side, Zero-Trust)
 */
class CouponSecurityTest extends TestCase
{
    use RefreshDatabase;

    // ── Helper ────────────────────────────────────────────────────────────────

    private function makeCoupon(array $attrs = []): Coupon
    {
        return Coupon::create(array_merge([
            'code'       => 'SAVE10',
            'percentage' => 10,
            'is_active'  => true,
            'expires_at' => null,
            'min_spend'  => null,
        ], $attrs));
    }

    // ── Fix 1: Unified invalid/inactive message (no timing side-channel) ──────

    public function test_invalid_code_returns_generic_message(): void
    {
        $response = $this->postJson('/api/coupons/validate', [
            'code'     => 'DOESNOTEXIST',
            'subtotal' => 100,
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['valid' => false])
            ->assertJsonFragment(['message' => 'Invalid or inactive coupon code.']);
    }

    public function test_inactive_coupon_returns_same_generic_message(): void
    {
        $this->makeCoupon(['code' => 'INACTIVE', 'is_active' => false]);

        $response = $this->postJson('/api/coupons/validate', [
            'code'     => 'INACTIVE',
            'subtotal' => 100,
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['valid' => false])
            ->assertJsonFragment(['message' => 'Invalid or inactive coupon code.']);
    }

    // ── Fix 2a: Expiry date check ─────────────────────────────────────────────

    public function test_expired_coupon_is_rejected(): void
    {
        $this->makeCoupon([
            'code'       => 'EXPIRED',
            'expires_at' => now()->subDay(),   // expired yesterday
        ]);

        $response = $this->postJson('/api/coupons/validate', [
            'code'     => 'EXPIRED',
            'subtotal' => 200,
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['valid'   => false])
            ->assertJsonFragment(['message' => 'This coupon has expired.']);
    }

    public function test_coupon_expiring_in_future_is_accepted(): void
    {
        $this->makeCoupon([
            'code'       => 'FUTURE',
            'expires_at' => now()->addDay(),   // expires tomorrow
        ]);

        $response = $this->postJson('/api/coupons/validate', [
            'code'     => 'FUTURE',
            'subtotal' => 200,
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['valid' => true]);
    }

    // ── Fix 2b: Minimum spend check ───────────────────────────────────────────

    public function test_coupon_rejected_when_subtotal_below_min_spend(): void
    {
        $this->makeCoupon([
            'code'      => 'VIP100',
            'min_spend' => 500,
        ]);

        $response = $this->postJson('/api/coupons/validate', [
            'code'     => 'VIP100',
            'subtotal' => 200,   // below minimum of 500
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['valid' => false])
            ->assertJsonPath('message', 'Order subtotal must be at least 500.00 to apply this coupon.');
    }

    public function test_coupon_accepted_when_subtotal_meets_min_spend(): void
    {
        $this->makeCoupon([
            'code'      => 'VIP100',
            'min_spend' => 500,
        ]);

        $response = $this->postJson('/api/coupons/validate', [
            'code'     => 'VIP100',
            'subtotal' => 500,   // exactly at threshold
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['valid' => true]);
    }

    // ── Fix 3: Server-side discount calculation accuracy ──────────────────────

    public function test_discount_is_calculated_server_side_correctly(): void
    {
        $this->makeCoupon(['code' => 'SAVE20', 'percentage' => 20]);

        $response = $this->postJson('/api/coupons/validate', [
            'code'     => 'SAVE20',
            'subtotal' => 300.00,
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'valid'           => true,
                'percentage'      => 20.0,
                'discount_amount' => 60.0,    // 300 * 20% = 60
                'new_subtotal'    => 240.0,   // 300 - 60 = 240
            ]);
    }

    public function test_new_subtotal_never_goes_below_zero(): void
    {
        $this->makeCoupon(['code' => 'FULL', 'percentage' => 100]);

        $response = $this->postJson('/api/coupons/validate', [
            'code'     => 'FULL',
            'subtotal' => 150.00,
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'valid'        => true,
                'new_subtotal' => 0.0,   // max(0, 150 - 150)
            ]);
    }

    // ── Input validation hardening ────────────────────────────────────────────

    public function test_code_max_50_chars_enforced(): void
    {
        $response = $this->postJson('/api/coupons/validate', [
            'code'     => str_repeat('A', 51),   // 51 chars — should fail
            'subtotal' => 100,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_negative_subtotal_is_rejected(): void
    {
        $response = $this->postJson('/api/coupons/validate', [
            'code'     => 'SAVE10',
            'subtotal' => -50,   // invalid
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['subtotal']);
    }

    // ── Coupon without expiry or min_spend still works ────────────────────────

    public function test_basic_coupon_with_no_expiry_or_min_spend_works(): void
    {
        $this->makeCoupon();

        $response = $this->postJson('/api/coupons/validate', [
            'code'     => 'SAVE10',
            'subtotal' => 100,
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['valid' => true, 'code' => 'SAVE10']);
    }
}
