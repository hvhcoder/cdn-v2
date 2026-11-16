<?php

namespace App\Services\Video;

use App\Models\Subscription;
use App\Models\User;
use App\Models\Video;
use App\Services\Billing\QuotaService;

/**
 * Thực thi chính sách hết hạn gói:
 *   - Khóa embed của video sau expiry_lock_days kể từ khi gói hết hạn.
 *   - Xóa video sau expiry_delete_days kể từ khi gói hết hạn.
 */
class ExpiryEnforcer
{
    public function __construct(
        private QuotaService $quota,
        private VideoDeleter $deleter,
    ) {}

    /**
     * Chạy toàn bộ chính sách. Trả về số video bị khóa embed và số video bị xóa.
     *
     * @return array{locked: int, deleted: int}
     */
    public function enforce(): array
    {
        $locked = 0;
        $deleted = 0;

        // Chỉ xử lý user đang dùng Free (gói cũ đã expired).
        $expiredUsers = User::whereHas(
            'subscriptions',
            fn ($q) => $q->where('status', Subscription::EXPIRED)
        )->get();

        foreach ($expiredUsers as $user) {
            $plan = $this->quota->planFor($user);

            // Vẫn còn gói trả phí → không làm gì.
            if (! $plan->isFree()) {
                continue;
            }

            $lastExpired = $user->subscriptions()
                ->where('status', Subscription::EXPIRED)
                ->latest('ends_at')
                ->first();

            if ($lastExpired === null) {
                continue;
            }

            $expiredAt = $lastExpired->ends_at;

            // Khóa embed.
            if ($plan->embed_locked_when_expired) {
                $lockAfter = $expiredAt->copy()->addDays($plan->expiry_lock_days ?? 0);

                if ($lockAfter->isPast()) {
                    $count = $user->videos()
                        ->whereNull('embed_locked_at')
                        ->update(['embed_locked_at' => now()]);
                    $locked += $count;
                }
            }

            // Xóa video.
            if ($plan->expiry_delete_days !== null) {
                $deleteAfter = $expiredAt->copy()->addDays($plan->expiry_delete_days);

                if ($deleteAfter->isPast()) {
                    $videos = $user->videos()->where('status', '!=', Video::PROCESSING)->get();
                    foreach ($videos as $video) {
                        $this->deleter->delete($video);
                        $deleted++;
                    }
                }
            }
        }

        return ['locked' => $locked, 'deleted' => $deleted];
    }
}
