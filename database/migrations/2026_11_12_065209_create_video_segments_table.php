<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('index');
            $table->decimal('duration', 8, 3);
            $table->text('cdn_url')->nullable();
            $table->foreignId('upload_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamps();

            $table->unique(['video_id', 'index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_segments');
    }
};
