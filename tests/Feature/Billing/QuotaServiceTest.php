<?php

use App\Exceptions\QuotaExceededException;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Video;
use App\Services\Billing\QuotaService;

beforeEach(function () {
    $this->free = Plan::factory()->free()->create();
    $this->quota = app(QuotaService::class);
});

test('user without subscription uses free plan', function () {
    $user = User::factory()->create();

    expect($this->quota->planFor($user)->id)->toBe($this->free->id);
});

test('active subscription overrides free plan and expired falls back', function () {
    $user = User::factory()->create();
    $pro = Plan::factory()->create(['slug' => 'pro']);

    $subscription = Subscription::factory()->for($user)->for($pro)->create();
    expect($this->quota->planFor($user)->id)->toBe($pro->id);

    $subscription->update(['ends_at' => now()->subMinute()]);
    expect($this->quota->planFor($user)->id)->toBe($this->free->id);
});

test('rejects file larger than plan upload limit', function () {
    $user = User::factory()->create();

    expect(fn () => $this->quota->assertCanUpload($user, 201 * 1024 ** 2))
        ->toThrow(QuotaExceededException::class, '200 MB');
});

test('rejects when video count reached', function () {
    $user = User::factory()->create();
    Video::factory()->count(5)->for($user)->create(['size_bytes' => 1]);

    try {
        $this->quota->assertCanUpload($user, 1024);
    } catch (QuotaExceededException $e) {
        expect($e->limit)->toBe('max_videos');

        return;
    }

    $this->fail('Expected QuotaExceededException');
});

test('rejects when storage would be exceeded', function () {
    $user = User::factory()->create();
    Video::factory()->for($user)->create(['size_bytes' => 1024 ** 3 - 100]);

    expect(fn () => $this->quota->assertCanUpload($user, 200))
        ->toThrow(QuotaExceededException::class, 'dung lượng');
});

test('rejects when monthly encode minutes would be exceeded', function () {
    $user = User::factory()->create();
    Video::factory()->for($user)->create(['size_bytes' => 1, 'duration_seconds' => 29 * 60]);

    expect(fn () => $this->quota->assertCanUpload($user, 1024, 120))
        ->toThrow(QuotaExceededException::class, 'encode');
});

test('allows upload within limits and ignores last month encode usage', function () {
    $user = User::factory()->create();
    Video::factory()->for($user)->create(['size_bytes' => 1, 'duration_seconds' => 3600, 'created_at' => now()->subMonth()]);

    $this->quota->assertCanUpload($user, 10 * 1024 ** 2, 600);

    expect(true)->toBeTrue();
});

test('admin is never limited', function () {
    $admin = User::factory()->admin()->create();

    $this->quota->assertCanUpload($admin, 10 * 1024 ** 3, 100000);

    expect($this->quota->canUseFolders($admin))->toBeTrue();
});

test('usage is scoped to the user', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    Video::factory()->for($b)->create();

    expect($this->quota->usage($a)['video_count'])->toBe(0);
    expect($this->quota->usage($b)['video_count'])->toBe(1);
});
