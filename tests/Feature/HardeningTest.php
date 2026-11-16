<?php

use App\Models\AbuseReport;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\UploadConfig;
use App\Models\User;
use App\Models\UserLog;
use App\Models\Video;
use App\Models\VideoSegment;
use App\Services\Video\DeadUrlDetector;
use App\Services\Video\EmbedGuard;
use App\Services\Video\ExpiryEnforcer;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    Plan::factory()->create(['slug' => 'free', 'embed_locked_when_expired' => true, 'expiry_lock_days' => 0, 'expiry_delete_days' => 30]);
    $this->user = User::factory()->vip()->create();
});

test('embed controller checks domain lock', function () {
    $plan = Plan::factory()->create(['embed_domain_limit' => 3]);
    Subscription::factory()->for($this->user)->for($plan)->create();
    $video = Video::factory()->for($this->user)->create(['status' => Video::COMPLETED, 'allowed_domains' => ['example.com']]);

    // Không có Referer → cho phép.
    $this->get(route('embed', $video))->assertOk()->assertSee('data-src');

    // Referer hợp lệ.
    $this->get(route('embed', $video), ['Referer' => 'https://example.com/page'])->assertOk();

    // Referer không hợp lệ.
    $this->get(route('embed', $video), ['Referer' => 'https://evil.com'])->assertForbidden()->assertSee('không được phép');
});

test('embed is locked when video has embed_locked_at', function () {
    $video = Video::factory()->for($this->user)->create(['status' => Video::COMPLETED, 'embed_locked_at' => now()]);

    $this->get(route('embed', $video))->assertForbidden()->assertSee('bị khóa');
});

test('m3u8 validates signed token when present', function () {
    $video = Video::factory()->for($this->user)->create(['status' => Video::COMPLETED]);

    // Không có token → cho phép.
    $this->get(route('m3u8', $video))->assertOk()->assertHeader('Content-Type', 'application/vnd.apple.mpegurl');

    // Token hợp lệ.
    $signed = URL::temporarySignedRoute('m3u8', now()->addMinutes(10), $video);
    $this->get($signed)->assertOk();

    // Token hết hạn.
    $expired = URL::temporarySignedRoute('m3u8', now()->subMinutes(10), $video);
    $this->get($expired)->assertForbidden();
});

test('dead URL detector marks segment as pending when CDN returns 404', function () {
    Http::fake(['https://dead.cdn/*' => Http::response('', 404), 'https://alive.cdn/*' => Http::response('', 200)]);

    $video = Video::factory()->for($this->user)->create(['status' => Video::COMPLETED]);
    VideoSegment::factory()->for($video)->create(['index' => 0, 'status' => VideoSegment::UPLOADED, 'cdn_url' => 'https://dead.cdn/segment1.png']);
    VideoSegment::factory()->for($video)->create(['index' => 1, 'status' => VideoSegment::UPLOADED, 'cdn_url' => 'https://alive.cdn/segment2.png']);

    $detector = app(DeadUrlDetector::class);
    $deadCount = $detector->check($video);

    expect($deadCount)->toBe(1);
    expect($video->fresh()->dead_segments)->toBe(1);
    expect($video->segments()->where('status', VideoSegment::PENDING)->count())->toBe(1);
});

test('expiry enforcer locks embed and deletes videos after grace period', function () {
    $plan = Plan::factory()->create(['slug' => 'pro', 'embed_locked_when_expired' => true, 'expiry_lock_days' => 7, 'expiry_delete_days' => 30]);
    $user = User::factory()->create();
    $video = Video::factory()->for($user)->create(['status' => Video::COMPLETED]);

    // Gói hết hạn 8 ngày trước (quá grace_lock 7 ngày).
    Subscription::factory()->for($user)->for($plan)->create(['status' => Subscription::EXPIRED, 'ends_at' => now()->subDays(8)]);

    $enforcer = app(ExpiryEnforcer::class);
    $result = $enforcer->enforce();

    expect($result['locked'])->toBe(1);
    expect($video->fresh()->embed_locked_at)->not->toBeNull();

    // Video chưa bị xóa vì chưa đủ 30 ngày.
    expect(Video::find($video->id))->not->toBeNull();

    // Giả lập hết hạn 31 ngày trước → xóa video.
    $user->subscriptions()->update(['ends_at' => now()->subDays(31)]);
    $result = $enforcer->enforce();

    expect($result['deleted'])->toBe(1);
    expect(Video::find($video->id))->toBeNull();
});

test('abuse reports can be submitted without authentication and rate limited', function () {
    $video = Video::factory()->for($this->user)->create(['status' => Video::COMPLETED]);

    $this->postJson(route('embed.report', $video), ['reason' => 'spam', 'details' => 'This is spam'])->assertCreated();

    expect(AbuseReport::where('video_id', $video->id)->count())->toBe(1);

    // Rate limit: 5 reports/min.
    for ($i = 0; $i < 5; $i++) {
        $this->postJson(route('embed.report', $video), ['reason' => 'spam']);
    }

    $this->postJson(route('embed.report', $video), ['reason' => 'spam'])->assertStatus(429);
});

test('admin can resolve abuse reports', function () {
    $admin = User::factory()->admin()->create();
    $video = Video::factory()->for($this->user)->create();
    $report = AbuseReport::factory()->for($video)->create(['status' => AbuseReport::PENDING]);

    $this->actingAs($admin)->put(route('admin.abuse-reports.update', $report), ['status' => 'resolved'])->assertRedirect();

    expect($report->fresh()->status)->toBe('resolved')->and($report->fresh()->resolved_by)->toBe($admin->id);
});

test('user logs record important actions', function () {
    UserLog::record($this->user->id, UserLog::VIDEO_UPLOADED, ['video' => 'abc123'], '127.0.0.1');

    expect(UserLog::where('user_id', $this->user->id)->where('action', UserLog::VIDEO_UPLOADED)->count())->toBe(1);
});

test('embed and m3u8 routes are rate limited per IP', function () {
    $video = Video::factory()->for($this->user)->create(['status' => Video::COMPLETED]);

    // Rate limit: 120/phút.
    for ($i = 0; $i < 120; $i++) {
        $this->get(route('embed', $video))->assertSuccessful();
    }

    $this->get(route('embed', $video))->assertStatus(429);
});

test('tiktok health check command shows account stats', function () {
    UploadConfig::factory()->create(['platform' => UploadConfig::TIKTOK, 'name' => 'Account 1', 'uploads_count' => 100, 'uploaded_bytes' => 1024 ** 3]);

    Artisan::call('tiktok:health-check');
    $output = Artisan::output();

    expect($output)->toContain('Account 1')->toContain('1');
});

test('videos check-dead-urls command checks stale videos', function () {
    Http::fake(['*' => Http::response('', 404)]);

    $video = Video::factory()->for($this->user)->create(['status' => Video::COMPLETED, 'dead_checked_at' => now()->subDays(2)]);
    VideoSegment::factory()->for($video)->create(['status' => VideoSegment::UPLOADED, 'cdn_url' => 'https://cdn.test/segment.png']);

    Artisan::call('videos:check-dead-urls', ['--days' => 1, '--limit' => 10]);

    expect($video->fresh()->dead_segments)->toBe(1);
});

test('enforce expiry command runs without error', function () {
    $exitCode = Artisan::call('videos:enforce-expiry');

    expect($exitCode)->toBe(0);
});

test('embed guard checks expiry and locks embed automatically', function () {
    $user = User::factory()->create();
    $expiredPlan = Plan::factory()->create();

    // Video được tạo khi gói còn hiệu lực.
    $video = Video::factory()->for($user)->create(['status' => Video::COMPLETED, 'created_at' => now()->subDays(10)]);

    // Gói hết hạn 1 ngày trước.
    Subscription::factory()->for($user)->for($expiredPlan)->create([
        'status' => Subscription::EXPIRED,
        'ends_at' => now()->subDay(),
    ]);

    $guard = app(EmbedGuard::class);
    $reason = $guard->check($video, null, $user);

    expect($reason)->toBe('subscription_expired');
    expect($video->fresh()->embed_locked_at)->not->toBeNull();
});

test('signed URL generation respects plan token TTL', function () {
    $plan = Plan::factory()->create(['embed_token_ttl_minutes' => 60]);
    $video = Video::factory()->for($this->user)->create();

    $guard = app(EmbedGuard::class);
    $url = $guard->signedUrl('m3u8', $video, $plan);

    expect($url)->toContain('expires')->toContain('signature');
});
