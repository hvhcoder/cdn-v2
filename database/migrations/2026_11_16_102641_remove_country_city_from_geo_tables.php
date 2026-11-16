<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chỉ giữ lại lat/lon, bỏ country/city để đơn giản hóa.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['last_login_country', 'last_login_city']);
        });

        Schema::table('user_logs', function (Blueprint $table) {
            $table->dropColumn(['country', 'city']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('last_login_country', 2)->nullable()->after('last_login_ip');
            $table->string('last_login_city', 100)->nullable()->after('last_login_country');
        });

        Schema::table('user_logs', function (Blueprint $table) {
            $table->string('country', 2)->nullable()->after('ip');
            $table->string('city', 100)->nullable()->after('country');
        });
    }
};
