<?php

use App\Models\User;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

function fakeGoogle(array $attributes = [], bool $verified = true): void
{
    Socialite::fake('google', SocialiteUser::fake(array_merge([
        'id' => 'g-123',
        'name' => 'Gia',
        'email' => 'gia@example.com',
        'avatar' => 'https://example.com/a.png',
        'user' => ['email_verified' => $verified],
    ], $attributes)));
}

test('redirects to google', function () {
    Socialite::fake('google');

    $this->get('/auth/google/redirect')->assertRedirect();
});

test('creates a new user from google', function () {
    fakeGoogle();

    $this->get('/auth/google/callback')->assertRedirect('/dashboard');

    $this->assertDatabaseHas('users', ['email' => 'gia@example.com', 'google_id' => 'g-123']);
    expect(User::first()->email_verified_at)->not->toBeNull();
    $this->assertAuthenticated();
});

test('links existing user by verified email', function () {
    $user = User::factory()->create(['email' => 'gia@example.com']);
    fakeGoogle();

    $this->get('/auth/google/callback')->assertRedirect('/dashboard');

    expect($user->fresh()->google_id)->toBe('g-123');
    expect(User::count())->toBe(1);
    $this->assertAuthenticatedAs($user);
});

test('does not link when google email is unverified', function () {
    $user = User::factory()->create(['email' => 'gia@example.com']);
    fakeGoogle(verified: false);

    $this->get('/auth/google/callback')->assertRedirect('/login');

    expect($user->fresh()->google_id)->toBeNull();
    $this->assertGuest();
});

test('logs in returning google user', function () {
    $user = User::factory()->create(['google_id' => 'g-123']);
    fakeGoogle(['email' => 'other@example.com']);

    $this->get('/auth/google/callback')->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
});
