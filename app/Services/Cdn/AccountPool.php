<?php

namespace App\Services\Cdn;

use App\Models\UploadConfig;
use Illuminate\Database\Eloquent\Collection;

/**
 * Pool tài khoản CDN do admin quản lý: chọn tài khoản ít dùng gần đây nhất, loại tài khoản chết.
 */
class AccountPool
{
    public function __construct(private string $platform = 'tiktok') {}

    /**
     * @param  array<int, int>  $excludeIds  tài khoản đã thử và lỗi trong lần upload này
     */
    public function next(array $excludeIds = []): ?UploadConfig
    {
        return UploadConfig::where('platform', $this->platform)
            ->where('status', UploadConfig::ACTIVE)
            ->whereNotIn('id', $excludeIds)
            ->orderByRaw('last_used_at is not null')
            ->orderBy('last_used_at')
            ->first();
    }

    /**
     * Mọi tài khoản đang hoạt động, ít dùng gần đây nhất đứng trước.
     *
     * @return Collection<int, UploadConfig>
     */
    public function active(): Collection
    {
        return UploadConfig::where('platform', $this->platform)
            ->where('status', UploadConfig::ACTIVE)
            ->orderByRaw('last_used_at is not null')
            ->orderBy('last_used_at')
            ->get();
    }

    public function hasActive(): bool
    {
        return UploadConfig::where('platform', $this->platform)->where('status', UploadConfig::ACTIVE)->exists();
    }
}
