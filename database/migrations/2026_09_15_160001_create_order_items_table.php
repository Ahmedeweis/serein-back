<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                  ->constrained('orders')
                  ->cascadeOnDelete();

            // Nullable FKs — product/variant may be deleted later
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->unsignedBigInteger('variant_id')->nullable()->index();

            // Snapshot of names at time of purchase
            $table->string('product_name_en');
            $table->string('product_name_ar')->nullable();
            $table->string('product_image')->nullable();
            $table->string('variant_label')->nullable();  // e.g. "500g" or "1L"

            $table->decimal('price', 10, 2);
            $table->unsignedInteger('quantity')->default(1);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
