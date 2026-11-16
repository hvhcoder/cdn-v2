<?php

use App\Jobs\EncodeVideoJob;
use App\Models\Plan;
use App\Models\UploadConfig;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoSegment;
use App\Services\Storage\R2Service;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Plan::factory()->free()->create();
    $this->user = User::factory()->vip()->create();

    $this->r2 = Mockery::mock(R2Service::class)->makePartial();
    $this->r2->shouldReceive('presignUpload')->andReturn('https://r2.test/put');
    app()->instance(R2Service::class, $this->r2);
});

test('presign creates a pending video owned by the user and returns an upload url', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/videos/presign', ['filename' => 'My Movie.mp4', 'size' => 50 * 1024 ** 2])
        ->assertCreated()
        ->assertJsonPath('upload_url', 'https://r2.test/put');

    $video = Video::where('uuid', $response->json('video'))->firstOrFail();
    expect($video->user_id)->toBe($this->user->id)->and($video->status)->toBe(Video::PENDING)->and($video->title)->toBe('My Movie');
});

test('presign is blocked before any resource is used when the plan is exceeded', function () {
    $this->actingAs($this->user)
        ->postJson('/videos/presign', ['filename' => 'big.mp4', 'size' => 500 * 1024 ** 2])
        ->assertStatus(422)
        ->assertJsonPath('limit', 'max_upload_size');

    expect(Video::count())->toBe(0);
});

test('presign requires authentication', function () {
    $this->postJson('/videos/presign', ['filename' => 'a.mp4', 'size' => 10])->assertUnauthorized();
});

test('process queues the encode job once the file exists on r2', function () {
    Queue::fake();
    UploadConfig::factory()->create();
    $video = Video::factory()->for($this->user)->create(['status' => Video::PENDING, 'r2_path' => 'raw/a.mp4', 'size_bytes' => 100]);
    $this->r2->shouldReceive('size')->with('raw/a.mp4')->andReturn(5_000_000);

    $this->actingAs($this->user)->postJson("/videos/{$video->uuid}/process")->assertAccepted();

    Queue::assertPushed(EncodeVideoJob::class);
    expect($video->fresh()->size_bytes)->toBe(5_000_000);
});

test('process rejects a file that is not on r2 yet', function () {
    Queue::fake();
    UploadConfig::factory()->create();
    $video = Video::factory()->for($this->user)->create(['status' => Video::PENDING, 'r2_path' => 'raw/a.mp4']);
    $this->r2->shouldReceive('size')->andReturn(null);

    $this->actingAs($this->user)->postJson("/videos/{$video->uuid}/process")->assertStatus(422);

    Queue::assertNothingPushed();
});

test('process deletes the upload when the real size exceeds the plan', function () {
    Queue::fake();
    UploadConfig::factory()->create();
    $video = Video::factory()->for($this->user)->create(['status' => Video::PENDING, 'r2_path' => 'raw/a.mp4', 'size_bytes' => 100]);
    $this->r2->shouldReceive('size')->andReturn(900 * 1024 ** 2);
    $this->r2->shouldReceive('delete')->once()->with('raw/a.mp4');

    $this->actingAs($this->user)->postJson("/videos/{$video->uuid}/process")->assertStatus(422);

    expect(Video::find($video->id))->toBeNull();
    Queue::assertNothingPushed();
});

test('process refuses when no cdn account is active', function () {
    Queue::fake();
    $video = Video::factory()->for($this->user)->create(['status' => Video::PENDING, 'r2_path' => 'raw/a.mp4']);

    $this->actingAs($this->user)->postJson("/videos/{$video->uuid}/process")->assertStatus(503);
});

test('a user cannot see or process another tenants video', function () {
    $other = User::factory()->create();
    $video = Video::factory()->for($other)->create(['status' => Video::PENDING, 'r2_path' => 'raw/a.mp4']);

    $this->actingAs($this->user)->getJson("/videos/{$video->uuid}/status")->assertForbidden();
    $this->actingAs($this->user)->postJson("/videos/{$video->uuid}/process")->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->getJson("/videos/{$video->uuid}/status")->assertOk();
});

test('status endpoint reports progress and m3u8 url when completed', function () {
    $video = Video::factory()->for($this->user)->create(['status' => Video::COMPLETED, 'segment_count' => 4]);
    VideoSegment::factory()->count(2)->sequence(['index' => 0], ['index' => 1])
        ->create(['video_id' => $video->id, 'status' => 'uploaded', 'cdn_url' => 'https://cdn/x.png']);

    $this->actingAs($this->user)->getJson("/videos/{$video->uuid}/status")
        ->assertOk()
        ->assertJsonPath('progress', 50)
        ->assertJsonPath('m3u8_url', route('m3u8', $video));
});

test('plain users cannot upload or process, only vip and admin can', function () {
    $plain = User::factory()->create();
    $video = Video::factory()->for($plain)->create(['status' => Video::PENDING, 'r2_path' => 'raw/a.mp4']);
    UploadConfig::factory()->create();

    $this->actingAs($plain)->postJson('/videos/presign', ['filename' => 'a.mp4', 'size' => 1000])->assertForbidden();
    $this->actingAs($plain)->postJson("/videos/{$video->uuid}/process")->assertForbidden();
    expect(Video::count())->toBe(1);

    $this->actingAs($this->user)->postJson('/videos/presign', ['filename' => 'a.mp4', 'size' => 1000])->assertCreated();
    $this->actingAs(User::factory()->admin()->create())->postJson('/videos/presign', ['filename' => 'b.mp4', 'size' => 1000])->assertCreated();
});

test('the upload page shows the dropzone only to vip and admin', function () {
    $plain = User::factory()->create();

    $this->actingAs($plain)->get('/videos/create')->assertOk()
        ->assertDontSee('id="dropzone"', false)->assertSee('VIP');

    $this->actingAs($this->user)->get('/videos/create')->assertOk()->assertSee('id="dropzone"', false);
    $this->actingAs(User::factory()->admin()->create())->get('/videos/create')->assertOk()->assertSee('id="dropzone"', false);
});

test('the video list shows only the tenants own videos', function () {
    $plain = User::factory()->create();
    Video::factory()->for($plain)->create(['title' => 'Plain Own Video']);
    Video::factory()->for($this->user)->create(['title' => 'Vip Own Video']);

    $this->actingAs($plain)->get('/videos')->assertOk()->assertSee('Plain Own Video')->assertDontSee('Vip Own Video');
    $this->actingAs($this->user)->get('/videos')->assertOk()->assertSee('Vip Own Video')->assertDontSee('Plain Own Video');
});

test('video page requires login', function () {
    $this->get('/videos')->assertRedirect('/login');
});

test('a user can delete own video but not another tenants', function () {
    $r2 = Mockery::mock(R2Service::class)->makePartial();
    $r2->shouldReceive('delete')->once()->with('raw/a.mp4');
    app()->instance(R2Service::class, $r2);

    $own = Video::factory()->for($this->user)->create(['status' => Video::FAILED, 'r2_path' => 'raw/a.mp4']);
    $other = Video::factory()->create();

    $this->actingAs($this->user)->deleteJson("/videos/{$other->uuid}")->assertForbidden();
    $this->actingAs($this->user)->deleteJson("/videos/{$own->uuid}")->assertOk();

    expect(Video::find($own->id))->toBeNull()->and(Video::find($other->id))->not->toBeNull();
});

test('a video being processed cannot be deleted', function () {
    $video = Video::factory()->for($this->user)->create(['status' => Video::PROCESSING]);

    $this->actingAs($this->user)->deleteJson("/videos/{$video->uuid}")->assertStatus(409);
});

test('the queue connection keeps jobs reserved longer than the longest job timeout', function () {
    expect(config('queue.connections.database.retry_after'))->toBeGreaterThan((new EncodeVideoJob(Video::factory()->make()))->timeout);
});
