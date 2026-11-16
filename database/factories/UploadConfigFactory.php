<?php

namespace Database\Factories;

use App\Models\UploadConfig;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UploadConfig>
 */
class UploadConfigFactory extends Factory
{
    /**
     * Mặc định là một tài khoản TikTok.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'platform' => UploadConfig::TIKTOK,
            'name' => fake()->unique()->userName(),
            'status' => UploadConfig::ACTIVE,
            'is_default' => false,
            'config_data' => ['cookie' => 'csrftoken=abc123; sessionid=xyz'],
        ];
    }

    public function r2(): static
    {
        return $this->state([
            'platform' => UploadConfig::R2,
            'config_data' => [
                'endpoint' => 'https://account.r2.cloudflarestorage.com',
                'bucket' => 'videos',
                'region' => 'auto',
                'access_key' => 'AKIAEXAMPLE',
                'secret_key' => 'secret-example-key',
            ],
        ]);
    }

    public function default(): static
    {
        return $this->state(['is_default' => true]);
    }
}
