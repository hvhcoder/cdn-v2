<?php

namespace App\Services\Video;

use RuntimeException;

/**
 * PNG Header Graft Encoder - Mã hóa MPEG-TS bằng cách tạo PNG 1x1 minimal hoàn chỉnh,
 * sau đó append raw TS data sau IEND chunk.
 *
 * PNG structure: [Signature + IHDR + IDAT + IEND] + [TS data]
 * Player sẽ quét tìm TS sync byte (0x47) để giải mã.
 */
class PngHeaderGraftEncoder
{
    private const TS_PACKET_SIZE = 188;

    /**
     * Tạo PNG 1x1 pixel hoàn chỉnh (grayscale, no compression).
     *
     * Structure:
     * - Signature: 8 bytes
     * - IHDR: 25 bytes (13 data + 12 wrapper)
     * - IDAT: 19 bytes (7 data + 12 wrapper)
     * - IEND: 12 bytes
     * Total: ~64 bytes
     */
    private function createMinimalPng(): string
    {
        $png = '';

        // PNG Signature
        $png .= "\x89\x50\x4e\x47\x0d\x0a\x1a\x0a";

        // IHDR chunk (1x1 pixel, grayscale, 8-bit)
        $ihdr = pack('NCCCCCC', 1, 1, 8, 0, 0, 0, 0); // width=1, height=1, depth=8, colorType=0 (grayscale)
        $png .= $this->makeChunk('IHDR', $ihdr);

        // IDAT chunk (1 pixel grayscale = filter byte + 1 byte pixel data)
        // Zlib compressed: filter=0, pixel=0 (black)
        $rawData = "\x00\x00"; // filter byte + pixel value
        $idat = gzcompress($rawData, 9);
        $png .= $this->makeChunk('IDAT', $idat);

        // IEND chunk
        $png .= $this->makeChunk('IEND', '');

        return $png;
    }

    /**
     * Tạo PNG chunk với length + type + data + CRC32.
     */
    private function makeChunk(string $type, string $data): string
    {
        $length = pack('N', strlen($data));
        $chunk = $type.$data;
        $crc = pack('N', crc32($chunk));

        return $length.$chunk.$crc;
    }

    /**
     * Load header từ file - DEPRECATED, giữ lại để tương thích.
     * Bây giờ tạo PNG minimal động nên không cần template nữa.
     */
    public function loadHeaderFromFile(string $path): string
    {
        // Generate minimal PNG instead of loading from file
        return $this->createMinimalPng();
    }

    /**
     * Encode TS segment thành PNG hoàn chỉnh + TS data.
     *
     * @throws RuntimeException
     */
    public function encode(string $tsBytes, ?string $header = null): string
    {
        // Validate TS size phải chia hết cho 188
        if (strlen($tsBytes) % self::TS_PACKET_SIZE !== 0) {
            throw new RuntimeException('TS data ('.strlen($tsBytes).'B) không chia hết cho '.self::TS_PACKET_SIZE);
        }

        // Tạo PNG minimal header
        $pngHeader = $this->createMinimalPng();

        // Ghép: [PNG hoàn chỉnh] + [TS data]
        return $pngHeader.$tsBytes;
    }

    /**
     * Encode file TS, ghi ra file mới.
     *
     * @throws RuntimeException
     */
    public function encodeFile(string $inputPath, string $outputPath, ?string $header = null): void
    {
        $tsBytes = file_get_contents($inputPath);

        if ($tsBytes === false) {
            throw new RuntimeException("Không đọc được file: {$inputPath}");
        }

        $encoded = $this->encode($tsBytes, $header);

        if (file_put_contents($outputPath, $encoded) === false) {
            throw new RuntimeException("Không ghi được file: {$outputPath}");
        }
    }

    /**
     * Get cached header (deprecated - trả về PNG minimal).
     */
    public function getCachedHeader(): ?string
    {
        return $this->createMinimalPng();
    }
}
