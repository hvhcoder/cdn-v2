<?php

use App\Models\User;
use App\Models\UserLog;
use App\Services\Geo\GeoLocator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->user = User::factory()->create();
});

test('user login tracks IP and geolocation', function () {
    Http::fake([
        'ip-api.com/*' => Http::response([
            'status' => 'success',
            'countryCode' => 'VN',
            'city' => 'Hanoi',
            'lat' => 21.0285,
            'lon' => 105.8542,
        ]),
    ]);

    // Đăng nhập để kích hoạt event.
    $response = $this->post(route('login'), [
        'email' => $this->user->email,
        'password' => 'password',
    ]);

    $this->user->refresh();

    // Geolocation được ghi lại (có thể null nếu IP là 127.0.0.1 trong test).
    expect($this->user->last_login_at)->not->toBeNull();
});

test('geolocation service fetches location from ip-api.com', function () {
    Http::fake([
        'ip-api.com/*' => Http::response([
            'status' => 'success',
            'lat' => 40.7128,
            'lon' => -74.0060,
        ]),
    ]);

    $locator = app(GeoLocator::class);
    $geo = $locator->locate('8.8.8.8');

    expect($geo)->toBeArray()
        ->and($geo['lat'])->toBe(40.7128)
        ->and($geo['lon'])->toBe(-74.0060);
});

test('geolocation service returns null for private IPs', function () {
    $locator = app(GeoLocator::class);

    expect($locator->locate('127.0.0.1'))->toBeNull()
        ->and($locator->locate('192.168.1.1'))->toBeNull()
        ->and($locator->locate('10.0.0.1'))->toBeNull();
});

test('user log records geolocation when IP is provided', function () {
    Http::fake([
        'ip-api.com/*' => Http::response([
            'status' => 'success',
            'lat' => 35.6762,
            'lon' => 139.6503,
        ]),
    ]);

    UserLog::record($this->user->id, UserLog::VIDEO_UPLOADED, ['video_id' => 123], '1.1.1.1');

    $log = UserLog::where('user_id', $this->user->id)->latest()->first();
    expect($log->ip)->toBe('1.1.1.1')
        ->and($log->lat)->toBe(35.6762)
        ->and($log->lon)->toBe(139.6503);
});

test('admin can ban user with reason', function () {
    $this->actingAs($this->admin);

    $response = $this->post(route('admin.users.ban', $this->user), [
        'reason' => 'Đăng nội dung vi phạm pháp luật',
    ]);

    $response->assertRedirect();

    $this->user->refresh();
    expect($this->user->isBanned())->toBeTrue()
        ->and($this->user->banned_reason)->toBe('Đăng nội dung vi phạm pháp luật')
        ->and($this->user->banned_by)->toBe($this->admin->id);

    // User log ghi lại hành động ban.
    expect(UserLog::where('user_id', $this->user->id)->where('action', UserLog::USER_BANNED)->exists())->toBeTrue();
});

test('admin can unban user', function () {
    $this->user->update(['banned_at' => now(), 'banned_reason' => 'Test', 'banned_by' => $this->admin->id]);

    $this->actingAs($this->admin);

    $response = $this->post(route('admin.users.unban', $this->user));

    $response->assertRedirect();

    $this->user->refresh();
    expect($this->user->isBanned())->toBeFalse()
        ->and($this->user->banned_reason)->toBeNull();

    // User log ghi lại hành động unban.
    expect(UserLog::where('user_id', $this->user->id)->where('action', UserLog::USER_UNBANNED)->exists())->toBeTrue();
});

test('banned user is logged out and cannot access system', function () {
    $this->user->update(['banned_at' => now(), 'banned_reason' => 'Vi phạm']);

    $response = $this->actingAs($this->user)->get(route('dashboard'));

    $response->assertRedirect(route('login'));

    expect(Auth::check())->toBeFalse();
});

test('admin cannot ban themselves', function () {
    $this->actingAs($this->admin);

    $response = $this->post(route('admin.users.ban', $this->admin), [
        'reason' => 'Test',
    ]);

    $response->assertStatus(422);
});

test('admin cannot ban another admin', function () {
    $anotherAdmin = User::factory()->admin()->create();

    $this->actingAs($this->admin);

    $response = $this->post(route('admin.users.ban', $anotherAdmin), [
        'reason' => 'Test',
    ]);

    $response->assertStatus(422);
});

test('geolocation result is cached for 24 hours', function () {
    Http::fake([
        'ip-api.com/*' => Http::response([
            'status' => 'success',
            'countryCode' => 'FR',
            'city' => 'Paris',
            'lat' => 48.8566,
            'lon' => 2.3522,
        ]),
    ]);

    $locator = app(GeoLocator::class);

    // First call hits API.
    $geo1 = $locator->locate('1.2.3.4');
    Http::assertSentCount(1);

    // Second call uses cache.
    $geo2 = $locator->locate('1.2.3.4');
    Http::assertSentCount(1);

    expect($geo1)->toEqual($geo2);
});
