<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // حذف name الافتراضي واستبداله بالاسم الأول والأخير
            if (Schema::hasColumn('users', 'name')) {
                $table->dropColumn('name');
            }
            $table->string('first_name')->after('id');
            $table->string('last_name')->after('first_name');
            $table->string('phone')->unique()->after('email');
            $table->string('country')->default('Egypt')->after('phone');
            $table->string('governorate')->nullable()->after('country');
            $table->text('address')->nullable()->after('governorate');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name', 'phone', 'country', 'governorate', 'address']);
            $table->string('name')->after('id');
        });
    }
};
