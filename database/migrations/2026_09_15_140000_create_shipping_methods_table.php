<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->string('id', 50)->primary(); // e.g. dhl, fedex, serein
            $table->string('name_en');
            $table->string('name_ar');
            $table->string('estimate_en');
            $table->string('estimate_ar');
            $table->decimal('cost', 8, 2)->default(0.00);
            $table->string('logo_type', 50)->default('default'); // dhl, fedex, serein, default
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_methods');
    }
};
