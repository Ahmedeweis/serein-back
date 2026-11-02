<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\SiteBranding;
use Illuminate\Http\JsonResponse;

class SiteSettingsController extends Controller
{
    /**
     * Public: storefront branding (announcement bar, favicon).
     */
    public function index(): JsonResponse
    {
        return response()->json(['data' => SiteBranding::publicSettings()]);
    }
}
