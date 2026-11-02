<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Governorate;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\ShippingZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminOrderController extends Controller
{
    // ── Index ─────────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $query = Order::with(['items', 'shippingZone'])
            ->orderByDesc('created_at');

        // Filter by status
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        // Filter by payment_status
        if ($paymentStatus = $request->query('payment_status')) {
            $query->where('payment_status', $paymentStatus);
        }

        // Search by order_number, customer_name, customer_email, customer_phone
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_email', 'like', "%{$search}%")
                  ->orWhere('customer_phone', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->query('per_page', 20);
        $orders  = $query->paginate($perPage);

        return response()->json([
            'data'         => $orders->items(),
            'current_page' => $orders->currentPage(),
            'last_page'    => $orders->lastPage(),
            'total'        => $orders->total(),
        ]);
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function show(int $id): JsonResponse
    {
        $order = Order::with(['items', 'shippingZone'])->findOrFail($id);

        return response()->json(['data' => $order]);
    }

    // ── Store (Manual Order Creation) ─────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_name'    => ['required', 'string', 'max:255'],
            'customer_email'   => ['required', 'email', 'max:255'],
            'customer_phone'   => ['nullable', 'string', 'max:30'],
            'customer_address' => ['nullable', 'string', 'max:500'],
            'customer_city'    => ['nullable', 'string', 'max:100'],
            'customer_country' => ['nullable', 'string', 'max:100'],
            'payment_method'   => ['required', 'string', 'max:100'],
            'payment_status'   => ['required', Rule::in(['pending', 'paid', 'failed'])],
            'status'           => ['required', Rule::in(['Pending', 'Confirmed', 'In Preparation', 'Out for Delivery', 'Delivered', 'Cancelled'])],
            'notes'            => ['nullable', 'string'],
            'governorate_id'   => ['nullable', 'integer', 'exists:governorates,id'],
            'items'            => ['required', 'array', 'min:1'],
            'items.*.product_name_en' => ['required', 'string'],
            'items.*.product_name_ar' => ['nullable', 'string'],
            'items.*.product_image'   => ['nullable', 'string'],
            'items.*.variant_label'   => ['nullable', 'string'],
            'items.*.price'           => ['required', 'numeric', 'min:0'],
            'items.*.quantity'        => ['required', 'integer', 'min:1'],
            'items.*.product_id'      => ['nullable', 'integer'],
            'items.*.variant_id'      => ['nullable', 'integer'],
        ]);

        // Resolve shipping from governorate zone
        $shippingCost   = 0;
        $shippingZoneId = null;

        if (!empty($validated['governorate_id'])) {
            $gov = Governorate::with('shippingZone')->find($validated['governorate_id']);
            if ($gov && $gov->shippingZone && $gov->shippingZone->is_active) {
                $shippingCost   = (float) $gov->shippingZone->price;
                $shippingZoneId = $gov->shippingZone->id;
            }
        } elseif (!empty($validated['customer_city'])) {
            $gov = Governorate::with('shippingZone')
                ->where('name_en', $validated['customer_city'])
                ->orWhere('name_ar', $validated['customer_city'])
                ->first();
            if ($gov && $gov->shippingZone && $gov->shippingZone->is_active) {
                $shippingCost   = (float) $gov->shippingZone->price;
                $shippingZoneId = $gov->shippingZone->id;
            }
        }

        // Compute subtotal and total
        $subtotal = collect($validated['items'])->sum(
            fn ($i) => (float) $i['price'] * (int) $i['quantity']
        );
        $total = round($subtotal + $shippingCost, 2);

        $order = DB::transaction(function () use ($validated, $subtotal, $shippingCost, $shippingZoneId, $total) {
            $order = Order::create([
                'order_number'     => Order::generateOrderNumber(),
                'customer_name'    => $validated['customer_name'],
                'customer_email'   => $validated['customer_email'],
                'customer_phone'   => $validated['customer_phone'] ?? null,
                'customer_address' => $validated['customer_address'] ?? null,
                'customer_city'    => $validated['customer_city'] ?? null,
                'customer_country' => $validated['customer_country'] ?? 'Egypt',
                'subtotal'         => $subtotal,
                'shipping_cost'    => $shippingCost,
                'discount_amount'  => 0,
                'total'            => $total,
                'shipping_zone_id' => $shippingZoneId,
                'payment_method'   => $validated['payment_method'],
                'payment_status'   => $validated['payment_status'],
                'status'           => $validated['status'],
                'notes'            => $validated['notes'] ?? null,
            ]);

            foreach ($validated['items'] as $item) {
                $order->items()->create($item);

                // Decrement variant stock when a variant_id is supplied
                if (!empty($item['variant_id'])) {
                    ProductVariant::where('id', $item['variant_id'])
                        ->where('stock', '>', 0)
                        ->decrement('stock', (int) $item['quantity']);
                }
            }

            return $order;
        });

        return response()->json(['data' => $order->load('items', 'shippingZone')], 201);
    }

    // ── Update (Edit Existing Manual Order) ───────────────────────────────────

    public function update(Request $request, int $id): JsonResponse
    {
        $order = Order::with('items')->findOrFail($id);

        $validated = $request->validate([
            'customer_name'    => ['required', 'string', 'max:255'],
            'customer_email'   => ['required', 'email', 'max:255'],
            'customer_phone'   => ['nullable', 'string', 'max:30'],
            'customer_address' => ['nullable', 'string', 'max:500'],
            'customer_city'    => ['nullable', 'string', 'max:100'],
            'customer_country' => ['nullable', 'string', 'max:100'],
            'payment_method'   => ['nullable', 'string', 'max:100'],
            'payment_status'   => ['nullable', Rule::in(['pending', 'paid', 'failed'])],
            'status'           => ['nullable', Rule::in(['Pending', 'Confirmed', 'In Preparation', 'Out for Delivery', 'Delivered', 'Cancelled'])],
            'notes'            => ['nullable', 'string'],
            'governorate_id'   => ['nullable', 'integer', 'exists:governorates,id'],
            'shipping_cost'    => ['nullable', 'numeric', 'min:0'],
            'items'            => ['required', 'array', 'min:1'],
            'items.*.product_name_en' => ['required', 'string'],
            'items.*.product_name_ar' => ['nullable', 'string'],
            'items.*.product_image'   => ['nullable', 'string'],
            'items.*.variant_label'   => ['nullable', 'string'],
            'items.*.price'           => ['required', 'numeric', 'min:0'],
            'items.*.quantity'        => ['required', 'integer', 'min:1'],
            'items.*.product_id'      => ['nullable', 'integer'],
            'items.*.variant_id'      => ['nullable', 'integer'],
        ]);

        // Resolve shipping from governorate zone or payload or keep existing
        $shippingCost   = isset($validated['shipping_cost']) ? (float) $validated['shipping_cost'] : (float) $order->shipping_cost;
        $shippingZoneId = $order->shipping_zone_id;

        if (!empty($validated['governorate_id'])) {
            $gov = Governorate::with('shippingZone')->find($validated['governorate_id']);
            if ($gov && $gov->shippingZone && $gov->shippingZone->is_active) {
                $shippingCost   = isset($validated['shipping_cost']) ? (float) $validated['shipping_cost'] : (float) $gov->shippingZone->price;
                $shippingZoneId = $gov->shippingZone->id;
            }
        } elseif (!empty($validated['customer_city'])) {
            $gov = Governorate::with('shippingZone')
                ->where('name_en', $validated['customer_city'])
                ->orWhere('name_ar', $validated['customer_city'])
                ->first();
            if ($gov && $gov->shippingZone && $gov->shippingZone->is_active) {
                $shippingCost   = isset($validated['shipping_cost']) ? (float) $validated['shipping_cost'] : (float) $gov->shippingZone->price;
                $shippingZoneId = $gov->shippingZone->id;
            }
        }

        // Compute subtotal and total
        $subtotal = collect($validated['items'])->sum(
            fn ($i) => (float) $i['price'] * (int) $i['quantity']
        );
        $discountAmount = (float) ($order->discount_amount ?? 0);
        $total = round($subtotal + $shippingCost - $discountAmount, 2);

        $order = DB::transaction(function () use ($order, $validated, $subtotal, $shippingCost, $shippingZoneId, $total) {
            // 1. Restore previous stock for old items
            foreach ($order->items as $oldItem) {
                if (!empty($oldItem->variant_id)) {
                    ProductVariant::where('id', $oldItem->variant_id)
                        ->increment('stock', (int) $oldItem->quantity);
                }
            }

            // 2. Validate and deduct stock for new items using lockForUpdate()
            $variantQuantities = [];
            foreach ($validated['items'] as $newItem) {
                if (!empty($newItem['variant_id'])) {
                    $vId = (int) $newItem['variant_id'];
                    $variantQuantities[$vId] = ($variantQuantities[$vId] ?? 0) + (int) $newItem['quantity'];
                }
            }

            foreach ($variantQuantities as $variantId => $requiredQty) {
                $variant = ProductVariant::lockForUpdate()->find($variantId);
                if ($variant) {
                    if ($variant->stock < $requiredQty) {
                        throw ValidationException::withMessages([
                            'items' => "Insufficient stock for {$variant->name_en}. Only {$variant->stock} remaining in stock.",
                        ]);
                    }
                    $variant->decrement('stock', $requiredQty);
                }
            }

            // 3. Sync order_items: remove old items, create new items
            $order->items()->delete();
            foreach ($validated['items'] as $item) {
                $order->items()->create($item);
            }

            // 4. Update order details (Order ID and order_number remain immutable)
            $order->update([
                'customer_name'    => $validated['customer_name'],
                'customer_email'   => $validated['customer_email'],
                'customer_phone'   => $validated['customer_phone'] ?? $order->customer_phone,
                'customer_address' => $validated['customer_address'] ?? $order->customer_address,
                'customer_city'    => $validated['customer_city'] ?? $order->customer_city,
                'customer_country' => $validated['customer_country'] ?? ($order->customer_country ?? 'Egypt'),
                'subtotal'         => $subtotal,
                'shipping_cost'    => $shippingCost,
                'total'            => $total,
                'shipping_zone_id' => $shippingZoneId,
                'payment_method'   => $validated['payment_method'] ?? $order->payment_method,
                'payment_status'   => $validated['payment_status'] ?? $order->payment_status,
                'status'           => $validated['status'] ?? $order->status,
                'notes'            => array_key_exists('notes', $validated) ? $validated['notes'] : $order->notes,
            ]);

            return $order;
        });

        return response()->json(['data' => $order->load('items', 'shippingZone')]);
    }

    // ── Update Status ─────────────────────────────────────────────────────────

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $order = Order::findOrFail($id);

        $validated = $request->validate([
            'status'         => ['required', Rule::in(['Pending', 'Confirmed', 'In Preparation', 'Out for Delivery', 'Delivered', 'Cancelled'])],
            'payment_status' => ['nullable', Rule::in(['pending', 'paid', 'failed'])],
            'notes'          => ['nullable', 'string'],
        ]);

        $order->status = $validated['status'];

        // Auto-update payment status when marking delivered for COD orders
        if ($validated['status'] === 'Delivered' && $order->payment_status === 'pending') {
            $order->payment_status = 'paid';
        }

        if (isset($validated['payment_status'])) {
            $order->payment_status = $validated['payment_status'];
        }

        if (isset($validated['notes'])) {
            $order->notes = $validated['notes'];
        }

        $order->save();

        return response()->json(['data' => $order->load('items', 'shippingZone')]);
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function destroy(int $id): JsonResponse
    {
        $order = Order::findOrFail($id);
        $order->delete();

        return response()->json(['message' => 'Order deleted successfully.']);
    }

    // ── Stats ─────────────────────────────────────────────────────────────────

    public function stats(): JsonResponse
    {
        $total     = Order::count();
        $pending   = Order::where('status', 'Pending')->count();
        $delivered = Order::where('status', 'Delivered')->count();
        $cancelled = Order::where('status', 'Cancelled')->count();
        $revenue   = Order::whereNotIn('status', ['Cancelled'])->sum('total');

        return response()->json([
            'total'     => $total,
            'pending'   => $pending,
            'delivered' => $delivered,
            'cancelled' => $cancelled,
            'revenue'   => (float) $revenue,
        ]);
    }
}
