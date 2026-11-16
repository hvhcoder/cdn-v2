<?php

namespace Database\Factories;

use App\Models\AbuseReport;
use App\Models\Video;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AbuseReport>
 */
class AbuseReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'video_id' => Video::factory(),
            'reason' => fake()->randomElement(array_keys(AbuseReport::REASONS)),
            'details' => fake()->optional()->sentence(),
            'reporter_ip' => fake()->ipv4(),
            'status' => AbuseReport::PENDING,
        ];
    }
}
