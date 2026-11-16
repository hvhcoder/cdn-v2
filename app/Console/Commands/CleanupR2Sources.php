<?php

namespace App\Console\Commands;

use App\Models\Video;
use App\Services\Storage\SourceCleaner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('videos:cleanup-r2')]
#[Description('Xóa file gốc còn sót trên R2 của các video đã xử lý xong')]
class CleanupR2Sources extends Command
{
    public function handle(SourceCleaner $cleaner): int
    {
        $done = 0;
        $failed = 0;

        Video::where('status', Video::COMPLETED)->whereNotNull('r2_path')->each(function (Video $video) use ($cleaner, &$done, &$failed) {
            $cleaner->purge($video) ? $done++ : $failed++;
        });

        $this->info("Đã dọn {$done} file, lỗi {$failed} file.");

        return self::SUCCESS;
    }
}
