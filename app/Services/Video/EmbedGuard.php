<?php

namespace App\Services\Video;

use App\Models\Plan;
use App\Models\User;
use App\Models\UserLog;
use App\Models\Video;
use App\Services\Billing\QuotaService;
use Illuminate\Support\Facades\URL;

/**
 * Khóa domain embed + token ký có hạn cho m3u8/embed.
 *
 * Logic:
 *   - Video bị khóa thủ công (embed_locked_at != null) → từ chối.
 *   - Gói có embed_locked_when_expired = true và user hết hạn → tự động khóa embed sau expiry_lock_days ngày.
 *   - Gói có allowed_domains → kiểm tra Referer / Origin.
 *   - Gói có embed_token_ttl_minutes → yêu cầu token ký (URL::signedRoute / temporarySignedRoute).
 */
class EmbedGuard
{
    public function __construct(private QuotaService $quota) {}

    /**
     * Kiểm tra video có được phép phát từ $origin không.
     * Trả về null nếu hợp lệ, chuỗi lý do nếu bị chặn.
     */
    public function check(Video $video, ?string $origin, ?User $owner = null): ?string
    {
        if ($video->isEmbedLocked()) {
            return 'embed_locked';
        }

        if ($owner !== null) {
            $plan = $this->quota->planFor($owner);

            if ($this->isExpiredAndShouldLock($owner, $plan, $video)) {
                $video->update(['embed_locked_at' => now()]);
                UserLog::record($owner->id, UserLog::EMBED_BLOCKED, ['video' => $video->uuid, 'reason' => 'subscription_expired']);

                return 'subscription_expired';
            }

            if (! $this->isDomainAllowed($video, $plan, $origin)) {
                return 'domain_not_allowed';
            }
        }

        return null;
    }

    /**
     * Tạo URL embed/m3u8 có token ký nếu gói yêu cầu.
     *
     * @param  'embed'|'m3u8'  $route
     */
    public function signedUrl(string $route, Video $video, ?Plan $plan = null): string
    {
        $ttl = $plan?->embed_token_ttl_minutes;

        if ($ttl !== null && $ttl > 0) {
            return URL::temporarySignedRoute($route, now()->addMinutes($ttl), $video);
        }

        return route($route, $video);
    }

    /**
     * Hết gói và gói hiện tại (Free) yêu cầu khóa embed → kiểm tra xem đã quá grace period chưa.
     */
    private function isExpiredAndShouldLock(User $owner, Plan $plan, Video $video): bool
    {
        if (! $plan->embed_locked_when_expired || ! $plan->isFree()) {
            return false;
        }

        // Tìm subscription vừa hết hạn gần nhất.
        $lastExpired = $owner->subscriptions()
            ->where('status', 'expired')
            ->latest('ends_at')
            ->first();

        if ($lastExpired === null) {
            return false;
        }

        $graceDays = $plan->expiry_lock_days ?? 0;

        return $lastExpired->ends_at->addDays($graceDays)->isPast()
            && $video->created_at <= $lastExpired->ends_at;
    }

    /**
     * Kiểm tra $origin so với allowed_domains của video và embed_domain_limit của gói.
     */
    private function isDomainAllowed(Video $video, Plan $plan, ?string $origin): bool
    {
        $allowed = $video->allowed_domains;

        if (empty($allowed)) {
            return true;
        }

        if ($origin === null) {
            return true; // direct / server-side request
        }

        $host = parse_url($origin, PHP_URL_HOST) ?: $origin;

        foreach ($allowed as $domain) {
            $pattern = '/^'.preg_quote(ltrim((string) $domain, '.'), '/').('$');
            if (str_starts_with((string) $domain, '.')) {
                $pattern = '/'.preg_quote(ltrim((string) $domain, '.'), '/').'/i';
                if (str_ends_with(strtolower((string) $host), strtolower(ltrim((string) $domain, '.')))) {
                    return true;
                }
            } elseif (strtolower((string) $host) === strtolower((string) $domain)) {
                return true;
            }
        }

        return false;
    }
}
