<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Models\Coupon;
use App\Models\Governorate;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\ShippingZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    /**
     * POST /api/orders
     *
     * Storefront checkout endpoint.
     * Enforces Zero-Trust architecture:
     * 1. Product/variant prices and cart totals sent by the client are NEVER trusted.
     * 2. All unit prices and stock availability are retrieved and locked within a DB transaction.
     * 3. Uses lockForUpdate() on ProductVariant records to prevent race conditions.
     * 4. Shipping costs are derived strictly from active ShippingZone records.
     * 5. Discounts are verified server-side against active Coupons.
     */
    public function store(CheckoutRequest $request): JsonResponse
    {
        $data   = $request->validated();
        $userId = auth('sanctum')->id() ?? $request->user('sanctum')?->id;

        $order = DB::transaction(function () use ($data, $userId) {
            $subtotal        = 0.0;
            $processedItems  = [];

            // ── 1. Validate, lock stock, and compute real prices from DB ────────
            foreach ($data['items'] as $item) {
                $variant = null;

                // 1. Resolve variant by variant_id or size_id
                $variantId = $item['variant_id'] ?? $item['size_id'] ?? null;
                if (!empty($variantId)) {
                    $variant = ProductVariant::with('product')
                        ->where('id', $variantId)
                        ->lockForUpdate()
                        ->first();
                }

                // 2. Fallback: If variant_id is missing, resolve by variant_label / size label
                if (!$variant && !empty($item['product_id']) && !empty($item['variant_label'])) {
                    $label = trim((string) $item['variant_label']);
                    $variant = ProductVariant::with('product')
                        ->where('product_id', $item['product_id'])
                        ->where(function ($q) use ($label) {
                            $q->where('name_en', $label)
                              ->orWhere('name_ar', $label)
                              ->orWhere('name_en', 'like', "%{$label}%")
                              ->orWhere('name_ar', 'like', "%{$label}%");
                        })
                        ->lockForUpdate()
                        ->first();
                }

                // 3. Fallback: Default variant of the product
                if (!$variant && !empty($item['product_id'])) {
                    $variant = ProductVariant::with('product')
                        ->where('product_id', $item['product_id'])
                        ->where('is_default', true)
                        ->lockForUpdate()
                        ->first()
                        ?? ProductVariant::with('product')
                        ->where('product_id', $item['product_id'])
                        ->lockForUpdate()
                        ->first();
                }

                if (!$variant) {
                    throw ValidationException::withMessages([
                        'items' => ['One or more selected items or variants could not be found.'],
                    ]);
                }

                $quantity = (int) $item['quantity'];

                // Enforce stock availability
                if ($variant->stock < $quantity) {
                    $productName = $variant->product?->name_en ?? $item['product_name_en'] ?? 'Product';
                    throw ValidationException::withMessages([
                        'items' => ["The requested quantity for '{$productName}' ({$quantity}) exceeds available stock ({$variant->stock})."],
                    ]);
                }

                // Atomically decrement stock
                $variant->decrement('stock', $quantity);

                // Zero-Trust: Unit price is ALWAYS taken from the database record
                $unitPrice    = (float) $variant->price;
                $itemSubtotal = round($unitPrice * $quantity, 2);
                $subtotal    += $itemSubtotal;

                $processedItems[] = [
                    'product_id'      => $variant->product_id,
                    'variant_id'      => $variant->id,
                    'product_name_en' => $item['product_name_en'] ?? $variant->product?->name_en ?? 'Product',
                    'product_name_ar' => $item['product_name_ar'] ?? $variant->product?->name_ar ?? null,
                    'product_image'   => $item['product_image'] ?? $variant->product?->image ?? null,
                    'variant_label'   => $item['variant_label'] ?? $variant->name_en ?? null,
                    'price'           => $unitPrice,
                    'quantity'        => $quantity,
                ];
            }

            // ── 2. Calculate Shipping Cost securely from Database ──────────────
            $shippingZoneId = $data['shipping_zone_id'] ?? null;
            $shippingCost   = 0.0;

            if ($shippingZoneId) {
                $zone = ShippingZone::where('id', $shippingZoneId)
                    ->where('is_active', true)
                    ->first();
                if ($zone) {
                    $shippingCost = (float) $zone->price;
                } else {
                    $shippingZoneId = null;
                }
            }

            if (!$shippingZoneId && !empty($data['city'])) {
                $gov = Governorate::with('shippingZone')
                    ->where('name_en', $data['city'])
                    ->orWhere('name_ar', $data['city'])
                    ->first();

                if ($gov && $gov->shippingZone && $gov->shippingZone->is_active) {
                    $shippingCost   = (float) $gov->shippingZone->price;
                    $shippingZoneId = $gov->shippingZone->id;
                }
            }

            // ── 3. Calculate Discount securely from Database (Zero-Trust & Concurrency Locked) ──
            $couponCode     = !empty($data['coupon_code']) ? strtoupper(trim($data['coupon_code'])) : null;
            $discountAmount = 0.0;
            $appliedCoupon  = null;

            if ($couponCode) {
                // Pessimistic Lock on coupon row: prevents concurrent race conditions on usage limits
                $coupon = Coupon::where('code', $couponCode)
                    ->lockForUpdate()
                    ->first();

                if (! $coupon || ! $coupon->is_active) {
                    throw ValidationException::withMessages([
                        'coupon_code' => ['The provided coupon code is invalid or inactive.'],
                    ]);
                }

                // Check Expiration
                if ($coupon->expires_at && $coupon->expires_at->isPast()) {
                    throw ValidationException::withMessages([
                        'coupon_code' => ['This coupon has expired.'],
                    ]);
                }

                // Double Check: Max usage limit under lock
                if ($coupon->max_uses !== null && $coupon->used_count >= $coupon->max_uses) {
                    throw ValidationException::withMessages([
                        'coupon_code' => ['This coupon has reached its maximum usage limit.'],
                    ]);
                }

                // Check Minimum Spend
                if ($coupon->min_spend !== null && $subtotal < (float) $coupon->min_spend) {
                    throw ValidationException::withMessages([
                        'coupon_code' => ["Order subtotal must be at least {$coupon->min_spend} to apply this coupon."],
                    ]);
                }

                $discountAmount = round(($subtotal * (float) $coupon->percentage) / 100, 2);
                $appliedCoupon  = $coupon;
            }

            // ── 4. Calculate Final Total ───────────────────────────────────────
            $total = round(max(0, $subtotal + $shippingCost - $discountAmount), 2);

            // ── 5. Create Order & Items ────────────────────────────────────────
            $order = Order::create([
                'order_number'     => Order::generateOrderNumber(),
                'user_id'          => $userId,
                'customer_name'    => trim($data['first_name'] . ' ' . $data['last_name']),
                'customer_email'   => $data['email'],
                'customer_phone'   => $data['phone'],
                'customer_address' => $data['address'],
                'customer_city'    => $data['city'],
                'customer_country' => $data['country'] ?? 'Egypt',
                'subtotal'         => $subtotal,
                'shipping_cost'    => $shippingCost,
                'discount_amount'  => $discountAmount,
                'coupon_code'      => $couponCode,
                'total'            => $total,
                'shipping_zone_id' => $shippingZoneId,
                'payment_method'   => $data['payment_method'] ?? 'cod',
                'payment_status'   => 'pending',
                'status'           => 'Pending',
                'notes'            => $data['notes'] ?? null,
            ]);

            foreach ($processedItems as $processedItem) {
                $order->items()->create($processedItem);
            }

            // ── 6. Atomic Increment of Coupon Usage ─────────────────────────────
            if ($appliedCoupon) {
                $appliedCoupon->increment('used_count');
            }

            return $order;
        });

        return response()->json([
            'success'      => true,
            'order_number' => $order->order_number,
            'order_id'     => $order->id,
            'total'        => $order->total,
        ], 201);
    }

    /**
     * GET /api/orders/{order}
     *
     * Protected endpoint to view specific order details.
     * Enforces OrderPolicy to prevent IDOR / horizontal privilege escalation:
     * - Admins can view any order.
     * - Authenticated users can view only their own orders.
     * - Other users receive 403 Forbidden.
     */
    public function show(Order $order): JsonResponse
    {
        Gate::authorize('view', $order);

        $order->load(['items', 'shippingZone']);

        return response()->json([
            'success' => true,
            'data'    => $order,
        ]);
    }
}
