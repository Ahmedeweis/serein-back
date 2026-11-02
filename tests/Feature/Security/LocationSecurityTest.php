<?php

namespace Tests\Feature\Security;

use App\Models\Country;
use App\Models\Governorate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * LocationSecurityTest
 * ────────────────────
 * Covers security hardening for Location endpoints:
 *  1. Cache Flooding / Poisoning prevention on getGovernorates via country_id exists validation
 *  2. Non-integer & arbitrary input validation on country_id
 *  3. Lang parameter validation on both countries & governorates
 *  4. Reactive cache invalidation via Country & Governorate model events
 */
class LocationSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    // ── Fix 1: Cache Flooding / Poisoning Prevention ─────────────────────────

    public function test_non_existent_country_id_fails_validation_and_prevents_cache_flooding(): void
    {
        // Attacker passes random non-existent country IDs in a loop
        $randomCountryId = 987123;

        $response = $this->getJson("/api/locations/governorates?country_id={$randomCountryId}");

        // Must reject with 422 Unprocessable Entity
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['country_id']);

        // CRITICAL: Cache must NOT have been created for this random key
        $this->assertFalse(Cache::has("govs_{$randomCountryId}_ar_v2"));
        $this->assertFalse(Cache::has("govs_{$randomCountryId}_en_v2"));
    }

    public function test_non_integer_country_id_fails_validation(): void
    {
        $response = $this->getJson('/api/locations/governorates?country_id=invalid_payload');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['country_id']);

        $this->assertFalse(Cache::has('govs_invalid_payload_ar_v2'));
    }

    public function test_invalid_lang_parameter_fails_validation(): void
    {
        $country = Country::create([
            'code'       => 'EG',
            'name_ar'    => 'مصر',
            'name_en'    => 'Egypt',
            'phone_code' => '+20',
            'is_active'  => true,
        ]);

        // Attempt cache poisoning with arbitrary language string on governorates
        $resGov = $this->getJson("/api/locations/governorates?country_id={$country->id}&lang=poisoned_lang_123");
        $resGov->assertStatus(422)->assertJsonValidationErrors(['lang']);

        // Attempt cache poisoning with arbitrary language string on countries
        $resCountry = $this->getJson('/api/locations/countries?lang=unsupported_lang');
        $resCountry->assertStatus(422)->assertJsonValidationErrors(['lang']);
    }

    // ── Fix 2: Finite Caching & Reactive Invalidation ────────────────────────

    public function test_valid_country_and_governorates_cache_successfully(): void
    {
        $country = Country::create([
            'code'       => 'EG',
            'name_ar'    => 'مصر',
            'name_en'    => 'Egypt',
            'phone_code' => '+20',
            'is_active'  => true,
        ]);

        $gov = Governorate::create([
            'country_id' => $country->id,
            'name_ar'    => 'القاهرة',
            'name_en'    => 'Cairo',
            'is_active'  => true,
        ]);

        $this->getJson('/api/locations/countries')->assertStatus(200);
        $this->getJson("/api/locations/governorates?country_id={$country->id}")->assertStatus(200);

        $this->assertTrue(Cache::has('countries_ar'));
        $this->assertTrue(Cache::has("govs_{$country->id}_ar_v2"));
    }

    public function test_updating_country_busts_country_and_governorate_cache(): void
    {
        $country = Country::create([
            'code'       => 'EG',
            'name_ar'    => 'مصر',
            'name_en'    => 'Egypt',
            'phone_code' => '+20',
            'is_active'  => true,
        ]);

        // Warm up cache
        $this->getJson('/api/locations/countries')->assertStatus(200);
        $this->getJson("/api/locations/governorates?country_id={$country->id}")->assertStatus(200);

        $this->assertTrue(Cache::has('countries_ar'));
        $this->assertTrue(Cache::has("govs_{$country->id}_ar_v2"));

        // Admin updates country
        $country->update(['name_en' => 'Arab Republic of Egypt']);

        // Cache must be busted automatically
        $this->assertFalse(Cache::has('countries_ar'));
        $this->assertFalse(Cache::has("govs_{$country->id}_ar_v2"));
    }

    public function test_updating_governorate_busts_governorate_cache(): void
    {
        $country = Country::create([
            'code'       => 'EG',
            'name_ar'    => 'مصر',
            'name_en'    => 'Egypt',
            'phone_code' => '+20',
            'is_active'  => true,
        ]);

        $gov = Governorate::create([
            'country_id' => $country->id,
            'name_ar'    => 'الجيزة',
            'name_en'    => 'Giza',
            'is_active'  => true,
        ]);

        // Warm up cache
        $this->getJson("/api/locations/governorates?country_id={$country->id}")->assertStatus(200);
        $this->assertTrue(Cache::has("govs_{$country->id}_ar_v2"));

        // Admin or system updates governorate
        $gov->update(['name_en' => 'Giza City']);

        // Governorate cache must be busted automatically
        $this->assertFalse(Cache::has("govs_{$country->id}_ar_v2"));
    }
}
