<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Support\SiteBranding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSiteSettingsController extends Controller
{
    // ── Show ──────────────────────────────────────────────────────────────────

    public function show(): JsonResponse
    {
        return response()->json([
            'data' => [
                'announcement_bar' => SiteBranding::announcement(),
                'favicon_url'      => SiteBranding::faviconUrl(),
                'features'         => SiteBranding::features(),
            ],
        ]);
    }

    // ── "Why choose Serein" feature cards (exactly 4) ────────────────────────

    public function updateFeatures(Request $request): JsonResponse
    {
        $validated = $request->validate(SiteBranding::featureRules(), [
            'features.size' => 'There must be exactly ' . SiteBranding::FEATURES_COUNT . ' feature cards.',
        ]);

        return response()->json([
            'message' => 'Features saved successfully.',
            'data'    => SiteBranding::saveFeatures($validated),
        ]);
    }

    public function uploadFeatureIcon(Request $request, int $index): JsonResponse
    {
        abort_unless($index >= 0 && $index < SiteBranding::FEATURES_COUNT, 404);
        $request->validate([
            'icon' => ['required', 'file', 'image', 'mimes:png,jpg,jpeg,webp', 'max:512'],
        ]);

        return response()->json(['data' => SiteBranding::setFeatureIcon($index, $request->file('icon'))]);
    }

    public function removeFeatureIcon(int $index): JsonResponse
    {
        abort_unless($index >= 0 && $index < SiteBranding::FEATURES_COUNT, 404);

        return response()->json(['data' => SiteBranding::setFeatureIcon($index, null)]);
    }

    // ── Announcement bar ──────────────────────────────────────────────────────

    public function updateAnnouncement(Request $request): JsonResponse
    {
        $validated = $request->validate(SiteBranding::announcementRules(), [
            'messages.*.link.regex' => 'The link must start with / (e.g. /shop) or https://',
        ]);

        return response()->json([
            'message' => 'Announcement bar saved successfully.',
            'data'    => SiteBranding::saveAnnouncement($validated),
        ]);
    }

    public function uploadAnnouncementLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => ['required', 'file', 'image', 'mimes:png,jpg,jpeg,webp', 'max:512'],
        ]);

        return response()->json(['data' => SiteBranding::setAnnouncementLogo($request->file('logo'))]);
    }

    public function removeAnnouncementLogo(): JsonResponse
    {
        return response()->json(['data' => SiteBranding::setAnnouncementLogo(null)]);
    }

    // ── Favicon ───────────────────────────────────────────────────────────────

    public function uploadFavicon(Request $request): JsonResponse
    {
        $request->validate([
            'favicon' => ['required', 'file', 'mimes:png,ico', 'max:512'],
        ]);

        return response()->json(['data' => ['favicon_url' => SiteBranding::setFavicon($request->file('favicon'))]]);
    }

    public function removeFavicon(): JsonResponse
    {
        return response()->json(['data' => ['favicon_url' => SiteBranding::setFavicon(null)]]);
    }
}
