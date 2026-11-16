<?php

use App\Services\Video\PixelPngCodec;

dataset('payload sizes', [
    'empty' => 0,
    'one byte' => 1,
    'less than one row' => 100,
    'exactly one row of frame' => 1024 * 3 - 13,
    'not divisible by row' => 5000,
    'multi row' => 300_001,
]);

test('encode then decode returns the exact bytes', function (int $size) {
    $payload = $size > 0 ? random_bytes($size) : '';
    $codec = new PixelPngCodec(1024);

    expect($codec->decode($codec->encode($payload)))->toBe($payload);
})->with('payload sizes');

test('output is a real decodable RGB png whose pixels hold the data', function () {
    $payload = random_bytes(10_000);
    $png = (new PixelPngCodec(64))->encode($payload);

    $info = getimagesizefromstring($png);
    expect($info['mime'])->toBe('image/png');
    expect($info[0])->toBe(64);

    $image = imagecreatefromstring($png);
    $rgb = imagecolorat($image, 0, 0);
    $frame = chr(($rgb >> 16) & 0xFF).chr(($rgb >> 8) & 0xFF).chr($rgb & 0xFF);
    expect($frame)->toBe(PixelPngCodec::MAGIC[0].PixelPngCodec::MAGIC[1].PixelPngCodec::MAGIC[2]);
});

test('survives a re-encode by another png library with different filters', function () {
    $payload = random_bytes(200_000);
    $codec = new PixelPngCodec(128);

    $image = imagecreatefromstring($codec->encode($payload));
    imagesavealpha($image, false);

    foreach ([PNG_NO_FILTER, PNG_FILTER_SUB, PNG_FILTER_UP, PNG_FILTER_AVG, PNG_FILTER_PAETH, PNG_ALL_FILTERS] as $filter) {
        ob_start();
        imagepng($image, null, 6, $filter);
        $reencoded = ob_get_clean();

        expect($codec->decode($reencoded))->toBe($payload);
    }
});

test('detects corrupted pixel data via crc', function () {
    $codec = new PixelPngCodec(64);
    $image = imagecreatefromstring($codec->encode(random_bytes(5000)));

    imagesetpixel($image, 10, 10, 0x123456);
    ob_start();
    imagepng($image);
    $tampered = ob_get_clean();

    expect(fn () => $codec->decode($tampered))->toThrow(RuntimeException::class);
});

test('rejects data that is not a png or was converted to another format', function () {
    $codec = new PixelPngCodec(64);

    expect(fn () => $codec->decode('not a png'))->toThrow(RuntimeException::class, 'PNG');

    $palette = imagecreate(8, 8);
    imagecolorallocate($palette, 10, 20, 30);
    ob_start();
    imagepng($palette);
    $paletteImage = ob_get_clean();

    expect(fn () => $codec->decode($paletteImage))->toThrow(RuntimeException::class, 'RGB 8-bit');
});
