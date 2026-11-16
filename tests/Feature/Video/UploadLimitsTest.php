<?php

use App\Exceptions\QuotaExceededException;
use App\Jobs\EncodeVideoJob;
use App\Models\Plan;
use App\Models\UploadConfig;
use App\Models\User;
use App\Models\Video;
use App\Services\Cdn\CdnUploader;
use App\Services\Storage\R2Service;
use App\Services\Video\FfmpegService;
use App\Services\Video\UploadLimits;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;

const GB = 1024 ** 3;

afterEach(function () {
    Video::all()->each(fn (Video $video) => File::deleteDirectory($video->tempDirectory()));
});

beforeEach(function () {
    Plan::factory()->free()->create();
    $this->vip = User::factory()->vip()->create();
    $this->limits = app(UploadLimits::class);

    $this->r2 = Mockery::mock(R2Service::class)->makePartial();
    $this->r2->shouldReceive('presignUpload')->andReturn('https://r2.test/put');
    app()->instance(R2Service::class, $this->r2);
});

function limitCode(callable $fn): ?string
{
    try {
        $fn();
    } catch (QuotaExceededException $e) {
        return $e->limit;
    }

    return null;
}

test('defaults are 5 GB per video and 15 GB in the queue, with the single put cap applied', function () {
    expect(config('cdnpro.limits.max_video_gb'))->toBe(5.0)->and(config('cdnpro.limits.max_queue_gb'))->toBe(15.0);

    // R2 chỉ nhận PUT đơn tới ~4.995 GiB nên 5 GB được chặn ở 4.99.
    expect($this->limits->maxVideoBytes())->toBe((int) (4.99 * GB));

    config(['cdnpro.limits.max_video_gb' => 2]);
    expect($this->limits->maxVideoBytes())->toBe(2 * GB);
    expect($this->limits->maxQueueBytes())->toBe(15 * GB);
});

test('a single video larger than the limit is rejected', function () {
    expect(limitCode(fn () => $this->limits->assertCanEnqueue($this->vip, 5 * GB)))->toBe('max_video_size');
    expect(limitCode(fn () => $this->limits->assertCanEnqueue($this->vip, 4 * GB)))->toBeNull();
});

test('the queue refuses videos once the total size would pass the queue limit', function () {
    config(['cdnpro.limits.max_queue_videos_per_user' => 50]);
    foreach (range(1, 3) as $i) {
        Video::factory()->create(['status' => Video::PROCESSING, 'size_bytes' => 4 * GB]);
    }

    expect(limitCode(fn () => $this->limits->assertCanEnqueue($this->vip, 4 * GB)))->toBe('queue_bytes');
    expect(limitCode(fn () => $this->limits->assertCanEnqueue($this->vip, 3 * GB)))->toBeNull();
    expect($this->limits->queueUsage())->toBe(['videos' => 3, 'bytes' => 12 * GB]);
});

test('queue video count limits apply globally and per user', function () {
    config(['cdnpro.limits.max_queue_videos' => 3, 'cdnpro.limits.max_queue_videos_per_user' => 2]);
    Video::factory()->count(2)->for($this->vip)->create(['status' => Video::PROCESSING, 'size_bytes' => 1]);

    expect(limitCode(fn () => $this->limits->assertCanEnqueue($this->vip, 1)))->toBe('queue_user_videos');
    expect(limitCode(fn () => $this->limits->assertCanEnqueue(User::factory()->vip()->create(), 1)))->toBeNull();

    Video::factory()->create(['status' => Video::PROCESSING, 'size_bytes' => 1]);
    expect(limitCode(fn () => $this->limits->assertCanEnqueue(User::factory()->vip()->create(), 1)))->toBe('queue_videos');
});

test('finished, failed and abandoned videos do not occupy the queue', function () {
    Video::factory()->create(['status' => Video::COMPLETED, 'size_bytes' => 5 * GB]);
    Video::factory()->create(['status' => Video::FAILED, 'size_bytes' => 5 * GB]);
    Video::factory()->create(['status' => Video::PENDING, 'size_bytes' => 5 * GB, 'created_at' => now()->subDays(2)]);
    Video::factory()->create(['status' => Video::PENDING, 'size_bytes' => 1 * GB]);

    expect($this->limits->queueUsage())->toBe(['videos' => 1, 'bytes' => 1 * GB]);
});

test('presign returns 422 for an oversized file and 429 when the queue is full', function () {
    $this->actingAs(User::factory()->admin()->create())->postJson('/videos/presign', ['filename' => 'big.mp4', 'size' => 5 * GB])
        ->assertStatus(422)->assertJsonPath('limit', 'max_video_size');

    config(['cdnpro.limits.max_queue_videos' => 0]);
    $this->actingAs($this->vip)->postJson('/videos/presign', ['filename' => 'a.mp4', 'size' => 1000])
        ->assertStatus(429)->assertJsonPath('limit', 'queue_videos');

    expect(Video::count())->toBe(0);
});

test('admin is also bound by the system queue limits', function () {
    $this->actingAs(User::factory()->admin()->create())->postJson('/videos/presign', ['filename' => 'big.mp4', 'size' => 6 * GB])
        ->assertStatus(422)->assertJsonPath('limit', 'max_video_size');
});

test('process keeps the uploaded file when the queue is full but deletes it when the real size is over the limit', function () {
    Queue::fake();
    UploadConfig::factory()->create();

    $video = Video::factory()->for($this->vip)->create(['status' => Video::PENDING, 'r2_path' => 'raw/a.mp4', 'size_bytes' => 100]);
    $this->r2->shouldReceive('size')->with('raw/a.mp4')->andReturn(1000);
    config(['cdnpro.limits.max_queue_videos' => 0]);

    $this->actingAs($this->vip)->postJson("/videos/{$video->uuid}/process")->assertStatus(429);
    expect(Video::find($video->id))->not->toBeNull();

    config(['cdnpro.limits.max_queue_videos' => 20]);
    $big = Video::factory()->for($this->vip)->create(['status' => Video::PENDING, 'r2_path' => 'raw/b.mp4', 'size_bytes' => 100]);
    $this->r2->shouldReceive('size')->with('raw/b.mp4')->andReturn(6 * GB);
    $this->r2->shouldReceive('delete')->once()->with('raw/b.mp4');

    $this->actingAs($this->vip)->postJson("/videos/{$big->uuid}/process")->assertStatus(422);
    expect(Video::find($big->id))->toBeNull();
    Queue::assertNothingPushed();
});

test('a video reprocessed from the queue does not count itself against the limit', function () {
    config(['cdnpro.limits.max_queue_videos_per_user' => 1]);
    $video = Video::factory()->for($this->vip)->create(['status' => Video::PENDING, 'size_bytes' => 1000]);

    expect(limitCode(fn () => $this->limits->assertCanEnqueue($this->vip, 1000, $video)))->toBeNull();
    expect(limitCode(fn () => $this->limits->assertCanEnqueue($this->vip, 1000)))->toBe('queue_user_videos');
});

test('the upload page shows the limits and the queue usage', function () {
    Video::factory()->create(['status' => Video::PROCESSING, 'size_bytes' => 3 * GB]);

    $this->actingAs($this->vip)->get('/videos/create')->assertOk()
        ->assertSee('Tối đa mỗi video')->assertSee('4.99 GB')->assertSee('3 / 15 GB')->assertSee('1 / 20');
});

test('the upload stream is opened with TIKTOK_CONCURRENCY slots and every segment is uploaded', function () {
    config(['cdnpro.tiktok.concurrency' => 2, 'cdnpro.upload.retry_sleep_ms' => 0]);
    UploadConfig::factory()->create();

    $uploader = new class implements CdnUploader
    {
        public function upload(string $filePath, string $filename, UploadConfig $account): string
        {
            return 'https://cdn/single.png';
        }

        public int $concurrency = 0;

        public array $keys = [];

        public function uploadStream(iterable $items, int $concurrency, callable $onResult): void
        {
            $this->concurrency = $concurrency;

            foreach ($items as $item) {
                $this->keys[] = $item['key'];
                $onResult($item['key'], "https://cdn/{$item['key']}.png");
            }
        }
    };
    app()->instance(CdnUploader::class, $uploader);

    $ffmpeg = new class extends FfmpegService
    {
        public function probeVideo(string $inputPath): array
        {
            return ['duration' => 25.0, 'width' => 1280, 'height' => 720];
        }

        public function convertToHls(string $inputPath, string $outputDir, int $segmentTime = 5): array
        {
            File::ensureDirectoryExists($outputDir);
            $segments = [];
            foreach (range(0, 4) as $i) {
                $path = $outputDir.DIRECTORY_SEPARATOR.sprintf('segment_%05d.ts', $i);
                file_put_contents($path, random_bytes(1000));
                $segments[] = ['path' => $path, 'duration' => 5.0];
            }

            return ['playlist' => $outputDir.'/playlist.m3u8', 'segments' => $segments];
        }
    };
    app()->instance(FfmpegService::class, $ffmpeg);

    $r2 = new class(['region' => 'auto']) extends R2Service
    {
        public function download(string $key, string $localPath): void
        {
            file_put_contents($localPath, 'x');
        }

        public function delete(string $key): void {}
    };
    app()->instance(R2Service::class, $r2);

    $video = Video::factory()->for($this->vip)->create(['status' => Video::PROCESSING, 'r2_path' => 'raw/a.mp4', 'duration_seconds' => 0]);

    EncodeVideoJob::dispatchSync($video);

    expect($uploader->concurrency)->toBe(2)->and($uploader->keys)->toBe([0, 1, 2, 3, 4]);
    expect($video->fresh()->status)->toBe(Video::COMPLETED);
    expect($video->segments()->where('status', 'uploaded')->count())->toBe(5);
});
