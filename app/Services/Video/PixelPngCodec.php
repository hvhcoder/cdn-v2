<?php

namespace App\Services\Video;

use RuntimeException;

/**
 * Giấu dữ liệu .ts vào pixel của một ảnh PNG RGB 8-bit hợp lệ (không alpha, không interlace).
 *
 * Khung: magic(4) | version(1) | payload_length(4, BE) | crc32(4, BE) | payload | padding 0x00
 */
class PixelPngCodec
{
    public const MAGIC = 'PXTS';

    public const VERSION = 1;

    private const HEADER_SIZE = 13;

    private const PNG_SIGNATURE = "\x89PNG\r\n\x1a\n";

    public function __construct(private int $width = 1024)
    {
        if ($width < 16) {
            throw new RuntimeException('Chiều rộng ảnh quá nhỏ.');
        }
    }

    /**
     * Dữ liệu video đã nén nên gần như ngẫu nhiên: nén thêm không có lợi. Dùng zlib mức 0 (stored) cho nhanh
     * (~10x so với mức 1, file chỉ lớn thêm ~0.04%); ảnh vẫn là PNG hợp lệ.
     */
    public function encode(string $payload): string
    {
        $frame = self::MAGIC.chr(self::VERSION).pack('N', strlen($payload)).pack('N', crc32($payload)).$payload;

        $rowBytes = $this->width * 3;
        $height = (int) ceil(strlen($frame) / $rowBytes);
        $frame = str_pad($frame, $height * $rowBytes, "\0");

        $raw = [];
        foreach (str_split($frame, $rowBytes) as $row) {
            $raw[] = "\0".$row;
        }

        return self::PNG_SIGNATURE
            .$this->chunk('IHDR', pack('NNCCCCC', $this->width, $height, 8, 2, 0, 0, 0))
            .$this->chunk('IDAT', gzcompress(implode($raw), 0))
            .$this->chunk('IEND', '');
    }

    /**
     * @throws RuntimeException khi ảnh hỏng, bị đổi định dạng hoặc sai CRC
     */
    public function decode(string $png): string
    {
        if (! str_starts_with($png, self::PNG_SIGNATURE)) {
            throw new RuntimeException('Không phải file PNG.');
        }

        [$width, $height, $idat] = $this->readChunks($png);
        $raw = @gzuncompress($idat);
        if ($raw === false) {
            throw new RuntimeException('Dữ liệu IDAT không giải nén được.');
        }

        $frame = $this->unfilter($raw, $width, $height);

        if (strlen($frame) < self::HEADER_SIZE || substr($frame, 0, 4) !== self::MAGIC) {
            throw new RuntimeException('Sai magic của khung dữ liệu.');
        }

        $length = unpack('N', substr($frame, 5, 4))[1];
        $crc = unpack('N', substr($frame, 9, 4))[1];
        $payload = substr($frame, self::HEADER_SIZE, $length);

        if (strlen($payload) !== $length || crc32($payload) !== $crc) {
            throw new RuntimeException('Sai CRC: ảnh đã bị CDN làm hỏng.');
        }

        return $payload;
    }

    /**
     * @return array{0: int, 1: int, 2: string}
     */
    private function readChunks(string $png): array
    {
        $offset = 8;
        $width = 0;
        $height = 0;
        $idat = [];

        while ($offset + 12 <= strlen($png)) {
            $length = unpack('N', substr($png, $offset, 4))[1];
            $type = substr($png, $offset + 4, 4);
            $data = substr($png, $offset + 8, $length);
            $offset += 12 + $length;

            if ($type === 'IHDR') {
                $h = unpack('Nw/Nh/Cdepth/Ctype/Ccompression/Cfilter/Cinterlace', $data);
                if ($h['depth'] !== 8 || $h['type'] !== 2 || $h['interlace'] !== 0) {
                    throw new RuntimeException('PNG không phải RGB 8-bit không interlace (CDN đã đổi định dạng).');
                }
                $width = $h['w'];
                $height = $h['h'];
            } elseif ($type === 'IDAT') {
                $idat[] = $data;
            } elseif ($type === 'IEND') {
                break;
            }
        }

        return [$width, $height, implode($idat)];
    }

    /**
     * Bỏ filter từng dòng (đủ 5 loại) vì CDN/thư viện khác có thể re-encode.
     */
    private function unfilter(string $raw, int $width, int $height): string
    {
        $rowBytes = $width * 3;
        if (strlen($raw) < $height * ($rowBytes + 1)) {
            throw new RuntimeException('Dữ liệu ảnh bị thiếu.');
        }

        $out = [];
        $prev = array_fill(0, $rowBytes, 0);

        for ($y = 0; $y < $height; $y++) {
            $base = $y * ($rowBytes + 1);
            $filter = ord($raw[$base]);

            if ($filter === 0) {
                $out[] = substr($raw, $base + 1, $rowBytes);
                $prev = array_values(unpack('C*', $out[$y]));

                continue;
            }

            $line = array_values(unpack('C*', substr($raw, $base + 1, $rowBytes)));
            $prev = $this->unfilterRow($filter, $line, $prev);
            $out[] = pack('C*', ...$prev);
        }

        return implode($out);
    }

    /**
     * @param  array<int, int>  $line
     * @param  array<int, int>  $prev
     * @return array<int, int>
     */
    private function unfilterRow(int $filter, array $line, array $prev): array
    {
        $bpp = 3;
        $row = [];

        foreach ($line as $i => $value) {
            $a = $i >= $bpp ? $row[$i - $bpp] : 0;
            $b = $prev[$i];
            $c = $i >= $bpp ? $prev[$i - $bpp] : 0;

            $row[$i] = ($value + match ($filter) {
                1 => $a,
                2 => $b,
                3 => intdiv($a + $b, 2),
                4 => $this->paeth($a, $b, $c),
                default => throw new RuntimeException("Filter PNG không hợp lệ: {$filter}."),
            }) & 0xFF;
        }

        return $row;
    }

    private function paeth(int $a, int $b, int $c): int
    {
        $p = $a + $b - $c;
        $pa = abs($p - $a);
        $pb = abs($p - $b);
        $pc = abs($p - $c);

        return ($pa <= $pb && $pa <= $pc) ? $a : (($pb <= $pc) ? $b : $c);
    }

    private function chunk(string $type, string $data): string
    {
        return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
    }
}
