<?php

use App\Services\Storage\R2Service;

test('sigv4 presign matches the official AWS documentation vector', function () {
    $r2 = new R2Service([
        'endpoint' => 'https://examplebucket.s3.amazonaws.com',
        'bucket' => 'examplebucket',
        'access_key' => 'AKIAIOSFODNN7EXAMPLE',
        'secret_key' => 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
        'region' => 'us-east-1',
        'presign_minutes' => 1440,
    ]);

    $query = $r2->signedQuery('GET', 'examplebucket.s3.amazonaws.com', '/test.txt', 86400, 1369353600);

    expect($query)->toContain('X-Amz-Signature=aeeed9bbccd4d02ee5c0109b86d86835f995330da4c265957d157751f604d404');
});

test('presigned url uses path style with bucket and encoded key', function () {
    $r2 = new R2Service([
        'endpoint' => 'https://acc.r2.cloudflarestorage.com', 'bucket' => 'vids',
        'access_key' => 'k', 'secret_key' => 's', 'region' => 'auto', 'presign_minutes' => 60,
    ]);

    $url = $r2->presignUpload('raw/2026/11/a b.mp4');

    expect($url)->toStartWith('https://acc.r2.cloudflarestorage.com/vids/raw/2026/11/a%20b.mp4?X-Amz-Algorithm=');
    expect($url)->toContain('X-Amz-Expires=3600')->toContain('X-Amz-Signature=');
});

test('missing configuration raises a clear error', function () {
    expect(fn () => (new R2Service(['endpoint' => '', 'bucket' => '', 'access_key' => '', 'secret_key' => '']))->presignUpload('a'))
        ->toThrow(RuntimeException::class, 'R2');
});

test('object keys keep the extension and are unique', function () {
    $r2 = new R2Service(['region' => 'auto']);

    $a = $r2->newObjectKey('Phim Hay.MP4');
    $b = $r2->newObjectKey('Phim Hay.MP4');

    expect($a)->toStartWith('raw/')->toEndWith('.mp4')->not->toBe($b);
});
