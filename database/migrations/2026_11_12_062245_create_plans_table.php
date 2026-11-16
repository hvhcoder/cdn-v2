<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cac cot gioi han = null nghia la khong gioi han.
     */
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedBigInteger('price')->default(0);
            $table->unsignedInteger('duration_days')->nullable();
            $table->unsignedInteger('storage_limit_gb')->nullable();
            $table->unsignedInteger('max_videos')->nullable();
            $table->unsignedInteger('max_upload_size_mb')->nullable();
            $table->unsignedInteger('monthly_encode_minutes')->nullable();
            $table->unsignedInteger('embed_domain_limit')->nullable();
            $table->boolean('can_use_folders')->default(false);
            $table->boolean('is_popular')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
