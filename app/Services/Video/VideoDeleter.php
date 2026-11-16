<?php

namespace App\Services\Video;

use App\Models\Video;
use App\Services\Storage\R2Service;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Xóa video và mọi thứ liên quan (file gốc còn trên R2, thư mục tạm, các segment trong DB).
 */
class VideoDeleter
{
    public function __construct(private R2Service $r2) {}

    public function delete(Video $video): void
    {
        if ($video->r2_path !== null) {
            try {
                $this->r2->delete($video->r2_path);
            } catch (Throwable $e) {
                Log::warning("Không xóa được file gốc R2 của video {$video->uuid}: {$e->getMessage()}");
            }
        }

        File::deleteDirectory($video->tempDirectory());
        $video->delete();
    }
}
