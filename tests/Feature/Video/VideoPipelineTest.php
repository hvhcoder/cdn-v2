<?php

use App\Exceptions\QuotaExceededException;
use App\Jobs\EncodeVideoJob;
use App\Jobs\UploadSegmentsJob;
use App\Models\Plan;
use App\Models\UploadConfig;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoSegment;
use App\Services\Cdn\AccountPool;
use App\Services\Cdn\CdnUploader;
use App\Services\Storage\R2Service;
use App\Services\Video\FfmpegService;
use App\Services\Video\PixelPngCodec;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class FakeFfmpeg extends FfmpegService
{
    public int $segments = 3;

    public float $duration = 14.0;

    public function probeVideo(string $inputPath): array
    {
        return ['duration' => $this->duration, 'width' => 1280, 'height' => 720];
    }

    public function convertToHls(string $inputPath, string $outputDir, int $segmentTime = 5): array
    {
        $segments = [];
        for ($i = 0; $i < $this->segments; $i++) {
            $path = $outputDir.DIRECTORY_SEPARATOR.sprintf('segment_%05d.ts', $i);
            File::ensureDirectoryExists($outputDir);
            file_put_contents($path, random_bytes(2000 + $i));
            $segments[] = ['path' => $path, 'duration' => 5.0];
        }

        return ['playlist' => $outputDir.'/playlist.m3u8', 'segments' => $segments];
    }
}

class FakeR2 extends R2Service
{
    public array $deleted = [];

    public function download(string $key, string $localPath): void
    {
        file_put_contents($localPath, 'fake-mp4');
    }

    public function size(string $key): ?int
    {
        return 1000;
    }

    public function delete(string $key): void
    {
        $this->deleted[] = $key;
    }

    public function presignUpload(string $key, ?int $minutes = null): string
    {
        return "https://r2.test/upload/{$key}";
    }
}

beforeEach(function () {
    Plan::factory()->free()->create();
    config(['cdnpro.upload.retry_sleep_ms' => 0]);

    $this->ffmpeg = new FakeFfmpeg;
    $this->r2 = new FakeR2(['region' => 'auto']);
    app()->instance(FfmpegService::class, $this->ffmpeg);
    app()->instance(R2Service::class, $this->r2);

    $this->user = User::factory()->create();
    $this->video = Video::factory()->for($this->user)->create([
        'status' => Video::PENDING, 'r2_path' => 'raw/a.mp4', 'size_bytes' => 1000, 'duration_seconds' => 0,
    ]);
});

afterEach(fn () => File::deleteDirectory($this->video->tempDirectory()));

function fakeTikTokOk(): void
{
    $n = 0;
    Http::fake(['*' => function () use (&$n) {
        $n++;

        return Http::response(['code' => 0, 'data' => ['src' => "https://x/seg{$n}~o.png"]]);
    }]);
}

test('full pipeline encodes, uploads every segment, finalizes and serves a dynamic m3u8', function () {
    UploadConfig::factory()->create();
    fakeTikTokOk();

    EncodeVideoJob::dispatchSync($this->video);

    $video = $this->video->fresh();
    expect($video->status)->toBe(Video::COMPLETED);
    expect($video->segment_count)->toBe(3);
    expect($video->duration_seconds)->toBe(14);
    expect($video->segments()->where('status', 'uploaded')->count())->toBe(3);
    expect($this->r2->deleted)->toBe(['raw/a.mp4']);
    expect(is_dir($video->tempDirectory()))->toBeFalse();

    $playlist = $this->get(route('m3u8', $video))->assertOk()
        ->assertHeader('Access-Control-Allow-Origin', '*')
        ->assertHeader('Content-Type', 'application/vnd.apple.mpegurl')
        ->getContent();

    expect($playlist)->toContain('#EXT-X-ENDLIST')->toContain('#EXTINF:5.000,');
    expect(substr_count($playlist, 'ibyteimg.com'))->toBe(3);
});

test('m3u8 is not served until the video is completed', function () {
    $this->get(route('m3u8', $this->video))->assertNotFound();
});

test('two videos with the same title never share a playlist', function () {
    $other = Video::factory()->create(['title' => $this->video->title, 'status' => Video::COMPLETED]);
    VideoSegment::factory()->create(['video_id' => $other->id, 'cdn_url' => 'https://cdn/other.png', 'status' => 'uploaded']);

    $this->get(route('m3u8', $other))->assertOk()->assertSee('https://cdn/other.png');
});

test('upload failure leaves finished segments saved and a retry resumes only the pending ones', function () {
    UploadConfig::factory()->create();
    $calls = 0;
    $recovered = false;
    Http::fake(['*' => function () use (&$calls, &$recovered) {
        $calls++;

        return ($calls <= 1 || $recovered)
            ? Http::response(['code' => 0, 'data' => ['src' => 'https://x/first~o.png']])
            : Http::response(['code' => 9, 'msg' => 'busy']);
    }]);

    expect(fn () => EncodeVideoJob::dispatchSync($this->video))->toThrow(RuntimeException::class);

    $video = $this->video->fresh();
    expect($video->segments()->where('status', 'uploaded')->count())->toBe(1);
    expect($video->segments()->where('status', 'pending')->count())->toBe(2);

    $recovered = true;
    (new UploadSegmentsJob($video))->handle(
        app(CdnUploader::class), app(AccountPool::class), app(PixelPngCodec::class), app(\App\Services\Video\PngHeaderGraftEncoder::class),
    );

    expect($video->fresh()->status)->toBe(Video::COMPLETED);
    Http::assertSentCount(15);
});

test('a dead account is marked and the next account takes over', function () {
    $bad = UploadConfig::factory()->create(['name' => 'bad', 'last_used_at' => null]);
    $good = UploadConfig::factory()->create(['name' => 'good', 'last_used_at' => now()]);

    Http::fake(function ($request) {
        return str_contains($request->header('Cookie')[0], 'bad-cookie')
            ? Http::response(['code' => 401, 'msg' => 'login required'], 401)
            : Http::response(['code' => 0, 'data' => ['src' => 'https://x/ok~o.png']]);
    });
    $bad->update(['config_data' => ['cookie' => 'csrftoken=t; id=bad-cookie']]);

    EncodeVideoJob::dispatchSync($this->video);

    expect($bad->fresh()->status)->toBe(UploadConfig::DEAD);
    expect($good->fresh()->uploads_count)->toBe(3);
    expect($this->video->fresh()->status)->toBe(Video::COMPLETED);
});

test('fails clearly when there is no active account', function () {
    Http::fake();

    expect(fn () => EncodeVideoJob::dispatchSync($this->video))->toThrow(RuntimeException::class, 'tài khoản');
});

test('encode job refuses when the monthly encode minutes quota is exceeded', function () {
    UploadConfig::factory()->create();
    $this->ffmpeg->duration = 31 * 60;
    Http::fake();

    expect(fn () => EncodeVideoJob::dispatchSync($this->video))->toThrow(QuotaExceededException::class);
    expect($this->video->fresh()->status)->toBe(Video::FAILED);
});
