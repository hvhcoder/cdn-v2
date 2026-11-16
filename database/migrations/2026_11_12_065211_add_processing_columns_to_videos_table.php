<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->string('r2_path')->nullable()->after('title');
            $table->unsignedSmallInteger('segment_time')->default(5)->after('status');
            $table->unsignedInteger('segment_count')->default(0)->after('duration_seconds');
            $table->text('error_message')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn(['r2_path', 'segment_time', 'segment_count', 'error_message']);
        });
    }
};
