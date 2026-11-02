<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Governorate;
use App\Models\ShippingZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ShippingZoneController extends Controller
{
    #[OA\Get(
        path: '/api/governorates',
        summary: 'Get all governorates with shipping zone information',
        tags: ['Locations'],
        responses: [
            new OA\Response(response: 200, description: 'List of governorates')
        ]
    )]
    public function getGovernorates(Request $request): JsonResponse
    {
        $lang = $request->query('lang', $request->header('Accept-Language', 'ar'));
        $lang = in_array($lang, ['ar', 'en']) ? $lang : 'ar';

        $governorates = Governorate::with(['shippingZone' => function ($q) {
            $q->where('is_active', true);
        }])
        ->where('is_active', true)
        ->orderBy($lang === 'en' ? 'name_en' : 'name_ar')
        ->get()
        ->map(function (Governorate $g) use ($lang) {
            return [
                'id'               => $g->id,
                'country_id'       => $g->country_id,
                'name'             => $lang === 'en' ? $g->name_en : $g->name_ar,
                'name_en'          => $g->name_en,
                'name_ar'          => $g->name_ar,
                'shipping_zone_id' => $g->shipping_zone_id,
                'shipping_zone'    => $g->shippingZone ? [
                    'id'       => $g->shippingZone->id,
                    'name'     => $lang === 'en' ? $g->shippingZone->name_en : $g->shippingZone->name_ar,
                    'name_en'  => $g->shippingZone->name_en,
                    'name_ar'  => $g->shippingZone->name_ar,
                    'price'    => (float) $g->shippingZone->price,
                    'min_days' => $g->shippingZone->min_days,
                    'max_days' => $g->shippingZone->max_days,
                ] : null,
            ];
        });

        return response()->json([
            'data' => $governorates,
        ]);
    }

    #[OA\Post(
        path: '/api/checkout/calculate-shipping',
        summary: 'Calculate shipping rate and timeframe based on selected governorate',
        tags: ['Shipping & Payment'],
        responses: [
            new OA\Response(response: 200, description: 'Calculated shipping details')
        ]
    )]
    public function calculateShipping(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'governorate_id'   => ['nullable', 'integer', 'exists:governorates,id'],
            'governorate_name' => ['nullable', 'string', 'max:255'],
            'city'             => ['nullable', 'string', 'max:255'],
        ]);

        $governorate = null;

        if (!empty($validated['governorate_id'])) {
            $governorate = Governorate::with('shippingZone')->find($validated['governorate_id']);
        } elseif (!empty($validated['governorate_name']) || !empty($validated['city'])) {
            $search = $validated['governorate_name'] ?? $validated['city'];
            $governorate = Governorate::with('shippingZone')
                ->where(function ($q) use ($search) {
                    $q->where('name_en', $search)
                      ->orWhere('name_ar', $search)
                      ->orWhere('name_en', 'like', "%{$search}%")
                      ->orWhere('name_ar', 'like', "%{$search}%");
                })
                ->first();
        }

        $zone = null;
        if ($governorate && $governorate->shippingZone && $governorate->shippingZone->is_active) {
            $zone = $governorate->shippingZone;
        } else {
            // Default fallback to first active zone or protected zone
            $zone = ShippingZone::where('is_protected', true)->where('is_active', true)->first()
                ?? ShippingZone::where('is_active', true)->first();
        }

        $price = $zone ? (float) $zone->price : 97.00;
        $minDays = $zone ? (int) $zone->min_days : 1;
        $maxDays = $zone ? (int) $zone->max_days : 3;

        return response()->json([
            'success'       => true,
            'governorate'   => $governorate ? [
                'id'      => $governorate->id,
                'name_en' => $governorate->name_en,
                'name_ar' => $governorate->name_ar,
            ] : null,
            'zone'          => $zone ? [
                'id'       => $zone->id,
                'name_en'  => $zone->name_en,
                'name_ar'  => $zone->name_ar,
                'price'    => (float) $zone->price,
                'min_days' => $zone->min_days,
                'max_days' => $zone->max_days,
            ] : null,
            'shipping_cost' => $price,
            'min_days'      => $minDays,
            'max_days'      => $maxDays,
            'estimate'      => [
                'en' => "Estimated Delivery Time: {$minDays} - {$maxDays} Days",
                'ar' => "توصيل مباشر: خلال {$minDays} - {$maxDays} يوم",
            ],
            'method_id'     => 'serein',
            'method_title'  => [
                'en' => 'Serein Private Courier',
                'ar' => 'مندوب سيرين الخاص',
            ],
        ]);
    }
}
