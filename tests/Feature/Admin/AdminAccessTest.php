<?php

use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PlanSeeder;

beforeEach(function () {
    Plan::factory()->free()->create();
});

test('new users get the user role', function () {
    $this->post('/register', [
        'name' => 'An', 'email' => 'an@example.com', 'password' => 'password123', 'password_confirmation' => 'password123',
    ]);

    expect((int) User::first()->fresh()->role_id)->toBe(Role::USER);
});

test('guests and non admins cannot reach admin pages', function () {
    $this->get('/admin/plans')->assertRedirect('/login');

    foreach ([User::factory()->create(), User::factory()->vip()->create()] as $user) {
        $this->actingAs($user)->get('/admin/plans')->assertForbidden();
        $this->actingAs($user)->get('/admin/users')->assertForbidden();
    }
});

test('admin can create and update a plan', function () {
    $admin = User::factory()->admin()->create();
    $payload = [
        'name' => 'Gold', 'slug' => 'gold', 'price' => 150000, 'duration_days' => 30, 'storage_limit_gb' => 100,
        'max_videos' => 300, 'max_upload_size_mb' => 2000, 'monthly_encode_minutes' => 900, 'embed_domain_limit' => 10,
        'sort' => 3, 'can_use_folders' => 1, 'is_active' => 1,
    ];

    $this->actingAs($admin)->post('/admin/plans', $payload)->assertRedirect('/admin/plans');
    $plan = Plan::where('slug', 'gold')->firstOrFail();
    expect($plan->can_use_folders)->toBeTrue();

    $this->actingAs($admin)->put("/admin/plans/{$plan->id}", [...$payload, 'price' => 120000])->assertRedirect('/admin/plans');
    expect($plan->fresh()->price)->toBe(120000);
});

test('non admin cannot create a plan', function () {
    $this->actingAs(User::factory()->create())->post('/admin/plans', ['name' => 'x'])->assertForbidden();
});

test('admin can change role and grant a plan', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    $pro = Plan::factory()->create(['slug' => 'pro']);

    $this->actingAs($admin)->put("/admin/users/{$user->id}/role", ['role_id' => Role::VIP])->assertRedirect();
    expect($user->fresh()->isVip())->toBeTrue();

    $this->actingAs($admin)->post("/admin/users/{$user->id}/plan", ['plan_id' => $pro->id, 'days' => 7])->assertRedirect();
    expect($user->subscriptions()->current()->count())->toBe(1);
});

test('admin cannot demote themselves', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->put("/admin/users/{$admin->id}/role", ['role_id' => Role::USER])->assertStatus(422);
    expect($admin->fresh()->isAdmin())->toBeTrue();
});

test('pricing and my plan pages render', function () {
    $this->seed(PlanSeeder::class);
    $user = User::factory()->create();

    $this->get('/pricing')->assertOk()->assertSee('Business');
    $this->actingAs($user)->get('/subscription')->assertOk()->assertSee('Free');
});

test('admin dashboard and admin pages render with the admin layout', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('Chào mừng trở lại')->assertSee('Quản lý gói');
    $this->actingAs($admin)->get('/admin/users')->assertOk();
    $this->actingAs($admin)->get('/admin/plans')->assertOk();
    $this->actingAs($admin)->get('/admin/plans/create')->assertOk();
    $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
});
