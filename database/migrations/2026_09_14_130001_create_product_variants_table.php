<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();

            // Foreign key to products (CASCADE on delete — variants are deleted with product)
            $table->foreignId('product_id')
                  ->constrained('products')
                  ->cascadeOnDelete();

            // Bilingual display labels (e.g. "250g" / "250 جرام")
            $table->string('name_en');
            $table->string('name_ar')->nullable();

            // Measurement details (e.g. unit="g", value=250.00)
            $table->string('unit');
            $table->decimal('value', 8, 2);

            // Pricing
            $table->decimal('price', 10, 2)->unsigned();
            $table->decimal('compare_at_price', 10, 2)->unsigned()->nullable();

            // Inventory
            $table->unsignedInteger('stock')->default(0);
            $table->string('sku')->nullable()->unique();

            // Default variant flag (drives the product's displayed price)
            $table->boolean('is_default')->default(false);

            $table->timestamps();

            // Composite index for fast "get default variant of product" queries
            $table->index(['product_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
