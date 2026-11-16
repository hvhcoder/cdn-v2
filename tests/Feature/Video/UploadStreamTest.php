<?php

use App\Models\UploadConfig;
use App\Services\Cdn\TikTokUploader;
use App\Services\Cdn\UploadFailedException;
use Symfony\Component\Process\Process;

function startSlowServer(array $env = []): array
{
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
    fclose($socket);

    $process = new Process(['node', 'tests/js/slow-server.cjs', (string) $port], base_path(), $env);
    $process->start();

    $deadline = microtime(true) + 8;
    while (! str_contains($process->getOutput(), 'READY') && microtime(true) < $deadline) {
        usleep(50_000);
    }

    return [$process, $port];
}

function nodeInstalled(): bool
{
    $process = new Process(['node', '-v']);

    try {
        $process->run();
    } catch (Throwable) {
        return false;
    }

    return $process->isSuccessful();
}

function tinyFiles(int $count): array
{
    $files = [];
    foreach (range(0, $count - 1) as $i) {
        $files[$i] = tempnam(sys_get_temp_dir(), 'stream');
        file_put_contents($files[$i], 'png-'.$i);
    }

    return $files;
}

test('the stream runs up to the limit of requests at once and is much faster than one at a time', function () {
    [$server, $port] = startSlowServer(['FAST_MS' => '300']);
    config(['cdnpro.tiktok.endpoint' => "http://127.0.0.1:{$port}/upload"]);

    $files = tinyFiles(12);
    $account = UploadConfig::factory()->make();
    $results = [];
    $pulled = 0;
    $done = 0;
    $maxOpen = 0;

    $items = (function () use ($files, $account, &$pulled, &$done, &$maxOpen) {
        foreach ($files as $key => $path) {
            $pulled++;
            $maxOpen = max($maxOpen, $pulled - $done);

            yield $key => ['key' => $key, 'path' => $path, 'filename' => "f{$key}.png", 'account' => $account];
        }
    })();

    $started = microtime(true);
    (new TikTokUploader)->uploadStream($items, 6, function ($key, $result) use (&$results, &$done) {
        $done++;
        $results[$key] = $result;
    });
    $elapsed = microtime(true) - $started;

    $stats = json_decode(file_get_contents("http://127.0.0.1:{$port}/stats"), true);
    $server->stop();

    expect($results)->toHaveCount(12)->and(collect($results)->every(fn ($r) => is_string($r) && str_contains($r, 'ibyteimg.com')))->toBeTrue();
    expect($stats['max'])->toBe(6)->and($stats['count'])->toBe(12);
    expect($maxOpen)->toBeLessThanOrEqual(6)->toBeGreaterThanOrEqual(5);
    expect($elapsed)->toBeLessThan(2.4); // một-lần-một mất ~3.6 giây
})->skip(fn () => ! nodeInstalled(), 'node is not installed');

test('the concurrency limit is respected and slow requests do not hold back the rest', function () {
    [$server, $port] = startSlowServer(['FAST_MS' => '200', 'SLOW_FIRST' => '2', 'SLOW_MS' => '1600']);
    config(['cdnpro.tiktok.endpoint' => "http://127.0.0.1:{$port}/upload"]);

    $files = tinyFiles(14);
    $account = UploadConfig::factory()->make();
    $finishedAt = [];
    $started = microtime(true);

    $items = (function () use ($files, $account) {
        foreach ($files as $key => $path) {
            yield $key => ['key' => $key, 'path' => $path, 'filename' => "f{$key}.png", 'account' => $account];
        }
    })();

    (new TikTokUploader)->uploadStream($items, 4, function ($key) use (&$finishedAt, $started) {
        $finishedAt[$key] = microtime(true) - $started;
    });

    $server->stop();

    expect($finishedAt)->toHaveCount(14);

    // Với 4 ô và 2 ô bị ảnh chậm chiếm, 12 ảnh nhanh phải xong bằng 2 ô còn lại (~1.3 giây) TRƯỚC khi ảnh chậm xong,
    // chứ không đợi cả lô như kiểu chia từng cụm.
    $fastBeforeSlow = collect($finishedAt)->filter(fn ($t, $key) => $key >= 2 && $t < 1.5)->count();
    expect($fastBeforeSlow)->toBeGreaterThanOrEqual(8);
    expect(max($finishedAt))->toBeLessThan(2.6);
})->skip(fn () => ! nodeInstalled(), 'node is not installed');

test('failed responses and refused connections are reported per file without stopping the others', function () {
    config(['cdnpro.tiktok.endpoint' => 'http://127.0.0.1:9/upload']); // cổng đóng
    $files = tinyFiles(3);
    $account = UploadConfig::factory()->make();
    $results = [];

    $items = (function () use ($files, $account) {
        foreach ($files as $key => $path) {
            yield $key => ['key' => $key, 'path' => $path, 'filename' => "f{$key}.png", 'account' => $account];
        }
    })();

    (new TikTokUploader)->uploadStream($items, 3, function ($key, $result) use (&$results) {
        $results[$key] = $result;
    });

    expect($results)->toHaveCount(3);
    foreach ($results as $result) {
        expect($result)->toBeInstanceOf(UploadFailedException::class)->and($result->getMessage())->toContain('kết nối');
    }
});

test('the file handles are closed after the stream finishes', function () {
    [$server, $port] = startSlowServer(['FAST_MS' => '50']);
    config(['cdnpro.tiktok.endpoint' => "http://127.0.0.1:{$port}/upload"]);
    $files = tinyFiles(4);
    $account = UploadConfig::factory()->make();

    $items = (function () use ($files, $account) {
        foreach ($files as $key => $path) {
            yield $key => ['key' => $key, 'path' => $path, 'filename' => "f{$key}.png", 'account' => $account];
        }
    })();

    (new TikTokUploader)->uploadStream($items, 2, fn () => null);
    $server->stop();

    // Windows không cho xóa file còn đang mở: unlink thành công nghĩa là handle đã đóng.
    foreach ($files as $path) {
        expect(@unlink($path))->toBeTrue();
    }
})->skip(fn () => ! nodeInstalled(), 'node is not installed');
