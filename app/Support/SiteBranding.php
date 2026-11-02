<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Storefront branding managed from the dashboard:
 * - Announcement bar at the very top of the store (messages, static/scrolling, colors, small logo)
 * - Favicon (browser tab icon)
 */
class SiteBranding
{
    public const ANNOUNCEMENT_KEY = 'announcement_bar';
    public const FAVICON_KEY      = 'favicon';

    public const FEATURES_KEY     = 'features';

    public const MAX_MESSAGES = 10;

    /** The "Why choose Serein" section always has exactly this many cards */
    public const FEATURES_COUNT = 4;

    /** Current hard-coded bar, used until the admin saves their own */
    public static function announcementDefaults(): array
    {
        return [
            'enabled'         => true,
            'mode'            => 'static',   // static (messages rotate) | marquee (scrolling)
            'speed'           => 'normal',   // slow | normal | fast
            'bg_color'        => '#edc71e',
            'text_color'      => '#ffffff',
            'highlight_color' => '#3c7136',  // color of *highlighted* words
            'logo'            => null,       // stored path on the public disk
            'messages'        => [
                [
                    'text_ar' => 'استمتع بتجربة منتجاتك المفضلة من *سرين* .. من قلب الطبيعة إلى قلبك',
                    'text_en' => 'Enjoy your favorite *Serein* products .. from the heart of nature to yours',
                    'link'    => null,
                ],
            ],
        ];
    }

    public static function announcement(): array
    {
        $stored = SiteSetting::getValue(self::ANNOUNCEMENT_KEY, []);
        $bar = array_merge(self::announcementDefaults(), array_intersect_key($stored, self::announcementDefaults()));
        $bar['logo_url'] = self::publicUrl($bar['logo']);

        return $bar;
    }

    public static function announcementRules(): array
    {
        $hex = 'regex:/^#[0-9a-fA-F]{6}$/';

        return [
            'enabled'            => ['required', 'boolean'],
            'mode'               => ['required', 'in:static,marquee'],
            'speed'              => ['required', 'in:slow,normal,fast'],
            'bg_color'           => ['required', $hex],
            'text_color'         => ['required', $hex],
            'highlight_color'    => ['required', $hex],
            'messages'           => ['required', 'array', 'min:1', 'max:' . self::MAX_MESSAGES],
            'messages.*.text_ar' => ['required', 'string', 'max:200'],
            'messages.*.text_en' => ['nullable', 'string', 'max:200'],
            // Internal path (/shop) or a full https link — never javascript: etc.
            'messages.*.link'    => ['nullable', 'string', 'max:500', 'regex:/^(\/(?!\/)[^\s]*|https?:\/\/[^\s]+)$/'],
        ];
    }

    public static function saveAnnouncement(array $validated): array
    {
        $current = SiteSetting::getValue(self::ANNOUNCEMENT_KEY, []);

        $bar = [
            'enabled'         => (bool) $validated['enabled'],
            'mode'            => $validated['mode'],
            'speed'           => $validated['speed'],
            'bg_color'        => strtolower($validated['bg_color']),
            'text_color'      => strtolower($validated['text_color']),
            'highlight_color' => strtolower($validated['highlight_color']),
            'logo'            => $current['logo'] ?? null, // logo is managed by its own endpoints
            'messages'        => array_values(array_map(fn ($m) => [
                'text_ar' => trim($m['text_ar']),
                'text_en' => trim((string) ($m['text_en'] ?? '')) ?: null,
                'link'    => trim((string) ($m['link'] ?? '')) ?: null,
            ], $validated['messages'])),
        ];

        SiteSetting::setValue(self::ANNOUNCEMENT_KEY, $bar);

        return self::announcement();
    }

    public static function setAnnouncementLogo(?UploadedFile $file): array
    {
        $bar = SiteSetting::getValue(self::ANNOUNCEMENT_KEY, []) + self::announcementDefaults();
        self::deleteFile($bar['logo'] ?? null);
        $bar['logo'] = $file?->store('branding', 'public');
        SiteSetting::setValue(self::ANNOUNCEMENT_KEY, $bar);

        return self::announcement();
    }

    // ── "Why choose Serein" feature cards (exactly 4) ────────────────────────

    /** Current hard-coded cards; icon null = the storefront's built-in icon for that card */
    public static function featureDefaults(): array
    {
        return [
            ['icon' => null, 'title_ar' => 'توصيل سريع ومجاني', 'title_en' => 'Fast & Free Delivery',
                'desc_ar' => 'شحن سريع ومجاني لجميع الطلبات التي تتجاوز 1000 جنيه', 'desc_en' => 'Fast, free shipping on all orders over 1,000 EGP'],
            ['icon' => null, 'title_ar' => 'دفع آمن ومحمي', 'title_en' => 'Secure Payments',
                'desc_ar' => 'حماية كاملة بتشفير SSL ومعايير الدفع الآمن', 'desc_en' => 'Protected by SSL encryption & PCI compliance'],
            ['icon' => null, 'title_ar' => 'دعم متواصل ٢٤/٧', 'title_en' => '24/7 Support',
                'desc_ar' => 'فريق مساعدة ودعم متخصص متاح على مدار الساعة', 'desc_en' => 'Expert assistance whenever you need it'],
            ['icon' => null, 'title_ar' => 'إرجاع سهل وسلس', 'title_en' => 'Easy Returns',
                'desc_ar' => 'ضمان استرجاع الأموال خلال 14 يوم', 'desc_en' => '14-day hassle-free money back guarantee'],
        ];
    }

    public static function features(): array
    {
        $stored = SiteSetting::getValue(self::FEATURES_KEY, []);

        return array_map(function (int $i) use ($stored) {
            $feature = array_merge(self::featureDefaults()[$i], array_intersect_key($stored[$i] ?? [], self::featureDefaults()[$i]));
            $feature['icon_url'] = self::publicUrl($feature['icon']);
            unset($feature['icon']);

            return $feature;
        }, range(0, self::FEATURES_COUNT - 1));
    }

    public static function featureRules(): array
    {
        return [
            'features'            => ['required', 'array', 'size:' . self::FEATURES_COUNT],
            'features.*.title_ar' => ['required', 'string', 'max:60'],
            'features.*.title_en' => ['required', 'string', 'max:60'],
            'features.*.desc_ar'  => ['required', 'string', 'max:160'],
            'features.*.desc_en'  => ['required', 'string', 'max:160'],
        ];
    }

    public static function saveFeatures(array $validated): array
    {
        $stored = SiteSetting::getValue(self::FEATURES_KEY, []);

        $features = array_map(fn (int $i) => [
            'icon'     => $stored[$i]['icon'] ?? null, // icons are managed by their own endpoints
            'title_ar' => trim($validated['features'][$i]['title_ar']),
            'title_en' => trim($validated['features'][$i]['title_en']),
            'desc_ar'  => trim($validated['features'][$i]['desc_ar']),
            'desc_en'  => trim($validated['features'][$i]['desc_en']),
        ], range(0, self::FEATURES_COUNT - 1));

        SiteSetting::setValue(self::FEATURES_KEY, $features);

        return self::features();
    }

    /** Upload a custom icon for card $index, or null to go back to the built-in icon */
    public static function setFeatureIcon(int $index, ?UploadedFile $file): array
    {
        $stored = SiteSetting::getValue(self::FEATURES_KEY, []);
        $features = array_map(
            fn (int $i) => array_merge(self::featureDefaults()[$i], $stored[$i] ?? []),
            range(0, self::FEATURES_COUNT - 1)
        );

        self::deleteFile($features[$index]['icon'] ?? null);
        $features[$index]['icon'] = $file?->store('branding/features', 'public');
        SiteSetting::setValue(self::FEATURES_KEY, $features);

        return self::features();
    }

    public static function faviconUrl(): ?string
    {
        return self::publicUrl(SiteSetting::getValue(self::FAVICON_KEY)['path'] ?? null);
    }

    public static function setFavicon(?UploadedFile $file): ?string
    {
        self::deleteFile(SiteSetting::getValue(self::FAVICON_KEY)['path'] ?? null);
        SiteSetting::setValue(self::FAVICON_KEY, ['path' => $file?->store('branding', 'public')]);

        return self::faviconUrl();
    }

    /** Public storefront payload */
    public static function publicSettings(): array
    {
        $bar = self::announcement();
        unset($bar['logo']);

        return [
            'announcement_bar' => $bar,
            'favicon_url'      => self::faviconUrl(),
            'features'         => self::features(),
        ];
    }

    private static function publicUrl(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }

    private static function deleteFile(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
