<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Validation\Validator;

/**
 * Tracking pixels (Meta, Google, TikTok, Snapchat) — any number per platform.
 *
 * Stored as a flat list: [{platform, name, enabled, pixel_id, purchase_label}, …].
 * Every ID is validated against a strict per-platform pattern because the
 * storefront injects it into third-party script URLs.
 */
class TrackingPixels
{
    public const SETTING_KEY = 'tracking_pixels';

    public const MAX_PIXELS = 50;

    /** platform => pixel_id regex */
    public const ID_PATTERNS = [
        'meta'               => '/^\d{5,20}$/',
        'google_analytics'   => '/^G-[A-Z0-9]{4,20}$/',
        'google_tag_manager' => '/^GTM-[A-Z0-9]{4,12}$/',
        'google_ads'         => '/^AW-\d{5,15}$/',
        'tiktok'             => '/^[A-Za-z0-9]{8,40}$/',
        'snapchat'           => '/^[A-Fa-f0-9-]{36}$/',
    ];

    public const PURCHASE_LABEL_PATTERN = '/^[A-Za-z0-9_-]{4,64}$/';

    /** All configured pixels (admin view) */
    public static function all(): array
    {
        $stored = SiteSetting::getValue(self::SETTING_KEY, []);

        return array_values(array_map([self::class, 'normalize'], $stored['pixels'] ?? []));
    }

    /**
     * Enabled pixels grouped by platform (public storefront view), e.g.
     * ['meta' => ['123…', '456…'], 'google_ads' => [['conversion_id' => 'AW-…', 'purchase_label' => '…']]]
     */
    public static function active(): array
    {
        $active = [];
        foreach (self::all() as $pixel) {
            if (!$pixel['enabled']) {
                continue;
            }
            $active[$pixel['platform']][] = $pixel['platform'] === 'google_ads'
                ? ['conversion_id' => $pixel['pixel_id'], 'purchase_label' => $pixel['purchase_label']]
                : $pixel['pixel_id'];
        }

        return $active;
    }

    public static function rules(): array
    {
        return [
            'pixels'                  => ['present', 'array', 'max:' . self::MAX_PIXELS],
            'pixels.*.platform'       => ['required', 'string', 'in:' . implode(',', array_keys(self::ID_PATTERNS))],
            'pixels.*.name'           => ['nullable', 'string', 'max:100'],
            'pixels.*.enabled'        => ['required', 'boolean'],
            'pixels.*.pixel_id'       => ['required', 'string', 'max:64'],
            'pixels.*.purchase_label' => ['nullable', 'string', 'regex:' . self::PURCHASE_LABEL_PATTERN],
        ];
    }

    /** Per-platform ID format + duplicate checks (attach with $validator->after()) */
    public static function validateIds(Validator $validator): void
    {
        $pixels = $validator->getData()['pixels'] ?? [];
        if (!is_array($pixels)) {
            return;
        }

        $seen = [];
        foreach ($pixels as $i => $pixel) {
            if (!is_array($pixel)) {
                continue;
            }
            $platform = $pixel['platform'] ?? null;
            $id       = trim((string) ($pixel['pixel_id'] ?? ''));
            if (!isset(self::ID_PATTERNS[$platform]) || $id === '') {
                continue;
            }
            if (!preg_match(self::ID_PATTERNS[$platform], $id)) {
                $validator->errors()->add("pixels.{$i}.pixel_id", 'The ID format is invalid for this platform.');
            } elseif (isset($seen["{$platform}:{$id}"])) {
                $validator->errors()->add("pixels.{$i}.pixel_id", 'This pixel ID is already added.');
            }
            $seen["{$platform}:{$id}"] = true;
        }
    }

    public static function save(array $validated): array
    {
        $pixels = array_values(array_map([self::class, 'normalize'], $validated['pixels'] ?? []));

        SiteSetting::setValue(self::SETTING_KEY, ['pixels' => $pixels]);

        return $pixels;
    }

    private static function normalize(array $pixel): array
    {
        $platform = $pixel['platform'] ?? '';
        $label    = trim((string) ($pixel['purchase_label'] ?? ''));

        return [
            'platform'       => $platform,
            'name'           => trim((string) ($pixel['name'] ?? '')) ?: null,
            'enabled'        => (bool) ($pixel['enabled'] ?? false),
            'pixel_id'       => trim((string) ($pixel['pixel_id'] ?? '')),
            'purchase_label' => $platform === 'google_ads' && $label !== '' ? $label : null,
        ];
    }
}
