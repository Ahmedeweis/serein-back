<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Home page hero slider — every slide needs BOTH a desktop and a mobile image
        Schema::create('hero_slides', function (Blueprint $table) {
            $table->id();
            $table->string('desktop_image');
            $table->string('mobile_image');
            $table->string('alt_ar')->nullable();
            $table->string('alt_en')->nullable();
            $table->string('link', 500)->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hero_slides');
    }
};
