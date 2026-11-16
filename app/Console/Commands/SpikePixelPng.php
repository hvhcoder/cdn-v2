<?php

namespace App\Console\Commands;

use App\Models\UploadConfig;
use App\Services\Cdn\TikTokUploader;
use App\Services\Cdn\UploadFailedException;
use App\Services\Video\PixelPngCodec;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

#[Signature('spike:pixel-png {--config= : ID cấu hình TikTok (mặc định: cấu hình TikTok đang bật đầu tiên)} {--sizes=1,3 : Danh sách dung lượng payload (MB)} {--width=1024} {--local : Chỉ benchmark encode/decode, không upload}')]
#[Description('Phase 0: kiểm chứng TikTok có giữ nguyên byte của PNG chứa dữ liệu trong pixel hay không')]
class SpikePixelPng extends Command
{
    public function handle(TikTokUploader $uploader): int
    {
        $codec = new PixelPngCodec((int) $this->option('width'));
        $sizes = array_filter(array_map('floatval', explode(',', (string) $this->option('sizes'))));

        $config = $this->option('local') ? null : $this->resolveConfig();
        if (! $this->option('local') && $config === null) {
            return self::FAILURE;
        }

        $results = [];
        foreach ($sizes as $mb) {
            $this->line("\n=== Payload {$mb} MB ===");
            $results[] = $this->runCase($codec, $uploader, $config, (int) ($mb * 1024 * 1024));
        }

        return $this->summarize($results, $config === null);
    }

    private function resolveConfig(): ?UploadConfig
    {
        $config = $this->option('config')
            ? UploadConfig::forPlatform('tiktok')->find($this->option('config'))
            : UploadConfig::forPlatform('tiktok')->active()->orderBy('id')->first();

        if ($config === null) {
            $this->error('Không có cấu hình TikTok. Thêm ở /admin/upload-configs rồi chạy lại.');
        } else {
            $this->info("Dùng cấu hình TikTok #{$config->id} ({$config->name})");
        }

        return $config;
    }

    /**
     * @return array<string, mixed>
     */
    private function runCase(PixelPngCodec $codec, TikTokUploader $uploader, ?UploadConfig $config, int $bytes): array
    {
        $payload = random_bytes($bytes);

        $t = microtime(true);
        $png = $codec->encode($payload);
        $encodeMs = (microtime(true) - $t) * 1000;

        $t = microtime(true);
        $localOk = $codec->decode($png) === $payload;
        $decodeMs = (microtime(true) - $t) * 1000;

        [$w, $h] = getimagesizefromstring($png);
        $this->line(sprintf('Local: png=%d B (%dx%d px), encode=%.0f ms, decode=%.0f ms, roundtrip=%s', strlen($png), $w, $h, $encodeMs, $decodeMs, $localOk ? 'OK' : 'FAIL'));

        $case = ['mb' => round($bytes / 1048576, 2), 'width' => $w, 'height' => $h, 'local_ok' => $localOk, 'encode_ms' => $encodeMs, 'decode_ms' => $decodeMs];

        if ($config === null) {
            return $case;
        }

        $file = tempnam(sys_get_temp_dir(), 'spk');
        file_put_contents($file, $png);

        try {
            $t = microtime(true);
            $url = $uploader->upload($file, 'spike_'.bin2hex(random_bytes(4)).'.png', $config);
            $case['upload_ms'] = (microtime(true) - $t) * 1000;
            $case['url'] = $url;
            $this->line(sprintf('Upload OK (%.0f ms): %s', $case['upload_ms'], $url));
        } catch (UploadFailedException $e) {
            $this->error('Upload thất bại: '.$e->getMessage().($e->accountIsDead ? ' (cookie có vẻ hết hạn)' : ''));
            $case['upload_error'] = $e->getMessage();

            return $case;
        } finally {
            @unlink($file);
        }

        return $case + $this->verifyDownload($codec, $url, $png, $payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function verifyDownload(PixelPngCodec $codec, string $url, string $png, string $payload): array
    {
        try {
            $response = Http::timeout(60)->withHeaders(['Origin' => 'http://localhost:8000'])->get($url);
        } catch (Throwable $e) {
            $this->error('Tải lại thất bại: '.$e->getMessage());

            return ['download_error' => $e->getMessage()];
        }

        $body = $response->body();
        $sameBytes = hash('sha256', $body) === hash('sha256', $png);
        $cors = $response->header('Access-Control-Allow-Origin') ?: null;

        try {
            $decodedOk = $codec->decode($body) === $payload;
            $decodeError = null;
        } catch (Throwable $e) {
            $decodedOk = false;
            $decodeError = $e->getMessage();
        }

        $this->line(sprintf(
            'Tải lại: HTTP %d, content-type=%s, %d B (gốc %d B), byte giống hệt=%s, giải mã đúng payload=%s, CORS=%s',
            $response->status(), $response->header('Content-Type'), strlen($body), strlen($png),
            $sameBytes ? 'CÓ' : 'KHÔNG', $decodedOk ? 'CÓ' : 'KHÔNG', $cors ?? 'không có'
        ));

        if ($decodeError) {
            $this->warn("Lý do giải mã lỗi: {$decodeError}");
        }

        return [
            'http' => $response->status(), 'content_type' => $response->header('Content-Type'), 'same_bytes' => $sameBytes,
            'decoded_ok' => $decodedOk, 'cors' => $cors, 'returned_bytes' => strlen($body),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     */
    private function summarize(array $results, bool $localOnly): int
    {
        $this->line("\n=== KẾT LUẬN ===");

        if ($localOnly) {
            $ok = collect($results)->every('local_ok');
            $this->info($ok ? 'Codec chạy đúng (chưa kiểm tra TikTok).' : 'Codec lỗi!');

            return $ok ? self::SUCCESS : self::FAILURE;
        }

        $uploaded = collect($results)->filter(fn ($r) => isset($r['url']));
        $intact = $uploaded->every(fn ($r) => $r['decoded_ok'] ?? false);

        if ($uploaded->isEmpty()) {
            $this->error('Không upload được case nào — chưa thể kết luận. Kiểm tra cookie rồi chạy lại.');

            return self::FAILURE;
        }

        $this->line('1) Giữ nguyên dữ liệu pixel: '.($intact ? 'CÓ — hướng PNG pixel dùng được' : 'KHÔNG — cần fallback (nối header hoặc đổi CDN)'));
        $this->line('   (byte file giống hệt: '.($uploaded->every(fn ($r) => $r['same_bytes'] ?? false) ? 'có' : 'không, nhưng chỉ cần pixel còn nguyên là đủ').')');
        $this->line('2) Giới hạn: upload thành công '.$uploaded->count().'/'.count($results).' case ('.collect($results)->pluck('mb')->implode(', ').' MB).');
        $this->line('3) CORS: '.($uploaded->contains(fn ($r) => ! empty($r['cors'])) ? 'CDN có trả Access-Control-Allow-Origin' : 'KHÔNG có header CORS → player cần proxy (vd Cloudflare Worker)'));
        $slowest = collect($results)->max(fn ($r) => $r['encode_ms'] + $r['decode_ms']);
        $this->line(sprintf('4) Tốc độ encode+decode chậm nhất: %.0f ms (segment 5s nên cần < 5000 ms)', $slowest));

        return $intact ? self::SUCCESS : self::FAILURE;
    }
}
