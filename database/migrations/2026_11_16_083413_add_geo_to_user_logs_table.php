<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Thêm thông tin địa lý vào user_logs để lưu vết mỗi hành động quan trọng.
     */
    public function up(): void
    {
        Schema::table('user_logs', function (Blueprint $table) {
            $table->string('country', 2)->nullable()->after('ip');
            $table->string('city', 100)->nullable()->after('country');
            $table->decimal('lat', 10, 7)->nullable()->after('city');
            $table->decimal('lon', 10, 7)->nullable()->after('lat');
        });
    }

    public function down(): void
    {
        Schema::table('user_logs', function (Blueprint $table) {
            $table->dropColumn(['country', 'city', 'lat', 'lon']);
        });
    }
};
