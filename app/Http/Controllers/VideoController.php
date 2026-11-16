<?php

namespace App\Http\Controllers;

use App\Exceptions\QuotaExceededException;
use App\Jobs\EncodeVideoJob;
use App\Jobs\UploadSegmentsJob;
use App\Models\Video;
use App\Models\VideoSegment;
use App\Services\Billing\QuotaService;
use App\Services\Cdn\AccountPool;
use App\Services\Storage\R2Service;
use App\Services\Video\FolderService;
use App\Services\Video\UploadLimits;
use App\Services\Video\VideoDeleter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class VideoController extends Controller
{
    private const PER_PAGE = 12;

    /**
     * Danh sách video của tôi: tìm kiếm, lọc theo trạng thái, sắp xếp và phân trang.
     */
    public function index(Request $request, FolderService $folders, QuotaService $quota): View
    {
        $user = $request->user();
        $status = $request->query('status');
        $sort = $request->query('sort', 'newest');

        $folderParam = $request->query('folder');
        $currentFolder = is_numeric($folderParam) ? $user->folders()->find((int) $folderParam) : null;
        $unfiledOnly = $folderParam === 'none';

        $videos = $user->videos()
            ->with('folder')
            ->withCount(['segments as uploaded_segments_count' => fn ($q) => $q->where('status', VideoSegment::UPLOADED)])
            ->when($currentFolder, fn ($q) => $q->where('folder_id', $currentFolder->id))
            ->when($unfiledOnly, fn ($q) => $q->whereNull('folder_id'))
            ->when($request->query('q'), fn ($q, $search) => $q->where('title', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->when(in_array($status, [Video::PENDING, Video::PROCESSING, Video::COMPLETED, Video::FAILED], true), fn ($q) => $q->where('status', $status))
            ->when(true, fn ($q) => match ($sort) {
                'oldest' => $q->oldest(),
                'title' => $q->orderBy('title'),
                'size' => $q->orderByDesc('size_bytes'),
                default => $q->latest(),
            })
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $tree = $folders->flatTree($user);

        return view('videos.index', [
            'videos' => $videos,
            'counts' => $user->videos()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'canUpload' => Gate::allows('upload', Video::class),
            'filters' => ['q' => (string) $request->query('q'), 'status' => $status, 'sort' => $sort, 'folder' => $currentFolder?->id ?? ($unfiledOnly ? 'none' : null)],
            'tree' => $tree,
            'currentFolder' => $currentFolder,
            'breadcrumb' => $currentFolder?->breadcrumb() ?? collect(),
            'subfolders' => $tree->filter(fn (array $row) => ($row['folder']->parent_id === $currentFolder?->id) && ! $unfiledOnly)->values(),
            'unfiledCount' => $user->videos()->whereNull('folder_id')->count(),
            'canManageFolders' => $quota->canUseFolders($user),
        ]);
    }

    /**
     * Trang upload (hàng đợi tải lên). Người dùng thường chỉ thấy thông báo cần nâng cấp.
     */
    public function create(Request $request, QuotaService $quota, UploadLimits $limits, FolderService $folders): View
    {
        $user = $request->user();

        return view('videos.create', [
            'tree' => $folders->flatTree($user),
            'canManageFolders' => $quota->canUseFolders($user),
            'defaultFolder' => $user->folders()->find((int) $request->query('folder')),
            'canUpload' => Gate::allows('upload', Video::class),
            'plan' => $quota->planFor($user),
            'usage' => $quota->usage($user),
            'limits' => $limits->summary(),
            'planMaxUploadBytes' => $user->isAdmin() ? null : $quota->planFor($user)->maxUploadSizeBytes(),
        ]);
    }

    public function show(Request $request, Video $video, FolderService $folders, QuotaService $quota): View
    {
        Gate::authorize('view', $video);

        return view('videos.show', [
            'video' => $video->load('folder'),
            'breadcrumb' => $video->folder?->breadcrumb() ?? collect(),
            'tree' => $video->user_id === $request->user()->id ? $folders->flatTree($request->user()) : collect(),
            'canManageFolders' => $quota->canUseFolders($request->user()),
            'payload' => $video->statusPayload(),
            'canUpload' => Gate::allows('process', $video),
        ]);
    }

    public function edit(Video $video): View
    {
        Gate::authorize('update', $video);

        return view('videos.edit', ['video' => $video]);
    }

    public function update(Request $request, Video $video): RedirectResponse
    {
        Gate::authorize('update', $video);

        $data = $request->validate(['title' => ['required', 'string', 'max:255']]);

        $video->update(['title' => trim($data['title'])]);

        return redirect()->route('videos.show', $video)->with('status', 'Đã cập nhật tiêu đề video.');
    }

    /**
     * Bước 1: kiểm quota, tạo video pending và cấp presigned URL để browser upload thẳng lên R2.
     */
    public function presign(Request $request, QuotaService $quota, UploadLimits $limits, R2Service $r2, FolderService $folders): JsonResponse
    {
        Gate::authorize('upload', Video::class);

        $data = $request->validate([
            'filename' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1'],
            'folder_id' => ['nullable', 'integer'],
            'encoding_method' => ['nullable', 'string', 'in:pixel,header-graft'],
        ]);

        $folderId = $data['folder_id'] ?? null;
        if ($folderId !== null) {
            $folders->assertCanManage($request->user());
            $request->user()->folders()->findOr($folderId, fn () => throw ValidationException::withMessages(['folder_id' => 'Thư mục đích không hợp lệ.']));
        }

        try {
            $quota->assertCanUpload($request->user(), $data['size']);
            $limits->assertCanEnqueue($request->user(), $data['size']);
        } catch (QuotaExceededException $e) {
            return $this->limitResponse($e);
        }

        $key = $r2->newObjectKey($data['filename']);

        $video = $request->user()->videos()->create([
            'folder_id' => $folderId,
            'title' => pathinfo($data['filename'], PATHINFO_FILENAME),
            'r2_path' => $key,
            'size_bytes' => $data['size'],
            'segment_time' => $limits->segmentTime(),
            'encoding_method' => $data['encoding_method'] ?? Video::ENCODING_PIXEL,
        ]);

        return response()->json([
            'video' => $video->uuid,
            'upload_url' => $r2->presignUpload($key),
        ], 201);
    }

    /**
     * Bước 2: sau khi browser upload xong, xác nhận dung lượng thật rồi đẩy vào hàng đợi.
     */
    public function process(Video $video, QuotaService $quota, UploadLimits $limits, R2Service $r2, AccountPool $pool): JsonResponse
    {
        Gate::authorize('process', $video);

        if (! in_array($video->status, [Video::PENDING, Video::FAILED], true)) {
            return response()->json(['message' => 'Video đang được xử lý hoặc đã hoàn tất.'], 409);
        }

        if (! $pool->hasActive()) {
            return response()->json(['message' => 'Hệ thống chưa có tài khoản CDN hoạt động, vui lòng thử lại sau.'], 503);
        }

        if ($video->r2_path !== null && $video->segments()->doesntExist()) {
            $size = $r2->size($video->r2_path);
            if ($size === null) {
                return response()->json(['message' => 'Chưa thấy file trên R2, hãy upload xong rồi thử lại.'], 422);
            }

            $video->update(['size_bytes' => $size]);

            try {
                $quota->assertStoredVideoAllowed($video->user, $size);
                $limits->assertCanEnqueue($video->user, $size, $video);
            } catch (QuotaExceededException $e) {
                if (! str_starts_with($e->limit, 'queue')) {
                    $r2->delete($video->r2_path);
                    $video->delete();
                }

                return $this->limitResponse($e);
            }
        }

        $video->update(['status' => Video::PROCESSING, 'error_message' => null]);

        $canResume = $video->segments()->where('status', VideoSegment::PENDING)->exists() && is_dir($video->tempDirectory());
        $canResume ? UploadSegmentsJob::dispatch($video) : EncodeVideoJob::dispatch($video);

        return response()->json(['video' => $video->uuid, 'status' => $video->status], 202);
    }

    /**
     * 422: vượt giới hạn cố định (gói, dung lượng/video). 429: hàng đợi đang đầy, thử lại sau.
     */
    private function limitResponse(QuotaExceededException $e): JsonResponse
    {
        return response()->json(['message' => $e->getMessage(), 'limit' => $e->limit], str_starts_with($e->limit, 'queue') ? 429 : 422);
    }

    /**
     * Trạng thái xử lý dạng JSON cho các trang tự cập nhật (polling).
     */
    public function status(Video $video): JsonResponse
    {
        Gate::authorize('view', $video);

        return response()->json($video->statusPayload());
    }

    public function destroy(Request $request, Video $video, VideoDeleter $deleter): JsonResponse|RedirectResponse
    {
        Gate::authorize('delete', $video);

        if ($video->status === Video::PROCESSING) {
            $message = 'Video đang được xử lý, hãy đợi xong rồi xóa.';

            return $request->expectsJson()
                ? response()->json(['message' => $message], 409)
                : back()->withErrors(['video' => $message]);
        }

        $deleter->delete($video);

        return $request->expectsJson()
            ? response()->json(['deleted' => true])
            : redirect()->route('videos.index')->with('status', 'Đã xóa video.');
    }
}
