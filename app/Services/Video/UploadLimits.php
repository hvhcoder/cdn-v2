<?php

namespace App\Services\Video;

use App\Exceptions\QuotaExceededException;
use App\Models\User;
use App\Models\Video;
use Illuminate\Database\Eloquent\Builder;

/**
 * Giới hạn hệ thống cho hàng đợi upload/xử lý (độc lập với quota theo gói): bảo vệ ổ đĩa và CPU của server.
 */
class UploadLimits
{
    /** R2 chỉ nhận PUT đơn tới ~4.995 GiB; vượt mức này phải dùng multipart. */
    private const SINGLE_PUT_MAX_GB = 4.99;

    public function maxVideoBytes(): int
    {
        return (int) (min((float) config('cdnpro.limits.max_video_gb'), self::SINGLE_PUT_MAX_GB) * 1024 ** 3);
    }

    /**
     * Độ dài mỗi đoạn HLS (giây), lấy từ HLS_SEGMENT_TIME và giới hạn trong 1-60.
     */
    public function segmentTime(): int
    {
        return max(1, min(60, (int) config('cdnpro.hls.segment_time')));
    }

    public function maxQueueBytes(): int
    {
        return (int) ((float) config('cdnpro.limits.max_queue_gb') * 1024 ** 3);
    }

    /**
     * Video đang chiếm chỗ trong hàng đợi: đang xử lý, hoặc vừa xin link upload trong 24h qua.
     *
     * @return Builder<Video>
     */
    private function active(?Video $exclude = null): Builder
    {
        return Video::query()
            ->where(fn ($q) => $q->where('status', Video::PROCESSING)
                ->orWhere(fn ($p) => $p->where('status', Video::PENDING)->where('created_at', '>=', now()->subDay())))
            ->when($exclude, fn ($q) => $q->whereKeyNot($exclude->getKey()));
    }

    /**
     * @return array{videos: int, bytes: int}
     */
    public function queueUsage(?Video $exclude = null): array
    {
        return [
            'videos' => $this->active($exclude)->count(),
            'bytes' => (int) $this->active($exclude)->sum('size_bytes'),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function summary(): array
    {
        $usage = $this->queueUsage();

        return [
            'max_video_bytes' => $this->maxVideoBytes(),
            'max_queue_bytes' => $this->maxQueueBytes(),
            'max_queue_videos' => (int) config('cdnpro.limits.max_queue_videos'),
            'segment_time' => $this->segmentTime(),
            'queue_videos' => $usage['videos'],
            'queue_bytes' => $usage['bytes'],
        ];
    }

    /**
     * @throws QuotaExceededException
     */
    public function assertCanEnqueue(User $user, int $sizeBytes, ?Video $exclude = null): void
    {
        if ($sizeBytes > $this->maxVideoBytes()) {
            throw new QuotaExceededException('max_video_size', 'Mỗi video tối đa '.$this->gb($this->maxVideoBytes()).' GB.');
        }

        $usage = $this->queueUsage($exclude);

        if ($usage['videos'] >= (int) config('cdnpro.limits.max_queue_videos')) {
            throw new QuotaExceededException('queue_videos', 'Hàng đợi đang đầy ('.$usage['videos'].' video), vui lòng thử lại sau ít phút.');
        }

        $mine = $this->active($exclude)->where('user_id', $user->id)->count();
        if ($mine >= (int) config('cdnpro.limits.max_queue_videos_per_user')) {
            throw new QuotaExceededException('queue_user_videos', 'Bạn đang có '.$mine.' video trong hàng đợi (tối đa '.config('cdnpro.limits.max_queue_videos_per_user').'), hãy đợi xử lý xong bớt.');
        }

        if ($this->maxQueueBytes() < $usage['bytes'] + $sizeBytes) {
            throw new QuotaExceededException('queue_bytes', 'Hàng đợi đã đạt giới hạn '.$this->gb($this->maxQueueBytes()).' GB, vui lòng thử lại khi các video trước xử lý xong.');
        }
    }

    private function gb(int $bytes): string
    {
        return rtrim(rtrim(number_format($bytes / 1024 ** 3, 2, '.', ''), '0'), '.');
    }
}
