<?php

namespace App\Http\Controllers;

use App\Models\Video;
use App\Services\Video\PlaylistBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class M3u8Controller extends Controller
{
    /**
     * Playlist công khai theo UUID. Nếu gói yêu cầu token ký → validate signature.
     */
    public function __invoke(Request $request, Video $video, PlaylistBuilder $builder): Response
    {
        abort_unless($video->status === Video::COMPLETED, 404);

        // Nếu URL có chữ ký (expires, signature) → validate.
        if ($request->has('expires') && $request->has('signature')) {
            abort_unless($request->hasValidSignature(), 403, 'Token hết hạn hoặc không hợp lệ.');
        }

        $headers = [
            'Content-Type' => 'application/vnd.apple.mpegurl',
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control' => 'public, max-age=60',
        ];

        if ($request->boolean('download')) {
            $headers['Content-Disposition'] = 'attachment; filename="'.Str::slug($video->title ?: $video->uuid).'.m3u8"';
        }

        return response($builder->build($video), 200, $headers);
    }
}
