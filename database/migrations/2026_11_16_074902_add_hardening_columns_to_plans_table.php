<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cấu hình chính sách hết hạn theo gói:
     *   expiry_lock_days      — số ngày sau khi hết gói mới khóa embed (null = khóa ngay).
     *   expiry_delete_days    — số ngày sau khi hết gói mới xóa video (null = không tự xóa).
     *   embed_token_ttl_minutes — thời hạn (phút) của token ký cho embed/m3u8 (null = không dùng token).
     *   embed_locked_when_expired — nếu true, hết gói sẽ khóa embed theo expiry_lock_days.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('expiry_lock_days')->nullable()->after('embed_domain_limit');
            $table->unsignedInteger('expiry_delete_days')->nullable()->after('expiry_lock_days');
            $table->unsignedInteger('embed_token_ttl_minutes')->nullable()->after('expiry_delete_days');
            $table->boolean('embed_locked_when_expired')->default(true)->after('embed_token_ttl_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['expiry_lock_days', 'expiry_delete_days', 'embed_token_ttl_minutes', 'embed_locked_when_expired']);
        });
    }
};
