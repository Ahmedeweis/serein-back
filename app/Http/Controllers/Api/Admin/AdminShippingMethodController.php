<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class AdminShippingMethodController extends Controller
{
    #[OA\Get(
        path: '/api/admin/shipping-methods',
        summary: 'List all shipping methods for admin management',
        tags: ['Admin Shipping & Payment'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'List of shipping methods')
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = ShippingMethod::query();

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('name_en', 'like', "%{$search}%")
                  ->orWhere('name_ar', 'like', "%{$search}%")
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
        path: '/api/admin/shipping-methods',
        summary: 'Create a new shipping method',
        tags: ['Admin Shipping & Payment'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 201, description: 'Shipping method created successfully')
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id'          => ['required', 'string', 'max:50', 'alpha_dash', 'unique:shipping_methods,id'],
            'name_en'     => ['required', 'string', 'max:255'],
            'name_ar'     => ['required', 'string', 'max:255'],
            'estimate_en' => ['required', 'string', 'max:255'],
            'estimate_ar' => ['required', 'string', 'max:255'],
            'cost'        => ['required', 'numeric', 'min:0'],
            'logo_type'   => ['nullable', 'string', 'max:50', Rule::in(['dhl', 'fedex', 'serein', 'default'])],
            'is_active'   => ['boolean'],
            'sort_order'  => ['integer', 'min:0'],
        ]);

        $validated['logo_type'] = $validated['logo_type'] ?? 'default';
        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        $method = ShippingMethod::create($validated);

        return response()->json([
            'message' => 'Shipping method created successfully',
            'data'    => $method,
        ], 201);
    }

    #[OA\Get(
        path: '/api/admin/shipping-methods/{id}',
        summary: 'Get shipping method details',
        tags: ['Admin Shipping & Payment'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Shipping method details')
        ]
    )]
    public function show(string $id): JsonResponse
    {
        $method = ShippingMethod::findOrFail($id);

        return response()->json([
            'data' => $method,
        ]);
    }

    #[OA\Put(
        path: '/api/admin/shipping-methods/{id}',
        summary: 'Update shipping method details',
        tags: ['Admin Shipping & Payment'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Shipping method updated successfully')
        ]
    )]
    public function update(Request $request, string $id): JsonResponse
    {
        $method = ShippingMethod::findOrFail($id);

        $validated = $request->validate([
            'name_en'     => ['sometimes', 'required', 'string', 'max:255'],
            'name_ar'     => ['sometimes', 'required', 'string', 'max:255'],
            'estimate_en' => ['sometimes', 'required', 'string', 'max:255'],
            'estimate_ar' => ['sometimes', 'required', 'string', 'max:255'],
            'cost'        => ['sometimes', 'required', 'numeric', 'min:0'],
            'logo_type'   => ['nullable', 'string', 'max:50', Rule::in(['dhl', 'fedex', 'serein', 'default'])],
            'is_active'   => ['boolean'],
            'sort_order'  => ['integer', 'min:0'],
        ]);

        $method->update($validated);

        return response()->json([
            'message' => 'Shipping method updated successfully',
            'data'    => $method,
        ]);
    }

    #[OA\Delete(
        path: '/api/admin/shipping-methods/{id}',
        summary: 'Delete shipping method',
        tags: ['Admin Shipping & Payment'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Shipping method deleted successfully')
        ]
    )]
    public function destroy(string $id): JsonResponse
    {
        $method = ShippingMethod::findOrFail($id);
        $method->delete();

        return response()->json([
            'message' => 'Shipping method deleted successfully',
        ]);
    }

    #[OA\Patch(
        path: '/api/admin/shipping-methods/{id}/toggle-active',
        summary: 'Toggle active status of shipping method',
        tags: ['Admin Shipping & Payment'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Active status toggled successfully')
        ]
    )]
    public function toggleActive(string $id): JsonResponse
    {
        $method = ShippingMethod::findOrFail($id);
        $method->is_active = !$method->is_active;
        $method->save();

        return response()->json([
            'message'   => 'Status updated successfully',
            'is_active' => $method->is_active,
            'data'      => $method,
        ]);
    }
}
