<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Display order inside each showcase section (1 = first). NULL = unordered (shown last).
            $table->unsignedInteger('featured_position')->nullable()->index()->after('is_featured');
            $table->unsignedInteger('trending_position')->nullable()->index()->after('is_trending');
            $table->unsignedInteger('new_arrival_position')->nullable()->index()->after('is_new_arrival');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['featured_position', 'trending_position', 'new_arrival_position']);
        });
    }
};
