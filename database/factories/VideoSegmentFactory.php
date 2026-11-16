<?php

namespace Database\Factories;

use App\Models\Video;
use App\Models\VideoSegment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VideoSegment>
 */
class VideoSegmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'video_id' => Video::factory(),
            'index' => 0,
            'duration' => 5,
            'status' => VideoSegment::PENDING,
        ];
    }
}
