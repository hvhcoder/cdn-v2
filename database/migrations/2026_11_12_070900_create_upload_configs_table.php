<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gộp r2_configs và upload_accounts thành một bảng upload_configs (theo platform), giữ nguyên dữ liệu đã nhập.
     */
    public function up(): void
    {
        Schema::create('upload_configs', function (Blueprint $table) {
            $table->id();
            $table->string('platform');
            $table->string('name');
            $table->string('status')->default('active');
            $table->boolean('is_default')->default(false);
            $table->longText('config_data');
            $table->text('last_error')->nullable();
            $table->unsignedBigInteger('uploads_count')->default(0);
            $table->unsignedBigInteger('uploaded_bytes')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['platform', 'status']);
        });

        $this->copyOldData();

        Schema::table('video_segments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('upload_account_id');
        });
        Schema::table('video_segments', function (Blueprint $table) {
            $table->foreignId('upload_config_id')->nullable()->after('cdn_url')->constrained('upload_configs')->nullOnDelete();
        });

        Schema::dropIfExists('r2_configs');
        Schema::dropIfExists('upload_accounts');
    }

    public function down(): void
    {
        Schema::dropIfExists('upload_configs');
    }

    private function copyOldData(): void
    {
        $now = now();

        if (Schema::hasTable('r2_configs')) {
            foreach (DB::table('r2_configs')->get() as $row) {
                DB::table('upload_configs')->insert([
                    'platform' => 'r2',
                    'name' => $row->name,
                    'status' => 'active',
                    'is_default' => (bool) $row->is_active,
                    'config_data' => Crypt::encryptString(json_encode([
                        'endpoint' => $row->endpoint,
                        'bucket' => $row->bucket,
                        'region' => $row->region,
                        'access_key' => Crypt::decryptString($row->access_key),
                        'secret_key' => Crypt::decryptString($row->secret_key),
                    ])),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if (Schema::hasTable('upload_accounts')) {
            foreach (DB::table('upload_accounts')->get() as $row) {
                DB::table('upload_configs')->insert([
                    'platform' => 'tiktok',
                    'name' => $row->name,
                    'status' => $row->status,
                    'is_default' => false,
                    'config_data' => Crypt::encryptString(json_encode(['cookie' => Crypt::decryptString($row->cookie)])),
                    'last_error' => $row->last_error,
                    'uploads_count' => $row->uploads_count,
                    'uploaded_bytes' => $row->uploaded_bytes,
                    'last_used_at' => $row->last_used_at,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
};
