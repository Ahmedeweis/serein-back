<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class AdminPaymentMethodController extends Controller
{
    #[OA\Get(
        path: '/api/admin/payment-methods',
        summary: 'List all payment methods for admin management',
        tags: ['Admin Shipping & Payment'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'List of payment methods')
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = PaymentMethod::query();

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('title_en', 'like', "%{$search}%")
                  ->orWhere('title_ar', 'like', "%{$search}%")
                  ->orWhere('id', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active') && $request->is_active !== '') {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $methods = $query->orderBy('sort_order', 'asc')->orderBy('id', 'asc')->get();

        return response()->json([
            'data' => $methods,
        ]);
    }

    #[OA\Post(
        path: '/api/admin/payment-methods',
        summary: 'Create a new payment method',
        tags: ['Admin Shipping & Payment'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 201, description: 'Payment method created successfully')
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id'              => ['required', 'string', 'max:50', 'alpha_dash', 'unique:payment_methods,id'],
            'title_en'        => ['required', 'string', 'max:255'],
            'title_ar'        => ['required', 'string', 'max:255'],
            'description_en'  => ['nullable', 'string'],
            'description_ar'  => ['nullable', 'string'],
            'badge'           => ['nullable', 'string', 'max:50', Rule::in(['cards', 'instapay', 'cod', 'default'])],
            'instructions_en' => ['nullable', 'string'],
            'instructions_ar' => ['nullable', 'string'],
            'is_active'       => ['boolean'],
            'sort_order'      => ['integer', 'min:0'],
        ]);

        $validated['badge'] = $validated['badge'] ?? 'cards';
        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        $method = PaymentMethod::create($validated);

        return response()->json([
            'message' => 'Payment method created successfully',
            'data'    => $method,
        ], 201);
    }

    #[OA\Get(
        path: '/api/admin/payment-methods/{id}',
        summary: 'Get payment method details',
        tags: ['Admin Shipping & Payment'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Payment method details')
        ]
    )]
    public function show(string $id): JsonResponse
    {
        $method = PaymentMethod::findOrFail($id);

        return response()->json([
            'data' => $method,
        ]);
    }

    #[OA\Put(
        path: '/api/admin/payment-methods/{id}',
        summary: 'Update payment method details',
        tags: ['Admin Shipping & Payment'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Payment method updated successfully')
        ]
    )]
    public function update(Request $request, string $id): JsonResponse
    {
        $method = PaymentMethod::findOrFail($id);

        $validated = $request->validate([
            'title_en'        => ['sometimes', 'required', 'string', 'max:255'],
            'title_ar'        => ['sometimes', 'required', 'string', 'max:255'],
            'description_en'  => ['nullable', 'string'],
            'description_ar'  => ['nullable', 'string'],
            'badge'           => ['nullable', 'string', 'max:50', Rule::in(['cards', 'instapay', 'cod', 'default'])],
            'instructions_en' => ['nullable', 'string'],
            'instructions_ar' => ['nullable', 'string'],
            'is_active'       => ['boolean'],
            'sort_order'      => ['integer', 'min:0'],
        ]);

        $method->update($validated);

        return response()->json([
            'message' => 'Payment method updated successfully',
            'data'    => $method,
        ]);
    }

    #[OA\Delete(
        path: '/api/admin/payment-methods/{id}',
        summary: 'Delete payment method',
        tags: ['Admin Shipping & Payment'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Payment method deleted successfully')
        ]
    )]
    public function destroy(string $id): JsonResponse
    {
        $method = PaymentMethod::findOrFail($id);
        $method->delete();

        return response()->json([
            'message' => 'Payment method deleted successfully',
        ]);
    }

    #[OA\Patch(
        path: '/api/admin/payment-methods/{id}/toggle-active',
        summary: 'Toggle active status of payment method',
        tags: ['Admin Shipping & Payment'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Active status toggled successfully')
        ]
    )]
    public function toggleActive(string $id): JsonResponse
    {
        $method = PaymentMethod::findOrFail($id);
        $method->is_active = !$method->is_active;
        $method->save();

        return response()->json([
            'message'   => 'Status updated successfully',
            'is_active' => $method->is_active,
            'data'      => $method,
        ]);
    }
}
