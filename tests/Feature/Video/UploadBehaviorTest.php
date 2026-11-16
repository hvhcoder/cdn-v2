<?php

use App\Jobs\EncodeVideoJob;
use App\Models\Plan;
use App\Models\UploadConfig;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoSegment;
use App\Services\Cdn\CdnUploader;
use App\Services\Cdn\UploadFailedException;
use App\Services\Storage\R2Service;
use App\Services\Video\FfmpegService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Sleep;

class ScriptedUploader implements CdnUploader
{
    /** @var array<int, int> keys of every uploaded item, in order */
    public array $keys = [];

    public int $calls = 0;

    /** @param  callable(array): (string|UploadFailedException)  $decide */
    public function __construct(private $decide) {}

    public function upload(string $filePath, string $filename, UploadConfig $account): string
    {
        return 'https://cdn/single.png';
    }

    /** @var array<int, int> */
    public array $concurrencies = [];

    public function uploadStream(iterable $items, int $concurrency, callable $onResult): void
    {
        $this->concurrencies[] = $concurrency;

        foreach ($items as $item) {
            $this->calls++;
            $this->keys[] = $item['key'];
            $onResult($item['key'], ($this->decide)($item));
        }
    }
}

function runPipeline(ScriptedUploader $uploader, int $segments): Video
{
    app()->instance(CdnUploader::class, $uploader);

    app()->instance(FfmpegService::class, new class($segments) extends FfmpegService
    {
        public function __construct(private int $count) {}

        public function probeVideo(string $inputPath): array
        {
            return ['duration' => 10.0, 'width' => 1280, 'height' => 720];
        }

        public function convertToHls(string $inputPath, string $outputDir, int $segmentTime = 5): array
        {
            File::ensureDirectoryExists($outputDir);
            $segments = [];
            foreach (range(0, $this->count - 1) as $i) {
                $path = $outputDir.DIRECTORY_SEPARATOR.sprintf('segment_%05d.ts', $i);
                file_put_contents($path, random_bytes(500));
                $segments[] = ['path' => $path, 'duration' => 5.0];
            }

            return ['playlist' => $outputDir.'/playlist.m3u8', 'segments' => $segments];
        }
    });

    app()->instance(R2Service::class, new class(['region' => 'auto']) extends R2Service
    {
        public function download(string $key, string $localPath): void
        {
            file_put_contents($localPath, 'x');
        }

        public function delete(string $key): void {}
    });

    $video = Video::factory()->for(User::factory()->vip()->create())->create(['status' => Video::PROCESSING, 'r2_path' => 'raw/a.mp4']);
    EncodeVideoJob::dispatchSync($video);

    return $video;
}

afterEach(function () {
    Video::all()->each(fn (Video $video) => File::deleteDirectory($video->tempDirectory()));
});

beforeEach(function () {
    Plan::factory()->free()->create();
    Sleep::fake();
    config(['cdnpro.tiktok.concurrency' => 2]);
});

test('the uploader rests 3-5 seconds after every 30-45 files', function () {
    config(['cdnpro.upload.rest' => ['after_min' => 4, 'after_max' => 4, 'seconds_min' => 3, 'seconds_max' => 5]]);
    UploadConfig::factory()->create();

    $uploader = new ScriptedUploader(fn ($item) => "https://cdn/{$item['key']}.png");
    runPipeline($uploader, 10);

    expect($uploader->keys)->toHaveCount(10);
    Sleep::assertSleptTimes(2);
    Sleep::assertSlept(fn ($d) => $d->totalMicroseconds >= 3_000_000 && $d->totalMicroseconds <= 5_000_000, 2);
});

test('the default rest window is 30-45 files and 3-5 seconds', function () {
    expect(config('cdnpro.upload.rest'))->toBe(['after_min' => 30, 'after_max' => 45, 'seconds_min' => 3, 'seconds_max' => 5]);
});

test('no rest happens below the threshold or after the final batch', function () {
    UploadConfig::factory()->create();
    runPipeline(new ScriptedUploader(fn ($item) => 'https://cdn/x.png'), 20);
    Sleep::assertNeverSlept();

    config(['cdnpro.upload.rest' => ['after_min' => 4, 'after_max' => 4, 'seconds_min' => 3, 'seconds_max' => 5]]);
    Sleep::fake();
    runPipeline(new ScriptedUploader(fn ($item) => 'https://cdn/x.png'), 4);
    Sleep::assertNeverSlept();
});

test('a failing segment is retried at most 5 times, then the job fails', function () {
    config(['cdnpro.upload.retry_sleep_ms' => 500]);
    UploadConfig::factory()->create();

    $uploader = new ScriptedUploader(fn ($item) => new UploadFailedException('busy'));

    expect(fn () => runPipeline($uploader, 1))->toThrow(RuntimeException::class, '5 lần');

    expect($uploader->calls)->toBe(6);
    Sleep::assertSleptTimes(5);
    Sleep::assertSequence([
        Sleep::for(500)->milliseconds(), Sleep::for(1000)->milliseconds(), Sleep::for(1500)->milliseconds(),
        Sleep::for(2000)->milliseconds(), Sleep::for(2500)->milliseconds(),
    ]);
});

test('the retry limit is configurable', function () {
    config(['cdnpro.upload.max_retries' => 2]);
    UploadConfig::factory()->create();
    $uploader = new ScriptedUploader(fn ($item) => new UploadFailedException('busy'));

    expect(fn () => runPipeline($uploader, 1))->toThrow(RuntimeException::class, '2 lần');
    expect($uploader->calls)->toBe(3);
});

test('a retry moves the segment to a different account', function () {
    $bad = UploadConfig::factory()->create(['name' => 'A']);
    $good = UploadConfig::factory()->create(['name' => 'B']);

    $uploader = new ScriptedUploader(fn ($item) => $item['account']->id === $bad->id
        ? new UploadFailedException('A is flaky')
        : "https://cdn/{$item['key']}.png");

    $video = runPipeline($uploader, 20);

    expect($video->fresh()->status)->toBe(Video::COMPLETED);
    expect($video->segments()->where('upload_config_id', $bad->id)->count())->toBe(0);
    expect($video->segments()->where('upload_config_id', $good->id)->count())->toBe(20);
    expect($uploader->calls)->toBeLessThanOrEqual(40);
});

test('random rotation spreads segments over the accounts irregularly', function () {
    config(['cdnpro.tiktok.rotation' => 'random', 'cdnpro.tiktok.concurrency' => 8]);
    $a = UploadConfig::factory()->create(['name' => 'A']);
    $b = UploadConfig::factory()->create(['name' => 'B']);

    $video = runPipeline(new ScriptedUploader(fn ($item) => "https://cdn/{$item['key']}.png"), 48);

    $byAccount = $video->segments()->get()->groupBy('upload_config_id');
    expect($byAccount->keys()->sort()->values()->all())->toBe([$a->id, $b->id]);
    expect($byAccount[$a->id]->count() + $byAccount[$b->id]->count())->toBe(48);

    $order = $video->segments()->get()->pluck('upload_config_id')->all();
    expect($order)->not->toBe(array_map(fn ($i) => $i % 2 ? $b->id : $a->id, array_keys($order)));
})->repeat(1);

test('round robin rotation alternates the accounts', function () {
    config(['cdnpro.tiktok.rotation' => 'round_robin', 'cdnpro.tiktok.concurrency' => 4]);
    $a = UploadConfig::factory()->create(['name' => 'A']);
    $b = UploadConfig::factory()->create(['name' => 'B']);

    $video = runPipeline(new ScriptedUploader(fn ($item) => "https://cdn/{$item['key']}.png"), 4);

    expect($video->segments()->pluck('upload_config_id')->all())->toBe([$a->id, $b->id, $a->id, $b->id]);
});

test('segment length is configurable and kept within 1-60 seconds', function () {
    $vip = User::factory()->vip()->create();
    $r2 = Mockery::mock(R2Service::class)->makePartial();
    $r2->shouldReceive('presignUpload')->andReturn('https://r2.test/put');
    app()->instance(R2Service::class, $r2);

    foreach ([[8, 8], [0, 1], [500, 60]] as [$configured, $effective]) {
        config(['cdnpro.hls.segment_time' => $configured]);

        $uuid = $this->actingAs($vip)->postJson('/videos/presign', ['filename' => 'a.mp4', 'size' => 1000])->assertCreated()->json('video');

        expect(Video::where('uuid', $uuid)->value('segment_time'))->toBe($effective);
    }

    expect(config('cdnpro.hls.segment_time'))->toBe(500);
});

test('the playlist is built on the fly from the database and can be downloaded as a file', function () {
    $video = Video::factory()->create(['status' => Video::COMPLETED, 'title' => 'Ban Duyen']);
    VideoSegment::factory()->create(['video_id' => $video->id, 'index' => 0, 'duration' => 5, 'status' => 'uploaded', 'cdn_url' => 'https://cdn/0.png']);

    $this->get(route('m3u8', $video))->assertOk()->assertHeaderMissing('Content-Disposition')->assertSee('https://cdn/0.png');
    $this->get(route('m3u8', $video).'?download=1')->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="ban-duyen.m3u8"');
});
