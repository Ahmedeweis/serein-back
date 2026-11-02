<?php

namespace Tests\Feature\Security;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategorySecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /**
     * Fix 1: Data Leak — Guest/regular user always sees ONLY active categories.
     * Passing ?status=all as a visitor must NOT expose inactive/draft categories.
     */
    public function test_guest_cannot_see_inactive_categories_via_status_all_param(): void
    {
        Category::create(['name_en' => 'Published', 'name_ar' => 'منشور', 'slug' => 'published', 'is_active' => true]);
        Category::create(['name_en' => 'Draft Secret', 'name_ar' => 'مسودة سرية', 'slug' => 'draft-secret', 'is_active' => false]);

        // Guest (no auth) with ?status=all manipulation attempt
        $response = $this->getJson('/api/categories?status=all');

        $response->assertStatus(200);

        // Must only return the 1 active category
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.slug', 'published');
    }

    /**
     * Fix 1 (Cont.): Authenticated regular user also cannot see inactive categories.
     */
    public function test_regular_user_cannot_see_inactive_categories(): void
    {
        Category::create(['name_en' => 'Active', 'name_ar' => 'نشط', 'slug' => 'active', 'is_active' => true]);
        Category::create(['name_en' => 'Hidden Plan', 'name_ar' => 'خطة مخفية', 'slug' => 'hidden-plan', 'is_active' => false]);

        $regularUser = User::create([
            'first_name'  => 'Regular',
            'last_name'   => 'User',
            'email'       => 'user@serein.test',
            'phone'       => '01011111111',
            'password'    => bcrypt('password'),
            'role'        => 'user',
        ]);

        Sanctum::actingAs($regularUser);

        // Regular user attempts to see all including inactive
        $response = $this->getJson('/api/categories?status=all');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data'); // Only 1 active
    }

    /**
     * Fix 1 (Cont.): Admin with ?status=all CAN see all categories including inactive.
     */
    public function test_admin_can_see_inactive_categories_with_status_all_param(): void
    {
        Category::create(['name_en' => 'Active', 'name_ar' => 'نشط', 'slug' => 'active-cat', 'is_active' => true]);
        Category::create(['name_en' => 'Inactive Draft', 'name_ar' => 'مسودة', 'slug' => 'inactive-draft', 'is_active' => false]);

        $admin = User::create([
            'first_name'  => 'Admin',
            'last_name'   => 'User',
            'email'       => 'admin@serein.test',
            'phone'       => '01099999999',
            'password'    => bcrypt('password'),
            'role'        => 'admin',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/categories?status=all');

        $response->assertStatus(200);
        // Admin sees BOTH active and inactive
        $response->assertJsonCount(2, 'data');
    }

    /**
     * Fix 3: Cache Layer — Verify caching stores results for repeated calls.
     * The 2nd request should be served from Cache, not from DB.
     */
    public function test_categories_are_cached_and_served_from_cache_on_repeated_requests(): void
    {
        Category::create(['name_en' => 'Honey', 'name_ar' => 'عسل', 'slug' => 'honey', 'is_active' => true]);

        // First request — warms cache
        $this->getJson('/api/categories')->assertStatus(200)->assertJsonCount(1, 'data');

        // Add a new category AFTER cache is warm
        Category::create(['name_en' => 'New Category', 'name_ar' => 'قسم جديد', 'slug' => 'new-cat', 'is_active' => true]);

        // Second request — should still return 1 item (served from cache)
        $cachedResponse = $this->getJson('/api/categories');
        $cachedResponse->assertStatus(200)->assertJsonCount(1, 'data');
    }

    /**
     * Fix 3 (Cont.): Cache is invalidated when Admin creates a new category.
     */
    public function test_cache_is_invalidated_when_admin_creates_category(): void
    {
        $admin = User::create([
            'first_name'  => 'Admin',
            'last_name'   => 'User',
            'email'       => 'admin2@serein.test',
            'phone'       => '01088888888',
            'password'    => bcrypt('password'),
            'role'        => 'admin',
        ]);

        Category::create(['name_en' => 'Original', 'name_ar' => 'أصلي', 'slug' => 'original', 'is_active' => true]);

        // Warm up the public cache
        $this->getJson('/api/categories')->assertStatus(200)->assertJsonCount(1, 'data');

        Sanctum::actingAs($admin);

        // Admin creates a new active category — should bust cache
        $this->postJson('/api/admin/categories', [
            'name_en'   => 'New Arrival',
            'name_ar'   => 'وصل جديد',
            'is_active' => true,
        ])->assertStatus(201);

        // Public endpoint must now reflect the new category (cache was busted)
        $freshResponse = $this->getJson('/api/categories');
        $freshResponse->assertStatus(200)->assertJsonCount(2, 'data');
    }

    /**
     * Verify that the name field is returned as an {en, ar} object.
     */
    public function test_categories_name_is_returned_as_bilingual_object(): void
    {
        Category::create(['name_en' => 'Mountain Honey', 'name_ar' => 'عسل جبلي', 'slug' => 'mountain-honey', 'is_active' => true]);

        $response = $this->getJson('/api/categories');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'slug', 'name' => ['en', 'ar'], 'image_url', 'products_count'],
                ]
            ])
            ->assertJsonPath('data.0.name.en', 'Mountain Honey')
            ->assertJsonPath('data.0.name.ar', 'عسل جبلي');
    }
}
