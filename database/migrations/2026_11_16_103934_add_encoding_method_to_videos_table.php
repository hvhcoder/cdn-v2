<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Thêm encoding_method để user chọn cách mã hóa:
     * - 'pixel': Pixel-based encoding (hiện tại)
     * - 'header-graft': PNG header graft (188 bytes header + raw TS)
     */
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->string('encoding_method', 20)->default('pixel')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn('encoding_method');
        });
    }
};
