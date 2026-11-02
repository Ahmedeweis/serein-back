<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class PaymentMethodController extends Controller
{
    #[OA\Get(
        path: '/api/payment-methods',
        summary: 'Get list of active payment methods for checkout',
        tags: ['Shipping & Payment'],
        responses: [
            new OA\Response(response: 200, description: 'Active payment methods list')
        ]
    )]
    public function index(): JsonResponse
    {
        $methods = PaymentMethod::where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->map(function (PaymentMethod $m) {
                return [
                    'id' => $m->id,
                    'title' => [
                        'en' => $m->title_en,
                        'ar' => $m->title_ar,
                    ],
                    'title_en' => $m->title_en,
                    'title_ar' => $m->title_ar,
                    'description' => [
                        'en' => $m->description_en ?? '',
                        'ar' => $m->description_ar ?? '',
                    ],
                    'description_en' => $m->description_en,
                    'description_ar' => $m->description_ar,
                    'badge' => $m->badge,
                    'instructions' => [
                        'en' => $m->instructions_en ?? '',
                        'ar' => $m->instructions_ar ?? '',
                    ],
                    'instructions_en' => $m->instructions_en,
                    'instructions_ar' => $m->instructions_ar,
                    'is_active' => (bool) $m->is_active,
                    'sort_order' => (int) $m->sort_order,
                ];
            });

        return response()->json([
            'data' => $methods,
        ]);
    }
}
