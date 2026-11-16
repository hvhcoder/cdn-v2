<?php

namespace App\Models;

use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'slug', 'price', 'duration_days', 'storage_limit_gb', 'max_videos', 'max_upload_size_mb',
    'monthly_encode_minutes', 'embed_domain_limit', 'can_use_folders', 'is_popular', 'is_active', 'sort',
])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    public const FREE_SLUG = 'free';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'can_use_folders' => 'boolean',
            'is_popular' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function isFree(): bool
    {
        return $this->slug === self::FREE_SLUG;
    }

    public static function free(): self
    {
        return self::where('slug', self::FREE_SLUG)->firstOrFail();
    }

    public function storageLimitBytes(): ?int
    {
        return $this->storage_limit_gb === null ? null : $this->storage_limit_gb * 1024 ** 3;
    }

    public function maxUploadSizeBytes(): ?int
    {
        return $this->max_upload_size_mb === null ? null : $this->max_upload_size_mb * 1024 ** 2;
    }
}
