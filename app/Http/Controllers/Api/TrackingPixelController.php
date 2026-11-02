<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\TrackingPixels;
use Illuminate\Http\JsonResponse;

class TrackingPixelController extends Controller
{
    /**
     * Public: enabled tracking pixels for the storefront.
     */
    public function index(): JsonResponse
    {
        return response()->json(['data' => (object) TrackingPixels::active()]);
    }
}
