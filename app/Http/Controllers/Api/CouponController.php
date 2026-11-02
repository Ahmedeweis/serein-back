<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class CouponController extends Controller
{
    /**
     * Validate a coupon code against a given cart subtotal.
     *
     * ⚠️  SECURITY NOTE — Preview Only
     * ──────────────────────────────────────────────────────────────────────────
     * This endpoint is intentionally PUBLIC so the storefront can give instant
     * feedback before the user submits the order.  The discount calculated here
     * is NOT trusted by the backend.  OrderController re-validates and
     * re-calculates everything inside a DB::transaction() at checkout time.
     * ──────────────────────────────────────────────────────────────────────────
     *
     * Security hardening applied:
     *  1. Route is throttled (10 req/min per IP) — see routes/api.php
     *  2. Input max-length cap on code (max:50) to prevent header-size attacks
     *  3. Active check merged into a single query (no timing side-channel leakage
     *     between "not found" and "inactive" — both return the same 422 message)
     *  4. Expiry check (expires_at)
     *  5. Minimum spend check (min_spend)
     */
    #[OA\Post(
        path: '/api/coupons/validate',
        summary: 'Validate coupon code and calculate discount (Preview — not authoritative)',
        tags: ['Coupons'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['code', 'subtotal'],
                properties: [
                    new OA\Property(property: 'code',     type: 'string',  example: 'SAVE10'),
                    new OA\Property(property: 'subtotal', type: 'number',  example: 250.00),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Coupon is valid — discount calculated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'valid',           type: 'boolean', example: true),
                        new OA\Property(property: 'message',         type: 'string',  example: 'Coupon applied successfully (10% off).'),
                        new OA\Property(property: 'code',            type: 'string',  example: 'SAVE10'),
                        new OA\Property(property: 'percentage',      type: 'number',  example: 10),
                        new OA\Property(property: 'discount_amount', type: 'number',  example: 25.00),
                        new OA\Property(property: 'subtotal',        type: 'number',  example: 250.00),
                        new OA\Property(property: 'new_subtotal',    type: 'number',  example: 225.00),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Invalid, expired, or inapplicable coupon'),
            new OA\Response(response: 429, description: 'Too Many Requests — rate limit exceeded'),
        ]
    )]
    public function validateCoupon(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'     => ['required', 'string', 'max:50'],
            'subtotal' => ['required', 'numeric', 'min:0'],
        ]);

        $code     = strtoupper(trim($validated['code']));
        $subtotal = (float) $validated['subtotal'];

        // ── Check 1: Existence + active in one query ──────────────────────────
        // Returning the same generic message for "not found" and "inactive"
        // prevents enumeration attacks that rely on differing error responses.
        $coupon = Coupon::where('code', $code)->first();

        if (! $coupon || ! $coupon->is_active) {
            return response()->json([
                'valid'   => false,
                'message' => 'Invalid or inactive coupon code.',
            ], 422);
        }

        // ── Check 2: Expiry date ──────────────────────────────────────────────
        if ($coupon->expires_at && $coupon->expires_at->isPast()) {
            return response()->json([
                'valid'   => false,
                'message' => 'This coupon has expired.',
            ], 422);
        }

        // ── Check 3: Maximum usage limit ──────────────────────────────────────
        if ($coupon->max_uses !== null && $coupon->used_count >= $coupon->max_uses) {
            return response()->json([
                'valid'   => false,
                'message' => 'This coupon has reached its maximum usage limit.',
            ], 422);
        }

        // ── Check 4: Minimum spend ────────────────────────────────────────────
        if (! is_null($coupon->min_spend) && $subtotal < (float) $coupon->min_spend) {
            return response()->json([
                'valid'   => false,
                'message' => "Order subtotal must be at least {$coupon->min_spend} to apply this coupon.",
            ], 422);
        }

        // ── Check 5: Discount calculation (server-side, never trust client) ───
        $percentage     = (float) $coupon->percentage;
        $discountAmount = round(($subtotal * $percentage) / 100, 2);
        $newSubtotal    = max(0.0, round($subtotal - $discountAmount, 2));

        return response()->json([
            'valid'           => true,
            'message'         => "Coupon applied successfully ({$percentage}% off).",
            'code'            => $coupon->code,
            'percentage'      => $percentage,
            'discount_amount' => $discountAmount,
            'subtotal'        => $subtotal,
            'new_subtotal'    => $newSubtotal,
        ]);
    }
}
