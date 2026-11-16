<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('upload_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('platform')->default('tiktok');
            $table->text('cookie');
            $table->string('status')->default('active');
            $table->text('last_error')->nullable();
            $table->unsignedBigInteger('uploads_count')->default(0);
            $table->unsignedBigInteger('uploaded_bytes')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['platform', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('upload_accounts');
    }
};
