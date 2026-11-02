<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Support\TrackingPixels;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminTrackingPixelController extends Controller
{
    // ── Show ──────────────────────────────────────────────────────────────────

    public function show(): JsonResponse
    {
        return response()->json(['data' => TrackingPixels::all()]);
    }

    // ── Update (replaces the whole pixel list) ────────────────────────────────

    public function update(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), TrackingPixels::rules());
        $validator->after(fn ($v) => TrackingPixels::validateIds($v));
        $validated = $validator->validate();

        return response()->json([
            'message' => 'Tracking pixels saved successfully.',
            'data'    => TrackingPixels::save($validated),
        ]);
    }
}
