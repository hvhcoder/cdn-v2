<?php

use App\Jobs\FinalizeVideoJob;
use App\Models\Video;
use App\Models\VideoSegment;
use App\Services\Storage\R2Service;
use Illuminate\Console\Scheduling\Schedule;

function finishedVideo(string $r2Path = 'raw/a.mp4'): Video
{
    $video = Video::factory()->create(['status' => Video::PROCESSING, 'r2_path' => $r2Path]);
    VideoSegment::factory()->create(['video_id' => $video->id, 'index' => 0, 'status' => 'uploaded', 'cdn_url' => 'https://cdn/0.png']);

    return $video;
}

test('the source file is removed from r2 as soon as the video is completed', function () {
    $r2 = Mockery::mock(R2Service::class)->makePartial();
    $r2->shouldReceive('delete')->once()->with('raw/a.mp4');
    app()->instance(R2Service::class, $r2);

    $video = finishedVideo();
    FinalizeVideoJob::dispatchSync($video);

    expect($video->fresh()->status)->toBe(Video::COMPLETED)->and($video->fresh()->r2_path)->toBeNull();
});

test('when r2 deletion fails the video still completes and the cleanup command removes the leftover later', function () {
    $r2 = Mockery::mock(R2Service::class)->makePartial();
    $r2->shouldReceive('delete')->once()->andThrow(new RuntimeException('r2 down'));
    app()->instance(R2Service::class, $r2);

    $video = finishedVideo();
    FinalizeVideoJob::dispatchSync($video);

    expect($video->fresh()->status)->toBe(Video::COMPLETED)->and($video->fresh()->r2_path)->toBe('raw/a.mp4');

    $healthy = Mockery::mock(R2Service::class)->makePartial();
    $healthy->shouldReceive('delete')->once()->with('raw/a.mp4');
    app()->instance(R2Service::class, $healthy);

    $this->artisan('videos:cleanup-r2')->expectsOutputToContain('Đã dọn 1 file')->assertSuccessful();
    expect($video->fresh()->r2_path)->toBeNull();
});

test('the cleanup command leaves videos that are still processing alone', function () {
    $r2 = Mockery::mock(R2Service::class)->makePartial();
    $r2->shouldNotReceive('delete');
    app()->instance(R2Service::class, $r2);

    $video = Video::factory()->create(['status' => Video::PROCESSING, 'r2_path' => 'raw/b.mp4']);

    $this->artisan('videos:cleanup-r2')->assertSuccessful();
    expect($video->fresh()->r2_path)->toBe('raw/b.mp4');
});

test('the cleanup command is scheduled hourly', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => str_contains($event->command, 'videos:cleanup-r2'));

    expect($events)->toHaveCount(1)->and($events->first()->expression)->toBe('0 * * * *');
});
