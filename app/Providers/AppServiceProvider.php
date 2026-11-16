<?php

namespace App\Providers;

use App\Listeners\TrackUserLogin;
use App\Models\UploadConfig;
use App\Services\Cdn\CdnUploader;
use App\Services\Cdn\TikTokUploader;
use App\Services\RuntimeSettings;
use App\Services\Storage\R2Service;
use App\Services\Video\FfmpegService;
use App\Services\Video\PixelPngCodec;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(RuntimeSettings::class);
        $this->app->bind(CdnUploader::class, TikTokUploader::class);
        $this->app->bind(FfmpegService::class, fn () => FfmpegService::fromConfig());
        $this->app->bind(PixelPngCodec::class, fn () => new PixelPngCodec(config('cdnpro.png.width')));
        $this->app->bind(R2Service::class, fn () => new R2Service(UploadConfig::defaultFor(UploadConfig::R2)?->toR2Config()));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Thời gian tương đối (diffForHumans) hiển thị tiếng Việt.
        Carbon::setLocale('vi');

        $this->applyRuntimeSettings();

        // Worker chạy lâu dài: đọc lại cài đặt admin trước mỗi job để đổi giá trị mà không cần restart.
        Queue::before(fn () => $this->applyRuntimeSettings());

        // Theo dõi IP và tọa độ mỗi lần đăng nhập.
        Event::listen(
            Login::class,
            TrackUserLogin::class,
        );
    }

    private function applyRuntimeSettings(): void
    {
        try {
            $this->app->make(RuntimeSettings::class)->apply();
        } catch (Throwable) {
            // Bảng settings chưa tồn tại (chưa migrate) hoặc DB chưa sẵn sàng: dùng mặc định trong .env.
        }
    }
}
