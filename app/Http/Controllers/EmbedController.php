<?php

namespace App\Http\Controllers;

use App\Models\Video;
use App\Services\Video\EmbedGuard;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EmbedController extends Controller
{
    /**
     * Trang player nhúng bằng iframe.
     * Kiểm tra: video hoàn tất, embed không bị khóa, domain hợp lệ.
     */
    public function __invoke(Request $request, Video $video, EmbedGuard $guard): Response
    {
        if ($video->status !== Video::COMPLETED) {
            return response()->view('embed', ['video' => null, 'message' => 'Video chưa sẵn sàng hoặc không tồn tại.'], 404);
        }

        $origin = $request->header('Origin') ?? $request->header('Referer');
        $owner = $video->user;
        $reason = $guard->check($video, $origin, $owner);

        if ($reason !== null) {
            $messages = [
                'embed_locked' => 'Video này đã bị khóa nhúng.',
                'subscription_expired' => 'Chủ video đã hết hạn gói, không thể phát.',
                'domain_not_allowed' => 'Tên miền này không được phép nhúng video.',
            ];

            return response()->view('embed', [
                'video' => null,
                'message' => $messages[$reason] ?? 'Không thể phát video này.',
            ], 403);
        }

        return response()->view('embed', ['video' => $video])->header('X-Robots-Tag', 'noindex');
    }
}
