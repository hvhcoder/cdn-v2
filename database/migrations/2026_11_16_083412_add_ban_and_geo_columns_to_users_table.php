<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Thêm cột cho tính năng ban user và lưu thông tin địa lý để tuân thủ pháp luật.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('banned_at')->nullable()->after('email_verified_at');
            $table->text('banned_reason')->nullable()->after('banned_at');
            $table->foreignId('banned_by')->nullable()->constrained('users')->nullOnDelete()->after('banned_reason');
            $table->string('last_login_ip', 45)->nullable()->after('remember_token');
            $table->string('last_login_country', 2)->nullable()->after('last_login_ip');
            $table->string('last_login_city', 100)->nullable()->after('last_login_country');
            $table->decimal('last_login_lat', 10, 7)->nullable()->after('last_login_city');
            $table->decimal('last_login_lon', 10, 7)->nullable()->after('last_login_lat');
            $table->timestamp('last_login_at')->nullable()->after('last_login_lon');

            $table->index('banned_at');
            $table->index('last_login_ip');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['banned_by']);
            $table->dropColumn([
                'banned_at', 'banned_reason', 'banned_by',
                'last_login_ip', 'last_login_country', 'last_login_city', 'last_login_lat', 'last_login_lon', 'last_login_at',
            ]);
        });
    }
};
