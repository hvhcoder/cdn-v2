<?php

namespace App\Console\Commands;

use App\Services\Video\DeadUrlDetector;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('videos:check-dead-urls {--days=1 : Kiểm tra video chưa check trong N ngày} {--limit=50 : Tối đa bao nhiêu video mỗi lần chạy}')]
#[Description('Kiểm tra URL CDN của các segment; đánh dấu segment chết để re-upload')]
class CheckDeadUrls extends Command
{
    public function handle(DeadUrlDetector $detector): int
    {
        $days = (int) $this->option('days');
        $limit = (int) $this->option('limit');

        $result = $detector->checkStale($days, $limit);

        $this->info("Đã kiểm tra {$result['checked']} video; {$result['dead_videos']} video có segment chết.");

        return self::SUCCESS;
    }
}
