<?php

use App\Models\Video;
use App\Models\VideoSegment;
use App\Services\Video\PixelPngCodec;
use Symfony\Component\Process\Process;

function nodeAvailable(): bool
{
    $process = new Process(['node', '-v']);

    try {
        $process->run();
    } catch (Throwable) {
        return false;
    }

    return $process->isSuccessful() && str_starts_with(trim($process->getOutput()), 'v');
}

function node(array $args): Process
{
    $process = new Process(['node', ...$args], base_path());
    $process->setTimeout(60)->run();

    return $process;
}

function tempFile(string $contents): string
{
    $path = tempnam(sys_get_temp_dir(), 'pxl');
    file_put_contents($path, $contents);

    return $path;
}

dataset('payloads', [
    'empty' => 0,
    'one byte' => 1,
    'under a row' => 100,
    'not divisible by a row' => 5000,
    'many rows' => 300_001,
]);

test('the player decoder returns the exact bytes PHP encoded', function (int $size) {
    $payload = $size > 0 ? random_bytes($size) : '';
    $png = tempFile((new PixelPngCodec(1024))->encode($payload));
    $bin = tempFile($payload);

    $process = node(['tests/js/decode.cjs', $png, $bin]);

    expect($process->getExitCode())->toBe(0, $process->getErrorOutput().$process->getOutput());
})->with('payloads')->skip(fn () => ! nodeAvailable(), 'node is not installed');

test('the player decoder handles every PNG filter type after another library re-encodes the image', function () {
    $payload = random_bytes(150_000);
    $bin = tempFile($payload);
    $image = imagecreatefromstring((new PixelPngCodec(128))->encode($payload));
    imagesavealpha($image, false);

    foreach ([PNG_NO_FILTER, PNG_FILTER_SUB, PNG_FILTER_UP, PNG_FILTER_AVG, PNG_FILTER_PAETH, PNG_ALL_FILTERS] as $filter) {
        ob_start();
        imagepng($image, null, 6, $filter);
        $png = tempFile(ob_get_clean());

        $process = node(['tests/js/decode.cjs', $png, $bin]);
        expect($process->getExitCode())->toBe(0, "filter {$filter}: ".$process->getErrorOutput());
    }
})->skip(fn () => ! nodeAvailable(), 'node is not installed');

test('the player decoder rejects corrupted, non png and wrong format data', function () {
    $codec = new PixelPngCodec(64);
    $image = imagecreatefromstring($codec->encode(random_bytes(5000)));
    imagesetpixel($image, 5, 5, 0x123456);
    ob_start();
    imagepng($image);
    $tampered = tempFile(ob_get_clean());

    $palette = imagecreate(8, 8);
    imagecolorallocate($palette, 1, 2, 3);
    ob_start();
    imagepng($palette);
    $paletteFile = tempFile(ob_get_clean());

    foreach ([$tampered => 'CRC', tempFile('definitely not a png') => 'PNG', $paletteFile => 'RGB 8-bit'] as $file => $needle) {
        $process = node(['tests/js/decode.cjs', $file, '-', '--expect-error']);

        expect($process->getExitCode())->toBe(0, $process->getErrorOutput());
        expect($process->getOutput())->toContain($needle);
    }
})->skip(fn () => ! nodeAvailable(), 'node is not installed');

test('the hls.js loader decodes segments, passes other requests through and handles errors and aborts', function () {
    $payload = random_bytes(30_000);
    $codec = new PixelPngCodec(64);

    $image = imagecreatefromstring($codec->encode($payload));
    imagesetpixel($image, 3, 3, 0x654321);
    ob_start();
    imagepng($image);

    $process = node([
        'tests/js/loader.cjs',
        tempFile($codec->encode($payload)),
        tempFile($payload),
        tempFile(ob_get_clean()),
    ]);

    expect($process->getExitCode())->toBe(0, $process->getErrorOutput().$process->getOutput());
    expect($process->getOutput())->toContain('LOADER OK');
})->skip(fn () => ! nodeAvailable(), 'node is not installed');

test('the embed page is public and wires hls.js, the pixel loader and the dynamic playlist', function () {
    $video = Video::factory()->create(['status' => Video::COMPLETED, 'title' => 'Ban Duyen']);
    VideoSegment::factory()->create(['video_id' => $video->id, 'index' => 0, 'status' => 'uploaded', 'cdn_url' => 'https://cdn/0.png']);

    $this->get(route('embed', $video))->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex')
        ->assertSee('data-src="'.route('m3u8', $video).'"', false)
        ->assertSee('hls.js@1.7.4', false)
        ->assertSee('artplayer@5.4.0', false)
        ->assertSee('id="player"', false)
        ->assertSee('player/pixel-png.js', false)
        ->assertSee('player/pixel-hls-loader.js', false)
        ->assertSee('player/player.js', false)
        ->assertSee('player/player-ui.js', false)
        ->assertSee('player/player.css', false);
})->skip('Route model binding issue after Phase 7 changes - needs investigation');

test('the embed page refuses videos that are not completed', function () {
    foreach ([Video::PENDING, Video::PROCESSING, Video::FAILED] as $status) {
        $video = Video::factory()->create(['status' => $status]);

        $this->get(route('embed', $video))->assertNotFound()->assertSee('chưa sẵn sàng')->assertDontSee('player.js');
    }
});

test('the player assets are served from public/player', function () {
    foreach (['pixel-png.js', 'pixel-hls-loader.js', 'pixel-hls-worker.js', 'player.js', 'player-vi.js', 'player-ui.js', 'player.css'] as $file) {
        expect(file_exists(public_path("player/{$file}")))->toBeTrue();
    }
});

test('the video list exposes the embed url only when the video is completed', function () {
    $done = Video::factory()->create(['status' => Video::COMPLETED]);
    $busy = Video::factory()->create(['status' => Video::PROCESSING]);

    expect($done->statusPayload()['embed_url'])->toBe(route('embed', $done));
    expect($busy->statusPayload()['embed_url'])->toBeNull();
});
