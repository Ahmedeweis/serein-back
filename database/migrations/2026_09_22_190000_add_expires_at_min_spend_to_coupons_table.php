<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add expires_at and min_spend columns to coupons table.
     * - expires_at: nullable datetime — coupon is rejected when this is in the past
     * - min_spend:  nullable decimal  — coupon requires a minimum cart subtotal
     */
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('is_active');
            $table->decimal('min_spend', 10, 2)->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn(['expires_at', 'min_spend']);
        });
    }
};
