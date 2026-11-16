<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Video;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'stats' => [
                ['label' => 'Người dùng', 'value' => User::count()],
                ['label' => 'Gói trả phí đang hoạt động', 'value' => Subscription::current()->count()],
                ['label' => 'Video', 'value' => Video::count()],
                ['label' => 'Dung lượng đã dùng', 'value' => $this->formatBytes((int) Video::sum('size_bytes'))],
            ],
            'latestUsers' => User::with('role')->latest()->limit(5)->get(),
        ]);
    }

    private function formatBytes(int $bytes): string
    {
        return $bytes >= 1024 ** 3
            ? round($bytes / 1024 ** 3, 2).' GB'
            : round($bytes / 1024 ** 2, 1).' MB';
    }
}
