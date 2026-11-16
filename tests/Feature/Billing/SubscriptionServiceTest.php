<?php

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\QuotaService;
use App\Services\Billing\SubscriptionService;

beforeEach(function () {
    Plan::factory()->free()->create();
    $this->service = app(SubscriptionService::class);
});

test('grant creates an active subscription', function () {
    $user = User::factory()->create();
    $pro = Plan::factory()->create(['slug' => 'pro', 'duration_days' => 30]);

    $subscription = $this->service->grant($user, $pro);

    expect($subscription->status)->toBe(Subscription::ACTIVE);
    expect((int) round(now()->diffInDays($subscription->ends_at)))->toBe(30);
    expect(app(QuotaService::class)->planFor($user)->id)->toBe($pro->id);
});

test('granting the same plan extends the current one', function () {
    $user = User::factory()->create();
    $pro = Plan::factory()->create(['slug' => 'pro']);

    $first = $this->service->grant($user, $pro, 10);
    $second = $this->service->grant($user, $pro, 5);

    expect($second->id)->toBe($first->id);
    expect((int) round(now()->diffInDays($second->fresh()->ends_at)))->toBe(15);
    expect(Subscription::count())->toBe(1);
});

test('granting a different plan replaces the current one', function () {
    $user = User::factory()->create();
    $pro = Plan::factory()->create(['slug' => 'pro']);
    $business = Plan::factory()->create(['slug' => 'business']);

    $old = $this->service->grant($user, $pro);
    $this->service->grant($user, $business);

    expect($old->fresh()->status)->toBe(Subscription::CANCELLED);
    expect(app(QuotaService::class)->planFor($user)->id)->toBe($business->id);
});

test('expire command marks overdue subscriptions and user returns to free', function () {
    $user = User::factory()->create();
    $overdue = Subscription::factory()->expired()->for($user)->create();
    $active = Subscription::factory()->create();

    $this->artisan('subscriptions:expire')->assertSuccessful();

    expect($overdue->fresh()->status)->toBe(Subscription::EXPIRED);
    expect($active->fresh()->status)->toBe(Subscription::ACTIVE);
    expect(app(QuotaService::class)->planFor($user)->isFree())->toBeTrue();
});
