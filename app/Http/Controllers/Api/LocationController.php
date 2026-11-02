<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Governorate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use OpenApi\Attributes as OA;

class LocationController extends Controller
{
    #[OA\Get(
        path: '/api/locations/countries',
        summary: 'Get list of active countries',
        tags: ['Locations'],
        parameters: [
            new OA\Parameter(
                name: 'lang',
                in: 'query',
                description: 'Language code (ar or en)',
                required: false,
                schema: new OA\Schema(type: 'string', default: 'ar', enum: ['ar', 'en'])
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Countries list returned successfully'),
            new OA\Response(response: 422, description: 'Validation error (invalid lang)')
        ]
    )]
    public function getCountries(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lang' => ['nullable', 'string', 'in:ar,en'],
        ]);

        $lang = $validated['lang'] ?? $request->header('Accept-Language', 'ar');
        $lang = in_array(strtolower($lang), ['ar', 'en']) ? strtolower($lang) : 'ar';

        // Fix: Use 24h TTL (86400s) instead of rememberForever to allow updates without manual cache clearing
        $data = Cache::remember("countries_{$lang}", 86400, function () use ($lang) {
            return Country::where('is_active', true)
                ->get()
                ->map(fn($c) => [
                    'id'         => $c->id,
                    'code'       => $c->code,
                    'name'       => $lang === 'en' ? $c->name_en : $c->name_ar,
                    'phone_code' => $c->phone_code,
                ])
                ->values()
                ->toArray();
        });

        return response()->json(['data' => $data]);
    }

    #[OA\Get(
        path: '/api/locations/governorates',
        summary: 'Get list of governorates by country ID',
        tags: ['Locations'],
        parameters: [
            new OA\Parameter(
                name: 'country_id',
                in: 'query',
                description: 'Country ID (Default: first active country or 1)',
                required: false,
                schema: new OA\Schema(type: 'integer', default: 1)
            ),
            new OA\Parameter(
                name: 'lang',
                in: 'query',
                description: 'Language code (ar or en)',
                required: false,
                schema: new OA\Schema(type: 'string', default: 'ar', enum: ['ar', 'en'])
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Governorates list returned successfully'),
            new OA\Response(response: 422, description: 'Validation error (invalid country_id or lang)')
        ]
    )]
    public function getGovernorates(Request $request): JsonResponse
    {
        // Fix: Zero-Trust validation on country_id & lang to prevent Cache Flooding / Poisoning
        $validated = $request->validate([
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'lang'       => ['nullable', 'string', 'in:ar,en'],
        ]);

        $lang = $validated['lang'] ?? $request->header('Accept-Language', 'ar');
        $lang = in_array(strtolower($lang), ['ar', 'en']) ? strtolower($lang) : 'ar';

        $countryId = isset($validated['country_id'])
            ? (int) $validated['country_id']
            : (Country::where('is_active', true)->value('id') ?? 1);

        $data = Cache::remember("govs_{$countryId}_{$lang}_v2", 86400, function () use ($countryId, $lang) {
            return Governorate::where('country_id', $countryId)
                ->where('is_active', true)
                ->orderBy($lang === 'en' ? 'name_en' : 'name_ar')
                ->get()
                ->map(fn($g) => [
                    'id'         => $g->id,
                    'country_id' => $g->country_id,
                    'name'       => $lang === 'en' ? $g->name_en : $g->name_ar,
                    'name_en'    => $g->name_en,
                    'name_ar'    => $g->name_ar,
                ])
                ->values()
                ->toArray();
        });

        return response()->json(['data' => $data]);
    }
}
