<?php

namespace App\Services\Billing;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;

class SubscriptionService
{
    /**
     * Cap hoac gia han goi. Cung goi dang con han thi cong don them ngay; goi khac thi thay the.
     */
    public function grant(User $user, Plan $plan, ?int $days = null): Subscription
    {
        $days ??= $plan->duration_days ?? 30;

        $current = $user->subscriptions()->current()->latest('ends_at')->first();

        if ($current && $current->plan_id === $plan->id) {
            $current->update(['ends_at' => $current->ends_at->addDays($days)]);

            return $current;
        }

        if ($current) {
            $current->update(['status' => Subscription::CANCELLED, 'canceled_at' => now()]);
        }

        return $user->subscriptions()->create([
            'plan_id' => $plan->id,
            'status' => Subscription::ACTIVE,
            'starts_at' => now(),
            'ends_at' => now()->addDays($days),
        ]);
    }

    public function cancel(Subscription $subscription): void
    {
        $subscription->update(['status' => Subscription::CANCELLED, 'canceled_at' => now()]);
    }

    /**
     * Chuyen cac goi het han sang expired. User tu dong ve Free vi QuotaService fallback.
     */
    public function expireDue(): int
    {
        return Subscription::where('status', Subscription::ACTIVE)
            ->where('ends_at', '<=', now())
            ->update(['status' => Subscription::EXPIRED]);
    }
}
