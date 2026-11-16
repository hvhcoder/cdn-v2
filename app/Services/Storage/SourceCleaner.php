<?php

namespace App\Services\Storage;

use App\Models\Video;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Xóa video gốc trên R2 sau khi xử lý xong (bản HLS đã nằm trên CDN).
 */
class SourceCleaner
{
    public function __construct(private R2Service $r2) {}

    /**
     * @return bool true khi đã xóa (hoặc không còn gì để xóa)
     */
    public function purge(Video $video): bool
    {
        if ($video->r2_path === null) {
            return true;
        }

        try {
            $this->r2->delete($video->r2_path);
        } catch (Throwable $e) {
            Log::warning("Không xóa được bản gốc R2 của video {$video->uuid}: {$e->getMessage()}");

            return false;
        }

        $video->update(['r2_path' => null]);

        return true;
    }
}
