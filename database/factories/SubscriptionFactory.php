<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'plan_id' => Plan::factory(),
            'status' => Subscription::ACTIVE,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(29),
            'auto_renew' => false,
        ];
    }

    public function expired(): static
    {
        return $this->state([
            'starts_at' => now()->subDays(31),
            'ends_at' => now()->subDay(),
        ]);
    }
}
