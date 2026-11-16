<?php

namespace App\Listeners;

use App\Services\Geo\GeoLocator;
use Illuminate\Auth\Events\Login;

/**
 * Lưu IP và tọa độ người dùng mỗi lần đăng nhập.
 * Dùng để tuân thủ pháp luật, có cơ sở báo cáo nếu phát hiện vi phạm.
 */
class TrackUserLogin
{
    public function __construct(private GeoLocator $geo) {}

    public function handle(Login $event): void
    {
        $user = $event->user;
        $ip = request()->ip();

        $geo = $this->geo->locate($ip);

        $user->update([
            'last_login_ip' => $ip,
            'last_login_lat' => $geo['lat'] ?? null,
            'last_login_lon' => $geo['lon'] ?? null,
            'last_login_at' => now(),
        ]);
    }
}
