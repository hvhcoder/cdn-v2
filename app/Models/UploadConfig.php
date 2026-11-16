<?php

namespace App\Models;

use App\Services\UploadPlatforms;
use Database\Factories\UploadConfigFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['platform', 'name', 'status', 'config_data', 'last_error'])]
#[Hidden(['config_data'])]
class UploadConfig extends Model
{
    /** @use HasFactory<UploadConfigFactory> */
    use HasFactory;

    public const R2 = 'r2';

    public const TIKTOK = 'tiktok';

    public const ACTIVE = 'active';

    public const DISABLED = 'disabled';

    public const DEAD = 'dead';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'config_data' => 'encrypted:array',
            'is_default' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<UploadConfig>  $query
     */
    public function scopeForPlatform(Builder $query, string $platform): void
    {
        $query->where('platform', $platform);
    }

    /**
     * @param  Builder<UploadConfig>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', self::ACTIVE);
    }

    public function data(string $key, mixed $default = null): mixed
    {
        return $this->config_data[$key] ?? $default;
    }

    public function platformLabel(): string
    {
        return UploadPlatforms::get($this->platform)['label'] ?? $this->platform;
    }

    /**
     * Cấu hình mặc định đang bật của một platform kiểu "single" (vd R2).
     */
    public static function defaultFor(string $platform): ?self
    {
        return self::forPlatform($platform)->active()->where('is_default', true)->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function toR2Config(): array
    {
        return [
            'endpoint' => $this->data('endpoint'),
            'bucket' => $this->data('bucket'),
            'access_key' => $this->data('access_key'),
            'secret_key' => $this->data('secret_key'),
            'region' => $this->data('region') ?: 'auto',
            'presign_minutes' => config('cdnpro.r2.presign_minutes'),
        ];
    }

    /**
     * Đặt làm mặc định của platform và bật nó lên.
     */
    public function makeDefault(): void
    {
        self::forPlatform($this->platform)->where('id', '!=', $this->id)->update(['is_default' => false]);
        $this->forceFill(['is_default' => true, 'status' => self::ACTIVE])->save();
    }

    /**
     * Với platform "single": luôn có đúng một mặc định đang bật nếu còn cấu hình khả dụng.
     */
    public static function ensureDefault(string $platform): void
    {
        if (! UploadPlatforms::isSingle($platform) || self::defaultFor($platform)) {
            return;
        }

        self::forPlatform($platform)->update(['is_default' => false]);
        self::forPlatform($platform)->active()->orderBy('id')->first()?->makeDefault();
    }

    public function markDead(string $reason): void
    {
        $this->update(['status' => self::DEAD, 'last_error' => mb_substr($reason, 0, 500)]);
    }

    public function recordUpload(int $bytes): void
    {
        $this->increment('uploads_count');
        $this->increment('uploaded_bytes', $bytes);
        $this->forceFill(['last_used_at' => now()])->save();
    }
}
