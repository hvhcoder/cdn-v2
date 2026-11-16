<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'name' => ucfirst($name),
            'slug' => $name,
            'price' => 99000,
            'duration_days' => 30,
            'storage_limit_gb' => 50,
            'max_videos' => 100,
            'max_upload_size_mb' => 1000,
            'monthly_encode_minutes' => 600,
            'embed_domain_limit' => 5,
            'can_use_folders' => true,
            'is_popular' => false,
            'is_active' => true,
            'sort' => 1,
        ];
    }

    public function free(): static
    {
        return $this->state([
            'name' => 'Free',
            'slug' => Plan::FREE_SLUG,
            'price' => 0,
            'duration_days' => null,
            'storage_limit_gb' => 1,
            'max_videos' => 5,
            'max_upload_size_mb' => 200,
            'monthly_encode_minutes' => 30,
            'embed_domain_limit' => 1,
            'can_use_folders' => false,
            'sort' => 0,
        ]);
    }
}
