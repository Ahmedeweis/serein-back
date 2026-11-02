<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();

            // Foreign key to product (cascade on delete)
            $table->foreignId('product_id')
                  ->constrained('products')
                  ->cascadeOnDelete();

            // Optional foreign key to registered user (set null on delete)
            $table->foreignId('user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            // Reviewer details (guests or customers)
            $table->string('reviewer_name');
            $table->string('reviewer_email')->nullable();

            // Numerical rating: 1 to 5 stars
            $table->unsignedTinyInteger('rating')->index();

            // Review comment content
            $table->text('comment');

            // Moderation status
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->index();

            // Verified purchase flag
            $table->boolean('is_verified_purchase')->default(false);

            $table->timestamps();

            // Composite index for fast public approved reviews query
            $table->index(['product_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
