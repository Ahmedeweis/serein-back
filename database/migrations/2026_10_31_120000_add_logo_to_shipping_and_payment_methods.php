<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Optional uploaded logo image; when empty the storefront keeps the built-in badge (logo_type / badge)
        Schema::table('shipping_methods', function (Blueprint $table) {
            $table->string('logo')->nullable()->after('logo_type');
        });
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->string('logo')->nullable()->after('badge');
        });
    }

    public function down(): void
    {
        Schema::table('shipping_methods', function (Blueprint $table) {
            $table->dropColumn('logo');
        });
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn('logo');
        });
    }
};
