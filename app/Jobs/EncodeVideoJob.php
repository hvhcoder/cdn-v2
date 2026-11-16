<?php

namespace App\Jobs;

use App\Models\Video;
use App\Models\VideoSegment;
use App\Services\Billing\QuotaService;
use App\Services\Storage\R2Service;
use App\Services\Video\FfmpegService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Bước 1: tải video gốc từ R2, kiểm quota phút encode, cắt HLS, ghi video_segments (pending).
 */
class EncodeVideoJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 3700;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function __construct(public Video $video) {}

    public function handle(R2Service $r2, FfmpegService $ffmpeg, QuotaService $quota): void
    {
        $video = $this->video->fresh();
        if ($video === null || $video->r2_path === null) {
            return;
        }

        $video->update(['status' => Video::PROCESSING, 'error_message' => null]);

        $dir = $video->tempDirectory();
        File::ensureDirectoryExists($dir);
        $source = $dir.DIRECTORY_SEPARATOR.'source.mp4';

        if (! is_file($source)) {
            $r2->download($video->r2_path, $source);
        }

        $probe = $ffmpeg->probeVideo($source);
        $quota->assertCanEncode($video->user, (int) ceil($probe['duration']));
        $video->update(['duration_seconds' => (int) ceil($probe['duration'])]);

        File::deleteDirectory($dir.DIRECTORY_SEPARATOR.'hls');
        $result = $ffmpeg->convertToHls($source, $dir.DIRECTORY_SEPARATOR.'hls', $video->segment_time);

        DB::transaction(function () use ($video, $result) {
            $video->segments()->delete();

            foreach ($result['segments'] as $index => $segment) {
                $video->segments()->create([
                    'index' => $index,
                    'duration' => $segment['duration'],
                    'status' => VideoSegment::PENDING,
                ]);
            }

            $video->update(['segment_count' => count($result['segments'])]);
        });

        File::delete($source);

        UploadSegmentsJob::dispatch($video);
    }

    public function failed(Throwable $exception): void
    {
        $this->video->update(['status' => Video::FAILED, 'error_message' => mb_substr($exception->getMessage(), 0, 1000)]);
    }
}
