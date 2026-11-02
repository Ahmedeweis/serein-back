<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Governorate;
use App\Models\ShippingZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class AdminShippingZoneController extends Controller
{
    #[OA\Get(
        path: '/api/admin/shipping-zones',
        summary: 'List all shipping zones with assigned governorates',
        tags: ['Admin Shipping & Payment'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'List of shipping zones')
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = ShippingZone::with(['governorates' => function ($q) {
            $q->select('id', 'shipping_zone_id', 'name_en', 'name_ar')
              ->orderBy('name_en');
        }])->withCount('governorates');

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('name_en', 'like', "%{$search}%")
                  ->orWhere('name_ar', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active') && $request->is_active !== '') {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $zones = $query->orderBy('price', 'asc')->get();

        return response()->json([
            'data' => $zones,
        ]);
    }

    #[OA\Get(
        path: '/api/admin/shipping-zones/available-governorates',
        summary: 'List unassigned governorates that can be added to shipping zones',
        tags: ['Admin Shipping & Payment'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'List of available governorates')
        ]
    )]
    public function availableGovernorates(Request $request): JsonResponse
    {
        $excludeZoneId = $request->query('exclude_zone_id');

        $query = Governorate::where('is_active', true)
            ->where(function ($q) use ($excludeZoneId) {
                $q->whereNull('shipping_zone_id');
                if (!empty($excludeZoneId)) {
                    $q->orWhere('shipping_zone_id', $excludeZoneId);
                }
            });

        $governorates = $query->orderBy('name_en')
            ->get(['id', 'name_en', 'name_ar', 'shipping_zone_id']);

        return response()->json([
            'data' => $governorates,
        ]);
    }

    #[OA\Post(
        path: '/api/admin/shipping-zones',
        summary: 'Create a new shipping zone and assign governorates',
        tags: ['Admin Shipping & Payment'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 201, description: 'Shipping zone created successfully')
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name_en'          => ['required', 'string', 'max:255'],
            'name_ar'          => ['required', 'string', 'max:255'],
            'price'            => ['required', 'numeric', 'min:0'],
            'min_days'         => ['required', 'integer', 'min:1'],
            'max_days'         => ['required', 'integer', 'gte:min_days'],
            'is_active'        => ['boolean'],
            'governorate_ids'  => ['nullable', 'array'],
            'governorate_ids.*'=> ['integer', 'exists:governorates,id'],
        ]);

        $zone = DB::transaction(function () use ($validated) {
            $zone = ShippingZone::create([
                'name_en'      => $validated['name_en'],
                'name_ar'      => $validated['name_ar'],
                'price'        => $validated['price'],
                'min_days'     => $validated['min_days'],
                'max_days'     => $validated['max_days'],
                'is_active'    => $validated['is_active'] ?? true,
                'is_protected' => false,
            ]);

            if (!empty($validated['governorate_ids'])) {
                Governorate::whereIn('id', $validated['governorate_ids'])
                    ->update(['shipping_zone_id' => $zone->id]);
            }

            return $zone;
        });

        $zone->load(['governorates' => function ($q) {
            $q->select('id', 'shipping_zone_id', 'name_en', 'name_ar')->orderBy('name_en');
        }]);
        $zone->loadCount('governorates');

        return response()->json([
            'message' => 'Shipping zone created successfully',
            'data'    => $zone,
        ], 201);
    }

    #[OA\Get(
        path: '/api/admin/shipping-zones/{id}',
        summary: 'Get shipping zone details with assigned governorates',
        tags: ['Admin Shipping & Payment'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Shipping zone details')
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $zone = ShippingZone::with(['governorates' => function ($q) {
            $q->select('id', 'shipping_zone_id', 'name_en', 'name_ar')->orderBy('name_en');
        }])->withCount('governorates')->findOrFail($id);

        return response()->json([
            'data' => $zone,
        ]);
    }

    #[OA\Put(
        path: '/api/admin/shipping-zones/{id}',
        summary: 'Update shipping zone details and sync governorates',
        tags: ['Admin Shipping & Payment'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Shipping zone updated successfully')
        ]
    )]
    public function update(Request $request, int $id): JsonResponse
    {
        $zone = ShippingZone::findOrFail($id);

        $validated = $request->validate([
            'name_en'          => ['sometimes', 'required', 'string', 'max:255'],
            'name_ar'          => ['sometimes', 'required', 'string', 'max:255'],
            'price'            => ['sometimes', 'required', 'numeric', 'min:0'],
            'min_days'         => ['sometimes', 'required', 'integer', 'min:1'],
            'max_days'         => ['sometimes', 'required', 'integer', 'gte:min_days'],
            'is_active'        => ['boolean'],
            'governorate_ids'  => ['nullable', 'array'],
            'governorate_ids.*'=> ['integer', 'exists:governorates,id'],
        ]);

        DB::transaction(function () use ($zone, $validated, $request) {
            $zone->update($validated);

            if ($request->has('governorate_ids')) {
                // Detach previous governorates for this zone
                Governorate::where('shipping_zone_id', $zone->id)
                    ->update(['shipping_zone_id' => null]);

                // Attach selected governorates
                if (!empty($validated['governorate_ids'])) {
                    Governorate::whereIn('id', $validated['governorate_ids'])
                        ->update(['shipping_zone_id' => $zone->id]);
                }
            }
        });

        $zone->load(['governorates' => function ($q) {
            $q->select('id', 'shipping_zone_id', 'name_en', 'name_ar')->orderBy('name_en');
        }]);
        $zone->loadCount('governorates');

        return response()->json([
            'message' => 'Shipping zone updated successfully',
            'data'    => $zone,
        ]);
    }

    #[OA\Delete(
        path: '/api/admin/shipping-zones/{id}',
        summary: 'Delete a shipping zone (protected zones cannot be deleted)',
        tags: ['Admin Shipping & Payment'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Shipping zone deleted successfully'),
            new OA\Response(response: 403, description: 'Cannot delete protected shipping zone')
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        $zone = ShippingZone::findOrFail($id);

        if ($zone->is_protected) {
            return response()->json([
                'message' => 'This shipping zone is protected and cannot be deleted.',
            ], 403);
        }

        DB::transaction(function () use ($zone) {
            // Unassign governorates so they return to unassigned pool
            Governorate::where('shipping_zone_id', $zone->id)
                ->update(['shipping_zone_id' => null]);

            $zone->delete();
        });

        return response()->json([
            'message' => 'Shipping zone deleted successfully. Its governorates are now unassigned.',
        ]);
    }

    #[OA\Patch(
        path: '/api/admin/shipping-zones/{id}/toggle-active',
        summary: 'Toggle active status of shipping zone',
        tags: ['Admin Shipping & Payment'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Active status toggled successfully')
        ]
    )]
    public function toggleActive(int $id): JsonResponse
    {
        $zone = ShippingZone::findOrFail($id);
        $zone->is_active = !$zone->is_active;
        $zone->save();

        return response()->json([
            'message'   => 'Status updated successfully',
            'is_active' => $zone->is_active,
            'data'      => $zone,
        ]);
    }
}
