<?php

namespace App\Jobs;

use App\Models\UploadConfig;
use App\Models\Video;
use App\Models\VideoSegment;
use App\Services\Cdn\AccountPool;
use App\Services\Cdn\CdnUploader;
use App\Services\Cdn\UploadFailedException;
use App\Services\Video\PixelPngCodec;
use App\Services\Video\PngHeaderGraftEncoder;
use Generator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use RuntimeException;
use Throwable;

/**
 * Bước 2: mã hóa segment thành PNG và đẩy lên TikTok với tối đa TIKTOK_CONCURRENCY ảnh cùng lúc theo kiểu cửa sổ
 * trượt (ảnh nào xong là ảnh kế tiếp bắt đầu ngay). Ghi DB ngay sau từng ảnh nên resume được; hết ngân sách
 * thời gian thì tự dispatch lại chính nó. Ảnh lỗi được thử lại ở các vòng sau, ưu tiên tài khoản khác.
 */
class UploadSegmentsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 600;

    /** Số ảnh đã upload thành công kể từ lần nghỉ gần nhất. */
    private int $sinceRest = 0;

    private int $restAfter = 0;

    private int $position = 0;

    /** @var array<int, array{account: UploadConfig, png: string, ts: string}> các ảnh đang bay, theo index */
    private array $inFlight = [];

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 60, 120, 300];
    }

    public function __construct(public Video $video) {}

    public function handle(CdnUploader $uploader, AccountPool $pool, PixelPngCodec $pixelCodec, PngHeaderGraftEncoder $headerEncoder): void
    {
        $video = $this->video->fresh();
        $hlsDir = $video->tempDirectory().DIRECTORY_SEPARATOR.'hls';

        // Chọn encoder dựa trên encoding_method
        $encoder = match ($video->encoding_method ?? Video::ENCODING_PIXEL) {
            Video::ENCODING_HEADER_GRAFT => $headerEncoder,
            default => $pixelCodec,
        };

        // Load PNG header template nếu dùng header-graft
        if ($video->encoding_method === Video::ENCODING_HEADER_GRAFT) {
            $templatePath = public_path('fake2.png');

            try {
                $headerEncoder->loadHeaderFromFile($templatePath);
                Log::info("Header Graft: Loaded PNG header from {$templatePath}");
            } catch (Throwable $e) {
                Log::error("Header Graft: Failed to load template: {$e->getMessage()}");
                throw $e;
            }
        }

        if (! is_dir($hlsDir)) {
            EncodeVideoJob::dispatch($video);

            return;
        }

        $deadline = microtime(true) + config('cdnpro.upload.job_time_budget_seconds');
        $concurrency = max(1, (int) config('cdnpro.tiktok.concurrency'));
        $retries = max(0, (int) config('cdnpro.upload.max_retries'));

        /** @var EloquentCollection<int, VideoSegment> $todo */
        $todo = $video->segments()->where('status', VideoSegment::PENDING)->get()->keyBy('index');
        $tried = [];
        $this->restAfter = $this->nextRestThreshold();

        for ($attempt = 0; $attempt <= $retries && $todo->isNotEmpty() && microtime(true) <= $deadline; $attempt++) {
            $accounts = $pool->active();
            if ($accounts->isEmpty()) {
                throw new RuntimeException('Không còn tài khoản TikTok hoạt động.');
            }

            try {
                $uploader->uploadStream(
                    $this->items($video, $todo, $hlsDir, $accounts, $tried, $encoder, $deadline),
                    $concurrency,
                    function (int|string $index, string|UploadFailedException $result) use ($todo, &$tried): void {
                        $this->settle((int) $index, $result, $todo, $tried);
                    },
                );
            } finally {
                $this->cleanUpInFlight();
            }

            if ($todo->isNotEmpty() && $attempt < $retries && microtime(true) <= $deadline) {
                Sleep::for(((int) config('cdnpro.upload.retry_sleep_ms')) * ($attempt + 1))->milliseconds();
            }
        }

        if ($todo->isEmpty()) {
            FinalizeVideoJob::dispatch($video);

            return;
        }

        // Còn ảnh chưa xong: hết thời gian của job thì job mới làm tiếp, hết số lần thử lại thì báo lỗi.
        if (microtime(true) > $deadline) {
            self::dispatch($video);

            return;
        }

        throw new RuntimeException('Segment #'.$todo->keys()->first()." upload thất bại sau {$retries} lần thử lại.");
    }

    /**
     * Sinh từng ảnh khi có chỗ trống: mã hóa PNG đúng lúc cần (không giữ sẵn cả lô trên đĩa), chọn tài khoản,
     * và nghỉ ngẫu nhiên sau mỗi 30-45 ảnh thành công (cấu hình được).
     *
     * @param  EloquentCollection<int, VideoSegment>  $todo
     * @param  Collection<int, UploadConfig>  $accounts
     * @param  array<int, array<int, int>>  $tried
     * @return Generator<int, array{key: int, path: string, filename: string, account: UploadConfig}>
     */
    private function items(Video $video, Collection $todo, string $hlsDir, Collection $accounts, array &$tried, PixelPngCodec|PngHeaderGraftEncoder $encoder, float $deadline): Generator
    {
        foreach ($todo->all() as $index => $segment) {
            if (microtime(true) > $deadline) {
                return;
            }

            if ($this->sinceRest >= $this->restAfter) {
                $this->rest();
                $this->sinceRest = 0;
                $this->restAfter = $this->nextRestThreshold();
            }

            $account = $this->pickAccount($accounts, $tried[$index] ?? []);
            $ts = $hlsDir.DIRECTORY_SEPARATOR.sprintf('segment_%05d.ts', $index);
            $png = $ts.'.png';

            File::put($png, $encoder->encode(File::get($ts)));
            $segment->increment('attempts');
            $this->inFlight[$index] = ['account' => $account, 'png' => $png, 'ts' => $ts];

            yield $index => ['key' => $index, 'path' => $png, 'filename' => "{$video->uuid}_{$index}.png", 'account' => $account];
        }
    }

    /**
     * @param  EloquentCollection<int, VideoSegment>  $todo
     * @param  array<int, array<int, int>>  $tried
     */
    private function settle(int $index, string|UploadFailedException $result, Collection $todo, array &$tried): void
    {
        $flight = $this->inFlight[$index];
        unset($this->inFlight[$index]);
        $bytes = is_file($flight['png']) ? (int) filesize($flight['png']) : 0;
        File::delete($flight['png']);

        if ($result instanceof UploadFailedException) {
            $tried[$index][] = $flight['account']->id;
            $this->recordFailure($flight['account'], $result);

            // Log chi tiết lỗi upload
            Log::warning("Segment #{$index} upload failed: {$result->getMessage()}", [
                'account_id' => $flight['account']->id,
                'png_size' => $bytes,
                'is_dead_account' => $result->accountIsDead,
            ]);

            return;
        }

        $segment = $todo[$index];
        $segment->update(['status' => VideoSegment::UPLOADED, 'cdn_url' => $result, 'upload_config_id' => $flight['account']->id]);
        $flight['account']->recordUpload($bytes);
        File::delete($flight['ts']);
        $todo->forget($index);
        $this->sinceRest++;
    }

    /**
     * Chọn tài khoản cho một ảnh: bỏ qua tài khoản đã chết và (nếu có thể) tài khoản đã lỗi với ảnh này.
     * Kiểu chọn: ngẫu nhiên (A: 1,4,8 - B: 2,3,5) hoặc lần lượt, theo TIKTOK_ROTATION.
     *
     * @param  Collection<int, UploadConfig>  $accounts
     * @param  array<int, int>  $triedIds
     */
    private function pickAccount(Collection $accounts, array $triedIds): UploadConfig
    {
        $alive = $accounts->filter(fn (UploadConfig $a) => $a->status === UploadConfig::ACTIVE)->values();
        if ($alive->isEmpty()) {
            throw new RuntimeException('Không còn tài khoản TikTok hoạt động.');
        }

        $candidates = $alive->reject(fn (UploadConfig $a) => in_array($a->id, $triedIds, true))->values();
        if ($candidates->isEmpty()) {
            $candidates = $alive;
        }

        return config('cdnpro.tiktok.rotation') === 'round_robin'
            ? $candidates[$this->position++ % $candidates->count()]
            : $candidates->random();
    }

    /**
     * Số ảnh upload được trước khi nghỉ (ngẫu nhiên để không đều đặn như máy).
     */
    private function nextRestThreshold(): int
    {
        $min = (int) config('cdnpro.upload.rest.after_min');
        $max = (int) config('cdnpro.upload.rest.after_max');

        return max(1, random_int(min($min, $max), max($min, $max)));
    }

    private function rest(): void
    {
        $min = (int) config('cdnpro.upload.rest.seconds_min');
        $max = (int) config('cdnpro.upload.rest.seconds_max');

        Sleep::for(random_int(min($min, $max) * 1000, max($min, $max) * 1000))->milliseconds();
    }

    /**
     * Xóa ảnh PNG còn sót nếu một vòng bị ngắt giữa chừng (lỗi hoặc hết thời gian).
     */
    private function cleanUpInFlight(): void
    {
        foreach ($this->inFlight as $flight) {
            File::delete($flight['png']);
        }
        $this->inFlight = [];
    }

    private function recordFailure(UploadConfig $account, UploadFailedException $e): void
    {
        if ($e->accountIsDead) {
            $account->markDead($e->getMessage());
        } else {
            $account->update(['last_error' => mb_substr($e->getMessage(), 0, 500)]);
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->video->update(['status' => Video::FAILED, 'error_message' => mb_substr($exception->getMessage(), 0, 1000)]);
    }
}
