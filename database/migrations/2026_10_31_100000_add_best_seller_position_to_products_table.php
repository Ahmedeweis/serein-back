<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Display order inside the "Best Sellers" section (1 = first). NULL = unordered (shown last).
            $table->unsignedInteger('best_seller_position')->nullable()->index()->after('is_best_seller');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('best_seller_position');
        });
    }
};
