<?php

use App\Services\Video\FfmpegService;
use App\Services\Video\PixelPngCodec;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

function ffmpegAvailable(): bool
{
    $process = new Process([config('cdnpro.ffmpeg.binary'), '-version']);

    try {
        $process->run();
    } catch (Throwable) {
        return false;
    }

    return $process->isSuccessful();
}

/**
 * Tạo video thử 6 giây với âm thanh theo tham số, trả về đường dẫn.
 *
 * @param  array<int, string>  $audioArgs
 */
function makeSource(string $dir, string $name, string $sineRate, array $audioArgs, string $filter = ''): string
{
    $path = "{$dir}/{$name}";
    $args = [config('cdnpro.ffmpeg.binary'), '-y', '-v', 'error', '-f', 'lavfi', '-i', 'testsrc=size=320x180:rate=25',
        '-f', 'lavfi', '-i', "sine=frequency=440:sample_rate={$sineRate}"];

    if ($filter !== '') {
        array_push($args, '-filter_complex', "[1:a]{$filter}[a]", '-map', '0:v', '-map', '[a]');
    }

    $args = array_merge($args, ['-t', '6', '-c:v', 'libx264', '-pix_fmt', 'yuv420p'], $audioArgs, [$path]);
    (new Process($args))->setTimeout(120)->mustRun();

    return $path;
}

/**
 * @return array<string, mixed> thông số stream âm thanh đầu tiên
 */
function audioInfo(string $file): array
{
    $process = new Process([config('cdnpro.ffmpeg.probe'), '-v', 'error', '-select_streams', 'a:0',
        '-show_entries', 'stream=codec_name,profile,sample_rate,channels', '-of', 'json', $file]);
    $process->mustRun();

    return json_decode($process->getOutput(), true)['streams'][0];
}

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/cdnpro-audio-'.uniqid();
    File::ensureDirectoryExists($this->dir);
});

afterEach(fn () => File::deleteDirectory($this->dir));

dataset('awkward audio sources', [
    'AAC 5.1' => [['sample' => '48000', 'args' => ['-c:a', 'aac', '-b:a', '384k'], 'filter' => 'pan=5.1|FL=c0|FR=c0|FC=c0|LFE=0*c0|BL=c0|BR=c0', 'name' => 'a.mp4']],
    'MP3 mono 22 kHz' => [['sample' => '22050', 'args' => ['-c:a', 'libmp3lame', '-ac', '1', '-b:a', '64k'], 'filter' => '', 'name' => 'b.mp4']],
    'AAC mono 24 kHz' => [['sample' => '24000', 'args' => ['-c:a', 'aac', '-ac', '1', '-b:a', '48k'], 'filter' => '', 'name' => 'c.mp4']],
    'AAC stereo 44.1 kHz' => [['sample' => '44100', 'args' => ['-c:a', 'aac', '-b:a', '128k'], 'filter' => '', 'name' => 'd.mp4']],
]);

test('audio is always normalised to AAC-LC stereo 48 kHz even when the video is copied', function (array $case) {
    $source = makeSource($this->dir, $case['name'], $case['sample'], $case['args'], $case['filter']);
    $ffmpeg = FfmpegService::fromConfig();

    expect($ffmpeg->needsTranscode($source))->toBeFalse('video is h264 so it should be copied, not re-encoded');

    $result = $ffmpeg->convertToHls($source, $this->dir.'/hls', 2);
    expect($result['segments'])->not->toBeEmpty();

    $codec = new PixelPngCodec(1024);
    $combined = '';
    foreach ($result['segments'] as $segment) {
        $ts = file_get_contents($segment['path']);
        $combined .= $codec->decode($codec->encode($ts));
    }
    file_put_contents($this->dir.'/all.ts', $combined);

    $audio = audioInfo($this->dir.'/all.ts');
    expect($audio['codec_name'])->toBe('aac')
        ->and($audio['profile'])->toBe('LC')
        ->and((int) $audio['sample_rate'])->toBe(48000)
        ->and((int) $audio['channels'])->toBe(2);
})->with('awkward audio sources')->skip(fn () => ! ffmpegAvailable(), 'ffmpeg is not available');

test('the video stream is copied untouched when it is already h264', function () {
    $source = makeSource($this->dir, 'v.mp4', '44100', ['-c:a', 'aac']);
    $ffmpeg = FfmpegService::fromConfig();

    $ffmpeg->convertToHls($source, $this->dir.'/hls', 2);

    $probe = new Process([config('cdnpro.ffmpeg.probe'), '-v', 'error', '-select_streams', 'v:0', '-show_entries', 'stream=codec_name', '-of', 'csv=p=0', $this->dir.'/hls/segment_00000.ts']);
    $probe->mustRun();

    expect(trim($probe->getOutput()))->toStartWith('h264');
})->skip(fn () => ! ffmpegAvailable(), 'ffmpeg is not available');
