<?php

namespace App\Jobs;

use App\Models\Video;
use App\Models\VideoSegment;
use App\Services\Storage\SourceCleaner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Bước 3: xác nhận mọi segment đã có URL, đánh dấu completed, xóa bản gốc trên R2 và thư mục tạm.
 * Nếu xóa R2 lỗi thì r2_path được giữ lại và lệnh `videos:cleanup-r2` (chạy mỗi giờ) sẽ dọn sau.
 */
class FinalizeVideoJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public Video $video) {}

    public function handle(SourceCleaner $cleaner): void
    {
        $video = $this->video->fresh();

        if ($video->segments()->where('status', '!=', VideoSegment::UPLOADED)->exists()) {
            UploadSegmentsJob::dispatch($video);

            return;
        }

        $video->update(['status' => Video::COMPLETED, 'error_message' => null]);

        $cleaner->purge($video);
        File::deleteDirectory($video->tempDirectory());
    }

    public function failed(Throwable $exception): void
    {
        $this->video->update(['status' => Video::FAILED, 'error_message' => mb_substr($exception->getMessage(), 0, 1000)]);
    }
}
