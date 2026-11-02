<?php

namespace Tests\Feature;

use App\Models\BlogCategory;
use App\Models\BlogTag;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\BlogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BlogPostApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'first_name' => 'Admin',
            'last_name'  => 'User',
            'email'      => 'admin@serein.test',
            'phone'      => '01000000001',
            'password'   => bcrypt('secret'),
            'role'       => 'admin',
        ]);

        $this->seed(BlogSeeder::class);
    }

    public function test_public_can_fetch_published_posts(): void
    {
        $response = $this->getJson('/api/posts');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'slug',
                        'title' => ['en', 'ar'],
                        'excerpt' => ['en', 'ar'],
                        'content' => ['en', 'ar'],
                        'coverImage',
                        'author',
                        'category',
                        'tags',
                        'readTimeMinutes',
                    ]
                ]
            ]);

        $this->assertCount(6, $response->json('data'));
    }

    public function test_public_can_fetch_single_post_and_increments_views(): void
    {
        $post = Post::where('slug', 'benefits-of-raw-honey')->firstOrFail();
        $initialViews = $post->views_count;

        $response = $this->getJson('/api/posts/benefits-of-raw-honey');

        $response->assertOk()
            ->assertJsonPath('data.slug', 'benefits-of-raw-honey')
            ->assertJsonStructure([
                'data' => ['id', 'slug', 'title', 'content', 'sections', 'author', 'category'],
                'related' => ['*' => ['id', 'slug', 'title']],
            ]);

        $this->assertEquals($initialViews + 1, $post->fresh()->views_count);
    }

    public function test_public_can_fetch_categories_with_count(): void
    {
        $response = $this->getJson('/api/posts/categories');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'slug', 'name_en', 'name_ar', 'posts_count']
                ]
            ]);
    }

    public function test_admin_can_create_post_with_simplified_sections(): void
    {
        Sanctum::actingAs($this->admin);

        $payload = [
            'title' => [
                'en' => 'The Wonders of Royal Jelly',
                'ar' => 'عجائب غذاء ملكات النحل',
            ],
            'excerpt' => [
                'en' => 'A deep dive into nature\'s royal nutrition.',
                'ar' => 'نظرة متعمقة على الغذاء الملكي.',
            ],
            'category' => [
                'en' => 'Wellness',
                'ar' => 'العافية',
            ],
            'author' => 'Dr. Ahmed Eweis',
            'authorRole' => [
                'en' => 'Apitherapy Specialist',
                'ar' => 'أخصائي العلاج بمنتجات النحل',
            ],
            'isFeatured' => true,
            'status' => 'published',
            'tags' => ['royal-jelly', 'energy', 'wellness'],
            'sections' => [
                [
                    'heading_en' => 'What Is Royal Jelly?',
                    'heading_ar' => 'ما هو غذاء ملكات النحل؟',
                    'body_en' => "Royal jelly is a milky secretion produced by worker honey bees.\nIt is exclusively fed to the queen bee, promoting extraordinary longevity.",
                    'body_ar' => 'غذاء ملكات النحل هو إفراز لبني تصنعه شغالات النحل.',
                ],
                [
                    'heading_en' => 'Key Active Nutrients',
                    'heading_ar' => 'أبرز العناصر النشطة',
                    'body_en' => "It contains 10-HDA, an exclusive fatty acid with antimicrobial qualities.",
                    'body_ar' => 'يحتوي على حمض 10-HDA الفريد.',
                ]
            ],
        ];

        $response = $this->postJson('/api/admin/posts', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.title.en', 'The Wonders of Royal Jelly')
            ->assertJsonPath('data.slug', 'the-wonders-of-royal-jelly')
            ->assertJsonPath('data.isFeatured', true);

        // Verify HTML compiled automatically from plain-text sections without raw tags from admin
        $created = Post::where('slug', 'the-wonders-of-royal-jelly')->firstOrFail();
        $this->assertStringContainsString('<h2>What Is Royal Jelly?</h2>', $created->content_en);
        $this->assertStringContainsString('<p>Royal jelly is a milky secretion', $created->content_en);
        $this->assertCount(2, $created->sections);
        $this->assertCount(3, $created->tags);
    }

    public function test_admin_can_toggle_post_status(): void
    {
        Sanctum::actingAs($this->admin);

        $post = Post::where('slug', 'benefits-of-raw-honey')->firstOrFail();
        $this->assertEquals('published', $post->status);

        $response = $this->patchJson("/api/admin/posts/{$post->id}/status");

        $response->assertOk()
            ->assertJsonPath('data.status', 'draft');

        $this->assertEquals('draft', $post->fresh()->status);
    }

    public function test_admin_can_delete_post(): void
    {
        Sanctum::actingAs($this->admin);

        $post = Post::where('slug', 'honey-for-diabetics')->firstOrFail();

        $response = $this->deleteJson("/api/admin/posts/{$post->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }

    public function test_post_extracts_youtube_embed_url_from_various_formats(): void
    {
        $formats = [
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
            'https://youtube.com/watch?v=dQw4w9WgXcQ&t=42s' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
            'https://youtu.be/dQw4w9WgXcQ' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
            'https://youtu.be/dQw4w9WgXcQ?si=testing123' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
            'https://www.youtube.com/embed/dQw4w9WgXcQ' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
            'https://www.youtube.com/shorts/dQw4w9WgXcQ' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
        ];

        foreach ($formats as $inputUrl => $expectedEmbed) {
            $post = new Post(['youtube_url' => $inputUrl]);
            $this->assertEquals($expectedEmbed, $post->youtube_embed_url, "Failed for URL: {$inputUrl}");
        }

        $emptyPost = new Post(['youtube_url' => null]);
        $this->assertNull($emptyPost->youtube_embed_url);
    }

    public function test_admin_can_create_post_with_youtube_url(): void
    {
        Sanctum::actingAs($this->admin);

        $payload = [
            'title' => [
                'en' => 'Bee Pollen Explained',
                'ar' => 'شرح حبوب اللقاح',
            ],
            'excerpt' => [
                'en' => 'Everything about bee pollen benefits.',
                'ar' => 'كل شيء عن فوائد حبوب اللقاح.',
            ],
            'category' => 'Wellness',
            'author' => 'Dr. Sara Ali',
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'status' => 'published',
        ];

        $response = $this->postJson('/api/admin/posts', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.youtube_url', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')
            ->assertJsonPath('data.youtube_embed_url', 'https://www.youtube.com/embed/dQw4w9WgXcQ')
            ->assertJsonPath('data.youtubeEmbedUrl', 'https://www.youtube.com/embed/dQw4w9WgXcQ');

        $this->assertDatabaseHas('posts', [
            'slug' => 'bee-pollen-explained',
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);
    }

    public function test_post_rejects_invalid_youtube_url(): void
    {
        Sanctum::actingAs($this->admin);

        $payload = [
            'title' => [
                'en' => 'Invalid Video Post',
            ],
            'youtube_url' => 'https://vimeo.com/123456789',
        ];

        $response = $this->postJson('/api/admin/posts', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['youtube_url']);
    }
}
