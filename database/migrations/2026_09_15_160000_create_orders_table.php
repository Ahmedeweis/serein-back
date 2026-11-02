<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            // Unique human-readable order number e.g. ORD-2026-1001
            $table->string('order_number')->unique()->index();

            // Customer snapshot (denormalised — preserves data even if user deleted)
            $table->string('customer_name');
            $table->string('customer_email')->index();
            $table->string('customer_phone', 30)->nullable();
            $table->string('customer_address')->nullable();
            $table->string('customer_city')->nullable();
            $table->string('customer_country', 100)->default('Egypt');

            // Pricing
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('shipping_cost', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);

            // Shipping zone reference (nullable — preserved even if zone deleted)
            $table->foreignId('shipping_zone_id')
                  ->nullable()
                  ->constrained('shipping_zones')
                  ->nullOnDelete();

            // Payment
            $table->string('payment_method', 50)->default('cod');
            $table->enum('payment_status', ['pending', 'paid', 'failed'])->default('pending');

            // Fulfilment workflow
            $table->enum('status', [
                'Pending',
                'Confirmed',
                'In Preparation',
                'Out for Delivery',
                'Delivered',
                'Cancelled',
            ])->default('Pending')->index();

            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
