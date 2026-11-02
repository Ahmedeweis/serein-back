<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // Foreign key to categories (RESTRICT on delete — category cannot be deleted if products exist)
            $table->foreignId('category_id')
                  ->constrained('categories')
                  ->restrictOnDelete();

            // Bilingual names & slug
            $table->string('name_en');
            $table->string('name_ar')->nullable();
            $table->string('slug')->unique();
            $table->index('slug');

            // Bilingual descriptions
            $table->text('description_en')->nullable();
            $table->text('description_ar')->nullable();

            // Main product image (stored path in public disk /products/)
            $table->string('image')->nullable();

            // Variant measurement type
            $table->enum('unit_type', ['weight', 'volume', 'unit'])->default('weight');

            // Ratings
            $table->decimal('rating', 2, 1)->default(5.0);
            $table->unsignedInteger('reviews_count')->default(0);

            // Showcase flags — indexed for instant homepage filtering
            $table->boolean('is_best_seller')->default(false)->index();
            $table->boolean('is_featured')->default(false)->index();
            $table->boolean('is_trending')->default(false)->index();
            $table->boolean('is_new_arrival')->default(true)->index();

            // Visibility
            $table->boolean('is_active')->default(true)->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
