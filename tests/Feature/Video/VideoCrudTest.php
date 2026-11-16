<?php

use App\Models\Plan;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoSegment;
use App\Services\Storage\R2Service;

beforeEach(function () {
    Plan::factory()->free()->create();
    $this->user = User::factory()->vip()->create();
});

test('the list is paginated 12 per page and keeps the filters in the page links', function () {
    Video::factory()->count(30)->for($this->user)->sequence(fn ($s) => ['title' => 'Phim '.str_pad($s->index, 2, '0', STR_PAD_LEFT)])->create();

    $first = $this->actingAs($this->user)->get('/videos?sort=title');
    $first->assertOk()->assertSee('Phim 00')->assertSee('Phim 11')->assertDontSee('Phim 12')
        ->assertSee('Hiển thị 1–12 trong 30 video')
        ->assertSee('sort=title', false);

    $this->actingAs($this->user)->get('/videos?sort=title&page=3')->assertOk()
        ->assertSee('Phim 24')->assertSee('Phim 29')->assertDontSee('Phim 23')
        ->assertSee('Hiển thị 25–30 trong 30 video');
});

test('there is no pagination bar when everything fits on one page', function () {
    Video::factory()->count(3)->for($this->user)->create();

    $this->actingAs($this->user)->get('/videos')->assertOk()->assertSee('Hiển thị 1–3 trong 3 video')->assertDontSee('rel="next"', false);
});

test('videos can be searched, filtered by status and sorted', function () {
    Video::factory()->for($this->user)->create(['title' => 'Bán Duyên', 'status' => Video::COMPLETED, 'size_bytes' => 100]);
    Video::factory()->for($this->user)->create(['title' => 'Tình Yêu', 'status' => Video::FAILED, 'size_bytes' => 900]);
    Video::factory()->for($this->user)->create(['title' => 'Mưa Rơi', 'status' => Video::PROCESSING, 'size_bytes' => 500]);

    $this->actingAs($this->user)->get('/videos?q=Duyên')->assertSee('Bán Duyên')->assertDontSee('Tình Yêu')->assertDontSee('Mưa Rơi');
    $this->actingAs($this->user)->get('/videos?status=failed')->assertSee('Tình Yêu')->assertDontSee('Bán Duyên');
    $this->actingAs($this->user)->get('/videos?status=bogus')->assertSee('Bán Duyên')->assertSee('Tình Yêu');
    $this->actingAs($this->user)->get('/videos?q=zzz')->assertSee('Không tìm thấy video phù hợp');

    $html = $this->actingAs($this->user)->get('/videos?sort=size')->getContent();
    expect(strpos($html, 'Tình Yêu'))->toBeLessThan(strpos($html, 'Mưa Rơi'))->and(strpos($html, 'Mưa Rơi'))->toBeLessThan(strpos($html, 'Bán Duyên'));
});

test('the search treats percent and underscore literally', function () {
    Video::factory()->for($this->user)->create(['title' => 'Hello World']);

    $this->actingAs($this->user)->get('/videos?q=%25')->assertSee('Không tìm thấy video phù hợp');
});

test('status tabs show the counts', function () {
    Video::factory()->count(2)->for($this->user)->create(['status' => Video::COMPLETED]);
    Video::factory()->for($this->user)->create(['status' => Video::FAILED]);

    $this->actingAs($this->user)->get('/videos')->assertSee('Hoàn tất')->assertSeeInOrder(['Tất cả', '3', 'Hoàn tất', '2']);
});

test('the detail page shows the player for completed videos and the share links', function () {
    $video = Video::factory()->for($this->user)->create(['status' => Video::COMPLETED, 'title' => 'Bán Duyên', 'duration_seconds' => 268]);
    VideoSegment::factory()->create(['video_id' => $video->id, 'status' => 'uploaded', 'cdn_url' => 'https://cdn/0.png']);

    $this->actingAs($this->user)->get("/videos/{$video->uuid}")->assertOk()
        ->assertSee('Bán Duyên')->assertSee('4:28')
        ->assertSee(route('embed', $video), false)->assertSee(route('m3u8', $video), false)
        ->assertSee('<iframe', false)->assertSee('Sao chép mã nhúng');
});

test('the detail page shows progress while processing and the error when failed', function () {
    $processing = Video::factory()->for($this->user)->create(['status' => Video::PROCESSING, 'segment_count' => 10]);
    $failed = Video::factory()->for($this->user)->create(['status' => Video::FAILED, 'error_message' => 'TikTok từ chối upload']);

    $this->actingAs($this->user)->get("/videos/{$processing->uuid}")->assertOk()->assertSee('Video đang được xử lý')->assertDontSee('<iframe', false);
    $this->actingAs($this->user)->get("/videos/{$failed->uuid}")->assertOk()->assertSee('Xử lý video thất bại')->assertSee('TikTok từ chối upload')->assertSee('Xử lý lại');
});

test('a plain user does not get the retry button', function () {
    $plain = User::factory()->create();
    $failed = Video::factory()->for($plain)->create(['status' => Video::FAILED, 'error_message' => 'x']);

    $this->actingAs($plain)->get("/videos/{$failed->uuid}")->assertOk()->assertDontSee('id="retry"', false);
});

test('other tenants cannot view, edit, update or delete a video', function () {
    $video = Video::factory()->create(['title' => 'Secret']);

    $this->actingAs($this->user)->get("/videos/{$video->uuid}")->assertForbidden();
    $this->actingAs($this->user)->get("/videos/{$video->uuid}/edit")->assertForbidden();
    $this->actingAs($this->user)->put("/videos/{$video->uuid}", ['title' => 'Hacked'])->assertForbidden();
    $this->actingAs($this->user)->delete("/videos/{$video->uuid}")->assertForbidden();

    expect($video->fresh()->title)->toBe('Secret');
});

test('the title can be edited and is validated', function () {
    $video = Video::factory()->for($this->user)->create(['title' => 'Old']);

    $this->actingAs($this->user)->get("/videos/{$video->uuid}/edit")->assertOk()->assertSee('value="Old"', false);

    $this->actingAs($this->user)->put("/videos/{$video->uuid}", ['title' => '  Tên mới  '])
        ->assertRedirect(route('videos.show', $video))->assertSessionHas('status');
    expect($video->fresh()->title)->toBe('Tên mới');

    $this->actingAs($this->user)->put("/videos/{$video->uuid}", ['title' => ''])->assertSessionHasErrors('title');
    $this->actingAs($this->user)->put("/videos/{$video->uuid}", ['title' => str_repeat('a', 256)])->assertSessionHasErrors('title');
    expect($video->fresh()->title)->toBe('Tên mới');
});

test('deleting from the page redirects to the list, while a json request gets json', function () {
    $r2 = Mockery::mock(R2Service::class)->makePartial();
    $r2->shouldReceive('delete')->andReturnNull();
    app()->instance(R2Service::class, $r2);

    $a = Video::factory()->for($this->user)->create(['status' => Video::FAILED, 'r2_path' => 'raw/a.mp4']);
    $b = Video::factory()->for($this->user)->create(['status' => Video::COMPLETED]);

    $this->actingAs($this->user)->delete("/videos/{$a->uuid}")->assertRedirect(route('videos.index'))->assertSessionHas('status');
    $this->actingAs($this->user)->deleteJson("/videos/{$b->uuid}")->assertOk()->assertJson(['deleted' => true]);

    expect(Video::count())->toBe(0);
});

test('a processing video cannot be deleted from the page', function () {
    $video = Video::factory()->for($this->user)->create(['status' => Video::PROCESSING]);

    $this->actingAs($this->user)->from('/videos')->delete("/videos/{$video->uuid}")->assertRedirect('/videos')->assertSessionHasErrors('video');
    expect(Video::find($video->id))->not->toBeNull();
});

test('create and presign routes are not swallowed by the video wildcard route', function () {
    $this->actingAs($this->user)->get('/videos/create')->assertOk()->assertSee('Tải video lên');
});

test('the dashboard shows the plan usage, counts and recent videos', function () {
    Video::factory()->count(2)->for($this->user)->create(['status' => Video::COMPLETED, 'size_bytes' => 100 * 1024 ** 2]);
    Video::factory()->for($this->user)->create(['status' => Video::PROCESSING, 'title' => 'Đang chạy nè']);
    Video::factory()->create(['title' => 'Of someone else']);

    $this->actingAs($this->user)->get('/dashboard')->assertOk()
        ->assertSee('Xin chào')->assertSee('Gói Free')->assertSee('Đang chạy nè')->assertDontSee('Of someone else')
        ->assertSee('Tải video lên')->assertSee('Đang xử lý');
});

test('the dashboard of a plain user points to the pricing page instead of upload', function () {
    $plain = User::factory()->create();

    $this->actingAs($plain)->get('/dashboard')->assertOk()->assertSee('Xem bảng giá')->assertSee('Chưa có video nào');
});

test('the sidebar adapts to the role', function () {
    $this->actingAs($this->user)->get('/dashboard')->assertSee('Tải lên video')->assertSee('VIP')->assertDontSee('Trang quản trị');
    $this->actingAs(User::factory()->admin()->create())->get('/dashboard')->assertSee('Trang quản trị');
});
