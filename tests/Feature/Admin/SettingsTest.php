<?php

use App\Models\Plan;
use App\Models\Setting;
use App\Models\User;
use App\Services\RuntimeSettings;
use App\Services\Video\UploadLimits;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobProcessing;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

function queuedJob(): Job
{
    $job = Mockery::mock(Job::class)->shouldIgnoreMissing();
    $job->shouldReceive('payload')->andReturn([]);

    return $job;
}

function payload(array $overrides = []): array
{
    return ['settings' => $overrides];
}

test('only admins can open or change the system settings', function () {
    $this->get('/admin/settings')->assertRedirect('/login');

    $user = User::factory()->vip()->create();
    $this->actingAs($user)->get('/admin/settings')->assertForbidden();
    $this->actingAs($user)->put('/admin/settings', payload(['tiktok__concurrency' => 2]))->assertForbidden();
    $this->actingAs($user)->delete('/admin/settings')->assertForbidden();

    expect(Setting::count())->toBe(0);
});

test('the page lists every setting with its .env default', function () {
    $this->actingAs($this->admin)->get('/admin/settings')->assertOk()
        ->assertSee('Số ảnh đẩy lên TikTok cùng lúc')
        ->assertSee('Mỗi đoạn video dài')
        ->assertSee('Dung lượng tối đa mỗi video')
        ->assertSee('Mặc định: 6', false)
        ->assertSee('Mặc định: 15', false);

    expect(array_keys(RuntimeSettings::fields()))->toHaveCount(13);
});

test('saved values override the .env defaults immediately', function () {
    $this->actingAs($this->admin)->put('/admin/settings', payload([
        'tiktok__concurrency' => '3',
        'tiktok__rotation' => 'round_robin',
        'hls__segment_time' => '8',
        'limits__max_queue_gb' => '20.5',
        'upload__rest__after_min' => '10',
        'upload__rest__after_max' => '12',
    ]))->assertRedirect()->assertSessionHas('status');

    expect(config('cdnpro.tiktok.concurrency'))->toBe(3)
        ->and(config('cdnpro.tiktok.rotation'))->toBe('round_robin')
        ->and(config('cdnpro.hls.segment_time'))->toBe(8)
        ->and(config('cdnpro.limits.max_queue_gb'))->toBe(20.5)
        ->and(config('cdnpro.upload.rest.after_min'))->toBe(10);

    expect(Setting::count())->toBe(6);
    expect(app(UploadLimits::class)->segmentTime())->toBe(8);
});

test('a blank field removes the override and restores the .env default', function () {
    $this->actingAs($this->admin)->put('/admin/settings', payload(['tiktok__concurrency' => '3']));
    expect(config('cdnpro.tiktok.concurrency'))->toBe(3);

    $this->actingAs($this->admin)->put('/admin/settings', payload(['tiktok__concurrency' => '']));

    expect(config('cdnpro.tiktok.concurrency'))->toBe(6)->and(Setting::count())->toBe(0);
});

test('reset restores every default at once', function () {
    $this->actingAs($this->admin)->put('/admin/settings', payload(['tiktok__concurrency' => '2', 'upload__max_retries' => '1']));

    $this->actingAs($this->admin)->delete('/admin/settings')->assertRedirect();

    expect(config('cdnpro.tiktok.concurrency'))->toBe(6)->and(config('cdnpro.upload.max_retries'))->toBe(5)->and(Setting::count())->toBe(0);
});

test('out of range values and unknown options are rejected', function () {
    $this->actingAs($this->admin)->put('/admin/settings', payload([
        'tiktok__concurrency' => '0',
        'hls__segment_time' => '61',
        'limits__max_video_gb' => '9',
        'tiktok__rotation' => 'fastest',
        'upload__max_retries' => 'abc',
    ]))->assertSessionHasErrors([
        'settings.tiktok__concurrency', 'settings.hls__segment_time', 'settings.limits__max_video_gb',
        'settings.tiktok__rotation', 'settings.upload__max_retries',
    ]);

    expect(Setting::count())->toBe(0)->and(config('cdnpro.tiktok.concurrency'))->toBe(6);
});

test('a maximum lower than its minimum is rejected', function () {
    $this->actingAs($this->admin)->put('/admin/settings', payload(['upload__rest__after_min' => '40', 'upload__rest__after_max' => '35']))
        ->assertSessionHasErrors('settings.upload__rest__after_max');

    $this->actingAs($this->admin)->put('/admin/settings', payload(['upload__rest__seconds_min' => '9']))
        ->assertSessionHasErrors('settings.upload__rest__seconds_max');

    expect(Setting::count())->toBe(0);
});

test('a running worker picks up changes before each job without a restart', function () {
    $fire = fn () => event(new JobProcessing('database', queuedJob()));

    Setting::create(['key' => 'tiktok.concurrency', 'value' => '2']);
    $fire();
    expect(config('cdnpro.tiktok.concurrency'))->toBe(2);

    Setting::where('key', 'tiktok.concurrency')->update(['value' => '4']);
    $fire();
    expect(config('cdnpro.tiktok.concurrency'))->toBe(4);

    Setting::where('key', 'tiktok.concurrency')->delete();
    $fire();
    expect(config('cdnpro.tiktok.concurrency'))->toBe(6);
});

test('applying settings does not touch config that was never overridden', function () {
    config(['cdnpro.upload.max_retries' => 2]);

    app(RuntimeSettings::class)->apply();
    event(new JobProcessing('database', queuedJob()));

    expect(config('cdnpro.upload.max_retries'))->toBe(2);
});

test('the video page and pipeline use the admin override', function () {
    Plan::factory()->free()->create();
    $this->actingAs($this->admin)->put('/admin/settings', payload(['hls__segment_time' => '10', 'limits__max_queue_videos' => '7']));

    $this->actingAs($this->admin)->get('/videos/create')->assertOk()->assertSee('các đoạn 10 giây')->assertSee('/ 7');
});
