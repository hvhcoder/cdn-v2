<?php

namespace App\Services\Video;

use App\Models\Video;
use App\Models\VideoSegment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Kiểm tra URL CDN của các segment còn sống hay không (HEAD request).
 * Nếu phát hiện segment chết → đánh dấu để re-upload job xử lý.
 */
class DeadUrlDetector
{
    /**
     * Kiểm tra tất cả segment của video; trả về số segment chết.
     */
    public function check(Video $video): int
    {
        if ($video->status !== Video::COMPLETED) {
            return 0;
        }

        $segments = $video->segments()->where('status', VideoSegment::UPLOADED)->whereNotNull('cdn_url')->get();
        $deadCount = 0;

        foreach ($segments as $segment) {
            if (! $this->isAlive((string) $segment->cdn_url)) {
                $segment->update(['status' => VideoSegment::PENDING]);
                $deadCount++;
            }
        }

        $video->update([
            'dead_checked_at' => now(),
            'dead_segments' => $deadCount,
        ]);

        if ($deadCount > 0) {
            Log::warning("Video {$video->uuid}: {$deadCount}/{$segments->count()} segment chết, cần re-upload.");
        }

        return $deadCount;
    }

    /**
     * Kiểm tra danh sách video chưa được kiểm tra trong N ngày.
     *
     * @return array{checked: int, dead_videos: int}
     */
    public function checkStale(int $afterDays = 1, int $limit = 50): array
    {
        $videos = Video::where('status', Video::COMPLETED)
            ->where(fn ($q) => $q->whereNull('dead_checked_at')->orWhere('dead_checked_at', '<', now()->subDays($afterDays)))
            ->limit($limit)
            ->get();

        $deadVideos = 0;

        foreach ($videos as $video) {
            if ($this->check($video) > 0) {
                $deadVideos++;
            }
        }

        return ['checked' => $videos->count(), 'dead_videos' => $deadVideos];
    }

    private function isAlive(string $url): bool
    {
        try {
            $response = Http::timeout(10)->withoutVerifying()->head($url);

            return $response->status() < 400;
        } catch (\Throwable) {
            return false;
        }
    }
}
