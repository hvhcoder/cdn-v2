<?php

namespace App\Console\Commands;

use App\Models\UploadConfig;
use App\Services\Cdn\CdnUploader;
use App\Services\Cdn\UploadFailedException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tiktok:health-check {--revive : Thử upload thử và bật lại tài khoản nếu thành công}')]
#[Description('Kiểm tra trạng thái tài khoản TikTok; tùy chọn thử bật lại tài khoản bị đánh dấu chết')]
class CheckTikTokHealth extends Command
{
    public function handle(CdnUploader $uploader): int
    {
        $configs = UploadConfig::where('platform', UploadConfig::TIKTOK)->orderBy('name')->get();

        if ($configs->isEmpty()) {
            $this->warn('Chưa có tài khoản TikTok nào.');

            return self::SUCCESS;
        }

        $headers = ['Tên', 'Trạng thái', 'Uploads', 'Dung lượng', 'Dùng lần cuối', 'Lỗi cuối'];
        $rows = $configs->map(fn (UploadConfig $cfg) => [
            $cfg->name,
            $cfg->status,
            number_format((int) $cfg->uploads_count),
            $this->formatBytes((int) $cfg->uploaded_bytes),
            $cfg->last_used_at?->diffForHumans() ?? '—',
            mb_substr((string) ($cfg->last_error ?? '—'), 0, 60),
        ])->all();

        $this->table($headers, $rows);

        if ($this->option('revive')) {
            $dead = $configs->where('status', UploadConfig::DEAD);

            if ($dead->isEmpty()) {
                $this->info('Không có tài khoản nào bị đánh dấu chết.');

                return self::SUCCESS;
            }

            foreach ($dead as $cfg) {
                $this->line("Thử bật lại: {$cfg->name}...");
                [$ok, $message] = $this->testTikTok($cfg, $uploader);
                $this->line($ok ? "<info>✓ {$message}</info>" : "<error>✗ {$message}</error>", null, 'raw');
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return array{0: bool, 1: string}
     */
    private function testTikTok(UploadConfig $config, CdnUploader $uploader): array
    {
        $file = tempnam(sys_get_temp_dir(), 'tt_health');
        ob_start();
        imagepng(imagecreatetruecolor(1, 1));
        file_put_contents($file, ob_get_clean());

        try {
            $url = $uploader->upload($file, 'healthcheck.png', $config);
            $config->update(['status' => UploadConfig::ACTIVE, 'last_error' => null]);

            return [true, "Upload thử thành công: {$url}"];
        } catch (UploadFailedException $e) {
            $e->accountIsDead ? $config->markDead($e->getMessage()) : $config->update(['last_error' => mb_substr($e->getMessage(), 0, 500)]);

            return [false, $e->getMessage()];
        } finally {
            @unlink($file);
        }
    }

    private function formatBytes(int $bytes): string
    {
        return $bytes >= 1024 ** 3
            ? round($bytes / 1024 ** 3, 2).' GB'
            : round($bytes / 1024 ** 2, 1).' MB';
    }
}
