<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free', 'slug' => 'free', 'price' => 0, 'duration_days' => null,
                'storage_limit_gb' => 1, 'max_videos' => 5, 'max_upload_size_mb' => 200,
                'monthly_encode_minutes' => 30, 'embed_domain_limit' => 1,
                'expiry_lock_days' => 0, 'expiry_delete_days' => 30, 'embed_token_ttl_minutes' => null,
                'embed_locked_when_expired' => true, 'can_use_folders' => false, 'is_popular' => false, 'sort' => 0,
            ],
            [
                'name' => 'Pro', 'slug' => 'pro', 'price' => 99000, 'duration_days' => 30,
                'storage_limit_gb' => 50, 'max_videos' => 200, 'max_upload_size_mb' => 1000,
                'monthly_encode_minutes' => 600, 'embed_domain_limit' => 5,
                'expiry_lock_days' => 0, 'expiry_delete_days' => 30, 'embed_token_ttl_minutes' => 240,
                'embed_locked_when_expired' => true, 'can_use_folders' => true, 'is_popular' => true, 'sort' => 1,
            ],
            [
                'name' => 'Business', 'slug' => 'business', 'price' => 299000, 'duration_days' => 30,
                'storage_limit_gb' => 200, 'max_videos' => 1000, 'max_upload_size_mb' => 4000,
                'monthly_encode_minutes' => 3000, 'embed_domain_limit' => 20,
                'expiry_lock_days' => 7, 'expiry_delete_days' => 60, 'embed_token_ttl_minutes' => 480,
                'embed_locked_when_expired' => true, 'can_use_folders' => true, 'is_popular' => false, 'sort' => 2,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
