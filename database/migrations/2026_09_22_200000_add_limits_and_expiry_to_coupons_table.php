<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds max_uses, used_count, and ensures expires_at on coupons table.
     */
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            if (!Schema::hasColumn('coupons', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('is_active');
            }
            if (!Schema::hasColumn('coupons', 'max_uses')) {
                $table->unsignedInteger('max_uses')->nullable()->after('expires_at');
            }
            if (!Schema::hasColumn('coupons', 'used_count')) {
                $table->unsignedInteger('used_count')->default(0)->after('max_uses');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $columnsToDrop = [];
            if (Schema::hasColumn('coupons', 'used_count')) {
                $columnsToDrop[] = 'used_count';
            }
            if (Schema::hasColumn('coupons', 'max_uses')) {
                $columnsToDrop[] = 'max_uses';
            }
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
