<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCouponRequest;
use App\Http\Requests\Admin\UpdateCouponRequest;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class AdminCouponController extends Controller
{
    #[OA\Get(
        path: '/api/admin/coupons',
        summary: 'List all coupons for admin',
        tags: ['Admin Coupons'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'List of coupons')
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = Coupon::query();

        if ($request->filled('search')) {
            $search = strtoupper(trim($request->query('search')));
            $query->where('code', 'like', "%{$search}%");
        }

        if ($request->has('is_active') && $request->is_active !== '') {
            $isActive = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isActive !== null) {
                $query->where('is_active', $isActive);
            }
        }

        $coupons = $query->orderByDesc('id')->get();

        $stats = [
            'total'     => Coupon::count(),
            'active'    => Coupon::where('is_active', true)->count(),
            'inactive'  => Coupon::where('is_active', false)->count(),
            'exhausted' => Coupon::whereNotNull('max_uses')->whereColumn('used_count', '>=', 'max_uses')->count(),
        ];

        return response()->json([
            'data'  => $coupons,
            'stats' => $stats,
        ]);
    }

    #[OA\Post(
        path: '/api/admin/coupons',
        summary: 'Create a new coupon',
        tags: ['Admin Coupons'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 201, description: 'Coupon created successfully')
        ]
    )]
    public function store(StoreCouponRequest $request): JsonResponse
    {
        $validated = $request->validated();
        unset($validated['used_count']); // Immutability: used_count is never set from client requests
        $validated['code'] = strtoupper(trim($validated['code']));
        $validated['is_active'] = $validated['is_active'] ?? true;

        $coupon = Coupon::create($validated);

        return response()->json([
            'message' => 'Coupon created successfully.',
            'data'    => $coupon,
        ], 201);
    }

    #[OA\Get(
        path: '/api/admin/coupons/{id}',
        summary: 'Get coupon details',
        tags: ['Admin Coupons'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Coupon details')
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $coupon = Coupon::findOrFail($id);

        return response()->json([
            'data' => $coupon,
        ]);
    }

    #[OA\Put(
        path: '/api/admin/coupons/{id}',
        summary: 'Update coupon details',
        tags: ['Admin Coupons'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Coupon updated successfully')
        ]
    )]
    public function update(UpdateCouponRequest $request, int $id): JsonResponse
    {
        $coupon = Coupon::findOrFail($id);
        $validated = $request->validated();
        unset($validated['used_count']); // Immutability: used_count cannot be altered via update

        if (isset($validated['code'])) {
            $validated['code'] = strtoupper(trim($validated['code']));
        }

        $coupon->update($validated);

        return response()->json([
            'message' => 'Coupon updated successfully.',
            'data'    => $coupon,
        ]);
    }

    #[OA\Delete(
        path: '/api/admin/coupons/{id}',
        summary: 'Delete coupon',
        tags: ['Admin Coupons'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Coupon deleted successfully')
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->delete();

        return response()->json([
            'message' => 'Coupon deleted successfully.',
        ]);
    }

    #[OA\Patch(
        path: '/api/admin/coupons/{id}/toggle-active',
        summary: 'Toggle active status of coupon',
        tags: ['Admin Coupons'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Status toggled successfully')
        ]
    )]
    public function toggleActive(int $id): JsonResponse
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->is_active = !$coupon->is_active;
        $coupon->save();

        return response()->json([
            'message'   => 'Coupon status updated successfully.',
            'is_active' => $coupon->is_active,
            'data'      => $coupon,
        ]);
    }
}
