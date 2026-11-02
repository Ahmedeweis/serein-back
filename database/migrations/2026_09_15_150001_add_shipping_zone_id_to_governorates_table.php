<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('governorates', function (Blueprint $table) {
            $table->foreignId('shipping_zone_id')
                ->nullable()
                ->after('country_id')
                ->constrained('shipping_zones')
                ->nullOnDelete();

            $table->index('shipping_zone_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('governorates', function (Blueprint $table) {
            $table->dropForeign(['shipping_zone_id']);
            $table->dropIndex(['shipping_zone_id']);
            $table->dropColumn('shipping_zone_id');
        });
    }
};
