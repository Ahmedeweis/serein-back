<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    use RefreshDatabase;

    protected function createAdmin(): User
    {
        return User::create([
            'first_name' => 'Admin',
            'last_name'  => 'Serein',
            'email'      => 'admin_' . uniqid() . '@example.com',
            'phone'      => '010' . rand(10000000, 99999999),
            'password'   => bcrypt('password'),
            'role'       => 'admin',
        ]);
    }

    protected function createUser(): User
    {
        return User::create([
            'first_name' => 'Regular',
            'last_name'  => 'Customer',
            'email'      => 'user_' . uniqid() . '@example.com',
            'phone'      => '011' . rand(10000000, 99999999),
            'password'   => bcrypt('password'),
            'role'       => 'user',
        ]);
    }

    public function test_public_categories_endpoint_returns_active_categories_localized(): void
    {
        Category::create([
            'name_en'   => 'Raw Mountain Honey',
            'name_ar'   => 'عسل جبلي خام',
            'slug'      => 'raw-mountain-honey',
            'is_active' => true,
        ]);

        Category::create([
            'name_en'   => 'Inactive Category',
            'name_ar'   => 'قسم معطل',
            'slug'      => 'inactive-category',
            'is_active' => false,
        ]);

        // Default: Arabic — name is now returned as a {en, ar} object
        $responseAr = $this->getJson('/api/categories');
        $responseAr->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name.ar', 'عسل جبلي خام')
            ->assertJsonPath('data.0.slug', 'raw-mountain-honey');

        // English query param
        $responseEn = $this->getJson('/api/categories?lang=en');
        $responseEn->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name.en', 'Raw Mountain Honey');
    }

    public function test_non_admin_cannot_access_admin_categories(): void
    {
        $user = $this->createUser();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/admin/categories');
        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Access Denied: Administrative privileges required',
            ]);
    }

    public function test_admin_can_create_category_with_image_and_generated_slug(): void
    {
        Storage::fake('public');

        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $image = UploadedFile::fake()->image('category.jpg', 600, 600);

        $response = $this->postJson('/api/admin/categories', [
            'name_en'   => 'Natural Oils',
            'name_ar'   => 'زيوت طبيعية',
            'image'     => $image,
            'is_active' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('category.slug', 'natural-oils')
            ->assertJsonPath('category.name_en', 'Natural Oils')
            ->assertJsonPath('category.name_ar', 'زيوت طبيعية');

        $this->assertDatabaseHas('categories', [
            'name_en' => 'Natural Oils',
            'slug'    => 'natural-oils',
        ]);

        $category = Category::where('slug', 'natural-oils')->first();
        $this->assertNotNull($category->image);
        Storage::disk('public')->assertExists($category->image);
    }

    public function test_admin_can_update_category_and_replace_image(): void
    {
        Storage::fake('public');

        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $oldImage = UploadedFile::fake()->image('old.jpg');
        $oldPath = $oldImage->store('categories', 'public');

        $category = Category::create([
            'name_en'   => 'Old Category',
            'name_ar'   => 'قسم قديم',
            'slug'      => 'old-category',
            'image'     => $oldPath,
            'is_active' => true,
        ]);

        $newImage = UploadedFile::fake()->image('new.jpg');

        $response = $this->postJson("/api/admin/categories/{$category->id}", [
            'name_en' => 'Updated Category',
            'name_ar' => 'قسم محدث',
            'image'   => $newImage,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('category.slug', 'updated-category')
            ->assertJsonPath('category.name_en', 'Updated Category');

        // Old image should be deleted, new image should exist
        Storage::disk('public')->assertMissing($oldPath);
        $category->refresh();
        Storage::disk('public')->assertExists($category->image);
    }

    public function test_admin_can_delete_empty_category_and_remove_image(): void
    {
        Storage::fake('public');

        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $image = UploadedFile::fake()->image('cat.png');
        $path = $image->store('categories', 'public');

        $category = Category::create([
            'name_en'   => 'Empty Category',
            'name_ar'   => 'قسم فارغ',
            'slug'      => 'empty-category',
            'image'     => $path,
            'is_active' => true,
        ]);

        $response = $this->deleteJson("/api/admin/categories/{$category->id}");
        $response->assertStatus(200)
            ->assertJson(['message' => 'تم حذف القسم بنجاح']);

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_admin_cannot_delete_category_with_active_products_conflict(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        // products table always exists via migrations — no need to create it manually
        $category = Category::create([
            'name_en'   => 'Category With Products',
            'name_ar'   => 'قسم به منتجات',
            'slug'      => 'category-with-products',
            'is_active' => true,
        ]);

        \App\Models\Product::create([
            'category_id' => $category->id,
            'name_en'     => 'Honey Jar',
            'name_ar'     => 'برطمان عسل',
            'slug'        => 'honey-jar',
            'is_active'   => true,
        ]);

        $response = $this->deleteJson("/api/admin/categories/{$category->id}");
        $response->assertStatus(409)
            ->assertJson([
                'message'        => 'Cannot delete category: There are currently 1 active product(s) assigned to this category.',
                'products_count' => 1,
            ]);

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }
}
