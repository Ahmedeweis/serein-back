<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShippingMethod;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class ShippingMethodController extends Controller
{
    #[OA\Get(
        path: '/api/shipping-methods',
        summary: 'Get list of active shipping methods for checkout',
        tags: ['Shipping & Payment'],
        responses: [
            new OA\Response(response: 200, description: 'Active shipping methods list')
        ]
    )]
    public function index(): JsonResponse
    {
        $methods = ShippingMethod::where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->map(function (ShippingMethod $m) {
                return [
                    'id' => $m->id,
                    'name' => [
                        'en' => $m->name_en,
                        'ar' => $m->name_ar,
                    ],
                    'name_en' => $m->name_en,
                    'name_ar' => $m->name_ar,
                    'estimate' => [
                        'en' => $m->estimate_en,
                        'ar' => $m->estimate_ar,
                    ],
                    'estimate_en' => $m->estimate_en,
                    'estimate_ar' => $m->estimate_ar,
                    'cost' => (float) $m->cost,
                    'costLabel' => [
                        'en' => (float) $m->cost === 0.0 ? 'Free Shipping' : "{$m->cost} EGP",
                        'ar' => (float) $m->cost === 0.0 ? 'شحن مجاني' : "{$m->cost} جنيه",
                    ],
                    'logo_type' => $m->logo_type,
                    'logoType' => $m->logo_type,
                    'is_active' => (bool) $m->is_active,
                    'sort_order' => (int) $m->sort_order,
                ];
            });

        return response()->json([
            'data' => $methods,
        ]);
    }
}
