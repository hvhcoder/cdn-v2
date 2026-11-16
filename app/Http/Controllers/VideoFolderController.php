<?php

namespace App\Http\Controllers;

use App\Services\Video\FolderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VideoFolderController extends Controller
{
    /**
     * Chuyển một hoặc nhiều video vào thư mục (folder_id trống = đưa ra ngoài thư mục).
     */
    public function __invoke(Request $request, FolderService $folders): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'video_ids' => ['required', 'array', 'min:1', 'max:200'],
            'video_ids.*' => ['string'],
            'folder_id' => ['nullable', 'integer'],
        ]);

        $moved = $folders->moveVideos($request->user(), $data['video_ids'], $data['folder_id'] ?? null);

        return $request->expectsJson()
            ? response()->json(['moved' => $moved])
            : back()->with('status', "Đã chuyển {$moved} video.");
    }
}
