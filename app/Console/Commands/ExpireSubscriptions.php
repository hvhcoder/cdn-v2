<?php

namespace App\Console\Commands;

use App\Services\Billing\SubscriptionService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('subscriptions:expire')]
#[Description('Chuyển các gói đã hết hạn sang trạng thái expired')]
class ExpireSubscriptions extends Command
{
    public function handle(SubscriptionService $subscriptions): int
    {
        $count = $subscriptions->expireDue();
        $this->info("Đã chuyển {$count} gói sang expired.");

        return self::SUCCESS;
    }
}
