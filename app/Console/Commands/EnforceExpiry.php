<?php

namespace App\Console\Commands;

use App\Services\Video\ExpiryEnforcer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('videos:enforce-expiry')]
#[Description('Khóa embed và xóa video của user đã hết gói theo chính sách expiry_lock_days / expiry_delete_days')]
class EnforceExpiry extends Command
{
    public function handle(ExpiryEnforcer $enforcer): int
    {
        $result = $enforcer->enforce();

        $this->info("Đã khóa embed {$result['locked']} video, xóa {$result['deleted']} video.");

        return self::SUCCESS;
    }
}
