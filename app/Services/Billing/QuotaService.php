<?php

namespace App\Services\Billing;

use App\Exceptions\QuotaExceededException;
use App\Models\Plan;
use App\Models\User;

class QuotaService
{
    /**
     * Goi dang ap dung: goi tra phi con han, neu khong thi Free.
     */
    public function planFor(User $user): Plan
    {
        $subscription = $user->subscriptions()
            ->current()
            ->with('plan')
            ->latest('ends_at')
            ->first();

        return $subscription?->plan ?? Plan::free();
    }

    /**
     * @return array{storage_bytes: int, video_count: int, encode_minutes: int}
     */
    public function usage(User $user): array
    {
        $videos = $user->videos();

        return [
            'storage_bytes' => (int) (clone $videos)->sum('size_bytes'),
            'video_count' => (clone $videos)->count(),
            'encode_minutes' => (int) ceil(
                (clone $videos)->where('created_at', '>=', now()->startOfMonth())->sum('duration_seconds') / 60
            ),
        ];
    }

    public function canUseFolders(User $user): bool
    {
        return $user->isAdmin() || $this->planFor($user)->can_use_folders;
    }

    /**
     * Kiem tra truoc khi cap presigned URL / bat dau job. Admin khong bi gioi han.
     *
     * @throws QuotaExceededException
     */
    public function assertCanUpload(User $user, int $sizeBytes, int $durationSeconds = 0): void
    {
        if ($user->isAdmin()) {
            return;
        }

        $plan = $this->planFor($user);
        $usage = $this->usage($user);

        if ($plan->maxUploadSizeBytes() !== null && $sizeBytes > $plan->maxUploadSizeBytes()) {
            throw new QuotaExceededException('max_upload_size', "Vượt gói: file tối đa {$plan->max_upload_size_mb} MB.");
        }

        if ($plan->max_videos !== null && $usage['video_count'] >= $plan->max_videos) {
            throw new QuotaExceededException('max_videos', "Vượt gói: tối đa {$plan->max_videos} video.");
        }

        if ($plan->storageLimitBytes() !== null && $plan->storageLimitBytes() < $usage['storage_bytes'] + $sizeBytes) {
            throw new QuotaExceededException('storage', "Vượt gói: dung lượng tối đa {$plan->storage_limit_gb} GB.");
        }

        $newMinutes = (int) ceil($durationSeconds / 60);
        if ($plan->monthly_encode_minutes !== null && $plan->monthly_encode_minutes < $usage['encode_minutes'] + $newMinutes) {
            throw new QuotaExceededException('encode_minutes', "Vượt gói: tối đa {$plan->monthly_encode_minutes} phút encode mỗi tháng.");
        }
    }

    /**
     * Kiem tra phut encode thang nay truoc khi cat video (sau khi biet thoi luong that).
     *
     * @throws QuotaExceededException
     */
    public function assertCanEncode(User $user, int $durationSeconds): void
    {
        if ($user->isAdmin()) {
            return;
        }

        $plan = $this->planFor($user);
        $minutes = $this->usage($user)['encode_minutes'] + (int) ceil($durationSeconds / 60);

        if ($plan->monthly_encode_minutes !== null && $minutes > $plan->monthly_encode_minutes) {
            throw new QuotaExceededException('encode_minutes', "Vượt gói: tối đa {$plan->monthly_encode_minutes} phút encode mỗi tháng.");
        }
    }

    /**
     * Kiem tra sau khi biet dung luong that cua file da upload (video da co dong trong DB).
     *
     * @throws QuotaExceededException
     */
    public function assertStoredVideoAllowed(User $user, int $actualSizeBytes): void
    {
        if ($user->isAdmin()) {
            return;
        }

        $plan = $this->planFor($user);

        if ($plan->maxUploadSizeBytes() !== null && $actualSizeBytes > $plan->maxUploadSizeBytes()) {
            throw new QuotaExceededException('max_upload_size', "Vượt gói: file tối đa {$plan->max_upload_size_mb} MB.");
        }

        if ($plan->storageLimitBytes() !== null && $this->usage($user)['storage_bytes'] > $plan->storageLimitBytes()) {
            throw new QuotaExceededException('storage', "Vượt gói: dung lượng tối đa {$plan->storage_limit_gb} GB.");
        }
    }
}
