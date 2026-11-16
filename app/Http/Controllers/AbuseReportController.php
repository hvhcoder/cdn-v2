<?php

namespace App\Http\Controllers;

use App\Models\AbuseReport;
use App\Models\Video;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AbuseReportController extends Controller
{
    /**
     * Báo cáo nội dung vi phạm (public, không cần đăng nhập). Rate limit theo IP.
     */
    public function store(Request $request, Video $video): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', Rule::in(array_keys(AbuseReport::REASONS))],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);

        AbuseReport::create([
            'video_id' => $video->id,
            'reason' => $data['reason'],
            'details' => $data['details'] ?? null,
            'reporter_ip' => $request->ip(),
            'status' => AbuseReport::PENDING,
        ]);

        return response()->json(['message' => 'Đã gửi báo cáo. Cảm ơn bạn đã giúp chúng tôi giữ nền tảng an toàn.'], 201);
    }
}
