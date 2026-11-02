<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HeroSlide;
use Illuminate\Http\JsonResponse;

class HeroSlideController extends Controller
{
    /**
     * Public: active home page slides, in display order.
     */
    public function index(): JsonResponse
    {
        $slides = HeroSlide::where('is_active', true)
            ->ordered()
            ->get()
            ->map(fn (HeroSlide $s) => [
                'id'      => $s->id,
                'desktop' => $s->desktop_url,
                'mobile'  => $s->mobile_url,
                'alt'     => ['ar' => $s->alt_ar, 'en' => $s->alt_en],
                'link'    => $s->link,
            ]);

        return response()->json(['data' => $slides]);
    }
}
