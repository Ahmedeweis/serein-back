<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Blog Categories
        Schema::create('blog_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name_en');
            $table->string('name_ar');
            $table->string('slug')->unique()->index();
            $table->timestamps();
        });

        // 2. Blog Posts
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_category_id')
                  ->nullable()
                  ->constrained('blog_categories')
                  ->nullOnDelete();

            $table->string('title_en');
            $table->string('title_ar')->nullable();
            $table->string('slug')->unique()->index();

            $table->text('excerpt_en')->nullable();
            $table->text('excerpt_ar')->nullable();

            // Structured section blocks: [{"heading_en": "...", "heading_ar": "...", "body_en": "...", "body_ar": "..."}]
            $table->json('sections')->nullable();

            // Compiled clean HTML content for storefront rendering
            $table->longText('content_en')->nullable();
            $table->longText('content_ar')->nullable();

            $table->string('cover_image')->nullable();

            // Author metadata
            $table->string('author_name')->default('Serein Team');
            $table->string('author_avatar')->nullable();
            $table->string('author_role_en')->nullable();
            $table->string('author_role_ar')->nullable();

            // Editorial flags & analytics
            $table->boolean('is_featured')->default(false)->index();
            $table->enum('status', ['published', 'draft'])->default('published')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->unsignedInteger('read_time_minutes')->default(5);
            $table->unsignedBigInteger('views_count')->default(0);

            $table->timestamps();
        });

        // 3. Blog Tags
        Schema::create('blog_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique()->index();
            $table->timestamps();
        });

        // 4. Post-Tag Pivot
        Schema::create('blog_post_tag', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('blog_tags')->cascadeOnDelete();
            $table->primary(['post_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_post_tag');
        Schema::dropIfExists('blog_tags');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('blog_categories');
    }
};
