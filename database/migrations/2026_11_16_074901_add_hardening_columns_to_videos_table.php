<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Thêm cột hỗ trợ Phase 7:
     *   embed_locked_at   — thời điểm embed bị khóa thủ công hoặc do hết gói.
     *   dead_checked_at   — lần cuối job kiểm tra URL segment còn sống.
     *   dead_segments     — số segment được phát hiện chết trong lần kiểm tra gần nhất.
     *   allowed_domains   — JSON array tên miền được phép embed (null = không giới hạn).
     */
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->timestamp('embed_locked_at')->nullable()->after('status');
            $table->timestamp('dead_checked_at')->nullable()->after('embed_locked_at');
            $table->unsignedSmallInteger('dead_segments')->default(0)->after('dead_checked_at');
            $table->json('allowed_domains')->nullable()->after('dead_segments');
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn(['embed_locked_at', 'dead_checked_at', 'dead_segments', 'allowed_domains']);
        });
    }
};
